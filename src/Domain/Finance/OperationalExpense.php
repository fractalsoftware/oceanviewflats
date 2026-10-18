<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use ArrayAccess;
use BadMethodCallException;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable domain entity representing a recorded Operational Expense in Settlement Currency (COP).
 *
 * Supports direct property attribution ('1606', '1707') and portfolio-wide shared expenses ('shared').
 *
 * @implements ArrayAccess<string, mixed>
 */
final class OperationalExpense implements ArrayAccess
{
    public const VALID_PROPERTIES = ['1606', '1707', 'shared'];
    public const MAX_TITLE_LENGTH = 160;

    public readonly ?int $id;
    public readonly string $expenseUid;
    public readonly string $propertyId;
    public readonly ExpenseCategory $category;
    public readonly string $title;
    public readonly float $amount;
    public readonly string $expenseDate;
    public readonly ?string $vendorName;
    public readonly ?string $referenceCode;
    public readonly ?string $reservationUid;
    public readonly ?string $notes;
    public readonly ?int $createdBy;
    public readonly ?string $createdByName;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;

    public function __construct(
        string $propertyId,
        ExpenseCategory|string $category,
        string $title,
        float $amount,
        string $expenseDate,
        ?string $expenseUid = null,
        ?int $id = null,
        ?string $vendorName = null,
        ?string $referenceCode = null,
        ?string $reservationUid = null,
        ?string $notes = null,
        ?int $createdBy = null,
        ?string $createdByName = null,
        DateTimeImmutable|string|null $createdAt = null,
        DateTimeImmutable|string|null $updatedAt = null
    ) {
        $normalizedProperty = trim($propertyId);
        if (!in_array($normalizedProperty, self::VALID_PROPERTIES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid property ID "%s". Must be 1606, 1707, or shared.', $propertyId)
            );
        }

        if (is_string($category)) {
            $resolvedCategory = ExpenseCategory::tryFrom(trim($category));
            if ($resolvedCategory === null) {
                throw new InvalidArgumentException(sprintf('Invalid expense category "%s".', $category));
            }
        } else {
            $resolvedCategory = $category;
        }

        $normalizedTitle = trim($title);
        if ($normalizedTitle === '') {
            throw new InvalidArgumentException('Expense title cannot be empty.');
        }
        if (mb_strlen($normalizedTitle) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Expense title cannot exceed %d characters.', self::MAX_TITLE_LENGTH)
            );
        }

        if ($amount <= 0.0 || !is_finite($amount)) {
            throw new InvalidArgumentException('Expense amount must be strictly greater than 0.00 COP.');
        }

        $normalizedDate = trim($expenseDate);
        if (!self::isValidDate($normalizedDate)) {
            throw new InvalidArgumentException(
                sprintf('Expense date "%s" must be a valid date in YYYY-MM-DD format.', $expenseDate)
            );
        }

        $normalizedResUid = $reservationUid !== null && trim($reservationUid) !== '' ? trim($reservationUid) : null;
        if ($normalizedResUid !== null && $normalizedProperty === 'shared') {
            throw new InvalidArgumentException(
                'Stay-linked expenses cannot be assigned to Shared Portfolio. Please select Property 1606 or 1707.'
            );
        }

        $normalizedUid = $expenseUid !== null && trim($expenseUid) !== ''
            ? trim($expenseUid)
            : self::generateUid();

