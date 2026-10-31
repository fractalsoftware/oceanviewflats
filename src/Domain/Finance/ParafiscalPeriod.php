<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use ArrayAccess;
use BadMethodCallException;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;

/**
 * Authoritative read model for a single evaluated quarterly Parafiscal Period and Property.
 *
 * Combines the live-calculated Parafiscal Taxable Base and 0.25% Parafiscal Contribution
 * with any persisted ParafiscalSettlement snapshot, lifecycle status, due-date countdown,
 * and recalculation variance metrics.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class ParafiscalPeriod implements ArrayAccess
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING = 'pending';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_SETTLED = 'settled';

    public function __construct(
        public readonly string $periodKey,
        public readonly string $periodLabel,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $dueDate,
        public readonly string $propertyId,
        public readonly string $rntNumber,
        public readonly float $activeStayRevenue,
        public readonly float $deductedDirectFees,
        public readonly float $excludedPolicyRetention,
        public readonly float $taxableBase,
        public readonly float $calculatedContribution,
        public readonly string $status,
        public readonly int $daysUntilDue,
        public readonly ?ParafiscalSettlement $settlement = null,
        public readonly ?OperationalExpense $linkedExpense = null,
        public readonly bool $hasRecalculationVariance = false,
        public readonly float $recalculationVarianceDelta = 0.0
    ) {
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function isClosedUnsettled(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_OVERDUE;
    }

    public function isZeroDeclaration(): bool
    {
        if (!$this->isSettled() || $this->settlement === null) {
            return false;
        }

        return ($this->settlement->settledAmount ?? 0.0) <= 0.0;
    }

    public function statusBadgeLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_SETTLED => 'Settled',
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
            self::STATUS_PENDING => $this->daysUntilDue <= 7
                ? 'bg-rose-100 text-rose-800 border border-rose-200'
                : 'bg-amber-100 text-amber-800 border border-amber-200',
            self::STATUS_OVERDUE => 'bg-rose-600 text-white border border-rose-700',
            self::STATUS_SETTLED => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            default => 'bg-gray-100 text-gray-800 border border-gray-200',
        };
    }

    public function alertId(): string
    {
        return AlertType::parafiscalAlertId($this->periodKey, $this->propertyId);
    }

    public function alertSeverity(): ?AlertSeverity
    {
        if (!$this->isClosedUnsettled()) {
            return null;
        }

        if ($this->isOverdue() || $this->daysUntilDue <= 7) {
            return AlertSeverity::CRITICAL;
        }

        return AlertSeverity::WARNING;
    }

    public function toOperationalAlert(): ?OperationalAlert
    {
        $severity = $this->alertSeverity();
        if ($severity === null) {
            return null;
        }

        $formattedContribution = '$ ' . number_format(round($this->calculatedContribution), 0, ',', '.') . ' COP';
        $formattedBase = '$ ' . number_format(round($this->taxableBase), 0, ',', '.') . ' COP';

        if ($this->isOverdue()) {
            $daysOverdue = abs($this->daysUntilDue);
            $dayWord = $daysOverdue === 1 ? 'day' : 'days';
            $title = sprintf(
                'FONTUR Parafiscal Overdue: %s (Property %s · RNT #%s)',
                $this->periodKey,
                $this->propertyId,
                $this->rntNumber
            );
            $description = sprintf(
                'Quarterly FONTUR declaration for %s (Property %s, RNT #%s) is %d %s past its statutory due date (%s). Assessed contribution: %s on %s taxable base.',
                $this->periodKey,
                $this->propertyId,
                $this->rntNumber,
                $daysOverdue,
                $dayWord,
                $this->dueDate,
                $formattedContribution,
                $formattedBase
            );
        } elseif ($this->daysUntilDue <= 7) {
            $dayText = $this->daysUntilDue === 0
                ? 'due today'
                : sprintf('due in %d %s', $this->daysUntilDue, $this->daysUntilDue === 1 ? 'day' : 'days');
            $title = sprintf(
                'FONTUR Parafiscal Due Soon: %s (Property %s · RNT #%s)',
                $this->periodKey,
                $this->propertyId,
                $this->rntNumber
            );
            $description = sprintf(
                'Quarterly FONTUR declaration for %s (Property %s, RNT #%s) is %s (%s). Assessed contribution: %s on %s taxable base.',
                $this->periodKey,
                $this->propertyId,
                $this->rntNumber,
                $dayText,
                $this->dueDate,
                $formattedContribution,
                $formattedBase
            );
        } else {
            $title = sprintf(
                'Pending FONTUR Parafiscal Declaration: %s (Property %s · RNT #%s)',
                $this->periodKey,
                $this->propertyId,
                $this->rntNumber
            );
            $description = sprintf(
                'Quarter %s has closed for Property %s (RNT #%s). Statutory payment deadline is %s (%d days remaining). Assessed contribution: %s on %s taxable base.',
                $this->periodKey,
                $this->propertyId,
                $this->rntNumber,
                $this->dueDate,
                $this->daysUntilDue,
                $formattedContribution,
                $formattedBase
            );
        }

        return new OperationalAlert(
            id: $this->alertId(),
            type: AlertType::PENDING_PARAFISCAL_SETTLEMENT,
            severity: $severity,
            propertyId: $this->propertyId,
            title: $title,
            description: $description,
            dueDate: $this->dueDate,
            actionPayload: [
                'period_key' => $this->periodKey,
                'property_id' => $this->propertyId,
                'rnt_number' => $this->rntNumber,
                'taxable_base' => $this->taxableBase,
                'calculated_amount' => $this->calculatedContribution,
                'due_date' => $this->dueDate,
                'days_until_due' => $this->daysUntilDue,
                'target_url' => '/financial-report#fontur-parafiscal-section',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period_key' => $this->periodKey,
            'period_label' => $this->periodLabel,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'due_date' => $this->dueDate,
            'property_id' => $this->propertyId,
            'rnt_number' => $this->rntNumber,
            'active_stay_revenue' => $this->activeStayRevenue,
            'deducted_direct_fees' => $this->deductedDirectFees,
            'excluded_policy_retention' => $this->excludedPolicyRetention,
            'taxable_base' => $this->taxableBase,
            'calculated_contribution' => $this->calculatedContribution,
            'status' => $this->status,
            'days_until_due' => $this->daysUntilDue,
            'settlement' => $this->settlement?->toArray(),
            'linked_expense' => $this->linkedExpense?->toArray(),
            'has_recalculation_variance' => $this->hasRecalculationVariance,
            'recalculation_variance_delta' => $this->recalculationVarianceDelta,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->offsetGet($offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'period_key', 'periodKey' => $this->periodKey,
            'period_label', 'periodLabel' => $this->periodLabel,
            'period_start', 'periodStart' => $this->periodStart,
            'period_end', 'periodEnd' => $this->periodEnd,
            'due_date', 'dueDate' => $this->dueDate,
            'property_id', 'propertyId' => $this->propertyId,
            'rnt_number', 'rntNumber' => $this->rntNumber,
            'active_stay_revenue', 'activeStayRevenue' => $this->activeStayRevenue,
            'deducted_direct_fees', 'deductedDirectFees' => $this->deductedDirectFees,
            'excluded_policy_retention', 'excludedPolicyRetention' => $this->excludedPolicyRetention,
            'taxable_base', 'taxableBase' => $this->taxableBase,
            'calculated_contribution', 'calculatedContribution' => $this->calculatedContribution,
            'status' => $this->status,
            'days_until_due', 'daysUntilDue' => $this->daysUntilDue,
            'settlement' => $this->settlement,
            'linked_expense', 'linkedExpense' => $this->linkedExpense,
            'has_recalculation_variance', 'hasRecalculationVariance' => $this->hasRecalculationVariance,
            'recalculation_variance_delta', 'recalculationVarianceDelta' => $this->recalculationVarianceDelta,
            'settled_amount', 'settledAmount' => $this->settlement?->settledAmount,
            'payment_date', 'paymentDate' => $this->settlement?->paymentDate,
            'reference_code', 'referenceCode' => $this->settlement?->referenceCode,
            'operational_expense_id', 'operationalExpenseId' => $this->settlement?->operationalExpenseId,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('ParafiscalPeriod value object is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('ParafiscalPeriod value object is immutable.');
    }
}
