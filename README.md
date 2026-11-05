# OceanViewFlats

Direct booking platform, guest fulfillment engine, and administrative property management system (PMS) for OceanViewFlats' beachfront short-term rental properties (`1707` — RNT #268781, and `1606` — RNT #270413) in Playa Salguero, Santa Marta, Colombia.

---

## Architecture Overview

OceanViewFlats is built as a three-layer hybrid platform:

1. **Public Portal (`oceanviewflats.com`)**: Zero-hydration **React 19 Static Site Generator (SSG)** (`render.tsx`), **Tailwind CSS v4** (`vite.config.ts`), lightweight Vanilla JS (`public/js/`), and a hardened PHP 8.3 REST API (`public/api/`).
2. **Admin Console (`admin.oceanviewflats.com`)**: Subdomain-isolated, server-rendered PHP 8.3 + **HTMX** application (`admin/`) deployed via **cPanel Pattern A** (`DocumentRoot` at `/home/<user>/admin/public`, keeping controllers, views, domain logic, `vendor/`, and `scripts/` outside the public web root) ([ADR 0005](docs/adr/0005-admin-interface-architecture-and-subdomain-isolation.md)).
3. **Shared Core Domain & Hexagonal Infrastructure (`src/Domain/`, `src/Infrastructure/`)**: Pure, framework-agnostic PHP domain services and hexagonal ports/adapters shared by both the public REST API and the Admin Console.

### Core Domain Capabilities

- **Authoritative Quote Engine ([ADR 0004](docs/adr/0004-authoritative-quote-engine-and-itemized-pricing.md))**: Evaluates night-by-night **Seasonal Rate Tiers** (`PdoRateSource` with `CsvRateSource` fallback), enforces minimum stays, and itemizes per-property **Cleaning Fees** and **Resort Fees** in **Settlement Currency** (`COP`).
- **Reservation Lifecycle Engine ([ADR 0011](docs/adr/0011-reservation-lifecycle-engine.md))**: Hexagonal engine (`ReservationLifecycleEngine`) coordinating concurrency-safe checkout holds (30-min **Standard Hold Window**; 72-hour **Voucher Hold Window** per [ADR 0003](docs/adr/0003-extended-hold-window-for-cash-vouchers.md)), **Direct Reservations** (`ovf_*`), **Manual Reservations** (`res-man-*`), **External Reservations** (`res-abnb-*` with **Channel Block Absorption** per [ADR 0007](docs/adr/0007-external-platform-reservations-and-guest-onboarding.md)), pre-transaction Mercado Pago **Gateway Refunds**, and **Resurrection Defense**.
- **Mandatory Guest Registry & Access Gating ([ADR 0001](docs/adr/0001-mandatory-registry-before-access.md), [ADR 0009](docs/adr/0009-concluded-reservation-credential-and-metadata-redaction.md))**: **Access Credentials** (cryptographically random `0XXXXXX#` smart-lock PINs, Wi-Fi, and **Assigned Parking Space** Bay 87 / Bay 95) are strictly withheld until the **Guest Registry** is completed, and automatically redacted once a stay becomes a **Concluded Reservation** (after 23:59:59 COT on the **Check-out Date**).
- **Automated Condominium Clearance Sync ([ADR 0008](docs/adr/0008-automated-condominium-clearance-sync.md))**: Out-of-band two-step integration (`HuespedManagerClearanceSync`) that sanitizes and transmits registered occupant rosters and vehicle plates to the Edificio Salguero Sunset **Condominium Administration Portal** (Huésped Manager), recording the issued **Condominium Clearance Number** (`consecutivo`) with 1-click admin retry and **Operational Alert** surfacing.
- **Personal Data Protection — Ley 1581 of 2012 ([ADR 0012](docs/adr/0012-personal-data-protection-and-ley-1581-compliance.md))**: Multilingual `/privacy` **Data Processing Policy**, verifiable **Personal Data Authorization** and **Minor Guardian Attestation**, XSalsa20-Poly1305 encryption at rest for `guest_registries.guests_payload` (`PayloadEncryptor`), PII masking across secondary logs/emails (`PiiMasker`), and financial-safe **PII Redaction** (admin ARCO action + 90-day post-checkout CLI runner).
- **Financial Report & FONTUR Parafiscal Compliance ([ADR 0013](docs/adr/0013-fontur-parafiscal-contribution-and-expense-sync.md))**: Portfolio and per-property analytics (**Gross Revenue**, **Operational Expenses**, **Net Operating Income**, **Average Daily Rate**, **RevPAR**, **Occupied Nights**) plus quarterly **Parafiscal Contribution** calculation (0.25% of **Parafiscal Taxable Base** per RNT), Colombian statutory 20-business-day deadlines, and bidirectional synchronization between **Parafiscal Settlements** and `taxes_insurance` **Operational Expenses**.
- **Multilingual Zero-Hydration Seam ([ADR 0010](docs/adr/0010-multilingual-page-hydration-seam.md))**: Pre-compiles 6 locales (`en`, `es`, `fr`, `it`, `de`, `ja`) with JSON-LD structured data, `hreflang` alternates, and scoped `#ovf-page-config` JSON payloads consumed at runtime via `window.t()` and `window.getPageConfig()`.

