<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

final class HeatmapWeekColumn
{
    /**
     * @param array<int, ?HeatmapDayCell> $days Index 0 (Monday) to 6 (Sunday). Null for padding outside month bounds.
     */
    public function __construct(
        public readonly int $weekNumber,
        public readonly array $days,
        public readonly ?string $monthLabel = null
    ) {}
}
