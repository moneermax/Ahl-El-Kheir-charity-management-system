# Employee Incentives — Continuation and Source Audit

**Checkpoint:** 2026-10-14  
**Status:** The incentive policy GM approval workflow and FM activation/cancellation acceptance gates are closed at their tested boundary. The ledger, award accounting helpers, draft payroll integration, and collection source-event hook are committed on `main`; local rollback-only accounting and source-event harnesses passed, including no award before verification and duplicate-event protection. Full lifecycle coverage remains incomplete. Durable win-back reactivation attribution and its atomic transition passed local rollback-only acceptance on 2026-10-14. The reactivation-payment qualification helper, recipient snapshots, and confirmed-payment wiring also passed the user-reported local rollback-only harnesses. The win-back UI explicitly selects the actual reactivating employee and separately records the confirmer. The new-sponsor first-payment hook and rollback-only harness are committed, with local acceptance pending; project-completion source/allocation, HR Manager verification/award issuance, employee notifications, and end-to-end lifecycle acceptance remain open.

## Scope

Three policy-driven, fixed-amount incentive categories:

1. Monthly sponsorship collection: reward the employee/collector after the linked collection is financially confirmed.
2. New sponsor or sponsor reactivation: reward only after sponsorship is established and its first payment is financially confirmed. Reactivation uses the agreed 90-day lapse lookback.
3. Successful project completion: reward after both administrative project closure and FM financial closure, with an explicitly approved allocation among named eligible team members.

The reward amount and currency are policy data configured when a policy version is created. **10,000 SDG was only an example, not an approved amount.** The monthly collection reward is not percentage-based.

## Agreed policy decisions

- Governance: GM approves the incentive policy; FM validates financial controls; HR administers it.
- Collection recipient: the recorded collector.
- Sponsor acquisition/reactivation recipient: the employee who brought the sponsor, resolved through the actual user-to-employee relationship.
- Project acceptance: administrative closure plus FM financial closure.
- Project rewards: fixed amount split according to an explicitly approved allocation for named eligible team members; do not auto-reward the project closer.
- Employment eligibility: preserve rewards already earned and route them through the approved process; block new awards when an employee is ineligible.
- Currency: only schedule payment when reward currency matches the employee's applicable salary currency. Otherwise hold it; do not invent FX.
- Reversal/reopening: retain the original reward and use controlled review/adjustment. Never silently delete or automatically cancel the historical reward.
- Expense account: use a dedicated incentive expense account created through the chart-of-accounts process; do not reuse account 5200 without explicit accounting approval.
- Payroll cutoff: next eligible payroll period that is still open for inclusion.
- Verification: manager verification for achievements, with separate financial confirmation where applicable.
- Employee notification must distinguish earned, scheduled, and paid states, and is sent only after the durable state change.

## Confirmed source integration points

### Sponsorship collections

`modules/accounting/fm_review_queue.php` approves a pending `sponsor_payments` row inside a database transaction, creates a posted `transactions` row, posts its journal, links the transaction, and commits. A reward event must be idempotent and based on the financially confirmed linked event—not on the FM user who approved it.

`sponsor_payments.supervisor_id` identifies the collector by **user ID**. `transactions.created_by` identifies the approving user and is not the collector. A linked `sponsor_payments.transaction_id` / transaction must not produce two awards.

### Sponsor acquisition/reactivation

`modules/administration/winback.php` marks a sponsor active and closes the win-back campaign when `mark_returned` is submitted. This is not sufficient for earning the reward because payment has not necessarily occurred yet. `sponsors.brought_by_user_id` is a user ID; any employee attribution must resolve the linked employee and validate eligibility. A confirmed first payment is the earning boundary.

### Project completion

`modules/projects/view.php` handles `close_project`, requires a pending closure request and no draft/submitted/approved project expenses, then records administrative closure in `project_lifecycle` and audit history. It notifies FM that financial closure is required.

`modules/projects/project_funding_accounting.php` and `modules/projects/view_fm.php` implement the separate FM financial-closure path. The project incentive must not be earned at administrative closure alone; the final durable FM closure event is the relevant boundary. Reopening needs a controlled adjustment workflow.

### Payroll/accounting

- `modules/hr/payroll.php` generates payroll drafts and refreshes draft salary components. It also has an `update_status` action that calls `hrPayrollChangeStatus()`; `approve_all` is a separate period-wide action.
- `modules/hr/lib_payroll_accounting.php` protects paid payroll from mutation and posts the payroll journal. Its current expense reconciliation is `net_salary + salary_advance_deduction`; salary-advance recovery credits account 1410, while the current expense line uses account 5200.
- `payroll` has no incentive attribution/detail field and no currency field. `hr_employee_salary_history.salary_currency` is the existing source for applicable employee salary currency.
- A generic allowance alone would lose the connection between each earned reward, policy version, achievement/source event, payroll inclusion, and accounting posting. The implementation therefore needs a dedicated incentive ledger/detail model and explicit journal reconciliation. Paid payroll must remain immutable.

## Schema/backup evidence

The latest repository backup commit is `de397397432797c674a9bf06133e90d57bffb5b7` (2026-10-12, message `data base back up`). It updates `database/ahl_el_kheir.sql` from a MariaDB 10.4.32 snapshot. The dump is a data-bearing snapshot, not a migration and not permission to restore into the live database. No incentive-specific schema is present in the inspected application/schema baseline; new permanent structures must be introduced through a dated migration.

The backup contains real organizational/application data. Keep database dumps outside Git in future; do not rewrite or delete repository history automatically. Treat any history cleanup as a separate security task requiring explicit approval.

## Implementation gates

1. Finish the source audit for exact table definitions, project FM closure transaction/audit ordering, and all payroll draft-refresh/status/accounting paths.
2. Add a dated migration for versioned policy, achievement/source-event linkage, immutable earned-reward records, allocation detail, payroll scheduling/linkage, and controlled adjustment/audit evidence. Final names/columns must be checked against the full current schema before authoring.
3. Implement explicit, idempotent award functions called only at confirmed business-event boundaries. Ensure the award record and source event are committed atomically where possible; send notifications after commit.
4. Integrate incentives into every payroll draft generation/refresh path and the payroll journal. Preserve salary-advance/attendance semantics, verify currency, and reconcile journal debits/credits including the dedicated incentive expense allocation.
5. Add rollback-only integration harnesses for duplicate source-event processing, currency hold, employee eligibility, project approved splits, draft refresh, payroll posting, immutability, reopening/reversal adjustment, and notification failure isolation.
6. PHP lint, inspect the exact diff, run targeted tests and the full relevant regression suite on the real local XAMPP/MariaDB environment, update docs with actual results, and commit on `main`.

## Verification boundary

Repository source and the latest committed backup have been inspected through GitHub. The GM approval migration is applied locally; schema inspection confirmed the expected `pending_gm` and `approved` statuses and the durable approval-history table. PHP syntax checks for `modules/hr/incentive_policy.php` and `dashboard/gm_dashboard.php` passed, and the policy page loads. The local database has zero policy versions, rules, and custom fields, so the complete role/state workflow has not yet been runtime-tested. Do not claim end-to-end approval, rejection, or activation verification until actual policy data can be tested safely; do not invent reward amounts or create a live policy just for testing.


## Direct sponsor attribution — implementation and local verification

The direct sponsor creation INSERT now sets `sponsors.brought_by_user_id` from `Session::getUserId()`. Sponsor-request conversion remains unchanged and continues preserving `sponsor_requests.brought_by`; historical missing attribution is not backfilled.

Commits:
- `aaa71897249ca4911ee2e0763b85737d21ebb698` — direct attribution field and parameter.
- `3bc49b342bb0e7aa988690582be2ef091bf1fe3c` — SQL placeholder correction.
- `4058cff5515fc021129a45bcc1bdec96c6ce94b3` — static evidence documentation.

Local verification completed by the user on 2026-10-12 after syncing `main` to `48ff9293`:
- `php -l modules\\sponsors\\create.php` — `No syntax errors detected`.
- `SHOW COLUMNS FROM sponsors LIKE 'brought_by_user_id'` — column exists as nullable `int(10) unsigned`, indexed, default NULL.

This confirms syntax and local schema compatibility. It does **not** yet prove a real sponsor INSERT succeeds or that the stored value equals the logged-in user ID. Direct-create behavior still needs a controlled local runtime test before this slice is called fully verified.

## 2026-10-12 — Local schema output and source transaction audit

The user supplied a live `information_schema.COLUMNS` result for the existing database. Confirmed local structures include:

- `sponsors.brought_by_user_id`: nullable indexed `int(10) unsigned`; no duplicate-column migration is justified.
- `sponsor_payments`: `supervisor_id`, `payment_type`, `payment_period`, `amount`, `currency_code`, `status`, and `transaction_id` are present.
- `transactions`: has sponsor/sponsorship/project linkage, amount/currency, status and transaction metadata.
- `winback_campaigns`: has nullable `handled_by`, status and `closed_at`, but no payment confirmation field.
- `project_lifecycle`: records administrative closure/reopening; `project_approval` has FM review fields; `project_team` stores team membership by user ID and active/unassigned timestamps.
- `payroll`: stores base pay, allowances, deductions, attendance deduction, salary-advance deduction, net salary, status and accounting references; it has no incentive detail/currency fields.
- `hr_employee_salary_history.salary_currency` is the applicable salary currency source identified by prior source audit.
- `accounts`, `journal_entries`, `journal_lines`, `notifications`, `users`, and `employees` are present.

Important type boundary: `sponsor_requests.brought_by` is `varchar(190)`, whereas `sponsors.brought_by_user_id` is an integer. The conversion code currently writes the request's `brought_by` value into the integer column. New requests write the session user ID into that varchar field, but legacy rows may contain names or other text. A reward resolver must validate that the value is a positive numeric user ID and that it maps to an eligible employee; never cast arbitrary historical text into an award recipient.

Source transaction boundaries confirmed from current `main`:
- FM sponsor-payment approval starts a transaction, locks a pending payment, creates a posted `transactions` row and its journal, updates the payment to approved with the transaction ID, then commits. This is the reliable collection-reward trigger boundary; create any award idempotently inside the same transaction or through a safely replayable post-commit process.
- Win-back `mark_returned` reactivates the sponsor and closes the campaign, but it is not a payment event. It also overwrites `handled_by` with the operator who marks the return, so this value alone is not guaranteed to identify the employee who won the sponsor back. Preserve/confirm the intended handler attribution before using it for awards.
- Project administrative closure in `modules/projects/view.php` is distinct from the FM financial closure audit event `FM_FINANCIAL_CLOSURE`. Project rewards must wait for both.
- Payroll accounting explicitly uses account code `5200` for salaries and rejects mutation of paid payroll. Do not book incentives to that account. Incentive payroll integration must preserve the salary-advance/attendance calculations and paid-payroll immutability.

The user's schema query reports columns and indexes, not table engines, foreign keys, unique indexes, or all migration constraints. Before a new migration is authored, obtain targeted `SHOW CREATE TABLE` output and index definitions for the relevant tables. The canonical data-bearing SQL backup is not to be restored into the live database.

## 2026-10-12 — Index, engine, and foreign-key audit

The user's live metadata query confirms all 18 relevant tables are InnoDB. Relevant uniqueness/index facts:

- `accounts.code` has unique index `uq_accounts_code`; do not assume an incentive expense account exists just because the accounts table exists.
- `journal_entries.entry_code` has unique index `uq_je_code`.
- `payroll` has unique indexes on both `(employee_id, month, year)` and `(employee_id, month, year)` under two names; one employee cannot have two payroll rows for the same period.
- `project_team` has unique index `uq_project_team_active` on `(project_id, user_id, section_code, unassigned_at)`, plus lookup indexes. Project incentive split records must snapshot the eligible recipients/splits rather than depend on mutable current team membership.
- Existing sponsor/payment indexes are non-unique for `sponsors.brought_by_user_id`, `sponsor_payments.transaction_id`, `sponsor_payments.sponsor_id`, `sponsor_payments.status`, and `winback_campaigns.sponsor_id`. Therefore the new ledger must supply its own idempotency constraint; the source tables do not guarantee one reward per event.
- `hr_employee_salary_history` has only non-unique employee/effective-date indexes; applicable currency lookup must respect effective dates and must not assume a uniqueness constraint.
- `sponsor_requests` has no foreign key for `brought_by`; it is varchar and must be validated before mapping to `users.id`.
- Existing foreign keys confirmed include `sponsors.brought_by_user_id -> users.id`, `sponsor_payments.supervisor_id -> users.id`, `sponsor_payments.transaction_id -> transactions.id`, `winback_campaigns.handled_by -> users.id`, `payroll.employee_id -> employees.id`, `payroll.journal_entry_id -> journal_entries.id`, `journal_lines.account_id -> accounts.id`, and `journal_lines.entry_id -> journal_entries.id`.
- `project_lifecycle`, `project_approval`, and `project_team` showed no foreign-key rows in the supplied metadata output. Do not assume referential constraints exist there.
- All relevant listed tables use InnoDB, supporting transactionally consistent award insertion when the source workflow and new ledger operations are executed on the same connection/transaction.

This metadata output still does not show full column definitions, defaults, check constraints, table collations, or complete DDL. The next gate is targeted `SHOW CREATE TABLE` for the payment, payroll, journal, project, and salary-history tables, plus inspection of the current transaction and payroll refresh functions. Do not author the incentive migration until those details and the approved reward-state model are reconciled.

## Current engineering gate — Explicit reactivator attribution and confirmed-payment wiring

The business decision is that **the employee who actually reactivated the sponsor receives the reactivation incentive**; the user who confirms the return is not automatically the recipient. The win-back form requires selection of a currently working employee linked uniquely to an active user account. The server revalidates that mapping, stores the selected user in `reactivated_by_user_id`, stores the logged-in confirmer separately in `reactivation_confirmed_by_user_id`, and preserves `handled_by` as operational case ownership. Legacy campaigns without the new confirmer snapshot are excluded from reactivation incentive qualification; the system does not assume their earlier actor attribution was correct.

New migration: `database/migrations/2026-10-14_winback_reactivation_confirmer.sql`. The FM payment-confirmation transaction now calls `hrIncentiveRecordReactivationPaymentEvent()` after the posted transaction/journal and approved payment linkage are present. The qualification helper requires a posted linked transaction and journal, a prior financially confirmed monthly-sponsorship payment at least 90 days before reactivation, and the first qualifying confirmed monthly-sponsorship payment created after the transition. It records only a `pending_verification` event; it does not issue an award.

