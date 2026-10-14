<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

/**
 * Deep Reservation Ledger service.
 * Consolidates availability checking across ephemeral Channel Blocks (ADR 0002)
 * and direct database reservations, enforcing dynamic hold windows (ADR 0003).
 */
final class ReservationLedger implements ReservationLedgerInterface
{
    private readonly MaintenanceBlockSourceInterface $maintenanceBlockSource;

    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly ChannelBlockSourceInterface $channelBlockSource,
        ?MaintenanceBlockSourceInterface $maintenanceBlockSource = null,
        private readonly int $standardHoldMinutes = Reservation::DEFAULT_STANDARD_HOLD_MINUTES,
        private readonly int $voucherHoldHours = Reservation::DEFAULT_VOUCHER_HOLD_HOURS
    ) {
        $this->maintenanceBlockSource = $maintenanceBlockSource ?? new InMemoryMaintenanceBlockSource();
    }

    public static function createDefault(
        ?PDO $pdo = null,
        ?string $cacheDir = null,
        ?MaintenanceBlockSourceInterface $maintenanceBlockSource = null,
        ?ReservationRepositoryInterface $repository = null
    ): self {
        $repo = $repository ?? ($pdo !== null
            ? new PdoReservationRepository($pdo)
            : new InMemoryReservationRepository());

        $channelBlockSource = $cacheDir !== null
            ? new FileCacheChannelBlockSource($cacheDir)
            : FileCacheChannelBlockSource::createDefault();

        $blockSource = $maintenanceBlockSource ?? ($pdo !== null
            ? new PdoMaintenanceBlockRepository($pdo)
            : new InMemoryMaintenanceBlockRepository());

        return new self(
            repository: $repo,
            channelBlockSource: $channelBlockSource,
            maintenanceBlockSource: $blockSource
        );
    }

    public function isAvailable(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        ?string $absorbingSource = null,
        ?string $excludeReservationUid = null
    ): bool {
        return count($this->getConflictReasons($propertyId, $checkIn, $checkOut, $now, $absorbingSource, $excludeReservationUid)) === 0;
    }

    public function getConflictReasons(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        ?string $absorbingSource = null,
        ?string $excludeReservationUid = null
    ): array {
        if ($checkIn >= $checkOut) {
            return [sprintf('Check-out date (%s) must be after check-in date (%s)', $checkOut, $checkIn)];
        }

        $reasons = [];

        // 1. Evaluate ephemeral in-memory Channel Blocks (ADR 0002 & ADR 0007)
        $channelBlocks = $this->channelBlockSource->getBlocks($propertyId);
        foreach ($channelBlocks as $block) {
            if ($absorbingSource !== null && strtolower($block->source) === strtolower($absorbingSource)) {
                continue; // Absorbed matching external platform channel block (ADR 0007)
            }
            if ($block->overlaps($checkIn, $checkOut)) {
                $reasons[] = sprintf(
                    'Dates overlap external %s channel block (%s to %s)',
                    $block->source,
                    $block->startDate,
                    $block->endDate
                );
            }
        }

        // 2. Evaluate authoritative administrative Maintenance Blocks (ADR 0006)
        $maintenanceBlocks = $this->maintenanceBlockSource->getBlocks($propertyId);
        foreach ($maintenanceBlocks as $mBlock) {
            if ($mBlock->overlaps($checkIn, $checkOut)) {
                $reasons[] = sprintf(
                    'Dates overlap maintenance hold (%s: %s to %s)',
                    $mBlock->reason,
                    $mBlock->startDate,
                    $mBlock->endDate
                );
            }
        }

        // 3. Evaluate active direct reservations with dynamic hold windows (ADR 0003)
        $conflictingReservations = $this->repository->findOverlappingActive(
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            now: $now,
            standardHoldMinutes: $this->standardHoldMinutes,
            voucherHoldHours: $this->voucherHoldHours,
            excludeReservationUid: $excludeReservationUid
        );

        foreach ($conflictingReservations as $res) {
            $statusLabel = $res->status->value;
            if ($res->status->isPending()) {
                $statusLabel .= $res->isVoucherHold() ? ' (efecty 72h voucher hold)' : ' (30m standard hold)';
            }
            $reasons[] = sprintf(
                'Dates overlap active direct reservation %s (%s to %s, status: %s)',
                $res->reservationUid,
                $res->checkIn,
                $res->checkOut,
                $statusLabel
            );
        }

        return $reasons;
    }

    public function findChannelConflict(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?string $absorbingSource = null
    ): ?ChannelBlock {
        foreach ($this->channelBlockSource->getBlocks($propertyId) as $block) {
            if ($absorbingSource !== null && strtolower($block->source) === strtolower($absorbingSource)) {
                continue;
            }
            if ($block->overlaps($checkIn, $checkOut)) {
                return $block;
            }
        }
        return null;
    }

    public function getChannelBlocks(string $propertyId): array
    {
        return $this->channelBlockSource->getBlocks($propertyId);
    }

    public function findMaintenanceConflict(
        string $propertyId,
        string $checkIn,
        string $checkOut
    ): ?MaintenanceBlock {
        foreach ($this->maintenanceBlockSource->getBlocks($propertyId) as $block) {
            if ($block->overlaps($checkIn, $checkOut)) {
                return $block;
            }
        }
        return null;
    }

    public function findReservationConflict(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        ?DateTimeImmutable $now = null,
        ?string $excludeReservationUid = null
    ): ?Reservation {
        $conflicts = $this->repository->findOverlappingActive(
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            now: $now,
            standardHoldMinutes: $this->standardHoldMinutes,
            voucherHoldHours: $this->voucherHoldHours,
            excludeReservationUid: $excludeReservationUid
        );
        return $conflicts[0] ?? null;
    }

    public function getBlockedNights(
        string $propertyId,
        ?DateTimeImmutable $now = null,
        ?string $absorbingSource = null
    ): array {
        // Ephemeral channel nights
        if ($absorbingSource !== null) {
            $channelBlocks = $this->channelBlockSource->getBlocks($propertyId);
            $channelNights = [];
            foreach ($channelBlocks as $block) {
                if (strtolower($block->source) === strtolower($absorbingSource)) {
                    continue;
                }
                foreach ($block->nights() as $night) {
                    $channelNights[] = $night;
                }
            }
        } else {
            $channelNights = $this->channelBlockSource->getBlockedNights($propertyId);
        }

        // Maintenance hold nights (ADR 0006)
        $maintenanceNights = $this->maintenanceBlockSource->getBlockedNights($propertyId);

        // Active direct reservation nights
        $activeReservations = $this->repository->findActiveByProperty(
            propertyId: $propertyId,
            now: $now,
            standardHoldMinutes: $this->standardHoldMinutes,
            voucherHoldHours: $this->voucherHoldHours
        );

        $reservationNights = [];
        foreach ($activeReservations as $res) {
            foreach ($res->nights() as $night) {
                $reservationNights[] = $night;
            }
        }

        $allBlocked = array_unique(array_merge($channelNights, $maintenanceNights, $reservationNights));
        sort($allBlocked);

        return $allBlocked;
    }

    public function hold(
        Reservation $reservation,
        ?DateTimeImmutable $now = null
    ): Reservation {
        // 1. Verify date validity
        $in = strtotime($reservation->checkIn);
        $out = strtotime($reservation->checkOut);
        if ($in === false || $out === false || $in >= $out) {
            throw ReservationConflictException::forDates(
                propertyId: $reservation->propertyId,
                checkIn: $reservation->checkIn,
                checkOut: $reservation->checkOut,
                conflictReason: 'Check-out date must be after check-in date'
            );
        }

        // 2. Ephemeral channel blocks checked in-memory (ADR 0002 & ADR 0007)
        // External OTA reservations absorb matching channel blocks (ADR 0007)
        $absorbingSource = $reservation->isExternal() ? $reservation->source : null;
        $channelConflict = $this->findChannelConflict(
            $reservation->propertyId,
            $reservation->checkIn,
            $reservation->checkOut,
            $absorbingSource
        );
        if ($channelConflict !== null) {
            throw ReservationConflictException::forDates(
                propertyId: $reservation->propertyId,
                checkIn: $reservation->checkIn,
                checkOut: $reservation->checkOut,
                conflictReason: sprintf(
                    'Dates overlap external %s channel block (%s to %s)',
                    $channelConflict->source,
                    $channelConflict->startDate,
                    $channelConflict->endDate
                )
            );
        }

        // 3. Maintenance blocks checked in domain (ADR 0006)
        $maintenanceConflict = $this->findMaintenanceConflict(
            $reservation->propertyId,
            $reservation->checkIn,
            $reservation->checkOut
        );
        if ($maintenanceConflict !== null) {
            throw ReservationConflictException::forDates(
                propertyId: $reservation->propertyId,
                checkIn: $reservation->checkIn,
                checkOut: $reservation->checkOut,
                conflictReason: sprintf(
                    'Dates overlap maintenance hold (%s: %s to %s)',
                    $maintenanceConflict->reason,
                    $maintenanceConflict->startDate,
                    $maintenanceConflict->endDate
                )
            );
        }

        // 4. Atomically check overlapping reservations and hold in repository
        // Never writes channel blocks to the database (ADR 0002), prevents race conditions
        return $this->repository->holdAtomic(
            reservation: $reservation,
            now: $now,
            standardHoldMinutes: $this->standardHoldMinutes,
            voucherHoldHours: $this->voucherHoldHours
        );
    }

    public function confirm(
        string $reservationUid,
        string $paymentId,
        ?string $paymentStatus = null,
        ?string $paymentDetail = null
    ): Reservation {
        $updated = $this->repository->updateStatus(
            reservationUid: $reservationUid,
            status: ReservationStatus::CONFIRMED,
            paymentId: $paymentId,
            paymentStatus: $paymentStatus,
            paymentDetail: $paymentDetail
        );

        if ($updated === null) {
            throw new InvalidArgumentException(sprintf('Reservation not found with UID: %s', $reservationUid));
        }

        return $updated;
    }

    /**
     * @deprecated Use ReservationLifecycleEngineInterface::cancel() for complete lifecycle transitions, including refund processing, audit logging, and post-commit events (ADR 0011).
     */
    public function cancel(
        string $reservationUid,
        string $reason = ''
    ): Reservation {
        $updated = $this->repository->updateStatus(
            reservationUid: $reservationUid,
            status: ReservationStatus::CANCELLED,
            paymentDetail: $reason !== '' ? sprintf('Cancelled: %s', $reason) : null
        );

        if ($updated === null) {
            throw new InvalidArgumentException(sprintf('Reservation not found with UID: %s', $reservationUid));
        }

        return $updated;
    }

    public function getReservation(string $reservationUid): ?Reservation
    {
        return $this->repository->findByUid($reservationUid);
    }
}
