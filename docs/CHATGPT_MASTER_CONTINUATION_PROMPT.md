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

**User has now resolved the attribution rule:** directly created sponsors should automatically record the logged-in employee's user ID as brought-by; conversion from a sponsor request preserves the request's original brought-by user; reactivation rewards go to the employee who handled the win-back after the first payment is confirmed. The conversion code already writes `sponsors.brought_by_user_id`; inspect the exact schema before considering a migration, and never duplicate an existing column. Do not infer or backfill historical attribution.

**Resolved and locally syntax/schema checked (2026-10-12):** `modules/sponsors/create.php` now writes `brought_by_user_id = Session::getUserId()`; sponsor-request conversion remains unchanged and preserves its original attribution. User ran PHP lint successfully and confirmed the column exists locally as nullable indexed `int(10) unsigned`, FK to `users.id`. A controlled sponsor INSERT/runtime behavior test remains pending. The latest live metadata audit confirms InnoDB for relevant tables, non-unique source-event indexes, payroll employee-period uniqueness, unique account codes/journal entry codes, and the current FK map. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.
 (checkpoint 2026-10-12)

Continue from `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. The agreed feature has three fixed-amount, policy-versioned categories: financially confirmed monthly sponsorship collections (recipient = recorded collector); new sponsor/reactivation (recipient resolved from `sponsors.brought_by_user_id`, award only after sponsorship and first payment are confirmed; 90-day reactivation lookback); and successful project completion (award only after administrative closure plus FM financial closure, with explicitly approved split among named eligible team members). Amount/currency are configured in policy; 10,000 SDG was only an example. Monthly collection reward is not percentage-based.

Governance: GM approves policy, FM validates financial controls, HR administers. Preserve already-earned rewards if employment status later changes; block new awards for ineligible employees. Hold rewards when currency does not match applicable salary currency; no invented FX. Reopen/reversal uses controlled review/adjustment, never silent deletion. Use a dedicated incentive expense account created through chart-of-accounts process; do not assume 5200. Schedule into the next eligible open payroll period. Notify employees after durable state changes and distinguish earned/scheduled/paid.

Evidence: `modules/accounting/fm_review_queue.php` is the sponsor-payment approval/posting boundary; `modules/administration/winback.php` reactivates sponsors before payment; `modules/projects/view.php` records administrative closure while `modules/projects/project_funding_accounting.php` / `view_fm.php` handle separate FM financial closure; `modules/hr/payroll.php` refreshes drafts and `modules/hr/lib_payroll_accounting.php` posts payroll journals. Payroll lacks incentive attribution and currency columns; generic allowances alone are not sufficient. Build a dedicated incentive ledger/detail model and explicitly reconcile the payroll journal while preserving paid-payroll immutability.

Latest DB backup commit: `de397397432797c674a9bf06133e90d57bffb5b7`. It is a data-bearing SQL snapshot, not a migration; do not restore it into the live database or commit future organizational dumps. No incentive code/migration has been implemented or runtime-verified yet. Local Windows/XAMPP is unavailable to this assistant, so do not claim PHP lint or runtime PASS without actual local evidence. Finish source/schema audit, implement the smallest complete migration + integration, add rollback-only acceptance harnesses, then verify locally and document actual results. Keep `main` only; preserve local changes and avoid destructive Git operations.


**Latest code/schema checkpoint (2026-10-12):** commits `aaa71897249ca4911ee2e0763b85737d21ebb698` and `3bc49b342bb0e7aa988690582be2ef091bf1fe3c` add direct sponsor attribution; static arity check confirmed 24 columns, 2 literals, and 22 placeholders matching 22 parameters. User synced local `main` to `48ff9293`, PHP lint passed for `modules\sponsors\create.php`, and live schema confirmed nullable indexed `sponsors.brought_by_user_id` with FK to `users.id`. User also supplied engine/index/FK metadata; see `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. Actual sponsor INSERT behavior is still runtime-unverified. Next gate: targeted `SHOW CREATE TABLE` output for source, project, payroll, salary-history and journal tables, then implement the migration and ledger only after constraints and the reward state model are reconciled. Never restore the data-bearing SQL snapshot into the live DB. No incentive policy/ledger/payroll implementation is complete.

## 2026-10-12 — Current task: Employee Incentives

Continue the active Employee Incentives feature using `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. Current commits on `main` include account 5500, the incentive ledger, category catalogue, custom policy fields, and `modules/hr/incentive_policy.php`. The user confirmed the prior ledger/account migrations are already applied locally, but the new category/custom-field migration has not yet been applied or runtime-verified. Governance: GM/FM discuss and agree first; HR Manager/HR Staff and FM/Admin may create drafts/add custom fields; only FM/Admin may activate without a second GM approval. Do not invent reward amounts. Next: sync latest `main`, apply the new migration, run PHP lint and local UI/role/validation tests, then continue source-event attribution/verification and payroll/accounting integration without altering paid payroll or salary-advance logic.

## 2026-10-12 — Salary portal and incentive extensibility

modules/accounting/fm_dashboard.php now has one salary portal card instead of separate salary-advance, waiver, and payroll cards. modules/hr/salary_portal.php is committed on main and provides role-filtered links to the three existing workflows plus modules/hr/incentive_policy.php. Commits: 8bc2be917afaa09d50a3c46f4d13a3922c42648b and 94f5c9c5666a84cea8fc4a161277dba3b707e2a1. Local lint and browser verification remain pending.

The category catalogue and policy-field extension are now committed. Migration `database/migrations/2026-10-12_hr_employee_incentive_catalogue_and_policy_fields.sql` seeds the four existing category codes, converts the ENUM columns to catalogue-backed VARCHAR codes with foreign keys, and creates version-scoped custom policy fields. The policy UI loads categories from the database and allows new categories only in manual-review mode. Manual nomination/verification/award/payment workflow is not yet implemented; do not claim custom categories can be earned or paid. Local PHP syntax and runtime checks remain pending.

## 2026-10-12 — Current incentive task boundary

Monthly employee performance recognition, scoring, ranking, co-winner handling, and certificates are explicitly deferred to a separate future feature. Current requirement: make incentive policy fields extensible for FM/HR while preserving FM activation authority and the agreed GM/FM governance. Keep custom policy fields separate from custom incentive award types (current type columns are ENUMs). The SDG currency field removal is visible locally (commit `12af8e46b2a9e042322bddf939462b973dd612d0`); local lint/runtime verification remains pending.


## 2026-10-12 — Latest incentive extensibility checkpoint

Latest commits:
- `eb92fa5a42d00c086da0fe6a439511178d8c149b`: category catalogue/custom-field migration.
- `68758c17c6363083f798e019c6348bdb596acc8c`: category-driven policy page, HR/FM draft and custom-field access, FM/Admin-only activation.
- `affd6538d4b3a140a2e3df725642254afdd1f71b`, `663efae40df2577dc517d8f02876c703eda1cfd1`, `40c47a468645de87f5f09eb1b9430c4733cf83e9`, `bdf7c41dff7dd4054096f9f941c3616fe00a1b47`: continuation/master docs updated.

Important: these are GitHub source commits, not local runtime evidence. The new migration has not been applied locally, and `modules/hr/incentive_policy.php` has not yet been locally linted/browser-tested. The assistant cannot access the Windows/XAMPP runtime. The next action is to sync `main`, inspect the exact local diff and current migration state, run `php -l modules\\hr\\incentive_policy.php`, apply the new migration only if not already applied, then test database conversion/foreign keys, category creation, field validation and version isolation, HR denial of activation, and FM activation. If any check fails, inspect the exact failure before changing code.

Custom category records are manual-review only. Do not issue awards or schedule payroll for these categories until a dedicated nomination/verification/award path exists. Monthly employee performance scoring/ranking/certificates remain deferred. Do not reopen closed salary-advance, attendance/payroll, i18n, or waiver work without regression evidence.


Migration follow-up: `eb92fa5a42d00c086da0fe6a439511178d8c149b` adds a metadata guard so ENUM conversion is skipped after the column is already VARCHAR. Still apply/test against local MariaDB 10.4 and inspect the exact DDL result.

## Current HR incentive policy checkpoint — 2026-10-12
The user confirmed existing Arabic and English category labels display correctly in the browser. `modules/hr/incentive_policy.php` on GitHub `main` now has an automatic, read-only category-code preview derived from the English category name; PHP generates the authoritative code and appends a numeric suffix on collision. Client-posted codes are ignored. Strict agreement timestamp validation and the 2,000-character notes limit are also included.

Latest commits: `23bdf2a84d81cd86f753c283e462f06f828aa510`, `1a1ced379bf5f4942cd44d554bbfbbfc2c499bbe`. User has not yet synced or runtime-tested these latest changes locally. First inspect local `git status` and current `main` before pulling; preserve all local edits. Then run PHP lint and verify preview behavior. No GM/FM reward amounts are agreed yet; test amounts may only be used in a clearly isolated rollback-only test, not in a live policy activation. Continue the incentive feature without reopening closed attendance/payroll, waiver, i18n, or salary-advance stages.


## Active continuation — 2026-10-13: Incentive policy GM approval

Continue with the existing repo and main only. GitHub source commits now implement the policy approval workflow, but local verification is still pending. Read docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md first. Preserve policy name/effective date; do not reintroduce agreement date/notes. Apply migrations only through the existing migration workflow; then PHP-lint and test the real local XAMPP/MariaDB application. Verify role authorization and the state path draft → pending_gm → approved → active, rejection reason/history, edit/resubmit, and activation safeguards. Do not claim runtime success before actual evidence.

## 2026-10-13 — Incentive policy custom-field concurrency guard

Commit `c53a5dd6b32f6a737f7d6264df7b033e3383cb45` updates `modules/hr/incentive_policy.php`: policy-management permission is checked before opening a transaction; custom-field creation locks the policy-version row with `SELECT ... FOR UPDATE`, requires the row still be a draft, inserts the field, then commits. This serializes the custom-field insert against submission, which locks the same policy row while validating required fields. Existing exception handling rolls back an open transaction.

The GM approval migration is applied locally and the schema was inspected. PHP syntax checks for the policy page and GM dashboard passed, and the policy page loads. Full approval/rejection/activation runtime verification remains pending because the local database has zero policy versions, rules, and custom fields. No concurrent two-process test has been run; do not claim it has been proven. Award issuance, source-event integration, payroll scheduling, and incentive accounting remain unfinished. Next: pull `main`, inspect the exact diff, lint the changed page, then perform safe local role/state verification without inventing business reward amounts.

## Required deferred task after incentive workflow acceptance

Once the current incentive-policy GM approval workflow is fully tested, start the cross-module payroll/accounting integration audit in `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`. Cover salary calculations/additions/deductions, attendance deductions, salary advances and repayment schedules/rescheduling, waivers/refunds/cancellations, implemented incentive award-to-payroll paths, accounting posting/journals, reversals, and reports. Use actual schema/source and documented accounting rules; independently recompute values and reconcile source ledgers to balanced journal entries. Test idempotency, duplicates, rollback, concurrency boundaries, effective dates/periods, rounding, and paid/posted immutability. Do not invent rules or reward amounts; use isolated rollback-only test data and report unsupported integrations as gaps. This task is deferred and must not interrupt the active incentive workflow.


## 2026-10-13 — Incentive policy role boundary clarified

The user confirmed this is a higher-management workflow involving only HR Manager, General Manager (GM), and Financial Manager (FM). `modules/hr/incentive_policy.php` now limits draft creation/editing, custom-field management, and category management to `hr_manager` and `financial_manager`; review/approval/rejection remains GM-only; activation is FM-only. `hr_staff` and `admin` are no longer authorized substitutes in this workflow. Authorization source change committed as `63534c5fd3aa5b0b3fc8bf640d3ecfb2280f314a`; UI alignment committed as `b065a93a98e1786a2e81702c0fa956b3a414f646`. The policy-creation form is now hidden from GM-only reviewers, and status guidance no longer names Admin as an activation actor.

**Verification boundary:** this is a source-level authorization correction only. Local PHP lint and real XAMPP/MariaDB role-based acceptance are still required. The three accounts previously identified are HR Manager user 32, GM user 2, and FM user 29, but their current login usability has not been established. Do not claim the workflow accepted until runtime tests pass. Do not use HR staff or Admin as test actors.

## 2026-10-14 — Incentive policy acceptance checkpoint

The user confirmed HR Manager, GM, and FM browser access boundaries. Before starting the controlled policy lifecycle test, the user noticed the “رمز الفئة (تلقائي)” field still visible in the category creation form. Commit `e4b0675dbed130a1a5235851df6dbdb4f342b524` removes that preview field and JavaScript; the server still generates category codes from the English name. User must pull `main`, lint `modules/hr/incentive_policy.php`, and verify the field is absent in the browser before continuing. No policy has yet been created as part of the lifecycle acceptance test.


## 2026-10-14 — Required UI feedback rule: toast, not top-of-page banners

This is a project-wide implementation rule, not an optional page-specific preference. Success, error, warning, and action-result informational feedback must be shown as temporary toast notifications through the centralized `AKNotify.toast` path, near the active workflow/modal when applicable. Do not leave action feedback as persistent banners at the top of a page. For legacy Bootstrap feedback nodes that need centralized conversion on a full-page load, add `data-ak-transient-alert="1"`; server flash nodes may use the established `data-ak-flash` convention. Keep field validation beside the relevant field, and keep genuinely persistent instructional/context panels separate from action feedback (mark them explicitly persistent if their markup matches transient-alert selectors).

**Required page-completion check:** inspect every success/error/warning/info message introduced or modified; verify both full-document POST/redirect loads and same-page/AJAX responses use toast behavior; confirm no action-result banner remains at the page top. Do not assume a page follows this rule merely because another module does. This rule applies to new pages and existing-page changes across the repository.


## 2026-10-14 — Incentive policy toast and English-language coverage update

Source-level review and correction completed on `main`:
- All eight successful incentive-policy actions now obtain their feedback from the stable incentive translation catalog and render through the page's transient-alert markers; the centralized `assets/js/notifications.js` path converts success/error feedback to toast notifications on normal page loads and observes alerts inserted during same-page/AJAX updates.
- Added matching Arabic and English stable catalogs `lang/incentive_policy_ar.php` and `lang/incentive_policy_en.php` with 135 matching keys covering page labels, status/history terms, confirmations, validation messages, and action feedback.
- Localized field-type and Yes/No option labels server-side because the language bridge intentionally does not translate native `option` text nodes; category names, custom-field labels, and currency names now use their English value when English mode is active, falling back to Arabic only when an English custom-field label was not supplied.
- Dynamic validation messages use translated prefixes and localized category/custom-field labels. User-entered policy names, custom-field values, rejection reasons, and other business data remain unchanged rather than being guessed/transliterated.

Repository source checks confirmed both catalogs contain 135 unique keys with no key mismatch, every `t('incentives.*')` key referenced by the module exists in both catalogs, all eight success feedback calls use the catalog, and both success/error alert nodes have `data-ak-transient-alert="1"`. This is **source-level verification only**. Local PHP lint, `php tools\\i18n_gap.php`, and browser checks in Arabic and English—including one successful action toast and one validation-error toast—remain required before marking runtime acceptance complete. Preserve the existing V1 test policy; do not create another policy solely to test notifications.


## 2026-10-14 — Incentive policy review-page presentation follow-up

The user confirmed the Arabic/English language toggle works and supplied local PHP lint results: `modules/hr/incentive_policy.php`, `lang/incentive_policy_ar.php`, and `lang/incentive_policy_en.php` all pass syntax checks. The official `tools/i18n_gap.php` scan reports 85 untranslated strings across 12 other files; `modules/hr/incentive_policy.php` is not listed, so unrelated files remain out of scope. The existing GM view exposed two narrow presentation issues: the system-currency notice was hard-coded in Arabic, and policy category/amount summaries had no visual separation. Commit `e446adee1ea9ae4e76bc5b48ad227926fec442c2` routes the currency notice through `t('incentives.currency_notice')` and styles each category/amount summary as a separated bordered item. This source correction is committed to `main`; local PHP lint and browser confirmation of the updated presentation remain pending. The Arabic creator name is stored user data and was intentionally preserved. V1 remains a draft; do not submit, approve, or activate until the toast acceptance checks and updated presentation are confirmed.


## 2026-10-14 — Incentive policy fallback/access checkpoint

GM rejection returns the version to HR as an editable draft. If HR does not submit a replacement or FM does not activate an approved replacement, the prior active policy remains in force. Existing source retires the prior active version only during successful FM activation; payroll payday effective-policy selection still needs inspection/runtime proof.

Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes the page gate so FM can reach activation. Success/error feedback uses transient-alert markers for toast conversion. Next: pull main, PHP-lint the page, test HR/GM/FM permissions, verify notifications and toast behavior, and inspect/test payroll's effective-policy lookup. Keep V1 inactive.

## 2026-10-14 — Hide unapproved incentive policies from FM

Commit `1ab2eac4173dc5f4f808865fc10c722058210bb3` changes `modules/hr/incentive_policy.php` so the FM policy list includes only `approved`, `active`, and `retired` versions. Drafts and `pending_gm` submissions are not disclosed to FM before GM approval; HR and GM retain the workflow views they need. This is a source-level correction only; local PHP lint and FM/HR/GM browser verification remain pending.

The existing V1 acceptance policy is still a draft and must remain unactivated. Do not delete it directly with SQL: its approval history and child records are acceptance evidence and may have referential constraints. Reuse V1 for HR edit/submit → GM reject with reason → HR revise/resubmit → GM approve. FM notification is expected at the GM approval handoff, not while the policy remains a draft. Verify actual recipient-role lookup and the persisted notification after approval before declaring delivery fixed. Payroll effective-policy selection remains a separate unverified gate.


## 2026-10-14 — Hide unsubmitted incentive-policy drafts from GM

Source audit found that `modules/hr/incentive_policy.php` filtered the policy list for FM only. Because the GM branch fell through to an unfiltered query, the GM could see HR drafts before submission. Commit `9a309f299c20aab963b5744eeee714bb5b2548b9` now applies explicit role-based visibility: HR sees all policy versions; GM sees `pending_gm`, `approved`, `active`, and `retired` versions but not `draft`; FM sees only `approved`, `active`, and `retired`. A GM-rejected version returned to `draft` is therefore hidden from GM again while remaining editable by HR. This changes list visibility only; it does not alter policy status, approval history, notifications, activation, or the prior-active-policy fallback.

Verification boundary: source change is committed to `main`; local PHP lint and browser acceptance are still required. Verify that the current draft is visible to HR but absent from GM and FM; then verify after HR submits it that it appears to GM as pending approval and remains hidden from FM. Keep the test policy inactive.

## 2026-10-14 — GM approval feedback simplified for clean acceptance test

Per the user's explicit direction, commit `0df1890e12a006cb1cae57c1bf906047178a39fd` removes the GM approval confirmation dialog and success toast from the incentive-policy approval action only. Authorization, transactional status/history recording, HR/FM notifications, and other workflow feedback remain unchanged. Approval success is to be checked through persisted status/history and notifications. Local lint/browser verification remains pending; the test policy cleanup was verified with no matching policy/notification rows and zero related rules, history, custom fields, or events. Do not recreate the policy until the code change is pulled and verified.

## 2026-10-14 — Incentive policy page: remove redundant currency/category notice

Commit `fa733524d67411e08d299c84e7568c79b601316a` removes the explanatory alert above the category section in `modules/hr/incentive_policy.php`. The application currency remains system-configured and continues to be stored with each policy rule; only the page-level notice was removed. The category management heading, category catalogue, and policy rule amount labels remain unchanged. Source change is committed on `main`; local pull, PHP lint, and browser verification are pending. This UI cleanup does not establish that the entire incentive lifecycle or incentive-to-payroll/accounting implementation is complete.


## 2026-10-14 — Active continuation: retest GM approval feedback

Latest source checkpoint: GM approval on R3 persisted, but the user observed no confirmation prompt or success toast. The current source fix restores both, uses bilingual confirmation text, and marks only the GM approval form `data-ak-native-post="1"` to avoid the window-capture same-page POST handler sending the request before the central confirmation listener. A guarded test-only reset is provided in `tools/reset_incentive_policy_gm_approval_acceptance.sql`; it targets policy ID 3 only when the exact R3 name/creator/version/status and not-activated guard match. Pull `main`, inspect the diff, lint the three changed PHP files, run the reset utility on local `ahl_el_kheir`, then test GM cancel and confirm paths, toast, persisted approval history, and HR/FM notifications. Keep R3 inactive. Do not claim runtime verification before evidence. Do not change shared JavaScript or unrelated modules without new causal evidence.


## Current continuation point — 2026-10-14: Incentive GM approval timestamp retest

The local browser confirmed GM approval confirmation and success toast. R3 was approved but remains inactive. HR/FM handoff notifications displayed an older timestamp than the approval-history row because stale notifications survived the test reset; the notification helper can suppress an existing unread duplicate without updating `created_at`. The reset's Arabic title comparison was encoding-fragile along the local PowerShell/CLI path. Stored title bytes are valid UTF-8; no production notification insertion defect is established, so do not change the production notification workflow without new evidence.

Commit `75484be186fdf7b935e27151e07604456b2cfa76` makes the test-only reset encoding-independent and narrowly scoped to the exact R3 policy, HR/FM recipients 29/32, the R3 body and policy link. It preserves GM recipient 2's original submission notification and does not activate the policy.

Next: in `D:\xampp\htdocs\AhlElKheir`, sync `main` without overwriting local changes, inspect the reset SQL, run it only against `ahl_el_kheir`, verify the policy is `pending_gm` and inactive and old HR/FM R3 handoff rows are gone, then perform one GM approval and compare new HR/FM notification timestamps to approval history. Confirm dialog cancellation remains non-mutating. Test data will be removed before go-live. Do not touch `ahl_el_kheir_production_test` or reopen unrelated closed work.


## Current continuation point — 2026-10-14: GM incentive approval handoff timestamp retest passed

The user completed a fresh browser/database acceptance of the confirmed-approval path for policy R3. GM confirmation dialog and success toast appeared. Approval history row 8 records GM user 2 at `2026-10-14 13:54:58`; HR notification 175 (recipient 32) and FM notification 176 (recipient 29) have the same timestamp. Policy 3 is `approved`, with `activated_by_user_id` and `activated_at` NULL. The prior discrepancy was caused by stale HR/FM test notifications surviving an encoding-fragile Arabic-title predicate in the test reset; commit `75484be186fdf7b935e27151e07604456b2cfa76` corrected the reset to use guarded R3 body/link and recipient IDs. Fresh timestamps now align. No production notification-writer defect was demonstrated; do not change that production code based on this issue.

Next: preserve the verified result in the relevant continuation/audit documents. Do not activate R3 or touch `ahl_el_kheir_production_test`. The confirmation-cancel outcome was not explicitly reported and remains unverified. Broader incentive acceptance still includes GM rejection with reason, HR revision/resubmission, FM activation and prior-active-policy fallback, and payroll effective-policy selection. Do not claim the whole incentive lifecycle is complete based on the approval-handoff result alone.


## Current continuation point — 2026-10-14: FM activation confirmation accepted; Cancel test pending

The user tested policy 3, `ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3`, as FM. The first attempt executed despite the confirmation prompt disappearing: status `active`, actor 29, timestamp `2026-10-14 14:12:23`. Source root cause was global same-page POST handling at window capture running before document-level confirmation handling. Commit `06b08d44afc48887b74a4588f1604e11f5d5e809` switches the FM form to translated `data-confirm` and `data-ak-native-post="1"`.

Local PHP lint passed. Following a guarded reset, the user confirmed the prompt and observed the activation-success toast; database now records R3 as `active`, actor 29, timestamp `2026-10-14 14:18:10`. Reset utility `tools/reset_incentive_policy_fm_activation_acceptance.sql` was updated in commit `a94080af39f82bcdaa56190c6c8eca264934d9f3` so both exact timestamp guards match this latest action. Relevant incentive continuation evidence is in commit `c0ed9d4d1c6bf680930dc40d3fc2ed58909b5bf0`.

Next: pull `main`; inspect and execute the guarded reset only against local `ahl_el_kheir`; verify R3 is `approved` and `activated_by_user_id`/`activated_at` are NULL; then as FM click Activate and choose Cancel. Expected: no activation-success toast and database remains approved/inactive. Do not repeat the confirmed activation path without regression evidence, do not create another policy, and do not touch `ahl_el_kheir_production_test`. Payroll effective-policy selection and award/payroll integration remain unimplemented/unverified.


## Current continuation point — 2026-10-14: GM rejection/recovery and approval notifications verified

The R3 acceptance policy (ID 3, `ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-14-R3`) completed the actual UI sequence GM rejection with reason → HR edit/resubmission → GM reapproval. HR user 32 received the rejection notification at 14:36:36; HR resubmitted at 14:38:35 (approval-history row 10, actor 32) and GM received the submission notification. GM approved at 14:40:49 (history row 11, actor 2). The final database query confirmed HR notification 181 and FM notification 182 were both created at 14:40:49 and refer to R3. Policy is `approved` and remains inactive; activation fields are NULL.

Important reset limitation: the guarded reset used for this retest removed the prior approval-history row and previous HR/FM handoff notifications while preserving submission history and the GM submission notification. Do not call it a full rollback-only reset. Earlier FM activation confirmation/acceptance and Cancel-branch testing are already closed; do not repeat absent regression evidence. No production notification-writer defect was demonstrated.

Next open engineering unit: inspect current source, schema, migrations, and relevant docs to design/implement incentive-award qualification and lifecycle, next-open-payroll inclusion, effective-policy selection/fallback, currency and net-pay calculation, paid-payroll immutability, and accounting treatment. Award issuance/payroll integration is not implemented or runtime-verified. Do not activate R3, change test financial values, or touch `ahl_el_kheir_production_test`. Continue on `main` only and make the smallest evidence-supported change.


## 2026-10-14 — Current incentive accounting continuation

The latest source-only milestone is dedicated Employee Incentives Payable account migration `database/migrations/2026-10-14_hr_employee_incentives_payable_account.sql`, commit `a281b8cd695e1555dcec86b7c900b1bf691f1562`. It provisions `2400` as a separate liability alongside existing `5500` Employee Incentives Expense. Documentation checkpoint commit: `f60545b6d70597875324aa48abe71c5cd1b8736d`. Neither commit proves the migration has been applied locally.

Next: user applies migration to local `ahl_el_kheir` and verifies `2400` exists, is active, and has account_type `liability`. Then continue implementation from actual `main` source/schema: separate award recognition and settlement journal IDs; explicit payroll incentive component/linkage; preserve `5200` salary expense, `5500` incentive expense, `2400` incentive payable, and `1410` salary-advance recovery; integrate all draft generation/refresh and paid-accounting paths; build rollback-only tests and verify with local PHP 8.2/MariaDB. Do not activate R3, rerun closed acceptance tests, or touch `ahl_el_kheir_production_test`. Never claim local runtime success without the user's actual evidence.


## 2026-10-14 — Incentive schema implementation checkpoint

In addition to the dedicated liability account migration (`a281b8cd695e1555dcec86b7c900b1bf691f1562`), schema migration `database/migrations/2026-10-14_hr_employee_incentives_payroll_accounting.sql` is committed as `211f1e033cf488f1a95e5d76956b4371183d6463`. It adds `payroll.incentive_amount` and splits the award journal pointer into recognition and settlement journal references. These are schema-only commits and are not evidence of local migration application or completed PHP integration.

Next: inspect both dated migrations against the local schema, apply them only to `ahl_el_kheir`, verify account `2400` and the new columns/indexes, then continue implementing the award lifecycle and payroll integration in procedural PHP. Preserve salary expense `5200`, incentive expense `5500`, incentive payable `2400`, and salary-advance receivable `1410`; keep incentive payout separate from `net_salary`. Do not activate R3 or touch `ahl_el_kheir_production_test`.


## 2026-10-14 — Incentive accounting integration in progress

The user applied both schema migrations locally and verified account `2400`, `5500`, payroll `incentive_amount`, and the award recognition/settlement journal columns. Latest source slice on `main` adds `modules/hr/lib_employee_incentives.php`, integrates incentive settlement in `modules/hr/lib_payroll_accounting.php`, and refreshes/displays the component in `modules/hr/payroll.php`. It is a partial, unverified implementation: source-event hooks, manager verification/award creation, employee notifications, PHP lint, and rollback-only runtime tests are still open.

Next exact step: pull `main`; run `php -l modules/hr/lib_employee_incentives.php`, `php -l modules/hr/lib_payroll_accounting.php`, and `php -l modules/hr/payroll.php`; report exact output. Fix lint/source issues first. Then continue with manager-verification/award creation and confirmed source-event hooks without activating R3 or touching `ahl_el_kheir_production_test`.


## 2026-10-14 — Exact next step: incentive accounting rollback gate

The rollback-only harness `tools/run_hr_employee_incentive_accounting_rollback_tests.php` is committed on `main` (commits `68a005d9`, `5cb05574`), but has **not** been linted/executed locally. First pull `main`, then run:

```powershell
& "D:\xampp\php\php.exe" -l "tools\run_hr_employee_incentive_accounting_rollback_tests.php"
& "D:\xampp\php\php.exe" "tools\run_hr_employee_incentive_accounting_rollback_tests.php"
```

Run only against local `ahl_el_kheir`; never use `ahl_el_kheir_production_test`. Report exact output. SKIP is not PASS; diagnose its stated missing fixture without creating or activating a policy. On FAIL, stop and inspect the exact root cause before editing. The harness checks 5500/2400 recognition, idempotency, caller-owned currency hold, draft scheduling, settlement with 5200 salary and 1410 recovery preserved, paid-payroll refresh immutability, and cleanup. Do not add event-boundary hooks until this gate passes and the MAX(JE suffix)+1 journal-code collision risk has been assessed. Keep R3 approved/inactive.


## 2026-10-14 — Accounting rollback gate passed; next step

The user-provided local result confirms `tools/run_hr_employee_incentive_accounting_rollback_tests.php` passed after PHP lint. Verified: recognition Dr 5500 / Cr 2400 for SDG 123.45; duplicate protection; caller-owned currency hold; draft scheduling/repeat refresh; balanced payroll settlement preserving 5200 salary expense and 1410 salary-advance recovery; award settlement linkage; rollback cleanup. This is not full lifecycle proof. Next inspect existing journal-code allocation and make incentive recognition collision-safe before implementing source-event hooks. Then continue manager verification/award creation, confirmed source-event boundaries, post-commit notifications, and rollback-only tests. Keep policy R3 approved/inactive and `ahl_el_kheir_production_test` untouched.


## 2026-10-14 — Next gate after journal-code retry hardening

The accounting rollback harness passed locally before the latest source change. Commit `143aec005e916b9d9a5b1923b82b16b16ca9b447` adds bounded retry for duplicate JE entry-code collisions in incentive recognition. Pull `main`, run PHP lint on `modules/hr/lib_employee_incentives.php`, then rerun `tools/run_hr_employee_incentive_accounting_rollback_tests.php` against local `ahl_el_kheir`. This is required before proceeding to source-event hooks. Never run against `ahl_el_kheir_production_test`; keep R3 approved/inactive. After the gate passes, inspect actual source/schema at confirmed collection, sponsor acquisition/reactivation, and project financial-closure boundaries before implementing the smallest justified event hooks, manager verification/award issuance, and post-commit notifications.


## 2026-10-14 — Active task: reactivation payment qualification local acceptance

The win-back transition itself passed local rollback-only acceptance. The reactivation qualification helper, recipient-snapshot migration, and rollback harness are committed to `main`, but production event wiring is intentionally withheld and local acceptance is paused until the actual reactivating employee is distinguished from the user who confirms the return.

1. Resolve one business decision first: does the user who clicks “confirm return” count as the employee who reactivated the sponsor, or must the form require selecting the actual reactivating employee and store the confirmer separately?
2. Do not apply `2026-10-14_hr_incentive_event_recipient_snapshot.sql` or run the new reactivation harness until the decision is resolved and source wiring is finalized.
3. Then pull `main`, apply the migration once to `ahl_el_kheir` only after checking column state, PHP-lint the incentive helper, FM review queue, and both source-event harnesses, and run the harnesses.

The helper checks the first financially confirmed monthly-sponsorship payment after a durable win-back transition, a prior confirmed monthly-sponsorship payment at least 90 days before that transition, and a posted linked transaction/journal. It snapshots the selected/recorded recipient and leaves the event pending HR Manager verification. No award is issued by the helper. Production reactivation-event wiring is currently withheld to prevent the confirmation user from being mistaken for the actual reactivating employee. Keep policy R3 inactive, do not use `ahl_el_kheir_production_test`, and do not reopen closed work without regression evidence.


## 2026-10-14 — Win-back reactivation attribution passed local rollback-only acceptance

The user pulled commit `958a4644160071ea573db15f35e92f0f2d738c9c`, applied `database/migrations/2026-10-14_winback_reactivation_attribution.sql` to local `ahl_el_kheir`, and ran PHP 8.2 lint on `modules/administration/lib_winback.php`, `modules/administration/winback.php`, and `tools/run_winback_reactivation_rollback_tests.php`; all three reported no syntax errors. The rollback-only harness reported:

- `PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked`
- `PASS | rollback_cleanup=PASS | campaign=3 | sponsor=1`

This proves the harnessed atomic sponsor/campaign transition, durable actor/timestamp, blocked repeated transition, and rollback cleanup on the local database. It does not prove concurrent two-process execution or the full incentive lifecycle. Next work remains the reactivation source-event qualification at the financially confirmed payment boundary, using confirmed payment history to prove the 90-day lapse and durable reactivation actor, with idempotency and HR Manager verification. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation payment qualification committed; local acceptance pending

The confirmed-payment hook now checks a posted linked transaction and posted journal, a durable successful win-back transition, a previous confirmed monthly-sponsorship payment at least 90 days before the transition, and the first confirmed monthly-sponsorship payment created after the transition. The event remains `pending_verification`; it does not issue an award. Recipient user and employee IDs are snapshotted on source events so the win-back actor is distinct from the payment reviewer. The collection hook also snapshots the collector's user/employee relationship. Missing or ambiguous employee mappings produce no event. Same-second competing payment confirmations are conservatively held rather than risking duplicate awards.

New migration: `database/migrations/2026-10-14_hr_incentive_event_recipient_snapshot.sql`. New rollback-only harness: `tools/run_hr_employee_incentive_reactivation_rollback_tests.php`. The existing collection source-event harness now checks recipient snapshots. **Local acceptance is pending**: apply the recipient-snapshot migration once to `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and both incentive source-event harnesses, then run both harnesses. No local runtime result is claimed for this new slice. HR Manager verification/award issuance, employee notifications, new-sponsor/project hooks, and end-to-end lifecycle testing remain open. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation actor decision implemented; local acceptance pending

