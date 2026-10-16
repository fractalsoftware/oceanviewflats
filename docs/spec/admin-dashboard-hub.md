# Admin Dashboard Hub Specification: Operational Command Center

**Issue**: [#117](https://github.com/fractalsoftware/oceanviewflats/issues/117)  
**Parent Map**: [#116](https://github.com/fractalsoftware/oceanviewflats/issues/116)  
**Related ADRs**: [ADR 0001 (Mandatory Registry)](../adr/0001-mandatory-registry-before-access.md), [ADR 0006 (Maintenance Blocks)](../adr/0006-authoritative-maintenance-blocks-and-distinct-ledger-port.md), [ADR 0007 (External Reservations & Onboarding)](../adr/0007-external-platform-reservations-and-guest-onboarding.md)  
**Domain Dictionary**: [`CONTEXT.md`](../../CONTEXT.md)

---

## 1. Overview & Operational Goals

The **Dashboard Hub** is the central administrative landing interface (`/`) of OceanViewFlats. It transforms the admin homepage from a static navigation directory into an actionable real-time operational command center.

An operator logging into the admin platform immediately sees:
1. **Operational Alerts**: High-priority items requiring immediate operator action:
   - **Incomplete Guest Registries**: Confirmed reservations checking in soon (today or within 72 hours) whose mandatory Guest Registry has not been submitted.
   - **Un-onboarded Channel Blocks**: Imported iCal reservations (e.g., from Airbnb) requiring guest identity creation and External Guest Dispatch per ADR 0007.
2. **7-Day Operations Schedule**: Chronological arrivals (Check-in), departures (Check-out), and critical **Turnovers** (same-day check-out and check-in at the same property).
3. **Rates & Availability Summary**: Today's active Nightly Rate per Property (baseline vs. active Seasonal Rate Tier), upcoming rate tier transitions, and scheduled Maintenance Blocks.
4. **Channel Sync Health**: Current sync status of Inbound Feeds and background polling freshness.
5. **Property Scoping**: Instant filtering across all properties or scoped to Property `1606` or `1707`.

---

## 2. Canonical Domain Alignment

Per [`CONTEXT.md`](../../CONTEXT.md), all terminology and view elements strictly follow canonical vocabulary:

| Concept | Canonical Term | Forbidden / Avoid Terms |
| :--- | :--- | :--- |
| Apartment / Unit | **Property** (`1606`, `1707`) | Flat, Apartment, Unit, Listing |
| Property Selector | **Property Filter** | Unit Switcher, Listing Tab |
| Booking / Order | **Reservation** (Direct, Manual, External) | Booking, Order, Rental |
| Nightly Pricing | **Nightly Rate**, **Seasonal Rate Tier** | Daily Price, Base Fee, Season Block |
| Calendar Blocks | **Channel Block** (OTA), **Maintenance Block** (Host/Repairs) | Blackout Dates, Owner Hold, Admin Block |
| Guest Identification | **Guest Registry** | Check-in Form, Registration Card, Guest List |
| Arrival Guide & Door PIN | **Guest Guide**, **Access Credential** | Welcome Pack, Door Password, Key Code |
| Central Landing Page | **Dashboard Hub** | Admin Home, Control Center, Overview Page |
| Actionable Warning | **Operational Alert** | Todo, Warning, System Notification |

---

## 3. Domain Data Models & Value Objects

The dashboard aggregation engine exposes structured, immutable value objects under namespace `OceanViewFlats\Domain\Reservation\Dashboard` (or consumed by the admin controller layer):

### 3.1 `OperationalAlert`

Represents an actionable operational condition requiring administrator intervention:

```php
namespace OceanViewFlats\Domain\Reservation\Dashboard;

use DateTimeImmutable;

enum AlertSeverity: string
{
    case CRITICAL = 'critical'; // Check-in today or past; immediate action
    case WARNING = 'warning';   // Check-in in 24–48 hours
    case INFO = 'info';         // Check-in in 48–72 hours or un-onboarded OTA block
}

enum AlertType: string
{
    case INCOMPLETE_GUEST_REGISTRY = 'incomplete_guest_registry';
    case UNONBOARDED_CHANNEL_BLOCK = 'unonboarded_channel_block';
}

final class OperationalAlert
{
    public function __construct(
        public readonly string $id,
        public readonly AlertType $type,
        public readonly AlertSeverity $severity,
        public readonly string $propertyId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $dueDate, // YYYY-MM-DD
        public readonly ?string $reservationUid = null,
        public readonly ?string $guestName = null,
        public readonly ?string $channelBlockUid = null,
        public readonly ?string $source = null,
        public readonly array $actionPayload = []
    ) {}
}
```

### 3.2 `OperationsEvent`

Represents a scheduled movement (arrival, departure, or turnaround) on the calendar:

```php
enum MovementType: string
{
    case CHECK_IN = 'check_in';
    case CHECK_OUT = 'check_out';
    case TURNOVER = 'turnover'; // Same-day check-out & check-in for the same property
}

final class OperationsEvent
{
    public function __construct(
        public readonly string $date, // YYYY-MM-DD
        public readonly MovementType $movementType,
        public readonly string $propertyId,
        public readonly string $reservationUid,
        public readonly string $guestName,
        public readonly ?string $guestPhone,
        public readonly string $status, // confirmed, pending_payment
        public readonly bool $registryCompleted,
        public readonly ?string $doorCode,
        public readonly string $source, // web, manual, airbnb
        public readonly ?string $externalConfirmationCode = null,
        public readonly ?string $departingReservationUid = null, // for TURNOVER
        public readonly ?string $departingGuestName = null       // for TURNOVER
    ) {}
}
```

### 3.3 `PropertyRateStatus`

Represents real-time pricing and upcoming seasonal tier transitions:

```php
final class PropertyRateStatus
{
    public function __construct(
        public readonly string $propertyId,
        public readonly float $currentNightlyRate, // Authoritative rate today in COP
        public readonly bool $isSeasonalTierActive,
        public readonly ?string $activeTierName,
        public readonly ?string $activeTierEndDate,
        public readonly ?float $nextTierRate = null,
        public readonly ?string $nextTierName = null,
        public readonly ?string $nextTierStartDate = null
    ) {}
}
```

### 3.4 `DashboardHubViewData`

The unified payload supplied to the presentation templates:

```php
final class DashboardHubViewData
{
    /**
     * @param list<OperationalAlert> $alerts
     * @param array<string, list<OperationsEvent>> $scheduleByDate Keyed by YYYY-MM-DD
     * @param array<string, PropertyRateStatus> $rateStatus Keyed by propertyId ('1606', '1707')
     * @param list<array<string, mixed>> $upcomingMaintenanceBlocks
     * @param array<string, mixed> $channelSyncData
     */
    public function __construct(
        public readonly string $selectedPropertyFilter, // 'all' | '1606' | '1707'
        public readonly array $alerts,
        public readonly array $scheduleByDate,
        public readonly array $rateStatus,
        public readonly array $upcomingMaintenanceBlocks,
        public readonly array $channelSyncData,
        public readonly int $todayArrivalsCount,
        public readonly int $todayDeparturesCount,
        public readonly int $activeStaysCount
    ) {}
}
```

---

## 4. Operational Alert Thresholds & Logic

### 4.1 Incomplete Guest Registry Rule
* **Eligibility**: `Reservation` where `status = CONFIRMED`, `registry_completed = 0`, and `check_in` satisfies:
  $$\text{CURRENT\_DATE} \le \text{check\_in} \le \text{CURRENT\_DATE} + 3\text{ days}$$
  *(Also includes stays currently in-house where check-in date is today or yesterday and registry remains unsubmitted).*
* **Severity Matrix**:
  * **Critical** (`CRITICAL`): `check_in <= CURRENT_DATE`. Guest is arriving today or already due for check-in with door PIN withheld under ADR 0001.
  * **Warning** (`WARNING`): `check_in == CURRENT_DATE + 1 day`. Guest arriving tomorrow; urgent dispatch needed.
  * **Info** (`INFO`): `check_in >= CURRENT_DATE + 2 days`. Advance invitation needed.
* **Quick Actions**:
  * Button 1: **"Copy Invite"** (triggers bilingual Stage 1 invitation snippet clipboard copy for Airbnb or opens guest email).
  * Button 2: **"Inspect"** (opens the existing `/reservations/{uid}` detail drawer via HTMX).
  * Button 3: **"Pre-mark Verified"** (triggers `/reservations/override-registry` if the host verified ID out-of-band).

### 4.2 Un-onboarded Channel Block Rule
* **Eligibility**: Inbound feed calendar events (`ChannelBlock`) where `endDate > CURRENT_DATE` and no matching `Reservation` exists with matching dates and property (`isOnboarded == false`).
* **Severity Matrix**:
  * **Warning** (`WARNING`): `startDate <= CURRENT_DATE + 2 days`.
  * **Info** (`INFO`): `startDate > CURRENT_DATE + 2 days`.
* **Quick Actions**:
  * Button 1: **"Onboard Guest"** (triggers modal opening `/reservations/new` pre-filled with `property_id`, `check_in`, `check_out`, and `channel_block_uid` per ADR 0007).

---

## 5. 7-Day Operations Schedule Rules

1. **Window**: Today through Today + 6 Days (7 contiguous days).
2. **Movements**:
   * **Arrivals**: Any reservation with `check_in = date`.
   * **Departures**: Any reservation with `check_out = date`.
   * **Turnover Detection**: If on a specific date and property, reservation $A$ has `check_out = date` and reservation $B$ has `check_in = date`:
     * Collapse into a dedicated **Turnover** banner with prominent cleaning badge: `"Apartment 1606 Turnover: [Guest A] departs 11:00 AM → [Guest B] arrives 3:00 PM"`.
3. **Registry & Credential Visibility**:
   * For arrivals: Badge displaying **Registry: Completed** (green) vs. **Registry: Pending** (amber/red).
   * Door Access PIN display (if generated) or a warning lock icon if withheld per ADR 0001.

---

## 6. Rates & Calendar Blocks Overview Rules

1. **Current Rate Card**:
   - Displays for each property:
     - Today's effective nightly rate in COP (e.g. `$450,000 COP`).
     - Badge: **Base Rate** vs. **Seasonal Tier: [Tier Name]**.
     - Upcoming rate change preview: Next seasonal tier window and rate within 30 days.
   - Quick Action: Button to open `/rates?property_id={prop}`.
2. **Maintenance Blocks Card**:
   - Active holds today or starting within the next 14 days.
   - Author, date range, and reason/notes.
   - Quick Action: Button to "+ Add Maintenance Block" (opening modal) or navigate to `/calendar-blocks`.

---

## 7. HTMX Endpoints & View Architecture

### 7.1 Routes

| Method | URI | Controller Action | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | `DashboardController::index` | Full page render with layout, Property Filter, and initial Hub content. |
| `GET` | `/dashboard/hub` | `DashboardController::hub` | HTMX partial response returning `#dashboard-hub-content` when changing Property Filter. |

### 7.2 Property Filter Contract

The Property Filter operates as segmented pills at the top of the dashboard:

```html
<div class="flex items-center space-x-2">
    <button hx-get="/dashboard/hub?property_id=all"
            hx-target="#dashboard-hub-content"
            hx-swap="innerHTML"
            class="filter-pill active">
        All Properties
    </button>
    <button hx-get="/dashboard/hub?property_id=1606"
            hx-target="#dashboard-hub-content"
            hx-swap="innerHTML"
            class="filter-pill">
        Apartment 1606
    </button>
    <button hx-get="/dashboard/hub?property_id=1707"
            hx-target="#dashboard-hub-content"
            hx-swap="innerHTML"
            class="filter-pill">
        Apartment 1707
    </button>
</div>
```

### 7.3 View Component Hierarchy

```
admin/src/Views/dashboard/
├── index.php                # Master container, header greeting, property filter pills
├── _hub_content.php         # Reactive wrapper for partial swaps (#dashboard-hub-content)
├── _summary_cards.php       # Quick stats row (today check-ins, check-outs, in-house, alert count)
├── _alerts_card.php         # High-priority alerts section (registries & un-onboarded blocks)
├── _schedule_feed.php       # 7-day chronological operational timeline & turnovers
├── _rates_blocks_card.php   # Real-time nightly rates & active maintenance blocks
└── _channel_card.php        # Inbound iCal sync status & manual sync trigger
```

---

## 8. Repository Contracts & Query Signatures

To support high performance and clean testability, aggregation methods are encapsulated cleanly:

### 8.1 `AdminReservationRepository` Extensions
```php
/**
 * Fetches upcoming arrivals, departures, and active stays for the specified date window.
 *
 * @return list<OperationsEvent>
 */
public function getOperationalSchedule(
    string $propertyId = 'all',
    string $startDate,
    string $endDate
): array;

/**
 * Fetches confirmed reservations with imminent check-in dates whose Guest Registry is incomplete.
 *
 * @return list<OperationalAlert>
 */
public function getIncompleteRegistryAlerts(
    string $propertyId = 'all',
    int $lookaheadDays = 3,
    ?DateTimeImmutable $now = null
): array;
```

### 8.2 `AdminCalendarBlockRepository` Extensions
```php
/**
 * Fetches active or upcoming maintenance blocks within lookahead window.
 *
 * @return list<MaintenanceBlock>
 */
public function getUpcomingBlocks(
    string $propertyId = 'all',
    int $lookaheadDays = 14,
    ?DateTimeImmutable $now = null
): array;
```

### 8.3 Rate Service Integration
```php
/**
 * Resolves current nightly rate and upcoming seasonal rate tiers for dashboard presentation.
 */
public function getPropertyRateStatus(
    string $propertyId,
    ?DateTimeImmutable $now = null
): PropertyRateStatus;
```

---

## 9. Security, Concurrency, and Session Guarantees

1. **Authentication**: All dashboard endpoints are gated by `AuthMiddleware` and `SessionMiddleware`.
2. **CSRF Protection**: All quick-action buttons (sync triggers, manual overrides, modals) transmit `HX-CSRF-Token` headers.
3. **Data Isolation**: Multi-tenant property scoping ensures clean separation without cross-property data leaks.
4. **Zero Client Hydration**: Pure server-side PHP HTML generation + HTMX 1.9 dynamic partial swaps and Tailwind CSS.
