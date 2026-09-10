# Ahl El Kheir Charity Management System
## Accounting Integrity Audit — Phase 1

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Document type:** Canonical Accounting Audit Record  
**Last verified:** 2026-09-10

This is the canonical Accounting Phase 1 audit document. It consolidates the previously separate chronological Accounting checkpoint, void/reversal checkpoint, and documentation-update records. Historical evidence is preserved here; the superseded duplicate files are removed to keep `docs/` organized.

---

# 1. Audit status

Accounting Phase 1 has completed and passed the following controls:

1. Creator → FM review workflow.
2. FM return with mandatory reason.
3. Creator edit/resubmit of returned transaction.
4. FM approval → posted transaction + balanced journal.
5. Returned transaction → creator cancellation with no journal.
6. Posted transaction → authorized void + balanced reversal journal.
7. Manual Journal Entry Integrity.
8. Trial Balance Integrity.

The next control is **Journal Integrity / Accounting History Interaction**.

---

# 2. Core transaction workflow — PASSED

Verified workflow:

```text
Creator creates
→ pending_fm_review
→ FM reviews
   ├─ RETURN → returned → creator edits/resubmits → pending_fm_review
   └─ APPROVE → posted + accounting journal
```

Controls verified:

- No accounting journal before FM approval.
- FM return requires a reason.
- Only the original creator performs the tested returned-transaction cancellation.
- Creator and FM reviewer are separate users in the controlled tests.
- FM approval creates the accounting posting.
- Posted transaction journals are balanced.

---

# 3. Controlled record TR-000014 — POSTING PASSED

- Transaction ID: `20`
- Creator: user `17` / ACC1
- FM reviewer: user `29`
- Type: `general_donation`
- Amount: `250,000.00 SDG`
- Payment method: `mobile`
- Final status: `posted`
- Journal ID: `36`
- Journal balanced: `250,000.00 / 250,000.00`

Lifecycle passed:

```text
ACC1 creates
→ FM returns
→ ACC1 edits
→ ACC1 resubmits
→ FM approves
→ transaction posts
→ balanced accounting journal posts
```

**TR-000014 is protected completed audit evidence. Do not modify or reuse it.**

---

# 4. Returned → Cancelled — PASSED

## Controlled record TR-000015

- Transaction ID: `21`
- Creator: user `17` / ACC1
- FM reviewer: user `29`
- Type: `project_donation`
- Amount: `25,000.00`
- Payment method: `bank_transfer`
- Reference: `987654321`
- Final status: `cancelled`
- `cancelled_by = 17`
- Cancellation reason: `إلغاء من المنشئ بعد الإرجاع`

Verified sequence:

```text
1516 SUBMIT_FM
1517 FM_RETURN
1518 CANCEL_RETURNED
```

The returned transaction created no journal. Cancellation preserved FM review information and audit history and removed only unused receipt files according to the established reference-safety rule.

**TR-000015 is protected completed audit evidence. Do not modify or reuse it.**

Implementation: `modules/transactions/cancel_returned.php`  
Correction commit: `62229c5a9a4be9ad2d55223402b5fe1f03bd81e4`

---

# 5. Posted → Void + Reversal — PASSED

## Controlled record TR-000016

- Transaction ID: `22`
- Type: `general_donation`
- Amount: `1,000.00`
- Payment method: `cash`
- Final transaction status: `voided`
- Original journal: `JE-000026` / ID `37` / final status `voided`
- Reversal journal: `JE-VOID-TXN-22`
- Reversal reference: `transaction_void / 22`
- Reversal status: `posted`
- Reversal balance: debit `1,000.00` = credit `1,000.00`
- Void performer: Financial Manager

Verified atomic result:

```text
Original journal → voided
        +
Reversal journal → posted + balanced
        +
Transaction → voided
```

Failure testing confirmed rollback protection. Duplicate reversal protection prevents a second `transaction_void` journal for the same transaction.

The implementation avoids schema-changing DDL inside the outer transaction because MySQL/MariaDB DDL may implicitly commit.

Primary helper: `modules/accounting/lib_transaction_void.php`  
Calling workflow: `modules/transactions/index.php`  
Hardening commit: `99759d186cbb9507be631aa4df48cbef4d7e202b`

**TR-000016 is protected completed audit evidence. Do not modify or reuse it.**

---

# 6. Manual Journal Entry Integrity — PASSED

## Implementation

Primary page: `modules/accounting/journal_create.php`

Server-side controls verified include:

- strict date validation;
- required/length-limited description;
- active-account validation;
- duplicate-account rejection;
- exactly one side per line;
- zero/negative/invalid amount rejection;
- monetary precision enforcement compatible with `DECIMAL(14,2)`;
- minimum two lines;
- exact debit/credit equality using integer cents;
- atomic database transaction and rollback;
- post-save line-count and total verification;
- serialized `JE-` numbering with MySQL named lock;
- database uniqueness as final collision protection;
- `reference_type = manual`, `reference_id = NULL`;
- successful creation as `posted`.

Implementation commits:

- `8aa473eee507cce3d5ad96aa6d5403a7dc467002`
- `45d07f3003eeb9df1eb297eb408c949948bc68b3`

## Role segregation

- **ACC1 / Accounting Staff:** journal access denied, including direct access.
- **Financial Manager:** journal/manual-journal access allowed.
- Creator cannot void their own manual journal.
- Separate authorized Financial Manager/Admin can void another user's manual journal.
- Automated journal references are protected from the manual-journal void route.

## Controlled journal

`JE-000027`

- Date: `2026-09-09`
- Description: `اختبار رقابي - قيد يومية يدوي`
- Reference type: `manual`
- Total: `1,000.00`
- Status: `posted`
- Creator: Financial Manager

The full validation set passed, including unbalanced, zero, negative, invalid numeric, excessive precision, fewer-than-two-lines, duplicate-account, both-sides-on-one-line, invalid/inactive-account, invalid-date, and invalid-description tests.

The controlled unbalanced test returned:

`القيد غير متوازن: مدين 1,000.00 ≠ دائن 15,000.00`

**JE-000027 is protected completed audit evidence. Do not modify or reuse it.**

---

# 7. Trial Balance Integrity — PASSED

## Objective

Verify that the complete posted accounting book is mathematically balanced:

**Total Debit = Total Credit**

## Implementation

Read-only page: `modules/accounting/trial_balance.php`

The Trial Balance:

- is restricted to Admin and Financial Manager;
- uses posted journal entries only;
- excludes voided journals;
- includes normal posted journals, posted transaction-void reversal journals, and posted manual journals;
- supports optional date filtering;
- reports account-level debit, credit, and net movement;
- independently calculates system-wide debit and credit totals;
- reports posted journal and posted-line counts.

## Verified result

- Total debit: **103,373,000.00**
- Total credit: **103,373,000.00**
- Difference: **0.00**
- Posted journals: **24**
- Posted lines: **54**
- Result: **✓ الميزان متوازن — PASSED**

The Trial Balance is an accounting-integrity control. It is intentionally separate from `modules/accounting/gm_reconciliation.php`, which is a management/operational reconciliation report covering inflows, outflows, cash movement, open disbursement batches, aging, returns, and voided disbursement history.

These reports are complementary, not duplicates:

| Trial Balance | GM Reconciliation |
|---|---|
| Proves posted journal debit/credit equality | Reviews operational financial movement |
| Covers every posted journal line | Focuses on management-level inflow/outflow/cash views |
| Account-level accounting totals | Monthly/category and workflow-oriented summaries |
| Accounting integrity control | Management oversight/reconciliation |

**Trial Balance Integrity = PASSED.**

Implementation commits:

- `4aa9c05892a806be016c20b9234b15d392b441b9` — initial Trial Balance
- `a0c9b73daf70a393d31587b2874b70154f9c0857` — corrected posted-line aggregation
- `af97f58607317a6605feac69adfb1ef5d590d4d6` — Chart of Accounts link
- `14a0b04e9916bf3c572fafb9b3f831acaafb849e` — Account Ledger
- `be24ce9046fc8d37bc31e48a2b9b137a8a26eb4e` — Chart of Accounts integration

---

# 8. Cross-session continuation communication rule

This project uses a **work-first, result-only communication rule** for ChatGPT continuation sessions.

- Perform repository inspection, analysis, documentation maintenance, and safe code changes directly before responding.
- Do not send progress-only messages such as `proceed`, `I will inspect`, `I will check`, plans, or announcements of intended work.
- Return to the user only when there is a concrete result, or when user action is genuinely required.
- User action should be requested only for a local verification/test that cannot be performed through repository inspection, or when a required file cannot be safely retrieved from the repository and must be provided/pulled by the user.
- When user action is required, state exactly what to check/provide and why, using the smallest possible request.
- Do not ask the user to repeat information already available in the repository or the current documented checkpoint.

This rule exists to reduce unnecessary chat messages, preserve conversation capacity, and keep continuation sessions focused on actual implementation and verification.
