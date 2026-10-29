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

## 2026-10-16 — Project incentive financial-closure draft-allocation safety guard (processor rollback gate passed; FM closure-path verification pending)

A source audit found that the controlled balance intentionally counts posted funding allocations only, but FM financial closure could still be recorded while a draft allocation remained. The same gap could allow the project-completion incentive processor to proceed if a caller supplied a zero balance and durable closure audit evidence existed.

The targeted correction adds a reusable draft-allocation predicate, blocks explicit FM financial closure while any draft allocation remains, and lets a valid funding return complete while deferring automatic financial closure/PM completion notification when a draft allocation remains. The incentive processor independently locks/checks for a draft allocation before award or recognition-journal processing and preserves caller-owned transaction semantics.

Added rollback-only regression harness: `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. It is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, requires the protected active R5 project-completion policy, reuses only an existing closed zero-posted-balance fixture with a draft allocation and pre-existing recipient snapshot, inserts temporary FM closure audit evidence inside a transaction, and rolls back. It does not change funding allocations, payment evidence, posted accounting, payroll, or R5.

**Verification evidence:** PHP 8.2 lint passed for `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, `modules/hr/lib_employee_incentives.php`, and the rollback harness before the latest testability refinement. The rollback-only harness first refused live `ahl_el_kheir`, then passed against `ahl_el_kheir_incentive_test_20261015_r1`:

`PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS`

`PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5`

This closes the **processor rollback-harness gate**. A follow-up now extracts the explicit FM financial-closure rejection into `akp_assert_no_draft_funding_allocations_for_financial_closure()` and adds an assertion for that same guard to the rollback-only harness. **That latest helper/harness change still requires PHP lint and a fresh runtime harness pass.** It tests the shared guard used by the FM handler; it does not prove the browser route itself or the automatic closure deferral after a funding return at runtime. Do not post a funding return or change project 7's draft allocation/payment evidence. After pulling `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`, then run the harness with process-only `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Keep the already-passed incentive, payroll, notification, salary-advance, and Projects Phase 5 gates closed unless regression evidence appears.


### 2026-10-16 — Automatic financial-closure decision made directly testable (local verification pending)

The FM funding-return handler now uses `akp_project_financial_closure_outcome()` from `modules/projects/project_funding_accounting.php` to select one of three outcomes: `complete` only when controlled balance is at most 0.009 and no draft funding allocation exists; `draft_allocations_pending` when balance is zero but a draft allocation remains; or `balance_remaining` when controlled balance remains positive. The existing rollback-only draft-funding harness asserts all three outcomes, the explicit FM closure guard, processor blocking, and unchanged award/journal/funding evidence. Local PHP lint and harness runtime must be repeated against `ahl_el_kheir_incentive_test_20261015_r1` after pulling the latest `main`. This is helper/rollback-harness coverage, not proof of the real browser POST route or delivered employee notification. Do not post a funding return or change project 7, its draft allocation/payment evidence, or policy R5.


## 2026-10-16 — Employee incentives: new-sponsor first-payment gate passed

The local rollback-only harness `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php` passed against the isolated database `ahl_el_kheir_incentive_test_20261015_r1`. The runner verified both configured and connected database names before invoking the harness. User-reported output:

```text
SAFE_TARGET | ahl_el_kheir_incentive_test_20261015_r1
PASS | new_sponsor=first_confirmed_payment | recipient_snapshot=PASS
PASS | later_payment_rejected=PASS | no_award_before_HR_verification=PASS
PASS | rollback_cleanup=PASS | sponsor=2858 | employee=16
TEST_EXIT_CODE=0
```

The new-sponsor first-confirmed-payment rollback-harness gate is closed at this acceptance boundary. Do not repeat it without regression evidence. Active R5 was not modified; the live database was not the test target. Remaining separate gates include the real-browser PM-to-FM project-closure workflow and end-to-end employee notification delivery/visibility. Details are recorded in `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.

## 2026-10-17 — Employee incentive notification acceptance

The employee-facing scheduled-incentive notification is now browser-verified at the tested local boundary. The user saw the expected Arabic title/body for 12.34 SDG in payroll period 2026-09 in the real inbox at `http://localhost:8081/AhlElKheir/modules/notifications/index.php`, with test notification ID 200. After release of harness PID 4528, independent cleanup checks confirmed notification 200 absent, payroll 21 restored to 0.00/draft, original disposable account hash restored exactly, `.htaccess` restored to `Options -Indexes`, clean Git status, and cleared test-only environment variables. The final harness console PASS transcript was not retained.

The test-generated link `http://localhost/modules/notifications/index.php` came from a CLI-run harness that does not seed web host/document-root/script-name context used by APP_URL/base-path detection. Treat this as a CLI test-context artifact unless reproduced through a real browser action; do not change production config without evidence. The employee inbox delivery/visibility gate is closed. Real-browser PM-to-FM project-closure acceptance remains separately open. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.


- **2026-10-17 — Project financial-closure guard verification:** Latest PHP 8.2 lint and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php` passed against `ahl_el_kheir_incentive_test_20261015_r1`. Checks include explicit FM closure guard, automatic closure outcomes, processor block with draft allocation, unchanged awards/journals/funding evidence, and rollback cleanup (project 7, policy R5). No funding return or durable financial change was made. Real-browser PM → FM closure workflow remains open; use `http://localhost:8081/AhlElKheir/` and a safe eligible test fixture only.

### 2026-10-17 — Employee incentive project-completion browser acceptance

Current open gate: resume the disposable-database real-browser workflow at existing project `PRJ-0006` after a Supervisor closure request was already recorded. Source commits `4258ebb656a3229b84d5d7729868ce92a683f921`, `f7f8bae9753694fd32b7f916443a1f2a8493a3c6`, and `ba5ed5cec327e45d396ecbfb333a6c6d27ea5637` address PM visibility, the Supervisor confirmation prompt, and removal of duplicate toast/scroll initialization. Local lint after the latest cleanup and browser verification are pending; diagnose the toast/scroll symptom if it persists. Read `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for the exact acceptance boundary. Do not resubmit the request or touch the live database.


### 2026-10-17 — PM closure confirmation/toast source fix; local verification pending

The disposable test database confirms `PRJ-0006` was already administratively closed by the PM and project-completion event 28 was created as `pending_verification`. There is no FM financial-closure audit row or award yet; active policy R5 remains unchanged at 1,500.00 SDG. The missing prompt was caused by the PM `close_project` form having no confirmation handler; the success toast was lost because redirecting back to the closed project failed the PM view guard. Source commits `986cef5d21d0ae972a1ec6dd9f624a619a851b40` and `b5879cb2cc69be7f3a30301eb72b4570efb12497` add confirmation and display the one-time success toast on the Projects dashboard. Pull `main`, lint `modules/projects/view.php` and `dashboard/projects_dashboard.php`, then verify in browser. Do not repeat closure for project 6; continue FM closure only through the normal UI on `ahl_el_kheir_incentive_test_20261015_r1`.

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


### 2026-10-18 — Employee incentive immediate-notification requirement

Active work: notify employees immediately when a qualifying activity is recorded, before later FM/higher-management approval gates. Notices must say the activity is recorded and the incentive remains pending the existing verification/financial conditions; final earned and paid notices remain separate. Implementation commits: `77da80e0`, `895b4ba2`, `6ccd172a`, `a3b6130b`, `04418b2f`, `50c43398`. Runtime acceptance is pending: lint, the modified rollback-only project harness, and browser inbox verification on existing disposable DB `ahl_el_kheir_incentive_test_20261015_r1`. Do not create a database or touch live payroll/financial data.


Latest immediate-notice wording clarification commit: `c0316a4d47b708dedd37e74da8ac2d884b840dff`. The early message describes a recorded activity and expected incentive, not final earned/paid status.


## 2026-10-18 — Incentive project-notification timing

The current incentive task is to ensure project-completion employee notices are sent only after FM final financial closure. Commits `28578e3c9088fe8efdfccc671e1e2e7e7b99721f` and `2a1d7b05e899582e40a6a0e864159914cc09b8e7` remove the early PM-closure notice and add rollback-harness assertions for the PM/FM boundary. Source-level only; local PHP lint and the targeted rollback-only harness have not yet been rerun. Use only `ahl_el_kheir_incentive_test_20261015_r1`; PRJ-0024 is already administratively closed and must not be closed/reopened again for testing.


## 2026-10-18 — Rollback verification passed

Local PHP lint passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_project_incentive_payroll_rollback_tests.php`. The targeted rollback-only harness passed on `ahl_el_kheir_incentive_test_20261015_r1`, including `project_notice_waits_for_FM=PASS` and `rollback_cleanup=PASS` (policy R5, 1,500 SDG). Real FM closure/earned-notice delivery for PRJ-0024 remains pending; do not repeat PM closure or alter R5.


## Latest continuation — 2026-10-18 — project-completion incentive regression

The real disposable-database acceptance case `PRJ-0024` exposed that the prior project incentive rollback harness did not cover a supervisor assigned only through `project_supervisor_assignments`. The closure-time recipient snapshot queried `project_team` alone. Correction commits on `main`: `dd5c072c34dcd40dd8232e8f5df3aeac9b748e30` (source) and `f2ff7bb7bd4b56011a45c03fc591a8aefbd43c82` (supervisor-only regression fixture). Local PHP lint and the guarded rollback-only harness passed after the integration merge. Both modified PHP files lint clean. The harness ran with process-scoped `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`, exit code 0; supervisor snapshot, FM gate, recognition journal, payroll link, pending outbox, wait-for-FM boundary, and rollback cleanup all passed (policy R5, reward 1500.00 SDG). The fixture uses a supervisor-only `project_supervisor_assignments` row and no active team row. This is synthetic rollback evidence only; PRJ-0024 historical recovery and actual notification delivery remain unverified. Do not repeat closure or modify PRJ-0024. The project creation form already requires a primary supervisor server-side.


## 2026-10-18 — Immediate incentive activity-notice acceptance harness added

Commit `69ac7eb1b7da82f7495ea12db71c59b2725c4540` adds `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`; commit `bb292f02acfcce504b84e69c1f8179b13332fc63` tightens it to require a newly inserted notification row for each tested type. The rollback-only harness covers collection, new sponsor, and reactivation recipient, category, expected reward context, provisional wording, unread state, and cleanup. Source review confirms call sites in `modules/transactions/create.php`, `modules/sponsors/create.php`, and `modules/administration/winback.php`. **Local PHP lint and execution against `ahl_el_kheir_incentive_test_20261015_r1` are still pending; do not claim runtime acceptance or close the incentive module until the user supplies those results.** The test calls the shared helper directly and does not simulate the three real browser POST workflows. Preserve R5 and the local `.htaccess` disposable-database override.

## 2026-10-18 — Immediate activity-notice helper acceptance passed

The user synced `main` and locally linted `modules/hr/lib_employee_incentives.php` and `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`; both passed. The rollback-only harness ran against the existing disposable database `ahl_el_kheir_incentive_test_20261015_r1` and returned:

```text
PASS | immediate_notice=collection,new_sponsor,reactivation | correct_recipient=PASS
PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS
```

This closes the shared-helper acceptance gate only. It does not simulate the collection, sponsor-creation, or reactivation POST routes, nor prove browser inbox rendering. Next focus on the remaining employee notification delivery/UI acceptance and the already-documented incentive browser gates. Preserve active policy R5, do not use the live financial database, and do not repeat closed tests without regression evidence.


## Deferred development point — collection incentive eligibility (recorded 2026-10-18)

The user raised a business-design concern after the real browser collection submission displayed a provisional “activity linked to an incentive” notice: routine monthly sponsorship collection is ordinary job responsibility and may not merit an additional incentive, especially where the same employee may also qualify for the separate new-sponsor acquisition incentive. Revisit this with the user's forthcoming idea before changing behavior. Candidate recommendation to evaluate later: disable or redesign the collection incentive category and its provisional activity notice, while retaining new-sponsor, reactivation, and project-completion incentives unless the user decides otherwise.

**Deferred, not approved for implementation.** Do not disable the category, change active policy R5, modify existing events/awards, delete notifications, reverse accounting, or change payroll now. Preserve historical evidence. The observed collection notice is provisional and does not prove an award was issued; before describing an employee as having received a new-sponsor incentive, inspect the actual event and award records. Continue the already-open acceptance work without repeating the completed collection/new-sponsor browser submissions or closed helper harness.


## 2026-10-18 — Browser evidence and next read-only incentive-state check

The user has now completed real-browser checks of two immediate activity-notice routes on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
- Active new-sponsor creation for user 19 created sponsor `SP-002859` (“اختبار الحوفز”); the inbox showed the provisional activity notice with expected reward 2,000 SDG and explicit pending-verification/not-paid wording (observed 19:16:45).
- Monthly sponsorship collection submission reported two payments submitted to FM for approval/posting; the inbox showed the provisional collection activity notice for expected reward 1,000 SDG (observed 19:29:40). Do not assume this submission inserted only one payment.

These browser results prove the activity notices rendered; they do **not** prove an award was issued. The user's concern that the same employee may already have received a new-sponsor incentive must be resolved from durable records, not notification wording. Next step: run a read-only query against the same disposable database for incentive events and joined awards for recipient user 19, especially `new_sponsor` and `collection`; report event status and award status/payroll/journal links before deciding whether any earned incentive exists. Do not mutate, reverse, delete, or rerun the already completed browser submissions. New-sponsor first-confirmed-payment acceptance from 2026-10-16 remains closed and must not be repeated.

The separate development point about whether routine collection should qualify for an incentive is deferred and recorded above; no category, active R5 policy, event, award, notification, payroll, or accounting behavior has been changed for that recommendation.



## 2026-10-19 — Routine collection incentive removal: rollback verification CLOSED

The collection-incentive removal is now verified locally against disposable database `ahl_el_kheir_incentive_test_20261015_r1`. Seven PHP syntax checks passed for the four changed PHP source files and three updated harnesses:
- `modules/transactions/create.php`
- `modules/accounting/lib_sponsor_payment_approval.php`
- `modules/hr/lib_employee_incentives.php`
- `modules/hr/incentive_verification.php`
- `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`
- `tools/run_hr_employee_incentive_source_event_rollback_tests.php`
- `tools/run_hr_incentive_verification_rollback_tests.php`

All three rollback-only harnesses passed:
- Immediate activity notice: new-sponsor/reactivation notices pass; collection notice blocked; correct recipient, provisional wording, reward context, unread state and rollback cleanup pass.
- Source event: collection source event blocked; no new collection award path; rollback cleanup passes (policy 18).
- Verification: HR manager fixture and recipient eligibility pass; pending-to-verified transition and award creation pass; recognition journal Dr 5500 / Cr 2400 for 2,000 SDG; duplicate-recognition guard and rollback cleanup pass (policy 11, employee 1).

**Closed:** collection-incentive removal rollback verification. Routine collection submissions no longer create a provisional incentive notice or a new collection source event, and pending legacy collection events cannot be verified into new awards. This blocks new collection incentives; it does not erase or rewrite historical events, awards, notifications, payroll, accounting entries, or policy R5. New-sponsor, reactivation, and project-completion incentives remain enabled. The catalogue entry and active R5 were not changed. No application/browser tests should be repeated absent concrete regression evidence.



## 2026-10-19 — Employee incentives: current acceptance boundary

The collection-incentive removal is verified and closed at its documented rollback-only acceptance boundary. Seven PHP syntax checks and three rollback-only harnesses passed on the disposable database before its cleanup. Routine collection no longer creates new incentive notices/events, and pending legacy collection events cannot be verified into new awards. Historical records, active policy R5, catalogue, payroll and accounting were preserved. New-sponsor, reactivation and project-completion paths remain enabled.

Other verified incentive evidence remains bounded: new-sponsor first-confirmed-payment was browser-verified on 2026-10-16; the shared immediate-notice helper passed rollback tests for new-sponsor/reactivation; project-completion award logic passed rollback-only tests including a separately assigned supervisor; and the one-time PRJ-0024 supervisor recovery passed on the disposable database (allocation 15, award 24, journal 178, scheduled award, outbox sent, notification 232, audit recorded). Neither the recovery nor synthetic harnesses prove the complete real-browser PM-to-FM closure path or retry behavior.

**Whole-module status: NOT YET FULLY ACCEPTANCE-CLOSED.** The source and the listed isolated runtime paths have evidence of working behavior, but the documented acceptance gaps remain: real-browser earned-notification delivery after FM financial closure (including supervisor PS1 and no duplicates on reload/retry), real business POST/browser inbox acceptance for the retained new-sponsor/reactivation provisional notices, and project-team assignment UI coverage (initial selection, add/duplicate rejection, unassign/history retention, edit-page behavior). Verification was performed on disposable test state; do not claim production/live-database acceptance. Do not repeat closed tests, re-close/recover PRJ-0024, recreate deleted disposable databases, or alter R5. Resume only the remaining gates above when the user is ready.

## 2026-10-19 — Incentive immediate-notice harness result

The user ran the updated immediate activity-notice rollback harness after syncing `main`. All six required tables reported InnoDB; the engine preflight, new-sponsor/reactivation provisional notices, blocked collection notice, recipient/content/unread assertions, and rollback cleanup passed. This is helper-level rollback evidence only; the real POST-to-inbox and PM-to-FM earned-notice/browser gates remain open. Do not rerun the harness without regression evidence or use the live database for further mutating acceptance tests.

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

## 2026-10-19 — Employee salary statement (source committed; local verification pending)

Added a secure employee-facing payroll statement at `modules/hr/payroll_statement.php`, linked by an in-system notification after `hrPayrollChangeStatus()` commits a successful paid transition. The route resolves the authenticated employee and requires the requested payroll to belong to that employee, with `status='paid'` and `accounting_status='posted'`. It uses saved payroll components and the effective salary-history currency; total paid follows the existing posting formula `net_salary + incentive_amount`, with incentives shown separately to avoid double-counting. The A4 Arabic-first bilingual layout supports browser print / Save as PDF. Notification insertion is best-effort after commit and deduplicated by recipient + statement link + title.

Source-only work. PHP lint and browser acceptance against the user's local XAMPP/MariaDB are pending; no payroll was paid and no database rows were mutated for this change. Local verification must be limited to lint, route access control, visual print preview using an already-paid/posted payroll, and checking that notification creation occurs only after a genuine successful paid transition when such a workflow can be safely observed. Do not create or pay a test payroll against live financial data just to test this feature.


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


### 2026-10-19 — GM payroll period completion report
The GM-only read/print payroll period report is committed to `main`. See `docs/HR_GM_PAYROLL_PERIOD_REPORT_CONTINUATION.md`. Static source edits are in place; local PHP syntax and live Apache/database verification remain pending. October 2026 payroll/accounting rows were not changed, and no repeat payment is required.


## 2026-10-19 — GM payroll payment history navigation
Added a dedicated GM payroll payment history page and a quick-access card on the GM dashboard. The history lists payroll periods with at least one payroll record in `paid` status, shows each period's eligible/paid-and-posted counts and posted payment totals, and links to the existing read-only period report. The GM sidebar entry now opens the history page; notification links still open their specific period report directly. The page is GM-only and read-only; it does not change payroll or accounting data.

**Verification boundary:** source committed to `main`; local PHP syntax and browser verification are pending. Do not pay, approve, regenerate, or otherwise mutate payroll records for this UI change.


## 2026-10-19 — GM payroll payment history navigation CLOSED
The user confirmed the GM payroll history navigation works as intended. The implementation on `main` is accepted: the GM dashboard and sidebar open the GM-only, read-only period history, and each period links to the detailed payroll payment report. No payroll/accounting rows were changed for this navigation task. Do not repeat its verification or reopen it without a concrete regression. The next active request is the FM payroll table usability refinement in `modules/hr/payroll.php`: reduce width, make the header remain visible during vertical table scrolling, preserve action-button usability after POSTs, and remove only demonstrably redundant display information. Inspect the existing same-path POST handling before changing it.


## 2026-10-19 — FM payroll table usability source update
The FM payroll table usability changes are committed to `main` in `modules/hr/payroll.php` (`85269f123d042a08735680d487c78b0c7715783b`) and `assets/js/app.js` (`f9cf0c0a2e079ae297c1674a8d18b7b150d738f0`). The table is compact/fixed-layout, has a bounded scroll area with sticky headers, combines status/accounting into one column while retaining journal IDs, removes the repeated salary effective-date display, fixes draft amount form markup (final valid-cell placement commit `fecd42a9edbae3ff91b1092f81bcb4dc8c06eeb8`), and preserves the marked table scroll position across same-page POST fragment replacement. No payroll/accounting data or business logic was changed. Local PHP syntax and browser verification are pending; see `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`.


## 2026-10-19 — FM payroll table refinements and selected approval

The follow-up request is implemented in source on `main`; local syntax/browser verification remains pending.

### Changes
- Replaced fixed percentage column widths with auto sizing and compact, translated header labels. Full labels remain available as header tooltips; cell contents wrap as needed.
- Darkened the sticky table header to a navy background with white text.
- Migrated the visible payroll page headings, period controls, summary labels, table headings, status/accounting badges, row actions, policy/note text, empty state, period month names, journal-entry label, and page-specific success/error messages to stable Arabic/English translation keys.
- Added draft-row checkboxes and a select-all-drafts checkbox in the Employee heading. The “Approve selected” action shows the current selection count and is disabled until at least one draft is selected. Existing “Approve all drafts” remains available.
- Added `hrPayrollApproveSelected()` in `modules/hr/lib_payroll_accounting.php`. It rejects malformed/empty/oversized selections, locks and validates every selected row against the chosen month/year and `draft` status, rejects non-positive basic salaries, and approves all selected rows in one transaction or none. It only changes status to `approved` and accounting status to `ready`; it does not pay payroll or post accounting.
- Kept the Save action because editable allowances, overtime and general deductions need an explicit save to persist them and trigger the existing automatic recalculation of attendance deductions, salary-advance deductions and scheduled incentives. It is now a compact, clearly labelled action beside Approve, rather than under Net salary.

### Scope and verification
- No schema changes or migration; no payment/accounting journal creation was added to the selected-approval operation.
- Static source audit confirms all literal `hr.payroll...` keys referenced by the page exist in both language catalogs, fixed percentage width rules are removed, the dark sticky header is present, the selected-checkbox form association exists, and selected approval has no paid/posting path.
- Local PHP lint and focused browser verification are still required. Do not approve, pay, or otherwise mutate real payroll records just to test the new UI; verify syntax first, then inspect selection controls and header layout without submitting an approval unless the user explicitly chooses safe draft rows.


## 2026-10-19 — FM payroll table refinement follow-up
Committed the follow-up to `modules/hr/payroll.php` and `modules/hr/lib_payroll_accounting.php`, plus Arabic/English catalog entries. The page now uses auto-sized columns with compact translated labels and full tooltips, a dark sticky header, translated English month names and visible payroll-page labels/messages, and checkbox-based selection for approving chosen draft rows. The new `hrPayrollApproveSelected()` helper validates all IDs against the selected period and draft state and applies approval atomically; it only sets approved/ready and does not pay or post accounting. Save remains necessary for explicit persistence of editable allowances/overtime/general deductions and their existing automatic recalculations, but is now compact and beside row actions. Static source audit passed; local PHP lint/browser verification pending. Details: `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`.


### FM payroll workflow redesign — 2026-10-20

Step 2 source audit is documented in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. Current account resolution, paid/accounting coupling, notification completion gap, statement authorization boundary, private storage requirement, proposed proof/default model and schema-retrieval blocker are recorded there. No application or database changes were made. Migration work is gated on verified local schema and storage conventions.


### FM payroll workflow redesign — 2026-10-20 Step 2 closed

Local schema output has been reviewed and the proposed data model finalized at design level. The continuation document records exact schema facts, account-policy boundary, period default, individual proof/evidence states, private authorized streaming, legacy paid evidence, employee notification success and GM completion requirements. No application/database changes yet. Proceed to authoring the migration and storage contract as the next separately documented step.

### FM payroll workflow redesign — 2026-10-20 Step 3 draft

Migration draft `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql` is committed on `main` (`f1225411c429f0b0eb9486ccec03f6be02e1ed8a`). It introduces period account settings and per-payroll proof metadata only. It is not applied. The continuation doc defines private storage, safe upload/streaming, legacy evidence, and no-silent-replacement constraints. No application or database state changed.

### FM payroll workflow redesign — 2026-10-20 Step 4 source checkpoint
Committed period-level payment account settings, approved-row individual/group account overrides, private proof upload, authorized proof download, and legacy evidence attachment. New proof files use a 10 MiB limit and PDF/JPG/PNG MIME validation; uploading does not itself mark payroll paid or post accounting. Migration is still unapplied; local lint/runtime verification is pending. Continue with the separate explicit transfer-confirmation/accounting/notification gate before treating the redesigned workflow as operational.


### FM payroll workflow redesign — 2026-10-20 Step 5 source checkpoint
Added a CSRF-protected explicit transfer-confirmation action, proof/account gates, transactional proof confirmation + paid status + existing accounting posting, notification success reporting, and GM completion checks for proof plus employee statement notification. Generic paid-status POST is blocked. Source is committed on `main`; migration not applied and local PHP lint/runtime checks pending. See `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md` before proceeding.

## 2026-10-20 — Initial Salary Capture at Employee Account Creation

Active issue: September 2026 draft payrolls contain zero basic salary for 18 employees because their matching salary-history rows are zero and the positive rows shown in the user-provided query begin on 2026-10-01. Do not copy October salaries backward or approve these drafts without verified September salary facts.

Source changes now on main:
- Shared account-creation form captures a required hire date and positive initial monthly basic salary.
- The canonical user/employee provisioning flow passes those values into employee creation.
- Initial salary history is created with the hire date as its effective start; payroll continues to select salary history effective at each period end.
- No schema migration was needed for these source changes.

Status: SOURCE CHANGES COMMITTED; local PHP lint and real XAMPP/browser verification are still pending. The pre-existing September salary data is not repaired by this onboarding change and requires authoritative historical amounts before any correction.


## 2026-10-20 — HR employment contract builder (source only; local verification pending)

The existing HR contract/salary page is `modules/hr/contracts.php`; contract metadata and effective-dated salary behavior remain in `modules/hr/lib_contract_salary.php`. A new authorized in-system editable Arabic contract form was added at `modules/hr/contract_builder.php`, using bundled TCPDF to generate an A4 RTL PDF for viewing/printing. A link was added from the existing contracts page. The PDF form includes employee/job details, editable dates and remuneration, work arrangements, leave fields, safeguarding/PSEA, conduct, confidentiality/data, anti-fraud/conflicts, health/safety, termination, disputes, governing law and signature areas. It does not persist draft content or mutate employee, contract, salary, payroll or accounting records.

