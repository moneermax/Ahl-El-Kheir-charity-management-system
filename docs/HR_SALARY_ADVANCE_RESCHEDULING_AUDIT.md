# Salary Advance Rescheduling — Audit and Design Gate

**Date:** 2026-10-11  
**Branch:** `main`  
**Status:** BUSINESS AUTHORITY DECIDED — IMPLEMENTATION DESIGN AND BUILD NEXT

## 1. Why this workflow is needed

Stage 5 now respects the saved policy version's `maximum_repayment_months`. When partial or skipped repayments leave a balance beyond that configured calendar window, automatic payroll selection stops. The outstanding balance and historical schedule remain; the application does not extend the approved plan automatically.

The latest focused local verification reported by the user passed:
- PHP syntax checks for `modules/hr/lib_salary_advance_payroll.php` and `tools/run_salary_advance_stage5_payroll_edge_tests.php`;
- all four `tools/run_salary_advance_stage5_schedule_tests.php` cases;
- the reported `tools/run_salary_advance_stage5_payroll_edge_tests.php` cases, including carry-forward inside the window, the post-horizon stop, the zero-deduction no-op, and rollback-only cleanup.

These completed checks are closed. Do not rerun them absent a regression.

## 2. Existing-source findings

### Policy and schedule
- `hr_salary_advance_policy_versions.maximum_repayment_months` is part of the saved policy version.
- The existing repayment schedule is `hr_salary_advance_repayment_schedule`; its unique keys are request + installment number and request + scheduled month.
- `hrSalaryAdvanceScheduleGenerate()` returns the existing schedule count when rows already exist. It is not a rescheduling operation and must not be reused as if it regenerated an approved plan.
- Payroll repayment history is stored separately in `hr_salary_advance_payroll_repayments`, with a unique request + payroll constraint and accounting trace. Existing actual repayment/allocation history must remain unchanged.
- No rescheduling-specific decision table, schedule version, UI endpoint, helper, or permission was found in the current repository scan.

### Direct repayment and settlement boundary
- Source already contains a direct-repayment posting path, policy checks, outstanding-balance protection, accounting posting to control account 1410, audit logging, and a full-payment transition to `settled`.
- Evidence upload is separate from the repayment transaction and is permission-checked.
- This source existence does not itself prove the complete Stage 6 workflow is runtime-verified. Stage 6 closure remains a separate audit gate.

### Role and approval references
- `docs/AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md` defines GM as responsible for senior organizational decisions/final management approvals and FM as responsible for financial review, posting, treasury, reconciliation, and financial closure.
- The existing GM waiver workflow provides a separation-of-duties precedent: FM prepares a concrete decision, GM approves/rejects, and FM executes after approval.
- Neither the canonical role document nor the current salary-advance policy explicitly assigns authority to change the repayment terms of an already-approved and disbursed advance. The waiver precedent is relevant but is not enough to silently invent a rescheduling policy.

## 3. Confirmed business rule

The user confirmed this operating model on 2026-10-11:

- GM and FM discuss and agree the salary-advance policy before it is created.
- FM then creates, applies, and administers the agreed policy.
- FM may prepare and execute a repayment reschedule without a second GM approval, provided the policy agreed by GM and FM explicitly authorizes FM to reschedule and, where needed, exceed the normal maximum repayment horizon.
- This is policy-level prior authorization, not permission to silently edit the approved policy version. The system must store the authorization in the policy version used by the advance and preserve that version unchanged.
- The app does not currently appear to persist a separate GM policy-approval record. Do not claim that it does; for this workflow the business agreement is prior organizational authorization, while the application enforces the recorded policy limits/authority.

The `maximum_repayment_months` value remains the normal schedule duration boundary. Going beyond that boundary is permitted only when the saved policy version explicitly enables FM rescheduling exceptions. The exception does not waive debt, change the original disbursed amount, or remove the monthly deduction cap. Every reschedule must still document its reason and revised plan.

## 4. Required safety constraints for implementation

