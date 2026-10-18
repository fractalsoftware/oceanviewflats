<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockSourceInterface;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Authoritative domain engine for accrual-prorated P&L, verified-settlement Policy Retention,
 * per-night capacity deduplication, and 50/50 shared Operational Expense allocation.
 */
final class FinancialReportEngine implements FinancialReportEngineInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly MaintenanceBlockSourceInterface $maintenanceBlockSource,
        private readonly OperationalExpenseRepositoryInterface $expenseRepository
    ) {
    }

    public function generate(
        FinancialReportFilter $filter,
        ?DateTimeImmutable $now = null
    ): FinancialReportViewData {
        [$periodStart, $periodEnd, $periodLabel] = $filter->resolveDateWindow($now);
        [$compStart, $compEnd, $compLabel] = $filter->resolveComparisonWindow($periodStart, $periodEnd);

        $blockedNights1606 = $this->maintenanceBlockSource->getBlockedNights('1606');
        $blockedNights1707 = $this->maintenanceBlockSource->getBlockedNights('1707');

        $currentReservations = $this->reservationRepository->findOverlappingRange($periodStart, $periodEnd, 'all');
        $currentExpenses = $this->expenseRepository->listForPeriod(
            startDate: $periodStart,
            endDate: $periodEnd,
            propertyId: 'all',
            category: $filter->category,
            search: $filter->search
        );

        $previousReservations = $this->reservationRepository->findOverlappingRange($compStart, $compEnd, 'all');
        $previousExpenses = $this->expenseRepository->listForPeriod(
            startDate: $compStart,
            endDate: $compEnd,
            propertyId: 'all',
            category: $filter->category,
            search: $filter->search
        );

        $portfolioSlice = $this->computeSlice(
            propertyFilter: 'all',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periodLabel: $periodLabel,
            sourceFilter: $filter->source,
            reservations: $currentReservations,
            expenses: $currentExpenses,
            blockedNights1606: $blockedNights1606,
            blockedNights1707: $blockedNights1707
        );

        $property1606Slice = $this->computeSlice(
            propertyFilter: '1606',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periodLabel: $periodLabel,
            sourceFilter: $filter->source,
            reservations: $currentReservations,
            expenses: $currentExpenses,
            blockedNights1606: $blockedNights1606,
            blockedNights1707: $blockedNights1707
        );

        $property1707Slice = $this->computeSlice(
            propertyFilter: '1707',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periodLabel: $periodLabel,
            sourceFilter: $filter->source,
            reservations: $currentReservations,
            expenses: $currentExpenses,
            blockedNights1606: $blockedNights1606,
            blockedNights1707: $blockedNights1707
        );

        $currentSlice = match ($filter->propertyId) {
            '1606' => $property1606Slice,
            '1707' => $property1707Slice,
            'shared' => $this->computeSlice(
                propertyFilter: 'shared',
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                periodLabel: $periodLabel,
                sourceFilter: $filter->source,
                reservations: $currentReservations,
                expenses: $currentExpenses,
                blockedNights1606: $blockedNights1606,
                blockedNights1707: $blockedNights1707
            ),
            default => $portfolioSlice,
        };

        $previousSlice = $this->computeSlice(
            propertyFilter: $filter->propertyId,
            periodStart: $compStart,
            periodEnd: $compEnd,
            periodLabel: $compLabel,
            sourceFilter: $filter->source,
            reservations: $previousReservations,
            expenses: $previousExpenses,
            blockedNights1606: $blockedNights1606,
            blockedNights1707: $blockedNights1707
        );

        $deltas = [
            'gross_revenue' => new FinancialMetricComparison(
                current: $currentSlice->grossRevenue,
                previous: $previousSlice->grossRevenue,
                comparisonLabel: $compLabel,
                invertColors: false
            ),
            'total_expenses' => new FinancialMetricComparison(
                current: $currentSlice->totalExpenses,
                previous: $previousSlice->totalExpenses,
                comparisonLabel: $compLabel,
                invertColors: true
            ),
            'noi' => new FinancialMetricComparison(
                current: $filter->source === 'all' ? $currentSlice->noi : $currentSlice->allSourcesNoi,
                previous: $filter->source === 'all' ? $previousSlice->noi : $previousSlice->allSourcesNoi,
                comparisonLabel: $compLabel,
                invertColors: false
            ),
            'occupancy_pct' => new FinancialMetricComparison(
                current: $currentSlice->occupancyPct,
                previous: $previousSlice->occupancyPct,
                comparisonLabel: $compLabel,
                invertColors: false
            ),
            'adr' => new FinancialMetricComparison(
                current: $currentSlice->adr,
                previous: $previousSlice->adr,
                comparisonLabel: $compLabel,
                invertColors: false
            ),
            'revpar' => new FinancialMetricComparison(
                current: $currentSlice->revpar,
                previous: $previousSlice->revpar,
                comparisonLabel: $compLabel,
                invertColors: false
            ),
        ];

        $monthlyTrends = $this->computeMonthlyTrends(
            propertyFilter: $filter->propertyId,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            sourceFilter: $filter->source,
            reservations: $currentReservations,
            expenses: $currentExpenses,
            blockedNights1606: $blockedNights1606,
            blockedNights1707: $blockedNights1707
        );

        return new FinancialReportViewData(
            filter: $filter,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periodLabel: $periodLabel,
            comparisonStart: $compStart,
            comparisonEnd: $compEnd,
            comparisonLabel: $compLabel,
            current: $currentSlice,
            previous: $previousSlice,
            deltas: $deltas,
            portfolioSlice: $portfolioSlice,
            property1606Slice: $property1606Slice,
            property1707Slice: $property1707Slice,
            monthlyTrends: $monthlyTrends
        );
    }

    /**
     * @param list<Reservation> $reservations
     * @param list<OperationalExpense> $expenses
     * @param list<string> $blockedNights1606
     * @param list<string> $blockedNights1707
     */
    private function computeSlice(
        string $propertyFilter,
        string $periodStart,
        string $periodEnd,
        string $periodLabel,
        string $sourceFilter,
        array $reservations,
        array $expenses,
        array $blockedNights1606,
        array $blockedNights1707
    ): FinancialReportSlice {
        $evaluatedProperties = match ($propertyFilter) {
            '1606' => ['1606'],
            '1707' => ['1707'],
            'shared' => [],
            default => ['1606', '1707'],
        };

        $periodDates = self::datesInWindow($periodStart, $periodEnd);
        $daysInPeriod = count($periodDates);
        $periodDateSet = array_fill_keys($periodDates, true);

        $allCommercialNights = [];
        $matchingOccupiedNights = [];
        $matchingPricedNights = [];
        $blockedOrOwnerNights = [];

        foreach ($evaluatedProperties as $propId) {
            $propBlocked = $propId === '1606' ? $blockedNights1606 : $blockedNights1707;
            foreach ($propBlocked as $blockedDate) {
                if (isset($periodDateSet[$blockedDate])) {
                    $blockedOrOwnerNights[$propId . ':' . $blockedDate] = true;
                }
            }
        }

        $activeStayRevenue = 0.0;
        $policyRetention = 0.0;
        $refundedAmount = 0.0;
        $grossCharged = 0.0;
        $allSourcesGrossRevenue = 0.0;

        $unpricedAirbnbCount = 0;
        $unpricedAirbnbUids = [];
        $activeStaysCount = 0;
        $totalActiveStayNights = 0;
        $cancelledWithRetentionCount = 0;

        /** @var list<AttributedReservationRow> $attributedReservations */
        $attributedReservations = [];

        /** @var array<string, array{revenue: float, active_revenue: float, policy_retention: float, nights: int, count: int}> $bySource */
        $bySource = [];
        foreach (BookingSourceGroup::commercialCases() as $srcGroup) {
            $bySource[$srcGroup->value] = [
                'revenue' => 0.0,
                'active_revenue' => 0.0,
                'policy_retention' => 0.0,
                'nights' => 0,
                'count' => 0,
            ];
        }

        foreach ($reservations as $reservation) {
            if (!in_array($reservation->propertyId, $evaluatedProperties, true)) {
                continue;
            }

            $allResNights = $reservation->nights();
            $totalNights = count($allResNights);
            if ($totalNights === 0) {
                continue;
            }

            $inPeriodDates = [];
            foreach ($allResNights as $nd) {
                if (isset($periodDateSet[$nd])) {
                    $inPeriodDates[] = $nd;
                }
            }

            $inPeriodNights = count($inPeriodDates);
            if ($inPeriodNights === 0) {
                continue;
            }

            $channelGroup = BookingSourceGroup::fromReservationSource($reservation->source);
            $factor = $inPeriodNights / $totalNights;
            $netContract = max(0.0, $reservation->totalPrice - $reservation->refundedAmount);
            $proratedCharged = $reservation->totalPrice * $factor;
            $proratedRefunded = $reservation->refundedAmount * $factor;
            $proratedNet = $netContract * $factor;

            if ($reservation->status->isConfirmed()) {
                if ($channelGroup === BookingSourceGroup::OWNER_STAY) {
                    foreach ($inPeriodDates as $nd) {
                        $blockedOrOwnerNights[$reservation->propertyId . ':' . $nd] = true;
                    }
                    continue;
                }

                // Confirmed commercial stay (including past concluded stays)
                foreach ($inPeriodDates as $nd) {
                    $allCommercialNights[$reservation->propertyId . ':' . $nd] = true;
                }

                $allSourcesGrossRevenue += $proratedNet;

                if ($sourceFilter !== 'all' && $channelGroup->value !== $sourceFilter) {
                    continue;
                }

                $bySource[$channelGroup->value]['revenue'] = round($bySource[$channelGroup->value]['revenue'] + $proratedNet, 2);
                $bySource[$channelGroup->value]['active_revenue'] = round($bySource[$channelGroup->value]['active_revenue'] + $proratedNet, 2);
                $bySource[$channelGroup->value]['nights'] += $inPeriodNights;
                $bySource[$channelGroup->value]['count']++;

                foreach ($inPeriodDates as $nd) {
                    $nightKey = $reservation->propertyId . ':' . $nd;
                    $matchingOccupiedNights[$nightKey] = true;
                    if ($netContract > 0.0) {
                        $matchingPricedNights[$nightKey] = true;
                    }
                }

                $activeStayRevenue += $proratedNet;
                $grossCharged += $proratedCharged;
                $refundedAmount += $proratedRefunded;
                $activeStaysCount++;
                $totalActiveStayNights += $totalNights;

                $isUnpricedAirbnb = ($channelGroup === BookingSourceGroup::AIRBNB && $netContract <= 0.0);
                if ($isUnpricedAirbnb) {
                    $unpricedAirbnbCount++;
                    $unpricedAirbnbUids[] = $reservation->reservationUid;
                }

                $attributedReservations[] = new AttributedReservationRow(
                    reservation: $reservation,
                    reservationUid: $reservation->reservationUid,
                    propertyId: $reservation->propertyId,
                    guestName: $reservation->guestName,
                    source: $reservation->source,
                    channelGroup: $channelGroup,
                    status: $reservation->status->value,
                    paymentStatus: $reservation->paymentStatus,
                    checkIn: $reservation->checkIn,
                    checkOut: $reservation->checkOut,
                    totalNights: $totalNights,
                    inPeriodNights: $inPeriodNights,
                    prorationFactor: $factor,
                    totalPrice: round($reservation->totalPrice, 2),
                    refundedAmount: round($reservation->refundedAmount, 2),
                    contractNet: round($netContract, 2),
                    proratedCharged: round($proratedCharged, 2),
                    proratedRefunded: round($proratedRefunded, 2),
                    attributedRevenue: round($proratedNet, 2),
                    isRetention: false,
                    isUnpricedAirbnb: $isUnpricedAirbnb
                );
            } elseif ($reservation->status->isCancelled()) {
                if ($channelGroup === BookingSourceGroup::OWNER_STAY) {
                    continue;
                }

                $paymentStatusNorm = strtolower(trim((string) ($reservation->paymentStatus ?? '')));
                $isSettled = in_array($paymentStatusNorm, ['approved', 'partially_refunded', 'refunded'], true)
                    || $reservation->refundedAmount > 0.0;

                if (!$isSettled) {
                    continue;
                }

                $allSourcesGrossRevenue += $proratedNet;

                if ($sourceFilter !== 'all' && $channelGroup->value !== $sourceFilter) {
                    continue;
                }

                $bySource[$channelGroup->value]['revenue'] = round($bySource[$channelGroup->value]['revenue'] + $proratedNet, 2);
                $bySource[$channelGroup->value]['policy_retention'] = round($bySource[$channelGroup->value]['policy_retention'] + $proratedNet, 2);

                $policyRetention += $proratedNet;
                $grossCharged += $proratedCharged;
                $refundedAmount += $proratedRefunded;

                if ($proratedNet > 0.0) {
                    $cancelledWithRetentionCount++;
                    $attributedReservations[] = new AttributedReservationRow(
                        reservation: $reservation,
                        reservationUid: $reservation->reservationUid,
                        propertyId: $reservation->propertyId,
                        guestName: $reservation->guestName,
                        source: $reservation->source,
                        channelGroup: $channelGroup,
                        status: $reservation->status->value,
                        paymentStatus: $reservation->paymentStatus,
                        checkIn: $reservation->checkIn,
                        checkOut: $reservation->checkOut,
                        totalNights: $totalNights,
                        inPeriodNights: $inPeriodNights,
                        prorationFactor: $factor,
                        totalPrice: round($reservation->totalPrice, 2),
                        refundedAmount: round($reservation->refundedAmount, 2),
                        contractNet: round($netContract, 2),
                        proratedCharged: round($proratedCharged, 2),
                        proratedRefunded: round($proratedRefunded, 2),
                        attributedRevenue: round($proratedNet, 2),
                        isRetention: true,
                        isUnpricedAirbnb: false
                    );
                }
            }
        }

        // Per-night capacity & occupancy evaluation with commercial stay precedence
        $totalCalendarNights = count($evaluatedProperties) * $daysInPeriod;
        $deductedNights = 0;
        $availableNights = 0;
        $occupiedNights = 0;
        $pricedOccupiedNights = 0;

        foreach ($evaluatedProperties as $propId) {
            foreach ($periodDates as $dateStr) {
                $nightKey = $propId . ':' . $dateStr;
                if (isset($allCommercialNights[$nightKey])) {
                    $availableNights++;
                    if (isset($matchingOccupiedNights[$nightKey])) {
                        $occupiedNights++;
                    }
                    if (isset($matchingPricedNights[$nightKey])) {
                        $pricedOccupiedNights++;
                    }
                } elseif (isset($blockedOrOwnerNights[$nightKey])) {
                    $deductedNights++;
                } else {
                    $availableNights++;
                }
            }
        }

        $activeStayRevenue = round($activeStayRevenue, 2);
        $policyRetention = round($policyRetention, 2);
        $refundedAmount = round($refundedAmount, 2);
        $grossCharged = round($grossCharged, 2);
        $grossRevenue = round($activeStayRevenue + $policyRetention, 2);
        $allSourcesGrossRevenue = round($allSourcesGrossRevenue, 2);

        $occupancyPct = $availableNights > 0
            ? round(($occupiedNights / $availableNights) * 100.0, 2)
            : 0.0;
        $adr = $pricedOccupiedNights > 0
            ? round($activeStayRevenue / $pricedOccupiedNights, 2)
            : 0.0;
        $revpar = $availableNights > 0
            ? round($grossRevenue / $availableNights, 2)
            : 0.0;
        $averageStayLengthNights = $activeStaysCount > 0
            ? round($totalActiveStayNights / $activeStaysCount, 1)
            : 0.0;

        // Operational Expenses & 50/50 Shared Allocation
        $directExpenses = 0.0;
        $allocatedSharedExpenses = 0.0;
        $rawSharedExpenses = 0.0;

        /** @var array<string, float> $byCategory */
        $byCategory = [];
        foreach (ExpenseCategory::cases() as $catCase) {
            $byCategory[$catCase->value] = 0.0;
        }

        /** @var list<AllocatedExpenseRow> $allocatedExpenses */
        $allocatedExpenses = [];
        /** @var list<OperationalExpense> $filteredExpenseEntities */
        $filteredExpenseEntities = [];

        foreach ($expenses as $expense) {
            if ($expense->expenseDate < $periodStart || $expense->expenseDate >= $periodEnd) {
                continue;
            }

            $allocationFactor = $expense->allocationFactorForProperty($propertyFilter);
            if ($allocationFactor <= 0.0) {
                continue;
            }

            $allocatedAmount = $expense->allocatedAmountForProperty($propertyFilter);
            if ($expense->propertyId === 'shared') {
                $allocatedSharedExpenses += $allocatedAmount;
                $rawSharedExpenses += $expense->amount;
            } else {
                $directExpenses += $allocatedAmount;
            }

            $byCategory[$expense->category->value] = round(
                ($byCategory[$expense->category->value] ?? 0.0) + $allocatedAmount,
                2
            );

            $allocatedExpenses[] = new AllocatedExpenseRow(
                expense: $expense,
                allocationFactor: $allocationFactor,
                allocatedAmount: $allocatedAmount
            );
            $filteredExpenseEntities[] = $expense;
        }

        $directExpenses = round($directExpenses, 2);
        $allocatedSharedExpenses = round($allocatedSharedExpenses, 2);
        $rawSharedExpenses = round($rawSharedExpenses, 2);
        $totalExpenses = round($directExpenses + $allocatedSharedExpenses, 2);

        $noi = round($grossRevenue - $totalExpenses, 2);
        $noiMarginPct = $grossRevenue > 0.0
            ? round(($noi / $grossRevenue) * 100.0, 2)
            : 0.0;
        $expenseRatioPct = $grossRevenue > 0.0
            ? round(($totalExpenses / $grossRevenue) * 100.0, 2)
            : 0.0;

        $allSourcesNoi = round($allSourcesGrossRevenue - $totalExpenses, 2);
        $allSourcesNoiMarginPct = $allSourcesGrossRevenue > 0.0
            ? round(($allSourcesNoi / $allSourcesGrossRevenue) * 100.0, 2)
            : 0.0;
        $allSourcesExpenseRatioPct = $allSourcesGrossRevenue > 0.0
            ? round(($totalExpenses / $allSourcesGrossRevenue) * 100.0, 2)
            : 0.0;

        return new FinancialReportSlice(
            propertyId: $propertyFilter,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            periodLabel: $periodLabel,
            daysInPeriod: $daysInPeriod,
            totalCalendarNights: $totalCalendarNights,
            deductedNights: $deductedNights,
            availableNights: $availableNights,
            occupiedNights: $occupiedNights,
            pricedOccupiedNights: $pricedOccupiedNights,
            unpricedAirbnbCount: $unpricedAirbnbCount,
            unpricedAirbnbUids: array_values(array_unique($unpricedAirbnbUids)),
            activeStaysCount: $activeStaysCount,
            averageStayLengthNights: $averageStayLengthNights,
            cancelledWithRetentionCount: $cancelledWithRetentionCount,
            grossCharged: $grossCharged,
            refundedAmount: $refundedAmount,
            activeStayRevenue: $activeStayRevenue,
            policyRetention: $policyRetention,
            grossRevenue: $grossRevenue,
            occupancyPct: $occupancyPct,
            adr: $adr,
            revpar: $revpar,
            directExpenses: $directExpenses,
            allocatedSharedExpenses: $allocatedSharedExpenses,
            rawSharedExpenses: $rawSharedExpenses,
            totalExpenses: $totalExpenses,
            noi: $noi,
            noiMarginPct: $noiMarginPct,
            expenseRatioPct: $expenseRatioPct,
            allSourcesGrossRevenue: $allSourcesGrossRevenue,
            allSourcesNoi: $allSourcesNoi,
            allSourcesNoiMarginPct: $allSourcesNoiMarginPct,
            allSourcesExpenseRatioPct: $allSourcesExpenseRatioPct,
            bySource: $bySource,
            byCategory: $byCategory,
            attributedReservations: $attributedReservations,
            allocatedExpenses: $allocatedExpenses,
            expenses: $filteredExpenseEntities
        );
    }

    /**
     * @param list<Reservation> $reservations
     * @param list<OperationalExpense> $expenses
     * @param list<string> $blockedNights1606
     * @param list<string> $blockedNights1707
     * @return list<FinancialReportSlice>
     */
    private function computeMonthlyTrends(
        string $propertyFilter,
        string $periodStart,
        string $periodEnd,
        string $sourceFilter,
        array $reservations,
        array $expenses,
        array $blockedNights1606,
        array $blockedNights1707
    ): array {
        if ($periodStart <= '0001-01-01' || $periodEnd >= '9000-01-01' || $periodEnd <= $periodStart) {
            return [];
        }

        $startDt = new DateTimeImmutable($periodStart);
        $endDt = new DateTimeImmutable($periodEnd);
        $cursor = $startDt->modify('first day of this month');

        $months = [];
        $safetyCounter = 0;
        while ($cursor < $endDt && $safetyCounter < 36) {
            $safetyCounter++;
            $nextMonth = $cursor->modify('first day of next month');
            $sliceStart = $cursor > $startDt ? $cursor->format('Y-m-d') : $periodStart;
            $sliceEnd = $nextMonth < $endDt ? $nextMonth->format('Y-m-d') : $periodEnd;

            if ($sliceEnd > $sliceStart) {
                $months[] = $this->computeSlice(
                    propertyFilter: $propertyFilter,
                    periodStart: $sliceStart,
                    periodEnd: $sliceEnd,
                    periodLabel: $cursor->format('Y-m'),
                    sourceFilter: $sourceFilter,
                    reservations: $reservations,
                    expenses: $expenses,
                    blockedNights1606: $blockedNights1606,
                    blockedNights1707: $blockedNights1707
                );
            }

            $cursor = $nextMonth;
        }

        return $months;
    }

    /**
     * Returns all YYYY-MM-DD calendar dates in the half-open interval [$startDate, $endDate).
     *
     * @return list<string>
     */
    public static function datesInWindow(string $startDate, string $endDate): array
    {
        if ($startDate <= '0001-01-01' || $endDate >= '9000-01-01' || $endDate <= $startDate) {
            return [];
        }

        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        $diffDays = (int) $start->diff($end)->days;
        if ($diffDays <= 0 || $diffDays > 1500) {
            return [];
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        $dates = [];
        foreach ($period as $dt) {
            $dates[] = $dt->format('Y-m-d');
        }

        return $dates;
    }
}
