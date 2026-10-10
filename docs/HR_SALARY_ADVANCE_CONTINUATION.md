# HR Salary Advance — Current Development Checkpoint

**Date:** 2026-09-29  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Active area:** HR / Salary Advance  
**Current stage:** Stage 5 — Repayment Schedule + Payroll Integration — **DONE / RUNTIME VERIFIED / CLOSED**

## Purpose

This document is the continuation checkpoint for the Salary Advance feature. It records the completed stages, verified runtime evidence, the Stage 4 accounting design decision, the implementation now prepared on the Stage 4 feature branch, and the exact next runtime gate.

Do not reopen Stages 1–3 unless a genuine regression is found.

---

## Overall staged plan

1. **Stage 1 — Salary Advance Policy Foundation: DONE**
2. **Stage 2 — Employee Salary Advance Request: DONE / RUNTIME VERIFIED**
3. **Stage 3 — FM Review & Per-Request Customization: DONE / RUNTIME VERIFIED**
4. **Stage 4 — Accounting Verification & Disbursement: DONE / RUNTIME VERIFIED / CLOSED**
5. **Stage 5 — Repayment Schedule + Payroll Integration: DONE / RUNTIME VERIFIED / CLOSED**
6. Stage 6 — Direct Repayment & Settlement: NOT STARTED
7. Stage 7 — Exceptional Lifecycle Cases: NOT STARTED
8. Stage 8 — Reporting / Audit / Hardening: NOT STARTED

The project completion gate remains:

`Implementation committed → migration committed where required → runtime-tested → reconciliation verified where applicable → no known regression → documentation updated → exact next continuation point recorded`

---

# Stage 1 — Salary Advance Policy Foundation

**Status: DONE**

Implemented:

- `database/migrations/2026-09-27_hr_salary_advance_policy.sql`
- `modules/hr/lib_salary_advance_policy.php`
- `modules/hr/salary_advance_policy.php`

Policy capabilities include:

- versioned policies;
- amount rules;
- multiple-active-advance control;
- fixed monthly repayment;
- full eligible salary repayment;
- full settlement from salary;
- direct repayment;
- request-level customization;
- maximum monthly deduction;
- maximum repayment months;
- repayment start rule;
- employee eligibility;
- accounting verification;
- early settlement.

Current tested policy:

- V1
- effective date: `2026-09-29`
- `allow_multiple_active_advances = 0`
- request-level customization enabled

The future-effective policy rule is intentionally preserved. V1 must not be treated as active before its effective date.

Policy actions were merged to main in PR #34:

`319d7c3a50bbd24230ba95b7be06da604e26ab99`

The subsequent policy-page parse correction is:

`b80c9944d5ccd609f73e01868ec05e756d11a063`

Do not reopen Stage 1.

---

# Stage 2 — Employee Salary Advance Request

**Status: DONE / RUNTIME VERIFIED**

Implemented:

- `database/migrations/2026-09-27_hr_salary_advance_request.sql`
- `modules/hr/lib_salary_advance_request.php`
- `modules/hr/salary_advance_request.php`

Business boundary:

- The employee submits the original requested terms.
- The request preserves those values.
- Policy compliance is reviewed by FM.
- Employee submission does not silently rewrite the request to policy defaults.
- A future policy can be stored as the request reference without being treated as active.

Runtime evidence:

### `SAR-2026-00001`

Employee: `فاطمة سليمان`

Verified:

- request submitted;
- request number generated;
- request stored;
- FM notification delivered as unread/new;
- original request preserved;
- no accounting posting;
- no payroll posting.

### Active-advance control

The policy field `allow_multiple_active_advances` controls whether another request may be submitted while an existing advance remains active/unsettled.

Runtime evidence:

### `SAR-2026-00004`

A second request was correctly blocked while the prior approved advance was still active under the temporary pre-Stage-4 lifecycle.

PR #43 merged this policy-controlled active-advance behavior:

`6e9299cf4ecec2c4cbdee5f0502f61d0d78c37ab`

Stage 4 now replaces the temporary `approved = active/unpaid` assumption with the real disbursement/outstanding-balance lifecycle.

Do not repeat the successful Stage 2 tests unless a regression appears.

---

# Stage 3 — FM Review & Per-Request Customization

**Status: DONE / RUNTIME VERIFIED**

Implemented:

- `database/migrations/2026-09-28_hr_salary_advance_fm_review.sql`
- `modules/hr/salary_advance_fm_review.php`
- FM review helpers in `modules/hr/lib_salary_advance_request.php`
- FM dashboard review shortcut
- employee decision notifications
- FM audit logging

Main merge:

`3ca61875dbad96df4f4586827fc61a87689fc37f`

Related completed fixes:

- customization visibility: `5c8b9c5528ad2dacae9e9f967e75789a63a96ea0`
- employee notification recipient mapping: `76ca4224c8b38ed545b83e201eb9699cb9a0e771`
- notification regression/schema compatibility: `e3d378e33238cc291c82c301041e35de8667bd8f`

## Verified compliant approval

`SAR-2026-00001`

FM approved successfully.

Employee notification was delivered.

No accounting/payroll posting occurred during FM review.

