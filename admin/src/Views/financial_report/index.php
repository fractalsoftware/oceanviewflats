<?php
/**
 * Master Financial Report & Operational Expense Management View.
 *
 * @var array<string, string> $filters
 * @var string $headerActionsHtml
 * @var string $reportContentHtml
 * @var string $csrfToken
 * @var array<string, mixed> $currentUser
 */

use OceanViewFlats\Domain\Finance\ExpenseCategory;

$propertyId = (string) ($filters['property_id'] ?? 'all');
$period = (string) ($filters['period'] ?? 'this_month');
$dateFrom = (string) ($filters['date_from'] ?? '');
$dateTo = (string) ($filters['date_to'] ?? '');
$compare = (string) ($filters['compare'] ?? 'prev_period');
$source = (string) ($filters['source'] ?? 'all');
$category = (string) ($filters['category'] ?? 'all');
$tab = (string) ($filters['tab'] ?? 'expenses');
$search = (string) ($filters['search'] ?? '');
?>

<div class="space-y-6">

    <!-- Page Header & Primary Actions -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2.5">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Financial Report</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                    Settlement Currency: COP
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Accrual-prorated Gross Revenue, Operational Expenses (with 50/50 shared portfolio allocation), Net Operating Income, and hospitality KPIs.
            </p>
        </div>
        <?= $headerActionsHtml ?>
    </div>

    <!-- 5-Control HTMX Filter Toolbar -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs">
        <form id="financial-report-filter-form"
              action="/financial-report"
              method="GET"
              hx-get="/financial-report"
              hx-target="#financial-report-content"
              hx-swap="outerHTML"
              hx-push-url="true"
              hx-trigger="submit, change from:select, change from:input[type=date], keyup changed delay:300ms from:#expense-search-input, reservationUpdated from:body"
              class="space-y-3">

            <input type="hidden" name="tab" id="financial-report-tab-input" value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <!-- 1. Property Filter -->
                <div>
                    <label for="property-filter-select" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Property</label>
                    <select id="property-filter-select"
                            name="property_id"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="all" <?= $propertyId === 'all' ? 'selected' : '' ?>>All Properties (Portfolio)</option>
                        <option value="1606" <?= $propertyId === '1606' ? 'selected' : '' ?>>Property 1606</option>
                        <option value="1707" <?= $propertyId === '1707' ? 'selected' : '' ?>>Property 1707</option>
                    </select>
                </div>

                <!-- 2. Time Period Preset -->
                <div>
                    <label for="period-select" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Time Period</label>
                    <select id="period-select"
                            name="period"
                            onchange="if(this.value !== 'custom'){ var df=document.getElementById('date-from-input'), dt=document.getElementById('date-to-input'); if(df) df.value=''; if(dt) dt.value=''; }"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                        <option value="ytd" <?= $period === 'ytd' ? 'selected' : '' ?>>Year to Date (YTD)</option>
                        <option value="last_12_months" <?= $period === 'last_12_months' ? 'selected' : '' ?>>Last 12 Months</option>
                        <option value="last_year" <?= $period === 'last_year' ? 'selected' : '' ?>>Last Year</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>
                </div>

                <!-- 3. Compare Against -->
                <div>
                    <label for="compare-select" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Compare Against</label>
                    <select id="compare-select"
                            name="compare"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="prev_period" <?= $compare === 'prev_period' ? 'selected' : '' ?>>Previous Period</option>
                        <option value="yoy" <?= $compare === 'yoy' ? 'selected' : '' ?>>Previous Year (YoY)</option>
                    </select>
                </div>

                <!-- 4. Booking Source -->
                <div>
                    <label for="source-select" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Booking Source</label>
                    <select id="source-select"
                            name="source"
                            class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>All Booking Sources</option>
                        <option value="direct_online" <?= $source === 'direct_online' ? 'selected' : '' ?>>Direct Online (web)</option>
                        <option value="direct_manual" <?= $source === 'direct_manual' ? 'selected' : '' ?>>Direct Manual (bank / cash / manual)</option>
                        <option value="airbnb" <?= $source === 'airbnb' ? 'selected' : '' ?>>External OTA — Airbnb</option>
                    </select>
                </div>

                <!-- 5. Expense Category & Reset -->
                <div>
                    <label for="category-select" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Expense Category</label>
                    <div class="flex items-center space-x-1.5">
                        <select id="category-select"
                                name="category"
                                class="block w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                            <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All 8 Categories</option>
                            <?php foreach (ExpenseCategory::cases() as $catCase): ?>
                                <option value="<?= htmlspecialchars($catCase->value, ENT_QUOTES, 'UTF-8') ?>" <?= $category === $catCase->value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($catCase->label(), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="/financial-report"
                           hx-get="/financial-report"
                           hx-target="#financial-report-content"
                           hx-swap="outerHTML"
                           hx-push-url="true"
                           onclick="var f=this.closest('form'); if(f){ f.reset(); var p=document.getElementById('property-filter-select'), pe=document.getElementById('period-select'), c=document.getElementById('compare-select'), s=document.getElementById('source-select'), cat=document.getElementById('category-select'), df=document.getElementById('date-from-input'), dt=document.getElementById('date-to-input'), sr=document.getElementById('expense-search-input'), t=document.getElementById('financial-report-tab-input'); if(p) p.value='all'; if(pe) pe.value='this_month'; if(c) c.value='prev_period'; if(s) s.value='all'; if(cat) cat.value='all'; if(df) df.value=''; if(dt) dt.value=''; if(sr) sr.value=''; if(t) t.value='expenses'; }"
                           title="Reset filters"
                           class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 border border-gray-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Secondary Filter Row: Custom Date Range & Search -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-gray-100 items-end">
                <div>
                    <label for="date-from-input" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">From Date (Custom)</label>
                    <input type="date"
                           id="date-from-input"
                           name="date_from"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>"
                           onchange="var pe=document.getElementById('period-select'); if(pe && this.value) pe.value='custom';"
                           class="block w-full py-1.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                </div>
                <div>
                    <label for="date-to-input" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">To Date (Custom)</label>
                    <input type="date"
                           id="date-to-input"
                           name="date_to"
                           value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>"
                           onchange="var pe=document.getElementById('period-select'); if(pe && this.value) pe.value='custom';"
                           class="block w-full py-1.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                </div>
                <div>
                    <label for="expense-search-input" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Search Ledger</label>
                    <input type="text"
                           id="expense-search-input"
                           name="search"
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search by expense UID (exp_*), title, vendor, invoice ref, or stay UID..."
                           class="block w-full py-1.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
                </div>
            </div>
        </form>
    </div>

    <!-- Main Financial Report Content Container -->
    <?= $reportContentHtml ?>

</div>

<!-- Modal Container for HTMX Expense CRUD -->
<div id="modal-container"></div>

<!-- Drawer Container for Reservation Inspection -->
<div id="drawer-container"></div>
