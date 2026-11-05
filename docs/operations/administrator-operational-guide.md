# OceanViewFlats — Administrator Operational Runbook

Operational reference for deploying, provisioning, maintaining, and troubleshooting the OceanViewFlats platform across `oceanviewflats.com` and `admin.oceanviewflats.com`.

---

## 1. Hosting Topology: cPanel Pattern A ([ADR 0005](../adr/0005-admin-interface-architecture-and-subdomain-isolation.md))

The Admin Console runs on an isolated subdomain (`admin.oceanviewflats.com`) with its `DocumentRoot` mapped to `/home/<user>/admin/public`. All application controllers, templates, shared domain models, Composer libraries, and CLI scripts live outside any web-accessible directory:

```text
/home/<cpanel_user>/
├── public_html/                 <-- DocumentRoot for oceanviewflats.com (Public SSG)
│   ├── index.html, css/, js/
│   ├── cache/                   <-- Ephemeral iCal & pricing caches (avail_*.json, channel_sync_status.json)
│   └── api/                     <-- Public REST APIs (loads /home/<user>/vendor/autoload.php)
├── admin/
│   ├── public/                  <-- DocumentRoot for admin.oceanviewflats.com (Pattern A)
│   │   ├── index.php            <-- Administrative Front-Controller
│   │   ├── setup.php            <-- One-Time Bootstrap Utility (auto-locks once an admin user exists)
│   │   └── .htaccess            <-- URL Rewriting & HTTP Security Headers
│   └── src/                     <-- Controllers, Repositories, Auth/CSRF Middleware, & HTMX Views (Views/)
├── src/
│   ├── Domain/                  <-- Shared Domain Models (Access, Database, Finance, Fulfillment,
│   │                            #   Payment, Privacy, Quote, Reservation, Security, Support)
│   └── Infrastructure/          <-- Hexagonal Persistence, Refund, Audit, & Event Adapters
├── vendor/                      <-- Production Composer Autoloader & Dependencies
└── scripts/                     <-- CLI Utilities (migrate.php, create-admin-user.php,
                                 #   sync-channels.php, redact-expired-pii.php, generate-docs.php)
```

### Subdomain & SSL Setup in cPanel

1. **Create Subdomain**: In cPanel -> **Domains** -> **Create A New Domain**, enter `admin.oceanviewflats.com`, uncheck *"Share document root"*, and set **Document Root** to `admin/public`.
2. **Enable AutoSSL**: In cPanel -> **Security** -> **SSL/TLS Status**, select `admin.oceanviewflats.com` and click **Run AutoSSL**. Enable **Force HTTPS Redirect** under **Domains**.

---

## 2. Dual-Pipeline CI/CD & GitHub Secrets

Deployments are split across two independent GitHub Actions workflows connecting via SFTP on **Port 2223**:

1. **Public Static Site (`.github/workflows/deploy.yml`)**: Compiles the React/TypeScript SSG (`npm run build`), injects `SetEnv` secrets into `public/.htaccess` and `MERCADOPAGO_PUBLIC_KEY` into `src/constants/config.ts`, and uploads `./dist/.` to `FTP_REMOTE_PATH` (`/home/<user>/public_html`).
2. **Admin & Domain Backend (`.github/workflows/admin-deploy.yml`)**: Triggers on changes to `admin/**`, `src/Domain/**`, `src/Infrastructure/**`, `scripts/**`, or `composer.*`. Runs PHP 8.3 lint, PHPStan Level 8, and PHPUnit suites, installs production dependencies (`composer install --no-dev --prefer-dist --optimize-autoloader`), injects `SetEnv` secrets into `admin/public/.htaccess`, and deploys `admin/`, `src/Domain/`, `src/Infrastructure/`, `vendor/`, and `scripts/` to `/home/<user>/`.

### Required GitHub Actions Secrets

