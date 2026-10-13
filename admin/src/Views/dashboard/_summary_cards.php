<?php
/**
 * HTMX Partial: Dashboard Operational Summary Cards
 *
 * @var \OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData $viewData
 * @var string $propertyId
 */
$alertCount = count($viewData->alerts);
?>
<div class="space-y-5">
    <!-- Operational Metrics Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Today's Arrivals -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Today's Arrivals</span>
                <div class="text-2xl font-black text-gray-900 mt-1"><?= (int) $viewData->todayArrivalsCount ?></div>
                <div class="text-[11px] text-gray-400 mt-0.5">Scheduled check-ins</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </div>
        </div>

        <!-- Today's Departures -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Today's Departures</span>
                <div class="text-2xl font-black text-gray-900 mt-1"><?= (int) $viewData->todayDeparturesCount ?></div>
                <div class="text-[11px] text-gray-400 mt-0.5">Scheduled check-outs</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                </svg>
            </div>
        </div>

        <!-- In-House Stays -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">In-House Stays</span>
                <div class="text-2xl font-black text-gray-900 mt-1"><?= (int) $viewData->activeStaysCount ?></div>
                <div class="text-[11px] text-gray-400 mt-0.5">Active occupied flats</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
        </div>

        <!-- Operational Alerts Counter -->
        <div class="bg-white p-4 rounded-xl border <?= $alertCount > 0 ? 'border-rose-200 bg-rose-50/30' : 'border-gray-200' ?> shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-xs font-medium <?= $alertCount > 0 ? 'text-rose-700' : 'text-gray-500' ?> uppercase tracking-wider">Operational Alerts</span>
                <div class="text-2xl font-black <?= $alertCount > 0 ? 'text-rose-600' : 'text-gray-900' ?> mt-1"><?= $alertCount ?></div>
                <div class="text-[11px] <?= $alertCount > 0 ? 'text-rose-500 font-medium' : 'text-gray-400' ?> mt-0.5">
                    <?= $alertCount > 0 ? 'Action required' : 'All systems clear' ?>
                </div>
            </div>
            <div class="w-10 h-10 rounded-lg <?= $alertCount > 0 ? 'bg-rose-100 text-rose-600' : 'bg-gray-100 text-gray-500' ?> flex items-center justify-center font-bold text-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>