Source commits: `f0e5fdba4b32e179091478b092eccd5dd10f033f` (builder), `ca14abbd85b490c5025f8367c1fe2d2caa43f88d` (contracts-page link). Continuation: `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

**Not yet verified locally:** PHP lint, authorized/unauthorized browser access, CSRF rejection, PDF Arabic rendering/pagination/print, and absence of database/storage writes. The contract wording is not legally approved; qualified Sudanese legal review is required before official use. Do not start the signed-contract/onboarding integration until this PDF acceptance boundary passes.

### Follow-up — contract field validation

Commit `eef4d17e02543e1327ed8cc3db9fee3b916aa11f` adds server-side length/email/weekly-hours validation, requires an end date for fixed-term contracts, and removes the assumed nationality default. Local PHP lint and browser/PDF acceptance remain pending.

### 2026-10-20 — HR employment contract portal and role catalogue

Source implementation is committed on `main`: separate HR dashboard shortcut `بوابة عقود العمل`, editable `hr_job_description_templates` catalogue/migration, role-matched job descriptions and role-specific contract terms, organization branding from existing settings, and review warnings for incomplete/unapproved descriptions. The migration has not yet been applied to the local database, and local PDF/browser behavior is not yet verified. Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`; do not invent job duties or mark catalogue placeholders approved. Sudanese lawyer review is required before official contract use.


## 2026-10-20 — HR job-description proposal drafts and contract auto-prefill

Added `database/migrations/2026-10-20_hr_job_description_proposals.sql` to populate empty job-description fields with proposed Arabic drafts while preserving existing content and approval status. The `HR Test` catalogue row is deactivated without deleting its employee. The contract builder pre-fills the proposed reporting line from the selected position's catalogue entry and labels it for HR confirmation. Runtime application/checks remain pending; see `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-20 — Role-aware job descriptions and responsibility list editor

Follow-up source now distinguishes `employees.position` from the linked account role (`employees.user_id` → `users.role_id` → `roles.code`). Added migration `database/migrations/2026-10-20_hr_job_description_system_roles.sql` to create position+system-role description variants from documented workflows, and `database/migrations/2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql` to clear only exact unchanged generic placeholders from the earlier proposal migration. Catalogue responsibilities are now individually editable with add/remove controls and stored as newline-separated items; the contract builder matches position+role first and prints responsibilities as an indented list. Local migration/runtime verification remains pending; see `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

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


### 2026-10-20 — HR job-description qualifications UTF-8 repair

The role-qualification seed migration now explicitly sets `SET NAMES utf8mb4`. A separate data-only repair migration, `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications_utf8_repair.sql`, restores only qualifications beginning with literal question marks on unapproved rows other than `position_title = 'HR Test'`. It reuses the original role-specific Arabic qualification drafts and preserves role purpose, responsibilities, titles, approval status and the HR Test row.

Local diagnostic confirmed 26 catalogue rows, 25 qualifications beginning with question marks, zero approved rows, one HR Test row, and utf8mb4_unicode_ci on all four relevant text columns. Purpose/responsibilities/title fields did not start with question marks. **Repair migration committed; local application and post-repair verification pending.** Do not rerun the original seed migration as a repair.


**2026-10-20 — Job-description qualification UTF-8 repair: local database verification passed.** User applied `2026-10-20_hr_job_description_zz_role_qualifications_utf8_repair.sql` with `--default-character-set=utf8mb4`. Verification returned `remaining_corrupted_qualifications = 0`; qualification hex values contain valid UTF-8 Arabic bytes rather than `3F` question-mark bytes. The `HR Test` row remained excluded and its qualifications remained NULL. PowerShell renders Arabic position titles as mojibake, but this output alone does not establish stored title corruption; do not rewrite titles without a byte-level diagnostic. Next scope is the user's original HR job-description/contract builder task, after reviewing the relevant continuation document and source. Local runtime UI/PDF acceptance remains pending.


### 2026-10-20 — Contract-builder purpose draft field correction

Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`. The contract builder previously combined purpose and responsibilities in one `duties` field, so `الغرض من الوظيفة` had no dedicated control. Source commits `73b91d1a40024564d7399a80ae91b011167dbf9c`, `8fc40930c4224f04dab7e1f8c9c96c4fdfafd4b7`, `ec565758660214ac371ef14b692b4265249fc2b7`, `4d3d030bc4b96537141eec16fc6e3e0e86bce05d`, `59332e20a7d186eae3f969a9a94c24b1e2225d44`, `59ecd7b5057d9fe2910ccc3314b073d305cc19d6` add separate purpose, responsibility, qualification and reporting-line controls, each populated from the selected employee's role-aware catalogue match. Source-only; local PHP lint and browser/PDF verification remain required. Do not rerun data migrations.


### 2026-10-20 — HR job-description fallback catalogue clarity

Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`. The employee-wide audit found that current linked employees have role-specific draft matches except HR Salary Test (employee 21), while some title-only rows are intentionally empty fallbacks. Commit `6264c897b2bbadb460e0ba0d3e0c029b1443312d` labels general fallback entries, shows active role-specific template counts, and warns when editing an empty fallback that has role-specific descriptions. Documentation update commit `ff90fae0ab121b85267490fe10679120af7cffab`. Source-only; local PHP lint and browser verification remain pending. Do not reactivate the HR Test template or change employee data without further evidence.


### Follow-up — null-safe fallback employee counts

Commit `4435226e37405e9a2963dd07af0d1c14f71e7f92` aligns general-template employee counting with contract-builder matching by treating NULL and empty `system_role_code` values as title-only fallbacks. Local lint and browser verification remain pending.
## 2026-10-20 — HR job-description general fallback drafts

The catalogue showed blank purpose/responsibility fields on general title-only fallback rows because an earlier cleanup migration removed exact generic proposals. New data-only migration `database/migrations/2026-10-20_hr_job_description_zzz_general_fallback_drafts.sql` restores distinct, broad, editable drafts only to active title-only records that remain `needs_review` and have blank fields. It preserves role-specific drafts and HR edits, and does not reactivate `HR Test` or change schema. Commit: `5b6a553b22ff5a0be3d0a2b6430556ccf5138be9`. Local migration application, SQL verification, PHP lint and browser checks are pending; do not rerun prior migrations.


### 2026-10-20 — HR job-description qualification baseline and reorderable fields

New data-only migration: `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql`. It prepends the minimum high-school qualification and basic computer/office-program knowledge to active job-description templates, preserves prior qualification text, and returns changed descriptions to `needs_review`. Apply after the existing job-description seed/proposal/role-qualification migrations.

The job-description catalogue and employment contract builder now support drag-to-reorder on purpose, responsibility, qualification and optional contract-term items, with automatic renumbering and saved/PDF order matching the visible order.

**Source committed; local migration, PHP lint, browser and PDF verification pending.** Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`; do not treat this UI/data change as runtime verified until the user confirms local checks.


## 2026-10-20 — Employment contract numbering and draft reservation

modules/hr/contract_builder.php now reserves an official CTR-YYYY-000001 number from the existing hr_employee_contracts record ID during PDF generation. The number field is read-only and is no longer client-submitted. The builder creates a draft record in a transaction, rejects overlapping draft/active contracts using the existing contract-register rule, and commits only after TCPDF successfully renders the PDF. A one-use session token prevents browser resubmission from creating duplicate draft records.

The builder intentionally does not call hrCreateEmployeeContract(): that helper also writes salary history and can update the employee salary mirror and employment state for current/past start dates. The new builder path leaves those changes to the existing explicit contract activation workflow. No schema migration was added.

Source commit: 8ed43dc8c7e0f2e2c5693e1777bfb8324bdc3115. Runtime verification is pending on the user's XAMPP app/database; do not claim the database insert, PDF number, overlap rejection, or salary-history protection is runtime-proven. Continue from docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md.


## 2026-10-20 — Unify contract creation and register workflow

Source changes committed directly to `main`:
- `315f221c9f43d4bbbe55b0f2acdce88e3b403296` — removed the duplicate `add_contract` creation path from `modules/hr/contracts.php`. New-contract links now open `modules/hr/contract_builder.php` with the selected employee. The old `?view=add` URL now explains the canonical workflow instead of presenting a second creation form.
- `b312f117f077f14e8c5f4f91bd80a4aeaf0c2ebf` — the builder's return link now returns to the employee's contract-register view, not the retired add form.
- `dd2b1dcc99762226e1ddf8a28000e6fc4a7d5190` — added an explicit optional probation-end date to the builder, validates it against the contract start date, includes it in the PDF text, and persists it to the draft's existing `probation_end_date` column so the register's existing activation/employment-state behavior remains available.

The register continues to manage contract status and salary history. It now uploads or replaces a signed copy against an existing contract record, rather than creating a new contract as a side effect of uploading a file. The upload validates that the contract belongs to the selected employee and retains the existing 10 MB / PDF-JPG-JPEG-PNG limits. Replacing a file updates the register path but deliberately does not delete older files.

No schema change or migration was introduced. The builder continues to reserve the official number and create a draft when PDF generation succeeds; this does not activate the contract or update salary history/employment state. The full editable clause text remains in the generated PDF, while the register stores its existing core fields.

**Verification status: SOURCE COMMITTED; PHP lint, browser interaction, signed-copy upload, draft activation, salary-history/employment-state behavior, and generated PDF are NOT runtime verified.** Pull `main`, lint both changed PHP files, then test one non-production/appropriate employee record through the configured XAMPP URL. Do not create a second draft for an employee/date period already covered by an active or draft contract. Sudanese legal review remains required before official use.


## 2026-10-21 — Consolidate the HR contract entry point (source committed; local verification pending)

The HR dashboard now exposes only one contract-related action: `بوابة عقود العمل` at `modules/hr/contract_builder.php`. The former separate `العقود والرواتب` dashboard shortcut was removed to reduce competing entry points. The portal header now links to both the existing contract register/salary-history page (`modules/hr/contracts.php`) and the job-description catalogue (`modules/hr/job_description_catalogue.php`). The register remains intentionally available as a subordinate workflow because it still owns signed-copy uploads, draft activation/termination, contract status and salary-history changes; this change does not delete records, files, or the register implementation.

Source commits: `be9c2515ec0f387406f4e54180e103d2985869bf` and `f3263b20eb666de05d096bc6d30d0143e92b15b7`. Local PHP lint and browser navigation remain unverified. Do not remove `contracts.php` until all register capabilities are intentionally integrated and runtime-tested through the unified portal.

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


## 2026-10-21 — Contract PDF logo compatibility

Inspected `includes/header.php`, `modules/accounting/voucher_print.php`, `modules/hr/salary_advance_voucher_print.php`, `modules/hr/salary_advance_repayment_voucher_print.php`, `modules/settings/index.php`, and `modules/hr/contract_builder.php`. The shared print header and voucher pages use `assets/img/logo.png`; settings and the contract-builder portal use the configured `site_logo`. The contract PDF alone now uses the existing same-logo `assets/img/logo.jpg` to avoid TCPDF's transparent-PNG requirement. This is source-only; local lint and PDF verification remain pending.


## 2026-10-21 — Second contract PDF design revision (voucher-style hierarchy; runtime pending)

Following user feedback that the employment contract still looked weaker than the existing vouchers, `modules/accounting/voucher_print.php` was re-read as the design reference. The contract's first-page composition has been revised to use a single bordered three-part letterhead (logo, centered bilingual organization identity, and boxed contract number/date), a full-width navy bilingual title bar, a compact employee/position and term summary, and a dedicated highlighted basic-salary band inspired by the voucher amount panel. Clause headings now use a pale-blue panel with navy text and a gold rule instead of a heavy filled bar, to improve readability across long legal text. Existing clauses, job-description content, signature page, logo JPEG compatibility fix, draft reservation/overlap guard, and activation workflow are preserved. No schema or accounting changes.

**Verification status: SOURCE COMMITTED; local PHP lint and actual PDF visual inspection are still pending.** The rendered PDF must be inspected before deciding whether this revision is accepted; do not create another overlapping contract draft just to preview the design.

## 2026-10-21 — Employment contract PDF compact layout (source committed; runtime pending)

Commit `ddffae6105a2da56bce1b5d706bc7d40e1f94ba2` keeps the branded letterhead only on the first page and compresses PDF typography, spacing, table padding, logo size and margins to target a two-page contract with a separate signature page. No schema change. Local PHP lint and actual PDF page-count/readability checks remain pending; continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md` and do not claim the two-page target is verified until inspected in the configured XAMPP app.

## 2026-10-21 — Contract PDF voucher-style header compression

Commit `f30fd321ae7932daa2e87b7b70be8e3f97d437a3` inspects the existing voucher print design and compresses the contract letterhead to a smaller logo, narrower side cells, compact number/date panel, short title band and tighter typography/spacing. The signature page has no repeated header. Local PHP lint and exact PDF page-count/readability remain pending. Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.


## 2026-10-21 — Contract PDF header proportion correction

After the user reiterated that the contract PDF should follow the existing voucher design, source inspection found a concrete sizing mismatch in `modules/hr/contract_builder.php`: the logo was rendered at 24 mm inside a 10%-width header cell, making it wider than its own column and increasing the header row height. Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` reduces the logo to 14 mm, sets the logo cell to 12%, keeps the contract number/date panel at 24%, adjusts the organization cell to 64%, and reduces header-cell padding from 2 mm to 1 mm. This retains the existing voucher-style navy frame, organization identity, and boxed number/date panel. No schema, contract lifecycle, legal wording, or page-break logic changed.

**Verification status: SOURCE COMMITTED; local PHP lint and rendered PDF/page-count verification remain pending.** Pull `main`, lint `modules/hr/contract_builder.php`, then preview only an eligible existing contract period (do not create an overlapping draft). Confirm the logo fits inside its cell, the header is compact, the contract is readable, and the PDF totals exactly two pages. The source still has automatic page breaks for the contract body followed by an explicit signature-page break, so a two-page result is not yet proven.

### 2026-10-21 — Contract PDF header sizing

The contract PDF header was corrected to prevent the logo from overflowing its header cell: logo 14 mm, logo column 12%, organization column 64%, document number/date column 24%, and reduced cell padding. Source commit: `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8`. Local PHP lint and PDF page-count/readability verification remain pending; the signature page is still separated by an explicit page break.

### Contract-builder invalid-form feedback — 2026-10-21
The caught error banner in `modules/hr/contract_builder.php` is now a fixed-position RTL toast with close/autohide behavior; business validation and PDF workflow are unchanged. Source-only until local lint/browser verification. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.


### Contract-builder validation no longer navigates to error page — 2026-10-21
Commit `bd088d1f4d090506df48eecf666c270add50f91b` adds local fetch handling to `modules/hr/contract_builder.php`: server validation errors become current-page toasts; valid PDF responses still open in a new tab. Local lint/browser verification pending. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.


### Contract-builder feedback positioning — 2026-10-21
Commit `e41cadc856223cd861dcfced9983efbb8009231a` removes the viewport-fixed toast and renders server/client validation feedback in the form action area directly before the PDF button. Existing fetch/PDF and validation safeguards remain. Local PHP lint and browser verification are pending; see `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

### Contract Builder rollback — 2026-10-21
The post-redesign validation-toast experiments were rolled back after a malformed action-area form was found in `e41cadc`. Commit `d074486b7cff1a204ab3bf353c829a59ecef32f7` restores `modules/hr/contract_builder.php` to the voucher-style PDF checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. Local runtime verification is still pending; see `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

Correction to rollback record: the complete 799-line checkpoint source was re-applied and checked against the same 799-line source fetched from `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. Final source commit: `7df2bc435d2a6464692895db62cb97ec03a2f109`. The complete file ends with the shared footer include; the PDF submit button and return link are separate. This comparison establishes source equality with the checkpoint, not local PHP/browser runtime success.

## 2026-10-21 — Employment-contract PDF download filename hardening

The user reported that using Download in the browser PDF preview saved the contract with a `.php` extension. Source inspection confirmed the PDF response already used `Content-Type: application/pdf` and an inline `filename="Ahl-El-Kheir-Employment-Contract.pdf"`, so the root cause is not proven without inspecting the actual local HTTP response headers. Commit `1cf3fb0b753b30b49ba5f950498ff534fb802de2` makes the response more robust by clearing any active output buffers immediately before PDF headers/body, and supplying both the ASCII `filename` and RFC 5987 `filename*` parameters while preserving `inline` preview behavior. This is source-only. After pulling `main`, run PHP lint and generate a PDF in the local XAMPP app; use the PDF viewer's Download control and confirm the saved file ends in `.pdf`. If it still saves as `.php`, inspect the actual response headers in browser DevTools before changing more code. Do not claim runtime success before this check.

### 2026-10-21 — Contract Builder info/error toast

Source commit `197f53d75cf4546e8efd6a483e9ea850a18916b5` fixes the source-level cause of contract-builder error messages appearing at the top of a new tab: the PDF form targets `_blank`, but the caught error was rendered as a full-page inline alert. Errors now post back to the original page and display as a toast beside the initiating button; successful PDF response behavior is preserved. Local PHP lint and browser verification are still pending.

### 2026-10-21 — Contract Builder error toast follow-up

Commit `890b9ef95390af64820083a8567c6e5e7fc2ccb0` replaces the failed opener-message approach after user browser feedback showed errors still appearing in a new tab. The form now submits via same-page `fetch()`: JSON errors close the temporary tab and show a toast beside the initiating button; PDF responses still open in the preview tab. Local lint/browser verification is pending.

### 2026-10-21 — Contract Builder toast experiment rollback

Commit `8af298793a4c86d38d303516b2b03992b7258405` restores `modules/hr/contract_builder.php` exactly to pre-toast checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b` after the fetch-based experiment resulted in a local Apache “Not Found” report. The restored file blob matches the checkpoint. Local XAMPP verification remains pending.

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

**Verification boundary:** source correction is committed to `main`; the user's local PHP lint has not yet been rerun, and browser/runtime behavior is not verified. After pulling, run `php -l modules\\hr\\contracts.php` and report the exact result before browser testing. Preserve the existing untracked `storage/hr_contracts/` directory; it was not modified or included in the commit.

## 2026-10-21 — HR contracts modal initialization

The employee-details modal did not open after selecting a card because page initialization ran before the Bootstrap bundle loaded in the shared footer. Source fix: `4fe5b9baaa09830c3bfc06b37c18c3f080660f45`. Local lint and browser verification remain pending; see `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR employee selector presentation correction

The selector was changed from cards to full-width list rows in `7464fc22c31f613fcf26f3c33f32ca3d92be491c`; modal initialization fix: `4fe5b9baaa09830c3bfc06b37c18c3f080660f45`. Local lint/browser verification pending. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR employee modal footer

Removed the redundant modal-footer close-button row; the modal header close control and main-page footer remain. Commit `7c724c77e65a055e9eb66da7605dfe69ec9ad300`. Local lint/browser verification pending. Details: `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

### HR employment contracts modal checkpoint — 2026-10-21

`modules/hr/contracts.php` now suppresses footer elements inside the employee modal and loads internal Contracts / Salary History / Salary Change tabs into the existing modal via fetch, avoiding full-page navigation for tab switching. The Contract Builder remains a separate page. Source commit: `40f1198a3b0c54b022b2154b55a12dfce345b730`. Local PHP lint and browser verification remain pending; do not describe as runtime verified until tested at `http://localhost:8081/AhlElKheir/`.
Follow-up `889a10e6f7368190f8e78a9d356a7fabd638b1c6` hides the shared page footer while the employee modal is open and restores it on close. Local lint/browser verification remains pending.

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


### 2026-10-22 — Nanny group bulk verification

Active task: `modules/accounting/group_verification.php`. Bulk verification supports one selected check across multiple selected families or all three checks, preserves unrelated flags, logs each family change in `verification_audit_log`, and enforces assigned-group scope server-side. See `docs/ACCOUNTING_NANNY_GROUP_VERIFICATION_CONTINUATION.md`. PHP syntax passed in GitHub Actions run `39715384849`; local runtime/browser verification remains pending. The same broad workflow had an unrelated Stage 5 salary-advance payroll integration failure; do not reopen that closed module for this task.


### 2026-10-22 — Nanny group verification controls

The individual family verification action had been displayed for non-pending groups, and its POST handler did not enforce pending status. Commit `e50686f0a8492506459e4465513d03778debc498` adds the individual POST pending-state guard; follow-up commit `da5320d05e2656bb9d6e1f4a7a94b94b032fd177` corrects the markup so the individual edit button/modal only render for pending groups. PHP lint passed in GitHub Actions run `39716749893` for the final source commit; local runtime/browser verification remains pending, so do not treat this as runtime-closed yet.

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

## 2026-10-22 — HR job-description proposals migration: local DB result

The user applied only `database/migrations/2026-10-20_hr_job_description_proposals.sql` to the local `ahl_el_kheir` database after creating a database dump backup. The command completed without a reported SQL error. Read-only verification confirmed:
- Active `needs_review` catalogue entries: 23; entries with blank `reporting_to`: 0.
- Approved entries IDs 6 and 20 remain `approved`, active, and have reporting lines.
- `HR Test` ID 11 is inactive and remains `needs_review`.
This closes the local reporting-line backfill check only; it does not prove every field affected by the migration or the HR portal's browser/PDF behavior. Do not claim the entire eight-migration sequence was applied. The local browser acceptance remains unverified and may be resumed if needed; if the user provides a new requirement, prioritize that task without silently treating browser verification as complete.


## 2026-10-22 — Role-aware AI assistant (initial source milestone)

The user requested a bilingual role-aware assistant using the existing application, with Google Gemini free-tier generation only for carefully limited non-confidential general questions. Initial source is committed on main:
- includes/ai_assistant_widget.php: shared floating panel, consent checkbox, text chat, and on-device-only speech dictation when supported.
- includes/footer.php: includes the widget for logged-in sessions only.
- modules/ai_assistant/chat.php: authenticated POST JSON endpoint, database-resolved active role, CSRF, bounded request size, per-session rate limit, role-section-filtered local user-guide retrieval, and consent-gated Gemini general-answer fallback.
- .github/workflows/php-syntax-check.yml: PHP lint coverage added.

No database/schema changes or business actions. Local guide excerpts and internal project material are not sent to Google. Current limitations and the full verification checklist are in docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md. PHP CI, local browser/session acceptance, and InfinityFree API connectivity remain unverified.


### 2026-10-22 — AI assistant Arabic retrieval follow-up
The first local browser test showed the assistant UI works, but the Arabic question about the first step after sign-in missed local retrieval and reached the unconfigured-Gemini fallback. Root cause: the canonical employee guide described sign-in only in English while retrieval is keyword-based. The guide now includes a reviewed Arabic/English post-login instruction: confirm the dashboard matches the user's job role, and complete a required password change if prompted. Retest the exact question locally before configuring Gemini. No application logic, schema, or privacy boundary changed.


### 2026-10-22 — AI assistant UI refinement
The assistant widget now omits the two introductory privacy notices from the panel, no longer renders source filenames below answers, shows a Font Awesome comments icon beside the launcher text, and starts with the Google-consent checkbox checked. The user's checkbox choice is retained between messages instead of being reset after each submission. The endpoint still performs local-first retrieval and still blocks internal/sensitive prompts from Google; a checked box does not bypass those filters. Source commit: `5149e20b6e42a393ca59fd6d84cec12edaf08372`. PHP lint and browser regression checks for this UI refinement are pending.


### 2026-10-22 — AI assistant launcher / chat history
The assistant launcher has a gold gradient, stronger shadow, hover state, and keyboard-focus outline (commit `f2e92ac73692ac95a340c338811f565ed3839df3`). Persistent chat history is a requested next step but is not implemented: inspect the actual local `users` primary-key schema before writing a migration; never guess FK types or add runtime DDL. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.

### 2026-10-22 — AI assistant persistent history
Per-user chat history UI/API and explicit migration are committed on `main`: `database/migrations/2026-10-22_ai_assistant_chat_history.sql`. Local schema evidence confirmed `users.id INT UNSIGNED`, matching the FK. Application runtime does not create tables. Migration application and local PHP/browser/security verification remain pending. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.

### 2026-10-22 — AI assistant follow-up: Arabic Nanny question + voice diagnosis

The Nanny guide now has Arabic instructions for viewing assigned groups and all active families across assigned groups; Arabic retrieval normalization and Arabic internal-workflow terms were added so missed Arabic questions do not fall through to external AI. The voice error now surfaces the browser's SpeechRecognition error code; voice is not yet verified/fixed. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`. Local lint/browser retest of these latest commits remains pending.

### 2026-10-22 — AI assistant launch-readiness follow-up

Commit `dc0e7640fe98cb134590c0ec3e314c80133e16f4` adds a deterministic local Arabic answer for the Nanny's assigned-groups question and normalizes role-code case. Local lint/browser verification is pending. On-device Arabic speech recognition returned `language-not-supported`; online speech recognition requires an explicit privacy decision and clear user consent before implementation. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.


### 2026-10-22 — AI assistant online voice consent
The owner approved online Arabic speech recognition only with clear consent before audio processing and a typing fallback. Source commit: `18bf40af2f905b5509396d92e17374c0c79ecd7c` (`includes/ai_assistant_widget.php`). The widget now requests consent before starting browser online recognition, retains approval only for the current page, and keeps the transcript editable before submission. Runtime acceptance is pending local PHP lint and browser tests; see `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`. Preserve existing local files and use the existing `ahl_el_kheir` database.


### 2026-10-22 — AI voice recognition diagnostic checkpoint
Owner reports online Arabic speech recognition still fails after consent. Source audit found the widget hid all non-permission failures behind one generic message. Commit `753a3b225c1a992bcb3a6b1ce627ea365fef3092` adds plain-language messages for common failure categories and console-only category logging. This is not runtime-verified; see `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md` for the next verification step.
### 2026-10-22 — AI assistant direct Gemini audio transcription

The owner approved transmitting recorded voice to Google's Gemini API for transcription, after explicit consent before recording/transmission. Commits `d5d91e4` and `d30efdb` replace browser-vendor speech recognition with a MediaRecorder → authenticated PHP endpoint → Gemini transcription flow. The transcript appears in the editable question box and is not submitted automatically; audio is not saved in chat history. Server-side Gemini key required; do not enable paid billing.

Local acceptance remains pending: pull main, PHP-lint `includes/ai_assistant_widget.php` and `modules/ai_assistant/chat.php`, then test consent, Arabic/English transcript, microphone denial, transcription failure, transcript editing, and typing fallback on the existing local app/database. Do not claim runtime success before user verification.


## 2026-10-22 — Gemini audio transcription diagnostic follow-up

The user-approved MediaRecorder → Gemini transcription path reached the endpoint but returned the generic upstream-failure response in the browser. Source audit found the endpoint was discarding both cURL transport diagnostics and Gemini's structured error response, preventing a root-cause diagnosis. Commit `130af192c49aafae11c1369e7e884e9367016ac8` now logs the HTTP status, cURL errno/error, and a bounded Gemini error status/message to the PHP error log while keeping the user-facing response generic and never logging the API key or audio payload. It also removes the undefined `$lang` reference from the transcription prompt, using one language-neutral bilingual prompt.

