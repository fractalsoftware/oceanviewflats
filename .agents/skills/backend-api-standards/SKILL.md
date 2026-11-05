---
name: backend-api-standards
description: Enforces secure coding guidelines and architectural standards for OceanViewFlats backend PHP REST APIs. Use when creating, modifying, refactoring, or auditing PHP files inside the public/api/ folder.
---

# Backend API Coding Standards

Guidelines for creating, modifying, and auditing PHP endpoints under `public/api/`.

## 1. Entry-Point Structure & Domain Delegation

Public endpoints under `public/api/` act as thin HTTP controllers that enforce transport security, validate inputs, and delegate business logic to `src/Domain/` and `src/Infrastructure/` (loaded via Composer's PSR-4 autoloader):

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';
$all_translations = require __DIR__ . '/translations.php';
```

## 2. Security & Transport Controls

1. **CORS & Security Headers**: Call `enforce_security_headers_and_cors(['POST', 'OPTIONS']);` before processing requests.
2. **Referer Validation**: Call `enforce_referer_check();` on form processors (`contact-processor.php`, `registry-processor.php`, `book-request.php`).
3. **Input Sanitization**: Sanitize scalar strings via `clean_input()` and validate locales via `$lang = get_validated_lang();`.
4. **Stateless HMAC Math CAPTCHA**: Verify signed challenges via `verify_captcha_challenge(...)` before database writes or email dispatches.
5. **File-Backed Rate Limiting**: Throttle public endpoints via `enforce_rate_limit(RATE_LIMIT_FILE, MAX_SUBMISSIONS, RATE_LIMIT_WINDOW, $t['err_rate_limit']);`.
6. **Prepared Statements & Transactions**: Use `get_db_connection($dbConfig)` with prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`). Never execute external HTTP gateway calls while holding a database transaction lock ([ADR 0011](../../../docs/adr/0011-reservation-lifecycle-engine.md)).

## 3. Privacy & Compliance Invariants

- **Ley 1581 Authorization ([ADR 0012](../../../docs/adr/0012-personal-data-protection-and-ley-1581-compliance.md))**: Reject public form submissions lacking `privacy_consent` (and `minor_guardian_consent` when any Guest has `age < 18` or a minor document type). Record `privacy_policy_version`, `privacy_consent_at`, and `privacy_consent_ip`.
- **Credential & Concluded Stay Gating ([ADR 0001](../../../docs/adr/0001-mandatory-registry-before-access.md), [ADR 0009](../../../docs/adr/0009-concluded-reservation-credential-and-metadata-redaction.md))**: Never expose door PINs or Wi-Fi credentials before `registry_completed = 1`, and return `HTTP 403` (`status: "concluded"`) with zero PII or stay metadata once `$reservation->isConcluded()` is true.

## 4. Verification

After modifying any file in `public/api/`:
1. Run `composer test && composer phpstan`.
2. Regenerate and verify OpenAPI specs (`public/api/openapi.json` and `public/api/openapi.yaml`) via `composer generate-docs`.