        $this->id = $id;
        $this->expenseUid = $normalizedUid;
        $this->propertyId = $normalizedProperty;
        $this->category = $resolvedCategory;
        $this->title = $normalizedTitle;
        $this->amount = round($amount, 2);
        $this->expenseDate = $normalizedDate;
        $this->vendorName = $vendorName !== null && trim($vendorName) !== '' ? trim($vendorName) : null;
        $this->referenceCode = $referenceCode !== null && trim($referenceCode) !== '' ? trim($referenceCode) : null;
        $this->reservationUid = $normalizedResUid;
        $this->notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        $this->createdBy = $createdBy;
        $this->createdByName = $createdByName !== null && trim($createdByName) !== '' ? trim($createdByName) : null;
        $this->createdAt = self::parseDateTime($createdAt);
        $this->updatedAt = self::parseDateTime($updatedAt);
    }

    public static function generateUid(): string
    {
        return 'exp_' . bin2hex(random_bytes(8));
    }

    /**
     * Returns the portfolio allocation factor (1.0, 0.5, or 0.0) when evaluating a given property filter.
     */
    public function allocationFactorForProperty(string $propertyFilter): float
    {
        if ($propertyFilter === 'all' || $propertyFilter === '') {
            return 1.0;
        }
        if ($propertyFilter === 'shared') {
            return $this->propertyId === 'shared' ? 1.0 : 0.0;
        }
        if ($this->propertyId === $propertyFilter) {
            return 1.0;
        }
        if ($this->propertyId === 'shared' && in_array($propertyFilter, ['1606', '1707'], true)) {
            return 0.5;
        }

        return 0.0;
    }

    /**
     * Returns the allocated COP amount for the given property filter.
     */
    public function allocatedAmountForProperty(string $propertyFilter): float
    {
        return round($this->amount * $this->allocationFactorForProperty($propertyFilter), 2);
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
            'expense_uid' => $this->expenseUid,
            'property_id' => $this->propertyId,
            'category' => $this->category->value,
            'title' => $this->title,
            'amount' => $this->amount,
            'expense_date' => $this->expenseDate,
            'vendor_name' => $this->vendorName,
            'reference_code' => $this->referenceCode,
            'reservation_uid' => $this->reservationUid,
            'notes' => $this->notes,
            'created_by' => $this->createdBy,
            'created_by_name' => $this->createdByName,
            'created_at' => $this->getCreatedAtString(),
            'updated_at' => $this->getUpdatedAtString(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $categoryRaw = $data['category'] ?? 'other';
        $category = $categoryRaw instanceof ExpenseCategory ? $categoryRaw : (string) $categoryRaw;

        return new self(
            propertyId: (string) ($data['property_id'] ?? $data['propertyId'] ?? ''),
            category: $category,
            title: (string) ($data['title'] ?? ''),
            amount: (float) ($data['amount'] ?? 0.0),
            expenseDate: (string) ($data['expense_date'] ?? $data['expenseDate'] ?? ''),
            expenseUid: isset($data['expense_uid']) || isset($data['expenseUid'])
                ? (string) ($data['expense_uid'] ?? $data['expenseUid'])
                : null,
            id: isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null,
            vendorName: isset($data['vendor_name']) || isset($data['vendorName'])
                ? (string) ($data['vendor_name'] ?? $data['vendorName'])
                : null,
            referenceCode: isset($data['reference_code']) || isset($data['referenceCode'])
                ? (string) ($data['reference_code'] ?? $data['referenceCode'])
                : null,
            reservationUid: isset($data['reservation_uid']) || isset($data['reservationUid'])
                ? (string) ($data['reservation_uid'] ?? $data['reservationUid'])
                : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            createdBy: isset($data['created_by']) || isset($data['createdBy'])
                ? (int) ($data['created_by'] ?? $data['createdBy'])
                : null,
            createdByName: isset($data['created_by_name']) || isset($data['createdByName'])
                ? (string) ($data['created_by_name'] ?? $data['createdByName'])
                : null,
            createdAt: $data['created_at'] ?? $data['createdAt'] ?? null,
            updatedAt: $data['updated_at'] ?? $data['updatedAt'] ?? null
        );
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, [
            'id',
            'expense_uid',
            'expenseUid',
            'property_id',
            'propertyId',
            'category',
            'title',
            'amount',
            'expense_date',
            'expenseDate',
            'vendor_name',
            'vendorName',
            'reference_code',
            'referenceCode',
            'reservation_uid',
            'reservationUid',
            'notes',
            'created_by',
            'createdBy',
            'created_by_name',
            'createdByName',
            'created_at',
            'createdAt',
            'updated_at',
            'updatedAt',
        ], true) && $this->offsetGet($offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'id' => $this->id,
            'expense_uid', 'expenseUid' => $this->expenseUid,
            'property_id', 'propertyId' => $this->propertyId,
            'category' => $this->category->value,
            'title' => $this->title,
            'amount' => $this->amount,
            'expense_date', 'expenseDate' => $this->expenseDate,
            'vendor_name', 'vendorName' => $this->vendorName,
            'reference_code', 'referenceCode' => $this->referenceCode,
            'reservation_uid', 'reservationUid' => $this->reservationUid,
            'notes' => $this->notes,
            'created_by', 'createdBy' => $this->createdBy,
            'created_by_name', 'createdByName' => $this->createdByName,
            'created_at', 'createdAt' => $this->getCreatedAtString(),
            'updated_at', 'updatedAt' => $this->getUpdatedAtString(),
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('OperationalExpense entity is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('OperationalExpense entity is immutable.');
    }

    public static function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
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
