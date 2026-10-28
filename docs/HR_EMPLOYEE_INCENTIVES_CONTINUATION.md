# Employee Incentives — Continuation and Source Audit

**Checkpoint:** 2026-10-15  
**Status:** GM approval and activation governance for the protected active R5 policy are complete at the documented acceptance boundary. Collection source-event, reactivation, incentive accounting, HR Manager authorization, scheduling notification outbox retry/failure isolation, and employee-facing in-app notification UX have previously passed their documented local/browser acceptance gates. Project-completion automation is implemented on `main`, but a real FM-closure acceptance case exposed that the prior rollback-only harness did not cover a supervisor assigned only through `project_supervisor_assignments`. The earlier statement that the harness proved the separate-supervisor case was incorrect and is superseded by the 2026-10-18 regression note below. The correction and the PRJ-0024 historical supervisor recovery are runtime-verified on the disposable test database, as recorded in the 2026-10-18 evidence below. Full lifecycle acceptance remains incomplete: the new-sponsor first-payment hook's documented local acceptance is still pending, and the real browser PM-to-FM project-closure workflow has not been fully verified. Active R5 must remain unchanged; never use `ahl_el_kheir_production_test`.

## 2026-10-16 — Project Manager closure-review panel investigation

The real-browser PM-to-FM project-closure acceptance test remains open. Source inspection found that the `$status === 'closed'` conditional around the Project Manager's financial-closure card also enclosed the separate closure-request review/reopen section, so the review panel was skipped for a project in `under_review`. Commit `133d0978ac350018fe5425bc32a438dd4d0697e7` on `main` closes the financial-card conditional immediately after that card, before the independent `$status !== 'closed'` review/reopen condition. The first source correction left the former trailing `endif` in place; local PHP lint then correctly caught the unmatched conditional at line 2626. Commit `c93e8db646923ee44622fdabbbf9d3fbe990b5ae` removes that obsolete extra closing directive. Local PHP lint and real-browser verification are still pending. Do not submit administrative closure until the panel renders and the applicable financial gate is inspected. Continue using disposable test DB `ahl_el_kheir_incentive_test_20261015_r1`; preserve active R5 policy and do not touch live DB.

## Scope

Three policy-driven, fixed-amount incentive categories:

1. Monthly sponsorship collection: reward the employee/collector after the linked collection is financially confirmed.
2. New sponsor or sponsor reactivation: reward only after sponsorship is established and its first payment is financially confirmed. Reactivation uses the agreed 90-day lapse lookback.
3. Successful project completion: reward after both administrative project closure and FM financial closure. Under the approved R5 acceptance policy, every eligible employee assigned to the project team at PM administrative closure receives the full configured project-completion amount; it is not a shared pool and does not require a separate per-project incentive approval.

The reward amount and currency are policy data configured when a policy version is created. **10,000 SDG was only an example, not an approved amount.** The monthly collection reward is not percentage-based.

## Agreed policy decisions

- Governance: GM approves the incentive policy; FM validates financial controls; HR administers it.
- Collection recipient: the recorded collector.
- Sponsor acquisition/reactivation recipient: the employee who brought the sponsor, resolved through the actual user-to-employee relationship.
- Project acceptance: administrative closure plus FM financial closure.
- Project rewards (R5 accepted rule): full configured amount per eligible active employee snapshotted from the project team at PM administrative closure, including an eligible Project Supervisor if assigned before closure. This is not a shared pool; no separate human approval metadata is fabricated. The award remains blocked until durable FM financial closure and zero controlled balance are verified.
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


## 2026-10-14 — HR Manager verification and award issuance page added (runtime pending)

Commits on `main`:
- `f0cef4f45e6564b76f097572732430c1659070f3` — add `modules/hr/incentive_verification.php`.
- `02bb9eef524025ce858f146baab8b6414f518408` — correct the localized category-label field.
- `4e4101988c41f2028f9aef384172ecd6b9e0c7cc` — add the HR Manager-only dashboard link.

The new page is restricted server-side to `hr_manager`. It lists recorded events and only allows verification for non-project events that are still `pending_verification`, have a snapshotted recipient, resolve to the same employee/user relationship, and currently map to an active working employment state and active user account. The policy rule must remain enabled with a positive fixed reward amount. The event transition, award insert, and recognition journal are performed in the same transaction; currency/amount holds are preserved by the existing recognition helper. Duplicate awards are checked and are additionally constrained by the ledger's unique event/employee key.

Project-completion verification is deliberately blocked because the approved-allocation workflow is not yet implemented. This page does not send the employee's next-payroll notification; scheduling and notification timing remain a separate open acceptance gate.

**Verification status: source committed, local syntax/runtime acceptance pending.** Do not describe the page as verified until the user pulls `main`, runs PHP lint on the new page and updated dashboard, then tests the HR Manager authorization and one safe rollback-only verification/award case against local `ahl_el_kheir`. Keep policy R3 inactive and do not touch `ahl_el_kheir_production_test`. Do not rerun previously passed source-event or accounting harnesses absent regression evidence.


## 2026-10-14 — HR incentive verification rollback harness prepared (runtime pending)

Added `tools/run_hr_incentive_verification_rollback_tests.php` on `main` (commit `9d7ab5e2c442b7df8e72bb9b36b60afcdb7c2040`). The harness creates a synthetic collection event using an existing eligible positive collection rule, verifies the snapshotted active working employee relationship, performs the same pending-to-verified and award-insert sequence used by `modules/hr/incentive_verification.php`, invokes the production recognition helper, checks the balanced Dr 5500 / Cr 2400 journal and duplicate-recognition idempotency, and rolls back all synthetic rows/journals. It performs no DDL, policy mutation, payroll mutation, or commit. This is a rollback-only database integration harness, not an HTTP/session/CSRF browser test.

The user's local read-only inspection found policy version 3 is approved (not active), with an enabled collection rule of 1,000.00 SDG; accounts 5500 and 2400 are active with the expected expense/liability types; one active `hr_manager` user exists; and no pending events currently exist. These observations do not prove verification runtime behavior. PHP lint and execution of the new harness against local `ahl_el_kheir` remain pending. Run only `tools/run_hr_incentive_verification_rollback_tests.php`; do not rerun previously passed source-event/accounting harnesses without regression evidence. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`. Employee notification after award scheduling remains open, and project-completion verification remains blocked pending approved allocation workflow.


## 2026-10-14 — HR Manager verification/award rollback acceptance passed locally

The user pulled `main`, PHP-linted `tools/run_hr_incentive_verification_rollback_tests.php` successfully, and ran it against local `ahl_el_kheir`. Exact runtime evidence:

`PASS | hr_manager_fixture=available | recipient_eligibility=PASS | pending_to_verified=PASS`

`PASS | award_creation=PASS | recognition=Dr5500/Cr2400 1000 SDG | duplicate_recognition=PASS`

`PASS | rollback_cleanup=PASS | policy=3 | employee=1`

This verifies the tested database path: recipient/employment eligibility, pending-to-verified transition, award creation, posted balanced incentive recognition, duplicate-recognition idempotency, and cleanup after rollback. It is not an HTTP/browser/session/CSRF test and does not prove the employee notification requirement. Policy version 3 was approved and its enabled collection rule was used only as test input; the policy was not activated or changed. The synthetic rows and journals were rolled back. Do not rerun this passed harness absent regression evidence. The next open task is to inspect the actual notification table/helper contract and implement failure-isolated employee notification when an award becomes scheduled for an eligible draft payroll. Project-completion award verification remains blocked pending approved recipient allocations. Do not use `ahl_el_kheir_production_test` or activate policy R3.


## 2026-10-14 — Incentive payroll-scheduling notification outbox added; local acceptance pending

Added migration `database/migrations/2026-10-14_hr_incentive_notification_outbox.sql`, production logic in `modules/hr/lib_employee_incentives.php`, and `tools/run_hr_incentive_notification_outbox_tests.php`. When a verified award is newly assigned to a draft payroll, a unique outbox row is inserted in the same transaction as award scheduling. Delivery is attempted only after that transaction commits. The notification insert and outbox `sent` transition share their own transaction; a failure leaves the item pending, records/logs the failure where possible, and does not roll back the committed payroll/award scheduling. Repeated refreshes cannot create a second outbox item for the same award, and concurrent dispatchers serialize on the outbox row.

The implementation uses the base `notifications` columns confirmed in the user's local schema and does not require the optional `reference_id`/`reference_type` columns (the local read-only schema query did not show those columns). The notification is in-app only and says the incentive is scheduled in the stated payroll period, subject to normal payroll approval/payment. The acceptance harness injects one delivery failure, checks scheduling remains committed and retryable, retries successfully, checks recipient/amount/period and duplicate protection, then removes its temporary event/award/outbox/notification and restores the original draft payroll incentive total.

**Local acceptance is pending.** Pull `main`, apply the new migration once to local `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php` and `tools/run_hr_incentive_notification_outbox_tests.php`, then run the new harness. It uses a safe eligible draft-payroll fixture and reports `SKIP` if none exists or an earlier pending outbox item needs investigation. Do not use `ahl_el_kheir_production_test`, activate policy R3, or rerun already-passed source-event/verification/accounting harnesses without regression evidence. Project-completion award verification remains blocked pending approved recipient allocations.


## 2026-10-14 — Incentive payroll-scheduling notification outbox: local acceptance PASSED

