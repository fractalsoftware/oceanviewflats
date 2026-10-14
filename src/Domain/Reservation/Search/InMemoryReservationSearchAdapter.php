<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Search;

use OceanViewFlats\Domain\Reservation\InMemoryReservationRepository;
use OceanViewFlats\Domain\Reservation\Reservation;

/**
 * Fast in-memory search adapter for hermetic unit testing without database fixtures.
 */
final class InMemoryReservationSearchAdapter implements ReservationSearchInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $reservations = [];

    /** @var array<string, array<string, mixed>> */
    private array $guestRegistries = [];

    /** @var list<array<string, mixed>> */
    private array $auditLogs = [];

    /** @var list<array<string, mixed>> */
    private array $refunds = [];

    /**
     * @param list<Reservation|array<string, mixed>> $reservations
     * @param list<array<string, mixed>> $auditLogs
     * @param array<string, array<string, mixed>>|list<array<string, mixed>> $guestRegistries
     * @param list<array<string, mixed>> $refunds
     */
    public function __construct(
        array $reservations = [],
        array $auditLogs = [],
        array $guestRegistries = [],
        array $refunds = [],
        private readonly ?InMemoryReservationRepository $repository = null
    ) {
        foreach ($reservations as $res) {
            $this->addReservation($res);
        }
        foreach ($auditLogs as $log) {
            $this->addAuditLog($log);
        }
        foreach ($guestRegistries as $key => $registry) {
            $uid = is_string($key) && $key !== '' ? $key : (string) ($registry['reservation_uid'] ?? '');
            if ($uid !== '') {
                $this->addGuestRegistry($uid, $registry);
            }
        }
        foreach ($refunds as $refund) {
            $this->addRefund($refund);
        }
    }

    /**
     * @param Reservation|array<string, mixed> $reservation
     */
    public function addReservation(Reservation|array $reservation): self
    {
        $data = $reservation instanceof Reservation ? $reservation->toArray() : $reservation;
        $rawMaxGuests = $data['max_guests'] ?? $data['maxGuests'] ?? Reservation::DEFAULT_MAX_GUESTS;
        $maxGuests = is_numeric($rawMaxGuests) ? (int) $rawMaxGuests : Reservation::DEFAULT_MAX_GUESTS;
        $data['max_guests'] = max(Reservation::MIN_MAX_GUESTS, min(Reservation::MAX_MAX_GUESTS, $maxGuests));

        $uid = (string) ($data['reservation_uid'] ?? '');
        if ($uid !== '') {
            $this->reservations[$uid] = $data;
        }
        return $this;
    }

    /**
     * @param array<string, mixed> $registry
     */
    public function addGuestRegistry(string $uid, array $registry): self
    {
        $this->guestRegistries[$uid] = $registry;
        return $this;
    }

    public function removeGuestRegistry(string $uid): self
    {
        unset($this->guestRegistries[$uid]);
        return $this;
    }

    /**
     * @param array<string, mixed> $auditLog
     */
    public function addAuditLog(array $auditLog): self
    {
        $this->auditLogs[] = $auditLog;
        return $this;
    }

    /**
     * @param array<string, mixed> $refund
     */
    public function addRefund(array $refund): self
    {
        $this->refunds[] = $refund;
        return $this;
    }

    public function search(ReservationSearchCriteria $criteria): ReservationSearchResult
    {
        $all = $this->allReservations();

        $filtered = array_filter($all, function (array $row) use ($criteria): bool {
            // 1. Property ID
            if ($criteria->propertyId !== '' && $criteria->propertyId !== 'all') {
                if ((string) ($row['property_id'] ?? '') !== $criteria->propertyId) {
                    return false;
                }
            }

            // 2. Status
            if ($criteria->status !== '' && $criteria->status !== 'all') {
                if ((string) ($row['status'] ?? '') !== $criteria->status) {
                    return false;
                }
            }

            // 3. Registry status
            if ($criteria->registryStatus === 'completed') {
                if (empty($row['registry_completed'])) {
                    return false;
                }
            } elseif ($criteria->registryStatus === 'pending') {
                if (!empty($row['registry_completed'])) {
                    return false;
                }
            }

            // 4. Source
            if ($criteria->source !== '' && $criteria->source !== 'all') {
                if ((string) ($row['source'] ?? 'web') !== $criteria->source) {
                    return false;
                }
            }

            // 5. Query
            if ($criteria->query !== null && trim($criteria->query) !== '') {
                $needle = mb_strtolower(trim($criteria->query));
                $name = mb_strtolower((string) ($row['guest_name'] ?? ''));
                $email = mb_strtolower((string) ($row['guest_email'] ?? ''));
                $phone = mb_strtolower((string) ($row['guest_phone'] ?? ''));
                $uid = mb_strtolower((string) ($row['reservation_uid'] ?? ''));
                $extCode = mb_strtolower((string) ($row['external_confirmation_code'] ?? ''));

                if (
                    str_contains($name, $needle) === false
                    && str_contains($email, $needle) === false
                    && str_contains($phone, $needle) === false
                    && str_contains($uid, $needle) === false
                    && str_contains($extCode, $needle) === false
                ) {
                    return false;
                }
            }

            // 6. Check-in range
            if ($criteria->checkInFrom !== null && trim($criteria->checkInFrom) !== '') {
                if ((string) ($row['check_in'] ?? '') < trim($criteria->checkInFrom)) {
                    return false;
                }
            }

            if ($criteria->checkInTo !== null && trim($criteria->checkInTo) !== '') {
                if ((string) ($row['check_in'] ?? '') > trim($criteria->checkInTo)) {
                    return false;
                }
            }

            return true;
        });

        // Sorting
        $sortCol = ReservationSearchCriteria::ALLOWED_SORT_COLUMNS[strtolower($criteria->sortBy)] ?? 'check_in';
        $isAsc = strtolower($criteria->sortDir) === 'asc';

        usort($filtered, function (array $a, array $b) use ($sortCol, $isAsc): int {
            $valA = $a[$sortCol] ?? '';
            $valB = $b[$sortCol] ?? '';

            if ($valA == $valB) {
                $idA = (int) ($a['id'] ?? 0);
                $idB = (int) ($b['id'] ?? 0);
                return $idB <=> $idA;
            }

            $cmp = ($valA <=> $valB);
            return $isAsc ? $cmp : -$cmp;
        });

        $total = count($filtered);
        $page = $criteria->page;
        $limit = $criteria->limit;
        $totalPages = $limit > 0 ? max(1, (int) ceil($total / $limit)) : 1;
        if ($page > $totalPages && $total > 0) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;
        /** @var list<array<string, mixed>> $items */
        $items = array_slice($filtered, $offset, $limit);

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
        $reservation = $this->findReservationArray($uid);
        if ($reservation === null) {
            return null;
        }

        $guestRegistry = $this->findGuestRegistry($uid);
        $refunds = $this->findRefunds($uid);

        $matchingLogs = array_values(array_filter(
            $this->auditLogs,
            fn(array $log): bool => ((string) ($log['entity_type'] ?? '')) === 'reservation'
                && ((string) ($log['entity_id'] ?? '')) === $uid
        ));

        usort($matchingLogs, function (array $a, array $b): int {
            $cA = (string) ($a['created_at'] ?? '');
            $cB = (string) ($b['created_at'] ?? '');
            if ($cA === $cB) {
                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }
            return strcmp($cB, $cA);
        });

        return new ReservationDossier(
            reservation: $reservation,
            guestRegistry: $guestRegistry,
            auditLogs: $matchingLogs,
            refunds: $refunds
        );
    }

    public function findGuestRegistry(string $uid): ?array
    {
        $registry = $this->guestRegistries[$uid] ?? null;
        if ($registry === null) {
            return null;
        }

        if (isset($registry['guests_payload']) && is_string($registry['guests_payload'])) {
            $decoded = json_decode($registry['guests_payload'], true);
            $registry['guests_payload'] = is_array($decoded) ? $decoded : [];
        }

        return $registry;
    }

    public function findRefunds(string $uid): array
    {
        $allRefunds = $this->refunds;
        if ($this->repository !== null) {
            $allRefunds = array_merge($allRefunds, $this->repository->getRefunds());
        }

        $matching = array_values(array_filter(
            $allRefunds,
            fn(array $r): bool => ((string) ($r['reservation_uid'] ?? '')) === $uid
        ));

        usort($matching, function (array $a, array $b): int {
            $cA = (string) ($a['created_at'] ?? '');
            $cB = (string) ($b['created_at'] ?? '');
            if ($cA === $cB) {
                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }
            return strcmp($cB, $cA);
        });

        return $matching;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function allReservations(): array
    {
        $results = $this->reservations;
        if ($this->repository !== null) {
            foreach ($this->repository->all() as $uid => $reservation) {
                if (!isset($results[$uid])) {
                    $results[$uid] = $reservation->toArray();
                }
            }
        }
        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findReservationArray(string $uid): ?array
    {
        if (isset($this->reservations[$uid])) {
            return $this->reservations[$uid];
        }
        if ($this->repository !== null) {
            $entity = $this->repository->findByUid($uid);
            if ($entity !== null) {
                return $entity->toArray();
            }
        }
        return null;
    }
}