---

## Project Layout

```text
├── .agents/skills/             # Specialized engineering agent skills
├── .github/workflows/          # CI (ci.yml, admin-ci.yml) & SFTP Deploy (deploy.yml, admin-deploy.yml)
├── admin/                      # Subdomain-isolated Admin Console (admin.oceanviewflats.com)
│   ├── public/                 # Pattern A DocumentRoot (index.php, setup.php, .htaccess)
│   ├── src/                    # AdminApp kernel, Controllers, Repositories, Auth/CSRF Middleware, HTMX Views
│   └── tests/                  # Admin PHPUnit test suite (PHPStan Level 8)
├── docs/
│   ├── adr/                    # Architecture Decision Records (0001–0013)
│   ├── agents/                 # Agent conventions (domain, issue-tracker, triage-labels)
│   └── operations/             # Production deployment & administrator operational runbook
├── public/                     # Static assets, Vanilla JS, and public PHP REST API (copied to dist/)
│   ├── api/                    # Public REST endpoints (quote, availability, book-request, payment,
│   │                           #   mercadopago-webhook, registry-lookup, registry-processor,
│   │                           #   guide-access, contact-processor, ical, openapi.json/yaml)
│   ├── data/prices.csv         # Seed seasonal pricing tiers
│   └── js/                     # Client scripts (page-config.js, main.js, registry.js, guide.js, lang-detect.js)
├── scripts/                    # CLI operational utilities & master SQL schema
│   ├── schema.sql              # Authoritative 13-table MySQL InnoDB schema
│   ├── migrate.php             # Self-healing CLI database migration runner
│   ├── create-admin-user.php   # CLI Argon2id administrator provisioning & unlock utility
│   ├── sync-channels.php       # CLI inbound iCal channel sync runner (15-min cron)
│   ├── redact-expired-pii.php  # CLI 90-day post-checkout Ley 1581 PII redaction runner
│   └── generate-docs.php       # OpenAPI 3.0 specification generator
├── src/
│   ├── Domain/                 # Pure PHP domain logic (Access, Database, Finance, Fulfillment,
│   │                           #   Payment, Privacy, Quote, Reservation, Security, Support)
│   ├── Infrastructure/         # Hexagonal persistence, refund, audit, and event adapters
│   ├── components/ & pages/    # Build-time React 19 UI components & page views
│   ├── config/pages.ts         # Route definitions, SEO metadata, JSON-LD, & client i18n slices
│   ├── constants/              # Centralized theme tokens (theme.ts) & endpoint configs (config.ts)
│   ├── i18n/dict.ts            # 6-language UI translation dictionary
│   └── templates/base.ts       # Master HTML shell & #ovf-page-config serializer
├── tests/                      # Domain, Infrastructure, API, and SSG test suites
├── CONTEXT.md                  # Canonical domain glossary (ubiquitous language)
├── AGENTS.md                   # Coding rules & workflow instructions for AI agents
└── render.tsx                  # Static Site Generator compiler entry point
```

