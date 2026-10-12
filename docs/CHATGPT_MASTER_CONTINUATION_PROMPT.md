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

## FINAL CHECKPOINT — 2026-10-06 — Attendance ↔ Payroll integration CLOSED

The Attendance ↔ Payroll integration is **RUNTIME VERIFIED / CLOSED**.

Do not restart or repeat the completed verification merely because a new chat session begins.

Final runtime command:
`php tools\\run_hr_attendance_payroll_extended_integration_tests.php`

Final PASS:
- policy effective-date selection: PASS
- attendance policy effective-date selection: PASS
- `net_before_advance`: PASS
- employee 8 / request SAR-2026-00007 / period 2026-10
- attendance deduction 333.33
- salary-advance deduction 9666.67
- net salary 0.00
- paid payroll immutable: PASS
- rollback: PASS

The earlier core integration harness also passed all explicit absence/leave/non-working/missing-attendance branches.

Known non-blocking observation: CLI execution emits the existing `REQUEST_METHOD` warning from `config/functions.php:52`. Do not alter production code solely to silence it without a separate root-cause task.

**Next continuation rule:** select the next task from the canonical documentation and the user's new requirement. Do not reopen closed waiver, i18n, Salary Advance Stages 1–6, or attendance/payroll verification without concrete regression evidence. The attendance same-page POST scroll-jump issue remains explicitly unfinished/out of scope.

## 2026-10-06 — FM UI consistency cleanup

A focused UI consistency pass corrected two visible inconsistencies in the FM dashboard quick-action section and the GM salary-advance waiver FM page.

- `modules/accounting/fm_dashboard.php`: the final two quick-action cards — **إدارة الرواتب** and **منظمة فينا الخير** — now have the same fixed-height/card treatment as the other FM quick-action cards, including their missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the top page title section now uses the same FM blue-gradient header treatment as the FM dashboard instead of the previous standalone white bordered card.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`

Runtime browser verification is still required on the local XAMPP application. Do not mark the UI fixes runtime-verified until the user confirms the affected FM pages visually.

## FINAL CHECKPOINT — 2026-10-06 — System-wide same-page POST scroll-jump fix CLOSED

The previously unfinished same-page POST viewport-jump issue is now **RUNTIME VERIFIED / CLOSED system-wide**.

### Authoritative rule
The global handler in `assets/js/app.js` uses a **default-on / opt-out** policy. A qualifying POST to the current application path is intercepted in the capture phase and processed in place, preserving the user's viewport instead of causing native full-document navigation.

Do not add per-page `data-ak-same-page="1"` attributes for ordinary same-page POSTs. The attendance page was tested after its opt-in attribute was removed, and the user confirmed that the page stayed in place. That test is the acceptance evidence for the global mechanism.

### Explicit exceptions
Native navigation remains intentional for:
- `data-ak-native-post="1"`;
- `data-ak-same-page="0"`;
- `data-ak-ajax-post="0"`;
- multipart/form-data submissions;
- non-self targets;
- cross-path or cross-origin submissions.

Submitter overrides are honored, including `formaction`, `formmethod`, `formenctype` and `formtarget`.

### Implementation commits
- `af5fe03e3592972c61ec2821fa7ad0a7f4b7abfd`
- `0a06fb57f687f4ac56c623251a7fa38d6dba083d`
- `4496a8f6fb4d38a856483b25d856c640a81732ec`
- `396576c6bc8d1f26af37a09e80c15909aac06e60`
- `8389708b84a9853f164d3b73d6f810dbe4967ef5`

### Continuation rule
Treat the scroll-jump issue as closed. Do not reopen it, repeat its old diagnostic tests, or add page-specific workarounds unless a concrete regression is reported. Continue from the next genuinely open task in the canonical documentation and the user's new requirement.

## 2026-10-06 — FM UI consistency cleanup: RUNTIME VERIFIED / CLOSED

The focused FM UI consistency cleanup is now **runtime verified / accepted**.

Verified/accepted changes:
- `modules/accounting/fm_dashboard.php`: the final FM quick-action cards now use the same fixed-height/card treatment, including the previously missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the FM salary-advance waiver page title/header now uses the FM blue-gradient header treatment consistently with the FM dashboard.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`
- `40d4b3ad552ac0f9a77720cbd112f41bee015aa6`

User runtime result: the FM UI is visually accepted. Do not reopen these UI issues absent a concrete regression.

