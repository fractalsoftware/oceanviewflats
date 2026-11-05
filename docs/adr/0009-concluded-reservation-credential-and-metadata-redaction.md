# Concluded Reservation Access Gating and Credential Redaction

## Context

Under [ADR 0001](0001-mandatory-registry-before-access.md), OceanViewFlats enforces mandatory Guest Registry submission prior to releasing property access credentials (cryptographically random door PIN, Wi-Fi credentials, parking assignments). Under [ADR 0007](0007-external-platform-reservations-and-guest-onboarding.md) and [ADR 0008](0008-automated-condominium-clearance-sync.md), guest registration automates building reception clearance and primary guest onboarding.

However, once a guest's stay concludes, retaining public access to property credentials and stay metadata presents significant privacy and security risks:
1. **Physical Security Risk**: Door PINs, building access procedures, and Wi-Fi credentials could remain accessible indefinitely to anyone with the reservation code, link, or browser history.
2. **Guest Privacy (PII) Leakage**: Reservation lookups and arrival guides expose primary guest names and dates of stay even after departure.
3. **Stale Registry Submissions**: Submitting or re-submitting a guest registry for an already concluded stay causes out-of-date records, unintended email dispatches, and invalid condominium clearance synchronization attempts.

Per statutory Colombian check-out times and property policies, guest occupancy ends on the check-out date. The reservation enters the authoritative **Concluded Reservation** domain state at 23:59:59 COT (Colombia Time, `America/Bogota`, UTC-5) on the check-out date.

## Decision

We establish an authoritative domain boundary for **Concluded Reservations** across backend domain services, public HTTP API endpoints, and client-side interfaces.

### 1. Canonical Domain Boundary (`Reservation::isConcluded`)

In `OceanViewFlats\Domain\Reservation\Reservation`, we implement:
```php
public function isConcluded(?DateTimeImmutable $now = null): bool
```
- Compares the reference time against 23:59:59 COT (`America/Bogota`) on `$this->checkOut`.
- Allows injecting `$now` for deterministic unit testing while defaulting to current time in `America/Bogota`.
- Returns `true` strictly after 23:59:59 COT on the check-out date.

### 2. Guest Registry Stay Lookup Hardening (`/api/registry-lookup.php`)

When a public registry lookup is performed via `/api/registry-lookup.php`:
- If `$reservation->isConcluded()` is true, the endpoint immediately responds with **HTTP 403 Forbidden**.
- The JSON response payload contains `{ "success": false, "status": "concluded", "message": "..." }` with localized messaging across all supported languages (`en`, `es`, `fr`, `it`, `de`, `ja`).
- Crucially, under the Principle of Least Privilege, the payload **strictly omits** the `reservation` object, property identifiers, dates, guest names, and credentials.

### 3. Guest Lifecycle Fulfillment Gating (`GuestLifecycleFulfillmentService`)

In `GuestLifecycleFulfillmentService::submitRegistry()`:
- Checks `$reservation->isConcluded()`.
- If concluded, immediately aborts before any database mutations or side-effects, returning a domain validation failure (`Cannot submit guest registry for a concluded reservation.`).
- Prevents post-checkout creation of `guest_registries` rows, audit logs, host emails, spreadsheet synchronizations, and condominium clearance syncs.

### 4. Guest Guide Access Gating (`GuideAccessService` & `/api/guide-access.php`)

In `GuideAccessService` and `/api/guide-access.php`:
- Evaluates whether the reservation has concluded.
- If concluded, returns HTTP 403 Forbidden with `status: "concluded"`.
- Completely suppresses door PIN, Wi-Fi SSID/password, parking assignments, and primary guest metadata.

### 5. Client-Side Defense-in-Depth (`/registry` & `/guide`)

Both frontend experiences enforce client-side defense-in-depth:
- **Initialization Check-out Date Suppression**: If the URL parameter `check_out` is earlier than today's date in COT, client scripts immediately suppress prefilling guest names, property names, and dates into the DOM, displaying the localized concluded notice without exposing stay details.
- **API 403 Concluded Handling**: When an API request returns HTTP 403 with `status: "concluded"`, the DOM resets display placeholders to `--`, hides form controls and submission triggers, and renders a localized concluded alert banner.

## Consequences

### Positive
- **Zero Credential Disclosure**: Access credentials (door PINs, Wi-Fi credentials, parking assignments) are completely shielded after check-out concludes.
- **Zero PII Leakage**: Personal guest identity and historical stay details are redacted from unauthenticated public lookup endpoints.
- **Operational Hygiene**: Prevents stale registry submissions from triggering downstream condominium clearance syncs and host notifications.
- **Consistent Timezone Handling**: Authoritative evaluations use `America/Bogota` (COT, UTC-5), eliminating server-timezone discrepancies.

### Neutral / Trade-offs
- Guests seeking past stay confirmation or invoices after departure cannot retrieve them via public check-in links and must contact the host or administrative channels.
- Adds localized translation strings (`msg_concluded`, `err_reservation_concluded`) across all 6 supported locales (`en`, `es`, `fr`, `it`, `de`, `ja`).
