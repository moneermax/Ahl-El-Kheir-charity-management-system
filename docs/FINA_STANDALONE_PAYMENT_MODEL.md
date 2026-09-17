# Fina Al-Khair — Standalone Payment Model

## Business rule

100% of money entered through the dedicated Fina payment workflow belongs to **فينا الخير**. Ahl El Kheir is collecting/holding the money on Fina's behalf.

Fina money is therefore not Ahl El Kheir revenue, sponsorship revenue, or administrative-fee revenue. It must not be used to absorb a shortfall in a sponsor's normal sponsorship obligation.

## Data isolation

Fina uses its own data model:

- `fina_sources` — the source of Fina money.
- `fina_collections` — the actual Fina collection records.

A Fina source may be:

- an existing Ahl El Kheir sponsor;
- an external individual;
- an organization/company;
- another external source.

When the source is an existing sponsor, `fina_sources.sponsor_id` stores the relationship to the existing sponsor. Sponsor name, phone, alternate phone, email, and address are read from the existing `sponsors` record; they are not duplicated into the Fina source record.

For an external source, the Fina source record stores its own contact and identification information. This keeps external Fina data separate from the normal sponsor database.

The Fina collection does not use `sponsor_payments` and does not create a normal sponsorship transaction. Its accounting relationship is through the dedicated Fina journal/control flow.

## Schema lifecycle

The standalone Fina schema is provisioned through the explicit migration:

`database/migrations/2026-09-17_fina_standalone_schema.sql`

Normal Fina web requests no longer create `fina_sources` or `fina_collections`. The shared helper `fina_ensure_tables()` only verifies that both tables exist and raises an application error if the migration has not been applied.

This follows the established project rule that application pages must not execute request-time `CREATE TABLE` or `ALTER TABLE` operations. Schema lifecycle belongs to migrations/deployment, not normal page rendering.

## Entry point

All users authorized by the application to directly enter sponsor-facing payments may use:

`modules/transactions/fina_payment_create.php`

The current authorization set is:

- admin
- accountant
- accountant_staff
- financial_manager
- supervisor
- vice_general_manager

The Supervisor is a primary operational user of this workflow.

When **كفيل من أهل الخير** is selected as the source, the sponsor selector auto-populates the sponsor's existing information into read-only fields to make data entry faster and prevent duplicate typing.

## Review

Fina submissions are reviewed through:

`modules/accounting/fina_payment_review.php`

The review/approval stage remains controlled by the financial-management workflow. Approval creates the standalone Fina accounting journal and records its journal ID on the Fina collection.

The review page uses separate POST branches for approval and return. The redirect after each mutation is scoped inside the corresponding POST branch; a normal GET request does not redirect back to itself. The Supervisor remains read-only because mutation branches require the FM review authorization check.

The receipt attachment is served through the authenticated `modules/accounting/fina_receipt.php` endpoint rather than by exposing `storage/receipts` directly. This preserves the storage directory's deny rule while enforcing application authentication and Supervisor ownership checks.

## Accounting treatment

For a Fina-only collection of X:

- Debit the asset account corresponding to the actual payment method.
- Credit Fina's dedicated liability/control account **2300** for X.
- Ahl El Kheir revenue: 0.
- Ahl El Kheir administrative fee: 0.
- Sponsorship obligation: 0.

Any future remittance/settlement to Fina is a separate controlled event that reduces the Fina liability; it is not an Ahl El Kheir expense.

The current journal schema does not carry a dedicated currency column. Therefore, the Fina collection retains its currency, while the accounting journal records the numeric amount and includes the currency code in the journal description.

## Separation from normal sponsor payments

Normal sponsor/sponsorship payments continue through the existing payment workflow. A sponsor obligation shortfall remains a sponsor-accounting issue and must never be reclassified as Fina money merely to balance the transaction.

## UI direction

The Fina payment entry page should be surfaced from dashboards used by the authorized sponsor-facing roles, with the Supervisor dashboard treated as the primary operational entry point.

The existing Fina review page remains the review/history surface rather than introducing a second entry mechanism.

## 2026-09-17 implementation checkpoint

### Schema lifecycle hardening

The Fina standalone tables were moved from request-time schema creation to an explicit database migration:

- `database/migrations/2026-09-17_fina_standalone_schema.sql`
- `modules/accounting/fina_lib.php` no longer executes `CREATE TABLE IF NOT EXISTS` during normal requests.
- `fina_ensure_tables()` now performs a read-only existence check against `information_schema.tables` and reports a clear setup error if the migration has not been applied.

Implementation commits:

- `59fd2e3717e627a4f80b47fcedb32e27800cb60e` — add standalone Fina schema migration.
- `5e11531c7a9c75b24ef1c08d7be9138719886b35` — remove request-time Fina schema creation.

### Receipt protection regression

The Fina review receipt link was corrected to use the authenticated `modules/accounting/fina_receipt.php?id=...` endpoint instead of linking directly into `storage/receipts`. The user runtime-tested the corrected link and confirmed the receipt opens successfully.

Implementation commit:

- `1080367531c037600141be3d3d97b8f104eeae10` — fix Fina review receipt link to authenticated viewer.

The storage `.htaccess` deny rule remains intact; it must not be weakened merely to make receipt links work.

## Verification status

- Standalone Fina data model: implemented and preserved.
- Fina review/approval separation: preserved.
- Fina accounting isolation through liability account `2300`: preserved.
- Direct receipt exposure: prevented; authenticated receipt viewer is runtime-confirmed working.
- Request-time Fina schema mutation: removed in code; migration added.

The next audit task remains the targeted Accounting Journal Cross-Module Integrity review for the protected automated reference types `payroll`, `disbursement_void`, and `item_return`. Do not restart the completed Supervisor ↔ Accounting integration audit or repeat closed Fina receipt/authorization tests unless new regression evidence appears.