**Local acceptance is pending** for this combined slice. Pull `main`, inspect schema first, and apply the confirmer migration and the previously pending recipient-snapshot migration only if their columns are absent on `ahl_el_kheir`. Lint the win-back helper/page, FM review queue, and all three relevant rollback-only harnesses, then run:
- `tools/run_winback_reactivation_rollback_tests.php`
- `tools/run_hr_employee_incentive_source_event_rollback_tests.php`
- `tools/run_hr_employee_incentive_reactivation_rollback_tests.php`

Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`. These rollback-only tests do not establish true two-process concurrency. HR Manager verification/award issuance, notifications, new-sponsor/project hooks, and full lifecycle acceptance remain open.

## 2026-10-12 — Incentive ledger schema committed

Two additive migrations are now present on `main`:

- `database/migrations/2026-10-12_hr_employee_incentives_expense_account.sql` establishes account 5500 (`مصروف حوافز الموظفين` / `Employee Incentives Expense`) if absent. It does not choose a parent account because no parent was approved.
- `database/migrations/2026-10-12_hr_employee_incentives_ledger.sql` creates versioned policy/rule records, idempotent source-event records, snapshotted project allocations, employee awards, and controlled correction/reversal records.

Schema protections include unique policy versions, one rule per incentive type per policy version, a unique event idempotency key, one award per event/employee, and foreign-key restrictions against deleting referenced policy/event/allocation/award history. Historical source events are not backfilled. The migrations do not create runtime DDL, triggers, views, procedures, functions, or events.

Commits:
- Expense account migration: `9f5914f3205988bf6c7078c823f432119bd69682`
- Incentive ledger migration: `afd84b9040893477f6052db9a9bbb1a4f7b5dda0`

**Status:** committed to GitHub main; local migrations and PHP workflow remain unverified. The policy approval administration code now exists on main. The next gate is local migration application, PHP lint, and policy workflow acceptance testing, followed later by transactional event/award integration. Do not mark the feature complete based on source/schema commits alone.

The policy governance decision is now an in-system workflow: HR/FM prepare and submit a draft; only normalized roles general_manager or gm can approve/reject; rejection requires a reason and returns the version to an editable draft while preserving history; FM/Admin activate only an approved version. Approval does not activate or retire any policy. Individual incentive achievements still require manager verification, with financial confirmation where the source event is a collection or project closure.

## 2026-10-12 — Policy administration page committed

`modules/hr/incentive_policy.php` is committed on `main` with draft creation using policy name/effective date and fixed reward amounts; the GM approval workflow adds submission, GM-only approval/rejection, durable history, full draft editing, and FM/Admin-only activation after approval. The agreement date/notes inputs, validation, display, and activation prerequisite have been removed from the new workflow; legacy DB columns remain untouched. Existing effective-date, reward-amount, enabled-category, and activation-overlap safeguards are retained. Amounts and currencies have no prefilled business defaults.

Commit: `e30dd46c03ba064ff06c0dd957d006ef6b92b88a` (activation-order guard; initial page commit `9f16cefb2795752d08b6e1182d45ddc49c665668`).

**Verification status:** source has not yet been PHP-linted or exercised against the local database. The next user-assisted gate is to pull `main`, apply the pending incentive migrations in order (including 2026-10-13_hr_incentive_policy_gm_approval.sql), lint the policy page, and run controlled draft/edit/submit/GM approve/reject/resubmit/FM activation checks before building source-event integrations. Do not treat the current page as runtime-verified.

## 2026-10-12 — Salary portal navigation milestone

The FM dashboard salary actions are consolidated into modules/hr/salary_portal.php, which provides role-filtered links for salary-advance management, salary-advance waivers, payroll management, and employee incentive policy. The three separate salary/advance cards were removed from the FM dashboard and replaced by one portal card for roles that have at least one authorized destination. Each destination retains its own server-side authorization; the portal does not grant access by itself.

Commits on main:
- 8bc2be917afaa09d50a3c46f4d13a3922c42648b — consolidate FM dashboard salary actions.
- 94f5c9c5666a84cea8fc4a161277dba3b707e2a1 — add salary portal.

Verification: source committed to GitHub; local pull, PHP lint, and browser verification of the portal/dashboard changes remain pending.

## Incentive category catalogue and manual-award boundary

The user approved a database-driven category catalogue. Migration `database/migrations/2026-10-12_hr_employee_incentive_catalogue_and_policy_fields.sql` seeds the four existing category codes, converts the `incentive_type` ENUM columns to catalogue-backed `VARCHAR(80)` codes, adds foreign keys, and creates version-scoped custom policy fields. The policy page now loads categories from `hr_incentive_types` rather than a hard-coded PHP array. User-created categories are deliberately `manual_review`; they can be configured in a policy but cannot generate automatic awards. A separate nomination/verification/award workflow is still required before such categories can be used for financial payment. The four existing source-driven categories retain their established codes and qualification boundaries.


## 2026-10-12 — System-wide currency field removal

The incentive policy form now uses `APP_CURRENCY_CODE` / `APP_CURRENCY_NAME_AR` from `config/config.php` and no longer asks the FM to enter a three-letter currency for each reward. New policy rules store the system currency (currently SDG); the form labels the amount column with that currency. The ledger's currency snapshots remain in the schema for audit/accounting integrity, not as user-selectable policy currencies.

Commit: `12af8e46b2a9e042322bddf939462b973dd612d0`.

**Verification status:** committed on GitHub `main`; local pull, PHP lint, and browser verification are still required. This change does not alter existing database rows or apply currency conversion.

## 2026-10-12 — Employee performance recognition design proposal (not yet implemented)

The user proposed automatic monthly selection of the highest-performing employee(s), including equal-score co-winners, and an official certificate. Initial design research supports published job-relevant criteria, evidence-backed results, consistent evaluation, and transparent certificate identity/verification rather than popularity voting or one narrow metric.

Recommended design direction:
- Keep source-driven incentives (collection, new sponsor, reactivation, project completion) separate from a new performance-recognition evaluation workflow.
- Use role/role-family-appropriate scorecards so unlike jobs are not compared using the same output measure.
- Start with objectively verifiable system data where it is suitable; treat missing records as unknown rather than a zero or a penalty.
- Attendance should measure reliability only under an approved rule: approved leave and non-working days must not be treated as poor performance.
- Include a reviewable evidence breakdown, eligibility rules, frozen monthly score snapshot, policy version, calculation timestamp, reviewer/finalization audit, and a deterministic tie rule that awards all employees tied at the qualifying top score.
- Keep the certificate as an official recognition record, distinct from any financial award. Include certificate number, employee name, award title/period, issue date, issuing authority, and a verification reference; provide a print-friendly Arabic RTL layout.
- Financial rewards remain subject to the existing SDG policy and next-open-payroll/paid-payroll immutability controls.

No scoring weights, minimum threshold, tie tolerance, eligibility window, reviewer workflow, or role-specific metrics have been approved. Do not implement scoring until the existing attendance, employee/work assignment, and performance evidence sources are audited and the business rules are agreed.

## 2026-10-12 — Performance recognition deferred; current policy extensibility is active

The user explicitly deferred the proposed monthly employee performance recognition/certificate feature to a separate future development task. Keep the discussion and proposal documented here, but do not implement its scoring engine, monthly ranking, co-winner handling, or certificate workflow as part of the current incentive task. Before that future task begins, audit the real attendance/work evidence sources and agree role-appropriate scorecards, eligibility, minimum threshold, tie handling, review/finalization, and certificate verification.

The current task is local verification of the employee incentive policy GM approval workflow. HR Manager/HR Staff and FM/Admin may create/edit drafts and add custom fields; only general_manager/gm may approve or reject; only FM/Admin may activate an approved policy. Agreement timestamp/notes are no longer part of the new workflow, and legacy columns remain preserved. Custom policy fields are version-scoped and writable only while draft. The category catalogue and approval workflow source/migrations remain unverified locally.

The user confirmed that the system-wide SDG currency change is visible on the local incentive policy page. Commit `12af8e46b2a9e042322bddf939462b973dd612d0` removed the currency input and uses `APP_CURRENCY_CODE` for new policy rule rows. Local PHP lint/runtime verification is still outstanding.

Authorization boundary confirmed by the user: HR may create drafts and add custom policy fields; FM retains activation authority. The implementation permits `hr_manager`, `hr_staff`, `financial_manager`, and `admin` to create drafts/manage categories/fields, while activation remains restricted to `financial_manager` and `admin`.

## 2026-10-12 — Current scope

Monthly performance recognition and certificate issuance are deferred to a separate future feature. Current focus: extensible policy fields for FM/HR, preserving FM policy activation authority. SDG currency input removal is visible locally; lint/runtime verification remains pending.


## 2026-10-12 — Incentive catalogue and custom policy fields committed

Commits:
- `eb92fa5a42d00c086da0fe6a439511178d8c149b` — adds `hr_incentive_types`, seeds the four existing category codes, converts the two ENUM-backed category columns to catalogue-backed strings, adds category foreign keys, and creates `hr_incentive_policy_custom_fields`.
- `68758c17c6363083f798e019c6348bdb596acc8c` — makes the policy page category-driven, adds manual-only category creation, allows HR/FМ to create drafts and add version-scoped fields, and restricts activation to FM/Admin.

Custom fields support short text, long text, number, date, yes/no, and controlled selection lists. Required fields must be populated before activation. Existing category codes remain unchanged. New categories are marked `manual_review` and the UI explicitly states that this does not implement award issuance or payment for them.

**Verification boundary:** these commits are source changes only. The new migration has not been applied to the user's local MariaDB, and the updated PHP has not yet been locally linted or exercised in the browser. Do not claim runtime success. Next gate: pull `main`, apply the new migration after confirming the prior incentive ledger migration is present, run PHP lint, then verify category creation, policy draft creation, custom-field validation/version isolation, HR activation denial, and FM activation.


Migration hardening follow-up: commit `eb92fa5a42d00c086da0fe6a439511178d8c149b` makes the ENUM-to-VARCHAR conversion conditional on the live column still being an ENUM, so a rerun after a completed or partially completed migration does not unnecessarily modify a column already referenced by a category foreign key. This source-level guard has not yet been exercised against the local MariaDB version.

## 2026-10-12 — Category-code UX and agreement validation
The category creation form now generates its code automatically from the required English name. The code is shown as a read-only preview rather than a user-editable/submitted field; PHP repeats the normalization server-side and appends a numeric suffix for existing codes. The database unique index remains the final duplicate barrier. This is intended to avoid manual code-entry mistakes while keeping stable English codes.

The policy creation handler also validates the agreement timestamp by strict format round-trip, rejects impossible/future dates, and rejects empty or over-2,000-character notes. These checks were included alongside the code-generation update.

Commits: `1a1ced379bf5f4942cd44d554bbfbbfc2c499bbe`, `23bdf2a84d81cd86f753c283e462f06f828aa510`.

The user confirmed the existing category labels render correctly in Arabic and English. The new generated-code preview and server behavior have not yet been locally linted or browser-tested. Reward amounts are not yet agreed; any test amount must be used only in a rollback-only harness or otherwise clearly isolated from real policy activation. Do not mark policy administration verified until local lint and database-backed acceptance checks pass.


## 2026-10-13 — GM policy approval workflow committed; local verification pending

Commits on main:
- 44b6fdcb62e3eb54c86255fdea7b6f22709f22c8 — add idempotent status/history migration database/migrations/2026-10-13_hr_incentive_policy_gm_approval.sql.
- 38e79e0851eed31640de00553c843c2d2920bdce — add draft editing and GM submit/approve/reject handlers, and require approved state for activation.
- 6ad9a4308e19f36b0a53132ab39b2048b00f56a4 — remove obsolete draft-activation UI path.

The create form keeps policy name/effective date and removes agreement date/notes. Workflow states are draft → pending_gm → approved → active; rejection records its reason and returns to draft. Approval history records actor and timestamp. Only general_manager/gm can approve/reject; only FM/Admin can activate. Rejected drafts are editable/resubmittable. Legacy agreement columns are preserved.

Not yet verified locally: PHP lint, migration execution/idempotent rerun, FK compatibility with live users schema, and browser/runtime role/transition tests. Do not treat the feature as runtime-verified until these pass.

The GM executive dashboard now includes a direct “مراجعة واعتماد سياسة الحوافز” shortcut to modules/hr/incentive_policy.php (commit fff2e518720094211ffa28010f09e4495342c4b7). Local browser verification of this shortcut remains pending.

## 2026-10-13 — Custom policy field concurrency guard

`modules/hr/incentive_policy.php` now checks the policy-management permission before opening a transaction, locks the target policy-version row with `SELECT ... FOR UPDATE`, verifies that it remains a draft, inserts the custom field, and commits. This serializes custom-field creation against policy submission, which locks the same policy-version row while validating required fields. Exceptions remain handled by the page's existing transaction rollback path. Commit: `c53a5dd6b32f6a737f7d6264df7b033e3383cb45`.

Verification boundary: the change is committed on `main`; PHP syntax/runtime verification of this exact commit is pending the user's local pull and test. No concurrent two-process test has been run, and none is claimed.

## Deferred follow-up after GM approval workflow acceptance — payroll/accounting audit

Do not begin this cross-module audit until the current incentive-policy GM approval/rejection/edit/resubmit/activation workflow has passed acceptance checks. Then follow the checklist in `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` to inspect and reconcile salaries, additions/deductions, attendance deductions, advances/repayment schedules/rescheduling, advance waivers/refunds/cancellations, any actually implemented incentive award/payment path, accounting journals, reversals, and reports. Policy configuration alone does not prove that incentive awards, payroll scheduling, or accounting posting exist. Independently recompute calculations from verified business rules, test duplicate/idempotent processing and rollback, and reconcile source ledgers to balanced journals using isolated rollback-only test fixtures. No accounting correctness or full integration should be claimed before evidence is collected.


## 2026-10-13 — Incentive policy role boundary clarified

The user confirmed this is a higher-management workflow involving only HR Manager, General Manager (GM), and Financial Manager (FM). `modules/hr/incentive_policy.php` now limits draft creation/editing, custom-field management, and category management to `hr_manager` and `financial_manager`; review/approval/rejection remains GM-only; activation is FM-only. `hr_staff` and `admin` are no longer authorized substitutes in this workflow. Authorization source change committed as `63534c5fd3aa5b0b3fc8bf640d3ecfb2280f314a`; UI alignment committed as `b065a93a98e1786a2e81702c0fa956b3a414f646`. The policy-creation form is now hidden from GM-only reviewers, and status guidance no longer names Admin as an activation actor.

**Verification boundary:** this is a source-level authorization correction only. Local PHP lint and real XAMPP/MariaDB role-based acceptance are still required. The three accounts previously identified are HR Manager user 32, GM user 2, and FM user 29, but their current login usability has not been established. Do not claim the workflow accepted until runtime tests pass. Do not use HR staff or Admin as test actors.


Role-boundary follow-up: commit `477b2151b63c0aeaa4bee98c006d3685283b6e5d` also updates the approval success message so it identifies FM alone as the activation actor. Runtime role checks remain pending locally.

2026-10-14 — Category-code input removed

The user reported that the “رمز الفئة (تلقائي)” control remained visible in the category form and requested its removal. Commit `e4b0675dbed130a1a5235851df6dbdb4f342b524` removes the code-preview input, label, help text, and preview JavaScript; the category form now presents only Arabic name, English name, and the submit action. The server continues generating and de-duplicating `type_code` from the English name; no client-supplied code is accepted. This is a source change committed to `main`; local pull, PHP lint, and browser verification remain pending. Resume the policy acceptance test only after syncing and confirming the field is absent.


## 2026-10-14 — Required UI feedback rule: toast, not top-of-page banners

This is a project-wide implementation rule, not an optional page-specific preference. Success, error, warning, and action-result informational feedback must be shown as temporary toast notifications through the centralized `AKNotify.toast` path, near the active workflow/modal when applicable. Do not leave action feedback as persistent banners at the top of a page. For legacy Bootstrap feedback nodes that need centralized conversion on a full-page load, add `data-ak-transient-alert="1"`; server flash nodes may use the established `data-ak-flash` convention. Keep field validation beside the relevant field, and keep genuinely persistent instructional/context panels separate from action feedback (mark them explicitly persistent if their markup matches transient-alert selectors).

**Required page-completion check:** inspect every success/error/warning/info message introduced or modified; verify both full-document POST/redirect loads and same-page/AJAX responses use toast behavior; confirm no action-result banner remains at the page top. Do not assume a page follows this rule merely because another module does. This rule applies to new pages and existing-page changes across the repository.


## 2026-10-14 — Incentive policy action feedback correction

The user observed that creating a policy left the success text `تم حفظ مسودة سياسة الحوافز V1...` as a top-of-page banner. Source inspection identified the exact cause: the page rendered `$message` and `$error` as Bootstrap alerts without the marker used by `assets/js/notifications.js` to convert transient alerts into centralized toast notifications. Commit `c98b5dc26fc2092102a4a6988a83391aec87c9ec` adds `data-ak-transient-alert="1"` to both success and error feedback nodes. This preserves the existing policy record and uses the already-established global toast behavior; the static informational guidance panel remains a persistent context panel. Repository source was re-fetched and both markers were verified on `main`. Local PHP lint and browser verification of the toast (including error feedback) remain pending; do not claim runtime verification until supplied.


## 2026-10-14 — Incentive policy toast and English-language coverage update

Source-level review and correction completed on `main`:
- All eight successful incentive-policy actions now obtain their feedback from the stable incentive translation catalog and render through the page's transient-alert markers; the centralized `assets/js/notifications.js` path converts success/error feedback to toast notifications on normal page loads and observes alerts inserted during same-page/AJAX updates.
- Added matching Arabic and English stable catalogs `lang/incentive_policy_ar.php` and `lang/incentive_policy_en.php` with 135 matching keys covering page labels, status/history terms, confirmations, validation messages, and action feedback.
- Localized field-type and Yes/No option labels server-side because the language bridge intentionally does not translate native `option` text nodes; category names, custom-field labels, and currency names now use their English value when English mode is active, falling back to Arabic only when an English custom-field label was not supplied.
- Dynamic validation messages use translated prefixes and localized category/custom-field labels. User-entered policy names, custom-field values, rejection reasons, and other business data remain unchanged rather than being guessed/transliterated.

Repository source checks confirmed both catalogs contain 135 unique keys with no key mismatch, every `t('incentives.*')` key referenced by the module exists in both catalogs, all eight success feedback calls use the catalog, and both success/error alert nodes have `data-ak-transient-alert="1"`. This is **source-level verification only**. Local PHP lint, `php tools\\i18n_gap.php`, and browser checks in Arabic and English—including one successful action toast and one validation-error toast—remain required before marking runtime acceptance complete. Preserve the existing V1 test policy; do not create another policy solely to test notifications.


## 2026-10-14 — Incentive policy review-page presentation follow-up

The user confirmed the Arabic/English language toggle works and supplied local PHP lint results: `modules/hr/incentive_policy.php`, `lang/incentive_policy_ar.php`, and `lang/incentive_policy_en.php` all pass syntax checks. The official `tools/i18n_gap.php` scan reports 85 untranslated strings across 12 other files; `modules/hr/incentive_policy.php` is not listed, so unrelated files remain out of scope. The existing GM view exposed two narrow presentation issues: the system-currency notice was hard-coded in Arabic, and policy category/amount summaries had no visual separation. Commit `e446adee1ea9ae4e76bc5b48ad227926fec442c2` routes the currency notice through `t('incentives.currency_notice')` and styles each category/amount summary as a separated bordered item. This source correction is committed to `main`; local PHP lint and browser confirmation of the updated presentation remain pending. The Arabic creator name is stored user data and was intentionally preserved. V1 remains a draft; do not submit, approve, or activate until the toast acceptance checks and updated presentation are confirmed.


## 2026-10-14 — Policy ownership and notification workflow correction

The user clarified the final policy workflow:
- **HR Manager:** creates and edits drafts, manages categories/custom policy fields, and submits the policy to GM.
- **GM:** receives a notification on submission and is the only role allowed to approve/reject. Rejection requires a reason, returns the policy to editable draft, and notifies the HR policy creator with that reason.
- **FM:** cannot create/edit/submit drafts or approve/reject. After GM approval, FM receives a notification that the policy is available to select/apply and can activate it. Activation notifies the HR creator and the approving GM.

Implementation commits on `main`: `21c94362c4f11f4000e050ba5e14160eedbac0fe` and `675bdc36b67ea1b7ede11abef37cdbf5c2340b84`.

Implementation reuses `ak_transaction_review_notify_event()`. Notifications run after each policy transaction commits; delivery errors are isolated from the business state transition. No schema changes were introduced.

**Acceptance pending on local XAMPP/MariaDB.** Pull main and lint the policy page. Verify HR Manager can create/edit/submit but cannot approve/activate; GM can approve/reject but cannot create/submit/activate; FM can activate an approved policy but cannot create/edit/submit/approve/reject. Verify recipients and rejection reason. Keep V1 inactive because its reward values are test amounts; safely test submit -> reject -> resubmit -> approve, leaving it approved and awaiting FM activation.


## 2026-10-14 — Fallback policy requirement confirmed

The user confirmed that GM rejection returns the policy to HR as an editable draft for review/edit/resubmission; it is not final closure. If HR does not submit a replacement, the last applicable policy must remain in force. If FM does not activate a newer GM-approved policy by payday, the previous applicable policy must continue to be used automatically.

Source inspection of `modules/hr/incentive_policy.php` confirms the policy-administration behavior already preserves the currently active policy while a candidate is draft, pending GM, rejected back to draft, or approved but not activated: the prior active policy is retired only inside the FM activation transaction for the replacement. Therefore no extra status transition is required to preserve the old policy in those workflow states.

Important gap: `modules/hr/payroll.php` and `modules/hr/lib_payroll_policy.php` currently contain no incentive-award integration; the payroll page has no `hr_incentive_awards` reference. Automatic application of the previous policy's eligible awards at payroll time is **not implemented or verified**. Treat the next engineering unit as a source/schema audit and design for incentive-award scheduling and payroll inclusion, including currency, net-pay calculation, paid-payroll immutability, and accounting treatment. Do not assume incentives can be added to `allowances` without explicit accounting/source justification. No production payroll values or V1 policy amounts may be changed for testing.

## 2026-10-14 — Rejection recovery, active-policy fallback, FM access, and toast rule

The user confirmed rejection returns the submitted version to HR as an editable draft; it does not terminate the policy process. If HR does not submit a replacement, the previously active policy remains unchanged. If FM does not activate a newly GM-approved version by payday, the previous active policy remains in force. Source inspection confirms activation retires the overlapping prior active version only inside the FM activation transaction; merely drafting, submitting, approving, or rejecting a replacement does not retire the existing active version.

A page-level access-gate defect was found during this review: the FM role had activation permission in the action guard but was redirected before reaching the page because the page gate did not include `$canActivatePolicies`. Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes that gate. Existing success/error action feedback is marked `data-ak-transient-alert="1"` for centralized toast conversion. The currency notice is contextual information, not an action-result message.

**Local verification remains pending:** PHP lint and browser checks for HR, GM, and FM access, rejection/resubmission, actual notification delivery, toast feedback, and fallback policy status. Do not activate the V1 test policy.


## 2026-10-14 — Rejection recovery and fallback rule

GM rejection returns the version to HR as an editable draft; it does not terminate the process. If HR does not submit a replacement, the prior active policy remains in force. If FM does not activate a newly approved version by payday, the prior active policy remains in force. Source inspection confirms an overlapping prior active version is retired only inside the successful FM activation transaction.

Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes a page-level access gate that previously redirected FM even though FM had activation permission. Existing action success/error messages use `data-ak-transient-alert="1"` for centralized toast conversion.

Local PHP lint, browser permissions, actual notification delivery, toast feedback, and payday policy selection still require runtime verification. Do not activate V1 test values.

## 2026-10-14 — Hide unapproved incentive policies from FM

Commit `1ab2eac4173dc5f4f808865fc10c722058210bb3` changes `modules/hr/incentive_policy.php` so the FM policy list includes only `approved`, `active`, and `retired` versions. Drafts and `pending_gm` submissions are not disclosed to FM before GM approval; HR and GM retain the workflow views they need. This is a source-level correction only; local PHP lint and FM/HR/GM browser verification remain pending.

The existing V1 acceptance policy is still a draft and must remain unactivated. Do not delete it directly with SQL: its approval history and child records are acceptance evidence and may have referential constraints. Reuse V1 for HR edit/submit → GM reject with reason → HR revise/resubmit → GM approve. FM notification is expected at the GM approval handoff, not while the policy remains a draft. Verify actual recipient-role lookup and the persisted notification after approval before declaring delivery fixed. Payroll effective-policy selection remains a separate unverified gate.


## 2026-10-14 — Hide unsubmitted incentive-policy drafts from GM

Source audit found that `modules/hr/incentive_policy.php` filtered the policy list for FM only. Because the GM branch fell through to an unfiltered query, the GM could see HR drafts before submission. Commit `9a309f299c20aab963b5744eeee714bb5b2548b9` now applies explicit role-based visibility: HR sees all policy versions; GM sees `pending_gm`, `approved`, `active`, and `retired` versions but not `draft`; FM sees only `approved`, `active`, and `retired`. A GM-rejected version returned to `draft` is therefore hidden from GM again while remaining editable by HR. This changes list visibility only; it does not alter policy status, approval history, notifications, activation, or the prior-active-policy fallback.

Verification boundary: source change is committed to `main`; local PHP lint and browser acceptance are still required. Verify that the current draft is visible to HR but absent from GM and FM; then verify after HR submits it that it appears to GM as pending approval and remains hidden from FM. Keep the test policy inactive.

## 2026-10-14 — Clean GM approval workflow for acceptance testing

At the user's direction, commit `0df1890e12a006cb1cae57c1bf906047178a39fd` removes both the pre-approval confirmation dialog and the post-approval success toast from the GM `approve_policy` action only. Approval authorization, transactional status/history updates, HR/FM notifications, redirect behavior, rejection feedback, and FM activation confirmation are unchanged. A successful GM approval is now verified through the persisted policy status/history and the handoff notifications, not transient popup/toast feedback. The previous acceptance policy and its dependent test rows/notifications were deleted locally and the user's follow-up query returned no matching policy or notifications and zero dependent rows. **Local PHP lint and browser verification of this source change are pending; do not recreate the test policy until the change is pulled and verified.**

## 2026-10-14 — Incentive policy page: remove redundant currency/category notice

Commit `fa733524d67411e08d299c84e7568c79b601316a` removes the explanatory alert above the category section in `modules/hr/incentive_policy.php`. The application currency remains system-configured and continues to be stored with each policy rule; only the page-level notice was removed. The category management heading, category catalogue, and policy rule amount labels remain unchanged. Source change is committed on `main`; local pull, PHP lint, and browser verification are pending. This UI cleanup does not establish that the entire incentive lifecycle or incentive-to-payroll/accounting implementation is complete.


## 2026-10-14 — GM approval confirmation and success toast restored

The user confirmed that test policy R3 was approved by GM user 2 at 10:52:48, but the page showed neither a confirmation prompt nor a success toast. The earlier commit `0df1890e12a006cb1cae57c1bf906047178a39fd` had deliberately removed both; the current requirement now explicitly restores them.

The source fix adds a bilingual `data-confirm` message and restores the existing translated `flash('success', t('incentives.approve_success'))` after the durable approval and post-commit handoff calls. The GM approval form is marked `data-ak-native-post="1"` so `assets/js/app.js` does not start a same-page fetch during the window-capture submit event before `assets/js/notifications.js` can show its confirmation. After confirmation, the established native POST/redirect path renders the transient alert, which the central notification code converts to a toast. No shared JavaScript was changed; rejection and FM activation behavior are untouched.

A guarded, test-specific SQL reset utility is added at `tools/reset_incentive_policy_gm_approval_acceptance.sql`. It only resets policy ID 3 when its exact R3 name, creator, version, `approved` status, and not-activated state match. It removes the test approval history row and matching approval handoff notifications only when that guarded update affects exactly one row; the original submission history remains. A zero-row guard means no reset was performed. Do not activate R3.

**Verification boundary:** source review and repository commits are not local runtime proof. After syncing `main`, run PHP lint for the policy page and both translation files, execute the guarded reset utility against the local `ahl_el_kheir` database, then retest as GM: cancel confirmation (must not approve), confirm approval (must persist status/history and show a success toast), and verify the HR/FM handoff notifications. Leave the policy approved but inactive. Report actual results before updating this checkpoint to runtime-verified.


## 2026-10-14 — GM approval notification timestamp discrepancy: root cause isolated

Browser acceptance confirmed that the GM approval confirmation dialog and success toast work, and R3 moved to `approved` without activation. The approval-history row was timestamped `11:03:57`, while HR/FM approval-handoff notifications displayed `10:52:48`.

The local notification rows' title bytes are valid UTF-8 Arabic and correspond to the approval-handoff titles. The mismatch was in the test reset path: the SQL reset depended on Arabic title literals, while the local PowerShell/CLI execution displayed mojibake and a direct native-command comparison converted Arabic literals to question marks. The old HR/FM rows were therefore not removed. The existing notification helper suppresses an equivalent notification while an unread row exists, so the stale rows could be reused without refreshing `created_at`. No evidence currently indicates that the incentive-policy page is corrupting notification titles; production notification code is intentionally unchanged.

Commit `75484be186fdf7b935e27151e07604456b2cfa76` makes the test-only reset encoding-independent. It guards the exact R3 policy in either `approved` or `pending_gm`, requires it to remain unactivated, matches only the R3 body and policy-page link, and limits deletion to the observed HR/FM recipients 32 and 29. The GM recipient 2's original submission notification is deliberately preserved. It removes the GM approval-history row only when it actually transitions an approved policy back to `pending_gm`; the original submitted history remains.

**Next local gate:** pull `main`, inspect the changed SQL, run it against only `ahl_el_kheir`, confirm the guarded policy remains `pending_gm` and inactive and the stale HR/FM R3 handoff rows are gone, then approve R3 once as GM and verify fresh HR/FM notification timestamps align with the new approval event. Confirm canceling the dialog still leaves status unchanged. Do not activate R3. Test data will be removed before go-live; preserve unrelated data and do not touch `ahl_el_kheir_production_test`.


## 2026-10-14 — GM approval confirmation, toast, and notification timestamps verified

Local browser and database acceptance now confirms the GM approval path for test policy R3. As GM, the user saw the bilingual confirmation prompt and, after confirming, the translated success toast. The policy moved from `pending_gm` to `approved`; approval history contains submission row 5 (HR user 32, `2026-10-14 10:31:41`) and approval row 8 (GM user 2, `2026-10-14 13:54:58`). HR notification 175 (recipient 32) and FM notification 176 (recipient 29) were both created at `2026-10-14 13:54:58`, matching the approval-history timestamp. The policy's `activated_by_user_id` and `activated_at` remain NULL.

The earlier timestamp mismatch was caused by stale HR/FM test notifications surviving an encoding-fragile reset that matched Arabic titles. The corrected guarded reset matches the exact R3 body/link and recipients without Arabic literals; after reset and a fresh approval, the notification timestamps aligned. No production notification-writer defect was demonstrated, so production notification code remains unchanged.

Acceptance boundary: the confirmed-approval path, toast, persisted history, HR/FM notifications, matching timestamps, and inactive policy state are verified. The user's message did not explicitly report the result of cancelling the confirmation, so do not mark the cancel path verified unless separately confirmed. Keep R3 approved but inactive; do not activate it or touch `ahl_el_kheir_production_test`. Broader rejection/resubmission, FM activation, and payroll effective-policy selection remain separate gates.


## 2026-10-14 — FM activation confirmation race identified; guarded reset and source fix committed

The first FM activation attempt showed a confirmation prompt that disappeared before a toast; the database recorded policy 3 (ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3) as active, with FM user 29 at 2026-10-14 14:12:23. This was an executed activation, not a cancellation.

Source audit identified the race: the system-wide same-page POST interceptor runs on window in capture phase, before the shared confirmation handler on document. The FM activation form used inline onsubmit="return confirm(...)" without opting out of same-page AJAX interception, so the AJAX POST could start before the confirmation handler completed.

Commit 06b08d44afc48887b74a4588f1604e11f5d5e809 changes only the FM activation form to use the existing translated incentives.confirm_activate message through data-confirm and sets data-ak-native-post="1". This aligns it with the established GM approval pattern. Local PHP lint passed. After the guarded reset, the user confirmed the FM prompt stayed long enough to choose and then saw the translated activation-success toast; the database recorded status active, actor FM user 29, timestamp 2026-10-14 14:18:10. The confirmation/acceptance path is now browser- and database-verified. The Cancel path is still unverified.

The guarded test-only reset utility, tools/reset_incentive_policy_fm_activation_acceptance.sql, was committed as 0f987a2dc367b4776525469ffefca05bb1c87bd6. Its exact timestamp guard has now been updated in commit a94080af39f82bcdaa56190c6c8eca264934d9f3 to match the latest observed activation, 2026-10-14 14:18:10. It resets R3 from active to approved and clears activation fields only if the exact observed R3 name/id/version/creator/status/effective date/FM actor/timestamp all match. It preserves approval history and notifications. It does not touch other policies or ahl_el_kheir_production_test.

Next local gate: pull main so the updated reset utility is present; inspect it and execute it only against local ahl_el_kheir; verify R3 is approved and inactive; then as FM click Activate and choose Cancel. Confirm no activation-success toast and verify status remains approved with both activation fields NULL. Do not repeat the already-passed confirm/activation path unless a regression appears. Payroll effective-policy selection is still unimplemented/unverified; activating this acceptance rule does not prove incentive payroll integration.


## 2026-10-14 — Incentive policy rejection, HR recovery, GM reapproval, and handoff notifications verified

Local browser and database acceptance completed the R3 rejection/recovery path for policy 3, `ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3`.

- GM user 2 rejected the submitted policy with the required reason: `ACCEPTANCE TEST: clarify the evidence required to confirm a qualifying collection before approval.`
- HR creator user 32 received the rejection notification at `2026-10-14 14:36:36`.
- HR edited/resubmitted the same policy. Approval history row 10 records `submitted`, actor 32, at `2026-10-14 14:38:35`; GM received the resubmission notification at that timestamp.
- GM reapproved through the actual UI. Approval history row 11 records `approved`, actor 2, at `2026-10-14 14:40:49`.
- The final read-only notification query confirmed HR notification 181 (recipient 32) and FM notification 182 (recipient 29), both created at `2026-10-14 14:40:49`. Their bodies identify the exact R3 policy; both link to `modules/hr/incentive_policy.php` and remain unread (`is_read=0`).
- Final policy state is `approved`; `activated_by_user_id` and `activated_at` are NULL. R3 is deliberately left approved but inactive.

Acceptance boundary: rejection with reason, HR recovery/resubmission, GM reapproval, persisted history, rejection/resubmission notifications, and final HR/FM approval handoff notifications are verified. Earlier FM activation confirmation/acceptance and Cancel-branch tests are already closed; do not repeat them without regression evidence.

Test-reset caveat: the guarded reset used to prepare this retest removed the prior approval-history row and prior HR/FM handoff notifications while preserving submission history and the GM submission notification. It is not a full rollback-only reset and must not be described as preserving all original test evidence. No production notification-writer defect was demonstrated; do not change production notification code based on this test.

Next engineering unit remains a separate source/schema audit and design for incentive-award qualification, scheduling, payroll inclusion, currency/net-pay handling, paid-payroll immutability, and accounting treatment. Award issuance/payroll integration and payroll effective-policy selection are not implemented or runtime-verified. Do not activate R3, alter test financial values, or touch `ahl_el_kheir_production_test`.


## 2026-10-14 — Dedicated incentive payable account established in source

Source audit confirmed the existing chart-of-accounts model and current account-code series:
- `2100` is general Payables.
- `2200` is Deferred Revenue.
- `2300` is reserved by `modules/accounting/fina_lib.php` for Fina Al-Khair Payable and is validated as a liability.
- `5500` is the dedicated Employee Incentives Expense account created by its own migration.
- The local chart-of-accounts inspection previously supplied for this task showed no `2400` account. The existing liability series and unique account-code constraint support reserving `2400` for Employee Incentives Payable rather than mixing incentives into `2100` or `2300`.

Added migration `database/migrations/2026-10-14_hr_employee_incentives_payable_account.sql` (commit `a281b8cd695e1555dcec86b7c900b1bf691f1562`). It idempotently inserts account `2400` as a liability and does not create/alter tables or run DDL at application runtime.

**Verification boundary:** this is a source commit only; the migration has not yet been applied or verified against local `ahl_el_kheir`. Do not claim the account exists in the live local database until the user applies it and confirms the account row/type/active state.

### Next engineering step — implement award recognition and payroll settlement without mixing expense

The existing payroll accounting function posts salary expense to `5200`, pays `payroll.net_salary` from the payment account, and credits `1410` for salary-advance recovery. Incentives must not be merged into `net_salary` in a way that increases the `5200` expense.

Required design before integration:
1. Keep the award ledger as the authoritative per-award record and preserve event/employee idempotency.
2. Recognize an earned, verified incentive against `5500` and `2400`; record recognition and settlement journal references separately rather than overloading the existing single `journal_entry_id`.
3. Add an explicit payroll incentive component/linkage so draft refresh, approval, and paid payroll can distinguish salary expense from incentive settlement. Payroll settlement should clear `2400`, pay the award amount, and leave `5200`/salary-advance accounting semantics unchanged.
4. Preserve the existing currency hold, next-open-payroll cutoff, employee eligibility, project allocation, immutable paid payroll, and controlled adjustment rules already documented above.
5. Audit every payroll draft-generation/refresh path and accounting status transition before implementation; add rollback-only tests for idempotency, currency hold, draft refresh, journal balance, paid immutability, and post-commit notification failure isolation.

Do not activate policy R3 or change any acceptance-policy/test financial data. Do not touch `ahl_el_kheir_production_test`. Local migration application, PHP lint, runtime integration tests, and documentation verification remain pending.


## 2026-10-14 — Payroll component and dual award-journal schema committed

Added migration `database/migrations/2026-10-14_hr_employee_incentives_payroll_accounting.sql` (commit `211f1e033cf488f1a95e5d76956b4371183d6463`). It:
- adds `payroll.incentive_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00` so the payout component is not merged into salary `net_salary`;
- renames the award's ambiguous `journal_entry_id` to `recognition_journal_entry_id`;
- adds `settlement_journal_entry_id` and distinct indexes, allowing the earned-award recognition journal and payroll-settlement journal to be traced separately.

This migration depends on the existing incentive-ledger migration and salary-advance payroll migration; chronological filenames preserve that order. It contains only migration-time DDL, not runtime DDL.

**Verification boundary:** source commit only; do not assume it has been applied to local `ahl_el_kheir`. Before application, inspect the migration and confirm the expected source schema exists. Then verify the two payroll/award columns and indexes in the local DB. PHP source integration is not yet implemented by this schema commit.


## 2026-10-14 — First award accounting/payroll integration slice committed (runtime verification pending)

The user applied both 2026-10-14 migrations to local `ahl_el_kheir`; the supplied read-only output confirms account `2400` is active/liability, `5500` is active/expense, `payroll.incentive_amount` exists, and the two award journal references and indexes exist.

Source commits now add the first accounting slice:
- `9e18faa55e38adad122ad2d86a22484ef23ae826` — `modules/hr/lib_employee_incentives.php`: idempotent award recognition helper (Dr 5500 / Cr 2400), draft-only currency-checked award scheduling, and award settlement state helper.
- `6b24c7719f9913085a2a6a47b6b553399b474ba5`, `dab53825082c47c9948d8a143de1e85bbd636b87`, `67a76a3f5bdfb5f7cb20324a9002729c286a89d3` — payroll accounting settlement now keeps salary expense on 5200, debits 2400 for scheduled incentive settlement, pays net salary plus incentives from the payment account, and records settlement journal references on awards.
- `2d8b1c2ce123e2bca69133a8f9a083e6a3b7daf7`, `14a10d6f2cd6bf235d3873ca2b0b76cf1479ac0a`, `7128c32216747a15f7437a95a4e77ae14f8e5c4c` — draft refresh/approval paths refresh incentive scheduling before approval, and the payroll UI shows salary net, scheduled incentives, and total expected payout separately.

**Not yet complete or runtime-proven:** source-event creation at collection/sponsor/project confirmation boundaries; manager-verification UI and award creation; employee notifications when an award is scheduled/paid; full PHP lint; accounting and rollback-only integration tests. No local runtime test has yet been performed against this code. Do not call the feature complete and do not activate policy R3.

Next gate: user pulls latest `main`, runs PHP syntax checks on the three changed PHP files, and reports exact output. Fix any syntax/source issue before adding event-boundary integration. Then implement the manager verification/award-creation flow and source-event boundaries from inspected source, with post-commit notifications and rollback-only tests.


## 2026-10-14 — Incentive recognition source review: currency-hold transaction fix

The first syntax gate passed for the incentive helper, payroll accounting, and payroll page. Follow-up source review found that the recognition helper updated an award to `held_currency` and then threw. When invoked inside a caller-owned transaction, that exception could cause the caller to roll back the hold itself. The helper now returns `0` for a held/invalid-amount award instead of throwing after the update; when the caller owns the transaction it commits the hold, and when the caller owns the outer transaction the caller can commit it. Any caller must interpret return value `0` as “held; no recognition journal posted.” The helper also validates that account `5500` is an active expense account and `2400` is an active liability account, not merely active accounts by code.

This is a source-level correction only. PHP lint and rollback-only runtime verification of the updated helper remain pending. Do not treat the award lifecycle as runtime-proven; event creation, manager verification/award creation, and post-commit employee notifications remain unimplemented.


## 2026-10-14 — Updated recognition helper PHP syntax gate passed

After commit `8fff901700bb13a301338100948d02259dc4ff2e) was pulled to local `main`, the user ran PHP 8.2 lint against `modules/hr/lib_employee_incentives.php`. Result: `No syntax errors detected`. The earlier lint results for `modules/hr/lib_payroll_accounting.php` and `modules/hr/payroll.php` also passed before the recognition-helper hardening; those files were unchanged by the hardening commit.