The user pulled `main`, applied `database/migrations/2026-10-14_hr_incentive_notification_outbox.sql` to local `ahl_el_kheir` (the table was initially absent), and PHP 8.2 lint passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_incentive_notification_outbox_tests.php`.

Exact local runtime evidence:

```
HR incentive scheduling notification failed for outbox 1: TEST_INJECTED_INCENTIVE_NOTIFICATION_FAILURE
PASS | scheduling_commit_survives_notification_failure=PASS | outbox_retryable=PASS
PASS | retry_delivery=PASS | correct_recipient_amount_period=PASS | repeated_dispatch_no_duplicate=PASS
PASS | cleanup=PASS | payroll_amount_restored=PASS
```

The injected delivery failure was isolated from the already-committed payroll/award scheduling. The outbox remained retryable; a subsequent dispatch delivered the correct in-app notification and repeated dispatch did not create a duplicate. Test cleanup passed and the original payroll incentive amount was restored. The warning line is the deliberately injected failure, not an acceptance failure.

**Acceptance boundary:** this proves the tested local database scheduling/outbox/retry/idempotency path. It does not prove browser/session/CSRF behavior, employee-facing browser UX, email/push delivery, true two-process concurrency, or the complete incentive lifecycle. The notification is in-app only. Project-completion verification remains blocked pending the approved recipient-allocation workflow. Keep policy R3 inactive, do not use `ahl_el_kheir_production_test`, and do not rerun already-passed incentive harnesses absent concrete regression evidence.

**Next open gate:** inspect/test the actual employee-facing notification UX in the local application and verify HR Manager page authorization in browser/session context, without reopening the passed rollback-only harnesses.

## 2026-10-15 — HR incentive policy notification boundary

The HR Manager policy creator is to receive in-app notification only for two outcomes: (1) GM rejection, including the rejection reason, and (2) FM activation. The GM-approval handler previously also notified the HR creator; that extra notification was removed in commit `2c47e5aa6691b735f996ebf7c0c2091926698d54`. The FM-awaiting-activation notification remains directed to FM users. The existing post-commit HR notification call after successful FM activation remains in place.

**Verification boundary:** this is a narrow source change; local PHP lint and browser/runtime verification are still required. Policy R3 is currently approved but unactivated; its effective date is 2026-10-14, so it cannot be activated on 2026-10-15 under the existing no-retroactive-activation guard. Do not directly update its status/date in the database. To test the successful activation notification, use a new policy through the normal HR → GM → FM workflow with a valid current/future effective date, then verify the committed active status and the notification row for the HR creator. If activation commits but the notification is absent, inspect delivery evidence before changing the shared notification helper. Do not use `ahl_el_kheir_production_test`.

## 2026-10-15 — HR incentive policy notification workflow: runtime acceptance PASSED

The user confirms the full policy-governance workflow was exercised locally and is working, including the HR creator's rejection notification with the GM's reason, no HR notification on GM approval, the FM notification that an approved policy awaits activation, and the HR creator's notification after successful FM activation. This closes the targeted policy-notification acceptance task; do not repeat its stages or reopen the source change without concrete regression evidence.

The source change removing the HR notification on GM approval is commit `2c47e5aa6691b735f996ebf7c0c2091926698d54`. The local FM reset script was run against `ahl_el_kheir`; its exact-state guard returned `guarded_activation_matches=0` and `reset_rows=0`, leaving policy R3 unchanged (`approved`, activation fields NULL, effective date `2026-10-14`). A read-only query found no approved policy with `effective_from >= CURRENT_DATE` at that checkpoint. The user subsequently completed and confirmed the valid full workflow; the successful workflow must not be conflated with the no-op R3 reset.

**Scope boundary:** this acceptance closes only the HR incentive policy governance/notification flow. It does not close the full employee-incentives lifecycle. Remaining open work includes the employee-facing notification UX and HR Manager authorization in browser/session context, plus project-completion verification/approved recipient allocations. Do not rerun already-passed rollback-only incentive harnesses or use `ahl_el_kheir_production_test` absent concrete regression evidence.



## 2026-10-15 — Audited GM withdrawal of unactivated policy approval (local verification pending)

**User request:** return policy V1/R3 (`ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3`, status approved/awaiting FM activation) to HR edit mode so all four existing incentive categories can be configured for lifecycle/accounting testing.

**Source change on main:** `modules/hr/incentive_policy.php` now exposes a GM-only action for an approved policy that has never been activated. The GM must supply a reason. The transaction locks the policy row, checks status and NULL activation evidence, inserts approval-history action `returned_to_draft`, changes status to `draft`, and commits before notifying the HR creator and FM. Activated policies cannot use this path. The history UI labels the action explicitly. Migration `database/migrations/2026-10-15_hr_incentive_policy_approval_withdrawal.sql` adds the action to the existing approval-history ENUM. Commits: migration `cb522dcee4b2e78e03f8d4c97402a61ca7e23d6c`; source `a9501f5f41e4f959a734d175cf14523c49c1e6d2`.

**Local verification is pending; no claim that R3 has already been reset.** Next: pull `main`; inspect whether the migration's ENUM value already exists and apply the migration to `ahl_el_kheir` only if needed; lint `modules/hr/incentive_policy.php`; sign in as GM and use the new return-to-draft action with a meaningful reason; verify status, history, and notifications; sign in as HR and edit all four category rules. Then use the ordinary HR → GM → FM workflow. R3's existing effective date (2026-10-14) is in the past and must be changed through the HR form. Respect R4's later effective date and existing chronological activation guard; do not edit statuses/dates directly in SQL. Keep `ahl_el_kheir_production_test` untouched.


## 2026-10-15 — Employee incentive notification UX: browser acceptance PASSED
The user confirms live notification delivery was browser-verified. The controlled test notification appeared in the live employee UI, and its Arabic body was readable in notification history when inserted using UTF-8 bytes. Cleanup output proves test notification ID 195 was deleted (`rows_deleted=1`), no matching test row remained, and recipient user 16 had zero notifications and zero unread notifications afterward.

**Closed acceptance gate:** employee-facing in-app notification delivery/display for the tested path. This does not prove email/push delivery, true two-process concurrency, or full incentive lifecycle acceptance. Do not rerun the passed outbox, source-event, verification, or accounting harnesses without concrete regression evidence.

**Next local gate:** verify the GM-only audited return-to-draft action for the approved, never-activated incentive policy using the normal browser workflow; confirm reason/history/notifications, then sign in as HR and edit all four incentive categories through the UI. Inspect whether the migration ENUM value already exists before applying the migration to `ahl_el_kheir`; lint `modules/hr/incentive_policy.php`. Respect the later R4 policy's effective-date chronology, do not directly mutate policy status/dates in SQL, and do not touch `ahl_el_kheir_production_test`. HR Manager browser/session authorization and project-completion recipient allocation/verification remain separate open items.


## 2026-10-15 — HR incentive verification authorization PASSED; active policy checkpoint corrected

The user reports the local browser/session authorization acceptance passed:
- Existing HR Manager account: the dashboard link was visible and the incentive verification page opened normally.
- Existing HR Staff account: the verification link was absent and direct access to `modules/hr/incentive_verification.php` was denied/redirected as expected.
- No additional details or failure symptoms were reported.

This closes only the HR Manager browser/session authorization gate. Do not rerun the already-passed source-event, accounting, verification rollback, or notification-outbox harnesses without concrete regression evidence.

**Policy-state correction from the user's actual local browser screen (2026-10-15):** the page shows one policy, `ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5`, Version V1, status active (`سارية`), effective date `2026-10-15`; its four enabled rule amounts are collection 1,000 SDG, new sponsor 2,000 SDG, reactivation 1,000 SDG, and project completion 1,500 SDG. The displayed approval history shows HR submission at `2026-10-15 09:19:58` and GM approval at `2026-10-15 09:20:10`. This is user-reported local UI evidence, not an independent database query.

This current R5 observation supersedes older continuation instructions that describe R3 as the active testing target or direct the next session to return R3 to draft. **Do not reset, edit, deactivate, or otherwise modify active R5; do not create another policy unless the user explicitly agrees.** Do not change policy status/effective dates directly in SQL. Preserve the prior historical R3/R4 notes as chronology only, not as current instructions.

**Next open engineering unit:** inspect the actual project-completion lifecycle/source integration and existing `hr_incentive_project_allocations` schema before proposing implementation. Project-completion incentive verification remains intentionally blocked until the approved recipient-allocation workflow is defined and implemented. Do not infer who qualifies, how percentages are assigned, who approves the allocation, or rounding/residual treatment from the schema alone. Preserve project lifecycle/approval evidence and existing accounting boundaries. No project-completion source changes or tests are claimed in this checkpoint. Keep `ahl_el_kheir_production_test` untouched.


## 2026-10-15 — Project-completion incentive source audit (design gate remains open)

Source inspected on `main`: `modules/hr/incentive_verification.php`, `modules/hr/lib_employee_incentives.php`, `modules/projects/view.php`, `modules/projects/project_lib.php`, `modules/accounting/fm_review_queue.php`, and `database/migrations/2026-10-12_hr_employee_incentives_ledger.sql`.

Findings:
- `modules/hr/incentive_verification.php` explicitly refuses to verify a `project_completion` event until the approved recipient-allocation workflow exists. Keep this guard.
- The ledger migration already defines `hr_incentive_project_allocations` with `event_id`, `employee_id`, `recipient_user_id`, `allocation_percent`, `reward_amount`, `currency_code`, `approved_by_user_id`, `approved_at`, and `allocation_note`. This schema alone does not define the authorized approver role or how allocations are proposed/calculated.
- The project page's `change_status` action updates `project_lifecycle.lifecycle_status` to `completed` and writes project status history in a transaction. In the inspected completion path, there is no incentive-event creation call and no separate formal-acceptance record established by that action.
- `modules/hr/lib_employee_incentives.php` permits `project_lifecycle` as the source table for a project-completion event, but the inspected project completion path does not call the event recorder. Do not claim that the hook exists.
- The project's current active team can be read from `project_team` (`unassigned_at IS NULL`), but team membership by itself does not establish eligibility for a completion reward.

**Implementation remains blocked on business-rule decisions, not on removing the guard:** define the authoritative formal-acceptance evidence/transition; who may propose recipients; whether recipients are manually selected or derived from some approved evidence; who approves the allocation and under which existing role; whether allocation percentages must total exactly 100%; and how currency rounding/remainder is assigned. Preserve existing audit/accounting boundaries. No project source code, schema, policy, or incentive data was changed in this source-audit step. Active R5 remains untouched; do not create another policy unless the user explicitly agrees. Do not use `ahl_el_kheir_production_test`.


### Additional existing project-completion acceptance constraint — preserve

Prior incentive-design discussion established that a project-completion award must remain blocked until **administrative project closure and FM financial closure are both evidenced**, eligible team recipients have explicit approved allocations, and those allocations are approved before award issuance. A project lifecycle status such as `completed` (or a project-screen stage/status alone) is not sufficient proof. This constraint is binding alongside the unresolved decisions above; do not implement event qualification from `lifecycle_status='completed'` alone.


## 2026-10-15 — Project-completion incentive automation implemented in source; local acceptance pending

The user confirmed the governing rule: PM administrative closure starts processing; awards are issued only after durable FM financial closure and a fresh controlled-balance check (existing tolerance 0.009). Each eligible employee on the active project team at PM closure receives the full active-policy project-completion amount (R5 currently shows 1,500 SDG per eligible employee). There is no separate per-award FM/HR approval. The allocation is per employee, not a shared pool. Active policy R5 was not changed.

Source commits on `main`:
- `6166c6f1` — migration `database/migrations/2026-10-15_hr_incentive_project_auto_allocation.sql` allows NULL approval metadata for system-generated allocations; it does not invent a human approver.
- `e1507598`, `1070775c`, `4e505fa6` — incentive helper snapshots active, working, unambiguously mapped team employees within the PM closure transaction; creates per-employee full-policy allocations; requires the durable `FM_FINANCIAL_CLOSURE` audit row, administrative closure, and a rechecked controlled balance before idempotent award/recognition processing; refreshes the earliest current/future draft payroll when available and relies on the existing notification outbox.
- `4cdcab41`, `896c6c40` — starts and snapshots the incentive event atomically with PM closure; a processing error rolls back that closure attempt instead of silently losing the recipient snapshot.
- `bc8b864c`, `9a879bae` — invokes processing from both FM financial-closure paths and retries processing on later FM project-page loads when durable closure evidence already exists.
- `2836d5ab` — updates the HR verification page's project-event text and reports automatic award totals.

The prior requirement for human-approved project allocations is superseded by the user's explicit newer instruction. The old approval columns remain for historical/manual rows but are NULL for automated rows. Project-completion awards are not processed by the HR Manager's manual verification form.

**Verification boundary:** these are source commits only. No PHP lint, migration application, local MariaDB runtime test, or browser acceptance has been run for this new slice. Do not describe the automation as verified. Next local gate: pull `main`; inspect and apply the new migration once to local `ahl_el_kheir`; PHP 8.2 lint the changed PHP files; then run a purpose-built rollback-only project closure/award test before exercising a real project. Never use `ahl_el_kheir_production_test`; never modify active policy R5; do not rerun already-passed incentive/accounting/notification harnesses absent regression evidence.


### Follow-up — rollback-only project closure harness added; local acceptance pending

Added `tools/run_hr_project_incentive_closure_rollback_tests.php` (commit `df40dc1c`). The harness uses an existing closed project fixture, changes its closure timestamp only inside an uncommitted transaction, snapshots eligible team members under the active effective policy, supplies temporary FM-closure audit evidence only if needed, checks per-employee awards and recognition journals, then rolls the transaction back. It skips if the migration or a suitable fixture is absent. Commit `2bff519b` makes the processor respect a caller-owned transaction so this test can validate rollback without leaving award, journal, notification, audit, or payroll changes.

**Local acceptance is still pending.** Pull `main`; inspect and apply `database/migrations/2026-10-15_hr_incentive_project_auto_allocation.sql` once if the allocation approval columns are still NOT NULL; PHP 8.2 lint `modules/hr/lib_employee_incentives.php`, `modules/hr/incentive_verification.php`, `modules/projects/view.php`, `modules/projects/view_fm.php`, and the new harness; then run only `tools/run_hr_project_incentive_closure_rollback_tests.php`. A `SKIP` is not a PASS. This does not prove browser behavior or true two-process concurrency. Keep R5 unchanged and never use `ahl_el_kheir_production_test`.


### Follow-up — positive-balance rollback coverage without accounting fabrication (2026-10-15)

The current disposable DB has no eligible closed project with zero controlled balance. Project 10 has 260,000 SDG in posted allocations and 30,000 SDG controlled balance, with no funding-return rows. Do not manufacture a return or alter posted accounting records to force the full harness through the award path. The harness now uses a genuine eligible closed project with a readable balance to verify recipient snapshotting, NULL system-allocation approval metadata, positive-balance award blocking, and rollback cleanup. Award/recognition success remains SKIP until a genuine zero-balance fixture is available. Local PHP lint and execution against ahl_el_kheir_incentive_test_20261015_r1 are pending. Active R5 and live accounting remain untouched.


### 2026-10-15 — Project incentive harness SQL failure: root cause and correction

The first local execution of `tools/run_hr_project_incentive_closure_rollback_tests.php` failed with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'currency_code' in 'field list'`. Source inspection isolated the invalid reference to `project_lifecycle.currency_code` in `hrIncentivePrepareProjectCompletion()`; project lifecycle does not expose a source currency field. Commit `920d4221122a65a8579e4bed5ef289e64a4934db` removes that column from the query and leaves `hr_incentive_events.source_currency_code` NULL for this non-monetary source event. The award currency remains sourced from the active policy rule and stored on the recipient allocation/award. This correction has not yet been locally linted or runtime-tested. Next: pull `main`, lint `modules/hr/lib_employee_incentives.php` and the project incentive rollback harness, then rerun only the rollback harness against `ahl_el_kheir_incentive_test_20261015_r1`. Do not alter active R5, live accounting, or fabricate funding returns. A later SQL failure must be diagnosed against the actual schema before further edits.