The actual reactivating employee is explicitly selected in the win-back UI and server-validated as a uniquely mapped, currently working employee. The logged-in confirmer is stored independently in `reactivation_confirmed_by_user_id`; `handled_by` remains operational ownership. Legacy campaigns without the new confirmer snapshot are excluded from reactivation incentive qualification; the system does not assume their earlier actor attribution was correct. The FM confirmed-payment transaction now calls `hrIncentiveRecordReactivationPaymentEvent()`, which requires posted financial evidence and a ≥90-day prior-payment lapse and records only a pending-verification event.

Next local gate after pulling main: inspect whether the columns already exist; apply `database/migrations/2026-10-14_winback_reactivation_confirmer.sql` and `database/migrations/2026-10-14_hr_incentive_event_recipient_snapshot.sql` only if their columns are absent; lint the win-back helper/page, FM review queue, and three relevant rollback-only harnesses; run all three harnesses. Use only `ahl_el_kheir`, keep policy R3 inactive, and do not use `ahl_el_kheir_production_test`. No runtime acceptance is claimed until the user reports exact local output.


## 2026-10-14 — Active local gate: new-sponsor source-event hook

Pull `main`, then PHP-lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php`. Run `php tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php`, then rerun the existing source-event and reactivation rollback harnesses. Apply no migrations for this slice; the recipient-snapshot and win-back confirmer migrations were already applied per user-provided local schema output. Report exact results. Do not use `ahl_el_kheir_production_test` or activate policy R3. If the new harness SKIPs, report the exact prerequisite and inspect it before changing the harness. After this gate, proceed to the project-completion source/allocation and HR Manager verification/award-issuance design; notifications must remain post-commit and failure-isolated.

## 2026-10-14 — Current HR incentive gate

Continue from current `main`; do not repeat completed source-event/accounting acceptance. HR Manager verification/award page is committed but local PHP lint and rollback-only runtime acceptance are pending. Next check only:
- lint `modules/hr/incentive_verification.php` and `dashboard/hr_dashboard.php`;
- run a targeted rollback-only verification/award test against local `ahl_el_kheir`;
- then implement/verify employee notifications when an award is scheduled for an eligible payroll.
Project-completion awards remain blocked until approved recipient allocation is implemented. Keep policy R3 inactive; never use `ahl_el_kheir_production_test`. Follow `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.


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



### 2026-10-15 — Next local gate: audited incentive-policy approval withdrawal

Current source on `main` adds a GM-only action to return an approved-but-unactivated policy to HR draft mode. Reason is required, approval history action is `returned_to_draft`, HR creator and FM are notified after commit, and activation evidence prevents reversal. Migration: `database/migrations/2026-10-15_hr_incentive_policy_approval_withdrawal.sql`. Commits: `cb522dcee4b2e78e03f8d4c97402a61ca7e23d6c` and `a9501f5f41e4f959a734d175cf14523c49c1e6d2`. **Pending local verification**: pull `main`; inspect/apply the migration to `ahl_el_kheir` only if the new enum value is absent; PHP-lint `modules/hr/incentive_policy.php`; browser-test as GM using a reason on R3; confirm R3 returns to draft with history and notifications; sign in as HR and edit all four category toggles/amounts. Do not change status with SQL. R3 effective date must be edited from its past date to a valid date, and the effective date must respect the later R4 policy's chronology. Do not touch `ahl_el_kheir_production_test` or rerun closed incentive harnesses without regression evidence.


## Latest verified checkpoint — 2026-10-15
Employee incentive in-app notification UX is browser-verified: the user confirmed live delivery, Arabic notification history rendered correctly for the controlled UTF-8 test row, and cleanup confirmed row 195 deleted, zero matching test rows, and zero notifications/unread for recipient user 16. This closes that UX gate only. Do not repeat completed notification-outbox or other incentive rollback harnesses without regression evidence. Remaining open items include HR Manager browser/session authorization and project-completion recipient allocation/verification. The latest pending local workflow checkpoint is GM-only audited return-to-draft for an approved, never-activated incentive policy, followed by HR editing the four categories via the UI. Follow the detailed next-step instructions in `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`; keep `ahl_el_kheir_production_test` untouched.


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


### Follow-up — project closure rollback harness added; local acceptance pending

Added `tools/run_hr_project_incentive_closure_rollback_tests.php` (commit `df40dc1c`). It exercises the automatic project allocation/award path inside an outer transaction and rolls back its fixture, journals, and payroll changes. The processor supports caller-owned transactions (commit `2bff519b`). A missing migration or safe project fixture results in `SKIP`, not PASS.

Next: pull `main`, inspect/apply `2026-10-15_hr_incentive_project_auto_allocation.sql` only if needed, lint the four changed PHP source files plus the harness with local PHP 8.2, and run only the new harness. Local lint/runtime acceptance remains pending. Keep active R5 unchanged; never use `ahl_el_kheir_production_test`.


### Follow-up — positive-balance rollback coverage without accounting fabrication (2026-10-15)

The current disposable DB has no eligible closed project with zero controlled balance. Project 10 has 260,000 SDG in posted allocations and 30,000 SDG controlled balance, so do not insert a synthetic return or mutate posted accounting records. The rollback harness now permits an eligible closed positive-balance project to test recipient snapshotting, absence of human approval metadata, rejection of award issuance while funds remain controlled, and transaction rollback. The successful award/recognition branch remains SKIP until a genuine zero-balance fixture exists. Pull main, lint the harness, and execute it only with AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1. Do not touch live ahl_el_kheir, project 7, or active R5. Do not claim award-recognition success from the positive-balance test.


### 2026-10-15 — Project incentive harness SQL failure: root cause and correction

The first local execution of `tools/run_hr_project_incentive_closure_rollback_tests.php` failed with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'currency_code' in 'field list'`. Source inspection isolated the invalid reference to `project_lifecycle.currency_code` in `hrIncentivePrepareProjectCompletion()`; project lifecycle does not expose a source currency field. Commit `920d4221122a65a8579e4bed5ef289e64a4934db` removes that column from the query and leaves `hr_incentive_events.source_currency_code` NULL for this non-monetary source event. The award currency remains sourced from the active policy rule and stored on the recipient allocation/award. This correction has not yet been locally linted or runtime-tested. Next: pull `main`, lint `modules/hr/lib_employee_incentives.php` and the project incentive rollback harness, then rerun only the rollback harness against `ahl_el_kheir_incentive_test_20261015_r1`. Do not alter active R5, live accounting, or fabricate funding returns. A later SQL failure must be diagnosed against the actual schema before further edits.


### Local verification result — project incentive positive-balance rollback harness (2026-10-15)

User ran the harness on local PHP 8.2 against disposable database `ahl_el_kheir_incentive_test_20261015_r1` after pulling the source correction. Both `php -l modules/hr/lib_employee_incentives.php` and `php -l tools/run_hr_project_incentive_closure_rollback_tests.php` passed. Runtime output:

- `PASS | project_team_snapshot=1 | no_approval_metadata=PASS | positive_balance_award_block=PASS`
- `PASS | rollback_cleanup=PASS | project=10 | controlled_balance=30000.00`
- `SKIP | award-recognition success branch | No existing eligible closed project has a zero controlled balance; no accounting records were fabricated or altered.`

This verifies the positive-controlled-balance block and transactional cleanup in the disposable DB only. It does **not** verify successful award creation or recognition journals, nor browser/runtime behavior on a real project. The success branch remains open pending a legitimate eligible closed project with controlled balance <= 0.009. Do not fabricate funding returns or edit posted accounting records to create one. Active policy R5 and live accounting were not used or changed by this test.

## 2026-10-15 — Employee incentive project-success test continuation

New main commit: `5b6fb97d975bdc3e591fa811ca55b2a214092bfc`, adding `tools/run_hr_project_incentive_success_rollback_tests.php`. This replaces the unsafe need to find or alter a real closed project's financial balance with a synthetic no-financial-activity project fixture, created and rolled back within one transaction on only `ahl_el_kheir_incentive_test_20261015_r1`. It refuses to run on any other database and asserts the active policy is the protected R5 acceptance policy. The eligible Project Supervisor is added to the team before simulated closure. It verifies the missing-FM-audit block, the successful full-policy award and Dr 5500 / Cr 2400 recognition journal, and rollback cleanup. **Next exact gate:** pull `main`, run PHP lint on the new harness, then execute it against the disposable test DB only. Do not rerun the already-passed negative harness without regression evidence, do not change R5, and do not touch the live database.

## 2026-10-15 — Project incentive award recognition gate CLOSED

User pulled main and locally verified `tools/run_hr_project_incentive_success_rollback_tests.php`. PHP lint and runtime PASS. Result:
`PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS`
`PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS`
`PASS | rollback_cleanup=PASS | policy=R5 | reward=1500.00 SDG`

The harness was restricted to `ahl_el_kheir_incentive_test_20261015_r1`, checked the protected R5 policy without changing it, snapshotted an eligible Project Supervisor before simulated closure, verified the FM financial-closure gate, checked the full Dr 5500 / Cr 2400 recognition journal, and rolled back all test data. Do not rerun absent regression evidence.

**Next:** inspect current incentive source and existing test harnesses to select the next genuinely open acceptance gate, likely payroll inclusion/refresh and employee notification delivery. Do not assume these are already covered by the recognition harness. Real browser PM/FM closure workflow remains unverified and must be considered separately. Never test against the live DB or modify posted financial records.


## 2026-10-16 active task — FM sponsor-payment approval integration

The approval operation is extracted into `modules/accounting/lib_sponsor_payment_approval.php`; its prepared accounting helpers must remain DDL-free, and production approval and harness call the same operation. Local verification is pending. Pull `main`, lint `modules/accounting/lib_admin_fee_policy.php`, `modules/accounting/lib.php`, `modules/accounting/fm_review_queue.php`, `modules/accounting/lib_sponsor_payment_approval.php`, and `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php`. Run only the new harness against `ahl_el_kheir_incentive_test_20261015_r1`; it must refuse the live database. Do not alter R5 or rerun closed tests.


## 2026-10-16 continuation update — FM integration passed; project payroll test pending

FM sponsorship approval integration is runtime-verified by user output from `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php` on `ahl_el_kheir_incentive_test_20261015_r1`. PHP 8.2 lint passed for all five relevant files. Output proves the shared FM path, posted transaction/journal, pending new-sponsor event without premature award, successful rollback, and rollback after injected event failure. Do not repeat this closed test absent regression evidence.

New test `tools/run_hr_project_incentive_payroll_rollback_tests.php` has been added to test project-completion award linkage to the current/future draft payroll, incentive component total, unchanged salary net, outbox row, and rollback restoration. The workflow lints this test. Local lint/runtime are pending. Pull `main` and run only this new harness against `ahl_el_kheir_incentive_test_20261015_r1`. Keep R5 unchanged; never use live `ahl_el_kheir` for rollback tests. The actual PM-to-FM browser workflow remains a separate open gate.


## 2026-10-16 continuation — Project incentive payroll harness now creates a rollback-only payroll fixture

The new project payroll test skipped because no current/future draft payroll existed for the selected Project Supervisor. User read-only diagnostics confirmed eligible salary history (10,000 SDG effective 2026-10-05), working employment category, no October payroll rows, and payroll policy version 1 effective 2026-10-01. Source commit `797f89afe848df39fee26a6f16bae6f2854c3c03` updates `tools/run_hr_project_incentive_payroll_rollback_tests.php` to create a temporary current-period draft inside its existing transaction when needed, using the production payroll INSERT shape and actual payroll-policy helper. It blocks existing payroll rows and verified unscheduled awards, and checks the temporary payroll is removed by rollback. **Next exact gate:** pull `main`, run `php -l tools\run_hr_project_incentive_payroll_rollback_tests.php`, then run only `php tools\run_hr_project_incentive_payroll_rollback_tests.php` while the configured database is exactly `ahl_el_kheir_incentive_test_20261015_r1`. Runtime output is pending. Do not modify active R5, use live `ahl_el_kheir`, or rerun closed tests.
## Latest verified checkpoint — 2026-10-16 project incentive payroll gate

The user pulled `main`, PHP 8.2-linted `tools/run_hr_project_incentive_payroll_rollback_tests.php`, and ran it against the disposable incentive test database. Reported results all PASS:

```
PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS
PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS
PASS | project_award_draft_payroll_link=PASS | incentive_total=PASS | net_salary_unchanged=PASS | outbox_pending=PASS
PASS | rollback_cleanup=PASS | temporary_payroll_rolled_back=PASS | policy=R5 | reward=1500.00 SDG
```

The project-completion incentive-to-draft-payroll rollback-harness gate is CLOSED. Do not rerun it absent regression evidence. R5 remains unchanged. This does not prove employee notification delivery or the real PM-to-FM browser closure workflow; those remain separate open acceptance gates. Continue by inspecting current source/docs and target only the next genuinely open gate.

## 2026-10-16 — Project incentive financial-closure draft-allocation safety guard (processor rollback gate passed; FM closure-path verification pending)

A source audit found that the controlled balance intentionally counts posted funding allocations only, but FM financial closure could still be recorded while a draft allocation remained. The same gap could allow the project-completion incentive processor to proceed if a caller supplied a zero balance and durable closure audit evidence existed.

The targeted correction adds a reusable draft-allocation predicate, blocks explicit FM financial closure while any draft allocation remains, and lets a valid funding return complete while deferring automatic financial closure/PM completion notification when a draft allocation remains. The incentive processor independently locks/checks for a draft allocation before award or recognition-journal processing and preserves caller-owned transaction semantics.

Added rollback-only regression harness: `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. It is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, requires the protected active R5 project-completion policy, reuses only an existing closed zero-posted-balance fixture with a draft allocation and pre-existing recipient snapshot, inserts temporary FM closure audit evidence inside a transaction, and rolls back. It does not change funding allocations, payment evidence, posted accounting, payroll, or R5.

**Verification evidence:** PHP 8.2 lint passed for `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, `modules/hr/lib_employee_incentives.php`, and the rollback harness before the latest testability refinement. The rollback-only harness first refused live `ahl_el_kheir`, then passed against `ahl_el_kheir_incentive_test_20261015_r1`:

`PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS`

`PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5`

This closes the **processor rollback-harness gate**. A follow-up now extracts the explicit FM financial-closure rejection into `akp_assert_no_draft_funding_allocations_for_financial_closure()` and adds an assertion for that same guard to the rollback-only harness. **That latest helper/harness change still requires PHP lint and a fresh runtime harness pass.** It tests the shared guard used by the FM handler; it does not prove the browser route itself or the automatic closure deferral after a funding return at runtime. Do not post a funding return or change project 7's draft allocation/payment evidence. After pulling `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`, then run the harness with process-only `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Keep the already-passed incentive, payroll, notification, salary-advance, and Projects Phase 5 gates closed unless regression evidence appears.


## Latest checkpoint — 2026-10-16: FM automatic closure decision harness refinement

Latest source now extracts `akp_project_financial_closure_outcome()` in `modules/projects/project_funding_accounting.php`; `modules/projects/view_fm.php` uses it after a funding return. The existing `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php` asserts all three outcomes and prints `automatic_closure_outcomes=PASS`. Local lint/runtime for this newest commit sequence is pending. Next, pull `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and the draft-funding harness, then run only that harness with process-scoped `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Still-open acceptance: real FM browser POST path and end-to-end employee notification delivery/experience. Never post a funding return or alter project 7's draft allocation/payment evidence. Keep R5 and previously closed tests untouched.


## 2026-10-16 — Latest employee incentives continuation checkpoint

The new-sponsor first-confirmed-payment rollback-only acceptance harness has now passed locally against `ahl_el_kheir_incentive_test_20261015_r1`. The user-reported exact result:

```text
SAFE_TARGET | ahl_el_kheir_incentive_test_20261015_r1
PASS | new_sponsor=first_confirmed_payment | recipient_snapshot=PASS
PASS | later_payment_rejected=PASS | no_award_before_HR_verification=PASS
PASS | rollback_cleanup=PASS | sponsor=2858 | employee=16
TEST_EXIT_CODE=0
```

Both configured and connected database guards matched the isolated database before the harness ran. Do not rerun this passed gate without regression evidence. Active R5 was not modified and live `ahl_el_kheir` was not the test target.

Current remaining distinct incentive acceptance gates: real-browser PM-to-FM project-closure workflow and end-to-end employee notification delivery/visibility. Do not infer either from the rollback harness or pending outbox evidence. Protect project 7's existing financial evidence and allocation; do not modify R5. Read `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for the detailed checkpoint and resume only from the actual remaining gate.

## 2026-10-17 continuation — employee incentive notification inbox acceptance passed

The user verified the incentive scheduling notification in the real local employee inbox while the notification outbox harness was paused against `ahl_el_kheir_incentive_test_20261015_r1`. The displayed Arabic title/body matched the expected 12.34 SDG incentive for payroll period 2026-09. Test notification ID 200 was visible in the browser. After releasing harness PID 4528, it exited; independent checks confirmed notification 200 was removed, payroll 21 restored to incentive_amount 0.00/status draft, original disposable user password hash exactly restored, `.htaccess` restored to its original content, clean Git status, and cleared test-only environment variables. Final harness console PASS lines were not captured, so document independent cleanup evidence rather than claiming a captured harness transcript.

The test row's link was `http://localhost/modules/notifications/index.php`. This was generated in CLI context: dispatcher uses `APP_URL`, while `config/config.php` derives APP_URL from web server request variables and document-root/script-name path detection; the CLI harness only sets REQUEST_METHOD. Treat the observed root URL as a test-context artifact unless a browser-originated action reproduces it. Do not change production URL configuration without reproduction. If refining the harness, explicitly seed representative `HTTP_HOST=localhost:8081`, `DOCUMENT_ROOT=D:/xampp/htdocs`, and a script name under `/AhlElKheir/` before config load, then assert the full app base path.

**Closed gate:** employee incentive notification delivery and visibility in the actual inbox. **Still open separately:** real-browser PM-to-FM project-closure workflow. Preserve R5 and all closed tests; continue from the real current open gate only.


### Latest verified milestone — 2026-10-17

The user PHP 8.2-linted `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php` with no syntax errors. Against `ahl_el_kheir_incentive_test_20261015_r1`, the rollback-only harness passed: explicit FM closure guard, automatic closure outcomes, draft-allocation processor block, unchanged awards and recognition journals, unchanged funding evidence, and rollback cleanup. Fixture project 7 and active policy R5 remain protected; no funding return was posted.

Next distinct open gate is the real-browser PM → FM project-closure workflow using the correct local app URL `http://localhost:8081/AhlElKheir/`. Inspect current docs, source, and actual safe fixture state before acting. Do not infer browser-route success from helper/harness tests. Never fabricate funding activity or alter project 7's financial evidence; if no safe test fixture is available, stop and report the blocker.

## Current exact continuation point — 2026-10-17 project-completion incentive browser test

The Supervisor already submitted the closure request for disposable test project `PRJ-0006` in `ahl_el_kheir_incentive_test_20261015_r1`. Read-only evidence: `created_by=33`, `approval_status=approved`, `fm_final_confirmation_count=0`, latest status `closure_requested`. The PM portfolio hid it because `akp_can_view_project()` did not admit the approved/under_review/latest-closure-request state.

Fixes committed on `main`:
- `4258ebb656a3229b84d5d7729868ce92a683f921` — narrow PM visibility for pending closure review.
- `f7f8bae9753694fd32b7f916443a1f2a8493a3c6` — Supervisor confirmation prompt.
- `ba5ed5cec327e45d396ecbfb333a6c6d27ea5637` — remove duplicate toast/scroll initialization after inspection found the existing shared implementation. The missing-toast/scroll symptom is not yet runtime-verified and must be diagnosed if it persists.

**Next:** pull main locally, lint `modules/projects/project_lib.php` and `modules/projects/view.php`, then browser-test as Supervisor that the confirmation can cancel without submitting and a confirmed request shows a toast without a viewport jump. Log in as PM and verify PRJ-0006 appears and opens with the pending closure-review panel. Do not resubmit the existing request. Continue normal PM administrative closure only if UI guards permit, then FM financial closure through the normal UI, and finally verify the project-completion incentive event/award/payroll/notification on the disposable DB. Do not use `ahl_el_kheir`, invent financial evidence, or bypass closure safeguards. Source fixes are not runtime-verified until those checks pass.


## 2026-10-17 — Latest exact continuation: project-completion browser acceptance after PM closure

The user ran read-only SELECTs against `ahl_el_kheir_incentive_test_20261015_r1`. Project 6 (`PRJ-0006`) is already administratively closed: status completed, lifecycle closed, closed by PM user 33 at `2026-10-17 08:27:50`, status-history row 22, `CLOSE` audit row 2479. Project-completion event 28 exists with `pending_verification` and the recipient-snapshot marker. The query found no `FM_FINANCIAL_CLOSURE` audit record and no award. Active R5/version 1 remains enabled at 1,500.00 SDG.

Root cause of the reported PM UI issue: the administrative closure form had no confirmation prompt; the handler redirected to the closed project, where PM access guard redirected away before showing the session toast. Commits `986cef5d21d0ae972a1ec6dd9f624a619a851b40` and `b5879cb2cc69be7f3a30301eb72b4570efb12497` add a confirmation and show the success toast on the Projects dashboard. These source changes are **not locally verified yet**.

Next steps: pull `main`; lint `modules/projects/view.php` and `dashboard/projects_dashboard.php`; use the real browser to verify the PM confirmation prompt on a safe eligible fixture if available (do not re-close project 6), and verify its success-toast route by source/runtime-safe means. Then resume FM financial closure for existing project 6 through the normal UI, after read-only inspection of its current funding/return/draft-allocation state. Do not bypass guards, fabricate evidence, rerun completed harnesses, change R5, or use live DB `ahl_el_kheir`.

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


## 2026-10-18 — Current continuation: immediate incentive activity notice acceptance

The user requires an employee-facing in-app notice immediately when a qualifying activity is recorded, without waiting for later FM/higher-management work or payroll background processing. Source implementation is committed on `main` for monthly sponsorship collection submission, active new-sponsor creation, reactivation, and PM project-team snapshot. These are provisional activity notices; they do not bypass manager verification, financial confirmation, final FM project closure, award recognition, or payroll/payment controls. Do not conflate this early notice with the later earned-notice.

Latest implementation commits: `77da80e0`, `895b4ba2`, `6ccd172a`, `a3b6130b`, `04418b2f`, `50c43398`; rollback harness assertion commit `0eb3457f`. Local PHP lint, disposable DB harness, and browser inbox acceptance are pending. Continue with exact-source review, then run only against existing disposable DB `ahl_el_kheir_incentive_test_20261015_r1`. Do not create a DB or mutate active R5/live payroll. Preserve local `.htaccess` and `storage/documents/projects/20/` changes; do not pull blindly.


Latest immediate-notice wording clarification commit: `c0316a4d47b708dedd37e74da8ac2d884b840dff`. The early message describes a recorded activity and expected incentive, not final earned/paid status.


## 2026-10-18 — Project incentive notice continuation

