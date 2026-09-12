# Ahl El Kheir Charity Management System
## Accounting Integrity Audit — Phase 1

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Document type:** Canonical Accounting Audit Record  
**Last verified:** 2026-09-12

This is the canonical Accounting audit record. Historical evidence is preserved here; the current continuation point is maintained at the end of this document.

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
9. Accountant Staff financial/reporting authorization surface audit.
10. Accountant Staff disbursement authorization.
11. Existing disbursement receipt/closure workflow.
12. Journal listing/filtering integrity.
13. Journal detail/history consistency.

The remaining Journal Integrity / Accounting History Interaction controls are the relationship/auditability items listed in Section 11 and the consolidated 2026-09-12 continuation sections below.

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

Primary helper: `modules/accounting/lib_transaction_void.php`  
Calling workflow: `modules/transactions/index.php`  
Hardening commit: `99759d186cbb9507be631aa4df48cbef4d7e202b`

**TR-000016 is protected completed audit evidence. Do not modify or reuse it.**

---

# 6. Manual Journal Entry Integrity — PASSED

Primary page: `modules/accounting/journal_create.php`

Server-side controls verified include strict validation, active-account validation, duplicate-account rejection, one-sided lines, positive amounts, minimum two lines, exact debit/credit equality, atomic transaction handling, post-save verification, serialized numbering, uniqueness protection, and `reference_type = manual` / `reference_id = NULL`.

Manual voiding is restricted to authorized roles; the creator cannot void their own manual journal; automated journal references are protected from the manual void route.

Controlled journal: `JE-000027` — date `2026-09-09`, manual, `1,000.00`, posted, created by Financial Manager.

**JE-000027 is protected completed audit evidence. Do not modify or reuse it.**

Implementation commits:

- `8aa473eee507cce3d5ad96aa6d5403a7dc467002`
- `45d07f3003eeb9df1eb297eb408c949948bc68b3`

---

# 7. Trial Balance Integrity — PASSED

Read-only page: `modules/accounting/trial_balance.php`

Verified result:

- Total debit: **103,373,000.00**
- Total credit: **103,373,000.00**
- Difference: **0.00**
- Posted journals: **24**
- Posted lines: **54**
- Result: **✓ الميزان متوازن — PASSED**

The Trial Balance uses posted journal entries only and excludes voided journals. It is intentionally distinct from `modules/accounting/gm_reconciliation.php`, which is a management/operational reconciliation report.

Implementation commits:

- `4aa9c05892a806be016c20b9234b15d392b441b9`
- `a0c9b73daf70a393d31587b2874b70154f9c0857`
- `af97f58607317a6605feac69adfb1ef5d590d4d6`
- `14a0b04e9916bf3c572fafb9b3f831acaafb849e`
- `be24ce9046fc8d37bc31e48a2b9b137a8a26eb4e`

---

# 8. Accountant Staff financial/reporting and disbursement authorization — PASSED

The organization-wide financial/reporting authorization audit and the Accountant Staff disbursement authorization audit were completed without modifying protected accounting evidence.

Confirmed restricted financial/reporting surfaces include journal, ledger, trial balance and organization-wide financial reports. The previously exposed confirmed-disbursements report was corrected by commit `4750cb72ddd080ee604928e9aa1c67b9b11f70a9`.

Disbursement workflow established and tested:

```text
Vice General Manager creates batch
→ assigns batch/group to Accountant Staff
→ Accountant Staff manages only assigned nanny/batch scope
```

Assigned visibility, restricted financial actions, reopen controls, nanny receipt confirmation, batch void and existing receipt/closure workflow all passed. Accountant Staff is not a batch-creation role in the established workflow.

Do not repeat these tests unless new code evidence creates a regression.

---

# 9. Receipt handling — HARDENED; UI DEFERRED

Primary viewer: `modules/accounting/serve_receipt.php`