## 2026-10-06 — Production-preparation database cleanup CLOSED

The production-preparation database cleanup task is now **RUNTIME VERIFIED / CLOSED at the disposable-database acceptance boundary**.

Corrected implementation commit:
`5f92a919ec64127428fda5e0adf8b5243ad8554f`

Final semantics:
- preserve exactly the production data explicitly protected by the original script;
- preserve accounts except the original eight exact test account codes;
- clear every other current base table as development/test data;
- do not drop tables;
- do not delete physical storage files;
- do not use runtime schema changes.

Runtime evidence:
- fresh live database dump imported into disposable `ahl_el_kheir_production_test`;
- corrected script executed successfully;
- users 26;
- employees 25;
- families 2208;
- family_children 4910;
- sponsors 2858;
- sponsorships 3560;
- sponsorship_children 2965;
- sponsor-supervisor history 270;
- supervisor letters 56;
- supervisor-letter history 6;
- roles 14;
- departments 8;
- currencies 4;
- letters 29;
- medical needs 14;
- settings 18;
- accounts 38;
- all 83 cleanup tables = 0 rows;
- original test accounts remaining = 0;
- no SQL execution error or warning.

**Continuation rule:** do not reopen or repeat this verification without a concrete regression or a change to the production-preparation contract. The next task should come from the current canonical documentation and the user's new requirement.

The temporary test artifacts `database/production/current_test_database.sql` and `database/production/prepare_production_database_test.sql` must not be committed or uploaded to the repository. They are local disposable test artifacts and should be deleted after verification.




## Current continuation point — 2026-10-12: Stage 7 browser reschedule saved; toast verification pending

User submitted a reschedule for request 7 in the isolated salary_advance_browser_test database. Read-only SQL confirmed plan 1 is preserved and superseded, new plan 2 (ID 8) is active, its five installments total 50,000.00 and each is at or below 10,000.00, and audit row 2263 records HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE. Do not repeat or undo this operation.

The success message appeared as an inline alert. Source inspection found the success/error alerts in modules/hr/salary_advance_reschedule.php did not match the global transient-alert selectors in assets/js/notifications.js. Commit b546005f7f7996d1c4f1946de82e9c72563733f7 adds alert-dismissible to those two messages only. Next: on the user's local checkout, pull main, inspect status/diff without overwriting local changes, run PHP lint on the page, and browser-test that success/error feedback is now toast-based while the persistent repayment instructions remain inline. Then inspect the actual isolated payroll fixture and verify payroll/accounting integration without blindly posting payroll or mutating canonical ahl_el_kheir. Restore the original .htaccess from the recorded backup only after all local browser testing is finished, verify stage7_db_probe.php is absent, update evidence, and keep Stage 7 OPEN until the payroll/accounting path passes.


## 2026-10-12 — Stage 7 payroll acceptance progress and payroll table refinement

The isolated browser workflow has reached November 2026 draft payroll generation in `salary_advance_browser_test`. The application created 22 drafts using payroll policy version 2. Read-only SQL identified payroll row ID 46 for employee ID 8 / EMP-0021: basic salary 10,000.00, salary-advance deduction 10,000.00, net salary 0.00, status `draft`, accounting status `none`, with no accounting entry, journal entry, or payment account yet. This confirms the active November rescheduled installment is reflected in the draft. It does not yet prove repayment application or accounting posting. Do not use the period-wide “approve drafts” action; it affects all 22 records.

Read-only account lookup in the isolated database returned account ID 2 / code 1200 (Bank), ID 37 / code 1410 (Employee Salary Advances Receivable), and ID 14 / code 5200 (Salaries). The next acceptance gate is to approve and pay only payroll row 46 using the page’s per-row actions, then verify exactly one repayment application, schedule/payment linkage, request outstanding balance, paid payroll state, posted accounting entry, and a 10,000.00 credit to account 1410. The canonical `ahl_el_kheir` database must not be modified.

The user also requested a more compact payroll table. Commit `ae7d9fc6209a8ff1366afa96b9fb73720d614249` on `main` removes the Contract and Policy columns, removes redundant currency labels from amount cells, tightens input/typography spacing, adds distinct blue cell borders and a blue-tinted header, and removes now-unused joins from the month-list query. The shared policy summary above the table remains. The user later reviewed the compact layout locally and described it as much better. This is browser visual feedback only; no separate PHP syntax-check result is asserted here.