### Local verification result — project incentive positive-balance rollback harness (2026-10-15)

User ran the harness on local PHP 8.2 against disposable database `ahl_el_kheir_incentive_test_20261015_r1` after pulling the source correction. Both `php -l modules/hr/lib_employee_incentives.php` and `php -l tools/run_hr_project_incentive_closure_rollback_tests.php` passed. Runtime output:

- `PASS | project_team_snapshot=1 | no_approval_metadata=PASS | positive_balance_award_block=PASS`
- `PASS | rollback_cleanup=PASS | project=10 | controlled_balance=30000.00`
- `SKIP | award-recognition success branch | No existing eligible closed project has a zero controlled balance; no accounting records were fabricated or altered.`

This verifies the positive-controlled-balance block and transactional cleanup in the disposable DB only. It does **not** verify successful award creation or recognition journals, nor browser/runtime behavior on a real project. The success branch remains open pending a legitimate eligible closed project with controlled balance <= 0.009. Do not fabricate funding returns or edit posted accounting records to create one. Active policy R5 and live accounting were not used or changed by this test.

## 2026-10-15 — Project completion incentive success-path harness added

The existing `tools/run_hr_project_incentive_closure_rollback_tests.php` already passed the negative gate (positive controlled balance blocks award issuance and rollback cleanup passes); its successful award-recognition branch was skipped because no existing eligible closed project had a zero controlled balance. No accounting records were fabricated or altered.

Added `tools/run_hr_project_incentive_success_rollback_tests.php` on `main` in commit `5b6fb97d975bdc3e591fa811ca55b2a214092bfc`. The new harness is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, verifies the active policy is the protected R5 acceptance policy, and uses a synthetic project with no funding, posted expenses, or returns. An eligible Project Supervisor is snapshotted before simulated administrative closure. The test checks that zero balance without durable `FM_FINANCIAL_CLOSURE` evidence cannot issue an award, then verifies the full configured reward and recognition journal (Dr 5500 employee incentive expense / Cr 2400 incentive payable), followed by rollback cleanup.

**Status: RUNTIME PENDING.** The new harness has not yet been locally PHP-linted or executed against MariaDB. Do not describe the success path as verified until its output passes. This harness does not test payroll inclusion, notification delivery, or the slow browser project-creation workflow; those remain separate open acceptance gates. The active R5 policy is read-only throughout.

## 2026-10-15 — Project completion award recognition: RUNTIME VERIFIED

User locally ran `tools/run_hr_project_incentive_success_rollback_tests.php` against `ahl_el_kheir_incentive_test_20261015_r1`; PHP lint passed and runtime result:
```
PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS
PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS
PASS | rollback_cleanup=PASS | policy=R5 | reward=1500.00 SDG
```

This proves, at the rollback-harness boundary, that an eligible Project Supervisor assigned before simulated closure receives the full R5 reward of 1500.00 SDG after durable FM financial closure and zero controlled balance; no award is issued before the FM gate; the recognition journal debits 5500 and credits 2400; and all test changes roll back. No posted accounting records or active policy values were changed.

**Still open:** payroll inclusion/refresh, employee notification delivery, and real PM-to-FM browser closure workflow. These were not exercised by this harness. Next step is to inspect the current implementation and existing harnesses before choosing a narrow test; do not rerun the passed recognition harness without regression evidence.


## 2026-10-16 — FM sponsor-payment approval integration source checkpoint

The FM approval business operation is extracted to `modules/accounting/lib_sponsor_payment_approval.php`. The page delegates its approval POST to the shared operation and preserves its role/CSRF boundary and post-commit GM notification. The shared operation does not perform DDL or own the transaction. Prepared accounting helpers cover fee-policy reads, fee snapshots, and transaction journal posting; existing wrappers preserve their prior setup behavior for other callers. The FM approval POST skips the page's schema setup calls, which remain on non-POST page loads.

Added `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php`. It refuses any database other than `ahl_el_kheir_incentive_test_20261015_r1`, reads but does not change active R5, exercises success and an injected new-sponsor-event failure after financial writes, and rolls both scenarios back. Local PHP lint/runtime acceptance is pending. Pull `main`, lint the three changed accounting PHP files and the harness, then run only the new harness against the disposable DB. Never run it against `ahl_el_kheir`; do not alter R5 or repeat closed tests.


### 2026-10-16 — Integration harness failure diagnosis and correction

First local run of `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php` failed at new-sponsor event recording with `يجب تسجيل حافز استقطاب الكفيل ضمن معاملة اعتماد الدفعة.`. Source tracing identified the root cause in `ak_apply_admin_fee_snapshot_prepared()`: it called the legacy `ak_calculate_admin_fee()` wrapper. When the prepared policy lookup returned `null`, that wrapper interpreted null as “policy not supplied” and called `ak_get_admin_fee_policy()`, which performs schema setup DDL; MariaDB implicitly ended the active transaction. Corrected the prepared snapshot function to call `ak_calculate_admin_fee_prepared()` directly. Fix commit: `d2e8ae01deade8ea88c4d7f68722b1d4e78a4aef`. Runtime verification is still pending; rerun only the new integration harness on the exact disposable DB after pulling this commit. Do not weaken the transaction guard or modify R5.


## 2026-10-16 — FM sponsor-payment integration RUNTIME VERIFIED; project payroll gate added

