<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use OceanViewFlats\Domain\Reservation\Reservation;

/**
 * Immutable typed outcome DTO for guest registry submissions.
 */
final class RegistryFulfillmentResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?Reservation $reservation = null,
        public readonly ?string $doorCode = null,
        public readonly ?string $guideUrl = null,
        public readonly bool $hostReportDispatched = false,
        public readonly array $errors = [],
        public readonly bool $accessDispatchDispatched = false
    ) {}

    public static function success(
        Reservation $reservation,
        string $doorCode,
        string $guideUrl,
        bool $hostReportDispatched = false,
        bool $accessDispatchDispatched = false
    ): self {
        return new self(
            success: true,
            reservation: $reservation,
            doorCode: $doorCode,
            guideUrl: $guideUrl,
            hostReportDispatched: $hostReportDispatched,
            errors: [],
            accessDispatchDispatched: $accessDispatchDispatched
        );
    }

    /**
     * @param array<int|string, string> $errors
     */
    public static function validationFailure(array $errors): self
    {
        return new self(
            success: false,
            errors: array_values($errors)
        );
    }

    public static function notFound(string $reservationCode, string $message = 'Reservation not found'): self
    {
        return new self(
            success: false,
            errors: [$message]
        );
    }

    public static function systemError(string $message): self
    {
        return new self(
            success: false,
            errors: [$message]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'reservation_code' => $this->reservation?->reservationUid,
            'door_code' => $this->doorCode,
            'guide_url' => $this->guideUrl,
            'host_report_dispatched' => $this->hostReportDispatched,
            'access_dispatch_dispatched' => $this->accessDispatchDispatched,
            'errors' => $this->errors,
        ];
    }
}
