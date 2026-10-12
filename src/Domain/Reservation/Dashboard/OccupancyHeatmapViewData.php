<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

final class OccupancyHeatmapViewData
{
    /**
     * @param list<HeatmapWeekColumn> $columns
     * @param list<array{label: string, startCol: int, span: int}> $monthHeaders
     */
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $propertyFilter, // 'all', '1606', '1707'
        public readonly array $columns,
        public readonly array $monthHeaders,
        public readonly float $overallOccupancyPercentage,
        public readonly int $totalDaysCount,
        public readonly int $totalOccupiedDaysCount,
        public readonly int $totalTurnoversCount = 0,
        public readonly int $totalAlertsCount = 0
    ) {}
}
