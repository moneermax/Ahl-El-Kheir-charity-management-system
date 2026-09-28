# HR Salary Advance — Current Development Checkpoint

**Date:** 2026-09-28  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `feature/hr-salary-advance-stage4-accounting`  
**Active area:** HR / Salary Advance  
**Current stage:** Stage 4 — Accounting Verification & Disbursement (implementation prepared; runtime verification pending)

## Purpose

This document is the continuation checkpoint for the Salary Advance feature. It records the completed stages, verified runtime evidence, the Stage 4 accounting design decision, the implementation now prepared on the Stage 4 feature branch, and the exact next runtime gate.

Do not reopen Stages 1–3 unless a genuine regression is found.

---

## Overall staged plan

1. **Stage 1 — Salary Advance Policy Foundation: DONE**
2. **Stage 2 — Employee Salary Advance Request: DONE / RUNTIME VERIFIED**
3. **Stage 3 — FM Review & Per-Request Customization: DONE / RUNTIME VERIFIED**
4. **Stage 4 — Accounting Verification & Disbursement: IMPLEMENTATION PREPARED / RUNTIME VERIFICATION OPEN**
5. Stage 5 — Repayment Schedule + Payroll Integration: NOT STARTED
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
- `modules/hr/salary_advance_accounting.php`

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

## Stage 4 runtime gate

Before marking Stage 4 complete, perform a controlled runtime test after applying the migration:

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

**Immediate next task:** review the prepared Stage 4 files, apply the Stage 4 migration in the controlled local development database, and run the Stage 4 runtime/reconciliation gate above.

Do not start Stage 5 until Stage 4 is runtime-verified and documented.
