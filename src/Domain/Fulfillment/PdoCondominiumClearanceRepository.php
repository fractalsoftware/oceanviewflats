<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;
use PDO;
use Throwable;

/**
 * PDO-backed database repository for CondominiumClearance entities.
 * Compatible with MySQL and SQLite.
 */
final class PdoCondominiumClearanceRepository implements CondominiumClearanceRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function save(CondominiumClearance $clearance): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $payloadJson = $clearance->requestPayload !== null
            ? json_encode($clearance->requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $now = date('Y-m-d H:i:s');
        $params = [
            ':reservation_uid' => $clearance->reservationUid,
            ':property_id' => $clearance->propertyId,
            ':status' => $clearance->status,
            ':clearance_number' => $clearance->clearanceNumber,
            ':error_message' => $clearance->errorMessage,
            ':request_payload' => $payloadJson,
            ':attempts' => $clearance->attempts,
            ':last_attempt_at' => $clearance->lastAttemptAt ?? $now,
            ':synced_at' => $clearance->syncedAt,
            ':created_at' => $clearance->createdAt ?? $now,
            ':updated_at' => $clearance->updatedAt ?? $now,
        ];

        if ($driver === 'sqlite') {
            $sql = '
                INSERT INTO condominium_clearances (
                    reservation_uid,
                    property_id,
                    status,
                    clearance_number,
                    error_message,
                    request_payload,
                    attempts,
                    last_attempt_at,
                    synced_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :reservation_uid,
                    :property_id,
                    :status,
                    :clearance_number,
                    :error_message,
                    :request_payload,
                    :attempts,
                    :last_attempt_at,
                    :synced_at,
                    :created_at,
                    :updated_at
                )
                ON CONFLICT(reservation_uid) DO UPDATE SET
                    property_id = excluded.property_id,
                    status = excluded.status,
                    clearance_number = excluded.clearance_number,
                    error_message = excluded.error_message,
                    request_payload = excluded.request_payload,
                    attempts = excluded.attempts,
                    last_attempt_at = excluded.last_attempt_at,
                    synced_at = excluded.synced_at,
                    updated_at = excluded.updated_at
            ';
        } else {
            $sql = '
                INSERT INTO `condominium_clearances` (
                    `reservation_uid`,
                    `property_id`,
                    `status`,
                    `clearance_number`,
                    `error_message`,
                    `request_payload`,
                    `attempts`,
                    `last_attempt_at`,
                    `synced_at`,
                    `created_at`,
                    `updated_at`
                ) VALUES (
                    :reservation_uid,
                    :property_id,
                    :status,
                    :clearance_number,
                    :error_message,
                    :request_payload,
                    :attempts,
                    :last_attempt_at,
                    :synced_at,
                    :created_at,
                    :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    `property_id` = VALUES(`property_id`),
                    `status` = VALUES(`status`),
                    `clearance_number` = VALUES(`clearance_number`),
                    `error_message` = VALUES(`error_message`),
                    `request_payload` = VALUES(`request_payload`),
                    `attempts` = VALUES(`attempts`),
                    `last_attempt_at` = VALUES(`last_attempt_at`),
                    `synced_at` = VALUES(`synced_at`),
                    `updated_at` = VALUES(`updated_at`)
            ';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function findByReservationUid(string $reservationUid): ?CondominiumClearance
    {
        $stmt = $this->pdo->prepare('SELECT * FROM condominium_clearances WHERE reservation_uid = :uid LIMIT 1');
        $stmt->execute([':uid' => $reservationUid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return CondominiumClearance::fromArray($row);
    }

    /**
     * @return list<CondominiumClearance>
     */
    public function findFailedClearances(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM condominium_clearances WHERE status = 'failed' ORDER BY last_attempt_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $row) {
            $results[] = CondominiumClearance::fromArray($row);
        }

        return $results;
    }

    /**
     * @return list<OperationalAlert>
     */
    public function getFailedClearanceAlerts(string $propertyId = 'all', string|DateTimeInterface|null $now = null): array
    {
        $today = is_string($now)
            ? substr($now, 0, 10)
            : ($now instanceof DateTimeInterface
                ? $now->format('Y-m-d')
                : (new DateTimeImmutable('today', new DateTimeZone('America/Bogota')))->format('Y-m-d'));

        $sql = "
            SELECT c.reservation_uid, c.property_id, c.error_message, c.status, c.attempts, c.last_attempt_at,
                   r.guest_name, r.check_in, r.check_out, r.status AS reservation_status
            FROM condominium_clearances c
            INNER JOIN reservations r ON c.reservation_uid = r.reservation_uid
            WHERE c.status = 'failed'
              AND r.status != 'cancelled'
              AND r.check_out > :today
        ";
        $params = [
            ':today' => $today,
        ];

        if ($propertyId !== 'all') {
            $sql .= ' AND c.property_id = :property_id';
            $params[':property_id'] = $propertyId;
        }

        $sql .= ' ORDER BY r.check_in ASC, c.last_attempt_at DESC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }

        $alerts = [];
        foreach ($rows as $row) {
            $reservationUid = (string) $row['reservation_uid'];
            $propId = (string) $row['property_id'];
            $checkIn = (string) $row['check_in'];
            $guestName = (string) ($row['guest_name'] ?? 'Guest');
            $rawErrorMessage = !empty($row['error_message']) ? (string) $row['error_message'] : 'Unknown error';

            // Severity: CRITICAL if check_in <= today, otherwise WARNING
            $severity = ($checkIn <= $today) ? AlertSeverity::CRITICAL : AlertSeverity::WARNING;

            $description = sprintf(
                'Clearance with Condominium Administration Portal failed for %s (%s). Building reception has not been notified.',
                $guestName,
                $rawErrorMessage
            );

            $alerts[] = new OperationalAlert(
                id: AlertType::clearanceAlertId($reservationUid),
                type: AlertType::FAILED_CONDOMINIUM_CLEARANCE,
                severity: $severity,
                propertyId: $propId,
                title: 'Condominium Clearance Failed',
                description: $description,
                dueDate: $checkIn,
                reservationUid: $reservationUid,
                guestName: $guestName,
                channelBlockUid: null,
                source: null,
                actionPayload: [
                    'reservationUid' => $reservationUid,
                    'propertyId' => $propId,
                    'errorMessage' => $rawErrorMessage,
                ]
            );
        }

        return $alerts;
    }
}
