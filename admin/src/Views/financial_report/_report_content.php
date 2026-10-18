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

    <!-- ROW 3: DIMENSIONAL BREAKDOWNS (Booking Source & Expense Category) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Gross Revenue by Booking Source -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Gross Revenue by Booking Source</h3>
                <span class="text-[11px] text-gray-500">All Channels: <?= formatCopCompactCurrency($slice->allSourcesGrossRevenue) ?></span>
            </div>
            <div class="space-y-3 pt-1">
                <?php foreach (BookingSourceGroup::commercialCases() as $srcGroup):
                    $srcItem = $slice->bySource[$srcGroup->value] ?? ['revenue' => 0.0, 'nights' => 0, 'count' => 0];
                    $srcRev = (float) $srcItem['revenue'];
                    $srcNights = (int) $srcItem['nights'];
                    $srcPct = $slice->allSourcesGrossRevenue > 0.0
                        ? ($srcRev / $slice->allSourcesGrossRevenue) * 100.0
                        : 0.0;
                ?>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($srcGroup->label(), ENT_QUOTES, 'UTF-8') ?> (<?= $srcNights ?>n)</span>
                            <span class="font-bold text-gray-900 tabular-nums"><?= formatCopCompactCurrency($srcRev) ?> (<?= number_format($srcPct, 0) ?>%)</span>
                        </div>
                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full <?= htmlspecialchars($srcGroup->barClass(), ENT_QUOTES, 'UTF-8') ?>" style="width: <?= $srcRev > 0.0 ? max(3, (int) round($srcPct)) : 0 ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Operational Expenses by Category -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Operational Expenses by Category</h3>
                <span class="text-[11px] text-gray-500"><?= count($slice->allocatedExpenses) ?> recorded <?= count($slice->allocatedExpenses) === 1 ? 'entry' : 'entries' ?></span>
            </div>
            <?php
            $nonZeroCategories = array_filter($slice->byCategory, static fn (float $val): bool => $val > 0.0);
            arsort($nonZeroCategories);
            ?>
            <?php if (empty($nonZeroCategories)): ?>
                <div class="py-6 text-center text-xs text-gray-400">
                    No category spend recorded for the selected filters.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <?php foreach ($nonZeroCategories as $catKey => $catAmount):
                        $catEnum = ExpenseCategory::tryFrom($catKey) ?? ExpenseCategory::OTHER;
                        $pct = $slice->totalExpenses > 0.0 ? ($catAmount / $slice->totalExpenses) * 100.0 : 0.0;
                    ?>
                        <div>
                            <div class="flex justify-between text-[11px] mb-0.5">
                                <span class="font-medium text-gray-700"><?= htmlspecialchars($catEnum->label(), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="font-bold text-gray-900 tabular-nums"><?= formatCopCompactCurrency($catAmount) ?> (<?= number_format($pct, 0) ?>%)</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full <?= htmlspecialchars($catEnum->barClass(), ENT_QUOTES, 'UTF-8') ?>" style="width: <?= max(3, (int) round($pct)) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
