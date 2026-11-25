<?php
/**
 * HTMX Partial: Dashboard Inbound Channel Feeds Stat Card
 *
 * @var string $health
 * @var string $badgeText
 * @var string $badgeClasses
 * @var string $badgeDotClass
 * @var string $iconBgClass
 * @var string $iconTextClass
 * @var string|null $lastSyncedAt
 * @var string $relativeSyncedTime
 * @var int $totalBlockedNights
 * @var array{type: string, message: string, isCooldown?: bool, propertyId?: ?string}|null $syncNotice
 * @var string $noticeClasses
 * @var string $csrfToken
 */
?>
<div id="channel-card-container" class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-5 flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0 <?= $iconBgClass ?> rounded-lg p-3 <?= $iconTextClass ?>">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Channel Feeds</h3>
                    <p class="text-xs text-emerald-600 font-semibold">Channel Feeds Active</p>
                </div>
            </div>
            <!-- Health Badge -->
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= $badgeClasses ?>">
                <span class="w-1.5 h-1.5 mr-1.5 rounded-full <?= $badgeDotClass ?>"></span>
                <?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <!-- Metrics & Relative Sync Timestamp -->
        <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between text-xs text-gray-600">
            <div>
                <span class="font-semibold text-gray-900 text-sm"><?= (int) $totalBlockedNights ?></span>
                <span class="text-gray-500 ml-1">blocked nights</span>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center cursor-help text-gray-500 hover:text-gray-700"
                      title="<?= htmlspecialchars($lastSyncedAt ?? 'Never synced', ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span id="channel-card-relative-time"><?= htmlspecialchars($relativeSyncedTime, ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>
        </div>

        <?php if (!empty($syncNotice)): ?>
            <div class="mt-3 p-2.5 rounded-lg text-xs <?= $noticeClasses ?> flex items-start justify-between space-x-2">
                <div class="flex-1">
                    <span><?= htmlspecialchars($syncNotice['message'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($syncNotice['isCooldown'])): ?>
                        <button type="button"
                                hx-post="/channel-sync?force=1"
                                hx-target="#channel-card-container"
                                hx-swap="outerHTML"
                                hx-indicator="#channel-sync-spinner"
                                class="ml-1.5 font-bold underline hover:opacity-80 cursor-pointer">
                            Force Sync
                        </button>
                    <?php endif; ?>
                </div>
                <button type="button" data-action="dismiss-parent" class="text-gray-400 hover:text-gray-600 cursor-pointer" aria-label="Dismiss notice">&times;</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card Action Footer -->
    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
        <a href="/calendar-blocks" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
            View Details &rarr;
        </a>
        <button type="button"
                id="channel-sync-btn"
                hx-post="/channel-sync"
                hx-target="#channel-card-container"
                hx-swap="outerHTML"
                hx-indicator="#channel-sync-spinner"
                hx-disabled-elt="this"
                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-2xs transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
            <span>Sync Now</span>
            <span id="channel-sync-spinner" class="htmx-indicator ml-1.5">
                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
        </button>
    </div>
</div>
