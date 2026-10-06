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
