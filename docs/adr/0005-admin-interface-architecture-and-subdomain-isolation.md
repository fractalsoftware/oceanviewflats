# Admin Interface Architecture, Subdomain Isolation, and Dual-Pipeline Deployment (Pattern A)

## Context

The OceanViewFlats platform combines a high-performance, guest-facing direct booking engine with comprehensive property management system (PMS) operations, including reservation ledger management, rate overrides, calendar blocks, and legal guest registry audits. 

Historically, public marketing views have been generated via a static site generator (SSG) compiled to `dist/` and deployed directly to cPanel's public web root (`public_html/`). However, introducing administrative capabilities demanded an architectural design that satisfies four strict criteria:
1. **Security Isolation**: Administrative tools, authenticated session perimeters, and internal business logic must be physically and logically separated from public guest traffic.
2. **Zero Code Duplication**: Core business logic (nightly seasonal quote calculation, calendar availability, reservation ledgers, and dynamic door PIN generation) must remain canonical and shared between guest-facing endpoints and admin views.
3. **Protected Source Topology**: Non-public PHP source code (`admin/src/`, `src/Domain/`), third-party packages (`vendor/`), and infrastructure CLI scripts (`scripts/`) must never be accessible over HTTP.
4. **Decoupled Deployment Lifecycles**: Backend administrative updates or domain model fixes must deploy independently without triggering slow compilation of public marketing pages, and vice versa.

## Decision

We establish a dedicated administrative application served from an isolated subdomain (`admin.oceanviewflats.com`) utilizing **cPanel Pattern A** topology and a **Dual-Pipeline CI/CD architecture**.

### 1. Webroot Topology: Pattern A vs. Pattern B
Rather than nesting the administrative application inside `public_html/admin` (Pattern B), we configure cPanel with Pattern A:
* **Subdomain DocumentRoot**: Explicitly mapped to `/home/<user>/admin/public`.
* **Out-of-Webroot Isolation**: All sensitive application code (`admin/src/`), templates (`admin/templates/`), domain core (`src/Domain/`), Composer dependencies (`vendor/`), and CLI management utilities (`scripts/`) reside directly under `/home/<user>/`, outside all DocumentRoots.
* **Apache Front-Controller**: An `.htaccess` file inside `admin/public/` enforces `DirectoryIndex index.php`, disables directory indexing (`Options -Indexes`), applies strict security headers (`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`), and rewrites all non-file/non-directory HTTP requests through `index.php`.

```text
/home/<user>/
├── public_html/                 <-- DocumentRoot for oceanviewflats.com (Public SSG)
│   ├── index.html
│   ├── css/ & js/
│   └── api/                     <-- Public REST APIs (calls ../vendor/autoload.php)
├── admin/
│   ├── public/                  <-- DocumentRoot for admin.oceanviewflats.com (Pattern A)
│   │   ├── index.php            <-- Administrative Front-Controller
│   │   └── .htaccess            <-- Rewrite & Security Headers
│   ├── src/                     <-- Protected Admin Application Logic
│   └── templates/               <-- Server-rendered PHP Templates (HTMX Components)
├── src/
│   ├── Domain/                  <-- Shared Domain Models (QuoteEngine, Ledger, DoorCode)
│   └── Infrastructure/          <-- Hexagonal Infrastructure Adapters (Persistence, Events, Refunds)
├── vendor/                      <-- Production Composer Autoloader & Dependencies
└── scripts/                     <-- CLI Utilities (migrate.php, create-admin-user.php)
```

### 2. Dual-Pipeline CI/CD Deployment Architecture
Deployments are strictly segregated across two independent GitHub Actions workflows:

1. **Public Static Site Pipeline (`.github/workflows/deploy.yml`)**:
   - Compiles React components and Tailwind CSS into static assets (`dist/`).
   - Injects public environment secrets (`RECIPIENT_EMAIL`, `CAPTCHA_SECRET`, database and payment keys) into `public/.htaccess`.
   - Transfers `./dist/.` to `/home/<user>/public_html/` via SFTP on port 2223.
