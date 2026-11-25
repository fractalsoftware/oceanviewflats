<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Payment;

use JsonSerializable;

/**
 * Immutable typed Data Transfer Object representing the outcome of a booking payment operation.
 */
final class BookingPaymentResult implements JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    public readonly array $extra;

    /**
     * @param bool $success
     * @param string $message
     * @param string|null $reservationUid
     * @param string|null $status 'confirmed', 'pending_payment', 'rejected', 'error'
     * @param array<string, mixed> $extra
     * @param int $httpStatusCode
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $reservationUid = null,
        public readonly ?string $status = null,
        array $extra = [],
        public readonly int $httpStatusCode = 200
    ) {
        if ($reservationUid !== null && !isset($extra['reservation_code'])) {
            $extra['reservation_code'] = $reservationUid;
        }
        if ($status !== null && !isset($extra['status'])) {
            $extra['status'] = $status;
        }
        $this->extra = $extra;
    }

    /**
     * Factory for successfully confirmed bookings (e.g. instant credit card approval).
     *
     * @param string $reservationUid
     * @param string|array<string, mixed> $message
     * @param array<string, mixed> $extra
     * @param int $httpStatusCode
     * @return self
     */
    public static function confirmed(
        string $reservationUid,
        string|array $message = 'Payment request processed successfully',
        array $extra = [],
        int $httpStatusCode = 200
    ): self {
        if (is_array($message)) {
            $extra = $message;
            $message = 'Payment already processed.';
        }

        return new self(
            success: true,
            message: $message,
            reservationUid: $reservationUid,
            status: 'confirmed',
            extra: $extra,
            httpStatusCode: $httpStatusCode
        );
    }

    /**
     * Factory for pending payment bookings (e.g. cash vouchers, PSE asynchronous clearing).
     *
     * @param string $reservationUid
     * @param string|array<string, mixed> $message
     * @param array<string, mixed> $extra
     * @param int $httpStatusCode
     * @return self
     */
    public static function pendingPayment(
        string $reservationUid,
        string|array $message = 'Payment request processed successfully',
        array $extra = [],
        int $httpStatusCode = 200
    ): self {
        if (is_array($message)) {
            $extra = $message;
            $message = 'Payment already processed.';
        }

        return new self(
            success: true,
            message: $message,
            reservationUid: $reservationUid,
            status: 'pending_payment',
            extra: $extra,
            httpStatusCode: $httpStatusCode
        );
    }

    /**
     * Factory for payments declined or rejected by the gateway.
     *
     * @param string $message
     * @param string|null $statusDetail
     * @param array<string, mixed> $extra
     * @param int $httpStatusCode
     * @return self
     */
    public static function rejected(
        string $message,
        ?string $statusDetail = null,
        array $extra = [],
        int $httpStatusCode = 400
    ): self {
        if ($statusDetail !== null && !isset($extra['status_detail'])) {
            $extra['status_detail'] = $statusDetail;
        }

        return new self(
            success: false,
            message: $message,
            reservationUid: null,
            status: 'rejected',
            extra: $extra,
            httpStatusCode: $httpStatusCode
        );
    }

    /**
     * Factory for calendar or availability conflicts (ADR 0002, ADR 0003).
     *
     * @param string $message
     * @param array<string, mixed> $extra
     * @param int $httpStatusCode
     * @return self
     */
    public static function conflict(
        string $message,
        array $extra = [],
        int $httpStatusCode = 409
    ): self {
        return new self(
            success: false,
            message: $message,
            reservationUid: null,
            status: 'error',
            extra: $extra,
            httpStatusCode: $httpStatusCode
        );
    }

    /**
     * Factory for validation errors, bad requests, or internal exceptions.
     *
     * @param string $message
     * @param int $httpStatusCode
     * @param string|null $status
     * @param array<string, mixed> $extra
     * @return self
     */
    public static function error(
        string $message,
        int $httpStatusCode = 400,
        ?string $status = 'error',
        array $extra = []
    ): self {
        return new self(
            success: false,
            message: $message,
            reservationUid: null,
            status: $status ?? 'error',
            extra: $extra,
            httpStatusCode: $httpStatusCode
        );
    }

    /**
     * Formats outcome into a standard array payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = array_merge([
            'success' => $this->success,
            'message' => $this->message,
        ], $this->extra);

        if ($this->reservationUid !== null) {
            $payload['reservation_code'] = $this->reservationUid;
        }

        if ($this->status !== null) {
            $payload['status'] = $this->status;
        }

        if (!$this->success) {
            $payload['error'] = $this->message;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