**Verification is pending.** The repository connector cannot run PHP lint or exercise the user's local Apache/PHP/Gemini request. Pull `main`, run `D:\\xampp\\php\\php.exe -l D:\\xampp\\htdocs\\AhlElKheir\\modules\\ai_assistant\\chat.php`, then make one short consented recording. If it fails, inspect `D:\\xampp\\apache\\logs\\error.log` for lines beginning `[Ahl El Kheir AI transcription]` and share only those lines; do not share the API key or audio. This is diagnostic instrumentation, not a claim that transcription now succeeds.

### 2026-10-22 — AI assistant Gemini audio failure diagnosis

The user-facing audio transcription endpoint returned JSON HTTP 502. Source inspection shows the endpoint itself emits 502 when cURL fails or Gemini responds non-2xx; the exact underlying cause is not yet known. Commit `3b7cc562c57b87533678146412cf0f44f5b1ca7c` maps likely provider/network failure categories to plain Arabic/English messages while keeping detailed diagnostics server-side. This is diagnostic UX, not a verified fix. Next: one consented short recording after pulling `main`; use the displayed friendly message to determine the actual next correction. No extra database or browser developer-tools work is required from the user.

### 2026-10-22 — Gemini voice model retirement identified

The user-facing transcription error category was NOT_FOUND / model setup. Source default was gemini-2.5-flash; current Google documentation records the Gemini Developer API shutdown of Gemini 2.5 Flash on October 16, 2026 and recommends the dedicated gemini-3.5-transcribe speech-to-text model. Commit b4b7c6e7a3069a0c8d5eb254b37db9d9941f5927 changes the default. A server-side GEMINI_MODEL or hosting.php override may still point at the retired model. Next: pull main, lint the PHP endpoint, and perform one short consented recording. Not yet runtime verified.



## 2026-10-22 — Gemini transcription API contract correction

The user tested the updated dedicated model and received “No usable transcript was returned.” The endpoint had been changed to `gemini-3.5-transcribe` but still called the legacy `generateContent` endpoint and parsed the legacy `candidates[0].content.parts[0].text` response. Official Google documentation specifies that Gemini 3.5 Transcribe uses `POST /v1beta/interactions`, accepts audio content with `type`, `data`, and `mime_type`, and returns the transcript in `output_text` (or text content within response steps). This explains the observed successful upstream response with an empty transcript under the old parser.

Commit `7f1ab66a8e8b386eca3bfa4d0482138d82658241` changes the audio request to the Interactions API, uses its `transcription_config.mode=smart`, maps browser `audio/mp4` to the API's documented `audio/m4a` MIME type, and reads `output_text` with a steps-content fallback. It preserves explicit consent, the editable composer, server-side API-key handling, existing size/rate checks, and typing fallback. Official reference: https://ai.google.dev/gemini-api/docs/transcribe and https://ai.google.dev/api/interactions-api-v1.

**Verification status: source correction committed on main; local PHP lint and one real-browser Arabic/English transcription test are still pending.** Do not claim the transcription path is fixed until the user confirms that words appear in the editable composer without automatic submission. If the next test fails, diagnose the exact new user-facing response and existing server log evidence before changing code again.


## 2026-10-22 — Gemini transcription still returns empty text

After the Interactions API request/response correction, the owner tested both Arabic and English and still received the no-usable-transcript message. The precise provider response shape remains unverified. Commit `1b733cc0fef5aeca86b3753230afced29429509d` adds safe response-shape diagnostics only (JSON validity, top-level keys, interaction status, output text length, and step/output counts); it never logs the audio, API key, or transcript. The next action is one local pull, PHP lint, one short consented recording, then inspect only the matching `[Ahl El Kheir AI transcription] Empty transcript response shape:` line from Apache's error log. Diagnose that evidence before changing request structure again. Runtime success is not established.

### 2026-10-22 — AI assistant Gemini transcription follow-up

Gemini 3.5 Transcribe REST request flow was corrected in commit 87c37e4238858b6e126382f41476db8f49929e18: upload the consented recording through the Gemini Files API, then pass the returned file URI to the Interactions API. The user's previous log excerpt contained Apache startup notices only, not the application's diagnostic. Local PHP lint and one browser transcription test remain pending; do not mark voice transcription fixed before successful transcript insertion into the editable composer.
The voice-consent prompt now discloses Google's temporary audio-file retention (up to 48 hours) for the Files API upload; commit f89eaf85bc3b91389ab1c11abe541c02a21abfb4.


## 2026-10-23 — AI assistant text-first checkpoint
Text chat source reviewed. Local guide retrieval is first and role-limited; Google fallback requires the text consent control and passes internal/sensitive-question filters. General-text default model updated to `gemini-3.5-flash-lite` (`2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b`). Voice microphone disabled and labelled future development (`75c52595eef9cd6dd2d8ab71756f06103601c1c8`). Local runtime checks remain pending; do not describe text as fully verified until tested on the user's XAMPP app.

### 2026-10-23 — AI assistant conversation continues across pages
The widget now stores the active conversation ID in tab-scoped `sessionStorage`, reloads its messages via the owner-checked `history_load` endpoint after navigation, clears the stored ID for explicit New chat, and closes the panel on `mouseleave` like the sidebar. Commits: `9db6c37945f2af5e775dc4f9bf651acee7d657b7`, `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`. Local PHP/browser verification is pending.

## 2026-10-23 — AI text chat implementation checkpoint

The requested text-chat implementation task is closed at the source/documentation boundary. The shared assistant now remembers the active conversation in tab-scoped session storage, restores it through the existing authenticated owner-scoped history endpoint after page navigation, clears the active ID for explicit New chat, and closes the panel on mouseleave. Relevant commits: `9db6c37945f2af5e775dc4f9bf651acee7d657b7` and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`. Text path/source checkpoints include `2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b` and `75c52595eef9cd6dd2d8ab71756f06103601c1c8` (voice deferred).

The user confirmed a local-guide text answer works. The repository-editing session did not run local PHP lint or browser acceptance, so the implementation is documented as closed while full local runtime acceptance remains unverified. If not already checked by the user, verify the two assistant PHP files, cross-page transcript restore/continuation, New chat separation, and mouseleave close/reopen on `http://localhost:8081/AhlElKheir/`. Do not reopen without concrete regression evidence and do not retry voice.

**Next session:** read all four required continuation/master documents plus the relevant module document, inspect current `main` and actual source/schema, then select the next genuinely open task. Do not assume the next task is AI chat or revive a closed module without evidence.

## 2026-10-23 — AI assistant account-switch and workflow-answer follow-up

A second user reported a conversation restore error and an unanswered question about assigning families to nannies. Source review found the active-conversation `sessionStorage` key was shared across accounts; the server correctly rejected a conversation owned by another user, but the failed ID remained stored. Commit `ad6d2c7ede03b7e665ff11b74af55949818b5a76` namespaces the key by signed-in user ID and clears the active pointer after restore failure.

The VGM source `modules/deputy_gm/groups.php` confirms group-based assignment: VGM manages groups, adds eligible active children from active families with active sponsorship, and assigns a nanny to the group. The employee guide and role workflow were updated (commits `fb030a08ba65093b51cf31cba4979189457e87bf`, `9cd98ce9f1b86d521a34e2e8e55dcdc1dd982d40`); a deterministic local answer is limited to VGM/vice-GM roles (commit `4fe4a98dc3bcf40e5122c04f069b1f3a40cb99dc`). No schema changes.

PHP lint and browser acceptance remain pending. Verify account switching, each account's own saved history, the VGM answer, and non-VGM role restrictions before marking this regression runtime-verified. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.


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


## 2026-10-23 — Document archive continuation

The document archive catalogue migration is committed and its five tables/indexes/FKs were verified in the user's local `ahl_el_kheir` database. Current pilot: read-only inventory of `project_documents` and `project_payment_evidence` using `tools/report_project_document_inventory.php`. The report has not yet been run against the local filesystem. Continue from `docs/DOCUMENT_ARCHIVE_CONTINUATION.md`; do not move, delete, rewrite, or backfill files/records during inventory.

 
## 2026-10-23 — Document archive pilot continuation
 
The archive catalogue migration is applied and verified in the user's local `ahl_el_kheir` database. The read-only inventory ran locally: 16 references across `project_documents` and `project_payment_evidence`, with 3 missing/invalid references, 3 rows with issues, 0 shared paths, and 3 distinct-path duplicate-content candidates. The three missing payment receipt filenames were not found elsewhere under project storage; these are test artifacts and must not be recreated.
 
Current implementation: `tools/backfill_project_document_archive.php` is committed on `main`. Default invocation is dry-run; `--apply` registers catalogue metadata without moving/deleting files or changing source records. Next gate is local PHP lint, then dry-run JSON review. Do not apply until suspicious actions are reviewed. After apply, rerun dry-run to verify idempotence. Upload/replacement/deletion integration is not yet implemented.


## 2026-10-23 — Project archive integrated with Global Search; upload registration wired

The user requested that archive search be integrated into the existing role-based Global Search Center, not built as a separate page. Source changes on `main` now add the `documents` search type to `modules/search/index.php` and `config/search_permissions.php`. Search results are limited to active archive links whose current source path still matches the business row, whose blob status is present, and whose project passes `akp_can_view_project()`. Payment-receipt results additionally match the existing serving endpoint's role allowlist. Results open the existing authorized project-document/payment-receipt endpoints.

A procedural adapter, `modules/projects/lib_project_document_archive.php`, registers project documents/payment evidence, verifies file containment/MIME/size/SHA-256, creates immutable versions on replacement, records archive events, and can unlink a source link without deleting archived bytes. It requires the business source write and archive metadata write to share the same DB transaction.

Automatic registration was wired in `modules/projects/view.php` for general project-document uploads/replacements, project expense receipts, labor-payment receipts, and funding-return proof; deletion paths mark archive source links unlinked and preserve physical bytes. `modules/projects/view_fm.php` registers uploaded/replaced project payment evidence and no longer unlinks the previous receipt after replacement. The existing business authorization, validation, and audit flows remain in place.

**Verification boundary:** these are repository-source changes only. PHP lint and Apache/browser/database runtime verification have not yet been run after these edits. The local archive migration was previously verified applied. Existing records still need the reviewed one-time metadata backfill; the last local dry-run reported 16 references, 16 planned registrations, 3 missing file references, and no path/blob metadata conflicts. Do not claim the feature is runtime verified until the user runs the specified lint checks, applies the backfill, confirms idempotence, and tests authorized/unauthorized search and upload/replacement flows.


### Archive pilot follow-up — historical versions added to Global Search (2026-10-23)

A follow-up source change now returns each present version for active project archive links, labels the current version versus prior versions, and opens the selected version through `modules/projects/project_archive_version.php`. That endpoint resolves the source adapter, re-checks project authorization and the payment-receipt role allowlist, constrains storage to the project directory, and verifies stored byte size/SHA-256 before streaming. This supersedes the immediately previous note that historical versions were not yet separately searchable.

Runtime remains pending. Add `modules/projects/project_archive_version.php` to the lint list and test access to both current and previous versions, including a denied cross-project request.


### Archive follow-up — deleted source records remain searchable (2026-10-23; runtime pending)

Global Search now also includes present archived versions whose source link is explicitly unlinked and whose source business row was deleted. The project association is recovered from archive event metadata and the result is still filtered by `akp_can_view_project()`; payment receipt results continue to respect the receipt endpoint's role allowlist. The version endpoint uses the same retained project metadata to authorize retrieval. This supersedes the immediately previous note that unlinked source records were not listed in search.

 
### Archive pilot — local lint and idempotent backfill verified (2026-10-23)

The user pulled `45912eed` and confirmed all six changed PHP files pass `php -l`. Against local `ahl_el_kheir`, archive backfill registered 16 references (8 project documents + 8 payment evidence); the immediate dry run reported 16 already registered, 0 planned registrations, 0 path/blob metadata mismatches, 0 reconciliation cases, and 3 known missing physical payment-evidence files. Catalogue registration is verified; Global Search browser behavior, role boundaries, and version replacement/retrieval remain pending. See `docs/DOCUMENT_ARCHIVE_CONTINUATION.md`.


## 2026-10-23 — Archive pilot checkpoint — implementation/backfill verified; end-to-end acceptance still open

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


## 2026-10-23 — Sponsor QR + Android scanner (source implementation; verification pending)

The active user-requested task is sponsor QR codes plus a companion Android scanner APK. Continue from `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`. QR generation uses the existing bundled TCPDF 2D barcode library; QR payloads contain only the existing permission-checked sponsor-profile URL. The sponsor profile now has source changes for a QR card and active distinct sponsored-orphan count; orphan form/profile sponsorship rows now have QR source changes. A native Android WebView companion with ZXing scanning and a GitHub Actions debug-APK build workflow has been added.

**Do not mark complete yet.** PHP lint, GitHub Actions Android build, Apache/browser, physical-device scan, active-count reconciliation, and role/scope checks remain unverified. The prior project document-archive pilot remains separately pending its documented browser acceptance; do not reopen it unless asked.
## Sponsor QR + Android scanner — 2026-10-23

Active new feature checkpoint: `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`. Sponsor QR PHP integration and native Android scanner source are on `main`; neither local application acceptance nor Android APK/device testing is complete. Current Android build fix `777f9b180ce61dade8c96649a8138e7f4bb85880` excludes obsolete Kotlin JDK 7/8 split artifacts after CI confirmed duplicate classes against Kotlin stdlib 1.8.22. Wait for GitHub Actions to verify before treating the APK as built.


### Sponsor QR Android scanner — latest build checkpoint (2026-10-23)
The duplicate Kotlin class fix is followed by a Java compile failure because `MainActivity.java` references `BuildConfig.DEBUG` while the AGP 8.7.3 module had not enabled BuildConfig generation. Commit `ecdc34c19f1d5f5c370a95b3a902739693710b7f` explicitly enables `buildFeatures.buildConfig`. Verify the new Actions run before proceeding; see `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`.


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


## 2026-10-23 — Sponsor QR scanner UI and server-hosted APK download

The user confirmed a sponsor QR opens the sponsor profile when the website is accessed through the laptop LAN IP matching the APK's configured base URL. The localhost QR mismatch is resolved by using the same host/port/application path on both sides.

Latest source changes on main:
- Android screen redesigned with a clean Arabic brand header and a single prominent scan action; removed the permanent server-settings button.
- Server URL settings remain available by long-pressing the app title; this preserves local/LAN configuration without cluttering the main header.
- Added a download action that requests the APK from the existing system host, not a third-party host.
- Added authenticated endpoint modules/sponsors/download_scanner_apk.php; it serves only storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk. Direct storage access remains denied.
- CI now lints the download endpoint and runs for its source changes.

Verification boundary: GitHub Actions build/lint and physical-device UI/download behavior are pending. The APK must be copied to storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk on each target server after building; until then the endpoint intentionally returns 404 with an Arabic message. Do not claim the download is live before the file exists and authenticated download is tested.


## 2026-10-23 — Physical portrait correction and simplified scanner screen

The user installed the previously available APK and confirmed APK download/install works, but the camera scanner still opened horizontally. The latest Android workflow run before this fix, [40001005068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001005068), failed at manifest merging: ZXing's dependency declares `CaptureActivity` as `sensorLandscape`, conflicting with the app's `portrait` declaration. The manifest now declares the tools namespace and uses `tools:replace="android:screenOrientation"` on ZXing's capture activity to override that exact dependency attribute.

The Android home screen is simplified to the **مسح رمز QR للكفيل** button plus the WebView required for website sign-in and sponsor profiles. The top branding/download header and connection-status strip are removed. Long-press the scan button to open server settings; the website's existing authenticated header remains the APK download route.

Next acceptance gate: verify the new Actions run succeeds and produces a fresh APK; install that new artifact (not the previous APK), then confirm the camera remains portrait when the phone is rotated and that scanning still opens the authorized sponsor profile. CI success alone does not prove physical-device behavior.


## 2026-10-23 — Corrected portrait build produced; device acceptance pending

GitHub Actions run [40001446199](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001446199) completed successfully on commit `29eee35eb4fc3bf9869913abcd4395fea77a93e9`. The Android duplicate-class check, manifest processing, Java compilation, debug APK assembly, and artifact upload completed successfully after adding `tools:replace="android:screenOrientation"` for ZXing's capture activity. Artifact: [`ahl-el-kheir-sponsor-qr-scanner-debug-apk`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001446199/artifacts/12522116796), artifact ID `12522116796`, expires 2026-11-22. Artifact ZIP SHA-256: `2b4eb5b801662bb05c1791ba0d0c1a5081822506c73c05a554bd55713c26ad1a`.

This build also includes the minimal home screen: one visible `مسح رمز QR للكفيل` button plus the required website WebView; branding and connection-status strips are removed. Long-press the scan button to change server settings.

Next: install this newly built artifact, not the older APK, and verify the scanner stays portrait while the phone is rotated and still opens an authorized sponsor profile. CI success does not replace physical-device acceptance.


## 2026-10-23 — Sponsor QR scanner closure and orphan form QR placement

The user confirmed the newly built sponsor QR scanner APK was deployed to the fixed in-system download path and closed the scanner work item. See `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`; do not reopen without regression evidence.

Current task: on `modules/families/orphan_form.php`, position sponsor QR code(s) in the upper-left of the printed form header, horizontally aligned with the logo and `إستمارة يتيم` title. Sponsor table rows retain sponsor identity/code and dates only. Source change: `1e29a29b85b4bf4ac0da2cd7aff226019a71cff2`. GitHub Actions run [40003543066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40003543066) passed the `php-lint` job, including `modules/families/orphan_form.php`. Browser/print layout verification remains pending; do not call it runtime-verified yet.


### 2026-10-23 correction — restore original orphan form design

After user feedback, commit `efe2eaf9b6aeaa1d25b7f136f9b9958f74e519a4` restores the original orphan-form header-row CSS (removing the flex layout, 27mm minimum height, and horizontal padding introduced with the QR move). The QR stays at the upper-left using absolute positioning; original logo/title markup and remaining form design are preserved. Syntax and browser/print verification for this correction are pending.

### 2026-10-23 follow-up — full-width sponsorship rows

Source commit `d31c1fd6c56f25ecca15adb5833131b425264935` adds `colspan="3"` to the value cells for “مدة الكفالة” and “قيمة الكفالة”. Those rows share a table with four-column sponsor rows; spanning the remaining columns makes both row borders extend across the same full table width without changing their content or the QR header. PHP syntax and local browser/print appearance verification are still pending.


## 2026-10-24 — Flutter mobile app cloud-build continuation

Continue from `docs/MOBILE_APP_CONTINUATION.md`. The Flutter source now has a GitHub Actions Android debug-APK workflow, generated Android scaffolding in the CI runner, and an Arabic login widget smoke test. The workflow accepts an API v1 base URL for manual builds; emulator default is `10.0.2.2`, physical devices need a reachable LAN host, and hosted servers must use HTTPS. Check the actual Actions run and artifact before claiming build success. API migration/runtime/security verification remains pending; do not expose XAMPP publicly.


## 2026-10-24 — Flutter login widget-test correction

GitHub run 40060794073 showed Dart analysis passing but the login widget test failing while launching full-app startup/session restoration. The test now mounts LoginPage directly so it tests the Arabic RTL login form without coupling to platform secure-storage initialization. Commit: b3216456948e71a80ae2629961efc78352769edf. Verify the next workflow run and APK artifact before claiming success.


## 2026-10-24 — Flutter phone connection and branding follow-up
Historical note superseded: the actual local/phone-browser reachability checks confirmed Apache port `8081` and no listener on `881`. Current app/workflow defaults use `http://192.168.18.9:8081/AhlElKheir/api/v1`; commit `da04d0fea8e73f505fa521546e653ca4e263e0c0` also corrects startup migration so a saved 8081 URL is not rewritten to 881. Current theme is violet/amber with lavender surfaces, intentionally different from the website. Read `docs/MOBILE_APP_CONTINUATION.md` before further mobile work.


### 2026-10-24 — Flutter navy/logo build analyzer correction
Branding/API-host update commit `54a1c06d2855b0d58044adf7cb3bbf6d8860d366` failed `flutter analyze` because Flutter 3.47 flags `Color.withOpacity` as deprecated under strict analysis. Replaced with `withValues(alpha: ...)` in `6dbb17b7f4276eeeaad2e3f6c8c267d939ef35f6`. Check the follow-up Actions run before claiming APK availability.


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


### Mobile app continuation — 2026-10-24
The Flutter app is being built inside the existing repository against authenticated PHP API v1 routes. Read `docs/MOBILE_APP_CONTINUATION.md` before mobile changes. The transaction-register row overflow correction is committed as `885ef809b431b174541d54c8c44ade3f97421172`; its GitHub Actions run is pending verification. Do not request device testing until the planned app work and automated checks are broadly complete.


### Mobile app — sponsorship register added (2026-10-24)
The transaction register correction was fully green in GitHub Actions run `40091698003`, including APK artifact publication. A read-only sponsorship register/API has now been added using the existing web module's role and supervisor scope rules. Its own CI run is pending; see `docs/MOBILE_APP_CONTINUATION.md` for details.


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


## 2026-10-24 — Mobile app continuation

Sponsor-request attribution, status actions, and supervisor directory are source-complete with the CI evidence recorded in `docs/MOBILE_APP_CONTINUATION.md`. Issue #95 is closed. Continue the next role-safe mobile workflow from actual web source/schema and endpoint permissions. Live Apache/MariaDB role-matrix testing and physical-device acceptance remain deferred until the broader app reaches release readiness; do not repeat passed CI jobs or reopen issue #94 payroll work.


## 2026-10-24 — Mobile project progress updates (CI partially verified)

The mobile project progress-update feature is implemented on `main`. Source and CI boundaries are recorded in `docs/MOBILE_APP_CONTINUATION.md`; Flutter analysis/tests passed, Android APK build was pending, and PHP lint passed at the checkpoint. Live Apache/MariaDB role acceptance and device acceptance remain deferred.


### 2026-10-24 — Mobile milestone creation checkpoint
The mobile project detail flow now supports creating a project milestone through authenticated `POST /api/v1/projects/milestone_create.php`, using the existing milestone and project-status-history tables without schema changes. Server-side authorization is limited to admin or a project supervisor with an active primary/operations assignment; closed projects are rejected. Flutter form, API wiring, refresh behavior, widget coverage, and PHP lint workflow changes are committed on `main`. CI is pending; live role/runtime and device acceptance remain outstanding. Do not request phone testing yet.


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


## 2026-10-24 — Mobile nanny preliminary verification CI result correction

- The preliminary-verification Flutter test commit `23c81c2dd69c0bd47bdf21d39415dfb550e1685c` completed successfully in GitHub Actions run [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559): analyzer, widget tests, Android debug APK build, and artifact publication passed. The app-wiring commit `250f4eab0e5101e10c56c8335af9165c6bad0fa2` also passed Flutter CI in [40113993153](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113993153).
- PHP syntax job passed for the two preliminary-verification endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). The latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014) again reports `php-lint`, MariaDB 10.4 migration, and rescheduling-service tests as passed; the independent full-schema and HR job-description migration jobs were still running when checked. Do not treat the separate full-schema payroll failure from earlier runs as a mobile feature failure.
- This supersedes the earlier pending-status note above. No live Apache/MariaDB nanny-role runtime matrix has been executed, and physical-device acceptance remains deferred. CI success is not runtime acceptance.
- Next work must remain source/schema-led. Do not duplicate the existing nanny verification workflows; inspect the next existing web workflow and its role/scope rules before exposing it in the mobile API. Do not ask the user for phone testing until the agreed broader-app release-readiness gate.


## 2026-10-24 — Mobile nanny preliminary verification CI result

Flutter CI run [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) passed analysis, widget tests, Android debug APK build, and artifact publication for the preliminary-verification flow. PHP lint passed for both new endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). Latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014) had PHP lint, MariaDB 10.4 migration, and rescheduling tests passed; remaining jobs were still running at last check. This supersedes the older pending-CI note. Live API role-matrix and device acceptance remain pending.


## 2026-10-24 — Employee detail mobile work

The employee detail API, Flutter detail page, directory tap/navigation, and Arabic widget test have been added on `main`. Flutter CI run [40115161999](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115161999) was still in progress at last check. PHP lint workflow does not yet explicitly include the new detail endpoint; live API role testing is pending. A separate full-schema Stage 5 payroll integration job failed due to a missing payroll-transfer migration prerequisite and must be investigated independently.


## 2026-10-24 — Employee details build evidence

The employee details feature has passed an Android build/test run at commit `a78bc176` ([run 40115151758](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115151758)): analyzer clean, widget tests completed, debug APK built and uploaded. Later commits added a dedicated Arabic details test and localized English employment-state label; latest run [40115569057](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115569057) remained in progress at last check. Live API authorization/database verification and PHP lint coverage for the new endpoint remain outstanding. Full-schema Stage 5 payroll migration prerequisite failure is separate and must be investigated before any change.


## 2026-10-24 — Employment-state localization refinement

Refined the employee detail screen to show the actual localized employment-state name instead of collapsing every `working` category into a generic label; this preserves distinctions such as probation. Updated the Arabic widget test fixture to include both Arabic and English state names. Latest Android build/test run [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) was queued at the last check. Earlier commit `a78bc176` passed analyzer, Flutter tests, and debug APK build; the newest revision still needs its own green result. PHP endpoint lint/runtime role tests remain outstanding, and the full-schema payroll migration prerequisite failure is separate.

## 2026-10-24 — Mobile employee details CI confirmed

The latest Flutter Android workflow [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) completed successfully (`analyze-and-build` success) for commit `ed9e169ab8bf75be8592d6765181314679b1dd06`. The mobile README was corrected to reflect current integrated features and the distinction between feature coverage and release readiness. Outstanding: live API role/scope runtime checks, explicit PHP lint coverage for `api/v1/hr/employee_detail.php`, and an appropriate HTTPS PHP/MariaDB deployment target. Continue with the next uncovered existing web workflow only after inspecting source/schema; no phone test yet.

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

## 2026-10-24 — Mobile financial review queue (CI pending)
A read-only mobile financial review queue was added for the same role set as `modules/accounting/fm_review_queue.php`. The endpoint returns pending supervisor collections plus the latest 20 approved/returned reviews; it does not approve, return, post, or alter financial records. Flutter page, role-aware home navigation, widget test, and PHP lint-list entry are committed on `main`. CI and runtime authorization/data verification are pending. See `docs/MOBILE_APP_CONTINUATION.md`.

## 2026-10-24 — Payroll-transfer prerequisite aligned in rollback-only CI fixtures (CI pending)
The full-schema CI job exposed that the existing Stage 5 payroll integration harness still attempted a production `paid` transition without the now-required payroll-transfer migration and prepared proof. Production correctly rejected that invalid fixture. Updated the disposable full-schema workflow to apply `2026-10-20_hr_fm_payroll_transfer_workflow.sql`, and updated the rollback-only Stage 5 payroll and payroll/rescheduling concurrency fixtures to create private proof bytes plus prepared proof metadata and a valid payment account. Fixtures clean up proof bytes; the concurrency harness removes proof metadata before deleting its payroll row. Await new CI results; this is a harness/environment alignment, not a production-rule relaxation.

