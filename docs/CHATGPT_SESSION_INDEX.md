# Ahl El Kheir — Session Index

## Canonical reading order

1. AHL_EL_KHEIR_SYSTEM_ANALYSIS.md
2. AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
3. AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
4. AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
5. AHL_EL_KHEIR_OPERATIONS_SECURITY.md
6. AHL_EL_KHEIR_DOCUMENTATION_INDEX.md

## Domain references

Projects: PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md and related Projects checkpoint documents.

HR/Salary Advance: HR_SALARY_ADVANCE_CONTINUATION.md and HR_SALARY_ADVANCE_STAGE5_AUDIT.md.

Fina: FINA_SETTLEMENT_PROCESS.md and FINA_STANDALONE_PAYMENT_MODEL.md.

Accounting: ACCOUNTING_JOURNAL_INTEGRITY_CHECKPOINT_2026-09-15.md and AUDIT_SUPERVISOR_ACCOUNTING_INTEGRATION_20260914.md.

## Current continuation point — 2026-10-06

Projects Phase 5 is closed after full controlled reconciliation and financial closure.

Do not repeat closed fixtures without regression evidence. Continue from the next genuinely open engineering or audit item.

## Working rule

Repository source, schema and later verified evidence outrank chat history and older checkpoint text.


### HR checkpoint — 2026-10-05

The HR employee salary register is implemented and refined:
- first positive salary-history value is used instead of the mutable current salary;
- the automatic 0.00 provisioning placeholder is ignored;
- current varied salary values are database test data only;
- non-working employment states are excluded from the current register using `hr_employment_states.category = 'working'` and `is_active = 1`;
- historical HR/payroll/accounting evidence remains preserved.

Latest related implementation commit: `b8adb067c3de34441192e63fff56c6b707b3aa22`.

The next session should inspect the current `main` state and identify the next concrete HR/accounting/reporting requirement rather than changing salary-register data again without a business reason.

### HR attendance policy checkpoint — 2026-10-05

Attendance policy foundation is implemented on main. Rules are versioned in hr_attendance_policy_versions; administration is modules/hr/attendance_policy.php; successful-login integration is in index.php through modules/hr/lib_attendance_policy.php; automatic absence is tools/finalize_daily_attendance.php. Default/example values are remote work, 07:00–16:00, stored as policy data. Canonical attendance eligibility remains based on employment state and approved leave.

Status: source implementation complete; migration application and runtime verification pending. Next gate: apply the migration, create/verify the first effective policy, test qualifying login, verify repeated login preserves the first check-in, verify non-working/approved-leave exclusions, and execute the finalizer idempotently.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.


## 2026-10-05 — Same-page POST navigation remediation

A repository-level audit confirmed that many operational pages use same-path HTTP POST forms that submit as full-document browser navigations. The previous `akGlobalScrollRestore` mechanism only repaired the viewport after that navigation and therefore could not eliminate the underlying visible jump.

Implemented on `main`:
- `assets/js/app.js`: centralized same-path POST interception using `fetch()`, replacing only the shared `.content` region and preserving the existing viewport; cross-page redirects remain normal navigations.
- `modules/hr/attendance.php`: attendance bulk refresh now uses the centralized in-place refresh; return-from-leave confirmation now re-enters the normal submit event so the centralized handler can process it.
- `includes/header.php`: removed the obsolete first-paint scroll-restoration guard because same-page POSTs are now kept in the existing document.

Commits: `412301e82718dba9a96146b4b9b42c4298469619`, `c7ade0903bf3aefbeed2d0e272c91a889d19038c`, `9e2e781ba465fd0c3ab4bdd41f0ac4e52f1b5b84`.

**Verification status:** code/repository review completed; local browser runtime verification is still required before marking the issue closed. Do not treat the change as runtime-verified until the attendance and representative same-page POST workflows are tested locally.


### 2026-10-05 — Same-page POST scroll issue: UNFINISHED

The attendance same-page POST viewport-jump issue remains **UNFINISHED / NOT FIXED**.

Runtime testing after multiple targeted remediation attempts still shows the browser jumping upward after the attendance POST. The attendance operation itself succeeds, but the viewport behavior is not acceptable. Do **not** mark this issue resolved and do not keep applying speculative scroll/focus/timing workarounds without first performing a causal browser/runtime diagnosis. The latest attempted commit was `087cf3bc456b72a58c263a4cc8d12e2e604294ba` (submit-time scroll capture); the user confirmed that it made no observable difference.

Relevant recent investigation commits include:
- `e788cf0d2ac6005a47b1c629c3c66604d50814bc` — isolated same-page flash toasts from the content swap.
- `f99080a54be760f35feaa41142b571b719e54feb` — temporary paint/scroll-anchor lock; runtime result still jumped upward.
- `087cf3bc456b72a58c263a4cc8d12e2e604294ba` — submit-time viewport capture; runtime result unchanged.

The next time this issue is resumed, use the existing causal scroll-diagnostic instrumentation to identify the exact operation that changes scrollY before making another behavioral change. Do not reopen this issue during unrelated module work unless explicitly requested.

### 2026-10-05 — New Salary Advance business requirement to study

A new business requirement has been raised: the **General Manager (GM)** may, for any legitimate reason, decide to **waive salary advances / salary-withdrawal requests** that employees have taken or submitted.

This is a **new requirement for analysis and design**, not yet implemented or approved as a final workflow. Before coding, audit the existing salary-advance lifecycle, policy, accounting, payroll repayment, notifications, audit trail, permissions and actual database schema. Determine precisely what “waive” means for each lifecycle state, whether the waiver can apply to an outstanding balance, an approved-but-not-disbursed request, or other request states, and how the financial/accounting consequences must be represented without destroying historical evidence. The GM's authority, approval/action point, audit evidence, employee notification, and interaction with future payroll deductions must be explicitly designed and verified before implementation.

Do **not** reopen or alter the completed Salary Advance Stages 1–6 merely because this new requirement touches salary advances. Treat the new waiver capability as a separate change request / exceptional lifecycle extension unless the audit proves an existing closed-stage regression.


### 2026-10-05 — GM Salary Advance Waiver / Exemption: DESIGN AGREED + IMPLEMENTATION IN PROGRESS

The new salary-advance waiver requirement is fully designed and documented in docs/HR_SALARY_ADVANCE_GM_WAIVER_CONTINUATION.md.

Authoritative business rule: GM may waive salary advances/withdrawal requests for any reason. A blanket decision includes previous loans. If a current payroll deduction has already been paid, that amount must be refunded; all remaining covered balances must be waived and future deductions stopped. Historical disbursement, repayment, payroll and journal records must remain intact.

Agreed authority flow: GM informs FM outside the system; FM prepares a concrete decision in-system; GM reviews/approves/rejects; after approval FM executes. Decision statuses are pending_gm, rejected_by_gm, approved_by_gm, executed. GM approval creates no journal.

Mandatory notifications: FM preparation -> GM; GM approval/rejection -> preparing FM; FM execution -> approving GM; FM execution -> every affected employee with a linked active account. Employee notifications must communicate the GM decision and FM action, consolidate multiple affected advances per employee, and occur after successful commit. Notification failure must never roll back financial execution.

Current implementation remains isolated to the eight feature files and has not been runtime verified. Migration database/migrations/2026-10-05_hr_salary_advance_waiver.sql is not yet applied locally. Feature baseline: af111bdfbb3770ea5ac782bc2f54376cfb565709. Latest notification wiring: 4415244444006e445912d231e8b59e80773b7857. Documentation checkpoint commit: f27dd3b952a2463efb4ce6fd3c7dc22b1bd9699e.

Do not mark the feature complete until the documented runtime verification matrix passes. Do not mix it with the unfinished attendance scroll-jump issue or reopen Salary Advance Stages 1–6 without a proven regression.


### GM salary advance waiver — 2026-10-06

The GM salary-advance waiver remains the active work unit. Stages 1–6 of the salary-advance module are closed and must not be reopened without regression evidence.

