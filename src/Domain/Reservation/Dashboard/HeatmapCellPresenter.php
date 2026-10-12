<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

/**
 * Presenter responsible for rendering Tailwind CSS presentation classes
 * for heatmap cells, decoupling view/styling concerns from the domain value object.
 */
final class HeatmapCellPresenter
{
    /**
     * Returns Tailwind background and text color classes based on occupancy capacity level.
     *
     * @param HeatmapDayCell|string $capacityLevel 'maintenance'|'vacant'|'partial'|'full' or HeatmapDayCell instance
     */
    public static function capacityCssClass(HeatmapDayCell|string $capacityLevel): string
    {
        $level = $capacityLevel instanceof HeatmapDayCell ? $capacityLevel->getCapacityLevel() : $capacityLevel;
        return match ($level) {
            'maintenance' => 'bg-amber-100 border border-amber-300 text-amber-900',
            'vacant' => 'bg-gray-100 text-gray-400',
            'partial' => 'bg-emerald-200 text-emerald-800',
            'full' => 'bg-emerald-600 text-white',
            default => 'bg-gray-100 text-gray-400',
        };
    }

    /**
     * Returns composite Tailwind CSS utility classes for an individual HeatmapDayCell,
     * including borders, rings, today highlights, and past date opacity.
     */
    public static function cellCssClasses(HeatmapDayCell $cell): string
    {
        $classes = self::capacityCssClass($cell->getCapacityLevel());
        if ($cell->hasMaintenance && $cell->occupiedUnits > 0) {
            $classes .= ' ring-2 ring-amber-400';
        }
        if ($cell->hasPendingHold && !$cell->hasMaintenance) {
            $classes .= ' border border-dashed border-slate-400';
        }
        if ($cell->isToday) {
            $classes .= ' ring-2 ring-indigo-600 ring-offset-1 z-10';
        } elseif ($cell->isPast) {
            $classes .= ' opacity-40';
        }
        return $classes;
    }
}