## 2026-10-24 — Follow-up CI fixture audit actor correction (CI pending)
After the migration/proof fixture correction, the next full-schema run reached production payroll posting but failed its proof confirmation foreign key because the CLI harness had no authenticated `Session` user and `Session::getUserId()` returned `0`. The production code correctly rejected that invalid audit actor. Both rollback-only harnesses now seed `$_SESSION['user_id']` from an existing test-database user before exercising the paid transition. Await fresh CI.

## 2026-10-24 — Mobile financial review queue CI passed; payroll CI fixture correction verified
- Flutter workflow [40117632176](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117632176) passed analyzer, widget tests, Android debug APK build, and artifact publication for commit `60883414`.
- PHP/database workflow [40118139681](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118139681) passed all five jobs: PHP lint, MariaDB 10.4 migration, HR job-description migrations, rescheduling-service tests, and full-schema migration/payroll regressions.
- The full-schema log explicitly passed Stage 5 rollback-only payroll cases, account 1410 reconciliation, production payroll/rescheduling lock contention and retry, and rollback cleanup. The CI fixture now matches the production transfer-proof prerequisite; no production payment guard was weakened.
- Live local Apache/MariaDB API authorization/record-scope tests remain outstanding. The app is not yet accepted as production-ready.

## 2026-10-24 — Mobile financial review queue actions (PHP CI passed; Flutter CI pending)
The mobile financial review queue now supports approve-and-post and return-for-correction actions through authenticated POST api/v1/accounting/fm_review_queue_action.php. The server enforces the same financial-review role allowlist as the web queue. Approval delegates to the existing ak_approve_sponsor_payment() transaction-boundary operation; return requires a non-empty reason, locks the pending row, updates status/reviewer/time, and records audit/notification side effects with the existing failure-isolation pattern. No schema change. PHP lint and the full-schema workflow passed in run 40118666512 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512). Android workflow 40118666504 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) was still in progress at this checkpoint. Live Apache/MariaDB role/action verification remains outstanding; do not treat CI as runtime acceptance.

## 2026-10-24 — Mobile financial review actions and sponsorship role parity verified by CI
- Added authenticated `POST /api/v1/accounting/fm_review_queue_action.php` for the existing financial-review roles. Approval delegates to the existing atomic `ak_approve_sponsor_payment()` transaction; return requires a non-empty reason, locks the pending payment, records return/audit metadata, and sends notifications after commit. No DDL and no alternate accounting path.
- Added approve/return controls to the Flutter financial review queue and widget coverage for mandatory return reason/action wiring.
- Corrected General Manager sponsorship read access to match the existing web role boundary in both sponsorship API endpoints and mobile navigation.
- CI passed for code commit `734df683`: Android workflow [40118666504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) (analyzer, widget tests, debug APK and artifact), PHP/database workflow [40118666512](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512) (all five jobs). All 31 API endpoint PHP files are included in the lint list.
- Live local Apache/MariaDB action authorization, role boundaries, and real sponsor-payment data acceptance remain outstanding. Do not treat CI as live runtime acceptance.

## 2026-10-24 — Project API operational-scope parity correction (CI passed)
Source audit found `api/v1/projects/detail.php` defaulted missing approval rows to `approved`, unlike `akp_can_view_project()` and the portfolio API, which default to `draft`. It also found `milestone_create.php` checked assignment and closed status but omitted the operational approval/lifecycle gate enforced by the web project view and progress API. Corrected the detail fallback to `draft`, added the approved + allowed lifecycle gate for non-admin milestone creation, and aligned the detail response's `can_add_milestone` flag with that gate. PHP/database workflow [40119297504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40119297504) passed all five jobs, including PHP lint. Live role/record-scope runtime tests remain open.

## 2026-10-24 — Mobile project milestone maintenance checkpoint

Milestone edit/delete API and Flutter controls have been committed to main. CI run 40120100653 was still in progress at this checkpoint. Source/CI must not be conflated with live Apache/MariaDB authorization acceptance. See docs/MOBILE_APP_CONTINUATION.md.

## 2026-10-24 — Mobile milestone maintenance authorization parity

Added milestone edit/delete endpoint and Flutter controls; separated maintenance permission from create permission to match web behavior. On source SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`, Dart analysis, all Flutter tests, debug APK build, and artifact publication passed in [40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219). PHP/database workflow [40121128872](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40121128872) passed all jobs on SHA `2a664a66316319983dc32d48cdfa9024b770a71a`. No live local API runtime acceptance yet. See `docs/MOBILE_APP_CONTINUATION.md`.


## 2026-10-24 — Mobile auth migration CI checkpoint
The PHP/database workflow now lints `api/v1/_bootstrap.php` and applies `database/migrations/2026-10-24_mobile_api_sessions.sql` twice against the disposable full-schema MariaDB CI database. This is CI-only migration compatibility/idempotence coverage, not evidence that the local `ahl_el_kheir` database has the migration applied or that the API passes live runtime acceptance. Workflow change: `4fd136bb6646b960d8ea46548eab8757c65872d7`; docs checkpoint: `8ff1d7825ca8c9d29822811a23dc3f97269882fd`. Await the new CI run before claiming pass. Continue mobile release-readiness checks without repeating closed payroll/HR suites or requesting phone testing yet.


## 2026-10-24 — Mobile auth migration CI gate passed
The PHP/database workflow passed after the mobile auth migration coverage change: [run 40122798655](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40122798655), source SHA `09a94183a9b2cb1e23feefeaf8733b02d97537c3`. All five jobs passed, including `php-lint` and `migration-full-schema`; the latter imported the checked-in schema, applied `2026-10-24_mobile_api_sessions.sql` twice to the disposable MariaDB 10.4.32 database, and completed the existing full-schema regressions. This proves the CI migration step succeeds in that disposable environment, not that the migration is applied locally or that API auth passes live runtime tests. Latest Android build remains successful at [run 40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219) on Flutter SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`; no Flutter source changed after that build. Remaining gates: local migration-state confirmation, controlled local Apache/MariaDB API auth/session and authorization tests, and HTTPS PHP/MariaDB hosting. Do not request phone testing yet.

## 2026-10-25 — Mobile continuation checkpoint

The Flutter API fallback was inconsistent with the verified local host evidence: `main.dart` used port `881`, but the workflow default and prior local/phone reachability checks use `8081`, and the prior check found no listener on `881`. The fallback was corrected to `http://192.168.18.9:8081/AhlElKheir/api/v1` in commit `41cd2e0176ee4bf2254dfc92fadde6b8c3ba6665`. Android CI verification is pending. Continue the mobile API source/schema-led audit and controlled acceptance preparation; do not repeat closed tests or claim live authorization verification from CI.


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


### 2026-10-25 — Mobile project milestone ID defect
Source audit found the project detail API selected milestone IDs but omitted them from its JSON response, preventing the Flutter milestone maintenance actions from using the required identifier. Added the ID to the response in `20631106ae8fe9f8267779faf4c6e9a1d7608057`. Focused endpoint verification passed in disposable-database run `40160569643`; ended-assignment and cross-project no-mutation assertions passed in run `40160867381`. Continue the mobile API role/record-scope audit. Live local Apache/MariaDB acceptance remains outstanding.


### 2026-10-25 — Mobile milestone response verification
The milestone ID response correction has focused CI endpoint coverage. The assignment-revocation milestone mutation assertion and the complete mobile auth integration harness passed in run 40160569643 on commit eebe53983eed73e9899ca9cf5d85754497e3e092. CI used disposable MariaDB only. Continue the source/schema-led mobile API authorization and record-scope audit; local XAMPP migration state and runtime acceptance remain open. Never run the harness against ahl_el_kheir.


### 2026-10-25 — Cross-project milestone isolation verified in CI
Commit `bc67997093d15fd616ac95ac4ead566840004883` adds a second-project/foreign-milestone fixture to the disposable mobile API integration harness. Attempting to edit the foreign milestone using the assigned project ID must return 404 `milestone_not_found` and leave the stored title unchanged. [Run 40160867381](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160867381) completed successfully. This adds record-isolation evidence but does not close the broad authorization audit or prove local XAMPP runtime/migration state. Do not run the harness against `ahl_el_kheir`.


### 2026-10-25 — Mobile notification cross-account isolation verified
Commit `ab2a6173e596f88ed4e7c2fb6e962e40e5429192` adds disposable-database HTTP assertions that a notification addressed to a second user is absent from the authenticated user's list and remains unread after that user calls the API mark-read endpoint. [Mobile API auth integration run 40161671591](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161671591) passed, including schema import, session migration, and the live endpoint harness. Cleanup deletes only the inserted notification. Local XAMPP migration state and broader live local API acceptance remain outstanding; never run the harness against `ahl_el_kheir`.


### 2026-10-25 — Mobile sponsor-request authorization verified in CI
Commit `f0dfa70a376771cbceeae04e73b57f2e6e40cb89` adds disposable HTTP integration checks proving a nanny cannot list sponsor requests or change a request's status, while an admin can transition `new` to `contacted`; repeating that transition returns 409 and leaves state unchanged. [Mobile API auth integration run 40161801824](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161801824) passed. Fixture cleanup removes the exact temporary request. Local XAMPP migration state and broader local API acceptance remain open; never run the harness against `ahl_el_kheir`.


### 2026-10-25 — Mobile nanny family record-scope checks verified
Commit `5b1b95cbdda200887dd03c63e2a7bf09665a746c` adds disposable fixtures proving a nanny can view a directly assigned family, cannot view a family assigned to another user, and sees only the assigned fixture in the family list. The first CI attempt exposed the test client's five-second timeout against the checked-in schema's 2,208 family rows; the family-list request now uses a 20-second timeout only for that request. The updated suite passed in [run 40162023286](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162023286), commit `9312a7118ebe7876986dcecfda0bdcd0e3e594d9`. This does not establish local XAMPP runtime acceptance. Never run the harness against `ahl_el_kheir`.


### 2026-10-25 — Mobile supervisor sponsor-responsibility scope verified
Commit `de9af9c85a56f5f5f593ec404dda7b26753bfdb8` adds a sponsor fixture whose `supervisor_id` points to the authenticated user but whose first-letter/gender is outside that user's responsibility matrix. Live API tests confirm direct detail access is denied (403) and the sponsor is excluded from the supervisor's list. [Run 40162198710](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162198710) passed against disposable MariaDB. No production code/schema changed; local XAMPP acceptance remains open. Never run the harness against `ahl_el_kheir`.


### 2026-10-25 — Mobile milestone lifecycle authorization finding
Source review found `api/v1/projects/milestone_action.php` enforced assignment and blocked closed projects but did not enforce the same approval/operational lifecycle boundary already used by `milestone_create.php`. Added the matching non-admin guard in commit `bc6f6ee0104326ee536fdaa732f1e693de739eb8`. Added a disposable-CI integration assertion that a Project Supervisor cannot edit a milestone on a draft/unapproved project and that the stored title remains unchanged in commit `c6cc08b3b646c1906392019e116a646ab038a5df`. GitHub Actions run 40162518220 completed with conclusion `success` on test commit `c6cc08b3b646c1906392019e116a646ab038a5df`; the disposable schema import/migration and live HTTP integration harness job passed. This verifies the draft-project denial/no-mutation regression in CI, not local XAMPP runtime. Local XAMPP/MariaDB acceptance remains outstanding; never run the mobile integration harness against `ahl_el_kheir`.


### 2026-10-25 — Mobile HR leave approval integration verified in CI
Extended `tools/test_mobile_api_auth_integration.php` with disposable-database live HTTP checks: a nanny's leave-approval attempt is denied (403) and leaves the request pending; HR staff approval records `hr_approved` and the approver, creates the expected `on_leave` attendance row, and a duplicate approval is rejected (409) without changing the approved state. Cleanup removes the exact leave, attendance and linked notification fixtures. Latest harness commit: `fd7d9652c3d9cc1bc6b6b17900742ef0f7d1d30b`. [GitHub Actions run 40162902436](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162902436) completed successfully against the checked-in schema and disposable MariaDB. Source inspection confirmed the approval update already uses a pending-state predicate, checks affected rows, and rolls back on conflict; no production endpoint change was justified. This is CI evidence only. Local XAMPP migration-state and live API acceptance remain open. Never run this harness against `ahl_el_kheir`; it requires disposable database `mobile_api_auth_test` and `MOBILE_API_AUTH_TEST=1`.


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


## 2026-10-25 — Flutter app menu navigation fix committed; device verification pending

Commit 49ef28ad185f72c4d484909d29a200797d66e718 fixes the confirmed app-level navigation-context defect in mobile/ahl_el_kheir_app/lib/main.dart. Menu callbacks previously called Navigator.of(context) using the state build context above MaterialApp; the new state-owned GlobalKey<NavigatorState> is attached to MaterialApp and used for menu route pushes. User confirmed card taps show a ripple while no route/API request appears, and the language toggle works. Source re-fetch verified 16 menu navigation call sites use the key and no context-based navigation remains in the home/menu section. Build and phone runtime acceptance remain pending; do not claim the APK fix is verified until Projects navigation and Apache request logging are confirmed. Details: docs/MOBILE_APP_CONTINUATION.md.


## 2026-10-25 — Mobile home-screen design and personal-link grouping

The user performed a quick device test and confirmed login works with multiple user accounts and the menu links now open. This is preliminary acceptance only; the user has not completed detailed testing.

Source change: `296b318ae173e2be9383e2308266ec7235cc7cbc` updates `mobile/ahl_el_kheir_app/lib/main.dart` to bring the home screen closer to the existing web system's established navy/blue, Cairo, and light-neutral design tokens. The welcome panel now uses the existing navy gradient, menu cards share rounded bordered surfaces with reduced elevation, and the flat menu is divided into **Work areas** and **My account** sections. Salary statements, leave requests, and notifications are grouped under the personal/account section instead of being mixed into the operational modules.

The web stylesheet's root palette already matches the app's existing color values; the main mismatch identified was the mobile screen's generic flat composition and missing hierarchy, not evidence for inventing a new brand palette. Existing role visibility conditions, routes, API calls, and server authorization were deliberately left unchanged in this visual pass. The salary-statement API returns an empty, explicit unlinked-profile state for accounts without an employee profile; the leave API similarly scopes personal requests and restricts review queues server-side.

**Verification status:** source update committed to `main`; Flutter CI and device retest are pending. Do not call this redesign runtime-verified until the new APK build succeeds and the user checks the home screen with multiple role accounts. User's current feedback is quick-test evidence, not a full acceptance run.


## 2026-10-25 — Mobile visual redesign follow-up: compact app-bar actions

User feedback after the first design pass: only the login page/header felt on-brand, the rest of the screen remained too generic, notifications should be a bell icon at the top without explanatory copy, and personal links should be represented by an account icon at the top. The user prefers a broader redesign now that core app navigation is in place.

Source change: `f627d130fe36b4bfe4232891aff521f63af1b518` updates `mobile/ahl_el_kheir_app/lib/main.dart`:
- The signed-in app bar now shows the organization logo/name and icon-only notification bell, account, and language controls; the icons have accessibility tooltips, but no explanatory text beside them.
- Notification history is opened from the bell. Salary statements and leave requests were removed from the main work-area list and are now in the account bottom sheet alongside the signed-in user's name/role and sign-out action.
- Shared Flutter theme styling was strengthened for the brand color scheme, dividers, list tile typography/spacing, and icon-button surfaces, in addition to the existing branded scaffold, app-bar, card, and form themes. This theme is shared by the app's routed pages; individual screens may still need focused visual refinement after a device review.
- Existing routes, API payloads, role visibility conditions, and server-side authorization are unchanged.

**Verification status:** source committed to `main`; Flutter CI and device verification pending. The user has not yet accepted the new design and has not completed detailed feature testing. Do not describe the broader redesign as complete or runtime-verified until the new APK passes CI and the user reviews it on device.

### Mobile app checkpoint — 2026-10-25 (dashboard/login/navigation follow-up)

The user selected a modern professional dashboard using the existing navy/blue branding and requested an eye icon for password visibility plus a forgot-password link. The current mobile changes are recorded in `docs/MOBILE_APP_CONTINUATION.md`. Source commits include the responsive two-column module dashboard, compact header without the visible role pill, password visibility and existing website recovery link, account sheet navigator-context correction, notification detail view with related-link action, and browser access to the existing orphan form. Latest source checkpoint: `ca16265515612bfa18442dcd59a987baaa303e0e`. Flutter CI and phone verification are pending; do not treat this as accepted until verified.
### CI correction follow-up — 2026-10-25

The first Flutter analyzer run for this work exposed concrete integration errors: a duplicate notification-tile `onTap` and required callbacks missing from the existing widget tests after constructor changes. The notification tap now uses one handler, mark-read failure stops navigation (preserving the prior error behavior), and tests provide explicit no-op callbacks where external navigation is not under test.

Correction commits: `e622c2c98aa4dea9462da5d0a632fe795cca5b56` (notification handler) and test callback updates through `c30c10b8e1a6df3f9ac2e197dc4363748419df22`. The new Flutter workflow runs for these commits are still pending/in progress at documentation time. Do not claim analysis, tests, or APK build passed until the latest run confirms it.
### Final notification handler correction — 2026-10-25

The next analyzer pass isolated one remaining Dart error: the notification mark-read helper returned without a boolean when the widget was unmounted during an error. Corrected to return `false` explicitly so the detail route is not opened after an unsuccessful mark-read operation. Source commit: `b572e49403805f3057e4830c211002c0d4fbd0e6`. The matching Flutter workflow run `40183286267` is queued; analysis/tests/APK status remain unverified.

## 2026-10-25 — Mobile UI feedback checkpoint

The user rejected the recent two-column mobile dashboard layout and asked to restore the prior single-column full-width operational-card arrangement. Sign-out must be directly in the top app-bar icon row, not hidden inside the account menu. Notification detail content must remain in-app; external browser navigation from a notification detail has been removed, with a clear in-app note when the related website destination is not available natively. Source changes and a regression widget test are committed on `main`; latest CI and APK artifact verification are pending. See `docs/MOBILE_APP_CONTINUATION.md`.


## 2026-10-25 — Mobile header two-row correction (CI pending)

Committed on `main`: `ee917da2ea64e6e817422850c80e9393ccac78ba`.

Source change: `mobile/ahl_el_kheir_app/lib/main.dart` now separates the signed-in header into two logical rows: the existing organization logo/name on the first row, and notification, account, language, and sign-out icons on the second. The full-width single-column dashboard remains unchanged. Notification details continue to use the native in-app screen; this change does not add browser navigation from notifications. The unnecessary “Tools available to your account” / Arabic subtitle below the work-area heading has been removed without changing role visibility or API authorization.

Branding evidence: the web theme in `assets/css/style.css` defines the navy palette `#081527`, `#0A1F44`, `#0E2A5C`, `#123A7A`, `#1F4E8C`, `#2E63A8`, light background `#F0F2F5`, border `#E3E8EF`, and Cairo typography. The mobile app uses the checked-in `assets/organization_logo.jpg` and Cairo theme. No replacement brand asset or new color palette was introduced.

Verification status at commit time: source committed, but Flutter formatter/analyzer/widget tests, Android APK workflow, and physical-device verification are pending. Do not claim the two-row header is runtime-verified until the workflow passes and the APK is tested on a phone. The feature-parity matrix and date-range investigation remain open; the transaction API currently validates ISO dates and applies inclusive `transaction_date >= from` / `transaction_date <= to` filters, while Flutter rejects reversed selected ranges and resets pagination on date selection. These are source observations, not yet proof that all range cases pass end-to-end.


## 2026-10-25 — Login branding correction and major parity gaps confirmed

Source commit: `c0d73ca0342c460c67faab2e9b7c037ef44ae1a2`.

The mobile login no longer shows a separate subtitle under the organization name; Arabic now displays the requested full name `منظمة أهل الخير النسوية لكفالة الأيتام`, with a full English equivalent in English mode. The `AHL EL KHEIR` English footer was removed. Existing authentication, password visibility, recovery link, logo asset, and theme remain in place. Flutter formatting/analyzer/tests and APK workflow for this commit are pending; do not claim verified yet.

User rejected the current mobile design as inferior to the installed web app/PWA and emphasized that the dashboard must reflect each real role's distinct tools, workflows, and visual structure. Treat this as a broader product-design and parity correction, not a request for isolated cosmetic patches. Preserve the accepted full-width single-column module-card arrangement unless the source/PWA evidence justifies a better role-specific design; do not restore the rejected generic/two-column design.

Confirmed functional gap: `includes/age_alert.php` is a real core web feature. It selects active children approaching or past age 18 within the 90-day alert window (with a lower age bound of 21 years), and displays child/family/birth date plus links to family details and child sponsorship suspension. Web role scope is nanny (assigned families only), admin/sudo, vice general manager, and general manager. The Flutter app has no equivalent age-alert endpoint, model, home alert, native modal, or suspension action in its current `main.dart` feature inventory. Do not fake this with local-only filtering: inspect the real SQL/workflow and server-side permissions, then add an authenticated API response and secure action endpoint only if needed, preserving existing business logic and the web feature's exact role scope. A mobile native alert must keep child/family details in-app; no external browser links for notification detail navigation.

Confirmed dashboard parity issue: web roles route to distinct pages in `config/functions.php::dashboard_for_role()`, including `dashboard/admin_dashboard.php` for sudo/admin/super_admin, `dashboard/gm_dashboard.php`, `dashboard/vgm_dashboard.php`, `modules/accounting/fm_dashboard.php`, `dashboard/accountant_staff_dashboard.php`, `dashboard/supervisor_dashboard.php`, `dashboard/nanny_dashboard.php`, `dashboard/staff_dashboard.php`, `dashboard/hr_dashboard.php`, and `dashboard/projects_dashboard.php`. The Flutter home currently constructs a mostly shared list of module cards with role-code conditions; this does not replicate the web dashboards or all their actions. In particular, the web admin dashboard has dedicated administration tools, stats/attention links, and audit access, while the Flutter sudo view is not an equivalent operational dashboard. Build a documented role-by-role feature matrix from these actual pages, navigation, permissions, and API endpoints before expanding screens. The existing PWA is the visual/interaction reference and should be inspected against its real assets, CSS, manifest, and workflows; do not infer that manifest metadata alone accounts for its richer design.

Next priority: comprehensive role-aware dashboard and design pass, including the age-reach alert. Compare the existing web/PWA for every real role, map each available action to a native Flutter screen/API endpoint, then implement in justified increments with backend authorization, regression tests, documentation, and CI. Date-range investigation remains open and must not be marked fixed. Physical-device acceptance remains pending.


### Mobile app — 2026-10-25 coordinated redesign milestone

Main commits: `1c51846a9c348f5fb09fd5fb9d551f10edf7cd62`, `9ddf86df2a145f18a553ab452e7577723408c71d`, `0253a88d3a6201c9a279b879dd2e5fa0fcfe1a46`, `17af21974b9decf913954f4b7d96127ac34704b1`. The mobile shell now has role-specific hero presentation and an authenticated native legal-age alert list using the web alert's date/active-child rule and nanny scope. CI verification was still in progress at the time of this note. This is a partial milestone, not mobile parity closure. Continue with actual CI results, secure native action workflows, role-dashboard feature matrix, and the open transaction date-range acceptance checks.


### 2026-10-25 — Dedicated admin dashboard source implementation

Added a dedicated administrator/system-developer mobile dashboard and authenticated overview endpoint, replacing the shared dashboard for `admin`, `sudo`, and `super_admin`. It surfaces the web admin's control groups and adds native operational routes where mobile screens already exist. Some system tools still open their existing website pages externally and are explicitly documented as non-native gaps. Feature matrix: `docs/MOBILE_ROLE_FEATURE_PARITY.md`. Automated verification remains pending.


## 2026-10-25 — Role-aware mobile tools and employee self-service

Source commits on `main`:
- `8f659a0cec3ae9d565dd8bba98297da2f94b7639`: expose native leave requests and salary statements from the mobile workspace.
- `842e4382bcf922f9c67b82fcc414abdd7e72fba8` and `9e3044d842c1893bf685ef2bb87ae253a7d2c150`: add a role-specific quick-tools section derived from each existing web dashboard. Website-only links are labelled WEB and remain subject to website server-side authorization; this is navigation coverage, not native workflow parity.
- Historical correction: those commits changed the endpoint to 881 based on incorrect notes. Actual reachability evidence confirms 8081 and no listener on 881. Commit `da04d0fea8e73f505fa521546e653ca4e263e0c0` is the current source correction.

The workspace now exposes the existing native personal leave-request page and finalized salary-statement page, and adds role-scoped quick links matching the real GM, VGM, FM, Accountant, Supervisor, Nanny, Administration/Staff, HR, and Projects dashboard routes. The API enforces leave-queue permissions and returns salary statements only for paid payrolls posted to accounting. No account without a linked employee profile is given another employee's records.

**Still open; do not claim full completion:** role-specific dashboard metrics and operational workflows are not all native yet; website-only tools require a valid website session in the external browser; native legal-age suspension source is now implemented; automated and local runtime verification remain pending; transaction date-range end-to-end acceptance and physical-device runtime remain pending; the mobile API migration must be applied to the intended local development database before API runtime testing. The newest source checks have now passed; see the verified CI milestone below.


## 2026-10-25 — Native legal-age suspension action added

Source commits on `main` add `POST api/v1/age_alerts/suspend_child.php`, wire a confirmation action into the native legal-age alert sheet, and include the endpoint in PHP syntax CI. The endpoint mirrors the existing web child-suspension business operation: it permits admin/sudo/super_admin, VGM aliases, and nanny; rechecks that the child is active and within the web alert's 90-day-to-age-18 / age-21 lower window; enforces nanny family assignment; locks the child row; rejects an existing active suspension; inserts a `suspension_cases` record with reason `age_out`; marks the child inactive/disqualified; pauses active linked sponsorships; and records the existing audit-log event. The General Manager can see alerts but cannot initiate this action, matching the web page's hidden create form for GM.

The API action adds no runtime DDL or schema change. The redundant runtime `CREATE TABLE` block was removed from `modules/families/suspensions.php` because `suspension_cases` and its referenced columns are already present in `database/ahl_el_kheir.sql`; PHP syntax CI lints both suspension entry points, and the latest PHP workflow passed. Verification boundary: the endpoint/service passed PHP lint and a rollback-only integration test against the checked-in full schema on MariaDB 10.4.32; Flutter analyze, widget tests, APK build, and artifact publication also passed. Real local XAMPP/API and physical-device acceptance are still required. The CI integration harness verifies an eligible child, active linked sponsorship pausing, duplicate rejection, nanny scope rejection, GM denial, and rollback restoration against a disposable full-schema fixture. Do not test this on a real beneficiary record.


## 2026-10-25 — Mobile API role-alias authorization correction