The waiver implementation has been source-hardened and the local database migration is already applied. PHP syntax checks pass for the waiver files. The existing rollback-only Stage 5 payroll harness has now passed locally for `SAR-2026-00004`, proving payroll repayment application, Cr 1410 accounting, schedule/outstanding updates, duplicate protection and rollback-only cleanup.

**Exact next point:** inspect/audit the current waiver implementation for an existing controlled rollback-only/SAVEPOINT verification mechanism, then execute the waiver-specific verification matrix without creating unnecessary permanent test data. Do not claim waiver completion until the matrix passes.

Known hardening commits include `b5e0bbd` (live paid-repayment revalidation), `2d035ae` (active linked employee notification targeting), `1a67312` (final paid deduction eligibility), and `7e63fd0` (GM page PHP syntax fix).

The attendance same-page POST scroll-jump issue remains explicitly unfinished and out of scope.


### 2026-10-06 — GM Salary Advance Waiver: latest continuation point

The waiver-specific runtime matrix has progressed through committed execution notifications.

Closed runtime gates: transaction-composable remaining-balance waiver; paid-deduction refund; undistributed cancellation; future-deduction blocking/draft refresh; approved-unpaid payroll protection; blanket scope/execution; sequential duplicate protection; post-commit preparation notification; post-commit GM approval notification; and post-commit full execution notifications to the approving GM and affected employee.

Latest evidence:
php tools\\run_salary_advance_gm_waiver_execution_notification_rollback_tests.php
PASS — request SAR-2026-00004, decision 12, GM notification = 1, employee notification = 1, waiver journal = 124; cleanup PASS with zero test decisions/notifications/journals.

Exact next task: notification failure-isolation runtime. Do not repeat any closed waiver test. After failure isolation, continue to audit preservation, then final 1410 reconciliation. Keep the same-employee multiple-advance scenario and true two-process concurrent execution as explicit evidence gaps unless separately proven.

Salary Advance Stages 1–6 remain closed and must not be reopened without regression evidence. The attendance same-page POST scroll-jump issue remains unfinished and out of scope.

### 2026-10-06 — GM Salary Advance Waiver: notification failure-isolation gate prepared

The current main branch now contains a narrow, inert test-only seam at `ak_transaction_review_notify_event()` and a dedicated runtime harness:
`tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

The seam was added only because source inspection found no existing deterministic notification-failure seam and the installed notification schema is legacy; no schema assumptions or runtime DDL were introduced.

**Runtime status: PENDING USER EXECUTION.**

Exact next command:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

Do not repeat any closed waiver tests. On PASS, continue to audit-preservation verification. On failure, stop at the exact failure and inspect the root cause.

Open evidence gaps remain:
- same employee with multiple eligible advances;
- true two-process concurrent execution.

### 2026-10-06 — Notification failure-isolation gate CLOSED

Runtime PASS:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

Evidence: `SAR-2026-00004`, decision `13`, two execution-notification delivery attempts, one injected failure, final status `executed`, outstanding `0`, waiver journal `125`, 10 schedule overlays, and clean restoration of test state.

**Next exact engineering gate: audit preservation runtime verification.**


### 2026-10-06 — GM Salary Advance Waiver: audit preservation + 1410 reconciliation CLOSED

The final two waiver verification gates have now passed locally on `main`.

**Audit preservation runtime gate — PASS**
Command:
`php tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`

Evidence:
- request: `SAR-2026-00004`
- decision: `15`
- original disbursement journal: `73`
- temporary paid payroll: `24`
- temporary payroll journal: `129`
- repayment: `6`
- historical audit rows preserved: `6`
- waiver audit rows: `3`
- schedule overlays: `9`
- cleanup restored the complete pre-test evidence set; temporary payroll/repayment data rolled back.

This proves the waiver execution preserves historical disbursement, payroll repayment/accounting and schedule evidence while recording separate waiver evidence.

**Final 1410 reconciliation — PASS**
Command:
`php tools\\run_salary_advance_1410_reconciliation.php`

Result:
- ledger balance: `190000`
- live outstanding: `190000`
- 1410 debits: `220000`
- 1410 credits: `30000`
- disbursement debit: `220000`
- payroll credit: `0`
- waiver-refund debit: `0`
- waiver credit: `0`
- direct-repayment credit: `30000`
- open disbursed requests: `5`
- posted journals/lines: `11 / 11`

The reconciliation correctly includes direct salary-advance repayments as Cr 1410. Therefore the accounting control balance exactly equals the live HR outstanding receivable: `220000 - 30000 = 190000`.

**Waiver verification status: CLOSED at the documented acceptance boundary.**

Do not rerun the closed waiver gates without concrete regression evidence. The remaining evidence gaps are intentionally unchanged:
- same employee with multiple eligible advances;
- true two-process concurrent execution.
Neither is to be described as runtime-proven.

The unfinished attendance same-page POST scroll-jump issue remains out of scope.

## 2026-10-06 — i18n cleanup: static scan + runtime verification CLOSED

The English-language cleanup is now **RUNTIME VERIFIED / CLOSED** at the current acceptance boundary.

Static verification:
- `php tools\\i18n_gap.php`
- Result: `Files with gaps: 0 | Untranslated Arabic strings: 0`
- Result confirms every scanned Arabic UI string has an English compatibility translation.

Runtime verification was then performed locally by the user in English mode on the affected HR, salary-advance waiver, accounting FM, HR dashboard and reports pages. The user confirmed:
- all tested UI is English;
- pages behave normally;
- no runtime English-language rendering defect was observed.

Do not reopen or repeat the i18n cleanup without a concrete regression or a newly introduced untranslated UI string.

The previously unfinished attendance same-page POST scroll-jump issue remains out of scope and is not changed by this checkpoint.

## 2026-10-06 — Attendance ↔ Payroll integration: runtime verification resumed

The next open engineering unit after the completed i18n cleanup is the 2026-10-05 Attendance Policy ↔ Payroll integration.

Source audit found a concrete calculation defect before runtime verification: `hrSalaryAdvancePayrollRefreshDraft()` recomputed `payroll.net_salary` without subtracting `attendance_deduction`, even though the attendance deduction is explicitly part of the `net_before_advance` basis used by the salary-advance calculator.

A targeted production fix was applied:
- `modules/hr/lib_salary_advance_payroll.php`
- commit `60adf7f4f428b1754f45b010c552ad93a2155323`

A rollback-only integration harness was added:
- `tools\\run_hr_attendance_payroll_integration_tests.php`
- commit `20c8f23e5fc239fd3b19f26ec4cd424c08dc9d34`

Runtime status: **PENDING USER EXECUTION**.

Exact next command:
`php tools\\run_hr_attendance_payroll_integration_tests.php`

Do not mark the attendance/payroll integration closed until this harness passes locally. Do not reopen completed salary-advance waiver tests, i18n verification, Salary Advance Stages 1–6, or the unfinished attendance scroll-jump issue.

## 2026-10-06 — Attendance ↔ Payroll integration: RUNTIME VERIFIED / CLOSED

The Attendance Policy ↔ Payroll integration has now completed its runtime acceptance gates on the user's local XAMPP/MariaDB environment.

Previously verified core branches:
- Working-day explicit absence deduction.
- Approved paid leave = zero deduction.
- Approved unpaid leave uses the payroll-policy percentage.
- Non-working-day absence = zero deduction.
- Missing attendance row = zero automatic deduction.
- Draft payroll attendance deduction and net salary refresh.
- Rollback-only cleanup.

Final extended runtime gate:
`php tools\\run_hr_attendance_payroll_extended_integration_tests.php`

PASS evidence:
- payroll policy effective-date selection: PASS
- attendance policy effective-date selection: PASS
- `net_before_advance` salary-advance interaction: PASS
- employee: 8
- salary-advance request: SAR-2026-00007
- period: 2026-10
- absence date: 2026-10-06
- attendance deduction: 333.33
- salary-advance deduction: 9666.67
- final net salary: 0.00
- paid payroll immutability: PASS
- rollback: PASS

Known observation: the CLI harness still emits the existing `$_SERVER['REQUEST_METHOD']` warning from `config/functions.php` line 52. This did not affect the test result and was not changed merely to suppress CLI output.

**Disposition: Attendance ↔ Payroll integration is RUNTIME VERIFIED / CLOSED at the documented acceptance boundary.**

Do not rerun these integration harnesses without concrete regression evidence. Do not reopen the GM Salary Advance Waiver, i18n cleanup, Salary Advance Stages 1–6, or the unfinished attendance same-page POST scroll-jump issue.

## 2026-10-06 — FM UI consistency cleanup

A focused UI consistency pass corrected two visible inconsistencies in the FM dashboard quick-action section and the GM salary-advance waiver FM page.

- `modules/accounting/fm_dashboard.php`: the final two quick-action cards — **إدارة الرواتب** and **منظمة فينا الخير** — now have the same fixed-height/card treatment as the other FM quick-action cards, including their missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the top page title section now uses the same FM blue-gradient header treatment as the FM dashboard instead of the previous standalone white bordered card.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`

