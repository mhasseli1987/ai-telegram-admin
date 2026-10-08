<?php
namespace ATA\Security;

defined('ABSPATH') || exit;

/**
 * SSRF Gate (D-8).
 * Validates outbound URLs before any HTTP call.
 * Blocks: non-http(s), private/reserved IPs, DNS-rebinding attempts.
 */
class SsrfGate
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
        '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
        '::1/128', 'fc00::/7', 'fe80::/10', 'ff00::/8',
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
        if ($host === '' || $host === 'localhost') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                return false;
            }
            return true;
        }
        // Resolve DNS and check each resolved IP.
        $ips = @gethostbynamel($host);
        if (!is_array($ips) || empty($ips)) {
            return false;
        }
        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                return false;
            }
        }
        return true;
    }

    private function isBlockedIp(string $ip): bool
    {
        $isV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $isV6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($isV4 === false && $isV6 === false) {
            return true;
        }
        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        $subnet = inet_pton($subnet);
        $ipBin = inet_pton($ip);
        if ($subnet === false || $ipBin === false || strlen($subnet) !== strlen($ipBin)) {
            return false;
        }
        $mask = (int)$mask;
        $len = strlen($subnet) * 8;
        if ($mask > $len || $mask < 0) {
            return false;
        }
        $maskBytes = $mask >> 3;
        $maskBits = $mask & 7;
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
}