<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Repository;

use DateTimeImmutable;
use OceanViewFlats\Domain\Reservation\ChannelBlock;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertSeverity;
use OceanViewFlats\Domain\Reservation\Dashboard\AlertType;
use OceanViewFlats\Domain\Reservation\Dashboard\MovementType;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationalAlert;
use OceanViewFlats\Domain\Reservation\Dashboard\OperationsEvent;
use OceanViewFlats\Domain\Reservation\PdoReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Reservation\Search\PdoReservationSearchAdapter;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchCriteria;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use PDO;

/**
 * Backward-compatible adapter delegating administrative reservation queries,
 * search, and persistence to domain-level adapters.
 *
 * @deprecated Use \OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface and \OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface instead.
 */
final class AdminReservationRepository
{
    private readonly ReservationSearchInterface $search;
    private readonly ReservationRepositoryInterface $repository;

    public function __construct(
        private readonly PDO $pdo,
        ?ReservationSearchInterface $search = null,
        ?ReservationRepositoryInterface $repository = null
    ) {
        $this->search = $search ?? new PdoReservationSearchAdapter($this->pdo);
        $this->repository = $repository ?? new PdoReservationRepository($this->pdo);
    }

    /**
     * Searches and paginates reservations based on multi-field filters.
     *
     * @param array<string, mixed> $filters
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int
     * }
     */
    public function searchReservations(array $filters = []): array
    {
        if (!isset($filters['limit']) && !isset($filters['per_page']) && !isset($filters['perPage'])) {
            $filters['limit'] = 25;
        }
        $criteria = ReservationSearchCriteria::fromArray($filters);
        return $this->search->search($criteria)->toArray();
    }

