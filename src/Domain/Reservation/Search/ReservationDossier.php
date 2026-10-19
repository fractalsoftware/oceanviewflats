<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Search;

/**
 * Immutable aggregated dossier for reservation slide-over inspection drawers,
 * containing reservation record, guest registry details, audit trail, and refunds.
 */
final class ReservationDossier
{
    public readonly ?string $piiRedactedAt;

    /**
     * @param array<string, mixed> $reservation
     * @param array<string, mixed>|null $guestRegistry
     * @param list<array<string, mixed>> $auditLogs
     * @param list<array<string, mixed>> $refunds
     */
    public function __construct(
        public readonly array $reservation,
        public readonly ?array $guestRegistry = null,
        public readonly array $auditLogs = [],
        public readonly array $refunds = [],
        ?string $piiRedactedAt = null
    ) {
        $resolvedRedactedAt = $piiRedactedAt
            ?? (isset($reservation['pii_redacted_at']) && (string) $reservation['pii_redacted_at'] !== ''
                ? (string) $reservation['pii_redacted_at']
                : (isset($reservation['piiRedactedAt']) && (string) $reservation['piiRedactedAt'] !== ''
                    ? (string) $reservation['piiRedactedAt']
                    : null));

        $this->piiRedactedAt = $resolvedRedactedAt;
    }

    public function isPiiRedacted(): bool
    {
        return $this->piiRedactedAt !== null
            || ((string) ($this->reservation['guest_name'] ?? '')) === 'Redacted Guest';
    }

    /**
     * @return array{
     *     reservation: array<string, mixed>,
     *     guest_registry: array<string, mixed>|null,
     *     guestRegistry: array<string, mixed>|null,
     *     audit_logs: list<array<string, mixed>>,
     *     auditLogs: list<array<string, mixed>>,
     *     refunds: list<array<string, mixed>>,
     *     pii_redacted_at: string|null,
     *     piiRedactedAt: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'reservation' => $this->reservation,
            'guest_registry' => $this->guestRegistry,
            'guestRegistry' => $this->guestRegistry,
            'audit_logs' => $this->auditLogs,
            'auditLogs' => $this->auditLogs,
            'refunds' => $this->refunds,
            'pii_redacted_at' => $this->piiRedactedAt,
            'piiRedactedAt' => $this->piiRedactedAt,
        ];
    }
}