Current open work: verify the correction that employee project-completion notices wait for FM final financial closure. On `main`, commit `28578e3c9088fe8efdfccc671e1e2e7e7b99721f` removed the provisional notification from the PM closure snapshot; commit `2a1d7b05e899582e40a6a0e864159914cc09b8e7` updates the rollback-only harness. These are not yet locally linted/runtime-verified. Pull `main` without overwriting local changes, lint `modules/hr/lib_employee_incentives.php` and `tools/run_hr_project_incentive_payroll_rollback_tests.php`, then run the harness with `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Do not run FM final closure on PRJ-0024 until the harness passes; it is already administratively closed, so do not close/reopen it again. Keep R5 unchanged and do not use `ahl_el_kheir_production_test`.


## 2026-10-18 — FM closure acceptance next

Local PHP lint and the targeted rollback-only harness have now passed on `ahl_el_kheir_incentive_test_20261015_r1`. Result includes `project_notice_waits_for_FM=PASS`, `missing_FM_gate_blocks_award=PASS`, and `rollback_cleanup=PASS`; policy R5 remains at 1,500 SDG. Next acceptance boundary is the actual FM final financial closure of PRJ-0024, which is already administratively closed. Before confirming in the FM UI, verify PRJ-0024 identity, zero controlled balance, and no draft funding allocations. After closure, verify one earned notification per closure-time eligible recipient and no duplicate after retry/reload. Do not repeat PM closure, reopen the project, alter R5, manually create notifications, or change payroll/accounting records. If the live UI outcome differs from these preconditions, stop and report it.


## Latest continuation boundary — 2026-10-18 — incentive supervisor recipient regression

Active task: verify the project-completion incentive recipient snapshot correction. On disposable DB `ahl_el_kheir_incentive_test_20261015_r1`, closed PRJ-0024 had an active eligible supervisor assignment before closure, but event 34 omitted PS1 because `hrIncentivePrepareProjectCompletion()` queried only `project_team`. The creation form already requires a primary supervisor server-side; do not add duplicate validation. Source commit `dd5c072c34dcd40dd8232e8f5df3aeac9b748e30` adds the latest active primary supervisor to the recipient union; test commit `f2ff7bb7bd4b56011a45c03fc591a8aefbd43c82` creates a supervisor-only assignment. The integrated local `main` code has now passed PHP lint for both modified PHP files and the guarded rollback-only harness against `ahl_el_kheir_incentive_test_20261015_r1` (exit code 0). The harness output confirmed supervisor-only `PS_snapshot=PASS`, `missing_FM_gate_blocks_award=PASS`, `FM_gate=PASS`, `recognition_journal_5500_2400=PASS`, payroll award linkage, pending outbox, `project_notice_waits_for_FM=PASS`, and `rollback_cleanup=PASS`; policy R5/reward 1500.00 SDG remained unchanged. This proves the synthetic regression scenario only. PRJ-0024 remains historically uncorrected/unverified for PS1's award/notice. Do not rerun closure, modify PRJ-0024, manually insert notifications/awards, or change payroll/accounting. Review a safe, auditable historical recovery path separately.

## Active acceptance gate — immediate incentive activity notifications (2026-10-18)

Source review confirms helper call sites for monthly collection submission (`modules/transactions/create.php`), active new sponsor creation (`modules/sponsors/create.php`), and recorded reactivation (`modules/administration/winback.php`). New rollback-only harness `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php` was added in commits `69ac7eb1b7da82f7495ea12db71c59b2725c4540` and `bb292f02acfcce504b84e69c1f8179b13332fc63`. It asserts new notification row, intended employee recipient, expected reward context, provisional wording that does not claim final approval/payment, unread state, and rollback cleanup for all three types. **Next step: sync `main`, lint the harness and incentive helper, then execute the harness only against `ahl_el_kheir_incentive_test_20261015_r1`.** Runtime results are pending. The helper harness does not execute the actual collection/sponsor/reactivation POST routes, so report that limitation. Do not modify active R5, live payroll/accounting, or projects 6/7; do not reopen completed project investigations.

## 2026-10-18 — Immediate activity-notice helper acceptance passed

The user synced `main`; the working tree was clean before pull. Both PHP lint checks passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`. The rollback-only harness against `ahl_el_kheir_incentive_test_20261015_r1` passed with exact results:

```text
PASS | immediate_notice=collection,new_sponsor,reactivation | correct_recipient=PASS
PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS
```

The immediate activity-notice shared-helper gate is closed. Do not claim the real collection/sponsor/reactivation POST routes or browser inbox are verified by this harness. Continue only with the remaining documented incentive notification/UI acceptance gates; preserve active R5 and do not test against live financial data or repeat already-closed suites.


## Deferred development point — collection incentive eligibility (recorded 2026-10-18)

The user raised a business-design concern after the real browser collection submission displayed a provisional “activity linked to an incentive” notice: routine monthly sponsorship collection is ordinary job responsibility and may not merit an additional incentive, especially where the same employee may also qualify for the separate new-sponsor acquisition incentive. Revisit this with the user's forthcoming idea before changing behavior. Candidate recommendation to evaluate later: disable or redesign the collection incentive category and its provisional activity notice, while retaining new-sponsor, reactivation, and project-completion incentives unless the user decides otherwise.

**Deferred, not approved for implementation.** Do not disable the category, change active policy R5, modify existing events/awards, delete notifications, reverse accounting, or change payroll now. Preserve historical evidence. The observed collection notice is provisional and does not prove an award was issued; before describing an employee as having received a new-sponsor incentive, inspect the actual event and award records. Continue the already-open acceptance work without repeating the completed collection/new-sponsor browser submissions or closed helper harness.


## 2026-10-18 — Browser evidence and next read-only incentive-state check

The user has now completed real-browser checks of two immediate activity-notice routes on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
- Active new-sponsor creation for user 19 created sponsor `SP-002859` (“اختبار الحوفز”); the inbox showed the provisional activity notice with expected reward 2,000 SDG and explicit pending-verification/not-paid wording (observed 19:16:45).
- Monthly sponsorship collection submission reported two payments submitted to FM for approval/posting; the inbox showed the provisional collection activity notice for expected reward 1,000 SDG (observed 19:29:40). Do not assume this submission inserted only one payment.

These browser results prove the activity notices rendered; they do **not** prove an award was issued. The user's concern that the same employee may already have received a new-sponsor incentive must be resolved from durable records, not notification wording. Next step: run a read-only query against the same disposable database for incentive events and joined awards for recipient user 19, especially `new_sponsor` and `collection`; report event status and award status/payroll/journal links before deciding whether any earned incentive exists. Do not mutate, reverse, delete, or rerun the already completed browser submissions. New-sponsor first-confirmed-payment acceptance from 2026-10-16 remains closed and must not be repeated.

The separate development point about whether routine collection should qualify for an incentive is deferred and recorded above; no category, active R5 policy, event, award, notification, payroll, or accounting behavior has been changed for that recommendation.



## Current continuation — collection incentive removal CLOSED (2026-10-19)

The collection-incentive removal is fully verified at the defined rollback-only acceptance boundary. Seven PHP lint checks passed for the four changed PHP files and three updated harnesses. All three rollback harnesses passed on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
1. Immediate activity notices: new-sponsor/reactivation pass; collection notice blocked; recipient, provisional wording, reward context, unread state, and cleanup pass.
2. Source events: collection event blocked; no new collection award path; cleanup passes (policy 18).
3. HR verification: recipient eligibility, pending-to-verified transition, award creation, duplicate protection, and cleanup pass; recognition Dr 5500 / Cr 2400 for 2,000 SDG (policy 11, employee 1).

Do not rerun these harnesses or perform browser submissions without concrete regression evidence. Routine collection no longer creates new incentive notices/events and pending legacy collection events cannot be verified into new awards. Historical events, awards, notifications, payroll, accounting entries, the catalogue entry, and active policy R5 are preserved and unchanged. New-sponsor, reactivation, and project-completion incentives remain enabled.



## Current continuation — employee incentives acceptance boundary (2026-10-19)

Do not call the whole employee incentive module fully complete yet. Several core paths have passed their defined checks, but the end-to-end acceptance gates below remain open.

**Closed; do not repeat without concrete regression evidence:**
- Routine collection incentive removal: seven PHP syntax checks and three rollback-only harnesses passed on the disposable DB. New routine collection notices/events are blocked and pending legacy collection events cannot be verified into new awards; historical records, catalogue and active R5 remain unchanged.
- New-sponsor first-confirmed-payment browser acceptance (2026-10-16).
- Shared provisional-notice helper rollback test for new-sponsor/reactivation; collection intentionally blocked.
- Project-completion rollback harness including FM gate, Dr 5500 / Cr 2400 recognition, payroll linkage/outbox and separate-supervisor fixture.
- One-time PRJ-0024 supervisor historical recovery on the disposable database: allocation 15, award 24, journal 178, scheduled, outbox sent, notification 232, audit recorded. Never rerun this recovery or re-close/reopen PRJ-0024 for testing.

**Remaining work only:**
1. Complete real-browser PM-to-FM financial closure acceptance and verify exactly one earned notice for every eligible snapshotted recipient, especially the separately assigned project supervisor PS1; verify reload/retry does not duplicate notices. Do not mutate PRJ-0024 or use historical recovery as a substitute.
2. Verify real POST-to-browser-inbox provisional notices for the retained new-sponsor and reactivation categories. Routine collection is no longer an incentive path and must not be retested as one.
3. Verify project-team assignment UI: initial selection, adding a member, duplicate rejection, unassign with history retention, and edit-page behavior.

Existing evidence is from disposable test state and rollback-only harnesses; it does not prove live/production database acceptance or the complete end-to-end gates above. The two disposable incentive databases were deleted after schema comparison; do not recreate them. Preserve active policy R5 and existing historical payroll/accounting records. Follow inspect → smallest justified change → lint/runtime evidence → documentation; do not rerun closed suites.

## 2026-10-19 — Immediate activity-notice harness passed

The user ran the updated immediate-notice rollback harness after syncing `main`. Six-table InnoDB preflight and all helper assertions passed for new-sponsor/reactivation provisional notices, blocked collection notice, correct recipient/content/unread state, and rollback cleanup. This does not close the real POST-to-inbox or PM-to-FM earned-notification browser gates. Do not rerun closed harnesses without regression evidence, recreate deleted disposable databases, or run further mutating acceptance tests against live `ahl_el_kheir`.

## 2026-10-19 — Project-team source syntax verification

The user ran PHP 8.2 syntax checks locally after syncing `main`; both commands passed:

- `php -l modules\\projects\\form.php` — `No syntax errors detected in modules\\projects\\form.php`.
- `php -l modules\\projects\\view.php` — `No syntax errors detected in modules\\projects\\view.php`.

This closes only the local PHP syntax-check item. Browser acceptance for initial team selection, edit-page display and authorization, add/duplicate rejection, unassignment/history retention, and success feedback remains pending. The user confirmed the two disposable incentive databases are deleted; do not recreate them and do not perform mutating acceptance tests against live `ahl_el_kheir`. No database rows or project assignments were changed as part of this syntax check.


## 2026-10-19 — Projects UI confirmation, feedback, and navigation remediation (source committed; local verification pending)

A targeted source audit of `modules/projects/*.php` and `dashboard/projects_dashboard.php` found that project actions used browser-native `confirm()`/`alert()` calls, and the shared `assets/js/app.js` same-page POST capture listener could start a request before a form's own confirmation handler ran. On the FM project page, custom funding/payment submit handlers also competed with the shared interceptor because their forms were not consistently opted out.

Source changes committed directly to `main`:
- `e2af93acef3fc68c451380fcb4de8a062897afb8` — added an opt-in SweetAlert confirmation helper for project action buttons.
- `3e8f237f19f3d1107f44dca6c90cfafc8d35bc2f` — replaced project-form native prompts/alerts with system dialogs.
- `c3769432261e75f44a865bc9a260178c72d3b823` and `ff108c2bf154b12d6e8d1acb47859408b1433c87` — replaced project-detail native confirmations and kept administrative closure on the project detail page so its post-action feedback remains visible.
- `cf3a95673debd5b78b588e7b430e00054c3d4b0a` and `d5952f3ec097eace6648e1b8a4643ea995c85fc2` — opted custom FM funding/payment handlers out of the shared POST interception and replaced native browser dialogs in those paths.

A post-change static scan found no remaining direct `confirm()` or `alert()` calls in `modules/projects/form.php`, `modules/projects/view.php`, `modules/projects/view_fm.php`, or `dashboard/projects_dashboard.php`. This is source inspection only: PHP syntax checks, JavaScript/browser behavior, button navigation, toast visibility, and FM funding/payment flows still require local verification. No database queries or mutations were performed, and no incentive eligibility, award, journal, payroll, or notification logic was changed.

Next: pull `main), run PHP syntax checks for the three changed project PHP pages, and verify in the real local application that project confirmations use the system dialog, cancel means no POST, confirmed actions execute once, project feedback is visible, FM funding/payment AJAX actions do not double-submit, and administrative closure returns to the project detail page. Do not perform a closure, funding, payment, or team-membership mutation merely to test UI; use safe non-mutating checks first and assess any live-data mutation separately.


### 2026-10-19 — Project form validation ordering follow-up

Follow-up source commit: `97ca44232ecec3bdb306992e46f5aec4ff6d770c`. The project create/edit form now opts out of the shared same-page POST interceptor so its existing client-side budget/team validation can run before submission. Server-side validation remains authoritative. This is still source-only verification; local PHP lint and browser verification are pending.

## 2026-10-19 — Employee salary statement checkpoint

Source committed on `main`: `modules/hr/payroll_statement.php` plus a post-commit notification hook in `modules/hr/lib_payroll_accounting.php`. The route requires login, resolves the current employee through the existing `hrGetEmployeeForUser()` identity helper, scopes the payroll query to that employee, and only serves rows whose payroll status is `paid` and accounting status is `posted`. Statement figures are read from saved payroll values; currency is resolved from salary history effective during the payroll period. Incentive amount is separate from net salary and total paid is `net_salary + incentive_amount`, matching existing accounting posting semantics. The A4 bilingual Arabic-first statement supports browser Print / Save as PDF. Notification errors are isolated after commit and duplicate notices are suppressed by recipient/link/title.

Local verification remains pending: run PHP lint on the two changed PHP files, then use an already-paid/posted payroll only if one is available and safe to inspect. Verify that the employee can open their own statement, a different employee cannot access it by changing `id`, unpaid/unposted rows are not served, the print preview is readable, and notification is not sent on approval. Do not pay a new payroll or mutate live accounting data solely to test. Report any exact output/errors and continue from evidence; do not reopen completed salary-advance, attendance, or incentive suites.


## 2026-10-19 — Salary statement presentation follow-up

The employee confirmed that the post-commit salary-statement notification was delivered and opened successfully for the paid October 2026 payroll (EMP-0017, 10,000.00 SDG; accounting entry 157). The statement was then refined on `main` to open payroll-statement notifications in a new tab (notification dropdown, live toast, and full notification history), center the organization logo in the header, use the navy header color for the outer statement border, compact earnings and deductions into side-by-side panels with a compact payment summary for A4 printing, and render only the currently selected `AK_LANG` language. Employee-only authorization, the paid + accounting-posted guard, saved payroll figures, separate incentive line, and `net_salary + incentive_amount` total-paid formula are preserved.

**Verification boundary:** the user confirmed notification delivery and initial statement opening before these presentation changes. The revised new-tab behavior, Arabic/English layouts, and A4 print fit remain pending local browser/print-preview confirmation; no additional payroll disbursement or database mutation is required to verify these UI changes.

## 2026-10-19 — Salary statement screen width refinement

Reduced the on-screen salary statement container maximum width from 900px to 748px, matching approximately the 198mm printable width of A4 portrait with the existing `@page` 6mm margins. The existing print-specific rule continues to use the full available printable width. This is a CSS-only adjustment; payroll data, authorization, notification behavior, and accounting are unchanged. Local browser and print-preview verification remain with the user.

## 2026-10-19 — Salary statement heading placement

The salary statement header now places “بيان تفاصيل الراتب” and “كشف راتب رسمي” immediately after the logo and organization name as one grouped brand/title line, instead of positioning the title independently at the edge of the header. The browser document title is also “بيان تفاصيل الراتب” in Arabic (or “Salary Statement” in English). Existing responsive and A4 print styling remains in place. Source committed on `main`; local browser/print-preview confirmation remains pending.

## 2026-10-19 — Salary statement heading stacked under branding

Follow-up correction: the logo and “جمعية أهل الخير” remain grouped on the first header line; “بيان تفاصيل الراتب” and “كشف راتب رسمي” are now a separate centered row directly underneath, not beside the logo. Header CSS/markup source committed to `main`. Local browser and A4 print-preview confirmation remain pending.


## 2026-10-19 — Employee salary statement implementation CLOSED

The user approved the final heading arrangement: the logo and “جمعية أهل الخير” remain together on the first row, with “بيان تفاصيل الراتب” and “كشف راتب رسمي” centered on a separate row below. The source changes are committed on `main`; the existing paid October 2026 payroll acceptance confirmed statement-notification delivery and initial statement opening for EMP-0017 (10,000.00 SDG; accounting entry 157). The implementation preserves employee-only access, the paid + accounting-posted guard, saved payroll values, incentive separation, and the existing `net_salary + incentive_amount` total-paid formula.

**Disposition:** implementation and the requested presentation correction are closed at the agreed scope. Do not run another payroll payment, mutate financial data, or repeat closed acceptance suites for this task. The assistant has not independently observed the user's final local A4 print preview after the last heading correction; this is not represented as runtime verification. Reopen only if the user reports a concrete defect.


## 2026-10-19 — GM payroll period completion report
Current active work: complete the local verification boundary for the GM-only payroll period report committed to `main`. Read `docs/HR_GM_PAYROLL_PERIOD_REPORT_CONTINUATION.md`. Do not repeat payroll payments or mutate paid October 2026 records. Check PHP syntax and then verify existing October completion notification, GM-only report access, period totals/journal IDs, and print output on the real local Apache/database environment. Do not mark this closed until that evidence exists. Existing employee statement notifications remain unchanged.


## 2026-10-19 — GM payroll payment history navigation
Added a dedicated GM payroll payment history page and a quick-access card on the GM dashboard. The history lists payroll periods with at least one payroll record in `paid` status, shows each period's eligible/paid-and-posted counts and posted payment totals, and links to the existing read-only period report. The GM sidebar entry now opens the history page; notification links still open their specific period report directly. The page is GM-only and read-only; it does not change payroll or accounting data.

**Verification boundary:** source committed to `main`; local PHP syntax and browser verification are pending. Do not pay, approve, regenerate, or otherwise mutate payroll records for this UI change.


## 2026-10-19 — GM payroll history closed; next task
The user confirmed the GM payroll payment history navigation works. Treat it as CLOSED; do not repeat checks without regression evidence.

Next active task: improve FM usability of `modules/hr/payroll.php?lang=en`. Read the canonical docs and inspect the live `main` source, especially the table structure/styles and `assets/js/app.js` same-path POST handler. Desired: reduce width, sticky headers during vertical scrolling, keep action controls usable after a row action without forcing the user to scroll back, and remove only demonstrably unnecessary display fields. Preserve all payroll/accounting business behavior. Use the smallest source-justified change, lint changed PHP, document the exact verification boundary, and do not mutate financial data for a UI-only task.


## 2026-10-19 — FM payroll table usability continuation
The requested source changes are committed on `main`, including the valid table-cell placement follow-up (`fecd42a9edbae3ff91b1092f81bcb4dc8c06eeb8`). See `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md` for findings and exact scope. Next gate is focused local verification only: pull `main`, run PHP lint on `modules/hr/payroll.php`, and visually verify the existing payroll table's compact layout and sticky header in the real Apache app. If a safe existing draft/approved row is available, check that its normal action retains the table scroll position; do not create/pay test payrolls or mutate paid accounting data. Do not repeat unrelated payroll suites.


## 2026-10-19 — FM payroll table refinement checkpoint
Read `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. Source is committed to `main`: compact auto-sized translated column headers, dark sticky header, English month names, explicit save action in the row Actions column, and checkbox-based selected draft approval. The selected-approval helper validates the chosen period and all selected draft rows, then updates only approved/ready state in one transaction; it does not pay or post accounting. Static translation/source checks passed, but local PHP lint and browser verification remain pending. Next: pull `main`, lint `modules/hr/payroll.php` and `modules/hr/lib_payroll_accounting.php`, lint `lang/en.php` and `lang/ar.php`, then visually check both `?lang=en` and Arabic mode and select-all/count/button behavior without submitting an approval or payment. Do not rerun payroll suites or mutate payroll data for this UI check.


### Current continuation — 2026-10-20, Step 2

Continue from the Step 2 audit in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. Do not implement the proposed schema until actual local `SHOW CREATE TABLE` output for payroll/accounts/users/notifications and relevant employee keys has been inspected, and a real local migration/storage example has been checked. The connector returned an empty body for `database/ahl_el_kheir.sql`; this is not evidence about schema content. Keep paid/accounting, proof upload, transfer confirmation, notification and GM completion as separate explicit gates. No source/schema/data changed in Step 2.


### FM payroll workflow redesign — current state 2026-10-20

Step 2 is closed at the design boundary after reviewing actual local schema output. See `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md` for the model and required gates. Next: draft the migration and private proof storage contract; document and commit that stage before implementing runtime behavior. Do not change existing account resolution semantics without an explicit business decision. No source/schema/data has been changed by Step 2.

### FM payroll workflow redesign — current continuation 2026-10-20

Step 3 authored a migration draft at `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql`, commit `f1225411c429f0b0eb9486ccec03f6be02e1ed8a`. Do not claim it is applied. Before application, review the exact SQL and verify the local migration runner and private storage path. Next implementation stage is period account preparation plus proof upload/download; document and commit that stage before implementing the payment confirmation/accounting gate. Full requirements and storage contract are in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`.

### FM payroll workflow redesign — current continuation after Step 4 (2026-10-20)
Step 4 source is committed on `main`: `modules/hr/lib_payroll_transfer.php`, `modules/hr/payroll_proof_download.php`, and `modules/hr/payroll.php`. It adds period account defaults, explicit group/row overrides for approved payroll, private PDF/JPG/PNG proof uploads (10 MiB limit), authorized downloads, and legacy evidence attachment without changing historical paid/accounting fields. The migration `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql` remains unapplied. Source has not been locally linted/runtime-tested. Next: redesign the paid transition as an explicit post-transfer confirmation requiring a prepared proof, perform proof confirmation + paid status + accounting posting transactionally, and make employee notification outcome and GM period completion depend on proof and notification success. Do not claim this workflow ready until migration and local checks are completed. Follow the per-step documentation checkpoint rule.


### FM payroll workflow redesign — current continuation after Step 5 (2026-10-20)
Step 5 source is committed on `main`. Explicit CSRF-protected transfer confirmation requires a readable `prepared` proof and active saved payment account; it confirms the proof, marks payroll paid, posts accounting, and applies existing salary-advance behavior transactionally. The old generic `update_status=paid` path is blocked. Employee statement notification now reports success/failure; GM completion requires each eligible employee's paid+posted+journal state, confirmed/legacy evidence, and recorded statement notification. Account changes are blocked after proof attachment. The migration remains unapplied and local PHP lint/runtime verification is pending. Next action: pull `main`, lint all changed PHP files, apply the reviewed migration through the project migration process, then run only a focused, safe local verification of account selection/upload/confirmation/notification gates. Do not claim success before local evidence; do not rerun unrelated closed suites.

## 2026-10-20 — Initial Salary Capture / September Draft Boundary

New source change on `main`: account creation requires the initial monthly basic salary and hire date; employee provisioning records the salary-history entry effective on the hire date. Payroll continues to resolve the effective-dated history for each month. Local PHP lint and XAMPP/browser verification remain pending.

Separate unresolved data issue: 18 September 2026 draft payrolls have zero salary and matching zero salary-history records. Most positive history rows supplied by the user start on 2026-10-01. Never copy October salaries backward or invent values. Next step is to pull current `main`, lint the three changed PHP files, and verify account creation creates the expected history row; do not approve September drafts until the actual September salary values are confirmed.


### HR employment contract builder — current continuation (2026-10-20)

The user requires an in-system contract page, not a separate Word template. Source on `main` now includes `modules/hr/contract_builder.php` and a link from `modules/hr/contracts.php`. HR can choose an existing employee, edit fields, and POST to a TCPDF-backed A4 Arabic RTL PDF for viewing/printing. The builder does not save drafts or mutate employee/contract/salary/payroll/accounting records. The contract is a legal draft; Sudanese legal counsel must review it before official use.

Commits: `f0e5fdba4b32e179091478b092eccd5dd10f033f` and `ca14abbd85b490c5025f8367c1fe2d2caa43f88d`. Details are in `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

**Next action:** sync local `main`, run `php -l modules/hr/contract_builder.php` and `php -l modules/hr/contracts.php`, then test at `http://localhost:8081/AhlElKheir/modules/hr/contract_builder.php` as an authorized HR user. Verify employee prefill/editing, PDF opening, Arabic RTL/logo, multi-page layout, print quality, role denial, CSRF rejection, and no DB/storage mutation. Do not begin the second task (onboarding + signed-copy upload integration) until this gate is accepted. Review the contract with a qualified Sudanese lawyer before operational use.

### Contract builder validation refinement

Commit `eef4d17e02543e1327ed8cc3db9fee3b916aa11f` adds server-side field-length/email/weekly-hours validation, requires an end date for fixed-term contracts, and removes the assumed nationality default. Continue with local PHP lint and browser/PDF acceptance only; do not begin onboarding integration before acceptance.

## Latest active task — HR role-specific employment contract portal (2026-10-20)

Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`. Source code and migration are committed on `main`, but the local migration and browser/PDF verification are pending. New migration: `database/migrations/2026-10-20_hr_job_description_catalogue.sql`. New catalogue UI: `modules/hr/job_description_catalogue.php`. Updated portal/PDF builder: `modules/hr/contract_builder.php`. Dashboard shortcut: `dashboard/hr_dashboard.php`.

Next: check the current PHP syntax workflow result, have the user pull `main`, apply the migration to `ahl_el_kheir`, run syntax checks, and verify the role-to-description loading and PDF rendering in the real XAMPP application. Preserve existing `modules/hr/contracts.php` and salary-history behavior. Never invent job descriptions; seeded catalogue entries require HR review. Employee `position` is authoritative, not application permission role. Contract wording requires qualified Sudanese lawyer review before official use. Do not reopen closed modules or repeat unrelated tests.


## 2026-10-20 — HR job-description drafts and contract prefill

The current follow-up source adds `database/migrations/2026-10-20_hr_job_description_proposals.sql`. It fills only blank job-description fields with proposed Arabic drafts, preserves existing HR content and approval status, keeps proposed records pending HR review, leaves special contract terms blank, and deactivates the `HR Test` catalogue row without deleting the employee. The contract builder pre-fills a proposed reporting line from the position catalogue and labels it for HR confirmation. Next: user applies the migration to `ahl_el_kheir`, then perform focused PHP lint and local browser verification. Do not claim runtime success before evidence. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-20 — Current employment contract/job description continuation

The latest follow-up distinguishes job title from application role using `employees.user_id` → `users.role_id` → `roles.code`. New migrations to apply in order after the already-applied catalogue/proposals migrations: `database/migrations/2026-10-20_hr_job_description_system_roles.sql`, then `database/migrations/2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql`. The catalogue now supports one responsibility per field with add/remove controls; values are newline-separated in the existing column and the PDF renders them as an indented list. Role-specific drafts are based on documented system workflows and remain `needs_review`; they do not prove every action is actually performed by the employee. Next: user pulls `main`, applies both new migrations in order to `ahl_el_kheir`, then run focused PHP lint and verify the catalogue fields/employee-role match/PDF list on `http://localhost:8081/AhlElKheir/`. Do not claim runtime verification before evidence. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-20 — Job-description item fields and supervisor selectors

Source changes committed directly to `main`:
- `6aecdb28e7abfbd58a8242090137ddfb1e09c89e` — separate editable item rows for role purpose, responsibilities, qualifications and optional role-specific terms; add/remove controls; reporting-line options sourced from distinct existing employee position titles.
- `21ee08af28a642800236d75369a69931b52a93a3` — persist the item lists as newline-separated text in the existing TEXT fields (no schema migration or runtime DDL).
- `ab2079268dfe72551613475aaa0e3bbc72478fc4` and `e97a1b5a1623b194d9f29e6559a71bc43e761b05` — direct-supervisor selector sourced from existing employee records, no longer guessed from the catalogue reporting title; prevent selecting the contract employee as their own supervisor; purpose, responsibility and qualification items render as separate list entries in the contract preview/PDF.

The existing “الأوصاف حسب المسمى الوظيفي ودور الحساب” catalogue table remains in place. Existing proposed purpose/responsibility text is preserved; no unsupported qualifications or optional contract clauses are invented. Proposed descriptions remain subject to HR review and approval, and the contract remains subject to qualified Sudanese legal review.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** No local XAMPP runtime or PHP CLI was available to this session, so do not mark the feature runtime verified. The GitHub Actions run triggered by the latest source commit reported failure, but its job steps/logs were not retrievable through the available GitHub connection; this is not evidence of a PHP syntax failure or pass. Local verification must run PHP lint and focused browser checks before acceptance.


## 2026-10-20 — Contract builder repeatable duties and special terms

On `modules/hr/contract_builder.php`, the two requested contract fields are now repeatable lists: **المهام والمسؤوليات والوصف الوظيفي** and **الشروط الخاصة المتفق عليها / البنود المرتبطة بالمسمى**. Each row has its own text area and add/delete controls. When an employee is selected, existing job-description and role-specific-term lines populate separate rows; submitted rows are combined with newline separators for the existing PDF rendering. No schema change or runtime DDL was introduced. Source commits: `05171ee43e74269a9ae397e9176fbb59e13544d9` and `61fd28bb49528d823b8e41e8a1cd59ad69e76d29`.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** PHP lint and browser/PDF checks have not been run in the local XAMPP application. After pulling `main`, verify adding, deleting, changing employee selection, and confirming all retained entries appear in the generated PDF.


## 2026-10-20 — Repeatable contract fields: blank-selection edge case

Source commit `7b0f675700651ea295569e3c410b44c7e39a679d` clears both repeatable lists and resets the job-description status message when the employee selector is returned to its blank option, preventing duties or special terms from the previously selected employee remaining visible. The fix is source-committed only. Local PHP lint, browser interaction, and PDF rendering remain unverified; do not mark the contract builder complete until the focused local checks pass.

## 2026-10-20 — Role-based qualification drafts from researched NGO job profiles

Added data-only migration `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications.sql`. Apply it after the earlier 2026-10-20 job-description catalogue, proposals, system-role and generic-draft cleanup migrations, in filename order.

The migration:
- removes the known “مسودة مستندة إلى مسارات النظام” seed prefix from unapproved role-purpose drafts so the field reads as the actual proposed purpose;
- replaces only exact generic placeholder text for the generic `موظف` row and unknown-role fallback with useful draft wording;
- fills blank or exact generic-placeholder qualifications with role-specific, research-informed proposed qualifications for management, finance/accounting, HR, IT administration, project management/supervision, sponsor supervision, family/child support, administration, general staff and communications/social media;
- retains HR-authored non-empty qualifications, leaves approved catalogue records untouched, does not change approval states, and does not create runtime DDL.

Qualification wording is deliberately framed as a proposed profile (degree/training or equivalent experience where suitable; relevant skills and experience), not a mandatory legal or hiring condition. All modified descriptions remain pending review unless already approved, and approved rows are not changed by this migration.