2. **Admin & Backend Pipeline (`.github/workflows/admin-deploy.yml`)**:
   - Triggers on changes to `admin/**`, `src/Domain/**`, `src/Infrastructure/**`, `scripts/**`, `composer.json`, or `composer.lock`.
   - Installs production Composer dependencies (`composer install --no-dev --prefer-dist --optimize-autoloader`).
   - Injects production secrets (`DB_*`, `MERCADOPAGO_*`, `PROPERTY_*`, `OVF_ADMIN_SESSION_SECRET`) into `admin/public/.htaccess`.
   - Stages a clean payload containing `admin/`, `src/Domain/`, `src/Infrastructure/`, `vendor/`, and `scripts/` (omitting test files and dev configurations).
   - Transfers the payload to `/home/<user>/` via SFTP on port 2223.

### 3. Shared Runtime Autoloading, Path Resilience, and Environment Discovery
* **Single Source of Truth**: The production Composer autoloader at `/home/<user>/vendor/autoload.php` serves both the admin application (`admin/public/index.php`) and public APIs (`public_html/api/*.php`).
* **Resilient Discovery (`ConfigPathResolver`)**: The admin kernel and database migration scripts resolve database configuration dynamically by sequentially checking `/public/api/config.php` (local dev) and `/public_html/api/config.php` (production cPanel), eliminating environment-specific file hacks.
* **Resilient Environment Loader (`EnvLoader`)**: Resolves credentials directly from `.htaccess` and `.env` files for non-web environments (such as CLI terminals and cron jobs) where Apache `SetEnv` variables are not inherited.

### 4. Administrative Security, Setup Gating, and Session Perimeter
* **Authentication**: Password hashes are generated via `PASSWORD_ARGON2ID` with hardened memory and iteration parameters.
* **Session Cookies**: Native PHP sessions are hardened via `SessionMiddleware` (`Secure`, `HttpOnly`, `SameSite=Lax`, name: `ovf_admin_session`) and restricted to the `admin.oceanviewflats.com` subdomain.
* **Brute-Force Defense**: Automated lockout after 5 consecutive failed login attempts tracked in `admin_users.failed_login_attempts` and `admin_users.locked_until`.
* **Immutable Audit Logging**: All mutations (reservation edits, cancellations, calendar holds, door code overrides) are recorded in `admin_audit_logs`.
* **Dual-Method Initial Bootstrap with Permanent Lockout**:
  - **CLI / Cron Provisioner**: `scripts/create-admin-user.php` and `scripts/migrate.php` allow zero-web execution.
  - **Secure Web Setup Utility**: `admin/public/setup.php` allows zero-SSH bootstrapping for restricted cPanel environments, protected by pre-shared token authorization (`OVF_SETUP_TOKEN`, `OVF_ADMIN_SESSION_SECRET`, or `DB_PASS`) and permanent auto-lockout (returns HTTP 403 once an administrator user exists).

### 5. Client-Side Async Feedback & HTMX Request Lifecycle (Dual-Layer Loading Bar & Mutation Locking)

#### Problem Context
Administrative workflows involve frequent asynchronous HTMX operations (filters, navigation, quote previews, mutations) needing clear operator feedback and double-click prevention. In the absence of structured async feedback:
1. Fast sub-150ms requests suffer visual flickering if spinners flash abruptly into view.
2. Slower operations leave operators uncertain if their click registered, prompting impatient double-clicks that risk duplicate transactions or race conditions.
3. Network disconnects or backend 5xx failures leave buttons in a disabled or ambiguous limbo state without actionable guidance.

#### Architecture
We implement a zero-dependency, dual-layer feedback and error-resilient request lifecycle integrated directly into `admin/src/Views/layout.php`:

1. **Global Top Progress Bar (`#global-progress-bar`)**:
   - Pinned to the top of the viewport (`fixed top-0 left-0 h-1 bg-indigo-600 z-50`).
   - Managed by an `activeRequests` counter to smoothly coordinate concurrent operations.
   - Enforces a **150ms debounce threshold** on request initiation to prevent UI flicker on fast sub-150ms operations. Operations extending beyond 150ms smoothly advance through incremental trickle phases (25% -> 60% -> 85% -> 100%) with an animated linear shimmer.
   - Complies with web accessibility standards via `role="status"`, `aria-live="polite"`, `aria-label="Loading"`, and `@media (prefers-reduced-motion: reduce)` animation bypasses.

