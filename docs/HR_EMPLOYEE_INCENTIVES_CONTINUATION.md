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


## Newly identified implementation blocker — direct sponsor attribution

The two sponsor creation paths are not equivalent:

- `modules/sponsors/requests.php` converts a sponsor request and writes `sponsor_requests.brought_by` into `sponsors.brought_by_user_id`. This gives a user-ID attribution that can be resolved to an employee.
- `modules/sponsors/create.php` directly creates a sponsor with `created_by` and free-text `brought_by_name`; it does **not** populate `brought_by_user_id`. The edit page also edits the free-text name, not the user-ID attribution.

Therefore, it would be incorrect to silently treat `created_by` as the person who brought the sponsor, or to reward an arbitrary free-text name. The feature needs an explicit rule for directly created sponsors: either require a manager-verified employee attribution through an explicit user/employee field before the first-payment award can be earned, or make direct-create records without verified attribution ineligible for that award. This decision must be settled before coding the acquisition path. The 90-day reactivation path is separately attributed to the employee who handled the win-back, if that is the intended recipient; the current `winback_campaigns.handled_by` is a user ID and must be resolved/verified rather than inferred from sponsor creation metadata.


## Decision confirmed by user — sponsor attribution (2026-10-12)

- For sponsors entered directly by an employee, the system should automatically record the logged-in employee's **user ID** as the person who brought the sponsor. The employee must not type their own attribution or choose an arbitrary recipient.
- For sponsor requests converted into sponsors, preserve the request's recorded `brought_by` user ID; do not overwrite the original attribution with the employee who processes the conversion.
- For win-back/reactivation, the reward recipient is the employee who handled the successful win-back. This rule is approved by the user; resolve `winback_campaigns.handled_by` through the employee/user relationship and award only after the first payment is financially confirmed.
- Existing source code already writes `sponsors.brought_by_user_id` in `modules/sponsors/requests.php`. Therefore, the next step is to verify the exact column definition against the latest committed `database/ahl_el_kheir.sql` and local schema. If the column exists as the request-conversion code indicates, do **not** add a duplicate column; instead, update the direct sponsor-creation path to populate it automatically. If the backup proves otherwise, add it through a dated migration.
- Historical sponsors with no recorded attribution must remain unattributed until an authorized, auditable correction supplies it. Do not backfill them from `created_by`, free-text `brought_by_name`, or another guessed field.
