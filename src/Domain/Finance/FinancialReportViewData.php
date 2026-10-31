<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Authoritative read model returned by FinancialReportEngineInterface::generate().
 */
final class FinancialReportViewData
{
    /**
     * @param array<string, FinancialMetricComparison> $deltas
     * @param list<FinancialReportSlice> $monthlyTrends
     * @param list<ParafiscalPeriod> $parafiscalPeriods
     */
    public function __construct(
        public readonly FinancialReportFilter $filter,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $periodLabel,
        public readonly string $comparisonStart,
        public readonly string $comparisonEnd,
        public readonly string $comparisonLabel,
        public readonly FinancialReportSlice $current,
        public readonly FinancialReportSlice $previous,
        public readonly array $deltas,
        public readonly FinancialReportSlice $portfolioSlice,
        public readonly FinancialReportSlice $property1606Slice,
        public readonly FinancialReportSlice $property1707Slice,
        public readonly array $monthlyTrends = [],
        public readonly array $parafiscalPeriods = []
    ) {
    }

    /**
     * Returns evaluated ParafiscalPeriod rows filtered by the given property filter ('all', '1606', '1707').
     *
     * @return list<ParafiscalPeriod>
     */
    public function getParafiscalPeriodsForProperty(?string $propertyFilter = null): array
    {
        $scope = $propertyFilter ?? $this->filter->propertyId;
        if (!in_array($scope, ['1606', '1707'], true)) {
            return $this->parafiscalPeriods;
        }

        return array_values(array_filter(
            $this->parafiscalPeriods,
            static fn (ParafiscalPeriod $p): bool => $p->propertyId === $scope
        ));
    }

    /**
     * Returns current in-progress ParafiscalPeriod rows scoped to the active property filter.
     *
     * @return list<ParafiscalPeriod>
     */
    public function getInProgressParafiscalPeriods(?string $propertyFilter = null): array
    {
        return array_values(array_filter(
            $this->getParafiscalPeriodsForProperty($propertyFilter),
            static fn (ParafiscalPeriod $p): bool => $p->isInProgress()
        ));
    }

    /**
     * Returns closed unsettled ('pending' or 'overdue') ParafiscalPeriod rows scoped to the active property filter.
     *
     * @return list<ParafiscalPeriod>
     */
    public function getUnsettledClosedParafiscalPeriods(?string $propertyFilter = null): array
    {
        return array_values(array_filter(
            $this->getParafiscalPeriodsForProperty($propertyFilter),
            static fn (ParafiscalPeriod $p): bool => $p->isClosedUnsettled()
        ));
    }

    public function isMultiMonth(): bool
    {
        if (in_array($this->filter->period, ['this_quarter', 'ytd', 'last_12_months', 'last_year'], true)) {
            return count($this->monthlyTrends) >= 1;
        }

        return count($this->monthlyTrends) > 1;
    }

    public function showMultiPropertyMatrix(): bool
    {
        return $this->filter->propertyId === 'all';
    }

    public function isSingleChannelFiltered(): bool
    {
        return $this->filter->source !== 'all';
    }
}
