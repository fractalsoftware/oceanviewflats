<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Privacy;

use OceanViewFlats\Domain\Reservation\Reservation;

/**
 * Immutable value object representing the outcome of an atomic PII redaction
 * operation on a concluded or cancelled reservation (ADR 0012 / Ley 1581 Art. 11).
 */
final class PiiRedactionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $reservationUid,
        public readonly string $redactedAt,
        public readonly bool $alreadyRedacted = false,
        public readonly int $registriesRedacted = 0,
        public readonly int $clearancesRedacted = 0,
        public readonly int $auditLogsScrubbed = 0,
        public readonly ?int $auditLogId = null,
        public readonly ?Reservation $reservation = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'reservation_uid' => $this->reservationUid,
            'redacted_at' => $this->redactedAt,
            'already_redacted' => $this->alreadyRedacted,
            'registries_redacted' => $this->registriesRedacted,
            'clearances_redacted' => $this->clearancesRedacted,
            'audit_logs_scrubbed' => $this->auditLogsScrubbed,
            'audit_log_id' => $this->auditLogId,
            'reservation' => $this->reservation?->toArray(),
        ];
    }
}
