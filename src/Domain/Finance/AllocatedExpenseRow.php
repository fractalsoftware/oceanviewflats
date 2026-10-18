<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Immutable row wrapping an OperationalExpense with its portfolio allocation factor and allocated COP amount.
 */
final class AllocatedExpenseRow
{
    public function __construct(
        public readonly OperationalExpense $expense,
        public readonly float $allocationFactor,
        public readonly float $allocatedAmount
    ) {
    }

    public function isSharedAllocatedHalf(): bool
    {
        return $this->expense->propertyId === 'shared' && abs($this->allocationFactor - 0.5) < 0.001;
    }
}
