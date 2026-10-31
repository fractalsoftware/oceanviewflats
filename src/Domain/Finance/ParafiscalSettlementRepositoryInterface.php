<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

/**
 * Authoritative repository port for FONTUR Parafiscal Settlements.
 */
interface ParafiscalSettlementRepositoryInterface
{
    public function findById(int $id): ?ParafiscalSettlement;

    public function findByUid(string $settlementUid): ?ParafiscalSettlement;

    public function findByPropertyAndPeriod(string $propertyId, string $periodKey): ?ParafiscalSettlement;

    public function findByOperationalExpenseId(int $operationalExpenseId): ?ParafiscalSettlement;

    /**
     * Lists Parafiscal Settlements filtered by property ('all', '1606', '1707'),
     * ordered by period_key DESC, property_id ASC.
     *
     * @return list<ParafiscalSettlement>
     */
    public function listAll(string $propertyId = 'all'): array;

    public function save(ParafiscalSettlement $settlement): ParafiscalSettlement;

    public function delete(int $id): bool;
}
