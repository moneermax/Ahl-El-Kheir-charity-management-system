# Ahl El Kheir — Operations, Security and Maintenance

Status: Canonical operational reference
Baseline: 2026-10-03

## 1. Development environment

- Windows
- XAMPP / Apache
- PHP 8.2+
- MariaDB/MySQL
- Database: ahl_el_kheir
- Local URL: http://localhost:8081/AhlElKheir/

Use config/environment.php and PRODUCTION_PREPARATION.md for deployment-specific settings.

## 2. Security rules

- All protected pages require authentication.
- Page authorization must be enforced server-side.
- Record-level scope must be enforced server-side.
- Workflow-state transitions must be validated server-side.
- State-changing requests use POST and CSRF.
- File-serving routes require authentication, authorization and safe path handling.
- Search must respect record scope.
- UI visibility is not a security control.

## 3. Financial safety

Never:
- edit historical posted journals to repair a test;
- invent balancing entries;
- alter treasury balances directly;
- represent a receivable/control event as an expense;
- bypass FM/accounting approval;
- settle Fina through Ahl operating treasury;
- close a project with an unreconciled controlled balance;
- repeat a return/disbursement/settlement after its completion without a real business event.

## 4. Database safety

Schema changes belong in database/migrations/.

Runtime requests must not create/alter schema or create triggers/views/stored procedures/functions/events.

## 5. Git safety

Work on main. Do not create routine branches.

Never use:
- git reset --hard
- git clean
- git restore to discard work
- force push

Before synchronization, inspect status and preserve intentional local work.

## 6. Engineering method

Inspect -> Understand -> Design -> Implement -> Static review -> Runtime test -> Documentation -> Commit.

For financial changes additionally define:
business event, debit/credit, reference, duplicate protection, evidence and reconciliation.

## 7. Testing

Tests must be tied to a real risk and actual schema/workflow.

Use controlled fixtures when necessary. Do not rerun closed acceptance fixtures unless there is regression evidence.

Runtime claims must be based on actual runtime evidence, not source inspection alone.

## 8. Production preparation

Before production:
- secure environment values;
- disable development error display;
- verify HTTPS;
- verify file/storage permissions;
- apply migrations in order;
- verify backups/restoration;
- verify accounting opening balances;
- verify users/roles;
- verify uploads/receipts;
- verify no runtime DDL remains.

## 9. Maintenance

Do not delete or rewrite an apparently unused table/migration/fixture without determining whether it is:
- current runtime dependency;
- historical evidence;
- migration dependency;
- compatibility code.

Fina settlement tables are intentionally retained for current dependencies/history.

## 10. Evidence standard

An incident record should capture:
user/role, URL/action, record ID, workflow state, audit event, journal/reference if financial, notification result and exact error.

## 11. Current verified boundaries

- Projects Phase 5 controlled reconciliation/closure: runtime verified and closed.
- HR Salary Advance Stages 1–5: completed at their documented acceptance boundary. Stage 6 Direct Repayment & Settlement remains NOT STARTED.
- Fina core settlement model: accepted.
- Accounting/notification audits: completed at documented evidence boundaries.
- Development/test data remains non-production data unless explicitly designated.


## 2026-10-05 — Attendance automation operations

Attendance automation has two controlled execution points: successful web login may create the day's attendance through the attendance policy helper; tools/finalize_daily_attendance.php creates missing absence records after the effective policy finalization time. Automatic absence cannot depend only on login, so Windows Task Scheduler or an equivalent scheduler is required for the finalizer. The finalizer is idempotent and performs no runtime DDL. Policy administration is restricted to HR Manager/Admin. Runtime verification remains pending until the migration and complete login/finalizer flow are exercised locally.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.

## 2026-10-06 — GM Salary Advance Waiver: Payroll Repayment Dependency Runtime Verified

The controlled payroll-integration dependency required by the GM salary-advance waiver has now been runtime verified locally on `main` using the existing rollback-only Stage 5 harness:

`php tools\\run_salary_advance_stage5_payroll_tests.php`

Result:
- PASS — payroll repayment application: `SAR-2026-00004`, temporary payroll ID 24, deduction 5,000 SDG, one repayment trace row, schedule status `paid`, outstanding balance 45,000 SDG, balanced journal 116.
- PASS — duplicate repayment protection for the same request/payroll.
- PASS — rollback-only cleanup; no payroll/request/schedule/journal mutation was committed.

This verifies the real payroll repayment path used by salary advances: payroll deduction -> repayment allocation -> schedule update -> outstanding-balance reduction -> Cr 1410 accounting -> duplicate protection, without creating permanent test data.

This does **not** close the GM waiver feature. The waiver-specific runtime matrix remains open, including FM preparation, GM approval/rejection, refund of already-paid current-period deductions, remaining-balance waiver, future-deduction blocking, undistributed-request cancellation, concurrency/duplicate execution protection, draft refresh, post-commit notifications, audit evidence and final 1410 reconciliation.

Evidence boundary:
- Current waiver implementation commits include `b5e0bbd`, `2d035ae`, `1a67312`, and `7e63fd0`.
- The local database already contains the waiver migration; no new schema change was made for this verification.
- No production payroll-generation code was changed as a result of the earlier read-only October simulation or this rollback-only test.

The unfinished attendance same-page POST scroll-jump issue remains separate and must not be reopened during this work.
