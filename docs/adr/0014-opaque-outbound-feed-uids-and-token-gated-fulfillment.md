# Opaque Outbound Feed UIDs and Token-Gated Guest Fulfillment

## Status

Accepted (Amends [ADR 0001](0001-mandatory-registry-before-access.md) and [ADR 0007](0007-external-platform-reservations-and-guest-onboarding.md))

## Context

In OceanViewFlats, a Reservation's `reservation_uid` (`ovf_*`, `res-man-*`, `res-abnb-*`) acts as the authoritative lookup token for both the public Guest Registry (`/api/registry-lookup.php`, `/api/registry-processor.php`) and the Guest Guide (`/api/guide-access.php`), which releases physical smart-lock Access Credentials, Wi-Fi credentials, and Assigned Parking Space details once the Guest Registry is completed.

Two public behaviors previously undermined that security boundary:
1. The unauthenticated Outbound Feed (`public/api/ical.php`) emitted each active Direct Reservation's raw `reservation_uid` in the iCalendar `UID:` property (`<reservation_uid>@oceanviewflats.com`), allowing any anonymous party to harvest live lookup tokens from `/api/ical.php` and retrieve physical door PINs and Wi-Fi passwords via `/api/guide-access.php`.
2. `GuestLifecycleFulfillmentService::submitRegistry()` fell back to matching reservations solely by `(property_id, check_in, check_out)` when `reservation_code` was absent or unrecognized. Because occupied dates are intentionally public on `/api/availability.php`, an unauthenticated party knowing only a property's booked dates could hijack an unregistered Confirmed Reservation, lock out the Primary Guest, and receive the generated Access Credential in the HTTP response.

## Decision

1. **Opaque HMAC-Derived Outbound Feed UIDs**: `public/api/ical.php` must never emit raw `reservation_uid` values. Instead, each exported Direct Reservation VEVENT derives a stable, deterministic, non-reversible iCalendar UID (`ovf-evt-<32-hex-hmac>@oceanviewflats.com`) via HMAC-SHA256 keyed by server-side secret (`ICAL_UID_SECRET` / `APP_ENCRYPTION_KEY`). External OTA consumers retain stable deduplication keys without ever learning guest lookup tokens.
2. **Mandatory Reservation Token on Public Guest Registry Submission**: `GuestLifecycleFulfillmentService::submitRegistry()` strictly resolves reservations by `reservation_uid` (`findByUid`) and never falls back to public date matching (`findByPropertyAndDates`). Guests always receive their `reservation_uid` through out-of-band authenticated channels (`Reservation Confirmation` email or `External Guest Dispatch`).
3. **Brute-Force Rate Limiting**: Public lookup endpoints (`guide-access.php` and `registry-lookup.php`) enforce tight per-IP rate limits (20 requests per 10-minute window) to deter token enumeration.

## Considered Options

- **Add a second factor (email/last name) on every Guest Guide click**: Rejected for now to preserve frictionless one-click links from `Reservation Confirmation` and `Access Dispatch` emails while closing both unauthenticated disclosure chains immediately. Can be layered later without breaking opaque Outbound Feed UIDs.
- **Authenticate the Outbound Feed URL with a secret query parameter**: Complementary, but external OTA feeds can still be leaked or logged by third parties; deriving opaque iCal UIDs ensures feed compromise never discloses Access Credentials.

## Consequences

- External OTA channels (Airbnb) observe a one-time VEVENT `UID:` change on the first poll after rollout; blocked stay dates remain identical, so calendar blocking is unaffected.
- Any public Guest Registry submission missing a valid `reservation_uid` is rejected with HTTP 400 / not found; administrative workflows in the Admin Console remain unaffected because they look up reservations by `reservation_uid` directly.
