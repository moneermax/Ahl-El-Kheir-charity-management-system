# Projects Runtime / Expense Workflow Checkpoint — 2026-09-26

## Purpose

This document records the verified project-expense/payment workflow and the exact point from which the next session should continue.

## Current project under controlled testing

- Project: **PRJ-0010 — اختبار المشاريع 2026**
- Project ID: **10**
- The project has already passed the PM → FM → GM approval workflow.
- GM/VGM final approval is a funding reservation/allocation event, **not an actual spend**.
- PM must explicitly launch the project; final approval alone does not activate it.
- The project is intentionally **not yet closed** because there is still a remaining item/task that must be completed before PM closure.

## Project-funded expense model — final working rule

For the Project Supervisor's project expenses, payment is made from the project budget already transferred/allocated to the Project Supervisor. This is **not** an organization cash/bank/e-wallet payment and does **not** create an organization accounting journal entry.

When the Project Supervisor records a payment:

1. A payment receipt is required.
2. The expense is recorded directly as a **posted** project expense.
3. The amount is deducted from the project's available approved budget.
4. No organization expense account is selected.
5. No organization payment account is selected.
6. No organizational journal entry is created.
7. The project expense remains visible in the **المصروفات** section.
8. A project payment voucher may be printed where supported.

### Existing/legacy draft expenses

Older draft project expenses were created under the previous workflow. They are **not automatically converted to posted** records because doing so without a payment receipt would falsely represent a completed payment.

The existing **تعديل / تسجيل الدفع** action is now the intended migration path for these records: open the draft expense, provide/confirm the payment receipt, save the edit, and the expense becomes **posted**.

## Edit/Delete behavior

The user explicitly confirmed that Project Supervisor expense records must retain **تعديل** and **حذف** actions after posting.

The final implementation therefore permits the assigned primary Project Supervisor to edit or delete project-funded expenses in both states: `draft` and `posted`. The server-side POST handlers and the UI use the same rule; this is not a visual-only change.

## Important implementation commits

- `14faa7f5da628f0eb13b19eeddffa36463286d6a` — temporary legacy finalization bridge; subsequently removed and **must not be revived**.
- `37e037b0c7b9cf2052dd7adb49ec7fc67ad1eae8` — permanently blocks the obsolete organization-account expense-entry path for Projects.
- `6e4dcf4c8aa0437d6ff50043e9e0c9bf84c1874e` — removed obsolete `project_expense_finalize.php` after moving finalization into the existing edit workflow.
- `b7ca93a4892faa47074428aa2fe4420e754d1ba4` — restored the project-expense success toast/feedback path.
- `f7d7062fdef265bce997baf1e53dc7ce70ffd6` — aligned the project-expense action/summary logic with the posted project-budget model.
- `5dda62c4bc74662fe0e9c415ddbbed88e9263a94` — final fix: Project Supervisor can edit/delete both draft and posted project-funded expenses; user runtime test confirmed the workflow is now complete.

## Expense/accounting boundary that must not be regressed

Do not reintroduce the old finance-form path that asks the Project Supervisor to choose organization expense/payment accounts for these project-funded expenses.

The obsolete legacy `add_expense` path remains blocked in `modules/projects/view.php` for compatibility/history; it must not become the active Projects expense workflow again.

Do not create a new standalone expense-finalization page. The finalization action belongs in the existing project expense edit workflow.

## Project closure workflow — schema correction

The PM closure workflow is already implemented in `modules/projects/view.php` and correctly requires a prior `closure_requested` record from the primary Project Supervisor. It also blocks closure when unposted project expenses remain, records the closure summary/reason, updates the lifecycle and legacy project status, and notifies the GM/VGM.

During the first runtime closure test on PRJ-0010, the closure action failed because `project_lifecycle.close_reason` was missing from the deployed schema while the existing closure handler already writes that field. This was a repository/schema mismatch, not a PRJ-0010 data problem.

A migration was added:

- `database/migrations/2026-09-27_project_closure_reason.sql`
- Commit: `b7bd414fce050b271e9653e47c4becd304622398`

The migration adds `project_lifecycle.close_reason` as a nullable `VARCHAR(64)` using the project's migration-only schema-change rule. No runtime DDL, trigger, or view was introduced.

**PRJ-0010 must remain open until this migration is applied and the PM closure workflow is runtime-tested successfully.**

## Current test state

The user has confirmed that the latest project-expense workflow is **done**.

The project is deliberately **not closed yet**. The next runtime step is to apply the new closure-schema migration, retry PM → إغلاق المشروع on PRJ-0010, and verify the complete closure workflow before treating the project as closed.

## Next session starting point

Do not restart the completed expense work.

Start by inspecting the current `main` branch and the current documentation. Apply/verify the closure schema migration, then perform the appropriate final PM closure/runtime verification when the project is actually ready.


