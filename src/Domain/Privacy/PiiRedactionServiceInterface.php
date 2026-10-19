<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Privacy;

use DateTimeImmutable;
use OceanViewFlats\Domain\Fulfillment\AdminContext;

/**
 * Contract for financial-safe post-checkout PII redaction and 90-day retention
 * enforcement under Colombian Statutory Law 1581 of 2012 and Decree 1377 of 2013 (ADR 0012).
 */
interface PiiRedactionServiceInterface
{
    public const DEFAULT_RETENTION_DAYS = 90;
    public const REDACTED_GUEST_NAME = 'Redacted Guest';
    public const REDACTED_GUEST_EMAIL = 'redacted@oceanviewflats.local';
    public const REDACTED_GUEST_PHONE = 'REDACTED';

    /**
     * Atomically scrubs personal identification and contact fields across reservations,
     * guest_registries, condominium_clearances, and historical admin_audit_logs for a
     * Concluded or Cancelled reservation while strictly preserving financial, occupancy,
     * and privacy consent proof columns.
     *
     * @throws \OceanViewFlats\Domain\Reservation\ReservationNotFoundException If the reservation does not exist
     * @throws \OceanViewFlats\Domain\Reservation\InvalidReservationStateException If the reservation is active or upcoming
     */
    public function redactReservation(
        string $reservationUid,
        ?AdminContext $admin = null,
        ?DateTimeImmutable $now = null
    ): PiiRedactionResult;

    /**
     * Identifies all unredacted reservations whose check-out date is more than
     * $retentionDays prior to $now in America/Bogota (COT) and executes redactReservation()
     * on each, returning the count of redacted reservations.
     *
     * @throws \InvalidArgumentException If $retentionDays is negative
     */
    public function redactExpiredConcludedReservations(
        int $retentionDays = self::DEFAULT_RETENTION_DAYS,
        ?DateTimeImmutable $now = null
    ): int;
}
