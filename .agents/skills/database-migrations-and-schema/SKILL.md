---
name: database-migrations-and-schema
description: Best practices for managing SQL relational schemas, table optimizations, double-charge idempotency keys, and CLI migrations.
---

# Database Schema & Migration Standards

Rules for managing MySQL InnoDB tables, indexes, and schema migrations across local, CI, and production environments.

## 1. Centralized CLI Migrations (No Runtime DDL)

Never execute `ALTER TABLE` or `CREATE TABLE` inside runtime HTTP endpoints (`public/api/*.php` or `admin/`).
- **Master Schema**: Declare all tables, columns, foreign keys, and indexes in `scripts/schema.sql` using `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- **Self-Healing Runner**: Implement idempotent table/column/index checks in `OceanViewFlats\Domain\Database\MigrationRunner` (`src/Domain/Database/MigrationRunner.php`), invoked via `php scripts/migrate.php`.

## 2. Core Schema Conventions (13 Tables)

- **Availability & Lookup Indexes**: Always index `(property_id, check_in, check_out)` / `(property_id, start_date, end_date)` and status filter columns.
- **Payment Idempotency (`payment_idempotency`)**: Query `idempotency_key` before dispatching payment or refund mutations to short-circuit duplicate requests.
- **Ley 1581 Data Protection ([ADR 0012](../../../docs/adr/0012-personal-data-protection-and-ley-1581-compliance.md))**:
  - `guest_registries.guests_payload` (`JSON NOT NULL`) stores XSalsa20-Poly1305 encrypted envelopes via `PayloadEncryptor` (`APP_ENCRYPTION_KEY`).
  - `condominium_clearances.request_payload` and `admin_audit_logs` store masked document numbers and phones (`PiiMasker`).
  - Financial and occupancy columns on `reservations` (`total_price`, `refunded_amount`, `check_in`, `check_out`, `property_id`, `source`, `status`) must remain intact when `PdoPiiRedactionService` scrubs PII (`pii_redacted_at`).
- **FONTUR Parafiscal Sync ([ADR 0013](../../../docs/adr/0013-fontur-parafiscal-contribution-and-expense-sync.md))**:
  - `parafiscal_settlements` links 1-to-1 with `operational_expenses` via `operational_expense_id` (`ON DELETE SET NULL`) and enforces `UNIQUE KEY uniq_pfs_property_period (property_id, period_key)`.
