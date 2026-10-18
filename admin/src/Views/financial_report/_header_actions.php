<?php
/**
 * Header Actions Partial for Financial Report.
 *
 * @var array<string, string> $filters
 * @var bool $oob
 */

$oobAttr = !empty($oob) ? ' hx-swap-oob="outerHTML"' : '';
$propertyId = (string) ($filters['property_id'] ?? 'all');
$newExpenseProp = in_array($propertyId, ['1606', '1707', 'shared'], true) ? $propertyId : '1606';

$exportQuery = http_build_query(array_filter([
    'property_id' => $filters['property_id'] ?? 'all',
    'period' => $filters['period'] ?? 'this_month',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'compare' => $filters['compare'] ?? 'prev_period',
    'source' => $filters['source'] ?? 'all',
    'category' => $filters['category'] ?? 'all',
    'search' => $filters['search'] ?? '',
], static fn (string $v): bool => $v !== ''));
?>
<div id="financial-report-header-actions" class="flex flex-wrap items-center gap-2.5"<?= $oobAttr ?>>
    <a id="export-reservations-csv-btn"
       href="/financial-report/export/reservations.csv<?= $exportQuery !== '' ? '?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') : '' ?>"
       class="inline-flex items-center px-3 py-2 border border-gray-200 shadow-2xs text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition">
        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Export Reservations CSV
    </a>
    <a id="export-expenses-csv-btn"
       href="/financial-report/export/expenses.csv<?= $exportQuery !== '' ? '?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') : '' ?>"
       class="inline-flex items-center px-3 py-2 border border-gray-200 shadow-2xs text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition">
        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Export Expenses CSV
    </a>
    <button type="button"
            hx-get="/financial-report/expenses/new?<?= htmlspecialchars(http_build_query(array_filter([
                'property_id' => $filters['property_id'] ?? $newExpenseProp,
                'period' => $filters['period'] ?? 'this_month',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'compare' => $filters['compare'] ?? 'prev_period',
                'source' => $filters['source'] ?? 'all',
                'category' => $filters['category'] ?? 'all',
                'tab' => $filters['tab'] ?? 'expenses',
                'search' => $filters['search'] ?? '',
            ], static fn (string $v): bool => $v !== '')), ENT_QUOTES, 'UTF-8') ?>"
            hx-target="#modal-container"
            hx-swap="innerHTML"
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition cursor-pointer">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        + Record Operational Expense
    </button>
</div>
