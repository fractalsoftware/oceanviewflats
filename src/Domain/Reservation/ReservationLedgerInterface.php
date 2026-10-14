<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;

/**
 * Authoritative Reservation Ledger domain service.
 * Enforces calendar date conflict detection, ephemeral channel blocks (ADR 0002),
 * and dynamic hold windows (ADR 0003).
 */
interface ReservationLedgerInterface
{
    /**
     * Verifies whether the requested property is available for the given dates.
     * Optionally absorbs channel blocks matching $absorbingSource (e.g. 'airbnb' per ADR 0007).
     * Optionally excludes $excludeReservationUid from self-conflict.
     */
    public function isAvailable(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        ?string $absorbingSource = null,
        ?string $excludeReservationUid = null
    ): bool;

    /**
     * Returns a list of conflict reasons if the requested dates are blocked, or empty if available.
     * Optionally absorbs channel blocks matching $absorbingSource (e.g. 'airbnb' per ADR 0007).
     * Optionally excludes $excludeReservationUid from self-conflict.
     *
     * @return list<string>
     */
    public function getConflictReasons(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        ?string $absorbingSource = null,
        ?string $excludeReservationUid = null
    ): array;

    /**
     * Finds the first overlapping ephemeral channel block, or null if none.
     * Channel blocks originating from $absorbingSource are absorbed/ignored (ADR 0007).
     */
    public function findChannelConflict(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?string $absorbingSource = null
    ): ?ChannelBlock;

    /**
     * Retrieves all active ephemeral channel blocks for a property.
     *
     * @return list<ChannelBlock>
     */
    public function getChannelBlocks(string $propertyId): array;

    /**
     * Finds the first overlapping maintenance block, or null if none.
     */
    public function findMaintenanceConflict(
        string $propertyId,
        string $checkIn,
        string $checkOut
    ): ?MaintenanceBlock;

    /**
     * Finds the first overlapping active direct reservation, or null if none.
     */
    public function findReservationConflict(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        ?string $excludeReservationUid = null
    ): ?Reservation;

    /**
     * Returns all blocked night dates (YYYY-MM-DD) for a property, merging ephemeral channel blocks
     * and active direct reservations.
     *
     * @return list<string>
     */
    public function getBlockedNights(
        string $propertyId,
        ?DateTimeImmutable $now = null,
        ?string $absorbingSource = null
    ): array;

    /**
     * Creates and records a Pending Reservation hold on the calendar.
     * Throws ReservationConflictException if the dates are occupied by channel blocks or active holds.
     */
    public function hold(
        Reservation $reservation,
        ?DateTimeImmutable $now = null
    ): Reservation;

    /**
     * Transitions a pending reservation to CONFIRMED status upon verified settlement.
     */
    public function confirm(
        string $reservationUid,
        string $paymentId,
        ?string $paymentStatus = null,
        ?string $paymentDetail = null
    ): Reservation;

    /**
     * Transitions a reservation to CANCELLED status, releasing calendar holds.
     *
     * @deprecated Use ReservationLifecycleEngineInterface::cancel() for complete lifecycle transitions, including refund processing, audit logging, and post-commit events (ADR 0011).
     */
    public function cancel(
        string $reservationUid,
        string $reason = ''
    ): Reservation;

    /**
     * Retrieves a reservation by business UID.
     */
    public function getReservation(string $reservationUid): ?Reservation;
}