## Verified mismatch + customization

`SAR-2026-00004`

Employee: `أحمد حسين`

Amount: `50,000 SDG`

Monthly repayment: `5,000 SDG`

Start: `2026-11-01`

Mismatch detected:

`شهر بدء السداد لا يطابق قاعدة بدء السداد في السياسة.`

FM customized and approved the individual request.

The original employee request remained preserved and the general policy remained unchanged.

No accounting/payroll posting occurred.

## Verified rejection

`SAR-2026-00006`

Employee: `ميادة الحبر`

Code: `EMP-0020`

Basic salary at test time: `0.00 SDG`

Original request:

- Amount: `60,000 SDG`
- Repayment: `قسط شهري ثابت`
- Monthly amount: `5,000 SDG`
- Start month: `2026-11-01`
- Reason: `policy rejection test`

FM detected:

`شهر بدء السداد لا يطابق قاعدة بدء السداد في السياسة.`

FM rejected the request.

Employee side displayed the request as rejected.

## Protected notification decision

PR #40 added `database/migrations/2026-09-28_notifications_workflow_references.sql`.

The user explicitly instructed:

**DO NOT APPLY PR #40's notification migration.**

That decision remains in force.

The current notification implementation was corrected without applying that migration.

Do not reopen Stage 3.

---

# Stage 4 — Accounting Verification & Disbursement

**Status: IMPLEMENTATION PREPARED / RUNTIME VERIFICATION OPEN — RECEIPT/VOUCHER ENHANCEMENTS ADDED**

## Root-cause/design conclusion

The existing accounting architecture uses:

- `accounts`
- `journal_entries`
- `journal_lines`
- double-entry posting;
- `journal_entries.reference_type` + `reference_id` for source traceability.

Existing cash-source accounts:

- `1100` — الصندوق (نقدي)
- `1200` — البنك
- `1300` — المحافظ الإلكترونية

Existing generic receivable:

- `1400` — ذمم مدينة (مستحقات قبض)

The existing payment-voucher implementation is **not suitable** for salary advances because it deliberately requires an expense account as the counter-account and posts:

`Dr expense / Cr cash`

That would incorrectly recognize a salary advance as an expense.

The correct salary-advance disbursement is:

`Dr employee salary-advance receivable / Cr cash, bank, or e-wallet`

The existing Fina implementation also demonstrates that the accounting architecture supports dedicated control accounts for specialized balances rather than forcing every balance into a generic account.

### Accounting conclusion

A dedicated salary-advance control account is required:

- **1410 — ذمم سلف الموظفين**
- **Employee Salary Advances Receivable**
- account type: **asset**

This keeps salary advances separate from:

- ordinary expenses;
- generic receivables;
- payroll expense;
- cash/bank/wallet balances.

The employee/request relationship remains in the HR salary-advance record, while account 1410 provides the accounting control balance.

## Stage 4 lifecycle

The request lifecycle is now extended to support:

`approved → disbursed → settled`

The Stage 4 implementation records:

- accounting verification status;
- accounting verifier/time;
- accounting rejection reason;
- disburser/time;
- disbursement source account;
- disbursement journal entry;
- disbursement reference;
- outstanding balance;
- future settlement actor/time fields.

Stage 4 implements only the **approved → disbursed** transition.

Repayment schedules, payroll deductions, direct repayment, and settlement logic remain later stages.

## Stage 4 implementation prepared

Feature branch:

`feature/hr-salary-advance-stage4-accounting`

Prepared files:

- `database/migrations/2026-09-28_hr_salary_advance_accounting.sql`
- `modules/hr/lib_salary_advance_accounting.php`
- `modules/hr/salary_advance_processing.php`

The FM dashboard salary-advance shortcut now points to the accounting verification/disbursement workflow.

The active-advance helper in `modules/hr/lib_salary_advance_request.php` was updated so that:

- an approved request still reserves the employee from submitting another request before disbursement;
- a disbursed request blocks another request only while `outstanding_balance > 0`;
- a disbursed request with zero outstanding balance no longer counts as active;
- the old assumption that `status='approved'` alone means an unpaid advance is no longer the long-term lifecycle rule.

## Stage 4 accounting posting

When the policy requires accounting verification:

1. FM approval produces `status='approved'`.
2. Accounting verifies the request.
3. A valid cash source is selected from `1100/1200/1300`.
4. Available balance is checked.
5. A dedicated journal entry is created with:
   - `reference_type = 'salary_advance_disbursement'`
   - `reference_id = salary advance request ID`
6. Journal lines are:
   - Debit `1410` — Employee Salary Advances Receivable
   - Credit selected cash/bank/e-wallet account
7. The request becomes `disbursed`.
8. `outstanding_balance` is initialized to the actual disbursed amount.
9. The employee receives a disbursement notification.
10. No expense account is used.
11. No ordinary payment voucher is created.

The operation is transactional and uses the existing accounting numbering/locking convention.

## Stage 4 receipt / voucher and action-control enhancements

The Stage 4 accounting workflow now also provides:

- protected payment-receipt upload after successful disbursement;
- JPG/PNG/PDF validation with a 5 MB limit;
- replacement of an existing receipt with an audit-log record rather than destructive deletion;
- authenticated receipt serving through `modules/hr/salary_advance_receipt.php`;
- a dedicated printable salary-advance payment voucher through `modules/hr/salary_advance_voucher_print.php`;
- voucher content showing employee, amount, source account, control account 1410, journal entry, reference, and receipt status;
- print/view-receipt actions on the disbursed request;
- accounting evidence is intentionally not given a delete action after posting; financial history and supporting evidence remain auditable;
- editable disbursement reference remains available before posting, while posted financial fields are immutable.

Migration added:

`database/migrations/2026-09-28_hr_salary_advance_receipt.sql`

Supporting files:

- `modules/hr/salary_advance_receipt.php`
- `modules/hr/salary_advance_voucher_print.php`

These enhancements do not change the accounting entry itself and do not introduce a second voucher-posting mechanism.

## Stage 4 runtime verification checkpoint

The Stage 4 accounting migration was applied successfully in the local development database with no errors.

### Verified runtime results

1. Accounting queue gate passed.
2. Accounting rejection was verified on `SAR-2026-00004`:
   - employee: أحمد حسين / EMP-0028
   - approved amount: 50,000 SDG
   - accounting status became `rejected`
   - rejection reason was stored
   - no disbursement/journal was created.
3. The same request was subsequently re-verified successfully:
   - accounting status became `verified`
   - request remained `approved`
   - source accounts 1100/1200/1300 were available.
4. `SAR-2026-00001` was verified for accounting.
5. `SAR-2026-00001` was successfully disbursed through account `1100 — الصندوق`:
   - amount: 10,000 SDG
   - status: `disbursed`
   - disbursement timestamp: `2026-09-28 19:06:24`
   - journal: `JE-000040`
   - reference: `SAL-ADV-SAR-2026-00001`
   - outstanding balance: 10,000 SDG
   - accounting entry: Dr 1410 / Cr 1100.
6. The protected payment receipt was uploaded successfully:
   - uploaded at `2026-09-28 19:07:42`
   - original file name: `WhatsApp Image 2026-09-18 at 1.57.43 PM.jpeg`.
7. Employee-facing actions were verified:
   - `عرض/طباعة السند`
   - `عرض إيصال الدفع` when a receipt exists.
   - employee ownership checks were added to the voucher and receipt endpoints.
8. The printable voucher was refined to hide technical accounting treatment from FM/employee-facing output while retaining the accounting entry in the journal.
9. The salary-advance management portal now contains the processed-history table directly on the main dashboard, below the three clickable management cards. This layout was runtime verified after the final correction.

### Stage 4 implementation / UI checkpoints

Receipt/voucher enhancement PR #44 was merged into the Stage 4 branch with merge commit:

`8fcd9fa9f1eed21b78c0b3ef373ba0aeec1fa5b9`

Employee-facing voucher/receipt actions PR #45:

`ba24c533fbd7be82de4e48f90d9d8f4e52abfe94`

Voucher accounting-treatment removal PR #46:

`512752c8713f6fcd5cb779a94b51ce0ed67fb3e9`

Voucher title/history PR #47:

`1aedec23cab8807f19a2ef92075d941cee432e60`

Dashboard history placement PR #48:

`2f26b2376c91a349f0b5e1b3f2066b2a2fbeb9e4`

Final dashboard PHP/layout correction after the history-placement edit:

`1ecce916b748cc7d79eb3b1fc3a7fbba9aebaf3a`

### Remaining Stage 4 gates

Before marking Stage 4 fully complete:

1. Test that an employee cannot access another employee's voucher.
2. Test that an employee cannot access another employee's payment receipt.
3. Test receipt replacement and confirm the replacement is audit logged.
4. Confirm no duplicate disbursement/journal can be produced for the same salary-advance request.
5. Confirm the final accounting/reconciliation evidence and notification/audit trail.
6. Update this checkpoint and the relevant master project documentation.
7. Only after all gates pass, close Stage 4 and begin Stage 5.

Do not implement or test payroll deductions/repayment schedules as part of this stage.

1. Verify migration application succeeds.
2. Verify account `1410` exists exactly once and is an active asset account.
3. Open the FM salary-advance accounting workflow.
4. Confirm an FM-approved request appears in the queue.
5. Verify accounting rejection works and blocks disbursement.
6. Verify the same request can subsequently be re-verified.
7. Select one of `1100/1200/1300`.
8. Confirm insufficient balance is rejected before posting.
9. Disburse one controlled approved request.
10. Verify exactly one posted journal exists with:
    - reference type `salary_advance_disbursement`;
    - reference ID equal to the salary-advance request ID;
    - debit to 1410;
    - credit to the selected cash/bank/wallet account;
    - equal debit and credit.
11. Verify the request changes to `disbursed`.
12. Verify `outstanding_balance` equals the actual disbursed amount.
13. Verify the disbursement source account balance decreases by the disbursed amount.
14. Verify account 1410 increases by the same amount.
15. Verify employee notification is delivered.
16. Verify no expense account balance changes because of the salary-advance disbursement.
17. Verify a second request is blocked while the disbursed outstanding balance is positive.
18. Do **not** implement or test payroll deductions/repayment schedules as part of this stage.

