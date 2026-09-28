# HR Salary Advance — Current Development Checkpoint

**Date:** 2026-09-28  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Active area:** HR / Salary Advance  
**Current stage:** Stage 2 — Employee Request Foundation

## Purpose

This document records the current Salary Advance development state so a new ChatGPT session can continue from the exact point reached without reopening unrelated completed work.

The Salary Advance feature is a dedicated HR workflow. It integrates with the existing payroll/accounting engines later; it must not be folded into the existing payroll-policy calculation engine or implemented as an unrelated parallel accounting system.

## Stage 1 — Salary Advance Policy Foundation

**Status: COMPLETE for the current development boundary.**

Stage 1 established the versioned Salary Advance policy foundation and the FM policy-management UI.

Implemented repository components:

- `database/migrations/2026-09-27_hr_salary_advance_policy.sql`
- `modules/hr/lib_salary_advance_policy.php`
- `modules/hr/salary_advance_policy.php`

The policy model supports:

- annual/versioned policies;
- any-amount requests or configurable minimum/maximum amounts;
- multiple-active-advance rule;
- fixed monthly repayment;
- full eligible-salary repayment;
- full settlement from salary;
- direct repayment;
- per-request customization permission;
- maximum monthly deduction;
- maximum repayment period;
- repayment start rule;
- insufficient-salary behavior;
- eligible salary basis;
- minimum service period;
- probation eligibility;
- terminated-employee rule;
- mandatory accounting verification;
- early settlement.

Relevant implementation commits already recorded in the prior checkpoint:

- Migration: `5426c288e5e049c81021f27a225e3d7c2a0d277d`
- Policy library: `cb9e0169a093e5b911effacf3321800209910a59`
- Policy UI: `740e9ec940b69cd459fb1280570ed39b826fd910`
- Earlier documentation checkpoint: `9ddc2dae6d47068a6b3a30704c2cf1d5f30dcfcf`

The user has now confirmed that the Stage 1 work we were originally completing is done. Do not reopen Stage 1 unless a genuine regression is found.

## Stage 2 — Employee Salary Advance Request Foundation

**Status: IMPLEMENTED IN REPOSITORY; RUNTIME VERIFICATION STILL REQUIRED.**

Current repository components include:

- `database/migrations/2026-09-27_hr_salary_advance_request.sql`
- `modules/hr/lib_salary_advance_request.php`
- `modules/hr/salary_advance_request.php`

The request table stores the employee, request number, policy version, requested amount, requested repayment method, optional monthly amount, requested start month, reason, status, submitter, and timestamps.

The request implementation currently provides:

1. Employee resolution through the existing user-to-employee linkage.
2. Active-policy resolution.
3. Employee eligibility validation.
4. Server-side request amount validation.
5. Server-side repayment-method validation against the active policy.
6. Fixed-monthly repayment validation, including monthly-deduction and maximum-term rules where configured.
7. Start-month validation according to the policy start rule.
8. Multiple-active-request protection when disabled by policy.
9. Policy-version capture on the request so later policy changes do not rewrite the original request.
10. Request-number generation using the existing request table sequence.
11. Employee-facing display of previous requests and their lifecycle status.
12. Submission toward FM review through the existing notification infrastructure.

## Important architectural checkpoint

The employee request must remain the employee's original request.

The later FM-review stage will be responsible for reviewing and, where policy permits, customizing the request. Do not silently overwrite the employee's submitted values during request creation.

Policy defaults are organizational defaults. They do not eliminate the need for server-side validation at request submission and again at FM review/approval.

## Stage 2 runtime gate

Do **not** mark Stage 2 complete yet.

Before completion, perform a controlled runtime test covering at minimum:

1. Apply/verify the Stage 2 migration in the local development database.
2. Confirm an existing active employee has a valid linked user account.
3. Confirm an active Salary Advance policy is available.
4. Open `modules/hr/salary_advance_request.php` as the employee.
5. Verify the employee sees only their own employee/request context.
6. Submit one controlled salary-advance request.
7. Verify the request is stored with the correct employee ID and active policy version.
8. Verify the submitted amount, repayment method, monthly amount/start month, and reason are preserved exactly.
9. Verify the request status is initially `submitted`.
10. Verify the FM notification is created through the intended existing notification mechanism.
11. Verify invalid values are rejected server-side, not only by HTML controls.
12. Verify the multiple-active-request rule when it is disabled by policy.
13. Verify no unrelated payroll/accounting result is changed by merely submitting the request.

Use controlled test data only. Do not modify historical payroll/accounting evidence merely to make the test pass.

## Specific inspection required before runtime acceptance

The current employee-request page calls:

`ak_transaction_review_notify_fm_event()`

for the FM notification.

