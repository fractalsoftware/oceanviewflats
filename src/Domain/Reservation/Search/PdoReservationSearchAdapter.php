<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Search;

use OceanViewFlats\Domain\Reservation\Reservation;
use PDO;
use PDOException;

/**
 * Production PDO search adapter implementing multi-field text search,
 * filtering, pagination, guest registry lookup, and audit trail aggregation.
 * Supports both MySQL and SQLite database drivers.
 */
final class PdoReservationSearchAdapter implements ReservationSearchInterface
{
    private const ALLOWED_SORT_COLUMNS = [
        'created_at' => 'created_at',
        'check_in' => 'check_in',
        'total_price' => 'total_price',
        'status' => 'status',
        'guest_name' => 'guest_name',
    ];

    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function search(ReservationSearchCriteria $criteria): ReservationSearchResult
    {
        $where = [];
        $params = [];

        // 1. Property ID filter
        if ($criteria->propertyId !== '' && $criteria->propertyId !== 'all') {
            $where[] = 'property_id = :property_id';
            $params[':property_id'] = $criteria->propertyId;
        }

        // 2. Status filter
        if ($criteria->status !== '' && $criteria->status !== 'all') {
            $where[] = 'status = :status';
            $params[':status'] = $criteria->status;
        }

        // 3. Registry status filter
        if ($criteria->registryStatus === 'completed') {
            $where[] = 'registry_completed = 1';
        } elseif ($criteria->registryStatus === 'pending') {
            $where[] = 'registry_completed = 0';
        }

        // 4. Source filter
        if ($criteria->source !== '' && $criteria->source !== 'all') {
            $where[] = 'source = :source';
            $params[':source'] = $criteria->source;
        }

        // 5. Text search on name, email, phone, reservation_uid, or external_confirmation_code
        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $where[] = '(guest_name LIKE :search OR guest_email LIKE :search OR guest_phone LIKE :search OR reservation_uid LIKE :search OR external_confirmation_code LIKE :search)';
            $params[':search'] = '%' . trim($criteria->query) . '%';
        }

        // 6. Check-in date range
        if ($criteria->checkInFrom !== null && trim($criteria->checkInFrom) !== '') {
            $where[] = 'check_in >= :check_in_from';
            $params[':check_in_from'] = trim($criteria->checkInFrom);
        }

        if ($criteria->checkInTo !== null && trim($criteria->checkInTo) !== '') {
            $where[] = 'check_in <= :check_in_to';
            $params[':check_in_to'] = trim($criteria->checkInTo);
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        // 7. Total count query
        $countSql = "SELECT COUNT(*) FROM `reservations` {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // 8. Pagination calculation
        $page = $criteria->page;
        $limit = $criteria->limit;
        $totalPages = $limit > 0 ? max(1, (int) ceil($total / $limit)) : 1;
        if ($page > $totalPages && $total > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        // 9. Sorting
        $sortCol = self::ALLOWED_SORT_COLUMNS[strtolower($criteria->sortBy)] ?? 'check_in';
        $sortDir = strtolower($criteria->sortDir) === 'asc' ? 'ASC' : 'DESC';

        // 10. Data query
        $dataSql = "
            SELECT * FROM `reservations`
            {$whereClause}
            ORDER BY `{$sortCol}` {$sortDir}, `id` DESC
            LIMIT :limit OFFSET :offset
        ";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($params as $key => $val) {
            $dataStmt->bindValue($key, $val);
        }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        /** @var list<array<string, mixed>> $items */
        $items = array_map(
            fn(array $row): array => $this->normalizeReservationProjection($row),
            $dataStmt->fetchAll(PDO::FETCH_ASSOC)
        );

        return new ReservationSearchResult(
            items: $items,
            totalCount: $total,
            page: $page,
            limit: $limit,
            totalPages: $totalPages
        );
    }

    public function findWithAuditTrail(string $uid): ?ReservationDossier
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `reservations` WHERE `reservation_uid` = :uid LIMIT 1');
        $stmt->execute([':uid' => $uid]);
        /** @var array<string, mixed>|false $reservation */
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservation === false) {
            return null;
        }

        $reservation = $this->normalizeReservationProjection($reservation);

        $guestRegistry = $this->findGuestRegistry($uid);
        $refunds = $this->findRefunds($uid);

        $auditLogs = [];
        try {
            $logStmt = $this->pdo->prepare('
                SELECT l.*, u.name AS admin_user_name, u.email AS admin_user_email
                FROM `admin_audit_logs` l
                LEFT JOIN `admin_users` u ON l.admin_user_id = u.id
                WHERE l.entity_type = :entity_type AND l.entity_id = :entity_id
                ORDER BY l.created_at DESC, l.id DESC
            ');
            $logStmt->execute([
                ':entity_type' => 'reservation',
                ':entity_id' => $uid,
            ]);
            /** @var list<array<string, mixed>> $auditLogs */
            $auditLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            $auditLogs = [];
        }

        return new ReservationDossier(
            reservation: $reservation,
            guestRegistry: $guestRegistry,
            auditLogs: $auditLogs,
            refunds: $refunds
        );
    }

    public function findGuestRegistry(string $uid): ?array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM `guest_registries`
                WHERE `reservation_uid` = :uid
                ORDER BY `id` DESC
                LIMIT 1
            ');
            $stmt->execute([':uid' => $uid]);
            /** @var array<string, mixed>|false $registry */
            $registry = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registry === false) {
                return null;
            }

            if (isset($registry['guests_payload']) && is_string($registry['guests_payload'])) {
                $decoded = json_decode($registry['guests_payload'], true);
                $registry['guests_payload'] = is_array($decoded) ? $decoded : [];
            }

            return $registry;
        } catch (PDOException) {
            return null;
        }
    }

    public function findRefunds(string $uid): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT r.*, u.name AS admin_user_name, u.email AS admin_user_email
                FROM `reservation_refunds` r
                LEFT JOIN `admin_users` u ON r.admin_user_id = u.id
                WHERE r.reservation_uid = :uid
                ORDER BY r.created_at DESC, r.id DESC
            ');
            $stmt->execute([':uid' => $uid]);
            /** @var list<array<string, mixed>> $refunds */
            $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $refunds;
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeReservationProjection(array $row): array
    {
        $rawMaxGuests = $row['max_guests'] ?? $row['maxGuests'] ?? Reservation::DEFAULT_MAX_GUESTS;
        $maxGuests = is_numeric($rawMaxGuests) ? (int) $rawMaxGuests : Reservation::DEFAULT_MAX_GUESTS;
        $row['max_guests'] = max(Reservation::MIN_MAX_GUESTS, min(Reservation::MAX_MAX_GUESTS, $maxGuests));

        return $row;
    }
}