2. **Immediate 0ms Mutation Lock on Buttons/Submits**:
   - Intercepts mutating verbs (`POST`, `DELETE`, `PUT`) and form submits instantly at the event level (0ms latency prior to network transport).
   - Eliminates duplicate submissions and race conditions by dynamically applying `pointer-events: none !important; opacity: 0.75; cursor: wait;` and `aria-disabled="true"` to trigger buttons and submits.
   - Automatically releases lock states upon request completion (`htmx:afterRequest`), response error (`htmx:responseError`), or network transport failure (`htmx:sendError`).

3. **Asynchronous Error Resiliency (Red Bar Flash & Floating Alert Banner)**:
   - **Rose Progress Bar Flash**: On 5xx server responses (`evt.detail.xhr.status >= 500`) or network drop/timeouts (`htmx:sendError`), the progress bar swaps from `bg-indigo-600` to `bg-rose-500`, animates to 100% width, holds for ~1.2s, and gracefully fades out before resetting color back to `bg-indigo-600` and width to 0%.
   - **Floating Alert Toast**: Injects an assertive toast notification (`role="alert"`, `aria-live="assertive"`) into `#global-alert-container` (`fixed top-4 right-4 z-50 pointer-events-none space-y-2`) styled with Tailwind rose tokens (`bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm p-3.5 rounded-lg shadow-lg flex items-center justify-between space-x-3 pointer-events-auto transition-opacity duration-300`).
   - Displays clear operator guidance: *"Network or server error occurred. Please try again."*
   - Includes an accessible manual close button (`Dismiss alert`) and an automated 4000ms fade-out dismissal.
   - **Automatic Button Unlocking**: Cleanses all mutation locks and re-enables buttons across the DOM on error events so the operator can retry immediately.

#### Compliance with Repository Development Rules
- **Theme Abstractions & Design Tokens**: Uses standard Tailwind CSS palette tokens (`indigo-600`, `rose-500`, `rose-50`, `rose-200`, `rose-800`) without hardcoded ad-hoc CSS hex values in application markup.
- **Client-Side Interactivity Standards**: 100% vanilla JavaScript with zero heavy external spinner or toast libraries, maintaining pristine PageSpeed and minimal bundle footprint.
- **Accessibility (a11y)**: Strict adherence to ARIA standards (`role="status"`, `role="alert"`, `aria-live="assertive"`, `aria-disabled="true"`).

## Consequences

### Positive
- **Guaranteed Source Code Protection**: Physical separation of PHP source code from public webroots eliminates accidental exposure vectors.
- **Fast, Independent Releases**: Content updates to marketing pages deploy in seconds without PHP dependency checks; admin and domain model updates deploy without static site compilation.
- **Zero Business Logic Divergence**: Public booking endpoints and admin PMS tools execute identical domain models (`QuoteEngine`, `ReservationLedger`).
- **Auditability**: Complete operational traceability across all administrative state changes.
- **Hosting Portability**: Works reliably across cPanel shared hosting with or without SSH/terminal access.
- **Operator Confidence & Zero Flicker**: Fast operations execute without distracting spinner flickers, while longer requests provide steady, debounced visual progress.
- **Double-Submit Immunity**: Immediate 0ms button-level mutation locking eliminates duplicate database writes and race conditions during payment settlements or ledger updates.
- **Resilient Error Recovery**: Instant rose bar flash on 5xx or network errors, paired with non-blocking floating alerts and automatic button unlocking, ensures operators are informed and can immediately retry.

### Neutral / Operational Trade-offs
- cPanel subdomain creation requires explicit configuration to avoid defaulting to `public_html/admin`.
- Deployments require setting both `FTP_REMOTE_PATH` and optionally `FTP_ADMIN_REMOTE_PATH` in GitHub repository secrets.
- Database migrations and initial admin setup require an intentional execution step via CLI, cron job, or the one-time `setup.php` web tool.
