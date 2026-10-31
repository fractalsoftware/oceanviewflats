# FONTUR Parafiscal Contribution Calculation and Bidirectional Operational Expense Synchronization

## Status

Accepted

## Context

OceanViewFlats operates two tourist housing properties in Santa Marta, Colombia under separate National Tourism Registry (*Registro Nacional de Turismo*) licenses: Property `1606` (`RNT #270413`) and Property `1707` (`RNT #268781`). Under Colombian Law 1101 of 2006 and Law 2068 of 2020, operators of tourist housing (*vivienda turística*) must liquidate and pay the **Parafiscal Contribution** (*Contribución Parafiscal para la Promoción del Turismo*) administered by FONTUR at **2.5 per thousand (0.25%)** of operational accommodation revenue on a quarterly cadence (`Q1`–`Q4`), due within the first 20 Colombian business days of the month following the close of each quarter.

Two structural constraints exist in the persistence model:
1. The `reservations` table stores a single `total_price` and `refunded_amount` without itemized per-reservation `cleaning_fee` and `resort_fee` columns. Direct reservations (`web` and `direct_manual`) incorporate flat per-stay Cleaning Fee and Resort Fee surcharges from `PropertyRatesConfig` (`100,000 COP` total for Property `1606`; `120,000 COP` total for Property `1707`), whereas External OTA (`airbnb`) reservations record the net host payout.
2. The `operational_expenses` table enforces `amount > 0.00` in Settlement Currency (COP) and attributes expenses to reporting windows by `expense_date`, whereas FONTUR requires quarterly declarations per RNT even in zero-revenue quarters (*declaración en cero*).

## Decision

1. **Per-Property Quarterly Assessment & Parafiscal Taxable Base**:
   - **Parafiscal Settlements** are evaluated and tracked independently per **Property** (`1606` → `RNT #270413`, `1707` → `RNT #268781`) across calendar quarters (`YYYY-Q1` through `YYYY-Q4`), alongside a live calculation for whatever custom filter window is active in the **Financial Report**.
   - Rather than backfilling historical surcharge columns onto `reservations`, the **Parafiscal Taxable Base** is derived deterministically from confirmed/concluded commercial Reservations (excluding `owner_stay` and excluding cancelled-stay **Policy Retention**):
     - For Direct Reservations (`web` and `direct_manual`), the Property's configured flat `Cleaning Fee + Resort Fee` (`PropertyRatesConfig`) is deducted from `netContract` prior to accrual night proration (`max(0.0, (netContract - flatSurcharges) * (inPeriodNights / totalNights))`).
     - For External OTA Reservations (`airbnb`), 100% of the prorated net host payout is included.
   - The **Parafiscal Contribution** is assessed at `0.25%` (`0.0025`) of the Parafiscal Taxable Base and rounded to the nearest unit (`1 COP`).

2. **Statutory Deadline & Dashboard Hub Operational Alert**:
   - Each **Parafiscal Period** deadline is computed as the 20th Colombian business day (Monday–Friday, excluding Colombian statutory holidays) of April (`Q1`), July (`Q2`), October (`Q3`), and January (`Q4`).
   - Once a calendar quarter closes and its **Parafiscal Settlement** for a Property remains `pending`, an **Operational Alert** is surfaced on both the **Dashboard Hub** and the **Financial Report** (`WARNING` severity upon quarter close, escalating to `CRITICAL` within 7 calendar days of the due date or when overdue) and clears automatically once settled.

3. **Immutable Settlement Snapshot & Bidirectional `Operational Expense` Synchronization**:
   - Settling a quarter captures an immutable snapshot of the settled amount (defaulting to the calculated 0.25% rounded to the nearest `1 COP`, editable by the Admin User), payment date, and optional FONTUR filing reference. If underlying reservations change retroactively after settlement, the settled record and its linked expense remain untouched while the UI surfaces a recalculation variance indicator.
   - When `settled_amount >= 1 COP`, a `taxes_insurance` **Operational Expense** is automatically created (or updated) for that Property with `expense_date = payment_date` and linked 1-to-1 via `parafiscal_settlements.operational_expense_id`. Zero-revenue declarations (`settled_amount = 0 COP`) are recorded as `settled` without inserting a `$0` row into `operational_expenses`.
   - Reverting a settlement or deleting its linked **Operational Expense** synchronizes both tables atomically and records all transitions (`parafiscal_settled`, `parafiscal_updated`, `parafiscal_reverted`) in `admin_audit_logs`.
