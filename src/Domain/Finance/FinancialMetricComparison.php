<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Value object representing a period-over-period or year-over-year metric comparison.
 */
final class FinancialMetricComparison
{
    public readonly float $deltaAmount;
    public readonly ?float $deltaPct;

    public function __construct(
        public readonly float $current,
        public readonly float $previous,
        public readonly string $comparisonLabel = 'vs prior period',
        public readonly bool $invertColors = false
    ) {
        $this->deltaAmount = round($current - $previous, 2);

        if (abs($previous) > 0.0001) {
            $this->deltaPct = round((($current - $previous) / abs($previous)) * 100.0, 1);
        } elseif (abs($current) <= 0.0001) {
            $this->deltaPct = 0.0;
        } else {
            $this->deltaPct = null;
        }
    }

    public function formatPercentageText(): string
    {
        if ($this->deltaPct === null) {
            return sprintf('New %s', $this->comparisonLabel);
        }
        $sign = $this->deltaPct > 0.0 ? '+' : '';
        return sprintf('%s%.1f%% %s', $sign, $this->deltaPct, $this->comparisonLabel);
    }

    public function formatCopDeltaText(): string
    {
        $rounded = (int) round($this->deltaAmount);
        if ($rounded === 0) {
            return '$ 0 COP';
        }
        $sign = $rounded > 0 ? '+' : '-';
        return sprintf('%s$ %s COP', $sign, number_format(abs($rounded), 0, ',', '.'));
    }

    public function formatPointsDeltaText(): string
    {
        $sign = $this->deltaAmount > 0.0 ? '+' : '';
        return sprintf('%s%.1f pts %s', $sign, $this->deltaAmount, $this->comparisonLabel);
    }

    public function badgeClass(): string
    {
        if (abs($this->deltaAmount) <= 0.0001) {
            return 'bg-gray-100 text-gray-600';
        }

        $isPositive = $this->deltaAmount > 0.0;
        $isFavorable = $this->invertColors ? !$isPositive : $isPositive;

        return $isFavorable
            ? 'bg-emerald-50 text-emerald-700'
            : 'bg-rose-50 text-rose-700';
    }
}
