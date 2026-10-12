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
    public readonly int $totalUnits;
    public readonly int $occupiedUnits;
    public readonly int $totalProperties;
    public readonly int $occupiedProperties;

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
        int $totalUnits = 2, // 2 if 'all', else 1
        int $occupiedUnits = 0, // 0, 1, 2
        public readonly string $capacityLevel = 'vacant', // 'vacant' | 'partial' | 'full' | 'maintenance'
        public readonly bool $isToday = false,
        public readonly bool $isPast = false,
        public readonly bool $hasTurnover = false,
        public readonly bool $hasMaintenance = false,
        public readonly bool $hasWarning = false,
        public readonly int $combinedRate = 0,
        public readonly array $properties = [],
        public readonly array $movements = [],
        public readonly array $maintenanceBlocks = [],
        public readonly array $warnings = [],
        public readonly array $reservationUids = [],
        ?int $totalProperties = null,
        ?int $occupiedProperties = null
    ) {
        $effTotal = $totalProperties ?? $totalUnits;
        $effOccupied = $occupiedProperties ?? $occupiedUnits;

        $this->totalUnits = $effTotal;
        $this->totalProperties = $effTotal;
        $this->occupiedUnits = $effOccupied;
        $this->occupiedProperties = $effOccupied;
    }

    public function isVacant(): bool
    {
        return $this->occupiedProperties === 0 && !$this->hasMaintenance;
    }

    public function isFull(): bool
    {
        return $this->occupiedProperties >= $this->totalProperties;
    }

    public function isPartial(): bool
    {
        return $this->occupiedProperties > 0 && $this->occupiedProperties < $this->totalProperties;
    }

    public function getTotalProperties(): int
    {
        return $this->totalProperties;
    }

    public function getOccupiedProperties(): int
    {
        return $this->occupiedProperties;
    }
}