This is syntax-only evidence. Recognition journal posting, currency holds inside caller-owned transactions, draft scheduling, payroll accounting, paid-award settlement, and concurrency remain unverified at runtime. Next gate is a rollback-only harness against local `ahl_el_kheir`, using the existing schema and source accounting conventions. Keep policy R3 approved but inactive; do not touch `ahl_el_kheir_production_test`.



## 2026-10-14 — Rollback-only accounting harness prepared (runtime pending)

Added `tools/run_hr_employee_incentive_accounting_rollback_tests.php` on `main` (initial commit `68a005d98b976b41d9f8773018407a06c8286cf8`; assertion hardening `5cb055746f70ea9f807cb0308bbf4e620ac3868a`). It uses an existing approved/active/retired policy version and an existing draft payroll with matching salary currency and no existing payroll journal. All inserted events/awards, recognition and settlement journals, and payroll changes are wrapped in one outer transaction and rolled back; it performs no DDL and does not create a policy.

The harness covers Dr 5500 / Cr 2400 recognition, duplicate-recognition idempotency, a persisted currency hold inside a caller-owned transaction, draft payroll scheduling and repeated refresh, balanced settlement while retaining 5200 salary expense and any 1410 recovery, award settlement linkage, paid-payroll refresh immutability, and rollback cleanup. It reports SKIP if safe existing fixtures/accounts are unavailable.