Runtime browser verification is still required on the local XAMPP application. Do not mark the UI fixes runtime-verified until the user confirms the affected FM pages visually.

## 2026-10-06 — System-wide same-page POST scroll-jump fix: RUNTIME VERIFIED / CLOSED

The previously reported same-page POST viewport jump is now **RUNTIME VERIFIED / CLOSED as a system-wide behavior**, not an attendance-specific workaround.

The final implementation is centralized in `assets/js/app.js` and uses a **global default / opt-out policy**:
- qualifying same-path POST forms are intercepted before native document navigation;
- the existing document/content region is updated in place;
- the user's viewport position is preserved by the centralized response renderer;
- interception is registered in the capture phase so the browser's native submit navigation does not win the race;
- form actions are resolved with `getAttribute('action')`, avoiding shadowing by hidden inputs named `action`;
- submitter overrides (`formaction`, `formmethod`, `formenctype`, `formtarget`) are respected;
- multipart uploads, non-self targets, cross-path/cross-origin submissions, and explicit native-post opt-outs remain native by design.

The attendance page was deliberately tested **after its `data-ak-same-page="1"` opt-in was removed**. The user confirmed the POST action now remains in place. This is the critical runtime evidence that the global policy itself works rather than an attendance-specific attribute.

Key implementation commits:
- `af5fe03e3592972c61ec2821fa7ad0a7f4b7abfd` — capture-phase interception;
- `0a06fb57f687f4ac56c623251a7fa38d6dba083d` — system-wide default/opt-out policy;
- `4496a8f6fb4d38a856483b25d856c640a81732ec` — submitter override hardening;
- `396576c6bc8d1f26af37a09e80c15909aac06e60` — effective submitter passed into policy;
- `8389708b84a9853f164d3b73d6f810dbe4967ef5` — attendance returned to the global policy with no page-specific opt-in.

Earlier attendance-specific and diagnostic attempts are historical investigation only and must not be treated as the current mechanism.

**Disposition:** CLOSED. Do not reopen or redesign this behavior absent a concrete regression. Do not add page-specific same-page POST attributes merely to reproduce the old fix; the system-wide policy is the authoritative mechanism.

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

## 2026-10-06 — Production-preparation database cleanup: disposable database runtime verification CLOSED

The current production-preparation script was corrected to match the original production-preparation contract while covering the current 101-table schema.

Authoritative behavior:
- Preserve the original explicitly protected production data: users, employees, families, family children, sponsors, sponsorships, sponsorship children, sponsor-supervisor assignment history, supervisor letters, supervisor-letter assignment history, roles, departments, currencies, letters, medical needs, settings, and accounts.
- Preserve accounts except for the original exact eight development/test account codes: `4400-1`, `5100-1`, `4400-2`, `5100-2`, `4400-3`, `5100-3`, `4400-4`, `5100-4`.
- Treat all other current base tables as development/test operational data and clear them with `TRUNCATE TABLE`.
- Do not drop tables, alter the schema, or delete physical files from `storage/`.

Implementation commit:
`5f92a919ec64127428fda5e0adf8b5243ad8554f`

Disposable-database runtime verification:
1. A fresh dump of the live `ahl_el_kheir` database was imported into disposable `ahl_el_kheir_production_test`.
2. The production-preparation script was executed against that disposable database.
3. Core production data remained present:
   - users 26
   - employees 25
   - families 2208
   - family children 4910
   - sponsors 2858
   - sponsorships 3560
   - sponsorship children 2965
   - sponsor-supervisor history 270
   - current supervisor letters 56
   - supervisor-letter history 6
4. Reference/configuration data remained present:
   - roles 14
   - departments 8
   - currencies 4
   - letters 29
   - medical needs 14
   - settings 18
   - accounts 38
5. Every one of the 83 non-preserved current tables returned zero rows.
6. The original test-account verification returned zero remaining rows.
7. No SQL execution error or warning was reported.

**Disposition: Production-preparation database cleanup is RUNTIME VERIFIED / CLOSED at the disposable-database acceptance boundary.**

The temporary disposable-database dump and test-copy SQL file are test artifacts only and must not be committed to the repository.




## 2026-10-12 — Salary Advance Stage 7 browser rescheduling evidence and toast feedback correction

Browser-driven rescheduling of isolated request 7 (SAR-2026-00007) reported success. Read-only SQL against salary_advance_browser_test confirmed old plan 1 is preserved as superseded with its five schedule rows; new plan 2 (row ID 8) is active, starts 2026-11-01, contains five installments totaling 50,000.00, and no installment exceeds 10,000.00. Audit row 2263 records HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE. The canonical ahl_el_kheir database was not modified by this operation.

A concrete notification presentation regression was identified in modules/hr/salary_advance_reschedule.php: its transient success/error alerts lacked a selector marker recognized by assets/js/notifications.js. Commit b546005f7f7996d1c4f1946de82e9c72563733f7 adds alert-dismissible to only those success/error alerts so the existing shared toast handler can convert them; the persistent warning/instructions remain inline. Local pull, PHP syntax check, browser toast verification, and payroll/accounting acceptance are still pending. Stage 7 remains OPEN.


## 2026-10-12 — Stage 7 payroll acceptance progress and payroll table refinement

The isolated browser workflow has reached November 2026 draft payroll generation in `salary_advance_browser_test`. The application created 22 drafts using payroll policy version 2. Read-only SQL identified payroll row ID 46 for employee ID 8 / EMP-0021: basic salary 10,000.00, salary-advance deduction 10,000.00, net salary 0.00, status `draft`, accounting status `none`, with no accounting entry, journal entry, or payment account yet. This confirms the active November rescheduled installment is reflected in the draft. It does not yet prove repayment application or accounting posting. Do not use the period-wide “approve drafts” action; it affects all 22 records.

Read-only account lookup in the isolated database returned account ID 2 / code 1200 (Bank), ID 37 / code 1410 (Employee Salary Advances Receivable), and ID 14 / code 5200 (Salaries). The next acceptance gate is to approve and pay only payroll row 46 using the page’s per-row actions, then verify exactly one repayment application, schedule/payment linkage, request outstanding balance, paid payroll state, posted accounting entry, and a 10,000.00 credit to account 1410. The canonical `ahl_el_kheir` database must not be modified.

The user also requested a more compact payroll table. Commit `ae7d9fc6209a8ff1366afa96b9fb73720d614249` on `main` removes the Contract and Policy columns, removes redundant currency labels from amount cells, tightens input/typography spacing, adds distinct blue cell borders and a blue-tinted header, and removes now-unused joins from the month-list query. The shared policy summary above the table remains. The user later reviewed the compact layout locally and described it as much better. This is browser visual feedback only; no separate PHP syntax-check result is asserted here.