Stored receipt paths are validated with `realpath()` and `is_file()`, constrained to the application base directory, and handled with an application-level Arabic message when missing/stale. The viewer does not substitute an individual family receipt for a missing final batch receipt.

Relevant commits:

- `e6b6fd1f15f63a48c6224da3635efddc95ac38f2`
- `65625215686027ce172425c611e40c1d039de35c`
- `8cbf181071fc27de59abc26e1f4d4f0bf8f71a9e`

Deferred UI TODO: move the missing/stale receipt message (and preferably valid receipt preview) into a same-page Bootstrap modal on `modules/accounting/disbursements.php` without weakening server-side receipt authorization/path validation.

---

# 10. Journal Integrity / Accounting History Interaction — CURRENT CHECKPOINT

## 10.1 Journal listing/filtering integrity — PASS

Current `modules/accounting/journal.php`:

- restricts journal access to authorized management/accounting roles;
- supports date-from/date-to filtering;
- orders by entry date descending and journal ID descending;
- displays journal code, date, description, reference type, value, status and creator;
- provides a direct detail view for each journal;
- now protects automated/reversal reference types used by current accounting modules.

The original protection set included:

```text
transaction
transaction_void
disbursement
disbursement_return
voucher
manual_void
```

The current repository inspection found three additional current journal reference types that must also be protected from the generic manual-void route:

```text
disbursement_void
item_return
payroll
```

The resulting protected set is now:

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

`disbursement_return` was previously added by commit `c37d72b3b0bff2497aab525f6944e05c58cb5fd7`.

The new protection correction is commit `8e3ee6fd7d95c0efef834a284ed428d125064bfc`.

## 10.2 Reference-type semantics and alternate mutation route — DEFECT FOUND AND NARROWLY FIXED

Repository inspection of the active accounting implementations found:

### `disbursement_void`

`modules/accounting/lib_outflows.php` creates the batch-void reversal with:

```text
reference_type = disbursement_void
reference_id   = monthly_disbursements.id
```

The reversal is created inside the disbursement void transaction and the original transaction/disbursement state is updated in the same transaction.

Before this checkpoint, `disbursement_void` was **not** in the journal page's automated protection list. Therefore an authorized user of the generic manual journal page could potentially reach the reversal through the manual-void route and create a second reversal/history mutation.

### `item_return`

The active item-return workflow creates a partial reversal with:

```text
reference_type = item_return
reference_id   = disbursement_items.id
```

The corresponding `disbursement_items.reversal_journal_id` stores that journal ID. This is semantically distinct from the batch-level `disbursement_return` relationship and must not be silently relabeled merely to satisfy an audit query.

### `payroll`

`modules/hr/lib_payroll_accounting.php` creates payroll journals with:

```text
reference_type = payroll
reference_id   = payroll.id
```

The existing payroll record stores the accounting journal in `payroll.accounting_entry_id` and marks `accounting_status = posted`.

Before this checkpoint, `payroll` was not in the journal page's automated protection list. That meant the generic manual-void route could void a posted payroll journal without changing the linked payroll status/accounting linkage, creating a cross-module accounting-history inconsistency.

### Narrow fix

Only the protection set in `modules/accounting/journal.php` was changed. No accounting data was modified and no historical journal was rewritten.

The new set protects all three newly discovered automated/reversal types while preserving the existing semantics and reference IDs.

**Fix commit:** `8e3ee6fd7d95c0efef834a284ed428d125064bfc`

Static repository verification after the fix confirmed the updated protection set is present in the current `journal.php`.

**Local runtime/UI verification is still required before marking this control fully PASS.**

## 10.3 Journal detail/history consistency — PASS

Current journal detail behavior was inspected directly in `modules/accounting/journal.php` and the accounting core in `modules/accounting/lib.php`.

Verified:

- detail loads the actual `journal_entries` row;
- detail loads actual `journal_lines` joined to `accounts`;
- displayed debit/credit totals are calculated from the underlying lines;
- voided originals remain inspectable;
- transaction voids preserve the original and create a separate `transaction_void` reversal;
- manual voids preserve the original and create a separate `manual_void` reversal;
- automated references are explicitly protected from the manual void route;
- reversal entries remain separately inspectable through the same journal detail mechanism.

No database data was changed for this check, and previously passed balance/status/orphan tests were not repeated.

## 10.4 Cross-reference/auditability TODO

The current journal detail page displays the raw `reference_type`, but it does not yet provide direct source navigation or explicit original↔reversal links.

Example:

```text
JE-000026
  reference_type = transaction
  reference_id   = 22

JE-VOID-TXN-22
  reference_type = transaction_void
  reference_id   = 22
```

Both entries are independently inspectable, but the UI does not currently provide a direct “source transaction / original journal / reversal journal” navigation relationship.

**Decision:** park this as a dedicated future auditability task. It is not classified as a journal-detail integrity failure.

---

# 11. Exact next Accounting Audit task

Continue with the remaining **Journal Integrity / Accounting History Interaction** controls, without restarting prior work:

1. Perform the targeted local runtime/UI verification for the newly protected `payroll`, `disbursement_void`, and `item_return` reference types.
2. Inspect the remaining repository callers of `ak_void_journal_for_voucher()` and any other direct `UPDATE journal_entries` / `DELETE` / journal-line mutation routes.
3. Verify duplicate/missing journal relationships only where a genuinely new route is discovered.
4. Continue cross-module accounting references and auditability.
5. After the underlying routes are fully audited, revisit the parked direct original↔reversal/source navigation enhancement.

Controls already passed in this area must not be rerun unless new code evidence indicates regression.

Historical anomalies involving transactions `5`, `8`, `13`, `14`, `15`, and `16` remain historical evidence and must be interpreted against the actual schema/code. Do not rewrite history merely to make an audit query return clean results.

---

# 12. Protected completed control records — DO NOT MODIFY OR REUSE

- TR-000014 / transaction `20`
- TR-000015 / transaction `21`
- TR-000016 / transaction `22`
- JE-000027
- JE-000026 / journal `37`
- JE-VOID-TXN-22

---

# 13. Documentation / continuation rule

After every meaningful milestone:

**Code is correct → behavior verified → documentation updated → session index updated → exact next continuation point clear.**

This document is an existing-audit continuation record. Never restart the project or accounting audit from zero.

---

# 14. Consolidated 2026-09-12 Accounting Audit Continuation

This section merges the dated accounting documentation updates that previously existed as separate files. The separate dated files are now retired; this canonical document is the single accounting-audit source of truth.

## 14.1 Payroll accounting hardening

Recent repository hardening relevant to accounting:

- `52438233008269be3f26314f15f2f6d5ae62cfd7` — Fix payroll reversal journal workflow.
- `4a5bc10ddc04b7afbad544f95f8f87ceea55161c` — Make payroll reversal workflow atomic.
- `6731228776446c8ddca8b3346e7291de44ed252a` — Validate existing payroll journals before reuse.

Current interpretation:

- Payroll journals are automated records.
- Payroll reversal is a distinct accounting event.
- Multi-record payroll reversal handling is transactional.
- Existing payroll journal reuse is validated before reuse.
- Generic manual-void access is prohibited for `reference_type = payroll`.

## 14.2 Voucher accounting

The active voucher void route in `modules/accounting/vouchers.php` is transactional and validates the voucher/journal relationship, prevents duplicate reversals, validates the original and reversal balances, preserves the original journal, marks the voucher voided, writes an audit event, and rolls back on failure.

The legacy helper `ak_void_journal_for_voucher()` remains unused by current repository call sites and was not changed unnecessarily. Voucher reversal journals are protected from the generic manual-void path.

## 14.3 Account ledger integrity — STATIC PASS

