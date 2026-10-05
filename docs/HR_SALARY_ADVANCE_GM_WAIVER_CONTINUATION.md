# GM Salary Advance Waiver — Continuation Checkpoint

Date: 2026-10-05
Status: design agreed; implementation in progress; runtime verification pending.

## Requirement
GM may decide, for any reason, to waive salary advances or submitted salary-withdrawal requests. A blanket decision includes previous loans. If a current payroll installment was already deducted and the remaining salary paid, that deducted amount must be refunded and the remaining covered advance balance must be waived. Example: 100,000 original, 60,000 previous repayments, 10,000 current deduction, 30,000 remaining => refund 10,000, waive 30,000, final 1410 balance zero. Historical disbursement and repayment records remain untouched.

## Workflow
1. GM informs FM outside the system.
2. FM prepares the concrete decision in-system: blanket/individual scope, effective payroll month, reason, affected advances, refund account, waiver expense account. No posting occurs.
3. Status becomes pending_gm.
4. GM reviews and approves or rejects. Approval creates no journal. Rejection requires a reason and has no financial effect.
5. Approved status is approved_by_gm; control returns to FM.
6. FM executes atomically; final status is executed.
7. Notifications are sent after commit.

## Accounting
Original disbursement remains Dr 1410 / Cr actual source. Waiver is Dr selected active expense / Cr 1410. Current paid deduction refund is Dr 1410 / Cr selected refund source. Refund sources are active 1100/1200/1300. Waiver expense must be active account_type=expense. Accounts are stored before GM approval and reused at execution. Never rewrite original journals or payroll repayment rows; never use whole-payroll reversal.

## Request and schedule rules
Disbursed requests remain disbursed and outstanding becomes zero. Undistributed qualifying requests are existing submitted, fm_review, or approved requests with closed_at IS NULL; they are cancelled without accounting. Do not invent a new request status. Future unpaid schedules remain historical rows; an overlay records waived installments. Executed waivers block future payroll deductions. Draft payrolls are refreshed. Approved-but-unpaid payrolls are blocked rather than silently modified.

## Notifications — mandatory
- FM preparation commit -> active GM: decision waiting for approval.
- GM approval commit -> preparing FM: approved and ready for execution.
- GM rejection commit -> preparing FM: rejected plus reason.
- FM execution commit -> approving GM: execution confirmation, effective month, refund total and waiver total.
- FM execution commit -> every affected employee with a linked active account: GM decision + FM action; include qualifying refund, waived balance and stopped future deductions, or cancellation of an undistributed request. Consolidate multiple affected advances into one employee notification.
Notification failure must never roll back committed business/financial work. Employees without linked accounts remain auditable but cannot receive in-system notification. Do not introduce the previously rejected notification migration.

## Isolated implementation
Eight files only: database/migrations/2026-10-05_hr_salary_advance_waiver.sql; includes/sidebar.php; lang/ar.php; lang/en.php; modules/hr/lib_salary_advance_payroll.php; modules/hr/lib_salary_advance_waiver.php; modules/hr/salary_advance_waiver_fm.php; modules/hr/salary_advance_waiver_gm.php.
Tables: hr_salary_advance_waiver_decisions, hr_salary_advance_waiver_items, hr_salary_advance_waiver_schedule_items.
Baseline: af111bdfbb3770ea5ac782bc2f54376cfb565709. Latest notification wiring: 4415244444006e445912d231e8b59e80773b7857.

## Verification gate
Before closure: syntax; migration; FM blanket/individual preparation; GM/FM notifications; approval/rejection; atomic execution; paid-deduction refund; remaining waiver; 1410 reconciliation; historical preservation; future-deduction blocking; draft refresh; approved-unpaid safety; undistributed cancellation; multiple advances; blanket/individual scope; duplicate/concurrency protection; rollback; final GM and employee notifications; consolidated employee notification; audit; notification failure isolation; final reconciliation. Prefer rollback-only/SAVEPOINT fixtures.

## Rollback
The feature is file-isolated but has multiple commits. Do not claim one-commit rollback exists. Rollback must be non-destructive and must include database reversal planning if migration is applied.

## Next step
Inspect current main and actual schema; apply the waiver migration locally; syntax-check; verify notification schema and employee-user linkage; then run the controlled verification matrix. Do not call complete merely because code exists.
