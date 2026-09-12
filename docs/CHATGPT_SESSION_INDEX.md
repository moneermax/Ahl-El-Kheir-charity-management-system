# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `https://github.com/moneermax/Ahl-El-Kheir-charity-management-system`  
**Local project:** `D:\xampp\htdocs\AhlElKheir`  
**Local URL:** `http://localhost:8081/AhlElKheir/`  
**Database:** `ahl_el_kheir`  
**Last maintained:** 2026-09-12

---

# CURRENT AUTHORITATIVE CHECKPOINT — 2026-09-12

This is an **existing project and existing audit continuation**.

**Do not restart the project. Do not restart completed audits. Do not repeat passed tests. Do not recreate fixtures unless genuine regression evidence requires it. Preserve completed audit evidence.**

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

Do not reopen HR work unless a current cross-module audit produces concrete regression evidence or a dependency requiring investigation.

---

# ACCOUNTING AUDIT STATUS

**Accounting Audit: COMPLETE through 2026-09-12.**

Completed controls include creator→FM review, FM return/resubmission, approval→posted balanced journal, cancellation/void/reversal integrity, manual journal integrity, Trial Balance, Accountant Staff authorization, disbursement authorization and receipt/closure, journal listing/detail/history, posted/voided preservation, accounting-history consistency, transaction↔journal controls, duplicate/orphan checks, cross-module mutation review, `disbursement_return` protection, and legacy `void_batch` protection.

Do not rerun completed accounting tests or recreate protected fixtures unless genuine regression evidence requires it.

Actual core accounting tables:

- `accounts`
- `journal_entries`
- `journal_lines`

Account display field: `accounts.name_ar`.

Do not assume a nonexistent `accounting_entries` table.

The local `audit_log` schema uses `action`, not `action_type`.

---

# PROTECTED ACCOUNTING EVIDENCE

Historical accounting anomalies and protected test records remain evidence and must not be modified merely to make audit queries look clean.

The detailed accounting evidence remains authoritative in:

`docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`

---

# LEGACY DISBURSEMENT VOID TEST — PASS / CLOSED

The legacy `void_batch` path was hardened with:

`modules/accounting/disbursement_void_guard.php`

Guard correction commit:

`0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

The controlled existing fixture was preserved. The resulting batch, transaction, original journal, reversal journal, balanced amounts and audit entry were verified successfully.

**Audit Test: PASS. CLOSED. Do not rerun.**

---

# AUTOMATED JOURNAL REFERENCE PROTECTION

The generic manual-void protection includes automated/cross-module references including:

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

The detailed current status remains in the accounting documentation. Runtime verification should be performed only if it remains explicitly listed as the next accounting audit task.

---

# VOUCHER ATOMICITY

Voucher void was hardened with atomic locking/transaction handling.

Commit:

`2165513c9009d1fa435fdd122d545bf4d2404b07`

Further work is limited to caller/mutation-route inspection when the accounting audit is reopened for that parked item.

---

# RECEIPT HANDLING

Server-side receipt path validation is hardened.

Deferred UI TODO remains the same-page Bootstrap modal for missing/stale receipt feedback, while preserving server-side authorization/path validation.

---

# NOTIFICATION AUDIT STATUS — COMPLETE SOURCE SWEEP / CHECKPOINT 2026-09-12

The existing **System-Wide Notification Integrity Audit** has been continued without restarting the project or repeating completed notification/accounting tests.

Completed notification controls include:

1. Notification UI/read-state integration.
2. Individual notification mark-read through POST+CSRF.
3. Mark-all-read through POST+CSRF.
4. Legacy GET notification mutation neutralization.
5. Safe application-relative/base-path redirect normalization.
6. Password-recovery approval/rejection notifications.
7. Password-recovery GET-mutation removal.
8. HR leave notification active-user filtering and delivery isolation.
9. Supervisor sponsor-payment notifications and resubmission notifications.
10. Accounting Financial Manager review notifications.
11. Transaction sponsor-payment notifications.
12. Transaction cancellation notifications.
13. All seven audited disbursement notification transitions.
14. Notification recipient scoping and actor exclusion.
15. Event/reference-aware notification delivery and deduplication.
16. Legacy relative-link compatibility.
17. No database-trigger notification architecture in the audited workflows.
18. Source-level sweep of likely legacy/business notification writers and notification-producing paths.

The seven disbursement notification transitions remain documented in:

`docs/AHL_EL_KHEIR_NOTIFICATION_AUDIT.md`

The source-level sweep did not establish a new active direct notification producer or bypass. The only remaining architecture finding is the legacy `config/messaging.php::send_system_notification()` helper. It accepts type/reference arguments but its implementation does not fully persist them. Reliable active caller evidence was not available, so it was intentionally **not modified speculatively**.

The notification source sweep is therefore **closed at the current evidence boundary**. Do not claim the legacy helper is definitively unused; revisit it only if reliable caller evidence appears.

No user-side test is required from this documentation-only closure.

---

# CURRENT AUDIT DIRECTION

The Notification Audit source-level sweep is complete at the current repository-evidence boundary.

The next audit area should proceed directly from the project's existing audit sequence. Do not restart Notification Audit work unless a new notification regression or reliable legacy-helper caller evidence appears.

Before starting the next area, use the existing audit documents as the authoritative record rather than recreating fixtures or rerunning completed checks.

---

# PARKED TODO LATER

1. Direct original ↔ reversal/source navigation in journal detail.
2. Missing/stale receipt same-page modal UX.
3. Broader accounting auditability enhancements after underlying mutation routes are fully audited.
4. Formal organization-wide permission/action matrix.
5. Formal report/source/calculation catalog.
6. Final notification event/recipient catalog where not already captured by the dedicated Notification Audit.

These are parked items, not reasons to reopen completed audit areas without evidence.

---

# AUTHORITATIVE DOCUMENTATION FOR CONTINUATION

Read/use these together as applicable:

1. `docs/AHL_EL_KHEIR_NOTIFICATION_AUDIT.md`
2. `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`
3. `docs/AHL_EL_KHEIR_CURRENT_SYSTEM_STATUS_2026-09-08.md`
4. `docs/AHL_EL_KHEIR_COMPLETE_REPOSITORY_AUDIT.md`
5. `docs/AHL_EL_KHEIR_REPOSITORY_AUDIT.md`
6. `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`
7. `docs/CHATGPT_SESSION_INDEX.md`

Historical audit documents remain historical and must not override the current checkpoint.

---

# IMPORTANT CONTINUATION RULES

- Existing project + existing audits — never restart.
- Do not ask for information already present in the repository/documentation.
- Do not repeat old SQL verification.
- Do not recreate known fixtures merely to rerun passed tests.
- Never guess table/column names; inspect actual schema if SQL is required.
- Keep SQL to the minimum necessary.
- Prefer safe functional browser testing.
- If code modification is needed, make it narrow and verify the resulting repository file.
- Preserve procedural PHP, Vanilla JS, Bootstrap 5.3 RTL, Font Awesome 6, Cairo, MariaDB/MySQL/PDO.
- Historical test records are evidence; do not modify them to make queries cleaner.
