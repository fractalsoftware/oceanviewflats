<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Repository;

use DateTimeImmutable;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockRepositoryInterface;
use OceanViewFlats\Domain\Reservation\PdoMaintenanceBlockRepository;
use PDO;

/**
 * Administrative repository for querying, creating, and removing maintenance blocks
 * in calendar_blocks with author joins and date overlap detection per ADR 0006.
 *
 * Implements MaintenanceBlockRepositoryInterface and delegates to PdoMaintenanceBlockRepository
 * while preserving backwards compatibility for legacy methods.
 *
 * @deprecated Use \OceanViewFlats\Domain\Reservation\MaintenanceBlockRepositoryInterface instead.
 */
class AdminCalendarBlockRepository implements MaintenanceBlockRepositoryInterface
{
    private readonly PdoMaintenanceBlockRepository $pdoRepo;

    public function __construct(PDO|PdoMaintenanceBlockRepository $pdoOrRepo)
    {
        $this->pdoRepo = $pdoOrRepo instanceof PdoMaintenanceBlockRepository
            ? $pdoOrRepo
            : new PdoMaintenanceBlockRepository($pdoOrRepo);
    }

    /**
     * Retrieves maintenance blocks, filtered by property ('all', '1606', '1707') and status ('upcoming', 'past', 'all').
     *
     * @return list<MaintenanceBlock>
     */
    public function getBlocks(
        string $propertyId = 'all',
        string $filter = 'upcoming',
        ?DateTimeImmutable $now = null
    ): array {
        if (func_num_args() === 1 && $propertyId !== 'all') {
            return $this->pdoRepo->getBlocks($propertyId);
        }

        return $this->pdoRepo->listFiltered($propertyId, $filter, $now);
    }

    /**
     * @return list<string>
     */
    public function getBlockedNights(string $propertyId): array
    {
        return $this->pdoRepo->getBlockedNights($propertyId);
    }

    /**
     * Finds a single maintenance block by ID with author details.
     */
    public function findById(int $id): ?MaintenanceBlock
    {
        return $this->pdoRepo->findById($id);
    }

    /**
     * Checks if a proposed maintenance block interval collides with an existing block in calendar_blocks.
     */
    public function hasOverlap(
        string $propertyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): bool {
        return $this->pdoRepo->hasOverlap($propertyId, $startDate, $endDate, $excludeId);
    }

    public function save(MaintenanceBlock $block): MaintenanceBlock
    {
        return $this->pdoRepo->save($block);
    }

    public function delete(int $id): bool
    {
        return $this->pdoRepo->delete($id);
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function listFiltered(
        string $propertyId = 'all',
        string $filter = 'upcoming',
        ?DateTimeImmutable $now = null
    ): array {
        return $this->pdoRepo->listFiltered($propertyId, $filter, $now);
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getUpcomingBlocks(
        string $propertyId = 'all',
        int $lookaheadDays = 14,
        ?DateTimeImmutable $now = null
    ): array {
        return $this->pdoRepo->getUpcomingBlocks($propertyId, $lookaheadDays, $now);
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getBlocksForRange(
        string $propertyId,
        string $startDate,
        string $endDate
    ): array {
        return $this->pdoRepo->getBlocksForRange($propertyId, $startDate, $endDate);
    }

    /**
     * Creates a new maintenance block record.
     */
    public function createBlock(
        string $propertyId,
        string $startDate,
        string $endDate,
        string $reason,
        ?int $createdBy = null
    ): int {
        $block = new MaintenanceBlock(
            propertyId: $propertyId,
            startDate: $startDate,
            endDate: $endDate,
            reason: $reason,
            createdBy: $createdBy
        );

        $saved = $this->save($block);
        return (int) $saved->id;
    }

    /**
     * Deletes a maintenance block by ID.
     */
    public function deleteBlock(int $id): bool
    {
        return $this->delete($id);
    }
}
