<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;

/**
 * In-memory test adapter for OperationalExpenseRepositoryInterface.
 */
final class InMemoryOperationalExpenseRepository implements OperationalExpenseRepositoryInterface
{
    /** @var array<int, OperationalExpense> */
    private array $expensesById = [];

    private int $autoIncrementId = 0;

    /**
     * @param list<OperationalExpense> $initialExpenses
     */
    public function __construct(array $initialExpenses = [])
    {
        foreach ($initialExpenses as $expense) {
            $this->save($expense);
        }
    }

    public function findById(int $id): ?OperationalExpense
    {
        return $this->expensesById[$id] ?? null;
    }

    public function findByUid(string $expenseUid): ?OperationalExpense
    {
        foreach ($this->expensesById as $expense) {
            if ($expense->expenseUid === $expenseUid) {
                return $expense;
            }
        }

        return null;
    }

    /**
     * @return list<OperationalExpense>
     */
    public function listForPeriod(
        string $startDate,
        string $endDate,
        string $propertyId = 'all',
        string $category = 'all',
        string $search = ''
    ): array {
        $normalizedProperty = trim($propertyId);
        $normalizedCategory = trim($category);
        $normalizedSearch = mb_strtolower(trim($search));

        $results = [];
        foreach ($this->expensesById as $expense) {
            if ($expense->expenseDate < $startDate || $expense->expenseDate >= $endDate) {
                continue;
            }

            if (in_array($normalizedProperty, ['1606', '1707'], true)) {
                if ($expense->propertyId !== $normalizedProperty && $expense->propertyId !== 'shared') {
                    continue;
                }
            } elseif ($normalizedProperty === 'shared') {
                if ($expense->propertyId !== 'shared') {
                    continue;
                }
            }

            if ($normalizedCategory !== '' && $normalizedCategory !== 'all' && $expense->category->value !== $normalizedCategory) {
                continue;
            }

            if ($normalizedSearch !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $expense->expenseUid,
                    $expense->title,
                    (string) $expense->vendorName,
                    (string) $expense->referenceCode,
                    (string) $expense->reservationUid,
                    (string) $expense->notes,
                ]));
                if (!str_contains($haystack, $normalizedSearch)) {
                    continue;
                }
            }

            $results[] = $expense;
        }

        usort($results, static function (OperationalExpense $a, OperationalExpense $b): int {
            $dateCompare = strcmp($b->expenseDate, $a->expenseDate);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return ($b->id ?? 0) <=> ($a->id ?? 0);
        });

        return $results;
    }

    public function save(OperationalExpense $expense): OperationalExpense
    {
        $id = $expense->id ?? ++$this->autoIncrementId;
        if ($id > $this->autoIncrementId) {
            $this->autoIncrementId = $id;
        }

        $existing = $this->expensesById[$id] ?? null;
        $now = new DateTimeImmutable();

        $createdBy = $expense->createdBy ?? ($existing !== null ? $existing->createdBy : null);
        $createdByName = $expense->createdByName ?? ($existing !== null ? $existing->createdByName : null);
        $createdAt = ($existing !== null && $existing->createdAt !== null)
            ? $existing->createdAt
            : ($expense->createdAt ?? $now);

        $persisted = new OperationalExpense(
            propertyId: $expense->propertyId,
            category: $expense->category,
            title: $expense->title,
            amount: $expense->amount,
            expenseDate: $expense->expenseDate,
            expenseUid: $expense->expenseUid,
            id: $id,
            vendorName: $expense->vendorName,
            referenceCode: $expense->referenceCode,
            reservationUid: $expense->reservationUid,
            notes: $expense->notes,
            createdBy: $createdBy,
            createdByName: $createdByName,
            createdAt: $createdAt,
            updatedAt: $now
        );

        $this->expensesById[$id] = $persisted;

        return $persisted;
    }

    public function delete(int $id): bool
    {
        if (isset($this->expensesById[$id])) {
            unset($this->expensesById[$id]);
            return true;
        }

        return false;
    }
}
