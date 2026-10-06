# GM Salary Advance Waiver — Continuation Checkpoint

Date: 2026-10-06
Status: implementation hardened; payroll repayment dependency runtime verified; post-commit notification gate runtime verified; remaining waiver verification gates still open.

## Requirement
GM may decide, for any reason, to waive salary advances or submitted salary-withdrawal requests. A blanket decision includes previous loans. If a current payroll installment was already deducted and the remaining salary paid, that deducted amount must be refunded and the remaining covered advance balance must be waived. Example: 100,000 original, 60,000 previous repayments, 10,000 current deduction, 30,000 remaining => refund 10,000, waive 30,000, final 1410 balance zero. Historical disbursement and repayment records remain untouched.

## Workflow
1. GM informs FM outside the system.
2. FM prepares the concrete decision in-system: blanket/individual scope, effective payroll month, reason, affected advances, refund account, waiver expense account. No posting occurs.
3. Status becomes pending_gm.
4. GM reviews and approves or rejects. Approval creates no journal. Rejection requires a reason and has no financial effect.
5. Approved status is approved_by_gm; control returns to FM.
6. FM executes atomically; final status is executed.
7. Notifications are sent after commit.

## Accounting
Original disbursement remains Dr 1410 / Cr actual source. Waiver is Dr selected active expense / Cr 1410. Current paid deduction refund is Dr 1410 / Cr selected refund source. Refund sources are active 1100/1200/1300. Waiver expense must be active account_type=expense. Accounts are stored before GM approval and reused at execution. Never rewrite original journals or payroll repayment rows; never use whole-payroll reversal.

## Request and schedule rules
Disbursed requests remain disbursed and outstanding becomes zero. Undistributed qualifying requests are existing submitted, fm_review, or approved requests with closed_at IS NULL; they are cancelled without accounting. Do not invent a new request status. Future unpaid schedules remain historical rows; an overlay records waived installments. Executed waivers block future payroll deductions. Draft payrolls are refreshed. Approved-but-unpaid payrolls are blocked rather than silently modified.

## Notifications — mandatory
- FM preparation commit -> active GM: decision waiting for approval.
- GM approval commit -> preparing FM: approved and ready for execution.
- GM rejection commit -> preparing FM: rejected plus reason.
- FM execution commit -> approving GM: execution confirmation, effective month, refund total and waiver total.
- FM execution commit -> every affected employee with a linked active account: GM decision + FM action; include qualifying refund, waived balance and stopped future deductions, or cancellation of an undistributed request. Consolidate multiple affected advances into one employee notification.
Notification failure must never roll back committed business/financial work. Employees without linked accounts remain auditable but cannot receive in-system notification. Do not introduce the previously rejected notification migration.

## Isolated implementation
Eight files only: database/migrations/2026-10-05_hr_salary_advance_waiver.sql; includes/sidebar.php; lang/ar.php; lang/en.php; modules/hr/lib_salary_advance_payroll.php; modules/hr/lib_salary_advance_waiver.php; modules/hr/salary_advance_waiver_fm.php; modules/hr/salary_advance_waiver_gm.php.
Tables: hr_salary_advance_waiver_decisions, hr_salary_advance_waiver_items, hr_salary_advance_waiver_schedule_items.
Baseline: af111bdfbb3770ea5ac782bc2f54376cfb565709. Latest notification wiring: 4415244444006e445912d231e8b59e80773b7857.

## 2026-10-06 Execution Hardening
During the fresh source audit, execution was found to revalidate the live request balance but not explicitly re-read and lock the paid payroll repayment evidence used to calculate the approved refund. The execution path now locks the relevant paid repayment rows for each disbursed request, recalculates their actual paid amount for the effective month, and aborts atomically if the presence or total amount differs from the GM-approved snapshot. This does not modify historical payroll repayment rows.

A notification-path audit also found that employee execution notifications were selected from the employee-linked user ID without requiring the linked user account to remain active. The execution notification query now joins `users` and requires `u.is_active = 1`; inactive/unlinked employee accounts remain financially/audit complete but do not receive an in-system notification, matching the documented requirement.