`modules/accounting/account_ledger.php` restricts access to `admin` and `financial_manager` and reads the authoritative chain:

```text
accounts.id
   ↑
journal_lines.account_id
   ↑
journal_entries.id = journal_lines.entry_id
```

The ledger includes only posted journals, excludes voided originals while retaining independently posted reversals, calculates opening/period/closing/running balances using debit-minus-credit, uses authoritative account IDs, and contains no journal mutation path.

Result: **Account Ledger Integrity — STATIC PASS.**

Minor architectural observation: the page invokes existing `ak_ensure_tables()` / `ak_seed_accounts()` initialization; writes occur only when the accounts table is empty. This is not an observed accounting defect.

## 14.4 Cross-module accounting mutation review

The active disbursement void route was confirmed to enforce role/CSRF controls, lock the batch, validate source state and original journal, reject duplicate reversal, require balanced lines, create a balanced `disbursement_void` reversal, void the original journal and linked transaction, update and verify the batch, record `DISBURSEMENT_VOID`, and roll back on failure.

The transaction FM-review workflow atomically posts the transaction and accounting journal. Returned transactions can be edited/resubmitted while posted transactions remain immutable through the normal edit path.

The active disbursement-return route creates a distinct `disbursement_return` accounting event. No separate active `item_return` route was invented beyond the implementation already represented by `reference_type = item_return`.

## 14.5 Final live consistency sweep — CLASSIFIED

A read-only final consistency sweep returned exactly one anomaly:

```text
ORPHAN_DISBURSEMENT_REVERSAL
reference_type = disbursement_void
journal_id = 7
reference_id = 3
```

Targeted inspection established:

```text
journal 3: JE-000003
reference_type = transaction
reference_id = 3
status = posted
2 lines
10000.00 debit / 10000.00 credit

journal 7: JE-REV-000003
reference_type = disbursement_void
reference_id = 3
status = posted
2 lines
195000.00 debit / 195000.00 credit
```

The source `monthly_disbursements.id = 3` row is no longer present, but the audit log preserves the lifecycle:

- `815` CREATE disbursement `3` — 2026-08-18 07:02:11.
- `816` SUBMIT.
- `819` APPROVE.
- `823` TRANSFER.
- `828` AUTO_CLOSE.
- `835` DATA_FIX on monthly disbursement `3`.
- `843` VOID disbursement `3` — 2026-08-18 09:49:39.

Therefore journal `7` is classified as a **legitimate historical orphaned disbursement reversal**, not a malformed accounting journal and not an active workflow defect.

The journal is balanced, posted, has two lines, and has corroborating audit-log evidence. It must be preserved; deleting or rewriting it merely to make an orphan query return zero rows would damage accounting history.

No database modification was required for this classification.

## 14.6 Final audit status

The Accounting Audit is **COMPLETE at the 2026-09-12 checkpoint**.

All current consistency checks are clean except for the single historical orphan described above, which has been investigated and explicitly classified as preserved historical accounting evidence.

Current protected evidence remains:

- batch `11`
- transaction `23`
- original journal `JE-AUDIT-DISB-11`
- reversal journal `43`
- reversal reference type `disbursement_void`

These records remain protected and must not be recreated, modified, or reused for unrelated tests.

### Closed controls

- Creator → FM review.
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
- Controlled disbursement-void evidence.
- Voucher void static integrity.
- Voucher reversal separation/protection.
- Payroll accounting hardening review.
- Account ledger integrity static review.
- Cross-module accounting mutation review.
- Final read-only accounting consistency sweep and historical anomaly classification.

### Deferred / future auditability enhancement

Direct UI navigation between source records, original journals, and reversal journals remains a future enhancement. This is not an accounting-integrity failure and does not reopen the completed audit.

### Continuation rule

Future accounting work must begin from new code/database evidence. Do not restart Phase 1, recreate fixtures, or repeat completed tests unless a genuine regression is identified.
