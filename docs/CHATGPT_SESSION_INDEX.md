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

The user confirmed this is a higher-management workflow involving only HR Manager, General Manager (GM), and Financial Manager (FM). `modules/hr/incentive_policy.php` now limits draft creation/editing, custom-field management, and category management to `hr_manager` and `financial_manager`; review/approval/rejection remains GM-only; activation is FM-only. `hr_staff` and `admin` are no longer authorized substitutes in this workflow. Source change committed as `63534c5fd3aa5b0b3fc8bf640d3ecfb2280f314a`.

**Verification boundary:** this is a source-level authorization correction only. Local PHP lint and real XAMPP/MariaDB role-based acceptance are still required. The three accounts previously identified are HR Manager user 32, GM user 2, and FM user 29, but their current login usability has not been established. Do not claim the workflow accepted until runtime tests pass. Do not use HR staff or Admin as test actors.
