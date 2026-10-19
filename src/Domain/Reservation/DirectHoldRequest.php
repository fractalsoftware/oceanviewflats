<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateTimeImmutable;

/**
 * Request DTO to place a temporary concurrency-safe hold for direct web checkout.
 */
final class DirectHoldRequest
{
    public function __construct(
        public readonly string $propertyId,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly PrimaryGuest $primaryGuest,
        public readonly ?string $paymentMethodId = null,
        public readonly ?int $customHoldMinutes = null,
        public readonly ?string $reservationUid = null,
        public readonly ?ActorContext $actor = null,
        public readonly ?string $notes = null,
        public readonly ?DraftPaymentDetails $payment = null,
        public readonly ?string $privacyPolicyVersion = null,
        public readonly ?DateTimeImmutable $privacyConsentAt = null,
        public readonly ?string $privacyConsentIp = null
    ) {
    }

    public static function create(
        string $propertyId,
        string $checkIn,
        string $checkOut,
        PrimaryGuest $primaryGuest,
        ?string $paymentMethodId = null,
        ?int $customHoldMinutes = null,
        ?string $reservationUid = null,
        ?ActorContext $actor = null,
        ?string $notes = null,
        ?DraftPaymentDetails $payment = null,
        ?string $privacyPolicyVersion = null,
        ?DateTimeImmutable $privacyConsentAt = null,
        ?string $privacyConsentIp = null
    ): self {
        return new self(
            $propertyId,
            $checkIn,
            $checkOut,
            $primaryGuest,
            $paymentMethodId,
            $customHoldMinutes,
            $reservationUid,
            $actor,
            $notes,
            $payment,
            $privacyPolicyVersion,
            $privacyConsentAt,
            $privacyConsentIp
        );
    }
}
