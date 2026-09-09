# Ahl El Kheir Charity Management System
## Documentation Update — 2026-09-09

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Authoritative branch:** `main`  
**Purpose:** Record the verified Accounting Phase 1 transaction workflow, posting integrity, returned-transaction cancellation control, and posted-transaction void/reversal control.

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

The controlled transaction used for the successful approval/posting workflow was:

- Transaction code: `TR-000014`
- Transaction ID: `20`
- Creator: user `17` / ACC1
- FM reviewer: user `29`
- Transaction type: `general_donation`
- Final amount: `250,000.00 SDG`
- Payment method: `mobile`
- Sponsor: `NULL`
- Sponsorship: `NULL`
- Project: `NULL`
- Status: `posted`
- FM review timestamp: `2026-09-09 11:18:20`

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

For transaction ID `20`, the database contains exactly one journal entry:

- Journal entry ID: `36`
- `reference_type = transaction`
- `reference_id = 20`
- `status = posted`
- Created at: `2026-09-09 11:18:20`

Journal entry `36` contains exactly two lines:

| Account | Account name | Debit | Credit |
|---|---|---:|---:|
| `1300` | المحافظ الإلكترونية | 250,000.00 | 0.00 |
| `4300` | التبرعات العامة | 0.00 | 250,000.00 |

Descriptions:

- `تحصيل TR-000014`
- `تبرع عام TR-000014`

Balance verification:

- Total debit: `250,000.00`
- Total credit: `250,000.00`
- Difference: `0.00`

Therefore **TR-000014 posting integrity = PASSED**.

The verified database relationship is:

`journal_lines.entry_id = journal_entries.id`

The journal-lines foreign-key column is `entry_id`, not `journal_entry_id`.

---

# 4. Final Transaction Integrity — VERIFIED

The final transaction database record for TR-000014 confirms:

- `id = 20`
- `transaction_code = TR-000014`
- `transaction_type = general_donation`
- `amount = 250000.00`
- `currency_code = SDG`
- `payment_method = mobile`
- `sponsor_id = NULL`
- `sponsorship_id = NULL`
- `project_id = NULL`
- `reference_number = NULL`
- `status = posted`
- `created_by = 17`
- `fm_reviewed_by = 29`
- `fm_reviewed_at = 2026-09-09 11:18:20`

---

# 5. Implementation Corrections Confirmed During This Phase

The returned-transaction editor was corrected to use the actual transaction schema field:

- `reference_number` instead of the nonexistent `reference` column.

The returned-transaction update no longer attempts to write an `other_source_note` field to `transactions`, because that field is not part of the verified transaction schema.

The returned editor supports preservation/replacement/removal of transaction receipts and unified receipts subject to the established safe-reference deletion rule.

The Arabic error message was clarified from the earlier wording containing `بشكل ذري` to:

`تعذر حفظ التعديلات وإعادة إرسال الدفعة للمراجعة المالية. يرجى التحقق من البيانات والمحاولة مرة أخرى.`

The returned-transaction cancellation implementation was corrected to enforce creator-only cancellation, require a non-empty cancellation reason server-side, perform the state change atomically, record `CANCEL_RETURNED`, notify FM users, and avoid any journal/posting operation. Receipt physical deletion remains subject to reference-safety checks.

Implementation commit:

`62229c5a9a4be9ad2d55223402b5fe1f03bd81e4`

---

# 6. Authorization / Segregation of Duties — VERIFIED

The verified posting test establishes:

- Creator = user `17` / ACC1
- Reviewer = user `29` / Financial Manager

The same user did not create and approve TR-000014.

The FM review workflow includes prevention of FM self-approval.

For returned cancellation, TR-000015 was cancelled by its original creator (user `17`), demonstrating the creator boundary in the tested route.

---

# 7. RETURNED TRANSACTION → CANCELLED — VERIFIED / PASSED

A separate controlled transaction was used so the completed posting test was not altered.

### Controlled transaction — TR-000015

- Transaction ID: `21`
- Creator: user `17` / ACC1
- FM reviewer: user `29`
- Type: `project_donation`
- Amount: `25,000`
- Payment method: `bank_transfer`
- Reference number: `987654321`

Tested lifecycle:

```text
ACC1 creates
→ pending_fm_review
→ FM returns with reason
→ ACC1 cancels
→ cancelled
```

### FM return verification — PASSED

The transaction changed to `returned` and disappeared from the FM pending queue.

Verified values included:

- `created_by = 17`
- `fm_reviewed_by = 29`
- `fm_reviewed_at = 2026-09-09 16:26:12`
- `fm_review_reason = اختبار رقابي — تم الإرجاع للاختبار قبل الإلغاء`

No journal entry existed for transaction `21` after return.

Audit records included:

- `1516` — `SUBMIT_FM`
- `1517` — `FM_RETURN`

### Cancellation verification — PASSED

ACC1 cancelled TR-000015 from the returned state.

Final verified values:

- `status = cancelled`
- `cancelled_at = 2026-09-09 16:30:38`
- `cancelled_by = 17`
- `cancel_reason = إلغاء من المنشئ بعد الإرجاع`

The UI confirmed:

`تم إلغاء الدفعة المُعادة وحذف الإيصالات غير المستخدمة مع الحفاظ على سجل التدقيق.`

No journal entry existed for transaction `21` after cancellation.

