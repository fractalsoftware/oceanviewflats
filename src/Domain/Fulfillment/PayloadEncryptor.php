<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use OceanViewFlats\Domain\Security\PayloadEncryptor as SecurityPayloadEncryptor;

/**
 * Convenience delegate for PayloadEncryptor within the Fulfillment domain namespace.
 */
final class PayloadEncryptor
{
    /**
     * @param array<int|string, mixed> $payload
     */
    public static function encrypt(array $payload, ?string $rawKey = null): string
    {
        return SecurityPayloadEncryptor::encrypt($payload, $rawKey);
    }

    /**
     * @param string|array<int|string, mixed>|null $stored
     * @return list<array<string, mixed>>
     */
    public static function decrypt(string|array|null $stored, ?string $rawKey = null): array
    {
        return SecurityPayloadEncryptor::decrypt($stored, $rawKey);
    }

    /**
     * @param string|array<int|string, mixed>|null $raw
     */
    public static function isEncryptedEnvelope(string|array|null $raw): bool
    {
        return SecurityPayloadEncryptor::isEncryptedEnvelope($raw);
    }

    public static function resolveKey(?string $rawKey = null): string
    {
        return SecurityPayloadEncryptor::resolveKey($rawKey);
    }
}