Research references used to shape common qualification profiles:
- Saferworld, Finance Officer — Sudan (accounting/finance degree, accounting controls, NGO operations, software, confidentiality and communication): https://www.saferworld-global.org/downloadfile.php?filepath=downloads%2Ffinance-officer-sudan.pdffinance-officer-sudan-final-april-2021.pdf
- CARE Egypt, Project Accountant (finance/accounting degree, NGO experience, Excel/ERP and Arabic/English): https://care.org.eg/wp-content/uploads/2024/05/Project-Accountant_Upper-Egypt.pdf
- UN Careers, Human Resources Assistant (role-family reference): https://careers.un.org/jobSearchDescription/286436?language=en
- UN Careers, Information Technology Assistant (systems/network/security support reference): https://careers.un.org/jobSearchDescription/282210
- UN Careers, Communications Assistant (public information/communications reference): https://careers.un.org/jobSearchDescription/286125?language=en
- UN Careers, Programme Management Assistant (project coordination, reporting, stakeholder and budget tracking reference): https://careers.un.org/jobSearchDescription/278900

These sources are benchmarks, not a claim that Ahl El Kheir must adopt another organization's minimum criteria. HR and the direct supervisor must confirm actual day-to-day duties, required education and experience, reporting line and role-specific conditions before approval. A system role documents workflow access; it does not prove which tasks an employee has personally performed.

**Verification status: SOURCE COMMITTED; local migration application, PHP lint, catalogue browser check and contract PDF check remain pending.**


## LATEST CHECKPOINT — 2026-10-20 — Job-description qualification UTF-8 repair

The user diagnosed a local encoding failure after importing `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications.sql` into `ahl_el_kheir`. Diagnostic evidence: 26 rows total, 25 qualifications values start with literal question marks, zero approved rows, one `HR Test` row; all relevant columns use `utf8mb4_unicode_ci`. A second check found no question-mark prefixes in role_purpose, responsibilities, or position_title.

Committed to `main`:
- Original seed migration now explicitly starts with `SET NAMES utf8mb4;`.
- New data-only correction: `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications_utf8_repair.sql`. It sets UTF-8 explicitly and reapplies the original role-specific Arabic qualification CASE only to rows whose qualifications begin with literal question marks, excluding approved rows and `HR Test`. It leaves the other description fields and all schema untouched.

**Next:** user pulls `main`, applies only the new repair migration with the mysql client explicitly set to utf8mb4, then runs read-only verification confirming the 25 corrupted values are restored, Arabic text survives HEX/terminal checks, and the `HR Test` row remains unchanged. Do not rerun the original seed migration as a repair. Do not claim runtime success before results are returned.


## LATEST CHECKPOINT — 2026-10-20 — Qualifications UTF-8 repair verified

The user pulled `main`, applied `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications_utf8_repair.sql` with `--default-character-set=utf8mb4`, and ran verification. Result: `remaining_corrupted_qualifications = 0`; qualification hex previews contain real UTF-8 Arabic bytes. `HR Test` remains excluded and its qualifications are NULL. PowerShell displays Arabic position titles as mojibake, but this does not by itself prove stored title corruption; do not modify them without byte-level evidence.

The data-repair milestone is verified and closed. Continue the broader job-description draft task from the relevant continuation doc/source; catalogue/browser display and contract-builder/PDF acceptance have not yet been reported verified. Do not rerun either migration or reopen the encoding repair absent regression evidence.


## 2026-10-20 — Contract builder: dedicated job-purpose draft field

The latest source correction is committed on `main`: `modules/hr/contract_builder.php` now has separate repeatable controls for job purpose, responsibilities and qualifications, plus a reporting-line field. The former combined duties control was the root cause of the missing `الغرض من الوظيفة` field. Employee selection loads the corresponding position + system-role template, with generic-position fallback, and PDF output retains the separate section headings. Commits: `73b91d1a40024564d7399a80ae91b011167dbf9c`, `8fc40930c4224f04dab7e1f8c9c96c4fdfafd4b7`, `ec565758660214ac371ef14b692b4265249fc2b7`, `4d3d030bc4b96537141eec16fc6e3e0e86bce05d`, `59332e20a7d186eae3f969a9a94c24b1e2225d44`, `59ecd7b5057d9fe2910ccc3314b073d305cc19d6`. Next: pull `main`, PHP-lint `modules/hr/contract_builder.php`, and test employee switching and PDF sections on the real XAMPP URL. No migration is needed.


## 2026-10-20 — HR job-description fallback catalogue clarity

Current continuation is `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`. Commit `6264c897b2bbadb460e0ba0d3e0c029b1443312d` clarifies title-only general fallback rows in the HR catalogue, shows active role-specific template counts, and warns when an empty fallback has role-specific records. Employee-wide SQL evidence showed employee 21 (HR Salary Test) has no active matching template; do not reactivate the intentionally deactivated HR Test row or change employee data without investigation. For **أخصائية شؤون الأمهات**, populated role-specific draft id 20 and empty fallback id 6 are distinct records. Source and docs are committed on main; local PHP lint and browser verification remain pending. Do not rerun migrations for this UI-only change.


## Follow-up — null-safe HR fallback counts

Commit `4435226e37405e9a2963dd07af0d1c14f71e7f92` makes catalogue employee counts handle NULL and empty `system_role_code` as general fallback values, matching contract-builder selection. Local PHP lint and browser verification remain pending; no migration is required.
## LATEST CHECKPOINT — 2026-10-20 — General job-description fallback drafts

User evidence showed the general/title-only fallback records still had empty `الغرض من الوظيفة` and `المهام والمسؤوليات الأساسية` fields. The reason was the earlier `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql`, which cleared exact generic proposals. New migration committed to `main`: `database/migrations/2026-10-20_hr_job_description_zzz_general_fallback_drafts.sql` (commit `5b6a553b22ff5a0be3d0a2b6430556ccf5138be9`). It restores distinct, broad proposed text only to active title-only rows that remain `needs_review` and have blank purpose/responsibility fields. Existing non-empty text, role-specific records, approval states, qualifications, reporting lines, employee data and schema are preserved; `HR Test` remains excluded.

**Next:** user runs `git checkout main` and `git pull --ff-only origin main`, then applies only the new migration using an explicit UTF-8 MySQL client. Verify active title-only rows have purpose/responsibilities and remain `needs_review`; then PHP-lint `modules/hr/job_description_catalogue.php` and browser-check the catalogue and employee-specific contract prefill. Do not rerun prior migrations. Local runtime acceptance is pending.


## 2026-10-20 — HR contract builder: baseline qualifications and drag ordering

The job-description catalogue and contract builder now provide pointer-based drag handles for repeatable purpose, responsibility, qualification and optional-term rows. Item numbers update automatically after reordering, addition or deletion, and the generated PDF uses the submitted row order.

Apply `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql` after the existing 2026-10-20 job-description migrations. It prepends:
1. `الحد الأدنى للمؤهل الأكاديمي: شهادة الثانوية العامة أو ما يعادلها.`
2. `معرفة أساسية باستخدام الحاسوب وبرامج المكتب.`

Changed descriptions are marked `needs_review`, not approved. Local migration, PHP lint, browser interaction and PDF checks remain pending; continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.


## Latest task — automatic employment contract numbering (2026-10-20)

User approved integrating modules/hr/contract_builder.php with the existing contract register: PDF generation must reserve a unique official number by creating a draft record. Source commit on main: 8ed43dc8c7e0f2e2c5693e1777bfb8324bdc3115.

Implementation details: read-only number field; number generated from saved hr_employee_contracts.id using existing CTR-YYYY-000001 format (year from start date); draft inserted in a transaction and committed only after TCPDF renders to memory; overlapping draft/active periods rejected using the existing helper's rule; one-use session token blocks resubmitting the same form. The builder avoids hrCreateEmployeeContract() because it writes salary history and can update current salary/employment state for current/past start dates. The new path does not alter salary history, employees.basic_salary, or employment state. No migration added.

Not runtime verified yet. On the user's machine, pull main, run D:\xampp\php\php.exe -l modules\hr\contract_builder.php, then use http://localhost:8081/AhlElKheir/modules/hr/contract_builder.php to generate a PDF and verify its number exactly matches the draft row in العقود والرواتب. Verify the draft status, no salary-history/current-salary/employment-state change, overlap rejection, and that resubmitting the same form does not create a second row. Do not mark this complete until evidence is supplied. Continue from docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md.


## 2026-10-20 — Unify contract creation and register workflow

Source changes committed directly to `main`:
- `315f221c9f43d4bbbe55b0f2acdce88e3b403296` — removed the duplicate `add_contract` creation path from `modules/hr/contracts.php`. New-contract links now open `modules/hr/contract_builder.php` with the selected employee. The old `?view=add` URL now explains the canonical workflow instead of presenting a second creation form.
- `b312f117f077f14e8c5f4f91bd80a4aeaf0c2ebf` — the builder's return link now returns to the employee's contract-register view, not the retired add form.
- `dd2b1dcc99762226e1ddf8a28000e6fc4a7d5190` — added an explicit optional probation-end date to the builder, validates it against the contract start date, includes it in the PDF text, and persists it to the draft's existing `probation_end_date` column so the register's existing activation/employment-state behavior remains available.

The register continues to manage contract status and salary history. It now uploads or replaces a signed copy against an existing contract record, rather than creating a new contract as a side effect of uploading a file. The upload validates that the contract belongs to the selected employee and retains the existing 10 MB / PDF-JPG-JPEG-PNG limits. Replacing a file updates the register path but deliberately does not delete older files.

No schema change or migration was introduced. The builder continues to reserve the official number and create a draft when PDF generation succeeds; this does not activate the contract or update salary history/employment state. The full editable clause text remains in the generated PDF, while the register stores its existing core fields.

**Verification status: SOURCE COMMITTED; PHP lint, browser interaction, signed-copy upload, draft activation, salary-history/employment-state behavior, and generated PDF are NOT runtime verified.** Pull `main`, lint both changed PHP files, then test one non-production/appropriate employee record through the configured XAMPP URL. Do not create a second draft for an employee/date period already covered by an active or draft contract. Sudanese legal review remains required before official use.


## 2026-10-21 — Current continuation: unify HR contract navigation (source only)

The user pulled `main` successfully and confirmed a clean working tree before these changes. Commits `be9c2515ec0f387406f4e54180e103d2985869bf` and `f3263b20eb666de05d096bc6d30d0143e92b15b7` make `بوابة عقود العمل` the only contract-related dashboard shortcut. The portal now links to `modules/hr/contracts.php` (register/salary history) and `modules/hr/job_description_catalogue.php` (job descriptions). Do not delete the register yet: it still owns signed-copy upload, contract activation/termination/status and salary-history workflows. No DB/schema changes were made.

Next: after syncing `main`, run PHP lint on `dashboard/hr_dashboard.php` and `modules/hr/contract_builder.php`, then use the configured local URL to verify that the dashboard has one contract entry and that the portal opens the register and catalogue. Then inspect whether register actions can safely be presented within the portal without duplicating business logic. Do not run contract generation against an employee/date range already covered by a draft or active contract. Do not claim runtime verification without local evidence.

## Contract PDF preview follow-up — 2026-10-21
- Runtime report from the local contract builder showed an `Undefined array key "reporting_to"` warning during PDF generation. Source inspection found `reporting_to` was listed in the field-length validation limits but absent from the `$data` array.
- Corrected `modules/hr/contract_builder.php` on `main` by adding `reporting_to` to the validated data array (commit `86bb2343fd19ed1455420dcc9c4775a07cda4605`). This is source-committed but not yet locally linted or runtime-verified.
- The UI also reported an overlapping draft/active contract for the selected employee and period. Treat that as a separate safeguard; inspect the relevant employee's contract register before retrying, and do not bypass the overlap check.
- Next: user pulls `main`, PHP-lints the builder, then chooses an employee/period with no conflicting draft or active contract to generate and inspect the actual PDF design. If the conflict persists unexpectedly, capture the selected employee and inspect the register before changing logic.


## 2026-10-21 — Employment contract PDF design correction (source only)

The user rejected the first generated PDF because it did not reflect the previously agreed formal Arabic RTL navy-and-gold design. Source commit `ed3e4abf0bf4c3ea906ee21c30d9c2b6b465cc3c` updates `modules/hr/contract_builder.php` to add a distinct cover page with organization branding, contract number, employee/position and formatted dates; strengthen navy/gold section styling; isolate the signature/acknowledgment block on a dedicated final page; format dates as DD/MM/YYYY; and explicitly isolate Latin employee names, contract numbers and salary/currency text for more reliable bidirectional rendering. Existing Sudanese-law-first clause wording, NGO safeguarding terms, draft reservation, overlap guard and non-activation workflow remain intact. No schema change was made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY VERIFIED.** The reported PDF also showed an apparently blank contract number and inconsistent Latin employee-name order. The source now explicitly presents the reserved number and isolates Latin text, but only a fresh local PDF can confirm TCPDF renders these correctly. User must pull `main`, lint `modules/hr/contract_builder.php`, and generate a PDF for an employee/date range with no overlapping draft/active contract. Inspect cover, number, name consistency, currency/date direction, page breaks and signature page. Do not create another draft for a covered period, bypass overlap protection, or claim runtime success before evidence.

## 2026-10-21 — Contract PDF aligned to existing voucher layout (source committed; runtime pending)

