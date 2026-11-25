<?php
/**
 * HTMX Partial: Dashboard 7-Day Operations Horizon Feed
 *
 * @var \OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData $viewData
 * @var string $propertyId
 */
use OceanViewFlats\Domain\Reservation\Dashboard\MovementType;

$scheduleByDate = $viewData->scheduleByDate;
$firstScheduleDate = $scheduleByDate !== [] ? (string) array_key_first($scheduleByDate) : '';
$todayDt = ($firstScheduleDate !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $firstScheduleDate) : false)
    ?: new \DateTimeImmutable('today', new \DateTimeZone('America/Bogota'));
$today = $todayDt->format('Y-m-d');
$tomorrow = $todayDt->modify('+1 day')->format('Y-m-d');
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
    <!-- Header -->
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50/70">
        <div>
            <h2 class="text-sm font-bold text-gray-900 tracking-tight">7-Day Operations Horizon</h2>
            <p class="text-xs text-gray-500">Arrivals, departures, and turnover handoffs across all units.</p>
        </div>
        <span class="text-xs font-semibold text-gray-600 bg-white px-2.5 py-1 rounded-md border border-gray-200 shadow-2xs">
            <?= $todayDt->format('M j') ?> – <?= $todayDt->modify('+6 days')->format('M j') ?>
        </span>
    </div>

    <!-- Feed Container -->
    <div class="divide-y divide-gray-100">
        <?php if (empty($scheduleByDate)): ?>
            <div class="p-8 text-center text-gray-400 text-xs">
                No movements scheduled for the current horizon.
            </div>
        <?php else: ?>
            <?php foreach ($scheduleByDate as $date => $events): ?>
                <?php
                $dateDt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $date) ?: new \DateTimeImmutable((string) $date);
                if ($date === $today) {
                    $dayLabel = 'Today (' . $dateDt->format('D, M j') . ')';
                    $headerBg = 'bg-indigo-50/40 text-indigo-900';
                } elseif ($date === $tomorrow) {
                    $dayLabel = 'Tomorrow (' . $dateDt->format('D, M j') . ')';
                    $headerBg = 'bg-gray-50 text-gray-800';
                } else {
                    $dayLabel = $dateDt->format('l, M j');
                    $headerBg = 'bg-gray-50/60 text-gray-700';
                }

                // Check for turnovers
                $turnovers = array_filter($events, fn($e) => $e->movementType === MovementType::TURNOVER);
                ?>
                <div class="p-5 space-y-3">
                    <!-- Day Subheading -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider <?= $date === $today ? 'text-indigo-700' : 'text-gray-700' ?>">
                            <?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="text-[11px] text-gray-400 font-medium">
                            <?= count($events) ?> <?= count($events) === 1 ? 'event' : 'events' ?>
                        </span>
                    </div>

                    <!-- Turnover Alert Banners (if any) -->
                    <?php foreach ($turnovers as $turnover): ?>
                        <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="text-amber-600 font-black">⚡</span>
                                <div>
                                    <span class="font-bold">Same-Day Turnover (Apartment <?= htmlspecialchars($turnover->propertyId, ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <span class="text-[11px] text-amber-800 ml-1">
                                        Departing: <strong><?= htmlspecialchars((string) $turnover->departingGuestName, ENT_QUOTES, 'UTF-8') ?></strong> (11:00 AM) &rarr; 
                                        Arriving: <strong><?= htmlspecialchars($turnover->guestName, ENT_QUOTES, 'UTF-8') ?></strong> (3:00 PM)
                                    </span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900 shrink-0">4h Window</span>
                        </div>
                    <?php endforeach; ?>

                    <!-- Events for this Date -->
                    <?php if (empty($events)): ?>
                        <p class="text-[11px] text-gray-400 italic pl-1">No arrivals or departures scheduled.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($events as $event): ?>
                                <?php
                                $isCheckIn = $event->movementType === MovementType::CHECK_IN || $event->movementType === MovementType::TURNOVER;
                                $timeFormatted = $isCheckIn ? '3:00 PM' : '11:00 AM';
                                ?>
                                <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-white hover:border-gray-300 transition shadow-2xs">
                                    <!-- Left: Movement Badge & Details -->
                                    <div class="flex items-center space-x-3 min-w-0">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 <?= $isCheckIn ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' ?>">
                                            <?= $event->movementType === MovementType::TURNOVER ? 'IN ⚡' : ($isCheckIn ? 'IN' : 'OUT') ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-gray-900 flex items-center gap-1.5 truncate">
                                                <span class="truncate"><?= htmlspecialchars($event->guestName, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold shrink-0 <?= $event->propertyId === '1606' ? 'bg-sky-100 text-sky-800' : 'bg-purple-100 text-purple-800' ?>">
                                                    Apt <?= htmlspecialchars($event->propertyId, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">
                                                <span>Time: <?= htmlspecialchars($timeFormatted, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="mx-1">&bull;</span>
                                                <span class="capitalize">Source: <?= htmlspecialchars($event->source, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right: Registry, Door PIN & Action -->
                                    <div class="flex items-center space-x-2 shrink-0">
                                        <?php if ($isCheckIn): ?>
                                            <?php if ($event->registryCompleted): ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    ✓ Registry OK
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    ⚠️ Pending
                                                </span>
                                            <?php endif; ?>

                                            <?php if (!empty($event->doorCode)): ?>
                                                <span class="px-2 py-0.5 bg-gray-100 text-gray-800 rounded text-[10px] font-mono font-bold">
                                                    PIN: <?= htmlspecialchars($event->doorCode, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 rounded text-[10px] font-semibold">
                                                    🔒 Withheld
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Inspect Drawer Link -->
                                        <a href="/reservations/<?= urlencode($event->reservationUid) ?>"
                                           hx-get="/reservations/<?= urlencode($event->reservationUid) ?>"
                                           hx-target="#drawer-container"
                                           hx-swap="innerHTML"
                                           role="button"
                                           title="Inspect Reservation"
                                           class="p-1 text-gray-400 hover:text-indigo-600 rounded transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
