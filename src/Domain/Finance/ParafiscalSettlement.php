<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use ArrayAccess;
use BadMethodCallException;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable domain entity representing a persisted FONTUR Parafiscal Settlement record
 * for a specific Property ('1606' or '1707') and Quarterly Parafiscal Period ('YYYY-Q1'..'YYYY-Q4').
 *
 * @implements ArrayAccess<string, mixed>
 */
final class ParafiscalSettlement implements ArrayAccess
{
    public const VALID_PROPERTIES = ['1606', '1707'];
    public const STATUS_PENDING = 'pending';
    public const STATUS_SETTLED = 'settled';

    public const DEFAULT_RNT_MAP = [
        '1606' => '270413',
        '1707' => '268781',
    ];

    public readonly ?int $id;
    public readonly string $settlementUid;
    public readonly string $propertyId;
    public readonly string $rntNumber;
    public readonly string $periodKey;
    public readonly string $periodStart;
    public readonly string $periodEnd;
    public readonly string $dueDate;
    public readonly string $status;
    public readonly ?float $settledTaxableBase;
    public readonly ?float $settledCalculatedAmount;
    public readonly ?float $settledAmount;
    public readonly ?string $paymentDate;
    public readonly ?string $referenceCode;
    public readonly ?string $notes;
    public readonly ?int $operationalExpenseId;
    public readonly ?int $settledBy;
    public readonly ?string $settledByName;
    public readonly ?DateTimeImmutable $settledAt;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;

