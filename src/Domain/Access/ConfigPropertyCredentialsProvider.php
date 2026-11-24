<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Access;

/**
 * Production provider that reads property access credentials from configuration arrays
 * with environment variable overrides.
 */
final class ConfigPropertyCredentialsProvider implements PropertyCredentialsProviderInterface
{
    /**
     * @param array<array-key, array{door_code?: string, wifi_ssid?: string, wifi_password?: string, parking_spot?: string}> $config
     */
    public function __construct(
        private readonly array $config = []
    ) {}

    public function getCredentials(string $propertyId): ?AccessCredentials
    {
        $cleanId = preg_replace('/\D/', '', $propertyId);

        // Check environment variable overrides first
        $envDoorCode = $_ENV["PROPERTY_{$cleanId}_DOOR_CODE"] ?? $_SERVER["PROPERTY_{$cleanId}_DOOR_CODE"] ?? getenv("PROPERTY_{$cleanId}_DOOR_CODE") ?: null;
        $envWifiSsid = $_ENV["PROPERTY_{$cleanId}_WIFI_SSID"] ?? $_SERVER["PROPERTY_{$cleanId}_WIFI_SSID"] ?? getenv("PROPERTY_{$cleanId}_WIFI_SSID") ?: null;
        $envWifiPassword = $_ENV["PROPERTY_{$cleanId}_WIFI_PASSWORD"] ?? $_SERVER["PROPERTY_{$cleanId}_WIFI_PASSWORD"] ?? getenv("PROPERTY_{$cleanId}_WIFI_PASSWORD") ?: null;
        $envParkingSpot = $_ENV["PROPERTY_{$cleanId}_PARKING_SPOT"] ?? $_SERVER["PROPERTY_{$cleanId}_PARKING_SPOT"] ?? getenv("PROPERTY_{$cleanId}_PARKING_SPOT") ?: null;

        $conf = $this->config[$cleanId] ?? $this->config[$propertyId] ?? [];

        $doorCode = self::resolveNonEmpty($envDoorCode, $conf['door_code'] ?? null);
        $wifiSsid = self::resolveNonEmpty($envWifiSsid, $conf['wifi_ssid'] ?? null);
        $wifiPassword = self::resolveNonEmpty($envWifiPassword, $conf['wifi_password'] ?? null);
        $parkingSpot = self::resolveNonEmpty($envParkingSpot, $conf['parking_spot'] ?? null);

        // Non-secret property metadata defaults (never embed live door codes or Wi-Fi passwords in source code)
        if ($wifiSsid === null && ($cleanId === '1606' || $cleanId === '1707')) {
            $wifiSsid = "APTO{$cleanId}";
        }
        if ($parkingSpot === null && $cleanId === '1606') {
            $parkingSpot = '87';
        } elseif ($parkingSpot === null && $cleanId === '1707') {
            $parkingSpot = '95';
        }

        if ($doorCode === null || $wifiSsid === null || $wifiPassword === null) {
            return null;
        }

        return new AccessCredentials(
            propertyId: $propertyId,
            doorCode: $doorCode,
            wifiSsid: $wifiSsid,
            wifiPassword: $wifiPassword,
            parkingSpot: $parkingSpot
        );
    }

    private static function resolveNonEmpty(mixed $primary, mixed $fallback): ?string
    {
        if ($primary !== null && trim((string) $primary) !== '') {
            return (string) $primary;
        }
        if ($fallback !== null && trim((string) $fallback) !== '') {
            return (string) $fallback;
        }
        return null;
    }
}
