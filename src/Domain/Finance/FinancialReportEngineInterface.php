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
}
