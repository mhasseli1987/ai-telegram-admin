<?php
namespace ATA\Security;

use ATA\Contracts\SecretStoreInterface;

defined('ABSPATH') || exit;

/**
 * Secrets at rest — libsodium XChaCha20-Poly1305-IETF.
 * Fixes fatally wrong crypto: old code passed ($plaintext, $key, $nonce, $tag)
 * to sodium_crypto_aead_*_encrypt() (key/nonce swapped, $tag is a return param
 * of the raw API), used a 24-byte nonce with AES256GCM (needs 12) and then
 * split the payload with a tag length that never matched how it was produced.
 *
 * XChaCha20-Poly1305-IETF accepts 24-byte nonces and is available on any PHP
 * 7.2+ build with sodium (bundled), no AES hardware requirement.
 *
 * Payload format: base64( magic | nonce(24) | ciphertext+tag ).
 */
class SecretStore implements SecretStoreInterface
{
    private const OPTION_PREFIX = 'ata_secret_';
    private const MAGIC = 'ATAS1';

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
        // Fallback: derive from WP salts (documented limitation in readme notes).
        $salt = defined('AUTH_KEY') ? constant('AUTH_KEY') : 'ata-fallback';
        if (defined('NONCE_SALT')) {
            $salt .= constant('NONCE_SALT');
        }
        return hash('sha256', $salt, true);
    }

    private function encrypt(string $plaintext): string
    {
        $key    = $this->getEncryptionKey();
        $nonce  = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES); // 24
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::MAGIC,           // $additional_data (bound into the tag)
            $nonce,
            $key
        );
        if ($cipher === false) {
            throw new \RuntimeException('Secret encryption failed.');
        }
        return base64_encode(self::MAGIC . $nonce . $cipher);
    }

    private function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }
        $magicLen = strlen(self::MAGIC);
        $npub     = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($raw) <= $magicLen + $npub || strncmp($raw, self::MAGIC, $magicLen) !== 0) {
            return null; // foreign or truncated payload — don't guess
        }
        $nonce = substr($raw, $magicLen, $npub);
        $cipher = substr($raw, $magicLen + $npub);
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $cipher,
            self::MAGIC,
            $nonce,
            $this->getEncryptionKey()
        );
        if ($plain === false) {
            return null; // tampered or key mismatch
        }
        return $plain;
    }
}
