<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Authoritative repository port for Operational Expenses.
 */
interface OperationalExpenseRepositoryInterface
{
    public function findById(int $id): ?OperationalExpense;

    public function findByUid(string $expenseUid): ?OperationalExpense;

    /**
     * Lists operational expenses occurring within the half-open date interval [$startDate, $endDate).
     *
     * When $propertyId is '1606' or '1707', returns both direct property expenses AND 'shared'
     * portfolio expenses so callers can apply 50/50 shared allocation.
     * When $propertyId is 'shared', returns only 'shared' expenses.
     * When $propertyId is 'all', returns expenses across '1606', '1707', and 'shared'.
     *
     * @return list<OperationalExpense>
     */
    public function listForPeriod(
        string $startDate,
        string $endDate,
        string $propertyId = 'all',
        string $category = 'all',
        string $search = ''
    ): array;

    public function save(OperationalExpense $expense): OperationalExpense;

    public function delete(int $id): bool;
}