The user confirmed the rescheduling success/error feedback now appears as a toast after commit `b546005f7f7996d1c4f1946de82e9c72563733f7`. The isolated payroll repayment/accounting acceptance and temporary-environment cleanup are now complete; see the dated Stage 7 acceptance-closure entry below for verified results. Do not restore the temporary routing again or repeat the completed acceptance absent concrete regression evidence.

## 2026-10-12 — Stage 7 salary-advance repayment rescheduling: ACCEPTANCE CLOSED

The isolated local end-to-end acceptance is complete. In `salary_advance_browser_test`, request `SAR-2026-00007` was rescheduled through the browser, November 2026 payroll draft 46 reflected a 10,000.00 salary-advance deduction, and the user individually approved and paid only that payroll row. Payroll 46 is `paid`/`posted`, with posted journal 116 (`PAY-46`): account 5200 debit 10,000.00 and account 1410 credit 10,000.00. The journal is balanced; net salary was zero, so no bank line was expected. Schedule 42 is paid with 10,000.00 applied, exactly one `applied` repayment trace exists for payroll 46, and request 7 outstanding balance decreased from 50,000.00 to 40,000.00 in the isolated database.

Cleanup and canonical-data safety are verified: `.htaccess` has been restored to exactly `Options -Indexes`; `stage7_db_probe.php` is absent; the protected untracked receipt remains present and untouched; local Git status showed only that receipt untracked on `main`. Read-only checks against canonical `ahl_el_kheir` confirmed policy version 1 still has rescheduling disabled, six-month maximum and 10,000.00 monthly cap, and request `SAR-2026-00007` remains disbursed with 50,000.00 outstanding. The observed posted account 1410 balance was 250,000.00; it was not changed as part of the isolated acceptance.

**Stage 7 status: ACCEPTANCE CLOSED at this documented boundary.** Do not rerun the browser/payroll acceptance or modify the canonical policy/request without a new requirement or concrete regression evidence. Evidence gaps remain explicit: multiple eligible advances for the same employee were not proven in local runtime, and a true concurrent production workflow was not locally runtime-proven. Existing CI contention tests are separate evidence and must not be overstated. The compact payroll table was visually reviewed by the user and described as much better; do not claim a separate PHP syntax result from that visual review.


## 2026-10-12 — New employee incentives feature

Active requirement: implement three policy-driven fixed-amount incentive categories (confirmed monthly sponsorship collections; new sponsor/reactivation after confirmed first payment; project completion after administrative and FM financial closure). The user synced `main`, passed PHP lint for direct sponsor attribution, and supplied local schema/index/FK metadata. Relevant tables are InnoDB; source tables lack a unique incentive idempotency key; payroll employee-period and journal/account identifiers have unique indexes. Detailed findings are in `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. No incentive policy/ledger/payroll migration has been implemented. Continue targeted full-DDL and payroll/source-flow audit; never restore the data-bearing SQL snapshot into the live database.

### 2026-10-12 — Employee Incentives (in progress)

The Employee Incentives feature is the current active task. Dedicated expense account 5500 and the initial incentive ledger schema migrations are committed on `main`; the FM/admin policy administration page is also committed. These changes are not yet locally applied or runtime-verified. Follow `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for scope, business rules, and the next verification gate. Do not claim the feature is complete.

### 2026-10-12 — Salary portal navigation

FM dashboard salary links now route through modules/hr/salary_portal.php, which presents role-filtered links for salary advances, waivers, payroll, and incentive policy. Commits 8bc2be917afaa09d50a3c46f4d13a3922c42648b and 94f5c9c5666a84cea8fc4a161277dba3b707e2a1 are on main. Local PHP lint and browser verification are pending. Employee incentive policy extensibility is the next business-design question: custom/manual awards (e.g. Employee of the Month) versus the existing source-driven reward types.

### 2026-10-12 — Employee incentive continuation

The policy page now uses the system-wide SDG currency; the user confirmed the change is visible locally (commit `12af8e46b2a9e042322bddf939462b973dd612d0`). Local lint/runtime verification remains pending. Monthly performance recognition/scoring/certificates are deferred to a separate future task. Current focus is extensible, versioned policy metadata that FM/HR can add as needed; confirm HR draft/metadata permissions while preserving FM activation authority. Custom incentive award types remain a distinct schema/workflow requirement because current type columns are ENUMs. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.

### 2026-10-12 — Employee incentive continuation

Monthly performance recognition/scoring/certificates are deferred to a separate future feature. Current focus: extensible incentive policy fields for FM/HR, with FM retaining activation authority. The SDG currency field removal is visible locally (commit `12af8e46b2a9e042322bddf939462b973dd612d0`); local lint/runtime verification remains pending. Custom incentive types are a separate schema/workflow issue. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.


## 2026-10-12 — Incentive category catalogue and custom policy fields

Latest source commits on `main`:
- `eb92fa5a42d00c086da0fe6a439511178d8c149b`: adds `hr_incentive_types`, preserves/seeds the four built-in category codes, converts ENUM-backed rule/event category columns to catalogue-backed strings with foreign keys, and adds version-scoped `hr_incentive_policy_custom_fields`.
- `68758c17c6363083f798e019c6348bdb596acc8c`: policy page loads categories from the database, lets HR/FM create policy drafts and add typed custom fields, permits creation of custom categories as manual-review only, and restricts activation to FM/Admin.
- Documentation updated in `affd6538d4b3a140a2e3df725642254afdd1f71b`, `663efae40df2577dc517d8f02876c703eda1cfd1`, and `40c47a468645de87f5f09eb1b9430c4733cf83e9`.

User-approved governance: HR Manager/HR Staff and FM/Admin may create drafts/add fields; FM/Admin alone activate; GM/FM agreement remains recorded before activation, without a second GM approval. Monthly performance recognition/certificates remain a separate future feature. New categories are manual-review only and cannot issue/pay awards until a dedicated nomination/verification/award workflow exists.

**Verification boundary:** new migration and updated PHP are committed but not locally verified. Next: sync `main`, apply the new migration against the existing incentive ledger, run PHP lint, and test category creation, custom field type/required/select validation, version scoping, HR activation denial, and FM activation. Do not claim runtime success until the local XAMPP/MariaDB/browser checks are reported.


Migration rerun guard added in `eb92fa5a42d00c086da0fe6a439511178d8c149b`; the conversion only runs while the target column remains ENUM. Verify this against the local MariaDB before considering the migration accepted.

### HR employee incentives — 2026-10-12 checkpoint
The local user confirmed the incentive policy page renders Arabic and English category labels correctly after the category-catalogue migration rerun. Category-code UX is now changed on GitHub `main`: the code is generated server-side from the English name, shown as a read-only preview, and duplicate codes receive numeric suffixes; submitted client code values are ignored. Commit `23bdf2a84d81cd86f753c283e462f06f828aa510` includes the category-code hardening. Commit `1a1ced379bf5f4942cd44d554bbfbbfc2c499bbe` also carries strict server validation for GM/FM agreement timestamp/notes. Local sync, PHP lint, and browser verification of the new read-only/generated-code behavior remain pending. No policy draft or financial rule has been created or activated; agreed reward amounts are not yet available. Next: sync `main`, inspect the working tree before any merge, lint `modules/hr/incentive_policy.php`, verify generated code preview/server uniqueness behavior, then design rollback-only tests for draft policy/custom-field/activation permissions. Do not use invented amounts as approved policy or activate a test policy.


### Incentive policy GM approval — 2026-10-13

Active task: locally verify the GM approval workflow committed on GitHub main. Keep policy name/effective date; agreement notes/date are removed from the new UI and validation while legacy DB columns remain. Workflow is draft → pending_gm → approved → active; rejection with a mandatory reason records durable history and returns the policy to an editable draft. GM roles (general_manager, gm) alone approve/reject; FM/Admin alone activate. New migration: database/migrations/2026-10-13_hr_incentive_policy_gm_approval.sql. Local pull, migration application, PHP lint, and runtime tests remain outstanding. See docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md.

## 2026-10-13 — Incentive policy custom-field concurrency guard

