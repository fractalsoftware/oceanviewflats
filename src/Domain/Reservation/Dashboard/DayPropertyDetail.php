<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

use OceanViewFlats\Domain\Reservation\MaintenanceBlock;

/**
 * Immutable DTO holding single-property operational detail for a specific calendar date.
 */
final class DayPropertyDetail
{
    /**
     * @param array<string, mixed> $rateInfo
     * @param array<string, mixed>|null $activeReservation
     * @param array<string, mixed>|null $departingReservation
     * @param array<string, mixed>|null $arrivingReservation
     * @param list<OperationsEvent> $movements
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $propertyId, // '1606' | '1707'
        public readonly string $propertyName, // 'Property 1606' | 'Property 1707'
        public readonly string $status, // 'Vacant' | 'Occupied' | 'Turnover' | 'Maintenance' | 'Pending'
        public readonly bool $isOccupied,
        public readonly int $nightlyRate,
        public readonly array $rateInfo,
        public readonly ?array $activeReservation = null,
        public readonly ?array $departingReservation = null,
        public readonly ?array $arrivingReservation = null,
        public readonly ?MaintenanceBlock $maintenanceBlock = null,
        public readonly array $movements = [],
        public readonly array $warnings = []
    ) {}

    public function hasMaintenance(): bool
    {
        return $this->maintenanceBlock !== null;
    }

    public function hasTurnover(): bool
    {
        return $this->departingReservation !== null && $this->arrivingReservation !== null;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }
}
