# Automated Condominium Clearance Sync and Building Reception Integration

## Context

Under [ADR 0001](0001-mandatory-registry-before-access.md), OceanViewFlats strictly withholds Access Credentials (door code PIN) and the Guest Guide until the Primary Guest completes the Guest Registry for all staying occupants, enforcing compliance with Colombian statutory hospitality regulations and Playa Salguero condominium administration security clearance.

In practice at Edificio Salguero Sunset (properties 1707 and 1606), physical building access, front-desk reception, and elevator/parking security clearance are managed through an external property management system (Huésped Manager). Historically, an OceanViewFlats human operator had to manually copy guest data from OceanViewFlats submissions and paste them into a two-step external web form (`booking.php` for reservation details and `guest_pre.php` for occupant details).

This manual workflow introduced operational delay, potential human data entry errors, and friction when guests arrived outside of standard administrative hours.

## Decision

We introduce an automated, fault-isolated **Condominium Clearance Sync** pipeline that integrates OceanViewFlats with the external Condominium Administration Portal upon Guest Registry completion, backed by persistent transactional tracking and administrative retry mechanisms.

### 1. Fault-Isolated Fulfillment Architecture (ADR 0001 Preservation)

To preserve high reliability and prevent third-party platform instability from degrading the guest arrival experience:
- **Non-blocking Execution**: Condominium Clearance Sync runs as an asynchronous or out-of-band side-effect within `GuestLifecycleFulfillmentService` immediately following successful local database commit of the Guest Registry.
- **Access Fulfillment Independence**: If the external Condominium Administration Portal is unavailable, times out, or encounters errors (e.g. HTTP 502/503), the guest's local registry submission remains successful. The cryptographically random door PIN is generated and the unlocked Guest Guide is issued immediately per ADR 0001 and [ADR 0012](0012-personal-data-protection-and-ley-1581-compliance.md).
- **Operational Alerting**: Any failed clearance attempt is persisted and surfaced as an `Operational Alert` on the administrative Dashboard Hub (`AlertType::FAILED_CONDOMINIUM_CLEARANCE`), notifying operators before guest arrival.

### 2. Multi-Property Configuration

External access endpoints and security check tokens are configured via environment variables with defaults:
- `HUESPED_MANAGER_BASE_URL`: Base portal endpoint (`https://salguerosunset.huespedmanager.com.co/propietarios/production`).
- `HUESPED_MANAGER_1707_CHECK`: Property 1707 check token (`ep92449222`).
- `HUESPED_MANAGER_1606_CHECK`: Property 1606 check token (`ep24281580`).

### 3. Guest Registry Data Fidelity & Smart Inheritance

The `/registry` interface across all supported languages is enhanced to capture statutory requirements:
- **Separated Name Fields**: First Name (`name`) and Last Name (`lname`) are collected separately to satisfy the building reception system, with optional middle name (`sname`) and second surname (`mname`).
- **Country of Nationality**: A standardized nationality dropdown (`country`) is added for all guests, mapped to the uppercase country names expected by the portal.
- **Phone Number Inheritance**: The Primary Guest is required to provide a contact phone number (`phone`). For companions (especially minors), phone entry is optional in the UI; if omitted, the sync adapter automatically inherits the Primary Guest's phone number to satisfy the external portal's mandatory phone requirement.
- **Document Type Translation**: Localized document types map 1:1 to portal codes (`CC`, `CE`, `PA`, `TI`, `CD`, `DE`, `RC`).

### 4. Persistence Model & Admin Retries

A dedicated table `condominium_clearances` isolates third-party integration state:
- **`reservation_uid`**: Unique foreign reference to `reservations.reservation_uid`.
- **`status`**: State machine (`pending`, `synced`, `failed`).
- **`clearance_number`**: Authoritative numeric sequence (`last_id` / `consecutivo`) returned by the portal upon successful creation (e.g., `#495`).
- **`error_message`**: Captured error strings or HTTP diagnostics upon failure.
- **`request_payload`**: Audited JSON payload transmitted to the portal, with government document numbers (`ide`) and contact phone numbers (`phone`) masked (`****5678`) at rest per [ADR 0012](0012-personal-data-protection-and-ley-1581-compliance.md) while full unmasked fields are transmitted over the outbound HTTP request to building reception.
- **`attempts` & `last_attempt_at`**: Retry counter and timestamp.
- **`synced_at`**: Timestamp of confirmed synchronization.

The Administrative Reservation Dossier (`/reservations/:uid`) displays the Condominium Clearance status, the issued Condominium Clearance Number, and a 1-click "Retry Condominium Clearance" action.

## Consequences

### Positive
- **Zero Operator Data Entry**: Eliminates manual double-entry of guest names, IDs, dates, and vehicle plates into the Salguero Sunset building portal.
- **Front-Desk Preparedness**: Building security has registered guest rosters ready in advance of arrival, ensuring seamless front-desk badge and key issuance.
- **High Resilience**: External downtime never locks guests out of their digital guide or door PIN.
- **Auditability**: Operators have clear visibility into whether building security has received clearance, with full error logging and one-click recovery.

### Neutral / Trade-offs
- Adds a new database table `condominium_clearances` via migration `scripts/schema.sql`.
- Adds country selection and structured names to the public `/registry` form across 6 languages.
