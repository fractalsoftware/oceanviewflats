<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * PDO database-backed adapter for Operational Expenses.
 */
final class PdoOperationalExpenseRepository implements OperationalExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(int $id): ?OperationalExpense
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT oe.*, u.name AS created_by_name
                FROM operational_expenses oe
                LEFT JOIN admin_users u ON oe.created_by = u.id
                WHERE oe.id = :id
                LIMIT 1
            ');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return null;
            }
            throw $e;
        }
    }

    public function findByUid(string $expenseUid): ?OperationalExpense
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT oe.*, u.name AS created_by_name
                FROM operational_expenses oe
                LEFT JOIN admin_users u ON oe.created_by = u.id
                WHERE oe.expense_uid = :expense_uid
                LIMIT 1
            ');
            $stmt->execute(['expense_uid' => $expenseUid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return null;
            }
            throw $e;
        }
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
        $conditions = [
            'oe.expense_date >= :start_date',
            'oe.expense_date < :end_date',
        ];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $normalizedProperty = trim($propertyId);
        if (in_array($normalizedProperty, ['1606', '1707'], true)) {
            $conditions[] = "(oe.property_id = :property_id OR oe.property_id = 'shared')";
            $params['property_id'] = $normalizedProperty;
        } elseif ($normalizedProperty === 'shared') {
            $conditions[] = "oe.property_id = 'shared'";
        }

        $normalizedCategory = trim($category);
        if ($normalizedCategory !== '' && $normalizedCategory !== 'all') {
            $conditions[] = 'oe.category = :category';
            $params['category'] = $normalizedCategory;
        }

        $normalizedSearch = trim($search);
        if ($normalizedSearch !== '') {
            $conditions[] = '(oe.expense_uid LIKE :search OR oe.title LIKE :search OR oe.vendor_name LIKE :search OR oe.reference_code LIKE :search OR oe.reservation_uid LIKE :search OR oe.notes LIKE :search)';
            $params['search'] = '%' . $normalizedSearch . '%';
        }

        $whereSql = implode(' AND ', $conditions);
        $sql = "
            SELECT oe.*, u.name AS created_by_name
            FROM operational_expenses oe
            LEFT JOIN admin_users u ON oe.created_by = u.id
            WHERE {$whereSql}
            ORDER BY oe.expense_date DESC, oe.id DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $expenses = [];
            foreach ($rows as $row) {
                $expenses[] = $this->hydrateRow($row);
            }

            return $expenses;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    public function save(OperationalExpense $expense): OperationalExpense
    {
        if ($expense->id === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO operational_expenses (
                    expense_uid,
                    property_id,
                    category,
                    title,
                    amount,
                    expense_date,
                    vendor_name,
                    reference_code,
                    reservation_uid,
                    notes,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :expense_uid,
                    :property_id,
                    :category,
                    :title,
                    :amount,
                    :expense_date,
                    :vendor_name,
                    :reference_code,
                    :reservation_uid,
                    :notes,
                    :created_by,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ');
            $stmt->execute([
                'expense_uid' => $expense->expenseUid,
                'property_id' => $expense->propertyId,
                'category' => $expense->category->value,
                'title' => $expense->title,
                'amount' => $expense->amount,
                'expense_date' => $expense->expenseDate,
                'vendor_name' => $expense->vendorName,
                'reference_code' => $expense->referenceCode,
                'reservation_uid' => $expense->reservationUid,
                'notes' => $expense->notes,
                'created_by' => $expense->createdBy,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            return $this->findById($id) ?? new OperationalExpense(
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
                createdBy: $expense->createdBy,
                createdByName: $expense->createdByName,
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable()
            );
        }

        $stmt = $this->pdo->prepare('
            UPDATE operational_expenses
            SET property_id = :property_id,
                category = :category,
                title = :title,
                amount = :amount,
                expense_date = :expense_date,
                vendor_name = :vendor_name,
                reference_code = :reference_code,
                reservation_uid = :reservation_uid,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $expense->id,
            'property_id' => $expense->propertyId,
            'category' => $expense->category->value,
            'title' => $expense->title,
            'amount' => $expense->amount,
            'expense_date' => $expense->expenseDate,
            'vendor_name' => $expense->vendorName,
            'reference_code' => $expense->referenceCode,
            'reservation_uid' => $expense->reservationUid,
            'notes' => $expense->notes,
        ]);

        return $this->findById($expense->id) ?? $expense;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM operational_expenses WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): OperationalExpense
    {
        return OperationalExpense::fromArray($row);
    }
}
