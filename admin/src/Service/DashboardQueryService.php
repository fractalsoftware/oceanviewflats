<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OceanViewFlats\Admin\Repository\AdminCalendarBlockRepository;
use OceanViewFlats\Admin\Repository\AdminRateRepository;
use OceanViewFlats\Admin\Repository\AdminReservationRepository;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceRepositoryInterface;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Quote\RateTier;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardQueryServiceInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\DayDetailViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DayPropertyDetail;
use OceanViewFlats\Domain\Reservation\Dashboard\HeatmapDayCell;
use OceanViewFlats\Domain\Reservation\Dashboard\HeatmapWeekColumn;
use OceanViewFlats\Domain\Reservation\Dashboard\MovementType;
use OceanViewFlats\Domain\Reservation\Dashboard\OccupancyHeatmapViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlertDismissalRepositoryInterface;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationsEvent;
use OceanViewFlats\Domain\Reservation\Dashboard\PdoOperationalAlertDismissalRepository;
use OceanViewFlats\Domain\Reservation\Dashboard\PropertyRateStatus;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;

final class DashboardQueryService implements DashboardQueryServiceInterface
{
    private readonly CondominiumClearanceRepositoryInterface $clearanceRepo;
    private readonly OperationalAlertDismissalRepositoryInterface $dismissalRepo;
    /** @var array<string, list<RateTier>> */
    private array $cachedRateTiers = [];

    public function __construct(
        private readonly AdminReservationRepository $reservationRepo,
        private readonly AdminCalendarBlockRepository $calendarBlockRepo,
        private readonly AdminRateRepository $rateRepo,
        private readonly InboundChannelSyncServiceInterface $channelSyncService,
        private readonly ?ReservationLedgerInterface $ledger = null,
        ?CondominiumClearanceRepositoryInterface $clearanceRepo = null,
        ?OperationalAlertDismissalRepositoryInterface $dismissalRepo = null
    ) {
        $this->clearanceRepo = $clearanceRepo ?? new PdoCondominiumClearanceRepository($this->reservationRepo->getPdo());
        $this->dismissalRepo = $dismissalRepo ?? new PdoOperationalAlertDismissalRepository($this->reservationRepo->getPdo());
    }

    /**
     * @return list<RateTier>
     */
    private function getTiersForProperty(string $propertyId): array
    {
        if (!isset($this->cachedRateTiers[$propertyId])) {
            try {
                $this->cachedRateTiers[$propertyId] = array_values($this->rateRepo->getTiersForProperty($propertyId));
            } catch (\Throwable) {
                $this->cachedRateTiers[$propertyId] = [];
            }
        }
        return $this->cachedRateTiers[$propertyId];
    }

