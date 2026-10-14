<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Direct Reservation domain entity representing a guest stay contract.
 * Enforces dynamic hold windows based on payment settlement type per ADR 0003.
 */
final class Reservation
{
    public const DEFAULT_STANDARD_HOLD_MINUTES = 30;
    public const DEFAULT_VOUCHER_HOLD_HOURS = 72;
    public const DEFAULT_MAX_GUESTS = 6;
    public const MIN_MAX_GUESTS = 1;
    public const MAX_MAX_GUESTS = 12;

    /**
     * @internal Hydrated exclusively by ReservationPersistencePort and repositories. For new reservation creation, use ReservationDraft and ReservationLifecycleEngineInterface.
     */
    public function __construct(
        public readonly string $reservationUid,
        public readonly string $propertyId,
        public readonly string $guestName,
        public readonly string $guestEmail,
        public readonly string $guestPhone,
        public readonly string $checkIn,  // YYYY-MM-DD
        public readonly string $checkOut, // YYYY-MM-DD
        public readonly float $totalPrice,
        public readonly ReservationStatus $status = ReservationStatus::PENDING_PAYMENT,
        public readonly ?string $paymentMethodId = null,
        public readonly ?int $id = null,
        public readonly ?string $mercadopagoPreferenceId = null,
        public readonly ?string $mercadopagoPaymentId = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?string $paymentDetail = null,
        public readonly string $lang = 'en',
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
        public readonly bool $registryCompleted = false,
        public readonly ?DateTimeImmutable $registryCompletedAt = null,
        public readonly ?string $doorCode = null,
        public readonly string $source = 'web',
        public readonly ?string $notes = null,
        public readonly float $refundedAmount = 0.0,
        public readonly ?string $externalConfirmationCode = null,
        public readonly ?string $channelBlockUid = null,
        public readonly int $maxGuests = self::DEFAULT_MAX_GUESTS
    ) {
        if ($this->reservationUid === '') {
            throw new InvalidArgumentException('Reservation UID cannot be empty');
        }
        if ($this->propertyId === '') {
            throw new InvalidArgumentException('Property ID cannot be empty');
        }
        if ($this->checkIn >= $this->checkOut) {
            throw new InvalidArgumentException(
                sprintf('Check-out date (%s) must be after check-in date (%s)', $this->checkOut, $this->checkIn)
            );
        }
        if ($this->maxGuests < self::MIN_MAX_GUESTS || $this->maxGuests > self::MAX_MAX_GUESTS) {
            throw new InvalidArgumentException(
                sprintf('Reservation max_guests must be between %d and %d (got %d)', self::MIN_MAX_GUESTS, self::MAX_MAX_GUESTS, $this->maxGuests)
            );
        }
    }

    /**
     * Factory method to create a new reservation instance with standard defaults.
     */
    public static function create(
        string $reservationUid,
        string $propertyId,
        string $guestName,
        string $guestEmail,
        string $guestPhone,
        string $checkIn,
        string $checkOut,
        float $totalPrice,
        ReservationStatus $status = ReservationStatus::PENDING_PAYMENT,
        ?string $paymentMethodId = null,
        ?int $id = null,
        ?string $mercadopagoPreferenceId = null,
        ?string $mercadopagoPaymentId = null,
        ?string $paymentStatus = null,
        ?string $paymentDetail = null,
        string $lang = 'en',
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        bool $registryCompleted = false,
        ?DateTimeImmutable $registryCompletedAt = null,
        ?string $doorCode = null,
        string $source = 'web',
        ?string $notes = null,
        float $refundedAmount = 0.0,
        ?string $externalConfirmationCode = null,
        ?string $channelBlockUid = null,
        int $maxGuests = self::DEFAULT_MAX_GUESTS
    ): self {
        return new self(
            reservationUid: $reservationUid,
            propertyId: $propertyId,
            guestName: $guestName,
            guestEmail: $guestEmail,
            guestPhone: $guestPhone,
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalPrice: $totalPrice,
            status: $status,
            paymentMethodId: $paymentMethodId,
            id: $id,
            mercadopagoPreferenceId: $mercadopagoPreferenceId,
            mercadopagoPaymentId: $mercadopagoPaymentId,
            paymentStatus: $paymentStatus,
            paymentDetail: $paymentDetail,
            lang: $lang,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            registryCompleted: $registryCompleted,
            registryCompletedAt: $registryCompletedAt,
            doorCode: $doorCode,
            source: $source,
            notes: $notes,
            refundedAmount: $refundedAmount,
            externalConfirmationCode: $externalConfirmationCode,
            channelBlockUid: $channelBlockUid,
            maxGuests: $maxGuests
        );
    }

