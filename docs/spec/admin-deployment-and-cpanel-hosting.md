# Specification: Admin Interface Deployment and cPanel Hosting (Pattern A)

## Problem Statement

The OceanViewFlats administrative interface has been engineered and validated with a comprehensive automated test suite (224 unit, controller, and route tests at PHPStan Level 8), fulfilling the operational requirements of ADR 0005. However, there is currently no deployment pipeline or production server configuration to deploy the admin codebase to production.

The existing CI/CD deployment workflow is designed exclusively for the public-facing React Static Site Generator (SSG), compiling static HTML pages into `dist/` and uploading them to `/home/<user>/public_html` via SFTP. Consequently:
1. The administrative application is completely omitted from production deployments.
2. Production Composer dependencies (`vendor/`) and shared backend packages (`src/Domain/`, `src/Infrastructure/`) are not deployed to the server, which breaks both the admin application and public API endpoints that rely on PSR-4 autoloading.
3. The admin public directory lacks an Apache front-controller rewrite configuration, causing all clean URL navigation (`/reservations`, `/rates`, `/login`) to return 404 errors on Apache.
4. Configuration resolution expects a fixed path relative to repo root which does not account for cPanel's `public_html/` directory naming convention.
5. There is no secure, automated mechanism to provision the initial administrator account with Argon2id password hashing on production.

## Solution

Implement an automated, decoupled deployment pipeline and server runtime configuration for `admin.oceanviewflats.com` on cPanel using Pattern A (DocumentRoot set to `/home/<user>/admin/public`).

This architecture places the web-accessible front-controller strictly within the subdomain's DocumentRoot, while all sensitive application controllers, shared domain business logic, infrastructure adapters, vendor libraries, and database migration scripts reside outside the web root in the user's home directory.

The solution includes:
1. An Apache front-controller configuration with URL rewriting and security headers.
2. Path resilience in configuration discovery supporting both local development and cPanel production layouts.
3. A secure CLI provisioning utility for bootstrapping administrator accounts with Argon2id password hashing.
4. A dedicated GitHub Actions deployment workflow (`.github/workflows/admin-deploy.yml`) that builds optimized production dependencies and deploys the admin subsystem via SFTP independently of public static site builds.
5. Operational runbooks documenting cPanel subdomain configuration, migration execution, and user management.

## User Stories

1. As a system administrator, I want the administrative application deployed to an isolated subdomain (`admin.oceanviewflats.com`), so that administrative sessions and tools are strictly separated from public guest-facing traffic.
2. As a DevOps engineer, I want an independent deployment workflow for the administrative interface, so that modifying admin features or fixing bugs does not trigger slow, unnecessary static site compilation of public marketing pages.
3. As a DevOps engineer, I want public marketing site deployments to remain independent, so that static content releases remain fast and decoupled from backend administrative changes.
4. As a system administrator, I want all HTTP requests on the admin subdomain (e.g. `/reservations`, `/rates`, `/login`, `/calendar-blocks`) to route through the front-controller, so that clean URLs function seamlessly without 404 errors on Apache.
5. As a security engineer, I want `admin/src/`, `src/Domain/`, `src/Infrastructure/`, `vendor/`, and `scripts/` to be stored outside the web server's DocumentRoot, so that raw PHP source code and configuration files cannot be accessed over HTTP.
6. As a system administrator, I want the front-controller and database migration scripts to automatically locate database configuration whether running locally or on cPanel, so that no manual path editing is required across environments.
7. As a property manager, I want a secure CLI command to provision the initial super-admin account with Argon2id password hashing, so that authorized staff can log in to the administrative portal.
8. As a property manager, I want the admin user provisioning tool to be idempotent, so that running it multiple times updates existing records safely without corrupting accounts or creating duplicates.
9. As a security engineer, I want the admin user provisioning tool to enforce CLI-only execution, so that it cannot be triggered through a web browser.
10. As a security engineer, I want the admin user provisioning tool to never output plaintext passwords in terminal output or log files, so that credentials are protected from shoulder-surfing and log indexing.
11. As a system administrator, I want database credentials, payment gateway tokens, and Condominium Administration Portal tokens injected into the admin web environment during deployment, so that sensitive production secrets are never stored in git.
12. As a system administrator, I want production Composer dependencies packaged with `--no-dev --optimize-autoloader`, so that developer test tools are excluded from production and class loading performance is maximized.
13. As a developer, I want public API endpoints (`public_html/api/*.php`) to utilize the production Composer autoloader deployed in the user home directory, so that domain logic is never duplicated between public and administrative runtimes.
14. As an operator, I want clear documentation detailing cPanel subdomain setup, database migration execution, and initial account creation, so that infrastructure maintenance can be executed reliably.
15. As a security engineer, I want strict HTTP security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) enforced on all admin HTTP responses, so that clickjacking, MIME-sniffing, and referrer leakage attacks are mitigated.

## Implementation Decisions

- **Subdomain Webroot Topology (Pattern A)**: The cPanel subdomain `admin.oceanviewflats.com` will point its DocumentRoot to `/home/<user>/admin/public`. The application source code, vendor libraries, domain models, infrastructure adapters, and CLI scripts will reside in `/home/<user>/` outside the web root.
- **Apache Front-Controller Rewrite**: An `.htaccess` file in the admin public directory will direct all non-file, non-directory requests to the front-controller entry point, while enforcing security headers.
- **Resilient Configuration Resolution**: The front-controller and migration scripts will inspect known configuration locations sequentially, resolving either the local development path or the cPanel webroot path without raising unhandled errors.
- **Argon2id CLI Provisioning Utility**: A CLI-exclusive script will accept email, full name, password, and role arguments, validate inputs, hash passwords using `PASSWORD_ARGON2ID` with recommended memory and time cost parameters, and upsert records into the database.
- **Decoupled CI/CD Workflow**: A dedicated GitHub Actions workflow will trigger on changes to admin code, domain models, infrastructure adapters, migration scripts, or Composer dependencies. It will install optimized production dependencies, inject environment secrets into the Apache configuration, stage the deployment payload, and transfer files via SFTP to the cPanel host.
- **Shared Runtime Autoloading**: The production Composer autoloader placed at `/home/<user>/vendor/autoload.php` will serve as the single source of truth for both the admin application and public API endpoints.

## Testing Decisions

- **Test Quality Standard**: Tests must verify observable behavior and security contracts rather than internal implementation details.
- **CLI Provisioning Seam**: A test suite will execute the user creation logic against an in-memory database, verifying exit codes, input validation failure cases, Argon2id hash generation, duplicate handling, and output safety.
- **Configuration Discovery Seam**: Tests will verify that configuration resolution succeeds across both directory layouts.
- **Workflow & Quality Seam**: The workflow must pass all static analysis checks (PHPStan Level 8) and regression test suites prior to deployment execution.
- **Prior Art**: Testing patterns follow the existing administrative test suites and database helpers established in the codebase.

## Out of Scope

- Modifying the public React Static Site Generator build process.
- Modifying guest-facing booking or payment REST APIs.
- Automated remote database migration execution over SFTP (migrations remain an intentional operational step executed via cPanel Terminal or SSH).
- Containerized or Kubernetes deployments.

## Further Notes

- The cPanel server uses port 2223 for SFTP connections.
- The deployment process respects the principle of least privilege by ensuring no write access is granted to web-accessible directories beyond what is required for static assets.