The user ran the rollback-only `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php` against `ahl_el_kheir_incentive_test_20261015_r1`; all three reported checks passed:
```
PASS | shared_FM_approval=PASS | posted_transaction_journal=PASS
PASS | new_sponsor_pending_verification=PASS | no_premature_award=PASS | success_rollback=PASS
PASS | injected_incentive_failure=PASS | payment_transaction_journal_rollback=PASS
```
The user also PHP 8.2-linted all five relevant accounting PHP files and the harness successfully. This closes this FM integration gate at the harness boundary. The live database and active R5 were not used or changed. The known CLI-only `REQUEST_METHOD` warning is not a production fix target.

Source inspection confirmed the existing generic payroll/accounting and outbox harnesses already cover general draft scheduling, settlement, and notification retry. They do not prove that the project-completion processor assigns its own generated award to the correct current/future draft payroll. Added `tools/run_hr_project_incentive_payroll_rollback_tests.php` and included it in the PHP 8.2 syntax workflow. The new test is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, preserves R5, uses a synthetic no-financial-activity project, and checks the project award's payroll linkage, incentive component increase, unchanged salary net, pending outbox row, and rollback restoration. **Local PHP lint and runtime acceptance of this new harness are pending; do not claim PASS until its exact output is reviewed.**

Next: pull `main`; lint the new harness; run only this new harness against the named disposable database. Do not rerun the closed project-recognition, generic payroll/accounting, outbox, or FM integration harnesses without regression evidence. Real PM-to-FM browser closure workflow remains a separate open gate.


## 2026-10-16 — FM sponsor-payment integration RUNTIME VERIFIED; project payroll gate added

The user ran the rollback-only `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php` against `ahl_el_kheir_incentive_test_20261015_r1`; all three reported checks passed:
```
PASS | shared_FM_approval=PASS | posted_transaction_journal=PASS
PASS | new_sponsor_pending_verification=PASS | no_premature_award=PASS | success_rollback=PASS
PASS | injected_incentive_failure=PASS | payment_transaction_journal_rollback=PASS
```
The user also PHP 8.2-linted all five relevant accounting PHP files and the harness successfully. This closes this FM integration gate at the harness boundary. The live database and active R5 were not used or changed. The known CLI-only `REQUEST_METHOD` warning is not a production fix target.

Source inspection confirmed the existing generic payroll/accounting and outbox harnesses already cover general draft scheduling, settlement, and notification retry. They do not prove that the project-completion processor assigns its own generated award to the correct current/future draft payroll. Added `tools/run_hr_project_incentive_payroll_rollback_tests.php` and included it in the PHP 8.2 syntax workflow. The new test is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, preserves R5, uses a synthetic no-financial-activity project, and checks the project award's payroll linkage, incentive component increase, unchanged salary net, pending outbox row, and rollback restoration. **Local PHP lint and runtime acceptance of this new harness are pending; do not claim PASS until its exact output is reviewed.**

Next: pull `main`; lint the new harness; run only this new harness against the named disposable database. Do not rerun the closed project-recognition, generic payroll/accounting, outbox, or FM integration harnesses without regression evidence. Real PM-to-FM browser closure workflow remains a separate open gate.


## 2026-10-16 — Project incentive payroll harness made self-contained (local verification pending)

The project payroll rollback harness previously skipped because no current/future draft payroll existed for the selected Project Supervisor. Read-only local diagnostics confirmed the supervisor salary history is effective through October 2026, employment category is `working`, salary currency is SDG, no October payroll row exists, and `hrPayrollPolicyGetActive()` resolves payroll policy version 1 effective 2026-10-01.

Commit `797f89afe848df39fee26a6f16bae6f2854c3c03` updates `tools/run_hr_project_incentive_payroll_rollback_tests.php`: when no suitable draft exists, the harness now prepares a current-period draft payroll fixture inside its existing rollback transaction, using the same INSERT columns/initial net-salary behavior as `modules/hr/payroll.php`, the actual effective salary-history row, the historical employment-state rule, matching currency, and the production payroll-policy helper. It refuses to mix in a pre-existing payroll row or verified unscheduled award, and verifies the temporary payroll row is absent after rollback. If a valid current/future draft already exists, it continues checking restoration of that existing row.

**Status: source change only; local PHP lint and runtime are pending.** Pull `main`, lint the harness, then run only this harness against `ahl_el_kheir_incentive_test_20261015_r1`. Preserve active R5 and do not run rollback tests against live `ahl_el_kheir`. Existing closed project recognition, FM approval, generic payroll, and outbox tests remain closed.
## 2026-10-16 — Project-completion incentive → draft payroll integration RUNTIME VERIFIED

User pulled `main` at `472fdd0f46705379a18518554f3b5d976f9d35de7e`, PHP 8.2 linted `tools/run_hr_project_incentive_payroll_rollback_tests.php` successfully, and executed the harness against the configured disposable test database. Exact user-reported output:

```
PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS
PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS
PASS | project_award_draft_payroll_link=PASS | incentive_total=PASS | net_salary_unchanged=PASS | outbox_pending=PASS
PASS | rollback_cleanup=PASS | temporary_payroll_rolled_back=PASS | policy=R5 | reward=1500.00 SDG
```

This closes the project-completion award-to-draft-payroll rollback-harness gate: award/payroll linkage, incentive component increase, unchanged net salary, pending outbox row, rollback cleanup, and rollback of the temporary draft payroll all passed. The earlier project recognition/FM gate checks also passed in the same run. Active policy R5 remains protected and was not changed. This is disposable-database rollback-harness evidence, not a real payroll run.

Do not rerun this closed harness without regression evidence. Remaining distinct acceptance work: real PM-to-FM browser closure workflow and end-to-end employee notification delivery/experience. Do not infer those from the pending-outbox assertion.

## 2026-10-16 — Project incentive financial-closure draft-allocation safety guard (processor rollback gate passed; FM closure-path verification pending)

A source audit found that the controlled balance intentionally counts posted funding allocations only, but FM financial closure could still be recorded while a draft allocation remained. The same gap could allow the project-completion incentive processor to proceed if a caller supplied a zero balance and durable closure audit evidence existed.

The targeted correction adds a reusable draft-allocation predicate, blocks explicit FM financial closure while any draft allocation remains, and lets a valid funding return complete while deferring automatic financial closure/PM completion notification when a draft allocation remains. The incentive processor independently locks/checks for a draft allocation before award or recognition-journal processing and preserves caller-owned transaction semantics.

Added rollback-only regression harness: `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. It is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, requires the protected active R5 project-completion policy, reuses only an existing closed zero-posted-balance fixture with a draft allocation and pre-existing recipient snapshot, inserts temporary FM closure audit evidence inside a transaction, and rolls back. It does not change funding allocations, payment evidence, posted accounting, payroll, or R5.

**Verification evidence:** PHP 8.2 lint passed for `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, `modules/hr/lib_employee_incentives.php`, and the rollback harness before the latest testability refinement. The rollback-only harness first refused live `ahl_el_kheir`, then passed against `ahl_el_kheir_incentive_test_20261015_r1`:

`PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS`

`PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5`

This closes the **processor rollback-harness gate**. A follow-up now extracts the explicit FM financial-closure rejection into `akp_assert_no_draft_funding_allocations_for_financial_closure()` and adds an assertion for that same guard to the rollback-only harness. **That latest helper/harness change still requires PHP lint and a fresh runtime harness pass.** It tests the shared guard used by the FM handler; it does not prove the browser route itself or the automatic closure deferral after a funding return at runtime. Do not post a funding return or change project 7's draft allocation/payment evidence. After pulling `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`, then run the harness with process-only `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Keep the already-passed incentive, payroll, notification, salary-advance, and Projects Phase 5 gates closed unless regression evidence appears.


### 2026-10-16 — FM automatic closure branch testability refinement (local verification pending)

`akp_project_financial_closure_outcome()` is now the shared decision used by the FM funding-return handler. The draft-funding rollback harness checks zero-balance-with-draft defer, zero-balance-without-draft completion eligibility, and positive-balance defer. After pulling latest `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`, then run only that harness against the named disposable DB using a process-only `AHL_DB_NAME` override. This remains rollback-harness evidence, not browser-route evidence. Employee notification delivery/visibility also remains unverified; pending outbox creation is not sufficient. Protect active R5 and project 7's financial evidence.


## 2026-10-16 — New-sponsor first-confirmed-payment incentive acceptance RUNTIME VERIFIED

The user executed the existing rollback-only harness from the local project against the isolated database. The temporary PHP runner set `AHL_DB_NAME` for this process only and checked both configured and connected database names before requiring the harness. The exact database guard output and harness result were:

```text
SAFE_TARGET | ahl_el_kheir_incentive_test_20261015_r1
PASS | new_sponsor=first_confirmed_payment | recipient_snapshot=PASS
PASS | later_payment_rejected=PASS | no_award_before_HR_verification=PASS
PASS | rollback_cleanup=PASS | sponsor=2858 | employee=16
TEST_EXIT_CODE=0
```

This closes the **new-sponsor first-confirmed-payment rollback-harness acceptance gate** at the tested local boundary: the first confirmed payment creates the expected event and recipient snapshot, a later payment is rejected as a second qualifying event, no award is issued before HR verification, and rollback cleanup passes. The configured and connected database were both confirmed as `ahl_el_kheir_incentive_test_20261015_r1`; the live `ahl_el_kheir` database was not the test target. Sponsor 2858 and employee 16 are fixture identifiers from this run, not permanent business rules.

Do not rerun this passed harness without concrete regression evidence. This result does not prove the separate real-browser PM-to-FM project-closure workflow or end-to-end employee notification delivery/visibility. Preserve active policy R5 and project 7's protected financial evidence. Continue from the remaining open gates only.


## 2026-10-17 — Browser notification acceptance pause: source correction, local verification pending

The opt-in pause in `tools/run_hr_incentive_notification_outbox_tests.php` previously used `fgets(STDIN)`; the user's run printed `BROWSER_CHECK` and proceeded straight to cleanup, so notification IDs 198 and 199 were removed before browser inspection. The precise reason STDIN returned immediately is not proven. Commit `baa3ce781d3ed34b6ed3b1d5877421538093e47f` replaces that pause with an explicit release-file gate under the system temporary directory. When `AHL_INCENTIVE_NOTIFICATION_PAUSE_BEFORE_CLEANUP=1`, the harness prints the release-file path and waits until a file is created there; normal runs without the opt-in flag are unchanged.