## 2026-09-27 — Project closure runtime verification completed

PRJ-0010 was runtime-tested through the PM closure workflow after applying the repository migration that adds `project_lifecycle.close_reason`.

Verified outcome:
- PM closed the project successfully without errors.
- The closure workflow completed its existing guards and state updates.
- GM and VGM received closure notifications.
- The closure notifications were confirmed by runtime testing.

The closure work unit is therefore **COMPLETE / RUNTIME VERIFIED / CLOSED**. Do not repeat the closure test unless a regression is reported.

## 2026-09-27 — Reports page UI cleanup completed

Four user-facing report pages were corrected after runtime review:
- `modules/reports/hr.php`
- `modules/reports/financial.php`
- `modules/reports/sponsorship.php`
- `modules/reports/operational.php`

Fixes:
- Removed each page's redundant manual bottom **عودة** button because the shared `includes/footer.php` already provides the standardized top-left and bottom-right Back controls. Each page now relies on the shared mechanism and no longer renders a duplicate bottom button.
- Made the welcome-section report title and subtitle explicitly white against the dark blue welcome background on all four pages. The shared header styling was not changed.

Implementation commits:
- HR: `45515e3ad1dfca46d362c4e3f023840d3ee7aab1` and `70b5b5dc81dbec38d04ef654ee9f516d32fb5d14`
- Financial: `2d4598734b23cd81ca109d8e57ff1ae44915aea3` and `d0bb8acc3a5ffe57f87cc43d75ab17c49c8b2617`
- Sponsorship: `11cf1f3ecd43ebb63dc4cb7f24d2868800d4a6db` and `6bb6e07207fda474e75f3bd9caa23682891cda84`
- Operational: `292220010ea59f10bc2f8a22c55354858cdd31dd` and `b97f02a5613aedd9c746cc28eda95a1f2dea243f`

This report-page cleanup is complete. Future Back-button fixes should continue to use the shared footer mechanism rather than adding page-local duplicate controls.

## Current Projects continuation point — 2026-09-27

The PRJ-0010 closure workflow and the four report-page cleanup items above are closed. The next Projects work should continue from the current repository/docs state rather than reopening completed expense, closure, or report fixes.

The next active Projects audit area is the remaining **post-closure / Projects-module audit and remediation work**, using the existing Projects audit plan and current runtime state as the source of truth. Start with repository inspection and the current audit/checkpoint documents before selecting the next concrete fix; do not assume an older pending item is still open if the repository already contains its resolution.

## 2026-09-27 — Pre-HR Salary Advance Backup Checkpoint

The user confirmed that the current project/repository and database backups have been completed successfully before beginning the new HR Salary Advance feature. This is the recovery checkpoint for the new feature work.

No Salary Advance implementation or schema change has been made at this checkpoint. The agreed design direction is policy-driven: an annual FM-configured Salary Advance Policy provides defaults, while the FM may override/customize the policy terms for an individual request. The existing HR/payroll/accounting implementation must be inspected before any schema or code changes are made.

## 2026-09-27 — HR Salary Advance Feature Working Plan

### Objective
Introduce a policy-driven Salary Advance feature integrated with the existing HR, payroll, and accounting architecture. The feature must support annual organizational defaults configured by the FM while allowing the FM to override/customize the final terms for an individual request without changing the annual policy.

### Design principles
- Employee may request any amount; the system does not impose an artificial request ceiling unless the active organizational policy explicitly defines one.
- Employee selects a proposed repayment method in the request.
- The active annual Salary Advance Policy supplies defaults.
- FM may apply policy defaults or customize the terms for the individual request.
- FM overrides are recorded separately from the annual policy and require an audit trail/reason where applicable.
- Once approved/disbursed, the advance retains its final approved terms; later policy versions do not rewrite historical advances.
- Salary advance is an employee receivable, not salary expense.
- Disbursement must be an explicit accounting event and must produce a printable disbursement receipt.
- Payroll deductions must be linked to the specific advance and repayment schedule; the generic payroll deduction field must not become the sole source of truth for advance balances.
- Deduction can never exceed the outstanding advance and cannot make salary negative.
- Direct/early repayment remains supported.
- Existing HR/payroll/accounting infrastructure must be reused; no parallel accounting or payroll engine.
- Schema changes only through migration files; no runtime DDL, triggers, or views.

### Staged implementation plan

#### Stage 1 — Policy and architecture foundation
Status: **STARTED**
1. Inspect the existing HR employee, employment-state, contract/salary-history, payroll, payroll-policy, and accounting structures.
2. Define the dedicated Salary Advance Policy model and its annual/versioned lifecycle.
3. Define which policy fields are defaults and which are mandatory controls.
4. Define FM permissions and policy lifecycle: draft → active → closed/versioned.
5. Define the policy application model for each request, including default-vs-customized terms and audit data.
6. Design and implement the Stage 1 migration only after the real schema has been verified.
7. Implement FM policy management UI using existing HR conventions.
8. Document and checkpoint Stage 1 before proceeding.

