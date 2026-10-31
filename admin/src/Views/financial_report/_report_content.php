<?php
/**
 * Main Financial Report Content Partial (#financial-report-content).
 *
 * @var \OceanViewFlats\Domain\Finance\FinancialReportViewData $report
 * @var \OceanViewFlats\Domain\Finance\FinancialReportSlice $slice
 * @var list<\OceanViewFlats\Domain\Finance\OperationalExpense> $expenses
 * @var array<string, string> $filters
 * @var array<string, float> $summary
 * @var array<string, float> $categoryTotals
 * @var string $csrfToken
 * @var bool $oob
 * @var string|null $flashMessage
 */

use OceanViewFlats\Domain\Finance\BookingSourceGroup;
use OceanViewFlats\Domain\Finance\ExpenseCategory;

$oobAttr = !empty($oob) ? ' hx-swap-oob="outerHTML"' : '';
$propertyFilter = (string) ($filters['property_id'] ?? 'all');
$sourceFilter = (string) ($filters['source'] ?? 'all');
$activeTab = (string) ($filters['tab'] ?? 'expenses');
$periodLabel = (string) ($filters['period_label'] ?? $slice->periodLabel);

$grossRevDelta = $report->deltas['gross_revenue'] ?? null;
$expDelta = $report->deltas['total_expenses'] ?? null;
$noiDelta = $report->deltas['noi'] ?? null;
$occDelta = $report->deltas['occupancy_pct'] ?? null;
$adrDelta = $report->deltas['adr'] ?? null;
$revparDelta = $report->deltas['revpar'] ?? null;

$isChannelScoped = $report->isSingleChannelFiltered();
$selectedSourceGroup = $isChannelScoped ? BookingSourceGroup::tryFrom($sourceFilter) : null;

$displayedNoi = $isChannelScoped ? $slice->allSourcesNoi : $slice->noi;
$displayedNoiMarginPct = $isChannelScoped ? $slice->allSourcesNoiMarginPct : $slice->noiMarginPct;
$displayedExpenseRatioPct = $isChannelScoped ? $slice->allSourcesExpenseRatioPct : $slice->expenseRatioPct;

$activeQueryParams = array_filter([
    'property_id' => $filters['property_id'] ?? 'all',
    'period' => $filters['period'] ?? 'this_month',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'compare' => $filters['compare'] ?? 'prev_period',
    'source' => $filters['source'] ?? 'all',
    'category' => $filters['category'] ?? 'all',
    'tab' => $filters['tab'] ?? 'expenses',
    'search' => $filters['search'] ?? '',
], static fn (string $v): bool => $v !== '');

if (!function_exists('formatCopCurrency')) {
    function formatCopCurrency(float $amount): string
    {
        return '$ ' . number_format(round($amount), 0, ',', '.') . ' COP';
    }
}