    /**
     * Factory method to hydrate a reservation from an associative array.
     * Supports both snake_case and camelCase keys.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusRaw = $data['status'] ?? ReservationStatus::PENDING_PAYMENT;
        if ($statusRaw instanceof ReservationStatus) {
            $status = $statusRaw;
        } elseif (is_string($statusRaw)) {
            $status = ReservationStatus::tryFrom($statusRaw) ?? ReservationStatus::PENDING_PAYMENT;
        } else {
            $status = ReservationStatus::PENDING_PAYMENT;
        }

        $createdAtRaw = $data['created_at'] ?? $data['createdAt'] ?? null;
        $createdAt = null;
        if ($createdAtRaw instanceof DateTimeImmutable) {
            $createdAt = $createdAtRaw;
        } elseif (is_string($createdAtRaw) && $createdAtRaw !== '') {
            $createdAt = new DateTimeImmutable($createdAtRaw);
        }

        $updatedAtRaw = $data['updated_at'] ?? $data['updatedAt'] ?? null;
        $updatedAt = null;
        if ($updatedAtRaw instanceof DateTimeImmutable) {
            $updatedAt = $updatedAtRaw;
        } elseif (is_string($updatedAtRaw) && $updatedAtRaw !== '') {
            $updatedAt = new DateTimeImmutable($updatedAtRaw);
        }

        $registryCompletedAtRaw = $data['registry_completed_at'] ?? $data['registryCompletedAt'] ?? null;
        $registryCompletedAt = null;
        if ($registryCompletedAtRaw instanceof DateTimeImmutable) {
            $registryCompletedAt = $registryCompletedAtRaw;
        } elseif (is_string($registryCompletedAtRaw) && $registryCompletedAtRaw !== '') {
            $registryCompletedAt = new DateTimeImmutable($registryCompletedAtRaw);
        }

        $regCompleted = $data['registry_completed'] ?? $data['registryCompleted'] ?? false;
        $maxGuestsRaw = $data['max_guests'] ?? $data['maxGuests'] ?? null;
        $maxGuests = ($maxGuestsRaw !== null && $maxGuestsRaw !== '')
            ? (int) $maxGuestsRaw
            : self::DEFAULT_MAX_GUESTS;

        return new self(
            reservationUid: (string) ($data['reservation_uid'] ?? $data['reservationUid'] ?? ''),
            propertyId: (string) ($data['property_id'] ?? $data['propertyId'] ?? ''),
            guestName: (string) ($data['guest_name'] ?? $data['guestName'] ?? ''),
            guestEmail: (string) ($data['guest_email'] ?? $data['guestEmail'] ?? ''),
            guestPhone: (string) ($data['guest_phone'] ?? $data['guestPhone'] ?? ''),
            checkIn: (string) ($data['check_in'] ?? $data['checkIn'] ?? ''),
            checkOut: (string) ($data['check_out'] ?? $data['checkOut'] ?? ''),
            totalPrice: (float) ($data['total_price'] ?? $data['totalPrice'] ?? 0.0),
            status: $status,
            paymentMethodId: isset($data['payment_method_id']) || isset($data['paymentMethodId'])
                ? (string) ($data['payment_method_id'] ?? $data['paymentMethodId'])
                : null,
            id: isset($data['id']) ? (int) $data['id'] : null,
            mercadopagoPreferenceId: isset($data['mercadopago_preference_id']) || isset($data['mercadopagoPreferenceId'])
                ? (string) ($data['mercadopago_preference_id'] ?? $data['mercadopagoPreferenceId'])
                : null,
            mercadopagoPaymentId: isset($data['mercadopago_payment_id']) || isset($data['mercadopagoPaymentId'])
                ? (string) ($data['mercadopago_payment_id'] ?? $data['mercadopagoPaymentId'])
                : null,
            paymentStatus: isset($data['payment_status']) || isset($data['paymentStatus'])
                ? (string) ($data['payment_status'] ?? $data['paymentStatus'])
                : null,
            paymentDetail: isset($data['payment_detail']) || isset($data['paymentDetail'])
                ? (string) ($data['payment_detail'] ?? $data['paymentDetail'])
                : null,
            lang: (string) ($data['lang'] ?? 'en'),
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            registryCompleted: (bool) $regCompleted,
            registryCompletedAt: $registryCompletedAt,
            doorCode: isset($data['door_code']) || isset($data['doorCode'])
                ? (string) ($data['door_code'] ?? $data['doorCode'])
                : null,
            source: (string) ($data['source'] ?? 'web'),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            refundedAmount: (float) ($data['refunded_amount'] ?? $data['refundedAmount'] ?? 0.0),
            externalConfirmationCode: isset($data['external_confirmation_code']) || isset($data['externalConfirmationCode'])
                ? (string) ($data['external_confirmation_code'] ?? $data['externalConfirmationCode'])
                : null,
            channelBlockUid: isset($data['channel_block_uid']) || isset($data['channelBlockUid'])
                ? (string) ($data['channel_block_uid'] ?? $data['channelBlockUid'])
                : null,
            maxGuests: $maxGuests
        );
    }

    /**
     * Determines whether this reservation is actively holding the dates on the calendar.
     *
     * - Confirmed reservations hold dates indefinitely.
     * - Cancelled reservations never hold dates.
     * - Pending reservations hold dates according to their payment settlement rail:
     *     - Efecty Cash Vouchers: 72 hours (ADR 0003)
     *     - Cards / PSE / Instant: 30 minutes (CONTEXT.md)
     */
    public function isHolding(
        ?DateTimeImmutable $now = null,
        int $standardHoldMinutes = self::DEFAULT_STANDARD_HOLD_MINUTES,
        int $voucherHoldHours = self::DEFAULT_VOUCHER_HOLD_HOURS
    ): bool {
        if ($this->status->isCancelled()) {
            return false;
        }

        if ($this->status->isConfirmed()) {
            return true;
        }

        // Must be PENDING_PAYMENT
        $currentTime = $now ?? new DateTimeImmutable();
        $createdTime = $this->createdAt ?? $currentTime;

        if ($this->isVoucherHold()) {
            $expirySeconds = $voucherHoldHours * 3600;
        } else {
            $expirySeconds = $standardHoldMinutes * 60;
        }

        $ageSeconds = $currentTime->getTimestamp() - $createdTime->getTimestamp();
        return $ageSeconds < $expirySeconds;
    }