The API bootstrap now normalizes only the role aliases explicitly routed to the same canonical dashboard by `config/functions.php::dashboard_for_role()`: `sudo`/`super_admin` → `admin`, `gm` → `general_manager`, `vgm` → `vice_general_manager`, and `fm`/`finance` → `financial_manager`. API authorization checks therefore evaluate the same canonical role as the web router, while `auth/me.php` returns the stored role code so the app retains the user's actual role identity. The `staff` alias is intentionally not normalized globally because its dashboard exposes a different subset of Administration tools.

This corrects concrete native-screen/API mismatches where the UI offered role-approved tools but endpoints rejected the equivalent alias account (including sponsor/family/sponsorship lists, supervisor management, HR screens, project portfolio, financial review, and transactions). A PHP regression test covers 14 canonical/alias cases and is run by `.github/workflows/php-syntax-check.yml`. Automated CI passed: the PHP workflow ran `PASS | mobile API role normalization | 14 cases`, and the Flutter APK workflow passed analyze, all widget tests, APK build, and artifact publication. Local XAMPP/mobile runtime acceptance is still required.


## 2026-10-25 — Verified CI milestone

- Flutter workflow run `40192383388` for source `c680777307d55513f01aff5dbe5536be497b2611`: **success**. Dart analysis, Flutter tests, debug APK build, and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk` (SHA-256 `9d407beda8a74d81c76655da2e6b74dcde015dba73ff237d5de9d0a9e41b782b`).
- PHP workflow run `40192899764` for source `5362b5dd250971db65b68f690560be208f16f43f`: **success**. Selected PHP lint passed, full-schema/migration jobs passed, and the role-alias regression reported `PASS | mobile API role normalization | 14 cases`.
- These CI runs verify source/build and disposable-schema checks only. They do not replace runtime verification against the user's local `ahl_el_kheir` database or physical Android device.


## 2026-10-25 — Suspension service integration and latest APK verified

- Flutter workflow `40193299239`, source `9e12cc7f491ed4e76649bdaf6f18a33d46f8e601`: **success** — Dart analysis, all Flutter widget tests, debug APK build, and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, SHA-256 `8ce5a5a2823be3a4be102a17894ee01032e8605bcc6333af4969b19f1e3384df`.
- PHP workflow `40193517552`, source `abc98c0a7e46dee67ce64daa87e59950812c397e`: **success** — selected PHP lint, full-schema migration suite, MariaDB 10.4.32 checks, and the rollback-only suspension service integration test passed. Exact service result: `PASS | mobile legal-age suspension service | success, linked sponsorship pause, duplicate guard, role/scope guards, rollback`.
- Role-alias regression: `PASS | mobile API role normalization | 14 cases` in PHP workflow `40192899764`.

Remaining acceptance boundary: these CI checks use disposable databases and cannot verify the user's running XAMPP server, actual local `ahl_el_kheir` schema alignment, the phone's network route, or a live authenticated session. The mobile API session migration must be applied to the intended local database before first use if it has not already been applied. Website-only tools remain labelled WEB and need a valid website session; they are not native parity claims.

## 2026-10-25 — Mobile role dashboard continuation

Latest mobile work adds `api/v1/dashboard/overview.php` plus native role-specific metric cards for GM, VGM, FM, accountant roles, supervisor, nanny, administration/staff/social media, HR, and project roles. Admin/sudo/super_admin keep the separate native system-control dashboard. Route-tree audit was performed; the Supervisor-only Fina collection creation link was removed because the real page denies that role. Source commits: API `f6ac0b4`, Flutter integration `7c96953`, PHP CI lint update `4fe6df0`; widget and role-permission tests were added.

**Current gate:** verify the latest combined GitHub Actions runs and their APK artifact, then perform authenticated API/database acceptance on local XAMPP. Do not report dashboard query runtime success from PHP lint or APK build alone. Transaction date filtering is implemented in the mobile UI and API, but full boundary, empty-result, reversed-date and pagination/refresh acceptance remains open. Website-only links are navigation only, not native parity.

## 2026-10-25 — Empty transaction results can now refresh

The transaction list's empty-result branch previously used a plain `ListView` without `AlwaysScrollableScrollPhysics`, while it is wrapped in a `RefreshIndicator`. When date/status/search filters returned no records, the short empty list could not reliably be pulled to refresh. The empty branch now uses `AlwaysScrollableScrollPhysics`, matching the populated list's refresh behavior.

Added widget coverage for multi-day inclusive date bounds persisting through pagination and refresh, and for rejecting a reversed range before making another request. The existing same-day range test remains. Source commits: `fa96f3a8451b86fc47b037d431bcc1bc23ec8a5e` and `64da0db9eef605a8a1442e9c623e1fa3a85f80e7`.

The mobile API already validates `YYYY-MM-DD`, rejects invalid calendar dates and reversed ranges, and applies inclusive SQL bounds (`transaction_date >= from`, `transaction_date <= to`). These source-level checks and widget tests do not replace a real authenticated API/database test with boundary records. Latest Flutter CI for this exact test revision is pending.

## 2026-10-25 — Role dashboard SQL now has a full-schema CI gate

The role-specific dashboard SQL was extracted to `api/v1/dashboard/overview_service.php` so it can be tested without bypassing endpoint authentication. `api/v1/dashboard/overview.php` now authenticates the caller, enforces the administrator endpoint split, invokes the service, and maps unavailable-role/database errors to bounded JSON responses.

Added `tools/test_mobile_role_dashboard_queries.php`. In the existing disposable MariaDB 10.4.32 full-schema CI job, it executes every role's metric query against the imported schema and validates the metric response structure without changing data. Both the endpoint/service and the harness are included in PHP lint. Commits: `fcc0c6f6e7554d46645319fb29b2c3cb92352e4d`, `56de647da473dec369102f2e619c98585308afc5`, `e186b3d30e610011ef6135ceab6dfe416ccbd4cd`, `7e6dc63be960efe612191a9b2e109dacd4f580a8`.

**Verification pending:** the new full-schema query gate must pass before the metric SQL can be described as schema-verified. This CI test uses the checked-in schema and a disposable database; it does not replace local `ahl_el_kheir` runtime checks.

## 2026-10-25 — Role dashboard full-schema query gate PASS

GitHub Actions run `40195397302` on source `7e6dc63be960efe612191a9b2e109dacd4f580a8` completed successfully. The PHP lint job passed, and the disposable MariaDB 10.4.32 full-schema job imported `database/ahl_el_kheir.sql`, applied the existing test migrations, then executed every native role-dashboard metric query successfully:
`PASS | mobile role dashboard SQL | 14 roles`.

Individual query counts passed for General Manager (10), VGM (7), FM (5), Accountant (5), Accountant Staff (5), Supervisor (6), Nanny (6), Administration (3), Staff (2), Social Media (2), HR Manager (5), HR Staff (5), Projects Manager (4), and Project Supervisor (4).

This proves the new role metric queries execute against the checked-in full schema on disposable MariaDB; it does not prove live values or permissions against the local `ahl_el_kheir` database. Physical-device and local API runtime checks remain open.

## 2026-10-25 — Flutter role dashboard and filter regression CI PASS

Flutter workflow run `40195228074` for source `64da0db9eef605a8a1442e9c623e1fa3a85f80e7` completed successfully. Dart analysis, the full Flutter widget test suite, debug APK build, and artifact publication all passed. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,875,054 bytes), available from [GitHub Actions run 40195228074](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40195228074).

The passing widget suite includes role-specific dashboard metrics/error recovery, HR Manager versus HR Staff tool visibility, accountant disbursement route restrictions, the Supervisor Fina-creation restriction, same-day date filters, multi-day bounds persisting through pagination/refresh, and reversed-range rejection. This artifact contains the Flutter source at the listed SHA; the later changes were PHP service/test and documentation changes, not Flutter source changes.

The PHP full-schema query gate separately passed in run `40195397302` for `7e6dc63be960efe612191a9b2e109dacd4f580a8`. Local XAMPP API/data verification and physical-device acceptance remain pending.

## 2026-10-25 — Native sponsor assignment workflow added

A native sponsor-assignment screen is now available to administrator roles and to GM/VGM from the mobile workspace. The new `GET api/v1/sponsors/assignment_options.php` returns the same 100-item unassigned-sponsor list and active-supervisor list used by the existing web page. The new `POST api/v1/sponsors/assign.php` restricts the action to admin/GM/VGM, validates both IDs, rechecks the supervisor is active, locks the selected records, runs the existing `recordSponsorAssignment()` history helper, updates the same sponsor assignment fields as `modules/sponsors/assign.php`, and commits atomically. No migration or runtime DDL was added. The VGM mobile tool was changed from an external website link to the native screen.

Verification:
- PHP lint passed for the new API/service and rollback harness.
- Full-schema CI run `40195979512`, source `9dadc1a7c04005e7d36954eec4d32c896ac39b7f`, passed the rollback-only service test: `PASS | mobile sponsor assignment service | sponsor=1 | supervisor=19 | rollback=PASS`. The test verified the sponsor update, actor fields, assignment history and full rollback restoration on disposable MariaDB.
- Flutter analysis and widget tests passed on run `40196042318`, source `ad8c97f0bbc61a2cc52ea6be1d707200446d43e5`; APK build/artifact publication is still in progress at this checkpoint.

Local XAMPP API testing and physical-device interaction remain pending. Do not describe those as verified from CI.

## 2026-10-25 — Native supervisor status controls added

The native supervisor list now exposes activate/suspend controls only when the caller is in the existing management entry points (admin or VGM). Each action requires an in-app confirmation and calls `POST api/v1/supervisors/action.php`. The endpoint permits only admin/VGM, accepts only the existing `toggle_status` action, rejects non-supervisor and archived accounts, updates `users.is_active`, and records the same best-effort audit event as `dashboard/vgm_dashboard.php`. It intentionally does not expose the destructive archive action; archive/edit and letter-assignment tools remain on their existing website pages.

Verification:
- PHP lint and full-schema CI run `40196421650` at source `f5fcc59570a44230e7971d7bbe486bdc6dd16357` passed. Rollback-only service test output: `PASS | mobile supervisor status service | supervisor=14 | rollback=PASS`.
- Flutter analysis and widget tests passed in run `40196556920` at source `d8a8e66e056d0f1535862bfefc1750592fe55a08`, including confirm/toggle/refresh behavior and read-only visibility. APK build/publication is still pending at this checkpoint.

The status service test verified the update and rollback against disposable MariaDB. Local XAMPP and physical-device acceptance remain open.


## 2026-10-25 — Mobile app continuation evidence

The mobile parity evidence matrix was refreshed. A repository route audit confirmed every website path in the mobile role-tools list exists, while noting these WEB links still depend on external-browser session continuity. Native role dashboard metrics passed full-schema MariaDB CI for 14 roles (run 40195397302); sponsor assignment and supervisor status service rollback tests passed (runs 40195979512 and 40196421650); the latest source with supervisor controls passed Flutter analyze/tests and APK publication (run 40196556920). A rollback-only transaction date-boundary harness passed PHP lint and full-schema MariaDB CI in run 40198548850: inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS. Local authenticated API/database and physical-device checks remain pending. Native financial, HR, beneficiary-form and Fina workflow parity remains incomplete; do not claim the mobile app is feature-complete.


## 2026-10-25 — Native suspension case management and date-filter integration

The mobile app now opens the suspension-case register natively from the existing suspension tool route. `GET api/v1/suspensions/index.php` returns the existing case list with nanny family scope; `POST api/v1/suspensions/lift.php` is restricted server-side to administrator/VGM roles and performs case, beneficiary, and linked sponsorship updates in one transaction, followed by best-effort audit logging. The native page supports refresh, active/lifted case visibility, bilingual reason labels, and a confirmation step before lifting. Non-age suspension creation and other non-native workflows remain outside this slice.

Full-schema PHP verification passed in run `40199009847`, including the rollback-only service output: `PASS | mobile legal-age suspension service | suspend/lift, linked sponsorship pause/resume, duplicate guard, role/scope guards, rollback`. The harness also checks administrator/nanny case-list visibility, nanny scope, and lift capability. Flutter analyze and the widget suite passed on source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6` in run `40199177461`; Android debug APK build/artifact publication was still in progress at the time of this documentation update.

Transaction date filtering also has a rollback-only full-schema test: run `40198548850` passed `PASS | mobile transaction date filter | inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS`.

Still open: authenticated API/database runtime against the intended local `ahl_el_kheir` instance, physical-device acceptance, website-session continuity for remaining WEB links, and full native parity for financial, HR, beneficiary-form and Fina workflows. Do not describe the app as feature-complete.


## 2026-10-25 — Native suspension workflow Android build PASS

Correction to the earlier in-progress checkpoint: GitHub Actions run [40199177461](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40199177461), source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6`, completed successfully. Dart analysis, the complete Flutter widget test suite, Android debug APK build, and artifact publication all passed. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,895,397 bytes). The only later `main` commit at the time of this note updates documentation, so the artifact contains the current mobile application source.

The PHP full-schema rollback-only suspension case/suspend/lift test passed in run `40199009847`; transaction date-boundary integration passed in run `40198548850`. This is automated CI evidence, not proof of live local XAMPP/API/database behavior or physical-device acceptance.

The mobile parity work is not complete: non-age suspension creation, native financial/HR/beneficiary-form/Fina workflows, and website-session continuity for remaining WEB links are still open. Do not claim full native feature parity until those gaps are implemented and verified.


## 2026-10-25 — Mobile role-tool parity continuation

Source-only continuation on `main` after the user reported that the mobile dashboards omitted many tools visible in the existing web system. The checked-in web dashboards remain the source of truth; do not replace them with a common role menu or infer permissions from role names alone.

This slice restores dashboard entries already evidenced by existing web dashboard routes:
- GM: sponsor assignment and the complete sponsor-request pipeline.
- VGM: sponsor assignment and supervisor management.
- Administration/Staff/Social Media: the complete sponsor-request pipeline alongside the existing new/contacted queues.
- Existing native sponsor-assignment, supervisor-list/status, and suspension workflows are routed into the app rather than launched in an external browser.

Commits: `2dc812eadedf47c600eb043e1cad84fbc37bfbad` (role tools), `651456187d01dc693dc666f2c8f1388b8f4e975c` (native routing), `35e9e3e350c996eeaf618987abc0458da33cf745` (widget tests).

Verification boundary: source edits are committed; Flutter analysis/widget CI for this exact revision and real-device acceptance are not yet confirmed. This is one incremental parity slice, not completion of all dashboards. Continue role-by-role against actual web dashboard links and route guards, preserve restricted-tool regression tests, and keep external WEB links clearly labelled until their authenticated session continuity is proven. Local XAMPP/API and physical-device verification remain separate acceptance gates.


## 2026-10-25 — Role-sidebar parity: omitted tools restored (source/CI pending)

Follow-up source audit compared `includes/sidebar.php` and each target page's real role guard with `mobile/ahl_el_kheir_app/lib/role_tools_section.dart`. The previous attempted additions of sponsor assignment, supervisor management, and the general sponsor-request pipeline to the supplemental role section were removed after confirming those are already present as native work-area cards; duplicate cards are not an acceptable fix.

The current change adds only tools present in the existing role sidebar and not otherwise exposed by the mobile workspace:
- GM: salary-advance waiver decisions (`modules/hr/salary_advance_waiver_gm.php`).
- VGM: orphan-group management (`modules/deputy_gm/groups.php`).
- FM: opening balance and assigned nannies (`modules/accounting/opening_balance.php`, `modules/accounting/my_nannies.php`).
- Accountant: vouchers, assigned nannies, and GM reconciliation; it deliberately does **not** expose journal, transaction, FM review, FM dashboard, or disbursement pages where the target page guards deny this role.
- Accountant Staff: vouchers, assigned nannies, and orphan forms.
- Supervisor and Nanny: orphan forms.

Source commit: `9bbc4dc8d4943dc3858c65c5e5898d3b27d939f3`. Widget coverage commit: `84e55aa12f1f13aea77659fe347be6edee447ac4`. The tests assert the added tools and preserve the accountant disbursement restriction.

**Verification boundary:** GitHub Actions for the exact latest Flutter source revision and a fresh APK are not yet confirmed at this checkpoint. WEB entries still launch the existing website and do not establish authenticated website-session continuity. Local XAMPP/API and physical-device acceptance remain pending. Continue the same route-by-route audit; do not describe overall mobile parity as complete.


## 2026-10-25 — Personal account options restored in mobile header

The existing website header exposes profile, account settings, and salary-advance request options to logged-in users. The mobile account sheet previously exposed only salary statements and leave requests. It now also exposes the existing profile and settings pages plus the salary-advance request page. These entries are explicitly labelled WEB; the salary-advance request notes that an employee profile is required. The links use the existing routes and do not duplicate or reimplement the web business logic.

Source commit: `16e6446c39ba3040a0ce4e7ab934c406ef2a23c7`. Flutter analyze/widget tests and APK build are queued/in progress for this exact revision at this checkpoint. Website session continuity is still not established; the app warns that website sign-in may be required. Local XAMPP/API and physical-device verification remain open.


## 2026-10-25 — Global search exposed in the mobile header

The existing website header exposes the role-aware global search at `modules/search/index.php`. The mobile header now includes a search action that opens that existing page, explicitly labelled WEB; it does not bypass the existing server-side search permissions or claim a native search implementation. As with all WEB routes, the external browser may require the user to sign in separately because mobile bearer authentication is not a website session handoff.

Source commit: `7c846968f7439fbf5db359374520671590d149c5`. Flutter analyze/widget tests and APK build for this exact revision are queued at this checkpoint. Local XAMPP and physical-device acceptance remain pending.


## 2026-10-25 — Admin recovery queue and mobile role-sidebar parity follow-up

The native Sudo/admin dashboard already displayed the pending password-recovery count but did not offer a direct action. It now includes a clearly labelled WEB tool routed to the existing `modules/users/recovery.php`, whose server-side guard permits only `admin` and `hr_manager`; this dashboard entry is shown only in the admin/Sudo workspace. A widget test asserts that tapping the tile dispatches the `recovery` route.

The latest role-sidebar parity slice remains in `mobile/ahl_el_kheir_app/lib/role_tools_section.dart`: GM waiver queue, VGM orphan-group management, FM opening balance/assigned nannies, accountant/accountant-staff permitted links, and orphan forms for Supervisor/Nanny. Restricted accountant pages remain hidden when the target web guard denies access. Personal profile/settings/salary-advance request and global role-aware search are also now discoverable from the mobile UI, labelled WEB where they still use the website.

Source commits: `45be3327f14e5129a9fb2dbb65daa618026dd648` (admin tile), `c19938979b181b53cc01237dce2d8c34bbfa6feb` (route), `12bd0f5dd017bc233e197b95cb4ef1814aeb9261` (widget test). Flutter analysis/widget CI and APK build for the newest source revision are pending. Website-session continuity, local XAMPP/API verification, and physical-device acceptance remain open. This is incremental tool discovery, not full native parity.


## 2026-10-25 — Authenticated mobile-to-website handoff implemented

Root cause confirmed: mobile requests authenticate with bearer tokens, but existing website pages require the PHP `AhlElKheirSession` browser session. Opening WEB tools directly therefore did not authenticate the browser. The mobile app now requests a short-lived handoff token from `api/v1/auth/web_handoff.php`; `mobile_web_handoff.php` validates and consumes it once, re-checks the active/unrevoked mobile session and account state, establishes the normal PHP session, and redirects only to the stored internal page/query. The app routes its existing WEB tools through this handoff. Native suspension and other native workflows remain native.

Security boundaries implemented:
- 256-bit random token; only its SHA-256 hash is stored.
- 60-second expiry; single-use consumption under a database transaction/row lock.
- Target paths restricted to internal `modules/` or `dashboard/` PHP pages; external URLs, traversal, backslashes, and duplicate slashes are rejected.
- Target query is bounded and stored server-side; the browser redirect does not carry the handoff token forward.
- Handoff is denied if the mobile session is revoked/expired or the account is inactive/forced to change password.
- No runtime DDL; migration is `database/migrations/2026-10-25_mobile_web_handoffs.sql`.

Integration evidence from GitHub Actions run `40201983559` (`d82559e9b3495233b0fdf219d7c0ed3356b2fbd5`): PASS for external-target rejection, token/hash/expiry storage, HttpOnly session creation, target redirect, consumption, replay rejection, and full mobile API auth integration. Flutter analyze and widget tests passed for `fb3e73ef3a575e3ff0e0e43585459ab960f1e0b9`; Android APK build is still running at this checkpoint. Local XAMPP/real-device acceptance remains outstanding.

**Required local deployment step after pulling:** apply `database/migrations/2026-10-25_mobile_web_handoffs.sql` to `ahl_el_kheir` before testing WEB links. Without the migration, handoff creation fails safely and the app displays an error. Do not run DDL from PHP requests.

Role-sidebar parity updates in this continuation also restore GM salary-advance waivers, VGM orphan-group management, FM finance dashboard/opening balance/assigned nannies, permitted accountant tools, and orphan forms for Supervisor/Nanny. Admin/Sudo now has a direct password-recovery tool, and global search, profile, settings, salary-advance request, and internal messages are discoverable. Website-only tools remain labelled WEB; full native workflow parity is still not complete.


## 2026-10-25 — Handoff negative-path stress tests and public recovery exception

The disposable MariaDB/PHP integration harness now additionally proves that an expired handoff token is rejected and a handoff issued by a mobile session is rejected after that session is revoked. Latest integration run `40202292241` on test commit `3140502d1a7596ab2ece0e099e7bdcef86de0fe8` completed **successfully**; output explicitly passed external-target rejection, one-time token issuance/hash storage, HttpOnly session creation, target redirect, consumed-token replay rejection, expiry rejection, revoked-session rejection, and the complete mobile API auth integration.

A deliberate exception is retained for `modules/users/password_recovery_request.php`: it is public and must remain openable from the logged-out mobile screen, so that one route opens directly without a bearer handoff. All protected website routes use the one-time handoff. This prevents the new authentication bridge from breaking the forgotten-password path.

Flutter analyze and widget tests passed on source commit `a00ec31729ff3c983838b7224e75ea80c00972f0`. The debug APK build/publish step is still in progress at this checkpoint. No local XAMPP migration or physical-device test has been performed from this environment.


## 2026-10-25 — Original role dashboards reachable from the mobile workspace

The role-specific mobile summary remains native, but users now also have a direct, clearly labelled WEB action to open their existing role-specific website dashboard with its complete original controls. The route map is sourced from `includes/sidebar.php` and verified against the target dashboard guards: Admin/Sudo, GM, VGM, FM, Accountant/Accountant Staff, Supervisor, Nanny, Administration/Staff/Social Media, HR Manager/HR Staff, and Projects Manager/Project Supervisor. Sudo/admin has the equivalent action in its dedicated control dashboard. Unknown role codes do not receive a guessed dashboard route.

The one-time website handoff normalizes legacy aliases (`sudo/super_admin`, `gm`, `vgm`, `fm/finance`) to the same canonical role codes as the mobile API before creating the PHP session. The public password-recovery page remains directly accessible from the logged-out screen.

Automated evidence: role-dashboard route mapping tests and the expanded mobile widget suite pass on the latest dashboard-link test run `40203004193` (commit `0fc03d7e2593f424fbe951e319f6d6dbbc47aa99`). The debug APK build step is in progress. The latest mobile API handoff integration run `40202879178` passed, including expired/revoked-token rejection and replay protection. Local migration/device acceptance remains outstanding.


## 2026-10-25 — Mobile API connection regression: wrong APK port corrected

Root cause confirmed from the exact build configuration: the latest push-triggered APK (Actions run `40203004193`) was compiled with the fallback API URL `http://192.168.18.9:881/AhlElKheir/api/v1`. Port `881` was the old/wrong port. The previously verified phone endpoint is `http://192.168.18.9:8081/AhlElKheir/api/v1`; laptop loopback verification used `http://127.0.0.1:8081/AhlElKheir/api/v1`. This explains the generic `تعذر الاتصال بخادم API` login message: the app's error handler hides transport/response failures behind one message.

Corrected both the Dart fallback in `mobile/ahl_el_kheir_app/lib/main.dart` and the GitHub Actions dispatch/fallback URL in `.github/workflows/build-flutter-mobile-app.yml` to port `8081`. Commits: `f88668ff93cf446e9cfe8d7c011b22721ff98787` and `c5d7b6f04d708e7503fc2d3d812394409f5842bc`. The first corrective run `40204712668` started before the workflow-file fallback was updated, so it still uses the stale port and must not be used. The authoritative corrected build is run `40204718850`, created from workflow commit `c5d7b6f04d708e7503fc2d3d812394409f5842bc`, whose workflow fallback and Dart fallback both specify port `8081`. Both builds passed Flutter analysis/tests; APK packaging was still running at the last check. Do not use artifacts from runs `40203004193` or `40204712668`; they have the wrong port. Acceptance remains pending until run `40204718850` publishes its APK and the existing login succeeds against the user's local server.

## 2026-10-25 — Mobile app UI regression fixes opened

User reported Sudo metric-card overflow, all System Administration website links failing, insufficient native mobile workflows (too many WEB handoffs), and dissatisfaction with the visual theme. Source inspection confirmed a concrete handoff bug in `mobile/ahl_el_kheir_app/lib/main.dart`: `call()` already returns the API response's `data` object, but `openWebPage()` incorrectly attempted to read `response['data']['handoff_token']` again. It now reads `response['handoff_token']`. Website-open failures now include diagnostic details instead of collapsing every failure into one generic message. Admin and role metric cards received overflow-safe layout changes; the mobile app's shared theme and dashboard palette were refreshed toward teal/sea-green. A regression assertion was added to `role_dashboard_page_test.dart`. Commits: `68ab7b57283b4fd3b052c860cb5cf6b64d73daf8`, `141662935f37f3898501eca3a279aa5fb4e949b4`, `c69eb19c122370ef41e1ee4a749977316809a627`, `8180d52e3e73a64d638f2cbb4b324c1ba3dc5891`, `d5ed6f1ce673354dad6c984e5e49941d0ff946e1`. These source changes are not yet runtime/device verified. The larger requirement—native mobile screens replacing website handoffs for all relevant workflows—remains open; do not describe the app as complete.


## 2026-10-25 — Mobile app-only navigation and native admin milestone

User requirement: no website handoff and no opening pages outside the Flutter app. Removed the mobile client's URL-launching path and `url_launcher` dependency; former WEB routes now open existing native screens where implemented, otherwise an in-app notice. No physical-device acceptance is claimed.

Added a native read-only user directory backed by `GET api/v1/admin/users.php`, and full department CRUD backed by `api/v1/admin/departments.php` + `api/v1/admin/departments_action.php`. Department authorization and validations follow `modules/departments/index.php`; delete is blocked when users/employees are assigned. Added a native recent-audit view (latest six only) and routed existing native project, HR, family, sponsor, sponsorship, transaction and review-queue screens from role tools.

Rollback-only full-schema MariaDB verification passed in Actions run [40210824853](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40210824853): create/list/duplicate guard/update/assigned-user delete guard/delete/rollback all PASS. Native user directory, department UI, audit UI and native-only notice widget tests have been added. This is an intermediate milestone; full native parity remains open, including user write actions, recovery decisions, reports, settings, accounting/disbursements/Fina, backups/database tools, search, profile/messages, orphan forms and additional HR/finance workflows.