---

## Development & Verification

### Prerequisites

- **Node.js** >= 24 (`npm`)
- **PHP** >= 8.3 (`pdo_mysql`, `sqlite3`, `mbstring`, `sodium`, `curl`, `json`, `argon2`) & **Composer**

### Setup & Build (Local Host)

```bash
# Install dependencies
npm ci
composer install

# Provision / update local MySQL schema (13 tables)
php scripts/migrate.php

# Compile static site (HTML, cache/prices.json, sitemap.xml, Tailwind CSS v4, Terser JS) to dist/
npm run build
```

### Run Locally with Docker

```bash
# Build and start the Public Portal, Admin Console, and MySQL 8.4 database
docker compose up --build -d
```

- **Public Portal & REST API**: `http://localhost:8080`
- **Admin Console**: `http://localhost:8081` (or `http://admin.localhost:8080`)
  - Default local credentials: `admin@oceanviewflats.com` / `AdminOceanView123!` (configurable via `ADMIN_EMAIL` and `ADMIN_PASSWORD`)
- **MySQL 8.4**: `localhost:3306` (`oceanviewflats_db`, auto-migrated on startup)

### Quality Gates & Tests

```bash
# Frontend checks (SSG page-config tests, TypeScript typecheck, Biome lint)
npm test
npm run typecheck
npm run lint

# Backend & Domain checks (PHPUnit & PHPStan)
composer test
composer phpstan

# Admin Console checks (PHPUnit & PHPStan Level 8)
composer admin:test
composer admin:phpstan

# Regenerate OpenAPI specification (public/api/openapi.json & openapi.yaml)
composer generate-docs
```

---

## Environment Variables (`.env`)

Copy `.env.example` to `.env` for local development. In production, CI workflows inject `SetEnv` directives into `public/.htaccess` and `admin/public/.htaccess`, and `EnvLoader` resolves them automatically across web, CLI, and cron contexts:

| Variable | Purpose |
| :--- | :--- |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL / MariaDB connection credentials |
| `RECIPIENT_EMAIL` | Host notification mailbox (`rentals@oceanviewflats.com`) |
| `CAPTCHA_SECRET` | HMAC secret for stateless mathematical CAPTCHA signing |
| `APP_ENCRYPTION_KEY` | 32-byte (or 64-char hex) key for XSalsa20-Poly1305 encryption of `guest_registries.guests_payload` ([ADR 0012](docs/adr/0012-personal-data-protection-and-ley-1581-compliance.md)) |
| `OVF_ADMIN_SESSION_SECRET` | 64-char hex secret for Admin Console session signing and `setup.php` authorization |
| `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_SANDBOX` | Mercado Pago Checkout Bricks & server-to-server payment/refund credentials |
| `PROPERTY_1606_DOOR_CODE`, `PROPERTY_1606_WIFI_SSID`, `PROPERTY_1606_WIFI_PASSWORD` | Property `1606` emergency fallback PIN and Wi-Fi credentials |
| `PROPERTY_1707_DOOR_CODE`, `PROPERTY_1707_WIFI_SSID`, `PROPERTY_1707_WIFI_PASSWORD` | Property `1707` emergency fallback PIN and Wi-Fi credentials |
| `HUESPED_MANAGER_BASE_URL`, `HUESPED_MANAGER_1707_CHECK`, `HUESPED_MANAGER_1606_CHECK` | Condominium Administration Portal base URL and per-property security check tokens ([ADR 0008](docs/adr/0008-automated-condominium-clearance-sync.md)) |

---

## Documentation Index

- **[`CONTEXT.md`](CONTEXT.md)**: Canonical domain glossary and ubiquitous language.
- **[`docs/adr/`](docs/adr/)**: Architecture Decision Records (`0001` through `0013`).
- **[`docs/operations/administrator-operational-guide.md`](docs/operations/administrator-operational-guide.md)**: Production deployment, cPanel Pattern A setup, CLI utilities, cron jobs, and troubleshooting runbook.
- **[`AGENTS.md`](AGENTS.md)**: Engineering rules and context pointers for AI coding agents.
