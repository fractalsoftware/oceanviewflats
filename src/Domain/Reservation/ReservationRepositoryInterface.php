<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;

interface ReservationRepositoryInterface
{
    /**
     * Persists a reservation (insert or update).
     */
    public function save(Reservation $reservation): Reservation;

    /**
     * Finds a reservation by its unique business reservation UID (e.g., ovf_...).
     */
    public function findByUid(string $reservationUid): ?Reservation;

    /**
     * Retrieves all actively holding reservations for a property, evaluating dynamic hold expirations.
     *
     * @return list<Reservation>
     */
    public function findActiveByProperty(
        string $propertyId,
        ?DateTimeImmutable $now = null,
        int $standardHoldMinutes = Reservation::DEFAULT_STANDARD_HOLD_MINUTES,
        int $voucherHoldHours = Reservation::DEFAULT_VOUCHER_HOLD_HOURS
    ): array;

    /**
     * Finds any actively holding reservations for a property that overlap with the requested dates.
     *
     * @return list<Reservation>
     */
    public function findOverlappingActive(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        int $standardHoldMinutes = Reservation::DEFAULT_STANDARD_HOLD_MINUTES,
        int $voucherHoldHours = Reservation::DEFAULT_VOUCHER_HOLD_HOURS,
        ?string $excludeReservationUid = null
    ): array;

    /**
     * Updates the status and payment details of an existing reservation.
     */
    public function updateStatus(
        string $reservationUid,
        ReservationStatus $status,
        ?string $paymentId = null,
        ?string $paymentStatus = null,
        ?string $paymentDetail = null
    ): ?Reservation;

    /**
     * Atomically validates that no active reservation overlaps the requested dates and persists the hold.
     * Prevents race conditions via database-level transaction locks.
     */
    public function holdAtomic(
        Reservation $reservation,
        ?DateTimeImmutable $now = null,
        int $standardHoldMinutes = Reservation::DEFAULT_STANDARD_HOLD_MINUTES,
        int $voucherHoldHours = Reservation::DEFAULT_VOUCHER_HOLD_HOURS
    ): Reservation;

    /**
     * Marks the guest registry as completed for the specified reservation (ADR 0001)
     * and optionally assigns the generated smart lock door code.
     */
    public function markRegistryCompleted(
        string $reservationUid,
        ?DateTimeImmutable $completedAt = null,
        ?string $doorCode = null
    ): ?Reservation;

    /**
     * Finds a reservation by property and exact stay dates.
     */
    public function findByPropertyAndDates(
        string $propertyId,
        string $checkIn,
        string $checkOut
    ): ?Reservation;

    /**
     * Updates the door code (PIN) for a reservation.
     */
    public function updateDoorCode(string $reservationUid, string $doorCode): ?Reservation;

    /**
     * Records a refund ledger entry associated with a reservation UID.
     *
     * @param array<string, mixed> $data
     */
    public function recordRefund(array $data): void;

    /**
     * Retrieves all reservations (including confirmed, cancelled, and pending_payment) whose
     * stay interval [check_in, check_out) overlaps the half-open period [$startDate, $endDate).
     *
     * When $propertyId is 'all' or '', returns reservations across all properties ('1606', '1707').
     *
     * @return list<Reservation>
     */
    public function findOverlappingRange(
        string $startDate,
        string $endDate,
        string $propertyId = 'all'
    ): array;
}