## 2026-10-25 — Native password recovery queue

Added native recovery queue for admin/Sudo and HR Manager. New GET/action API endpoints use the existing recovery request/user/notification/audit tables and existing workflow semantics. Approval uses a transaction and row lock for the account-password and request-status update, enforces password change on next login, sets a 24-hour expiry, and returns the temporary password only in the immediate response; notification/audit are best-effort after commit. Rejection marks the pending request and attempts a best-effort notification. No schema changes.

Rollback-only full-schema MariaDB test passed in Actions run [40211295992](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40211295992): queue, approve, password-hash verification, forced change, expiry, duplicate guard, reject, and rollback all PASS. Flutter analyzer passed; latest widget-test/APK publication status remains tracked by the current build run. No physical-device verification is claimed.



## 2026-10-25 — Mobile native-only navigation and Sudo administration checkpoint

The Flutter app's navigation policy is native-only: no browser, WebView, external URL, or website dashboard handoff is used as a substitute for an app workflow. The separate “Full website dashboard” action and duplicate administration-dashboard tile were removed. Routes that still lack a native implementation now show an in-app notice and do not open a placeholder page. The server-side website-handoff API now returns HTTP 410, and the old mobile_web_handoff.php redirect route also returns HTTP 410 without setting a browser session or redirecting; the integration test asserts both behaviors and confirms no handoff records are created. The old handoff migration/table is retained but is no longer used; no runtime DDL or destructive database change was introduced. These routes remain unfinished product work and must not be reported as implemented.

Native user administration was expanded using the existing HR employee provisioning workflow and current database schema:
- User directory now has native create and edit screens, with role/department/manager options from the existing roles, departments, and users data.
- Creation submits to the existing native API action and provisions the linked employee profile and initial salary history through hrCreateUserWithEmployee; the temporary password is shown in-app for one-time sharing.
- Editing submits identity/role/department/manager/status changes through the native API and calls hrSyncEmployeeIdentityFromUser to synchronize linked employee identity fields.
- The server-side user-action authorization list was aligned with the user-directory policy to include admin, sudo, super_admin, and hr_manager. Previously, Sudo could see the directory but was denied by the action endpoint; this was a concrete authorization mismatch found in source review.
- Native user create/edit widget tests and the rollback-only service test cover form payloads, validation, identity synchronization, status changes, password reset rules, and rollback. No local physical-device test has been performed in this checkpoint.

Related commits: cb2b809172b80a31ff0a7ee61ee84ddea5384c53, c8558ae6c6d354bba33973b743cdc04040b33d68, e47b5c0b6b19dd06fedfa8a09eba01c60090cfc8, 84efd7c2981065454a4b51ca7758b38a7c9d50d4, 11019d79b6dd39bcd63aa0c4ff96237cdcd03477, ecbf8e0520fd2940a34ece36616e390c6b54eb2d, 383714f576b8933daf7f54a38ddf74ec2a77139c, 3c7834b9a15a2863a71bd551deb781de4bc42e31, ce1822e7dae12c27bac62127e3d4bbec8bc463ff, 156e974991ba10e58dbc4c774cde95ae119a51e7.

Verification: Flutter analysis passed, all Flutter widget tests passed, and the Android debug APK was built and published by GitHub Actions run 40212763374 for app source commit 383714f576b8933daf7f54a38ddf74ec2a77139c. Download the artifact from https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40212763374 (artifact: ahl-el-kheir-flutter-android-debug-apk; retention through 2026-11-24). The native-only backend retirement was verified by mobile API integration run 40213109443: API handoff requests return HTTP 410, create no handoff records, and the retired browser route does not redirect or set a browser session. The rollback-only native user-action service test passed in integration run 40213296633: identity update, status toggle, password-reset hash/forced-change flag, short-password guard, self-action guard, and rollback all passed. This does not prove physical-device connectivity to the user's local XAMPP API; that remains unverified. Native parity remains incomplete for settings, reports, backup/database tools, finance dashboard/accounts/disbursements/Fina collection, orphan form, profile/settings, salary advance request, messages and system search. Do not send the user to test this as a finished app until the current build is green and the next consolidated native workflow slice is ready.



## 2026-10-25 — Configurable mobile API endpoint

The native login screen now exposes an API-server configuration control. It validates HTTP/HTTPS URLs that end in /api/v1, persists the selected endpoint in platform secure storage, displays the active endpoint on the login screen, and clears the old bearer token when the endpoint changes. This removes dependence on a single hard-coded LAN IP and lets a phone be configured for the computer's currently reachable local address without rebuilding the APK. It does not bypass Android cleartext policy, firewall rules, or host reachability requirements.

Verification checkpoint: Flutter analysis passed and all Flutter tests passed in GitHub Actions run 40213757297; APK compilation/publication was still in progress at this documentation point. Do not claim the new endpoint-configurable APK is ready until that run publishes its artifact.


## Current continuation point — 2026-10-25 mobile dashboard

Continue the existing Flutter app on `main`. The narrow-phone admin dashboard regression test passes in Actions run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941); APK compilation/publication is pending. The latest mobile source checkpoint is `fa11cde81f98839175431dd2d86348ee363d2270`. Do not ask for device testing until the APK artifact is published and the remaining requested native workflows are consolidated. Preserve the no-browser/no-external-page requirement.

Current open finding: role authorization mismatch between the shared admin dashboard and specific existing tools. Inspect and preserve actual web/API guards; do not guess that `sudo` or `super_admin` may use departments, settings, or password recovery. Native parity for settings, reports, backup/database, finance/accounting, beneficiary forms, profile/settings, salary-advance requests, messages, and system search remains incomplete.


## 2026-10-25 — Native audit register and Android build confirmation

- Added authenticated read-only `GET api/v1/audit/index.php?page=1&q=...`. Authorization follows the existing `modules/logs/audit.php` guard: canonical administrator and General Manager roles can read the register; other roles receive 403. Search covers action, entity type, username and full name; results are paginated at 50 per page with parameterized search terms. The mobile API does not expose audit deletion and introduces no schema change/runtime DDL.
- Replaced the previous six-event-only native audit screen with a searchable/paginated register and added a widget test for search and page navigation. The PHP lint workflow now includes the endpoint.
- Source commits: `b0af7d3f98555582ee978843f89373fd1c8740a2` (API), `e0599319cc2a820de8b00731a2c14ceda4a27ccb` (app wiring), `97103af61e0b25f8efa09012db60725e4b3cee0b` (screen), `249a383786c08067657ae8bd705c269160d182b4` (widget test), `da50eab0685a9828bd456d2d0e9777bcfc403c9a` (PHP lint workflow).
- Correction to the prior pending-build checkpoint: Flutter workflow run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941) completed successfully, including Dart analysis, widget tests, debug APK build and artifact publication. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,487,052 bytes, SHA-256 `a02fbca24a2e9a14915bc895ec999ef4108f2854a3caf6e8d57ae5e3f5dc2d5b`, expires 2026-11-24.
- The audit-register change's own CI run is pending. No local XAMPP API/role-matrix or physical-device acceptance is claimed. Full native role parity remains incomplete; keep existing authorization boundaries and do not use external-browser handoff as a substitute for native features.


## 2026-10-25 — Audit API role-guard correction and current Android artifact

- Flutter build workflow run [40217046629](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40217046629) completed successfully for app source `69c35a22978976db13b2d62942f08a87a7ee18d9`: Dart analysis, widget tests, debug APK build and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, 77,483,457 bytes, SHA-256 `166dcafc2b9f3c51cc6330231b4e6b5ca3a789f03bf7dcd1e789bc8f775d813c`, expires 2026-11-24.
- Source review found a concrete authorization mismatch in the newly added audit API: `ak_api_authenticated_user()` normalizes `sudo` and `super_admin` to `admin`, but the existing `modules/logs/audit.php` checks the persisted role exactly and permits only `admin` and `general_manager`. The API now checks `raw_role_code` so it matches the web guard; no permissions were broadened. Correction commit: `b54fc8bd6af618313f20a16adf7e5283c63eccf4`.
- The corrected API endpoint still needs its own PHP lint and role-matrix CI confirmation. No local XAMPP/API or physical-device verification is claimed. Continue native parity from current `main`; keep existing role guards authoritative and avoid external-browser handoffs.


## 2026-10-25 — Native system settings implementation

- Added `GET/POST api/v1/admin/settings.php` and `mobile/ahl_el_kheir_app/lib/admin_settings_page.dart` for organization, financial, SMTP, system settings, and administrator-only organization-logo upload. Logo uploads accept detected JPG/PNG/GIF/WebP content up to 2 MB, update the existing `settings.site_logo` value, and audit the change. Disposable API integration passed upload success, unauthenticated denial, and nanny-role denial in run `40299909395`; Flutter analysis/widget verification for the new picker UI was pending at this checkpoint.
- Authorization checks the persisted `raw_role_code` and allows only exact `admin`, matching `modules/settings/index.php`; `sudo`/`super_admin` are not granted settings access by normalized routing roles. The API rejects unknown setting keys, validates email/select/number values, does not return stored setting values outside the allowlist, never returns the SMTP password, preserves an existing SMTP password when the field is blank, and writes a masked best-effort audit event for changed values. No schema change or runtime DDL.
- Added a Flutter widget test for loading/editing/saving settings and password omission, and added the endpoint to the PHP syntax workflow. Dart analysis passed; the first widget-test attempt revealed the test tapped a save button below the viewport. The test now scrolls the button into view before tapping. Latest Flutter run [40218216549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40218216549) has passed analysis and widget tests; Android APK build/artifact publication was still running at this documentation point.
- Native settings is available only to the exact admin role. The administrator dashboard labels that restriction rather than implying that `sudo` has access. Local XAMPP/API role-matrix and physical-device acceptance remain unverified.
- CI note: the separate full-schema migration workflow's repeated failure is the known harness guard `Refusing to run outside mobile_api_auth_test with MOBILE_API_AUTH_TEST=1`; the PHP lint, MariaDB 10.4 migration, HR migration and rescheduling jobs passed in run `40218041093`. No closed payroll code or production behavior was changed in response to that unrelated harness failure.

**Native-only navigation correction:** the Flutter app's `openNativeTool()` explicitly never launches a website, browser, or external application. Any website-only route not mapped to a native screen displays an in-app unavailable message. Older parity text that describes WEB links as opening the external browser is stale; WEB labels are placeholders, not completed workflows.


## 2026-10-25 — Native settings verification and Android APK published

Correction to the previous pending-build note: Flutter workflow run [40218216549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40218216549) completed successfully for app source `d2566a3130d89d7ba04b510063a49cbac410a05c`. Dart analysis, the complete Flutter widget-test suite (including native settings form test), debug APK build and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,491,114 bytes, SHA-256 `26e427538cca26cb9573ab0ce3e4b1c3a0f25175ea3f164dba553beb4e164362`, expires 2026-11-24. Download through [GitHub Actions run 40218216549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40218216549).

The PHP lint job passed in run `40217995457`; the mobile API auth integration job passed in run `40217995530`. The separate full-schema workflow still fails only at its existing invocation guard `Refusing to run outside mobile_api_auth_test with MOBILE_API_AUTH_TEST=1`; its PHP lint, MariaDB 10.4 migration, HR migration and rescheduling jobs passed. This does not establish a settings DB role-matrix test. Local XAMPP API/database behavior and physical-device acceptance remain unverified.


## 2026-10-25 — Native reports overview and uncovered-families report

- Added `GET api/v1/reports/overview.php`, guarded against the exact persisted role using `modules/reports/report_registry.php`. It returns the same core overview metrics from the existing report center and the caller's report catalog; the uncovered-families count uses the authoritative `family_children` relationship and active sponsorship link.
- Added `GET api/v1/reports/orphaned_families.php` with role guard from the same registry, city/status filters, family/child counts and monthly need values. The native screen opens existing family detail inside the app. It does not create a browser handoff. Report pages not implemented natively remain disabled and labelled as not ready.
- Added native reports center and uncovered-families screens, in-app routing for the existing reports tool, and widget tests. PHP lint passed in workflow run `40218906611`. Flutter analysis and widget tests passed in run [40219066747](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40219066747); Android debug APK build/artifact publication was still running at this checkpoint.
- The endpoints use the report registry's exact role list, not normalized dashboard roles. Local XAMPP API/data-role verification and physical-device acceptance remain unverified. Native parity remains incomplete; do not install an intermediate APK or treat the reports hub as implementation of every listed report.

## 2026-10-26 — Native administrator dashboard role-guard alignment

- Source review found the administrator dashboard showed Departments, Settings, Password Recovery, Reports and Audit to `sudo`/`super_admin` even where the existing server-side guards do not authorize those roles. This made dashboard entries appear actionable but fail after opening; no permissions were broadened.
- `AdminDashboardPage` now receives the persisted role code. The listed tools are shown only for exact `admin`, matching the relevant existing guards; native user administration remains available to `sudo` because its API supports that role. The Reports tile description now reflects the implemented native reports center rather than saying the whole center is unavailable.
- Added a narrow-phone widget regression test for `sudo` visibility and retained the existing admin recovery-route test. Source commits: `158a42c1037029e6aa8027cf5aadc1bb22cbe181`, `9bc902b6f5bbee664f1fcdaf0716f79f8b577e0b`, `b679a9312ef4946aacbce12974137abf26b62211`. CI and APK publication for this change must be confirmed before distributing a new build. Local XAMPP/API and physical-device acceptance remain unverified.



## 2026-10-26 — Mobile admin role-guard test and APK verified

Flutter workflow [40273273719](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40273273719) passed Dart analysis, the full Flutter widget-test suite, Android debug APK build, and artifact publication at app/test source `9c57765ab408afa43c1d81823e2aa7a5af1ec703`. The Sudo dashboard regression test verifies that user administration remains available while exact-admin-only Departments, Settings, Password Recovery, Reports and Audit tiles are absent. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,507,377 bytes, SHA-256 `0c195da7469284f95aa8d7664874b2c62534ed096060e88f263844c9ce62279c`, expires 2026-11-25. Local XAMPP/API and physical-device acceptance remain open; mobile feature parity is not complete.


## 2026-10-26 — Native administrator finance overview in progress

Added authenticated `GET api/v1/admin/finance_overview.php` and a native read-only finance overview screen. Its SQL is derived from the existing `modules/accounting/fm_dashboard.php` treasury, posted monthly flow, administrative-fee, monthly-disbursement and operational-count calculations. The endpoint checks the persisted raw role and permits exact `admin` only; no schema or accounting mutations were added. Live endpoint integration against the checked-in schema passed in [run 40274853455](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274853455); PHP lint passed in [run 40274952065](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952065). Flutter analysis and widget tests passed in [run 40274952027](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952027); the Android APK build/publication was still running at this checkpoint. Do not distribute until artifact publication is confirmed. Local XAMPP/API and physical-device acceptance remain open.


Correction — 2026-10-26: Flutter workflow [40274952027](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952027) has now completed successfully. Dart analysis, the full widget suite, Android debug APK build and artifact publication all passed for app source `89def08b6adde95bec648cb1349f18860b945ef5`. Published artifact `ahl-el-kheir-flutter-android-debug-apk`: 77,523,067 bytes; SHA-256 `7800554326cf99174b90550284ede7e1d44f634d48cc895dfb522f85335742a0`; expires 2026-11-25. Download via the workflow run’s Artifacts section. This is automated CI evidence only; authenticated runtime against the user’s local XAMPP/API/database and physical-device acceptance remain outstanding. The native finance page is read-only; chart of accounts, monthly disbursements and Fina collection remain unfinished natively.


## 2026-10-26 — Native finance and chart-of-accounts checkpoint

The administrator dashboard now has native read-only Finance Overview and native Chart of Accounts screens. The latter lists account balances based on posted journal lines and supports account creation and active-status toggling using existing account columns; no runtime DDL, schema migration, or accounting-entry mutation was added. The exact-role API endpoints are `GET api/v1/admin/finance_overview.php`, `GET api/v1/admin/accounts.php`, and `POST api/v1/admin/accounts_action.php`. Live full-schema integration passed for finance metrics and chart account listing/create/toggle/zero-balance behavior in [run 40275754236](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40275754236). Flutter analysis and all widget tests passed on current app source `27ff209075056f606db2a85c6c1a16259cac9316` in [run 40276057511](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40276057511); APK build/publication is still in progress at this checkpoint, so no new APK is available yet. Local XAMPP/API/database and physical-device acceptance remain open. Chart of accounts, monthly disbursements and Fina collection are not yet full native feature parity.


Correction — 2026-10-26: The Android build has now completed successfully. Workflow [40276057511](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40276057511) passed Dart analysis, all Flutter widget tests, debug APK build and publication for source `27ff209075056f606db2a85c6c1a16259cac9316`. Artifact `ahl-el-kheir-flutter-android-debug-apk`: 77,526,098 bytes; SHA-256 `798f2756a91c19f0a0448165907b2dbfa364c26d3465925784cbcab96e3bf144`; expires 2026-11-25. Download from the workflow run’s Artifacts section. Live API integration passed on the same backend/test source line in [run 40275754236](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40275754236). This proves CI/disposable-schema behavior only; local XAMPP/API/database and physical-device acceptance remain open. Full native feature parity is not complete.

## 2026-10-26 — Native administrator monthly disbursement register (verification checkpoint)

Added a read-only native monthly-disbursement register and batch-detail view in the Flutter app. The new authenticated `GET api/v1/admin/disbursements.php` endpoint supports optional month/status/search filters, paginated batches, and paginated family items. It authorizes only the exact persisted `admin` role, follows the existing `modules/accounting/disbursements.php` query fields/statuses, and adds no schema changes or financial mutations. The admin dashboard now labels Chart of Accounts and the read-only disbursement register as available in-app; no external browser handoff was added.

At source revision `dea8c633ef446690f6ec91bc7200d859959b5135`, Flutter analysis and all widget tests passed in [workflow 40278814068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40278814068); Android debug APK build/publication is still pending at this checkpoint. The separate PHP lint job passed its configured file list, but this new endpoint is not yet in that list; do not claim endpoint PHP lint or live/full-schema query verification. Local XAMPP/API/database and physical-device acceptance remain open. Disbursement actions (create/transfer/confirm/return/reopen/void) and native Fina collection are not implemented by this read-only slice; mobile role parity remains incomplete.


## 2026-10-26 — Mobile Fina CI follow-up

Fina native registration/review source now passes Dart analysis and all 85 Flutter widget tests in workflow [40287521591](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40287521591), source 841916ae88481a28ca33bb0ef3d6a0f591ad1566. APK build failed because the generated Android project compiled against API 34 while flutter_plugin_android_lifecycle requires API 36. Workflow commit 37b8a2756c7c2699a85ff277e98c4b1a97724804 explicitly sets compile SDK 36. Follow run [40288510863](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40288510863) through APK publication before offering any install. Native Fina API local runtime and device PDF-preview verification remain open.


Correction — 2026-10-26 mobile Android build gate: run 40288510863 proved setting only the generated app compile SDK was insufficient; the failing Gradle module was file_picker itself, still compiled against API 34. Commit d408c1b4d756a8a151f6e7fff120061c595efdac upgrades file_picker to ^10.3.6, whose 10.3.x Android build uses Flutter's compileSdkVersion; API 36 remains explicit for the generated app. The Finance Manager native Fina registration/review routing and exact role gate are also committed and covered by a widget regression test. Follow latest run [40289421824](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40289421824); no APK is verified until its artifact is published.


## 2026-10-26 — Native Fina API integration PASS

Workflow [40289622000](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40289622000) passed live PHP API integration against disposable MariaDB after importing the checked-in Fina standalone schema migration. Verified authentication, invalid amount rejection, pending creation, no journal on creation, pending queue visibility, and creator self-approval rejection with unchanged pending status/no journal. The harness cleans its Fina fixtures. Multipart receipt upload and notification-failure isolation remain unverified by this harness; local XAMPP and physical-device/PDF preview remain outstanding. Current APK build gate is [40289711691](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40289711691).


## 2026-10-26 — Mobile Fina and administrator tools

The native Fina dashboard/registration/review, administrator read-only database browser, and administrator backup screen are now implemented in source. Disposable MariaDB API integration [40291447241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40291447241) passed Fina role/creation/review guards, no-journal-on-create, self-review denial, database table/schema reads, real SQL backup create/download/delete, clean dump header, and nanny denial. The app's Dart entrypoint was reconstructed from last known good commit `f44e98858f65a7a450c7f603dd618fbc40cb28b1` after a malformed duplicate source suffix; the current widget/build gate is [40291896014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40291896014). Other native parity remains open (disbursement actions, other finance/HR/payroll, beneficiary/orphan forms, and reports); do not claim the whole app is complete or hand off until the current gate passes and the remaining priority workflows are addressed.


## 2026-10-26 — Mobile native report detail work

Added `GET api/v1/reports/detail.php` and a shared native report page for financial, sponsorship, operational, and HR report families, with report-registry authorization, date validation, and parameterized date filters. Flutter analysis, widget tests, Android debug APK build, and artifact publication passed in run `40298607464` for app source `9ab66cd9ff69ceb1553cb69f2d91e066cc4cd34c`. Artifact: `ahl-el-kheir-flutter-android-debug-apk` at https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40298607464. Disposable MariaDB API integration passed in run `40298934944`, covering all four report families, reversed-date rejection, database backup actions, and role-denial checks. The API harness's stale disbursement reopen assertion was corrected in `fedcff2806c4a98798dbd5faafac24b8306906b5` and now checks the HTTP response plus persisted pending status/cleared confirmation timestamp. See `docs/MOBILE_ROLE_FEATURE_PARITY.md` for remaining acceptance gaps.

## 2026-10-26 — Mobile continuation: native parity and corrected Android API default

- Native monthly disbursements now include role-scoped batch creation, transfer receipt submission, nanny item confirmation, return receipt, reopening, and void actions. The API enforces each action's persisted-role allowlist and nanny/accountant-staff record scope. Widget coverage is in `mobile/ahl_el_kheir_app/test/admin_disbursements_page_test.dart`; the mobile API integration harness exercises these paths.
- Native attendance-policy management is present at `api/v1/hr/attendance_policy.php` and `mobile/ahl_el_kheir_app/lib/attendance_policy_page.dart`. It mirrors the existing HR Manager/admin boundary and version edit/delete lock rules. Disposable MariaDB API integration passed in [run 40310902190](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40310902190), including create/read/update/validation/delete and nanny denial.
- The Flutter history widget test now scrolls the lazy ListView to the policy history heading and row. Dart analysis, the full widget suite, Android debug APK build and artifact publication passed in [run 40314527960](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40314527960) for app source `4952889a754138467d8af61077090e879096f028`. Artifact `ahl-el-kheir-flutter-android-debug-apk`: 102,203,303 bytes; expires 2026-11-25. The artifact is built with the corrected port-881 API default.
- Source audit found the phone-facing API default had regressed to port 8081. Restored the app default and in-app hint to `http://192.168.18.9:881/AhlElKheir/api/v1` in `486912b34bedc580584022653aa0d2ec7ec11cd7`, and aligned both workflow fallback/manual defaults to port 881 in `4952889a754138467d8af61077090e879096f028`. The in-app API configuration remains available if the LAN IP changes.
- The separate Salary Advance verification workflow still has its previously known full-schema invocation-guard failure (`Refusing to run outside mobile_api_auth_test with MOBILE_API_AUTH_TEST=1`); its PHP lint, MariaDB 10.4, HR migration and rescheduling jobs pass. Do not change closed salary-advance behavior to suppress this unrelated harness guard.
- Local XAMPP/API/database and physical-device acceptance remain unverified. Native payroll operations and other role-specific workflows remain open; no external-browser handoff is allowed.

## 2026-10-26 — Native HR payroll source audit

Created `docs/MOBILE_HR_PAYROLL_CONTINUATION.md` in commit `7ef743068c1c4b14063801ec5972bad3d4235484`. The next mobile work is a native payroll-management API and Flutter screen using the existing payroll engine and proof-transfer helpers. Source audit confirms exact management roles (`hr_manager`, `hr_staff`, `financial_manager`, `admin`), draft-only amount edits, separate approval, prepared-proof upload, explicit proof-backed transfer confirmation with transactional accounting, and legacy evidence that cannot mutate paid history. No payroll source, database, or accounting data was changed in this audit step. The existing transfer migration must be applied to disposable MariaDB in CI before testing the new endpoint. Local XAMPP and physical-device acceptance remain separate gates.

## 2026-10-26 — Native HR payroll management implementation checkpoint

The native payroll management API, Flutter screen, role navigation, and private proof preview are now implemented on `main`. Disposable API integration passed in [run 40318619066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318619066), with PHP lint passing in [run 40318633490](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318633490). Verified behavior includes proof upload without payment, private proof authorization/streaming, explicit actor attribution, balanced transactional posting, duplicate-confirmation rejection, and legacy evidence preserving paid history. Flutter analysis and all 105 widget tests, Android debug APK build and artifact publication passed in [run 40318833316](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318833316) for source `339440d1747abdf4388188bcb8809cead65c3df7`. Artifact `ahl-el-kheir-flutter-android-debug-apk` (102,218,468 bytes; ID `12647679265`) expires 2026-11-25; uploaded ZIP SHA-256 `c30a9d0fe19579c72ebc25cfd75f027d37dc2553f82631b0d81b03e2bc9916f0`. Expanded draft-edit/approval API integration passed in [run 40319313245](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40319313245). Automated gates are green; local XAMPP/API/database and physical-device acceptance remain unverified. Local XAMPP/API/database and physical-device acceptance remain unverified.



## 2026-10-26 — Mobile visual identity refreshed

The Flutter app now uses a distinct native visual palette rather than attempting to copy the original web system: deep teal (`#115E59`), bright teal (`#20A39E`), coral (`#E07A5F`), slate text, and a cool light background. Applied the palette to the app-wide Material theme, login/notification surfaces, administrator dashboard, role dashboard, role-tool list, and shared native screens that used the old palette literals. Arabic RTL, English LTR, Cairo typography, organization branding, API behavior, and authorization rules are unchanged. Automated Flutter analysis/widget/build verification is pending for these latest palette commits; do not treat visual device acceptance as proven.

Next native parity priority: implement the existing role-aware global search inside Flutter, reusing `config/search_permissions.php` and the web search's record-level visibility rules. The search icon must not open a browser; unsupported domains remain explicitly unavailable until implemented.


## 2026-10-26 — Native search center (initial supported scopes)

The home-screen search icon now opens an in-app native search center for families, sponsors, and sponsorships. Results use the existing authenticated API endpoints and their existing role/record-level guards; the UI exposes only categories currently accepted by those endpoints and never falls back to a browser. Search results can open the existing native family, sponsor, and sponsorship detail screens. Monthly disbursement and archived project-document search remain explicitly identified as unfinished native scopes; this is an incremental parity milestone, not completion of the original global search.