Commit `c53a5dd6b32f6a737f7d6264df7b033e3383cb45` updates `modules/hr/incentive_policy.php`: policy-management permission is checked before opening a transaction; custom-field creation locks the policy-version row with `SELECT ... FOR UPDATE`, requires the row still be a draft, inserts the field, then commits. This serializes the custom-field insert against submission, which locks the same policy row while validating required fields. Existing exception handling rolls back an open transaction.

The GM approval migration is applied locally and the schema was inspected. PHP syntax checks for the policy page and GM dashboard passed, and the policy page loads. Full approval/rejection/activation runtime verification remains pending because the local database has zero policy versions, rules, and custom fields. No concurrent two-process test has been run; do not claim it has been proven. Award issuance, source-event integration, payroll scheduling, and incentive accounting remain unfinished. Next: pull `main`, inspect the exact diff, lint the changed page, then perform safe local role/state verification without inventing business reward amounts.

## Deferred required work — cross-module payroll/accounting reconciliation audit

After the current employee-incentive GM approval workflow is fully acceptance-tested, perform the comprehensive evidence-based integration audit documented in `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`. Scope: salary additions and deductions, attendance deductions, salary advances/repayment schedules/rescheduling, advance waivers/refunds/cancellations, implemented incentive award flows, accounting journals/transactions, reversals, and reconciliation reports. Trace source-to-calculation-to-persisted ledger-to-accounting posting, independently recompute expected arithmetic, verify debit/credit balance and source-to-journal reconciliation, and test idempotency, rollback, concurrency boundaries, period/effective-date behavior, and paid/posted immutability. Use real source/schema and isolated rollback-only test data; do not invent accounting rules or incentive amounts. This is future work and is not yet verified.


## 2026-10-13 — Incentive policy role boundary clarified

The user confirmed this is a higher-management workflow involving only HR Manager, General Manager (GM), and Financial Manager (FM). `modules/hr/incentive_policy.php` now limits draft creation/editing, custom-field management, and category management to `hr_manager` and `financial_manager`; review/approval/rejection remains GM-only; activation is FM-only. `hr_staff` and `admin` are no longer authorized substitutes in this workflow. Authorization source change committed as `63534c5fd3aa5b0b3fc8bf640d3ecfb2280f314a`; UI alignment committed as `b065a93a98e1786a2e81702c0fa956b3a414f646`. The policy-creation form is now hidden from GM-only reviewers, and status guidance no longer names Admin as an activation actor.

**Verification boundary:** this is a source-level authorization correction only. Local PHP lint and real XAMPP/MariaDB role-based acceptance are still required. The three accounts previously identified are HR Manager user 32, GM user 2, and FM user 29, but their current login usability has not been established. Do not claim the workflow accepted until runtime tests pass. Do not use HR staff or Admin as test actors.

### 2026-10-14 — Incentive policy acceptance

Role access has been browser-confirmed for HR Manager, GM, and FM. Commit `e4b0675dbed130a1a5235851df6dbdb4f342b524` removes the visible automatic category-code preview field; server-side category-code generation remains. Local lint and browser verification are pending. Next: sync `main`, verify the field is absent, then resume controlled draft/edit/submit/GM decision/FM activation testing. Do not claim full policy acceptance or start the deferred payroll/accounting audit yet.


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


## 2026-10-14 — Incentive policy fallback semantics and FM page access

The user confirmed that GM rejection returns the policy to HR for editing/resubmission; it does not close the workflow. If no replacement is submitted, the previous active policy remains in force. If FM does not activate an approved replacement, the previous active policy remains in force. Source inspection shows prior active policy retirement happens only during successful FM activation, not during draft creation/submission/GM approval/rejection.

