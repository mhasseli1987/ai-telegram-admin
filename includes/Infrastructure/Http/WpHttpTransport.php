<?php
namespace ATA\Infrastructure\Http;

use ATA\Contracts\HttpClientInterface;
use ATA\Security\SsrfGate;

defined('ABSPATH') || exit;

/**
 * WordPress-based HTTP transport.
 * Uses wp_remote_request for transport but validates every URL through SSRF gate.
 */
class WpHttpTransport implements HttpClientInterface
{
    private SsrfGate $gate;

    public function __construct()
    {
        $this->gate = new SsrfGate();
    }

    public function request(string $url, array $opts = []): array
    {
        if (!$this->gate->validate($url)) {
            throw new \RuntimeException("SSRF blocked: invalid outbound URL");
        }

        $method = $opts['method'] ?? 'GET';
        $headers = $opts['headers'] ?? [];
        $body = $opts['body'] ?? null;
        $timeout = $opts['timeout'] ?? 30;

        $args = [
            'method'   => strtoupper($method),
            'timeout'  => $timeout,
            'headers'  => $headers,
            'sslverify'=> true,
            'user-agent'=> 'AI-Telegram-Admin/' . ATA_VERSION,
        ];

        if ($body !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException('HTTP error: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $respHeaders = wp_remote_retrieve_headers($response);
        $respBody = (string) wp_remote_retrieve_body($response);

        return [
            'status' => $status,
            'headers' => is_array($respHeaders) ? $respHeaders : [],
            'body'   => $respBody,
        ];
    }
}