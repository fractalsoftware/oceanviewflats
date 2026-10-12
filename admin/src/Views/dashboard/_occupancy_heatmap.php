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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-3">
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
        <div class="flex items-center space-x-3 self-start sm:self-auto">
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
                <!-- Y-Axis Weekday Labels (Mon, Wed, Fri) -->
                <div class="flex flex-col gap-1 text-[10px] text-gray-400 font-medium select-none pr-2 shrink-0">
                    <!-- Top spacer aligned with month labels -->
                    <div class="h-4"></div>
                    <!-- Row 0: Mon -->
                    <div class="h-3.5 sm:h-4 flex items-center">Mon</div>
                    <!-- Row 1: Tue -->
                    <div class="h-3.5 sm:h-4"></div>
                    <!-- Row 2: Wed -->
                    <div class="h-3.5 sm:h-4 flex items-center">Wed</div>
                    <!-- Row 3: Thu -->
                    <div class="h-3.5 sm:h-4"></div>
                    <!-- Row 4: Fri -->
                    <div class="h-3.5 sm:h-4 flex items-center">Fri</div>
                    <!-- Row 5: Sat -->
                    <div class="h-3.5 sm:h-4"></div>
                    <!-- Row 6: Sun -->
                    <div class="h-3.5 sm:h-4"></div>
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
                                    $tooltip = $cell->date;
                                    if ($cell->isToday) {
                                        $tooltip .= ' (Today)';
                                    }
                                    if ($cell->occupiedUnits > 0) {
                                        $tooltip .= ": {$cell->occupiedUnits}/{$cell->totalUnits} occupied";
                                    } else {
                                        $tooltip .= ': Vacant';
                                    }
                                    ?>
                                    <div class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-xs transition-transform duration-75 hover:scale-125 cursor-pointer <?= $cell->getCellCssClasses() ?>"
                                         title="<?= htmlspecialchars($tooltip) ?>"
                                         data-date="<?= htmlspecialchars($cell->date) ?>">
                                    </div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Capacity Legend -->
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
        </div>

        <div class="flex items-center gap-4 text-gray-500">
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-xs bg-gray-100 ring-2 ring-indigo-600 ring-offset-1 inline-block"></span>
                <span>Today</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-xs bg-gray-100 opacity-40 inline-block"></span>
                <span>Past Dates</span>
            </div>
        </div>
    </div>
</div>