#### Stage 2 — Employee salary advance request
- Employee request screen and permissions.
- Load current applicable policy defaults.
- Capture employee-requested amount and proposed repayment method/terms.
- Preserve the original employee request.
- Request lifecycle and notifications.
- Prevent invalid requests according to mandatory policy/system controls.

#### Stage 3 — FM review and per-request customization
- FM review screen.
- Side-by-side employee request vs policy defaults.
- Apply defaults or customize.
- Record each override and reason.
- Approve/reject workflow.
- Freeze final approved terms once approval is completed.

#### Stage 4 — Accounting verification and disbursement
- Accounting verification workflow.
- Employee advance receivable accounting.
- Cash/bank disbursement integration using existing accounting infrastructure.
- Disbursement status and audit trail.
- Printable Salary Advance Disbursement Receipt.
- Prevent duplicate disbursement.

#### Stage 5 — Repayment schedule and payroll integration
- Generate repayment obligations from final approved terms.
- Integrate deductions with existing payroll calculation.
- Support fixed monthly deduction, full eligible-salary deduction, full settlement, and other approved policy methods.
- Handle insufficient salary according to policy.
- Prevent negative payroll and over-recovery.
- Link every payroll recovery to the originating advance.

#### Stage 6 — Direct repayment and settlement
- Direct cash/bank repayment where permitted.
- Early/full settlement.
- Automatic outstanding-balance calculation.
- Settlement status and receipt/audit trail.
- Prevent further payroll deductions after settlement.

#### Stage 7 — Exceptional lifecycle cases
- Repayment-plan change after disbursement through controlled authorization.
- Employee termination/final settlement treatment.
- Leave/partial-pay/insufficient-pay scenarios.
- Multiple active advances if the final policy permits exceptions.
- Reconciliation and recovery controls.

#### Stage 8 — Reporting, audit, and hardening
- Employee advance history.
- Outstanding advances and aging.
- Policy/override audit.
- Accounting reconciliation.
- Payroll reconciliation.
- Permission/security review.
- UI/RTL/accessibility review.
- Full regression testing.

### Stage completion gates
A stage is not marked complete until:
- Repository implementation is committed and ready to pull.
- Required migration(s) are committed.
- Runtime behavior is tested where applicable.
- Accounting/payroll totals reconcile where applicable.
- No known regression remains in the touched workflow.
- Relevant documentation/checkpoint is updated.

### Stage 1 checkpoint
Stage 1 implementation is now present in the repository. The remaining Stage 1 gate is controlled runtime validation: apply the committed migration to the local database, open the FM Salary Advance Policy page, create a controlled future-dated test policy, verify that the policy is stored and listed correctly, and then checkpoint the result before moving to Stage 2.

### Stage 1 implementation checkpoint — 2026-09-27

Stage 1 has now been implemented in the repository as the Salary Advance Policy foundation.

Implemented files:
- database/migrations/2026-09-27_hr_salary_advance_policy.sql
- modules/hr/lib_salary_advance_policy.php
- modules/hr/salary_advance_policy.php

The policy foundation is separate from the existing hr_payroll_policy_versions because salary-advance rules are a distinct business process. The policy is annual/versioned and includes defaults for request amount behavior, repayment methods, monthly deduction controls, repayment timing, insufficient-salary behavior, salary basis, employee eligibility, accounting verification, and early settlement.

FM roles recognized by the existing accounting module are used for access: financial_manager, fm, with admin retained as administrative access.

Important: this is an implementation checkpoint, not a runtime-completion checkpoint. The migration has not yet been applied to the local database and the Stage 1 UI has not yet been runtime-tested. Do not mark Stage 1 complete until migration application, UI validation, and policy creation/versioning are tested successfully.


## 2026-09-27 — Salary Advance Stage 1 repository inspection checkpoint

Repository inspection was completed before requesting any user-side runtime action.

Verified:
- Existing payroll policy uses the same future-effective-date/versioning pattern; the Salary Advance policy remains a separate subsystem.
- Existing database helper conventions provide `dbFetchOne()` and `dbFetchAll()`, matching the Stage 1 policy library.
- Existing session role normalization maps `fm` to `financial_manager`; the Salary Advance policy page therefore remains FM-only in practice, with admin retained.
- The Stage 1 migration is schema-only and contains no runtime DDL, triggers, or views.
- The Salary Advance Policy page is now reachable from the FM dashboard through a dedicated quick action.

Implementation commit for FM dashboard access:
- `72b6cb364efad6fcda251742874223a2035162ad`