## Verification gate
Before closure: syntax; migration; FM blanket/individual preparation; GM/FM notifications; approval/rejection; atomic execution; paid-deduction refund; remaining waiver; 1410 reconciliation; historical preservation; future-deduction blocking; draft refresh; approved-unpaid safety; undistributed cancellation; multiple advances; blanket/individual scope; duplicate/concurrency protection; rollback; final GM and employee notifications; consolidated employee notification; audit; notification failure isolation; final reconciliation. Prefer rollback-only/SAVEPOINT fixtures.

## Rollback
The feature is file-isolated but has multiple commits. Do not claim one-commit rollback exists. Rollback must be non-destructive and must include database reversal planning if migration is applied.



## 2026-10-06 — Waiver-specific transaction-composable execution runtime verified

The first waiver-specific rollback-only runtime gate was executed locally on `main` after the PDO statement-row-count correction.

Command:
`php tools\\run_salary_advance_gm_waiver_rollback_tests.php`

Result:
- **PASS — transaction-composable GM waiver execution:** `SAR-2026-00004`; rollback-only decision `2`; remaining balance waived = 50,000.00 SDG; current-period refund = 0.00; waiver journal = 117; 10 future schedule overlay rows created inside the test transaction.
- **PASS — rollback-only cleanup:** decision rows = 0; waiver item rows = 0; schedule-overlay rows = 0 after rollback.

This verifies the no-refund remaining-balance execution path, caller-owned transaction composability, waiver journal balancing, future schedule overlay creation, preservation of the disbursed request status, and complete rollback of the temporary decision/accounting/schedule mutations.

This does **not** close the waiver feature. Refund, rejection, undistributed-request cancellation, draft refresh, approved-unpaid protection, blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notifications, audit preservation and final reconciliation remain open.

## 2026-10-06 Payroll Repayment Dependency Runtime Verification

The existing rollback-only Stage 5 payroll integration harness was executed locally on `main`:

`php tools\run_salary_advance_stage5_payroll_tests.php`

Results:
- PASS — Payroll repayment application: `SAR-2026-00004`, temporary payroll ID 24, deduction 5,000 SDG, one repayment trace row, schedule status `paid`, outstanding balance 45,000 SDG, journal 116 balanced.
- PASS — Duplicate repayment protection for the same request/payroll.
- PASS — Rollback-only cleanup; no payroll/request/schedule/journal mutation was committed.

This proves the actual payroll repayment/accounting dependency used by the waiver without committing test data. It does not prove the waiver execution itself.


## 2026-10-06 — Paid-deduction refund branch runtime verified

The dedicated rollback-only refund harness was executed locally on `main` after correcting only its expense-account helper return type.

Command:
`php tools\\run_salary_advance_gm_waiver_refund_rollback_tests.php`

Result:
- **PASS — paid-deduction refund branch:** `SAR-2026-00004`; temporary payroll ID 24; current salary-advance deduction = 5,000.00 SDG; refund = 5,000.00 SDG; total waived = 50,000.00 SDG; refund journal = 119; waiver journal = 120.
- The test verified the full sequence using the real payroll/accounting/repayment functions: paid payroll repayment -> FM preparation -> GM approval -> FM execution.
- The executed waiver preserved the request as `disbursed`, reduced final outstanding balance to zero, preserved the historical paid repayment row and its original payroll accounting entry, and produced the expected refund journal Dr 1410 / Cr 1100 plus waiver journal Dr selected expense / Cr 1410.
- **PASS — rollback-only refund cleanup:** payroll rows = 22; repayment rows = 0; waiver decision rows = 0; waiver journal rows = 0 after rollback.

This closes the controlled paid-deduction refund execution gate. It does not close the overall waiver feature. Remaining gates include undistributed-request cancellation, future-deduction blocking and draft refresh, approved-unpaid payroll protection, blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notification behavior and failure isolation, audit preservation, and final 1410 reconciliation.

## Next step
Proceed to the waiver-specific controlled verification matrix. First inspect the current waiver source for an existing rollback-only/SAVEPOINT harness or other safe fixture mechanism. Then verify FM preparation, GM approval/rejection, current-period refund, remaining waiver, future-deduction blocking, undistributed-request cancellation, draft refresh, concurrency/duplicate protection, post-commit notifications, audit preservation and final 1410 reconciliation. Do not mark the waiver complete until these gates are evidenced locally.