The new audit event was:

- `1518` — user `17` — `CANCEL_RETURNED` — transaction `21`

The complete audit sequence is:

```text
1516 SUBMIT_FM
1517 FM_RETURN
1518 CANCEL_RETURNED
```

The FM return history remained preserved after cancellation.

**TR-000015 is now a completed cancellation control-test record and must not be modified or reused.**

Detailed checkpoint: `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT_CHECKPOINT_2026-09-09.md`

---

# 8. POSTED TRANSACTION → VOID + REVERSAL — VERIFIED / PASSED

A separate controlled transaction was used so the completed TR-000014 and TR-000015 tests were not altered.

### Controlled transaction — TR-000016

- Transaction ID: `22`
- Transaction code: `TR-000016`
- Type: `general_donation`
- Amount: `1,000.00`
- Payment method: `cash`
- Original journal ID: `37`
- Original journal code: `JE-000026`

The transaction was posted successfully before the void test.

### Void verification — PASSED

The authorized Financial Manager voided TR-000016 from the transactions module.

Final transaction state:

`TR-000016 → voided`

The transaction record was retained rather than deleted.

The original journal `JE-000026` was retained and changed from `posted` to `voided` with void metadata.

### Reversal journal — PASSED

The operation created a separate journal:

- Code: `JE-VOID-TXN-22`
- Reference type: `transaction_void`
- Reference ID: `22`
- Status: `posted`
- Amount: `1,000.00`
- Creator: Financial Manager

The reversal contains the opposite debit/credit direction of the original journal lines.

Verified totals:

- Debit: `1,000.00`
- Credit: `1,000.00`
- Difference: `0.00`

### Atomicity and failure rollback — PASSED

The final successful test confirmed the complete financial operation was committed together:

```text
Original journal → voided
        +
Reversal journal → posted + balanced
        +
Transaction → voided
```

Earlier controlled failure testing confirmed that a failed reversal rolled back the transaction/journal changes rather than leaving a partial financial update.

The implementation avoids schema-changing DDL through `ak_ensure_tables()` while the outer transaction is active because MySQL/MariaDB DDL can implicitly commit and break transaction atomicity.

### Duplicate protection — PASSED by implementation

The void helper rejects a second `transaction_void` journal for the same transaction and requires the original journal to be `posted` before a new void operation begins.

Implementation commit:

`99759d186cbb9507be631aa4df48cbef4d7e202b`

Detailed checkpoint: `docs/AHL_EL_KHEIR_ACCOUNTING_VOID_REVERSAL_CHECKPOINT_2026-09-09.md`

**TR-000016 is now a completed control-test record and must not be modified or reused.**

---

# 9. Current Accounting Phase 1 State

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
NEXT: MANUAL JOURNAL ENTRY INTEGRITY
```

Completed protected control records:

- TR-000014 — posting control
- TR-000015 — return/cancellation control
- TR-000016 — posted void/reversal control

None of these records should be modified or reused.

---

# 10. Next Accounting Audit Control

The next concrete Accounting integrity control is **Manual Journal Entry Integrity**.

Target implementation:

`modules/accounting/journal_create.php`

The current implementation permits authorized Accountant / Financial Manager / Admin users to create manual journals directly as `posted`. The next audit must inspect, without prematurely changing behavior:

1. authorization and segregation of duties;
2. server-side debit/credit validation;
3. minimum-line and balance enforcement;
4. duplicate-account restrictions;
5. journal-number generation and uniqueness;
6. atomic creation of journal header and all journal lines;
7. behavior when line insertion fails;
8. audit-trail requirements;
9. posted-entry immutability;
10. manual-entry void controls and their interaction with accounting history.

Do not reuse any completed transaction control record for this audit.

---

# 11. Continuation Rule

The current Accounting continuation state is:

```text
HR FOUNDATION / INTEGRITY AUDIT
        ↓
      CLOSED
        ↓
HR DASHBOARD / NAVIGATION
        ↓
      CLOSED
        ↓
ACCOUNTING AUDIT
        ↓
PHASE 1 — TRANSACTION WORKFLOW / FM REVIEW
        ↓
CORE WORKFLOW — PASSED
        ↓
TR-000014 POSTING INTEGRITY — PASSED
        ↓
TR-000015 RETURNED → CANCELLED — PASSED
        ↓
TR-000016 POSTED → VOID + REVERSAL — PASSED
        ↓
NEXT: MANUAL JOURNAL ENTRY INTEGRITY
```

Do not restart the Accounting audit from the beginning.

---

# 12. Documentation Maintenance

This document records the verified 2026-09-09 Accounting milestones. The project documentation hierarchy remains:

- Historical audit documents preserve historical findings.
- Long-lived architecture documentation remains the architectural source of truth.
- Chronological dated updates record verified implementation milestones.
- `CHATGPT_SESSION_INDEX.md` records the current continuation point.
- `AHL_EL_KHEIR_ACCOUNTING_AUDIT_CHECKPOINT_2026-09-09.md` records the detailed return/cancellation control test.
- `AHL_EL_KHEIR_ACCOUNTING_VOID_REVERSAL_CHECKPOINT_2026-09-09.md` records the detailed posted-transaction void/reversal control test.

Required maintenance sequence remains:

**Code correct → behavior verified → documentation updated → session index updated → next continuation point clear.**