| Secret Name | Scope | Description |
| :--- | :--- | :--- |
| `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | Both | cPanel SFTP host, username, and password (Port 2223) |
| `FTP_REMOTE_PATH` | Both | Public webroot path (`/home/<user>/public_html`) |
| `FTP_ADMIN_REMOTE_PATH` | Admin | Home directory path (`/home/<user>`, defaults to `dirname(FTP_REMOTE_PATH)`) |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Both | MySQL database connection credentials |
| `APP_ENCRYPTION_KEY` | Both | 32-byte (or 64-char hex) key for XSalsa20-Poly1305 encryption of `guest_registries.guests_payload` ([ADR 0012](../adr/0012-personal-data-protection-and-ley-1581-compliance.md)) |
| `OVF_ADMIN_SESSION_SECRET` | Admin | 64-char random hex secret (`openssl rand -hex 32`) for session/CSRF signing and initial `setup.php` token |
| `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_SANDBOX` | Both | Mercado Pago Checkout Bricks public key, server-to-server access token, and boolean sandbox flag |
| `RECIPIENT_EMAIL` | Public | Host notification mailbox (`rentals@oceanviewflats.com`) |
| `CAPTCHA_SECRET` | Public | HMAC key for signing stateless mathematical CAPTCHA tokens |
| `PROPERTY_1606_DOOR_CODE`, `PROPERTY_1606_WIFI_SSID`, `PROPERTY_1606_WIFI_PASSWORD` | Both | Fallback smart-lock PIN (`0160600#`) and Wi-Fi credentials for Property `1606` |
| `PROPERTY_1707_DOOR_CODE`, `PROPERTY_1707_WIFI_SSID`, `PROPERTY_1707_WIFI_PASSWORD` | Both | Fallback smart-lock PIN (`0170700#`) and Wi-Fi credentials for Property `1707` |
| `HUESPED_MANAGER_BASE_URL` | Both | Condominium Administration Portal base URL (`https://salguerosunset.huespedmanager.com.co`) |
| `HUESPED_MANAGER_1707_CHECK`, `HUESPED_MANAGER_1606_CHECK` | Both | Per-property security check tokens for Huésped Manager (`ep92449222` / `ep24281580`) |

---

## 3. Database Migrations & Schema (`scripts/migrate.php`)

Runtime API scripts never execute `ALTER TABLE` or `CREATE TABLE`. All schema migrations are centralized in `OceanViewFlats\Domain\Database\MigrationRunner` (`scripts/schema.sql`) and use `EnvLoader` to automatically discover credentials from `.htaccess` or `.env`.

### Running Migrations

- **Via SSH / cPanel Terminal**:
  ```bash
  php scripts/migrate.php
  ```
- **Via One-Time Web Setup (Zero-SSH fallback before first admin user is created)**:
  Visit `https://admin.oceanviewflats.com/setup.php?token=<OVF_ADMIN_SESSION_SECRET>` and click **Run Database Migrations**. Once an administrator account exists in `admin_users`, `setup.php` permanently returns `HTTP 403 Forbidden`.

### Managed MySQL Tables (13 Tables)

1. **`reservations`**: Core booking ledger (`reservation_uid`, `property_id`, `check_in`, `check_out`, `total_price`, `refunded_amount`, `source`, `external_confirmation_code`, `channel_block_uid`, `status`, `registry_completed`, `door_code`, `max_guests`, Ley 1581 consent columns `privacy_policy_version`, `privacy_consent_at`, `privacy_consent_ip`, and `pii_redacted_at`).
2. **`payment_idempotency`**: Double-charge prevention keyed by `idempotency_key`.
3. **`guest_registries`**: Statutory guest registration records (`guests_payload` encrypted at rest via `PayloadEncryptor`, `car_plates`, `car_model`, `minor_guardian_consent`, `privacy_consent_*`, `pii_redacted_at`).
4. **`admin_users`**: Admin Console accounts (`email`, Argon2id `password_hash`, `role` in `superadmin|admin|manager|viewer`, `is_active`, `failed_login_attempts`, `locked_until`).
5. **`admin_audit_logs`**: Immutable audit trail with actor attribution, IP/user-agent, and masked `payload_before` / `payload_after` JSON diffs.
6. **`calendar_blocks`**: Authoritative **Maintenance Blocks** ([ADR 0006](../adr/0006-authoritative-maintenance-blocks-and-distinct-ledger-port.md)).
7. **`property_rates`**: Database-backed **Seasonal Rate Tiers** (`PdoRateSource`).
8. **`reservation_refunds`**: Itemized Mercado Pago **Gateway Refund** ledger.
9. **`condominium_clearances`**: Huésped Manager synchronization state, attempts, masked request payload, error diagnostics, and **Condominium Clearance Number** (`clearance_number` / `consecutivo`) ([ADR 0008](../adr/0008-automated-condominium-clearance-sync.md)).
10. **`operational_alert_dismissals`**: Tracks operator dismissals of **Operational Alerts** on the **Dashboard Hub**.
11. **`operational_expenses`**: Property (`1606`, `1707`) and portfolio-shared (`shared`, split 50/50) **Operational Expenses** by **Expense Category**.
12. **`parafiscal_settlements`**: Quarterly FONTUR **Parafiscal Settlements** per Property (`1606` RNT `#270413`, `1707` RNT `#268781`), tracking taxable base, 0.25% contribution, statutory due date, settlement snapshot, and 1-to-1 link to `operational_expenses` ([ADR 0013](../adr/0013-fontur-parafiscal-contribution-and-expense-sync.md)).

---

## 4. Administrator Account Provisioning & Lockout Recovery

