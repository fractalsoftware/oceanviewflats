<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Immutable P&L and hospitality KPI aggregation slice for a specific property scope and reporting window.
 */
final class FinancialReportSlice
{
    /**
     * @param list<string> $unpricedAirbnbUids
     * @param array<string, array{revenue: float, active_revenue: float, policy_retention: float, nights: int, count: int}> $bySource
     * @param array<string, float> $byCategory
     * @param list<AttributedReservationRow> $attributedReservations
     * @param list<AllocatedExpenseRow> $allocatedExpenses
     * @param list<OperationalExpense> $expenses
     */
    public function __construct(
        public readonly string $propertyId,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $periodLabel,
        public readonly int $daysInPeriod,
        public readonly int $totalCalendarNights,
        public readonly int $deductedNights,
        public readonly int $availableNights,
        public readonly int $occupiedNights,
        public readonly int $pricedOccupiedNights,
        public readonly int $unpricedAirbnbCount,
        public readonly array $unpricedAirbnbUids,
        public readonly int $activeStaysCount,
        public readonly float $averageStayLengthNights,
        public readonly int $cancelledWithRetentionCount,
        public readonly float $grossCharged,
        public readonly float $refundedAmount,
        public readonly float $activeStayRevenue,
        public readonly float $policyRetention,
        public readonly float $grossRevenue,
        public readonly float $occupancyPct,
        public readonly float $adr,
        public readonly float $revpar,
        public readonly float $directExpenses,
        public readonly float $allocatedSharedExpenses,
        public readonly float $rawSharedExpenses,
        public readonly float $totalExpenses,
        public readonly float $noi,
        public readonly float $noiMarginPct,
        public readonly float $expenseRatioPct,
        public readonly float $allSourcesGrossRevenue,
        public readonly float $allSourcesNoi,
        public readonly float $allSourcesNoiMarginPct,
        public readonly float $allSourcesExpenseRatioPct,
        public readonly array $bySource,
        public readonly array $byCategory,
        public readonly array $attributedReservations,
        public readonly array $allocatedExpenses,
        public readonly array $expenses
    ) {
    }
}
