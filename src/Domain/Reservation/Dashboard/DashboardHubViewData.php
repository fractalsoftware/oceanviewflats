<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

use OceanViewFlats\Domain\Reservation\MaintenanceBlock;

final class DashboardHubViewData
{
    /**
     * @param list<OperationalAlert> $alerts
     * @param array<string, list<OperationsEvent>> $scheduleByDate Keyed by YYYY-MM-DD
     * @param array<string|int, PropertyRateStatus> $rateStatus Keyed by propertyId ('1606', '1707')
     * @param list<MaintenanceBlock> $upcomingMaintenanceBlocks
     * @param array<string, mixed> $channelSyncData
     */
    public function __construct(
        public readonly string $selectedPropertyFilter, // 'all' | '1606' | '1707'
        public readonly array $alerts,
        public readonly array $scheduleByDate,
        public readonly array $rateStatus,
        public readonly array $upcomingMaintenanceBlocks,
        public readonly array $channelSyncData,
        public readonly int $todayArrivalsCount,
        public readonly int $todayDeparturesCount,
        public readonly int $activeStaysCount,
        public readonly ?OccupancyHeatmapViewData $heatmap = null
    ) {}
}
