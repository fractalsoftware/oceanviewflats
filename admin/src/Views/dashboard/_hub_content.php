<?php
/**
 * HTMX Partial: Complete Reactive Dashboard Hub Content
 *
 * @var string $propertyId
 * @var \OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData $viewData
 * @var array<string, mixed> $channelCardData
 * @var string $csrfToken
 */
?>
<div id="dashboard-hub-content" class="space-y-6">
    <!-- Property Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-sm font-bold text-gray-900 tracking-tight">Operations Hub Overview</h2>
            <p class="text-xs text-gray-500">Real-time schedule, guest arrival readiness, rates, and active holds.</p>
        </div>
        <div class="flex items-center space-x-1 bg-gray-100 p-1 rounded-xl border border-gray-200 self-start sm:self-auto">
            <a href="/?property_id=all"
               hx-get="/dashboard/hub?property_id=all"
               hx-target="#dashboard-hub-content"
               hx-swap="outerHTML"
               hx-push-url="/?property_id=all"
               role="button"
               <?= $propertyId === 'all' ? 'aria-current="page" ' : '' ?>class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $propertyId === 'all' ? 'bg-indigo-600 text-white shadow-2xs' : 'text-gray-700 hover:bg-gray-200' ?>">
                All Properties
            </a>
            <a href="/?property_id=1606"
               hx-get="/dashboard/hub?property_id=1606"
               hx-target="#dashboard-hub-content"
               hx-swap="outerHTML"
               hx-push-url="/?property_id=1606"
               role="button"
               <?= $propertyId === '1606' ? 'aria-current="page" ' : '' ?>class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $propertyId === '1606' ? 'bg-indigo-600 text-white shadow-2xs' : 'text-gray-700 hover:bg-gray-200' ?>">
                Apartment 1606
            </a>
            <a href="/?property_id=1707"
               hx-get="/dashboard/hub?property_id=1707"
               hx-target="#dashboard-hub-content"
               hx-swap="outerHTML"
               hx-push-url="/?property_id=1707"
               role="button"
               <?= $propertyId === '1707' ? 'aria-current="page" ' : '' ?>class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer <?= $propertyId === '1707' ? 'bg-indigo-600 text-white shadow-2xs' : 'text-gray-700 hover:bg-gray-200' ?>">
                Apartment 1707
            </a>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <?php include __DIR__ . '/_summary_cards.php'; ?>

    <!-- 7-Month Occupancy Heatmap -->
    <?php if ($viewData->heatmap !== null): ?>
        <?php include __DIR__ . '/_occupancy_heatmap.php'; ?>
    <?php endif; ?>

    <!-- Two-Column Operations Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Main 8-Column Left Stream: 7-Day Operations Schedule -->
        <div class="lg:col-span-8 space-y-6">
            <?php include __DIR__ . '/_schedule_feed.php'; ?>
        </div>

        <!-- 4-Column Right Sidebar: Alerts, Rates, Holds, and Channel Feeds -->
        <div class="lg:col-span-4 space-y-6">
            <?php include __DIR__ . '/_alerts_card.php'; ?>
            <?php include __DIR__ . '/_rates_blocks_card.php'; ?>
            <?php
            extract($channelCardData, EXTR_OVERWRITE);
            include __DIR__ . '/_channel_card.php';
            ?>
        </div>
    </div>
</div>