1. A reschedule must be an explicit, authorized decision. Payroll must never extend the expired horizon on its own.
2. Preserve the original approved policy version, original schedule rows, payroll repayment allocations, posted journals, and audit trail.
3. Record the reason, old and proposed terms, who prepared the request, who approved/rejected it, timestamps, effective month, and the identity of the new schedule version.
4. Enforce the new plan's monthly deduction and duration limits from the approved terms; do not mutate the original policy version.
5. Revalidate the live outstanding balance and repayment history under transaction locks at approval/execution time; reject stale proposals if the balance or repayment basis changed.
6. Activate a new schedule only after the required approval. Old unpaid rows must become ineligible without deleting or rewriting historical paid/partial/skipped rows.
7. Keep preview and posting eligibility identical; protect against duplicate approval/execution and concurrent payroll posting.
8. Emit notifications only after the relevant transaction commits; notification failure must not roll back financial work.
9. Use a migration for schema changes. No runtime DDL, triggers, or views.
10. Add rollback-only database tests for prepare, reject, approve, execute, stale-balance conflict, duplicate/concurrent execution, schedule-history preservation, old-plan suppression, monthly cap, duration boundary, payroll integration, audit, notification failure isolation, and reconciliation.

## 5. Schema/design implication

The current schedule's unique keys prevent inserting a second row for the same request and installment number or scheduled month. Re-running the existing generator cannot safely represent a new plan while preserving the old plan's history. Implementation therefore needs a deliberate versioning strategy (for example, explicit plan/decision version identity plus an active-plan relationship) and corresponding payroll selection changes. Do not overwrite the old schedule or merely delete/recreate unpaid rows.

The exact migration design should follow the repository's actual MariaDB schema and current query patterns during implementation; this audit does not create or assume new columns.

## 6. Implementation progress — not yet complete or runtime-verified

The following foundational code has now been committed to `main`:

- `database/migrations/2026-10-11_hr_salary_advance_rescheduling.sql` adds a disabled-by-default policy authorization flag, versioned plan metadata, a plan reference on schedule rows, and a backfill for existing schedules.
- `modules/hr/lib_salary_advance_policy.php` validates the new policy setting.
- `modules/hr/salary_advance_policy.php` exposes the setting to FM when creating a future policy version.
- `modules/hr/lib_salary_advance_repayment.php` creates a first plan for newly generated schedules.
- `modules/hr/lib_salary_advance_payroll.php` limits both preview and posting to the active plan and evaluates the horizon from that plan's start month and duration.

Relevant commits: `69ccd60`, `d3d62e1`, `a6252c4`, `b2cdd75`, `3afdbdd`, `8c53838`.

**These are foundational changes, not a finished rescheduling feature.** The FM rescheduling form/service, authorization enforcement at execution, plan replacement workflow, rollback-only tests, MariaDB migration execution, PHP lint, payroll regression verification, and reconciliation have not yet been completed. Do not apply the migration to a live/important database until the migration has been reviewed and tested against the actual local schema. Existing policies default to no rescheduling authorization.

## 7. Remaining implementation plan

The approval-authority decision is closed; the remaining work is engineering, not another approval discussion.

1. Add a policy-version flag that explicitly authorizes FM-managed rescheduling beyond the normal maximum horizon. Existing policies must default to **not authorized** so their existing behavior is unchanged.
2. Introduce versioned repayment plans/reschedule records without deleting, renumbering, or rewriting the original schedule, applied payroll allocations, direct repayments, waiver decisions, or posted journals.
3. Record reason, actor, timestamp, prior-plan reference, new-plan reference, outstanding balance at decision time, start month, duration, and monthly terms. Recheck balance/history under transaction locks before activation.
4. Update both payroll preview and payroll-paid application to select only the active plan and enforce the same policy flag, monthly deduction cap, and duration rules. An expired plan must remain ineligible unless a qualifying FM reschedule is explicitly recorded.
5. Add an FM-only authorized workflow with idempotency/concurrency protection, audit events, and post-commit notifications. Do not add a second GM approval step.
6. Add rollback-only MariaDB tests for policy default/authorization, in-policy reschedule, exception beyond horizon, monthly-cap enforcement, stale balance, duplicate/concurrent attempts, original schedule/history preservation, active-plan selection, payroll preview/posting parity, and notification failure isolation.
7. Run local PHP lint and the focused tests; inspect reconciliation; then update the master continuation docs and record the verified commit.

Implementation must begin with the actual schema/query inventory, not assumed column names. Do not apply any migration automatically or use runtime DDL.