**Runtime status: PENDING local execution.** After pulling `main`, run `& "D:\\xampp\\php\\php.exe" "tools\\run_hr_employee_incentive_accounting_rollback_tests.php"` against only local `ahl_el_kheir`. Report the exact output. Do not run against `ahl_el_kheir_production_test`. No event-boundary hooks or manager award-creation UI should be added until this gate is understood. PHP lint and runtime execution have not been claimed by the remote commit itself.

Source-review risk still requiring resolution before concurrency-sensitive event hooks: `hrIncentiveNextJournalCode()` chooses MAX(JE suffix)+1; unique `journal_entries.entry_code` prevents duplicate stored codes, but concurrent recognitions can race for the same candidate. Inspect the repository's established journal-code allocation convention and add a narrow collision-safe approach if justified.


## 2026-10-14 — Rollback-only accounting harness prepared (runtime pending)

Added `tools/run_hr_employee_incentive_accounting_rollback_tests.php` on `main` (initial commit `68a005d98b976b41d9f8773018407a06c8286cf8`; assertion hardening `5cb055746f70ea9f807cb0308bbf4e620ac3868a`). It uses an existing approved/active/retired policy version and an existing draft payroll with matching salary currency and no existing payroll journal. All inserted events/awards, recognition and settlement journals, and payroll changes are wrapped in one outer transaction and rolled back; it performs no DDL and does not create a policy.

