<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Canonical booking channel rollup groups for financial and hospitality reporting.
 */
enum BookingSourceGroup: string
{
    case DIRECT_ONLINE = 'direct_online';
    case DIRECT_MANUAL = 'direct_manual';
    case AIRBNB = 'airbnb';
    case OWNER_STAY = 'owner_stay';

    /**
     * Maps a raw reservation `source` string to its canonical BookingSourceGroup.
     */
    public static function fromReservationSource(string $source): self
    {
        $normalized = strtolower(trim($source));

        return match ($normalized) {
            'web', 'direct_online' => self::DIRECT_ONLINE,
            'airbnb' => self::AIRBNB,
            'owner_stay' => self::OWNER_STAY,
            'bank_transfer', 'cash', 'manual_override', 'manual', 'direct', 'direct_manual' => self::DIRECT_MANUAL,
            default => self::DIRECT_ONLINE,
        };
    }

    /**
     * Returns the 3 commercial booking source groups (excluding non-commercial owner stays).
     *
     * @return list<self>
     */
    public static function commercialCases(): array
    {
        return [
            self::DIRECT_ONLINE,
            self::DIRECT_MANUAL,
            self::AIRBNB,
        ];
    }

    public function isCommercial(): bool
    {
        return $this !== self::OWNER_STAY;
    }

    public function label(): string
    {
        return match ($this) {
            self::DIRECT_ONLINE => 'Direct Online',
            self::DIRECT_MANUAL => 'Direct Manual',
            self::AIRBNB => 'External OTA — Airbnb',
            self::OWNER_STAY => 'Owner Stay (Non-Commercial)',
        };
    }

    public function fullLabel(): string
    {
        return match ($this) {
            self::DIRECT_ONLINE => 'Direct Online (web)',
            self::DIRECT_MANUAL => 'Direct Manual (bank / cash / manual)',
            self::AIRBNB => 'External OTA — Airbnb',
            self::OWNER_STAY => 'Owner Stay (Non-Commercial)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DIRECT_ONLINE => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::DIRECT_MANUAL => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::AIRBNB => 'bg-rose-50 text-rose-700 border-rose-200',
            self::OWNER_STAY => 'bg-gray-100 text-gray-700 border-gray-300',
        };
    }

    public function barClass(): string
    {
        return match ($this) {
            self::DIRECT_ONLINE => 'bg-indigo-600',
            self::DIRECT_MANUAL => 'bg-emerald-600',
            self::AIRBNB => 'bg-rose-500',
            self::OWNER_STAY => 'bg-gray-400',
        };
    }
}