## 2026-10-06 — Undistributed-request cancellation runtime verified

The dedicated rollback-only cancellation harness was executed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_cancellation_rollback_tests.php`

Result:
- **PASS — undistributed request cancellation:** `SAR-2026-00009`, previous status `approved`, resulting status `cancelled`, with zero accounting journals and zero waiver schedule overlays.
- The test verified FM preparation, GM approval, and FM execution using the real waiver functions.
- The request's outstanding balance was not treated as a financial waiver, and no refund or waiver journal was created.
- **PASS — rollback-only cleanup:** waiver decision rows = 0; item rows = 0; schedule-overlay rows = 0; waiver journal rows = 0 after rollback; the original request status, balance, and `closed_at` were restored.

This closes the controlled undistributed-request cancellation gate. It does not close the overall waiver feature. Remaining gates include future-deduction blocking and draft refresh, approved-unpaid payroll protection, blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notifications and failure isolation, audit preservation, and final 1410 reconciliation.



## 2026-10-06 — Future-deduction blocking and draft-refresh runtime verified

The dedicated rollback-only harness was executed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_draft_refresh_rollback_tests.php`

Result:
- **PASS — future-deduction blocking + draft refresh:** `SAR-2026-00004`; temporary draft payroll ID 24 initially calculated a 5,000.00 SDG salary-advance deduction. After FM preparation, GM approval, and FM execution, the same draft payroll was refreshed to a 0.00 SDG salary-advance deduction; 10 future schedule-overlay rows existed inside the transaction.
- The test also re-ran the real salary-advance payroll eligibility query after execution and confirmed that no pending/partial repayment rows remained eligible for the waived request/effective month.
- The request remained `disbursed` and its outstanding balance became zero inside the test transaction.
- **PASS — rollback-only cleanup:** temporary payroll ID 24 was removed and waiver decision/item/schedule-overlay counts returned to zero.

This closes the controlled future-deduction blocking + draft-refresh gate. It proves the waiver overlay is honored by the existing payroll eligibility calculation and that an existing draft payroll is refreshed after execution.

This does **not** close the overall waiver feature. Remaining gates include approved-unpaid payroll protection, blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notification behavior and failure isolation, audit preservation, and final 1410 reconciliation.


## 2026-10-06 — Approved-unpaid payroll protection runtime verified

The dedicated rollback-only harness was executed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_approved_unpaid_rollback_tests.php`

Result:
- **PASS — approved-unpaid payroll protection:** `SAR-2026-00004`; temporary payroll ID 24 was created as `approved` with a 5,000.00 SDG salary-advance deduction.
- FM preparation and GM approval succeeded, leaving the waiver at `approved_by_gm`.
- FM execution was correctly blocked because the approved, unpaid payroll still contained a positive salary-advance deduction.
- The error explicitly required correction of the payroll before waiver execution and confirmed that no financial effect was made.
- Request status/balance and payroll deduction/net salary remained unchanged; no waiver/refund journals were created.
- **PASS — rollback-only cleanup:** temporary payroll ID 24 and all waiver decision/item/overlay/journal rows were removed.

This closes the approved-unpaid payroll protection gate. The protection is now runtime verified as an explicit pre-financial-mutation block.

Remaining gates: blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notification behavior and failure isolation, audit preservation, and final 1410 reconciliation.


## 2026-10-06 — Blanket waiver scope and execution runtime verified

The dedicated rollback-only blanket-scope harness was executed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_blanket_scope_rollback_tests.php`

Result:
- **PASS — Blanket waiver scope + execution:** effective month `2026-10-01`; 6 eligible requests were snapshotted and all 6 executed.
- The 6-item blanket contained 5 disbursed requests and 1 undistributed request.
- The 5 disbursed requests were financially waived; the 1 undistributed request was cancelled without accounting.
- Combined waiver amount was 190,000.00 SDG and the resulting waiver journal was balanced.
- The live fixture contained no employee with multiple eligible advances at the same time (`multiple_employee_advances=not_present`), so the blanket-all-eligible scope was runtime verified, but the specific multiple-advances-for-one-employee scenario remains untested with a live fixture.
- **PASS — Rollback-only cleanup:** decision, item, and schedule-overlay rows returned to zero.

