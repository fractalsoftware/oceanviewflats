<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

use OceanViewFlats\Domain\Reservation\MaintenanceBlock;

/**
 * Immutable aggregate DTO holding multi-stay, turnover, and operational detail
 * for a specific calendar date in the administrative Day Summary drawer.
 */
final class DayDetailViewData
{
    /**
     * @param array<string|int, DayPropertyDetail> $properties Keyed by propertyId ('1606', '1707')
     * @param list<OperationsEvent> $movements
     * @param list<MaintenanceBlock> $maintenanceBlocks
     * @param list<string> $warnings
     * @param list<string> $reservationUids
     */
    public function __construct(
        public readonly string $date, // 'YYYY-MM-DD'
        public readonly string $formattedDate, // e.g. 'Thursday, Oct 15, 2026'
        public readonly string $dayOfWeekName, // e.g. 'Thursday'
        public readonly string $propertyFilter, // 'all' | '1606' | '1707'
        public readonly int $totalUnits, // 2 if 'all', else 1
        public readonly int $occupiedUnits, // 0, 1, 2
        public readonly string $capacityLevel, // 'vacant' | 'partial' | 'full' | 'maintenance'
        public readonly bool $isToday,
        public readonly bool $isPast,
        public readonly bool $hasTurnover,
        public readonly bool $hasMaintenance,
        public readonly bool $hasWarning,
        public readonly int $combinedRate,
        public readonly array $properties,
        public readonly array $movements = [],
        public readonly array $maintenanceBlocks = [],
        public readonly array $warnings = [],
        public readonly array $reservationUids = []
    ) {}

    public function isVacant(): bool
    {
        return $this->occupiedUnits === 0 && !$this->hasMaintenance;
    }

    public function isFull(): bool
    {
        return $this->occupiedUnits >= $this->totalUnits;
    }

    public function isPartial(): bool
    {
        return $this->occupiedUnits > 0 && $this->occupiedUnits < $this->totalUnits;
    }
}
