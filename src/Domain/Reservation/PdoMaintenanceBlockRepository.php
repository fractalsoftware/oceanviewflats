<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * PDO database-backed adapter for administrative maintenance blocks per ADR 0006.
 * Implements both read-port (MaintenanceBlockSourceInterface) and mutation-port (MaintenanceBlockRepositoryInterface).
 */
class PdoMaintenanceBlockRepository implements MaintenanceBlockRepositoryInterface
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getBlocks(string $propertyId): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT cb.id, cb.property_id, cb.start_date, cb.end_date, cb.reason, cb.created_by, cb.created_at,
                       u.name AS created_by_name
                FROM calendar_blocks cb
                LEFT JOIN admin_users u ON cb.created_by = u.id
                WHERE cb.property_id = :property_id
                ORDER BY cb.start_date ASC
            ');
            $stmt->execute(['property_id' => $propertyId]);

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blocks = [];
            foreach ($rows as $row) {
                $blocks[] = $this->hydrateRow($row);
            }

            return $blocks;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
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

    public function findById(int $id): ?MaintenanceBlock
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT cb.id, cb.property_id, cb.start_date, cb.end_date, cb.reason, cb.created_by, cb.created_at,
                       u.name AS created_by_name
                FROM calendar_blocks cb
                LEFT JOIN admin_users u ON cb.created_by = u.id
                WHERE cb.id = :id
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

    /**
     * Checks if a proposed stay or block interval collides with an existing maintenance block in the database.
     */
    public function hasOverlap(
        string $propertyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null
    ): bool {
        $sql = '
            SELECT COUNT(*) 
            FROM calendar_blocks 
            WHERE property_id = :property_id 
              AND start_date < :end_date 
              AND end_date > :start_date
        ';
        $params = [
            'property_id' => $propertyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return false;
            }
            throw $e;
        }
    }

    public function save(MaintenanceBlock $block): MaintenanceBlock
    {
        if (!in_array($block->propertyId, ['1606', '1707'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid property ID: %s', $block->propertyId));
        }

        if ($block->id === null) {
            $stmt = $this->pdo->prepare('
                INSERT INTO calendar_blocks (property_id, start_date, end_date, reason, created_by, created_at, updated_at)
                VALUES (:property_id, :start_date, :end_date, :reason, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            $stmt->execute([
                'property_id' => $block->propertyId,
                'start_date' => $block->startDate,
                'end_date' => $block->endDate,
                'reason' => trim($block->reason),
                'created_by' => $block->createdBy,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            return $this->findById($id) ?? new MaintenanceBlock(
                propertyId: $block->propertyId,
                startDate: $block->startDate,
                endDate: $block->endDate,
                reason: trim($block->reason),
                id: $id,
                createdBy: $block->createdBy,
                createdByName: $block->createdByName,
                createdAt: new DateTimeImmutable()
            );
        }

        $stmt = $this->pdo->prepare('
            UPDATE calendar_blocks
            SET property_id = :property_id,
                start_date = :start_date,
                end_date = :end_date,
                reason = :reason,
                created_by = :created_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $block->id,
            'property_id' => $block->propertyId,
            'start_date' => $block->startDate,
            'end_date' => $block->endDate,
            'reason' => trim($block->reason),
            'created_by' => $block->createdBy,
        ]);

        return $this->findById($block->id) ?? $block;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM calendar_blocks WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
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

        $baseSql = '
            FROM calendar_blocks cb
            LEFT JOIN admin_users u ON cb.created_by = u.id
            WHERE 1=1
        ';
        $params = [];

        if ($propertyId !== 'all') {
            $baseSql .= ' AND cb.property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        if ($filter === 'upcoming') {
            $baseSql .= ' AND cb.end_date >= :today ORDER BY cb.start_date ASC';
            $params['today'] = $today;
        } elseif ($filter === 'past') {
            $baseSql .= ' AND cb.end_date < :today ORDER BY cb.start_date DESC';
            $params['today'] = $today;
        } else {
            $baseSql .= ' ORDER BY cb.start_date DESC';
        }

        $sql = 'SELECT cb.id, cb.property_id, cb.start_date, cb.end_date, cb.reason, cb.created_by, cb.created_at, u.name AS created_by_name ' . $baseSql;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blocks = [];
            foreach ($rows as $row) {
                $blocks[] = $this->hydrateRow($row);
            }

            return $blocks;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
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

        $baseSql = '
            FROM calendar_blocks cb
            LEFT JOIN admin_users u ON cb.created_by = u.id
            WHERE cb.end_date >= :today
              AND cb.start_date <= :max_date
        ';
        $params = [
            'today' => $today,
            'max_date' => $maxDate,
        ];

        if ($propertyId !== 'all') {
            $baseSql .= ' AND cb.property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        $baseSql .= ' ORDER BY cb.start_date ASC';

        $sql = 'SELECT cb.id, cb.property_id, cb.start_date, cb.end_date, cb.reason, cb.created_by, cb.created_at, u.name AS created_by_name ' . $baseSql;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blocks = [];
            foreach ($rows as $row) {
                $blocks[] = $this->hydrateRow($row);
            }

            return $blocks;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * @return list<MaintenanceBlock>
     */
    public function getBlocksForRange(
        string $propertyId,
        string $startDate,
        string $endDate
    ): array {
        $baseSql = '
            FROM calendar_blocks cb
            LEFT JOIN admin_users u ON cb.created_by = u.id
            WHERE cb.start_date < :end_date
              AND cb.end_date > :start_date
        ';
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($propertyId !== 'all') {
            $baseSql .= ' AND cb.property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        $baseSql .= ' ORDER BY cb.start_date ASC';

        $sql = 'SELECT cb.id, cb.property_id, cb.start_date, cb.end_date, cb.reason, cb.created_by, cb.created_at, u.name AS created_by_name ' . $baseSql;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blocks = [];
            foreach ($rows as $row) {
                $blocks[] = $this->hydrateRow($row);
            }

            return $blocks;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function hydrateRow(array $row): MaintenanceBlock
    {
        $createdAt = isset($row['created_at']) && is_string($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;

        return new MaintenanceBlock(
            propertyId: (string) $row['property_id'],
            startDate: (string) $row['start_date'],
            endDate: (string) $row['end_date'],
            reason: (string) $row['reason'],
            id: isset($row['id']) ? (int) $row['id'] : null,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdByName: isset($row['created_by_name']) ? (string) $row['created_by_name'] : null,
            createdAt: $createdAt
        );
    }
}
