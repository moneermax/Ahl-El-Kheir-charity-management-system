# Ahl El Kheir Charity Management System
## Documentation Update — 2026-09-09

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Authoritative branch:** `main`  
**Purpose:** Record verified Accounting Phase 1 transaction controls and the completed Manual Journal Entry Integrity control.

> This is a chronological implementation and verification record. Earlier audit documents remain historical evidence and are not rewritten merely because later work has been completed.

---

# 1. Accounting Phase 1 — Transaction Workflow / FM Review

## 1.1 Core workflow — VERIFIED / PASSED

The complete transaction review workflow has been implemented and verified through controlled tests:

```text
Creator creates transaction
        ↓
pending_fm_review
        ↓
Financial Manager reviews
     ↙       ↘
 RETURN     APPROVE
   ↓           ↓
Creator      posted
edits/resubmits
   ↓
pending_fm_review
   ↓
FM approves
   ↓
posted + accounting journal
```

Verified controls:

- New transactions enter `pending_fm_review`.
- No accounting journal is created before FM approval.
- FM can return a transaction with a mandatory reason.
- The original creator can edit a returned transaction.
- The creator can resubmit the corrected transaction to FM review.
- Resubmission resets the prior FM review fields appropriately.
- FM approval changes the transaction to `posted`.
- Accounting posting occurs only after FM approval.
- The creator and FM reviewer are separate users in the verified test.
- The posting creates exactly one journal entry for the transaction.
- The journal is balanced.

---

# 2. Controlled posting test — TR-000014 — PASSED

- Transaction code: `TR-000014`
- Transaction ID: `20`
- Creator: user `17` / ACC1
- FM reviewer: user `29`
- Transaction type: `general_donation`
- Final amount: `250,000.00 SDG`
- Payment method: `mobile`
- Status: `posted`

TR-000014 successfully completed:

```text
ACC1 creates
→ FM returns
→ ACC1 edits
→ ACC1 resubmits
→ FM approves
→ transaction posts
→ accounting journal posts
```

**TR-000014 is a completed control-test record and must not be modified or reused.**

---

# 3. Journal Posting Integrity — VERIFIED / PASSED

For transaction ID `20`, the database contains exactly one balanced journal entry with the verified transaction reference. The journal contains the expected debit/credit lines and remains protected as completed audit evidence.

**TR-000014 posting integrity = PASSED.**

---

# 4. Returned Transaction → Cancelled — VERIFIED / PASSED

### Controlled transaction — TR-000015

- Transaction ID: `21`
- Creator: user `17` / ACC1
- FM reviewer: user `29`
- Type: `project_donation`
- Amount: `25,000`
- Payment method: `bank_transfer`
- Reference number: `987654321`
- Final status: `cancelled`

Tested lifecycle:

```text
ACC1 creates
→ pending_fm_review
→ FM returns with reason
→ ACC1 cancels
→ cancelled
```

The cancellation was creator-only, required a reason, preserved the FM return history, and created no journal.

**TR-000015 is a completed cancellation control-test record and must not be modified or reused.**

---

# 5. Posted Transaction → Void + Reversal — VERIFIED / PASSED

### Controlled transaction — TR-000016

- Transaction ID: `22`
- Transaction code: `TR-000016`
- Type: `general_donation`
- Amount: `1,000.00`
- Payment method: `cash`
- Original journal: `JE-000026`
- Reversal journal: `JE-VOID-TXN-22`
- Final transaction status: `voided`

The original journal was retained and voided, a separate balanced reversal journal was posted, and the transaction was voided atomically. Duplicate reversal protection and rollback behavior were verified.

**TR-000016 is a completed control-test record and must not be modified or reused.**

---

# 6. Manual Journal Entry Integrity — VERIFIED / PASSED

## 6.1 Target implementation

`modules/accounting/journal_create.php`

The implementation was hardened before live verification to enforce manual-journal integrity server-side.

Relevant implementation controls include:

- strict server-side date validation;
- required and length-limited description;
- active-account validation;
- duplicate-account rejection;
- exactly one side per journal line;
- zero-value rejection;
- negative/invalid numeric rejection;
- monetary precision enforcement compatible with `DECIMAL(14,2)`;
- minimum two journal lines;
- exact debit/credit equality using integer cents arithmetic;
- explicit database transaction around header and line creation;
- rollback on save failure;
- post-save line-count and total verification;
- serialized `JE-` number generation using a MySQL named lock;
- database uniqueness as final journal-code collision protection;
- `reference_type = manual` and `reference_id = NULL` for manual journals;
- posted status on successful creation.

Implementation commits:

- `8aa473eee507cce3d5ad96aa6d5403a7dc467002` — manual journal validation/atomicity/numbering hardening.
- `45d07f3003eeb9df1eb297eb408c949948bc68b3` — manual-journal void segregation and protection.

## 6.2 Correct role model — VERIFIED

The manual journal is an accounting management function. Accounting Staff does not receive journal access merely because the role can create operational transactions.

Live access testing established:

- **ACC1 / Accounting Staff:** cannot access the accounting journal/manual-journal functionality.
- **Financial Manager:** can access the accounting journal and manual journal functionality.

No role escalation was performed for testing.

## 6.3 Valid manual journal — PASSED

A controlled valid manual journal was created by the Financial Manager:

- Journal: `JE-000027`
- Date: `2026-09-09`
- Description: `اختبار رقابي - قيد يومية يدوي`
- Type/reference: `manual`
- Total: `1,000.00`
- Status: `posted`
- Creator: Financial Manager

The journal was successfully posted and appeared correctly in the accounting journal.

**JE-000027 is a completed manual-journal control-test record and should not be modified or reused.**

## 6.4 Validation tests — PASSED

The Financial Manager live testing confirmed server-side rejection of:

- unbalanced debit/credit totals;
- zero-value lines;
- negative amounts;
- invalid monetary input;
- excessive decimal precision;
- fewer than two lines;
- duplicate accounts;
- a line containing both debit and credit;
- invalid/nonexistent accounts;
- inactive accounts;
- invalid dates;
- invalid/empty or excessive descriptions.

The unbalanced test specifically produced:

`القيد غير متوازن: مدين 1,000.00 ≠ دائن 15,000.00`

This confirms that balance enforcement is performed before an invalid journal can be posted.

## 6.5 Atomicity and numbering — PASSED

The live tests confirmed that rejected journal submissions do not result in a visible partial journal. Valid journals receive unique `JE-XXXXXX` codes, and numbering/collision protection passed the controlled tests.

## 6.6 Audit trail and immutability — PASSED

The valid manual journal records its creator and accounting metadata. Posted manual entries cannot be ordinarily edited through the journal workflow.

## 6.7 Manual journal void segregation — PASSED

The manual-journal void control was tested successfully:

- creator cannot void their own manual journal;
- a separate authorized Financial Manager/Admin can void another user's manual journal;
- void metadata and reason are preserved;
- the original journal remains in accounting history;
- automated journal references remain protected from the manual-journal void route.

## 6.8 Final control result

**Manual Journal Entry Integrity = PASSED.**

The complete live test set covered access control, valid creation, balance enforcement, invalid values, account validation, duplicate accounts, minimum lines, debit/credit-side integrity, date/description validation, atomicity, numbering, audit trail, posted immutability, segregation of duties, manual voiding, and protection of automated journals.

---

# 7. Current Accounting Audit State

The verified Accounting Phase 1 state is now:

```text
Creator → pending_fm_review                    PASSED
        ↓
FM RETURN → returned                            PASSED
        ↓
Creator EDIT / RESUBMIT                         PASSED
        ↓
FM APPROVE → posted + journal                   PASSED
        ↓
Returned → Creator CANCEL → cancelled          PASSED
        ↓
Posted → Void + balanced reversal journal      PASSED
        ↓
Manual Journal Entry Integrity                 PASSED
        ↓
NEXT ACCOUNTING CONTROL: Journal Integrity / History Interaction
```

Completed protected control records:

- TR-000014 — posting control
- TR-000015 — return/cancellation control
- TR-000016 — posted void/reversal control
- JE-000027 — manual journal integrity control

**Do not modify or reuse these completed control-test records.**

---

# 8. Next Accounting Audit Control

The next audit item is the broader **Journal Integrity / Accounting History Interaction** control.

The next review should focus on the journal/history layer as a whole, without repeating the completed manual-entry validation:

1. journal listing and filtering integrity;
2. correct separation of `manual`, `transaction`, `transaction_void`, `disbursement`, and `voucher` references;
3. journal detail/history consistency;
4. visibility of posted versus voided entries;
5. preservation of original entries after reversal/void;
6. prevention of unauthorized journal mutation through alternate routes;
7. accounting-history totals and balance consistency;
8. interaction between transaction status and journal status;
9. duplicate/missing journal relationships;
10. cross-module accounting references and auditability.

Do not modify or reuse TR-000014, TR-000015, TR-000016, or JE-000027.

---

# 9. Documentation Maintenance Rule

The repository documentation follows:

**Code correct → behavior verified → documentation updated → session index updated → next continuation point clear.**

Historical audit documents remain historical evidence. New verified milestones are recorded chronologically here and in the session index.