**Status: source committed; local PHP lint and runtime verification pending.** Pull `main`, lint the harness, then run it only against `ahl_el_kheir_incentive_test_20261015_r1` with the pause flag enabled. When `BROWSER_CHECK` appears, leave that PowerShell process running. The next acceptance step must temporarily point Apache/PHP at the disposable DB before opening the employee inbox at `http://localhost:8081/AhlElKheir/`; never expose test fixtures through the live DB. Create the exact printed release file only after the browser check, which allows cleanup to proceed. If the browser check cannot be completed, create the release file to allow test cleanup, and report the gate as still pending. The harness pause does not itself prove browser delivery or visibility.

## 2026-10-17 — Employee incentive notification: browser delivery and cleanup VERIFIED

The user completed the opt-in notification outbox test against disposable database `ahl_el_kheir_incentive_test_20261015_r1`, kept the harness paused, and opened the employee-facing inbox through the real local application at `http://localhost:8081/AhlElKheir/modules/notifications/index.php`. The browser displayed the expected notification:

- Title: `تمت جدولة حافزك في مسير الرواتب`
- Body: `تمت جدولة حافز بقيمة 12.34 SDG ضمن مسير الرواتب لشهر 2026-09. يخضع المسير لإجراءات الاعتماد والصرف المعتادة.`
- Status: unread/new; inbox showed 3 total notifications and 1 unread.
- Timestamp: `2026-10-17 06:54:52`; test notification ID: `200`.

After browser inspection, the release file was created for harness PID 4528 and the process exited. Cleanup was independently confirmed with read-only SQL: notification ID 200 count = 0; payroll ID 21 restored to `incentive_amount=0.00`, status `draft`. The original test-user password hash matched the saved original hash exactly. `.htaccess` was restored byte-for-byte to its backup (contents: `Options -Indexes`); `git status --short` was empty; test-only environment variables were removed. The harness's final console PASS lines were not retained, so report the browser gate and independent cleanup evidence as verified, but do not claim a captured final harness PASS transcript.

### Notification URL observation — CLI test-context artifact

The test notification stored `http://localhost/modules/notifications/index.php`, missing the local app's `/AhlElKheir/` prefix and port. Source inspection shows the dispatcher uses `APP_URL . 'modules/notifications/index.php'`; `APP_URL` is composed in `config/config.php` from request server variables and derives the base path from `DOCUMENT_ROOT` or a recognized `SCRIPT_NAME`. The test harness runs under CLI and only seeds `REQUEST_METHOD`, so web request host/document-root/script-name context is absent; the observed `http://localhost/` base is consistent with the CLI-derived `APP_URL`. This is a test-context issue based on the current evidence, not proof of a production-browser URL defect. Do not change production URL configuration without a browser-originated reproduction. If this harness assertion is strengthened later, explicitly seed a representative local web context before loading config and assert the generated link includes the application base path.

The employee-facing notification delivery/visibility gate is now CLOSED at this tested boundary. The separate real-browser PM-to-FM project-closure workflow remains an independent acceptance gate. Preserve active incentive policy R5 and all previously closed gates; do not rerun passed harnesses without regression evidence.


## 2026-10-17 — Project financial-closure draft-funding guard: latest rollback verification PASS

After syncing local `main`, the user PHP 8.2-linted:
- `modules/projects/project_funding_accounting.php`
- `modules/projects/view_fm.php`
- `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`

All three reported `No syntax errors detected`. The latest rollback-only run against `ahl_el_kheir_incentive_test_20261015_r1` returned:

```
PASS | fm_explicit_closure_guard=PASS | automatic_closure_outcomes=PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS
PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5
```

This closes the latest **source/helper and rollback-harness verification** for explicit FM closure blocking, automatic-closure outcome decisions, processor blocking while a draft allocation exists, unchanged awards/journals/funding evidence, and rollback cleanup. Project 7's financial evidence and active incentive policy R5 remain protected. This is not a real-browser PM-to-FM workflow result, and it does not prove the actual FM POST route or a live funding-return action. Do not post a funding return or modify project 7 to extend testing.

Next open gate: perform the separate real-browser PM → FM project-closure acceptance through the local application at `http://localhost:8081/AhlElKheir/`. Inspect the actual eligible test fixture and current workflow state before taking any action. Never point the browser at live data for disposable acceptance, never change project 7's financial evidence, and do not force the flow through by fabricating funding activity. Record actual UI outcomes and cleanup evidence; stop if a safe eligible test project is not available.


## 2026-10-17 — Project closure browser blocker and Supervisor feedback fixes (source committed; local verification pending)

The disposable-database browser acceptance flow reached project `PRJ-0006` (ID 6). The Supervisor's closure request is already recorded; the latest history status is `closure_requested`, approval is `approved`, the project was created by PM user 33, and the audit query found no `FM_CONFIRM_PAYMENT_EVIDENCE` row for project 6. The missing PM portfolio row was caused by `akp_can_view_project()`: PM visibility covered the final FM handoff or the PM's own pre-approval states, but not an approved project awaiting closure review.

Source fix on `main`: pending closure requests are visible to the PM only when approval is `approved`, lifecycle is `under_review`, and the latest project status event is `closure_requested`. Existing closure authorization and financial guards remain in place.

The missing confirmation prompt is addressed on `main`. Source inspection then showed that `view.php` already contained toast markup and a shared toast/scroll-restoration script before the attempted toast change; the newly introduced duplicate script was removed in commit `ba5ed5cec327e45d396ecbfb333a6c6d27ea5637`. Therefore, the reported missing toast/scroll jump is **not yet considered fixed**: test it after pulling and establish the exact runtime cause if it persists. The existing POST/redirect/GET flow and session toast message remain unchanged.

Commits:
- `4258ebb656a3229b84d5d7729868ce92a683f921` — PM pending-closure visibility.
- `f7f8bae9753694fd32b7f916443a1f2a8493a3c6` — Supervisor confirmation, toast, and viewport handling.

**Verification boundary:** these are source-level fixes only. Local PHP lint and real-browser verification have not yet been performed. Pull `main`, lint the two changed PHP files, then resume the existing disposable DB test at project 6 without resubmitting the already-recorded request. Verify PM can see/open the request, the confirmation prompt can cancel safely, a confirmed request displays a toast without jumping to the top, and the PM closure controls still enforce pending-expense/authorization guards. Do not perform administrative or FM financial closure until the normal browser workflow and existing safeguards allow it. Never use live DB `ahl_el_kheir` for this test.


## 2026-10-17 — PM administrative closure confirmation and feedback fix (runtime verification pending)

The disposable-database browser acceptance for `PRJ-0006` established that PM administrative closure succeeded: project status `completed`, lifecycle `closed`, `CLOSE` audit row `2479`, and project-completion event `28` was created under policy version 11 with status `pending_verification` and the recipient-snapshot marker. No `FM_FINANCIAL_CLOSURE` audit or incentive award was present in the read-only query. Active policy version 1 / R5 remains enabled at 1,500.00 SDG; do not change it.

The user reported that PM closure redirected to the dashboard without a confirmation prompt or visible success toast. Source review found the PM `close_project` form had no confirmation handler; after success the handler redirected back to the now-closed project, where PM access checks could redirect away before the toast was rendered.

Source changes committed on `main`:
- `986cef5d21d0ae972a1ec6dd9f624a619a851b40` — add confirmation to the PM administrative-closure form and redirect successful closure to the PM dashboard while preserving the session toast.
- `b5879cb2cc69be7f3a30301eb72b4570efb12497` — render and auto-show the one-time closure success toast on the Projects dashboard.

**Verification status: PENDING local PHP lint and real-browser verification.** After pulling `main`, lint `modules/projects/view.php` and `dashboard/projects_dashboard.php`. Do not repeat closure on project 6; it is already administratively closed. Continue the existing acceptance at FM financial closure through the normal UI only after inspecting the current state and ensuring no draft funding allocation or other guard blocks it. Keep all actions on `ahl_el_kheir_incentive_test_20261015_r1`; never use live `ahl_el_kheir`.

## 2026-10-17 — Project-team assignment workflow added; local acceptance pending

A repository-wide local `git grep -n -i "project_team" -- "*.php"` showed that production PHP only read active team membership for authorization, display, and the project-completion incentive snapshot; the only explicit INSERTs were in rollback test harnesses. The current schema in `database/ahl_el_kheir.sql` already defines `project_team` with section codes `general`, `finance`, `operations`, `documents`, and `closure`, so no migration or runtime DDL is needed.

On `main`, `modules/projects/view.php` now exposes team membership using the existing `akp_can_edit_section('team', $id)` authorization boundary:
- active users can be assigned to a schema-valid section;
- duplicate active user/section assignments are rejected;
- unassignment sets `unassigned_at` instead of deleting history;
- assignment/unassignment audit rows are written in the same transaction;
- both operations reject closed projects and lock the lifecycle before the project row to align with the closure path;
- the page explains that primary-supervisor assignment is separate from `project_team`.

Source commits: `907d8be0c6f945bd030db45c8902d19427320c2b`, `5f0519f1e4418a367003849c76e1dcafa256fc64`.

**Verification status: SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER ACCEPTANCE PENDING.** Do not claim the workflow is verified. Next: pull `main`, run `php -l modules\projects\view.php`, then test add/duplicate-rejection/unassign/history through the normal browser UI on a newly created test project in `ahl_el_kheir_incentive_test_20261015_r1`. Do not modify PRJ-0007, PRJ-0018, active policy R5, or any funding/expense/payment/journal evidence. Keep the real PM-to-FM financial-closure incentive acceptance open until the browser path is genuinely verified.

## 2026-10-17 — Initial project team selection during creation (source committed; local verification pending)

Source commit: `f23f6252f37e287549e5dbc461d963021d9eef12` on `main`, file `modules/projects/form.php`.

The new-project form now offers optional repeatable initial team rows (active user, existing schema-valid section, optional notes). The primary supervisor remains a separate required assignment and is never implicitly added to `project_team`; only users explicitly selected in the new section become team members. Server-side validation checks active users, allowed section codes, duplicate users, and the existing 500-character notes limit. Team rows and matching `ASSIGN_TEAM_MEMBER` audit entries are inserted inside the existing project-creation transaction, so a failure rolls back project creation and team assignments together. No migration or schema change was needed; the existing `project_team` schema already defines the five allowed section codes.