## 2026-10-26 — Native search monthly-disbursement increment

The native global-search screen now includes monthly disbursement search for the exact persisted roles accepted by `api/v1/admin/disbursements.php`: `admin`, `financial_manager`, `general_manager`, `accountant_staff`, and `nanny`. It reuses that authenticated API and opens the native disbursement register filtered to the selected batch ID; no browser handoff or parallel financial query was introduced. A role-visibility test and a result-routing test were added. Flutter analysis and the full widget suite passed in workflow [40325180234](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40325180234) for source `17feccacfa312ba1982fdb0e0549fce1809c42d7`; APK build/publication was still in progress at the time of this checkpoint. Archived project-document search/preview and broader search-role parity remain open. Local XAMPP/API/database and physical-device acceptance are not verified.

## 2026-10-26 — Mobile API connection regression corrected; native search status reconciled

Source/history audit found a concrete API-port regression on `main`: the app default and APK workflow had been changed back to `http://192.168.18.9:881/AhlElKheir/api/v1`, and the startup migration rewrote the previously verified `192.168.18.9:8081` endpoint back to port 881. That made a valid saved endpoint unusable from the phone whenever only port 8081 was reachable. Commit `da04d0fea8e73f505fa521546e653ca4e263e0c0` restores port 8081 in the app and APK workflow, migrates only the exact known stale 881 URL to 8081, preserves other hosts/custom ports, and updates `api_connection_diagnostics_test.dart`. Automated verification for this correction is pending; do not claim phone-level connectivity until the local server/device boundary is tested.

The current source theme is the distinct native violet-and-amber palette with a lavender-tinted background, established by `4681babff5b6a8bec869d238753e53ae1677ee41` and unified by `8807322161b6fa03993dbd6316e10b46c94425e1`. This intentionally differs from the original website's navy/blue palette; preserve Cairo, Arabic RTL/English LTR, and organization identity. Older notes describing the palette as deep teal were stale relative to current source.

The native global-search screen and APIs now include archived project-document search and private in-app file retrieval, alongside families, sponsors, sponsorships, and role-limited monthly disbursement search. Archived search/streaming integration passed in run `40335923994`; broader search-role parity and other native workflows remain open. No external-browser handoff is permitted. Full local XAMPP/API/device acceptance and full app feature parity remain unproven.

## 2026-10-26 — Vice General Manager native disbursement read access

Source audit confirmed the existing web monthly-disbursement page allows `vice_general_manager` to view the register, while the native GET API omitted that role. Commit `1fb5efe34b85a6f484c50e7420e4218b74e7031c` adds VGM to the read endpoint and native global-search category only. All mutation capabilities remain false for VGM, and action endpoints were not widened. Added a widget test for VGM search visibility, disposable-MariaDB integration asserting read access and no create/transfer/void/reopen/confirm capabilities, and explicit PHP lint for the endpoint. CI is pending; no device acceptance is claimed.


## 2026-10-26 — Mobile API error traced to stale APK artifact

The user's reported login error was investigated against the actual current source and the APK previously supplied. The APK link previously provided pointed to workflow run `40318833316`, built from source commit `339440d1747abdf4388188bcb8809cead65c3df7`. The current `main` was already 95 commits ahead at `4a628f789d717273517a6fd42ef2bb6d34673a56`; comparing those refs shows `mobile/ahl_el_kheir_app/lib/main.dart` changed by 427 lines (362 additions, 65 deletions), and the older artifact source lacks `probeApiEndpoint`, `migrateKnownLegacyApiBaseUrl`, and the current host/port-specific connection diagnostics. Therefore the previous device test used a stale APK; it did not test the then-current source. This was an artifact handoff mistake, not evidence that the current source's API fix failed.

Corrective changes now on `main`:
- `c04ebc24e18aea00f75df6598b312e455bea0e48`: Flutter APK build embeds `MOBILE_BUILD_REVISION=$GITHUB_SHA` and publishes `mobile-build-info.txt` alongside the APK.
- `5216d8f8230688935c6eaeccbc20178d8a6b0d1c`: login screen displays the shortened source revision so a stale installation can be identified before testing.
- `a24cbdd88a0b4a112bbb4fc631337e1c44f08942` and `df18877c5a99c4c12ad5dc2dc59cade622e1f64d`: update API diagnostic regression tests and expected build label.

The source still defaults to `http://192.168.18.9:8081/AhlElKheir/api/v1` and migrates only the exact previously saved `192.168.18.9:881/AhlElKheir/api/v1` URL. The build/provenance workflow and login revision indicator are committed; CI analysis, tests, APK publication, and actual phone connectivity for this new head must be checked before sending another APK for acceptance. Do not ask the user to retest the stale artifact. No external-browser handoff is permitted. The app intentionally uses a distinct violet/amber/pink visual identity rather than copying the web palette.


## 2026-10-26 — Native account password settings and forced-change flow

The mobile app now includes a native Account Settings screen for changing a signed-in user's password, plus an in-app forced-password-change dialog when login returns the existing `password_change_required` response. The app retries login with the new password after the server confirms the forced change; it does not direct the user to the website.

Backend additions on `main`:
- `api/v1/auth/change_password.php`: bearer-authenticated self-service password change; checks the current password, enforces the existing six-character minimum and matching confirmation, updates `password_hash` and clears the required flag, and preserves the existing approved-recovery completion and audit behavior. It performs no DDL.
- `api/v1/auth/complete_forced_password_change.php`: pre-login change path restricted to active accounts that are actually flagged for a forced change; requires username and current password, validates the new password, clears the required flag, and records the existing audit/recovery side effects without issuing a session or token. It performs no DDL.
- `api/v1/auth/login.php` now describes the forced change as an in-app step rather than a website requirement.

Native UI: `mobile/ahl_el_kheir_app/lib/account_settings_page.dart`; account-menu routing and login retry are in `main.dart`. Widget tests cover normal password-change submission and local mismatch validation. The disposable API integration harness now covers invalid current password, mismatched confirmation, successful hash update, old-password rejection, new-password login, forced-change completion and flag clearing, and cleanup of test audit/recovery/session state. The API workflow explicitly lints both new endpoints and the login endpoint. CI results for the newest source must be checked before claiming these new paths verified; no local XAMPP/device result is claimed.

Remaining account parity gaps are still open: personal profile editing/photo management and password-recovery request are not yet native. Do not label the account area fully complete. No external browser handoff is allowed. The overall app uses a distinct violet/amber/pink palette and should not be changed to copy the original web theme.


## 2026-10-26 — Native password recovery and accounting journal workflows added

Further native parity work has been committed on `main` after the stale-APK/API diagnosis and account password work.

### Password recovery
- `api/v1/auth/password_recovery_request.php` creates a pending request for an active matching username/email, avoids duplicate pending requests, and notifies active `admin`/`hr_manager` users best-effort. The response is identical for known and unknown accounts to avoid account enumeration. No DDL is performed.
- `mobile/ahl_el_kheir_app/lib/password_recovery_page.dart` replaces the login screen's former unavailable native workflow; the app does not open the website.
- API integration coverage checks request creation, duplicate protection, and the same generic response for unknown identities. The fixture records and removes the test request and its recovery notifications.

### Accounting journal
- `api/v1/accounting/journal.php` now supports role-protected read/list/detail, date filters, active-account listing for authorized creators, manual entry creation with active-account validation, unique account lines, decimal precision checks, balanced debit/credit totals, transactional persistence and a journal-numbering lock.
- Manual voiding follows the existing web workflow's restrictions: only admin/Financial Manager, no automated/reversal entries, no creator self-void, no duplicate reversal, and a balanced reversing entry plus preserved original void metadata. No runtime DDL is used.
- Native screens: `journal_page.dart` and `journal_manual_entry_page.dart`; role tools now route the journal inside Flutter. API integration coverage includes role denial, invalid date filters, detail/list, unbalanced-create rollback, successful manual create, creator self-void denial, different-reviewer void, balanced reversal, duplicate-void rejection, and cleanup. Widget tests cover list/detail, manual entry validation/submission, and reviewer void UI.

These changes are committed and the workflows are configured to run, but the current GitHub Actions outcomes have not been independently retrieved in this session. Do not claim these newest paths passed CI until the actual run results are checked. No local XAMPP or physical-device result is claimed. The mobile app remains unfinished overall: full role-by-role parity, profile/photo editing, and other remaining web workflows still require audit and implementation. Keep the distinct violet/amber/pink app design; do not copy the original web theme or introduce browser handoffs.


### Mobile CI recovery checkpoint — 2026-10-26

