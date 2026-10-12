<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Service;

use DateTimeImmutable;
use OceanViewFlats\Admin\Repository\AdminCalendarBlockRepository;
use OceanViewFlats\Admin\Repository\AdminRateRepository;
use OceanViewFlats\Admin\Repository\AdminReservationRepository;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceRepositoryInterface;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardQueryServiceInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\HeatmapDayCell;
use OceanViewFlats\Domain\Reservation\Dashboard\HeatmapWeekColumn;
use OceanViewFlats\Domain\Reservation\Dashboard\MovementType;
use OceanViewFlats\Domain\Reservation\Dashboard\OccupancyHeatmapViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationsEvent;
use OceanViewFlats\Domain\Reservation\Dashboard\PropertyRateStatus;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;

final class DashboardQueryService implements DashboardQueryServiceInterface
{
    private readonly CondominiumClearanceRepositoryInterface $clearanceRepo;

    public function __construct(
        private readonly AdminReservationRepository $reservationRepo,
        private readonly AdminCalendarBlockRepository $calendarBlockRepo,
        private readonly AdminRateRepository $rateRepo,
        private readonly InboundChannelSyncServiceInterface $channelSyncService,
        private readonly ?ReservationLedgerInterface $ledger = null,
        ?CondominiumClearanceRepositoryInterface $clearanceRepo = null
    ) {
        $this->clearanceRepo = $clearanceRepo ?? new PdoCondominiumClearanceRepository($this->reservationRepo->getPdo());
    }

    public function getDashboardHubData(
        string $propertyId = 'all',
        ?DateTimeImmutable $now = null
    ): DashboardHubViewData {
        $now = $now ?? new DateTimeImmutable('today');
        $today = $now->format('Y-m-d');
        $horizonEnd = $now->modify('+6 days')->format('Y-m-d');

        // 1. 7-Day Operational Schedule with turnover detection
        $events = $this->reservationRepo->getOperationalSchedule($propertyId, $today, $horizonEnd);

        // Group into all 7 days so view template receives an array keyed by each date
        /** @var array<string, list<OperationsEvent>> $scheduleByDate */
        $scheduleByDate = [];
        for ($i = 0; $i < 7; $i++) {
            $dateKey = $now->modify("+{$i} days")->format('Y-m-d');
            $scheduleByDate[$dateKey] = [];
        }
        foreach ($events as $event) {
            if (isset($scheduleByDate[$event->date])) {
                $scheduleByDate[$event->date][] = $event;
            }
        }

        // 2. Today's movements counters
        $todayArrivalsCount = 0;
        $todayDeparturesCount = 0;
        if (isset($scheduleByDate[$today])) {
            foreach ($scheduleByDate[$today] as $event) {
                if ($event->movementType === MovementType::CHECK_IN || $event->movementType === MovementType::TURNOVER) {
                    $todayArrivalsCount++;
                } elseif ($event->movementType === MovementType::CHECK_OUT) {
                    $todayDeparturesCount++;
                }
            }
        }

        // Active stays count
        $activeStaysCount = $this->reservationRepo->getActiveStaysCount($propertyId, $now);

        // 3. Operational Alerts
        // Incomplete guest registries (up to 3 days lookahead + in-house)
        $registryAlerts = $this->reservationRepo->getIncompleteRegistryAlerts($propertyId, 3, $now);

        // Failed condominium clearances
        $clearanceAlerts = $this->clearanceRepo->getFailedClearanceAlerts($propertyId, $now);

        // Un-onboarded channel blocks
        /** @var list<ChannelBlock> $channelBlocks */
        $channelBlocks = [];
        if ($this->ledger !== null) {
            $propIds = $propertyId === 'all' ? ['1606', '1707'] : [$propertyId];
            foreach ($propIds as $pid) {
                $channelBlocks = array_merge($channelBlocks, $this->ledger->getChannelBlocks($pid));
            }
        }
        $channelAlerts = $this->reservationRepo->getUnonboardedChannelBlockAlerts($channelBlocks, $propertyId, $now);

        $alerts = array_merge($registryAlerts, $clearanceAlerts, $channelAlerts);
        // Sort alerts by severity: CRITICAL, then WARNING, then INFO; then dueDate ASC
        usort($alerts, static function (OperationalAlert $a, OperationalAlert $b): int {
            $severityRank = [
                AlertSeverity::CRITICAL->value => 0,
                AlertSeverity::WARNING->value => 1,
                AlertSeverity::INFO->value => 2,
            ];
            $rankA = $severityRank[$a->severity->value];
            $rankB = $severityRank[$b->severity->value];
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return strcmp($a->dueDate, $b->dueDate);
        });

        // 4. Rate Statuses
        $targetProps = $propertyId === 'all' ? ['1606', '1707'] : [$propertyId];
        /** @var array<string|int, PropertyRateStatus> $rateStatus */
        $rateStatus = [];
        foreach ($targetProps as $pid) {
            $propKey = (string) $pid;
            $rateStatus[$propKey] = $this->rateRepo->getPropertyRateStatus($propKey, $now);
        }

        // 5. Upcoming Maintenance Blocks (14-day horizon)
        $upcomingMaintenanceBlocks = $this->calendarBlockRepo->getUpcomingBlocks($propertyId, 14, $now);

        // 6. Channel Sync Status
        $statuses = $this->channelSyncService->getAllStatuses();
        $isHealthy = !empty($statuses);
        foreach ($statuses as $status) {
            if (!$status->isHealthy()) {
                $isHealthy = false;
                break;
            }
        }
        $channelSyncData = [
            'statuses' => $statuses,
            'is_healthy' => $isHealthy,
        ];

        // 7. 7-Month Occupancy Heatmap
        $heatmap = $this->buildOccupancyHeatmap($propertyId, $now);

        return new DashboardHubViewData(
            selectedPropertyFilter: $propertyId,
            alerts: $alerts,
            scheduleByDate: $scheduleByDate,
            rateStatus: $rateStatus,
            upcomingMaintenanceBlocks: $upcomingMaintenanceBlocks,
            channelSyncData: $channelSyncData,
            todayArrivalsCount: $todayArrivalsCount,
            todayDeparturesCount: $todayDeparturesCount,
            activeStaysCount: $activeStaysCount,
            heatmap: $heatmap
        );
    }