    /**
     * Identifies if this reservation qualifies for the extended offline cash voucher window (ADR 0003).
     */
    public function isVoucherHold(): bool
    {
        return $this->paymentMethodId !== null && strtolower($this->paymentMethodId) === 'efecty';
    }

    /**
     * Identifies if this reservation has concluded past 23:59:59 COT on its check-out date (ADR 0009).
     * Strictly verifies that the reservation is confirmed. Cancelled or pending bookings with past
     * checkout dates return false (they remain cancelled or pending).
     *
     * @param DateTimeImmutable|null $now Optional reference time for testing or deterministic evaluation.
     */
    public function isConcluded(?DateTimeImmutable $now = null): bool
    {
        if ($this->status !== ReservationStatus::CONFIRMED) {
            return false;
        }

        $timezone = new DateTimeZone('America/Bogota');
        $referenceTime = $now ?? new DateTimeImmutable('now', $timezone);
        $concludedThreshold = new DateTimeImmutable($this->checkOut . ' 23:59:59', $timezone);

        return $referenceTime > $concludedThreshold;
    }

    /**
     * Identifies if this reservation originated from an external OTA platform (ADR 0007).
     */
    public function isExternal(): bool
    {
        return strtolower($this->source) === 'airbnb';
    }

    /**
     * Identifies if this reservation specifically originated from Airbnb.
     */
    public function isAirbnb(): bool
    {
        return strtolower($this->source) === 'airbnb';
    }

