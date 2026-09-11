# Ahl El Kheir Charity Management System — Documentation Update

**Date:** 2026-09-11  
**Area:** Accounting Audit — Journal Integrity / Accounting History Interaction  
**Status:** Current milestone checkpoint updated; disbursement void protection test PASS; targeted automated-reference runtime verification remains pending

---

## 1. Previous disbursement authorization milestone — PASSED

The Accountant Staff disbursement authorization audit was completed against `modules/accounting/disbursements.php`.

Established workflow:

```text
Vice General Manager creates the disbursement batch
→ assigns the batch/group to an accountant staff user
→ Accountant Staff manages only batches belonging to an assigned nanny
```

Assigned visibility, restricted financial actions, reopen controls, batch void and existing receipt/closure workflow all passed. Accountant Staff is not a batch-creation role in the established workflow.

Do not repeat these tests unless new code evidence creates a regression.

---

## 2. Receipt handling hardening — COMPLETED

`modules/accounting/serve_receipt.php` validates stored receipt paths with `realpath()` and `is_file()`, confirms the resolved file remains inside the application base directory, and shows an application-level Arabic message for missing/stale receipts. It does not substitute an individual family receipt for a missing final batch receipt.

Relevant commits:

- `e6b6fd1f15f63a48c6224da3635efddc95ac38f2`
- `65625215686027ce172425c611e40c1d039de35c`
- `8cbf181071fc27de59abc26e1f4d4f0bf8f71a9e`

### Deferred receipt-preview UI TODO

The requested final UX remains:

> Show missing/stale receipt feedback as a same-page Bootstrap modal on `modules/accounting/disbursements.php` instead of navigating to a separate receipt page/window.

This is a UI TODO only. It does not invalidate the completed authorization/security controls.

---

## 3. Journal listing/filtering — PASSED

Current `modules/accounting/journal.php` was inspected and the journal listing/filtering control was completed.

The earlier protection set included:

```text
transaction
transaction_void
disbursement
disbursement_return
voucher
manual_void
```

The `disbursement_return` omission was corrected by commit `c37d72b3b0bff2497aab525f6944e05c58cb5fd7`.

---

## 4. Journal detail/history consistency — PASSED

Current `modules/accounting/journal.php` and `modules/accounting/lib.php` were inspected directly.

Verified:

1. Journal detail loads the actual `journal_entries` record.
2. Journal lines are loaded from `journal_lines` and resolved through `accounts`.
3. Displayed debit/credit totals are calculated from the actual displayed lines.
4. Voided originals remain inspectable.
5. Transaction voiding preserves the original journal and creates a separate `transaction_void` reversal journal.
6. Manual voiding preserves the original journal and creates a separate `manual_void` reversal journal.
7. Automated reference types cannot be voided through the manual-journal route.
8. Original and reversal journals remain independently inspectable through the journal detail route.

No accounting data was changed for this inspection, and previously completed balance/status/orphan tests were not repeated.

**Result: PASS.**

---

## 5. New reference-type / alternate-mutation finding — FIXED

Repository inspection of the active accounting paths found three current journal reference types that were not protected by the generic manual-void route:

```text
disbursement_void
item_return
payroll
```

### `disbursement_void`

`modules/accounting/lib_outflows.php` creates the batch-void reversal using:

```text
reference_type = disbursement_void
reference_id   = monthly_disbursements.id
```

Because this reversal is itself an automated accounting history record, allowing the generic manual-void page to void it would create an alternate mutation path.

### `item_return`

The active partial item-return flow creates:

```text
reference_type = item_return
reference_id   = disbursement_items.id
```

The journal ID is stored in `disbursement_items.reversal_journal_id`. This is intentionally distinct from the batch-level `disbursement_return` relationship and was not renamed merely to satisfy the older audit naming convention.

### `payroll`

`modules/hr/lib_payroll_accounting.php` creates payroll journals using:

```text
reference_type = payroll
reference_id   = payroll.id
```

The payroll row stores the journal in `accounting_entry_id` and marks `accounting_status = posted`. Before this fix, the generic journal void route could void the payroll journal without changing the linked payroll status, creating a cross-module history mismatch.

### Narrow fix

Only `modules/accounting/journal.php` was changed. The protected reference set is now:

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

No accounting records or historical evidence were modified.

**Fix commit:** `8e3ee6fd7d95c0efef834a284ed428d125064bfc`

Static repository verification confirmed the updated protection set is present in the current branch.

**Local runtime/UI verification for these three reference types is still pending.**

---

## 6. Legacy disbursement void protection — PASS

