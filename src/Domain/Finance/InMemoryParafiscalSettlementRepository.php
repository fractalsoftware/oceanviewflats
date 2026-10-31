<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use DateTimeImmutable;

/**
 * In-memory test adapter for ParafiscalSettlementRepositoryInterface.
 */
final class InMemoryParafiscalSettlementRepository implements ParafiscalSettlementRepositoryInterface
{
    /** @var array<int, ParafiscalSettlement> */
    private array $settlementsById = [];

    private int $autoIncrementId = 0;

    /**
     * @param list<ParafiscalSettlement> $initialSettlements
     */
    public function __construct(array $initialSettlements = [])
    {
        foreach ($initialSettlements as $settlement) {
            $this->save($settlement);
        }
    }

    public function findById(int $id): ?ParafiscalSettlement
    {
        return $this->settlementsById[$id] ?? null;
    }

    public function findByUid(string $settlementUid): ?ParafiscalSettlement
    {
        foreach ($this->settlementsById as $settlement) {
            if ($settlement->settlementUid === $settlementUid) {
                return $settlement;
            }
        }

        return null;
    }

    public function findByPropertyAndPeriod(string $propertyId, string $periodKey): ?ParafiscalSettlement
    {
        $normalizedProperty = trim($propertyId);
        $normalizedPeriod = strtoupper(trim($periodKey));

        foreach ($this->settlementsById as $settlement) {
            if ($settlement->propertyId === $normalizedProperty && $settlement->periodKey === $normalizedPeriod) {
                return $settlement;
            }
        }

        return null;
    }

    public function findByOperationalExpenseId(int $operationalExpenseId): ?ParafiscalSettlement
    {
        foreach ($this->settlementsById as $settlement) {
            if ($settlement->operationalExpenseId === $operationalExpenseId) {
                return $settlement;
            }
        }

        return null;
    }

    /**
     * @return list<ParafiscalSettlement>
     */
    public function listAll(string $propertyId = 'all'): array
    {
        $normalizedProperty = trim($propertyId);
        $results = [];

        foreach ($this->settlementsById as $settlement) {
            if (in_array($normalizedProperty, ['1606', '1707'], true) && $settlement->propertyId !== $normalizedProperty) {
                continue;
            }
            $results[] = $settlement;
        }

        usort($results, static function (ParafiscalSettlement $a, ParafiscalSettlement $b): int {
            $periodCompare = strcmp($b->periodKey, $a->periodKey);
            if ($periodCompare !== 0) {
                return $periodCompare;
            }

            return strcmp($a->propertyId, $b->propertyId);
        });

        return $results;
    }

    public function save(ParafiscalSettlement $settlement): ParafiscalSettlement
    {
        $existing = $settlement->id !== null
            ? ($this->settlementsById[$settlement->id] ?? null)
            : $this->findByPropertyAndPeriod($settlement->propertyId, $settlement->periodKey);

        $id = $existing !== null
            ? ($existing->id ?? ++$this->autoIncrementId)
            : ($settlement->id ?? ++$this->autoIncrementId);
        if ($id > $this->autoIncrementId) {
            $this->autoIncrementId = $id;
        }

        $now = new DateTimeImmutable();
        $createdAt = ($existing !== null && $existing->createdAt !== null)
            ? $existing->createdAt
            : ($settlement->createdAt ?? $now);
        $uid = $existing !== null ? $existing->settlementUid : $settlement->settlementUid;

        $persisted = new ParafiscalSettlement(
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
            settlementUid: $uid,
            id: $id,
            createdAt: $createdAt,
            updatedAt: $now
        );

        $this->settlementsById[$id] = $persisted;

        return $persisted;
    }

    public function delete(int $id): bool
    {
        if (isset($this->settlementsById[$id])) {
            unset($this->settlementsById[$id]);
            return true;
        }

        return false;
    }
}
