<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;

/**
 * Deep domain interface for accrual P&L, 50/50 shared expense allocation, and hospitality KPI computation.
 */
interface FinancialReportEngineInterface
{
    public function generate(
        FinancialReportFilter $filter,
        ?DateTimeImmutable $now = null
    ): FinancialReportViewData;

    /**
     * Evaluates all contiguous quarterly Parafiscal Periods ('YYYY-Q1' through 'YYYY-Q4')
     * for the given property filter ('all', '1606', '1707').
     *
     * @return list<ParafiscalPeriod>
     */
    public function evaluateParafiscalPeriods(
        string $propertyFilter = 'all',
        ?DateTimeImmutable $now = null
    ): array;

    /**
     * Evaluates a single quarterly Parafiscal Period ('YYYY-Q1' through 'YYYY-Q4')
     * for a specific Property ('1606' or '1707').
     */
    public function evaluateSingleParafiscalPeriod(
        string $periodKey,
        string $propertyId,
        ?DateTimeImmutable $now = null
    ): ParafiscalPeriod;
}
