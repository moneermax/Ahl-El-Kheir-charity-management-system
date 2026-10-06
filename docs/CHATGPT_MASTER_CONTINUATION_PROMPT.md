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

## LATEST WAIVER CHECKPOINT — 2026-10-06

The GM Salary Advance Waiver verification matrix has passed through post-commit execution notification delivery.

The immediate next gate is **notification failure-isolation runtime verification**.

Source inspection has already confirmed:
- `ak_transaction_review_notify_event()` catches `Throwable`;
- the installed notification path must support the legacy schema fallback;
- no existing deterministic failure seam was available.

A narrow inert test-only delivery hook and dedicated harness are now on `main`:
`tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

Runtime status: **PENDING USER EXECUTION**.

Do not repeat any closed waiver harness. If the failure-isolation harness passes, proceed to audit-preservation runtime verification, then final 1410 reconciliation. Keep same-employee multiple-advance and true two-process concurrent execution as explicit open evidence gaps unless separately proven.

## LATEST CHECKPOINT — 2026-10-06 — Notification Failure-Isolation PASSED

The notification failure-isolation gate is closed.

Runtime:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

PASS evidence:
- `SAR-2026-00004`
- decision `13`
- delivery attempts `2`
- injected failures `1`
- status `executed`
- outstanding `0`
- waiver journal `125`
- schedule overlays `10`
- cleanup restored notifications/request and removed decision/journal residue.

**NEXT:** audit preservation runtime verification.

Do not repeat prior waiver tests. Before creating the audit-preservation harness, inspect the actual salary-advance request, repayment, payroll-accounting, journal and audit schemas and existing repository test patterns. Then build the narrowest real-fixture runtime verification and ask for the exact command. Keep multiple eligible advances for one employee and true two-process concurrency explicitly open unless separately proven.


## FINAL CHECKPOINT — 2026-10-06 — GM Salary Advance Waiver CLOSED

The GM Salary Advance Waiver verification unit is now **RUNTIME VERIFIED / CLOSED at the documented acceptance boundary**.

Final audit-preservation gate:
`php tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`
PASS for `SAR-2026-00004`, decision `15`; original disbursement journal `73`, temporary payroll `24`, payroll journal `129`, repayment `6`; historical audit rows preserved `6`; waiver audit rows `3`; schedule overlays `9`; rollback cleanup fully restored the pre-test evidence set.

Final 1410 reconciliation:
`php tools\\run_salary_advance_1410_reconciliation.php`
PASS with ledger balance `190000` = live outstanding `190000`; debits `220000`; credits `30000`; direct repayment credit `30000`; 5 open disbursed requests; 11 journals / 11 lines.

The verified 1410 equation is:
`disbursement debits + waiver-refund debits - payroll repayment credits - direct-repayment credits - waiver credits = live outstanding receivable`.

Do not repeat the closed waiver verification matrix without concrete regression evidence. Do not reopen Salary Advance Stages 1–6.

Explicit evidence gaps that remain intentionally open:
- same employee with multiple eligible advances;
- true two-process concurrent execution.

These are not failures and are not runtime-proven.

The unfinished attendance same-page POST scroll-jump issue remains out of scope.

**Next continuation rule:** select the next task from the current canonical documentation and the user's new requirement. Do not automatically reopen the waiver unit.

## 2026-10-06 — English i18n cleanup CLOSED

The system English i18n cleanup is now STATIC + RUNTIME VERIFIED / CLOSED.

Static command:
php tools\i18n_gap.php

Result:
Files with gaps: 0 | Untranslated Arabic strings: 0

Runtime verification:
The affected pages were tested locally in English mode with the language switch, including attendance policy, payroll, payroll policy, salary-advance waiver FM/GM, accounting FM dashboard, HR dashboard and reports. User result: everything is English and pages behave normally.

Continuation rule:
- Do not rerun the i18n scan or runtime verification unless a concrete regression or new untranslated source string is introduced.
- Do not reopen the completed GM Salary Advance Waiver verification matrix.
- Do not reopen Salary Advance Stages 1–6.
- Do not reopen the unfinished attendance same-page POST scroll-jump issue unless explicitly requested.
- Select the next task from the current canonical documentation and the user's next requirement.

## 2026-10-06 — Attendance ↔ Payroll integration: current continuation point

The next open task is the **Attendance Policy ↔ Payroll integration runtime verification**.

A source-level audit found and corrected one concrete defect before runtime verification: `hrSalaryAdvancePayrollRefreshDraft()` did not subtract `attendance_deduction` when recomputing `net_salary`. The salary-advance eligibility calculator already treated attendance deduction correctly for `net_before_advance`, so the refresh path was inconsistent.

Fix commit:
`60adf7f4f428b1754f45b010c552ad93a2155323`

Rollback-only runtime harness:
`tools\\run_hr_attendance_payroll_integration_tests.php`

Harness commit:
`20c8f23e5fc239fd3b19f26ec4cd424c08dc9d34`

**Runtime status: PENDING USER EXECUTION.**

Exact next command:
`php tools\\run_hr_attendance_payroll_integration_tests.php`

Continuation rules:
- Do not repeat closed GM Salary Advance Waiver tests.
- Do not repeat closed i18n verification.
- Do not reopen Salary Advance Stages 1–6.
- Do not reopen the unfinished attendance same-page POST scroll-jump issue.
- If the harness fails, inspect the exact failure/root cause before any further modification.
- If it passes, document the evidence and then perform any remaining attendance/payroll runtime coverage required by the canonical acceptance boundary.