The user explicitly requested the employment contract PDF follow the general layout/design of existing financial vouchers. The concrete source reference is `modules/accounting/voucher_print.php`: bilingual organization identity, boxed document-number/date panel, strong navy title bar, structured bordered/shaded information tables, consistent accent colors, and clear signature blocks. Commit `1cd7a318cb1f6075f66badfe235ba758aab4b527` updates `modules/hr/contract_builder.php` to remove the standalone decorative cover page and instead start the contract with a voucher-style title/number/date panel and employee/position/date summary, apply the voucher navy (#1b4d8f), muted shaded table labels and gold (#9a7628) accent to clause headings and signature tables, and use the same official Arabic/English organization names as the voucher print page. The legal clauses, draft reservation, overlap guard, and explicit activation workflow remain unchanged; no schema change was made.

**Verification status: SOURCE COMMITTED; PHP lint and actual local PDF rendering NOT verified.** User must pull `main`, lint `modules/hr/contract_builder.php`, then inspect a valid existing draft/eligible preview without generating another contract for an overlapping employee/date range. Confirm there is no oversized standalone cover, contract number/date panel renders correctly, header and names match the voucher branding, clause bars/tables are readable, no warning appears, and the final signature page remains usable. Do not claim visual acceptance until the local PDF has been inspected.

## 2026-10-21 — Employment contract branding correction (source committed; local PDF verification pending)

After the user reported that the revised PDF still looked unprofessional and had no visible organization logo, source inspection found the contract depended on TCPDF's generic header for the logo while the main contract body did not explicitly embed it. The PDF also included an internal HR job-description review warning inside the employee-facing contract. Source commits `7619773e52cb2e62bcde0721fa750b7913161689` and `19a79e00d2747616609edbd5a82b36a9b66cfc96` now use the same repository logo as the voucher print page (`assets/img/logo.png`), explicitly embed a bilingual branded letterhead in the contract body and signature page, avoid a duplicate generic TCPDF header, and remove the internal review warning from the contract PDF. HR review status remains a builder/catalogue concern; this change does not mark any job description approved. No database/schema or contract lifecycle changes were made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY LINTED OR RENDERED.** User must pull `main`, run PHP lint, and inspect the actual PDF before acceptance. Confirm the logo is visibly embedded, there is one clean organization letterhead (not duplicate branding), the contract number/date and Latin text render correctly, the internal review warning is absent from the employee-facing PDF, clause styling and page breaks remain readable, and the signature page is properly branded. Do not create a new contract solely to test if an overlapping draft/active contract already exists; preserve the overlap guard and existing records.


## 2026-10-21 — Active task: contract PDF alpha-PNG error

Source inspection of the current `main` branch found the contract PDF directly embedded `assets/img/logo.png` in TCPDF HTML, while the shared print header and voucher pages use the same PNG through browser rendering. Settings and the contract-builder portal honor `site_logo`. The user confirmed `assets/img/logo.jpg` is the same logo; the PDF letterhead now uses that existing JPEG without changing browser pages, settings, Apache/PHP configuration, database schema, or contract lifecycle. Local PHP lint and actual PDF rendering are still required; preserve the existing contract overlap guard and avoid creating another draft for an already-covered period.


## 2026-10-21 — Second contract PDF design revision (voucher-style hierarchy; runtime pending)

Following user feedback that the employment contract still looked weaker than the existing vouchers, `modules/accounting/voucher_print.php` was re-read as the design reference. The contract's first-page composition has been revised to use a single bordered three-part letterhead (logo, centered bilingual organization identity, and boxed contract number/date), a full-width navy bilingual title bar, a compact employee/position and term summary, and a dedicated highlighted basic-salary band inspired by the voucher amount panel. Clause headings now use a pale-blue panel with navy text and a gold rule instead of a heavy filled bar, to improve readability across long legal text. Existing clauses, job-description content, signature page, logo JPEG compatibility fix, draft reservation/overlap guard, and activation workflow are preserved. No schema or accounting changes.

**Verification status: SOURCE COMMITTED; local PHP lint and actual PDF visual inspection are still pending.** The rendered PDF must be inspected before deciding whether this revision is accepted; do not create another overlapping contract draft just to preview the design.

## Latest employment contract PDF layout follow-up — 2026-10-21

Commit `ddffae6105a2da56bce1b5d706bc7d40e1f94ba2` removes the letterhead from the signature page and tightens PDF typography, margins, table padding and section spacing to target exactly two pages total. Runtime verification is pending. Next: pull `main`, lint `modules/hr/contract_builder.php`, generate/inspect an eligible contract using the configured XAMPP URL, and confirm one header on page one, no repeated header on page two, complete text, readable layout and exactly two pages. Do not bypass the existing draft/active overlap guard or claim runtime success before local evidence.

## Latest contract PDF layout change — 2026-10-21

Inspect commit `f30fd321ae7932daa2e87b7b70be8e3f97d437a3`: contract header now follows the compact voucher composition more closely, with a 24px logo, narrower side cells, compact number/date panel and title, and tighter body spacing. Header only appears on page one. Pull `main`, lint `modules/hr/contract_builder.php`, then inspect the real PDF via the configured XAMPP app and confirm exactly two pages with readable legal text. Runtime acceptance is pending; don't claim page count before evidence.


## 2026-10-21 — Contract PDF header proportion correction

Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` narrows the PDF logo to 14 mm, gives it a 12% header cell, retains the 24% number/date panel, sets the organization column to 64%, and reduces header padding to match the compact voucher composition. No legal wording, schema or contract lifecycle behavior changed. Pull `main`, run `D:\xampp\php\php.exe -l modules\hr\contract_builder.php`, then verify the generated PDF locally using an eligible existing contract period. Do not create a duplicate/overlapping draft. Two-page layout and readability remain unverified.

## 2026-10-21 — Latest narrow UI change: contract-builder error toast
`modules/hr/contract_builder.php` now displays caught generation errors in a fixed-position RTL toast (close button, seven-second auto-dismiss) instead of an in-flow alert. Validate locally with PHP lint and browser interaction. Do not alter the one-use token, CSRF, transaction, overlap guard, draft reservation or PDF flow when verifying. A fixed toast avoids layout shift but does not cancel native full-page navigation.


## 2026-10-21 — Contract-builder form response handling
Commit `bd088d1f4d090506df48eecf666c270add50f91b` fixes the cause of validation messages appearing after a page jump: the contract form's `_blank` target sent error HTML into a separate document. The local handler now renders server errors as toasts in the current form page and opens only actual PDF responses in a new tab. Verify PHP syntax, missing fixed-term end date, stale token, other server errors, and valid PDF behavior locally. Do not change global POST interception or contract business rules without new evidence.


## Current Contract Builder state — 2026-10-21
Do not continue the toast-positioning experiment. The user requested restoration to the source checkpoint where the voucher-style PDF design was completed. Commit `d074486b7cff1a204ab3bf353c829a59ecef32f7` restores `modules/hr/contract_builder.php` to `e7f96e8` and removes the later toast/fetch experiments after malformed `.cb-actions` markup was identified in `e41cadc`. Next verification belongs on the real local XAMPP app: run `php -l modules/hr/contract_builder.php`, then confirm existing-contract viewing and creating a contract for a current employee, and inspect the generated PDF. Do not claim verified until those checks actually pass.

Correction to rollback record: the complete 799-line checkpoint source was re-applied and checked against the same 799-line source fetched from `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. Final source commit: `7df2bc435d2a6464692895db62cb97ec03a2f109`. The complete file ends with the shared footer include; the PDF submit button and return link are separate. This comparison establishes source equality with the checkpoint, not local PHP/browser runtime success.

## 2026-10-21 — Employment-contract PDF download filename hardening

The user reported that using Download in the browser PDF preview saved the contract with a `.php` extension. Source inspection confirmed the PDF response already used `Content-Type: application/pdf` and an inline `filename="Ahl-El-Kheir-Employment-Contract.pdf"`, so the root cause is not proven without inspecting the actual local HTTP response headers. Commit `1cf3fb0b753b30b49ba5f950498ff534fb802de2` makes the response more robust by clearing any active output buffers immediately before PDF headers/body, and supplying both the ASCII `filename` and RFC 5987 `filename*` parameters while preserving `inline` preview behavior. This is source-only. After pulling `main`, run PHP lint and generate a PDF in the local XAMPP app; use the PDF viewer's Download control and confirm the saved file ends in `.pdf`. If it still saves as `.php`, inspect the actual response headers in browser DevTools before changing more code. Do not claim runtime success before this check.

### 2026-10-21 — Current Contract Builder toast checkpoint

The source-level error-toast change is committed on `main` as `197f53d75cf4546e8efd6a483e9ea850a18916b5`. Root cause: PDF form `target="_blank"` caused caught server errors to render at the top of a new full page. The error response now posts to the original page, which shows a toast near the initiating button and avoids navigating/scrolling the original form. Successful inline PDF response is unchanged. Next: user pulls `main`, runs PHP lint, and browser-tests one error case and one successful PDF preview. Do not claim runtime verification until user confirms.

### 2026-10-21 — Contract Builder toast correction checkpoint

Current source commit: `890b9ef95390af64820083a8567c6e5e7fc2ccb0`. The prior opener-message approach failed in browser testing; errors still rendered in the new tab. Replaced with original-page fetch submission and JSON error responses. Errors close the temporary preview tab and toast beside the clicked button; successful PDF responses open in the preview tab. Next: user pulls `main`, runs PHP lint, tests missing fixed-term end date and a successful PDF preview. Do not claim runtime verification until confirmed.

## 2026-10-21 — Contract Builder toast changes rolled back

Current source commit: `8af298793a4c86d38d303516b2b03992b7258405`. `modules/hr/contract_builder.php` has been restored exactly to pre-toast checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b` after the latest fetch-based attempt produced a local Apache “Not Found” error. Do not continue toast experiments unless explicitly requested. Local PHP lint and browser/PDF behavior remain unverified; ask the user only to pull `main` and confirm the pre-toast contract workflow is restored.

## 2026-10-21 — Authoritative Contract Builder continuation state

This checkpoint supersedes earlier entries that proposed further toast/fetch changes or asked to test those experiments. The latest documented rollback is commit `8af298793a4c86d38d303516b2b03992b7258405`: `modules/hr/contract_builder.php` was restored to the pre-toast voucher-style PDF checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`; its source blob was reported to match that checkpoint. Do not reintroduce or continue the toast-positioning/fetch experiment unless the user explicitly requests it.

This is source/checkpoint evidence only, not runtime acceptance. Local XAMPP PHP lint, existing-contract viewing, contract creation through the normal workflow, and generated PDF behavior have not been confirmed after the rollback. If verification is requested, use the configured app URL `http://localhost:8081/AhlElKheir/`, preserve the existing draft/active overlap guard, and do not create a duplicate contract merely to preview the PDF. Record only observed results; do not claim runtime success in advance. Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — Contract Builder toast handling with native PDF response

The user explicitly resumed the Contract Builder toast task and requested that its feedback match proven notification patterns elsewhere in the application. The existing `modules/hr/job_description_catalogue.php` pattern uses a fixed-position RTL toast with accessible live announcement, dismiss control, and five-second auto-dismiss. `modules/hr/contract_builder.php` now applies that toast presentation to caught generation errors on the original form page.

Source commit: `54d4cef36e58695faea509a61b2a0fc5232589bb`. The form targets a named preview window (`contractPdfPreview`); the server returns caught generation errors as a small same-origin response that posts the message to the opener and closes the preview window. Successful generation continues to return the original native `application/pdf` response with its existing inline filename headers, avoiding a fetch/blob preview that could alter download naming. Contract validation, one-use submission token, CSRF handling, transaction/rollback, overlap protection, and PDF generation logic were not intentionally changed. The interim fetch/blob implementation in commit `572ccf46ea16b856592885d492ee6b4b9fc0a30b` was superseded by this native-response approach.

**Verification boundary: SOURCE COMMITTED; local PHP lint and browser behavior are not yet verified.** On the configured XAMPP app (`http://localhost:8081/AhlElKheir/`), first test one controlled server-side validation failure and confirm the toast appears on the original form page without navigating it or leaving an error page open. Verify that the named preview window closes after delivering the message. Check PHP syntax. Test a successful PDF response only when creating the corresponding contract draft is intentional; do not create duplicate/overlapping drafts merely to test preview. Confirm the browser still receives the native PDF response and filename headers. Do not mark this fixed until actual local evidence is available.


## 2026-10-21 — Fixed-term missing end-date error stays on the form

After the user confirmed that the exact validation message `تاريخ نهاية العقد مطلوب للعقد محدد المدة.` still rendered in the preview tab, source inspection found that the submit handler opened `contractPdfPreview` before PHP performed this deterministic validation. The popup response's `window.opener.postMessage()` path had not delivered the message in the user's browser. Commit `c1b80acd47904f91e6d11e615e19de740138ddb5` adds a narrowly scoped pre-submit check for `contract_type=fixed_term` with an empty `end_date`; it prevents submission/opening the preview tab and uses the existing toast on the original page. The authoritative PHP validation remains unchanged.

**Verification boundary:** source committed to `main`; local PHP lint and browser behavior are not yet verified. Test this exact missing-end-date case first: the original page must remain in place, show the toast, and open no new tab. This change only covers the confirmed validation case; other server-side generation errors still need separate runtime diagnosis and must not be described as fixed by this narrow change. Successful PDF generation and existing contract safeguards were not intentionally changed.


## 2026-10-21 — Contract Builder error routing and stale-token recovery (supersedes earlier toast attempts)

User runtime feedback showed that the fixed `contractPdfPreview` browsing-context name could navigate the current tab when that tab already had the same browsing-context name; the resulting server error page was not consistently reaching the form toast. Source inspection also confirmed that successful PDF generation intentionally unsets `ak_contract_builder_submission_token`, leaving the still-open form with a consumed token until it is refreshed.

Commit `c89e22386f921c339b8965de1cf49fd07a11f25b` changes the form to open a fresh, unique preview-window name for each submission while retaining the native form POST and native PDF response (including the inline filename header). Server-side errors send the error and the current/reissued form token to the opener via same-origin `postMessage`; the original page displays the existing toast and refreshes its hidden token. The deterministic missing-end-date check remains before opening any preview window. The AJAX/blob implementation from intermediate commit `dc6a80b0dfc9c086c5cc0346d028b6b7bc872b4b` was superseded to preserve native PDF response/download behavior.

**Verification boundary:** static source inspection confirms the unique preview target, error message/token payload, client token refresh, unchanged native PDF `Content-Disposition`, and preserved one-time-token consumption after success. Local PHP lint and browser runtime are not available from this remote source session; do not call this resolved until the exact local acceptance test passes. Test (1) fixed-term contract with empty end date: toast on original page, no preview tab; (2) server-side token/validation error: toast on original page, error tab closes, no white/error page; (3) successful PDF: opens in separate tab with native PDF response/filename. Do not create duplicate contract drafts solely for testing.

## 2026-10-21 — Contract Builder toast task closed at user-requested boundary

The user confirmed that the error toast now appears after completely closing the page and logging out/in again. On a further check, the user clarified that the page does **not** jump/scroll upward; instead, there is a very brief refresh-like visual effect before the toast appears.

The user explicitly instructed us to stop here and make no further changes. **Task status: CLOSED BY USER REQUEST; residual visual behavior is documented, not diagnosed or claimed fixed.** No further source edits, experiments, or speculative scroll/focus/navigation workarounds are authorized for this task unless the user explicitly reopens it. Do not reopen automatically in a new session.

No cause has been established for the brief refresh-like effect. The current observed behavior is the acceptance boundary the user chose; it is not evidence that the underlying visual effect was technically eliminated. No local runtime or PHP-lint result is newly claimed by this documentation update.

For the next session, read the canonical project documentation and inspect current `main` to determine the next genuinely open task or follow the user's new requirement. Do not resume Contract Builder toast/scroll work unless explicitly requested.

## 2026-10-21 — HR contracts employee details moved into a modal

User request: on `modules/hr/contracts.php`, selecting an employee should open the employee's contract/salary area as a popup, and the employee list should expand to use the width previously occupied by the two-column list/details layout.

Source commit: `29f650e189f748777427ac90eafc630feac58a18` (on `main`).

Implemented in `modules/hr/contracts.php`:
- Replaced the narrow left-hand employee sidebar plus always-visible details panel with a full-width responsive employee-card grid.
- Selecting an employee loads that employee's existing `employee_id` URL and automatically opens a wide, scrollable Bootstrap modal.
- Moved the employee-specific tabs and existing sections into the modal: `العقود`, `سجل الرواتب`, `إعداد عقد جديد`, and `تغيير الراتب`.
- Kept the existing contract status actions, signed-copy upload, salary history, salary-change form, employee/contract queries, permissions, and business rules in place; no schema or migration change.
- Search continues to filter employee cards and now shows an empty-results message. The modal is reopened after the existing form POST reload so the user can see the result/error inside the employee context.

**Verification boundary:** source change committed to `main`; no local XAMPP/PHP lint or real-browser runtime verification is claimed from this session. After pulling, check PHP syntax and test employee selection, all four modal sections, search/no-results, contract actions/upload, salary history/change, and that success/error feedback remains visible inside the modal after POST. Do not claim runtime verified until these checks pass.


## 2026-10-21 — HR contracts modal parse error corrected

After the user pulled the employee-details modal change, local PHP lint reported an unexpected end of file on line 134, expecting `elseif`, `else`, or `endif`. Source inspection identified the exact cause: the employee-selection `if ($emp)` alternative-syntax block had no closing `endif`; the existing terminator closed the nested view switch instead. Commit `decb8a577df182396def4eea906220bd4b4b009a` adds the missing outer `endif` in `modules/hr/contracts.php` without changing application logic.

**Verification boundary:** source correction is committed to `main`; the user's local PHP lint has not yet been rerun, and browser/runtime behavior is not verified. After pulling, run `php -l modules\hr\contracts.php` and report the exact result before browser testing. Preserve the existing untracked `storage/hr_contracts/` directory; it was not modified or included in the commit.

## 2026-10-21 — Current HR contracts modal checkpoint

Employee selection was refreshing the page without opening the employee-details modal. Source inspection found modal initialization ran before Bootstrap JavaScript was loaded by `includes/footer.php`. Commit `4fe5b9baaa09830c3bfc06b37c18c3f080660f45` defers initialization until `DOMContentLoaded`. Local lint/browser verification is pending. Preserve untracked `storage/hr_contracts/`; next run PHP lint and test employee-card selection plus modal sections. Details in `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR contracts latest checkpoint

The employee picker is now full-width list rows (not cards), commit `7464fc22c31f613fcf26f3c33f32ca3d92be491c`. Modal initialization timing fix: `4fe5b9baaa09830c3bfc06b37c18c3f080660f45`. Local PHP lint/browser verification pending. Preserve untracked `storage/hr_contracts/`. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## Latest HR employment-contract UI checkpoint — 2026-10-21

Source commit `40f1198a3b0c54b022b2154b55a12dfce345b730` updates `modules/hr/contracts.php` to suppress footer elements inside the employee modal and to load internal contract/salary tabs via fetch without a full-page navigation. Contract Builder remains a separate page. Local PHP lint and browser verification are still required; do not claim runtime success until checked in the real XAMPP app.
Follow-up commit `889a10e6f7368190f8e78a9d356a7fabd638b1c6` hides the shared page footer while the employee modal is open and restores it on close. Local runtime verification remains required.

## 2026-10-21 — Secure viewing of uploaded employment contract copies

Root cause confirmed from repository source: `storage/.htaccess` contains `Require all denied`, which correctly blocks direct HTTP access to `storage/hr_contracts/`. The contract register previously linked directly to `APP_URL . contract_file_path`, so Apache returned `403 Forbidden`.

Source changes committed on `main`:
- `773229e8753979ecfa66ec588b8626b96d1325a8` — change the contract-file link to `modules/hr/view_contract.php?contract_id=...`.
- `a6576eb379988d11f8a112f9c6c073a14ee84c2a` — add the protected viewer. It applies the same HR manager / HR staff / admin role gate as the contract register, resolves the file through the existing contract record, confines real paths to `storage/hr_contracts/`, validates allowed extension and detected MIME type, and streams supported files inline. The storage-wide deny rule is intentionally unchanged; no schema change or migration is needed.

**Verification status: SOURCE COMMITTED; local PHP lint and browser verification remain pending.** After pulling `main`, lint both `modules/hr/contracts.php` and `modules/hr/view_contract.php`, then open an uploaded PDF from the HR contract register while logged in with an allowed HR role. Also verify the raw `storage/hr_contracts/...` URL remains forbidden and a logged-out/non-HR request to the viewer is denied.


## 2026-10-21 — Contract preview, print, and download controls

The HR contract file route now defaults to an authorized HTML preview page rather than returning the file response as the top-level page. The preview embeds the protected file endpoint, provides an explicit **طباعة** action for PDFs and an explicit **تنزيل الملف** action, and retains **فتح الملف في تبويب جديد**. The file endpoint uses `Content-Disposition: inline` by default and switches to `attachment` only when the explicit download action is requested. Storage remains protected by `storage/.htaccess`; no database/schema changes were made.

Commit: `ac91f1b65f87300114f53ceeae98b28734f90d64`.

Verification boundary: source is committed to `main`; local PHP 8.2 lint and browser behavior are not yet verified. In particular, browser PDF handling can still depend on client settings, so confirm the embedded PDF preview and print/download controls in the local Chrome/XAMPP environment before calling this runtime-verified.


## 2026-10-21 — Reverted contract preview wrapper

At the user's request, removed the additional HTML preview wrapper and restored the prior direct authorized file response in `modules/hr/view_contract.php`. The HR contract register continues linking to that protected route; `storage/.htaccess` remains restrictive. The route sends the file with `Content-Disposition: inline`, allowing the browser's PDF viewer/settings to determine inline display versus download. No database/schema changes.

Commit: `e3b458eb57e618acaf8c8bb0a06df79cd02ece7e`. Local PHP 8.2 lint and browser behavior remain unverified.


## Active continuation — Nanny group bulk verification (2026-10-22)

Continue the existing implementation in `modules/accounting/group_verification.php`; do not rebuild or change schema speculatively. The feature supports bulk marking of one chosen check or all three checks for selected families, preserves unselected flags, and writes a per-family audit row transactionally. Assignment, family-in-group, and pending-group constraints are checked server-side. See `docs/ACCOUNTING_NANNY_GROUP_VERIFICATION_CONTINUATION.md`.

GitHub Actions run `39715384849` passed the `php-lint` job for this page. A separate existing Stage 5 salary-advance payroll integration job failed because its expected payroll-transfer migration was not applied (`يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع`); do not reopen that closed module for this task. Next ask the user to pull `main` and perform the documented acceptance checks against their current local application/database. Runtime acceptance is not yet proven; do not claim closure or create disposable databases.


## 2026-10-22 — Nanny group verification follow-up

Commit `e50686f0a8492506459e4465513d03778debc498` adds the individual POST pending-state guard; follow-up commit `da5320d05e2656bb9d6e1f4a7a94b94b032fd177` corrects the markup so individual verification controls are only rendered for pending groups. Both the UI and server now restrict individual verification to pending groups with active nanny assignment and valid family membership. Next: run PHP syntax verification and local browser/database acceptance on the existing `ahl_el_kheir` environment only. Preserve existing records; do not reopen unrelated modules or create a disposable database. PHP lint passed in GitHub Actions run `39716749893`; local runtime verification remains pending.

## 2026-10-22 — Nanny preliminary monthly family verification

Added `modules/accounting/nanny_pre_verification.php` as a separate Nanny workspace and linked it from the Nanny sidebar and the existing group-verification page.

The page reuses `nanny_family_verifications` for the displayed payment month (`YYYY-MM`); it introduces no separate preliminary status and requires no schema migration. Added English compatibility translations for the new Arabic UI strings in `lang/bridge/ar_to_en.php`. Individual and bulk changes use the existing three flags and append per-family `verification_audit_log` records transactionally. Single-check bulk operations preserve the other flags; individual review can explicitly change or clear flags. The bulk selection is fully validated before writes.

Server-side scope is limited to the logged-in Nanny's active assignments and pending groups. Because the schema stores one verification row per family/month rather than per group, families also present in active non-pending groups are excluded to avoid modifying a shared record already used by a submitted/locked group. This page does not submit groups to Accounting.

**Verification boundary:** source committed to `main`; PHP 8.2 syntax lint PASS in GitHub Actions run `39719045605` (the `php-lint` job explicitly includes `modules/accounting/nanny_pre_verification.php`); local XAMPP/browser/database acceptance remains pending. Use the existing `ahl_el_kheir` database only; do not create a disposable database or reset operational data.


## 2026-10-22 — Nanny monthly verification scope and month selection

The preliminary verification page now accepts a validated `YYYY-MM` month and lists all active families with active children in active groups currently assigned to the logged-in Nanny, regardless of each group's global verification status. Eligibility and save-time authorization use the same assignment/family scope; monthly values remain in the existing `nanny_family_verifications` rows keyed by family and month, and all changes retain transactional per-family audit logging. No schema or existing data changes were made.

The group-verification page now uses the selected month for reads and writes, carries it through navigation and POST forms, and allows the assigned Nanny to review/edit that month's verification flags for a family in any active assigned group. The group itself can still be submitted only under the existing pending-state rule; downstream submission/accounting behavior was not changed.

Source commits: `53190c05f7f4ba4bcfd0952e8a5fc17129d8a1f3`, `fd5d9c70e7b1c0be75e7a10b8823982f79f30f95`, `23722801177e921a82850b9c8ff15fcd6838fe55`, `7198ef5a3a488a404dfb73b41bc2c72e73def6a1`. Static/runtime verification is pending; do not claim the change is runtime verified until PHP syntax and local XAMPP/browser acceptance are confirmed.


## 2026-10-22 — Shared family/month verification visibility correction

Source commit: `f108ac6f43a71b210518e8d932977e3f0a1ccd11` updates `modules/accounting/lib_group_workflow.php`. The existing schema stores one verification record per family/month, but `get_latest_verification_status()` also filtered by the last verifier's `nanny_id`. That made the same shared family/month record appear unverified when read by a different Nanny assigned to another group containing that family. The lookup now uses the family/month key; `nanny_id` remains attribution for the latest verification action, not a visibility filter.

No schema or data changes were made. This affects the preliminary Nanny page and group verification reads that share this helper. **Verification boundary:** source committed to `main`; PHP lint, relevant regression checks, and local XAMPP/browser acceptance are still pending. Verify that the same family's flags for a selected month remain consistent between the preliminary page and each assigned group, including when the record's last verifier differs, and that another month remains independent. Do not claim runtime acceptance until tested against the existing `ahl_el_kheir` database.


## 2026-10-22 — Monthly family verification selection follow-up

After the user reported that the bulk-verification UI still showed “لم يتم تحديد أسر” after selecting families, `modules/accounting/nanny_pre_verification.php` was updated in commit `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`.

The page now mirrors checked family IDs into hidden controls physically inside `bulkVerificationForm`, synchronizes that mirror when individual or select-all checkboxes change, and uses the synchronized selection count for confirmation. After confirmation it uses `requestSubmit()` where available so the shared same-page POST handler can process the request rather than bypassing the submit event with `form.submit()`.

No schema/database changes were made. Source-level implementation is committed; PHP syntax check, browser behavior, and real local XAMPP/database verification are **pending**. The user will pull `main` and perform the local acceptance steps; do not mark fixed until the bank-only operation succeeds and preserves the orphan flag.


## 2026-10-22 — Closed-group verification controls refined for Nanny workflow

The user clarified that a Nanny may reconnect with a family later and need to reverify that family for an older payment month, even when the group is already closed. Commit `821774a06aa6434a31bc4538bfe37217ebafe55c` supersedes the earlier overly restrictive closed-group UI rule in `modules/accounting/group_verification.php`:

- Individual family confirmation/edit actions remain available for any selected active group still assigned to the logged-in Nanny, including a closed group, and the individual POST guard permits this same assigned-group workflow. The selected payment month continues to scope the family/month verification record.
- Bulk verification controls and family-selection checkboxes remain available only when the group status is `pending`. Bulk POSTs still require pending status, so a closed/processed group cannot be bulk-modified.
- Group submission remains pending-only. No group status, schema, or verification data was changed by these source edits.

This correction supersedes the preceding note that all closed-group individual controls should be hidden. The user must pull `main`, run PHP lint on `modules/accounting/group_verification.php`, and browser-test: closed group + historical/future month shows individual actions but no bulk controls; pending group shows both individual and bulk controls; individual save affects only the selected month; bulk POST against a non-pending group is rejected. Local runtime acceptance remains pending.

## 2026-10-22 — Nanny monthly group verification acceptance CLOSED

The user pulled the latest `main` (`c3493831`), ran `D:\\xampp\\php\\php.exe -l modules\\accounting\\group_verification.php`, and confirmed **no PHP syntax errors**. The user then confirmed the updated workflow behaves as intended. This closes the current Nanny verification-control acceptance at the user's reported boundary:

- The selected payment month scopes the shared family/month verification record.
- Individual family verification remains available in an active group assigned to the Nanny, including a closed group, so a family can be reverified for a historical payment month.
- Bulk selection and bulk verification remain pending-group-only; non-pending bulk POSTs are rejected server-side.
- Group submission remains pending-only.
- No schema migration or database/data change was part of this correction.

Source correction: `821774a06aa6434a31bc4538bfe37217ebafe55c`. Bulk-selection synchronization fix: `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`. Related documentation has been updated to record this user-confirmed acceptance. Do not reopen this workflow without concrete regression evidence.

**Next task from the remaining open documentation:** continue the HR employment-contract portal's pending local verification, starting with the latest `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md` instructions and current `main` source. Do not resume the Contract Builder toast/brief-refresh visual issue; the user explicitly closed that task.

## 2026-10-22 — HR job-description migration safety review

Before asking the user to apply the pending job-description migrations, source review found a concrete data-preservation risk in `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql`: its qualification-baseline update could change an already `approved` active description and downgrade its status to `needs_review`, contrary to the documented preservation boundary. Commit `0e7ec314d4335d2ff3428640b76b6c18629fc86b` adds `AND description_status <> 'approved'` to that migration's update predicate. This is a migration-file correction only; no local database was accessed or changed. Existing approved descriptions are now excluded from this baseline update. Do not claim the migrations are applied or the portal runtime-verified. The remaining work is to inspect the entire migration sequence against the current schema and actual local database state, then perform the local application and focused HR portal checks without touching unrelated pages or existing contract files.

## 2026-10-22 — HR portal pre-migration safety guard

Source commits `4173bb3d1a42a2cc95e796e5d66a3d9e0134d0f9` (`modules/hr/contract_builder.php`) and `5258fa82988902811212544d2a01ef70d8e2e45b` (`modules/hr/job_description_catalogue.php`) add a read-only `information_schema.tables` preflight for `hr_job_description_templates`. If the migration has not been applied, Contract Builder now continues with its existing employee/contract fields and manual job-description entry, shows a warning that automatic catalogue prefill is unavailable, and avoids querying the missing table. The new catalogue page displays a clear HTTP 503 migration-required message instead of failing on downstream SQL. This guard performs no DDL and changes no business data. When the table exists, the normal catalogue query path is unchanged.

These are source-only changes committed on `main`; PHP lint and local XAMPP behavior are not yet verified. Next: check the relevant GitHub Actions run, then complete the migration-order/data-preservation audit and the local migration + HR portal acceptance. Do not mark runtime verified without local evidence.

## 2026-10-22 — HR job-description migration safety follow-up

- Audited the eight `2026-10-20_hr_job_description_*` migrations for preservation of approved catalogue records.
- Corrected `2026-10-20_hr_job_description_proposals.sql` so its proposed-field backfill and HR Test deactivation skip rows with `description_status = 'approved'`. The qualification baseline migration also excludes approved rows. No database was changed by this repository edit.
- Expanded `.github/workflows/php-syntax-check.yml` to PHP-lint `modules/hr/contract_builder.php` and `modules/hr/job_description_catalogue.php`.
- Latest full-schema CI run imported the checked-in schema and passed its migration/backfill step, but failed in the subsequent Stage 5 salary-advance regression step. This is not evidence of an HR migration failure; the exact regression failure remains to be diagnosed before treating the whole workflow as green.
- Local XAMPP/MariaDB migrations and browser/PDF verification remain pending. Back up `ahl_el_kheir` before applying migrations; do not run DDL from PHP. Preserve local untracked files.

## 2026-10-22 — HR migration CI coverage

- Added the `hr-job-description-migrations` CI job in `.github/workflows/php-syntax-check.yml`. It imports the checked-in schema into a disposable MariaDB 10.4.32 instance, applies all eight `2026-10-20_hr_job_description_*` migrations in filename sequence, and asserts that an approved HR-manager title-only catalogue row retains its approved status and blank content fields.
- The same workflow now PHP-lints `modules/hr/contract_builder.php` and `modules/hr/job_description_catalogue.php`.
- Repository-side safeguards are committed; local `ahl_el_kheir` remains untouched. Do not apply the migrations locally until the CI migration job completes successfully and a backup is available. Local page, prefill and PDF verification is still pending.

## 2026-10-22 — Latest HR job-description migration checkpoint

The user applied `database/migrations/2026-10-20_hr_job_description_proposals.sql` to the local `ahl_el_kheir` database after making a backup. The command returned no reported SQL error. Verification:
- 23 active `needs_review` entries; 0 have blank `reporting_to`.
- Approved IDs 6 and 20 remain approved and active with reporting lines.
- `HR Test` ID 11 is inactive and remains `needs_review`.

Do not say all eight job-description migrations have been applied: only the proposals migration is confirmed applied in this checkpoint. This verifies the reporting-line result and the listed row safeguards, not every updated field. Local browser/prefill/PDF acceptance remains unverified. Do not rerun the migration or reopen completed unrelated work. If the user starts a new task, follow that new requirement while preserving this verification boundary.


## 2026-10-22 — New user requirement: role-aware AI assistant

The active user-requested task is the Ahl El Kheir AI assistant, superseding the previously listed next tasks until the user changes direction. Read docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md. Initial source is committed on main, but syntax CI and local/hosted runtime verification are pending. Continue by reviewing the committed source, correcting issues found by lint/source audit, adding deterministic tests for role-filtered retrieval/privacy gating, then asking the user to pull main and test on the existing local XAMPP app/database. Do not use a disposable database, add schema, send internal documentation to Google, expose the API key, or enable paid billing.


### 2026-10-22 — AI assistant Arabic retrieval follow-up
The first local browser test showed the assistant UI works, but the Arabic question about the first step after sign-in missed local retrieval and reached the unconfigured-Gemini fallback. Root cause: the canonical employee guide described sign-in only in English while retrieval is keyword-based. The guide now includes a reviewed Arabic/English post-login instruction: confirm the dashboard matches the user's job role, and complete a required password change if prompted. Retest the exact question locally before configuring Gemini. No application logic, schema, or privacy boundary changed.


## 2026-10-22 — AI assistant UI refinement
The assistant panel no longer displays introductory privacy notices or local-guide filenames under answers. The launcher has a Font Awesome comments icon beside its label. Google consent starts checked and the selection persists between messages, while server-side internal/sensitive prompt blocking remains enabled. Source commit: `5149e20b6e42a393ca59fd6d84cec12edaf08372`. After pulling, PHP-lint the widget and test local answers, unchecked-consent fallback, and internal-question blocking. Do not configure paid billing.


## 2026-10-22 — AI assistant follow-up
The assistant launcher now has a gold gradient and improved hover/focus visibility (commit `f2e92ac73692ac95a340c338811f565ed3839df3`). The user requested persistent per-user chat history. Before implementing it, inspect the actual local `users` primary-key definition and schema constraints; the repository schema dump is too large for the current source reader to retrieve. Do not guess FK types or create tables at runtime. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.

## 2026-10-22 — AI assistant follow-up: Arabic Nanny retrieval and voice

The user reported that the post-login Arabic answer worked on a subsequent attempt, but “كيف أستطيع عرض كل المجموعات المخصصه لي” fell through to the unconfigured general-AI response and on-device dictation failed. Source commits on main: `1e0d98874e95b85d6454f007bc89483fb9f24b66` adds Arabic Nanny guide instructions; `cea8e775842f0f7a18ed7d790e5099fa9cbb7fac` normalizes Arabic ta marbuta and blocks Arabic internal-workflow terms from external AI; `57d5cfeb982f9e24459eb79c3fe6d1cf88d12844` displays the actual browser speech-recognition error code. These follow-ups are not yet locally linted or browser-verified. After pull, lint both assistant PHP files, test the Nanny groups question once, then click the microphone once and capture the exact error code. Diagnose voice before changing the recognition method; do not add network-based speech recognition without an explicit privacy decision. Preserve local untracked files and use the existing database only.

## 2026-10-22 — AI assistant launch-readiness follow-up

User priority: prepare a dependable, staff-friendly assistant without exposing technical diagnostics to ordinary users. Commit `dc0e7640fe98cb134590c0ec3e314c80133e16f4` adds a deterministic local Arabic answer for the Nanny assigned-groups question and normalizes role-code case. It remains unverified on the user's local machine. The microphone reports `language-not-supported` for on-device Arabic speech. Do not silently switch to network-based recognition because audio processing is a separate privacy decision. Obtain explicit owner approval, then implement clear first-use consent and a simple typing fallback. Keep existing local-first answer retrieval, role boundaries, Gemini question filters, chat-history ownership checks, and no-paid-billing constraint.


## AI assistant voice continuation — 2026-10-22
Owner-approved scope: online Arabic browser speech recognition is permitted only after a clear consent prompt, with typing fallback. Implementation commit: `18bf40af2f905b5509396d92e17374c0c79ecd7c` in `includes/ai_assistant_widget.php`. Before additional changes, pull main, lint both assistant PHP files, then verify in the real local app: first microphone click shows consent without starting recognition; cancel starts no recognition; agree starts recognition; Arabic speech produces an editable transcript; browser denial/provider failure gives a simple message and typing still works; reload asks for consent again. Do not mark runtime-verified before those checks. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.


## AI assistant voice failure diagnostic — 2026-10-22
The owner reports online Arabic speech recognition still fails after consent. Latest source commit `753a3b225c1a992bcb3a6b1ce627ea365fef3092` adds friendly messages for common recognition errors and console category logging. Pull main, lint `includes/ai_assistant_widget.php` and `modules/ai_assistant/chat.php`, retry the microphone once, and report the visible specific message. If still unresolved, inspect only the `Ahl El Kheir voice recognition error:` category in the browser console. Do not claim runtime success until verified; do not bypass consent or introduce another speech provider without review.
## AI assistant voice transcription checkpoint — 2026-10-22

The owner approved sending recorded audio to Google Gemini for transcription after clear consent before recording/transmission. Commits `d5d91e4` and `d30efdb` implement MediaRecorder capture, authenticated/CSRF-protected PHP transcription, size/MIME/rate limits, and editable transcript insertion without auto-send. Audio is not saved in chat history; the Gemini key remains server-side. This is not yet runtime verified.

Next: pull `main`; run PHP 8.2 syntax checks on `includes/ai_assistant_widget.php` and `modules/ai_assistant/chat.php`; verify consent decline, microphone denial, Arabic/English transcript, manual stop and 20-second cap, editing before sending, API failure, and typing fallback at the real local URL with the existing `ahl_el_kheir` database. Preserve local untracked files and do not enable paid billing.


## 2026-10-22 — Gemini audio transcription diagnostic follow-up

The user-approved MediaRecorder → Gemini transcription path reached the endpoint but returned the generic upstream-failure response in the browser. Source audit found the endpoint was discarding both cURL transport diagnostics and Gemini's structured error response, preventing a root-cause diagnosis. Commit `130af192c49aafae11c1369e7e884e9367016ac8` now logs the HTTP status, cURL errno/error, and a bounded Gemini error status/message to the PHP error log while keeping the user-facing response generic and never logging the API key or audio payload. It also removes the undefined `$lang` reference from the transcription prompt, using one language-neutral bilingual prompt.

**Verification is pending.** The repository connector cannot run PHP lint or exercise the user's local Apache/PHP/Gemini request. Pull `main`, run `D:\\xampp\\php\\php.exe -l D:\\xampp\\htdocs\\AhlElKheir\\modules\\ai_assistant\\chat.php`, then make one short consented recording. If it fails, inspect `D:\\xampp\\apache\\logs\\error.log` for lines beginning `[Ahl El Kheir AI transcription]` and share only those lines; do not share the API key or audio. This is diagnostic instrumentation, not a claim that transcription now succeeds.

## 2026-10-22 — AI assistant audio transcription continuation

Current status: **UNRESOLVED / DIAGNOSTIC UX IMPROVED, NOT RUNTIME VERIFIED**. The endpoint's JSON HTTP 502 is application-generated when cURL fails or Gemini returns a non-2xx response. The precise provider/network cause has not been established. Commit `3b7cc562c57b87533678146412cf0f44f5b1ca7c` adds plain-language failure categories; detailed provider errors remain server-side and API keys/audio are not logged. Next step: pull `main`, make one short recording after consent, and report only the friendly message shown by the assistant. Use that category to target the actual cause. Do not request browser DevTools/log inspection from the user unless simpler options are exhausted.

## 2026-10-22 — Gemini transcription model update

The user reproduced the model-setup error. Root cause identified from source plus current Google documentation: the endpoint defaulted to gemini-2.5-flash, which was shut down for the Gemini Developer API on October 16, 2026. Commit b4b7c6e7a3069a0c8d5eb254b37db9d9941f5927 changes the default to gemini-3.5-transcribe. Before declaring success, pull main, run PHP lint, and test one short consented recording. Check for an explicit GEMINI_MODEL environment variable or hosting.php ai.gemini_model override only if the new default still returns NOT_FOUND. Status remains unverified until real local test passes.



## 2026-10-22 — Gemini transcription API contract correction

The user tested the updated dedicated model and received “No usable transcript was returned.” The endpoint had been changed to `gemini-3.5-transcribe` but still called the legacy `generateContent` endpoint and parsed the legacy `candidates[0].content.parts[0].text` response. Official Google documentation specifies that Gemini 3.5 Transcribe uses `POST /v1beta/interactions`, accepts audio content with `type`, `data`, and `mime_type`, and returns the transcript in `output_text` (or text content within response steps). This explains the observed successful upstream response with an empty transcript under the old parser.

Commit `7f1ab66a8e8b386eca3bfa4d0482138d82658241` changes the audio request to the Interactions API, uses its `transcription_config.mode=smart`, maps browser `audio/mp4` to the API's documented `audio/m4a` MIME type, and reads `output_text` with a steps-content fallback. It preserves explicit consent, the editable composer, server-side API-key handling, existing size/rate checks, and typing fallback. Official reference: https://ai.google.dev/gemini-api/docs/transcribe and https://ai.google.dev/api/interactions-api-v1.

**Verification status: source correction committed on main; local PHP lint and one real-browser Arabic/English transcription test are still pending.** Do not claim the transcription path is fixed until the user confirms that words appear in the editable composer without automatic submission. If the next test fails, diagnose the exact new user-facing response and existing server log evidence before changing code again.


## 2026-10-22 — Gemini transcription still returns empty text

After the Interactions API request/response correction, the owner tested both Arabic and English and still received the no-usable-transcript message. The precise provider response shape remains unverified. Commit `1b733cc0fef5aeca86b3753230afced29429509d` adds safe response-shape diagnostics only (JSON validity, top-level keys, interaction status, output text length, and step/output counts); it never logs the audio, API key, or transcript. The next action is one local pull, PHP lint, one short consented recording, then inspect only the matching `[Ahl El Kheir AI transcription] Empty transcript response shape:` line from Apache's error log. Diagnose that evidence before changing request structure again. Runtime success is not established.

## 2026-10-22 — AI assistant voice transcription continuation

Current code commit: 87c37e4238858b6e126382f41476db8f49929e18. Gemini 3.5 Transcribe now follows the documented REST flow: upload the consented audio via Files API, then pass its URI to Interactions API. Next: user pulls main, runs PHP 8.2 lint on modules\ai_assistant\chat.php, then performs one short consented browser recording. Confirm the transcript is editable and not auto-submitted. If it fails, inspect only matching [Ahl El Kheir AI transcription] lines in Apache's error log; startup notices are not relevant diagnostics. Do not claim runtime success before user verification.
The explicit voice-consent prompt also discloses possible temporary Google Files API retention of the audio for up to 48 hours (commit f89eaf85bc3b91389ab1c11abe541c02a21abfb4). Verify the prompt in the browser as part of the next one-recording acceptance test.


## 2026-10-23 — AI assistant continuation
Current task boundary: text chat is the supported path; voice is deferred and the microphone control is disabled. General-text default model is `gemini-3.5-flash-lite`, but local `GEMINI_MODEL`/`hosting.php` overrides must be checked. Next: pull `main`, lint `modules/ai_assistant/chat.php` and `includes/ai_assistant_widget.php`, then perform the documented local text acceptance checks. Runtime status is pending; do not claim fully verified.

## 2026-10-23 — AI assistant conversation continuity
The latest requested widget behavior is committed on `main`: current conversation ID is tab-scoped in `sessionStorage`, the conversation transcript is restored through the existing owner-checked history endpoint after full-page navigation, explicit New chat clears the active conversation, and the panel closes on `mouseleave` like the sidebar. Commits `9db6c37945f2af5e775dc4f9bf651acee7d657b7` and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`. Next step: user pulls `main`, lints the two PHP files, then verifies transcript restore/continued messages, New chat, and mouseleave close/reopen in the local XAMPP app. Runtime status is pending.

## 2026-10-23 — AI text chat task boundary and next-session rule

The text-chat implementation task is closed at the source/documentation boundary. Active conversation continuity across page navigation, explicit New chat separation, and panel close-on-mouseleave are committed on `main` (commits `9db6c37945f2af5e775dc4f9bf651acee7d657b7` and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`). Text fallback default and voice deferral are recorded in commits `2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b` and `75c52595eef9cd6dd2d8ab71756f06103601c1c8`.

Do not claim local runtime acceptance for PHP lint, cross-page restoration, New chat, or mouseleave unless the owner has confirmed those checks. If outstanding, use the short checklist in `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`; otherwise do not reopen this task without concrete regression evidence. Voice transcription remains deferred.

For the next chat, read the four required documents and the continuation document for the relevant module, inspect current `main`, source, schema and latest evidence, then identify the next genuinely open task. Do not preselect a task from stale chat history or automatically reopen any completed module. Existing-project-only, main-only, non-destructive Git, smallest justified change, verification, and documentation rules remain mandatory.

## 2026-10-23 — AI assistant account isolation and VGM guidance follow-up

A reported cross-account restore error and missing internal workflow answer were traced to source. The active conversation key was shared across accounts in one browser tab and was not cleared after a failed restore. Commit `ad6d2c7ede03b7e665ff11b74af55949818b5a76` now namespaces it by signed-in user ID and clears the active pointer after failure. The VGM group/nanny workflow is documented in the employee guide (commit `fb030a08ba65093b51cf31cba4979189457e87bf`) and answered locally only for VGM/vice-GM roles (commit `4fe4a98dc3bcf40e5122c04f069b1f3a40cb99dc`). The canonical role workflow update is commit `9cd98ce9f1b86d521a34e2e8e55dcdc1dd982d40`.

No schema changes. PHP lint and local browser/runtime acceptance remain pending. Before declaring this correction verified, test two different accounts in the same browser, each account's own saved history, a VGM query such as `كيف يمكنني توزيع الأسر على الحاضنات`, and a non-VGM account that must not receive VGM-only steps. Use the existing XAMPP app and inspect exact failures before further changes.


## 2026-10-23 — Final AI assistant acceptance preparation

A static acceptance audit of current `main` found one concrete regression that prevented the agreed voice-input path from being tested or used: the microphone button still had the HTML `disabled` attribute and the widget script explicitly disabled it with the old “planned for a future update” label, despite the consented MediaRecorder → Gemini transcription implementation being present. This stale deferral code made the transcription implementation unreachable from the UI.

The smallest correction removes the stale disabled state and future-development label while retaining the explicit consent dialog, recording limit, authenticated PHP endpoint, editable transcript, and no-auto-submit behavior. No schema/database changes.

**Status:** source-level root cause identified and correction committed on `main` in `67d26422ee41c63f644ba8e05cd6c5ee7d2e623e`. Still pending local PHP lint and real-browser acceptance; repository tooling cannot execute the user's XAMPP app, microphone, session, database, or configured Gemini credentials. The owner should be asked to pull/test only after the remaining source checks and documentation are committed. Required final browser matrix: account switching/history privacy; same-user navigation restore/New chat/mouseleave; VGM versus non-VGM local answer; no-consent and internal/sensitive-question blocking; eligible general fallback only with consent; voice consent decline, microphone permission, short Arabic/English recording, editable transcript/no auto-submit, and text fallback. Do not mark end-to-end complete until these local checks pass.


## 2026-10-23 — Systematic document archive design opened

The new independent task is a systematic archive for receipts and uploaded documents. Read `docs/DOCUMENT_ARCHIVE_CONTINUATION.md` before implementation. The current checkpoint is discovery/design only: no application code, database schema, stored files, or business records were changed. Source inspection found multiple storage roots and module-specific serving routes, plus runtime DDL in `modules/families/documents.php` and `modules/transactions/receipt_file.php`; preserve these as explicit audit findings and do not use runtime DDL. Recommended approach is a central searchable catalogue/version/audit layer, preserving domain authorization and business records, registering historical files in place first, and migrating physical files only after schema and host behavior are verified. Next: finish upload/serving/schema inventory, inspect the actual local `ahl_el_kheir` schema and hosting constraints, then finalize a migration design before implementation.


## 2026-10-23 — Document archive source inventory checkpoint

**Status: discovery/source audit advanced; no application code or schema changed.** Source files inspected on `main` include transaction/sponsor receipt submission and returned-record resubmission, FINA external collection upload and settlement evidence, monthly disbursement/family/return receipt upload, family document upload/delete/serve, project document and expense-receipt upload/replacement/serve, HR contract upload/serve, salary-advance payment and direct-repayment evidence upload/serve, and message attachment upload/delete/download.

### Confirmed workflow findings (source-level only)

- Transaction/sponsor receipts: `modules/transactions/create.php` writes shared physical receipts under `storage/receipts`; accepts JPG/JPEG/PNG/PDF by extension and 10 MB size, generates unique names, and supports separate line and unified receipts. Returned transaction/payment resubmission replaces the DB path then invokes an unreferenced-path cleanup helper after commit. Existing path-sharing in the DB means cleanup must be reviewed as a shared-reference rule, never blindly copied into archive code. The source does not perform detected MIME validation in these upload branches.
- FINA external receipts: `modules/transactions/fina_payment_create.php` stores under `storage/receipts` and removes the new file on DB transaction failure. Settlement transfer evidence uses `storage/receipts/fina_settlements`; its caller removes the uploaded file when transfer processing fails. Confirmed from source, not runtime.
- Disbursement receipts: `modules/accounting/disbursements.php` has separate family, return, and bank-transfer receipt upload paths. Most inspected branches validate detected MIME and a 10 MB cap. File writes and DB/journal updates span multi-step workflows, so cleanup and rollback behavior must be mapped per action before central integration.
- Family documents: `modules/families/documents.php` accepts PDF/JPG/JPEG/PNG by extension and 10 MB size, creates date/sequence names, inserts `family_documents` metadata, and logs upload. Its delete flow unlinks the physical file before deleting the DB row. A shared/versioned archive must change that lifecycle deliberately; no direct replacement is safe. Existing runtime DDL remains an unrelated pre-existing issue to address only in a justified scoped change.
- Project documents: `modules/projects/view.php` uses detected MIME allowlists, opaque random names, and a 10 MB limit. Replacing a document updates the stored path and then unlinks the prior path; expense receipt update/delete paths also unlink stored files. These are concrete archive-integration points because historical versions and shared references cannot be preserved by the current unlink behavior without a coordinated change.
- HR contracts: `modules/hr/contracts.php` accepts PDF/JPG/JPEG/PNG by extension with a 10 MB limit and opaque generated names, but the upload branch does not detect MIME. On replacing a contract file it updates `hr_employee_contracts.contract_file_path` without deleting the old file, so the prior physical file can become unreferenced. `modules/hr/view_contract.php` performs stronger path containment and detected-MIME validation during retrieval.
- Salary-advance receipt/evidence: `modules/hr/lib_salary_advance_accounting.php` validates detected MIME and a 5 MB limit, records audit metadata, replaces the current payment-receipt row in a transaction, and deletes the old file after commit. `modules/hr/lib_salary_advance_repayment_evidence.php` follows a similar row-replacement pattern and removes the prior file after success. The archive integration must preserve evidence history rather than silently discard replaced bytes.
- Message attachments: `modules/messages/attachment.php` uses extension plus detected-MIME allowlists, generated stored names, and removes a newly uploaded file if DB insertion fails. Its delete path removes the DB row then unlinks the file. `modules/messages/download_attachment.php` performs authenticated record lookup and streams the attachment.
- Serving routes are not uniform in caching, MIME source, path containment, role/scope checks, and disposition. Keep each domain's authorization adapter and existing URLs while introducing archive metadata; an archive ID or search result must never independently authorize access.

### Archive implications

1. Register historical files in place first; do not move/rename existing files during initial rollout.
2. Before the pilot, specify version creation and failure/rollback boundaries for every replacement path, and prevent physical deletion while any source record/version references the file.
3. Do not reuse existing unlink logic as an archive deletion API. Deletion must be a reviewed business action plus reference/version checks and audit evidence.
4. New upload integration needs consistent detected-MIME validation, opaque storage identity, checksum capture, CSRF, size limits, cleanup after failed DB work, and no-store/nosniff streaming where appropriate. Existing workflows vary; this is a design target, not a claim that every current endpoint is vulnerable.
5. No runtime verification of local Apache/production storage behavior was performed by this source pass. No code, schema, files, or business records were changed.

### Next exact action

Finish the remaining source/schema matrix for all source tables and deletion/reset workflows, then compare it with `database/ahl_el_kheir.sql` and the actual `ahl_el_kheir` schema before proposing explicit migration DDL. Do not implement the archive tables or change file lifecycle until that schema contract and the pilot category are justified by evidence.



## 2026-10-23 — Archive schema migration drafted (not applied)

A schema-only migration has been added on main:
`database/migrations/2026-10-23_document_archive_catalogue.sql`
Commit: `4b5a141511a4d2469f36a3e2df7af2ad31d990d7`

It defines four catalogue tables: `document_archive_blobs`, `document_archive_documents`, `document_archive_versions`, `document_archive_source_links`, and `document_archive_events` (five tables; the catalogue responsibilities remain stored blobs, logical documents, immutable versions, source links, and audit events). Internal foreign keys restrict deletion of referenced archive records. Source-record IDs are typed locators without guessed foreign keys to domain tables. User actor IDs intentionally have no FK so historical attribution is not coupled to user-row deletion. A unique storage key supports registering one shared physical path once; a unique source locator tracks the current source mapping; replacement history is represented by version rows plus audit events.

Evidence used: the repository SQL dump was retrieved via GitHub contents and parsed (5,691,158 characters); it defines the existing project tables and indexes, and contains no `document_archive_*` tables. The dump's project schema agrees with the supplied actual-DB column inventory for `project_documents` and `project_payment_evidence`. The migration follows the repository's existing InnoDB/utf8mb4/CREATE TABLE IF NOT EXISTS migration style.

**Important status:** migration file only. It has not been executed against `ahl_el_kheir`; no PHP integration, file backfill, or runtime test has occurred. No existing files or source records have been changed. Before applying it, verify the live DB still has no archive objects and run the DDL against a disposable schema copy or an isolated test environment. Then implement an idempotent, report-only project-file registration/backfill and the project-document/payment-evidence adapters. Do not start physical migration or change replacement/deletion behavior until those adapters and acceptance checks are ready.


## 2026-10-23 — Current active task: finish and verify project archive pilot

The user chose to integrate archive search into the existing role-based Global Search Center; do not create a separate archive search page.

Source implementation on `main` adds a `documents` search type and transactional archive registration hooks for project documents, expense/labor receipts, funding-return proof, and FM payment evidence. Search filters by active matching archive links, present blobs, project access, and the payment receipt endpoint's role allowlist. Previous file bytes are preserved on replacement; source deletion unlinks rather than purges archive bytes.

**Runtime is still pending.** Start by pulling `main`, then PHP-lint:
`modules/projects/lib_project_document_archive.php`, `modules/projects/view.php`, `modules/projects/view_fm.php`, `modules/search/index.php`, and `config/search_permissions.php`.
Then run `tools/backfill_project_document_archive.php --apply` only against the already-confirmed local `ahl_el_kheir` database; the previously reviewed dry-run showed 16 planned registrations and 3 missing-file test artifacts. Do not recreate missing files. Rerun the tool without `--apply` to verify idempotence, then test Global Search and uploads/replacements in the real Apache application. Do not claim completion until those outputs are supplied and reviewed.


### Archive follow-up — version-aware Global Search (2026-10-23)

Historical versions are now listed separately in the existing Global Search when the archive blob is present and the source link remains active. Each row indicates whether it is the current or a previous version. Retrieval uses `modules/projects/project_archive_version.php`, which applies project authorization, the payment-receipt role allowlist, storage containment and SHA-256/size checks. Add this endpoint to the PHP lint list. Runtime verification is still pending; do not call the feature complete yet.


### Archive follow-up — retained deleted-source records (2026-10-23)

Global Search now includes present versions attached to unlinked source links when the original business row has been deleted. It derives the project association from archive event metadata and still applies `akp_can_view_project()` plus the payment receipt role allowlist. This source change is not yet runtime verified.

 
### Archive pilot — latest local evidence (2026-10-23)

The user pulled `45912eed`. All six changed PHP files passed local `php -l`. Backfill `--apply` registered 16 references (8 project documents, 8 payment evidence); the next dry run found 16 already registered, 0 planned writes, no path/blob metadata mismatches, no reconciliation cases, and 3 known missing payment-evidence files. Continue with real browser tests at `http://localhost:8081/AhlElKheir/`: Global Search finds known present docs/receipts; permission filtering is correct; current version opens; replacement preserves and exposes prior version; unauthorized cross-project access is denied. Do not call complete until those tests pass.


## 2026-10-23 — Archive pilot disposition and next-task boundary

The original pilot scope is limited to project documents (`project_documents`) and project payment evidence (`project_payment_evidence`), integrated into the existing role-based Global Search Center. Source implementation and one-time metadata registration are complete for this scope, but the overall feature is **not fully acceptance-closed** because real Apache/browser verification has not been reported.

### Verified evidence
- User-reported PHP syntax checks passed for all six changed files: `config/search_permissions.php`, `modules/search/index.php`, `modules/projects/lib_project_document_archive.php`, `modules/projects/project_archive_version.php`, `modules/projects/view.php`, and `modules/projects/view_fm.php`.
- Against local `ahl_el_kheir`, the backfill registered 16 references (8 project documents and 8 project payment-evidence references). The subsequent dry run found all 16 already registered, with zero planned registrations, path mismatches, blob metadata mismatches, or reconciliation cases. This verifies idempotent catalogue registration for the inspected references only.
- Three existing project payment-evidence references point to missing physical test-artifact files: evidence ID 4/project 7, ID 1/project 8, and ID 3/project 8. They must remain recorded as missing; do not recreate files or claim they can be retrieved.
- These results do not prove browser search, download, authorization boundaries, automatic registration during live uploads, or replacement-version retrieval.

### Scope clarification: nanny/family sponsorship payment proofs
The user clarified that the receipts they expected to see are uploaded by a nanny as proof that she paid families or delivered monthly sponsorship to families. These are **not** the project payment-evidence records covered by the pilot. Search Center's empty archive result for that nanny does not prove those files are missing; the actual family-payment workflow has not yet been traced or integrated. Do not add the nanny role to the project-payment-evidence allowlist as a workaround. Any future extension must inspect the real upload handler, schema/path columns, serving route, and family-level permission rules, then integrate automatic registration and a safe backfill into the existing Global Search Center.

### Disposition / next acceptance gate
- Mark the **project archive implementation + existing-reference backfill phase** complete, with the above evidence and limitations.
- Keep **end-to-end acceptance** open until the user tests the existing Global Search in `http://localhost:8081/AhlElKheir/`: find a known present project document and receipt, open current and previous versions after a replacement, and confirm an unauthorized cross-project request is denied. Do not use the three missing files as test fixtures.
- Treat nanny/family sponsorship payment proofs as an explicit scope extension, not as a defect that can be solved by bypassing current project receipt permissions.
- Do not claim the entire document-archiving system is done. At the start of the next repository task, reread the canonical docs and inspect the real repository state before proceeding.


## 2026-10-23 — Active task: sponsor QR + Android scanner APK

Continue from `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`. Source-only work has added local TCPDF QR generation to sponsor profile/orphan sponsorship displays and a native Android ZXing/WebView companion with a GitHub Actions debug-APK workflow. QR payloads contain only the existing permission-checked sponsor profile URL; the existing PHP authentication and scope checks remain authoritative. No schema migration was added. PHP lint, Android build, Apache/browser, physical-device, count-reconciliation and authorization runtime checks are still pending. The previous project archive acceptance gap remains separate.


## Current task — Sponsor QR + Android scanner (2026-10-23)

Read `docs/SPONSOR_QR_ANDROID_CONTINUATION.md` and continue the existing feature on `main`. The previous Actions failure is now specifically traced to Kotlin 1.8.22 containing classes duplicated by legacy `kotlin-stdlib-jdk7/jdk8:1.6.21` artifacts pulled transitively via AndroidX lifecycle/coroutines. Fix commit: `777f9b180ce61dade8c96649a8138e7f4bb85880`; next action is verify the new GitHub Actions run and APK artifact. Do not claim build success until the run and artifact confirm it. Continue with local PHP lint, Apache runtime, read-only active sponsorship count reconciliation, authorization/supervisor-scope checks, and Android phone testing only after build succeeds.


## Sponsor QR Android scanner — latest checkpoint (2026-10-23)
The Kotlin duplicate-class exclusion was committed as `777f9b180ce61dade8c96649a8138e7f4bb85880`. The next Actions run then failed compiling `MainActivity.java` because `BuildConfig` was unavailable. Inspected `android/sponsor-qr-scanner/build.gradle`: AGP is 8.7.3. Commit `ecdc34c19f1d5f5c370a95b3a902739693710b7f` enables BuildConfig generation via `buildFeatures { buildConfig true }`. Next action: inspect the new Actions run; do not claim success until compile, assemble, and APK artifact upload pass.


## 2026-10-23 — Sponsor QR host diagnosis and portrait scanner change

User confirmed that opening the existing system through the laptop LAN address and scanning the newly generated sponsor QR successfully opens the sponsor profile. The previously reported “رمز غير صالح” was caused by generating the QR while the website was opened at `localhost`; that URL points to the requesting device and does not match the phone scanner's configured laptop LAN host. No origin-validation bypass was added. For physical-phone testing, generate QR codes while browsing the site using the same LAN host/port/application path configured in the APK.

Portrait change committed to main:
- `android/sponsor-qr-scanner/app/src/main/AndroidManifest.xml`: lock `.MainActivity` to portrait.
- `android/sponsor-qr-scanner/app/src/main/java/org/ahlelkhier/qrscanner/MainActivity.java`: set ZXing `setOrientationLocked(true)`, so the capture screen follows the portrait-locked activity.
- `android/sponsor-qr-scanner/README.md`: document portrait behavior and the matching-host requirement.

Source changes are committed, but the updated APK and physical-device portrait behavior are not yet verified. The Android workflow should build a new debug APK from these commits. After it succeeds, install that artifact and verify scanning while holding the phone upright. Keep existing website authentication, sponsor permissions, supervisor scope checks, and same-origin validation unchanged.


### 2026-10-23 — Portrait scanner APK build verified

GitHub Actions run [39990143104](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/39990143104) completed successfully on source commit `ea50c4f4cc24ad287381967d6c1a2c7e45a4ffd4`. PHP QR integration lint, Android duplicate-class check, Java compilation, debug APK assembly, and artifact upload all passed. Artifact: `ahl-el-kheir-sponsor-qr-scanner-debug-apk`, artifact ID `12517556746`, SHA-256 `46453215f819c7cbfb1a8bb8fd091cddde533488cd2b652061f1c2e8314b2980`, expires 2026-11-22.

Remaining acceptance gate: install this newly built APK on the user's phone and confirm the scanner stays portrait while scanning, then scan a QR generated from the same LAN base URL and verify sponsor page opening. CI build success does not prove physical-device orientation behavior.


## 2026-10-23 — Current sponsor QR scanner continuation

The user confirmed that sponsor QR scanning opens the sponsor profile when QR generation and APK configuration use the same LAN host/path; localhost was the cause of the earlier invalid-QR message.

Latest main changes: redesigned Arabic Android screen; removed the permanent server-settings button and preserved settings via long-press on the title; added in-system APK download action and authenticated PHP endpoint modules/sponsors/download_scanner_apk.php; CI lints the endpoint. APK fixed storage path: storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk. Direct storage access remains denied.

Next: inspect the latest GitHub Actions run for lint/build/artifact success; then install that artifact and test portrait scanning, and copy the built APK to the fixed storage path before verifying authenticated download. Until the APK file is actually present on the target system, endpoint 404 is expected. Do not claim runtime completion based on source or CI alone.


## 2026-10-23 — Sponsor QR scanner: portrait build correction and minimal home UI

The user confirmed that APK download and installation work, but the scanner camera still opens horizontally. CI run [40001005068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001005068) provided the root cause: ZXing's library manifest sets its separate `CaptureActivity` to `sensorLandscape`, and the app's explicit `portrait` setting caused a manifest merge failure. The Android manifest now uses `tools:replace="android:screenOrientation"` on that activity to override the dependency's value. A fresh successful build and physical-phone retest are still required.

The main Android screen has been reduced to the **مسح رمز QR للكفيل** action plus the WebView required for existing website login and sponsor pages. The branding/download header and connection-status strip are removed. Long-press the scan button to change the configured server URL. The authenticated website header continues to provide the APK download link.

Do not mark portrait fixed until a fresh APK build succeeds and the user verifies portrait orientation on the physical phone. Keep sponsor URL validation, login, permissions, and supervisor scope unchanged.


## 2026-10-23 — Corrected sponsor scanner APK build verified; physical-device acceptance pending

GitHub Actions run [40001446199](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001446199) completed successfully on commit `29eee35eb4fc3bf9869913abcd4395fea77a93e9`. The manifest merge, Java compilation, debug APK assembly, and artifact upload passed after the ZXing capture activity portrait override was changed to `tools:replace="android:screenOrientation"`. Artifact ID `12522116796`, name `ahl-el-kheir-sponsor-qr-scanner-debug-apk`, expires 2026-11-22; artifact ZIP SHA-256 `2b4eb5b801662bb05c1791ba0d0c1a5081822506c73c05a554bd55713c26ad1a`.

The home UI now has only the **مسح رمز QR للكفيل** native action and the required WebView; the extra branding/download header and connection-status strip are removed. Long-press the scan action to change server settings. The website's authenticated header remains available for downloading the APK.

The user must still install this fresh artifact and verify physical portrait scanning and sponsor-profile opening. Do not describe device orientation as verified until the user confirms it.


## 2026-10-23 — Current continuation boundary: orphan form QR placement

Closed by user: latest sponsor QR scanner APK was deployed to the system's fixed download path. Do not reopen absent regression evidence. Details are in `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`.

Active task: review and verify commit `1e29a29b85b4bf4ac0da2cd7aff226019a71cff2` in `modules/families/orphan_form.php`. Sponsor QR code(s) were moved to the upper-left header, horizontally aligned with the logo and form title; the sponsorship table retains sponsor name/code and dates. Next: verify the orphan form in `http://localhost:8081/AhlElKheir/` and its print/PDF layout. PHP syntax has passed in GitHub Actions run [40003543066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40003543066), job `php-lint`. Include existing single/multiple sponsorship and blank-form cases where available. Only close after browser/print evidence; do not modify unrelated form/schema behavior. Include existing single/multiple sponsorship and blank-form cases where available. Only close after evidence; do not modify unrelated form/schema behavior.


### Current correction — original orphan form design restored

Review commit `efe2eaf9b6aeaa1d25b7f136f9b9958f74e519a4`: the original orphan form header styling was restored by removing the flex/min-height/padding changes. The sponsor QR remains absolutely positioned at upper-left in the original header row. Next run PHP syntax validation and verify the local Apache form and print/PDF preview; do not close until the user confirms the original design is preserved.

### 2026-10-23 follow-up — full-width sponsorship rows

Source commit `d31c1fd6c56f25ecca15adb5833131b425264935` adds `colspan="3"` to the value cells for “مدة الكفالة” and “قيمة الكفالة”. Those rows share a table with four-column sponsor rows; spanning the remaining columns makes both row borders extend across the same full table width without changing their content or the QR header. PHP syntax and local browser/print appearance verification are still pending.


## 2026-10-24 — Flutter mobile cloud build

Read `docs/MOBILE_APP_CONTINUATION.md`. User does not need to install Flutter locally just to build the Android APK: use `.github/workflows/build-flutter-mobile-app.yml` and download artifact `ahl-el-kheir-flutter-android-debug-apk` from the successful run. Verify the run and artifact before claiming build success. For a phone on the local network, manually dispatch the workflow with the laptop's reachable LAN API URL; for hosting, use HTTPS. Do not expose XAMPP. API migration/runtime/security acceptance remains separate and pending.


## 2026-10-24 — Flutter login widget-test follow-up

Cloud run 40060794073 passed Dart analysis but failed the test because it launched full-app startup/session restoration instead of isolating the login form. The test now mounts LoginPage directly with RTL directionality in commit b3216456948e71a80ae2629961efc78352769edf. Check the next workflow run and artifact. Do not claim APK success until confirmed; API runtime/security acceptance remains pending.


## 2026-10-24 — Mobile physical phone and theme follow-up
Continue from `docs/MOBILE_APP_CONTINUATION.md`. User confirmed laptop LAN IP `192.168.18.9` and Apache port `881`; Flutter default targets `http://192.168.18.9:881/AhlElKheir/api/v1`. Login UI uses the existing website navy palette and `assets/img/logo.jpg`. Do not claim login works until CI passes, phone reaches the endpoint, `2026-10-24_mobile_api_sessions.sql` is confirmed applied to the intended `ahl_el_kheir` DB, and local API runtime/security checks pass. Do not expose XAMPP publicly.


## 2026-10-24 — Flutter UI analyzer follow-up
Brand/API URL source change is in `54a1c06d2855b0d58044adf7cb3bbf6d8860d366`; its first CI run failed `flutter analyze` because `Color.withOpacity` is deprecated in Flutter 3.47. Commit `6dbb17b7f4276eeeaad2e3f6c8c267d939ef35f6` uses `withValues(alpha: ...)`. Verify latest Actions run and artifact before asking user to install. Physical-device connectivity and API migration/runtime remain pending.


## 2026-10-24 — Flutter login request never reaches Apache
The user retried login with the physical-phone APK and the latest Apache access-log tail still contained no `POST /AhlElKheir/api/v1/auth/login.php`; only the earlier browser `GET` (expected HTTP 405) was present. Source inspection found that `.github/workflows/build-flutter-mobile-app.yml` overwrote Flutter's generated debug `AndroidManifest.xml` with `usesCleartextTraffic="true"` but omitted `android.permission.INTERNET`. The debug manifest generation was corrected on `main` in commit `99a37d69bc19edaea42c28c9807a6560ac1bb8af` to include both the INTERNET permission and the cleartext setting. This is the identified source-level cause consistent with the absence of requests in Apache logs; verification is pending. Next: confirm the GitHub Actions run triggered by that commit passes and publishes a new `ahl-el-kheir-flutter-android-debug-apk`; install that new artifact, attempt login once, and check for a new Apache POST entry. Do not reuse the earlier artifact or claim device login fixed before that physical-device check. API migration is now confirmed applied by the user to `ahl_el_kheir`; continue to keep API/auth runtime acceptance separate from CI build success.


## 2026-10-24 — Flutter mobile notification inbox (implementation checkpoint)

- Physical-phone authentication has been confirmed by the user: deliberately invalid credentials fail and the user's real credentials sign in successfully against the local XAMPP API.
- The first business-data slice is the shared notification inbox. New authenticated routes: `GET /api/v1/notifications/index.php?page=1` and `POST /api/v1/notifications/mark_read.php` with JSON `notification_id`.
- Both routes derive the recipient from the authenticated bearer session. The query/update are scoped to `notifications.recipient_user_id = current user id`; the mobile client cannot select another recipient. No schema change or migration was added.
- Flutter's signed-in screen now links to an Arabic-first notification inbox with English support, unread/read presentation, pull-to-refresh, paging, and mark-one-read behavior. Added a widget test for the notification inbox.
- Verification boundary: source changes are committed to `main`; the latest Flutter GitHub Actions run must be checked for analyzer/test/APK status. PHP syntax and authenticated API runtime checks against the local XAMPP database remain pending; do not claim this module is runtime-verified until those checks pass.
- Next: inspect the exact CI run, then locally lint the two new PHP endpoints and test own-user listing/mark-read plus cross-user isolation using existing accounts/notifications. Do not create test notifications or modify business data unnecessarily. Continue by selecting the next real business module only after mapping its existing source authorization and schema.


## 2026-10-24 — Mobile project portfolio implementation checkpoint

- Added authenticated read-only endpoint `GET /api/v1/projects/index.php?page=1` and an Arabic-first/English Flutter project portfolio screen.
- The endpoint is limited to the same portfolio roles allowed by `modules/projects/index.php`. Project visibility follows the existing project-domain rules inspected in `modules/projects/project_lib.php`: global financial/executive visibility, PM creator/pre-handoff and closure-request rules, and the assigned project-supervisor launch/scope checks. The mobile client role check only controls whether the entry point is shown; server-side authorization remains authoritative.
- Portfolio data uses the existing project, approval, lifecycle, posted funding/expense, beneficiary, and active supervisor records. No schema changes or runtime DDL were introduced.
- Added a Flutter widget test for Arabic project portfolio rendering and added the endpoint to the PHP 8.2 syntax-check workflow.
- **Verification boundary at this checkpoint:** GitHub Actions runs for the new Flutter page/test and the PHP endpoint are in progress. Do not call the feature CI-verified until those runs finish. The endpoint has not yet been exercised against the local Apache/MariaDB database or tested with different role accounts. No physical-device test is requested at this stage.
- Next: inspect the actual Actions results; fix only concrete failures; then add the next project workflow slice after reviewing the existing project detail endpoint and its role/scope rules. Keep broader mobile modules queued until the project read path is verified.


## 2026-10-24 — Mobile project details slice

- Added `GET /api/v1/projects/detail.php?id=<project_id>`, using the existing `other_projects`, project approval/lifecycle, posted funding/expense, team assignment, milestone and status-history records. It is read-only and makes no schema changes.
- The endpoint checks the live bearer identity and role, then enforces project visibility for the executive/financial roles, the Projects Manager's existing creator/pre-handoff and closure-request rules, and the Project Supervisor's final-approval/launch plus active assignment scope. Client navigation is not treated as authorization.
- Added an Arabic-first/English project detail screen reachable from the portfolio, with status/approval, financial summary, active team, milestones and recent history. Added a widget test for the Arabic detail view.
- The PHP lint workflow now includes both project API endpoints.
- **Verification boundary:** The selected PHP files passed lint on commit `7e5f13b9f2e35833ca6dcdf3eb2b8bc47608ac23`, but that run did not yet include `api/v1/projects/detail.php`. A subsequent PHP lint workflow with the detail endpoint added is queued/running. The Flutter workflow for the detail screen and widget test was still running at the time of this note. No local Apache/MariaDB API runtime test or role-account matrix has been run, so this feature is not yet runtime-accepted.
- Next: inspect the latest Flutter Actions result and fix only concrete failures. Then continue the mobile project read workflow with the next useful, role-safe slice; do not ask the user to test until the broader app reaches the agreed release-readiness gate.


## 2026-10-24 — Mobile HR attendance register

- Added read-only `GET /api/v1/hr/attendance.php`, restricted server-side to `hr_manager`, `hr_staff`, and `admin`. It uses the existing effective employment-state roster and attendance/approved-leave records; it does not write attendance or mutate leave-return records.
- Missing attendance remains `missing` in the API and UI, never inferred as absence. Approved leave is shown as leave unless a persisted or legacy same-day return marker exists.
- Added an Arabic-first/English attendance register with date selection, refresh, employee/department rows, check-in/out display, and summary counts. Added a widget test specifically preserving the missing-vs-absent distinction.
- The PHP lint workflow now includes the attendance endpoint.
- **Verification boundary:** Flutter CI exposed and we corrected a widget-test visibility issue in the project detail test; the latest build covering that correction and the attendance screen is running. PHP lint for the new attendance endpoint is queued/running. No local API runtime test or HR-role account matrix has been performed.
- Next: close the latest Flutter/PHP CI checks, then continue with role-safe HR leave-request read views and subsequent modules using the same source-first approach.


## 2026-10-24 — Mobile leave-request views

- Added read-only `GET /api/v1/hr/leaves.php?view=mine` for an employee's own leave history, resolving the employee through the existing `hrGetEmployeeForUser()` identity helper. The endpoint returns an empty list if no unambiguous employee profile is linked.
- Added the authorized review queue: HR Manager/HR Staff see non-HR employees' requests; General Manager (including the existing `gm` compatibility role) sees HR employees' requests. Role and view are checked server-side; the mobile role controls only the available navigation.
- Added Arabic-first/English leave list UI, status filters and paging. This is a read-only slice; request submission, approval and rejection are not exposed by this endpoint and must be implemented separately against the existing workflow rules.
- PHP lint passed for the new HR attendance and leave endpoints in GitHub Actions run `40085047414`. The mobile build exposed two widget-test assumptions (an off-screen attendance row and an HR review-queue test that had not switched from “my requests”); the tests were corrected on commit `638130e98af2fdd237e93a042bfad37f8c5f650c`. Its Flutter run is pending/in progress.
- No local API runtime test or role-account matrix has been performed. Do not mark the mobile HR views runtime-accepted yet.
- Next: confirm the current Flutter CI run, then implement leave request submission and role-authorized approval/rejection as separate validated API actions before moving to the next domain.


## 2026-10-24 — Mobile leave-request workflow actions

- Added authenticated `POST /api/v1/hr/leaves/action.php` for submitting leave requests and approving/rejecting pending requests. It preserves the existing role split: HR approves non-HR requests; General Manager approves HR employees' requests; rejection is limited to the same role-owned queue.
- Request types, inclusive day calculation, strict date format, duplicate pending-request check, final `hr_approved` status, approver columns, attendance-row creation, and post-action notifications follow the existing `modules/hr/leaves.php` workflow. Notifications are isolated so a notification insert failure does not undo a committed leave decision.
- The Flutter leave page now supports creating requests and the authorized review queue's approve/reject actions. Server-side authorization remains authoritative.
- PHP lint passed for the new action endpoint in GitHub Actions run associated with commit `b3aff6ab25320b43fa033dcc38a3694d3097efc5`. The Flutter analysis/tests/APK build for this combined change are still pending. No live-database request/approval runtime test has been performed.
- This supersedes the prior checkpoint statement that mobile leave requests were read-only: the list endpoint remains read-only, and the separate action endpoint now handles writes.


## Mobile app continuation — 2026-10-24
Continue on `main` from the current repository head. Read `docs/MOBILE_APP_CONTINUATION.md` first. The user explicitly wants the app built and tested on GitHub as far as possible, with device testing requested only when the app is broadly complete. Transaction-register row overflow correction is commit `885ef809b431b174541d54c8c44ade3f97421172`; confirm Flutter tests and APK build before moving on. Never claim full completion or live API runtime proof from a passing APK build alone.


## Mobile app continuation — 2026-10-24 update
Transaction-register layout correction is verified green: workflow run `40091698003` passed Dart analysis, widget tests, debug APK build, and artifact publication. Current `main` additionally contains a read-only sponsorship register/API, with Arabic/English search, status filters, active monthly summary, and supervisor scope enforcement; its newer CI run is pending. Continue building on `main` and check the latest workflow before claiming the new screen passed. Do not ask for physical-device testing yet; the user wants that only after the app is broadly complete.


## 2026-10-24 — Sponsor request register CI verified
- Flutter workflow run `40093088822` for app integration commit `e3ad346c1267736b4e725805e0c877e524e8f2ac` completed successfully: dependency resolution, Dart analysis, widget tests, debug APK build, and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` (artifact id `12559303403`) was confirmed present and unexpired at verification time.
- PHP verification run `40093091580` completed with the `php-lint` job passing, including the new `api/v1/sponsor_requests/index.php` endpoint. The independent Stage 5 payroll regression job failed at its existing acceptance check with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; the rescheduling, HR migration and MariaDB 10.4 jobs passed. This is not evidence of a mobile endpoint failure and does not reopen the closed salary-advance work.
- Sponsor request screen now has CI evidence. API runtime against local Apache/MariaDB, per-role authorization and phone UI checks remain unverified. Continue building the remaining app on GitHub; do not request device testing yet.


## 2026-10-24 — Mobile profile completeness and transaction date filtering
- Expanded the existing family detail API/client with profile fields already displayed by `modules/families/view.php`: alternate phone, registration number, city, address, mother's marital status, father's name/death details, monthly need and notes. Existing family authorization and supervisor/nanny scope checks remain unchanged.
- Expanded the existing sponsor detail API/client with fields already displayed by `modules/sponsors/view.php`: email, address, sponsor type and preferred payment method. Existing sponsor role allowlist and supervisor scope check remain unchanged.
- Added start/end date controls to the mobile transaction register and connected them to the existing `api/v1/transactions/index.php` `from`/`to` filters. Client rejects an inverted date range; clear-dates restores an unbounded range. No schema or workflow mutations were introduced.
- The expanded-profile Flutter run `40093704453` has passed Dart analysis and all widget tests; debug APK build/artifact publication is still pending at this documentation checkpoint. The family detail test was updated to scroll to the children and sponsorship sections after the expanded profile moved them beyond the initial viewport.
- PHP 8.2 lint passed in run `40093671534` for the family/sponsor endpoint changes. The separate `migration-full-schema` job continues to fail only at the closed Stage 5 payroll regression step with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; do not attribute that failure to mobile code or reopen closed salary-advance work.
- API runtime on local Apache/MariaDB, per-role live account matrix, and physical-device acceptance remain outstanding. Continue app development and CI verification before requesting device testing.

## 2026-10-24 — Mobile HR employee directory implementation
- Added authenticated read-only `GET /api/v1/hr/employees.php?q=&page=1`, restricted server-side to the exact `hr_manager`, `hr_staff`, and `admin` roles allowed by `modules/hr/employees.php`.
- The endpoint uses existing `employees`, `departments`, and `hr_employment_states` fields, with name/employee-code/position search and 20-row pagination. It intentionally omits national ID, personal contact/address details, salary, bank data, and employee-editing actions.
- Added the Arabic-first/English Flutter employee directory with search, refresh, pagination, department, position, hire date, and employment state. The entry point is shown only for the same HR/admin roles; the API independently enforces authorization.
- Added a widget test and included the API route in the PHP 8.2 lint workflow. No database migration, schema change, or runtime DDL was added.
- CI verification for this slice is pending until the Flutter analyzer/tests/APK artifact and PHP lint job finish. Live Apache/MariaDB API checks and role-matrix runtime verification remain outstanding; do not request device testing yet.

## 2026-10-24 — Mobile employee directory CI confirmation and leave-request guard
- Correction to the prior checkpoint: Flutter run [40094355923](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40094355923) completed successfully, including Dart analysis, widget tests, Android debug APK build, and artifact publication. Artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12560083920`, 77,209,735 bytes) was confirmed unexpired. PHP lint passed in run [40094355939](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40094355939).
- New follow-up: the existing leave API explicitly returns `employee_linked=false` when the authenticated user has no linked employee profile. The mobile leave screen previously ignored this signal and still displayed the request action, which would lead to a predictable server rejection. It now hides that action and displays an Arabic/English explanation for unlinked accounts.
- Added a widget test for the unlinked-account state. Current verification for this follow-up is running in Flutter [40095047706](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095047706) and PHP workflow [40095047697](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095047697); do not mark the follow-up verified until the checks finish.
- The independent `migration-full-schema` CI job still reports the known Stage 5 payroll acceptance failure; it remains unrelated to mobile changes and must not reopen the closed payroll work. Live Apache/MariaDB API role checks and device acceptance remain outstanding.

## 2026-10-24 — Leave-request guard follow-up verification checkpoint
- The first Flutter attempt `40095047706` failed at Dart analysis because the newly added widget test had been placed outside `main()`. The failure was confirmed from the job log; the test was moved into `main()` in commit `76b83f7b83c96ce03e9853b7d47e8f00e91c3609`.
- On rerun [40095123841](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123841), Dart analysis and all widget tests have passed. Android debug APK build is still in progress and no artifact has been published for this revision at this checkpoint.
- PHP lint passed in [40095123850](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123850). The separate full-schema job is still running at this checkpoint; continue to treat any known Stage 5 payroll acceptance failure as unrelated to the mobile work.
- The employee-link guard is based on the existing API's explicit `employee_linked=false` response; no database or approval behavior was changed. Live Apache/MariaDB API role checks and device acceptance remain outstanding.


## 2026-10-24 — Leave-request guard CI verified
- Flutter workflow [40095123841](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123841) completed successfully on `main`: Dart analysis, all widget tests, Android debug APK build, and artifact publication passed. APK artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12560951078`, 77,209,480 bytes) is present and unexpired.
- PHP workflow [40095123850](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123850): the `php-lint` job passed, as did the HR migration, MariaDB 10.4 migration and rescheduling jobs. The overall workflow remains failed only because its separate `migration-full-schema` job failed at the known Stage 5 payroll regression/account 1410 reconciliation step; do not attribute this to mobile work or reopen the closed payroll feature without new regression evidence.
- The leave-request guard is now CI-verified: when the existing API reports `employee_linked=false`, the mobile page hides the leave-request action and explains that HR must link an employee profile. The added widget test passes. No leave business rules or database schema were changed.
- Verification boundary remains explicit: no live Apache/MariaDB API role matrix or physical-device acceptance has been performed. Continue implementing and testing the broader app on GitHub before requesting device testing.


## 2026-10-24 — Mobile project progress updates verified
- Project detail API `api/v1/projects/detail.php` now includes up to 10 existing `project_progress_updates` rows, ordered newest first, with update date, completion percentage, summary, achievements, issues, next steps and submitter name. This is read-only and adds no schema changes or workflow actions.
- `mobile/ahl_el_kheir_app/lib/project_detail_page.dart` displays these records in an Arabic-first/English section; `test/project_detail_page_test.dart` covers the rendered content. Test scroll ordering was corrected after the first CI run exposed an off-screen assertion; production behavior was not altered to satisfy the test.
- Successful Flutter workflow [40097366358](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40097366358), commit `7ea27b76d59af2fda7451e0d9278cac895367b89`: Dart analysis, full Flutter widget tests, Android debug APK build and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12560489959`, 77,209,301 bytes, is present and unexpired.
- PHP lint passed in the paired workflow's `php-lint` job. Its separate `migration-full-schema` job remains failed at the known Stage 5 payroll acceptance/reconciliation issue; do not reopen the closed payroll feature as part of mobile work.
- Live Apache/MariaDB API role checks and physical-device acceptance have not been performed. Continue mobile development and GitHub CI before asking the user to test a device.


## 2026-10-24 — Project portfolio search improvement checkpoint
- The project list previously filtered only the currently loaded page on-device, so results on other pages could be missed. `api/v1/projects/index.php` now accepts optional `q` and filters project code/name after applying the existing role-specific visibility checks and before pagination; totals/pages reflect the filtered authorized result set.
- `projects_page.dart` sends the search term to the existing API with a 350 ms debounce and generation checks to prevent stale in-flight responses from overwriting newer search results. `main.dart` URL-encodes the query. The widget test now asserts that the search term reaches the page loader.
- Commits: API `011a96d6a3bf4eedc0440ac9393203b43bcad169`; Flutter debounce `461b46c97cf962d5116ca3d01704d342791b83c3`; API call wiring `d0ad325cc0e654c21ca94d58f58e50c7511effb3`; widget test `184d3e3c45cbaf06810a717c2ed4cbfa55abee6a`.
- Latest complete-revision Flutter workflow [40098149592](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40098149592): Dart analysis passed and all Flutter widget tests passed; Android debug APK build is still in progress at this checkpoint. Do not mark this search change fully verified or claim a new APK artifact until the run finishes successfully.
- PHP lint passed in the paired workflow's `php-lint` job. Its independent full-schema migration job is not evidence against this API change; the known Stage 5 payroll acceptance failure remains out of scope. Live Apache/MariaDB API role checks and physical-device acceptance remain outstanding.


## 2026-10-24 — Project portfolio search CI confirmation
- Follow-up polling confirmed workflow [40098149592](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40098149592) completed successfully at 2026-10-24T13:30:33Z. The complete Flutter job passed and published unexpired Android debug APK artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12561936059`, size 77,206,559 bytes.
- The portfolio search change is now fully CI-verified: Dart analysis, Flutter tests, Android debug APK build and artifact publication passed; paired PHP lint passed. Live Apache/MariaDB API role testing and physical-device acceptance remain outstanding.


## 2026-10-24 — Notification inbox failure-path coverage added (CI pending)
- Commit [e05824931378d4d8ac16712aa6c1005bb757fdae](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/e05824931378d4d8ac16712aa6c1005bb757fdae) adds Flutter widget coverage for two notification-inbox behaviors: a failed mark-as-read request displays the error without reloading the inbox, and tapping an already-read item does not send a mark-as-read request.
- This extends the prior unread-item test; production code and API behavior were not changed.
- Verification boundary: GitHub's available commit-status and commit-associated workflow lookups returned no status records/runs for this push. The new tests are **not yet CI-verified**. Do not claim analyzer, widget tests, APK build, or artifact publication passed until a real workflow result is available.
- Next: obtain actual Flutter workflow evidence for this commit (analyzer, complete widget suite, Android debug APK build, artifact publication). Keep live Apache/MariaDB role tests and device acceptance deferred. Do not touch closed payroll work.


## 2026-10-24 — Mobile sponsorship role allowlist aligned with web module

- Source comparison found a concrete permission mismatch: `modules/sponsorships/index.php` allows `admin`, `vice_general_manager`, `supervisor`, and `accountant`; the mobile API and home-screen entry had additionally exposed the module to `general_manager` while excluding no other web-authorized role.
- Corrected `api/v1/sponsorships/index.php` to enforce the same four-role allowlist, and corrected the Flutter home-screen entry to match. Server-side API authorization remains authoritative; no business data, schema, or workflow was changed.
- Commits: API `350d18f1f5307ef1c273630489be321a5066f505`; Flutter navigation `39b65a7d887376e7fa451692483621bc718be5b8`.
- Verification boundary: source comparison justifies the change, but PHP lint, Flutter analysis/tests/APK build, and live role-account runtime checks have not been confirmed for these commits. Do not claim the change is verified until actual CI evidence is available. No device testing requested yet.


## 2026-10-24 — Sponsorship detail API role parity follow-up

- Commit [bc39b23fdd036298f64ed80a3b09e2bb906c9653](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/bc39b23fdd036298f64ed80a3b09e2bb906c9653) aligned `api/v1/sponsorships/detail.php` with the sponsorship list API allowlist: `admin`, `vice_general_manager`, `supervisor`, and `accountant`; the supervisor responsibility-scope guard remains.
- Current `main` source was re-fetched and confirms the four-role allowlist. This is source verification only; PHP lint, Flutter analysis/tests/APK build, and live API role checks are still unverified for these latest commits. Do not request device testing yet.
- Audit note: `api/v1/sponsors/detail.php` retains its broader role set because it matches `modules/sponsors/view.php`; no change was justified there.


## 2026-10-24 — Mobile sponsor-request attribution correction (CI pending)
- Commits: Flutter card attribution `4999c0f4b82ecb50e72203d990488840486dabd1`; widget coverage `7f4f8f2159abe1e4f86d6864d75c75f5fb6587d4`; API attribution search `864608962c4ed3f9ac3ae2a1e86711f247170413`.
- Flutter sponsor-request cards now display `brought_by_name`, falling back to `brought_by_user`; widget test checks the preferred name wins over the fallback.
- API search now checks sponsor name, phone, source, `r.brought_by_name`, and joined `users.full_name`. The count query uses the same LEFT JOIN as the list query; five search placeholders have five matching parameters. No schema change was made.
- Verification boundary: source changes are committed on `main`; GitHub commit status currently reports no status records for the API commit, and the Flutter workflow has not yet been confirmed. Do not claim PHP lint, Flutter analysis/tests, Android APK build, or artifact publication passed until actual CI evidence is available. Live Apache/MariaDB acceptance and device testing remain deferred.


## 2026-10-24 — Sponsor-request attribution fix CI verified
- Flutter workflow [40106182906](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106182906) completed successfully at commit `91655d9cf8a1232eb30cad4786290a276270cb5b`. Dart analysis, the full widget suite, Android debug APK build and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12564862356`, 77,208,880 bytes) is present and unexpired; SHA-256: `eab6bf7c5c9e833ce05fff19f3720913c3de41cadcedfb912fe64b093a1e514d`.
- Paired PHP workflow [40106183059](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106183059): PHP lint, HR job-description migrations, MariaDB 10.4 migration, and rescheduling-service tests passed. The separate full-schema Stage 5 payroll acceptance job failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; track it under deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), do not attribute it to the mobile change or reopen closed payroll work without root-cause evidence.
- Sponsor-request attribution UI/search change and preferred-name/fallback widget coverage are CI-verified. API live Apache/MariaDB role testing, and physical-device acceptance, remain outstanding. Continue the broader mobile app on GitHub; do not request phone testing yet.


