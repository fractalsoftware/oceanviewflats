<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * PDO database-backed adapter for FONTUR Parafiscal Settlements.
 */
final class PdoParafiscalSettlementRepository implements ParafiscalSettlementRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(int $id): ?ParafiscalSettlement
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT pfs.*, u.name AS settled_by_name
                FROM parafiscal_settlements pfs
                LEFT JOIN admin_users u ON pfs.settled_by = u.id
                WHERE pfs.id = :id
                LIMIT 1
            ');
            if ($stmt === false) {
                return null;
            }
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                return null;
            }
            throw $e;
        }
    }

    public function findByUid(string $settlementUid): ?ParafiscalSettlement
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT pfs.*, u.name AS settled_by_name
                FROM parafiscal_settlements pfs
                LEFT JOIN admin_users u ON pfs.settled_by = u.id
                WHERE pfs.settlement_uid = :settlement_uid
                LIMIT 1
            ');
            if ($stmt === false) {
                return null;
            }
            $stmt->execute(['settlement_uid' => $settlementUid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                return null;
            }
            throw $e;
        }
    }

    public function findByPropertyAndPeriod(string $propertyId, string $periodKey): ?ParafiscalSettlement
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT pfs.*, u.name AS settled_by_name
                FROM parafiscal_settlements pfs
                LEFT JOIN admin_users u ON pfs.settled_by = u.id
                WHERE pfs.property_id = :property_id
                  AND pfs.period_key = :period_key
                LIMIT 1
            ');
            if ($stmt === false) {
                return null;
            }
            $stmt->execute([
                'property_id' => trim($propertyId),
                'period_key' => strtoupper(trim($periodKey)),
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                return null;
            }
            throw $e;
        }
    }

    public function findByOperationalExpenseId(int $operationalExpenseId): ?ParafiscalSettlement
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT pfs.*, u.name AS settled_by_name
                FROM parafiscal_settlements pfs
                LEFT JOIN admin_users u ON pfs.settled_by = u.id
                WHERE pfs.operational_expense_id = :operational_expense_id
                LIMIT 1
            ');
            if ($stmt === false) {
                return null;
            }
            $stmt->execute(['operational_expense_id' => $operationalExpenseId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @return list<ParafiscalSettlement>
     */
    public function listAll(string $propertyId = 'all'): array
    {
        $normalizedProperty = trim($propertyId);
        $params = [];
        $whereSql = '';

        if (in_array($normalizedProperty, ['1606', '1707'], true)) {
            $whereSql = 'WHERE pfs.property_id = :property_id';
            $params['property_id'] = $normalizedProperty;
        }

        $sql = "
            SELECT pfs.*, u.name AS settled_by_name
            FROM parafiscal_settlements pfs
            LEFT JOIN admin_users u ON pfs.settled_by = u.id
            {$whereSql}
            ORDER BY pfs.period_key DESC, pfs.property_id ASC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                return [];
            }
            $stmt->execute($params);

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $settlements = [];
            foreach ($rows as $row) {
                $settlements[] = $this->hydrateRow($row);
            }

            return $settlements;
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                return [];
            }
            throw $e;
        }
    }

    public function save(ParafiscalSettlement $settlement): ParafiscalSettlement
    {
        $existing = $settlement->id !== null
            ? $this->findById($settlement->id)
            : $this->findByPropertyAndPeriod($settlement->propertyId, $settlement->periodKey);

        if ($existing === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO parafiscal_settlements (
                    settlement_uid,
                    property_id,
                    rnt_number,
                    period_key,
                    period_start,
                    period_end,
                    due_date,
                    status,
                    settled_taxable_base,
                    settled_calculated_amount,
                    settled_amount,
                    payment_date,
                    reference_code,
                    notes,
                    operational_expense_id,
                    settled_by,
                    settled_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :settlement_uid,
                    :property_id,
                    :rnt_number,
                    :period_key,
                    :period_start,
                    :period_end,
                    :due_date,
                    :status,
                    :settled_taxable_base,
                    :settled_calculated_amount,
                    :settled_amount,
                    :payment_date,
                    :reference_code,
                    :notes,
                    :operational_expense_id,
                    :settled_by,
                    :settled_at,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ');
            if ($stmt !== false) {
                $stmt->execute([
                    'settlement_uid' => $settlement->settlementUid,
                    'property_id' => $settlement->propertyId,
                    'rnt_number' => $settlement->rntNumber,
                    'period_key' => $settlement->periodKey,
                    'period_start' => $settlement->periodStart,
                    'period_end' => $settlement->periodEnd,
                    'due_date' => $settlement->dueDate,
                    'status' => $settlement->status,
                    'settled_taxable_base' => $settlement->settledTaxableBase,
                    'settled_calculated_amount' => $settlement->settledCalculatedAmount,
                    'settled_amount' => $settlement->settledAmount,
                    'payment_date' => $settlement->paymentDate,
                    'reference_code' => $settlement->referenceCode,
                    'notes' => $settlement->notes,
                    'operational_expense_id' => $settlement->operationalExpenseId,
                    'settled_by' => $settlement->settledBy,
                    'settled_at' => $settlement->getSettledAtString(),
                ]);
            }

            $id = (int) $this->pdo->lastInsertId();
            return $this->findById($id) ?? new ParafiscalSettlement(
                propertyId: $settlement->propertyId,
                periodKey: $settlement->periodKey,
                periodStart: $settlement->periodStart,
                periodEnd: $settlement->periodEnd,
                dueDate: $settlement->dueDate,
                rntNumber: $settlement->rntNumber,
                status: $settlement->status,
                settledTaxableBase: $settlement->settledTaxableBase,
                settledCalculatedAmount: $settlement->settledCalculatedAmount,
                settledAmount: $settlement->settledAmount,
                paymentDate: $settlement->paymentDate,
                referenceCode: $settlement->referenceCode,
                notes: $settlement->notes,
                operationalExpenseId: $settlement->operationalExpenseId,
                settledBy: $settlement->settledBy,
                settledByName: $settlement->settledByName,
                settledAt: $settlement->settledAt,
                settlementUid: $settlement->settlementUid,
                id: $id,
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable()
            );
        }

        $targetId = (int) $existing->id;
        $stmt = $this->pdo->prepare('
            UPDATE parafiscal_settlements
            SET property_id = :property_id,
                rnt_number = :rnt_number,
                period_key = :period_key,
                period_start = :period_start,
                period_end = :period_end,
                due_date = :due_date,
                status = :status,
                settled_taxable_base = :settled_taxable_base,
                settled_calculated_amount = :settled_calculated_amount,
                settled_amount = :settled_amount,
                payment_date = :payment_date,
                reference_code = :reference_code,
                notes = :notes,
                operational_expense_id = :operational_expense_id,
                settled_by = :settled_by,
                settled_at = :settled_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        if ($stmt !== false) {
            $stmt->execute([
                'id' => $targetId,
                'property_id' => $settlement->propertyId,
                'rnt_number' => $settlement->rntNumber,
                'period_key' => $settlement->periodKey,
                'period_start' => $settlement->periodStart,
                'period_end' => $settlement->periodEnd,
                'due_date' => $settlement->dueDate,
                'status' => $settlement->status,
                'settled_taxable_base' => $settlement->settledTaxableBase,
                'settled_calculated_amount' => $settlement->settledCalculatedAmount,
                'settled_amount' => $settlement->settledAmount,
                'payment_date' => $settlement->paymentDate,
                'reference_code' => $settlement->referenceCode,
                'notes' => $settlement->notes,
                'operational_expense_id' => $settlement->operationalExpenseId,
                'settled_by' => $settlement->settledBy,
                'settled_at' => $settlement->getSettledAtString(),
            ]);
        }

        return $this->findById($targetId) ?? $settlement;
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM parafiscal_settlements WHERE id = :id');
            if ($stmt === false) {
                return false;
            }
            $stmt->execute(['id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if ($this->isMissingTableException($e)) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): ParafiscalSettlement
    {
        return ParafiscalSettlement::fromArray($row);
    }

    private function isMissingTableException(PDOException $e): bool
    {
        return str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist");
    }
}