Admin accounts use `PASSWORD_ARGON2ID` (64 MB memory, 4 iterations) and lock for 15 minutes after 5 consecutive failed logins (60 minutes after 10 failures).

Create a new account, rotate a password, or unlock a locked account via CLI (idempotent upsert):

```bash
php scripts/create-admin-user.php \
  --email="admin@oceanviewflats.com" \
  --name="Property Administrator" \
  --password="YourSecurePassword123!" \
  --role="superadmin"
```

Supported roles: `superadmin`, `admin`, `manager`, `viewer`. Note that `/financial-report` and `/audit-logs` are restricted strictly to `admin` and `superadmin` roles.

---

## 5. Scheduled Cron Jobs (cPanel)

Configure two background cron jobs in cPanel (**Advanced** -> **Cron Jobs**):

### 5.1 Inbound Channel Sync (Every 15 Minutes — [ADR 0002](../adr/0002-external-bookings-as-ephemeral-channel-blocks.md))

Polls external Airbnb iCal feeds and caches **Channel Blocks** to `public_html/cache/avail_{property}.json` and health metrics to `public_html/cache/channel_sync_status.json`:

```cron
*/15 * * * * /usr/local/bin/php /home/<user>/scripts/sync-channels.php >> /home/<user>/logs/channel_sync.log 2>&1
```

Manual CLI flags:
```bash
php scripts/sync-channels.php --force              # Bypass 60s cooldown for all properties
php scripts/sync-channels.php --property=1606 -f   # Force refresh for Property 1606 only
```

### 5.2 Ley 1581 Post-Checkout PII Redaction (Daily — [ADR 0012](../adr/0012-personal-data-protection-and-ley-1581-compliance.md))

Scrubs expired personal identification documents, contact details, door codes, vehicle records, and clearance payloads for stays whose **Check-out Date** passed more than 90 days ago, while preserving financial and occupancy columns (`total_price`, `refunded_amount`, `check_in`, `check_out`, `property_id`, `source`, `status`):

```cron
15 3 * * * /usr/local/bin/php /home/<user>/scripts/redact-expired-pii.php --days=90 >> /home/<user>/logs/pii_redaction.log 2>&1
```

Operators can also trigger immediate PII redaction for an individual concluded or cancelled reservation from the Admin Console Reservation Dossier (**Redact PII** / `POST /reservations/{uid}/redact-pii`) to fulfill a guest Habeas Data (ARCO) suppression request.

---

## 6. Key Operational Workflows

### 6.1 Access Credentials & Smart-Lock PINs ([ADR 0001](../adr/0001-mandatory-registry-before-access.md), [ADR 0009](../adr/0009-concluded-reservation-credential-and-metadata-redaction.md), [ADR 0012](../adr/0012-personal-data-protection-and-ley-1581-compliance.md))

- **Cryptographically Random Generation**: `DoorCodeGenerator` generates non-deterministic 7-digit keypad codes followed by `#` (`0XXXXXX#` via `random_int(100000, 999999)`). PINs are never derived from guest ID numbers or phone numbers.
- **Registry Gating**: `/api/guide-access.php` withholds the door PIN, Wi-Fi credentials, and **Assigned Parking Space** (Bay 87 for `1606`, Bay 95 for `1707`) until `reservations.registry_completed = 1`.
- **Concluded Reservation Redaction**: At 23:59:59 COT (`America/Bogota`) on the **Check-out Date**, the stay becomes a **Concluded Reservation**. Both `/api/guide-access.php` and `/api/registry-lookup.php` return `HTTP 403 Forbidden` (`status: "concluded"`) and redact all credentials and guest metadata.
- **Admin Controls**: In `/reservations/{uid}`, operators can manually complete the registry, override a custom PIN (`POST /reservations/{uid}/door-code/override`), or regenerate a random PIN (`POST /reservations/{uid}/door-code/regenerate`).

### 6.2 Condominium Clearance Sync ([ADR 0008](../adr/0008-automated-condominium-clearance-sync.md))

When a guest submits `/registry`, `HuespedManagerClearanceSync` executes a non-blocking 2-step HTTP handshake against the Edificio Salguero Sunset **Condominium Administration Portal**:
1. **Step 1 (`reg_guest_owner_pre.php`)**: Submits stay dates, property ID, occupant count, sanitized vehicle plate (`RX.placa`: 3–8 uppercase alphanumeric chars), and sanitized notes (`RX.texto`: max 250 Latin/numeric chars) to obtain the numeric **Condominium Clearance Number** (`last_id` / `consecutivo`) and session cookies.
2. **Step 2 (`reg_hpds_pre.php`)**: Submits sanitized Latin occupant names (`RX.nombre`: 2–60 chars), validated document codes (`CC`, `TI`, `RC`, `CE`, `PA`, falling back to `DE` when outside Colombian numeric bounds), uppercase nationalities, and normalized 7–15 digit phone numbers (`RX.telefono`, inheriting the Primary Guest's phone when a companion's phone is omitted).

