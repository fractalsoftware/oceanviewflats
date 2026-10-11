<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

/**
 * Authoritative lifecycle and fulfillment service interface orchestrating:
 * 1. Guest registry submissions, validation, ADR 0001 access credential generation, and clearance reports.
 * 2. Administrative manual registry completion, PIN overrides, and PIN regeneration.
 */
interface GuestLifecycleFulfillmentServiceInterface
{
    /**
     * Processes guest registration, derives ADR 0001 door code, updates reservation,
     * logs audit event, and dispatches host clearance report.
     */
    public function submitRegistry(GuestRegistrySubmission $submission): RegistryFulfillmentResult;

    /**
     * Manually marks guest registry as completed for out-of-band guests,
     * assigning or preserving door code and recording administrative audit trail.
     */
    public function completeRegistryManually(
        string $reservationUid,
        ?AdminContext $admin = null,
        ?GuestRegistrySubmission $submission = null
    ): RegistryFulfillmentResult;

    /**
     * Manually overrides the door code (PIN) for a confirmed reservation with keypad formatting.
     */
    public function overrideDoorCode(
        string $reservationUid,
        string $rawDoorCode,
        ?AdminContext $admin = null
    ): DoorCodeResult;

    /**
     * Generates a fresh random 7-digit keypad door code (PIN) for a confirmed reservation.
     */
    public function regenerateDoorCode(
        string $reservationUid,
        ?AdminContext $admin = null
    ): DoorCodeResult;
}
