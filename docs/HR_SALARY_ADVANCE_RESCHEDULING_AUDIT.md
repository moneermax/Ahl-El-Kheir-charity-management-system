# Salary Advance Rescheduling — Audit and Design Gate

**Date:** 2026-10-11  
**Branch:** `main`  
**Status:** SYNTAX, FULL-DUMP MIGRATION, RESCHEDULING, STAGE 5 PAYROLL REGRESSIONS, ACCOUNT 1410 RECONCILIATION, AND PAYROLL/RESCHEDULING CONCURRENCY PASSED IN CI; LOCAL INSTANCE AND BROWSER WORKFLOW VERIFICATION PENDING

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

- A fresh verification run on the current implementation commit completed successfully across all four jobs: PHP syntax checks, migration on disposable MariaDB 10.4.32, import of the checked-in full schema plus legacy data and migration/backfill/rerun verification, and rescheduling-service/payroll safeguards. [Workflow run 38121863772](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38121863772). This is isolated CI evidence; it does not inspect the user's local XAMPP database or replace the remaining end-to-end accounting check.


- PHP 8.2 syntax checks for the salary-advance PHP files and notification dependency passed: [workflow run 38119108564](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38119108564).
- The Stage 7 migration ran twice against an isolated MariaDB 10.4.32 database. The test verified the default-disabled policy flag, legacy schedule backfill, preservation of both finite and NULL/unbounded policy durations, preserved schedule row count, plan-scoped indexes replacing the old indexes, and no duplicate plan or schedule rows on rerun. This job passed in the same workflow run.
- The same migration also passed against the **full checked-in `database/ahl_el_kheir.sql` dump and its legacy rows** on MariaDB 10.4.32. The full-schema test verified every legacy schedule row remained and received a plan link, one initial plan per scheduled request, policy durations/caps and start months preserved, existing policy authorization left disabled, old unique indexes replaced, and rerun safety. See [workflow run 38120228433](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38120228433).
- The rescheduling service and payroll eligibility query passed disposable-database tests for successful plan replacement, active-plan-only payroll selection, post-horizon suppression, specific rejection reasons for disabled policy authorization, unexpired horizon, monthly cap, insufficient duration, direct-repayment exclusion, and installments rounding down to zero. The tests also verified preservation of old plan/schedule rows, one audit event on success, refusal when multiple active plans exist, and rollback when the audit write fails. Payroll preview and apply tests verify active-plan selection, no deduction after the plan horizon, exact balance reduction, schedule/trace/audit updates, rejection of repeated payroll application, and no duplicate repayment trace. The fixture now matches the committed base schema's audit-log field sizes/types. See [workflow run 38120090114](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38120090114).
- The committed `database/ahl_el_kheir.sql` confirms `employees.user_id` is the canonical employee/user relationship. It defines `audit_log.action` as `VARCHAR(100)`, `entity_type` as `VARCHAR(50)`, `entity_id` as `BIGINT UNSIGNED`, `ip_address` as `VARCHAR(45)`, and `user_agent` as `VARCHAR(255)`. The service's action/entity values fit these fields, and the CI fixture now uses these exact types. The user's local database has not been inspected, so it still must be confirmed to match the checked-in dump.

**Still required before applying the migration to the working database or declaring the feature complete:**

1. Confirm the user's actual local database schema matches the checked-in base dump; the repository schema is now verified, but the local instance has not been inspected.
2. The focused service test's live-balance revalidation case passed in CI: it changes the request balance after the hypothetical form display and verifies the replacement plan and schedule use the current database balance. The throwing notification-stub regression test also passed in CI; it verifies the notification attempt is contained and committed repayment balance/trace data remain unchanged. Separate concurrent payroll/reschedule coverage remains pending. The payroll apply helper is tested in isolation; the complete application payroll/accounting posting path is still unverified.
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

Follow-up verification commits include `af91d773606a201deae51597f41ca2e5ee77a4bc` (direct-repayment list exclusion), `032594e0c9161920d0bf37df0ece67f344e54193` (live-balance test), `7af0c52e22a1113e669d7d815faeeed9d4071115` (notification failure isolation), `e21e14a5cd8d6e928a06de2d941dfff52949ba9a` (notification-failure regression test), `1a6e822405e4de3d471ba32fd196666bc6517697` (employee test fixture), and `04597a200af3dde107e70816f34e8c85094e46cb` (CLI `APP_URL` test fixture). All passed the latest CI run.

