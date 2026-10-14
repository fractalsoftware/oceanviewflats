<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

/**
 * Intention-revealing parameter DTO encapsulating parameters for creating
 * or confirming direct, manual, and external platform reservations.
 */
final class ReservationDraft
{
    public function __construct(
        public readonly string $propertyId,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly PrimaryGuest $primaryGuest,
        public readonly string $source = 'web',
        public readonly ?float $totalPrice = null,
        public readonly ?DraftPaymentDetails $payment = null,
        public readonly ?string $notes = null,
        public readonly ?string $externalConfirmationCode = null,
        public readonly ?string $channelBlockUid = null,
        public readonly bool $preMarkRegistry = false,
        public readonly ?string $reservationUid = null,
        public readonly ?ActorContext $actor = null,
        public readonly bool $sendConfirmationEmail = true,
        public readonly int $maxGuests = Reservation::DEFAULT_MAX_GUESTS
    ) {
        if ($this->maxGuests < Reservation::MIN_MAX_GUESTS || $this->maxGuests > Reservation::MAX_MAX_GUESTS) {
            throw ReservationValidationException::withViolations([
                sprintf('Maximum occupancy (max_guests) must be between %d and %d.', Reservation::MIN_MAX_GUESTS, Reservation::MAX_MAX_GUESTS),
            ], 'INVALID_MAX_GUESTS');
        }
    }

    /**
     * Intention factory for direct online web checkout reservations.
     */
    public static function direct(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        PrimaryGuest $primaryGuest,
        ?DraftPaymentDetails $payment = null,
        ?float $totalPrice = null,
        ?string $reservationUid = null,
        ?string $notes = null,
        ?ActorContext $actor = null,
        bool $preMarkRegistry = false,
        bool $sendConfirmationEmail = true,
        int $maxGuests = Reservation::DEFAULT_MAX_GUESTS
    ): self {
        return new self(
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            primaryGuest: $primaryGuest,
            source: 'web',
            totalPrice: $totalPrice,
            payment: $payment ?? DraftPaymentDetails::empty(),
            notes: $notes,
            externalConfirmationCode: null,
            channelBlockUid: null,
            preMarkRegistry: $preMarkRegistry,
            reservationUid: $reservationUid,
            actor: $actor ?? ActorContext::guest(),
            sendConfirmationEmail: $sendConfirmationEmail,
            maxGuests: $maxGuests
        );
    }

    /**
     * Intention factory for administrative manual reservation creation.
     */
    public static function manual(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        PrimaryGuest $primaryGuest,
        ?float $totalPrice = null,
        ?string $notes = null,
        bool $preMarkRegistry = false,
        string $source = 'manual_override',
        ?DraftPaymentDetails $payment = null,
        ?string $reservationUid = null,
        ?ActorContext $actor = null,
        bool $sendConfirmationEmail = false,
        int $maxGuests = Reservation::DEFAULT_MAX_GUESTS
    ): self {
        return new self(
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            primaryGuest: $primaryGuest,
            source: $source,
            totalPrice: $totalPrice,
            payment: $payment ?? new DraftPaymentDetails(paymentMethodId: 'manual', paymentStatus: 'approved'),
            notes: $notes,
            externalConfirmationCode: null,
            channelBlockUid: null,
            preMarkRegistry: $preMarkRegistry,
            reservationUid: $reservationUid,
            actor: $actor ?? ActorContext::system(),
            sendConfirmationEmail: $sendConfirmationEmail,
            maxGuests: $maxGuests
        );
    }

    /**
     * Intention factory for onboarding external platform reservations (e.g. Airbnb per ADR 0007).
     */
    public static function external(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        PrimaryGuest $primaryGuest,
        string $externalConfirmationCode,
        ?string $channelBlockUid = null,
        float $hostPayoutCop = 0.0,
        ?string $notes = null,
        bool $preMarkRegistry = false,
        string $source = 'airbnb',
        ?DraftPaymentDetails $payment = null,
        ?string $reservationUid = null,
        ?ActorContext $actor = null,
        bool $sendConfirmationEmail = false,
        ?float $totalPrice = null,
        int $maxGuests = Reservation::DEFAULT_MAX_GUESTS
    ): self {
        $effectivePrice = $totalPrice ?? $hostPayoutCop;
        return new self(
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            primaryGuest: $primaryGuest,
            source: $source,
            totalPrice: $effectivePrice,
            payment: $payment ?? new DraftPaymentDetails(paymentMethodId: 'external_ota', paymentStatus: 'approved'),
            notes: $notes,
            externalConfirmationCode: $externalConfirmationCode,
            channelBlockUid: $channelBlockUid,
            preMarkRegistry: $preMarkRegistry,
            reservationUid: $reservationUid,
            actor: $actor ?? ActorContext::system(),
            sendConfirmationEmail: $sendConfirmationEmail,
            maxGuests: $maxGuests
        );
    }

    public function isDirect(): bool
    {
        return strtolower($this->source) === 'web';
    }

    public function isManual(): bool
    {
        $src = strtolower($this->source);
        return in_array($src, ['manual_override', 'manual', 'bank_transfer', 'cash', 'owner_stay'], true);
    }

    public function isExternal(): bool
    {
        return strtolower($this->source) === 'airbnb';
    }

    public function isAirbnb(): bool
    {
        return strtolower($this->source) === 'airbnb';
    }
}
