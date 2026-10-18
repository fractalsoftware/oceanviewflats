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
        public readonly array $monthlyTrends = []
    ) {
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