Source commits for this continuation include `365c7ca`, `050a4c8`, `688b2ce`, `801411f`, `ca53bc5`, `1d2180e`, `3404e72`, `9ee080e`, `1f2872d`, `8523a92`, `69eebd9`, `94e255b`, `91cde79`, `635ccf4`, `f776dc7`, `f4cec9f`, `47c6f30`, `def80c6`, `2e4c2e3`, `109318d`, and `f3f7ee3`.

### Verification gate and known limits

These edits have been made through remote repository access only. The latest GitHub Actions run (`38124958731`, commit `04597a200af3dde107e70816f34e8c85094e46cb`) passed all four jobs: PHP syntax, migration on disposable MariaDB 10.4.32, migration against the checked-in full schema/backfill, and rescheduling-service/payroll-helper safeguards. The payroll apply helper is tested in isolation, but the full application payroll/accounting posting path and account 1410 reconciliation remain unverified. The new FM page/service must not be considered production-ready until the remaining checks pass.

The current service intentionally rejects rescheduling when the saved policy has no finite `maximum_repayment_months`, because there is no expired finite horizon to qualify for this exception. It uses the proposed monthly amount as the new scheduled installment for all payroll-based repayment methods; payroll still limits actual deduction by eligible salary and the policy's monthly cap. Confirm this matches the organization's intended rescheduling terms during local workflow testing.

The UI lists only requests with an active plan, a positive outstanding balance, an expired plan, a positive saved maximum duration, and explicit saved-policy FM authorization. It does not provide a way to reschedule an in-horizon plan.
- Follow-up UI correction `af91d773606a201deae51597f41ca2e5ee77a4bc` also filters the FM list to the three payroll-based repayment methods accepted by the service, so direct-repayment advances are not presented as eligible. The source change and its regression coverage are included in the latest successful CI run.

**Next required work:** confirm the local XAMPP database matches the checked-in dump; add or run concurrent payroll/reschedule coverage; verify the full application's payroll-to-accounting posting path; then run the existing payroll repayment regressions and account 1410 reconciliation. Do not apply the migration to the working database or claim the feature complete until the remaining evidence is recorded.



## 9. Latest verification update — 2026-10-11

The latest run, [38124958731](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38124958731), completed successfully on commit `04597a200af3dde107e70816f34e8c85094e46cb`. All four jobs passed: PHP lint, MariaDB 10.4.32 migration, full checked-in schema/backfill migration, and rescheduling/payroll-helper service tests. The notification failure test now reaches the stub and confirms a delivery exception does not change the already-committed repayment trace or outstanding balance.

A source review confirms rescheduling locks the request before locking its active plan, while payroll applies a joined `FOR UPDATE` query over schedule, plan, and request records. InnoDB deadlock detection should abort one competing transaction rather than permit both to proceed, but the lock-order behavior has not yet been exercised by a two-connection concurrency test. Treat this as an explicit remaining verification item; no speculative lock-order rewrite has been made.

No migration has been applied to the user's local database. Local schema confirmation, full application payroll/accounting-path verification, concurrency testing, existing payroll regressions, and account 1410 reconciliation remain open.


## 10. Expanded regression workflow result — 2026-10-11

The expanded workflow [run 38125299220](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38125299220), on commit `6b4e7e659e5b8532695a4ae6b79c9a94bd0cf6b4`, passed all four jobs. In addition to PHP lint, the disposable MariaDB 10.4.32 migration test, full checked-in schema/backfill migration, and focused rescheduling service tests, the full-schema job ran these existing regression programs after migration:

- `tools/run_salary_advance_stage5_schedule_tests.php`
- `tools/run_salary_advance_stage5_payroll_tests.php`
- `tools/run_salary_advance_stage5_payroll_edge_tests.php`
- `tools/run_salary_advance_1410_reconciliation.php`

All completed successfully against the disposable CI database. The workflow change also lints these scripts. This is CI evidence, not evidence that the migration has been applied to the user's local XAMPP database or that a browser-driven end-to-end payroll posting was exercised.

The payroll and rescheduling services both use InnoDB transactions and row locks, but their SQL lock acquisition order differs: payroll starts from the joined eligible schedule query, while rescheduling locks the request first and then its active plan. No lock-order change is made based solely on inspection; a controlled two-connection concurrency regression remains a separate gate. Until that test and local schema/integrity confirmation are complete, do not apply the migration to the working database or mark Stage 7 closed.


