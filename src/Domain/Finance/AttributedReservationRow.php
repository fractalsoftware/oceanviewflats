<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Finance;

use OceanViewFlats\Domain\Reservation\Reservation;

/**
 * Immutable row representing a reservation's accrual-prorated attribution within a reporting period.
 */
final class AttributedReservationRow
{
    public function __construct(
        public readonly Reservation $reservation,
        public readonly string $reservationUid,
        public readonly string $propertyId,
        public readonly string $guestName,
        public readonly string $source,
        public readonly BookingSourceGroup $channelGroup,
        public readonly string $status,
        public readonly ?string $paymentStatus,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly int $totalNights,
        public readonly int $inPeriodNights,
        public readonly float $prorationFactor,
        public readonly float $totalPrice,
        public readonly float $refundedAmount,
        public readonly float $contractNet,
        public readonly float $proratedCharged,
        public readonly float $proratedRefunded,
        public readonly float $attributedRevenue,
        public readonly bool $isRetention,
        public readonly bool $isUnpricedAirbnb
    ) {
    }

    public static function fromReservation(
        Reservation $reservation,
        BookingSourceGroup $channelGroup,
        int $totalNights,
        int $inPeriodNights,
        float $prorationFactor,
        float $contractNet,
        float $proratedCharged,
        float $proratedRefunded,
        float $attributedRevenue,
        bool $isRetention,
        bool $isUnpricedAirbnb
    ): self {
        return new self(
            reservation: $reservation,
            reservationUid: $reservation->reservationUid,
            propertyId: $reservation->propertyId,
            guestName: $reservation->guestName,
            source: $reservation->source,
            channelGroup: $channelGroup,
            status: $reservation->status->value,
            paymentStatus: $reservation->paymentStatus,
            checkIn: $reservation->checkIn,
            checkOut: $reservation->checkOut,
            totalNights: $totalNights,
            inPeriodNights: $inPeriodNights,
            prorationFactor: $prorationFactor,
            totalPrice: round($reservation->totalPrice, 2),
            refundedAmount: round($reservation->refundedAmount, 2),
            contractNet: round($contractNet, 2),
            proratedCharged: round($proratedCharged, 2),
            proratedRefunded: round($proratedRefunded, 2),
            attributedRevenue: round($attributedRevenue, 2),
            isRetention: $isRetention,
            isUnpricedAirbnb: $isUnpricedAirbnb
        );
    }

    public function prorationPercentage(): int
    {
        return (int) round($this->prorationFactor * 100.0);
    }
}