    public function getDashboardHubData(
        string $propertyId = 'all',
        ?DateTimeImmutable $now = null
    ): DashboardHubViewData {
        $now = $now ?? new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
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
        $dismissedMap = array_fill_keys($this->dismissalRepo->getDismissedAlertIds($propertyId), true);
        if ($dismissedMap !== []) {
            $alerts = array_values(array_filter(
                $alerts,
                static fn(OperationalAlert $alert): bool => !isset($dismissedMap[$alert->id])
            ));
        }

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
        $now = $now ?? new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
        $todayStr = $now->format('Y-m-d');
        $lookaheadMax = $now->modify('+7 days')->format('Y-m-d');

        // 7-month calendar horizon: 1st day of current month to last day of 6th future month
        $startDate = (new DateTimeImmutable($now->format('Y-m-01')))->setTime(0, 0, 0);
        $endMonth = $startDate->modify('+6 months');
        $endDate = (new DateTimeImmutable($endMonth->format('Y-m-t')))->setTime(23, 59, 59);

        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        // Fetch confirmed and pending reservations in the horizon
        $reservations = $this->reservationRepo->getConfirmedReservationsForRange($propertyId, $startStr, $endStr);

        // Fetch maintenance blocks for range
        /** @var list<MaintenanceBlock> $maintenanceBlocks */
        $maintenanceBlocks = [];
        try {
            $maintenanceBlocks = $this->calendarBlockRepo->getBlocksForRange($propertyId, $startStr, $endStr);
        } catch (\Throwable) {
            $maintenanceBlocks = [];
        }

        /** @var array<string, array<string, MaintenanceBlock>> $maintenanceByDate */
        $maintenanceByDate = [];
        foreach ($maintenanceBlocks as $block) {
            $bStart = max($block->startDate, $startStr);
            $bEnd = min($block->endDate, $endDate->modify('+1 day')->format('Y-m-d'));
            $currB = new DateTimeImmutable($bStart);
            $bEndDt = new DateTimeImmutable($bEnd);
            while ($currB < $bEndDt && $currB->format('Y-m-d') <= $endStr) {
                $d = $currB->format('Y-m-d');
                $maintenanceByDate[$d][$block->propertyId] = $block;
                $currB = $currB->modify('+1 day');
            }
        }

        // Fetch failed condominium clearances
        /** @var array<string, string> $failedClearanceMap */
        $failedClearanceMap = [];
        try {
            $failedClearances = $this->clearanceRepo->findFailedClearances(200);
            foreach ($failedClearances as $fc) {
                $failedClearanceMap[$fc->reservationUid] = $fc->errorMessage ?: 'Condominium clearance failed';
            }
        } catch (\Throwable) {
            $failedClearanceMap = [];
        }

        $dismissedMap = array_fill_keys($this->dismissalRepo->getDismissedAlertIds($propertyId), true);

        // Fetch seasonal rate tiers
        $tiers1606 = $this->getTiersForProperty('1606');
        $tiers1707 = $this->getTiersForProperty('1707');

        // Index reservations by check_in, check_out, and active stay nights
        /** @var array<string, array<string, list<array<string, mixed>>>> $checkInsByProp */
        $checkInsByProp = [];
        /** @var array<string, array<string, list<array<string, mixed>>>> $checkOutsByProp */
        $checkOutsByProp = [];
        /** @var array<string, array<string, array<string, mixed>>> $activeConfirmedStaysByDate */
        $activeConfirmedStaysByDate = [];
        /** @var array<string, array<string, array<string, mixed>>> $pendingHoldsByDate */
        $pendingHoldsByDate = [];

        foreach ($reservations as $res) {
            $pid = (string) $res['property_id'];
            $cin = (string) $res['check_in'];
            $cout = (string) $res['check_out'];
            $status = (string) $res['status'];

            $checkInsByProp[$pid][$cin][] = $res;
            $checkOutsByProp[$pid][$cout][] = $res;

            $rStart = max($cin, $startStr);
            $rEnd = min($cout, $endDate->modify('+1 day')->format('Y-m-d'));
            $curr = new DateTimeImmutable($rStart);
            $rEndDt = new DateTimeImmutable($rEnd);
            while ($curr < $rEndDt && $curr->format('Y-m-d') <= $endStr) {
                $d = $curr->format('Y-m-d');
                if ($status === 'confirmed') {
                    $activeConfirmedStaysByDate[$d][$pid] = $res;
                } elseif ($status === 'pending_payment') {
                    $pendingHoldsByDate[$d][$pid] = $res;
                }
                $curr = $curr->modify('+1 day');
            }
        }

        $totalUnits = ($propertyId === 'all') ? 2 : 1;
        $totalDaysCount = 0;
        $totalOccupiedDaysCount = 0;
        $totalPossibleUnitNights = 0;
        $totalOccupiedUnitNights = 0;
        $totalTurnoversCount = 0;
        $totalAlertsCount = 0;

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

        $propertiesToEval = ($propertyId === 'all') ? ['1606', '1707'] : [$propertyId];

        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $dateStr = $cursor->format('Y-m-d');
            $dayOfMonth = (int) $cursor->format('j');
            $dow = (int) $cursor->format('N'); // 1 = Mon ... 7 = Sun
            $rowIndex = $dow - 1; // 0 = Mon ... 6 = Sun

            $isToday = ($dateStr === $todayStr);
            $isPast = ($dateStr < $todayStr);

            $occupiedUnits = 0;
            $dayHasTurnover = false;
            $dayHasMaintenance = false;
            $dayHasPendingHold = false;
            /** @var list<string> $warningMessages */
            $warningMessages = [];
            /** @var list<string> $reservationUids */
            $reservationUids = [];
            /** @var array<string, mixed> $flatDetails */
            $flatDetails = [];
            $combinedRate = 0;

            foreach ($propertiesToEval as $pid) {
                $rateInfo = $this->calculateNightlyRate($pid, $dateStr, $tiers1606, $tiers1707);
                $combinedRate += $rateInfo['amount'];

                // Turnover check for property $pid on $dateStr
                $hasPropTurnover = !empty($checkInsByProp[$pid][$dateStr]) && !empty($checkOutsByProp[$pid][$dateStr]);
                if ($hasPropTurnover) {
                    $dayHasTurnover = true;
                }

                // Maintenance check for property $pid on $dateStr
                $mBlock = $maintenanceByDate[$dateStr][$pid] ?? null;
                if ($mBlock !== null) {
                    $dayHasMaintenance = true;
                }

                // Active stay on dateStr
                $confirmedRes = $activeConfirmedStaysByDate[$dateStr][$pid] ?? null;
                $pendingRes = $pendingHoldsByDate[$dateStr][$pid] ?? null;
                $resIn = $checkInsByProp[$pid][$dateStr][0] ?? null;
                $resOut = $checkOutsByProp[$pid][$dateStr][0] ?? null;

                if ($confirmedRes !== null) {
                    $occupiedUnits++;
                    $resUid = (string) $confirmedRes['reservation_uid'];
                    $reservationUids[] = $resUid;

                    if (!$isPast) {
                        $regCompleted = (int) ($confirmedRes['registry_completed'] ?? 0);
                        $guestName = (string) ($confirmedRes['guest_name'] ?: 'Guest');
                        $rCin = (string) ($confirmedRes['check_in'] ?? '');
                        $rCout = (string) ($confirmedRes['check_out'] ?? '');
                        $isWithinLookahead = ($rCout > $todayStr && $rCin <= $lookaheadMax);
                        if ($regCompleted === 0 && $isWithinLookahead && !isset($dismissedMap['registry_' . $resUid])) {
                            $warningMessages[] = "Guest registry incomplete ({$guestName})";
                        }
                        if ($rCout > $todayStr && isset($failedClearanceMap[$resUid]) && !isset($dismissedMap['clearance_' . $resUid])) {
                            $warningMessages[] = "Condominium clearance failed ({$guestName})";
                        }
                    }
                }

                if ($pendingRes !== null) {
                    $dayHasPendingHold = true;
                    $reservationUids[] = (string) $pendingRes['reservation_uid'];
                }

                // Determine flat status, guest name/code, movement
                $flatStatus = 'Vacant';
                $primaryGuestOrCode = null;
                $movement = null;

                if ($mBlock !== null) {
                    $flatStatus = 'Maintenance';
                    $primaryGuestOrCode = 'Hold: ' . $mBlock->reason;
                    $movement = 'Maintenance';
                } elseif ($hasPropTurnover && $resOut !== null && $resIn !== null) {
                    $flatStatus = 'Reserved (Confirmed)';
                    $outGuest = (string) ($resOut['guest_name'] ?: ($resOut['external_confirmation_code'] ?: 'Guest'));
                    $inGuest = (string) ($resIn['guest_name'] ?: ($resIn['external_confirmation_code'] ?: 'Guest'));
                    $primaryGuestOrCode = "{$outGuest} (Check-out) / {$inGuest} (Check-in)";
                    $movement = 'Turnover';
                } elseif ($confirmedRes !== null) {
                    $flatStatus = 'Reserved (Confirmed)';
                    $primaryGuestOrCode = (string) ($confirmedRes['guest_name'] ?: ($confirmedRes['external_confirmation_code'] ?: 'Guest'));
                    $movement = ((string) $confirmedRes['check_in'] === $dateStr) ? 'Check-in' : 'Stay';
                } elseif ($pendingRes !== null) {
                    $flatStatus = 'Reserved (Pending)';
                    $primaryGuestOrCode = (string) ($pendingRes['guest_name'] ?: ($pendingRes['external_confirmation_code'] ?: 'Hold'));
                    $movement = ((string) $pendingRes['check_in'] === $dateStr) ? 'Check-in' : 'Pending Hold';
                } elseif ($resOut !== null) {
                    $flatStatus = 'Vacant';
                    $primaryGuestOrCode = (string) ($resOut['guest_name'] ?: ($resOut['external_confirmation_code'] ?: 'Guest'));
                    $movement = 'Check-out';
                }

                $flatDetails[$pid] = [
                    'property_id' => $pid,
                    'status' => $flatStatus,
                    'label' => "Property {$pid}: {$flatStatus}",
                    'rate' => $rateInfo['amount'],
                    'rate_info' => $rateInfo,
                    'guest' => $primaryGuestOrCode,
                    'movement' => $movement,
                    'reservation_uid' => $confirmedRes['reservation_uid'] ?? $pendingRes['reservation_uid'] ?? null,
                ];
            }

            $uniqueReservationUids = array_values(array_unique($reservationUids));
            $singleReservationUid = count($uniqueReservationUids) === 1 ? $uniqueReservationUids[0] : null;
            $dayHasWarning = count($warningMessages) > 0;

            $effectiveRate = ($propertyId === 'all')
                ? $combinedRate
                : ($flatDetails[$propertyId]['rate'] ?? $combinedRate);

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
                propertyFilter: $propertyId,
                hasTurnover: $dayHasTurnover,
                hasMaintenance: $dayHasMaintenance,
                hasPendingHold: $dayHasPendingHold,
                hasRiskAlert: $dayHasWarning,
                singleReservationUid: $singleReservationUid,
                reservationUids: $uniqueReservationUids,
                flatDetails: $flatDetails,
                effectiveRate: (int) $effectiveRate,
                warningMessages: $warningMessages,
                hasWarning: $dayHasWarning,
                totalProperties: $totalUnits,
                occupiedProperties: $occupiedUnits,
                propertyDetails: $flatDetails
            );