## 11. Ordered payroll/rescheduling locks — 2026-10-11

Commit [dfbc193354fda8f1a5295143026d8659e5a08fd6](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/dfbc193354fda8f1a5295143026d8659e5a08fd6) now locks the employee's outstanding disbursed salary-advance request rows in ascending request ID order before payroll locks eligible schedule/plan rows. This aligns the parent-request lock order with rescheduling's request-then-plan order and reduces one avoidable source of lock inversion. The change does not alter repayment amounts, schedule selection, or accounting behavior.

Verification: [GitHub Actions run 38126111808](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38126111808) passed all four jobs: PHP lint, MariaDB 10.4.32 migration, full checked-in schema/backfill migration, and rescheduling-service tests (including the Stage 5 schedule/payroll/edge regressions and account 1410 reconciliation in the full-schema job). This establishes regression compatibility for the new lock acquisition step; it is **not** a substitute for a controlled two-connection concurrency test or a browser-driven end-to-end payroll/accounting posting test.

The local XAMPP schema/integrity confirmation remains outstanding. The migration has not been applied to the working database. Do not mark Stage 7 complete until the local schema, concurrency behavior, and full application payroll/accounting path are verified.


## 12. Production payroll transition and transaction-boundary regression — 2026-10-11

Commits [027e70aeb173ba7be6ceec548bd99b5808d4645c](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/027e70aeb173ba7be6ceec548bd99b5808d4645c) and [d2a89b275770ce8f2e76abc1a90bdaa2a872bb67](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/d2a89b275770ce8f2e76abc1a90bdaa2a872bb67) address a transaction-boundary risk and strengthen integration coverage.

- `hrPayrollChangeStatus()` now initializes accounting tables/accounts before opening a transaction only when it owns the transaction. When called inside an existing transaction, it does not run schema-initialization DDL that could implicitly commit the caller's work. The application payroll page calls this function directly; the rollback-only integration test now also exercises the caller-owned transaction case.
- `tools/run_salary_advance_stage5_payroll_tests.php` now invokes the production `hrPayrollChangeStatus(..., 'paid', ...)` path instead of manually marking payroll paid and calling the journal/repayment helpers separately. The test checks the posted payroll journal, repayment trace totals, balanced journal, account 1410 credit, duplicate protection, and rollback-only cleanup.

Both workflow runs [38126267396](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38126267396) and [38126283077](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38126283077) passed all four jobs: PHP lint, MariaDB 10.4.32 migration, full checked-in schema/backfill migration, and rescheduling-service tests. The latter full-schema job also ran the Stage 5 schedule/payroll/edge regression scripts and account 1410 reconciliation successfully. This materially improves integration evidence but remains a CLI rollback-only test, not a browser-driven user journey.

Still open: a controlled two-connection concurrency test, local XAMPP schema/integrity confirmation, and browser-driven payroll/accounting verification. The migration remains unapplied to the working database; Stage 7 remains open.

## 13. Two-connection InnoDB lock serialization regression — 2026-10-11

Commit [eab5e1a8ba4ff98e7df74ace9c75c06bae7cf0c7](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/eab5e1a8ba4ff98e7df74ace9c75c06bae7cf0c7) adds `tools/test_salary_advance_rescheduling_lock_order.php`. It uses two independent PDO connections against the disposable CI database: connection A locks an outstanding disbursed request; connection B confirms it cannot acquire that same request lock within the configured short wait; after A commits, B acquires the request and then its active plan. The script refuses to run unless the dedicated service-test environment/database safeguards are set.

The test is wired into the rescheduling-service CI job by [commit 3fe1e04d81f02ccc61893991757d938e723cb026](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/3fe1e04d81f02ccc61893991757d938e723cb026). [Workflow run 38126660778](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38126660778) completed successfully; all four jobs passed, including the MariaDB 10.4.32 migration, full-schema/backfill and regression job, PHP lint, and the rescheduling-service job containing the new two-connection test.

**Scope limit:** this proves the expected InnoDB parent-request lock serialization primitive and that the test runs in CI. It does not execute a simultaneous production payroll-posting transaction against a real reschedule request, and it does not prove browser-driven payroll/accounting behavior. Those remain distinct verification gates. Local XAMPP schema/integrity confirmation is also still outstanding. The migration remains unapplied to the working database; Stage 7 remains open.

