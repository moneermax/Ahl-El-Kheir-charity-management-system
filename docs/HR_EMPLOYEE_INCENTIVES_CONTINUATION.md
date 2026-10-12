# Employee Incentives — Continuation and Source Audit

**Checkpoint:** 2026-10-12  
**Status:** Business rules captured; source audit in progress; no feature code or migration has been implemented or runtime-verified.

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

Repository source and the latest committed backup have been inspected through GitHub. The Windows checkout and local XAMPP database are not accessible from this execution environment. No production code, schema, or live database has been changed, and no local runtime verification is claimed. Do not mark this feature complete until local PHP syntax checks and database-backed acceptance tests actually pass.


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

Continue the exact schema and transaction-boundary audit for the incentive ledger and payroll integration. Before authoring a migration, verify existing table definitions and constraints from the canonical SQL dump and current local database. Do not restore the data-bearing dump into the live database. Do not add a duplicate attribution column. No incentive policy, reward ledger, or payroll/accounting feature code has been implemented yet.

## 2026-10-12 — Incentive ledger schema committed

Two additive migrations are now present on `main`:

- `database/migrations/2026-10-12_hr_employee_incentives_expense_account.sql` establishes account 5500 (`مصروف حوافز الموظفين` / `Employee Incentives Expense`) if absent. It does not choose a parent account because no parent was approved.
- `database/migrations/2026-10-12_hr_employee_incentives_ledger.sql` creates versioned policy/rule records, idempotent source-event records, snapshotted project allocations, employee awards, and controlled correction/reversal records.

Schema protections include unique policy versions, one rule per incentive type per policy version, a unique event idempotency key, one award per event/employee, and foreign-key restrictions against deleting referenced policy/event/allocation/award history. Historical source events are not backfilled. The migrations do not create runtime DDL, triggers, views, procedures, functions, or events.

Commits:
- Expense account migration: `9f5914f3205988bf6c7078c823f432119bd69682`
- Incentive ledger migration: `afd84b9040893477f6052db9a9bbb1a4f7b5dda0`

**Status:** committed to GitHub `main`; not yet applied to the local database; no PHP workflow has yet been implemented or runtime-verified. The next gate is local migration application and verification, followed by policy administration and the transactional event/award integration. Do not mark the feature complete based on schema creation alone.

The policy workflow follows the user-approved governance decision: GM/FM agree on the policy first; FM records/activates the agreed policy without a second GM approval. Individual incentive achievements still require manager verification, with financial confirmation where the source event is a collection or project closure.