## 2026-10-24 — Mobile sponsor-request status actions added (verification pending)
- Added authenticated `POST /api/v1/sponsor_requests/action.php` for the exact non-destructive status transitions already supported by `modules/sponsors/requests.php`: `new → contacted`, `new/contacted → lost`, and `lost → contacted`. The endpoint enforces the same six-role allowlist as the existing sponsor-request register, locks/rechecks the current status in a transaction, and rejects invalid/stale transitions. It does not expose delete or sponsor conversion; no schema changes or runtime DDL were added.
- Flutter sponsor-request cards now provide confirmation-protected actions for those transitions, local Arabic/English success feedback, and search hint text that includes referrer attribution. Added widget coverage for confirming the contacted transition and reloading the updated state. PHP lint workflow now includes the action endpoint.
- Source commits: API `7614893bf51c7fab7f5938c41356a0c833352113`; app wiring `0b8b71de2fc83c0d013cba54e40c4ae4eadf3dd9`; UI actions `40457577937138bab9116695a3878e5c33ec6594`; widget test `7d83fe6719d188bff84c93cdbcc4503e831f939c`; localization/hint `013c01042794c62a8f4a96c8d8c8dd268894739b`; analyzer correction `9240efbbae4969e66188f2d747b8854932d99dcc`.
- Verification: PHP lint passed in [workflow 40107450497](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450497). Flutter analyzer and widget tests passed in [workflow 40107450516](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450516); Android debug APK build/artifact publication were still running at the last check, so do not mark the slice fully CI-verified until confirmed. Earlier analyzer run [40107338619](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107338619) failed only because it reported an unused local variable; this was removed in the subsequent commit and the latest analyzer passed.
- The independent full-schema Stage 5 payroll job again failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; keep it in deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), unrelated to this mobile work. Live Apache/MariaDB role-matrix testing remains unperformed. Do not request phone testing yet.


