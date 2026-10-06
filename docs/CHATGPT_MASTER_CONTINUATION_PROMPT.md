# Ahl El Kheir — Continuation Rules

## Start here

Read the canonical documentation first:
1. AHL_EL_KHEIR_SYSTEM_ANALYSIS.md
2. AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
3. AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
4. AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
5. AHL_EL_KHEIR_OPERATIONS_SECURITY.md
6. AHL_EL_KHEIR_DOCUMENTATION_INDEX.md

Then inspect the relevant source, schema and domain evidence.

## Current boundary — 2026-10-05

Projects Phase 5 controlled PRJ-0015 reconciliation and financial closure is runtime verified and closed. Do not recreate the fixture or repeat the return.

HR Salary Advance Stages 1–5 are complete at their documented acceptance boundary. Stage 6 — Direct Repayment & Settlement — remains NOT STARTED and must not be implemented unless explicitly opened as the next agreed work unit.

Fina core settlement acceptance is complete at its documented boundary.

## Non-negotiable rules

- Existing project only; do not rebuild.
- main only; do not create branches for routine work.
- Never use reset --hard, clean, restore-to-discard, or force-push.
- Inspect actual source/schema before changes.
- Never invent tables, columns, statuses, roles or accounts.
- No runtime CREATE/ALTER.
- No triggers/views/stored procedures/functions/events.
- Server-side authorization is the security boundary.
- Preserve historical accounting evidence.
- Test only against a documented risk or regression.
- Update canonical documentation after meaningful changes.

## Engineering method

Inspect -> Understand -> Verify -> Identify risk -> Fix narrowly -> Test -> Document.


## Latest HR checkpoint — 2026-10-05

The employee salary register is implemented at `modules/hr/employees.php?action=salary_register`.

Rules:
- use the earliest positive salary-history row per employee;
- ignore the automatic 0.00 provisioning placeholder;
- current salary values are database-only development/test data;
- include only employment states with `category = 'working'` and `is_active = 1`;
- do not delete or alter historical HR/payroll/accounting evidence for employees who become non-working.

Latest source commit: `b8adb067c3de34441192e63fff56c6b707b3aa22`.

When continuing, pull/inspect the current `main` state first. Do not assume the next task from chat history; identify it from the current canonical documents and the user's new requirement.

## Latest HR attendance checkpoint — 2026-10-05

A policy-driven employee attendance foundation is implemented. Do not replace it with hard-coded hours or create a second attendance subsystem. Implementation files: database/migrations/2026-10-05_hr_attendance_policy.sql, modules/hr/lib_attendance_policy.php, modules/hr/attendance_policy.php, index.php login integration, tools/finalize_daily_attendance.php. Existing modules/hr/lib_attendance_integrity.php remains authoritative for employment-state and approved-leave eligibility.

V1 policy controls working start/end, attendance cutoff, absence finalization, automatic login attendance, automatic absence and default work mode. Example/default values are remote work, 07:00–16:00, but these are policy data. Qualifying login creates today's present record and preserves the first check-in; non-working and approved-leave employees are excluded; employees who never log in require the scheduled finalizer; the finalizer is idempotent and never overwrites an existing daily row.

Runtime status: STATICALLY IMPLEMENTED — RUNTIME VERIFICATION PENDING. First apply/verify the migration and complete the login/finalizer runtime gate before adding late-arrival, shifts, weekends, holidays or other advanced attendance rules.

Salary Advance boundary: Stages 1–5 are closed at the documented acceptance boundary. Stage 6 Direct Repayment & Settlement remains NOT STARTED unless explicitly opened by the user.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.


## 2026-10-06 — Mandatory continuation checkpoint: GM Salary Advance Waiver

Continue the existing GM Salary Advance Waiver work exactly from this checkpoint. Do not restart analysis or repeat already closed runtime gates.

Closed gates — DO NOT RERUN WITHOUT REGRESSION EVIDENCE:
- transaction-composable remaining-balance execution;
- paid-deduction refund;
- undistributed-request cancellation;
- future-deduction blocking and draft refresh;
- approved-unpaid payroll protection;
- blanket scope/execution;
- sequential duplicate execution protection;
- post-commit FM preparation → GM notification;
- post-commit GM approval → FM notification;
- post-commit FM execution → approving GM notification;
- post-commit FM execution → affected employee notification.

Latest closed execution-notification evidence:
php tools\\run_salary_advance_gm_waiver_execution_notification_rollback_tests.php
PASS: decision 12, request SAR-2026-00004, GM notification 1, employee notification 1, waiver journal 124; cleanup PASS with zero test decisions/notifications/journals.

OPEN — start here next:
1. Notification failure isolation runtime. Inspect the actual ak_transaction_review_notify_event implementation and existing notification schema/test patterns first. Do not guess a failure mechanism. Create a controlled, rollback-safe test that causes notification delivery to fail and proves the waiver financial/business commit remains intact.
2. Audit preservation runtime. Verify original disbursement, payroll repayment and accounting history are untouched, while waiver decision/item/schedule/audit records provide the required evidence.
3. Final 1410 reconciliation. Reconcile the 1410 control balance after waiver execution against the remaining live salary-advance receivables.

Evidence gaps to retain, not silently close:
- Same-employee multiple eligible advances: current blanket fixture had none, so consolidated employee notification for that exact scenario is not runtime-proven.
- True two-process concurrent commit: source row locking plus sequential duplicate protection are proven, but no dedicated two-process runtime test has been executed.

Hard continuation rules:
- Do not rerun any closed waiver harness just because the chat session changed.
- Do not reopen Salary Advance Stages 1–6.
- Do not reopen the unfinished attendance same-page POST scroll-jump issue.
- Do not change the waiver production logic merely to make a failure-isolation test pass; first identify the real notification failure path and use the narrowest controlled test seam available.
- After each newly closed gate, update the waiver continuation doc, master status, session index, and this continuation prompt before moving to the next gate.