    public function __construct(
        string $propertyId,
        string $periodKey,
        string $periodStart,
        string $periodEnd,
        string $dueDate,
        ?string $rntNumber = null,
        string $status = self::STATUS_PENDING,
        ?float $settledTaxableBase = null,
        ?float $settledCalculatedAmount = null,
        ?float $settledAmount = null,
        ?string $paymentDate = null,
        ?string $referenceCode = null,
        ?string $notes = null,
        ?int $operationalExpenseId = null,
        ?int $settledBy = null,
        ?string $settledByName = null,
        DateTimeImmutable|string|null $settledAt = null,
        ?string $settlementUid = null,
        ?int $id = null,
        DateTimeImmutable|string|null $createdAt = null,
        DateTimeImmutable|string|null $updatedAt = null
    ) {
        $normalizedProperty = trim($propertyId);
        if (!in_array($normalizedProperty, self::VALID_PROPERTIES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid Parafiscal settlement property ID "%s". Must be 1606 or 1707.', $propertyId)
            );
        }

        $normalizedPeriodKey = strtoupper(trim($periodKey));
        if (!preg_match('/^\d{4}-Q[1-4]$/', $normalizedPeriodKey)) {
            throw new InvalidArgumentException(
                sprintf('Invalid Parafiscal period key "%s". Expected YYYY-Q1 through YYYY-Q4.', $periodKey)
            );
        }

        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, [self::STATUS_PENDING, self::STATUS_SETTLED], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid Parafiscal settlement status "%s". Must be pending or settled.', $status)
            );
        }

        if ($settledAmount !== null && ($settledAmount < 0.0 || !is_finite($settledAmount))) {
            throw new InvalidArgumentException('Settled amount must be greater than or equal to 0.00 COP.');
        }

        $resolvedRnt = $rntNumber !== null && trim($rntNumber) !== ''
            ? trim($rntNumber)
            : self::DEFAULT_RNT_MAP[$normalizedProperty];

        $this->id = $id;
        $this->settlementUid = $settlementUid !== null && trim($settlementUid) !== ''
            ? trim($settlementUid)
            : self::generateUid();
        $this->propertyId = $normalizedProperty;
        $this->rntNumber = $resolvedRnt;
        $this->periodKey = $normalizedPeriodKey;
        $this->periodStart = trim($periodStart);
        $this->periodEnd = trim($periodEnd);
        $this->dueDate = trim($dueDate);
        $this->status = $normalizedStatus;
        $this->settledTaxableBase = $settledTaxableBase !== null ? round($settledTaxableBase, 2) : null;
        $this->settledCalculatedAmount = $settledCalculatedAmount !== null ? round($settledCalculatedAmount, 2) : null;
        $this->settledAmount = $settledAmount !== null ? round($settledAmount, 2) : null;
        $this->paymentDate = $paymentDate !== null && trim($paymentDate) !== '' ? trim($paymentDate) : null;
        $this->referenceCode = $referenceCode !== null && trim($referenceCode) !== '' ? trim($referenceCode) : null;
        $this->notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        $this->operationalExpenseId = $operationalExpenseId;
        $this->settledBy = $settledBy;
        $this->settledByName = $settledByName !== null && trim($settledByName) !== '' ? trim($settledByName) : null;
        $this->settledAt = self::parseDateTime($settledAt);
        $this->createdAt = self::parseDateTime($createdAt);
        $this->updatedAt = self::parseDateTime($updatedAt);
    }

    public static function generateUid(): string
    {
        return 'pfs_' . bin2hex(random_bytes(8));
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function getSettledAtString(): ?string
    {
        return $this->settledAt?->format('Y-m-d H:i:s');
    }

    public function getCreatedAtString(): ?string
    {
        return $this->createdAt?->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtString(): ?string
    {
        return $this->updatedAt?->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'settlement_uid' => $this->settlementUid,
            'property_id' => $this->propertyId,
            'rnt_number' => $this->rntNumber,
            'period_key' => $this->periodKey,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'due_date' => $this->dueDate,
            'status' => $this->status,
            'settled_taxable_base' => $this->settledTaxableBase,
            'settled_calculated_amount' => $this->settledCalculatedAmount,
            'settled_amount' => $this->settledAmount,
            'payment_date' => $this->paymentDate,
            'reference_code' => $this->referenceCode,
            'notes' => $this->notes,
            'operational_expense_id' => $this->operationalExpenseId,
            'settled_by' => $this->settledBy,
            'settled_by_name' => $this->settledByName,
            'settled_at' => $this->getSettledAtString(),
            'created_at' => $this->getCreatedAtString(),
            'updated_at' => $this->getUpdatedAtString(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $periodKey = strtoupper(trim((string) ($data['period_key'] ?? $data['periodKey'] ?? '')));
        $parsedQuarter = null;
        if (preg_match('/^\d{4}-Q[1-4]$/', $periodKey)) {
            $parsedQuarter = ColombianStatutoryCalendar::parseQuarterKey($periodKey);
        }

        $periodStart = (string) ($data['period_start'] ?? $data['periodStart'] ?? ($parsedQuarter['period_start'] ?? ''));
        $periodEnd = (string) ($data['period_end'] ?? $data['periodEnd'] ?? ($parsedQuarter['period_end'] ?? ''));
        $dueDate = (string) ($data['due_date'] ?? $data['dueDate'] ?? ($parsedQuarter !== null ? ColombianStatutoryCalendar::calculateQuarterDueDate($periodKey) : ''));

        return new self(
            propertyId: (string) ($data['property_id'] ?? $data['propertyId'] ?? ''),
            periodKey: $periodKey,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            dueDate: $dueDate,
            rntNumber: isset($data['rnt_number']) || isset($data['rntNumber'])
                ? (string) ($data['rnt_number'] ?? $data['rntNumber'])
                : null,
            status: (string) ($data['status'] ?? self::STATUS_PENDING),
            settledTaxableBase: isset($data['settled_taxable_base']) && $data['settled_taxable_base'] !== ''
                ? (float) $data['settled_taxable_base']
                : (isset($data['settledTaxableBase']) && $data['settledTaxableBase'] !== '' ? (float) $data['settledTaxableBase'] : null),
            settledCalculatedAmount: isset($data['settled_calculated_amount']) && $data['settled_calculated_amount'] !== ''
                ? (float) $data['settled_calculated_amount']
                : (isset($data['settledCalculatedAmount']) && $data['settledCalculatedAmount'] !== '' ? (float) $data['settledCalculatedAmount'] : null),
            settledAmount: isset($data['settled_amount']) && $data['settled_amount'] !== ''
                ? (float) $data['settled_amount']
                : (isset($data['settledAmount']) && $data['settledAmount'] !== '' ? (float) $data['settledAmount'] : null),
            paymentDate: isset($data['payment_date']) || isset($data['paymentDate'])
                ? (string) ($data['payment_date'] ?? $data['paymentDate'])
                : null,
            referenceCode: isset($data['reference_code']) || isset($data['referenceCode'])
                ? (string) ($data['reference_code'] ?? $data['referenceCode'])
                : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            operationalExpenseId: isset($data['operational_expense_id']) && $data['operational_expense_id'] !== ''
                ? (int) $data['operational_expense_id']
                : (isset($data['operationalExpenseId']) && $data['operationalExpenseId'] !== '' ? (int) $data['operationalExpenseId'] : null),
            settledBy: isset($data['settled_by']) && $data['settled_by'] !== ''
                ? (int) $data['settled_by']
                : (isset($data['settledBy']) && $data['settledBy'] !== '' ? (int) $data['settledBy'] : null),
            settledByName: isset($data['settled_by_name']) || isset($data['settledByName'])
                ? (string) ($data['settled_by_name'] ?? $data['settledByName'])
                : null,
            settledAt: $data['settled_at'] ?? $data['settledAt'] ?? null,
            settlementUid: isset($data['settlement_uid']) || isset($data['settlementUid'])
                ? (string) ($data['settlement_uid'] ?? $data['settlementUid'])
                : null,
            id: isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null,
            createdAt: $data['created_at'] ?? $data['createdAt'] ?? null,
            updatedAt: $data['updated_at'] ?? $data['updatedAt'] ?? null
        );
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->offsetGet($offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'id' => $this->id,
            'settlement_uid', 'settlementUid' => $this->settlementUid,
            'property_id', 'propertyId' => $this->propertyId,
            'rnt_number', 'rntNumber' => $this->rntNumber,
            'period_key', 'periodKey' => $this->periodKey,
            'period_start', 'periodStart' => $this->periodStart,
            'period_end', 'periodEnd' => $this->periodEnd,
            'due_date', 'dueDate' => $this->dueDate,
            'status' => $this->status,
            'settled_taxable_base', 'settledTaxableBase' => $this->settledTaxableBase,
            'settled_calculated_amount', 'settledCalculatedAmount' => $this->settledCalculatedAmount,
            'settled_amount', 'settledAmount' => $this->settledAmount,
            'payment_date', 'paymentDate' => $this->paymentDate,
            'reference_code', 'referenceCode' => $this->referenceCode,
            'notes' => $this->notes,
            'operational_expense_id', 'operationalExpenseId' => $this->operationalExpenseId,
            'settled_by', 'settledBy' => $this->settledBy,
            'settled_by_name', 'settledByName' => $this->settledByName,
            'settled_at', 'settledAt' => $this->getSettledAtString(),
            'created_at', 'createdAt' => $this->getCreatedAtString(),
            'updated_at', 'updatedAt' => $this->getUpdatedAtString(),
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('ParafiscalSettlement entity is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('ParafiscalSettlement entity is immutable.');
    }

    private static function parseDateTime(DateTimeImmutable|string|null $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }
        return null;
    }
}