    /**
     * Builds the 7-month Occupancy Heatmap spanning from the 1st of the current month
     * to the last day of the 6th future month.
     */
    public function buildOccupancyHeatmap(
        string $propertyId = 'all',
        ?DateTimeImmutable $now = null
    ): OccupancyHeatmapViewData {
        $now = $now ?? new DateTimeImmutable('today');
        $todayStr = $now->format('Y-m-d');

        // 7-month calendar horizon: 1st day of current month to last day of 6th future month
        $startDate = (new DateTimeImmutable($now->format('Y-m-01')))->setTime(0, 0, 0);
        $endMonth = $startDate->modify('+6 months');
        $endDate = (new DateTimeImmutable($endMonth->format('Y-m-t')))->setTime(23, 59, 59);

        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        // Fetch confirmed reservations in the horizon
        $reservations = $this->reservationRepo->getConfirmedReservationsForRange($propertyId, $startStr, $endStr);

        /** @var array<string, array<string, array{reservation_uid: string, guest_name: string}>> $occupancyByDate */
        $occupancyByDate = [];
        foreach ($reservations as $res) {
            $rStart = max((string) $res['check_in'], $startStr);
            $rEnd = min((string) $res['check_out'], $endDate->modify('+1 day')->format('Y-m-d'));
            $curr = new DateTimeImmutable($rStart);
            $rEndDt = new DateTimeImmutable($rEnd);
            while ($curr < $rEndDt && $curr->format('Y-m-d') <= $endStr) {
                $d = $curr->format('Y-m-d');
                $pid = (string) $res['property_id'];
                $occupancyByDate[$d][$pid] = [
                    'reservation_uid' => (string) $res['reservation_uid'],
                    'guest_name' => (string) $res['guest_name'],
                ];
                $curr = $curr->modify('+1 day');
            }
        }

        $totalUnits = ($propertyId === 'all') ? 2 : 1;
        $totalDaysCount = 0;
        $totalOccupiedDaysCount = 0;
        $totalPossibleUnitNights = 0;
        $totalOccupiedUnitNights = 0;

        /** @var list<HeatmapWeekColumn> $columns */
        $columns = [];
        /** @var array<int, ?HeatmapDayCell> $currentWeekDays */
        $currentWeekDays = array_fill(0, 7, null);
        $colIndex = 0;
        $colMonthLabel = null;

        /** @var list<array{label: string, startCol: int, span: int}> $monthHeaders */
        $monthHeaders = [];
        /** @var ?array{label: string, startCol: int, span: int} $currentMonthHeader */
        $currentMonthHeader = null;

        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $dateStr = $cursor->format('Y-m-d');
            $dayOfMonth = (int) $cursor->format('j');
            $dow = (int) $cursor->format('N'); // 1 = Mon ... 7 = Sun
            $rowIndex = $dow - 1; // 0 = Mon ... 6 = Sun

            $isToday = ($dateStr === $todayStr);
            $isPast = ($dateStr < $todayStr);

            $dayOccupancy = $occupancyByDate[$dateStr] ?? [];
            if ($propertyId === 'all') {
                $occupiedUnits = min(2, count($dayOccupancy));
            } else {
                $occupiedUnits = isset($dayOccupancy[$propertyId]) ? 1 : 0;
            }

            /** @var list<string> $reservationUids */
            $reservationUids = [];
            /** @var array<string, mixed> $flatDetails */
            $flatDetails = [];
            foreach ($dayOccupancy as $pid => $info) {
                $reservationUids[] = $info['reservation_uid'];
                $flatDetails[$pid] = $info;
            }
            $singleReservationUid = count($reservationUids) === 1 ? $reservationUids[0] : null;

            if ($dayOfMonth === 1) {
                $monthLabel = $cursor->format('M');
                $colMonthLabel = $monthLabel;

                if ($currentMonthHeader !== null) {
                    $currentMonthHeader['span'] = $colIndex - $currentMonthHeader['startCol'];
                    $monthHeaders[] = $currentMonthHeader;
                }
                $currentMonthHeader = [
                    'label' => $monthLabel,
                    'startCol' => $colIndex,
                    'span' => 0,
                ];
            }

            $cell = new HeatmapDayCell(
                date: $dateStr,
                dayOfMonth: $dayOfMonth,
                dayOfWeek: $dow,
                isToday: $isToday,
                isPast: $isPast,
                totalUnits: $totalUnits,
                occupiedUnits: $occupiedUnits,
                singleReservationUid: $singleReservationUid,
                reservationUids: $reservationUids,
                flatDetails: $flatDetails
            );

            $currentWeekDays[$rowIndex] = $cell;

            $totalDaysCount++;
            if ($occupiedUnits > 0) {
                $totalOccupiedDaysCount++;
            }
            $totalPossibleUnitNights += $totalUnits;
            $totalOccupiedUnitNights += $occupiedUnits;

            $isSunday = ($dow === 7);
            $isLastDay = ($cursor->format('Y-m-d') === $endStr);

            if ($isSunday || $isLastDay) {
                $columns[] = new HeatmapWeekColumn(
                    weekNumber: $colIndex + 1,
                    days: $currentWeekDays,
                    monthLabel: $colMonthLabel
                );
                $colIndex++;
                $currentWeekDays = array_fill(0, 7, null);
                $colMonthLabel = null;
            }

            $cursor = $cursor->modify('+1 day');
        }

        if ($currentMonthHeader !== null) {
            $currentMonthHeader['span'] = count($columns) - $currentMonthHeader['startCol'];
            $monthHeaders[] = $currentMonthHeader;
        }

        $overallOccupancyPercentage = $totalPossibleUnitNights > 0
            ? round(($totalOccupiedUnitNights / $totalPossibleUnitNights) * 100, 1)
            : 0.0;

        return new OccupancyHeatmapViewData(
            startDate: $startStr,
            endDate: $endStr,
            propertyFilter: $propertyId,
            columns: $columns,
            monthHeaders: $monthHeaders,
            overallOccupancyPercentage: $overallOccupancyPercentage,
            totalDaysCount: $totalDaysCount,
            totalOccupiedDaysCount: $totalOccupiedDaysCount
        );
    }
}