The user confirmed the rescheduling success/error feedback now appears as a toast after commit `b546005f7f7996d1c4f1946de82e9c72563733f7`. The isolated payroll repayment/accounting acceptance and temporary-environment cleanup are now complete; see the dated Stage 7 acceptance-closure entry below for verified results. Do not restore the temporary routing again or repeat the completed acceptance absent concrete regression evidence.

## Current continuation point — 2026-10-12

HR Salary Advance Stage 7 repayment rescheduling has passed the documented isolated browser acceptance and is **CLOSED at that acceptance boundary**. The verified journey is documented in `docs/HR_SALARY_ADVANCE_RESCHEDULING_AUDIT.md` and `docs/HR_SALARY_ADVANCE_CONTINUATION.md`. Do not repeat the acceptance or reopen closed salary-advance stages without concrete regression evidence.

Local cleanup is complete: `.htaccess` is exactly `Options -Indexes`, the temporary `stage7_db_probe.php` is absent, and the protected receipt image remains untouched. Canonical `ahl_el_kheir` policy authorization remains disabled and canonical request 7 remains at 50,000.00 outstanding. The isolated test alone reduced its copy of request 7 to 40,000.00 after payroll 46 posted journal 116 and applied schedule 42 once.

Evidence gaps remain: local runtime has not proven multiple simultaneously eligible advances for one employee or true concurrent production execution. Do not state these are proven. At the start of the next task, inspect current `main` and the canonical docs, then select the next genuinely open requirement; do not automatically reopen Stage 7, the closed waiver/i18n/attendance-payroll stages, or the unfinished attendance scroll-jump issue unless explicitly requested.


## NEW ACTIVE FEATURE — Employee incentives

**Source-audit blocker:** `modules/sponsors/requests.php` records `brought_by_user_id` from sponsor-request conversion, but `modules/sponsors/create.php` only writes `created_by` plus free-text `brought_by_name`. Never infer the award recipient from `created_by`. Before implementing new-sponsor awards, ask whether directly created sponsors without verified attribution should require manager-verified employee attribution or remain ineligible. Win-back records `handled_by` as a user ID; do not assume it is the reward recipient without confirming the intended business rule.
 (checkpoint 2026-10-12)

Continue from `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. The agreed feature has three fixed-amount, policy-versioned categories: financially confirmed monthly sponsorship collections (recipient = recorded collector); new sponsor/reactivation (recipient resolved from `sponsors.brought_by_user_id`, award only after sponsorship and first payment are confirmed; 90-day reactivation lookback); and successful project completion (award only after administrative closure plus FM financial closure, with explicitly approved split among named eligible team members). Amount/currency are configured in policy; 10,000 SDG was only an example. Monthly collection reward is not percentage-based.

Governance: GM approves policy, FM validates financial controls, HR administers. Preserve already-earned rewards if employment status later changes; block new awards for ineligible employees. Hold rewards when currency does not match applicable salary currency; no invented FX. Reopen/reversal uses controlled review/adjustment, never silent deletion. Use a dedicated incentive expense account created through chart-of-accounts process; do not assume 5200. Schedule into the next eligible open payroll period. Notify employees after durable state changes and distinguish earned/scheduled/paid.

Evidence: `modules/accounting/fm_review_queue.php` is the sponsor-payment approval/posting boundary; `modules/administration/winback.php` reactivates sponsors before payment; `modules/projects/view.php` records administrative closure while `modules/projects/project_funding_accounting.php` / `view_fm.php` handle separate FM financial closure; `modules/hr/payroll.php` refreshes drafts and `modules/hr/lib_payroll_accounting.php` posts payroll journals. Payroll lacks incentive attribution and currency columns; generic allowances alone are not sufficient. Build a dedicated incentive ledger/detail model and explicitly reconcile the payroll journal while preserving paid-payroll immutability.

Latest DB backup commit: `de397397432797c674a9bf06133e90d57bffb5b7`. It is a data-bearing SQL snapshot, not a migration; do not restore it into the live database or commit future organizational dumps. No incentive code/migration has been implemented or runtime-verified yet. Local Windows/XAMPP is unavailable to this assistant, so do not claim PHP lint or runtime PASS without actual local evidence. Finish source/schema audit, implement the smallest complete migration + integration, add rollback-only acceptance harnesses, then verify locally and document actual results. Keep `main` only; preserve local changes and avoid destructive Git operations.