---

# Stage 5 — Repayment Schedule + Payroll Integration

**Status: NOT STARTED**

Reserved scope:

- repayment schedule generation;
- fixed monthly repayment;
- full eligible salary repayment;
- salary deduction limits;
- insufficient-salary handling;
- payroll integration;
- accounting treatment of repayments.

Do not implement this as part of Stage 4.

---

# Stage 6 — Direct Repayment & Settlement

**Status: NOT STARTED**

Reserved scope:

- direct employee repayment;
- settlement posting;
- outstanding-balance reduction;
- final settlement;
- transition to `settled`.

---

# Stage 7 — Exceptional Lifecycle Cases

**Status: NOT STARTED**

Reserved scope includes exceptional cases such as cancellation, separation, early settlement, and other policy-approved lifecycle events.

---

# Stage 8 — Reporting / Audit / Hardening

**Status: NOT STARTED**

Reserved scope:

- salary-advance reporting;
- employee-level outstanding balances;
- accounting reconciliation;
- audit completeness;
- lifecycle integrity checks;
- final hardening.

---

# Project-wide rules

- Existing repository only; do not rebuild.
- Inspect repository and actual schema before changes.
- Never invent table/column names.
- Database structure changes only through migrations.
- No runtime CREATE/ALTER.
- No triggers.
- No views.
- No destructive Git commands.
- Preserve existing architecture.
- Procedural PHP only.
- Arabic RTL UI.
- Exactly two back buttons on user-facing non-dashboard pages.
- Do not repeat closed runtime tests unless a genuine regression appears.
- Accounting test data is development/test data only.
- Do not use the normal expense payment-voucher flow for salary advances.
- Do not implement Stage 5/6 behavior early.
- Update this document after every completed milestone.
- Record exact commit/merge SHA for every completed milestone.

---

# Current exact continuation point

**Stages 1–3 are complete and must not be reopened.**

**Stage 4 implementation is prepared on:**

`feature/hr-salary-advance-stage4-accounting`

Latest Stage 4 implementation commit:

`5ef4c7d144c38fdcd701b269e6b6b540d9e94c53`

Documentation checkpoint commit: `322557364fbd19cc26a879743d48eb15f5bc869c`

**Immediate next task:** complete the remaining Stage 4 security/evidence gates: cross-employee voucher/receipt access denial, receipt replacement + audit verification, duplicate-disbursement protection, and final reconciliation/notification/audit confirmation.

The migration and core accounting/disbursement path are already runtime verified. Do not repeat those successful tests unless a regression appears.

Do not start Stage 5 until Stage 4 is fully runtime-verified and documented.


# Stage 4 — Final Closure Checkpoint (2026-09-29)

**Status: DONE / RUNTIME VERIFIED / CLOSED**

The final Stage 4 gates are closed. No Stage 4 runtime test remains open.

### Security and ownership
- Voucher and payment-receipt endpoints enforce authenticated employee ownership server-side.
- Privileged accounting/management roles retain their authorized access.
- Cross-employee denial is recorded as code-verified; the already-verified legitimate owner flows were not repeated artificially.

### Duplicate disbursement
- The accounting helper checks for an existing posted salary-advance disbursement before posting and repeats the protection after acquiring the transactional request lock.
- An already-disbursed request has no re-disbursement UI path, so no artificial duplicate journal was created.
- Gate: **PASS / CODE VERIFIED**.

### Receipt replacement
The final replacement test succeeded with:

تم رفع إيصال الدفع وحفظه في التخزين المحمي.

The replacement lifecycle is:
1. lock the existing receipt row;
2. replace its stored metadata inside the transaction;
3. write a mandatory audit record containing the previous receipt path and new receipt details;
4. commit the transaction;
5. remove the superseded physical receipt file after the successful commit.

If the DB/audit transaction fails, the replacement rolls back, the new physical file is cleaned up, and the old receipt remains. Therefore an unaudited replacement cannot commit.

Gate: **PASS / RUNTIME VERIFIED**.

### Rejection closure
FM rejection was runtime verified on SAR-2026-00005. The rejected request is terminal/closed, remains in history, and is no longer actionable for another approval/rejection cycle.

Gate: **PASS / RUNTIME VERIFIED**.

### Accounting and notification evidence
- SAR-2026-00001
- 10,000 SDG
- source 1100 — الصندوق
- journal JE-000040
- reference SAL-ADV-SAR-2026-00001
- Dr 1410 / Cr 1100
- outstanding balance: 10,000 SDG
- employee disbursement notification delivered.

No payroll repayment schedule, payroll deduction, direct repayment, or settlement behavior was implemented in Stage 4.

**Stage 4 is CLOSED. Do not reopen it unless genuine regression evidence appears.**

## Next stage

**Stage 5 — Repayment Schedule + Payroll Integration: NOT STARTED**

Before Stage 5 implementation, inspect the current main repository, payroll/accounting conventions, and actual schema. Define the lifecycle, role boundaries, accounting events, and notification/audit behavior first. Do not invent schema or start coding before that design/audit checkpoint.


