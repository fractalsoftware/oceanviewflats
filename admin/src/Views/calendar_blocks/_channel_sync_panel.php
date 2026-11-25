<?php
/**
 * Inbound Channel Sync Panel Partial for Calendar Blocks.
 *
 * Rendered as an HTMX partial or included in the master calendar blocks view.
 * Supports on-demand manual sync for all feeds or individual units,
 * live health indicators, masked feed links, and diagnostic error banners per ADR 0002.
 *
 * @var string $health ('healthy'|'degraded'|'error'|'pending')
 * @var string $badgeText
 * @var string $badgeClasses
 * @var string $badgeDotClass
 * @var string|null $lastSyncedAt
 * @var string $relativeSyncedTime
 * @var int $totalBlockedNights
 * @var array<string, array{
 *     id: string,
 *     name: string,
 *     status: ?\OceanViewFlats\Domain\Reservation\ChannelSyncStatus,
 *     health: 'healthy'|'degraded'|'error'|'pending',
 *     badgeText: string,
 *     badgeClasses: string,
 *     badgeDotClass: string,
 *     lastSyncedAt: ?string,
 *     lastAttemptedAt: ?string,
 *     relativeSyncedTime: string,
 *     blockedNightsCount: int,
 *     feedUrl: ?string,
 *     maskedFeedUrl: string,
 *     httpCode: ?int,
 *     errorMessage: ?string,
 *     isDegraded: bool,
 *     isError: bool
 * }> $properties
 * @var bool $hasDiagnostics
 * @var list<array{
 *     propertyId: string,
 *     propertyName: string,
 *     isDegraded: bool,
 *     isError: bool,
 *     httpCode: ?int,
 *     errorMessage: string,
 *     blockedNightsRetained: int
 * }> $diagnostics
 * @var array{type: string, message: string, isCooldown?: bool, propertyId?: ?string}|null $syncNotice
 * @var string $noticeClasses
 * @var string $csrfToken
 */
