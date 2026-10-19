<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Security;

/**
 * Authenticated symmetric encryption at rest for Guest Registry occupant payloads (ADR 0012).
 *
 * Uses libsodium XSalsa20-Poly1305 (sodium_crypto_secretbox) keyed by APP_ENCRYPTION_KEY
 * and wraps the nonce and ciphertext inside a valid JSON envelope so MySQL JSON columns
 * remain structurally valid while storing zero plaintext PII.
 */
final class PayloadEncryptor
{
    public const ENVELOPE_VERSION = 1;
    public const ALGORITHM = 'xsalsa20-poly1305';
    private const DEFAULT_TEST_KEY = 'ovf-default-test-encryption-key-2026';

    /**
     * Encrypts an array of occupant records into a JSON-wrapped XSalsa20-Poly1305 envelope.
     *
     * @param array<int|string, mixed> $payload
     */
    public static function encrypt(array $payload, ?string $rawKey = null): string
    {
        $plaintext = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = self::resolveKey($rawKey);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return (string) json_encode([
            '_encrypted' => true,
            'v' => self::ENVELOPE_VERSION,
            'alg' => self::ALGORITHM,
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Transparently decrypts a JSON-wrapped ciphertext envelope or returns legacy plaintext arrays as-is.
     *
     * @param string|array<int|string, mixed>|null $stored
     * @return list<array<string, mixed>>
     */
    public static function decrypt(string|array|null $stored, ?string $rawKey = null): array
    {
        if ($stored === null || $stored === '' || $stored === '[]' || $stored === []) {
            return [];
        }

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            if (!is_array($decoded) || $decoded === []) {
                return [];
            }
        } else {
            $decoded = $stored;
        }

        if (($decoded['_encrypted'] ?? false) === true) {
            $nonceB64 = (string) ($decoded['nonce'] ?? '');
            $cipherB64 = (string) ($decoded['ciphertext'] ?? '');

            $nonce = base64_decode($nonceB64, true);
            $ciphertext = base64_decode($cipherB64, true);

            if (
                !is_string($nonce)
                || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
                || !is_string($ciphertext)
                || $ciphertext === ''
            ) {
                return [];
            }

            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, self::resolveKey($rawKey));
            if ($plaintext === false) {
                return [];
            }

            $decrypted = json_decode($plaintext, true);
            if (!is_array($decrypted)) {
                return [];
            }

            return self::normalizeOccupantList($decrypted);
        }

        return self::normalizeOccupantList($decoded);
    }

    /**
     * Checks whether a stored JSON string or decoded array is an encrypted envelope.
     *
     * @param string|array<int|string, mixed>|null $raw
     */
    public static function isEncryptedEnvelope(string|array|null $raw): bool
    {
        if ($raw === null || $raw === '' || $raw === '[]' || $raw === []) {
            return false;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded)
            && ($decoded['_encrypted'] ?? false) === true
            && isset($decoded['nonce'], $decoded['ciphertext']);
    }

    /**
     * Checks whether a non-empty encryption key is explicitly provided or configured in the environment.
     */
    public static function hasConfiguredKey(?string $rawKey = null): bool
    {
        if ($rawKey !== null && trim($rawKey) !== '') {
            return true;
        }

        $envVal = $_ENV['APP_ENCRYPTION_KEY']
            ?? $_SERVER['APP_ENCRYPTION_KEY']
            ?? (getenv('APP_ENCRYPTION_KEY') ?: null)
            ?? (defined('APP_ENCRYPTION_KEY') ? (string) constant('APP_ENCRYPTION_KEY') : null);

        return is_string($envVal) && trim($envVal) !== '';
    }

    /**
     * Derives a 32-byte binary key for sodium_crypto_secretbox from APP_ENCRYPTION_KEY.
     */
    public static function resolveKey(?string $rawKey = null): string
    {
        $configured = $rawKey;
        if ($configured === null || trim($configured) === '') {
            $envVal = $_ENV['APP_ENCRYPTION_KEY']
                ?? $_SERVER['APP_ENCRYPTION_KEY']
                ?? (getenv('APP_ENCRYPTION_KEY') ?: null)
                ?? (defined('APP_ENCRYPTION_KEY') ? (string) constant('APP_ENCRYPTION_KEY') : null);
            $configured = is_string($envVal) ? $envVal : '';
        }

        $trimmed = trim($configured);
        if ($trimmed === '') {
            $trimmed = self::DEFAULT_TEST_KEY;
        }

        if (str_starts_with($trimmed, 'base64:')) {
            $decoded = base64_decode(substr($trimmed, 7), true);
            if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $decoded;
            }
        }

        return hash('sha256', $trimmed, true);
    }

    /**
     * @param array<int|string, mixed> $items
     * @return list<array<string, mixed>>
     */
    private static function normalizeOccupantList(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $list[] = $item;
            }
        }

        return $list;
    }
}