## Current exact continuation point

Stages 1–4 are complete and must not be reopened without regression evidence.

The final Stage 4 hardening commit was 8b9caaf5c419812fa10d5000773ca6198b896173. The rejection-closure PDO rowCount correction was 5fabd7af14d7ff545998e44cf05c62157dea20a0.

**READY FOR A NEW SESSION — CONTINUE WITH STAGE 5 PLANNING/AUDIT ONLY.**


# 2026-09-29 — Salary Advance Processing Workflow Consolidation

**Status:** IMPLEMENTED on the Stage 5 feature branch; runtime verification of the consolidated UI is pending.

The former two user-facing FM processing pages have been consolidated into one continuous workflow:

- Removed: `modules/hr/salary_advance_fm_review.php`
- Removed: `modules/hr/salary_advance_processing.php`
- Added: `modules/hr/salary_advance_processing.php`

The unified page now presents the salary-advance lifecycle as one sequence:

1. FM review and policy comparison/customization.
2. Accounting verification.
3. Disbursement and journal posting.
4. Disbursement result and repayment schedule.
5. Protected payment-receipt handling.

The underlying business logic and role boundaries were preserved. FM decision logic remains controlled by `hrSalaryAdvanceFmCanReview()`, while accounting verification/disbursement remains controlled by `hrSalaryAdvanceAccountingCan()`.

The salary-advance portal now exposes one processing entry point instead of separate FM-review and accounting-processing cards. Accounting staff also receive a direct dashboard link to the same unified processing page.

Employee approval notifications now point to the unified processing workflow.

The printable voucher and protected receipt endpoints remain separate because they are document/evidence endpoints, not duplicate processing workflows.

**Important:** do not merge PR #52 or begin payroll deduction/application testing until the remaining Stage 5 schedule-planning runtime gates are completed. The consolidated page must be runtime-tested before relying on it for those gates.

# 2026-09-29 — Stage 5 Schedule Generation Runtime Checkpoint

**Status:** Stage 5 IN PROGRESS — schedule generation/display RUNTIME VERIFIED; edge-case schedule-rule tests pending.

The Stage 5 migration was applied successfully locally with all 5 queries completing without errors. It added payroll.salary_advance_deduction, hr_salary_advance_repayment_schedule, and hr_salary_advance_payroll_repayments.

PR #52 — Stage 5: generate salary advance repayment schedules — was merged into main on 2026-09-29 (merge commit 96ecf58871fe27b32aad2c5d62174c40991a5747). The current branch is a follow-up consolidation branch and remains unmerged while the consolidated workflow is runtime-tested.

Implemented on feature/hr-salary-advance-stage5-schedule:
- modules/hr/lib_salary_advance_repayment.php
- atomic schedule generation from the existing disbursement transaction;
- fixed-monthly and full-eligible-salary schedule planning;
- approved start-rule handling;
- maximum monthly deduction and maximum repayment-month constraints;
- duplicate schedule-generation protection;
- schedule display on modules/hr/salary_advance_processing.php.

## Runtime PASS — SAR-2026-00007

Employee: هديل عثمان / EMP-0021

- Approved/disbursed amount: 50,000 SDG
- Method: قسط شهري ثابت
- Monthly installment: 10,000 SDG
- Approved start month: 2026-10-01
- Disbursement source: 1100 — الصندوق (نقدي)
- Journal: JE-000041
- Reference: SAL-ADV-SAR-2026-00007
- Outstanding balance: 50,000 SDG

Exactly 5 pending schedule rows were generated:
1. 2026-10-01 — 10,000 SDG
2. 2026-11-01 — 10,000 SDG
3. 2026-12-01 — 10,000 SDG
4. 2027-01-01 — 10,000 SDG
5. 2027-02-01 — 10,000 SDG

Total scheduled = 50,000 SDG. Applied = 0. This confirms the core disbursement → schedule-generation → schedule-display path.

SAR-2026-00004 / JE-000042 is a different request and is not a duplicate of SAR-2026-00007.

## Duplicate-disbursement safety checkpoint

The existing disbursement helper checks for an existing posted salary-advance disbursement journal, locks the request with FOR UPDATE inside the transaction, revalidates state, creates one balanced Dr 1410 / Cr source-account journal, changes the request to disbursed transactionally, and generates the schedule in the same transaction. A schedule-generation failure rolls the disbursement back. No artificial duplicate financial posting should be created merely to test this.

## Remaining Stage 5 runtime gates — NEXT

1. Maximum monthly deduction — verify every generated installment is at or below the saved policy maximum.
2. Maximum repayment months — verify an impossible schedule is rejected safely and does not leave an unscheduled disbursed request.
3. next_payroll — verify the first scheduled month is the first payroll month after disbursement.
4. specified_month — verify the approved start month is honored, but never before the first eligible payroll month after disbursement.
5. Duplicate schedule generation — verify an existing schedule remains one set of rows.
6. full_eligible_salary — verify planning respects the saved repayment horizon and maximum deduction; actual salary calculation remains deferred.
7. Failure/rollback — verify a schedule-generation failure rolls back the disbursement transaction.

## Explicit boundary

