# Salary Advance Rescheduling — Audit and Design Gate

**Date:** 2026-10-11  
**Branch:** `main`  
**Status:** SYNTAX, MIGRATION, RESCHEDULING, AND FOCUSED PAYROLL APPLY TESTS PASSED IN CI; FULL ACCOUNTING/LOCAL-SCHEMA VERIFICATION PENDING

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
3. Record the reason, old and proposed terms, executing FM user, timestamp, effective month, and new schedule identity. The saved policy flag represents prior GM/FM policy agreement; this workflow does not require a second GM decision for each reschedule.
4. Enforce the new plan's monthly deduction and duration limits from the approved terms; do not mutate the original policy version.
5. Revalidate the live outstanding balance and repayment history under transaction locks at approval/execution time; reject stale proposals if the balance or repayment basis changed.
6. Activate a new schedule atomically with the audit event after all policy and balance checks pass. Old unpaid rows must become ineligible without deleting or rewriting historical paid/partial/skipped rows.
7. Keep preview and posting eligibility identical; protect against duplicate approval/execution and concurrent payroll posting.
8. Emit notifications only after the relevant transaction commits; notification failure must not roll back financial work.
9. Use a migration for schema changes. No runtime DDL, triggers, or views.
10. Add rollback-only database tests for prepare, reject, approve, execute, stale-balance conflict, duplicate/concurrent execution, schedule-history preservation, old-plan suppression, monthly cap, duration boundary, payroll integration, audit, notification failure isolation, and reconciliation.

## 5. Schema/design implication

The current schedule's unique keys prevent inserting a second row for the same request and installment number or scheduled month. Re-running the existing generator cannot safely represent a new plan while preserving the old plan's history. Implementation therefore needs a deliberate versioning strategy (for example, explicit plan/decision version identity plus an active-plan relationship) and corresponding payroll selection changes. Do not overwrite the old schedule or merely delete/recreate unpaid rows.

The exact migration design should follow the repository's actual MariaDB schema and current query patterns during implementation; this audit does not create or assume new columns.

## 6. Implementation progress — source committed, syntax, migration, and service checks passed

The following source changes are present on `main`:

- The migration adds a disabled-by-default policy authorization flag, versioned repayment-plan metadata, and a plan reference on schedule rows; it also backfills an initial plan for legacy requests with schedule rows.
- The policy editor stores the FM rescheduling authorization in the policy version. Existing versions default to not authorized.
- Schedule generation links newly generated installments to an initial plan, and schedule history exposes plan identity/status and plan terms.
- Payroll preview and posting join only active plans and apply the active plan's start month and duration. A nullable plan duration preserves the no-fixed-horizon predicate where that model is supported.
- The FM-only rescheduling page and service validate the saved policy authorization, outstanding balance, payroll-based repayment method, expired finite plan, policy duration/cap, future start month, and reason; the service creates a replacement plan and schedule while retaining old schedule rows and records an audit event.
- Employee notification is attempted after the service commits its own transaction; notification errors are caught so they do not misreport or undo committed financial work.
- Direct-repayment advances are excluded from rescheduling so they cannot be silently converted to payroll deductions.

These are source-level observations. Dedicated CI checks also pass PHP syntax checks for the changed salary-advance files and run the migration twice against an isolated MariaDB 10.4.32 fixture. This verifies migration syntax, legacy schedule backfill, policy-default behavior, plan-scoped indexes, rerun safety, and core rescheduling-service safeguards against an isolated fixture schema. It does not verify the full local schema, payroll integration, or account reconciliation.

## 7. Verification status and remaining gate

**Passed in GitHub Actions (2026-10-11):**

- PHP 8.2 syntax checks for the salary-advance PHP files and notification dependency passed: [workflow run 38119108564](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38119108564).
- The Stage 7 migration ran twice against an isolated MariaDB 10.4.32 database. The test verified the default-disabled policy flag, legacy schedule backfill, preservation of both finite and NULL/unbounded policy durations, preserved schedule row count, plan-scoped indexes replacing the old indexes, and no duplicate plan or schedule rows on rerun. This job passed in the same workflow run.
- The rescheduling service and payroll eligibility query passed disposable-database tests for successful plan replacement, active-plan-only payroll selection, post-horizon suppression, specific rejection reasons for disabled policy authorization, unexpired horizon, monthly cap, insufficient duration, direct-repayment exclusion, and installments rounding down to zero. The tests also verified preservation of old plan/schedule rows, one audit event on success, refusal when multiple active plans exist, and rollback when the audit write fails. Payroll preview and apply tests verify active-plan selection, no deduction after the plan horizon, exact balance reduction, schedule/trace/audit updates, rejection of repeated payroll application, and no duplicate repayment trace. The fixture now matches the committed base schema's audit-log field sizes/types. See [workflow run 38120090114](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38120090114).
- The committed `database/ahl_el_kheir.sql` confirms `employees.user_id` is the canonical employee/user relationship. It defines `audit_log.action` as `VARCHAR(100)`, `entity_type` as `VARCHAR(50)`, `entity_id` as `BIGINT UNSIGNED`, `ip_address` as `VARCHAR(45)`, and `user_agent` as `VARCHAR(255)`. The service's action/entity values fit these fields, and the CI fixture now uses these exact types. The user's local database has not been inspected, so it still must be confirmed to match the checked-in dump.