**Verification status: SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER ACCEPTANCE PENDING.** After pulling `main`, run `php -l modules\\projects\\form.php`. On the disposable database `ahl_el_kheir_incentive_test_20261015_r1`, test creating a project with no initial team, then a separate project with two explicitly selected team members in valid sections. Verify the primary supervisor is not auto-assigned, selected rows and audit history are saved, and an invalid/duplicate submission is rejected without leaving a partial project. Do not modify PRJ-0019 or any protected project/funding/accounting evidence. Keep the real-browser PM-to-FM incentive closure acceptance paused until this change and the remaining normal workflow are verified. Preserve the local `.htaccess` database-routing setting until test cleanup is explicitly verified.


## 2026-10-17 — Move project-team editing into the project edit page (source committed; local acceptance pending)

The user tested creation of `PRJ-0020` (“اختبار حوافز المشاريع”) and confirmed that the two explicitly selected team members were saved (2 active assignments), but the detail page still displayed the add-member form and the success toast was not visible.

Source changes committed directly to `main`:
- `666e3d41ccfec087c17aba28e86818d20eabb3c9` — `modules/projects/form.php`: existing-project edit page now displays current active team members and, when the existing `akp_can_edit_section('team', $id)` permission allows it and the project is not closed, supports add/unassign actions. Each change is handled as a separate CSRF-protected POST, locks lifecycle then project, preserves the prior transaction/audit approach, and keeps unassignment history.
- `89414e7efe5ea4458a559ca30f3bbdd579214c13` — restricts the team editor card to existing projects, so it does not appear alongside initial-team selection on the create form.
- `ebd543b40b445af7858660f1291318805d7d8cae` — `modules/projects/view.php`: removed add/unassign controls from the project detail page; the active roster remains visible and eligible project managers get a link to manage the team from the edit page.
- `a92be7e0efd6e7d4dcfe0f83c99e001e18ffb3fe` — `modules/projects/view.php`: added a fallback path for project success feedback.
- `1d9f9809c530a025c1231a9d8198941263022f16` — prefers a SweetAlert toast for project success feedback, with Bootstrap and visible-markup fallbacks if needed.
- `a2ee0211b9b7443427465f56371be3870350f30b` — `modules/projects/form.php` now uses the same SweetAlert-first success-toast behavior for team changes made on the edit page.

**Verification boundary: source committed, not yet locally linted or browser-verified.** Pull `main`, preserve the local `.htaccess` override routing Apache to `ahl_el_kheir_incentive_test_20261015_r1`, then lint `modules\\projects\\form.php` and `modules\\projects\\view.php`. Browser-check `PRJ-0020` without changing its existing membership: the detail page must not show add/unassign forms; its edit page must list both active members and expose add/unassign controls only when authorized; verify add, duplicate rejection, unassign/history retention, and a visible success toast. Do not edit unrelated project/funding/accounting evidence. If the toast still does not appear, inspect browser console/network/runtime evidence rather than guessing. Keep the PM-to-FM financial-closure incentive acceptance open until separately verified.


### 2026-10-17 — Project edit-page team editor placement

The existing-project team editor was moved from above the project form into the form flow immediately after **البيانات الأساسية** and before **نطاق المشروع وأهدافه**, matching the initial-team section's placement during project creation. Team actions continue to use the existing early POST action handler; the client-side budget check is bypassed only for team actions, and team-member selection is validated only when adding a member. Source committed on `main`; local PHP lint and browser acceptance for this placement/action wiring are pending. Preserve the local `.htaccess` disposable-database override during pull/testing. Do not alter existing team assignments as part of this UI change.


### 2026-10-17 — Project detail team card placement

The read-only **فريق المشروع** card on `modules/projects/view.php` was moved to immediately after **ملخص المشروع** and before **التنفيذ والشركاء والتوريد**. Removed the duplicate team-specific edit link from that card; the existing primary **تعديل المشروع** action in the page banner remains the single edit entry point. No team assignments or lifecycle behavior were changed. Source committed on `main` as `2780a8dd14a54d91c1606b0d63d91d917a412f65`. Local PHP lint and browser verification are pending; do not describe this UI adjustment as runtime-verified until tested locally.


## 2026-10-17 — Employee incentive notification at earned stage (source committed; local verification pending)

Source commits on `main`:
- `62a98884d49938ccbcbe5fbba0f54f592802bcf3` — queue the employee notice atomically when a verified award is financially recognized; dispatch after commit without requiring a payroll row; stop creating the first notice during payroll-draft scheduling; use wording that confirms the reward is earned but not yet paid.
- `21dcef9eb784f6c636f486a59e3ae710b9a21f07` — update the outbox migration's explanatory comments to describe earned-stage notices. No schema/DDL change was made.
- `191410b5fa816b62320c527767c0bacb6d365b5a` — clarify earned-notice error/failure handling.

Root cause: the dispatcher joined `hr_incentive_awards` to `payroll`, while the unique outbox row was created only when the award was scheduled into a draft. Therefore an earned award without payroll linkage could not notify its employee. The dispatcher now reads the award directly; the existing unique `award_id` keeps delivery idempotent, and notification insertion remains atomic with outbox state after the award transaction commits. Delivery failure remains isolated and retryable.

The notice now says the employee has earned the reward, that it will be included in an eligible payroll and paid through the normal approved payroll process, and that the notice does not mean payment has already occurred. It is not a payment confirmation.

Awards 14 and 15 already have sent outbox rows and existing notifications 212/213. They were not rewritten, resent, or backfilled; the October 2026 payroll drafts remain untouched and unpaid. Active policy R5 remains unchanged. Arabic appearing as `???` in prior SQL-console output is not proof of stored-text corruption and remains a separate encoding check.

**Verification status: source committed; local PHP lint and fresh-award runtime/browser acceptance are pending.** After pulling `main`, lint `modules\\hr\\lib_employee_incentives.php`. Verify the next safe, new project-completion award through the normal workflow on `ahl_el_kheir_incentive_test_20261015_r1`: the outbox/employee notice must be created after recognition commits even before payroll linkage, retries must not duplicate it, and the employee inbox must show the earned-not-paid wording. Do not repeat closure for `PRJ-0020`, rewrite existing notifications, change R5, or approve/pay October payrolls. Preserve the local `.htaccess` disposable-database override.


## 2026-10-18 — Immediate qualifying-activity notifications (implementation committed; runtime acceptance pending)

The requirement is that an employee receives an in-app notice as soon as the qualifying activity is recorded, rather than waiting for a later FM/higher-management workflow or a background payroll process. The notice is explicitly provisional and must not falsely say the reward is finally earned or paid before the existing verification/financial gates pass.

Implementation committed on `main`:
- `77da80e00daaf096c516198ab0935a8bc0515dfd`: initial immediate activity-notice helper and rollback-test extension.
- `895b4ba2af347b25f1b289772b3e4d534a122140`: make pre-approval activity notice separate from post-verification award recognition.
- `6ccd172a70251deca4c55ee55b743035d56ca637`: notify the collector immediately when a monthly sponsorship collection is submitted, before FM approval.
- `a3b6130b5808b49775e266d52ed2826b1215cd2c`: notify an eligible recorded sponsor bringer immediately when an active new sponsor is created, before first-payment confirmation.
- `04418b2f5da481993d0c1768b6981645ff24a2ac`: notify the recorded reactivator immediately when reactivation is durably recorded, before first-payment confirmation.
- `50c433984b97bedee37c3bf22fa3b47fca5c58c5; `c0316a4d47b708dedd37e74da8ac2d884b840dff` (clarify notices are provisional activity updates)`: isolate notice-insert failure from the underlying business workflow and log the error.

Project-completion activity notice is created transactionally with the PM closure-time eligible-team snapshot; its wording explicitly says final FM financial closure and verification remain outstanding. The later earned notice remains a separate message emitted when the award is recognized. No policy, R5, payroll, award status, or accounting gate was weakened.

The rollback-only project harness now asserts that the PM snapshot creates exactly one immediate activity notice, that its wording does not claim final approval/payment, and that rollback removes the temporary notification. This modified harness has **not yet been run locally**. PHP lint, disposable-DB harness execution, and browser inbox verification are pending. Do not mark this milestone runtime-verified until those checks pass. Use only existing disposable DB `ahl_el_kheir_incentive_test_20261015_r1`; never test on live financial data or create another DB.


## 2026-10-18 — One-shot PRJ-0024 closure reliability guard

A final source review found that the shared immediate-notification helper intentionally swallowed notification INSERT failures. In the project closure transaction, that could allow the recipient-snapshot marker and administrative closure to commit even if an employee notice failed; a retry would then skip the snapshot loop. A missing effective project-completion policy could also return event ID 0 without blocking closure.

Commit `66aa8a820c67d632f53cfa48319157ef21ae7a48` narrows the behavior for project completion: project-completion notification INSERT failures now propagate to the enclosing PM closure transaction, and a missing active/effective project-completion policy throws before closure can commit. The other activity-notification paths retain their existing failure-isolation behavior. This makes the project closure and both recipient notifications atomic: either the closure and notices commit together, or the closure transaction rolls back.

**Verification is pending after this source change. Do not click Close on PRJ-0024 yet.** On the local disposable database, pull `main`, lint the changed PHP file and project page, rerun the existing rollback-only project incentive harness, then perform a read-only preflight confirming PRJ-0024's active team members map one-to-one to active employees in working employment states and that the effective active policy enables project_completion. Only then do the one-time browser closure attempt. Do not create another project, repeat the closure, alter R5, or touch payroll/accounting states. The active team snapshot is based on `project_team`; the primary supervisor is not implicitly included.


## 2026-10-18 — Project-completion notification timing correction

The user clarified that employee project-completion notifications must be sent **after FM final financial closure**, not at PM administrative closure. Source inspection found `hrIncentivePrepareProjectCompletion()` both snapshotted eligible employees and inserted a provisional `notifications` row for each one. Commit `28578e3c9088fe8efdfccc671e1e2e7e7b99721f` removes only that early notification call; the PM transaction still records the idempotent source event and closure-time allocation snapshot. The existing `hrIncentiveProcessProjectCompletion()` path remains gated by the durable `FM_FINANCIAL_CLOSURE` audit record and zero controlled balance, recognizes each eligible award, queues the earned notice in `hr_incentive_notification_outbox`, and dispatches after commit.

