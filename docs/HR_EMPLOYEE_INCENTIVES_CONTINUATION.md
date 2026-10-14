# Employee Incentives — Continuation and Source Audit

**Checkpoint:** 2026-10-13  
**Status:** Incentive ledger, category catalogue/custom policy fields, and the GM policy approval workflow are committed on `main`. The GM approval migration has been applied locally and the schema inspected; PHP syntax checks passed and the policy page loads. The complete approval/rejection/activation workflow remains unverified at runtime because the local database has no policy versions, rules, or custom fields. Award issuance, source-event integration, payroll scheduling, and incentive accounting remain separate unfinished gates.

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

## Next engineering gate

The next gate is local verification of the committed GM policy approval workflow, followed by the separate source-event/award and payroll/accounting integrations. Before further schema work, verify actual local table definitions and constraints; do not restore the data-bearing dump or add duplicate attribution columns. The policy approval source/migration exists on main but is not locally runtime-verified.

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
