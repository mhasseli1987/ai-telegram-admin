<?php
namespace ATA\Contracts;

defined('ABSPATH') || exit;

interface HttpClientInterface
{
    /**
     * @param array{method:string, headers:array, body:?string, timeout:int} $opts
     * @return array{status:int, headers:array, body:string}
     */
    public function request(string $url, array $opts = []): array;
}