This closes the blanket-scope gate. The separate multiple-advances-for-one-employee runtime scenario remains open until a genuine live fixture exists or is created through an explicitly controlled test fixture without committing production data.

Remaining gates: concurrency/duplicate protection, post-commit notification behavior and failure isolation, audit preservation, final 1410 reconciliation, and the specific multiple-advances-for-one-employee runtime fixture.

## 2026-10-06 — Concurrency / duplicate-execution runtime gate passed

The dedicated rollback-only duplicate-execution harness was executed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_duplicate_execution_rollback_tests.php`

Result:
- **PASS — Duplicate waiver execution protection:** `SAR-2026-00004`; decision ID `9`; first execution succeeded; second execution was rejected; journal count remained unchanged.
- **PASS — Rollback-only duplicate cleanup:** decision rows = 0; waiver item rows = 0; schedule-overlay rows = 0 after rollback.

This runtime gate proves that an already executed waiver decision cannot be executed a second time through the production execution function and that the rejected duplicate attempt creates no additional waiver/refund accounting journal. It does not claim a separate two-process concurrent-commit runtime test.

The source inspection already established the stronger concurrency mechanism: the waiver decision row is locked with `SELECT ... FOR UPDATE` before the `approved_by_gm` status check, so concurrent execution attempts on the same decision serialize at that row. The advisory accounting-number lock is not treated as the transaction duplicate guard.

**Verification status:** sequential duplicate-execution protection is **RUNTIME VERIFIED / CLOSED**. True concurrent two-process execution remains a separate evidence item only if later required by the verification matrix.

## 2026-10-06 — Post-commit notification verification prepared

The next verification gate targets the required notification boundary. Source inspection confirms that preparation, GM review, and FM execution call their notification helpers only after the function-owned transaction commits; notification helpers also catch notification-delivery failures so they cannot roll back a completed business/financial action.

A dedicated runtime harness was added:
`tools\\run_salary_advance_gm_waiver_post_commit_notification_tests.php`

The harness will exercise the real committed FM-preparation and GM-approval paths, verify that the corresponding GM/FM workflow notifications exist after commit, and explicitly clean up the committed test decision, items, audit rows, and test notifications without executing any financial waiver.

**Runtime status: PENDING USER EXECUTION.**


## 2026-10-06 — Post-commit notification runtime gate passed

The dedicated committed-fixture notification harness was executed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_post_commit_notification_tests.php`

Result:
- **PASS — Post-commit preparation notification:** decision ID `11`, committed status `pending_gm`, GM notification found.
- **PASS — Post-commit GM approval notification:** the same committed decision reached `approved_by_gm`, and the preparing FM notification was found after the GM review commit.
- **PASS — Post-commit notification cleanup:** decision rows = 0 and test notification rows = 0 after cleanup.

The harness deliberately did not execute the financial waiver. It therefore verifies the real committed notification boundary for FM preparation and GM approval without creating accounting effects. The live installation uses the legacy notification schema; the harness verified the notification through that actual schema and removed only the exact test notification rows it created.

**Verification status:** the FM-preparation → GM notification and GM-approval → FM notification post-commit gate is **RUNTIME VERIFIED / CLOSED**. The broader notification requirement remains open for execution notifications and forced notification-failure isolation runtime evidence.


## 2026-10-06 — Post-commit full execution notification runtime gate passed

The dedicated committed-fixture execution-notification harness was executed locally on main.

Command: php tools\\run_salary_advance_gm_waiver_execution_notification_rollback_tests.php

Result:
- PASS — Post-commit execution notifications: request SAR-2026-00004; decision ID 12; gm_notification=1; employee_notification=1; waiver journal 124.
- The real FM preparation → GM approval → FM execution path committed successfully before notification delivery was checked.
- The approving GM received the execution notification after commit.
- The affected employee with an active linked user account received the execution notification after commit.
- PASS — cleanup: decision rows = 0; test notifications = 0; test journals = 0 after cleanup.

This closes the runtime gate for the required post-commit execution notification delivery to the approving GM and affected employee. The employee notification path uses an active linked user account and the production helper consolidates affected advances per employee.

## 2026-10-06 — Current waiver verification checkpoint after execution-notification gate