Runtime status:
- Migration has **not** yet been applied to the local database.
- Stage 1 has **not** yet been runtime-tested.
- No Stage 1 completion claim should be made until the controlled runtime test succeeds.


## 2026-09-27 — Salary Advance Stage 1 UI polish and migration checkpoint

The local Stage 1 migration was successfully imported by the user into the local `ahl_el_kheir` database.

Two UI polish fixes were then implemented:
- `modules/hr/salary_advance_policy.php`: replaced the plain opening card heading with the established HR policy hero/header treatment used by the existing payroll policy page, while preserving the existing Salary Advance content and workflow.
- `modules/accounting/fm_dashboard.php`: moved the Salary Advance Policy quick-action card before the Feen/فينا الخير card, added its matching description, and added the missing action-card styling so it follows the same visual card treatment as the other FM quick actions.

Commits:
- Salary Advance Policy header: `41c0707cfd0f46956d950c9ddd06b959232e3495`
- FM dashboard card order/style: `d877c957db3e734f9b3038a1613e198317d5d400`

Runtime status:
- Migration import: **successful**.
- Salary Advance Policy page before UI polish: **opened successfully** as FM.
- The new UI polish has not yet been runtime-tested.
- Stage 1 remains **not complete** until the updated page/dashboard are pulled and visually verified, followed by the controlled future-dated policy creation/versioning test.


## 2026-09-27 — Salary Advance Stage 2 implementation checkpoint

Stage 2 employee-request foundation is now implemented in the repository.

Implemented:
- `database/migrations/2026-09-27_hr_salary_advance_request.sql`
  - Dedicated employee salary-advance request table.
  - Preserves the employee's original requested amount, repayment method, proposed monthly amount/start month, reason, submitting user, and the policy version used at submission.
  - Request lifecycle starts at `submitted`; later FM review/approval is a separate stage.
  - No runtime DDL, triggers, or views.
- `modules/hr/lib_salary_advance_request.php`
  - Resolves the employee record linked to the logged-in user.
  - Validates policy-based employee eligibility.
  - Validates requested amount and repayment method against the active policy.
  - Enforces monthly repayment limits and repayment-duration controls when applicable.
  - Generates request numbers and retrieves the employee's request history.
- `modules/hr/salary_advance_request.php`
  - Employee-facing Arabic RTL request page.
  - Shows the employee identity and active policy.
  - Captures the original requested amount and proposed repayment terms.
  - Prevents submission when no policy is active or mandatory eligibility rules fail.
  - Prevents a second active request when the policy disallows multiple active advances.
  - Sends an FM workflow notification after successful submission using the existing notification infrastructure.

Important implementation boundary:
- Stage 2 does not approve, customize, disburse, or create repayment schedules.
- The original employee request remains immutable through the later FM-review stage; FM customization belongs to Stage 3.
- The existing annual policy remains unchanged by an employee request.

Current runtime status:
- Stage 2 migration has not yet been applied to the local database.
- Employee request page has not yet been runtime-tested.
- Stage 2 is therefore **IMPLEMENTED / NOT YET RUNTIME VERIFIED**.
- Do not begin Stage 3 until the migration and controlled employee-request test pass.


## 2026-09-27 — Employee identity resolution checkpoint

The shared employee/user identity path was corrected after confirming that the application treats the user account and employee profile as the same person, with `employees.user_id` as the canonical relationship.

Problem identified:
- The HR employee list reads directly from `employees` and therefore can display an employee even when the employee row is not linked through `employees.user_id`.
- The shared header, leave request page, and salary advance request library previously relied only on `employees.user_id`.
- This caused an employee who exists in the HR employee list (including the reported Project Supervisor account) to receive no employee-request links or a "no employee record" message.

Fix implemented:
- Added `modules/hr/lib_employee_identity.php` with one common employee resolver.
- The resolver first uses the canonical `employees.user_id` relationship.
- For legacy/unlinked records, it uses an exact full-name match against the logged-in user's `users.full_name` only when exactly one employee profile matches; it does not guess when names are ambiguous.
- Shared header now uses this resolver for the Leave Request and Salary Advance Request links.
- Leave requests use the same resolver.
- Salary Advance requests use the same resolver while preserving employment-state lookup.

Commits:
- `1c58e05f5e9b8971e974cccbfe5009320ee31a30` — common employee identity resolver.
- `9f62808a16507ca1f58a343f33d662b0aa1edc42` — shared header integration.
- `1f56be95c35950ad7c8627d6441d4364f5b83559` — leave request integration.
- `7a8bc5fa5ae0a477637322eacabebae3adb227e2` — salary advance integration.

Runtime status:
- Repository implementation is complete and committed.
- Runtime verification is pending. The next test should use the reported `ps1` account and verify that both employee-request links appear and that both request pages resolve the same employee identity.
