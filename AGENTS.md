# OceanViewFlats Agent Guidelines

## Architecture & Development Rules

1. **Theme & Config Centralization (`src/constants/theme.ts`, `src/constants/config.ts`)**:
   - Centralize style classes, layout flags, imagery arrays, and endpoint constants in `src/constants/`.
   - Keep React components (`src/components/`, `src/pages/`) 100% style-agnostic without property-conditional layout ternaries.

2. **Multilingual Zero-Hydration Seam ([ADR 0010](docs/adr/0010-multilingual-page-hydration-seam.md))**:
   - Define all UI translations across all 6 locales (`en`, `es`, `fr`, `it`, `de`, `ja`) in `src/i18n/dict.ts` and backend/email strings in `public/api/translations.php`.
   - Expose client-side strings via scoped slices in `src/config/pages.ts`, serialized at build time into `<script id="ovf-page-config" type="application/json">` (`src/templates/base.ts`).
   - In Vanilla JS (`public/js/`), read translations exclusively via `window.t(key, params, fallback)` and runtime paths (`lang`, `assetPrefix`, `apiBase`) via `window.getPageConfig()` (`public/js/page-config.js`). Never hardcode text literals, embed inline translation dictionaries, or scrape `data-msg-*` attributes.

3. **Relative Asset Prefix Propagation**:
   - Pass the build-time `assetPrefix` prop (`./` or `../`) through React components and prepend it to every local asset or page link so static builds work at root and nested depths.

4. **On-Demand Third-Party SDK Loading**:
   - Omit external SDK scripts (e.g., Mercado Pago `sdk.mercadopago.com/js/v2`) from global HTML templates (`src/templates/base.ts`). Load them asynchronously via Promises in `public/js/main.js` only on active user intent.

5. **Centralized Database Migrations (`scripts/schema.sql`, `scripts/migrate.php`)**:
   - Never run `ALTER TABLE` or `CREATE TABLE` inside runtime HTTP endpoints. Define schema changes in `scripts/schema.sql` and `OceanViewFlats\Domain\Database\MigrationRunner`, executed via `php scripts/migrate.php`.

6. **Hexagonal Domain & Privacy Invariants**:
   - Route reservation holds, confirmations, manual/external onboarding, and cancellations/refunds through `ReservationLifecycleEngine` ([ADR 0011](docs/adr/0011-reservation-lifecycle-engine.md)); execute external Mercado Pago refunds before acquiring database locks.
   - Enforce Colombian Law 1581 of 2012 ([ADR 0012](docs/adr/0012-personal-data-protection-and-ley-1581-compliance.md)): require explicit `Personal Data Authorization` (and `Minor Guardian Attestation` when `age < 18` or minor document type), encrypt `guest_registries.guests_payload` at rest via `PayloadEncryptor`, mask PII in secondary logs/emails via `PiiMasker`, and generate cryptographically random door PINs (`0XXXXXX#` via `DoorCodeGenerator`).

## Verification Gates

Run the relevant verification suite before marking any task complete:
- **Frontend & SSG**: `npm test && npm run typecheck && npm run lint && npm run build`
- **Backend & Domain**: `composer test && composer phpstan`
- **Admin Console (Level 8)**: `composer admin:test && composer admin:phpstan`
- **OpenAPI Spec** (when modifying `public/api/`): `composer generate-docs`

## Context Pointers

- **Domain Glossary**: Read [`CONTEXT.md`](CONTEXT.md) for canonical ubiquitous language and forbidden synonyms (`docs/agents/domain.md`).
- **Architecture Decisions**: Read [`docs/adr/`](docs/adr/) (`0001`–`0013`) before modifying domain rules, fulfillment, privacy, or financial/parafiscal logic.
- **Operations & Deployment**: Read [`docs/operations/administrator-operational-guide.md`](docs/operations/administrator-operational-guide.md) when working on cPanel Pattern A topology, CI/CD secrets, cron jobs, or CLI scripts.
- **Issue Tracker & Triage**: Use `gh` CLI against `fractalsoftware/oceanviewflats` per [`docs/agents/issue-tracker.md`](docs/agents/issue-tracker.md) and [`docs/agents/triage-labels.md`](docs/agents/triage-labels.md).