## 14. Actual rescheduling service under a competing row lock — 2026-10-11

Commit [55c2539585b0c175a68c495264837893c18357ce](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/55c2539585b0c175a68c495264837893c18357ce) upgrades the lock-order regression from testing lock acquisition alone to calling the real `hrSalaryAdvanceScheduleReschedule()` service with two independent PDO connections against the disposable CI database.

- Connection A holds the salary-advance request row lock while connection B calls the service with a short InnoDB lock wait timeout.
- The test requires the competing service call to fail with lock timeout and checks that no partial replacement plan was left behind.
- After connection A releases its lock, connection B retries the service. The test requires a replacement plan, preservation of the previous plan as history, exactly one active plan, and one corresponding audit row.
- The script remains guarded by the dedicated disposable database/environment variables; it also restores the intentionally removed `audit_log.user_agent` column in that disposable test database so the actual service can run after the separate audit-failure rollback test.

Verification: [GitHub Actions run 38126940406](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38126940406) completed successfully with all four jobs passing: PHP lint, MariaDB 10.4.32 migration, rescheduling service/concurrency tests, and full-schema migration plus Stage 5 schedule/payroll/edge regressions and account 1410 reconciliation.

The follow-up documentation commit [e378d63d9c131e82086442c321c1855e03c3fe14](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/e378d63d9c131e82086442c321c1855e03c3fe14) was also verified by [workflow run 38127386318](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38127386318), with all four jobs passing.

**Scope limit:** this now exercises the actual rescheduling service against a competing request-row lock, but it still does not run a simultaneous production payroll-posting transaction against a reschedule. Browser-driven payroll/accounting verification and read-only local XAMPP schema/integrity confirmation remain open. The migration has not been applied to the working database; Stage 7 remains open.

## 15. Production payroll transition contending with actual rescheduling — 2026-10-11

Commit [e65f29827251c8c9f256c5a7cb18e82bf9479c48](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/e65f29827251c8c9f256c5a7cb18e82bf9479c48) fixes the disposable CI regression fixture to use an existing `users.id` as the audit actor, respecting the `audit_log.user_id` foreign key.

The new `tools/test_salary_advance_rescheduling_payroll_concurrency.php` regression now executes the actual production payroll paid-transition and rescheduling service in separate PHP processes/connections against the disposable full-schema MariaDB 10.4.32 database:

- While one connection holds the advance request row lock, the competing production `hrPayrollChangeStatus(..., 'paid', ...)` call reaches the configured InnoDB lock timeout and rolls back safely.
- The owning connection performs a real reschedule, creating a replacement plan.
- The payroll transition is retried after the replacement plan commits. The test verifies that payroll reaches paid status and that both a payroll repayment trace and posted payroll journal exist.
- The payroll posting attempt runs inside a rollback-only transaction; the test rolls it back and removes its temporary payroll fixture. It does not commit a payroll posting into a non-test database.

Verification: [GitHub Actions run 38128024549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38128024549), commit `e65f29827251c8c9f256c5a7cb18e82bf9479c48`, passed all four jobs: PHP lint, MariaDB 10.4.32 migration, full checked-in schema/backfill plus Stage 5 payroll regressions and account 1410 reconciliation, and rescheduling service/concurrency tests. The full-schema job output explicitly reported the competing payroll transition timeout, successful retry against the replacement plan with journal/repayment trace, and the overall concurrency regression as PASS.

**Scope limit:** this is strong CLI/database integration evidence for the production payroll transition interacting with rescheduling under contention. It is still not a browser-driven user journey and does not confirm that the user's local XAMPP database matches the checked-in schema. No migration has been applied to the local database. Local schema/integrity inspection and browser-driven payroll/accounting verification remain open; Stage 7 is not closed.



## 16. Policy creation must persist rescheduling authorization — 2026-10-11

A deeper review after the passing payroll-contention regression found a separate gap not covered by the existing rescheduling tests: the FM policy-creation INSERT listed `allow_fm_repayment_rescheduling` as a column but omitted its value from the parameter array. The resulting column/placeholder/parameter mismatch could prevent creating a policy version. Policy editing already included the field in its update statement.

