<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

use PDO;
use Throwable;

/**
 * PDO-backed repository for manual operational alert dismissals.
 * Compatible with both MySQL and SQLite.
 */
final class PdoOperationalAlertDismissalRepository implements OperationalAlertDismissalRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function isDismissed(string $alertId): bool
    {
        if ($alertId === '') {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM operational_alert_dismissals WHERE alert_id = :alert_id LIMIT 1'
            );
            $stmt->execute([':alert_id' => $alertId]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param list<string>|null $alertIds
     * @return list<string>
     */
    public function getDismissedAlertIds(string $propertyId = 'all', ?array $alertIds = null): array
    {
        if ($alertIds !== null && $alertIds === []) {
            return [];
        }

        $sql = 'SELECT alert_id FROM operational_alert_dismissals WHERE 1=1';
        /** @var list<string> $params */
        $params = [];

        if ($propertyId !== 'all' && $propertyId !== '') {
            $sql .= ' AND (property_id = ? OR property_id = \'all\')';
            $params[] = $propertyId;
        }

        if ($alertIds !== null) {
            $placeholders = implode(', ', array_fill(0, count($alertIds), '?'));
            $sql .= " AND alert_id IN ({$placeholders})";
            foreach ($alertIds as $id) {
                $params[] = $id;
            }
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            /** @var list<string> $rows */
            $rows = array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    public function dismiss(
        string $alertId,
        string $alertType,
        string $propertyId,
        ?string $reservationUid = null,
        ?int $dismissedBy = null
    ): void {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = date('Y-m-d H:i:s');

        $params = [
            ':alert_id' => $alertId,
            ':alert_type' => $alertType,
            ':property_id' => $propertyId,
            ':reservation_uid' => $reservationUid,
            ':dismissed_by' => $dismissedBy,
            ':dismissed_at' => $now,
        ];

        if ($driver === 'sqlite') {
            $sql = '
                INSERT INTO operational_alert_dismissals (
                    alert_id,
                    alert_type,
                    property_id,
                    reservation_uid,
                    dismissed_by,
                    dismissed_at
                ) VALUES (
                    :alert_id,
                    :alert_type,
                    :property_id,
                    :reservation_uid,
                    :dismissed_by,
                    :dismissed_at
                )
                ON CONFLICT(alert_id) DO UPDATE SET
                    alert_type = excluded.alert_type,
                    property_id = excluded.property_id,
                    reservation_uid = excluded.reservation_uid,
                    dismissed_by = excluded.dismissed_by,
                    dismissed_at = excluded.dismissed_at
            ';
        } else {
            $sql = '
                INSERT INTO `operational_alert_dismissals` (
                    `alert_id`,
                    `alert_type`,
                    `property_id`,
                    `reservation_uid`,
                    `dismissed_by`,
                    `dismissed_at`
                ) VALUES (
                    :alert_id,
                    :alert_type,
                    :property_id,
                    :reservation_uid,
                    :dismissed_by,
                    :dismissed_at
                )
                ON DUPLICATE KEY UPDATE
                    `alert_type` = VALUES(`alert_type`),
                    `property_id` = VALUES(`property_id`),
                    `reservation_uid` = VALUES(`reservation_uid`),
                    `dismissed_by` = VALUES(`dismissed_by`),
                    `dismissed_at` = VALUES(`dismissed_at`)
            ';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function clearDismissal(string $alertId): void
    {
        if ($alertId === '') {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM operational_alert_dismissals WHERE alert_id = :alert_id'
            );
            $stmt->execute([':alert_id' => $alertId]);
        } catch (Throwable) {
            // Ignore when table does not exist in minimal unit test schemas
        }
    }
}
