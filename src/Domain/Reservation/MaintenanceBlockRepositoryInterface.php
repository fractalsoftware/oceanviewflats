<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;

/**
 * Authoritative domain repository port for maintenance calendar holds per ADR 0006.
 */
interface MaintenanceBlockRepositoryInterface extends MaintenanceBlockSourceInterface
{
    public function findById(int $id): ?MaintenanceBlock;

    public function hasOverlap(string $propertyId, string $startDate, string $endDate, ?int $excludeId = null): bool;

    public function save(MaintenanceBlock $block): MaintenanceBlock;

    public function delete(int $id): bool;

    /**
     * Retrieves maintenance blocks filtered by property ('all', '1606', '1707') and status ('upcoming', 'past', 'all').
     *
     * @return list<MaintenanceBlock>
     */
    public function listFiltered(string $propertyId = 'all', string $filter = 'upcoming', ?DateTimeImmutable $now = null): array;

    /**
     * Retrieves active or upcoming maintenance blocks within a lookahead window.
     *
     * @return list<MaintenanceBlock>
     */
    public function getUpcomingBlocks(string $propertyId = 'all', int $lookaheadDays = 14, ?DateTimeImmutable $now = null): array;

    /**
     * Retrieves maintenance blocks overlapping the given date range.
     *
     * @return list<MaintenanceBlock>
     */
    public function getBlocksForRange(string $propertyId, string $startDate, string $endDate): array;
}