    /**
     * Retrieves a reservation by UID along with its associated audit trail and refunds.
     *
     * @return array{
     *     reservation: array<string, mixed>,
     *     audit_logs: list<array<string, mixed>>,
     *     refunds: list<array<string, mixed>>
     * }|null
     */
    public function findReservationWithAuditTrail(string $uid): ?array
    {
        $dossier = $this->search->findWithAuditTrail($uid);
        return $dossier?->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRefundsByReservationUid(string $uid): array
    {
        return $this->search->findRefunds($uid);
    }

    /**
     * Retrieves reservation record for update with row locking where supported.
     *
     * @return array<string, mixed>|null
     */
    public function findReservationForUpdate(string $uid): ?array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lockClause = ($driver === 'mysql') ? ' FOR UPDATE' : '';

        $stmt = $this->pdo->prepare("SELECT * FROM reservations WHERE reservation_uid = :uid{$lockClause} LIMIT 1");
        $stmt->execute(['uid' => $uid]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Retrieves guest registry record for a given reservation UID.
     *
     * @return array<string, mixed>|null
     */
    public function findGuestRegistryByReservationUid(string $uid): ?array
    {
        return $this->search->findGuestRegistry($uid);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findReservationByUid(string $uid): ?array
    {
        $reservation = $this->repository->findByUid($uid);
        return $reservation?->toArray();
    }

    public function updateRegistryCompleted(string $uid, ?string $doorCode = null): bool
    {
        return $this->repository->markRegistryCompleted($uid, new \DateTimeImmutable(), $doorCode) !== null;
    }

    public function updateDoorCode(string $uid, string $doorCode): bool
    {
        return $this->repository->updateDoorCode($uid, $doorCode) !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createManualReservation(array $data): string
    {
        $reservation = Reservation::fromArray($data);
        $saved = $this->repository->save($reservation);
        return $saved->reservationUid;
    }

    /**
     * Updates reservation status to 'cancelled', records refund in reservation_refunds if amount > 0,
     * and updates refunded_amount and payment_status atomically.
     */
    public function cancelReservationWithRefund(
        string $uid,
        string $reason,
        string $refundType,
        float $refundAmount,
        ?string $mpRefundId,
        ?string $mpPaymentId,
        ?int $adminUserId,
        string $source = 'admin_pms'
    ): bool {
        $reservation = $this->repository->findByUid($uid);
        if ($reservation === null) {
            return false;
        }

        $totalPrice = $reservation->totalPrice;
        $currentRefunded = $reservation->refundedAmount;
        $refundAmount = max(0.0, round($refundAmount, 2));
        $newRefundedAmount = round($currentRefunded + $refundAmount, 2);

        $newPaymentStatus = $reservation->paymentStatus ?? 'pending_payment';
        if ($newRefundedAmount >= $totalPrice && $totalPrice > 0) {
            $newPaymentStatus = 'refunded';
        } elseif ($newRefundedAmount > 0) {
            $newPaymentStatus = 'partially_refunded';
        }

        // 1. Insert refund record if amount > 0
        if ($refundAmount > 0) {
            $this->repository->recordRefund([
                'reservation_uid' => $uid,
                'mercadopago_refund_id' => $mpRefundId,
                'mercadopago_payment_id' => $mpPaymentId !== null && $mpPaymentId !== '' ? $mpPaymentId : 'offline',
                'amount' => $refundAmount,
                'status' => 'approved',
                'reason' => $reason,
                'source' => $source,
                'admin_user_id' => $adminUserId,
            ]);
        }

        // 2. Append cancel note to existing notes
        $cancelNote = '[Cancelled ' . date('Y-m-d H:i') . '] ' . $reason;
        if ($refundAmount > 0) {
            $cancelNote .= ' (Refund: COP ' . number_format($refundAmount, 2) . ', type: ' . $refundType . ')';
        } else {
            $cancelNote .= ' (Policy retention: No refund)';
        }
        $existingNotes = $reservation->notes !== null ? trim($reservation->notes) : '';
        $updatedNotes = $existingNotes !== '' ? $existingNotes . "\n" . $cancelNote : $cancelNote;

        $cancelled = $reservation->withRefund(
            additionalRefundAmount: $refundAmount,
            notes: $updatedNotes,
            status: ReservationStatus::CANCELLED,
            paymentStatus: $newPaymentStatus
        );

        $this->repository->save($cancelled);
        return true;
    }

    /**
     * Fetches upcoming arrivals, departures, and active stays for the specified date window.
     * Detects same-day turnovers for identical properties.
     *
     * @param string $propertyId 'all' | '1606' | '1707'
     * @return list<OperationsEvent>
     */
    public function getOperationalSchedule(
        string $propertyId,
        string $startDate,
        string $endDate
    ): array {
        $sql = '
            SELECT reservation_uid, property_id, guest_name, guest_phone, check_in, check_out,
                   status, registry_completed, door_code, source, external_confirmation_code
            FROM reservations
            WHERE status IN ("confirmed", "pending_payment")
              AND (
                  (check_in >= :ci_start AND check_in <= :ci_end)
                  OR (check_out >= :co_start AND check_out <= :co_end)
              )
        ';
        $params = [
            'ci_start' => $startDate,
            'ci_end' => $endDate,
            'co_start' => $startDate,
            'co_end' => $endDate,
        ];

        if ($propertyId !== 'all') {
            $sql .= ' AND property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /** @var array<string, array<string, array<string, mixed>>> $departuresByDateAndProp */
        $departuresByDateAndProp = [];
        /** @var list<OperationsEvent> $events */
        $events = [];

        foreach ($rows as $row) {
            $checkOut = (string) $row['check_out'];
            $prop = (string) $row['property_id'];
            if ($checkOut >= $startDate && $checkOut <= $endDate) {
                $departuresByDateAndProp[$checkOut][$prop] = $row;
                $events[] = new OperationsEvent(
                    date: $checkOut,
                    movementType: MovementType::CHECK_OUT,
                    propertyId: $prop,
                    reservationUid: (string) $row['reservation_uid'],
                    guestName: (string) $row['guest_name'],
                    guestPhone: isset($row['guest_phone']) && $row['guest_phone'] !== '' ? (string) $row['guest_phone'] : null,
                    status: (string) $row['status'],
                    registryCompleted: ((int) ($row['registry_completed'] ?? 0)) === 1,
                    doorCode: isset($row['door_code']) && $row['door_code'] !== '' ? (string) $row['door_code'] : null,
                    source: (string) ($row['source'] ?? 'web'),
                    externalConfirmationCode: isset($row['external_confirmation_code']) && $row['external_confirmation_code'] !== '' ? (string) $row['external_confirmation_code'] : null
                );
            }
        }

        foreach ($rows as $row) {
            $checkIn = (string) $row['check_in'];
            $prop = (string) $row['property_id'];
            if ($checkIn >= $startDate && $checkIn <= $endDate) {
                $hasTurnover = isset($departuresByDateAndProp[$checkIn][$prop]);
                $depRow = $hasTurnover ? $departuresByDateAndProp[$checkIn][$prop] : null;

                $events[] = new OperationsEvent(
                    date: $checkIn,
                    movementType: $hasTurnover ? MovementType::TURNOVER : MovementType::CHECK_IN,
                    propertyId: $prop,
                    reservationUid: (string) $row['reservation_uid'],
                    guestName: (string) $row['guest_name'],
                    guestPhone: isset($row['guest_phone']) && $row['guest_phone'] !== '' ? (string) $row['guest_phone'] : null,
                    status: (string) $row['status'],
                    registryCompleted: ((int) ($row['registry_completed'] ?? 0)) === 1,
                    doorCode: isset($row['door_code']) && $row['door_code'] !== '' ? (string) $row['door_code'] : null,
                    source: (string) ($row['source'] ?? 'web'),
                    externalConfirmationCode: isset($row['external_confirmation_code']) && $row['external_confirmation_code'] !== '' ? (string) $row['external_confirmation_code'] : null,
                    departingReservationUid: $depRow !== null ? (string) $depRow['reservation_uid'] : null,
                    departingGuestName: $depRow !== null ? (string) $depRow['guest_name'] : null
                );
            }
        }

        usort($events, static function (OperationsEvent $a, OperationsEvent $b): int {
            if ($a->date !== $b->date) {
                return strcmp($a->date, $b->date);
            }
            $orderA = $a->movementType === MovementType::CHECK_OUT ? 0 : 1;
            $orderB = $b->movementType === MovementType::CHECK_OUT ? 0 : 1;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            return strcmp($a->propertyId, $b->propertyId);
        });

        return $events;
    }

    /**
     * Fetches confirmed reservations with imminent check-in dates whose Guest Registry is incomplete.
     *
     * @param string $propertyId 'all' | '1606' | '1707'
     * @return list<OperationalAlert>
     */
    public function getIncompleteRegistryAlerts(
        string $propertyId = 'all',
        int $lookaheadDays = 3,
        ?DateTimeImmutable $now = null
    ): array {
        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = ($now ?? new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $maxDate = ($now ?? new DateTimeImmutable('today'))->modify("+{$lookaheadDays} days")->format('Y-m-d');

        $sql = '
            SELECT reservation_uid, property_id, guest_name, guest_phone, check_in, check_out,
                   source, external_confirmation_code
            FROM reservations
            WHERE status = "confirmed"
              AND (registry_completed = 0 OR registry_completed IS NULL)
              AND (
                  (check_in >= :today_start AND check_in <= :max_date)
                  OR (check_in < :today_past AND check_out > :today_active)
              )
        ';
        $params = [
            'today_start' => $today,
            'max_date' => $maxDate,
            'today_past' => $today,
            'today_active' => $today,
        ];

        if ($propertyId !== 'all') {
            $sql .= ' AND property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        $sql .= ' ORDER BY check_in ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alerts = [];
        foreach ($rows as $row) {
            $checkIn = (string) $row['check_in'];
            $uid = (string) $row['reservation_uid'];
            $prop = (string) $row['property_id'];
            $guest = (string) $row['guest_name'];

            if ($checkIn <= $today) {
                $severity = AlertSeverity::CRITICAL;
                $title = $checkIn === $today
                    ? "Check-in Today: Missing Guest Registry ({$guest})"
                    : "In-House Guest: Missing Guest Registry ({$guest})";
                $description = "Apartment {$prop} check-in on {$checkIn} has no statutory guest identification registered. Door access credentials remain withheld.";
            } elseif ($checkIn === $tomorrow) {
                $severity = AlertSeverity::WARNING;
                $title = "Check-in Tomorrow: Guest Registry Required ({$guest})";
                $description = "Apartment {$prop} arrival tomorrow ({$checkIn}). Send registration link before door credentials are generated.";
            } else {
                $severity = AlertSeverity::INFO;
                $title = "Upcoming Arrival: Guest Registry Pending ({$guest})";
                $description = "Apartment {$prop} check-in on {$checkIn}. Advance registration pending.";
            }

            $alerts[] = new OperationalAlert(
                id: 'registry_' . $uid,
                type: AlertType::INCOMPLETE_GUEST_REGISTRY,
                severity: $severity,
                propertyId: $prop,
                title: $title,
                description: $description,
                dueDate: $checkIn,
                reservationUid: $uid,
                guestName: $guest,
                source: (string) ($row['source'] ?? 'web'),
                actionPayload: [
                    'guest_phone' => isset($row['guest_phone']) && $row['guest_phone'] !== '' ? (string) $row['guest_phone'] : null,
                    'external_code' => isset($row['external_confirmation_code']) && $row['external_confirmation_code'] !== '' ? (string) $row['external_confirmation_code'] : null,
                    'check_in' => $checkIn,
                    'check_out' => (string) $row['check_out'],
                ]
            );
        }

        return $alerts;
    }

    /**
     * Resolves un-onboarded external channel blocks from iCal sync feeds into OperationalAlerts.
     *
     * @param list<ChannelBlock> $channelBlocks
     * @param string $propertyId 'all' | '1606' | '1707'
     * @return list<OperationalAlert>
     */
    public function getUnonboardedChannelBlockAlerts(
        array $channelBlocks,
        string $propertyId = 'all',
        ?DateTimeImmutable $now = null
    ): array {
        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $warningLimit = ($now ?? new DateTimeImmutable('today'))->modify('+2 days')->format('Y-m-d');

        $alerts = [];
        foreach ($channelBlocks as $block) {
            if ($block->endDate <= $today) {
                continue;
            }
            if ($propertyId !== 'all' && $block->propertyId !== $propertyId) {
                continue;
            }

            $blockIdentifier = md5("{$block->propertyId}_{$block->startDate}_{$block->endDate}_{$block->source}");

            $stmt = $this->pdo->prepare('
                SELECT reservation_uid, guest_name
                FROM reservations
                WHERE property_id = :prop
                  AND status != "cancelled"
                  AND (
                      channel_block_uid = :block_uid
                      OR (check_in = :exact_start AND check_out = :exact_end)
                      OR (check_in < :overlap_end AND check_out > :overlap_start AND source = :source)
                  )
                LIMIT 1
            ');
            $stmt->execute([
                'prop' => $block->propertyId,
                'block_uid' => $blockIdentifier,
                'exact_start' => $block->startDate,
                'exact_end' => $block->endDate,
                'overlap_end' => $block->endDate,
                'overlap_start' => $block->startDate,
                'source' => $block->source,
            ]);
            $matched = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($matched !== false) {
                continue;
            }

            $isUrgent = $block->startDate <= $warningLimit;
            $severity = $isUrgent ? AlertSeverity::WARNING : AlertSeverity::INFO;
            $sourceLabel = ucfirst($block->source);

            $alerts[] = new OperationalAlert(
                id: 'channel_block_' . $blockIdentifier,
                type: AlertType::UNONBOARDED_CHANNEL_BLOCK,
                severity: $severity,
                propertyId: $block->propertyId,
                title: "Un-onboarded {$sourceLabel} Booking (Apt {$block->propertyId})",
                description: "External {$sourceLabel} hold from {$block->startDate} to {$block->endDate} has no guest profile or reservation record.",
                dueDate: $block->startDate,
                channelBlockUid: $blockIdentifier,
                source: $block->source,
                actionPayload: [
                    'start_date' => $block->startDate,
                    'end_date' => $block->endDate,
                    'summary' => $block->summary,
                    'property_id' => $block->propertyId,
                    'channel_block_uid' => $blockIdentifier,
                ]
            );
        }

        usort($alerts, static fn(OperationalAlert $a, OperationalAlert $b): int => strcmp($a->dueDate, $b->dueDate));

        return $alerts;
    }

    /**
     * Fetches confirmed reservations overlapping the specified date window.
     *
     * @param string $propertyId 'all' | '1606' | '1707'
     * @return list<array{
     *     reservation_uid: string,
     *     property_id: string,
     *     check_in: string,
     *     check_out: string,
     *     guest_name: string,
     *     status: string,
     *     registry_completed: int|string,
     *     source: string,
     *     external_confirmation_code: ?string
     * }>
     */
    public function getConfirmedReservationsForRange(
        string $propertyId,
        string $startDate,
        string $endDate
    ): array {
        $sql = '
            SELECT reservation_uid, property_id, check_in, check_out, guest_name, status,
                   registry_completed, source, external_confirmation_code
            FROM reservations
            WHERE status IN ("confirmed", "pending_payment")
              AND check_in <= :end_date
              AND check_out >= :start_date
        ';
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($propertyId !== 'all') {
            $sql .= ' AND property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        $sql .= ' ORDER BY check_in ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var list<array{
         *     reservation_uid: string,
         *     property_id: string,
         *     check_in: string,
         *     check_out: string,
         *     guest_name: string,
         *     status: string,
         *     registry_completed: int|string,
         *     source: string,
         *     external_confirmation_code: ?string
         * }> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    public function getActiveStaysCount(string $propertyId = 'all', ?DateTimeImmutable $now = null): int
    {
        $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $sql = '
            SELECT COUNT(*)
            FROM reservations
            WHERE status = "confirmed"
              AND check_in <= :today_in
              AND check_out > :today_out
        ';
        $params = [
            'today_in' => $today,
            'today_out' => $today,
        ];

        if ($propertyId !== 'all') {
            $sql .= ' AND property_id = :property_id';
            $params['property_id'] = $propertyId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