    /**
     * Determines whether the reservation contains an external or placeholder email address
     * eligible for enrichment upon verified Guest Registry submission.
     */
    public function hasExternalOrPlaceholderEmail(): bool
    {
        $guestEmailLower = strtolower(trim($this->guestEmail));

        return $this->source === 'airbnb'
            || str_starts_with($this->reservationUid, 'res-abnb-')
            || str_contains($guestEmailLower, 'airbnb.com')
            || str_contains($guestEmailLower, 'airbnb-guest.local')
            || str_contains($guestEmailLower, '@guest.oceanviewflats.com')
            || str_ends_with($guestEmailLower, '.local')
            || $guestEmailLower === ''
            || str_starts_with($guestEmailLower, 'guest@')
            || str_starts_with($guestEmailLower, 'none@');
    }

    /**
     * Evaluates if the stay dates overlap with a given check-in / check-out interval.
     */
    public function overlaps(string $checkIn, string $checkOut): bool
    {
        return ($this->checkIn < $checkOut) && ($this->checkOut > $checkIn);
    }

    /**
     * Returns an array of ISO 8601 date strings for each night occupied by this reservation.
     *
     * @return list<string>
     */
    public function nights(): array
    {
        $start = new DateTimeImmutable($this->checkIn);
        $end = new DateTimeImmutable($this->checkOut);
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);

        $nights = [];
        foreach ($period as $date) {
            $nights[] = $date->format('Y-m-d');
        }