Review uncovered a page-level access gate that redirected FM users despite the activation action permission. Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` includes the FM activation role in the page gate. Existing incentive-policy success/error feedback uses `data-ak-transient-alert="1"` for centralized toast conversion.

Local PHP lint/browser/notification/fallback verification is still pending. Keep V1 inactive.


## 2026-10-14 — Incentive policy fallback and FM page access

GM rejection returns the policy to HR as an editable draft. If HR does not submit a replacement, or FM does not activate a newly approved replacement by payday, the previous active policy remains in force. Source inspection shows prior active versions are retired only during successful FM activation. Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes the page-level gate so FM can reach the activation UI. Action success/error messages already use transient-alert markers for toast conversion. Local lint, browser role checks, notifications, toast behavior, and payroll effective-policy selection remain unverified; keep V1 inactive.

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


### 2026-10-14 — Incentive policy GM approval feedback retest

Active checkpoint: source correction restores GM approval confirmation and success toast; only the GM approval form opts out of same-page POST interception to ensure confirmation runs before submission. Guarded reset utility: `tools/reset_incentive_policy_gm_approval_acceptance.sql`. R3 must remain inactive. Local lint and runtime acceptance (cancel, confirm, toast, history, HR/FM notifications) are pending. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.


### 2026-10-14 — Incentive GM approval notification timestamp follow-up

The GM approval confirmation and toast were browser-confirmed. A timestamp discrepancy was traced to stale HR/FM approval-handoff notifications that the test reset did not remove because it compared Arabic title literals through a problematic local PowerShell/CLI encoding path. Stored title bytes are valid UTF-8, and no production notification insertion defect has been established. Commit `75484be186fdf7b935e27151e07604456b2cfa76` makes the reset encoding-independent using the exact R3 body/link and recipients 29/32, preserving GM recipient 2's submission notification and requiring R3 to remain unactivated. Next: pull, inspect and execute the reset on `ahl_el_kheir`, confirm old HR/FM handoff rows are removed, approve once as GM, and compare fresh notification timestamps with approval history. R3 must remain approved but inactive; do not touch `ahl_el_kheir_production_test`.


### 2026-10-14 — Incentive GM approval handoff timestamp retest passed

Fresh browser/database acceptance for R3 passed the confirmed-approval path: GM confirmation dialog and success toast displayed; approval history row 8 records GM user 2 at `2026-10-14 13:54:58`; HR notification 175 (recipient 32) and FM notification 176 (recipient 29) have the same timestamp. Policy 3 is `approved`, with activation fields NULL. The prior mismatch came from stale HR/FM notifications surviving an Arabic-title reset predicate that was encoding-fragile in the local PowerShell/CLI path. Commit `75484be186fdf7b935e27151e07604456b2cfa76` corrected the test-only reset; no production notification-writer defect was demonstrated. Keep R3 inactive and do not touch `ahl_el_kheir_production_test`. The cancel-confirmation result was not explicitly reported and remains unverified; rejection/resubmission, FM activation, and payroll effective-policy selection are separate pending gates.


### 2026-10-14 — Incentive FM activation confirmation acceptance

Initial FM activation executed at 14:12:23 despite the confirmation prompt disappearing. Root cause was the window-capture same-page POST interceptor running before document-level confirmation handling. Fix `06b08d44afc48887b74a4588f1604e11f5d5e809` uses translated `data-confirm` and `data-ak-native-post="1"` on the FM activation form. Local PHP lint passed; after reset, FM confirmed the prompt and received the success toast. Database confirms policy 3 active, actor 29, timestamp `2026-10-14 14:18:10`. The guarded reset utility timestamp was updated in commit `a94080af39f82bcdaa56190c6c8eca264934d9f3`; docs recorded in `c0ed9d4d1c6bf680930dc40d3fc2ed58909b5bf0`. Next: pull main, inspect/run the reset only against local `ahl_el_kheir`, then test Cancel and verify policy remains approved with both activation fields NULL. Do not touch `ahl_el_kheir_production_test`; payroll effective-policy selection and incentive payroll integration remain unverified.


### 2026-10-14 — Incentive GM rejection/recovery and approval handoff verified

R3 policy 3 completed the actual UI flow GM rejection with a required reason → HR edit/resubmission → GM reapproval. HR user 32 received the rejection notification at 14:36:36; history row 10 records HR resubmission at 14:38:35 and GM was notified; history row 11 records GM approval at 14:40:49. Read-only DB verification confirmed HR notification 181 and FM notification 182 were both created at 14:40:49 for the exact R3 policy. Policy remains `approved`, with both activation fields NULL. The test reset removed the previous approval-history row and previous HR/FM handoff notifications but preserved submission history and GM submission notification; it was not a full rollback-only reset. Do not repeat already-closed FM activation tests or change production notification code without new evidence. Next open engineering unit: audit/design incentive-award qualification and payroll/accounting integration; this remains unimplemented/unverified. Do not touch `ahl_el_kheir_production_test`.


### 2026-10-14 — Incentive accounting separation checkpoint

Source-only milestone: migration `2026-10-14_hr_employee_incentives_payable_account.sql` adds dedicated liability account `2400` for Employee Incentives Payable, alongside the existing dedicated `5500` Employee Incentives Expense account. Commit: `a281b8cd695e1555dcec86b7c900b1bf691f1562`. Documentation commit: `f60545b6d70597875324aa48abe71c5cd1b8736d`.

The migration has not been applied/verified in the user's local database. Incentive award recognition, payroll inclusion/settlement, and effective-policy selection remain unimplemented or unverified. Preserve separate salary expense `5200`, incentive expense `5500`, incentive payable `2400`, and salary-advance receivable `1410`. Do not use general Payables `2100` or Fina Al-Khair Payable `2300` for incentive awards. Next step is source-audited award recognition/payroll linkage design, then minimal implementation and rollback-only runtime verification. Do not activate R3 or touch `ahl_el_kheir_production_test`.


Schema-only checkpoint: on 2026-10-14, dedicated incentive payable account migration `a281b8cd695e1555dcec86b7c900b1bf691f1562` and payroll/award journal lifecycle migration `211f1e033cf488f1a95e5d76956b4371183d6463` were added to `main`. Local migration application remains pending. Do not treat this as runtime-complete; award qualification, payroll scheduling/refresh, journal posting, and rollback-only tests remain open.


### 2026-10-14 — Incentive accounting integration slice (runtime pending)

Local schema gate passed per user evidence: account 2400 active liability; 5500 active expense; payroll incentive_amount and award recognition/settlement journal references/indexes present. Initial source integration commits add award recognition/scheduling helpers, separate payroll settlement through 2400 while keeping salary expense on 5200, and separate payroll UI display. This is partial source work only; source event hooks, manager verification/award creation, notifications, PHP lint, and rollback-only runtime tests remain open. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. Do not activate R3 or touch `ahl_el_kheir_production_test`.



### 2026-10-14 — Employee incentive accounting rollback harness prepared

Added `tools/run_hr_employee_incentive_accounting_rollback_tests.php` on `main` (commits `68a005d9`, `5cb05574`). This is a prepared rollback-only runtime gate, not a PASS. It exercises award recognition/idempotency, caller-owned currency hold, draft scheduling/repeat refresh, settlement journal separation (5500/2400/5200/1410), paid-payroll refresh immutability, and transaction cleanup using existing policy/payroll fixtures only. Pull `main` and run `& "D:\\xampp\\php\\php.exe" "tools\\run_hr_employee_incentive_accounting_rollback_tests.php"` against local `ahl_el_kheir` only. If SKIP, report the exact prerequisite; if FAIL, stop and inspect that exact root cause. Do not touch `ahl_el_kheir_production_test`, activate R3, or add source-event hooks before this gate is understood. Journal-code MAX+1 concurrency remains a source-review risk before concurrency-sensitive hooks.


### 2026-10-14 — Employee incentive accounting rollback harness prepared

Added `tools/run_hr_employee_incentive_accounting_rollback_tests.php` on `main` (commits `68a005d9`, `5cb05574`). This is a prepared rollback-only runtime gate, not a PASS. It exercises award recognition/idempotency, caller-owned currency hold, draft scheduling/repeat refresh, settlement journal separation (5500/2400/5200/1410), paid-payroll refresh immutability, and transaction cleanup using existing policy/payroll fixtures only. Pull `main` and run `& "D:\xampp\php\php.exe" "tools\run_hr_employee_incentive_accounting_rollback_tests.php"` against local `ahl_el_kheir` only. If SKIP, report the exact prerequisite; if FAIL, stop and inspect that exact root cause. Do not touch `ahl_el_kheir_production_test`, activate R3, or add source-event hooks before this gate is understood. Journal-code MAX+1 concurrency remains a source-review risk before concurrency-sensitive hooks.


### 2026-10-14 — Incentive accounting rollback test passed locally

User-provided local evidence: harness lint passed and `tools/run_hr_employee_incentive_accounting_rollback_tests.php` reported recognition Dr 5500 / Cr 2400 for SDG 123.45, duplicate recognition protection, caller-owned currency hold, draft refresh/repeat refresh, balanced settlement preserving salary account 5200 and salary-advance account 1410, paid award linkage, and rollback cleanup. Fixture IDs: payroll 21, employee 16, policy 3. This is runtime evidence for the tested accounting slice only, not full lifecycle proof. Next inspect journal-code allocation and address MAX+1 collision risk before event hooks. Keep R3 inactive and do not use `ahl_el_kheir_production_test`.


### 2026-10-14 — Incentive journal-code collision retry locally verified

The user reported PHP lint and the rollback-only accounting harness passing after the bounded five-attempt duplicate-entry-code retry. This verifies the tested incentive accounting path only; it does not prove concurrent two-process execution or full lifecycle completion.


## 2026-10-14 — Win-back reactivation attribution

Implementation and local rollback-only acceptance are complete for the transition slice. The migration was applied to `ahl_el_kheir`; all three PHP lint checks passed; the harness passed atomic state, durable attribution, duplicate-transition blocking, and rollback cleanup. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for evidence and the next reactivation-payment/HR-verification requirements.


## 2026-10-14 — Win-back reactivation attribution passed local rollback-only acceptance

The user pulled commit `958a4644160071ea573db15f35e92f0f2d738c9c`, applied `database/migrations/2026-10-14_winback_reactivation_attribution.sql` to local `ahl_el_kheir`, and ran PHP 8.2 lint on `modules/administration/lib_winback.php`, `modules/administration/winback.php`, and `tools/run_winback_reactivation_rollback_tests.php`; all three reported no syntax errors. The rollback-only harness reported:

- `PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked`
- `PASS | rollback_cleanup=PASS | campaign=3 | sponsor=1`

This proves the harnessed atomic sponsor/campaign transition, durable actor/timestamp, blocked repeated transition, and rollback cleanup on the local database. It does not prove concurrent two-process execution or the full incentive lifecycle. Next work remains the reactivation source-event qualification at the financially confirmed payment boundary, using confirmed payment history to prove the 90-day lapse and durable reactivation actor, with idempotency and HR Manager verification. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation payment qualification committed; local acceptance pending

The confirmed-payment hook now checks a posted linked transaction and posted journal, a durable successful win-back transition, a previous confirmed monthly-sponsorship payment at least 90 days before the transition, and the first confirmed monthly-sponsorship payment created after the transition. The event remains `pending_verification`; it does not issue an award. Recipient user and employee IDs are snapshotted on source events so the win-back actor is distinct from the payment reviewer. The collection hook also snapshots the collector's user/employee relationship. Missing or ambiguous employee mappings produce no event. Same-second competing payment confirmations are conservatively held rather than risking duplicate awards.

New migration: `database/migrations/2026-10-14_hr_incentive_event_recipient_snapshot.sql`. New rollback-only harness: `tools/run_hr_employee_incentive_reactivation_rollback_tests.php`. The existing collection source-event harness now checks recipient snapshots. **Local acceptance is pending**: apply the recipient-snapshot migration once to `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and both incentive source-event harnesses, then run both harnesses. No local runtime result is claimed for this new slice. HR Manager verification/award issuance, employee notifications, new-sponsor/project hooks, and end-to-end lifecycle testing remain open. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation incentive wiring paused for actor-attribution decision

