<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Access;

/**
 * Pure domain service generating standardized cryptographically random 7-digit
 * smart lock keypad codes followed by '#' (Format: 0XXXXXX#) per ADR 0001 and ADR 0012.
 *
 * Physical access credentials are strictly decoupled from the Primary Guest's
 * government ID and phone number to prevent bidirectional PII disclosure.
 */
final class DoorCodeGenerator
{
    public const CODE_PREFIX = '0';
    public const SUFFIX = '#';
    public const CORE_DIGIT_LENGTH = 6;

    public static function generate(string $documentNumber = '', string $phone = ''): string
    {
        return self::generateRandom();
    }

    /**
     * Generates a fresh, non-deterministic 7-digit keypad code followed by '#'
     * for smart lock PIN regeneration or administrative overrides.
     */
    public static function generateRandom(): string
    {
        $randomCore = (string) random_int(100000, 999999);
        return self::CODE_PREFIX . $randomCore . self::SUFFIX;
    }

    /**
     * Alias for generateRandom() satisfying property-specific regeneration interface requirements.
     */
    public static function generateForProperty(string $propertyId = ''): string
    {
        return self::generateRandom();
    }
}