The harness covers Dr 5500 / Cr 2400 recognition, duplicate-recognition idempotency, a persisted currency hold inside a caller-owned transaction, draft payroll scheduling and repeated refresh, balanced settlement while retaining 5200 salary expense and any 1410 recovery, award settlement linkage, paid-payroll refresh immutability, and rollback cleanup. It reports SKIP if safe existing fixtures/accounts are unavailable.

**Runtime status: PENDING local execution.** After pulling `main`, run `& "D:\xampp\php\php.exe" "tools\run_hr_employee_incentive_accounting_rollback_tests.php"` against only local `ahl_el_kheir`. Report the exact output. Do not run against `ahl_el_kheir_production_test`. No event-boundary hooks or manager award-creation UI should be added until this gate is understood. PHP lint and runtime execution have not been claimed by the remote commit itself.

Source-review risk still requiring resolution before concurrency-sensitive event hooks: `hrIncentiveNextJournalCode()` chooses MAX(JE suffix)+1; unique `journal_entries.entry_code` prevents duplicate stored codes, but concurrent recognitions can race for the same candidate. Inspect the repository's established journal-code allocation convention and add a narrow collision-safe approach if justified.


## 2026-10-14 — Rollback-only accounting integration passed locally

The user pulled `main` through `3341df38`, linted `tools/run_hr_employee_incentive_accounting_rollback_tests.php`, and executed it against local `ahl_el_kheir`. PHP lint passed. Runtime output:

