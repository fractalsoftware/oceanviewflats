<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

final class HeatmapDayCell
{
    public readonly bool $hasWarning;
    public readonly int $totalUnits;
    public readonly int $occupiedUnits;
    public readonly int $totalProperties;
    public readonly int $occupiedProperties;
    /** @var array<array-key, mixed> */
    public readonly array $flatDetails;
    /** @var array<array-key, mixed> */
    public readonly array $propertyDetails;

    /**
     * @param list<string> $reservationUids
     * @param array<array-key, mixed> $flatDetails
     * @param list<string> $warningMessages
     * @param array<array-key, mixed> $propertyDetails
     */
    public function __construct(
        public readonly string $date,            // 'YYYY-MM-DD'
        public readonly int $dayOfMonth,        // 1 - 31
        public readonly int $dayOfWeek,         // 1 (Mon) - 7 (Sun)
        public readonly bool $isToday,
        public readonly bool $isPast,
        int $totalUnits = 2,                    // 2 if property_id=all, else 1
        int $occupiedUnits = 0,                 // 0, 1, or 2
        public readonly string $propertyFilter = 'all',
        public readonly bool $hasTurnover = false,      // Same-day check-out & check-in
        public readonly bool $hasMaintenance = false,   // Active maintenance block
        public readonly bool $hasPendingHold = false,   // Pending reservation or hold
        public readonly bool $hasRiskAlert = false,     // Incomplete registry or failed clearance
        public readonly ?string $singleReservationUid = null, // UID if exactly 1 reservation touches this cell
        public readonly array $reservationUids = [], // All reservation UIDs for this day
        array $flatDetails = [],     // ['1606' => ['status' => 'occupied', 'guest' => '...'], ...]
        public readonly int $effectiveRate = 0,      // Base or effective daily rate in USD / COP
        public readonly array $warningMessages = [], // list<string>
        ?bool $hasWarning = null,
        ?int $totalProperties = null,
        ?int $occupiedProperties = null,
        array $propertyDetails = []
    ) {
        $effTotal = $totalProperties ?? $totalUnits;
        $effOccupied = $occupiedProperties ?? $occupiedUnits;
        $effProps = !empty($propertyDetails) ? $propertyDetails : $flatDetails;

        $this->totalUnits = $effTotal;
        $this->totalProperties = $effTotal;
        $this->occupiedUnits = $effOccupied;
        $this->occupiedProperties = $effOccupied;
        $this->flatDetails = $effProps;
        $this->propertyDetails = $effProps;
        $this->hasWarning = $hasWarning ?? ($this->hasRiskAlert || count($this->warningMessages) > 0);
    }

    /**
     * @return 'maintenance'|'vacant'|'partial'|'full'
     */
    public function getCapacityLevel(): string
    {
        if ($this->hasMaintenance && $this->occupiedProperties === 0) {
            return 'maintenance';
        }
        if ($this->occupiedProperties === 0) {
            return 'vacant';
        }
        if ($this->occupiedProperties < $this->totalProperties) {
            return 'partial';
        }
        return 'full';
    }

    /**
     * Returns the click navigation action for state-adaptive HTMX routing:
     * - 'vacant': 0 reservations, no maintenance -> opens New Reservation modal
     * - 'single_reservation': exactly 1 reservation, no turnover, no maintenance -> opens Reservation detail drawer
     * - 'day_drawer': multi-stay, turnover, or maintenance block -> opens Day Summary drawer
     *
     * @return 'vacant'|'single_reservation'|'day_drawer'
     */
    public function getClickAction(): string
    {
        if ($this->hasMaintenance || $this->hasTurnover || count($this->reservationUids) > 1) {
            return 'day_drawer';
        }
        if (count($this->reservationUids) === 1 && $this->singleReservationUid !== null) {
            return 'single_reservation';
        }
        return 'vacant';
    }

    public function isClickActionVacant(): bool
    {
        return $this->getClickAction() === 'vacant';
    }

    public function isClickActionSingleReservation(): bool
    {
        return $this->getClickAction() === 'single_reservation';
    }

    public function isClickActionDayDrawer(): bool
    {
        return $this->getClickAction() === 'day_drawer';
    }

    public function getCapacityCssClass(): string
    {
        return HeatmapCellPresenter::capacityCssClass($this->getCapacityLevel());
    }

    public function getCellCssClasses(): string
    {
        return HeatmapCellPresenter::cellCssClasses($this);
    }

    public function hasTurnover(): bool
    {
        return $this->hasTurnover;
    }

    public function hasMaintenance(): bool
    {
        return $this->hasMaintenance;
    }

    public function hasPendingHold(): bool
    {
        return $this->hasPendingHold;
    }

    public function hasRiskAlert(): bool
    {
        return $this->hasRiskAlert;
    }

    public function hasWarning(): bool
    {
        return $this->hasWarning;
    }

    /**
     * @return list<string>
     */
    public function getWarningMessages(): array
    {
        return $this->warningMessages;
    }

    public function getEffectiveRate(): int
    {
        return $this->effectiveRate;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getFlatDetails(): array
    {
        return $this->flatDetails;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getPropertyDetails(): array
    {
        return $this->propertyDetails;
    }
}
