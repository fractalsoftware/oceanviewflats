<?php
/**
 * @var list<array<string, mixed>> $items
 * @var int $total
 * @var int $page
 * @var int $per_page
 * @var int $total_pages
 * @var array<string, mixed> $filters
 */

$startItem = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$endItem = min($total, $page * $per_page);

/**
 * Helper to build pagination query strings preserving active filters.
 *
 * @param array<string, mixed> $currentFilters
 * @param int $targetPage
 * @return string
 */
$buildPageUrl = static function (array $currentFilters, int $targetPage): string {
    $params = array_merge($currentFilters, ['page' => $targetPage]);
    return '/reservations?' . http_build_query($params);
};
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <!-- Header summary info -->
    <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-gray-50/50">
        <div class="text-xs text-gray-500 font-medium">
            <?php if ($total > 0): ?>
                Showing <span class="font-semibold text-gray-800"><?= $startItem ?></span> to <span class="font-semibold text-gray-800"><?= $endItem ?></span> of <span class="font-semibold text-gray-800"><?= number_format($total) ?></span> reservations
            <?php else: ?>
                No reservations found
            <?php endif; ?>
        </div>
        <div class="text-xs text-gray-400">
            Page <?= $page ?> of <?= $total_pages ?>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <tr>
                    <th scope="col" class="px-6 py-3.5">Property</th>
                    <th scope="col" class="px-6 py-3.5">Guest & Contact</th>
                    <th scope="col" class="px-6 py-3.5">Stay Dates</th>
                    <th scope="col" class="px-6 py-3.5">Total (COP)</th>
                    <th scope="col" class="px-6 py-3.5">Status</th>
                    <th scope="col" class="px-6 py-3.5">Registry</th>
                    <th scope="col" class="px-6 py-3.5">Access PIN</th>
                    <th scope="col" class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center">
                            <div class="max-w-xs mx-auto">
                                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                <p class="text-sm font-medium text-gray-700">No reservations found</p>
                                <p class="text-xs text-gray-500 mt-1">Try adjusting your filter criteria or search terms.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $uid = (string) $item['reservation_uid'];
                        $propertyId = (string) $item['property_id'];
                        $checkIn = (string) $item['check_in'];
                        $checkOut = (string) $item['check_out'];
                        $nights = (int) max(1, (strtotime($checkOut) - strtotime($checkIn)) / 86400);
                        $status = (string) ($item['status'] ?? 'pending_payment');
                        $registryCompleted = (int) ($item['registry_completed'] ?? 0) === 1;
                        $doorCode = (string) ($item['door_code'] ?? '');
                        $source = (string) ($item['source'] ?? 'web');
                        $totalPrice = (float) ($item['total_price'] ?? 0.0);

                        $isConcluded = \OceanViewFlats\Domain\Reservation\Reservation::isStayConcluded($status, $checkOut);
                        $isEditable = ($status === 'confirmed' && !$isConcluded);
                        ?>
                        <tr class="hover:bg-indigo-50/30 transition-colors duration-150">
                            <!-- Property -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $propertyId === '1606' ? 'bg-sky-100 text-sky-800' : 'bg-purple-100 text-purple-800' ?>">
                                    Apto <?= htmlspecialchars($propertyId, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- Guest & Contact -->
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($item['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars((string) ($item['guest_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars((string) ($item['guest_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>

                            <!-- Stay Dates -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($checkIn, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($checkOut, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-gray-400 mt-0.5"><?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?></div>
                            </td>

                            <!-- Total -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-semibold text-gray-900">$<?= number_format($totalPrice, 0, '.', ',') ?> COP</div>
                                <div class="text-[11px] text-gray-400 uppercase tracking-tight"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($status === 'confirmed'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500"></span> Confirmed
                                    </span>
                                <?php elseif ($status === 'pending_payment'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-amber-500 animate-pulse"></span> Pending
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-rose-500"></span> Cancelled
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Registry -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button type="button"
                                        hx-get="/reservations/<?= urlencode($uid) ?>/registry"
                                        hx-target="#modal-container"
                                        hx-swap="innerHTML"
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer <?= $registryCompleted ? 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200' ?>"
                                        title="Click to view registry details">
                                    <?php if ($registryCompleted): ?>
                                        <svg class="w-3.5 h-3.5 mr-1 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Completed
                                    <?php else: ?>
                                        <svg class="w-3.5 h-3.5 mr-1 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Pending
                                    <?php endif; ?>
                                </button>
                            </td>

                            <!-- Access PIN -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($doorCode !== ''): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                        <?= htmlspecialchars($doorCode, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">&mdash;</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-1.5">
                                <?php if ($isEditable): ?>
                                    <button type="button"
                                            hx-get="/reservations/<?= urlencode($uid) ?>/edit"
                                            hx-target="#modal-container"
                                            hx-swap="innerHTML"
                                            title="Edit Reservation"
                                            class="inline-flex items-center px-2.5 py-1.5 border border-gray-200 shadow-2xs text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 hover:text-indigo-700 hover:border-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 transition cursor-pointer">
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit Reservation</span>
                                    </button>
                                <?php endif; ?>
                                <button type="button"
                                        hx-get="/reservations/<?= urlencode($uid) ?>"
                                        hx-target="#drawer-container"
                                        hx-push-url="/reservations/<?= urlencode($uid) ?>"
                                        class="inline-flex items-center px-3 py-1.5 border border-indigo-200 shadow-2xs text-xs font-semibold rounded-md text-indigo-700 bg-indigo-50/60 hover:bg-indigo-100 hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 transition cursor-pointer">
                                    <span>Details</span>
                                    <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200 flex items-center justify-between">
            <div>
                <?php if ($page > 1): ?>
                    <button type="button"
                            hx-get="<?= htmlspecialchars($buildPageUrl($filters, $page - 1), ENT_QUOTES, 'UTF-8') ?>"
                            hx-target="#reservations-table-container"
                            hx-push-url="true"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                        &larr; Previous
                    </button>
                <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1.5 border border-gray-200 text-xs font-medium rounded-md text-gray-300 bg-gray-50 cursor-not-allowed">
                        &larr; Previous
                    </span>
                <?php endif; ?>
            </div>

            <div class="hidden sm:flex items-center space-x-1">
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                    <button type="button"
                            hx-get="<?= htmlspecialchars($buildPageUrl($filters, $p), ENT_QUOTES, 'UTF-8') ?>"
                            hx-target="#reservations-table-container"
                            hx-push-url="true"
                            class="px-3 py-1.5 text-xs font-medium rounded-md transition cursor-pointer <?= $p === $page ? 'bg-indigo-600 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300' ?>">
                        <?= $p ?>
                    </button>
                <?php endfor; ?>
            </div>

            <div>
                <?php if ($page < $total_pages): ?>
                    <button type="button"
                            hx-get="<?= htmlspecialchars($buildPageUrl($filters, $page + 1), ENT_QUOTES, 'UTF-8') ?>"
                            hx-target="#reservations-table-container"
                            hx-push-url="true"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                        Next &rarr;
                    </button>
                <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1.5 border border-gray-200 text-xs font-medium rounded-md text-gray-300 bg-gray-50 cursor-not-allowed">
                        Next &rarr;
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