Commit [35934865d3b6729323b1de356fbbf75545cf47f5](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/35934865d3b6729323b1de356fbbf75545cf47f5) extracts the exact INSERT into `hrSalaryAdvancePolicyInsertVersion()` in `modules/hr/lib_salary_advance_policy.php` and makes the policy editor call that shared helper. The full-schema CI regression now calls the same helper with a validated policy that explicitly enables rescheduling, reads the inserted row back, verifies the authorization and adjacent repayment-rule fields landed in the intended columns, and rolls the fixture back.

Verification: [GitHub Actions run 38128854517](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38128854517) passed all four jobs. The full-schema job explicitly reported the migration/backfill/rerun and rollback-only policy-creation regression as PASS. This is verified against the checked-in schema in disposable CI, not the local XAMPP database.

**Scope limit:** Stage 7 remains open until read-only local schema/integrity confirmation and browser-driven policy/rescheduling/payroll/accounting checks are completed. The local migration remains unapplied.


## 17. Read-only local preflight before the remaining runtime gate — 2026-10-11

Added `tools/inspect_salary_advance_rescheduling_prerequisites.php` to make the local verification step small and safe. It uses the application's configured database connection but performs only metadata and `SELECT` queries. It checks the legacy tables/columns required by Stage 7, request/policy and schedule/request integrity, and whether the migration markers are all absent, all present, or partially applied. It never runs the migration and does not print employee or financial row details.

The tool is included in the PHP syntax CI job. After the latest `main` commits are pulled locally, run it from the repository root with the XAMPP PHP CLI. Its output will determine whether the local database is in the expected pre-migration state or needs investigation before any schema change.

**Scope limit:** CI passing does not replace this local read-only check or browser-driven verification. Stage 7 remains open and the local migration remains unapplied.


## 18. Automatic repayment installment recommendation during first FM approval — 2026-10-11

The first-approval workflow now calculates the minimum fixed monthly installment needed to finish within the saved policy's maximum repayment period, rounds the recommendation upward to two decimal places, and pre-fills the FM customization form when the requested installment would exceed that period. The FM can review or adjust the amount before approval. If the calculated amount exceeds the saved policy's maximum monthly deduction, the UI explains that the requested amount cannot fit the policy horizon under the current cap; the recommendation is not silently applied as an exception.

The request validation helper now accepts the candidate approved terms when checking policy mismatches. This closes the prior gap where marking terms as customized skipped all policy mismatch checks: customized amount, repayment method, monthly installment, repayment duration, and start-month constraints are checked against the saved policy before the approval can be committed. The disbursement schedule generator's existing duration guard remains unchanged.

A rollback-free pure calculation regression has been added to the disposable full-schema test for:
- 70,000 over six months at a 15,000 monthly cap, recommending 11,666.67;
- the same recommendation being blocked by a 10,000 monthly cap;
- upward currency-precision rounding; and
- refusing to recommend an installment when there is no finite policy horizon.

GitHub Actions run 38151858277 passed all four jobs on commit 572dc85afdddb9dbe238457d73f4d1dfae73bdd9: PHP lint, MariaDB 10.4.32 migration, full-schema migration/backfill with payroll regressions and account 1410 reconciliation, and rescheduling service/concurrency tests. The new fixed-installment policy validation cases passed in the full-schema job. This is repository/CI work only; the local XAMPP browser workflow has not been exercised by this change.


## 19. FM dashboard navigation and local Stage 7 preflight — 2026-10-11

