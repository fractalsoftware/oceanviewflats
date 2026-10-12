<?php
/**
 * Slide-over drawer partial: Multi-stay and operational day summary
 *
 * @var \OceanViewFlats\Domain\Reservation\Dashboard\DayDetailViewData $summary
 * @var string $propertyFilter
 * @var string $csrfToken
 */
?>

<script>
window.closeReservationDrawer = window.closeReservationDrawer || function() {
    var container = document.getElementById('drawer-container');
    if (container) {
        container.innerHTML = '';
    } else {
        window.location.href = '/reservations';
        return;
    }
    if (window.location.pathname.startsWith('/reservations/')) {
        window.history.pushState(null, '', '/reservations');
    }
};
</script>

<div id="day-summary-drawer" class="fixed inset-0 z-40 overflow-hidden" aria-labelledby="slide-over-day-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs transition-opacity duration-300 cursor-pointer"
         onclick="closeReservationDrawer();"
         aria-hidden="true"></div>

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div class="w-screen max-w-xl bg-white shadow-2xl flex flex-col transform transition ease-in-out duration-300">

            <!-- Drawer Header -->
            <div class="px-6 py-5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                        <!-- Property Filter Badge -->
                        <?php if ($summary->propertyFilter === '1606'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-800">
                                Property 1606
                            </span>
                        <?php elseif ($summary->propertyFilter === '1707'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                Property 1707
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                All Properties
                            </span>
                        <?php endif; ?>

                        <!-- Capacity Badge -->
                        <?php if ($summary->capacityLevel === 'full'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                Full (<?= $summary->occupiedProperties ?>/<?= $summary->totalProperties ?>)
                            </span>
                        <?php elseif ($summary->capacityLevel === 'partial'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Partial (<?= $summary->occupiedProperties ?>/<?= $summary->totalProperties ?>)
                            </span>
                        <?php elseif ($summary->capacityLevel === 'maintenance'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                Maintenance
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                Vacant (0/<?= $summary->totalProperties ?>)
                            </span>
                        <?php endif; ?>

                        <?php if ($summary->isToday): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Today
                            </span>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900 mt-1" id="slide-over-day-title">
                        <?= htmlspecialchars($summary->formattedDate, ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Nightly Rate: <strong class="text-emerald-700 font-bold">$<?= number_format($summary->combinedRate, 0, '.', ',') ?> COP</strong>
                        &bull; <?= $summary->occupiedProperties ?> of <?= $summary->totalProperties ?> properties active
                    </p>
                </div>
                <button type="button"
                        onclick="closeReservationDrawer();"
                        class="rounded-lg p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition cursor-pointer">
                    <span class="sr-only">Close panel</span>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Drawer Scrollable Body -->
            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 divide-y divide-gray-100">

                <!-- 1. Operational Warnings Banner -->
                <?php if ($summary->hasWarning): ?>
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 space-y-1.5">
                        <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-rose-700">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Operational Warnings</span>
                        </div>
                        <ul class="text-xs list-disc list-inside space-y-0.5 text-rose-700 pl-1">
                            <?php foreach ($summary->warnings as $warn): ?>
                                <li><?= htmlspecialchars($warn, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- 2. Same-Day Turnover Banner -->
                <?php if ($summary->hasTurnover): ?>
                    <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 flex items-center space-x-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>
                        <div class="text-xs">
                            <strong class="font-semibold text-blue-900">Same-Day Turnover Scheduled</strong>
                            <span class="block text-blue-700">Guest departure (11:00 AM) and arrival (3:00 PM) scheduled on this calendar date.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 3. Property Breakdowns -->
                <div class="space-y-4 pt-1">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Property Status Breakdown</h3>

                    <?php foreach ($summary->properties as $prop): ?>
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xs space-y-3.5">
                            <!-- Property Header -->
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold <?= $prop->propertyId === '1606' ? 'bg-sky-100 text-sky-800' : 'bg-purple-100 text-purple-800' ?>">
                                        Property <?= htmlspecialchars($prop->propertyId, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($prop->propertyName, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-semibold text-gray-600">
                                        $<?= number_format($prop->nightlyRate, 0, '.', ',') ?> <span class="text-[10px] text-gray-400 font-normal">/nt</span>
                                    </span>
                                    <?php if ($prop->status === 'Occupied'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Occupied</span>
                                    <?php elseif ($prop->status === 'Turnover'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Turnover</span>
                                    <?php elseif ($prop->status === 'Maintenance'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Maintenance</span>
                                    <?php elseif ($prop->status === 'Pending'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">Pending</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Vacant</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Case A: Maintenance Hold -->
                            <?php if ($prop->maintenanceBlock !== null): ?>
                                <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-amber-900 space-y-1 text-xs">
                                    <div class="font-bold flex items-center justify-between">
                                        <span>Maintenance Hold Active</span>
                                        <span class="font-mono text-[10px] text-amber-700 uppercase">Blocked</span>
                                    </div>
                                    <div>
                                        <span class="text-amber-700">Reason:</span>
                                        <strong><?= htmlspecialchars($prop->maintenanceBlock->reason, ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="text-[11px] text-amber-600">
                                        Duration: <?= htmlspecialchars($prop->maintenanceBlock->startDate, ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($prop->maintenanceBlock->endDate, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>

                            <!-- Case B: Turnover (Departing & Arriving) -->
                            <?php elseif ($prop->hasTurnover()): ?>
                                <div class="space-y-2">
                                    <?php if ($prop->departingReservation !== null): ?>
                                        <?php
                                        $dep = $prop->departingReservation;
                                        $depPayStatus = (string) ($dep['payment_status'] ?? '');
                                        ?>
                                        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1.5">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-1.5 flex-wrap gap-y-1">
                                                    <span class="inline-flex items-center text-[10px] font-bold uppercase text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded">
                                                        Departure (11:00 AM)
                                                    </span>
                                                    <?php if ($depPayStatus !== ''): ?>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium <?= match ($depPayStatus) {
                                                            'paid', 'approved' => 'bg-emerald-100 text-emerald-800',
                                                            'refunded', 'partially_refunded' => 'bg-amber-100 text-amber-800',
                                                            default => 'bg-slate-100 text-slate-700',
                                                        } ?>">
                                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $depPayStatus)), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button"
                                                        hx-get="/reservations/<?= urlencode((string) $dep['reservation_uid']) ?>"
                                                        hx-target="#drawer-container"
                                                        hx-swap="innerHTML"
                                                        class="text-indigo-600 hover:underline font-mono text-[11px] font-semibold cursor-pointer">
                                                    <?= htmlspecialchars((string) $dep['reservation_uid'], ENT_QUOTES, 'UTF-8') ?> &rarr;
                                                </button>
                                            </div>
                                            <div class="font-medium text-gray-900">
                                                <?= htmlspecialchars((string) ($dep['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-slate-200/60">
                                                <span>Door PIN: <strong class="font-mono text-gray-800"><?= htmlspecialchars((string) ($dep['door_code'] ?? 'None'), ENT_QUOTES, 'UTF-8') ?></strong></span>
                                                <span>Stay: <?= htmlspecialchars((string) ($dep['check_in'] ?? '')) ?> &rarr; <?= htmlspecialchars((string) ($dep['check_out'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($prop->arrivingReservation !== null): ?>
                                        <?php
                                        $arr = $prop->arrivingReservation;
                                        $arrPayStatus = (string) ($arr['payment_status'] ?? '');
                                        ?>
                                        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1.5">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-1.5 flex-wrap gap-y-1">
                                                    <span class="inline-flex items-center text-[10px] font-bold uppercase text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded">
                                                        Arrival (3:00 PM)
                                                    </span>
                                                    <?php if ($arrPayStatus !== ''): ?>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium <?= match ($arrPayStatus) {
                                                            'paid', 'approved' => 'bg-emerald-100 text-emerald-800',
                                                            'refunded', 'partially_refunded' => 'bg-amber-100 text-amber-800',
                                                            default => 'bg-slate-100 text-slate-700',
                                                        } ?>">
                                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $arrPayStatus)), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button"
                                                        hx-get="/reservations/<?= urlencode((string) $arr['reservation_uid']) ?>"
                                                        hx-target="#drawer-container"
                                                        hx-swap="innerHTML"
                                                        class="text-indigo-600 hover:underline font-mono text-[11px] font-semibold cursor-pointer">
                                                    <?= htmlspecialchars((string) $arr['reservation_uid'], ENT_QUOTES, 'UTF-8') ?> &rarr;
                                                </button>
                                            </div>
                                            <div class="font-medium text-gray-900">
                                                <?= htmlspecialchars((string) ($arr['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-slate-200/60">
                                                <span>Door PIN: <strong class="font-mono text-gray-800"><?= htmlspecialchars((string) ($arr['door_code'] ?? 'None'), ENT_QUOTES, 'UTF-8') ?></strong></span>
                                                <span>Stay: <?= htmlspecialchars((string) ($arr['check_in'] ?? '')) ?> &rarr; <?= htmlspecialchars((string) ($arr['check_out'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- Case C: Single Reservation (Active / Arriving / Departing) -->
                            <?php elseif ($prop->activeReservation !== null || $prop->arrivingReservation !== null || $prop->departingReservation !== null): ?>
                                <?php
                                $res = $prop->activeReservation ?? $prop->arrivingReservation ?? $prop->departingReservation;
                                $resUid = (string) ($res['reservation_uid'] ?? '');
                                $resName = (string) ($res['guest_name'] ?? 'Guest');
                                $resEmail = (string) ($res['guest_email'] ?? '');
                                $resPhone = (string) ($res['guest_phone'] ?? '');
                                $resCheckIn = (string) ($res['check_in'] ?? '');
                                $resCheckOut = (string) ($res['check_out'] ?? '');
                                $resDoorCode = (string) ($res['door_code'] ?? '');
                                $resRegistryCompleted = (int) ($res['registry_completed'] ?? 0) === 1;
                                $resClearanceStatus = (string) ($res['clearance_status'] ?? '');
                                $resSource = (string) ($res['source'] ?? 'web');
                                $resPayStatus = (string) ($res['payment_status'] ?? '');
                                ?>
                                <div class="bg-gray-50/80 p-3.5 rounded-lg border border-gray-100 text-xs space-y-2.5">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                                <span class="font-semibold text-gray-900 text-sm">
                                                    <?= htmlspecialchars($resName, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <?php if ($resPayStatus !== ''): ?>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium <?= match ($resPayStatus) {
                                                        'paid', 'approved' => 'bg-emerald-100 text-emerald-800',
                                                        'refunded', 'partially_refunded' => 'bg-amber-100 text-amber-800',
                                                        default => 'bg-slate-100 text-slate-700',
                                                    } ?>">
                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $resPayStatus)), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-gray-500 font-mono mt-0.5">
                                                UID: <?= htmlspecialchars($resUid, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </div>
                                        <button type="button"
                                                hx-get="/reservations/<?= urlencode($resUid) ?>"
                                                hx-target="#drawer-container"
                                                hx-swap="innerHTML"
                                                class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition cursor-pointer">
                                            View Stay &rarr;
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-600 pt-1">
                                        <div>
                                            <span class="text-gray-400 block">Check-in &rarr; Out</span>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($resCheckIn) ?> &rarr; <?= htmlspecialchars($resCheckOut) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-400 block">Booking Channel</span>
                                            <span class="font-medium text-gray-800 uppercase"><?= htmlspecialchars($resSource) ?></span>
                                        </div>
                                        <?php if ($resEmail !== ''): ?>
                                            <div>
                                                <span class="text-gray-400 block">Email</span>
                                                <span class="truncate block font-mono text-gray-700" title="<?= htmlspecialchars($resEmail) ?>"><?= htmlspecialchars($resEmail) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($resPhone !== ''): ?>
                                            <div>
                                                <span class="text-gray-400 block">Phone</span>
                                                <span class="text-gray-700"><?= htmlspecialchars($resPhone) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($resPayStatus !== ''): ?>
                                            <div>
                                                <span class="text-gray-400 block">Payment</span>
                                                <span class="font-medium <?= match ($resPayStatus) {
                                                    'paid', 'approved' => 'text-emerald-700',
                                                    'refunded', 'partially_refunded' => 'text-amber-700',
                                                    default => 'text-gray-700',
                                                } ?>">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $resPayStatus)), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Credentials & Clearances -->
                                    <div class="pt-2 border-t border-gray-200/60 flex items-center justify-between text-[11px]">
                                        <div class="flex items-center space-x-1">
                                            <span class="text-gray-500">PIN:</span>
                                            <?php if ($resDoorCode !== ''): ?>
                                                <span class="font-mono font-bold text-indigo-700"><?= htmlspecialchars($resDoorCode) ?></span>
                                            <?php else: ?>
                                                <span class="text-amber-600 font-medium">Not Assigned</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($resRegistryCompleted): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800">Registry Complete</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">Registry Pending</span>
                                            <?php endif; ?>

                                            <?php if ($resClearanceStatus === 'synced'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800" title="<?= htmlspecialchars((string) ($res['clearance_number'] ?? '')) ?>">Clearance OK<?= !empty($res['clearance_number']) ? ' #' . htmlspecialchars((string) $res['clearance_number']) : '' ?></span>
                                            <?php elseif ($resClearanceStatus === 'failed'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-100 text-rose-800">Clearance Failed</span>
                                            <?php elseif ($resClearanceStatus === 'pending'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">Clearance Pending</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                            <!-- Case D: Vacant -->
                            <?php else: ?>
                                <div class="p-4 bg-gray-50 rounded-lg border border-dashed border-gray-200 text-center space-y-2.5">
                                    <p class="text-xs text-gray-500">Property is vacant on this calendar date.</p>
                                    <div class="flex items-center justify-center gap-2 flex-wrap">
                                        <button type="button"
                                                hx-get="/reservations/new?check_in=<?= htmlspecialchars($summary->date, ENT_QUOTES, 'UTF-8') ?>&property_id=<?= htmlspecialchars($prop->propertyId, ENT_QUOTES, 'UTF-8') ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                onclick="closeReservationDrawer();"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition cursor-pointer">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Create Reservation
                                        </button>
                                        <button type="button"
                                                hx-get="/calendar/blocks/new?property_id=<?= htmlspecialchars($prop->propertyId, ENT_QUOTES, 'UTF-8') ?>&start_date=<?= htmlspecialchars($summary->date, ENT_QUOTES, 'UTF-8') ?>&end_date=<?= htmlspecialchars($summary->date, ENT_QUOTES, 'UTF-8') ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                onclick="closeReservationDrawer();"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-50 text-amber-800 hover:bg-amber-100 transition cursor-pointer">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            Maintenance Hold
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 4. Operational Movements (if any) -->
                <?php if (!empty($summary->movements)): ?>
                    <div class="space-y-3 pt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Scheduled Operational Events</h3>
                        <div class="space-y-2">
                            <?php foreach ($summary->movements as $event): ?>
                                <?php
                                $isCheckIn = $event->movementType === \OceanViewFlats\Domain\Reservation\Dashboard\MovementType::CHECK_IN || $event->movementType === \OceanViewFlats\Domain\Reservation\Dashboard\MovementType::TURNOVER;
                                $timeFormatted = $isCheckIn ? '3:00 PM' : '11:00 AM';
                                ?>
                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-2.5">
                                        <span class="w-2 h-2 rounded-full <?= $isCheckIn ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                                        <div>
                                            <span class="font-semibold text-gray-900"><?= htmlspecialchars($timeFormatted, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-gray-500 ml-1.5"><?= htmlspecialchars($event->guestName, ENT_QUOTES, 'UTF-8') ?> (Property <?= htmlspecialchars($event->propertyId, ENT_QUOTES, 'UTF-8') ?>)</span>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $isCheckIn ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                        <?= htmlspecialchars($event->movementType->value, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Drawer Footer / Quick Actions -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <button type="button"
                            hx-get="/reservations/new?check_in=<?= htmlspecialchars($summary->date, ENT_QUOTES, 'UTF-8') ?>&property_id=<?= $summary->propertyFilter === 'all' ? '1606' : htmlspecialchars($summary->propertyFilter, ENT_QUOTES, 'UTF-8') ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML"
                            onclick="closeReservationDrawer();"
                            class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-2xs transition cursor-pointer">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Reservation
                    </button>
                    <button type="button"
                            hx-get="/calendar/blocks/new?property_id=<?= $summary->propertyFilter === 'all' ? '1606' : htmlspecialchars($summary->propertyFilter, ENT_QUOTES, 'UTF-8') ?>&start_date=<?= htmlspecialchars($summary->date, ENT_QUOTES, 'UTF-8') ?>&end_date=<?= htmlspecialchars($summary->date, ENT_QUOTES, 'UTF-8') ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML"
                            onclick="closeReservationDrawer();"
                            class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-amber-900 bg-amber-100 hover:bg-amber-200 rounded-lg shadow-2xs transition cursor-pointer">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Maintenance Hold
                    </button>
                </div>
                <button type="button"
                        onclick="closeReservationDrawer();"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-2xs transition cursor-pointer">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