            $currentWeekDays[$rowIndex] = $cell;

            $totalDaysCount++;
            if ($occupiedUnits > 0) {
                $totalOccupiedDaysCount++;
            }
            if ($dayHasTurnover) {
                $totalTurnoversCount++;
            }
            if ($dayHasWarning) {
                $totalAlertsCount++;
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
            totalOccupiedDaysCount: $totalOccupiedDaysCount,
            totalTurnoversCount: $totalTurnoversCount,
            totalAlertsCount: $totalAlertsCount
        );
    }

    /**
     * @param array<int, RateTier> $tiers1606
     * @param array<int, RateTier> $tiers1707
     * @return array{amount: int, isSeasonal: bool, tierName: string}
     */
    private function calculateNightlyRate(string $pid, string $d, array $tiers1606, array $tiers1707): array
    {
        $tiers = ($pid === '1606') ? $tiers1606 : $tiers1707;
        $base = ($pid === '1606') ? 110 : 120;
        foreach ($tiers as $tier) {
            if ($tier->coversDate($d)) {
                return [
                    'amount' => (int) $tier->nightlyRateCop,
                    'isSeasonal' => true,
                    'tierName' => $tier->seasonName,
                ];
            }
        }
        return [
            'amount' => $base,
            'isSeasonal' => false,
            'tierName' => 'Standard Rate',
        ];
    }

    /**
     * Aggregates detailed property statuses, reservations, movements, rates,
     * and maintenance blocks for a specific calendar date into DayDetailViewData.
     *
     * @param string $date 'YYYY-MM-DD'
     * @param string $propertyFilter 'all' | '1606' | '1707'
     * @throws InvalidArgumentException If date format or calendar date is invalid
     */
    public function getDaySummary(
        string $date,
        string $propertyFilter = 'all',
        ?DateTimeImmutable $now = null
    ): DayDetailViewData {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException("Invalid date format: {$date}. Expected YYYY-MM-DD.");
        }
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dateObj === false || $dateObj->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid calendar date: {$date}.");
        }

        $now = $now ?? new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
        $todayStr = $now->format('Y-m-d');
        $lookaheadMax = $now->modify('+7 days')->format('Y-m-d');
        $isToday = ($date === $todayStr);
        $isPast = ($date < $todayStr);
        $formattedDate = $dateObj->format('l, M j, Y');
        $dayOfWeekName = $dateObj->format('l');

        $filter = in_array($propertyFilter, ['1606', '1707'], true) ? $propertyFilter : 'all';
        $targetProps = ($filter === 'all') ? ['1606', '1707'] : [$filter];
        $totalUnits = count($targetProps);

        // 1. Fetch seasonal rate tiers
        $tiers1606 = $this->getTiersForProperty('1606');
        $tiers1707 = $this->getTiersForProperty('1707');

        // 2. Fetch maintenance blocks covering date
        /** @var list<MaintenanceBlock> $allBlocks */
        $allBlocks = [];
        try {
            $allBlocks = $this->calendarBlockRepo->getBlocksForRange($filter, $date, $date);
        } catch (\Throwable) {
            $allBlocks = [];
        }

        /** @var array<string|int, MaintenanceBlock> $maintenanceByProp */
        $maintenanceByProp = [];
        /** @var list<MaintenanceBlock> $dayMaintenanceBlocks */
        $dayMaintenanceBlocks = [];
        foreach ($allBlocks as $block) {
            $covers = ($block->startDate === $block->endDate)
                ? ($block->startDate === $date)
                : ($block->startDate <= $date && $date < $block->endDate);
            if ($covers) {
                $maintenanceByProp[$block->propertyId] = $block;
                $dayMaintenanceBlocks[] = $block;
            }
        }

        // 3. Operational schedule (movements on this date)
        /** @var list<OperationsEvent> $events */
        $events = [];
        try {
            $events = $this->reservationRepo->getOperationalSchedule($filter, $date, $date);
        } catch (\Throwable) {
            $events = [];
        }

        // 4. Reservations touching this date
        $rangeReservations = $this->reservationRepo->getConfirmedReservationsForRange($filter, $date, $date);

        /** @var array<string|int, list<array<string, mixed>>> $checkInsByProp */
        $checkInsByProp = [];
        /** @var array<string|int, list<array<string, mixed>>> $checkOutsByProp */
        $checkOutsByProp = [];
        /** @var array<string|int, array<string, mixed>> $activeStaysByProp */
        $activeStaysByProp = [];

        foreach ($rangeReservations as $row) {
            $uid = (string) $row['reservation_uid'];
            $pid = (string) $row['property_id'];
            $cin = (string) $row['check_in'];
            $cout = (string) $row['check_out'];

            $fullRes = $this->reservationRepo->findReservationByUid($uid) ?? $row;

            try {
                $clearance = $this->clearanceRepo->findByReservationUid($uid);
                if ($clearance !== null) {
                    $fullRes['clearance_status'] = $clearance->status;
                    $fullRes['clearance_number'] = $clearance->clearanceNumber;
                    $fullRes['clearance_error'] = $clearance->errorMessage;
                } else {
                    $fullRes['clearance_status'] = null;
                    $fullRes['clearance_number'] = null;
                    $fullRes['clearance_error'] = null;
                }
            } catch (\Throwable) {
                $fullRes['clearance_status'] = null;
                $fullRes['clearance_number'] = null;
                $fullRes['clearance_error'] = null;
            }

            if ($cin === $date) {
                $checkInsByProp[$pid][] = $fullRes;
            }
            if ($cout === $date) {
                $checkOutsByProp[$pid][] = $fullRes;
            }
            if ($cin <= $date && $cout > $date) {
                $activeStaysByProp[$pid] = $fullRes;
            }
        }

        /** @var array<string|int, DayPropertyDetail> $propertyDetails */
        $propertyDetails = [];
        $occupiedUnits = 0;
        $combinedRate = 0;
        $hasTurnover = false;
        $hasMaintenance = !empty($dayMaintenanceBlocks);
        /** @var list<string> $allWarnings */
        $allWarnings = [];
        /** @var list<string> $allReservationUids */
        $allReservationUids = [];
        $dismissedMap = array_fill_keys($this->dismissalRepo->getDismissedAlertIds($filter), true);

        foreach ($targetProps as $pid) {
            $rateInfo = $this->calculateNightlyRate($pid, $date, $tiers1606, $tiers1707);
            $combinedRate += $rateInfo['amount'];

            $mBlock = $maintenanceByProp[$pid] ?? null;
            $arrRes = $checkInsByProp[$pid][0] ?? null;
            $depRes = $checkOutsByProp[$pid][0] ?? null;
            $actRes = $activeStaysByProp[$pid] ?? null;

            /** @var list<OperationsEvent> $propMovements */
            $propMovements = [];
            foreach ($events as $event) {
                if ($event->propertyId === $pid) {
                    $propMovements[] = $event;
                }
            }

            if ($actRes !== null) {
                $allReservationUids[] = (string) $actRes['reservation_uid'];
            }
            if ($arrRes !== null) {
                $allReservationUids[] = (string) $arrRes['reservation_uid'];
            }
            if ($depRes !== null) {
                $allReservationUids[] = (string) $depRes['reservation_uid'];
            }

            $isPropTurnover = ($depRes !== null && $arrRes !== null);
            if ($isPropTurnover) {
                $hasTurnover = true;
            }

            $isOccupied = false;
            $status = 'Vacant';
            if ($mBlock !== null) {
                $status = 'Maintenance';
            } elseif ($isPropTurnover) {
                $status = 'Turnover';
                $isOccupied = true;
            } elseif ($actRes !== null) {
                $resStatus = (string) ($actRes['status'] ?? 'confirmed');
                if ($resStatus === 'confirmed') {
                    $status = 'Occupied';
                    $isOccupied = true;
                } else {
                    $status = 'Pending';
                }
            }

            if ($isOccupied) {
                $occupiedUnits++;
            }

            /** @var list<string> $propWarnings */
            $propWarnings = [];
            if ($mBlock !== null) {
                $propWarnings[] = "Maintenance hold: {$mBlock->reason}";
            }

            if (!$isPast) {
                $resToWarn = array_filter([$actRes, $arrRes], static fn(?array $r): bool => $r !== null);
                foreach ($resToWarn as $r) {
                    $rUid = (string) ($r['reservation_uid'] ?? '');
                    $gName = (string) ($r['guest_name'] ?? 'Guest');
                    $rCin = (string) ($r['check_in'] ?? '');
                    $rCout = (string) ($r['check_out'] ?? '');
                    $isWithinLookahead = ($rCout > $todayStr && $rCin <= $lookaheadMax);
                    if (empty($r['registry_completed']) && $isWithinLookahead && !isset($dismissedMap['registry_' . $rUid])) {
                        $msg = "Guest registry incomplete ({$gName})";
                        if (!in_array($msg, $propWarnings, true)) {
                            $propWarnings[] = $msg;
                        }
                    }
                    if ($rCout > $todayStr && ($r['clearance_status'] ?? '') === 'failed' && !isset($dismissedMap['clearance_' . $rUid])) {
                        $msg = "Condominium clearance failed ({$gName})";
                        if (!in_array($msg, $propWarnings, true)) {
                            $propWarnings[] = $msg;
                        }
                    }
                }
            }

            foreach ($propWarnings as $w) {
                if (!in_array($w, $allWarnings, true)) {
                    $allWarnings[] = $w;
                }
            }

            $propertyName = "Property {$pid}";
            $propertyDetails[$pid] = new DayPropertyDetail(
                propertyId: $pid,
                propertyName: $propertyName,
                status: $status,
                isOccupied: $isOccupied,
                nightlyRate: $rateInfo['amount'],
                rateInfo: $rateInfo,
                activeReservation: $actRes,
                departingReservation: $depRes,
                arrivingReservation: $arrRes,
                maintenanceBlock: $mBlock,
                movements: $propMovements,
                warnings: $propWarnings
            );
        }

        $allReservationUids = array_values(array_unique($allReservationUids));

        $capacityLevel = 'vacant';
        if ($hasMaintenance && $occupiedUnits === 0) {
            $capacityLevel = 'maintenance';
        } elseif ($occupiedUnits === 0) {
            $capacityLevel = 'vacant';
        } elseif ($occupiedUnits < $totalUnits) {
            $capacityLevel = 'partial';
        } else {
            $capacityLevel = 'full';
        }

        return new DayDetailViewData(
            date: $date,
            formattedDate: $formattedDate,
            dayOfWeekName: $dayOfWeekName,
            propertyFilter: $filter,
            totalUnits: $totalUnits,
            occupiedUnits: $occupiedUnits,
            capacityLevel: $capacityLevel,
            isToday: $isToday,
            isPast: $isPast,
            hasTurnover: $hasTurnover,
            hasMaintenance: $hasMaintenance,
            hasWarning: count($allWarnings) > 0,
            combinedRate: $combinedRate,
            properties: $propertyDetails,
            movements: $events,
            maintenanceBlocks: $dayMaintenanceBlocks,
            warnings: $allWarnings,
            reservationUids: $allReservationUids,
            totalProperties: $totalUnits,
            occupiedProperties: $occupiedUnits
        );
    }
}