Runtime-verified / closed gates:
- waiver transaction-composable remaining-balance execution;
- paid-deduction refund;
- undistributed-request cancellation;
- future-deduction blocking and draft refresh;
- approved-unpaid payroll protection;
- blanket scope and execution;
- sequential duplicate-execution protection;
- FM preparation → GM notification after commit;
- GM approval → FM notification after commit;
- FM execution → approving GM and affected employee notifications after commit.

Still open — do not claim closure:
1. Notification failure-isolation runtime proof: force/induce a real notification-delivery failure through a controlled test path and prove the already-committed waiver execution remains committed.
2. Audit preservation runtime proof: verify original disbursement, payroll repayment and accounting evidence remain unchanged after an executed waiver and that waiver-specific audit evidence is complete.
3. Final 1410 reconciliation: prove the post-waiver 1410 control balance agrees with the remaining live salary-advance receivables after the controlled execution evidence.
4. Same-employee multiple eligible advances: the blanket fixture contained no employee with multiple eligible advances, so employee-level notification consolidation for that exact scenario is not runtime-proven yet.
5. True two-process concurrent execution: source row-locking and sequential duplicate protection are proven, but a dedicated two-process concurrent-commit runtime test has not been executed.

Mandatory continuation rule: the next session must start at item 1 (notification failure isolation). Do not rerun any of the closed gates above unless a later change creates concrete regression evidence.

## 2026-10-06 — Notification failure-isolation harness prepared

Before creating the failure test, the current production notification implementation was inspected directly.

Authoritative notification helper:
- `modules/accounting/lib_transaction_review.php`
- `ak_transaction_review_notify_event()`

Source findings:
- The helper catches `Throwable` around notification delivery.
- When workflow reference columns are unavailable, the helper's reference-aware attempt fails inside its nested try and falls back to the legacy notification shape using only `recipient_user_id`, `title`, `body`, `link`, `is_read`, and `created_at`.
- The live installation has already been runtime-proven to use the legacy notification schema; the previously rejected `2026-09-28_notifications_workflow_references.sql` migration was not used.
- Repository inspection found no existing controlled notification-failure test seam suitable for this gate.

A narrow test-only seam was therefore added to `ak_transaction_review_notify_event()`:
- production behavior is unchanged unless a test defines `ak_notification_test_delivery_hook()` before loading the helper;
- the hook is called at the actual notification-delivery boundary;
- the production helper still catches the injected `Throwable`;
- no schema mutation or permanent failure mode was introduced.

