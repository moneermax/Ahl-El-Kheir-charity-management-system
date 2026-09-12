# Ahl El Kheir Charity Management System — System Analysis Update

**Date:** 2026-09-12  
**Parent document:** `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`  
**Repository HEAD reviewed:** `6731228776446c8ddca8b3346e7291de44ed252a`  
**Document type:** Living system-analysis addendum  
**Status:** Current implementation delta

> This document is an update/addendum to the existing system analysis. It does not replace the original analysis and does not restart the project.

---

# 1. Accounting architecture — current implementation clarification

The accounting subsystem is now documented as a controlled journal architecture rather than a generic CRUD ledger.

The authoritative accounting relationship is:

```text
journal_entries
      │
      └── journal_lines.entry_id
              │
              └── accounts.id
```

A journal header represents the accounting event. Journal lines represent its debit/credit effect. Account identity is resolved through the `accounts` table.

Financial history must preserve the original event and create a separate reversal/void event when a posted event is reversed.

---

# 2. Automated journal reference model

The current system contains several automated/reversal journal reference types. They are not ordinary manual journal entries and must not be exposed to a generic manual-void mutation path.

Current protected reference types include:

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

The distinction is architectural:

- `transaction` represents an approved/postable financial transaction.
- `transaction_void` represents the accounting reversal of a transaction.
- `disbursement` represents the accounting effect of a disbursement.
- `disbursement_return` represents a batch/group return event.
- `disbursement_void` represents a disbursement void/reversal event.
- `item_return` represents a partial item-level return/reversal.
- `voucher` represents a voucher-linked accounting event.
- `payroll` represents a payroll-linked accounting event.
- `manual_void` represents a controlled reversal of a manual journal.

The reference type therefore carries business meaning and must be preserved rather than normalized away for convenience.

---

# 3. Financial history and reversal architecture

The current implementation follows this principle for posted financial events:

```text
POSTED SOURCE EVENT
      │
      ├── original journal preserved
      │
      ├── source record status updated coherently
      │
      └── separate balanced reversal journal created
```

The original journal is not deleted merely because the business event has been voided or reversed.

This is essential for auditability, reconciliation, and historical reporting.

Where a reversal is created, the reversal must itself be a valid balanced journal and must be independently inspectable.

---

# 4. Voucher accounting clarification

The current active voucher void workflow is transactional and validates the relationship between the voucher and its original journal before creating a reversal.

The implemented behavior includes:

- voucher/journal locking;
- reference-type and reference-ID validation;
- duplicate reversal prevention;
- original journal balance validation;
- separate `voucher_void` reversal;
- debit/credit reversal of original lines;
- reversal balance verification;
- original journal preservation;
- voucher status update;
- audit-log recording;
- rollback on failure.

A legacy helper remains in the accounting library but has no current repository caller. The active voucher workflow must be treated as authoritative.

---

# 5. Payroll accounting clarification

Payroll accounting is an automated cross-module workflow.

The payroll record stores its accounting relationship through `accounting_entry_id` and uses `accounting_status` to reflect accounting state.

Payroll journals use:

```text
reference_type = payroll
reference_id   = payroll.id
```

Recent hardening has made payroll reversal operations atomic and validates existing payroll journals before reuse.

This means payroll accounting must be analyzed as a coordinated HR + accounting workflow rather than as an isolated journal-entry screen.

---

# 6. Disbursement accounting clarification

Disbursement accounting contains both batch-level and item-level financial relationships.

Batch voids use:

```text
reference_type = disbursement_void
reference_id   = monthly_disbursements.id
```

Partial item returns use:

```text
reference_type = item_return
reference_id   = disbursement_items.id
```

The item-level reversal journal is linked through `disbursement_items.reversal_journal_id`.

These relationships are intentionally distinct. Code must not merge them into one generic return type merely to simplify reporting.

---

# 7. Security and authorization clarification

The system analysis now explicitly treats UI-level journal controls and server-side journal mutation controls as separate layers.

The correct model is:

```text
UI protection
    +
Server-side authorization/validation
    +
Source-module state validation
    +
Database transaction/rollback
```

Hiding a Void button is therefore not sufficient. The backend must independently reject an automated journal presented to a generic manual-void endpoint.

---

# 8. Audit and development methodology update

For accounting work, the project now follows an evidence-preserving audit sequence:

```text
Inspect current repository
→ identify actual accounting routes
→ distinguish active vs legacy/dead helpers
→ patch only genuine alternate mutation paths
→ verify behavior
→ preserve existing evidence
→ document exact checkpoint
→ continue from the remaining control
```

Completed audit fixtures are protected evidence and must not be recreated or reused for unrelated tests.

Historical anomalies must be interpreted against the actual schema and code. They must not be rewritten simply to make an audit query appear clean.

---

# 9. Current system-analysis continuation point

The next technical analysis target is the account ledger layer and its relationship with the journal architecture.

Specifically, verify:

1. Ledger queries join `journal_entries`, `journal_lines`, and `accounts` correctly.
2. Only the intended journal statuses are included.
3. Account filtering is safe and uses authoritative account identifiers.
4. Debit, credit, and running balance calculations are mathematically correct.
5. Voided originals and posted reversals are represented consistently.
6. The ledger is read-only from the user's perspective and has no hidden mutation path.
7. No alternate direct journal mutation route can bypass the accounting controls already established.

This analysis continues from the existing accounting audit; it is not a new ledger project.

---

# 10. Documentation authority

The existing `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` remains the primary system-analysis document.

This file records only the 2026-09-12 implementation changes and architectural clarifications. Future updates should either revise the primary analysis when practical or add another dated addendum rather than silently contradicting the historical baseline.