A separate audit of the legacy `void_batch` route in `modules/accounting/disbursements.php` identified an unsafe direct-mutation path for posted disbursement accounting state.

A narrow guard was implemented in:

`modules/accounting/disbursement_void_guard.php`

The guard intercepts the legacy `void_batch` POST route and performs the accounting operation atomically:

```text
posted disbursement
→ separate balanced disbursement_void reversal
→ original journal preserved + voided
→ linked transaction voided
→ monthly disbursement voided
→ audit_log recorded
```

The local `audit_log` schema was explicitly verified before correction. It uses `action`, not `action_type`.

The corrected guard was committed as:

`0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

### Controlled fixture

Existing controlled fixture used without recreation:

- monthly disbursement ID `11`
- transaction ID `23`
- transaction reference `AUDIT-DISB-VOID-11`
- original journal `JE-AUDIT-DISB-11`
- original amount `100.00`

### Browser execution — PASS

The application returned:

> تم إبطال الدفعة وإنشاء القيد العكسي مع الحفاظ على القيد الأصلي وسجل التدقيق.

### Final database verification — PASS

Verified result:

- batch `11` → `voided`
- reversal journal ID `43`
- transaction `23` → `voided`
- original journal `JE-AUDIT-DISB-11` → `voided`
- reversal journal `JE-REV-DISB-000011-20260911183412`
- reversal reference type `disbursement_void`
- reversal status `posted`
- reversal debit `100.00`
- reversal credit `100.00`
- reversal balanced
- `DISBURSEMENT_VOID` audit entries: `1`

**Audit Test: PASS.**

Do not rerun or recreate this fixture unless genuine regression evidence appears.

Detailed milestone record:

`docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11_DISBURSEMENT_VOID.md`

---

## 7. Cross-reference/auditability gap — TODO LATER

The journal detail page currently exposes the raw `reference_type`, but does not provide direct source navigation or explicit original↔reversal navigation.

For example:

```text
JE-000026
  transaction / 22

JE-VOID-TXN-22
  transaction_void / 22
```

Both records are inspectable, but an accountant must currently interpret the relationship manually rather than follow a direct UI link.

This is **not classified as a journal-detail integrity failure**. It remains a dedicated future cross-module accounting-auditability task.

---

## 8. Current Accounting Audit state

The accounting audit remains an existing continuation, not a new audit.

Completed controls include:

- creator → FM review workflow;
- FM return/resubmission;
- FM approval → posted + balanced journal;
- returned transaction cancellation with no journal;
- authorized transaction void + reversal;
- manual journal integrity;
- Trial Balance integrity;
- Accountant Staff financial/report authorization;
- Accountant Staff disbursement authorization;
- receipt/closure workflow;
- journal listing/filtering;
- journal detail/history consistency;
- posted/voided visibility;
- preservation of original entries;
- accounting-history balance consistency;
- current transaction-status/journal-status control verification;
- duplicate/orphan relationship audit;
- legacy disbursement `void_batch` protection and accounting reversal.

The three newly protected automated reference types (`payroll`, `disbursement_void`, `item_return`) remain **code-fixed but runtime verification pending**.

Known historical accounting anomalies remain documented and must not be destructively corrected merely to satisfy an audit query. In particular, observations involving transactions `5`, `8`, `13`, `14`, `15`, and `16` require schema/code interpretation.

Protected completed evidence remains:

- TR-000014 / transaction `20`
- TR-000015 / transaction `21`
- TR-000016 / transaction `22`
- JE-000027
- JE-000026 / journal `37`
- JE-VOID-TXN-22

---

## 9. Exact next Accounting Audit task

Continue from this exact checkpoint:

1. Perform targeted runtime/UI verification that the generic manual-void route does not expose/allow voiding for:
   - `payroll`
   - `disbursement_void`
   - `item_return`
2. Inspect the remaining callers of `ak_void_journal_for_voucher()` and any direct journal mutation routes.
3. Continue duplicate/missing relationship checks only where a genuinely new route is discovered.
4. Continue cross-module accounting references and auditability.
5. Only after the underlying routes are fully audited, revisit the parked original↔reversal/source navigation enhancement.

Do not rerun the already-passed accounting tests.

---

## 10. Continuation rule

The next session must read/use together:

- `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`
- `docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11.md`
- `docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11_DISBURSEMENT_VOID.md`
- `docs/CHATGPT_SESSION_INDEX.md`

Treat the disbursement void test as **closed/PASS**. The next session begins with the remaining automated-reference manual-void protection verification.