if (!function_exists('formatCopCompactCurrency')) {
    function formatCopCompactCurrency(float $amount): string
    {
        return '$ ' . number_format(round($amount), 0, ',', '.');
    }
}
?>
<div id="financial-report-content" class="space-y-6"<?= $oobAttr ?>>

    <?php if (!empty($flashMessage)): ?>
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Unpriced ($0 COP) Airbnb Host Payout Warning Banner -->
    <?php if ($slice->unpricedAirbnbCount > 0): ?>
        <div id="unpriced-airbnb-banner" class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start space-x-3">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 font-bold text-xs shrink-0 mt-0.5">!</span>
                <div>
                    <p class="text-xs font-bold text-amber-900">
                        <?= $slice->unpricedAirbnbCount ?> External <?= $slice->unpricedAirbnbCount === 1 ? 'Reservation' : 'Reservations' ?> (Airbnb) in this period <?= $slice->unpricedAirbnbCount === 1 ? 'has' : 'have' ?> a $0 COP host payout
                    </p>
                    <p class="text-[11px] text-amber-800 mt-0.5">
                        Included in Occupied Nights (<?= $slice->occupiedNights ?> nights), but excluded from the Average Daily Rate denominator (<?= $slice->pricedOccupiedNights ?> priced nights) so unentered payouts do not depress ADR.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <?php foreach ($slice->unpricedAirbnbUids as $unpricedUid): ?>
                    <a href="/reservations?search=<?= urlencode($unpricedUid) ?>"
                       hx-get="/reservations/<?= urlencode($unpricedUid) ?>/edit"
                       hx-target="#modal-container"
                       hx-swap="innerHTML"
                       class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-2xs cursor-pointer">
                        Set Host Payout (<?= htmlspecialchars($unpricedUid, ENT_QUOTES, 'UTF-8') ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Single Booking Source Channel Contribution Notice Banner -->
    <?php if ($isChannelScoped && $selectedSourceGroup !== null):
        $allSourcesQuery = http_build_query(array_merge($activeQueryParams, ['source' => 'all']));
    ?>
        <div id="channel-contribution-notice" class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-xs text-indigo-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span>
                <strong>Channel Contribution View (<?= htmlspecialchars($selectedSourceGroup->label(), ENT_QUOTES, 'UTF-8') ?>):</strong>
                Revenue, Average Daily Rate, RevPAR, and channel Occupancy % reflect this channel's contribution. Operational Expenses and Net Operating Income are evaluated at the Property/Portfolio (All Sources) level.
            </span>
            <a href="/financial-report?<?= htmlspecialchars($allSourcesQuery, ENT_QUOTES, 'UTF-8') ?>"
               hx-get="/financial-report?<?= htmlspecialchars($allSourcesQuery, ENT_QUOTES, 'UTF-8') ?>"
               hx-target="#financial-report-content"
               hx-swap="outerHTML"
               hx-push-url="true"
               onclick="var s=document.getElementById('source-select'); if(s) s.value='all';"
               class="text-indigo-700 underline font-semibold shrink-0 cursor-pointer">
                Show All Sources
            </a>
        </div>
    <?php endif; ?>

    <!-- ROW 1: 3 PRIMARY P&L HERO CARDS -->
    <div id="pnl-hero-cards" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Card 1: Gross Revenue (Realized) -->
        <div id="card-gross-revenue" class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Gross Revenue (Realized)</span>
                <?php if ($grossRevDelta !== null): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= htmlspecialchars($grossRevDelta->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($grossRevDelta->formatPercentageText(), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($grossRevDelta->formatCopDeltaText(), ENT_QUOTES, 'UTF-8') ?>)
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-2xl font-extrabold text-gray-900 mt-2 tabular-nums">
                <?= formatCopCurrency($slice->grossRevenue) ?>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-3 gap-2 text-[11px]">
                <div>
                    <span class="text-gray-400 block">Active Stays</span>
                    <span class="font-semibold text-gray-800 tabular-nums"><?= formatCopCompactCurrency($slice->activeStayRevenue) ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block">Policy Retention</span>
                    <span class="font-semibold text-amber-700 tabular-nums"><?= formatCopCompactCurrency($slice->policyRetention) ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block">Refunded</span>
                    <span class="font-semibold text-rose-600 tabular-nums"><?= formatCopCompactCurrency($slice->refundedAmount) ?></span>
                </div>
            </div>
        </div>

        <!-- Card 2: Operational Expenses -->
        <div id="card-operational-expenses" class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Operational Expenses</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700">
                    Expense Ratio: <?= number_format($displayedExpenseRatioPct, 1) ?>%
                </span>
            </div>
            <div class="text-2xl font-extrabold text-gray-900 mt-2 tabular-nums">
                <?= formatCopCurrency($slice->totalExpenses) ?>
            </div>
            <div class="mt-1 flex items-center justify-between text-[11px]">
                <?php if ($isChannelScoped): ?>
                    <span class="text-indigo-700 font-medium">All Sources (Property/Portfolio Level)</span>
                <?php else: ?>
                    <span class="text-gray-400"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($expDelta !== null): ?>
                    <span class="font-semibold <?= htmlspecialchars($expDelta->badgeClass(), ENT_QUOTES, 'UTF-8') ?> px-1.5 py-0.5 rounded">
                        <?= htmlspecialchars($expDelta->formatPercentageText(), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($expDelta->formatCopDeltaText(), ENT_QUOTES, 'UTF-8') ?>)
                    </span>
                <?php endif; ?>
            </div>
            <div class="mt-2.5 pt-2.5 border-t border-gray-100 grid grid-cols-2 gap-2 text-[11px]">
                <div>
                    <span class="text-gray-400 block">Direct Property Expenses</span>
                    <span class="font-semibold text-gray-800 tabular-nums"><?= formatCopCompactCurrency($slice->directExpenses) ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block">
                        <?= in_array($propertyFilter, ['1606', '1707'], true) ? 'Allocated Shared Expenses (50%)' : 'Shared Pool (100%)' ?>
                    </span>
                    <span class="font-semibold text-amber-700 tabular-nums"><?= formatCopCompactCurrency($slice->allocatedSharedExpenses) ?></span>
                    <?php if (in_array($propertyFilter, ['1606', '1707'], true) && $slice->rawSharedExpenses > 0.0): ?>
                        <span class="text-[10px] text-gray-400 block">50% of <?= formatCopCompactCurrency($slice->rawSharedExpenses) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 3: Net Operating Income (NOI) -->
        <div id="card-noi" class="bg-white rounded-xl border <?= $displayedNoi >= 0.0 ? 'border-emerald-200' : 'border-rose-200' ?> p-5 shadow-2xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Net Operating Income (NOI)</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $displayedNoi >= 0.0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                    NOI Margin: <?= number_format($displayedNoiMarginPct, 1) ?>%
                </span>
            </div>
            <div class="text-2xl font-extrabold <?= $displayedNoi >= 0.0 ? 'text-emerald-700' : 'text-rose-700' ?> mt-2 tabular-nums">
                <?= formatCopCurrency($displayedNoi) ?>
            </div>
            <?php if ($isChannelScoped): ?>
                <div class="text-[11px] text-indigo-700 font-medium mt-1">
                    Evaluated across All Sources (Gross Rev <?= formatCopCompactCurrency($slice->allSourcesGrossRevenue) ?> &minus; Operational Expenses)
                </div>
            <?php endif; ?>
            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between gap-2 text-[11px] text-gray-500">
                <span>Gross Revenue &minus; Operational Expenses</span>
                <?php if ($noiDelta !== null): ?>
                    <span class="font-semibold px-2 py-0.5 rounded-full <?= htmlspecialchars($noiDelta->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($noiDelta->formatPercentageText(), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($noiDelta->formatCopDeltaText(), ENT_QUOTES, 'UTF-8') ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ROW 2: 4 HOSPITALITY KPI CARDS -->
    <div id="hospitality-kpi-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Occupancy % -->
        <div id="card-occupancy" class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
            <div class="flex items-center justify-between gap-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Occupancy Rate</span>
                <?php if ($occDelta !== null): ?>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold <?= htmlspecialchars($occDelta->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($occDelta->formatPointsDeltaText(), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-xl font-extrabold text-gray-900 mt-1 tabular-nums">
                <?= number_format($slice->occupancyPct, 1) ?>%
            </div>
            <div class="text-[11px] text-gray-500 mt-1">
                <?= $slice->occupiedNights ?> Occupied / <?= $slice->availableNights ?> Available Nights (<?= $slice->deductedNights ?> Maint/Owner deducted)
            </div>
        </div>

        <!-- KPI 2: Average Daily Rate (ADR) -->
        <div id="card-adr" class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
            <div class="flex items-center justify-between gap-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Average Daily Rate (ADR)</span>
                <?php if ($slice->unpricedAirbnbCount > 0): ?>
                    <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">
                        <?= $slice->unpricedAirbnbCount ?> Unpriced OTA
                    </span>
                <?php elseif ($adrDelta !== null): ?>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold <?= htmlspecialchars($adrDelta->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($adrDelta->formatPercentageText(), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-xl font-extrabold text-gray-900 mt-1 tabular-nums">
                <?= formatCopCurrency($slice->adr) ?>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">
                Across <?= $slice->pricedOccupiedNights ?> priced Occupied Nights (excl. retention)
            </div>
        </div>

        <!-- KPI 3: RevPAR -->
        <div id="card-revpar" class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
            <div class="flex items-center justify-between gap-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">RevPAR</span>
                <?php if ($revparDelta !== null): ?>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold <?= htmlspecialchars($revparDelta->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($revparDelta->formatPercentageText(), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-xl font-extrabold text-gray-900 mt-1 tabular-nums">
                <?= formatCopCurrency($slice->revpar) ?>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">
                Gross Revenue &divide; <?= $slice->availableNights ?> Available Nights
            </div>
        </div>

        <!-- KPI 4: Stays & Cancellations -->
        <div id="card-stays-cancellations" class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
            <div class="flex items-center justify-between gap-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Stays &amp; Cancellations</span>
                <?php if ($slice->activeStaysCount > 0): ?>
                    <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-semibold">
                        Avg <?= number_format($slice->averageStayLengthNights, 1) ?>n
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-xl font-extrabold text-gray-900 mt-1 tabular-nums">
                <?= $slice->activeStaysCount ?> Active <?= $slice->activeStaysCount === 1 ? 'Stay' : 'Stays' ?>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">
                <?= $slice->cancelledWithRetentionCount ?> Settled <?= $slice->cancelledWithRetentionCount === 1 ? 'Cancellation' : 'Cancellations' ?> (<?= formatCopCompactCurrency($slice->policyRetention) ?> retained)
            </div>
        </div>
    </div>

    <!-- MULTI-PROPERTY COMPARISON MATRIX (Rendered when Property Filter = All Properties) -->
    <?php if ($report->showMultiPropertyMatrix()):
        $sAll = $report->portfolioSlice;
        $s1606 = $report->property1606Slice;
        $s1707 = $report->property1707Slice;

        $sAllNoi = $isChannelScoped ? $sAll->allSourcesNoi : $sAll->noi;
        $sAllNoiMargin = $isChannelScoped ? $sAll->allSourcesNoiMarginPct : $sAll->noiMarginPct;
        $s1606Noi = $isChannelScoped ? $s1606->allSourcesNoi : $s1606->noi;
        $s1606NoiMargin = $isChannelScoped ? $s1606->allSourcesNoiMarginPct : $s1606->noiMarginPct;
        $s1707Noi = $isChannelScoped ? $s1707->allSourcesNoi : $s1707->noi;
        $s1707NoiMargin = $isChannelScoped ? $s1707->allSourcesNoiMarginPct : $s1707->noiMarginPct;
    ?>
        <div id="multi-property-comparison-matrix" class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">
                        Multi-Property Financial &amp; Hospitality Matrix (<?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>)
                    </h2>
                    <p class="text-xs text-gray-500">
                        Side-by-side P&amp;L and KPI comparison across Portfolio Total, Property 1606, and Property 1707 (including 50/50 shared expense allocation).
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    1606 + 1707 = Portfolio Total
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs tabular-nums">
                    <thead class="bg-gray-100/80 text-[11px] font-bold uppercase text-gray-600">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left">Metric / Line Item</th>
                            <th scope="col" class="px-5 py-3 text-right bg-indigo-50/50 text-indigo-900">Portfolio Total (All)</th>
                            <th scope="col" class="px-5 py-3 text-right">Property 1606</th>
                            <th scope="col" class="px-5 py-3 text-right">Property 1707</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <!-- Section 1: Hospitality Capacity & Yield KPIs -->
                        <tr class="bg-gray-50/50 font-semibold text-gray-500 text-[11px] uppercase">
                            <td colspan="4" class="px-5 py-1.5">Hospitality Capacity &amp; Yield KPIs</td>
                        </tr>
                        <tr data-matrix-row="available-nights">
                            <td class="px-5 py-2.5 text-gray-700">Available Nights (vs. Total Calendar Nights)</td>
                            <td class="px-5 py-2.5 text-right font-semibold bg-indigo-50/20"><?= $sAll->availableNights ?> / <?= $sAll->totalCalendarNights ?> nights</td>
                            <td class="px-5 py-2.5 text-right"><?= $s1606->availableNights ?> / <?= $s1606->totalCalendarNights ?> nights</td>
                            <td class="px-5 py-2.5 text-right"><?= $s1707->availableNights ?> / <?= $s1707->totalCalendarNights ?> nights</td>
                        </tr>
                        <tr data-matrix-row="occupied-nights">
                            <td class="px-5 py-2.5 text-gray-700">Occupied Nights &amp; Occupancy %</td>
                            <td class="px-5 py-2.5 text-right font-bold bg-indigo-50/20"><?= $sAll->occupiedNights ?>n (<?= number_format($sAll->occupancyPct, 1) ?>%)</td>
                            <td class="px-5 py-2.5 text-right font-semibold"><?= $s1606->occupiedNights ?>n (<?= number_format($s1606->occupancyPct, 1) ?>%)</td>
                            <td class="px-5 py-2.5 text-right font-semibold"><?= $s1707->occupiedNights ?>n (<?= number_format($s1707->occupancyPct, 1) ?>%)</td>
                        </tr>
                        <tr data-matrix-row="adr">
                            <td class="px-5 py-2.5 text-gray-700">Average Daily Rate (ADR &mdash; Priced Nights)</td>
                            <td class="px-5 py-2.5 text-right font-semibold bg-indigo-50/20"><?= formatCopCurrency($sAll->adr) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency($s1606->adr) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency($s1707->adr) ?></td>
                        </tr>
                        <tr data-matrix-row="revpar">
                            <td class="px-5 py-2.5 text-gray-700">RevPAR (Gross Revenue &divide; Available Nights)</td>
                            <td class="px-5 py-2.5 text-right font-semibold bg-indigo-50/20"><?= formatCopCurrency($sAll->revpar) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency($s1606->revpar) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency($s1707->revpar) ?></td>
                        </tr>

                        <!-- Section 2: Revenue Breakdown by Booking Source -->
                        <tr class="bg-gray-50/50 font-semibold text-gray-500 text-[11px] uppercase">
                            <td colspan="4" class="px-5 py-1.5">Revenue Breakdown by Booking Source</td>
                        </tr>
                        <tr data-matrix-row="source-direct-online">
                            <td class="px-5 py-2.5 text-gray-700">Direct Online (web)</td>
                            <td class="px-5 py-2.5 text-right bg-indigo-50/20"><?= formatCopCurrency((float) ($sAll->bySource['direct_online']['active_revenue'] ?? 0.0)) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency((float) ($s1606->bySource['direct_online']['active_revenue'] ?? 0.0)) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency((float) ($s1707->bySource['direct_online']['active_revenue'] ?? 0.0)) ?></td>
                        </tr>
                        <tr data-matrix-row="source-direct-manual">
                            <td class="px-5 py-2.5 text-gray-700">Direct Manual (bank_transfer / cash / manual)</td>
                            <td class="px-5 py-2.5 text-right bg-indigo-50/20"><?= formatCopCurrency((float) ($sAll->bySource['direct_manual']['active_revenue'] ?? 0.0)) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency((float) ($s1606->bySource['direct_manual']['active_revenue'] ?? 0.0)) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency((float) ($s1707->bySource['direct_manual']['active_revenue'] ?? 0.0)) ?></td>
                        </tr>
                        <tr data-matrix-row="source-airbnb">
                            <td class="px-5 py-2.5 text-gray-700">External OTA — Airbnb</td>
                            <td class="px-5 py-2.5 text-right bg-indigo-50/20"><?= formatCopCurrency((float) ($sAll->bySource['airbnb']['active_revenue'] ?? 0.0)) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency((float) ($s1606->bySource['airbnb']['active_revenue'] ?? 0.0)) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency((float) ($s1707->bySource['airbnb']['active_revenue'] ?? 0.0)) ?></td>
                        </tr>
                        <tr data-matrix-row="policy-retention">
                            <td class="px-5 py-2.5 text-amber-800">Policy Retention (Settled Cancellations)</td>
                            <td class="px-5 py-2.5 text-right text-amber-800 bg-indigo-50/20"><?= formatCopCurrency($sAll->policyRetention) ?></td>
                            <td class="px-5 py-2.5 text-right text-amber-800"><?= formatCopCurrency($s1606->policyRetention) ?></td>
                            <td class="px-5 py-2.5 text-right text-amber-800"><?= formatCopCurrency($s1707->policyRetention) ?></td>
                        </tr>
                        <tr data-matrix-row="gross-revenue" class="bg-gray-50 font-bold text-gray-900">
                            <td class="px-5 py-3">Gross Revenue</td>
                            <td class="px-5 py-3 text-right bg-indigo-50/40"><?= formatCopCurrency($sAll->grossRevenue) ?></td>
                            <td class="px-5 py-3 text-right"><?= formatCopCurrency($s1606->grossRevenue) ?></td>
                            <td class="px-5 py-3 text-right"><?= formatCopCurrency($s1707->grossRevenue) ?></td>
                        </tr>

                        <!-- Section 3: Operational Expenses & Net Operating Income -->
                        <tr class="bg-gray-50/50 font-semibold text-gray-500 text-[11px] uppercase">
                            <td colspan="4" class="px-5 py-1.5">Operational Expenses &amp; Net Operating Income</td>
                        </tr>
                        <tr data-matrix-row="direct-expenses">
                            <td class="px-5 py-2.5 text-gray-700">Direct Property Operational Expenses (100%)</td>
                            <td class="px-5 py-2.5 text-right bg-indigo-50/20"><?= formatCopCurrency($sAll->directExpenses) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency($s1606->directExpenses) ?></td>
                            <td class="px-5 py-2.5 text-right"><?= formatCopCurrency($s1707->directExpenses) ?></td>
                        </tr>
                        <tr data-matrix-row="shared-expenses">
                            <td class="px-5 py-2.5 text-amber-800">Shared Portfolio Expenses (Allocated 50/50 per Property)</td>
                            <td class="px-5 py-2.5 text-right text-amber-800 bg-indigo-50/20"><?= formatCopCurrency($sAll->allocatedSharedExpenses) ?> (100%)</td>
                            <td class="px-5 py-2.5 text-right text-amber-800"><?= formatCopCurrency($s1606->allocatedSharedExpenses) ?> (50%)</td>
                            <td class="px-5 py-2.5 text-right text-amber-800"><?= formatCopCurrency($s1707->allocatedSharedExpenses) ?> (50%)</td>
                        </tr>
                        <tr data-matrix-row="total-expenses" class="font-bold text-rose-700">
                            <td class="px-5 py-2.5">Total Operational Expenses</td>
                            <td class="px-5 py-2.5 text-right bg-indigo-50/20">- <?= formatCopCurrency($sAll->totalExpenses) ?></td>
                            <td class="px-5 py-2.5 text-right">- <?= formatCopCurrency($s1606->totalExpenses) ?></td>
                            <td class="px-5 py-2.5 text-right">- <?= formatCopCurrency($s1707->totalExpenses) ?></td>
                        </tr>
                        <tr data-matrix-row="noi" class="bg-emerald-50/80 font-extrabold text-emerald-900 text-sm">
                            <td class="px-5 py-3.5">Net Operating Income (NOI &amp; Margin %)</td>
                            <td class="px-5 py-3.5 text-right"><?= formatCopCurrency($sAllNoi) ?> (<?= number_format($sAllNoiMargin, 1) ?>%)</td>
                            <td class="px-5 py-3.5 text-right"><?= formatCopCurrency($s1606Noi) ?> (<?= number_format($s1606NoiMargin, 1) ?>%)</td>
                            <td class="px-5 py-3.5 text-right"><?= formatCopCurrency($s1707Noi) ?> (<?= number_format($s1707NoiMargin, 1) ?>%)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ROW 3: DIMENSIONAL BREAKDOWNS (Booking Source & Expense Category) -->
    <div id="dimensional-breakdowns" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Gross Revenue by Booking Source -->
        <div id="booking-source-breakdown-panel" class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Gross Revenue by Booking Source</h3>
                <span class="text-[11px] text-gray-500">Total: <?= formatCopCurrency($slice->grossRevenue) ?></span>
            </div>
            <div class="space-y-3.5 pt-1">
                <?php foreach (BookingSourceGroup::commercialCases() as $srcGroup):
                    $srcItem = $slice->bySource[$srcGroup->value] ?? ['revenue' => 0.0, 'nights' => 0, 'count' => 0];
                    $srcRev = (float) $srcItem['revenue'];
                    $srcNights = (int) $srcItem['nights'];
                    $srcPct = $slice->grossRevenue > 0.0
                        ? ($srcRev / $slice->grossRevenue) * 100.0
                        : 0.0;
                ?>
                    <div data-booking-source-row="<?= htmlspecialchars($srcGroup->value, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex justify-between items-baseline text-xs mb-1 gap-2">
                            <span class="font-medium text-gray-700">
                                <?= htmlspecialchars($srcGroup->label(), ENT_QUOTES, 'UTF-8') ?>
                                <span class="text-gray-400 font-normal">(<?= $srcNights ?> <?= $srcNights === 1 ? 'night' : 'nights' ?>)</span>
                            </span>
                            <span class="font-bold text-gray-900 tabular-nums">
                                <?= formatCopCurrency($srcRev) ?> (<?= number_format($srcPct, 1) ?>%)
                            </span>
                        </div>
                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full <?= htmlspecialchars($srcGroup->barClass(), ENT_QUOTES, 'UTF-8') ?>" style="width: <?= $srcRev > 0.0 ? max(3, (int) round($srcPct)) : 0 ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Operational Expenses by Category (Sorted Descending Across 8 Canonical Categories) -->
        <div id="expense-category-breakdown-panel" class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Operational Expenses by Expense Category</h3>
                <span class="text-[11px] text-gray-500"><?= count($slice->allocatedExpenses) ?> recorded <?= count($slice->allocatedExpenses) === 1 ? 'entry' : 'entries' ?> &middot; <?= formatCopCurrency($slice->totalExpenses) ?></span>
            </div>
            <?php
            $sortedCategories = [];
            foreach (ExpenseCategory::cases() as $catCase) {
                $sortedCategories[$catCase->value] = (float) ($slice->byCategory[$catCase->value] ?? 0.0);
            }
            arsort($sortedCategories);
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <?php foreach ($sortedCategories as $catKey => $catAmount):
                    $catEnum = ExpenseCategory::from($catKey);
                    $pct = $slice->totalExpenses > 0.0 ? ($catAmount / $slice->totalExpenses) * 100.0 : 0.0;
                ?>
                    <div data-expense-category-row="<?= htmlspecialchars($catEnum->value, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex justify-between items-baseline text-[11px] mb-0.5 gap-2">
                            <span class="font-medium <?= $catAmount > 0.0 ? 'text-gray-700' : 'text-gray-400' ?>"><?= htmlspecialchars($catEnum->label(), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="font-bold <?= $catAmount > 0.0 ? 'text-gray-900' : 'text-gray-400' ?> tabular-nums"><?= formatCopCurrency($catAmount) ?> (<?= number_format($pct, 1) ?>%)</span>
                        </div>
                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full <?= htmlspecialchars($catEnum->barClass(), ENT_QUOTES, 'UTF-8') ?>" style="width: <?= $catAmount > 0.0 ? max(3, (int) round($pct)) : 0 ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- MONTHLY P&L TREND TABLE (Rendered on multi-month reporting windows) -->
    <?php if ($report->isMultiMonth()):
        $maxMonthlyScale = 1.0;
        foreach ($report->monthlyTrends as $mItem) {
            $maxMonthlyScale = max($maxMonthlyScale, $mItem->grossRevenue, $mItem->totalExpenses);
        }
    ?>
        <div id="monthly-pnl-trend-table" class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">
                        Monthly P&amp;L Trend Table (<?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>)
                    </h2>
                    <p class="text-xs text-gray-500">
                        Month-by-month accrual Gross Revenue, Operational Expenses, Net Operating Income, and capacity utilization across <?= count($report->monthlyTrends) ?> calendar months.
                    </p>
                </div>
                <div class="flex items-center space-x-3 text-[11px] text-gray-600">
                    <span class="inline-flex items-center"><span class="w-2.5 h-2.5 rounded-xs bg-emerald-500 mr-1.5"></span>Gross Revenue</span>
                    <span class="inline-flex items-center"><span class="w-2.5 h-2.5 rounded-xs bg-rose-500 mr-1.5"></span>Operational Expenses</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs tabular-nums">
                    <thead class="bg-gray-100/80 text-[11px] font-bold uppercase text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">Month</th>
                            <th scope="col" class="px-4 py-3 text-right">Available Nights</th>
                            <th scope="col" class="px-4 py-3 text-right">Occupied Nights</th>
                            <th scope="col" class="px-4 py-3 text-right">Occupancy %</th>
                            <th scope="col" class="px-4 py-3 text-right">Gross Revenue</th>
                            <th scope="col" class="px-4 py-3 text-right">Operational Expenses</th>
                            <th scope="col" class="px-4 py-3 text-right">Net Operating Income</th>
                            <th scope="col" class="px-4 py-3 text-right">NOI Margin %</th>
                            <th scope="col" class="px-4 py-3 text-left min-w-[160px]">Revenue vs. Expenses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($report->monthlyTrends as $mSlice):
                            $mNoi = $isChannelScoped ? $mSlice->allSourcesNoi : $mSlice->noi;
                            $mNoiMargin = $isChannelScoped ? $mSlice->allSourcesNoiMarginPct : $mSlice->noiMarginPct;
                            $revBarWidth = $mSlice->grossRevenue > 0.0
                                ? max(3, (int) round(($mSlice->grossRevenue / $maxMonthlyScale) * 100.0))
                                : 0;
                            $expBarWidth = $mSlice->totalExpenses > 0.0
                                ? max(3, (int) round(($mSlice->totalExpenses / $maxMonthlyScale) * 100.0))
                                : 0;
                        ?>
                            <tr class="hover:bg-gray-50/80" data-trend-month="<?= htmlspecialchars($mSlice->periodLabel, ENT_QUOTES, 'UTF-8') ?>">
                                <td class="px-4 py-2.5 font-mono font-bold text-gray-900 whitespace-nowrap">
                                    <?= htmlspecialchars($mSlice->periodLabel, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700 whitespace-nowrap">
                                    <?= $mSlice->availableNights ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-800 whitespace-nowrap">
                                    <?= $mSlice->occupiedNights ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-indigo-700 whitespace-nowrap">
                                    <?= number_format($mSlice->occupancyPct, 1) ?>%
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-900 whitespace-nowrap">
                                    <?= formatCopCurrency($mSlice->grossRevenue) ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-rose-700 whitespace-nowrap">
                                    <?= formatCopCurrency($mSlice->totalExpenses) ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-bold <?= $mNoi >= 0.0 ? 'text-emerald-700' : 'text-rose-700' ?> whitespace-nowrap">
                                    <?= formatCopCurrency($mNoi) ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold <?= $mNoi >= 0.0 ? 'text-emerald-700' : 'text-rose-700' ?> whitespace-nowrap">
                                    <?= number_format($mNoiMargin, 1) ?>%
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="space-y-1">
                                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden" title="Gross Revenue: <?= htmlspecialchars(formatCopCurrency($mSlice->grossRevenue), ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="h-full bg-emerald-500 trend-revenue-bar" style="width: <?= $revBarWidth ?>%"></div>
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden" title="Operational Expenses: <?= htmlspecialchars(formatCopCurrency($mSlice->totalExpenses), ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="h-full bg-rose-500 trend-expense-bar" style="width: <?= $expBarWidth ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold text-gray-900 border-t border-gray-200">
                        <tr>
                            <td class="px-4 py-3">Period Total</td>
                            <td class="px-4 py-3 text-right"><?= $slice->availableNights ?></td>
                            <td class="px-4 py-3 text-right"><?= $slice->occupiedNights ?></td>
                            <td class="px-4 py-3 text-right text-indigo-700"><?= number_format($slice->occupancyPct, 1) ?>%</td>
                            <td class="px-4 py-3 text-right"><?= formatCopCurrency($slice->grossRevenue) ?></td>
                            <td class="px-4 py-3 text-right text-rose-700"><?= formatCopCurrency($slice->totalExpenses) ?></td>
                            <td class="px-4 py-3 text-right <?= $displayedNoi >= 0.0 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= formatCopCurrency($displayedNoi) ?></td>
                            <td class="px-4 py-3 text-right <?= $displayedNoi >= 0.0 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= number_format($displayedNoiMarginPct, 1) ?>%</td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 font-normal"><?= count($report->monthlyTrends) ?> months</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- COLOMBIAN LEGAL CONTRIBUTIONS — FONTUR PARAFISCAL (0.25%) -->
    <?php
    $parafiscalCards = [];
    if ($propertyFilter === '1606' || $propertyFilter === 'all') {
        $parafiscalCards[] = [
            'id' => 'parafiscal-card-1606',
            'scope' => '1606',
            'title' => 'Property 1606 (RNT #' . ($report->property1606Slice->rntNumber ?? '270413') . ')',
            'badge' => 'RNT #' . ($report->property1606Slice->rntNumber ?? '270413'),
            'surchargeNote' => 'Direct Flat Surcharge: $ 100.000 COP / stay',
            'slice' => $report->property1606Slice,
            'isPortfolio' => false,
        ];
    }
    if ($propertyFilter === '1707' || $propertyFilter === 'all') {
        $parafiscalCards[] = [
            'id' => 'parafiscal-card-1707',
            'scope' => '1707',
            'title' => 'Property 1707 (RNT #' . ($report->property1707Slice->rntNumber ?? '268781') . ')',
            'badge' => 'RNT #' . ($report->property1707Slice->rntNumber ?? '268781'),
            'surchargeNote' => 'Direct Flat Surcharge: $ 120.000 COP / stay',
            'slice' => $report->property1707Slice,
            'isPortfolio' => false,
        ];
    }
    if ($propertyFilter === 'all') {
        $parafiscalCards[] = [
            'id' => 'parafiscal-card-portfolio',
            'scope' => 'all',
            'title' => 'Portfolio Total (1606 + 1707)',
            'badge' => 'RNT #270413 & RNT #268781',
            'surchargeNote' => 'Combined 1606 + 1707 RNT Declarations',
            'slice' => $report->portfolioSlice,
            'isPortfolio' => true,
        ];
    }
    ?>
    <div id="fontur-parafiscal-section" class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-4 bg-gray-50 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-gray-900">
                    Colombian Legal Contributions &mdash; FONTUR Parafiscal (0.25%)
                </h2>
                <p class="text-xs text-gray-500">
                    <em>Contribuci&oacute;n Parafiscal para la Promoci&oacute;n del Turismo</em> (Law 1101 of 2006 &amp; Law 2068 of 2020) for <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?> &mdash; assessed at 2.5 per thousand (0.25%) of Parafiscal Taxable Base, rounded to nearest 1 COP.
                </p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 shrink-0">
                Rate: 0.25% (2.5&permil;) &middot; Rounded to 1 COP
            </span>
        </div>

        <div class="p-5 grid grid-cols-1 <?= count($parafiscalCards) > 1 ? 'lg:grid-cols-3' : 'max-w-xl' ?> gap-4">
            <?php foreach ($parafiscalCards as $pCard):
                /** @var \OceanViewFlats\Domain\Finance\FinancialReportSlice $pSlice */
                $pSlice = $pCard['slice'];
                $isPortfolioCard = (bool) $pCard['isPortfolio'];
            ?>
                <div id="<?= htmlspecialchars($pCard['id'], ENT_QUOTES, 'UTF-8') ?>"
                     data-parafiscal-card="<?= htmlspecialchars($pCard['scope'], ENT_QUOTES, 'UTF-8') ?>"
                     class="rounded-xl border <?= $isPortfolioCard ? 'border-indigo-200 bg-indigo-50/30' : 'border-gray-200 bg-white' ?> p-4 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider <?= $isPortfolioCard ? 'text-indigo-900' : 'text-gray-800' ?>">
                                <?= htmlspecialchars($pCard['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold <?= $isPortfolioCard ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' ?>">
                                <?= htmlspecialchars($pCard['badge'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                            <?= htmlspecialchars($pCard['surchargeNote'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <dl class="mt-3 pt-3 border-t border-gray-100 space-y-2 text-xs tabular-nums">
                            <div class="flex items-center justify-between gap-2" data-parafiscal-line="active-stay-revenue">
                                <dt class="text-gray-600">Commercial Stay Revenue</dt>
                                <dd class="font-semibold text-gray-900"><?= formatCopCurrency($pSlice->activeStayRevenue) ?></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2" data-parafiscal-line="deducted-direct-fees">
                                <dt class="text-gray-600">Prorated Direct Cleaning &amp; Resort Fees Deducted</dt>
                                <dd class="font-medium text-rose-700">- <?= formatCopCurrency($pSlice->parafiscalDeductedFees) ?></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2 text-[11px]" data-parafiscal-line="excluded-policy-retention">
                                <dt class="text-amber-800">Policy Retention Excluded (Cancelled Stays)</dt>
                                <dd class="font-medium text-amber-800"><?= formatCopCurrency($pSlice->policyRetention) ?> (Excluded)</dd>
                            </div>
                            <div class="pt-2 border-t border-gray-200 flex items-center justify-between gap-2 font-bold text-gray-900" data-parafiscal-line="taxable-base">
                                <dt>Parafiscal Taxable Base</dt>
                                <dd><?= formatCopCurrency($pSlice->parafiscalTaxableBase) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="pt-3 border-t <?= $isPortfolioCard ? 'border-indigo-200' : 'border-gray-200' ?> flex items-center justify-between gap-2" data-parafiscal-line="assessed-contribution">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider <?= $isPortfolioCard ? 'text-indigo-900' : 'text-gray-700' ?> block">
                                Assessed Parafiscal Contribution (0.25%)
                            </span>
                            <span class="text-[10px] text-gray-500">
                                <?= $pSlice->activeStaysCount ?> commercial <?= $pSlice->activeStaysCount === 1 ? 'stay' : 'stays' ?> &middot; <?= $pSlice->occupiedNights ?> occupied <?= $pSlice->occupiedNights === 1 ? 'night' : 'nights' ?>
                            </span>
                        </div>
                        <span class="text-lg font-extrabold <?= $isPortfolioCard ? 'text-indigo-700' : 'text-emerald-700' ?> tabular-nums">
                            <?= formatCopCurrency($pSlice->parafiscalContribution) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        $scopedParafiscalPeriods = $report->getParafiscalPeriodsForProperty($propertyFilter);
        $inProgressPeriods = $report->getInProgressParafiscalPeriods($propertyFilter);
        $unsettledClosedPeriods = $report->getUnsettledClosedParafiscalPeriods($propertyFilter);
        $inProgressAccruedTotal = 0.0;
        foreach ($inProgressPeriods as $ipPeriod) {
            $inProgressAccruedTotal += $ipPeriod->calculatedContribution;
        }
        $primaryInProgress = $inProgressPeriods[0] ?? null;
        ?>

        <!-- NEXT PAYMENT & PENDING DECLARATIONS BANNER -->
        <div id="parafiscal-next-payment-banner" class="px-5 py-4 border-t border-gray-200 bg-slate-50/70 space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-start space-x-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">
                            Next Payment &amp; Pending Declarations Banner
                        </h3>
                        <?php if ($primaryInProgress !== null): ?>
                            <p class="text-xs text-gray-700 mt-0.5">
                                <strong>Current In-Progress Quarter (<?= htmlspecialchars($primaryInProgress->periodKey, ENT_QUOTES, 'UTF-8') ?>):</strong>
                                Accrued Contribution <span class="font-bold text-indigo-700 tabular-nums"><?= formatCopCurrency($inProgressAccruedTotal) ?></span>
                                &middot; Next Statutory Deadline:
                                <span class="font-mono font-semibold text-gray-900"><?= htmlspecialchars($primaryInProgress->dueDate, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-indigo-700 font-semibold">(<?= $primaryInProgress->daysUntilDue ?> <?= $primaryInProgress->daysUntilDue === 1 ? 'day' : 'days' ?> remaining &mdash; 20th Colombian business day)</span>
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-gray-600 mt-0.5">
                                All current quarterly Parafiscal declarations have been settled.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($primaryInProgress !== null && count($inProgressPeriods) > 1): ?>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] shrink-0">
                        <?php foreach ($inProgressPeriods as $ipRow): ?>
                            <span class="px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-gray-700 font-medium tabular-nums">
                                Property <?= htmlspecialchars($ipRow->propertyId, ENT_QUOTES, 'UTF-8') ?> (RNT #<?= htmlspecialchars($ipRow->rntNumber, ENT_QUOTES, 'UTF-8') ?>):
                                <strong class="text-gray-900"><?= formatCopCurrency($ipRow->calculatedContribution) ?></strong>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($unsettledClosedPeriods)): ?>
                <div id="parafiscal-pending-declarations-callouts" class="space-y-2 pt-1">
                    <?php foreach ($unsettledClosedPeriods as $pendingRow):
                        $isCriticalRow = $pendingRow->isOverdue() || $pendingRow->daysUntilDue <= 7;
                        $calloutBg = $isCriticalRow ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900';
                        $badgeBg = $isCriticalRow ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white';
                        $badgeText = $pendingRow->isOverdue()
                            ? 'OVERDUE (' . abs($pendingRow->daysUntilDue) . 'd past due)'
                            : ($pendingRow->daysUntilDue <= 7
                                ? 'CRITICAL (Due in ' . $pendingRow->daysUntilDue . 'd)'
                                : 'PENDING (Due in ' . $pendingRow->daysUntilDue . 'd)');
                        $pendingModalQuery = http_build_query($activeQueryParams);
                    ?>
                        <div data-pending-parafiscal-callout="<?= htmlspecialchars($pendingRow->periodKey . '_' . $pendingRow->propertyId, ENT_QUOTES, 'UTF-8') ?>"
                             class="p-3 rounded-xl border <?= $calloutBg ?> flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                            <div class="flex items-start sm:items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $badgeBg ?> shrink-0">
                                    <?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="font-bold">
                                    <?= htmlspecialchars($pendingRow->periodKey, ENT_QUOTES, 'UTF-8') ?> &mdash; Property <?= htmlspecialchars($pendingRow->propertyId, ENT_QUOTES, 'UTF-8') ?> (RNT #<?= htmlspecialchars($pendingRow->rntNumber, ENT_QUOTES, 'UTF-8') ?>)
                                </span>
                                <span class="text-gray-700 tabular-nums">
                                    Taxable Base: <strong><?= formatCopCurrency($pendingRow->taxableBase) ?></strong> &middot;
                                    Assessed Contribution (0.25%): <strong><?= formatCopCurrency($pendingRow->calculatedContribution) ?></strong> &middot;
                                    Due Date: <strong class="font-mono"><?= htmlspecialchars($pendingRow->dueDate, ENT_QUOTES, 'UTF-8') ?></strong>
                                </span>
                            </div>
                            <div class="flex items-center gap-2.5 shrink-0">
                                <span class="text-[11px] font-semibold <?= $isCriticalRow ? 'text-rose-800' : 'text-amber-800' ?>">
                                    Action Required: File RNT #<?= htmlspecialchars($pendingRow->rntNumber, ENT_QUOTES, 'UTF-8') ?> Declaration
                                </span>
                                <button type="button"
                                        hx-get="/financial-report/parafiscal/<?= urlencode($pendingRow->periodKey) ?>/<?= urlencode($pendingRow->propertyId) ?>/modal<?= $pendingModalQuery !== '' ? '?' . htmlspecialchars($pendingModalQuery, ENT_QUOTES, 'UTF-8') : '' ?>"
                                        hx-target="#modal-container"
                                        hx-swap="innerHTML"
                                        class="px-3 py-1 rounded-lg <?= $isCriticalRow ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700' ?> text-white text-[11px] font-semibold shadow-2xs cursor-pointer">
                                    Mark as Paid
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- HISTORICAL QUARTERLY PARAFISCAL SETTLEMENTS TABLE -->
        <div id="parafiscal-historical-settlements-panel" class="border-t border-gray-200">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800">
                        Historical Quarterly Parafiscal Settlements
                    </h3>
                    <p class="text-[11px] text-gray-500">
                        Per-RNT quarterly FONTUR Parafiscal timeline from earliest commercial Reservation quarter through current quarter (statutory deadline: 20th Colombian business day of Apr, Jul, Oct, Jan).
                    </p>
                </div>
                <span class="text-[11px] text-gray-500 font-medium">
                    <?= count($scopedParafiscalPeriods) ?> quarterly <?= count($scopedParafiscalPeriods) === 1 ? 'record' : 'records' ?>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table id="parafiscal-settlements-table" class="min-w-full divide-y divide-gray-200 text-xs tabular-nums">
                    <thead class="bg-gray-100/70 text-[11px] font-bold uppercase text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">Quarter</th>
                            <th scope="col" class="px-4 py-3 text-left">Property &amp; RNT</th>
                            <th scope="col" class="px-4 py-3 text-right">Parafiscal Taxable Base</th>
                            <th scope="col" class="px-4 py-3 text-right">Calculated (0.25%)</th>
                            <th scope="col" class="px-4 py-3 text-left">Statutory Due Date</th>
                            <th scope="col" class="px-4 py-3 text-left">Status</th>
                            <th scope="col" class="px-4 py-3 text-left">Settled Details</th>
                            <th scope="col" class="px-4 py-3 text-left">Linked Expense</th>
                            <th scope="col" class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php
                        $parafiscalModalQuery = http_build_query($activeQueryParams);
                        foreach ($scopedParafiscalPeriods as $pRow):
                            $pModalHref = '/financial-report/parafiscal/' . urlencode($pRow->periodKey) . '/' . urlencode($pRow->propertyId) . '/modal'
                                . ($parafiscalModalQuery !== '' ? '?' . $parafiscalModalQuery : '');
                            $pRevertHref = '/financial-report/parafiscal/' . urlencode($pRow->periodKey) . '/' . urlencode($pRow->propertyId) . '/revert';
                        ?>
                            <tr data-parafiscal-row="<?= htmlspecialchars($pRow->periodKey . '_' . $pRow->propertyId, ENT_QUOTES, 'UTF-8') ?>"
                                data-parafiscal-property="<?= htmlspecialchars($pRow->propertyId, ENT_QUOTES, 'UTF-8') ?>"
                                data-parafiscal-status="<?= htmlspecialchars($pRow->status, ENT_QUOTES, 'UTF-8') ?>"
                                class="hover:bg-gray-50/80">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-mono font-bold text-gray-900"><?= htmlspecialchars($pRow->periodKey, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-[10px] text-gray-500"><?= htmlspecialchars($pRow->periodStart, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($pRow->periodEnd, ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-gray-900">Property <?= htmlspecialchars($pRow->propertyId, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-[11px] font-mono text-indigo-700 font-semibold">RNT #<?= htmlspecialchars($pRow->rntNumber, ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="font-semibold text-gray-900"><?= formatCopCurrency($pRow->taxableBase) ?></div>
                                    <?php if ($pRow->deductedDirectFees > 0.0): ?>
                                        <div class="text-[10px] text-rose-600">- <?= formatCopCompactCurrency($pRow->deductedDirectFees) ?> direct fees</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="font-bold text-emerald-700"><?= formatCopCurrency($pRow->calculatedContribution) ?></div>
                                    <?php if ($pRow->hasRecalculationVariance && $pRow->settlement !== null): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 mt-0.5"
                                              data-parafiscal-variance="true">
                                            Recalculated Variance (Snapshot: <?= formatCopCompactCurrency($pRow->settlement->settledCalculatedAmount ?? 0.0) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-mono font-medium text-gray-900"><?= htmlspecialchars($pRow->dueDate, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-[10px] <?= $pRow->isOverdue() ? 'text-rose-600 font-semibold' : ($pRow->isPending() && $pRow->daysUntilDue <= 7 ? 'text-amber-700 font-semibold' : 'text-gray-500') ?>">
                                        <?php if ($pRow->isSettled()): ?>
                                            Filed &amp; Settled
                                        <?php elseif ($pRow->isOverdue()): ?>
                                            <?= abs($pRow->daysUntilDue) ?> <?= abs($pRow->daysUntilDue) === 1 ? 'day' : 'days' ?> overdue
                                        <?php elseif ($pRow->daysUntilDue === 0): ?>
                                            Due today
                                        <?php else: ?>
                                            In <?= $pRow->daysUntilDue ?> <?= $pRow->daysUntilDue === 1 ? 'day' : 'days' ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= htmlspecialchars($pRow->statusBadgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($pRow->statusBadgeLabel(), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($pRow->isSettled() && $pRow->settlement !== null): ?>
                                        <div class="font-bold text-gray-900">
                                            <?= formatCopCurrency($pRow->settlement->settledAmount ?? 0.0) ?>
                                            <?php if ($pRow->isZeroDeclaration()): ?>
                                                <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-[10px] font-semibold">Declaraci&oacute;n en Cero</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[10px] text-gray-500">
                                            Paid: <?= htmlspecialchars((string) ($pRow->settlement->paymentDate ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($pRow->settlement->referenceCode !== null): ?>
                                                &middot; Ref: <span class="font-mono font-semibold text-gray-700"><?= htmlspecialchars($pRow->settlement->referenceCode, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Not settled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($pRow->linkedExpense !== null): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            Synced (<?= htmlspecialchars($pRow->linkedExpense->expenseUid, ENT_QUOTES, 'UTF-8') ?>)
                                        </span>
                                    <?php elseif ($pRow->isZeroDeclaration()): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600">
                                            $0 Declaration (No Expense)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[11px]">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right space-x-1.5">
                                    <?php if ($pRow->isSettled()): ?>
                                        <button type="button"
                                                hx-get="<?= htmlspecialchars($pModalHref, ENT_QUOTES, 'UTF-8') ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium text-[11px] cursor-pointer">
                                            Edit
                                        </button>
                                        <button type="button"
                                                hx-post="<?= htmlspecialchars($pRevertHref, ENT_QUOTES, 'UTF-8') ?>"
                                                hx-vals='<?= htmlspecialchars((string) json_encode([
                                                    'csrf_token' => $csrfToken,
                                                    'current_property_id' => $filters['property_id'] ?? 'all',
                                                    'current_period' => $filters['period'] ?? 'this_month',
                                                    'current_date_from' => $filters['date_from'] ?? '',
                                                    'current_date_to' => $filters['date_to'] ?? '',
                                                    'current_compare' => $filters['compare'] ?? 'prev_period',
                                                    'current_source' => $filters['source'] ?? 'all',
                                                    'current_category' => $filters['category'] ?? 'all',
                                                    'current_tab' => $filters['tab'] ?? 'expenses',
                                                    'current_search' => $filters['search'] ?? '',
                                                ], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'
                                                hx-target="#financial-report-content"
                                                hx-swap="outerHTML"
                                                hx-confirm="Are you sure you want to revert this Parafiscal Settlement back to Pending? Any linked FONTUR Operational Expense will be deleted."
                                                class="px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-medium text-[11px] cursor-pointer">
                                            Revert
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                hx-get="<?= htmlspecialchars($pModalHref, ENT_QUOTES, 'UTF-8') ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                class="px-2.5 py-1 rounded bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-[11px] shadow-2xs cursor-pointer">
                                            Mark as Paid
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ROW 4: TABBED BOTTOM DETAIL LEDGERS -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/60">
            <div class="flex space-x-2">
                <?php
                $expTabQuery = http_build_query(array_merge($activeQueryParams, ['tab' => 'expenses']));
                $resTabQuery = http_build_query(array_merge($activeQueryParams, ['tab' => 'reservations']));
                ?>
                <a href="/financial-report?<?= htmlspecialchars($expTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-get="/financial-report?<?= htmlspecialchars($expTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-target="#financial-report-content"
                   hx-swap="outerHTML"
                   hx-push-url="true"
                   onclick="var t=document.getElementById('financial-report-tab-input'); if(t) t.value='expenses';"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $activeTab === 'expenses' ? 'bg-indigo-600 text-white shadow-2xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100' ?>">
                    Operational Expenses Ledger (<?= count($slice->allocatedExpenses) ?>)
                </a>
                <a href="/financial-report?<?= htmlspecialchars($resTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-get="/financial-report?<?= htmlspecialchars($resTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-target="#financial-report-content"
                   hx-swap="outerHTML"
                   hx-push-url="true"
                   onclick="var t=document.getElementById('financial-report-tab-input'); if(t) t.value='reservations';"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $activeTab === 'reservations' ? 'bg-indigo-600 text-white shadow-2xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100' ?>">
                    Attributed Reservations (<?= count($slice->attributedReservations) ?>)
                </a>
            </div>
            <div class="flex items-center space-x-3 text-xs text-gray-500">
                <?php if ($activeTab === 'expenses'): ?>
                    <span>Click <strong>Duplicate</strong> on any expense row to pre-fill recurring monthly bills.</span>
                <?php else: ?>
                    <span>All reservation revenues and policy retentions are prorated uniformly across nights in <?= htmlspecialchars($slice->periodStart, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($slice->periodEnd, ENT_QUOTES, 'UTF-8') ?>.</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($activeTab === 'reservations'): ?>
            <!-- TAB 2: ATTRIBUTED RESERVATIONS LEDGER -->
            <?php if (empty($slice->attributedReservations)): ?>
                <div class="p-8 text-center text-xs text-gray-500">
                    No reservations overlap this reporting slice.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto" id="attributed-reservations-ledger-panel">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">Reservation UID</th>
                                <th scope="col" class="px-4 py-3 text-left">Property</th>
                                <th scope="col" class="px-4 py-3 text-left">Primary Guest</th>
                                <th scope="col" class="px-4 py-3 text-left">Booking Source</th>
                                <th scope="col" class="px-4 py-3 text-left">Stay Dates</th>
                                <th scope="col" class="px-4 py-3 text-center">In-Period Nights / Total Nights</th>
                                <th scope="col" class="px-4 py-3 text-right">Contract Net</th>
                                <th scope="col" class="px-4 py-3 text-right">In-Period Attributed Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php foreach ($slice->attributedReservations as $attrRow): ?>
                                <tr class="hover:bg-gray-50/80" data-reservation-uid="<?= htmlspecialchars($attrRow->reservationUid, ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-indigo-700">
                                        <a href="/reservations?search=<?= urlencode($attrRow->reservationUid) ?>" class="hover:underline">
                                            <?= htmlspecialchars($attrRow->reservationUid, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-800">
                                            Prop <?= htmlspecialchars($attrRow->propertyId, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <?= htmlspecialchars($attrRow->guestName, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($attrRow->isRetention): ?>
                                            <span class="ml-1.5 px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold">
                                                Cancelled &middot; Policy Retention
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded border text-[11px] font-medium <?= htmlspecialchars($attrRow->channelGroup->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($attrRow->channelGroup->label(), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-gray-600">
                                        <?= htmlspecialchars($attrRow->checkIn, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($attrRow->checkOut, ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <?php if ($attrRow->isRetention): ?>
                                            <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-semibold">
                                                0 Occupied (<?= $attrRow->inPeriodNights ?> / <?= $attrRow->totalNights ?> nights &middot; <?= $attrRow->prorationPercentage() ?>% retained)
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-800 font-semibold">
                                                <?= $attrRow->inPeriodNights ?> / <?= $attrRow->totalNights ?> nights (<?= $attrRow->prorationPercentage() ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right tabular-nums text-gray-600">
                                        <div><?= formatCopCurrency($attrRow->contractNet) ?></div>
                                        <?php if ($attrRow->refundedAmount > 0.0): ?>
                                            <div class="text-[10px] text-rose-600">Refunded: <?= formatCopCompactCurrency($attrRow->refundedAmount) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right tabular-nums">
                                        <?php if ($attrRow->isUnpricedAirbnb): ?>
                                            <a href="/reservations?search=<?= urlencode($attrRow->reservationUid) ?>"
                                               hx-get="/reservations/<?= urlencode($attrRow->reservationUid) ?>/edit"
                                               hx-target="#modal-container"
                                               hx-swap="innerHTML"
                                               class="inline-flex items-center px-2.5 py-1 rounded bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[11px] cursor-pointer">
                                                $0 COP &middot; Set Payout
                                            </a>
                                        <?php else: ?>
                                            <span class="font-bold text-emerald-700"><?= formatCopCurrency($attrRow->attributedRevenue) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- TAB 1: OPERATIONAL EXPENSES LEDGER -->
            <?php if (empty($slice->allocatedExpenses)): ?>
                <div class="p-8 text-center text-xs text-gray-500">
                    No Operational Expenses match the current filter criteria.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto" id="operational-expenses-ledger-panel">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">Date</th>
                                <th scope="col" class="px-4 py-3 text-left">Property</th>
                                <th scope="col" class="px-4 py-3 text-left">Category</th>
                                <th scope="col" class="px-4 py-3 text-left">Title &amp; Vendor</th>
                                <th scope="col" class="px-4 py-3 text-left">Ref / Linked Stay</th>
                                <th scope="col" class="px-4 py-3 text-right">Amount (COP)</th>
                                <th scope="col" class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php foreach ($slice->allocatedExpenses as $allocatedRow):
                                $expense = $allocatedRow->expense;
                                $expId = (int) $expense->id;
                                $allocationFactor = $allocatedRow->allocationFactor;
                                $allocatedAmount = $allocatedRow->allocatedAmount;
                                $modalFilterQuery = http_build_query($activeQueryParams);
                            ?>
                                <tr class="hover:bg-gray-50/80" data-expense-uid="<?= htmlspecialchars($expense->expenseUid, ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-gray-600">
                                        <div><?= htmlspecialchars($expense->expenseDate, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-[10px] text-gray-400"><?= htmlspecialchars($expense->expenseUid, ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($expense->propertyId === 'shared'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                                Shared (<?= $allocatedRow->isSharedAllocatedHalf() ? '50% Alloc' : 'Portfolio' ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-800">
                                                Prop <?= htmlspecialchars($expense->propertyId, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] font-medium <?= htmlspecialchars($expense->category->badgeClass(), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($expense->category->label(), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900"><?= htmlspecialchars($expense->title, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if ($expense->vendorName !== null): ?>
                                            <div class="text-[11px] text-gray-500"><?= htmlspecialchars($expense->vendorName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if ($expense->notes !== null): ?>
                                            <div class="text-[11px] text-gray-400 italic mt-0.5"><?= htmlspecialchars($expense->notes, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($expense->referenceCode !== null): ?>
                                            <span class="font-mono text-[11px] text-gray-600 bg-gray-100 px-1.5 py-0.5 rounded">
                                                <?= htmlspecialchars($expense->referenceCode, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-300">&mdash;</span>
                                        <?php endif; ?>
                                        <?php if ($expense->reservationUid !== null): ?>
                                            <div class="mt-1">
                                                <a href="/reservations?search=<?= urlencode($expense->reservationUid) ?>"
                                                   class="font-mono text-[10px] text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200 hover:underline">
                                                    Stay: <?= htmlspecialchars($expense->reservationUid, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right tabular-nums">
                                        <div class="font-bold text-gray-900"><?= formatCopCurrency($allocatedAmount) ?></div>
                                        <?php if ($allocatedRow->isSharedAllocatedHalf()): ?>
                                            <div class="text-[10px] text-amber-700">50% of <?= formatCopCurrency($expense->amount) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right space-x-1.5">
                                        <button type="button"
                                                hx-get="/financial-report/expenses/new?duplicate_from=<?= $expId ?><?= $modalFilterQuery !== '' ? '&amp;' . htmlspecialchars($modalFilterQuery, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold text-[11px] cursor-pointer"
                                                title="Duplicate for new month">
                                            Duplicate
                                        </button>
                                        <button type="button"
                                                hx-get="/financial-report/expenses/<?= $expId ?>/edit<?= $modalFilterQuery !== '' ? '?' . htmlspecialchars($modalFilterQuery, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium text-[11px] cursor-pointer">
                                            Edit
                                        </button>
                                        <button type="button"
                                                hx-post="/financial-report/expenses/<?= $expId ?>/delete"
                                                hx-vals='<?= htmlspecialchars((string) json_encode([
                                                    'csrf_token' => $csrfToken,
                                                    'current_property_id' => $filters['property_id'] ?? 'all',
                                                    'current_period' => $filters['period'] ?? 'this_month',
                                                    'current_date_from' => $filters['date_from'] ?? '',
                                                    'current_date_to' => $filters['date_to'] ?? '',
                                                    'current_compare' => $filters['compare'] ?? 'prev_period',
                                                    'current_source' => $filters['source'] ?? 'all',
                                                    'current_category' => $filters['category'] ?? 'all',
                                                    'current_tab' => $filters['tab'] ?? 'expenses',
                                                    'current_search' => $filters['search'] ?? '',
                                                ], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'
                                                hx-target="#financial-report-content"
                                                hx-swap="outerHTML"
                                                hx-confirm="Are you sure you want to delete this operational expense? This action will be recorded in the Audit Log."
                                                class="px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-medium text-[11px] cursor-pointer">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