?>
<div id="channel-sync-panel" class="bg-white rounded-xl border border-gray-200 shadow-2xs p-5 space-y-4">
    <!-- Panel Header: Title, Global Health & Global Sync Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0 bg-indigo-50 rounded-lg p-2 text-indigo-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h2 class="text-base font-bold text-gray-900 tracking-tight">Inbound Channel Sync</h2>
                    <span class="text-2xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-600 rounded">Channel Feeds</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">
                    Live upstream calendar feeds imported to reserve dates, prevent double-bookings, and track external holds.
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3 flex-shrink-0">
            <!-- Global Health Status Badge -->
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border <?= $badgeClasses ?>">
                <span class="w-1.5 h-1.5 mr-1.5 rounded-full <?= $badgeDotClass ?>"></span>
                <?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?>
            </span>

            <!-- Global Sync All Button -->
            <button type="button"
                    hx-post="/channel-sync?view=panel"
                    hx-target="#channel-sync-panel"
                    hx-swap="outerHTML"
                    hx-indicator="#sync-spinner-all"
                    hx-disabled-elt="this"
                    class="inline-flex items-center px-3.5 py-1.5 border border-transparent text-xs font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-2xs cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <span>Sync All</span>
                <span id="sync-spinner-all" class="htmx-indicator ml-1.5">
                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </div>
    </div>

    <!-- Diagnostic Error Banner (Upstream error callout banner if any property is Degraded or Error) -->
    <?php if (!empty($hasDiagnostics) && !empty($diagnostics)): ?>
        <div id="channel-sync-diagnostics" class="space-y-2">
            <?php foreach ($diagnostics as $diag): ?>
                <?php
                $isDegraded = !empty($diag['isDegraded']);
                $diagBg = $isDegraded ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-rose-50 border-rose-200 text-rose-900';
                $diagIconColor = $isDegraded ? 'text-amber-500' : 'text-rose-500';
                ?>
                <div class="p-3.5 rounded-lg border <?= $diagBg ?> text-xs flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-0.5 <?= $diagIconColor ?>">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="flex-1 space-y-1">
                        <div class="font-semibold flex items-center space-x-2">
                            <span>Upstream Feed Warning &bull; <?= htmlspecialchars($diag['propertyName'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($diag['httpCode'])): ?>
                                <span class="font-mono text-2xs px-1.5 py-0.2 bg-white bg-opacity-70 rounded border border-current">
                                    HTTP <?= (int) $diag['httpCode'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-opacity-95 leading-relaxed">
                            <span class="font-medium">Error:</span> <?= htmlspecialchars($diag['errorMessage'], ENT_QUOTES, 'UTF-8') ?>.
                            <?php if ($isDegraded): ?>
                                <span class="font-medium">Previous cached blocks (<?= (int) $diag['blockedNightsRetained'] ?> nights) were safely retained</span> to prevent unintended double-booking.
                            <?php else: ?>
                                No previous cached calendar blocks were available.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- User Action Notification Banner (Sync Feedback / Cooldown) -->
    <?php if (!empty($syncNotice)): ?>
        <div class="p-3 rounded-lg text-xs <?= $noticeClasses ?> flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span><?= htmlspecialchars($syncNotice['message'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($syncNotice['isCooldown'])): ?>
                    <button type="button"
                            hx-post="/channel-sync?force=1&view=panel<?= !empty($syncNotice['propertyId']) ? '&property_id=' . urlencode($syncNotice['propertyId']) : '' ?>"
                            hx-target="#channel-sync-panel"
                            hx-swap="outerHTML"
                            hx-indicator="#sync-spinner-all"
                            class="ml-2 font-bold underline hover:opacity-80 cursor-pointer">
                        Force Sync
                    </button>
                <?php endif; ?>
            </div>
            <button type="button" data-action="dismiss-parent" class="text-gray-400 hover:text-gray-600 cursor-pointer" aria-label="Dismiss notification">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Per-Unit Grid (Property 1606 and Property 1707) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($properties as $prop): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex flex-col justify-between space-y-3">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($prop['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-2xs font-semibold uppercase px-2 py-0.5 bg-gray-200 text-gray-700 rounded">Property <?= htmlspecialchars($prop['id'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <!-- Individual Unit Health Badge -->
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-medium border <?= $prop['badgeClasses'] ?>">
                            <span class="w-1.5 h-1.5 mr-1 rounded-full <?= $prop['badgeDotClass'] ?>"></span>
                            <?= htmlspecialchars($prop['badgeText'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <!-- Upstream Feed URL Indicator -->
                    <div class="text-xs text-gray-500 flex items-center space-x-1 min-w-0">
                        <span class="font-medium text-gray-600 flex-shrink-0">Feed URL:</span>
                        <span class="font-mono text-2xs text-gray-500 truncate" title="<?= htmlspecialchars($prop['feedUrl'] ?? 'Not configured', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($prop['maskedFeedUrl'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <!-- Blocked Nights Count & Last Sync Relative Time with ISO-8601 hover tooltip -->
                    <div class="pt-2 border-t border-gray-200/60 flex items-center justify-between text-xs text-gray-600">
                        <div>
                            <span class="text-base font-bold text-gray-900"><?= (int) $prop['blockedNightsCount'] ?></span>
                            <span class="text-gray-500 ml-1">blocked nights</span>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center cursor-help text-gray-500 hover:text-gray-700"
                                  title="<?= htmlspecialchars($prop['lastSyncedAt'] ?? 'Never synced', ENT_QUOTES, 'UTF-8') ?>">
                                <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?= htmlspecialchars($prop['relativeSyncedTime'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Individual Unit Sync Action Footer -->
                <div class="pt-2 border-t border-gray-200/60 flex justify-end">
                    <button type="button"
                            hx-post="/channel-sync?property_id=<?= urlencode((string) $prop['id']) ?>&view=panel"
                            hx-target="#channel-sync-panel"
                            hx-swap="outerHTML"
                            hx-indicator="#sync-spinner-<?= htmlspecialchars((string) $prop['id'], ENT_QUOTES, 'UTF-8') ?>"
                            hx-disabled-elt="this"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 shadow-2xs transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <span>Sync Property</span>
                        <span id="sync-spinner-<?= htmlspecialchars((string) $prop['id'], ENT_QUOTES, 'UTF-8') ?>" class="htmx-indicator ml-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-gray-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Detected External Bookings & Guest Onboarding (ADR 0007 / Variant A) -->
    <div class="mt-6 pt-5 border-t border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center">
                    <svg class="w-4 h-4 mr-1.5 text-rose-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                    Detected External Platform Bookings (Airbnb)
                </h4>
                <p class="text-xs text-gray-500 mt-0.5">Ephemeral blocks detected from connected iCal calendars. Onboard guests to issue Guest Registry and Access Guides.</p>
            </div>
            <span class="text-xs text-gray-400 font-medium"><?= count($detectedBlocks ?? []) ?> Active <?= count($detectedBlocks ?? []) === 1 ? 'Booking' : 'Bookings' ?></span>
        </div>

        <?php if (empty($detectedBlocks)): ?>
            <div class="bg-gray-50 border border-gray-200/80 rounded-xl p-6 text-center">
                <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="mt-2 text-xs text-gray-500 font-medium">No active external calendar blocks detected across connected feeds.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-gray-500 font-semibold uppercase text-2xs tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">Property</th>
                                <th scope="col" class="px-4 py-3 text-left">Dates & Duration</th>
                                <th scope="col" class="px-4 py-3 text-left">Source Feed</th>
                                <th scope="col" class="px-4 py-3 text-left">Status</th>
                                <th scope="col" class="px-4 py-3 text-right">Workflow Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php foreach ($detectedBlocks as $block): ?>
                                <tr class="hover:bg-gray-50/80 transition">
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="font-bold text-gray-900"><?= htmlspecialchars((string) $block['propertyName'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-2xs text-gray-400">Playa Salguero</div>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) $block['startDate'], ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars((string) $block['endDate'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-2xs text-gray-500"><?= (int) $block['nights'] ?> <?= (int) $block['nights'] === 1 ? 'night' : 'nights' ?> stay</div>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <?= htmlspecialchars(strtoupper((string) $block['source']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <?php if (!empty($block['isOnboarded'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                                Onboarded (<?= htmlspecialchars((string) $block['reservationUid'], ENT_QUOTES, 'UTF-8') ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                                Pending Onboarding
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                        <?php if (!empty($block['isOnboarded'])): ?>
                                            <button type="button"
                                                    hx-get="/reservations/<?= urlencode((string) $block['reservationUid']) ?>"
                                                    hx-target="#drawer-container"
                                                    class="text-indigo-600 hover:text-indigo-800 font-semibold text-xs inline-flex items-center cursor-pointer">
                                                <span>View Reservation</span>
                                                <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    hx-get="/reservations/new?property_id=<?= urlencode((string) $block['propertyId']) ?>&check_in=<?= urlencode((string) $block['startDate']) ?>&check_out=<?= urlencode((string) $block['endDate']) ?>&source=airbnb"
                                                    hx-target="#modal-container"
                                                    hx-swap="innerHTML"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#FF385C] hover:bg-[#E00B41] text-white shadow-2xs transition cursor-pointer">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                                <span>Onboard Guest</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
