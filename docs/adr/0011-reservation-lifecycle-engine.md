# Unified Reservation Lifecycle Engine and Hexagonal Port Architecture

## Context

In OceanViewFlats, reservation creation, payment capture, administrative holds, cancellations, refunds, and lifecycle state changes were historically scattered across procedural scripts and shallow services:
1. **Procedural Inception**: Direct online checkout (`public/api/book-request.php` and `BookingPaymentProcessor.php`) manually computed rates, checked calendar availability, handled payment gateways, and directly instantiated `Reservation` entities requiring 26 constructor arguments.
2. **Shallow Ledger and Controller Bloat**: `ReservationLedger::cancel()` implemented a shallow one-line SQL status update. Consequently, `ReservationController::cancel()` bloated to over 230 lines coordinating refundable balance calculations, external Mercado Pago gateway refund dispatches, database transactions, note string manipulations, audit log entries, and email delivery.
3. **Network Latency & Lock Contention Hazards**: In previous procedural cancellation flows, external payment gateway HTTP calls were risked inside database transactions, holding table row locks during remote gateway latency or timeouts and risking connection pool starvation.
4. **Resurrection Hazards**: Late, out-of-order asynchronous payment webhooks or IPN callbacks risked resurrecting or altering reservations that had already been cancelled or concluded under [ADR 0009](0009-concluded-reservation-credential-and-metadata-redaction.md).

We need an authoritative, deep domain engine that encapsulates all reservation lifecycle invariants, decouples business rules from third-party payment rails and presentation layers, and enables 100% in-memory unit testing.

---

## Decision

We establish an authoritative, deep **Reservation Lifecycle Engine** (`ReservationLifecycleEngineInterface`) backed by a pure **Hexagonal Ports & Adapters** architecture.

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                   ReservationLifecycleEngine (Public Seam)                     │
│                                                                                │
│  holdDirect()     confirmOrRecord()     cancel()     previewCancellation()     │
└──────────────────────────────────────┬─────────────────────────────────────────┘
                                       │ Coordinates:
     ┌─────────────────────────────────┼────────────────────────────────┐
     ▼                                 ▼                                ▼
[Authoritative Quote]        [PaymentRefundPort]             [ReservationPersistencePort]
- Dynamic night rates        - Mercado Pago client           - Atomic hold locks (FOR UPDATE)
- Mandatory Cleaning Fee     - Deterministic idempotency     - State machine validation
- Seasonal minimum stay      - Pre-DB commit dispatch        - Terminal status defense
     │                                 │                                │
     ▼                                 ▼                                ▼
[Channel Absorption]        [AuditPort]                     [LifecycleEventPublisherPort]
- Ephemeral iCal checks      - Immutable admin audit logs    - Guest confirmation emails
- ADR 0007 Airbnb absorption - Attribution tracking          - Cancellation notifications
```

### 1. Unified Domain Interface (`ReservationLifecycleEngineInterface`)

The public interface replaces scattered procedural orchestration with four cohesive entry points:

```php
namespace OceanViewFlats\Domain\Reservation;

interface ReservationLifecycleEngineInterface
{
    /**
     * Places a temporary concurrency-safe hold for direct web checkout.
     */
    public function holdDirect(DirectHoldRequest $request): DirectHoldResult;

    /**
     * Inception seam: confirms direct checkout or records manual/external reservations.
     * Enforces calendar availability, seasonal minimum stay, channel block absorption,
     * authoritative Quote calculation (COP), and access credential allocation.
     */
    public function confirmOrRecord(ReservationDraft $draft): ReservationResult;

    /**
     * Side-effect-free dry-run calculating refundable balance, elapsed days, and
     * suggested policy retention for administrative cancellation interfaces.
     */
    public function previewCancellation(string $reservationUid): CancellationPreview;