If the portal is unreachable or returns a validation error, guest access fulfillment still succeeds locally and an **Operational Alert** (`FAILED_CONDOMINIUM_CLEARANCE`) appears on the **Dashboard Hub**. Operators can inspect the error message and click **Retry Clearance Sync** (`POST /reservations/{uid}/clearance-retry`) in the Reservation Dossier.

### 6.3 External OTA Onboarding ([ADR 0007](../adr/0007-external-platform-reservations-and-guest-onboarding.md))

Un-onboarded Airbnb **Channel Blocks** surface an **Operational Alert** on the **Dashboard Hub** and an **Onboard Guest** button in `/calendar-blocks`. Onboarding creates an **External Reservation** (`res-abnb-*`, `source = 'airbnb'`) with an **External Confirmation Code** (`HMXXXXXXXX`), absorbs the overlapping **Channel Block** on the `ReservationLedger`, excludes the stay from Airbnb outbound `/api/ical.php` feeds (preventing echo loops), and provides a copyable EN/ES chat snippet for Stage 1 Guest Registry invitation.

### 6.4 Financial Report & FONTUR Parafiscal Settlements ([ADR 0013](../adr/0013-fontur-parafiscal-contribution-and-expense-sync.md))

Available at `/financial-report` (`admin` and `superadmin` roles):
- **Parafiscal Taxable Base**: Realized commercial accommodation revenue from Confirmed and Concluded Reservations (excluding `owner_stay` and cancelled-stay **Policy Retention**), deducting flat Cleaning + Resort Fees (`100,000 COP` for `1606`; `120,000 COP` for `1707`) on Direct Reservations (`web`, `direct_manual`) before night-proration, and including 100% of prorated net payouts on External OTA (`airbnb`) Reservations.
- **Quarterly Assessment & Deadlines**: Assessed at **0.25%** (`2.5 per thousand`, rounded to nearest `1 COP`) per Property (`1606` → RNT `#270413`; `1707` → RNT `#268781`) across `Q1`–`Q4`. Due on the **20th Colombian business day** of the month following quarter close (Apr, Jul, Oct, Jan). Unsettled closed quarters trigger an **Operational Alert** on the **Dashboard Hub**.
- **Bidirectional Expense Sync**: Recording a **Parafiscal Settlement** (`POST /financial-report/parafiscal/{periodKey}/{propertyId}/settle`) with `settled_amount >= 1 COP` automatically creates/updates a linked `taxes_insurance` **Operational Expense** (`parafiscal_settlements.operational_expense_id`). Zero-revenue declarations (`0 COP`) settle the period without creating a `$0` expense row. Reverting a settlement or deleting its linked expense updates both records atomically.

---

## 7. Troubleshooting Quick Reference

| Symptom | Cause | Resolution |
| :--- | :--- | :--- |
| Guest Guide shows masked PIN (`••••••`) | `registry_completed = 0`, status is not `confirmed`, or stay is `concluded` (> 23:59:59 COT on Check-out Date). | Have the guest submit `/registry/?code=<uid>`, or click **Complete Registry** in Admin Console `/reservations/{uid}`. |
| Dashboard shows **Condominium Clearance Failed** | Huésped Manager timeout, rotated check token, or portal validation rejection. | Open `/reservations/{uid}`, review the extracted portal error, verify `HUESPED_MANAGER_*` env vars, and click **Retry Clearance Sync**. |
| Channel Sync status is `degraded` or `error` | Upstream Airbnb iCal feed returned non-200 HTTP status or timed out (existing cached blocks are retained on `degraded`). | Inspect `/home/<user>/logs/channel_sync.log` and `public_html/cache/channel_sync_status.json`, then run `php scripts/sync-channels.php --force`. |
| Public API rate-limit lockout (`HTTP 429`) | IP exceeded 10-minute sliding window in `sys_get_temp_dir()`. | Flush temporary rate-limit files: `rm -f /tmp/ovf_*_rate_limits.json`. |
| Admin Console clean URLs return Apache 404 | Missing `admin/public/.htaccess` or wrong cPanel subdomain `DocumentRoot`. | Verify `DocumentRoot` is `/home/<user>/admin/public` and `admin/public/.htaccess` is present. |
| Encrypted `guests_payload` cannot be decrypted | Missing or mismatched `APP_ENCRYPTION_KEY` between `public/.htaccess` and `admin/public/.htaccess`. | Ensure identical `APP_ENCRYPTION_KEY` secret is deployed to both pipelines. |
