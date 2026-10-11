# Salary Advance Rescheduling — Audit and Design Gate

**Date:** 2026-10-11  
**Branch:** `main`  
**Status:** SOURCE/DOCUMENTATION AUDIT COMPLETE — IMPLEMENTATION BLOCKED ONLY ON APPROVAL-AUTHORITY DECISION

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

## 3. Required safety constraints for any future implementation

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

## 4. Schema/design implication

The current schedule's unique keys prevent inserting a second row for the same request and installment number or scheduled month. Re-running the existing generator cannot safely represent a new plan while preserving the old plan's history. Implementation therefore needs a deliberate versioning strategy (for example, explicit plan/decision version identity plus an active-plan relationship) and corresponding payroll selection changes. Do not overwrite the old schedule or merely delete/recreate unpaid rows.

The exact migration design should follow the repository's actual MariaDB schema and current query patterns during implementation; this audit does not create or assume new columns.

## 5. Decision required before implementation

**Who must approve a repayment reschedule?**

The existing roles support two plausible policies, but the repository does not settle the choice:

- **Option A — dual control, recommended for financial-term changes:** FM prepares the proposed terms and supporting balance/reconciliation; GM approves or rejects; FM executes the approved plan.
- **Option B — FM-only approval:** FM documents the reason, approves the revised terms, and executes them under the existing financial authorization.

Do not implement either option until the business owner confirms the approval authority. The remainder of the workflow and schema can then be designed around that rule.

## 6. Next checkpoint

After approval authority is confirmed:
1. inspect the current live schema and every payroll query that selects schedule rows;
2. finalize the plan-version schema and state transitions;
3. implement the smallest migration and workflow consistent with the approved authority;
4. add rollback-only regression tests;
5. run local lint and focused MariaDB tests;
6. update the master continuation docs and commit the verified result.
