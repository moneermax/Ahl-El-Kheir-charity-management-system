# Ahl El Kheir Charity Management System — Documentation Update

**Date:** 2026-09-11  
**Area:** Accounting Audit — Legacy Disbursement Void Protection  
**Status:** **PASS — test completed; future work parked**

---

## 1. Exact milestone completed

The audit identified a legacy `void_batch` POST route in:

`modules/accounting/disbursements.php`

The legacy route could directly mutate disbursement/accounting state without creating the required separate balanced reversal journal for a posted disbursement.

A narrow guard was introduced:

`modules/accounting/disbursement_void_guard.php`

The guard is loaded from the application configuration and intercepts the exact legacy `void_batch` POST action before the old mutation block can execute.

The guard:

- restricts the operation to `admin` and `financial_manager`;
- validates CSRF;
- locks the target `monthly_disbursements` row;
- permits only the intended pre-return states;
- locates the original posted disbursement journal;
- validates that the original journal is balanced and valid;
- creates a separate `disbursement_void` reversal journal;
- swaps debit/credit lines for the reversal;
- validates the reversal balance;
- voids the original journal without deleting it;
- voids the linked transaction when applicable;
- marks the monthly disbursement `voided`;
- stores `reversal_journal_id`;
- records a `DISBURSEMENT_VOID` audit-log event;
- commits all related financial changes atomically and rolls back on failure.

Implementation commit for the guard correction:

`0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

---

## 2. Controlled test fixture

A dedicated temporary audit fixture was already created and was **not recreated** during this test.

- Monthly disbursement: **ID 11**
- Month: `2026-10`
- Nanny: `16`
- Group: `1`
- Amount: `100.00`
- Initial status: `transferred`
- Transaction: **ID 23**
- Transaction reference: `AUDIT-DISB-VOID-11`
- Initial transaction status: `posted`
- Original journal: `JE-AUDIT-DISB-11`
- Original journal amount: debit `100.00`, credit `100.00`
- Initial `reversal_journal_id`: `NULL`

This fixture was intentionally isolated from the protected historical evidence and was used only for this targeted workflow test.

---

## 3. Debugging finding and correction

The first execution reached the audit-log write but failed because the local database schema uses:

```text
audit_log.action
```

not:

```text
audit_log.action_type
```

The actual schema was verified with `SHOW COLUMNS FROM audit_log` before making the correction.

The corrected guard now records the action using the actual `action` column and preserves the available audit values.

This was a schema-alignment defect in the guard, not a failure of the accounting workflow itself. The failed attempt rolled back and the fixture remained eligible for the controlled test.

---

## 4. Browser test result — PASS

The same existing batch 11 was voided through the actual application UI.

Application result:

> تم إبطال الدفعة وإنشاء القيد العكسي مع الحفاظ على القيد الأصلي وسجل التدقيق.

No fixture recreation was performed.

---

## 5. Final database verification — PASS

Final targeted verification for batch 11 returned:

| Control | Verified result |
|---|---|
| Batch ID | `11` |
| Batch status | `voided` |
| Reversal journal ID | `43` |
| Transaction ID | `23` |
| Transaction status | `voided` |
| Original journal | `JE-AUDIT-DISB-11` |
| Original journal status | `voided` |
| Reversal journal | `JE-REV-DISB-000011-20260911183412` |
| Reversal reference type | `disbursement_void` |
| Reversal status | `posted` |
| Reversal debit | `100.00` |
| Reversal credit | `100.00` |
| Reversal balance | **balanced** |
| `DISBURSEMENT_VOID` audit entries | `1` |

Therefore the complete tested accounting invariant is:

```text
Posted disbursement
    ↓
Separate balanced disbursement_void reversal
    ↓
Original journal preserved + voided
    ↓
Linked transaction voided
    ↓
Monthly disbursement voided
    ↓
Audit history recorded
```

**Audit Test: PASS**

---

## 6. Relationship to previous accounting audit work

This test is a continuation of the existing Accounting Audit. It does not replace or reopen any previous control.

Already completed controls remain protected and must not be rerun without regression evidence, including:

- creator → Financial Manager review;
- no journal before approval;
- FM return/resubmission;
- FM approval → posted + balanced journal;
- returned transaction cancellation without journal;
- posted transaction authorized void + balanced `transaction_void` reversal;
- manual journal integrity;
- Trial Balance integrity;
- Accountant Staff financial/reporting authorization;
- Accountant Staff disbursement authorization;
- existing receipt/closure workflow;
- journal listing/filtering;
- journal detail/history consistency;
- previously completed relationship/orphan/status checks.

Protected historical evidence remains untouched, including TR-000014 / transaction 20, TR-000015 / transaction 21, TR-000016 / transaction 22, JE-000027, JE-000026 / journal 37, and JE-VOID-TXN-22.

---

## 7. Important database facts confirmed during this milestone

Actual accounting tables are:

- `accounts`
- `journal_entries`
- `journal_lines`

Do not assume a nonexistent `accounting_entries` table.

Account display name is `accounts.name_ar`.

The local `audit_log` schema uses `action`, not `action_type`.

The transaction → journal relationship is application-level through `journal_entries.reference_type` / `reference_id`; there is no assumed direct `transactions.journal_entry_id` foreign key.

---

## 8. Current code/history references

Relevant implementation commits include:

- Transaction void/reversal hardening: `99759d186cbb9507be631aa4df48cbef4d7e202b`
- Voucher void atomicity: `2165513c9009d1fa435fdd122d545bf4d2404b07`
- Automated/reversal reference protection: `8e3ee6fd7d95c0efef834a284ed428d125064bfc`
- Legacy disbursement void guard correction: `0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

---

# 9. TODO LATER — deliberately parked

The following work is **not part of this completed test** and should remain parked:

1. Direct original ↔ reversal/source navigation in journal detail.
2. Same-page Bootstrap modal UX for missing/stale disbursement receipts.
3. Any broader journal auditability enhancement after the remaining mutation-route audit is complete.

Do not use the parked UI/auditability items as a reason to reopen this passed test.

---

# 10. EXACT NEXT ACCOUNTING AUDIT TASK

Continue the existing **Accounting Audit → Journal Integrity / Accounting History Interaction** work.

The next task is **not** to retest disbursement voiding.

Proceed with:

1. Targeted runtime/UI verification that the generic manual-void route does not expose/allow voiding for the automated reference types:
   - `payroll`
   - `disbursement_void`
   - `item_return`
2. Inspect remaining callers of `ak_void_journal_for_voucher()`.
3. Inspect other direct journal mutation routes (`UPDATE journal_entries`, journal-line mutation, destructive deletes) only where they represent a real alternate accounting mutation path.
4. Continue cross-module journal relationship/auditability review.
5. Preserve historical accounting anomalies and protected evidence.

Do not restart the audit. Do not rerun passed tests. Do not recreate known fixtures unless genuine regression evidence requires it.

---

## 11. Continuation rule

The next session should treat this document as the **latest milestone supplement** to:

- `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`
- `docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11.md`
- `docs/CHATGPT_SESSION_INDEX.md`

The 2026-09-11 disbursement-void test is **closed/PASS**. The next session begins with the remaining automated-reference manual-void protection verification described above.