Commit `2a1d7b05e899582e40a6a0e864159914cc09b8e7` updates `tools/run_hr_project_incentive_payroll_rollback_tests.php` to assert (1) no employee incentive notification is inserted at PM closure, (2) no award is issued without the FM gate, and (3) after the gate permits award recognition, one earned-notice outbox row is pending per eligible recipient while the outer rollback-only test transaction is still open. These are source-level changes only; local PHP lint and execution against `ahl_el_kheir_incentive_test_20261015_r1` remain required. Do not run the FM closure on PRJ-0024 until the updated harness passes and source is synced locally. PRJ-0024 is already administratively closed; do not re-close or reopen it for this test. Keep active policy R5 unchanged and never use `ahl_el_kheir_production_test`.


## 2026-10-18 — Rollback verification passed

The user synced `main` locally and ran PHP lint on `modules/hr/lib_employee_incentives.php` and `tools/run_hr_project_incentive_payroll_rollback_tests.php`; both reported no syntax errors. The updated rollback-only harness passed on `ahl_el_kheir_incentive_test_20261015_r1`:

```
PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS
PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS
PASS | project_award_draft_payroll_link=PASS | incentive_total=PASS | net_salary_unchanged=PASS | outbox_pending=PASS | project_notice_waits_for_FM=PASS
PASS | rollback_cleanup=PASS | temporary_payroll_rolled_back=NOT_NEEDED | policy=R5 | reward=1500.00 SDG
```

This proves the rollback-only scenario's PM/FM notification boundary and cleanup, not the real browser-triggered FM closure or successful delivery to PRJ-0024 recipients. PRJ-0024 remains administratively closed and FM final closure is still pending. Before the FM action, verify the displayed project and financial summary are still PRJ-0024, remaining controlled balance is zero, and no draft funding allocations remain. Do not re-close/reopen PM state or alter policy R5. After the FM action, inspect the project-completion awards/outbox and employee notifications for exactly one earned notice per eligible snapshotted recipient; verify retry/reload does not duplicate notifications. If any discrepancy appears, stop and report the evidence without manually inserting notices or altering accounting data.


## 2026-10-18 — Project-completion supervisor-only recipient regression

The disposable database `ahl_el_kheir_incentive_test_20261015_r1` supplied concrete runtime evidence for `PRJ-0024`: project 24 was administratively closed at `2026-10-18 09:47:23`; active supervisor assignment 18 assigned PS1 (user 34) at `09:31:01`; PS1 was active and linked to working employee 22, but had no active `project_team` row. Verified incentive event 34 carried `system:project-recipient-snapshot-v1` but allocations/awards/outbox existed only for users 16 and 28. Their notices were delivered, proving the omission occurred before award/outbox creation rather than during delivery.

Root cause: `hrIncentivePrepareProjectCompletion()` selected recipients exclusively from `project_team`. The application stores the primary Project Supervisor separately in `project_supervisor_assignments`. The project creation form already requires a primary supervisor both in HTML and server-side validation; no duplicate creation validation change was made. The test project had a supervisor assignment before closure, so the observed omission is not evidence that creation without a supervisor was allowed.

Correction committed to `main`:
- `dd5c072c34dcd40dd8232e8f5df3aeac9b748e30` — include the latest active primary supervisor assignment in the recipient set and UNION it with active project-team members, deduplicating a person who appears in both.
- `f2ff7bb7bd4b56011a45c03fc591a8aefbd43c82` — change the rollback-only fixture to assign its eligible supervisor through `project_supervisor_assignments` only and assert the fixture has no active `project_team` row for that supervisor.

Verification: the integrated local `main` code passed PHP lint for both `modules/hr/lib_employee_incentives.php` and `tools/run_hr_project_incentive_payroll_rollback_tests.php`. The user ran the guarded rollback-only harness with process-scoped `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`; exit code `0`. Exact output: `PS_snapshot=PASS`, `full_policy_amount_per_employee=PASS`, `missing_FM_gate_blocks_award=PASS`, `FM_gate=PASS`, `recognition_journal_5500_2400=PASS`, `project_award_draft_payroll_link=PASS`, `incentive_total=PASS`, `net_salary_unchanged=PASS`, `outbox_pending=PASS`, `project_notice_waits_for_FM=PASS`, `rollback_cleanup=PASS`; policy R5, reward `1500.00 SDG`. The fixture assigns the supervisor only through `project_supervisor_assignments`, not `project_team`. This proves the synthetic rollback scenario on the disposable database; it does not prove historical repair or actual notification delivery for PRJ-0024. Do not rerun closure on PRJ-0024, mutate its records, or manually insert allocations/awards/notifications. Review historical recovery separately through an auditable application path.

## 2026-10-18 — Guarded historical PRJ-0024 supervisor incentive recovery tool

The event/policy diagnostic confirmed event 34 is verified, tied to active policy R5 (policy version ID 11), and its enabled project-completion reward is 1,500.00 SDG. Previously reviewed local evidence establishes that PS1 (user 34 / employee 22) had active primary-supervisor assignment 18 at project closure but was omitted from the original recipient snapshot; the two existing recipients already have scheduled/accounted awards and sent notices.

A one-purpose recovery tool was committed directly to `main`:
- `tools/recover_hr_project_incentive_prj0024_supervisor.php`
- Source commit: `e5202629840daca5ed441d8dc3b899e86ecb63b2`

The tool is strictly gated to disposable database `ahl_el_kheir_incentive_test_20261015_r1`, project 24, event 34, assignment 18, user 34 / employee 22, active R5 policy and the exact 1,500.00 SDG reward. It checks the durable FM financial-closure audit, zero controlled balance, no draft funding allocations, recipient eligibility, original two-recipient state, existing award/journal/notice state, and an empty pending incentive-notification outbox before changing anything. It refuses duplicates or any unexpected state. Within one transaction it adds only PS1's missing allocation with explicit historical-recovery notes and a system audit row that does not fabricate a human approver, then invokes the existing gated project-completion processor for recognition/accounting/payroll/outbox behavior. It commits only after validating the PS1 award, balanced 5500/2400 journal, and pending outbox row; it then uses the existing post-commit dispatcher. No DDL or policy changes are performed.

**Runtime status: VERIFIED locally on 2026-10-18.** The one-time lint and recovery execution completed successfully against only `ahl_el_kheir_incentive_test_20261015_r1`; do not rerun absent concrete regression evidence.

```text
PREFLIGHT PASS | DB=ahl_el_kheir_incentive_test_20261015_r1 | project=24 | event=34 | PS1=user34/employee22 | reward=1500.00 SDG | balance=0.000
PASS | historical_allocation=15 | award=24 | reward=1500.00 SDG | status=scheduled | recognition_journal=178 | outbox=sent | notification=232 | audit=recorded
RECOVERY_EXIT_CODE=0
```

The one-time lint and recovery run are complete, with the exact successful result recorded above. This is a verified historical repair in the named disposable database only; production was not targeted. Do not rerun the recovery tool without concrete regression evidence. Preserve the separate remaining acceptance gates below; this recovery does not complete the real-browser PM-to-FM closure or project-team assignment UI testing.


## 2026-10-18 — PRJ-0024 supervisor recovery: runtime verified

Local PHP lint passed for `tools/recover_hr_project_incentive_prj0024_supervisor.php`. The tool was then executed exactly once with `--apply` against the disposable database `ahl_el_kheir_incentive_test_20261015_r1`. The preflight confirmed project 24, closure event 34, PS1 user 34 / employee 22, reward 1,500.00 SDG, and zero controlled balance. The committed result created historical allocation 15 and award 24, recognized the reward through journal 178, linked it to draft payroll processing (`status=scheduled`), delivered notification 232, and recorded the audit row. Exit code was 0. The production database was not targeted. Do not rerun this recovery tool without concrete evidence of regression; it is intentionally a one-time, tightly gated historical repair.

The separate-supervisor regression is now covered by the updated project-completion recipient snapshot and rollback-only harness fixture. The earlier harness result remains synthetic rollback-only evidence; it is not evidence of true two-process concurrency or a same-employee/multiple-advance scenario. Other remaining acceptance gaps are the full real-browser PM-to-FM project-closure workflow and the project-team assignment UI workflow (initial selection, add/duplicate rejection, unassign/history retention, and edit-page behavior). New-sponsor first-confirmed-payment acceptance was already runtime-verified on 2026-10-16 and must not be repeated without regression evidence.


## 2026-10-18 — Immediate activity-notice helper acceptance harness added (runtime pending)

Source inspection confirms the immediate provisional-notice helper is called from monthly sponsorship collection submission (`modules/transactions/create.php`), active sponsor creation (`modules/sponsors/create.php`), and win-back reactivation (`modules/administration/winback.php`). Commits `69ac7eb1b7da82f7495ea12db71c59b2725c4540` and `bb292f02acfcce504b84e69c1f8179b13332fc63` add `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`. In one outer transaction, it creates a temporary active policy with the three source-event rules, calls the shared notification helper for each category, verifies that each call inserts a new unread notification for the intended employee with the expected activity/reward and explicit provisional wording, then rolls back and checks cleanup. **This is source/test-harness work only so far: local PHP lint and disposable-DB execution are pending.** It does not simulate the real business POST routes or browser inbox. Next: sync `main`, lint the helper and new harness, then run the harness only with process-scoped `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Keep R5 unchanged. Do not repeat the already verified new-sponsor first-payment test without regression evidence.

## 2026-10-18 — Immediate activity-notice helper acceptance passed

**Runtime evidence:** the user synced `main`; PHP lint passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`. With process-scoped `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`, the rollback-only test returned:

```text
PASS | immediate_notice=collection,new_sponsor,reactivation | correct_recipient=PASS
PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS
```

The test created and checked new unread provisional notices for collection, new sponsor, and reactivation, confirmed reward context and explicit not-finally-approved/not-paid wording, then verified rollback cleanup. **Closed:** helper-level acceptance for these three notice categories. **Still open:** proof of the real business POST call sites and browser inbox rendering; the helper test is not route/browser simulation. Preserve R5 and use only `ahl_el_kheir_incentive_test_20261015_r1` for synthetic tests. Do not repeat already-closed new-sponsor first-payment or other suites without regression evidence.


## Deferred development point — collection incentive eligibility (recorded 2026-10-18)

The user raised a business-design concern after the real browser collection submission displayed a provisional “activity linked to an incentive” notice: routine monthly sponsorship collection is ordinary job responsibility and may not merit an additional incentive, especially where the same employee may also qualify for the separate new-sponsor acquisition incentive. Revisit this with the user's forthcoming idea before changing behavior. Candidate recommendation to evaluate later: disable or redesign the collection incentive category and its provisional activity notice, while retaining new-sponsor, reactivation, and project-completion incentives unless the user decides otherwise.