**Still required before applying the migration to the working database or declaring the feature complete:**

1. Confirm the user's actual local database schema matches the checked-in base dump; the repository schema is now verified, but the local instance has not been inspected.
2. Extend the focused tests to cover stale balance/concurrent payroll interaction and notification failure isolation. The payroll apply helper is tested in isolation; the complete application payroll/accounting posting path is still unverified.
3. Run existing salary-advance payroll regression tests and account 1410 reconciliation against the disposable/local test database.
4. Record those results here. Do not treat a fixture-based migration test as proof that the full feature works end to end.

The CI database is disposable and uses a deliberately minimal schema fixture to exercise this migration. The workflow does not connect to the user's local XAMPP database. Do not apply the migration to the working database until the remaining checks pass.

## 8. FM rescheduling source implementation — 2026-10-11

**Status: PHP syntax, disposable MariaDB migration, and core service-safeguard tests passed; end-to-end payroll/local-schema verification remains required. Do not apply the migration to the working database yet.**

The following source work now exists on `main`:

- Added `hrSalaryAdvanceScheduleReschedule()` to `modules/hr/lib_salary_advance_repayment.php`. It validates the request and saved policy authorization, locks the request and current plan, requires the current plan horizon to have elapsed, validates a future month, limits duration and monthly amount to the saved policy, confirms the proposed installment can cover the outstanding balance, supersedes the old plan, inserts a replacement plan and schedule rows, and writes an audit event in the same transaction. The page sends an employee notification after the transaction commits; notification failure is isolated from the already-committed financial change.
- Added `modules/hr/salary_advance_reschedule.php`, an FM-only page listing eligible expired plans and collecting the proposed start month, duration, monthly installment, and reason.
- Added a link to the new page from the salary-advance processing screen for FM users.
- Schedule history now includes plan number/status and the plan's start/duration/reason so replacement schedules can be distinguished from the preserved historical plan.
- Corrected the versioned-plan migration and initial-plan generator so a policy with `maximum_repayment_months IS NULL` retains an unbounded plan duration. Both payroll queries explicitly preserve the existing no-fixed-horizon behavior for those policies. This avoids accidentally stopping payroll after one month for a policy that deliberately has no duration boundary.
- Tightened the migration's plan-duration column to nullable even if an earlier draft of the new table had already been created.

Source commits for this continuation include `365c7ca`, `050a4c8`, `688b2ce`, `801411f`, `ca53bc5`, `1d2180e`, `3404e72`, `9ee080e`, `1f2872d`, `8523a92`, `69eebd9`, `94e255b`, `91cde79`, `635ccf4`, `f776dc7`, `f4cec9f`, `47c6f30`, `def80c6`, `2e4c2e3`, `109318d`, and `f3f7ee3`.

### Verification gate and known limits

These edits have been made through remote repository access only. PHP syntax checks, a disposable MariaDB 10.4.32 migration/backfill/rerun test, and core rescheduling-service safeguard/rollback tests have passed in GitHub Actions. Payroll integration and accounting regression tests have **not** been run in this continuation. The new FM page/service must not be considered production-ready until those remaining checks pass.

The current service intentionally rejects rescheduling when the saved policy has no finite `maximum_repayment_months`, because there is no expired finite horizon to qualify for this exception. It uses the proposed monthly amount as the new scheduled installment for all payroll-based repayment methods; payroll still limits actual deduction by eligible salary and the policy's monthly cap. Confirm this matches the organization's intended rescheduling terms during local workflow testing.

The UI lists only requests with an active plan, a positive outstanding balance, an expired plan, a positive saved maximum duration, and explicit saved-policy FM authorization. It does not provide a way to reschedule an in-horizon plan.

**Next required work:** confirm the audit-log schema from the actual local database/schema source; add tests for stale balance/concurrent payroll and notification failure isolation; verify the full application's payroll-to-accounting posting path; then run the existing payroll repayment regressions and account 1410 reconciliation. Do not claim the feature complete until the remaining evidence is recorded.

