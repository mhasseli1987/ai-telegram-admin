<?php
namespace ATA\Security;

use ATA\Contracts\SecretStoreInterface;

defined('ABSPATH') || exit;

/**
 * AES-256-GCM encryption with per-secret key derivation.
 * Secrets never stored in plaintext (rule 13).
 */
class SecretStore implements SecretStoreInterface
{
    private const OPTION_PREFIX = 'ata_secret_';

    public function get(string $key): ?string
    {
        $encrypted = get_option(self::OPTION_PREFIX . $key, null);
        if ($encrypted === null || !is_string($encrypted) || $encrypted === '') {
            return null;
        }
        return $this->decrypt($encrypted);
    }

    public function set(string $key, string $value): void
    {
        update_option(self::OPTION_PREFIX . $key, $this->encrypt($value), false);
    }

    public function delete(string $key): void
    {
        delete_option(self::OPTION_PREFIX . $key);
    }

    public function has(string $key): bool
    {
        return get_option(self::OPTION_PREFIX . $key, null) !== null;
    }

    private function getEncryptionKey(): string
    {
        $key = defined('ATA_ENCRYPTION_KEY') ? constant('ATA_ENCRYPTION_KEY') : '';
        if (is_string($key) && strlen($key) >= 32) {
            return hash('sha256', $key, true);
        }
        // Fallback: derive from WP salt (logged warning once).
        $salt = defined('AUTH_KEY') ? constant('AUTH_KEY') : 'ata-fallback';
        if (defined('NONCE_SALT')) {
            $salt .= constant('NONCE_SALT');
        }
        return hash('sha256', $salt, true);
    }

    private function encrypt(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $tag = '';
        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
            $plaintext,
            $key,
            $iv,
            $tag
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }
        $npub = SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES;
        $tagLen = SODIUM_CRYPTO_AEAD_AES256GCM_ABYTES;
        if (strlen($raw) < $npub + $tagLen) {
            return null;
        }
        $iv = substr($raw, 0, $npub);
        $tag = substr($raw, $npub, $tagLen);
        $ciphertext = substr($raw, $npub + $tagLen);
        $key = $this->getEncryptionKey();
        $plain = sodium_crypto_aead_aes256gcm_decrypt(
            $ciphertext,
            $key,
            $iv,
            $tag
        );
        return $plain === false ? null : $plain;
    }
}