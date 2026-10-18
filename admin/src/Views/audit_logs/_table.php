<?php
/**
 * @var list<array<string, mixed>> $items
 * @var int $total
 * @var int $page
 * @var int $per_page
 * @var int $total_pages
 * @var array<string, mixed> $filters
 */

if (!function_exists('getAuditActionBadgeClass')) {
    /**
     * Returns Tailwind class string for action pills based on operational risk/domain.
     */
    function getAuditActionBadgeClass(string $action): string {
        if (str_contains($action, 'failed') || str_contains($action, 'cancelled') || str_contains($action, 'delete')) {
            return 'bg-rose-50 text-rose-700 ring-rose-600/20 border-rose-200';
        }
        if (str_contains($action, 'update') || str_contains($action, 'override') || str_contains($action, 'regenerate')) {
            return 'bg-amber-50 text-amber-700 ring-amber-600/20 border-amber-200';
        }
        if (str_contains($action, 'block') || str_contains($action, 'sync')) {
            return 'bg-purple-50 text-purple-700 ring-purple-600/20 border-purple-200';
        }
        if (str_contains($action, 'create') || str_contains($action, 'success') || str_contains($action, 'complete') || str_contains($action, 'submitted')) {
            return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 border-emerald-200';
        }
        return 'bg-gray-50 text-gray-700 ring-gray-600/20 border-gray-200';
    }
}

if (!function_exists('formatAuditAction')) {
    /**
     * Converts internal machine action to clean human-readable title.
     */
    function formatAuditAction(string $action): string {
        return ucwords(str_replace('_', ' ', $action));
    }
}

$startEntry = $total > 0 ? (($page - 1) * $per_page) + 1 : 0;
$endEntry = min($total, $page * $per_page);
?>

<div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">

    <?php if (empty($items)): ?>
        <div class="p-12 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 text-gray-400 mx-auto flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-gray-900">No audit logs found</h3>
            <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                No operational audit events matched your selected filter criteria. Try adjusting your query or resetting the filters.
            </p>
        </div>
    <?php else: ?>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs text-gray-600">
                <thead class="bg-gray-50 text-2xs uppercase tracking-wider text-gray-500 font-semibold border-b border-gray-200">
                    <tr>
                        <th scope="col" class="py-3.5 pl-5 pr-3">Timestamp (COT)</th>
                        <th scope="col" class="px-3 py-3.5">Action</th>
                        <th scope="col" class="px-3 py-3.5">Target Entity</th>
                        <th scope="col" class="px-3 py-3.5">Actor</th>
                        <th scope="col" class="px-3 py-3.5">IP Address</th>
                        <th scope="col" class="py-3.5 pl-3 pr-5 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($items as $item): ?>
                        <?php
                            $actionClass = getAuditActionBadgeClass($item['action']);
                            $actionLabel = formatAuditAction($item['action']);
                            $entityType = (string) $item['entity_type'];
                            $entityId = (string) $item['entity_id'];
                            $isReservation = ($entityType === 'reservation');
                            $isOperationalExpense = ($entityType === 'operational_expense');
                        ?>
                        <tr class="hover:bg-gray-50/75 transition-colors group">
                            
                            <!-- Timestamp -->
                            <td class="py-3.5 pl-5 pr-3 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="text-2xs text-gray-400">
                                    ID #<?= (int) $item['id'] ?>
                                </div>
                            </td>

                            <!-- Action -->
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-2xs font-semibold ring-1 ring-inset border <?= $actionClass ?>">
                                    <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- Target Entity -->
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <div class="flex items-center space-x-1.5">
                                    <span class="text-2xs uppercase tracking-wider font-semibold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
                                        <?= htmlspecialchars($entityType, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if ($isReservation): ?>
                                        <a href="/reservations?search=<?= urlencode($entityId) ?>"
                                           title="View reservation in ledger"
                                           class="font-mono text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                                            <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php elseif ($isOperationalExpense): ?>
                                        <a href="/financial-report?tab=expenses&amp;search=<?= urlencode($entityId) ?>"
                                           title="View operational expense in ledger"
                                           class="font-mono text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                                            <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="font-mono text-xs font-medium text-gray-700">
                                            <?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Actor -->
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <?php if ($item['admin_user_name'] !== null): ?>
                                    <div class="flex items-center space-x-1.5">
                                        <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-2xs">
                                            <?= htmlspecialchars(strtoupper(substr($item['admin_user_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-900"><?= htmlspecialchars($item['admin_user_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-2xs text-gray-400">(<?= htmlspecialchars($item['admin_user_role'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>)</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span>
                                        System / Guest
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Client IP Address -->
                            <td class="px-3 py-3.5 whitespace-nowrap font-mono text-2xs text-gray-500">
                                <?= htmlspecialchars((string) ($item['ip_address'] !== '' ? $item['ip_address'] : '—'), ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <!-- Inspect Action -->
                            <td class="py-3.5 pl-3 pr-5 text-right whitespace-nowrap">
                                <button type="button"
                                        hx-get="/audit-logs/<?= (int) $item['id'] ?>"
                                        hx-target="#drawer-container"
                                        class="inline-flex items-center px-2.5 py-1.5 border border-gray-200 shadow-2xs text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 hover:text-indigo-600 hover:border-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Inspect
                                </button>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-gray-600">
            <div>
                Showing <span class="font-semibold text-gray-900"><?= $startEntry ?></span> to <span class="font-semibold text-gray-900"><?= $endEntry ?></span> of <span class="font-semibold text-gray-900"><?= $total ?></span> events
            </div>

            <div class="flex items-center space-x-2">
                <?php
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($total_pages, $page + 1);
                    $prevParams = array_merge($filters, ['page' => $prevPage]);
                    $nextParams = array_merge($filters, ['page' => $nextPage]);
                ?>

                <!-- Prev Button -->
                <button type="button"
                        <?= $page <= 1 ? 'disabled' : '' ?>
                        hx-get="/audit-logs?<?= http_build_query($prevParams) ?>"
                        hx-target="#audit-table-container"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs transition">
                    &larr; Previous
                </button>

                <!-- Page indicator -->
                <span class="px-2 text-2xs font-semibold text-gray-500">
                    Page <?= $page ?> of <?= max(1, $total_pages) ?>
                </span>

                <!-- Next Button -->
                <button type="button"
                        <?= $page >= $total_pages ? 'disabled' : '' ?>
                        hx-get="/audit-logs?<?= http_build_query($nextParams) ?>"
                        hx-target="#audit-table-container"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs transition">
                    Next &rarr;
                </button>
            </div>
        </div>

    <?php endif; ?>

</div>