## 2026-10-24 — Mobile supervisor directory added (CI pending)

- Added authenticated read-only `GET /api/v1/supervisors/index.php`, restricted server-side to the website module's exact `admin` and `vice_general_manager` roles. It supports bounded name/username/phone search, pagination, and an explicit include-departed/archived filter; the default excludes departed and archived supervisors, matching `modules/supervisors/index.php`.
- The response uses checked-in schema columns from `users`, `roles`, `supervisor_letters`, and `letters`. It returns current supervisor status, contact details, assigned letters/gender markers, and last-login timestamp. No schema changes or runtime DDL; no supervisor create/edit/status/exit actions were added.
- Added an Arabic-first/English mobile directory page, navigation visible only to admin and vice general manager, and widget coverage for supervisor rendering and the archived filter. Added the endpoint to PHP lint workflow.
- Commits: API `74fcb0550e5c057903712f10d320d06c7e49ceaa`; PHP lint workflow `6af0c6ffd5a35d18e88b914be2a02698e1f5256c`; Flutter page `98e444570207b102427fac79cc69e860115e1036`; app navigation/API wiring `8313b555b707829d24f08cb227c6669ec7ec8db1`; widget test `8397f8d7fe75d3a381bc00c9976f9c475b196640`.
- Verification at documentation time: Flutter workflow `40108888325` at the test commit is in progress. PHP workflow has started; its full-schema Stage 5 payroll job has historically failed independently, so inspect the PHP lint job specifically and keep the known payroll issue deferred under #94. Do not claim this slice CI-verified until Dart analysis/tests/APK and endpoint PHP lint results are confirmed. Live Apache/MariaDB role tests and device acceptance remain pending; do not request phone testing yet.


## 2026-10-24 — Mobile app continuation update

The sponsor-request attribution issue #95 is closed after source and CI verification. Sponsor-request status actions are also CI-verified, and the supervisor directory Flutter workflow passed analysis/tests/APK build. Exact run IDs and acceptance boundaries are recorded in `docs/MOBILE_APP_CONTINUATION.md`. Continue the mobile app on `main`, inspect actual source/schema/permissions for the next workflow, and do not ask for device testing until the app reaches the agreed release-readiness gate. Keep live Apache/MariaDB role acceptance distinct from CI. Do not reopen the unrelated payroll CI issue #94 or closed payroll work without root-cause evidence.


## 2026-10-24 — Mobile project progress updates (CI partially verified)

Mobile project progress-update endpoint/form implemented using existing web permissions and fields, without schema changes. Flutter analysis/tests passed; APK build pending at last check; PHP lint passed. See `docs/MOBILE_APP_CONTINUATION.md`. Confirm latest CI, then continue mobile development. Do not request physical-device acceptance until broader release readiness; keep payroll issue #94 isolated.


### 2026-10-24 — Mobile milestone creation checkpoint
Mobile project detail now supports authenticated milestone creation via `api/v1/projects/milestone_create.php`, using existing milestone/history tables without schema changes. Server-side permission checks match the web operation boundary (admin or assigned primary/operations project supervisor) and reject closed projects. Flutter form/API wiring, refresh behavior, widget test and PHP lint workflow coverage are committed on `main`. CI is pending; live role/runtime and device acceptance remain outstanding. Do not request phone testing yet.


## 2026-10-24 — Mobile milestone CI verified; Netlify noted for later deployment planning
- Flutter workflow [40112041575](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40112041575) completed successfully on commit `f571077c894dea7429375ba36768827cfd832095`: Dart analysis, full widget tests, Android debug APK build, and artifact publication all passed. The successful run includes the milestone form/widget test. Artifact name: `ahl-el-kheir-flutter-android-debug-apk`; artifact publication step succeeded.
- PHP workflow [40112106628](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40112106628): `php-lint`, MariaDB 10.4 migration, rescheduling-service tests, and HR job-description migration jobs passed. Its separate full-schema Stage 5 payroll job failed at the known rollback-only payroll/account 1410 regression; keep it isolated under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), unrelated to mobile milestones.
- User provided their Netlify team projects page as a possible option for later live testing: https://app.netlify.com/teams/moneer-ali-taha/projects. Consider it during deployment planning, not as a PHP/MariaDB backend host: Netlify can serve static front-end output (including a Flutter Web build), but this existing procedural PHP API and MariaDB database would need a separate compatible HTTPS host. Do not deploy or connect services without an explicit later deployment plan.
- Mobile milestone creation is CI-verified only. Local Apache/MariaDB role-matrix verification and device acceptance remain pending; do not request phone testing until the broader app reaches release readiness.


## 2026-10-24 — Mobile nanny group verification implementation checkpoint

- Inspected `modules/accounting/group_verification.php`, `modules/accounting/lib_group_workflow.php`, and the checked-in `database/ahl_el_kheir.sql` schema before implementation. Schema confirms the exact `nanny_family_verifications` columns and unique key `(family_id, month)`, plus the `verification_audit_log` columns used by the existing web workflow. No migration or schema change was required.
- Added authenticated `GET /api/v1/nanny/group_verification.php?month=YYYY-MM&group_id=<optional>` and `POST /api/v1/nanny/group_verification_action.php`. Both are restricted server-side to the `nanny` role and use the authenticated user ID to scope active group assignments.
- Read view follows the existing group workflow: active assigned groups, families whose children remain in the group, monthly verification state, and active sponsorship amounts for active family children. Writes support individual verification, bulk verification of up to 500 selected families, and group submission. Individual verification remains available for an assigned active group even when its workflow status is no longer pending; bulk verification and submission require `pending`. Each verification write and its audit row share one transaction; bulk audit failure rolls back the whole operation. No deletion, accounting/disbursement creation, or schema DDL was added.
- Added an Arabic-first/English Flutter page, nanny-only home entry, API wiring, widget coverage for opening an assigned group and saving an individual verification, and PHP lint workflow coverage.
- Commits: GET API `0d26f030340c6192f7a09fd17937f7a773a5ab52`; action API `f0edc26d3c2835c43ed20c1e3bc429a7314fdeb7`; action-response syntax correction `aaf8a39bdba54aa3c83d371da645c490291bebcd`; Flutter page `01c57deeea518f9972127a2aadd8288a85661b4f`; app wiring `1d092bf7427cd5d2885c8339375ffa30097f6ba3`; widget test `ef224b4070e5c06b312e318f984cfd371f1f28b0`; PHP lint workflow `90b55550a467bed32e7064943753b49aac92eb26`.
- Verification is pending at this checkpoint. GitHub Flutter run [40113312109](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113312109) and PHP run [40113317445](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113317445) were still running. A prior full-schema job failed only at the known Stage 5 payroll/account 1410 check; this is issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), not evidence about this mobile feature. Await actual Flutter analyzer/widget/APK/artifact and PHP lint results before claiming CI verification.
- Live Apache/MariaDB nanny-role runtime checks and physical-device acceptance remain outstanding. Do not ask the user to test the phone yet; continue CI/source verification first.


## 2026-10-24 — Nanny group verification: first CI gate passed; closed-group coverage pending