        return $nights;
    }

    /**
     * Creates an updated clone with a new status and optional payment information.
     */
    public function withStatus(
        ReservationStatus $status,
        ?string $paymentId = null,
        ?string $paymentStatus = null,
        ?string $paymentDetail = null,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $paymentId ?? $this->mercadopagoPaymentId,
            paymentStatus: $paymentStatus ?? $this->paymentStatus,
            paymentDetail: $paymentDetail ?? $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            registryCompleted: $this->registryCompleted,
            registryCompletedAt: $this->registryCompletedAt,
            doorCode: $this->doorCode,
            source: $this->source,
            notes: $this->notes,
            refundedAmount: $this->refundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $this->maxGuests
        );
    }

    /**
     * Creates an updated clone marking the guest registry as completed (ADR 0001)
     * and optionally binding the generated 7-digit smart lock door code.
     */
    public function withRegistryCompleted(?DateTimeImmutable $completedAt = null, ?string $doorCode = null): self
    {
        $timestamp = $completedAt ?? new DateTimeImmutable();
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $this->status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $this->mercadopagoPaymentId,
            paymentStatus: $this->paymentStatus,
            paymentDetail: $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            registryCompleted: true,
            registryCompletedAt: $timestamp,
            doorCode: $doorCode ?? $this->doorCode,
            source: $this->source,
            notes: $this->notes,
            refundedAmount: $this->refundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $this->maxGuests
        );
    }

    /**
     * Creates an updated clone with a specified smart lock door code.
     */
    public function withDoorCode(?string $doorCode): self
    {
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $this->status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $this->mercadopagoPaymentId,
            paymentStatus: $this->paymentStatus,
            paymentDetail: $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            registryCompleted: $this->registryCompleted,
            registryCompletedAt: $this->registryCompletedAt,
            doorCode: $doorCode,
            source: $this->source,
            notes: $this->notes,
            refundedAmount: $this->refundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $this->maxGuests
        );
    }

    /**
     * Creates an updated clone with a specified guest email.
     */
    public function withGuestEmail(string $guestEmail): self
    {
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $this->status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $this->mercadopagoPaymentId,
            paymentStatus: $this->paymentStatus,
            paymentDetail: $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            registryCompleted: $this->registryCompleted,
            registryCompletedAt: $this->registryCompletedAt,
            doorCode: $this->doorCode,
            source: $this->source,
            notes: $this->notes,
            refundedAmount: $this->refundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $this->maxGuests
        );
    }

    /**
     * Creates an updated clone recording an additional refund amount and optional note/status.
     */
    public function withRefund(
        float $additionalRefundAmount,
        ?string $notes = null,
        ?ReservationStatus $status = null,
        ?string $paymentStatus = null,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        $newRefundedAmount = round($this->refundedAmount + max(0.0, $additionalRefundAmount), 2);
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $status ?? $this->status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $this->mercadopagoPaymentId,
            paymentStatus: $paymentStatus ?? $this->paymentStatus,
            paymentDetail: $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            registryCompleted: $this->registryCompleted,
            registryCompletedAt: $this->registryCompletedAt,
            doorCode: $this->doorCode,
            source: $this->source,
            notes: $notes ?? $this->notes,
            refundedAmount: $newRefundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $this->maxGuests
        );
    }

    /**
     * Creates an updated clone with updated notes.
     */
    public function withNotes(?string $notes): self
    {
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $this->status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $this->mercadopagoPaymentId,
            paymentStatus: $this->paymentStatus,
            paymentDetail: $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            registryCompleted: $this->registryCompleted,
            registryCompletedAt: $this->registryCompletedAt,
            doorCode: $this->doorCode,
            source: $this->source,
            notes: $notes,
            refundedAmount: $this->refundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $this->maxGuests
        );
    }

    /**
     * Creates an updated clone with a specified maximum guest occupancy override.
     */
    public function withMaxGuests(int $maxGuests): self
    {
        return new self(
            reservationUid: $this->reservationUid,
            propertyId: $this->propertyId,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            guestPhone: $this->guestPhone,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            totalPrice: $this->totalPrice,
            status: $this->status,
            paymentMethodId: $this->paymentMethodId,
            id: $this->id,
            mercadopagoPreferenceId: $this->mercadopagoPreferenceId,
            mercadopagoPaymentId: $this->mercadopagoPaymentId,
            paymentStatus: $this->paymentStatus,
            paymentDetail: $this->paymentDetail,
            lang: $this->lang,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            registryCompleted: $this->registryCompleted,
            registryCompletedAt: $this->registryCompletedAt,
            doorCode: $this->doorCode,
            source: $this->source,
            notes: $this->notes,
            refundedAmount: $this->refundedAmount,
            externalConfirmationCode: $this->externalConfirmationCode,
            channelBlockUid: $this->channelBlockUid,
            maxGuests: $maxGuests
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reservation_uid' => $this->reservationUid,
            'property_id' => $this->propertyId,
            'guest_name' => $this->guestName,
            'guest_email' => $this->guestEmail,
            'guest_phone' => $this->guestPhone,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'total_price' => $this->totalPrice,
            'refunded_amount' => $this->refundedAmount,
            'source' => $this->source,
            'status' => $this->status->value,
            'payment_method_id' => $this->paymentMethodId,
            'mercadopago_preference_id' => $this->mercadopagoPreferenceId,
            'mercadopago_payment_id' => $this->mercadopagoPaymentId,
            'payment_status' => $this->paymentStatus,
            'payment_detail' => $this->paymentDetail,
            'lang' => $this->lang,
            'registry_completed' => $this->registryCompleted,
            'registry_completed_at' => $this->registryCompletedAt?->format('Y-m-d H:i:s'),
            'door_code' => $this->doorCode,
            'notes' => $this->notes,
            'external_confirmation_code' => $this->externalConfirmationCode,
            'channel_block_uid' => $this->channelBlockUid,
            'max_guests' => $this->maxGuests,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