This recommendation was deferred at the time and is superseded by the explicit implementation request recorded in the 2026-10-18 section below. Historical evidence and active policy R5 remain protected.


## 2026-10-18 — Browser evidence and next read-only incentive-state check

The user has now completed real-browser checks of two immediate activity-notice routes on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
- Active new-sponsor creation for user 19 created sponsor `SP-002859` (“اختبار الحوفز”); the inbox showed the provisional activity notice with expected reward 2,000 SDG and explicit pending-verification/not-paid wording (observed 19:16:45).
- Monthly sponsorship collection submission reported two payments submitted to FM for approval/posting; the inbox showed the provisional collection activity notice for expected reward 1,000 SDG (observed 19:29:40). Do not assume this submission inserted only one payment.

These browser results prove the activity notices rendered; they do **not** prove an award was issued. The user's concern that the same employee may already have received a new-sponsor incentive must be resolved from durable records, not notification wording. Next step: run a read-only query against the same disposable database for incentive events and joined awards for recipient user 19, especially `new_sponsor` and `collection`; report event status and award status/payroll/journal links before deciding whether any earned incentive exists. Do not mutate, reverse, delete, or rerun the already completed browser submissions. New-sponsor first-confirmed-payment acceptance from 2026-10-16 remains closed and must not be repeated.

The separate development point about whether routine collection should qualify for an incentive is deferred and recorded above; no category, active R5 policy, event, award, notification, payroll, or accounting behavior has been changed for that recommendation.



## 2026-10-19 — Routine collection incentive removal: RUNTIME VERIFIED / CLOSED

The user completed seven PHP syntax checks successfully for the four changed PHP source files and three updated rollback harnesses:
- `modules/transactions/create.php`
- `modules/accounting/lib_sponsor_payment_approval.php`
- `modules/hr/lib_employee_incentives.php`
- `modules/hr/incentive_verification.php`
- `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`
- `tools/run_hr_employee_incentive_source_event_rollback_tests.php`
- `tools/run_hr_incentive_verification_rollback_tests.php`

All three harnesses passed against disposable database `ahl_el_kheir_incentive_test_20261015_r1`:

```text
===== tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php =====
PASS | immediate_notice=new_sponsor,reactivation | collection_notice=blocked | correct_recipient=PASS
PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS

===== tools/run_hr_employee_incentive_source_event_rollback_tests.php =====
PASS | collection_source_event=blocked | no_new_collection_award_path=PASS
PASS | rollback_cleanup=PASS | policy=18

===== tools/run_hr_incentive_verification_rollback_tests.php =====
PASS | hr_manager_fixture=available | recipient_eligibility=PASS | pending_to_verified=PASS
PASS | award_creation=PASS | recognition=Dr5500/Cr2400 2000 SDG | duplicate_recognition=PASS
PASS | rollback_cleanup=PASS | policy=11 | employee=1
```

The collection-incentive removal rollback verification is **CLOSED**. Routine collection no longer creates provisional incentive notices or new collection source events. The HR verification endpoint also blocks issuance of new awards from pending legacy collection events. New-sponsor, reactivation, and project-completion incentives remain enabled.

Important preservation boundary: this change blocks new routine collection incentive activity; it does not delete, reject, reverse, or rewrite historical collection events, awards, notifications, payroll, accounting records, the catalogue entry, or active policy R5. No production database or policy change was made. No additional application tests or browser submissions are warranted without concrete regression evidence.



## 2026-10-19 — Incentive module status: core paths verified, full acceptance still open

The current evidence supports **partial runtime verification, not a claim that the entire module is finished end-to-end**.

### Closed gates
- **Routine collection incentive removal — CLOSED.** Seven PHP lint checks and three rollback-only harnesses passed on disposable test state. New routine collection submissions no longer create provisional incentive notices or new collection source events; pending legacy collection events cannot be verified into new awards. Historical records, awards, notices, payroll/accounting evidence, catalogue entry and active R5 were not deleted or rewritten.
- **New-sponsor first-confirmed-payment — CLOSED** at the browser acceptance boundary recorded for 2026-10-16. Do not repeat without regression evidence.
- **Immediate provisional-notice helper — CLOSED** for new-sponsor and reactivation; rollback-only evidence confirms correct recipient, provisional wording, reward context, unread status and cleanup. Collection is intentionally blocked and is not part of the retained incentive paths.
- **Project-completion award mechanics — CLOSED at rollback-only boundary.** Evidence covers FM financial-closure gating, full per-eligible-employee policy amount, Dr 5500 / Cr 2400 recognition, draft-payroll linkage, earned-notice outbox and rollback. The fixture includes a primary supervisor assigned through project_supervisor_assignments without a project_team row.
- **PRJ-0024 supervisor omission recovery — DONE ONCE.** On the disposable database only, preflight and recovery passed: project 24/event 34, PS1 user 34/employee 22, reward 1,500 SDG; allocation 15, award 24, journal 178, scheduled award, outbox sent, notification 232, audit recorded. Production was not targeted. Do not rerun recovery or repeat closure.

### Remaining end-to-end acceptance gates
1. Real-browser PM-to-FM financial closure path: verify earned notices reach all snapshotted recipients after FM closure, including separately assigned supervisor PS1, and reload/retry does not duplicate delivery. Synthetic rollback evidence and the one-time recovery are not substitutes.
2. Real POST-to-browser-inbox acceptance for the retained new-sponsor and reactivation provisional notices. The earlier helper-level harness does not simulate these POST routes; routine collection must remain blocked.
3. Project-team assignment UI: initial selection, adding members, duplicate rejection, unassign/history retention, and edit-page behavior.

Until those gates have concrete evidence, describe the module as implemented with several verified paths but **not fully acceptance-closed**. Current evidence comes from disposable test state, not production/live database acceptance. The two disposable incentive databases were deleted after schema metadata comparison; do not recreate them. Do not alter active policy R5, historical events/awards, payroll/accounting records, or repeat closed tests without an explicit new requirement or concrete regression evidence.


## 2026-10-19 — Remaining incentive gates: source call-site audit

A read-only review of the current `main` source confirmed the relevant integration call sites exist:

- `modules/sponsors/create.php`: after a successful active-sponsor insert, calls `hrIncentiveNotifyPendingActivity(..., 'new_sponsor', ...)`.
- `modules/administration/winback.php`: after `wb_mark_returned()` returns a successful reactivation transition, calls `hrIncentiveNotifyPendingActivity(..., 'reactivation', ...)` for the selected reactivator.
- `modules/accounting/lib_sponsor_payment_approval.php`: within the caller-owned financial transaction, records the new-sponsor and reactivation first-confirmed-payment events for monthly sponsorship payments.
- `modules/projects/form.php` and `modules/projects/view.php`: contain initial-team validation/persistence and authorized add/unassign handlers; duplicate active assignments are rejected and unassignment retains the assignment row with `unassigned_at`.
- `modules/projects/view_fm.php`: invokes project-completion processing after durable FM financial-closure evidence and has a retry-safe processing path for an already-closed project.

This is **source inspection only**. It does not establish successful browser POSTs, visible inbox rendering, actual project-team UI behavior, or duplicate-free delivery after reload/retry. No code change is justified by this read-only audit alone.

The two previously used disposable incentive databases have been deleted and must not be recreated. The live `ahl_el_kheir` database must not be used for mutating acceptance tests. Therefore, the remaining end-to-end gates cannot be honestly closed from source review; they require a separately authorized safe local test environment and real-browser evidence. Preserve R5, historical incentive/accounting/payroll data, and the already-closed tests.

## 2026-10-19 — Immediate activity-notice rollback harness safety gate

The rollback-only harness `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php` now checks the actual connected database and prints the engine for each of its six read/write tables before performing any fixture writes: `notifications`, `hr_incentive_policy_versions`, `hr_incentive_policy_rules`, `hr_incentive_types`, `employees`, and `users`. It exits with code 2 before writing if any table is missing or is not InnoDB. This is a safety preflight, not runtime acceptance evidence; the harness has not been run against the live database as part of this documentation change. Run it only with explicit authorization for the connected database and inspect the complete preflight and PASS/FAIL output. Existing R5 policy and real business records must remain untouched.

## 2026-10-19 — Immediate activity-notice rollback harness executed

The user ran `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php` after fast-forwarding local `main` to `a7f1be28`. Reported output confirmed all six preflight tables were InnoDB and `all_six_tables_transactional=PASS`. Runtime assertions passed: new-sponsor/reactivation provisional notices, collection notice blocked, correct recipient, provisional wording, reward context, unread status, and rollback cleanup. This closes the shared-helper rollback acceptance check with the new engine-safety preflight. It does not prove real POST-to-inbox behavior or project PM-to-FM earned-notice delivery. Do not rerun this harness without concrete regression evidence. Remaining end-to-end gates are unchanged.

## 2026-10-19 — Project-team source syntax verification

The user ran PHP 8.2 syntax checks locally after syncing `main`; both commands passed:

- `php -l modules\\projects\\form.php` — `No syntax errors detected in modules\\projects\\form.php`.
- `php -l modules\\projects\\view.php` — `No syntax errors detected in modules\\projects\\view.php`.

This closes only the local PHP syntax-check item. Browser acceptance for initial team selection, edit-page display and authorization, add/duplicate rejection, unassignment/history retention, and success feedback remains pending. The user confirmed the two disposable incentive databases are deleted; do not recreate them and do not perform mutating acceptance tests against live `ahl_el_kheir`. No database rows or project assignments were changed as part of this syntax check.


## 2026-10-28 — Salary portal navigation permission alignment

Related PHP backlog item PHP-04 was corrected in commit `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9`: `modules/hr/salary_portal.php` now shows the incentive-policy navigation link only to `financial_manager`, matching the existing `modules/hr/incentive_policy.php` authorization. This does not change incentive-policy permissions or policy/accounting behavior. Real-browser visibility acceptance remains pending. The separate end-to-end gates listed above (PM-to-FM closure earned notices, POST-to-inbox provisional notices, and project-team assignment UI) remain open until evidence closes them. Do not recreate the deleted disposable incentive databases or mutate live `ahl_el_kheir` to test them.