- Flutter workflow [40113312109](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113312109) completed successfully on commit `ef224b4070e5c06b312e318f984cfd371f1f28b0`: Dart analysis passed, all 25 widget tests passed (including the new individual group-verification flow), Android debug APK build passed, and artifact publication succeeded. Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12567906074`, size 77,264,820 bytes, SHA-256 `403b36c3996e3ed98b6b3f6ed859d8bff37486c3496ebc414b691c8f7a051f4c`, is present and unexpired.
- PHP workflow [40113393725](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113393725) passed `php-lint` (including both new nanny endpoints), MariaDB 10.4 migration, rescheduling-service tests, and HR job-description migration jobs. The separate full-schema job failed at the known Stage 5 payroll prerequisite/account 1410 check: `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` Track only under deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94); do not reopen the closed payroll feature or attribute this failure to mobile verification.
- Added a second widget test in commit `4f8a9cf4ef065c8e0c7161d1a16bae4048d77ae4` for the documented closed-group boundary: hide bulk verification and submission, but permit individual verification for a family in a still-assigned active group. Flutter workflow [40113705909](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113705909) is pending at this checkpoint. Do not claim that additional test passed until its run completes.
- The group-verification implementation has CI evidence for source lint, app analysis/tests (first test), APK build and artifact publication. The latest closed-group regression test, live Apache/MariaDB role/runtime checks, and physical-device acceptance remain pending. Do not ask the user to test the phone yet.


## 2026-10-24 — Nanny closed-group test passed; preliminary verification slice added (CI pending)

- Flutter workflow [40113705909](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113705909) completed successfully on commit `4f8a9cf4ef065c8e0c7161d1a16bae4048d77ae4`. The additional widget test passed: closed groups hide bulk verification/submission while allowing an individual verification for a family in a still-assigned active group. This test-only revision passed analyzer, full widget suite, APK build and artifact publication.
- Added authenticated nanny-only `GET /api/v1/nanny/pre_verification.php?month=YYYY-MM` and `POST /api/v1/nanny/pre_verification_action.php`, based on the exact existing `modules/accounting/nanny_pre_verification.php` scope. The read view includes active families with active children in the nanny's active assigned groups regardless of group workflow status, their assigned group names, and shared monthly checks. Individual and up-to-500-family bulk writes use the existing `nanny_family_verifications` and `verification_audit_log` tables; bulk verification only adds selected checks and preserves other checks. No schema changes or runtime DDL.
- Added the Arabic-first/English preliminary-verification Flutter page, nanny-only home entry, bearer-authenticated API wiring, widget tests for individual save and bulk action, and PHP lint workflow entries for both new endpoints.
- Source commits: preliminary GET API `5eca38ee14670ce85e2b7c411faf4c599c08922b`; action API `ca4457984acb435a5f8d1ee9693032cb2cec3036`; Flutter page `a3ecc1ac649643451a462aea1c571c583d6359a0`; app wiring `250f4eab0e5101e10c56c8335af9165c6bad0fa2`; PHP lint workflow `9d170e3839e26799fff3a8f64271509ed4ab56ca`; tests `23c81c2dd69c0bd47bdf21d39415dfb550e1685c`.
- PHP lint passed in workflow [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834) for both preliminary API endpoints. The remaining independent full-schema job is not a mobile acceptance test; it has historically failed at the known Stage 5 payroll prerequisite/account 1410 check under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Flutter workflows [40113978865](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113978865), [40113993153](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113993153), and [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) were still running/queued at this checkpoint. Do not mark the preliminary-verification UI/tests/APK verified until the latest test commit passes. Live Apache/MariaDB nanny-role checks and physical-device acceptance remain deferred; do not ask for phone testing yet.


## 2026-10-24 — Mobile nanny preliminary verification CI result

Flutter CI run [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) passed analysis, widget tests, Android debug APK build, and artifact publication for the preliminary-verification flow. PHP lint passed for both new endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). Latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014) had PHP lint, MariaDB 10.4 migration, and rescheduling tests passed; remaining jobs were still running at last check. This supersedes the older pending-CI note. Live API role-matrix and device acceptance remain pending. Continue mobile work only after source/schema and authorization-scope inspection; do not duplicate nanny verification flows or request phone testing before broader release readiness.


## 2026-10-24 — Employee details verification checkpoint

Employee detail API/UI/navigation passed Android analyzer, tests, and debug APK build at commit `a78bc176` (run [40115151758](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115151758)). Later changes added an Arabic detail-page test and localized English employment-state name; wait for latest run [40115569057](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115569057) before calling the newest revision green. Live API authorization/database verification and explicit PHP lint coverage for `api/v1/hr/employee_detail.php` remain outstanding. Keep the full-schema Stage 5 payroll migration prerequisite failure separate and inspect its test setup before changing anything.


## 2026-10-24 — Employment-state localization refinement

Refined the employee detail screen to show the actual localized employment-state name instead of collapsing every `working` category into a generic label; this preserves distinctions such as probation. Updated the Arabic widget test fixture to include both Arabic and English state names. Latest Android build/test run [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) was queued at the last check. Earlier commit `a78bc176` passed analyzer, Flutter tests, and debug APK build; the newest revision still needs its own green result. PHP endpoint lint/runtime role tests remain outstanding, and the full-schema payroll migration prerequisite failure is separate.

## 2026-10-24 — Mobile app checkpoint after employee details

Latest employee-details Flutter CI is green: [run 40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291), commit `ed9e169ab8bf75be8592d6765181314679b1dd06`. `mobile/ahl_el_kheir_app/README.md` has been corrected to reflect integrated modules and release boundaries. Do not report the mobile app as complete or production-ready. Outstanding acceptance: live Apache/MariaDB API role and record-scope checks, PHP lint coverage for `api/v1/hr/employee_detail.php`, and a real HTTPS-compatible PHP/MariaDB host. Netlify static hosting alone is not a backend for this app. Continue by inspecting the next uncovered existing web workflow and its authorization/schema; avoid duplicating nanny verification and avoid reopening closed work. Do not ask for physical-device testing yet.

## 2026-10-24 — Self-service mobile salary statements added (CI pending)

- Added authenticated `GET /api/v1/hr/my_salary_statements.php` and an Arabic-first/English Flutter screen linked from the signed-in home page. It resolves only the current user's employee profile through the existing `hrGetEmployeeForUser()` helper and returns only that employee's payroll rows where `status='paid'` and `accounting_status='posted'`, matching the web statement's availability boundary. Draft, approved-unpaid, and unposted rows are excluded by query conditions; no schema changes were introduced.
- Inspected `database/ahl_el_kheir.sql` directly through its Git blob: the `payroll`, `employees`, and `hr_employee_salary_history` columns used by the endpoint exist. The salary currency lookup follows the same effective-date selection used by `modules/hr/payroll_statement.php`.
- Source commit: [`7bcb340c83f42211bb6d65e80d57af3d52e95949`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/7bcb340c83f42211bb6d65e80d57af3d52e95949). The first Flutter run [40116469884](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116469884) found a widget-test fixture collision (two displayed amounts were both `100.00 SDG`); only the fixture was adjusted in [`dd226b794ca9136aa76359be62b927b018370bad`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/dd226b794ca9136aa76359be62b927b018370bad). Latest Flutter run [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126) was still in progress at this checkpoint; do not call the feature CI-verified until it finishes green.
- A PHP CLI syntax check of the new endpoint source returned `No syntax errors detected`. The existing PHP workflow's explicit lint list still does not include this new endpoint, and live Apache/MariaDB role-and-data testing remains outstanding. No device testing requested yet.


## 2026-10-24 — Mobile HR API PHP lint coverage expanded

- Added `api/v1/hr/employee_detail.php` and `api/v1/hr/my_salary_statements.php` to `.github/workflows/php-syntax-check.yml` so the two mobile HR endpoints are syntax-checked by CI, rather than relying only on a one-off CLI lint.
- Change committed directly to `main`: [`ebbf618117bab4d1235459621809ae824f0f863f`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/ebbf618117bab4d1235459621809ae824f0f863f). The workflow execution for this commit still needs confirmation; do not call this CI-verified until its PHP-lint job passes.
- The self-service salary statement Flutter revision passed Android workflow [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126): analyzer, widget tests, debug APK build, and artifact publication succeeded. This does not verify live API authorization/data behavior. Local Apache/MariaDB role-scope acceptance and a suitable HTTPS PHP/MariaDB deployment remain outstanding.


## 2026-10-24 — Mobile API syntax-check coverage completed (CI pending)

- Audited the checked-in `api/v1/` tree against `.github/workflows/php-syntax-check.yml`. All 26 PHP API endpoint files are now included in the selected PHP lint list, including authentication (`login`, `logout`, `me`), notifications (`index`, `mark_read`), HR, nanny, projects, sponsors, sponsorships, families, supervisors, transactions, and sponsor requests.
- Commit: [`eae29e5e210019ebdc6c8defd9ca9152e445e29b`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/eae29e5e210019ebdc6c8defd9ca9152e445e29b). Workflow run [40117147104](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117147104) was still running when checked; do not mark lint coverage CI-verified until `php-lint` concludes successfully. Other jobs in that workflow are separate existing test suites and should not be changed without their own concrete failure evidence.
- Live Apache/MariaDB authorization and record-scope acceptance is still outstanding. Do not treat source-level role checks or successful builds as proof of runtime security.

## 2026-10-24 — Mobile financial review queue checkpoint
Added read-only `GET /api/v1/accounting/fm_review_queue.php` and `FmReviewQueuePage` in the Flutter app, using the role boundary and pending/recent-history behavior from `modules/accounting/fm_review_queue.php`. The API uses existing `sponsor_payments`, `users`, `sponsorships`, `sponsors`, and `family_children` columns verified in `database/ahl_el_kheir.sql`; no migration/schema changes. Receipt file paths are not returned. The mobile page is read-only; approval/return actions remain out of scope until separately mapped to the existing atomic review workflow. Flutter widget test and PHP lint coverage are added; await CI. Live Apache/MariaDB role/data tests remain outstanding.

## 2026-10-24 — Current checkpoint: CI harness prerequisite fix (pending CI)
Latest confirmed failure was not PHP lint or the production payroll guard: full-schema CI failed because Stage 5 test harness attempted to mark a payroll row paid without applying the payroll-transfer migration and supplying a prepared proof file. Updated `.github/workflows/php-syntax-check.yml` to apply `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql` to the disposable imported full-schema DB. Updated `tools/run_salary_advance_stage5_payroll_tests.php` and `tools/test_salary_advance_rescheduling_payroll_concurrency.php` to create a valid account assignment plus prepared proof metadata/private bytes for rollback-only fixtures and clean up the bytes/metadata. No production rule changed. Wait for fresh CI; inspect any next failure at its exact source. Live local API authorization checks and mobile end-to-end acceptance remain outstanding.

## 2026-10-24 — Latest CI checkpoint: CLI session actor fixture
After applying the transfer migration and adding prepared proof fixtures, run [40117930844](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117930844) failed at the expected proof-confirmation FK because CLI `Session::getUserId()` returned `0` without session state. Corrected only the test harnesses: set `$_SESSION['user_id']` to an existing user in `tools/run_salary_advance_stage5_payroll_tests.php` and the concurrency child process. Production validation remains unchanged. Check latest PHP/full-schema CI and inspect exact logs if any further failure occurs.

## 2026-10-24 — Latest verified checkpoint
The mobile financial review queue code at `60883414` passed Flutter workflow [40117632176](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117632176) (analyzer, widget tests, debug APK, artifact). Payroll CI harness alignment then passed all jobs in [40118139681](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118139681), including full-schema Stage 5 payroll, 1410 reconciliation, and payroll/rescheduling concurrency rollback checks. The fix only updated CI migration setup and test fixtures; production payment requirements remain intact. Next: continue source audit of mobile API role/record boundaries and plan live local Apache/MariaDB acceptance; do not ask for device testing until the broader acceptance gate is met.

## 2026-10-24 — Mobile financial review queue action checkpoint
Current mobile financial review queue includes approve-and-post and return-for-correction. Endpoint: api/v1/accounting/fm_review_queue_action.php; UI: mobile/ahl_el_kheir_app/lib/fm_review_queue_page.dart. The server derives actor identity from the bearer session and enforces the web queue's role allowlist. Approval reuses the existing ak_approve_sponsor_payment() atomic business operation. Return requires a reason, locks/rechecks pending status, updates reviewer/status/time and attempts audit recording before commit; notification is post-commit and isolated. No schema change. PHP lint and all PHP/database jobs passed in 40118666512 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512). Android analyzer/widget/APK workflow 40118666504 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) was still in progress. Do not claim end-to-end acceptance until it passes and the live local API is tested with authorized and unauthorized roles, including duplicate/stale pending actions.

## 2026-10-24 — Latest mobile checkpoint: financial review actions CI-green
Financial review queue is no longer read-only: the Flutter page offers Approve and post, or Return with mandatory reason. API approval reuses `ak_approve_sponsor_payment()` in a transaction; return locks the pending row, writes `return_note`/audit information, and notifies the supervisor after commit. Both use the existing queue role allowlist. General Manager access to sponsorships was corrected to match web permissions. Code commit `734df683` passed Android workflow [40118666504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) and PHP/database workflow [40118666512](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512); all 31 API endpoints are in PHP lint. Continue with source-level audit and plan live local Apache/MariaDB authorization/action verification; do not request device testing until the broader acceptance gate is met.

## 2026-10-24 — Latest project API scope audit checkpoint
Fixed a source-level authorization mismatch in mobile project APIs: project detail now defaults missing approval state to `draft`; milestone creation now checks approval and lifecycle for non-admin supervisors, matching the existing web view/progress boundary; and `can_add_milestone` is computed consistently with the endpoint. CI [40119297504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40119297504) passed all PHP/database jobs. Continue API role/record-scope audit with source evidence; local Apache/MariaDB runtime checks remain outstanding.

## 2026-10-24 — Current mobile continuation point

Continue from the milestone maintenance change on main. Confirm the latest Android workflow and PHP lint workflow results for `api/v1/projects/milestone_action.php`. Fix only concrete CI failures. Then continue source/schema-led API role and record-scope audit. Live local Apache/MariaDB authorization/action tests remain outstanding; do not request physical-device testing until broader mobile release readiness. Do not reopen closed payroll/attendance work.

## 2026-10-24 — Current continuation: milestone maintenance CI

Latest mobile change separates milestone edit/delete permission from the creation capability. The mutation endpoint matches the web edit/delete gate: admins may act on non-closed projects; project supervisors need an active primary-supervisor or operations assignment; milestone IDs are project-scoped. Check the latest Android workflow and PHP/database workflow before claiming verification. The new Flutter widget test had failed twice at the dialog-open assertion; stable keys and explicit visibility/tapping were added, then the capability fixture was updated. On source SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`, Dart analysis, all Flutter tests, debug APK build, and artifact publication passed in run [40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219). PHP/database run [40121128872](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40121128872) passed all jobs on SHA `2a664a66316319983dc32d48cdfa9024b770a71a`. APK predates the final PHP-only authorization alignment; Flutter source was unchanged after its build. Do not ask for device testing until CI passes and the remaining API scope/runtime gates are assessed.


## 2026-10-24 — Mobile auth migration CI gate
Continue mobile app work from current `main`. The PHP/database workflow now includes `api/v1/_bootstrap.php` in PHP lint and applies `database/migrations/2026-10-24_mobile_api_sessions.sql` twice after importing the base schema into the disposable full-schema MariaDB 10.4.32 job. Check the resulting workflow run before calling this passed. This does not apply the migration to local `ahl_el_kheir` and does not replace runtime authentication/session/role/record-scope acceptance. Keep the current release blockers: verify local migration state first, run controlled real Apache/MariaDB API acceptance only after target DB confirmation, and identify an HTTPS PHP/MariaDB hosting target. No public XAMPP exposure and no phone-testing request until the release-readiness gate. Do not reopen completed unrelated tests.


## 2026-10-24 — Mobile auth migration CI gate passed
The PHP/database workflow passed after the mobile auth migration coverage change: [run 40122798655](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40122798655), source SHA `09a94183a9b2cb1e23feefeaf8733b02d97537c3`. All five jobs passed, including `php-lint` and `migration-full-schema`; the latter imported the checked-in schema, applied `2026-10-24_mobile_api_sessions.sql` twice to the disposable MariaDB 10.4.32 database, and completed the existing full-schema regressions. This proves the CI migration step succeeds in that disposable environment, not that the migration is applied locally or that API auth passes live runtime tests. Latest Android build remains successful at [run 40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219) on Flutter SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`; no Flutter source changed after that build. Remaining gates: local migration-state confirmation, controlled local Apache/MariaDB API auth/session and authorization tests, and HTTPS PHP/MariaDB hosting. Do not request phone testing yet.

## 2026-10-25 — Current mobile continuation point

A source comparison found the Flutter compile-time API fallback on port `881`, while the workflow default and existing local/phone reachability evidence point to Apache port `8081` (the previous local check found no listener on `881`). The fallback was corrected in `mobile/ahl_el_kheir_app/lib/main.dart` in commit `41cd2e0176ee4bf2254dfc92fadde6b8c3ba6665`. Check the Android workflow triggered by this change before claiming CI verification. Continue the mobile API source/schema-led authorization audit and design controlled, non-destructive local Apache/MariaDB acceptance; do not request device testing or claim runtime security from CI alone. Do not reopen closed unrelated work.


## 2026-10-25 — Mobile Android CI verification

The Android workflow for fallback API URL correction commit `41cd2e0176ee4bf2254dfc92fadde6b8c3ba6665` completed successfully: [workflow run 40156431795](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40156431795). Flutter dependency resolution, Dart analysis, Flutter tests, debug APK build, and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,305,556 bytes), available until 2026-11-24. This is a debug APK build/test result, not production signing or live API acceptance. The corrected fallback remains `http://192.168.18.9:8081/AhlElKheir/api/v1`; verify the current LAN IP before using the artifact on a physical phone. Local `ahl_el_kheir` migration state, authenticated role/record-scope/action acceptance against Apache/MariaDB, and HTTPS PHP/MariaDB hosting remain open release gates. Do not claim the full app is complete or request final phone acceptance yet.


## 2026-10-25 — Mobile API authentication integration harness added

- Added `tools/test_mobile_api_auth_integration.php`, a live HTTP integration harness gated to the exact disposable database name `mobile_api_auth_test` and the explicit `MOBILE_API_AUTH_TEST=1` flag. It refuses other database names, uses the checked-in schema, and restores the selected user's original account fields while deleting test-created bearer sessions and test login audit rows.
- Added `.github/workflows/mobile-api-auth-integration.yml`. It imports `database/ahl_el_kheir.sql` into disposable MariaDB 10.4.32, applies the mobile-session migration twice to check repeatability, starts the real PHP API endpoints, and tests missing/invalid credentials, wrong HTTP method, valid login, hash-only token storage, `/me`, role-based HR endpoint denial/allowance, live role changes, account deactivation, password-change-required denial, expired/revoked sessions, and logout revocation.
- CI runtime result: GitHub Actions run `40157433470` completed with conclusion `success` on commit `f5dfe7f7b1ab118f259909d555d212634635da8c`. The job successfully imported the checked-in schema, applied the mobile-session migration twice, and completed the live HTTP integration script. Because the script exits nonzero on a failed assertion, this verifies the scripted authentication/session paths against disposable MariaDB. It does not verify every business endpoint, record-level scope, brute-force throttling, or live local XAMPP behavior.
- The harness uses only a disposable CI database. Do not run it against `ahl_el_kheir` or a production database.
- The existing `.github/workflows/php-syntax-check.yml` still runs its historical salary-advance regression jobs on every push to `main`; a commit adding this harness will trigger that workflow too. Do not interpret that automatic CI activity as a reason to reopen completed salary-advance work.


## 2026-10-25 — Mobile project portfolio approval fallback correction

A follow-up source audit found `api/v1/projects/index.php` still defaulted a missing `project_approval` row to `approved`, while `api/v1/projects/detail.php` and the supervisor/project-manager authorization helpers use `draft` when the approval row is absent. The portfolio endpoint’s row-visibility helper separately queries authoritative approval state for restricted roles, so the confirmed inconsistency was the portfolio response’s displayed `approval_status` (and any client behavior based on that response), not a proven authorization bypass. Changed the portfolio fallback to `draft` in commit `cb5d222e1f5a6c9b88ddd56bbfd6ae2fb859df77`. Verification: the PHP lint job passed in GitHub Actions run `40157943519` on commit `c6a525c2fad2462bd68bf2759e94e10d60ce25b2`, which includes this source correction; the disposable live HTTP authentication/session integration workflow passed in run `40157925602` on the code commit. The auth integration run does not exercise the project portfolio endpoint, so no dedicated project endpoint runtime assertion is claimed. No schema or database changes were made. No schema or database changes were made.

## 2026-10-25 — Mobile project portfolio and progress authorization integration tests

- Extended `tools/test_mobile_api_auth_integration.php` with disposable-CI-only business endpoint assertions. It creates a uniquely coded temporary project without a `project_approval` row and verifies authenticated `GET /api/v1/projects/index.php` returns `approval_status=draft`. It then creates approved/active project fixtures, verifies an assigned project supervisor can post a progress update, ends that supervisor assignment, and verifies the next progress update is denied with HTTP 403.
- Code commit: `7194871271a058005a19534caff9d8d3ab837d49`.
- Runtime integration verification: PASS in [GitHub Actions run 40158833255](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40158833255). Log assertions explicitly passed: `project portfolio defaults a missing approval row to draft`, `assigned project supervisor can add progress to an approved active project`, and `project supervisor cannot add progress after the assignment ends`. The complete existing auth/session integration suite also passed.
- The harness removes its temporary project, progress update, supervisor assignment, lifecycle row and approval row in cleanup. No schema or migration changes were made, and no local/production data was touched.
- Source audit inspected transaction-time assignment revalidation and web assignment locking; no additional confirmed concurrency defect was found, so no speculative production change was made.
- Do not run this harness against `ahl_el_kheir` or any production/development database; it explicitly requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`.


## 2026-10-25 — Latest mobile API audit checkpoint
A concrete API/UI contract defect was corrected on `main`: `api/v1/projects/detail.php` selected project milestone IDs but omitted them from the response, while Flutter edit/delete actions require `milestone_id`. Commit `20631106ae8fe9f8267779faf4c6e9a1d7608057` adds the ID to each serialized milestone. Focused disposable-database endpoint coverage passed in run `40160569643`; ended-assignment and cross-project no-mutation assertions passed in run `40160867381`. Continue mobile API role/record-scope review. Do not reopen closed payroll/attendance/waiver tests. Local Apache/MariaDB authorization/action acceptance and an HTTPS PHP/MariaDB host remain open release gates.


## 2026-10-25 — Mobile API milestone CI verification
The focused project-detail milestone ID assertion and ended-assignment mutation denial both passed in [Mobile API auth integration run 40160569643](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160569643), source test commit eebe53983eed73e9899ca9cf5d85754497e3e092. The job used the checked-in schema and disposable MariaDB; it does not prove local migration state or local Apache/MariaDB acceptance. Continue reviewing remaining API role and record boundaries. Do not run the harness against ahl_el_kheir or reopen closed unrelated modules.


## 2026-10-25 — Mobile notification isolation test passed
Commit `ab2a6173e596f88ed4e7c2fb6e962e40e5429192` adds a disposable-CI fixture proving another account's notification is excluded from the authenticated user's list and cannot have its read state changed through the API. [Mobile API auth integration run 40161671591](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161671591) passed against the checked-in schema and disposable MariaDB. Continue the remaining source/schema-led API authorization audit; local XAMPP migration state and live local role/record-scope/action acceptance remain unverified. Never run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile sponsor-request authorization tests passed
Commit `f0dfa70a376771cbceeae04e73b57f2e6e40cb89` adds live disposable-DB checks for sponsor-request role denial and state-transition integrity. Nanny list/mutation is denied with no state change; admin can transition `new` to `contacted`; repeating the transition is rejected with 409 and no mutation. [Mobile API auth integration run 40161801824](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161801824) passed. Continue the source/schema-led API authorization audit; local XAMPP migration state and broader live local role/record-scope/action acceptance remain unverified. Never run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile nanny family scope tests passed
The disposable mobile API integration harness now verifies that a nanny can view an assigned family, cannot view another user's family, and receives a list containing the assigned fixture but excluding the foreign fixture. The first attempt timed out at the five-second client limit because the checked-in schema contains 2,208 family rows; only the family-list request's test timeout was raised to 20 seconds. Commit `9312a7118ebe7876986dcecfda0bdcd0e3e594d9`; [run 40162023286](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162023286) passed. Continue the source/schema-led audit. Local XAMPP migration state and broader live local role/record-scope/action acceptance remain unverified; never run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile supervisor sponsor scope tests passed
The disposable integration harness now proves a supervisor cannot access a sponsor solely because `sponsor.supervisor_id` matches: when the sponsor's first-letter/gender falls outside that supervisor's matrix, detail returns 403 and list omits the sponsor. Commit `de9af9c85a56f5f5f593ec404dda7b26753bfdb8`; [run 40162198710](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162198710) passed. Continue remaining API authorization/source review; local XAMPP migration state and broader live local role/record-scope/action acceptance remain unverified. Never run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile API milestone lifecycle guard
The mobile API source audit found that milestone edit/delete did not enforce the approval and operational lifecycle guard already present in milestone creation. This was corrected on `main` in `bc6f6ee0104326ee536fdaa732f1e693de739eb8`. A disposable-CI test now attempts to edit a milestone on the draft fixture and verifies both HTTP 409 `project_not_operational` and no data mutation; test commit `c6cc08b3b646c1906392019e116a646ab038a5df`. Run 40162518220 completed with conclusion `success`; the live HTTP integration harness passed against disposable MariaDB, including the draft milestone denial/no-mutation assertion. Continue the source/schema-led API authorization audit. Local XAMPP migration state and live role/record/action acceptance remain open. Never run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile API leave-approval integration checkpoint
Latest test commit: `fd7d9652c3d9cc1bc6b6b17900742ef0f7d1d30b`. [GitHub Actions run 40162902436](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162902436) passed the disposable MariaDB/live PHP HTTP suite. New assertions prove: nanny approval denied with 403 and pending state preserved; HR approval of a non-HR employee's leave persists `hr_approved` and the correct approver, creates expected `on_leave` attendance, and repeated approval returns 409 without mutation. Exact fixture leave/attendance/linked notifications are cleaned up. Source inspection of `api/v1/hr/leaves/action.php` did not confirm the suspected concurrent-approval defect: conditional pending-state update, affected-row validation and transaction rollback are already present, so no production change was made. Continue the source/schema-led audit only where a concrete gap is evidenced. Local XAMPP migration state and live local API acceptance remain open. Never run the harness against `ahl_el_kheir`; it requires `mobile_api_auth_test` plus `MOBILE_API_AUTH_TEST=1`.


### Mobile API authorization audit checkpoint — 2026-10-25

The mobile API authorization audit remains open. Commit `fbf83136ace25fb80c3f51f82b529552aa311d85` adds four disposable-harness assertions that HR staff are denied nanny group/preliminary verification GET and action routes. Documentation of the checkpoint is in `docs/MOBILE_APP_CONTINUATION.md`. These assertions are verified by the successful live-HTTP integration run `40164371241`; see the latest CI verification checkpoint below. They are test coverage, not a newly confirmed production defect. Do not run `tools/test_mobile_api_auth_integration.php` against `ahl_el_kheir`; its guard requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`. Preserve local runtime acceptance as pending and continue source-grounded endpoint review.


### Mobile API source review — 2026-10-25

Additional source review covered the bearer-session bootstrap/login/me/logout path, family/sponsor/sponsorship detail scoping, HR attendance access, and employee salary-statement scoping. No new production authorization defect was confirmed in these paths. This is source inspection only. The assertions are verified by the successful live-HTTP integration run `40164371241`; the Flutter workflow is separate from the PHP harness. Continue treating disposable-database HTTP integration and local runtime acceptance as open. See `docs/MOBILE_APP_CONTINUATION.md` for the detailed checkpoint.


## 2026-10-25 — Mobile API role-denial integration assertions VERIFIED in CI

The earlier pending-verification notes for the new read/action role-denial assertions are superseded for these specific assertions. GitHub Actions run [40163578388](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40163578388) passed on test commit `d62656a87dd77f187254b7f342c425e6dac0dcbf`; run [40163733247](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40163733247) passed on test commit `fbf83136ace25fb80c3f51f82b529552aa311d85`. Both runs imported the checked-in schema, applied the mobile-session migration twice to disposable MariaDB 10.4.32, and passed the live PHP HTTP integration harness. Logs explicitly show all seven nanny read-endpoint denials and all four HR-staff denials for nanny verification read/action routes passing. No production endpoint or schema change was made in those test commits. This verifies the scripted assertions in CI only; it does not establish local XAMPP migration state or local Apache/MariaDB acceptance. Continue the finite source/schema-led audit and keep local runtime/release gates open. Never run the harness against `ahl_el_kheir`; it requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`.


## 2026-10-25 — Latest mobile API integration run passed

GitHub Actions run [40164371241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40164371241) passed on commit `428decce211ab7a9cff743375679ba16f3e1fae4`. The run imported the checked-in schema, applied the mobile-session migration, and ran `php tools/test_mobile_api_auth_integration.php` over live HTTP against disposable MariaDB. The log explicitly confirms the added checks `nanny is denied access to the HR leave approval queue` and `nanny cannot create project milestones` passed, along with the broader suite. The same commit's separate salary-advance verification workflow also passed (run `40164371167`). This verifies CI only; local XAMPP acceptance and the final full audit closure remain outstanding. The test harness is restricted to `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`; never run it against the real `ahl_el_kheir` database.


## 2026-10-25 — Finite mobile API source-review checkpoint

The current source pass covered the remaining project portfolio/detail authorization paths, sponsor-request role gates, notification ownership checks, HR leave request/approval boundaries, nanny verification role and active-assignment checks, and financial-review action gates. Existing tests and source checks were cross-referenced against the live-HTTP harness. No additional production authorization defect was confirmed in this pass, so no production code change was justified. `api/v1/sponsorships/index.php` does load/filter rows in PHP; this is a potential scalability concern, not a proven authorization bypass, and is not being changed as part of this audit without measured evidence and a separate performance scope.

The latest integration test commit `428decce211ab7a9cff743375679ba16f3e1fae4` passed [Mobile API auth integration run 40164371241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40164371241), including the new HR leave-queue and project-milestone role denials. The same commit's separate salary-advance verification workflow passed run `40164371167`. The CI gate is therefore green for the current harness. Remaining acceptance item is local XAMPP confirmation of the explicit `2026-10-24_mobile_api_sessions.sql` migration and a controlled local API smoke test; do not run the integration harness against `ahl_el_kheir`. The audit remains open only for that local acceptance gate and final reconciliation of the finite checklist, not because a production defect is currently confirmed.


### Active task — verify Flutter menu navigation fix on device (2026-10-25)

Commit 49ef28ad185f72c4d484909d29a200797d66e718 on main fixes the root cause found in mobile/ahl_el_kheir_app/lib/main.dart: app-level menu callbacks were using a build context above MaterialApp in Navigator.of(context). Added a state-owned GlobalKey<NavigatorState>, attached it to MaterialApp, and changed app-menu route pushes to use the key. User observed menu ripple/highlight but no navigation/API request; language toggle worked. Re-fetched source confirms 16 menu pushes now use the key and no context-based push remains in the home/menu section. Next: wait for the Flutter workflow to finish, obtain its APK artifact, have the user install it, then test Projects while watching Apache access.log. Close only after the page opens and a corresponding API request is observed. No PHP/schema changes; never run tools/test_mobile_api_auth_integration.php against the real ahl_el_kheir database.


## 2026-10-25 — Mobile home-screen design and personal-link grouping

The user performed a quick device test and confirmed login works with multiple user accounts and the menu links now open. This is preliminary acceptance only; the user has not completed detailed testing.

Source change: `296b318ae173e2be9383e2308266ec7235cc7cbc` updates `mobile/ahl_el_kheir_app/lib/main.dart` to bring the home screen closer to the existing web system's established navy/blue, Cairo, and light-neutral design tokens. The welcome panel now uses the existing navy gradient, menu cards share rounded bordered surfaces with reduced elevation, and the flat menu is divided into **Work areas** and **My account** sections. Salary statements, leave requests, and notifications are grouped under the personal/account section instead of being mixed into the operational modules.

The web stylesheet's root palette already matches the app's existing color values; the main mismatch identified was the mobile screen's generic flat composition and missing hierarchy, not evidence for inventing a new brand palette. Existing role visibility conditions, routes, API calls, and server authorization were deliberately left unchanged in this visual pass. The salary-statement API returns an empty, explicit unlinked-profile state for accounts without an employee profile; the leave API similarly scopes personal requests and restricts review queues server-side.

**Verification status:** source update committed to `main`; Flutter CI and device retest are pending. Do not call this redesign runtime-verified until the new APK build succeeds and the user checks the home screen with multiple role accounts. User's current feedback is quick-test evidence, not a full acceptance run.
