# Ahl El Kheir Charity Management System — Documentation Update

**Date:** 2026-09-12  
**Area:** Accounting Audit — Automated Journal References, Voucher Reversal, Payroll Accounting, and Continuation Checkpoint  
**Repository HEAD reviewed:** `6731228776446c8ddca8b3346e7291de44ed252a`  
**Status:** Documentation checkpoint updated; previously passed disbursement-void evidence remains closed/PASS; next audit target is account-ledger integrity and remaining journal mutation paths.

---

## 1. Continuation rule

This is a continuation of the existing Ahl El Kheir Accounting Audit. No previously completed fixtures or tests are to be recreated merely for documentation purposes.

Protected completed disbursement-void evidence remains:

- monthly disbursement `11`
- transaction `23`
- original journal `JE-AUDIT-DISB-11`
- reversal journal ID `43`
- reversal reference type `disbursement_void`

The disbursement-void audit is closed/PASS and must not be rerun unless genuine regression evidence appears.

---

## 2. Automated journal reference protection — STATIC PASS

The generic manual journal route in `modules/accounting/journal.php` now protects the automated/reversal reference types used by the current accounting modules:

```text
transaction
transaction_void
disbursement
disbursement_return
disbursement_void
item_return
voucher
payroll
manual_void
```

This prevents an automated accounting record from being converted into an additional manual-void mutation through the generic journal page.

Fix commit:

`8e3ee6fd7d95c0efef834a284ed428d125064bfc`

Local browser/runtime verification for the three newly discovered types (`payroll`, `disbursement_void`, `item_return`) remains a separate runtime task. Static code protection is PASS; runtime verification is not being falsely marked PASS.

---

## 3. Voucher accounting workflow — STATIC PASS

The active voucher workflow in `modules/accounting/vouchers.php` was reviewed.

The current void route:

1. Locks the voucher and linked journal.
2. Confirms the journal is a `voucher` journal linked to the same voucher.
3. Requires the original voucher journal to be unique.
4. Rejects an existing `voucher_void` reversal.
5. Requires journal lines and validates original debit/credit balance.
6. Creates a separate balanced `voucher_void` reversal with debit/credit swapped.
7. Verifies the reversal balance.
8. Marks the original journal `voided`.
9. Marks the voucher `voided`.
10. Writes the VOID audit-log event.
11. Performs the entire mutation inside a database transaction.

Result: **STATIC PASS.**

A legacy helper, `ak_void_journal_for_voucher()`, remains in `modules/accounting/lib.php`, but repository call-site inspection found no current caller. It therefore remains an unreachable/dead legacy path and was not changed unnecessarily.

A later hardening commit also protects voucher reversal journals from the generic manual-void path:

`37fec1a5ed6f3e58c7c441a7b3666290e1ebe8fb`

The voucher workflow therefore remains protected without rewriting historical voucher data.

---

## 4. Payroll accounting hardening — CURRENT REPOSITORY STATE

Recent repository commits after the previous documentation checkpoint strengthened payroll accounting:

- `52438233008269be3f26314f15f2f6d5ae62cfd7` — Fix payroll reversal journal workflow
- `4a5bc10ddc04b7afbad544f95f8f87ceea55161c` — Make payroll reversal workflow atomic
- `6731228776446c8ddca8b3346e7291de44ed252a` — Validate existing payroll journals before reuse

These changes are important to the accounting audit because payroll is an automated journal reference type and its journal/status relationship must remain synchronized.

Current audit interpretation:

- payroll journals are automated records;
- payroll reversal is treated as a distinct accounting event;
- multi-record payroll reversal handling is transactional;
- existing payroll journal reuse is validated before reuse;
- generic manual-void access remains prohibited for `reference_type = payroll`.

These repository changes supersede the older checkpoint wording that treated payroll accounting as only a discovered risk. The risk has received additional code hardening; local runtime verification remains required before the complete payroll control is marked fully PASS.

---

## 5. Accounting history principle confirmed

The current audit continues to enforce the following rule:

```text
Original financial event
        ↓
Original journal is preserved
        ↓
Void/reversal creates a separate journal
        ↓
Original status and source-module state remain coherent
        ↓
Audit history remains inspectable
```

A reversal is not implemented by silently deleting or overwriting the original journal.

This applies to transaction voids, disbursement voids, item returns, voucher voids, payroll reversals, and protected manual-void behavior where applicable.

---

## 6. Current audit state

### Passed / closed controls

- Creator → FM review workflow.
- FM return/resubmission.
- FM approval → posted balanced journal.
- Returned transaction cancellation with no journal.
- Authorized transaction void + balanced reversal.
- Manual journal integrity.
- Trial Balance integrity.
- Accountant Staff financial/report authorization.
- Accountant Staff disbursement authorization.
- Receipt/closure workflow.
- Journal listing/filtering.
- Journal detail/history consistency.
- Preservation of original entries.
- Legacy disbursement `void_batch` protection.
- Controlled disbursement-void fixture and reversal evidence.
- Voucher void route static integrity.
- Voucher reversal separation/protection static integrity.

### Code-fixed but runtime verification still required

- Generic manual-void rejection for `payroll`.
- Generic manual-void rejection for `disbursement_void`.
- Generic manual-void rejection for `item_return`.
- End-to-end payroll reversal runtime behavior where the current local test path is appropriate.

### Parked auditability enhancement

Direct UI navigation between source record, original journal, and reversal journal remains a future enhancement. This is not currently classified as an accounting-integrity failure.

---

## 7. Exact next audit target

Proceed without restarting prior work:

1. Inspect `modules/accounting/account_ledger.php` and related ledger/reporting code.
2. Verify journal-entry → journal-line → account relationships.
3. Verify the ledger is read-only and cannot mutate journal history.
4. Verify posted/voided journal handling is consistent with the accounting model.
5. Verify running debit/credit/balance calculations.
6. Verify reversal entries are represented correctly so original + reversal produce the expected net accounting effect.
7. Search for any remaining direct journal/header/line mutation paths only where needed.
8. Perform runtime tests only when static inspection identifies a genuine gap requiring them.

Do not recreate protected fixtures or repeat completed accounting tests.

---

## 8. Documentation maintenance rule

The canonical audit record remains `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`.

This dated update records the newer 2026-09-12 repository state so that future sessions can distinguish the 2026-09-11 checkpoint from subsequent payroll/voucher hardening.

The system analysis addendum is:

`docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS_UPDATE_2026-09-12.md`
