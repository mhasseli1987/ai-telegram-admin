<?php
namespace ATA\Contracts;

defined('ABSPATH') || exit;

interface SecretStoreInterface
{
    public function get(string $key): ?string;
    public function set(string $key, string $value): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
}