Flutter APK workflow run [#571](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40354883125) passed on commit `2422efc2c7d38e1d86f4bed1ca7b315a48888542`: Dart analysis, widget tests, debug APK build, provenance, and artifact publication all succeeded. This is CI evidence only; no physical-device or local API-connectivity result is claimed.

The salary verification workflow run [#3495](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40354883450) failed because its full-schema job invoked `tools/test_mobile_admin_users_action_service.php` with `AHL_DB_NAME=salary_advance_migration_test`; the test correctly refuses to run unless `AHL_DB_NAME=mobile_api_auth_test` and `MOBILE_API_AUTH_TEST=1`. Removed that unrelated invocation from the salary job; the dedicated Mobile API auth integration workflow owns that test and supplies its required isolated database/guard. Do not weaken the test guard. Await the new salary workflow result before treating this correction as verified.

## 2026-10-26 — Native receipt/payment voucher actions

- Native Flutter voucher register now supports authorized receipt/payment issuance and authorized voiding with a required reason. It uses the existing authenticated api/v1/accounting/vouchers.php endpoint; the server remains authoritative for role access, dates, amount bounds, eligible cash and counter-accounts, payment balance, posting, reversal integrity, duplicate void prevention, and audit records.
- Issuance form sends the server-defined payload (action=issue_voucher, voucher type/date/amount, cash account, counter-account, party, description, optional reference). The void dialog owns its TextEditingController and disposes it with its own widget state; this was corrected after CI exposed premature disposal during the dialog exit transition.
- Disposable MariaDB API integration passed in run 40366312607: issuance, balanced posting, void reason validation, balanced reversal, duplicate-void rejection, role denial, and cleanup.
- Flutter analysis and all 138 widget tests passed, including issue and void interaction tests, in run 40369415336. The Android debug APK built and was published in the same run from app-source commit 69bb50391c3509906279e526f4a3a12aed2a25fd; artifact ahl-el-kheir-flutter-android-debug-apk, ID 12672886503, 102,394,809 bytes, expires 2026-11-25. Workflow: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40369415336
- Current main test-only follow-up is 39f8c20baade5d7209c6928da74edaed51443633; its Flutter analysis and all widget tests also passed in run 40369875925. That run's redundant APK build subsequently completed successfully. Workflow 40369875925 published artifact `ahl-el-kheir-flutter-android-debug-apk` (artifact ID `12671918522`, 102,395,467 bytes; expires 2026-11-25). Its source commit is `39f8c20baade5d7209c6928da74edaed51443633`; the current main head is `91f42d83988c42c7664c85bf7099891440901c65`, and the salary-advance verification workflow also passed at that head. These are CI results only, not local XAMPP/API or physical-device acceptance. App Dart source is unchanged from the successful APK build above.
- No local XAMPP/API/database or physical-device acceptance is claimed. Do not ask the user to test until the latest workflow result is checked and final pull/install instructions are ready.


## 2026-10-26 — CI trigger noise reduction

- Root cause: `.github/workflows/php-syntax-check.yml` (workflow display name `Salary advance verification`) triggered on every push to `main` and every pull request, including documentation-only commits. This created redundant parallel verification runs during documentation synchronization.
- Fixed in commit `e34aa69b477d76783df90b6d52f87808351520ed`: both push and pull-request triggers now filter for PHP/API/module/tool/config/database/language/assets and relevant root PHP/SQL/config changes, including the workflow file itself. Manual `workflow_dispatch` remains available.
- Existing runs already started before this trigger change were not cancelled by this edit and may still finish. The new filter prevents documentation-only commits from starting more PHP verification runs. Do not claim the active-run queue is cleared until the Actions page confirms it.


## 2026-10-26 — Native accounting disbursements route

- Audited the shared native-tool route list against `mobile/ahl_el_kheir_app/lib/main.dart`. The role tools exposed `modules/accounting/disbursements.php` as a native tool, but `openNativeTool()` had no corresponding route, so selecting it showed the generic “not available natively yet” message instead of opening the already-implemented native disbursement page.
- Fixed in commit `4444397352d8cb95316e43eb76e5f10e355fb207`: the route now calls `openAdminDisbursements()` and applies the same explicit allowed-role list as the existing `disbursements` route key (`admin`, `financial_manager`, `general_manager`, `accountant_staff`, `nanny`). The API remains authoritative for scoped data and operation capabilities.
- Android workflow `40372872420` later completed successfully for this exact source commit; details are recorded in the verification entry below.
- CI noise fix: commit `e34aa69b477d76783df90b6d52f87808351520ed` limits PHP verification to relevant source paths; the workflow itself passed in run `40372688782`. Documentation-only commits no longer match its trigger paths.


## 2026-10-26 — Native disbursements build verification completed

- Android workflow `40372872420` completed successfully for source commit `4444397352d8cb95316e43eb76e5f10e355fb207`: Dart analysis PASS, Flutter tests PASS, debug APK build PASS, artifact upload PASS.
- Artifact: `ahl-el-kheir-flutter-android-debug-apk`, ID `12673971537`, size `102,393,732` bytes, expires `2026-11-25T18:00:37Z`. [Workflow run](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40372872420).
- Build log confirms the APK was compiled with `API_BASE_URL=http://192.168.18.9:8081/AhlElKheir/api/v1` and `MOBILE_BUILD_REVISION=4444397352d8cb95316e43eb76e5f10e355fb207`. The artifact also contains `mobile-build-info.txt` with build provenance.
- This verifies CI build/test only. It does not prove that `192.168.18.9:8081` is reachable from the user's phone, that Apache/API/database are healthy, or that on-device sign-in succeeds. Do not describe those as tested.


## 2026-10-26 — Native personal-profile tool routing

- Confirmed `mobile/ahl_el_kheir_app/lib/profile_page.dart` and authenticated `api/v1/account/profile.php` / `api/v1/account/avatar.php` already implement personal profile editing and photo upload/removal. However, the staff role-tool tile used `modules/users/profile.php`, which was not marked native and had no matching `openNativeTool()` route; tapping it therefore fell through to the native-pending notice despite the existing page.
- Fixed in source commits `3f5ea08337788168e9dee84f56c959137be2b7d7`, `22467e9a7be468824a4a303ea2dd76159345c9ce`, and `535c6d4899537f8ffe2aea2e5687ff1c5712fc22`: mark the profile tool IN APP, route it to `openMyProfilePage()`, and add a widget regression test for the label and callback.
- Flutter analysis and widget tests completed successfully in workflow run `40375023649` for final source commit `535c6d4899537f8ffe2aea2e5687ff1c5712fc22`; the Android debug APK build was still in progress at the time of this update. A redundant earlier run `40375002234` was triggered by the intermediate per-file commit and also passed analysis/tests; do not distribute its APK because it predates the final regression test.
- Do not claim the final APK build passed until run `40375023649` completes. No local XAMPP/API or physical-device acceptance is claimed.

## 2026-10-27 — Native profile APK verification and role-tool audit

- Android workflow [40375023649](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40375023649) completed successfully for source revision `535c6d4899537f8ffe2aea2e5687ff1c5712fc22`: Dart analysis PASS, all Flutter widget tests PASS, debug APK build PASS, provenance recording PASS, and artifact publication PASS.
- Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12675101498`, size `102,389,889` bytes, SHA-256 `bc89c3eaed4bbb4c2d0b84aac0dac0c1b09f803ac587884660734d10104659d6`, expires 2026-11-25. Build provenance confirms API base URL `http://192.168.18.9:8081/AhlElKheir/api/v1` and source revision above.
- The profile role-tool routing fix is now fully CI-verified. Do not distribute the earlier intermediate artifact from workflow `40375002234`; it predates the profile-route regression test.
- Continued role-tool audit found 17 distinct role-tool paths intentionally not marked native: GM reconciliation, sponsor win-back, incentive policy, GM salary-advance waiver, deputy-GM groups, opening balance, assigned nannies, chart of accounts, FM ordinary transaction review, salary portal, group disbursements, Fina payment report, nanny returned funds, available families, contract builder, payroll reversal, and project create/edit form. Source review confirmed that superficially similar existing screens are not automatically equivalent: for example, the native chart-of-accounts endpoint is admin-only while the web accounting account page is offered to finance roles; the existing native FM review queue corresponds to a different workflow than ordinary transaction review; and the general families/project pages do not implement the exact available-family assignment or project form flows. These remain NOT READY until exact API behavior, authorization, and workflows are implemented and tested. Do not mark them native based on name similarity.
- The login screen already supports editing the API base URL and testing it; the probe distinguishes unreachable host/port, invalid API path, non-JSON response, and server errors. The latest APK still defaults to the LAN endpoint above. A successful cloud build does not prove that this IP is current/reachable from the user's phone or that local Apache/PHP/database are healthy. Local XAMPP/API and physical-device acceptance remain unverified.

## 2026-10-27 — Native GM reconciliation implementation

Implemented the native GM reconciliation workflow in the existing Flutter app. Source: `api/v1/accounting/gm_reconciliation.php`, `mobile/ahl_el_kheir_app/lib/gm_reconciliation_page.dart`, and native routing in `main.dart`.

- The endpoint is bearer-authenticated and GET-only; it returns posted-ledger monthly totals, monthly disbursement totals, open batches, returned items, and voided batches without modifying financial records or performing DDL.
- Server authorization follows the existing report registry: administrator, General Manager, Vice General Manager, and Financial Manager. Accountant visibility was removed from the role-tool list because the existing web guard denies that role access.
- Workflow run [40450633032](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40450633032) passed PHP 8.2 syntax and disposable-MariaDB integration checks: General Manager and Financial Manager reads passed, non-GET requests returned 405, and Accountant access returned 403.
- Flutter workflow run [40450764757](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40450764757) passed analysis, all 140 widget/unit tests, Android debug APK build, provenance recording, and artifact publication for app source revision `82fd2570793e794b99fe9bf55f878596107b124c`.
- Artifact: `ahl-el-kheir-flutter-android-debug-apk`, ID `12710033148`, SHA-256 `87a816236f9648087c4bef0b3822947f7e2a98e53ccf4f4cb734df8ea936ff47`, expires 2026-11-26.
- No database migration or schema change was made. Local XAMPP/API and physical-device acceptance remain unverified; a successful CI build does not prove the phone can reach the configured LAN API.
- Remaining native gaps from the prior role-tool audit are still open; do not label a role-tool IN APP without equivalent authorization and behavior tests.

## 2026-10-27 — Native sponsor win-back implementation

Implemented sponsor win-back in the existing Flutter app with authenticated API support. Sources: `api/v1/administration/winback.php`, `mobile/ahl_el_kheir_app/lib/sponsor_winback_page.dart`, and native routing/role-tool updates.

- Preserves the web workflow: eligible lapsed-sponsor queue (90-day rule), open follow-up, contact history, decline/reopen transitions, successful reactivation with actual employee attribution, previous-family coverage reference, and available-family quick reference.
- Server-side access follows the existing web guard: admin, General Manager, Vice General Manager, and administration. Accountant access is denied. Reactivation reuses `wb_mark_returned()`; the immediate incentive activity notice is attempted after the successful transition and notification failure is isolated.
- API integration run [40452694704](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40452694704) passed PHP syntax and disposable-MariaDB integration: role allow/deny, queue eligibility, duplicate-open rejection, contact/status update, case detail, decline/reopen, successful reactivation attribution, and duplicate-return rejection.
- Flutter run [40452694443](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40452694443) passed analysis, all 141 tests, Android debug APK build, provenance recording, and artifact publication for source revision `d6cab6772b3e2683434a1bb927d62ec55c5be9c6`.
- Artifact: `ahl-el-kheir-flutter-android-debug-apk`, ID `12710904687`, SHA-256 `eaf1ecc57262b1a71329dbcdb85dfb23121d478912a81013b6d3225df3e71fd8`, expires 2026-11-26.
- No database migration or runtime DDL was added. Local XAMPP/API and physical-device acceptance remain unverified.


## 2026-10-27 — Waiver widget-test failure diagnosis

Flutter run #607 completed with Dart analysis passing, but widget tests failed (148 passed, 2 failed), so APK build/provenance/publication were skipped. The exact failures are in `salary_advance_waiver_page_test.dart`: the GM test tapped the approval action while its card control was outside the test viewport, and the FM test looked for the execution action before scrolling the long preparation form/decision list into view. Source inspection shows the production page places decision cards after the introductory panel and, for FM, the preparation form; these are `ListView` children and later controls may not yet be built by the lazy viewport. The narrow correction is to use `scrollUntilVisible` for the actual GM approval and FM execution controls before tapping/asserting them. No production UI, API, or financial service logic is changed. Await the next sequential Flutter run before claiming test/build success; do not overlap runs or rerun closed financial waiver harnesses.


### 2026-10-27 — Flutter GM attention queue (CI pending)

The latest main checkpoint is `51a0a053135020bd339b0e4b61f816f01f748481`; APK workflow run #608 passed. The next mobile parity slice adds a native GM attention queue using only metrics already exposed by the authenticated dashboard API. Pending-family attention remains explicitly incomplete because the API does not yet return the existing `pending_families` query value. Preserve the distinct app theme, native-only navigation, and sequential workflow verification.

## 2026-10-27 — GM pending-family API metric (backend stage)

The mobile dashboard API now returns the existing `pending_families` count already selected by the General Manager overview query, using the web dashboard's `families.status='pending'` definition. The dedicated role-dashboard query harness asserts the metric is present exactly once and numeric, and the Mobile API Auth Integration workflow runs that harness against disposable MariaDB. The native Flutter attention card remains the next sequential step; full parity is pending until that step passes CI.

## 2026-10-27 — GM pending-family attention card (Flutter CI pending)

Mobile API Auth Integration run #218 (40469806870) passed after adding the existing 'pending_families' count to the authenticated General Manager metric response. The Flutter attention queue and widget test now include an actionable pending-family card routed to the existing operational report. Await the sequential Android workflow before claiming UI/build verification. Local XAMPP/API and physical-device acceptance remain pending.

## 2026-10-27 — Flutter GM attention widget-test selector correction (CI pending)

Android workflow #610 (run ID 40470131963) failed in the new GM attention test, not during Dart analysis or APK compilation. The exact failure was `Bad state: Too many elements` from `tester.ensureVisible(find.text('Families awaiting action'))` because the text finder matched more than one widget. The test now selects the matching `ListTile` ancestor and uses that tile for visibility and tap, avoiding ambiguous text selection. No production app behavior changed in this correction. Await the next sequential Flutter workflow before claiming the test/build passes; do not push another code change until it finishes.


## 2026-10-27 — Native VGM orphan-group management implementation submitted for CI

The next mobile parity slice adds a bearer-authenticated API at `api/v1/deputy_gm/groups.php` and a native Flutter screen at `mobile/ahl_el_kheir_app/lib/orphan_groups_page.dart`. The existing VGM role tool now dispatches to this screen and is marked IN APP in the source. The API authorizes only the canonical Vice General Manager role; the UI role label is not treated as authorization.

The implementation follows the existing `modules/deputy_gm/groups.php` workflow and the checked-in `orphan_groups`, `group_children`, `nanny_group_assignments`, and `monthly_disbursements` schema. It adds no migration, runtime DDL, or financial logic. It preserves group/member/nanny history, blocks member changes when a current-month disbursement exists, requires active-family/active-sponsorship eligibility for additions, keeps the transfer history, and allows permanent deletion only for never-used groups. Group lifecycle actions are restricted to the actions exposed by the existing web UI.

Regression coverage has been added for VGM-only API access, list/detail, draft creation/edit, rejecting activation of an empty group, deleting a never-used draft, the native role-tool route, group/member display, and the current-month disbursement UI lock.

**CI status (2026-10-27):** Flutter APK workflow #615 passed Dart analysis, Flutter tests, and debug APK build on commit `bb929687b16cbec1fa66d2fe8b39adb7e73d9d90`. Mobile API auth integration #221, salary advance verification #3534, and Sponsor QR Scanner APK #214 passed on preceding commit `6e82f4b36c79c9cf47737a9a94a44fb7a423ac9b`; those workflows were not rerun by the Flutter-only follow-up commit. CI does not establish local XAMPP/API or physical-device acceptance. The existing group workflow and financial acceptance boundaries have not been changed.


## 2026-10-27 — Native VGM group screen fixes verified in Flutter CI

The initial VGM group screen CI run (#612) exposed three test failures. The first focused correction moved group create/edit text controllers into the dialog-owned StatefulWidget lifecycle and corrected the ambiguous current-members label matcher. The repaired tree was checked after the follow-up commit to ensure the API endpoint and feature files remained present.

Flutter APK run #614 then narrowed the remaining failure to a stale test assertion: the implementation renders “Member history”, while the test expected “Membership history”. Source review also confirmed a web/mobile parity gap: closed/terminal groups could return current members from the API but the Flutter UI hid the entire member card when member changes were prohibited.

Commit `bb929687b16cbec1fa66d2fe8b39adb7e73d9d90` fixes the assertion and keeps current group members visible in read-only mode. Nanny assignment, member transfer/removal, and add-member controls remain conditional on member editability; member popup actions also remain hidden under the current-month disbursement lock. A widget regression test covers a closed group with a visible historical member and no mutation controls.

**Verification: PASS in Flutter APK workflow #615** — Dart source analysis, Flutter tests, and debug APK build all completed successfully. Earlier commit `6e82f4b36c79c9cf47737a9a94a44fb7a423ac9b` also had Mobile API auth integration #221 PASS, salary advance verification #3534 PASS, and Sponsor QR Scanner APK #214 PASS. Those workflows were not rerun for the Flutter-only follow-up commit. Local XAMPP/API acceptance and physical-device acceptance remain unverified; do not claim them. Next step: inspect the current role-feature parity backlog and proceed with the next uncompleted, evidence-backed mobile feature only after reviewing the current source and schema. Do not repeat closed payroll/HR acceptance suites without regression evidence.


## 2026-10-27 — Mobile notification navigation and hosting boundary

Source commit: `e2e3873cb2a2650f373f155dbdd770bed23e1e83`.

- Corrected the notification tap flow so the native detail screen opens without waiting for the mark-as-read request to succeed. A read-state failure is reported after returning to the inbox; it no longer blocks access to the notification content.
- Notification details now expose an in-app destination action for module-path links. The app resolves the path to an existing native route and does not launch arbitrary external URLs or a browser. Unsupported native destinations remain a mobile feature-parity gap; the notification details still open.
- Added regression tests for mark-read failure, inbox refresh after returning, supported module-path parsing, rejection of arbitrary external paths, and the related-page callback.
- Verification: Flutter analysis, the full Flutter widget test suite, Android debug APK build, and artifact publication all passed in workflow [#622](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40500061042). Artifact: `ahl-el-kheir-flutter-android-debug-apk`, ID `12733350066`, archive SHA-256 `d02aa283ffc3b53742458bb0be1cd9ce62247714247c318ff6e088dfbf0eee50`, expires 2026-11-26. This is CI evidence only; no physical-device verification is claimed.
- Deployment boundary: this artifact is a **debug build** configured for local testing at `http://192.168.18.9:8081/AhlElKheir/api/v1`. XAMPP remains development-only and must not be exposed publicly. An online/release build must be configured with the actual deployed HTTPS API base URL, and that host must be tested with native API requests before release. Do not assume a hosting provider supports mobile API traffic merely because the website loads in a browser; do not bypass hosting verification controls.
- Next acceptance: install this artifact on the test phone and verify tapping both read and unread notifications, opening a supported related native destination, returning to the inbox, and mark-read failure behavior. Before online release, select and verify the API-compatible HTTPS host. Do not rerun unrelated closed HR/payroll/waiver suites.


## 2026-10-27 — Notification details remain in-app; link actions removed

Source commit: `82a04b7d9daac51b657c6b7cba6c39c2ecb632db`.

- Notification tap opens the native notification details screen containing title, timestamp, and message body.
- Removed the related-page button/card and the notification-specific link parsing/routing callback. Notification payload links are not presented as actions and do not navigate anywhere from the detail screen.
- Preserved the independent mark-as-read behavior: a read-state request failure does not block details; returning to the inbox refreshes it and reports the failure.
- Added/updated a widget test with a notification that has a link value and asserts the details screen is shown with no related-page action or link label.
- Workflow #623 passed Dart analysis, Flutter tests, Android debug APK build, provenance and artifact publication: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40503179161. Artifact ID `12733593790`; SHA-256 `a2d93c054e2969e639618e0cc568bb27161011a2e05b9c16a6d3a1f920542013`; expires 2026-11-26.
- Hosting boundary unchanged: XAMPP is development-only. This debug build targets the local test API; the eventual release must use a verified deployed HTTPS API endpoint. No physical-device acceptance or production-host verification is claimed.


## 2026-10-27 — Native suspension creation and notification-detail verification

- Notification details-only follow-up is verified: workflow [#623](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40503179161) passed Dart analysis, the full Flutter widget-test suite, debug APK build, provenance and artifact publication for source `82a04b7d9daac51b657c6b7cba6c39c2ecb632db`. Artifact ID `12733593790`; archive SHA-256 `a2d93c054e2969e639618e0cc568bb27161011a2e05b9c16a6d3a1f920542013`; expires 2026-11-26.
- Native suspension creation now covers searching families/children, selecting one, choosing one of the web workflow's existing reasons, optional notes, confirmation, duplicate protection, nanny scope checks, and family/child linked sponsorship pausing. Existing case-list/lift behavior remains intact.
- Initial Flutter workflow #625 failed one create-dialog widget test: the dialog overflowed by 22 pixels and its disposed `TextEditingController` was still attached. The source was corrected in commit `1545496e0caaee0371de81373f30f63a45fb4f63` by removing the unsafe controller lifecycle and making the dialogs scrollable.
- Verification: Mobile API auth integration workflow [40507040144](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40507040144) passed PHP lint, the rollback-only full-schema suspension service harness (including generic child/family creation, duplicate and nanny-scope guards), and API integration on source `adaef343c9fa23d53511ab62ba4f1a0b395f686c`. Flutter workflow [#626](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40508054070) passed Dart analysis, all Flutter tests, Android debug APK build, provenance and artifact publication for source `1545496e0caaee0371de81373f30f63a45fb4f63`. Artifact ID `12736527104`; archive SHA-256 `3a3dc777f1ec4ceff90812675d1266f25bff760296063cdcbf6ed3341403389d`; expires 2026-11-26.
- These are CI/disposable-database results only. Local XAMPP/API acceptance and physical-device testing remain for the user. The debug APK still targets the local development API; do not expose XAMPP publicly. Release requires a verified HTTPS API host.



## 2026-10-27 — Native assigned-nanny overview verified

- Added native coverage for modules/accounting/my_nannies.php through api/v1/accounting/assigned_nannies.php and Flutter AssignedNanniesPage. The page supports month selection, nanny summary, active-family detail, bank/contact information, month-specific verification flags/notes, and latest monthly disbursement status.
- Authorization follows the existing web guard: admin, accountant, accountant_staff, and financial_manager. Accountant Staff results and detail reads are scoped to accountant_nanny_assignments. The endpoint uses the checked-in schema directly and does not invoke the runtime schema-creation helper or add a migration.
- Code commits: 204e74d1af2e129a690ab32e4328b24f1f91951f; Dart interpolation correction 2499189543b039f0a1bc8e4c36c3d92a6b7bd249; state callback analyzer correction 77e4926a620b57d7f222b91b39811722f6e8f201.
- Verification PASS: Mobile API auth integration run #225 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40513189589) exercised unauthenticated denial, admin summary/detail, and Accountant Staff scope. Flutter APK run #629 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40513730679) passed Dart analysis, all Flutter tests, Android debug APK build, provenance, and artifact publication. Artifact ID 12737979531, SHA-256 a2c819e4d0cd5afe8edd20b56b42ca33c12c56f78a24aefda151398d1b1fb7ca, expires 2026-11-26.
- CI uses disposable MariaDB; local XAMPP/API and physical-device acceptance remain for the user. The debug APK targets the local development API; do not expose XAMPP publicly. Online release requires a verified HTTPS API endpoint.


## 2026-10-27 — Native available-families list verified in CI

- Added native Flutter listing for `modules/administration/available_families.php`, with in-app family detail navigation. The API returns only families whose status is `active` or `pending` and which have no active sponsorship linked through `family_children`, matching the existing web eligibility query. It is read-only; no sponsorship assignment or accounting mutation was added.
- Role boundary verified: admin, general_manager, vice_general_manager, and administration may list available families. The mobile API integration test checks unauthenticated denial, Administration access and family-detail access, and denial for financial_manager. The test compares eligible ID sets because the web query does not define a deterministic tie-breaker for equal sort values.
- Flutter workflow [#630](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40515806830) passed analysis, all Flutter tests, Android debug APK build, provenance, and artifact publication. Artifact ID `12739941941`; SHA-256 `26cc42dd41e8616f409776dcd16f3a126d362c86e98df638013c96de117fac59`; expires 2026-11-26.
- Mobile API integration workflow [#228](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40516194914) passed on `952fcff2e147b91e73dfcaedf979ecb7dda15b0d`. This is disposable-MariaDB CI evidence only; local XAMPP/API and physical-device acceptance remain for the user. Debug builds target the local development API; do not expose XAMPP publicly. Online release requires a verified HTTPS API endpoint.
- Next: inspect the remaining current `RoleToolsSection` NOT READY paths and their exact web guards/schema before selecting the next slice. Do not assume that every role tool is read-only or safe to port.

## 2026-10-27 — Transaction receipt preview source repair verified
The first Flutter receipt-preview attempt introduced a malformed insertion in `mobile/ahl_el_kheir_app/lib/main.dart` and misplaced the receipt viewer class; workflow #633 failed Dart analysis. Rebuilt `main.dart` from its pre-feature parent and reapplied only the receipt loader and route wiring. Corrected source commit `2a932eeb1a08df2439643fa011f676c1e9b072ac`. Workflow #635 (`40529923422`) passed Dart analysis, all Flutter tests, Android debug APK build, provenance and publication. Artifact `12746676243`, SHA-256 `d1e8d4681bb65324598cac8eda122ae9844acccce457c5a92fdcc0789fe74832`, expires 2026-11-26. This remains CI evidence; local API and physical-device acceptance are pending.

## 2026-10-27 — Native payroll portal hub verified
Added `SalaryPortalPage` and native routing for `modules/hr/salary_portal.php`. Role exposure now matches the portal's existing web guard: `financial_manager`, `admin`, `fm`, `hr_manager`, and `hr_staff`; the legacy `finance` alias is not offered this route. The hub opens supported payroll/waiver/policy screens inside the app and explicitly disables the salary-advance dashboard while that destination remains unimplemented. The admin incentive-policy card is also disabled because the destination page's current web guard denies admin access; no permission was broadened. First workflow #636 failed a role-tool test because the hub was only exposed to FM; corrected in `8dccf561766237eff8c9b361515818a93d4da452`. Workflow #637 / run `40532657459` passed analysis, all Flutter tests, debug APK build, provenance and publication. Artifact `12747371626`, SHA-256 `460cef9f4a5ba3214c8a2aa53b45eb3e90ff9e738affcdb1ed2caa1b0040df43`, expires 2026-11-26. Next inspect the existing salary-advance dashboard source/guard before implementing its native page.


## 2026-10-27 — Native salary-advance dashboard verified

The native dashboard for `modules/hr/salary_advance_dashboard.php` is implemented and verified. It displays the four existing request-status counts and processed/previous request history in-app, with refresh/retry behavior and no external navigation or mutation actions. The API `GET api/v1/hr/salary_advance_dashboard.php` mirrors the existing web guard (`financial_manager`, `admin`, `fm`), rejects unauthenticated calls, and returns the existing accounting-history query plus matching status counts. Disposable-MariaDB integration verified summary/history values, Financial Manager access, and nanny denial.

API commit `d3fe5ab1a7bfdd1945aa88c6c053cdf14aa3ff01`; Flutter commit `3a3a02df609dc9fbd1a1e881ff15504283a2cdb6`; final widget-test correction `e75174f6e776065440768705f7c9277872d5c0c5`. API integration run [40535893510](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40535893510) passed. Flutter run [40537946711](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40537946711) passed Dart analysis, all Flutter tests, debug APK build, provenance and publication. Artifact ID `12748619953`; SHA-256 `1b1c4713863790bb8fa6dc043318fc4c25a5d6f34bcc98f5ed5fff8a4ae220cd`; expires 2026-11-26. CI/disposable-database evidence only; local XAMPP/API and physical-device acceptance remain pending. Debug APK targets the local development API; do not expose XAMPP publicly. Release requires a verified HTTPS API host.

Next: inspect current `RoleToolsSection` NOT READY items and their actual web guards before choosing one narrow feature. Do not treat native salary-advance request processing, policy management, or rescheduling as implemented by this read-only dashboard.


## 2026-10-27 — Trial Balance test and group-disbursement route verified

Flutter workflow [#646](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40544549643) completed successfully on `e53e886044b52e035e167c1a6db64f3cd81ce12c`: Dart analysis, all Flutter widget tests, Android debug APK build, build provenance, and artifact publication passed. This closes the Trial Balance widget-test scrolling correction and verifies that the Accountant Staff legacy route `modules/accounting/group_disbursements.php` dispatches to the existing native disbursement screen, with role-tool regression coverage. Artifact ID `12752901569`, SHA-256 `1e70ff5a83ac60fd3c33677c2b6c8b732163fcf67f94ad467f4b9f3d4b632cd9`, expires 2026-11-26.

This is GitHub CI evidence only; local XAMPP/API and physical-device acceptance remain unverified. The debug APK targets the local development API. Do not expose XAMPP publicly; an online release requires a verified HTTPS API host. Next, continue with one remaining role-tool parity gap only after reviewing the actual web guard, source workflow, and schema. Avoid further Trial Balance reruns without regression evidence.


## 2026-10-27 — Mobile Chart of Accounts role parity (CI pending)

The native Flutter Chart of Accounts route now matches the web page's role boundary: admin and Financial Manager can manage accounts; General Manager and Vice General Manager can view accounts/balances without mutation controls. Their in-app role tools route to the native page without opening a browser. The Flutter widget test now scrolls the status action into view before tapping; the disposable full-schema API harness covers role-specific list capability and GM/VGM mutation denial. Await CI before calling this milestone verified. Local XAMPP/API and physical-device acceptance remain unverified.


## 2026-10-27 — Native Chart of Accounts role parity verified in CI

Commit: `6550c9bcbed841aa631097ca9d178f7e3ab0906a`.

- Flutter workflow [#647](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40551368392) passed Dart analysis, all Flutter widget tests, Android debug APK build, provenance and artifact publication.
- Mobile API integration workflow [40551368548](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40551368548) passed full-schema role checks: Financial Manager, General Manager and Vice General Manager can read the chart; GM/VGM mutation requests are rejected with HTTP 403.
- APK artifact ID `12754229320`, size 102,601,768 bytes. Debug-only and configured for the local development API.
- No schema or accounting rules changed. Local XAMPP/API and physical-device acceptance remain unverified; do not expose XAMPP publicly. Online release requires a verified HTTPS API host.


## 2026-10-28 — PHP work resumed; Flutter development paused

The user has paused Flutter/native-app development to resume the existing PHP system. PHP/backend findings and acceptance gaps discovered during mobile development are tracked in [`docs/PHP_SYSTEM_REMEDIATION_BACKLOG.md`](PHP_SYSTEM_REMEDIATION_BACKLOG.md) and GitHub issue [#96](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/96). The backlog distinguishes confirmed source-level permission inconsistency from runtime/deployment verification gaps and items requiring re-triage. Do not treat missing native screens as PHP defects or reopen closed PHP acceptance without regression evidence. Read the backlog alongside the four canonical project documents before selecting the next PHP task.


## 2026-10-28 — PHP backlog triage: salary portal navigation and test guard

- Fixed the salary portal's incentive-policy link visibility: `modules/hr/salary_portal.php` now shows the link only to `financial_manager`, matching the destination guard in `modules/hr/incentive_policy.php`. Commit: `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9`. This does not broaden page permissions; real-browser acceptance remains pending.
- Re-triaged PHP-06: current `.github/workflows/mobile-api-auth-integration.yml` sets `MOBILE_API_AUTH_TEST=1` and `AHL_DB_NAME=mobile_api_auth_test` for the guarded test harness and provisions the matching disposable database. Marked resolved in `docs/PHP_SYSTEM_REMEDIATION_BACKLOG.md`; no production change needed.


## 2026-10-28 — HR job-description migration CI fixture correction

Workflow [#40632821897](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40632821897) exposed a stale test setup: the checked-in schema already contains the post-migration `system_role_code` column and role-aware unique index, while the HR migration job then tried to apply the historical migration that expects the old title-only index. The workflow fixture was corrected in commit `daa821f1d607aa74d5efd90227e47567043c5ec1` to recreate the catalogue table only inside the disposable CI database before applying the migration sequence. Verification is pending run [#40633086558](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633086558). Tracked as PHP-07 in the remediation backlog.


## 2026-10-28 — PHP workflow verification: HR migration fixture passed

Workflow [#40633086558](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633086558) completed successfully. All five jobs passed: PHP lint, HR job-description migrations, MariaDB 10.4 migration, rescheduling service tests, and full-schema migration/regressions. PHP-07 is closed as a CI fixture issue in `docs/PHP_SYSTEM_REMEDIATION_BACKLOG.md`. This does not replace local HR browser/PDF acceptance.


## 2026-10-28 — Project-team route cleanup verified in CI

Commit `0e67f8f63682d8370453f1dc35f135b6784ea3a9` removed stale, unrendered project-team assignment/unassignment POST handlers from `modules/projects/view.php`; `modules/projects/form.php` remains the supported edit-page mutation route. Workflow [#40633372107](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633372107) passed all five jobs, including PHP lint. PHP-05 remains open for real-browser team UI acceptance and incentive-notification browser acceptance; do not mutate live project/incentive data to test.


## 2026-10-28 — Changed PHP files explicitly linted

Workflow [#40633600907](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633600907) passed all five jobs. The PHP lint job explicitly includes changed `modules/hr/salary_portal.php` and project team route pages `modules/projects/form.php` and `modules/projects/view.php`; logs confirmed no syntax errors for all three. Full-schema migration, HR job-description migration, MariaDB 10.4 migration and rescheduling-service jobs also passed. Browser/runtime acceptance remains open where recorded in the PHP backlog.

## 2026-10-28 — PHP API lint inventory and coverage expansion

A read-only inventory found 111 `api/v1/*.php` files, but `.github/workflows/php-syntax-check.yml` explicitly linted 74. Commit `c4c212980d1a1677729db94d2b6815a07aba0289` adds the remaining 37 files to the lint list. Workflow [#40634508587](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40634508587) was queued at last check; verify its actual result before calling the change CI-verified. PHP-01 local runtime acceptance remains open. Flutter/native work is paused; do not run mutating API integration tests against live `ahl_el_kheir`.
## 2026-10-28 — PHP API lint coverage verified


The API lint coverage expansion is verified. Commit `c4c212980d1a1677729db94d2b6815a07aba0289` added the 37 previously omitted API files so all 111 `api/v1/*.php` files are in the explicit PHP lint list. Workflow [#40634508587](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40634508587) passed all five jobs, including `php-lint`. PHP-01 local API runtime acceptance remains open; CI syntax success is not local runtime acceptance.

## 2026-10-28 — Original PHP system audit: runtime DDL found


While returning to the original PHP system (not Flutter UI files), source inspection found that `modules/users/index.php` runs `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` during page requests and also performs data-seeding updates. Logged as PHP-08 in `docs/PHP_SYSTEM_REMEDIATION_BACKLOG.md`. No code was changed yet: inspect `database/ahl_el_kheir.sql` object definitions and dependent behavior before creating migration(s) and removing runtime DDL. Do not delete statements blindly.


## PHP system remediation checkpoint — 2026-10-28

Flutter/native development remains paused. The original PHP website is active scope. PHP-08 source remediation is committed on `main`: `modules/users/index.php` no longer creates/alters schema or seeds/updates role and department reference data during page requests. Those operations are in `database/migrations/2026-10-28_user_management_runtime_schema_cleanup.sql`. The uploaded checked-in schema was inspected before editing and confirmed the existing objects/columns, including the extra `family_documents` fields. CI verification is pending; do not treat the item as closed until the migration test passes.


## PHP website checkpoint — 2026-10-28

PHP-08 user-management runtime DDL cleanup is source- and CI-verified on `main`. The new migration was applied twice against the checked-in full schema in GitHub Actions run [#40640508040](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40640508040); all five jobs passed, including PHP lint and the full-schema migration/regression job. This does not replace browser acceptance on the user's actual local XAMPP instance. Next step is a targeted User Management browser smoke test after pulling the commits, then continue the original PHP backlog without resuming Flutter work.


## 2026-10-28 — Follow-up PHP runtime-DDL audit

PHP-08 user-management remediation is verified by GitHub Actions run [#40641855323](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40641855323): all five jobs passed, including the legacy-shape migration test. A read-only scan of all 206 PHP files under `modules/` found further confirmed runtime DDL in the family workflows (PHP-09), accounting libraries (PHP-10), and profile/import/sync/receipt/payroll-reversal pages (PHP-11). These are logged in `docs/PHP_SYSTEM_REMEDIATION_BACKLOG.md`; they are separate open items and must be reconciled against the checked-in schema before changes. Continue on the original PHP website; Flutter/native work remains paused.


## 2026-10-28 — Family schema cleanup in progress

PHP-09 remediation is committed on `main` as `599ad25302df14af3e7d0fb034fa3ddc35214c41`: schema additions used by family creation/editing, orphan forms and document uploads now live in `database/migrations/2026-10-28_family_runtime_schema_cleanup.sql`, and the four pages no longer perform request-time DDL. CI now lints the pages and tests migration repeatability from a disposable legacy-shaped schema. CI is pending; do not claim runtime acceptance until verified. PHP-10 and PHP-11 remain open findings.


## 2026-10-28 — Family schema migration CI verified

PHP-09 source cleanup is CI-verified by GitHub Actions run [#40642297829](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40642297829): all five jobs passed. The full-schema job applied the family migration twice and then simulated missing family/child columns and the two family tables in the disposable database; migration restoration and the unique form-serial index passed. This is not local browser acceptance. PHP-10 (accounting libraries) and PHP-11 (other pages) remain open.


## 2026-10-28 — Accounting core schema cleanup in progress

PHP-10 is committed on `main`: `modules/accounting/lib.php` and `modules/accounting/lib_admin_fee_policy.php` no longer perform request-time DDL; `database/migrations/2026-10-28_accounting_core_runtime_schema_cleanup.sql` owns the schema. CI applies it twice against the checked-in schema and tests table creation in a separate disposable database with only prerequisite base tables. The existing `ak_seed_accounts()` accounting data initialization was deliberately left unchanged. PHP-12 tracks the more complex outflow schema sync separately; its ENUM/data semantics must be reconciled before editing. CI for PHP-10 is pending.


## 2026-10-28 — PHP runtime-DDL cleanup checkpoint

PHP-10 accounting core cleanup is CI-verified by run [#40643161875](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40643161875): all five jobs passed, including migration repeatability on the checked-in schema and table creation in a separate blank database. PHP-11 cleanup is now committed on `main`: profile, sponsor-import, sponsor-sync, transaction receipt, and payroll-reversal pages no longer perform schema DDL; migration `database/migrations/2026-10-28_other_php_runtime_schema_cleanup.sql` owns the required columns/tables. CI verification is pending. PHP-12 outflow schema sync remains open because its status ENUM and dynamic-column behavior need a separate evidence-based migration plan.


## 2026-10-28 — PHP-11 migration CI verified

PHP-11 source cleanup is CI-verified by GitHub Actions run [#40643694231](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40643694231): all five jobs passed, including PHP lint, full-schema application, and legacy-shape restoration for the profile, sponsor import/sync, receipt, and payroll-reversal schema. PHP-08 through PHP-11 now have source/migration CI evidence; local browser acceptance remains pending. PHP-12 outflow schema synchronization is the next source finding, and its ENUM/data behavior must be audited before changes.


## 2026-10-28 — Accounting outflow schema cleanup in progress

PHP-12 is committed on `main`: `modules/accounting/lib_outflows.php` no longer creates/changes schema during requests. Migration `database/migrations/2026-10-28_accounting_outflows_schema_cleanup.sql` restores the canonical tables, missing group/return columns, group foreign key/index, and status ENUMs. The legacy `pending` batch status is normalized to `pending_approval` because the source explicitly treats those states as equivalent for approval, and the web/API transitions use `pending_approval`; CI tests this with a disposable legacy fixture. CI verification is pending. PHP-08 through PHP-11 have passed their source/migration CI checks; local browser acceptance remains outstanding.


## 2026-10-28 — PHP-12 outflow migration CI verified

PHP-12 is source- and CI-verified by GitHub Actions run [#40644551812](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40644551812): all five jobs passed. The full-schema job applied the migration twice; a separate disposable legacy fixture confirmed restoration of missing group/return columns, group FK/index, and `pending` → `pending_approval` status normalization before restoring the canonical ENUM. PHP-08 through PHP-12 now have source/migration CI evidence. Local browser acceptance for these original PHP workflows remains outstanding, and the next audit step is a focused scan of any remaining request-time DDL outside these tracked files.


## 2026-10-28 — PHP-13 public sponsor application mismatch

A focused scan outside `modules/` found a confirmed mismatch between `apply.php`, the checked-in `sponsor_requests` schema, and the admin/API request queue. The public page uses `full_name`/`pending`, while the canonical table and queue use `sponsor_name` and `new/contacted/converted/lost`; the old 2026-09-14 migration also creates an incomplete variant. The public form supports individual/company/organization requests but omits gender, while the admin conversion flow currently requires male/female. This is logged as PHP-13; no source change was made because the intended handling of individual versus organization applications must be clarified rather than guessed. PHP-08 through PHP-12 have source/migration CI evidence; local browser acceptance remains pending.


## 2026-10-29 — Linux Mint development environment restored

The user has moved the development workstation from Windows to Linux Mint and confirmed that the existing PHP application opens and accepts login locally.

- OS: Linux Mint.
- XAMPP installation: `/opt/lampp` (XAMPP for Linux 8.2.12-0).
- Apache document root / project path: `/opt/lampp/htdocs/AhlElKheir/`.
- Local application URL confirmed by the user: `http://localhost/AhlElKheir/`.
- VS Code is installed and connected to that exact project folder, so edits there are served by the local Apache instance.
- XAMPP Apache and MariaDB were started successfully during setup.
- The recovered database imports available in the fresh XAMPP MariaDB include `ahl_el_kheir`, `ahl_el_kheir-development db`, `ahlelkheir_fina_current_20260916`, `ahl_el_kheir_production_test`, and `pharmacy_management_dev`.
- The preserved recovery snapshot is `/opt/lampp/recovery/data_snapshot`; the working recovery copy is `/opt/lampp/recovery/work_oct28`; exports are preserved under `/opt/lampp/recovery/exports_oct28`. Do not delete these recovery copies as part of routine project work.
- The old Windows partition is mounted read-only at `/mnt/windows_recovery`; preserve it as a recovery source.
- The old `school_db_production` export was only 1028 bytes and did not contain the database schema/data; a separate backup exists and is intentionally set aside for later. Do not claim that database is restored.
- This environment checkpoint records setup, not full database row-by-row equivalence or application-wide runtime acceptance. The user confirmed only that the project URL opens and login succeeds.

Continue on the existing repository and database; do not create a replacement project. Before code changes, read the canonical project documents and the current PHP remediation backlog from `main`. Keep Flutter/native development paused.

### 2026-10-29 — Public sponsor confirmation dialog refinement

The applicant-facing supervisor note has been removed from the pre-submission form. `apply.php` now shows the assigned active supervisor only in an automatically opened success dialog after a valid application is saved. The terms/instructions dialog remains. The unused `apply_supervisor.php` endpoint was removed. Local PHP lint and browser runtime verification are pending; do not mark runtime-verified until tested on Linux Mint XAMPP.

### 2026-10-29 — Public sponsor form organization header

`apply.php` header now displays the organization logo at the top, then the configured Arabic/English organization name, then only the contact details populated in existing settings (address, support phone, support email), followed by the form title. No schema or contact data changes. Local PHP lint/browser verification remain pending.

### 2026-10-29 — Sponsor application header alignment

`apply.php` organization contact details now remain on one horizontal line with compact spacing; on narrow screens the line can scroll horizontally instead of stacking. Local browser verification remains pending.

### 2026-10-29 — Public application supervisor-name emphasis

The successful `apply.php` confirmation now styles the assigned supervisor name with a high-contrast magenta highlight and pale background. Source committed to `main`; local browser verification remains pending.

### 2026-10-29 — Supervisor notification redirect hardening

Updated `modules/notifications/mark_read.php` so supervisor request links preserve a trusted absolute URL only when it belongs to the current application, reject external or protocol-relative legacy links, and prefix only relative paths. Commit `cefd47eea8504da35e541d75eb961af73795eed6`. This is source-reviewed; PHP lint and Linux Mint browser acceptance remain pending.

### 2026-10-29 — PHP-13 syntax-check coverage

Expanded the existing PHP syntax workflow to lint `apply.php`, `modules/notifications/mark_read.php`, `modules/sponsors/my_request.php`, and `modules/sponsors/requests.php`, which were absent from its explicit file list. Commit `6a8f66e05ae17a92ad5772d1122d831835b413c4`; workflow result pending, local browser acceptance still required.