`PASS | recognition=Dr5500/Cr2400 123.45 SDG | duplicate_recognition=PASS | caller_transaction_currency_hold=PASS`

`PASS | draft_refresh=PASS | repeated_refresh=PASS | settlement=balanced | salary_5200=preserved | advance_1410=preserved | award_paid_link=PASS`

`PASS | rollback_cleanup=PASS | payroll=21 | employee=16 | policy=3 | test_rows=2 awards=2 journals=2`

This proves the harnessed recognition, duplicate protection, caller-owned currency hold, draft scheduling/repeat refresh, payroll settlement accounting, award linkage, and rollback cleanup on this local database. It does not prove business source-event hooks, manager verification/award creation, notifications, concurrency, or the complete incentive lifecycle. R3 remains approved/inactive; `ahl_el_kheir_production_test` was not used. Next, inspect the existing journal-code allocation convention and make the incentive recognizer resilient to the observed MAX+1 collision race before implementing concurrent event hooks.


## 2026-10-14 — Incentive accounting and collection source-event rollback checks passed

The user reported PHP lint and both rollback-only harnesses passing after the bounded incentive journal-code collision retry and collection source-event hook. Evidence includes: collection event is `pending_verification`; duplicate event is blocked; no award exists before verification; rollback cleanup passes; recognition posts Dr 5500 / Cr 2400 for the test amount; currency mismatch is held; draft refresh is idempotent; payroll settlement is balanced and preserves salary expense 5200 and salary-advance account 1410; paid-award linkage and rollback cleanup pass. These results validate only the tested helper/accounting/collection slice, not the complete lifecycle or concurrency.


