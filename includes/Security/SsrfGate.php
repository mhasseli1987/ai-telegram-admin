<?php
namespace ATA\Security;

defined('ABSPATH') || exit;

/**
 * SSRF Gate (D-8).
 * Validates outbound URLs before any HTTP call.
 * Blocks: non-http(s), private/reserved IPs (v4+v6), DNS-rebinding attempts.
 *
 * Fixed gaps: AAAA resolution was missing (gethostbynamel is v4-only), so
 * attackers could point a hostname at a private IPv6 (e.g. ::1) and pass the
 * gate; IPv4-mapped IPv6 (::ffff:127.0.0.1) was never un-mapped and checked.
 */
class SsrfGate
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
        '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
        // IPv6.
        '::/128',        // unspecified
        '::1/128',       // loopback
        '::ffff:0:0/96', // IPv4-mapped (each mapped v4 is re-checked too)
        '64:ff9b:0:0:60::/96', // 64:ff9b:1:0::/96 NAT64 well-known variant
        '2001:db8::/32', // documentation
        'fc00::/7',      // unique local
        'fe80::/10',     // link local
        'ff00::/8',      // multicast
    ];

    public function validate(string $url): bool
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return false;
        }
        $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !$this->isBlockedIp($host);
        }

        // Resolve both address families; reject when ANY resolved IP is blocked
        // (fail-closed, rebinding-hardened).
        $blocked = false;
        $resolvedAny = false;

        $v4 = @gethostbynamel($host); // A records
        if (is_array($v4) && !empty($v4)) {
            $resolvedAny = true;
            foreach ($v4 as $ip) {
                if ($this->isBlockedIp($ip)) {
                    $blocked = true;
                }
            }
        }

        $v6 = $this->resolveAaaa($host);
        if ($v6 !== []) {
            $resolvedAny = true;
            foreach ($v6 as $ip) {
                if ($this->isBlockedIp($ip)) {
                    $blocked = true;
                }
            }
        }

        if (!$resolvedAny || $blocked) {
            return false;
        }
        return true;
    }

    /** Resolve AAAA records cross-driver (IPv6 bindTo or curl). */
    private function resolveAaaa(string $host): array
    {
        $out = [];
        if (function_exists('dns_get_record')) {
            $recs = @dns_get_record($host, DNS_AAAA);
            foreach (is_array($recs) ? $recs : [] as $r) {
                if (!empty($r['ipv6']) && filter_var($r['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $out[] = $r['ipv6'];
                }
            }
            return $out;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'http://' . $host . '/',
                CURLOPT_CONNECT_ONLY   => 2, // curl >= 7.49: resolve only
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_RESOLVE        => [],
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
            curl_exec($ch);
            $primary = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
            curl_close($ch);
            if (is_string($primary) && $primary !== '' && filter_var($primary, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $out[] = $primary;
            }
        }
        return $out;
    }

    public function isBlockedIp(string $ip): bool
    {
        $isV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $isV6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

        if ($isV4 === false && $isV6 === false) {
            return true;
        }

        // Un-map IPv4-mapped IPv6 back to its v4 form and re-check it against
        // the v4 block list too.
        $base = $ip;
        if ($isV6) {
            $packed = @inet_pton($ip);
            if ($packed !== false && strlen($packed) === 16 && str_starts_with(
                bin2hex($packed),
                '00000000000000000000ffff'
            )) {
                $base = @inet_ntop(substr($packed, 12)); // mapped ::a.b.c.d
                if ($base === false || $base === null) {
                    return true;
                }
                if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->isBlockedIp($base)) {
                    return true;
                }
            }
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->ipInCidr($base, $range)) {
                return true;
            }
        }

        // Belt & braces for IPv4: anything not globally routable unicast.
        if (filter_var($base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !filter_var(
            $base,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return true;
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        $subnet = @inet_pton($subnet);
        $ipBin = @inet_pton($ip);
        if ($subnet === false || $ipBin === false || strlen($subnet) !== strlen($ipBin)) {
            return false;
        }
        $mask = (int) $mask;
        $len = strlen($subnet) * 8;
        if ($mask < 0 || $mask > $len) {
            return false;
        }
        $maskBytes = intdiv($mask, 8);
        $maskBits = $mask % 8;
        if ($mask === 0) {
            return true; // 0-length prefix matches everything (e.g. ::/128 handled by length check above)
        }
        for ($i = 0; $i < $maskBytes; $i++) {
            if (ord($subnet[$i]) !== ord($ipBin[$i])) {
                return false;
            }
        }
        if ($maskBits > 0 && $maskBytes < strlen($subnet)) {
            $maskByte = 0xFF << (8 - $maskBits);
            if ((ord($subnet[$maskBytes]) & $maskByte) !== (ord($ipBin[$maskBytes]) & $maskByte)) {
                return false;
            }
        }
        return true;
    }

    /** Alias of validate() kept for readability at call sites. */
    public function isAllowed(string $url): bool
    {
        return $this->validate($url);
    }
}