    /**
     * Voids an active reservation, coordinates pre-transaction gateway refunds,
     * updates database state, records audit log, and delivers cancellation notices.
     */
    public function cancel(string $reservationUid, CancellationRequest $request): CancellationResult;
}
```

### 2. Hexagonal Infrastructure Ports

The engine owns all business logic and orchestration, interacting with infrastructure strictly through four explicit ports:

1. **`ReservationPersistencePort`** (`Local-substitutable`):
   - Handles atomic concurrency holds (`holdAtomic`), state retrieval, and atomic database transaction execution (`executeInTransaction`).
   - Production adapter: `PdoReservationPersistenceAdapter`.
   - Test adapter: `InMemoryReservationPersistenceAdapter`.
2. **`PaymentRefundPort`** (`True external`):
   - Dispatches monetary refunds to the external payment gateway (`issueRefund(string $paymentId, float $amountCop, string $idempotencyKey): RefundReceipt`).
   - Production adapter: `MercadoPagoPaymentRefundAdapter`.
   - Test adapter: `InMemoryPaymentRefundAdapter` (supports simulated approvals, declines, network timeouts).
3. **`LifecycleEventPublisherPort`** (`Remote but owned`):
   - Publishes domain lifecycle events post-commit (`ReservationConfirmedEvent`, `ReservationCancelledEvent`).
   - Production adapter: `QueuedEventPublisherAdapter` / `TransactionalEmailPublisherAdapter`.
   - Test adapter: `InMemoryEventPublisherAdapter`.
4. **`AuditPort`** (`Local-substitutable`):
   - Records immutable administrative audit entries capturing actor identity, IP, user-agent, and before/after state diffs.
   - Production adapter: `PdoAuditAdapter`.
   - Test adapter: `InMemoryAuditAdapter`.

### 3. Core Invariants Enforced at the Seam

1. **Pre-Transaction Gateway Refund Dispatch**:
   - Any external Gateway Refund (Mercado Pago) MUST be executed **BEFORE** acquiring the local database transaction lock.
   - A deterministic `Idempotency Key` (`ref_{reservationUid}_{amountCop}_{timestamp}`) protects against duplicate charges.
   - If the gateway fails or times out, the local database remains unmutated; database row locks are never held across external network hops.
2. **Channel Block Absorption ([ADR 0007](0007-external-platform-reservations-and-guest-onboarding.md))**:
   - Availability validation blocks conflicting reservations, active holds, and maintenance blocks.
   - When onboarding an External Reservation originating from Airbnb, overlapping ephemeral Channel Blocks from 'airbnb' are absorbed rather than reported as conflicts.
3. **Resurrection Defense & Terminal States**:
   - `CANCELLED` and `CONCLUDED` ([ADR 0009](0009-concluded-reservation-credential-and-metadata-redaction.md)) are strictly terminal.
   - Delayed asynchronous webhooks (e.g. late Mercado Pago IPN approvals) are rejected and logged to prevent resurrecting voided or departed stays.
4. **Statutory Access Credential Shielding ([ADR 0001](0001-mandatory-registry-before-access.md))**:
   - Access Credentials (door PINs) are generated at confirmation but strictly suppressed from guest communications until statutory Guest Registry verification is submitted.
5. **Eradication of the 26-Argument Constructor**:
   - External callers never instantiate `Reservation` directly. Intention-revealing parameter DTOs (`ReservationDraft::direct()`, `ReservationDraft::manual()`, `ReservationDraft::external()`, `CancellationRequest`) cleanly encapsulate caller parameters.

---

## Considered Options

1. **Option 1: Minimalist Engine (3 Methods)**:
   - *Considered*: Combining all transitions into `createReservation`, `cancelReservation`, and `transitionStatus`.
   - *Verdict*: High depth, but lacked an explicit cancellation dry-run (forcing controllers to duplicate refund retention math in modals) and kept database persistence internal rather than port-isolated.
2. **Option 2: Extensible State Machine & Transition Pipeline**:
   - *Considered*: First-class transition objects (`HoldTransition`, `CancelTransition`) with pluggable `CancellationPolicyInterface` and `RefundStrategyInterface`.
   - *Verdict*: Rejected as over-engineering. OceanViewFlats manages two physical properties (1606 and 1707) and one payment rail; a polymorphic pipeline introduced excessive structural surface area without tangible leverage.
3. **Option 3: Caller-Optimized View Model Engine**:
   - *Considered*: Having the engine return pre-rendered HTMX drawers and UI view models directly to callers.
   - *Verdict*: Rejected to maintain strict separation of concerns; formatting HTML drawers and UI display text belongs in presentation presenters/controllers, not inside the domain engine.
4. **Option 4: Hexagonal Ports & Adapters Hybrid (Selected)**:
   - Combines the clean hexagonal boundaries of Option 4 with the minimalist intention DTOs of Option 1 and the cancellation dry-run ergonomics of Option 3.

---

## Consequences

### Positive
- **Controller Simplification**: `ReservationController::cancel()` collapses from ~230 lines to under 25 lines; `createManual()` collapses from ~160 lines to under 30 lines.
- **Connection Pool Protection**: Zero risk of database connection starvation caused by third-party payment gateway latency during refunds.
- **Flawless Unit Testing**: The entire lifecycle (double-booking concurrency, partial refunds, gateway declines, webhook resurrection) is testable in-memory in sub-milliseconds without mock libraries, SQLite, or network sockets.
- **Complete Invariant Encapsulation**: Single authoritative home for quote computation, minimum stay, channel block absorption, refundable balance math, audit logging, and credential shielding.

### Neutral / Trade-offs
- Requires implementing 4 explicit ports and maintaining dual adapters (production PDO/HTTP + in-memory fakes) per the `DEEPENING.md` two-adapter rule.
- Migration must follow an expand-contract tracer bullet strategy to progressively migrate direct checkout, admin cancellations, and Airbnb onboarding without breaking active checkout traffic.