A dedicated harness was added:
`tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

The harness uses the real committed FM-preparation -> GM-approval -> FM-execution path, injects exactly one controlled execution-notification failure, then verifies that a later execution notification can still be delivered and that the already-committed financial/business state remains intact. It also verifies waiver journal balance, request balance, schedule overlays, audit evidence, and exact notification/test-state cleanup.

Implementation commits:
- `2319ccc421fb8944dc3b59a874410868d658d333` — narrow test-only delivery seam.
- `bcce74ec825e3e45193f3e44f58e67bcbe2aa43c` through `46aec64c840bd64007ccff242a2a1a9403f4c8e8` — failure-isolation harness and cleanup hardening.

**Runtime status: PENDING USER EXECUTION.**

Exact next command:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

Do not rerun any previously closed waiver harness. If this test fails, stop at the exact failure, inspect the root cause, and do not speculate. If it passes, document the runtime evidence and continue to audit-preservation verification.

Open evidence gaps remain unchanged:
- same employee with multiple eligible advances;
- true two-process concurrent execution.
Do not claim either as runtime-proven.

## 2026-10-06 — Notification failure-isolation gate PASSED

Runtime command:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

Evidence:
- request: `SAR-2026-00004`
- decision: `13`
- controlled notification delivery attempts: `2`
- injected notification failures: `1`
- final decision state: `executed`
- outstanding balance: `0`
- waiver journal: `125`
- schedule overlays: `10`
- cleanup: decision rows `0`, journals `0`, notifications restored `1`, request restored `1`

This proves the notification-delivery failure was real and isolated from the already-committed financial/business execution. The first execution notification failed through the controlled delivery seam; the later notification attempt continued, while the waiver state, balance, journal, schedule overlays and audit state remained committed.

**Gate CLOSED.**

Next gate: **audit preservation runtime verification**. The audit test must prove historical disbursement, historical payroll repayment and original payroll accounting evidence remain intact while waiver accounting and waiver audit evidence are represented separately.

Open evidence gaps remain:
- same employee with multiple eligible advances;
- true two-process concurrent execution.

## 2026-10-06 — Audit-preservation runtime gate prepared

The notification failure-isolation gate passed and was closed. The next gate is audit preservation.

Direct inspection of the waiver execution source and additive waiver migration confirmed:
- original disbursement journal is never updated/deleted;
- historical payroll repayment rows are re-read/locked for validation and are not rewritten;
- original payroll accounting journal is not rewritten;
- refund accounting and waiver accounting use separate journal entries;
- original repayment schedule rows are preserved and waiver overlays are additive;
- waiver decision/item/audit evidence is additive.

New rollback-only harness:
`tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`

It requires an existing disbursed request with a paid historical payroll repayment, snapshots the actual historical journals, repayment row, schedules and request audit rows, executes the real FM preparation -> GM approval -> FM execution path inside one caller-owned transaction, verifies historical evidence remains unchanged while separate waiver evidence is created, then rolls the fixture back and verifies the complete pre-test evidence set is restored.

**Runtime status: PENDING USER EXECUTION.**

Exact next command:
`php tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`

If the harness fails, stop and inspect the exact failure. Do not speculate and do not rerun closed gates.


# FINAL RUNTIME CHECKPOINT — 2026-10-06

The GM Salary Advance Waiver verification matrix is now **RUNTIME VERIFIED / CLOSED at the documented acceptance boundary**.

## Final gate 1 — Audit preservation

Command:
`php tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`

Result:
`PASS | Audit preservation | request=SAR-2026-00004 | decision_id=15 | disbursement_journal=73 | payroll_id=24 | payroll_journal=129 | repayment_id=6 | historical_audit_rows_preserved=6 | waiver_audit_rows=3 | schedule_overlays=9`

Cleanup:
`PASS | Audit preservation cleanup | request=SAR-2026-00004 | decision_rows=0 | historical_journals_restored=1 | temporary_payroll_rolled_back=1 | repayment_rolled_back=1 | schedules_restored=1 | audit_restored=1`

Interpretation: historical disbursement, payroll repayment/accounting, schedule and audit evidence remained intact; waiver-specific evidence was recorded separately; all rollback-only test data was removed/restored.

## Final gate 2 — 1410 reconciliation

Command:
`php tools\\run_salary_advance_1410_reconciliation.php`

Result:
`PASS | 1410 reconciliation | ledger_balance=190000 | live_outstanding=190000 | debit=220000 | credit=30000 | disbursement_debit=220000 | payroll_credit=0 | waiver_refund_debit=0 | waiver_credit=0 | direct_repayment_credit=30000 | open_requests=5 | journals=11 | lines=11`

The verified control equation is:

`1410 balance = disbursement debits + waiver-refund debits - payroll repayment credits - direct-repayment credits - waiver credits`

Current evidence:
`220000 - 30000 = 190000`, matching the live HR outstanding receivable exactly.

The reconciliation harness was corrected after source inspection found the existing `salary_advance_direct_repayment` journal reference type. No production accounting behavior was altered by this test-harness correction.

## Acceptance status

Closed gates now include:
- remaining-balance waiver execution;
- paid-deduction refund;
- undistributed-request cancellation;
- future-deduction blocking and draft refresh;
- approved-unpaid payroll protection;
- blanket scope/execution;
- sequential duplicate protection;
- post-commit FM preparation notification;
- post-commit GM approval notification;
- post-commit FM execution notifications;
- notification failure isolation;
- audit preservation;
- final 1410 reconciliation.

Explicit evidence gaps remain open:
- same employee with multiple eligible advances;
- true two-process concurrent execution.

These are not failures and must not be silently marked proven.

Do not rerun closed gates without concrete regression evidence. Salary Advance Stages 1–6 remain closed. The attendance same-page POST scroll-jump issue remains unfinished and out of scope.

## Next continuation boundary

The GM Salary Advance Waiver verification unit is closed. The next task must be selected from the current canonical documentation and the user's new requirement; do not automatically reopen waiver testing.

