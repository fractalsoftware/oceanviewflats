<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Reservation\Dashboard;

interface OperationalAlertDismissalRepositoryInterface
{
    /**
     * Checks whether a specific deterministic alert_id is currently dismissed.
     */
    public function isDismissed(string $alertId): bool;

    /**
     * Returns the list of dismissed alert_ids, optionally scoped by property_id.
     *
     * @return list<string>
     */
    public function getDismissedAlertIds(string $propertyId = 'all'): array;

    /**
     * Persists a manual dismissal for the given alert_id idempotently.
     */
    public function dismiss(
        string $alertId,
        string $alertType,
        string $propertyId,
        ?string $reservationUid = null,
        ?int $dismissedBy = null
    ): void;

    /**
     * Removes any existing dismissal for the given alert_id.
     */
    public function clearDismissal(string $alertId): void;
}