## 2026-10-14 — Durable win-back reactivation attribution committed; local gate pending

The existing `winback_campaigns.handled_by` is mutable case ownership and is not a reliable incentive recipient. Migration `database/migrations/2026-10-14_winback_reactivation_attribution.sql` adds `reactivated_by_user_id` and `reactivated_at` to preserve the first successful reactivation transition. `modules/administration/lib_winback.php` locks the campaign row and updates the sponsor and campaign in one transaction; it writes the actor/time only if not already set. `modules/administration/winback.php` uses this helper and writes the audit entry after the business transaction succeeds. No incentive event or award is created by the campaign transition.

Rollback-only harness: `tools/run_winback_reactivation_rollback_tests.php`.  **Local gate PASSED 2026-10-14:** migration applied to `ahl_el_kheir`; PHP 8.2 lint passed for the helper, page, and rollback-only harness. Runtime output: `PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked`; `PASS | rollback_cleanup=PASS | campaign=3 | sponsor=1`. This proves only the tested transition and rollback boundary; no concurrent two-process test is claimed.

## 2026-10-14 — Win-back reactivation attribution passed local rollback-only acceptance

The user pulled commit `958a4644160071ea573db15f35e92f0f2d738c9c`, applied `database/migrations/2026-10-14_winback_reactivation_attribution.sql` to local `ahl_el_kheir`, and ran PHP 8.2 lint on `modules/administration/lib_winback.php`, `modules/administration/winback.php`, and `tools/run_winback_reactivation_rollback_tests.php`; all three reported no syntax errors. The rollback-only harness reported:

- `PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked`
- `PASS | rollback_cleanup=PASS | campaign=3 | sponsor=1`

This proves the harnessed atomic sponsor/campaign transition, durable actor/timestamp, blocked repeated transition, and rollback cleanup on the local database. It does not prove concurrent two-process execution or the full incentive lifecycle. Next work remains the reactivation source-event qualification at the financially confirmed payment boundary, using confirmed payment history to prove the 90-day lapse and durable reactivation actor, with idempotency and HR Manager verification. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation payment qualification committed; local acceptance pending

The confirmed-payment hook now checks a posted linked transaction and posted journal, a durable successful win-back transition, a previous confirmed monthly-sponsorship payment at least 90 days before the transition, and the first confirmed monthly-sponsorship payment created after the transition. The event remains `pending_verification`; it does not issue an award. Recipient user and employee IDs are snapshotted on source events so the win-back actor is distinct from the payment reviewer. The collection hook also snapshots the collector's user/employee relationship. Missing or ambiguous employee mappings produce no event. Same-second competing payment confirmations are conservatively held rather than risking duplicate awards.

New migration: `database/migrations/2026-10-14_hr_incentive_event_recipient_snapshot.sql`. New rollback-only harness: `tools/run_hr_employee_incentive_reactivation_rollback_tests.php`. The existing collection source-event harness now checks recipient snapshots. **Local acceptance is pending**: apply the recipient-snapshot migration once to `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and both incentive source-event harnesses, then run both harnesses. No local runtime result is claimed for this new slice. HR Manager verification/award issuance, employee notifications, new-sponsor/project hooks, and end-to-end lifecycle testing remain open. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation actor decision implemented; local acceptance pending

The actual reactivating employee is explicitly selected and server-validated; the confirmer is stored separately. The confirmed-payment source hook is now wired to the existing FM approval transaction. The source event remains pending HR Manager verification and no award is issued by the payment hook. See the current engineering gate above for migrations, harnesses, and exact local acceptance steps.


## 2026-10-14 — New-sponsor first-payment source hook committed; local acceptance pending

Added `hrIncentiveRecordNewSponsorPaymentEvent()` in `modules/hr/lib_employee_incentives.php` and wired it into the existing FM payment-confirmation transaction in `modules/accounting/fm_review_queue.php`. It requires an approved monthly-sponsorship payment, linked posted transaction and posted journal, sponsor creation before the payment record, an attributed `sponsors.brought_by_user_id` with exactly one employee mapping, no earlier financially confirmed monthly-sponsorship payment, and no qualifying durable reactivation transition. The event snapshots recipient user/employee IDs and remains `pending_verification`; it does not issue an award. The existing collection reward remains independently evaluated for the collector.

Rollback-only harness: `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php`. It uses an existing sponsor with a unique employee attribution and no prior approved monthly payment; if no safe fixture exists, it reports `SKIP` without mutating data. When run, it inserts only transactional test policy/payment/journal/event rows and rolls them all back.

**Local acceptance pending:** pull `main`; lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php`; run the new harness plus the existing source-event and reactivation rollback harnesses. No local result is claimed yet. Project-completion source/allocation, HR Manager verification and award issuance, post-commit employee notifications, and full lifecycle acceptance remain open. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.
