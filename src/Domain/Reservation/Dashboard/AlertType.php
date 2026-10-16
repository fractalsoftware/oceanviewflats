<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

enum AlertType: string
{
    case INCOMPLETE_GUEST_REGISTRY = 'incomplete_guest_registry';
    case UNONBOARDED_CHANNEL_BLOCK = 'unonboarded_channel_block';
    case FAILED_CONDOMINIUM_CLEARANCE = 'failed_condominium_clearance';

    public const CLEARANCE_PREFIX = 'clearance_';
    public const REGISTRY_PREFIX = 'registry_';
    public const CHANNEL_BLOCK_PREFIX = 'channel_block_';

    public static function clearanceAlertId(string $reservationUid): string
    {
        return self::CLEARANCE_PREFIX . $reservationUid;
    }

    public static function registryAlertId(string $reservationUid): string
    {
        return self::REGISTRY_PREFIX . $reservationUid;
    }

    public static function channelBlockAlertId(string $blockHash): string
    {
        return self::CHANNEL_BLOCK_PREFIX . $blockHash;
    }

    public function idPrefix(): string
    {
        return match ($this) {
            self::FAILED_CONDOMINIUM_CLEARANCE => self::CLEARANCE_PREFIX,
            self::INCOMPLETE_GUEST_REGISTRY => self::REGISTRY_PREFIX,
            self::UNONBOARDED_CHANNEL_BLOCK => self::CHANNEL_BLOCK_PREFIX,
        };
    }

    public function matchesAlertId(string $alertId): bool
    {
        return str_starts_with($alertId, $this->idPrefix());
    }

    public function extractReservationUid(string $alertId): ?string
    {
        return match ($this) {
            self::FAILED_CONDOMINIUM_CLEARANCE, self::INCOMPLETE_GUEST_REGISTRY => $this->matchesAlertId($alertId)
                ? substr($alertId, strlen($this->idPrefix()))
                : null,
            self::UNONBOARDED_CHANNEL_BLOCK => null,
        };
    }
}