Source review of the actual win-back UI found that its “confirm return” form has no field for the employee who actually reactivated the sponsor. The action records the logged-in confirmer, while `handled_by` is mutable and the contact outcomes do not identify a successful reactivator. Therefore the current confirmer must not automatically be treated as the incentive recipient. The reactivation qualification helper and rollback-only test are committed, but the production call from `modules/accounting/fm_review_queue.php` is intentionally withheld until this business attribution is decided. The collection hook remains separate and snapshots the collector. Do not apply the new recipient-snapshot migration or run the new reactivation harness yet; first decide whether the confirmer is, by definition, the reactivating employee, or the workflow must require a separate employee selection and preserve the confirmer independently. No runtime acceptance is claimed for the reactivation qualification slice.


## 2026-10-14 — Explicit win-back reactivator attribution and confirmed-payment hook (local gate pending)

The agreed recipient is the employee who actually reactivated the sponsor, not automatically the user who confirms the return. The win-back UI now requires selecting a uniquely mapped, currently working employee; server code revalidates the mapping and stores that user in `reactivated_by_user_id`. The confirmer is stored separately in `reactivation_confirmed_by_user_id`; `handled_by` remains mutable operational ownership. Legacy campaigns without the new confirmer snapshot are excluded from reactivation incentive qualification; the system does not assume their earlier actor attribution was correct. The FM confirmed-payment transaction now calls the reactivation qualification helper, which checks posted transaction/journal evidence and a 90-day prior-payment lapse and records only a pending-verification event. New migration: `database/migrations/2026-10-14_winback_reactivation_confirmer.sql`. Local acceptance is pending; see `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for exact migrations and rollback-only tests. Do not use `ahl_el_kheir_production_test` or activate policy R3.


## 2026-10-14 — New-sponsor incentive source event

The first financially confirmed monthly-sponsorship payment can now create an idempotent `new_sponsor` event when the sponsor has a valid `brought_by_user_id` → unique employee mapping, no earlier confirmed monthly payment, and no qualifying reactivation transition. The event remains pending HR Manager verification. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for the rollback-only harness and local acceptance commands; runtime acceptance is pending.

### HR Employee Incentives — 2026-10-14 continuation

Current open gate: local PHP lint and rollback-only acceptance for the new HR Manager verification page, followed by employee notifications when awards become scheduled for payroll. Project-completion verification remains blocked until approved recipient allocations are implemented. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. Do not repeat passed source-event/accounting harnesses without regression evidence; keep policy R3 inactive.


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



### 2026-10-15 — Incentive policy approval withdrawal (local verification pending)

A narrow GM-only action was added to return an **approved but not activated** incentive policy to HR draft mode. It requires a reason, writes `returned_to_draft` into approval history, notifies the HR policy creator and FM, and refuses policies with activation evidence. Migration: `database/migrations/2026-10-15_hr_incentive_policy_approval_withdrawal.sql`. Commits: migration `cb522dcee4b2e78e03f8d4c97402a61ca7e23d6c`; source `a9501f5f41e4f959a734d175cf14523c49c1e6d2`. Local PHP lint, migration application, and GM/HR browser acceptance are **pending**. Do not directly update policy status. R3 effective date is already past and must be changed to a valid current/future date; activation must also respect the later R4 effective date. Keep `ahl_el_kheir_production_test` untouched.


### 2026-10-15 — Employee incentive notification browser UX verified
The live in-app notification UX was browser-verified for the employee account used in the controlled test: automatic delivery was observed, and the notification-history page rendered the Arabic body correctly when the test notification was inserted using UTF-8 bytes. Test notification ID 195 was deleted afterward; the exact cleanup query reported `rows_deleted=1`, `remaining_test_rows=0`, and `remaining_notifications=0 / remaining_unread=0` for recipient user 16. This closes the employee-facing notification UX gate only. It does not close HR Manager browser/session authorization or the full incentive lifecycle. Next documented local gate: verify the GM-only audited return-to-draft action for the approved, unactivated incentive policy, then confirm HR can edit the four category rules using the normal UI. Do not rerun passed incentive rollback harnesses or touch `ahl_el_kheir_production_test`.


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


### Follow-up — rollback-only closure harness added (runtime pending)

Added `tools/run_hr_project_incentive_closure_rollback_tests.php` (commit `df40dc1c`). It uses an existing closed project fixture, changes its closure timestamp only inside an uncommitted transaction, snapshots the currently eligible team against the active effective project rule, supplies temporary FM-closure audit evidence only if none exists, checks one full-policy award per eligible employee and recognition journals, and rolls the transaction back. It skips safely if the allocation migration or a suitable fixture is absent. The processor now respects a caller-owned outer transaction (commit `2bff519b`) so the harness can prove rollback without leaving awards, journals, notifications, audit rows, or payroll changes behind.

**Next local verification (only against `ahl_el_kheir`):**
```powershell
cd D:\xampp\htdocs\AhlElKheir
git checkout main
git pull origin main
& "D:\xampp\mysql\bin\mysql.exe" --default-character-set=utf8mb4 --host=127.0.0.1 --user=root --database=ahl_el_kheir --table --execute="SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_incentive_project_allocations' AND COLUMN_NAME IN ('approved_by_user_id','approved_at');"
```

Inspect the migration and apply it once only if those columns are still `NO`. Then run:
```powershell
& "D:\xampp\php\php.exe" -l "modules/hr/lib_employee_incentives.php"
& "D:\xampp\php\php.exe" -l "modules/hr/incentive_verification.php"
& "D:\xampp\php\php.exe" -l "modules/projects/view.php"
& "D:\xampp\php\php.exe" -l "modules/projects/view_fm.php"
& "D:\xampp\php\php.exe" -l "tools/run_hr_project_incentive_closure_rollback_tests.php"
& "D:\xampp\php\php.exe" "tools/run_hr_project_incentive_closure_rollback_tests.php"
```

A `SKIP` from the harness means it could not find a safe eligible closed-project fixture; it is not a PASS. The harness is a database integration check, not browser proof or true concurrent-process proof. Source changes and migration remain **unverified locally** until exact lint and harness output is reviewed. Keep active R5 unchanged and do not touch `ahl_el_kheir_production_test`.


### Follow-up — project closure rollback harness added; local acceptance pending

Added `tools/run_hr_project_incentive_closure_rollback_tests.php` (commit `df40dc1c`). It exercises the automatic project allocation/award path inside an outer transaction and rolls back its fixture, journals, and payroll changes. The processor supports caller-owned transactions (commit `2bff519b`). A missing migration or safe project fixture results in `SKIP`, not PASS.

Next: pull `main`, inspect/apply `2026-10-15_hr_incentive_project_auto_allocation.sql` only if needed, lint the four changed PHP source files plus the harness with local PHP 8.2, and run only the new harness. Local lint/runtime acceptance remains pending. Keep active R5 unchanged; never use `ahl_el_kheir_production_test`.


### 2026-10-15 — Project incentive rollback harness: safe positive-balance coverage

The rollback harness was narrowed to avoid manufacturing a zero-balance accounting fixture. It now selects a closed project with an eligible active team and readable controlled balance. When the balance is positive, it verifies recipient snapshotting, absence of human approval metadata, award blocking, and rollback cleanup; the award/recognition success branch remains conditional on an existing genuine zero-balance project. This does not alter funding allocations, posted expenses, returns, or active R5. Pull main, lint the harness, and run it only against disposable database ahl_el_kheir_incentive_test_20261015_r1. A positive-balance branch PASS does not prove award recognition; its explicit SKIP remains an acceptance gap.


### 2026-10-15 — Project incentive harness SQL failure: root cause and correction

The first local execution of `tools/run_hr_project_incentive_closure_rollback_tests.php` failed with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'currency_code' in 'field list'`. Source inspection isolated the invalid reference to `project_lifecycle.currency_code` in `hrIncentivePrepareProjectCompletion()`; project lifecycle does not expose a source currency field. Commit `920d4221122a65a8579e4bed5ef289e64a4934db` removes that column from the query and leaves `hr_incentive_events.source_currency_code` NULL for this non-monetary source event. The award currency remains sourced from the active policy rule and stored on the recipient allocation/award. This correction has not yet been locally linted or runtime-tested. Next: pull `main`, lint `modules/hr/lib_employee_incentives.php` and the project incentive rollback harness, then rerun only the rollback harness against `ahl_el_kheir_incentive_test_20261015_r1`. Do not alter active R5, live accounting, or fabricate funding returns. A later SQL failure must be diagnosed against the actual schema before further edits.


