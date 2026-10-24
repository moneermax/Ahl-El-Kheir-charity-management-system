# GM Payroll Period Completion Report — Continuation Checkpoint

**Date:** 2026-10-19  
**Status:** Source committed to `main`; local PHP syntax and live Apache/database verification remain pending.  
**Scope:** GM-only, read-only monthly payroll completion report and period-level completion notification.

## Requirement and behavior

- Once per payroll period, notify active General Manager accounts only after every payroll-eligible employee for that period has a payroll row with `status = 'paid'`, `accounting_status = 'posted'`, and a non-null journal entry ID.
- Eligibility follows the current payroll-generation rules: hire date on or before period end (or null), a salary-history row effective on period end, positive historical basic salary, and a working employment state effective at period end.
- Check after a payment commits and when the payroll management page loads the selected period. The page-load check allows a period already paid before deployment (including October 2026) to be recognized without another payment action.
- Deduplicate using the existing `notifications` table's recipient, link, and title. No schema changes or runtime DDL.
- The report is only available to `general_manager` / `gm`, and is read/print-only. It includes payroll components, payment date, journal entry ID, and the existing total-paid formula `net_salary + incentive_amount`.
- Existing individual employee salary-statement notifications remain unchanged.

## Implementation

- `modules/hr/lib_gm_payroll_period.php` — period eligibility/completion calculation and idempotent GM notifications.
- `modules/hr/gm_payroll_period_report.php` — GM-only Arabic/RTL read-only report and print layout.
- `modules/hr/lib_payroll_accounting.php` — completion notification check after a payment transaction commits.
- `modules/hr/payroll.php` — completion check when the selected payroll period loads.
- `includes/sidebar.php`, `lang/ar.php`, `lang/en.php` — GM navigation and labels.

## Verification boundary

Repository edits are committed to `main`. No payroll was paid again, and no October payroll or accounting rows were changed. Local verification is still required:
1. Run PHP syntax checks on the four changed PHP source files.
2. Open payroll management for October 2026 as HR/FM and confirm the already-paid period produces at most one GM notification.
3. Sign in as GM, open the notification/report, and verify employee count, paid totals, payment dates, and journal entry IDs against saved October records.
4. Confirm the report prints cleanly and GM cannot access payroll management or mutate a payroll through this report.
5. Confirm a non-GM direct request to the report is redirected.

Do not repeat any payment, change paid payroll, or create test payroll data to perform these checks. Report any specific discrepancy before modifying business logic.


## 2026-10-19 — GM payroll payment history navigation
Added a dedicated GM payroll payment history page and a quick-access card on the GM dashboard. The history lists payroll periods with at least one payroll record in `paid` status, shows each period's eligible/paid-and-posted counts and posted payment totals, and links to the existing read-only period report. The GM sidebar entry now opens the history page; notification links still open their specific period report directly. The page is GM-only and read-only; it does not change payroll or accounting data.

**Verification boundary:** source committed to `main`; local PHP syntax and browser verification are pending. Do not pay, approve, regenerate, or otherwise mutate payroll records for this UI change.


## 2026-10-19 — GM payroll payment history navigation CLOSED
The user confirmed that the history navigation works as intended. The dedicated history page, GM dashboard shortcut, and sidebar route are accepted. This is read-only navigation; payroll and accounting records were not changed. Do not reopen or repeat verification without a concrete regression.
