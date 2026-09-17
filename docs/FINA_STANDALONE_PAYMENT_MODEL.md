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

## Currency policy — SDG only

The application uses one system-wide currency:

- `APP_CURRENCY_CODE = 'SDG'`
- `APP_CURRENCY_NAME_AR = 'الجنيه السوداني'`
- `APP_CURRENCY_SYMBOL = 'ج.س'`

Users must not select or enter a currency on the Fina collection entry form. The server uses `APP_CURRENCY_CODE` when creating the collection.

The database field `fina_collections.currency_code` remains intentionally present because it preserves the currency attached to each historical/accounting collection record. Removing the field from the UI does not mean removing historical currency evidence from the database.

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

## Entry form UI standard

The Fina collection entry form no longer exposes a redundant currency field because currency is system-wide SDG.

The form also follows the current UI direction for practical data entry:

- label + field are horizontally aligned where practical;
- short fields such as phone, date, amount, and IDs use compact widths appropriate to their data;
- longer fields such as address, purpose, source details, and description receive more width;
- the layout remains responsive on small screens.

Implementation commits:

- `3d84d57e2de0acb3fefb1fe4fdcc2ed9c4b703c9` — remove redundant Fina collection currency field.
- `e2ea1ab741da709e6b5543c85556fcbc7a15c765` — improve Fina collection form field layout and sizing.

This UI standard should be applied carefully to forms touched during future work; it is not permission to blindly redesign unrelated forms.

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

## Notifications

Fina submission notifications use the existing shared notification workflow. The recipient must be the appropriate active Financial Manager reviewer; Supervisor must not receive a self-notification merely because the Supervisor submitted the collection.

The shared notification widget polls every 5 seconds. New notifications are intended to produce both the unread bell indicator and a small right-corner toast without requiring page refresh or logout/login.

A live-toast regression was identified on 2026-09-17: the polling widget attempted to call `window.AKNotify.toast()` but no toast implementation was present in the current repository. The shared widget was corrected to provide its own lightweight `AKNotify.toast()` implementation and restore the right-corner pop-up behavior without changing notification storage, recipient rules, or accounting logic.

Implementation commit:

- `e248ae74f6a69ea69ec1781df63a42b09cbd9699` — restore live right-corner notification toast.

Runtime verification of the restored toast remains the immediate user test before the next chat/session.

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

### Supervisor dashboard placement

The Supervisor dashboard's Fina section has been moved below the four primary action cards, in the requested order:

1. Sponsors — الكفلاء
2. Families — الأسر
3. Sponsorships — الكفالات
4. Fina collection entry — تحصيل فينا الخير
5. Fina statistics and links — إحصاءات تحصيل فينا الخير الخاصة بك

The Fina statistics remain restricted to the logged-in Supervisor's own `fina_collections`, and the three existing review/history/report links were preserved. No duplicate Fina workflow or new intake was introduced.

Implementation commit:

- `d5ad2075a2f4791d016685b6a90f2a0d3f68e012` — move Fina section below Supervisor action cards.

## Verification status

- Standalone Fina data model: implemented and preserved.
- Fina review/approval separation: preserved.
- Fina accounting isolation through liability account `2300`: preserved.
- Direct receipt exposure: prevented; authenticated receipt viewer is runtime-confirmed working.
- Request-time Fina schema mutation: removed in code; migration added.
- Supervisor Fina ownership filtering: preserved.
- Supervisor Fina dashboard placement: implemented; runtime visual confirmation remains the UI check for the latest layout-only change.
- Fina entry currency selection: removed; server uses system-wide SDG.
- Fina entry form: compact horizontal label/field layout implemented; runtime visual confirmation remains pending.
- Live notification toast: code fix implemented; runtime confirmation remains pending.

The next audit task after these immediate UI/runtime checks is the targeted Accounting Journal Cross-Module Integrity review for the protected automated reference types `payroll`, `disbursement_void`, and `item_return`. Do not restart the completed Supervisor ↔ Accounting integration audit or repeat closed Fina receipt/authorization tests unless new regression evidence appears.
