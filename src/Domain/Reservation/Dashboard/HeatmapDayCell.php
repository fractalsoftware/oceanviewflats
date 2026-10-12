<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

final class HeatmapDayCell
{
    public readonly bool $hasWarning;

    /**
     * @param list<string> $reservationUids
     * @param array<array-key, mixed> $flatDetails
     * @param list<string> $warningMessages
     */
    public function __construct(
        public readonly string $date,            // 'YYYY-MM-DD'
        public readonly int $dayOfMonth,        // 1 - 31
        public readonly int $dayOfWeek,         // 1 (Mon) - 7 (Sun)
        public readonly bool $isToday,
        public readonly bool $isPast,
        public readonly int $totalUnits,        // 2 if property_id=all, else 1
        public readonly int $occupiedUnits,     // 0, 1, or 2
        public readonly string $propertyFilter = 'all',
        public readonly bool $hasTurnover = false,      // Same-day check-out & check-in
        public readonly bool $hasMaintenance = false,   // Active maintenance block
        public readonly bool $hasPendingHold = false,   // Pending reservation or hold
        public readonly bool $hasRiskAlert = false,     // Incomplete registry or failed clearance
        public readonly ?string $singleReservationUid = null, // UID if exactly 1 reservation touches this cell
        public readonly array $reservationUids = [], // All reservation UIDs for this day
        public readonly array $flatDetails = [],     // ['1606' => ['status' => 'occupied', 'guest' => '...'], ...]
        public readonly int $effectiveRate = 0,      // Base or effective daily rate in USD / COP
        public readonly array $warningMessages = [], // list<string>
        ?bool $hasWarning = null
    ) {
        $this->hasWarning = $hasWarning ?? ($this->hasRiskAlert || count($this->warningMessages) > 0);
    }

    /**
     * @return 'maintenance'|'vacant'|'partial'|'full'
     */
    public function getCapacityLevel(): string
    {
        if ($this->hasMaintenance && $this->occupiedUnits === 0) {
            return 'maintenance';
        }
        if ($this->occupiedUnits === 0) {
            return 'vacant';
        }
        if ($this->occupiedUnits < $this->totalUnits) {
            return 'partial';
        }
        return 'full';
    }

    public function getCapacityCssClass(): string
    {
        return match ($this->getCapacityLevel()) {
            'maintenance' => 'bg-amber-100 border border-amber-300 text-amber-900',
            'vacant' => 'bg-gray-100 text-gray-400',
            'partial' => 'bg-emerald-200 text-emerald-800',
            'full' => 'bg-emerald-600 text-white',
        };
    }

    public function getCellCssClasses(): string
    {
        $classes = $this->getCapacityCssClass();
        if ($this->hasMaintenance && $this->occupiedUnits > 0) {
            $classes .= ' ring-2 ring-amber-400';
        }
        if ($this->hasPendingHold && !$this->hasMaintenance) {
            $classes .= ' border border-dashed border-slate-400';
        }
        if ($this->isToday) {
            $classes .= ' ring-2 ring-indigo-600 ring-offset-1 z-10';
        } elseif ($this->isPast) {
            $classes .= ' opacity-40';
        }
        return $classes;
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
}