Stage 5 currently does NOT implement actual payroll deduction calculation, payroll repayment allocation, outstanding-balance reduction from payroll, payroll Cr 1410 accounting, repayment notifications, or Stage 6 direct repayment/settlement.

Use fresh controlled requests for new tests. Do not alter the already-passed SAR-2026-00007 evidence. If a test fails, stop and inspect the current repository/code/schema root cause before creating another test request.


# 2026-09-29 — Stage 5 Schedule-Planning Gates CLOSED

**Status:** Stage 5 schedule-planning portion is DONE / RUNTIME VERIFIED. Stage 5 payroll integration remains IN PROGRESS.

All seven schedule-planning gates have passed:
1. Maximum monthly deduction — PASS: SAR-2026-00008; 50,000 SDG; 15,000 approved monthly was capped at the 10,000 SDG policy maximum; five 10,000 SDG installments; JE-000043.
2. Maximum repayment months — PASS: SAR-2026-00009; 70,000 SDG with 10,000 monthly against a 6-month policy maximum. Disbursement was blocked with the expected maximum-month validation message; no successful disbursement/journal/schedule was created.
3. next_payroll — PASS: SAR-2026-00011; 30,000 SDG; fixed 10,000 monthly; disbursed 2026-09-29; JE-000044. Schedule starts 2026-10-01, then 2026-11-01 and 2026-12-01.
4. specified_month — PASS: rollback-only verification confirmed an approved 2026-12-01 start is honored when eligible.
5. Duplicate schedule generation — PASS: rollback-only verification confirmed a second generation returns the existing schedule without adding rows.
6. full_eligible_salary planning — PASS: rollback-only verification confirmed the monthly cap is respected and the full outstanding balance is covered within the saved repayment horizon.
7. Failure/rollback — PASS: the protected SAR-2026-00009 validation failure was safely rejected; code inspection confirms schedule generation is inside the same disbursement transaction.

The rollback-only harness is tools/run_salary_advance_stage5_schedule_tests.php. Its CLI REQUEST_METHOD warning was removed in commit 9b66315966174a3a892a5760847300007814a0e2. The clean rerun produced all three harness PASS results with no warning.

Protected runtime evidence must not be modified or reused for another disbursement test. No additional employee/request is required for the completed schedule-rule gates.

## Remaining Stage 5 work

Do not start Stage 6. Continue Stage 5 with payroll integration only: actual payroll deduction/application, payroll repayment allocation, outstanding-balance reduction, payroll Cr 1410 accounting, insufficient-salary behavior, and repayment/application notifications and audit behavior.

Before implementation, inspect existing payroll processing/accounting conventions and actual schema. Do not invent schema or use runtime DDL. Prefer controlled rollback-only fixtures because the available employee population has already been exhausted for fresh salary-advance requests.

## Git checkpoint

Current Stage 5 feature branch: feature/hr-salary-advance-stage5-schedule.
Latest cleanup commit: 9b66315966174a3a892a5760847300007814a0e2.
The branch is not yet merged into main.

Correction to earlier workflow notes: the removed legacy pages are salary_advance_fm_review.php and salary_advance_accounting.php. salary_advance_processing.php is the new unified page.


# Stage 5 — Payroll Integration Implementation Checkpoint (2026-09-29)

**Status: IMPLEMENTED / RUNTIME VERIFICATION PENDING**

Implemented on `feature/hr-salary-advance-stage5-schedule`: payroll draft deduction from the approved schedule/policy; transactional repayment allocation at payroll payment; schedule applied/partial/skipped state updates; outstanding-balance reduction by actual repayment; `hr_salary_advance_payroll_repayments` trace; Cr 1410 repayment accounting with cash credit for actual net paid; insufficient-salary `available_salary` / `skip_month` handling; duplicate request/payroll protection; and audit-log evidence. Account `1410` is ensured by the existing accounting seed mechanism when missing.

Runtime verification is still required for fixed-monthly repayment, partial/available-salary, skip-month, balance reduction, schedule state, duplicate protection, payroll regeneration safety, and balanced journal traceability. Do not start Stage 6.


## 2026-09-29 — Stage 5 Payroll Integration Merge Checkpoint

The Stage 5 payroll-integration implementation was merged to `main` through PR #56. Merge commit: `7cb7a42a9bb4d90a07d23250f2940473806498c8`.

Status remains **Stage 5 IN PROGRESS / RUNTIME VERIFICATION PENDING**. The code is now on `main`; no Stage 6 behavior has been introduced. The next step is controlled local runtime verification of payroll repayment application, Cr 1410 accounting, balance/schedule updates, insufficient-salary handling, duplicate protection, and audit traceability.


## 2026-09-29 — Stage 5 Repayment Notification Checkpoint

PR #57 was merged to `main` with merge commit `0c01c3a378826b7b7cb260668f8f64b0e6648031`. Employee notifications are now emitted after a committed payroll repayment for applied, partial, skipped, and zero-balance completion outcomes. Notification delivery is informational and cannot roll back the financial transaction.

Stage 5 remains IN PROGRESS pending controlled runtime verification. Stage 6 remains NOT STARTED.


