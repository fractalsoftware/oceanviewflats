<?php
/**
 * HTMX Partial: 7-Month Continuous Occupancy Heatmap Matrix
 *
 * @var \OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData $viewData
 * @var string $propertyId
 */

$heatmap = $viewData->heatmap;
if ($heatmap === null) {
    return;
}

$startDt = DateTimeImmutable::createFromFormat('Y-m-d', $heatmap->startDate) ?: new DateTimeImmutable($heatmap->startDate);
$endDt = DateTimeImmutable::createFromFormat('Y-m-d', $heatmap->endDate) ?: new DateTimeImmutable($heatmap->endDate);
$formattedHorizon = $startDt->format('M j, Y') . ' – ' . $endDt->format('M j, Y');
$isAllProperties = ($heatmap->propertyFilter === 'all');
?>
<div class="bg-white p-5 rounded-xl border border-gray-200 shadow-2xs space-y-4">
    <!-- Header: Title, Horizon, and Overall Occupancy -->
    <div class="flex flex-wrap sm:flex-nowrap items-start sm:items-center justify-between gap-3 border-b border-gray-100 pb-3">
        <div>
            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <h3 class="text-sm font-bold text-gray-900 tracking-tight">Occupancy Heatmap</h3>
                <span class="text-xs text-gray-400 font-medium">(7-Month Horizon)</span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                <?= htmlspecialchars($formattedHorizon) ?> &bull; <?= (int) $heatmap->totalDaysCount ?> calendar days
            </p>
        </div>
        <div class="flex items-center space-x-3 self-start sm:self-auto shrink-0">
            <div class="text-right">
                <div class="text-xs font-bold text-gray-900">
                    <?= number_format($heatmap->overallOccupancyPercentage, 1) ?>% Occupied
                </div>
                <div class="text-[11px] text-gray-400">
                    <?= (int) $heatmap->totalOccupiedDaysCount ?> of <?= (int) $heatmap->totalDaysCount ?> days active
                </div>
            </div>
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Scrollable Matrix Area -->
    <div class="overflow-x-auto pb-2 -mx-2 px-2">
        <div class="min-w-max inline-block align-top py-1">
            <div class="flex items-start gap-1">
                <!-- Y-Axis Weekday Labels (Mon - Sun) -->
                <div class="sticky left-0 bg-white border-r border-gray-100 pr-1.5 z-10 flex flex-col gap-1 text-[10px] text-gray-600 font-medium select-none shrink-0">
                    <!-- Top spacer aligned with month labels -->
                    <div class="h-4"></div>
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel): ?>
                        <div class="h-3.5 sm:h-4 flex items-center"><?= $dayLabel ?></div>
                    <?php endforeach; ?>
                </div>

                <!-- 7-Month Columns Container -->
                <div class="flex gap-1 items-start">
                    <?php foreach ($heatmap->columns as $column): ?>
                        <div class="flex flex-col gap-1 items-center shrink-0">
                            <!-- Column Month Label Header -->
                            <div class="h-4 relative w-3.5 sm:w-4 text-[10px] font-semibold text-gray-600 select-none">
                                <?php if ($column->monthLabel !== null): ?>
                                    <span class="absolute left-0 top-0 whitespace-nowrap"><?= htmlspecialchars($column->monthLabel) ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- 7 Weekday Rows (Row 0 = Mon ... Row 6 = Sun) -->
                            <?php for ($rowIndex = 0; $rowIndex < 7; $rowIndex++): ?>
                                <?php $cell = $column->days[$rowIndex] ?? null; ?>
                                <?php if ($cell === null): ?>
                                    <div class="w-3.5 h-3.5 sm:w-4 sm:h-4"></div>
                                <?php else: ?>
                                    <?php
                                    $cellDt = DateTimeImmutable::createFromFormat('Y-m-d', $cell->date) ?: new DateTimeImmutable($cell->date);
                                    $cellDateFormatted = $cellDt->format('l, M j, Y');
                                    $popoverData = [
                                        'date' => $cellDateFormatted,
                                        'rawDate' => $cell->date,
                                        'isToday' => $cell->isToday,
                                        'rate' => $cell->effectiveRate,
                                        'propertyFilter' => $cell->propertyFilter,
                                        'hasTurnover' => $cell->hasTurnover,
                                        'hasMaintenance' => $cell->hasMaintenance,
                                        'hasPendingHold' => $cell->hasPendingHold,
                                        'hasWarning' => $cell->hasWarning,
                                        'warnings' => $cell->warningMessages,
                                        'flats' => array_values($cell->flatDetails),
                                        'properties' => array_values($cell->propertyDetails),
                                    ];
                                    $popoverJson = json_encode($popoverData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                    ?>
                                    <?php
                                    $hxGet = '';
                                    $hxTarget = '';
                                    if ($cell->isClickActionVacant()) {
                                        $targetProp = ($cell->propertyFilter === 'all') ? '1606' : $cell->propertyFilter;
                                        $hxGet = '/reservations/new?check_in=' . htmlspecialchars($cell->date, ENT_QUOTES, 'UTF-8') . '&property_id=' . htmlspecialchars($targetProp, ENT_QUOTES, 'UTF-8');
                                        $hxTarget = '#modal-container';
                                    } elseif ($cell->isClickActionSingleReservation()) {
                                        $hxGet = '/reservations/' . htmlspecialchars((string) $cell->singleReservationUid, ENT_QUOTES, 'UTF-8');
                                        $hxTarget = '#drawer-container';
                                    } else {
                                        $hxGet = '/dashboard/day?date=' . htmlspecialchars($cell->date, ENT_QUOTES, 'UTF-8') . '&property_id=' . htmlspecialchars($cell->propertyFilter, ENT_QUOTES, 'UTF-8');
                                        $hxTarget = '#drawer-container';
                                    }
                                    ?>
                                    <button type="button"
                                            class="relative w-3.5 h-3.5 sm:w-4 sm:h-4 p-0 border-0 rounded-xs transition-transform duration-75 hover:scale-125 hover:z-20 cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-indigo-500 <?= $cell->getCellCssClasses() ?>"
                                            data-heatmap-cell="true"
                                            data-date="<?= htmlspecialchars($cell->date) ?>"
                                            data-popover="<?= htmlspecialchars((string) $popoverJson, ENT_QUOTES, 'UTF-8') ?>"
                                            aria-label="<?= htmlspecialchars($cellDateFormatted, ENT_QUOTES, 'UTF-8') ?>"
                                            hx-get="<?= $hxGet ?>"
                                            hx-target="<?= $hxTarget ?>"
                                            hx-swap="innerHTML">
                                        <?php if ($cell->hasTurnover): ?>
                                            <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-blue-500 ring-1 ring-white pointer-events-none" title="Turnover"></span>
                                        <?php endif; ?>
                                        <?php if ($cell->hasWarning): ?>
                                            <span class="absolute -bottom-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-rose-500 ring-1 ring-white pointer-events-none" title="Operational Warning"></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Capacity & Operational Legend -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100 text-xs text-gray-600">
        <div class="flex flex-wrap items-center gap-4">
            <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Capacity:</span>
            <?php if ($isAllProperties): ?>
                <div class="flex items-center space-x-1.5">
                    <span class="w-3 h-3 rounded-xs bg-gray-100 border border-gray-200 inline-block"></span>
                    <span>Vacant (0/2)</span>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="w-3 h-3 rounded-xs bg-emerald-200 inline-block"></span>
                    <span>Partial (1/2)</span>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="w-3 h-3 rounded-xs bg-emerald-600 inline-block"></span>
                    <span>Full (2/2)</span>
                </div>
            <?php else: ?>
                <div class="flex items-center space-x-1.5">
                    <span class="w-3 h-3 rounded-xs bg-gray-100 border border-gray-200 inline-block"></span>
                    <span>Vacant</span>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="w-3 h-3 rounded-xs bg-emerald-600 inline-block"></span>
                    <span>Occupied</span>
                </div>
            <?php endif; ?>
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-xs bg-amber-100 border border-amber-300 inline-block"></span>
                <span>Maintenance</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-xs bg-gray-100 border border-dashed border-slate-400 inline-block"></span>
                <span>Pending Hold</span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-gray-500">
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-xs bg-gray-100 ring-2 ring-indigo-600 ring-offset-1 inline-block"></span>
                <span>Today</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-xs bg-gray-100 opacity-40 inline-block"></span>
                <span>Past Dates</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500 ring-1 ring-white inline-block"></span>
                <span>Turnover</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-500 ring-1 ring-white inline-block"></span>
                <span>Operational Warning</span>
            </div>
        </div>
    </div>

    <!-- Viewport-Fixed Non-Clipping Hover Popover Container -->
    <div id="heatmap-popover"
         class="fixed pointer-events-none hidden z-50 bg-slate-900 text-white rounded-lg shadow-xl p-3 text-xs w-72 max-w-sm border border-slate-700/60 leading-relaxed transition-opacity duration-150"
         role="tooltip"
         aria-hidden="true">
    </div>
</div>
