<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * In-memory double for MaintenanceBlockRepositoryInterface.
 * Enables zero-DDL fast in-memory testing for administrative and domain calendar operations.
 */
class InMemoryMaintenanceBlockRepository extends InMemoryMaintenanceBlockSource implements MaintenanceBlockRepositoryInterface
{
    /** @var array<int, MaintenanceBlock> */
    private array $blocksById = [];

    private int $autoIncrementId = 0;

    /**
     * @param list<MaintenanceBlock> $initialBlocks
     */
    public function __construct(array $initialBlocks = [])
    {
        parent::__construct();
        foreach ($initialBlocks as $block) {
            $this->save($block);
        }
    }

    public function findById(int $id): ?MaintenanceBlock
    {
        return $this->blocksById[$id] ?? null;
    }

    public function hasOverlap(
        string $propertyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): bool {
        foreach ($this->getBlocks($propertyId) as $existing) {
            if ($excludeId !== null && $existing->id === $excludeId) {
                continue;
            }
            if ($existing->overlaps($startDate, $endDate)) {
                return true;
            }
        }

        return false;
    }

    public function save(MaintenanceBlock $block): MaintenanceBlock
    {
        if (!in_array($block->propertyId, ['1606', '1707'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid property ID: %s', $block->propertyId));
        }

        $id = $block->id ?? ++$this->autoIncrementId;
        if ($id > $this->autoIncrementId) {
            $this->autoIncrementId = $id;
        }

        $createdAt = $block->createdAt ?? new DateTimeImmutable();
        $persisted = new MaintenanceBlock(
            propertyId: $block->propertyId,
            startDate: $block->startDate,
            endDate: $block->endDate,
            reason: trim($block->reason),
            id: $id,
            createdBy: $block->createdBy,
            createdByName: $block->createdByName,
            createdAt: $createdAt
        );

        $this->blocksById[$id] = $persisted;

        return $persisted;
    }

    public function delete(int $id): bool
    {
        if (isset($this->blocksById[$id])) {
            unset($this->blocksById[$id]);
            return true;
        }

        return false;
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getBlocks(string $propertyId): array
    {
        $filtered = array_values(array_filter(
            $this->blocksById,
            static fn (MaintenanceBlock $b): bool => $b->propertyId === $propertyId
        ));

        usort($filtered, static fn (MaintenanceBlock $a, MaintenanceBlock $b): int => strcmp($a->startDate, $b->startDate));

        return $filtered;
    }

    public function addBlock(MaintenanceBlock $block): void
    {
        $this->save($block);
    }

    /**
     * @return list<string>
     */
    public function getBlockedNights(string $propertyId): array
    {
        $nights = [];
        foreach ($this->getBlocks($propertyId) as $block) {
            foreach ($block->nights() as $night) {
                $nights[] = $night;
            }
        }

        $unique = array_values(array_unique($nights));
        sort($unique);
        return $unique;
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function listFiltered(
        string $propertyId = 'all',
        string $filter = 'upcoming',
        ?DateTimeImmutable $now = null
    ): array {
        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $results = [];

        foreach ($this->blocksById as $block) {
            if ($propertyId !== 'all' && $block->propertyId !== $propertyId) {
                continue;
            }

            if ($filter === 'upcoming') {
                if ($block->endDate >= $today) {
                    $results[] = $block;
                }
            } elseif ($filter === 'past') {
                if ($block->endDate < $today) {
                    $results[] = $block;
                }
            } else {
                $results[] = $block;
            }
        }

        usort($results, static function (MaintenanceBlock $a, MaintenanceBlock $b) use ($filter): int {
            if ($filter === 'upcoming') {
                return strcmp($a->startDate, $b->startDate);
            }
            return strcmp($b->startDate, $a->startDate);
        });

        return $results;
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getUpcomingBlocks(
        string $propertyId = 'all',
        int $lookaheadDays = 14,
        ?DateTimeImmutable $now = null
    ): array {
        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $maxDate = ($now ?? new DateTimeImmutable('today'))->modify("+{$lookaheadDays} days")->format('Y-m-d');

        $results = [];

        foreach ($this->blocksById as $block) {
            if ($propertyId !== 'all' && $block->propertyId !== $propertyId) {
                continue;
            }

            if ($block->endDate >= $today && $block->startDate <= $maxDate) {
                $results[] = $block;
            }
        }

        usort($results, static fn(MaintenanceBlock $a, MaintenanceBlock $b): int => strcmp($a->startDate, $b->startDate));

        return $results;
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getBlocksForRange(
        string $propertyId,
        string $startDate,
        string $endDate
    ): array {
        $results = [];

        foreach ($this->blocksById as $block) {
            if ($propertyId !== 'all' && $block->propertyId !== $propertyId) {
                continue;
            }

            if ($block->startDate < $endDate && $block->endDate > $startDate) {
                $results[] = $block;
            }
        }

        usort($results, static fn(MaintenanceBlock $a, MaintenanceBlock $b): int => strcmp($a->startDate, $b->startDate));

        return $results;
    }
}