Before accepting this as final architecture, inspect the helper and its existing callers. Confirm that reusing this notification infrastructure is appropriate for an HR salary-advance request and does not accidentally imply accounting transaction authority or create incorrect notification semantics.

Do not replace it speculatively. Inspect first, then fix narrowly if necessary.

## Next development stage

After Stage 2 runtime verification passes:

### Stage 3 — FM Review and Per-Request Customization

Expected scope:

- FM-only review authorization using the existing FM roles/access model;
- request review screen;
- employee-submitted values displayed as the original request;
- permitted FM customization according to the active policy;
- approval/rejection workflow;
- rejection reason;
- preservation of the employee's original request and FM decision separately;
- correct notification back to the employee;
- accounting-verification gate before any financial disbursement stage;
- no premature payroll/accounting posting during review.

Do not implement repayment schedules, payroll deductions, direct repayment settlement, or accounting disbursement posting as part of Stage 2. Those belong to later stages.

## Overall Salary Advance staged plan

1. **Stage 1 — Policy foundation:** COMPLETE.
2. **Stage 2 — Employee request:** IMPLEMENTED; runtime verification OPEN.
3. **Stage 3 — FM review and per-request customization:** NOT STARTED.
4. **Stage 4 — Accounting verification and disbursement:** NOT STARTED.
5. **Stage 5 — Repayment schedule + payroll integration:** NOT STARTED.
6. **Stage 6 — Direct repayment and settlement:** NOT STARTED.
7. **Stage 7 — Exceptional lifecycle cases:** NOT STARTED.
8. **Stage 8 — Reporting, audit, and hardening:** NOT STARTED.

Every stage must satisfy the project completion gate:

`Implementation committed → migration committed where required → runtime-tested → reconciliation verified where applicable → no known regression → documentation updated → exact next continuation point recorded`

## Project-wide rules that remain in force

- Existing project only; do not rebuild or start a new project.
- Inspect the repository and actual schema before changing code or SQL.
- Never invent table or column names.
- Database structure changes belong in migration files.
- No runtime `CREATE TABLE`, `ALTER TABLE`, triggers, or views.
- Do not use destructive Git operations (`reset --hard`, `clean`, `restore`, force-push, etc.).
- Preserve intentional local work and protected test evidence.
- Server-side authorization is the security boundary.
- Do not repeat closed tests unless genuine regression evidence appears.
- Keep Arabic RTL UI and the existing application architecture.
- Use procedural PHP; do not introduce OOP into this project.
- Do not modify verified payroll/accounting results merely to facilitate testing.
- Documentation is part of implementation.

## Exact continuation point

**Stage 1 Salary Advance Policy Foundation: COMPLETE.**

**Stage 2 Employee Salary Advance Request Foundation: IMPLEMENTED IN REPOSITORY, NOT YET RUNTIME-VERIFIED.**

**Immediate next task:** inspect the current Stage 2 implementation and the existing `ak_transaction_review_notify_fm_event()` notification helper/callers, then apply/verify the Stage 2 migration and perform the first controlled employee request runtime test.

Do not jump to Stage 3 until Stage 2 passes its runtime gate.


## Checkpoint — 2026-09-28: Stage 2 request runtime issue + policy actions

- Stage 1 policy foundation remains complete.
- Stage 2 employee salary-advance request implementation remains complete in repository; runtime verification is still pending.
- The current policy record reported during testing is V1 with `effective_from = 2026-09-29`.
- `config/config.php` centrally sets the application timezone to `Africa/Khartoum`; therefore policy activation uses the application date from that timezone. A policy dated 2026-09-29 is not active while the application date is 2026-09-28. Do not weaken the future-effective policy rule merely to hide this date difference.
- Added checkpoint branch: `checkpoint/hr-salary-advance-policy-actions`.
- Added safe Edit/Delete actions to `modules/hr/salary_advance_policy.php`.
- Safety rules:
  - Only future policy versions can be edited or deleted.
  - A policy linked to any `hr_salary_advance_requests` record cannot be edited/deleted.
  - Active or historical policies are protected; use a new policy version instead of rewriting historical/current policy records.
  - Edit preserves all existing policy values in the form before saving.
  - Delete requires CSRF protection and an explicit browser confirmation.
- No schema change, runtime DDL, trigger, or view was added.
- Next runtime verification:
  1. Pull/use the checkpoint changes.
  2. Open salary advance policy page as FM.
  3. Confirm the existing future V1 row shows Edit and Delete actions.
  4. Open Edit and verify every policy field is pre-populated exactly.
  5. Save a harmless change and confirm the same version number is retained.
  6. Verify Delete is available only for an unused future policy; verify active/historical rows are protected.
  7. Then verify the policy activation/request page again on the application's actual date boundary before proceeding to Stage 3 FM review/customization.
