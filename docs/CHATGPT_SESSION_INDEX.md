# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `https://github.com/moneermax/Ahl-El-Kheir-charity-management-system`  
**Local project:** `D:\xampp\htdocs\AhlElKheir`  
**Local URL:** `http://localhost:8081/AhlElKheir/`  
**Database:** `ahl_el_kheir`  
**Last maintained:** 2026-09-11

---

# CURRENT AUTHORITATIVE CHECKPOINT — 2026-09-11

This is an **existing project and existing Accounting Audit continuation**.

**Do not restart the project. Do not restart the audit. Do not repeat passed tests. Do not recreate fixtures unless genuine regression evidence requires it. Preserve completed audit evidence.**

Follow:

**Inspect → Understand → Verify → Identify risk → Fix narrowly → Test → Document**

---

# PROJECT / ARCHITECTURE

- Windows + XAMPP + Apache
- PHP 8.2+
- MariaDB/MySQL
- Procedural PHP only — **NO OOP**
- Vanilla JavaScript only
- Bootstrap 5.3 RTL
- Font Awesome 6
- Google Fonts Cairo

The current repository and actual database schema are authoritative. Never guess table or column names.

---

# HR STATUS

**HR foundation/audit: COMPLETE AND CLOSED.**

Do not reopen HR work unless a current Accounting/cross-module audit produces concrete regression evidence or a dependency requiring investigation.

---

# ACCOUNTING AUDIT STATUS

We are continuing the existing:

**Accounting Audit → Journal Integrity / Accounting History Interaction**

The following are already passed and must not be rerun without regression evidence:

1. Creator → FM review workflow.
2. No journal before FM approval.
3. FM return with mandatory reason.
4. Creator edit/resubmit.
5. FM approval → posted + balanced journal.
6. Returned transaction → creator cancellation without journal.
7. Posted transaction → authorized void + balanced `transaction_void` reversal.
8. Manual Journal Entry Integrity.
9. Trial Balance Integrity.
10. Accountant Staff financial/reporting authorization.
11. Accountant Staff disbursement authorization.
12. Existing disbursement receipt/closure workflow.
13. Journal listing/filtering.
14. Journal detail/history consistency.
15. Posted/voided visibility and preservation of originals.
16. Accounting-history totals/balance consistency.
17. Current transaction-status ↔ journal-status control.
18. Duplicate/orphan/mismatched relationship audit.
19. `disbursement_return` alternate-route protection.
20. Legacy disbursement `void_batch` protection — **PASS in this checkpoint**.

---

# PROTECTED COMPLETED EVIDENCE — DO NOT MODIFY OR REUSE

- TR-000014 / transaction `20`
- TR-000015 / transaction `21`
- TR-000016 / transaction `22`
- JE-000027
- JE-000026 / journal `37`
- JE-VOID-TXN-22

Historical anomalies involving transactions `5`, `8`, `13`, `14`, `15`, and `16` are preserved findings. Do not modify them merely to make an audit query look clean.

---

# ACCOUNTING SCHEMA FACTS

Actual core accounting tables:

- `accounts`
- `journal_entries`
- `journal_lines`

Account display field: `accounts.name_ar`.

Do not assume a nonexistent `accounting_entries` table.

Transaction ↔ journal linkage is application-level through `journal_entries.reference_type` / `reference_id`; there is no assumed direct `transactions.journal_entry_id` FK.

Disbursement-item relationship uses `disbursement_items.disbursement_id`.

The local `audit_log` schema uses:

```text
action
```

not `action_type`.

---

# ACCOUNTANT STAFF DISBURSEMENT MODEL

Known test users/data:

- `acc1` / user `17` / `accountant_staff`
- `nany1` / user `16`
- assignment `1`: accountant `17` → nanny `16`
- group `1`: `مجموعة الحاضنة 1`

Established workflow:

```text
Vice General Manager creates batch
→ assigns batch/group
→ Accountant Staff manages only assigned nanny/batch scope
```

Accountant Staff is not a batch-creation role in the tested workflow.

Do not touch protected batch `6` as a fixture; it is evidence from the earlier disbursement-return audit.

---

# LEGACY DISBURSEMENT VOID TEST — PASS

A legacy `void_batch` mutation path in `modules/accounting/disbursements.php` was hardened with:

`modules/accounting/disbursement_void_guard.php`

Guard correction commit:

`0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

Controlled fixture already existed and was not recreated:

- batch `11`
- transaction `23`
- reference `AUDIT-DISB-VOID-11`
- original journal `JE-AUDIT-DISB-11`
- amount `100.00`

Browser result:

> تم إبطال الدفعة وإنشاء القيد العكسي مع الحفاظ على القيد الأصلي وسجل التدقيق.

Final database verification:

- batch `11` → `voided`
- reversal journal ID `43`
- transaction `23` → `voided`
- original journal `JE-AUDIT-DISB-11` → `voided`
- reversal journal `JE-REV-DISB-000011-20260911183412`
- reversal `reference_type = disbursement_void`
- reversal status `posted`
- reversal debit = credit = `100.00`
- `DISBURSEMENT_VOID` audit entries = `1`

**Audit Test: PASS. CLOSED. Do not rerun.**

Detailed record:

`docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11_DISBURSEMENT_VOID.md`

---

# AUTOMATED JOURNAL REFERENCE PROTECTION — CODE FIXED, RUNTIME TEST PENDING

Repository inspection found these automated reference types were not previously protected from the generic manual-void route:

- `disbursement_void`
- `item_return`
- `payroll`

Current protection set in `modules/accounting/journal.php`:

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

Fix commit:

`8e3ee6fd7d95c0efef834a284ed428d125064bfc`

**Important:** the code fix is present, but targeted local runtime/UI verification for these three reference types has NOT yet been completed.

---

# VOUCHER ATOMICITY

Voucher void was hardened with atomic locking/transaction handling.

Commit:

`2165513c9009d1fa435fdd122d545bf4d2404b07`

The remaining task is to inspect callers of the legacy helper `ak_void_journal_for_voucher()` and other direct journal mutation routes before deciding whether further hardening is necessary.

---

# TRIAL BALANCE — PASSED

Verified earlier:

- Debit: `103,373,000.00`
- Credit: `103,373,000.00`
- Difference: `0.00`
- Posted journals: `24`
- Posted lines: `54`

Later global accounting integrity check:

- Journals: `28`
- Lines: `62`
- Debit: `204,945,000.00`
- Credit: `204,945,000.00`

Do not rerun these checks unless regression evidence requires it.

---

# RECEIPT HANDLING

Server-side receipt path validation is hardened.

Deferred UI TODO:

- same-page Bootstrap modal for missing/stale receipt feedback;
- preferably valid receipt preview;
- preserve existing server-side authorization/path validation.

Do not reopen this until the accounting audit reaches the parked UI work.

---

# PARKED TODO LATER

1. Direct original ↔ reversal/source navigation in journal detail.
2. Missing/stale receipt same-page modal UX.
3. Broader accounting auditability enhancements after underlying mutation routes are fully audited.

These are deliberately parked and are not blockers for the current audit sequence.

---

# EXACT NEXT TASK — NEXT CHAT SESSION

Continue directly from this checkpoint.

### NEXT TEST / ACTION

**Targeted runtime/UI verification of the generic manual-void protection for automated journal reference types:**

1. `payroll`
2. `disbursement_void`
3. `item_return`

Use existing records where possible. **Do not create artificial fixtures unless genuinely necessary.**

The expected security behavior is that the generic manual-void route must not expose/allow manual voiding of these automated/cross-module journal entries.

After that:

4. Inspect remaining callers of `ak_void_journal_for_voucher()`.
5. Inspect other direct journal mutation routes (`UPDATE journal_entries`, journal-line mutation, destructive deletes) only where they represent real alternate accounting mutation paths.
6. Continue cross-module journal relationship/auditability review.
7. Preserve all historical anomalies and protected evidence.

Do not rerun completed tests.

---

# AUTHORITATIVE DOCUMENTATION FOR CONTINUATION

Read/use these together:

1. `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`
2. `docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11.md`
3. `docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-11_DISBURSEMENT_VOID.md`
4. `docs/CHATGPT_SESSION_INDEX.md`
5. `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`
6. `docs/AHL_EL_KHEIR_CURRENT_SYSTEM_STATUS_2026-09-08.md`

Historical audit documents remain historical and must not override this current checkpoint.

---

# IMPORTANT CONTINUATION RULES

- Existing project + existing Accounting Audit — never restart.
- Do not ask for information already present in the repository/documentation.
- Do not repeat old SQL verification.
- Do not recreate known fixtures merely to rerun passed tests.
- Never guess table/column names; inspect actual schema if SQL is required.
- Keep SQL to the minimum necessary.
- Prefer safe functional browser testing.
- If code modification is needed, make it narrow and verify the resulting repository file.
- Preserve procedural PHP, Vanilla JS, Bootstrap 5.3 RTL, Font Awesome 6, Cairo, MariaDB/MySQL/PDO.
- Historical test records are evidence; do not modify them to make queries cleaner.