### Local verification result — project incentive positive-balance rollback harness (2026-10-15)

User ran the harness on local PHP 8.2 against disposable database `ahl_el_kheir_incentive_test_20261015_r1` after pulling the source correction. Both `php -l modules/hr/lib_employee_incentives.php` and `php -l tools/run_hr_project_incentive_closure_rollback_tests.php` passed. Runtime output:

- `PASS | project_team_snapshot=1 | no_approval_metadata=PASS | positive_balance_award_block=PASS`
- `PASS | rollback_cleanup=PASS | project=10 | controlled_balance=30000.00`
- `SKIP | award-recognition success branch | No existing eligible closed project has a zero controlled balance; no accounting records were fabricated or altered.`

This verifies the positive-controlled-balance block and transactional cleanup in the disposable DB only. It does **not** verify successful award creation or recognition journals, nor browser/runtime behavior on a real project. The success branch remains open pending a legitimate eligible closed project with controlled balance <= 0.009. Do not fabricate funding returns or edit posted accounting records to create one. Active policy R5 and live accounting were not used or changed by this test.

### 2026-10-15 — Project incentive success-path harness added (runtime pending)

The existing project-completion rollback harness passed its negative gate but skipped award recognition because its existing project had a positive controlled balance. A separate success-path harness is now on `main`: `tools/run_hr_project_incentive_success_rollback_tests.php` (commit `5b6fb97d975bdc3e591fa811ca55b2a214092bfc`). It refuses any database except `ahl_el_kheir_incentive_test_20261015_r1`, requires the active R5 acceptance policy without modifying it, and uses a synthetic no-financial-activity project in one rollback-only transaction. It explicitly assigns an eligible Project Supervisor before closure, checks the no-FM-audit gate, then verifies the full policy amount and recognition journal. Local PHP lint and runtime execution are pending; no successful-path result is claimed yet.

### 2026-10-15 — Project completion incentive recognition: RUNTIME PASS

User executed `tools/run_hr_project_incentive_success_rollback_tests.php` locally after pulling `main`. PHP lint passed. Runtime output:
`PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS`
`PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS`
`PASS | rollback_cleanup=PASS | policy=R5 | reward=1500.00 SDG`

This closes the isolated successful project-award recognition gate at the harness boundary: eligible Project Supervisor snapshot before closure, full policy reward, durable FM gate, balanced recognition journal, and rollback cleanup. It does not prove payroll inclusion, employee notification delivery, or browser closure workflow. Next, inspect current source and existing harnesses to identify the smallest remaining acceptance gate; do not rerun the passed harness absent regression evidence.


### 2026-10-16 — Active FM sponsor-payment integration gate

The FM sponsor-payment approval operation is shared between the production POST route and a DDL-free rollback-only integration harness. Local PHP syntax/runtime acceptance is pending against `ahl_el_kheir_incentive_test_20261015_r1`. Active R5 is read-only; do not repeat closed incentive tests or use the live database for this gate.


### 2026-10-16 — FM sponsor-payment integration verified; next project payroll test

The user reported runtime PASS for `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php` against `ahl_el_kheir_incentive_test_20261015_r1`: shared FM approval, posted transaction/journal, pending new-sponsor verification without premature award, successful rollback, injected incentive failure, and rollback of payment/transaction/journal. PHP 8.2 lint passed for all five relevant files. This gate is closed at the rollback-harness boundary.

Added `tools/run_hr_project_incentive_payroll_rollback_tests.php` and added it to the PHP syntax workflow. It targets the remaining project-specific payroll linkage gap, checking scheduled award/payroll association, incentive total, unchanged net salary, outbox creation, and rollback restoration. Local lint/runtime remain pending. Next run only this new harness against `ahl_el_kheir_incentive_test_20261015_r1`; preserve R5 and do not repeat closed tests.


### 2026-10-16 — FM sponsor-payment integration verified; next project payroll test

The user reported runtime PASS for `tools/run_hr_fm_sponsor_payment_approval_integration_tests.php` against `ahl_el_kheir_incentive_test_20261015_r1`: shared FM approval, posted transaction/journal, pending new-sponsor verification without premature award, successful rollback, injected incentive failure, and rollback of payment/transaction/journal. PHP 8.2 lint passed for all five relevant files. This gate is closed at the rollback-harness boundary.

Added `tools/run_hr_project_incentive_payroll_rollback_tests.php` and added it to the PHP syntax workflow. It targets the remaining project-specific payroll linkage gap, checking scheduled award/payroll association, incentive total, unchanged net salary, outbox creation, and rollback restoration. Local lint/runtime remain pending. Next run only this new harness against `ahl_el_kheir_incentive_test_20261015_r1`; preserve R5 and do not repeat closed tests.


### 2026-10-16 — Project incentive payroll harness: fixture setup corrected; local verification pending

Read-only diagnostics showed the current payroll policy resolves to version 1 effective 2026-10-01 and eligible Project Supervisors have effective October salary history/working state but no October payroll row. Commit `797f89afe848df39fee26a6f16bae6f2854c3c03` makes `tools/run_hr_project_incentive_payroll_rollback_tests.php` self-contained: it can create a temporary current-period draft payroll inside the test transaction using the production payroll INSERT shape and effective policy, then asserts rollback removes that temporary row. Existing draft fixtures remain supported and must be restored exactly. Local PHP lint/runtime are still pending; run only this harness on `ahl_el_kheir_incentive_test_20261015_r1`. Do not modify R5 or repeat closed tests.
### 2026-10-16 — Project incentive payroll integration CLOSED at rollback-harness boundary

User locally PHP 8.2-linted and ran `tools/run_hr_project_incentive_payroll_rollback_tests.php` after pulling `main`. Reported PASS checks cover synthetic no-financial-activity project setup, Project Supervisor snapshot, full R5 amount, missing-FM-gate blocking, FM gate, recognition journal 5500/2400, correct draft-payroll link, incentive total increase, unchanged net salary, pending outbox, cleanup, and temporary payroll rollback. Reward was 1,500.00 SDG; active R5 was not changed.

This closes only the disposable-database rollback-harness gate. It does not prove a real PM-to-FM browser closure workflow or notification delivery to an employee. Do not rerun the passed harness absent regression evidence.

## 2026-10-16 — Project incentive financial-closure draft-allocation safety guard (source committed; local acceptance pending)

A source audit found that the controlled balance intentionally counts posted funding allocations only, but FM financial closure could still be recorded while a draft allocation remained. The same gap could allow the project-completion incentive processor to proceed if a caller supplied a zero balance and durable closure audit evidence existed.

The targeted correction adds a reusable draft-allocation predicate, blocks explicit FM financial closure while any draft allocation remains, and lets a valid funding return complete while deferring automatic financial closure/PM completion notification when a draft allocation remains. The incentive processor independently locks/checks for a draft allocation before award or recognition-journal processing and preserves caller-owned transaction semantics.

Added rollback-only regression harness: `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. It is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, requires the protected active R5 project-completion policy, reuses only an existing closed zero-posted-balance fixture with a draft allocation and pre-existing recipient snapshot, inserts temporary FM closure audit evidence inside a transaction, and rolls back. It does not change funding allocations, payment evidence, posted accounting, payroll, or R5.

**Local PHP 8.2 lint and runtime execution are pending.** Do not click FM financial closure on project 7 or run the harness against live `ahl_el_kheir`. Keep the already-passed incentive, payroll, notification, salary-advance, and Projects Phase 5 gates closed unless regression evidence appears.
