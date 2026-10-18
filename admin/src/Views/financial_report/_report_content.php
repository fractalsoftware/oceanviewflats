<?php
/**
 * Main Financial Report Content Partial (#financial-report-content).
 *
 * @var list<\OceanViewFlats\Domain\Finance\OperationalExpense> $expenses
 * @var array<string, string> $filters
 * @var array<string, float> $summary
 * @var array<string, float> $categoryTotals
 * @var string $csrfToken
 * @var bool $oob
 * @var string|null $flashMessage
 */

use OceanViewFlats\Domain\Finance\ExpenseCategory;

$oobAttr = !empty($oob) ? ' hx-swap-oob="outerHTML"' : '';
$propertyFilter = (string) ($filters['property_id'] ?? 'all');
$activeTab = (string) ($filters['tab'] ?? 'expenses');
$periodLabel = (string) ($filters['period_label'] ?? 'Selected Period');

$directExpenses = (float) ($summary['direct_expenses'] ?? 0.0);
$allocatedSharedExpenses = (float) ($summary['allocated_shared_expenses'] ?? 0.0);
$totalExpenses = (float) ($summary['total_expenses'] ?? 0.0);

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

    <!-- Operational Expenses Summary & Category Breakdown Strip -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Operational Expenses</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700">
                    <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <div class="text-2xl font-extrabold text-gray-900 mt-2 tabular-nums">
                <?= formatCopCurrency($totalExpenses) ?>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-2 gap-2 text-[11px]">
                <div>
                    <span class="text-gray-400 block">Direct Property Expenses</span>
                    <span class="font-semibold text-gray-800 tabular-nums"><?= formatCopCompactCurrency($directExpenses) ?></span>
                </div>
                <div>
                    <span class="text-gray-400 block"><?= in_array($propertyFilter, ['1606', '1707'], true) ? 'Allocated Shared (50%)' : 'Shared Pool (100%)' ?></span>
                    <span class="font-semibold text-amber-700 tabular-nums"><?= formatCopCompactCurrency($allocatedSharedExpenses) ?></span>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Operational Expenses by Category</h3>
                <span class="text-[11px] text-gray-500"><?= count($expenses) ?> recorded <?= count($expenses) === 1 ? 'entry' : 'entries' ?></span>
            </div>
            <?php
            $nonZeroCategories = array_filter($categoryTotals, static fn (float $val): bool => $val > 0.0);
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
                        $pct = $totalExpenses > 0.0 ? ($catAmount / $totalExpenses) * 100.0 : 0.0;
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

    <!-- Bottom Detail Ledger Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/60">
            <div class="flex space-x-2">
                <?php
                $expTabQuery = http_build_query(array_merge($filters, ['tab' => 'expenses']));
                $resTabQuery = http_build_query(array_merge($filters, ['tab' => 'reservations']));
                ?>
                <a href="/financial-report?<?= htmlspecialchars($expTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-get="/financial-report?<?= htmlspecialchars($expTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-target="#financial-report-content"
                   hx-swap="outerHTML"
                   hx-push-url="true"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $activeTab === 'expenses' ? 'bg-indigo-600 text-white shadow-2xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100' ?>">
                    Operational Expenses Ledger (<?= count($expenses) ?>)
                </a>
                <a href="/financial-report?<?= htmlspecialchars($resTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-get="/financial-report?<?= htmlspecialchars($resTabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   hx-target="#financial-report-content"
                   hx-swap="outerHTML"
                   hx-push-url="true"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $activeTab === 'reservations' ? 'bg-indigo-600 text-white shadow-2xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100' ?>">
                    Attributed Reservations
                </a>
            </div>
            <div class="flex items-center space-x-3 text-xs text-gray-500">
                <span>Click <strong>Duplicate</strong> on any expense row to pre-fill recurring monthly bills.</span>
            </div>
        </div>

        <?php if (empty($expenses)): ?>
            <div class="p-8 text-center text-xs text-gray-500">
                No Operational Expenses match the current filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
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
                        <?php foreach ($expenses as $expense):
                            $expId = (int) $expense->id;
                            $allocationFactor = $expense->allocationFactorForProperty($propertyFilter);
                            $allocatedAmount = $expense->allocatedAmountForProperty($propertyFilter);
                        ?>
                            <tr class="hover:bg-gray-50/80" data-expense-uid="<?= htmlspecialchars($expense->expenseUid, ENT_QUOTES, 'UTF-8') ?>">
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-gray-600">
                                    <div><?= htmlspecialchars($expense->expenseDate, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-[10px] text-gray-400"><?= htmlspecialchars($expense->expenseUid, ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($expense->propertyId === 'shared'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                            Shared (<?= $allocationFactor === 0.5 ? '50% Alloc' : 'Portfolio' ?>)
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
                                    <?php if ($allocationFactor === 0.5): ?>
                                        <div class="text-[10px] text-amber-700">50% of <?= formatCopCompactCurrency($expense->amount) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right space-x-1.5">
                                    <button type="button"
                                            hx-get="/financial-report/expenses/new?duplicate_from=<?= $expId ?>"
                                            hx-target="#modal-container"
                                            hx-swap="innerHTML"
                                            class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold text-[11px] cursor-pointer"
                                            title="Duplicate for new month">
                                        Duplicate
                                    </button>
                                    <button type="button"
                                            hx-get="/financial-report/expenses/<?= $expId ?>/edit"
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
                                                'current_period' => $filters['period'] ?? '',
                                                'current_category' => $filters['category'] ?? 'all',
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
    </div>

</div>