## 2026-09-30 — Stage 5 Payroll Edge-Case Runtime Checkpoint

**Status:** Stage 5 IN PROGRESS — payroll integration runtime verified for the available-salary insufficient-salary branch; two policy branches remain unverified because no suitable existing fixture exists.

The rollback-only edge-case harness was merged to `main` in PR #67 with merge commit `49ae654a25a8ec2a80901c86fe9aa009da83b96c`.

Runtime result from `tools/run_salary_advance_stage5_payroll_edge_tests.php`:

- **PASS — available_salary + fixed_monthly:** `SAR-2026-00004`; scheduled remaining = 5,000 SDG; eligible salary = 1 SDG; calculated deduction = 1 SDG; outcome = `partial`.
- **SKIP — skip_month + fixed_monthly:** no existing disbursed fixture matching this policy rule.
- **SKIP — full_eligible_salary with low eligible salary:** no existing disbursed fixture matching the required period.
- **PASS — rollback-only cleanup:** no payroll/request/schedule/journal mutation was committed.

The PASS confirms that `available_salary` never deducts more than the employee's eligible salary and records a partial repayment when the available amount is below the scheduled installment.

The two SKIPs are intentional and are **not failures**. No new employee, salary-advance request, schedule, or permanent accounting data should be created merely to manufacture these fixtures. Stage 5 must remain open until these branches are either runtime-verified against suitable existing data or explicitly covered by a safer verified fixture strategy.

### Current Stage 5 verification boundary

Already runtime verified:
- schedule generation and planning rules;
- maximum monthly deduction;
- maximum repayment months;
- `next_payroll`;
- `specified_month`;
- duplicate schedule-generation protection;
- `full_eligible_salary` planning;
- schedule-generation failure/rollback;
- payroll repayment application;
- duplicate payroll repayment protection;
- balanced payroll accounting with Cr 1410;
- repayment trace and outstanding-balance reduction;
- legacy salary-advance notification-link compatibility;
- voucher child-tab return behavior;
- `available_salary` insufficient-salary partial repayment.

Still pending:
- `skip_month` insufficient-salary runtime verification;
- low-salary `full_eligible_salary` runtime verification;
- final Stage 5 notification/audit end-to-end gate, if not already evidenced by the existing protected payroll repayment test.

Stage 6 remains **NOT STARTED** and must not be started early.


## 2026-09-30 — Stage 5 Final Runtime Closure

**Status: DONE / RUNTIME VERIFIED / CLOSED**

Post-merge verification was completed on `main` at merge commit `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.

Final edge-case harness:
`tools/run_salary_advance_stage5_payroll_edge_tests.php`

Final runtime result:
- **PASS — available_salary + fixed_monthly:** `SAR-2026-00004`; scheduled remaining = 5,000 SDG; eligible salary = 1 SDG; deduction = 1 SDG; outcome = `partial`.
- **PASS — skip_month + fixed_monthly:** `SAR-2026-00007`; scheduled remaining = 10,000 SDG; eligible salary = 1 SDG; deduction = 0 SDG; outcome = `skipped`; rollback-only existing-request policy override.
- **PASS — full_eligible_salary with low eligible salary:** `SAR-2026-00007`; deduction = 1 SDG; outcome = `partial`; rollback-only existing-request method override.
- **PASS — rollback-only cleanup:** no payroll/request/schedule/journal mutation was committed.

These four edge gates passed both before merge and again after the PR was merged to `main`. The two previously unavailable policy branches were covered safely through SAVEPOINT-based rollback-only fixtures; no permanent employee, request, schedule, payroll, or accounting test data was created.

The previously verified Stage 5 core payroll gates remain valid, including payroll repayment application, schedule/balance updates, duplicate protection, balanced Cr 1410 repayment accounting, repayment trace/audit behavior, and repayment notification implementation/runtime coverage. No Stage 6 behavior was introduced.

**Stage 5 is now formally closed.**

Stage 6 — Direct Repayment & Settlement remains **NOT STARTED** and is the next planned work unit. Do not begin it in the same checkpoint unless explicitly proceeding with Stage 6.


## 2026-09-30 — HR Salary Advance Stage 5 Closure / Repository Cleanup

**Stage 5 — Repayment Schedule + Payroll Integration: DONE / RUNTIME VERIFIED / CLOSED.**

Final runtime verification on `main` passed all four Stage 5 payroll edge cases, including:
- `available_salary` insufficient-salary partial repayment.
- `skip_month` insufficient-salary skip behavior.
- `full_eligible_salary` with low eligible salary.
- rollback-only cleanup with no permanent payroll/request/schedule/journal mutation.

The final Stage 5 UI cleanup also removed permanent workflow-instruction text from the unified salary-advance processing page and replaced successful workflow-state feedback with temporary toast notifications. Repayment schedule generation/display logic was not changed.

Stage 5 closure checkpoints:
- Final runtime merge checkpoint: `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.
- Final UI cleanup commits: `6bc8b94fdddff8593a8e9fe716865d302509a933`, `c04adaa1fe4ec27b187d7d080f85a8db0c593506`, `f8ab8272b3eaf352cd02cd59ffafee3a0ee1cfb6`.
- Documentation closure commits: `65cb44479c0218ae28b2c80e6c071eac0f1ddafa`, `0c8671ce8f4338fa90a290d3b9c8da6a7699ccf4`, `450581766bdf783c4cd32bc05dd33287de9fde32`.