PR [#92](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/pull/92) was merged as commit [aa6e9e69039c54dc2b11d6852ac66cfe1317aac3](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/aa6e9e69039c54dc2b11d6852ac66cfe1317aac3). The salary-advance dashboard now exposes a rescheduling card to users whose role is `financial_manager` or `fm`; the destination page's server-side policy, plan-expiry, repayment-method, and outstanding-balance checks remain authoritative. The dashboard was added to the PHP syntax CI list. The change does not enable policy authorization or modify the database.

Verification: [GitHub Actions run 38165399320](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/38165399320) passed all four jobs: PHP lint, the guarded rescheduling-service tests, full checked-in-schema migration/backfill and rollback-only payroll/accounting regressions, and migration verification on MariaDB 10.4.32.

The user's read-only local preflight against the canonical `ahl_el_kheir` database also passed. All three Stage 7 schema markers were present; there were 15 requests, 30 schedule rows, and one policy version; no requests lacked a policy, no schedules lacked a request or plan, and no requests had multiple active plans. This means the Stage 7 migration artifacts are already present locally. **Do not reapply the migration**; the preflight is not a reason to run it again.

A separate read-only account 1410 reconciliation passed: ledger balance and live outstanding balance both equaled 250,000 (debits 280,000 less direct-repayment credits 30,000). The current local policy version is limited to six months, has a monthly deduction cap of 10,000, and has `allow_fm_repayment_rescheduling = 0`. There are six active repayment plans and no schedules missing a plan. The user opened the policy page and FM rescheduling page: the policy authorization checkbox is visible but unchecked, and the rescheduling page correctly reports no eligible advances under the current policy. No policy setting was changed.

### Remaining Stage 7 gate

- The dashboard navigation code is merged and all four CI jobs pass.
- Local schema/integrity preflight and account 1410 balance reconciliation have passed without writes.
- Policy authorization remains disabled because the current saved policy does not authorize the exception; do not enable it without the prior GM/FM agreement described above.
- Still required: verify the FM dashboard card after pulling the merged commit, and complete a browser-driven end-to-end rescheduling and payroll/accounting journey using an isolated test fixture that is safe to mutate. Do not use the canonical `ahl_el_kheir` database for destructive service tests, and do not declare Stage 7 closed based only on CI or the read-only preflight.

### Current-status clarification

The read-only local preflight recorded in this section supersedes earlier statements in sections 13–18 that the Stage 7 migration artifacts were absent or unapplied. The latest preflight found the Stage 7 schema markers already present in the canonical `ahl_el_kheir` database. Do not rerun the migration. This clarification does not authorize enabling rescheduling or changing the saved policy.
## 20. Local isolated full-schema Stage 7 migration verification — 2026-10-11

The full checked-in schema was imported without application data into the dedicated disposable MariaDB database `salary_advance_full_schema_test`. The Stage 7 full-schema regression script was guarded to permit only `salary_advance_migration_test` and `salary_advance_full_schema_test`, and still requires `SALARY_ADVANCE_MIGRATION_TEST=1`. The script verifies the connected database against that allowlist.

Verification completed successfully against `salary_advance_full_schema_test`:

- The Stage 7 migration and legacy repayment-schedule backfill passed.
- Existing schedule-row counts and repayment-plan links were verified.
- Plan-scoped indexes and migration rerun safety passed.
- FM policy creation persisted the rescheduling authorization and adjacent repayment-rule values correctly; the policy fixture was rolled back.
- The test process exited with code `0`.

**Scope limit:** This verifies migration and policy behavior against the checked-in full schema in a disposable database. It does not complete browser-driven verification of FM policy creation, rescheduling, payroll, and accounting against the local application. Stage 7 remains open. The canonical `ahl_el_kheir` database was not used for this destructive test, and the untracked receipt image remains untouched.



## 2026-10-12 — Isolated browser rescheduling transaction verified; final acceptance still open

After the user submitted the rescheduling form for request 7 in salary_advance_browser_test, read-only SQL returned:
- actual database: salary_advance_browser_test;
- plan 1 row ID 2 is superseded and its five historical schedule rows remain (total 50,000.00);
- plan 2 row ID 8 is active, starts 2026-11-01, duration six months, monthly cap 10,000.00;
- plan 2 has five installments totaling 50,000.00, maximum installment 10,000.00, first/last scheduled months 2026-11-01 / 2027-03-01;
- audit row 2263 records HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE for request 7.

The user also reported that the success confirmation appeared inline instead of as a toast. Inspection established that the page's success/error alerts were not matched by the existing global converter because they lacked alert-dismissible. Commit b546005f7f7996d1c4f1946de82e9c72563733f7 adds that marker only to transient success/error alerts, leaving the persistent policy/instruction warning unchanged. The local checkout still needs to pull the commit, run PHP syntax validation, and confirm in-browser that the success/error messages are converted to toasts. The payroll/accounting path remains unverified. Do not close Stage 7 until that path is verified; do not mutate canonical ahl_el_kheir or repeat the already-completed rescheduling transaction.


## 2026-10-12 — Stage 7 payroll acceptance progress and payroll table refinement

The isolated browser workflow has reached November 2026 draft payroll generation in `salary_advance_browser_test`. The application created 22 drafts using payroll policy version 2. Read-only SQL identified payroll row ID 46 for employee ID 8 / EMP-0021: basic salary 10,000.00, salary-advance deduction 10,000.00, net salary 0.00, status `draft`, accounting status `none`, with no accounting entry, journal entry, or payment account yet. This confirms the active November rescheduled installment is reflected in the draft. It does not yet prove repayment application or accounting posting. Do not use the period-wide “approve drafts” action; it affects all 22 records.

Read-only account lookup in the isolated database returned account ID 2 / code 1200 (Bank), ID 37 / code 1410 (Employee Salary Advances Receivable), and ID 14 / code 5200 (Salaries). The next acceptance gate is to approve and pay only payroll row 46 using the page’s per-row actions, then verify exactly one repayment application, schedule/payment linkage, request outstanding balance, paid payroll state, posted accounting entry, and a 10,000.00 credit to account 1410. The canonical `ahl_el_kheir` database must not be modified.

The user also requested a more compact payroll table. Commit `ae7d9fc6209a8ff1366afa96b9fb73720d614249` on `main` removes the Contract and Policy columns, removes redundant currency labels from amount cells, tightens input/typography spacing, adds distinct blue cell borders and a blue-tinted header, and removes now-unused joins from the month-list query. The shared policy summary above the table remains. The user later reviewed the compact layout locally and described it as much better. This is browser visual feedback only; no separate PHP syntax-check result is asserted here.

The user confirmed the rescheduling success/error feedback now appears as a toast after commit `b546005f7f7996d1c4f1946de82e9c72563733f7`. The isolated payroll repayment/accounting acceptance and temporary-environment cleanup are now complete; see the dated Stage 7 acceptance-closure entry below for verified results. Do not restore the temporary routing again or repeat the completed acceptance absent concrete regression evidence.

## 2026-10-12 — Final Stage 7 isolated browser/payroll/accounting acceptance: PASS

### Rescheduling and draft payroll
The browser rescheduling flow completed in `salary_advance_browser_test` for request 7 (`SAR-2026-00007`, employee 8). The active replacement plan is ID 8, starts 2026-11-01, has a six-month duration and 10,000.00 monthly installment cap, and contains five pending installments from November 2026 through March 2027. The former plan (ID 2) is superseded and retained as history. Audit row 2263 records `HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE` for request 7. The success feedback appeared as a toast after commit `b546005f7f7996d1c4f1946de82e9c72563733f7`.

### Individual payroll posting
The user generated November 2026 drafts, then individually approved and paid only payroll ID 46 for employee 8. The other 21 payroll rows remained drafts. Read-only SQL verified:
- payroll 46: `status=paid`, `accounting_status=posted`, `accounting_entry_id=116`, salary-advance deduction 10,000.00, net salary 0.00;
- journal 116 (`PAY-46`): posted, reference type `payroll`, reference ID 46;
- journal lines: account 5200 debit 10,000.00 and account 1410 credit 10,000.00, balanced; no bank line is expected because net pay is zero;
- repayment trace ID 3: request 7, schedule 42, payroll 46, actual amount 10,000.00, outcome `applied`, accounting entry 116; exactly one matching trace exists;
- schedule 42: scheduled/applied amount 10,000.00, status `paid`, linked payroll 46;
- isolated request 7 outstanding balance: 40,000.00, down from 50,000.00.

### Cleanup and canonical database safety
The temporary routing override was removed only after validating the backup. Final `.htaccess` contains exactly `Options -Indexes`; `stage7_db_probe.php` is absent. The protected receipt `storage/receipts/salary-advances/SAL-ADV-SAR-2026-00021-48bacf449bde7ad1.png` remains present and untouched. Local Git was on `main`, and `git status --short` showed only that receipt as untracked.

Read-only checks against canonical `ahl_el_kheir` verified policy version 1 is still unchanged (`allow_fm_repayment_rescheduling=0`, maximum repayment duration 6 months, maximum monthly deduction 10,000.00) and request 7 remains `disbursed` with outstanding balance 50,000.00. The observed posted account 1410 balance was 250,000.00. No policy, request, payroll, journal, or other financial data in the canonical database was mutated by the isolated acceptance.

**Final status: Stage 7 ACCEPTANCE CLOSED at this documented boundary.** Do not repeat the successful journey or rerun closed tests without concrete regression evidence. Remaining local-runtime evidence gaps: the same employee with multiple simultaneously eligible advances and true concurrent production execution were not proven. Existing CI tests for lock contention are useful separate evidence, but do not erase these explicit local-runtime gaps.