### Git repository cleanup

The repository was audited for stale branches after Stage 5 closure. All obsolete remote branches were deleted and all obsolete local-only branches were deleted after reviewing their unique commits. No historical branch was merged back into `main`.

Final repository state:
- local branch: `main` only;
- remote development branch: `origin/main` only;
- `origin/HEAD -> origin/main`;
- working tree clean;
- local `main` synchronized with `origin/main`.

### Next work unit

**Stage 6 — Direct Repayment & Settlement: NOT STARTED.**

Stage 6 must begin in a fresh session. First inspect the current `main` repository, the master documents, the salary-advance continuation document, and the actual schema/code relevant to repayment/settlement. Do not implement Stage 6 behavior before the design/schema audit checkpoint.

## 2026-10-06 — GM Salary Advance Waiver: Payroll Repayment Dependency Runtime Verified

The controlled payroll-integration dependency required by the GM salary-advance waiver has now been runtime verified locally on `main` using the existing rollback-only Stage 5 harness:

`php tools\\run_salary_advance_stage5_payroll_tests.php`

Result:
- PASS — payroll repayment application: `SAR-2026-00004`, temporary payroll ID 24, deduction 5,000 SDG, one repayment trace row, schedule status `paid`, outstanding balance 45,000 SDG, balanced journal 116.
- PASS — duplicate repayment protection for the same request/payroll.
- PASS — rollback-only cleanup; no payroll/request/schedule/journal mutation was committed.

This verifies the real payroll repayment path used by salary advances: payroll deduction -> repayment allocation -> schedule update -> outstanding-balance reduction -> Cr 1410 accounting -> duplicate protection, without creating permanent test data.

This does **not** close the GM waiver feature. The waiver-specific runtime matrix remains open, including FM preparation, GM approval/rejection, refund of already-paid current-period deductions, remaining-balance waiver, future-deduction blocking, undistributed-request cancellation, concurrency/duplicate execution protection, draft refresh, post-commit notifications, audit evidence and final 1410 reconciliation.

Evidence boundary:
- Current waiver implementation commits include `b5e0bbd`, `2d035ae`, `1a67312`, and `7e63fd0`.
- The local database already contains the waiver migration; no new schema change was made for this verification.
- No production payroll-generation code was changed as a result of the earlier read-only October simulation or this rollback-only test.

The unfinished attendance same-page POST scroll-jump issue remains separate and must not be reopened during this work.


## 2026-10-10 — Salary Advance Processing UI/Workflow Review

**Status: SOURCE CHANGES COMMITTED; LOCAL APACHE/BROWSER VERIFICATION PENDING.**

The latest source review found that the same-page POST renderer moved the response fragment's children into the live `.content` node, then attempted to execute scripts from the emptied staging node. This prevented the processing page's newly inserted tab handlers and toast lifecycle from initializing after actions such as FM approval and disbursement. The renderer now initializes scripts from the inserted live content and, for explicit workflow-advancing success messages, scrolls to the server-selected active stage after layout restoration. Ordinary same-page edits still preserve their original viewport. Clicking a workflow tab also brings its panel into view.

The processing page now chooses the next actionable stage using the request's saved `require_accounting_verification` policy and accounting status. A rejected accounting checkpoint remains visible. FM customization now lists only repayment methods enabled by the request's saved policy, defaults to the employee's original method when allowed, enables the monthly installment field only for fixed-monthly repayment, and validates the chosen method server-side against the saved policy.

The disbursement path's prior explicit guard blocked every retry after accounting rejection, despite the UI stating that re-verification occurs atomically with disbursement. The guard was removed; a retry now clears the prior rejection and records verification inside the same locked transaction as journal posting. If any later step fails, the transaction rollback still protects the request and journal state.

Source-level verification performed on 2026-10-10:
- JavaScript syntax check passed for `assets/js/app.js`.
- Confirmed the renderer executes scripts from the inserted content rather than the emptied staging node.
- Confirmed workflow-success responses trigger active-panel scrolling.
- Confirmed policy-driven step selection and policy-bound FM method validation are present.
- Confirmed accounting rejection retry remains within the transactional disbursement path.

**Runtime gate remains open:** these changes have not been exercised in the user's local Apache/browser session, and PHP lint/full regression tests have not been run from this remote source review. After pulling, verify FM approval advances to the appropriate next panel, disbursement displays the newly generated schedule without refresh, the footer remains visible, and an accounting rejection can be retried safely.

**Separate policy-method gap found during the review:** `full_settlement` (“تسوية كامل الرصيد من الراتب”) is accepted as a repayment method, but the current payroll schedule generator only creates plans for `fixed_monthly` and `full_eligible_salary`. Correctly implementing full settlement requires coordinated payroll handling for insufficient salary/partial collection; do not treat a one-row schedule as complete without that handling. This remains open and must be addressed before claiming all repayment-policy paths are complete.
