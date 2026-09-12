# Ahl El Kheir Charity Management System
## Current System Status & Cross-Module Documentation Revalidation — Consolidated through 2026-09-12

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`
**Authoritative branch:** `main`
**Original revalidation date:** 2026-09-08
**Consolidated through:** 2026-09-12
**Purpose:** Provide the current repository-grounded status of the complete system and consolidate the chronological system-update records that previously existed as separate files.

> This is the canonical current-state document. Historical audit evidence remains in the dedicated audit documents. This document incorporates verified updates from 2026-09-02 through 2026-09-12 and records the current continuation state. It does not erase historical findings.

---

# 1. Repository Revalidation

The current repository contains the following business/module areas:

- Authentication / sessions
- Users, roles and organizational administration
- Departments
- Families and beneficiary records
- Sponsors
- Sponsorships
- Supervisors and nannies
- Group and monthly sponsorship operations
- Accounting
- Transactions / donations and payment records
- Monthly disbursements and receipts
- Projects
- HR
- Internal messaging and attachments
- Notifications
- Search
- Reports
- Settings
- System administration, database/backup/health utilities
- Audit/system logs

The repository also contains shared configuration, language resources, storage controls, PDF generation through TCPDF, and the authoritative database artifact under `database/ahl_el_kheir.sql`.

The current authoritative rule for future work remains:

`Inspect main → diagnose → make the smallest justified change → test → review → commit`

Older development branches are historical context unless deliberately reused. Current repository code and the authoritative database artifact remain the source of truth.

---

# 2. Current Architecture

**VERIFIED**

- Procedural PHP application code.
- Vanilla JavaScript.
- Bootstrap 5.3 RTL presentation.
- MariaDB/MySQL through PDO.
- Prepared SQL statements through shared database helpers.
- Server-side authentication and authorization throughout protected modules.
- CSRF protection on important state-changing operations.
- Sensitive storage protected through controlled serving endpoints where appropriate.

The architecture remains procedural PHP + Vanilla JS unless an explicit future architectural decision changes that constraint.

---

# 3. Authentication, Users, Roles and Departments

### Authentication / sessions — VERIFIED

The application has login/logout, authenticated sessions, role resolution, password hashing/verification, last-login tracking and audit logging for login.

### User administration — VERIFIED / IMPLEMENTED-PARTIAL

`modules/users/index.php` manages users, roles, departments, managers, activation state, password operations, gender and accountant-to-nanny assignments.

### Departments — VERIFIED / IMPLEMENTED-PARTIAL

`modules/departments/index.php` and `sync.php` provide department administration/synchronization. Department relationships are also used by user organizational data.

### Permission governance — IMPLEMENTED-PARTIAL

Role checks exist at page/action level and module-specific policy helpers exist, but a single organization-wide action matrix remains a governance/documentation task.

---

# 4. Families and Beneficiaries

**VERIFIED / IMPLEMENTED-PARTIAL**

The family domain provides family creation/edit/view, child/orphan records, documents, orphan forms/profiles, suspensions and monthly verification.

The authoritative conceptual relationship remains:

`Family → Family Child → Sponsorship`

Child-level sponsorship is intentional and allows siblings to have different sponsors.

Family verification participates in the monthly sponsorship/disbursement workflow. Protected document storage must not be weakened for convenience.

---

# 5. Sponsors and Sponsorships

**VERIFIED / IMPLEMENTED-PARTIAL**

Sponsor records are distinct from sponsorship records. The implemented relationship is:

`Sponsor → Sponsorship → Child → Family`

Observed sponsorship states include `active`, `paused`, `completed`, and `cancelled`.

The matching workflow supports prioritized matching, deterministic ordering, supervisor scope and applicable letter/gender restrictions. A legacy `sponsorship_children` concept remains unverified and must not become authoritative merely because an old configuration constant exists.

Remaining documentation work includes final sponsor lifecycle/status and organization-wide scope matrices.

---

# 6. Supervisors, Nannies and Groups

**VERIFIED / IMPLEMENTED-PARTIAL**

Supervisor scope is used by sponsorship matching and operational access rules. Nanny/group relationships are used by monthly group/disbursement processing. Accountant-to-nanny assignments are protected for uniqueness.

The organization-wide record-scope matrix for supervisors, accountant staff and nannies remains an open governance item.

---

# 7. Monthly Verification and Disbursement

**VERIFIED / IMPLEMENTED-PARTIAL**

Current business chain:

`Family Verification → Group Submission → Accountant Review/Batch Creation → Transfer → Nanny Confirmation → Return/Reversal → Reconciliation/Closure`

The repository contains group verification, batch creation, disbursement items, transfer, nanny confirmation, receipts, returned amounts, reversal journals and closure behavior.

Observed item statuses include `pending`, `paid` and `returned`.

The return workflow calculates outstanding amounts, records return evidence, creates the reversal journal and returns the financial effect to the cash/safe side of the workflow.

A known technical-debt point remains: older group-workflow functions and the current disbursement page contain overlapping logging/execution patterns. Consolidation should occur only after caller-by-caller verification.

---

# 8. Accounting

**VERIFIED / IMPLEMENTED-PARTIAL → AUDIT PHASE COMPLETED 2026-09-12**

The accounting module contains chart of accounts, journal listing/creation, opening balances, vouchers, receipt handling, financial-manager review, disbursement integration, reconciliation views and accounting reports.

Principal accounting relationship:

`journal_entries → journal_lines → accounts`

Actual accounting tables confirmed during the September audit are:

- `accounts`
- `journal_entries`
- `journal_lines`

Account display name is `accounts.name_ar`. There is no assumed `accounting_entries` table.

## 8.1 Accounting audit result — COMPLETE

The existing Accounting Audit was continued through 2026-09-12 without restarting prior work or recreating protected fixtures.

Completed controls include:

- creator → Financial Manager review;
- FM return/resubmission;
- FM approval → posted + balanced journal;
- returned transaction cancellation with no journal;
- authorized transaction void + balanced `transaction_void` reversal;
- manual journal integrity;
- Trial Balance integrity;
- Accountant Staff financial/reporting authorization;
- Accountant Staff assignment-scoped disbursement authorization;
- receipt/closure workflow;
- journal listing/filtering;
- journal detail/history consistency;
- posted/voided visibility;
- preservation of original entries after reversal;
- accounting-history balance consistency;
- transaction-status/journal-status interaction checks;
- duplicate/missing relationship checks;
- cross-module accounting mutation review;
- legacy disbursement `void_batch` protection and atomic accounting reversal.

The canonical detailed evidence is now maintained in:

`docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`

## 8.2 Accountant Staff financial/reporting authorization — PASSED

The targeted authorization audit confirmed that organization-wide financial/reporting surfaces remain restricted from `accountant_staff`, including journal, ledger, trial balance and organization-wide financial reports.

`modules/reports/my_financial.php` remains the dedicated read-only scoped financial report for Accountant Staff, and `modules/reports/index.php` routes that role to the scoped report.

The organization-wide confirmed-disbursements report was removed from Accountant Staff access. Fix commit:

`4750cb72ddd080ee604928e9aa1c67b9b11f70a9`

The working Accountant Staff dashboard and its `تقاريري المالية` shortcut were intentionally preserved.

## 8.3 Disbursement receipt handling — HARDENED

`modules/accounting/serve_receipt.php` validates stored receipt paths with `realpath()` and `is_file()`, constrains the resolved file to the application base directory, and reports missing/stale receipts safely.

Relevant hardening commits:

- `e6b6fd1f15f63a48c6224da3635efddc95ac38f2`
- `65625215686027ce172425c611e40c1d039de35c`
- `8cbf181071fc27de59abc26e1f4d4f0bf8f71a9e`

Deferred UI-only item: same-page Bootstrap modal feedback for missing/stale receipts in `modules/accounting/disbursements.php`.

## 8.4 Automated journal reference protection — FIXED

Repository inspection found three current automated/reversal reference types that were not protected by the generic manual-void route:

```text
disbursement_void
item_return
payroll
```

The protected set is now:

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

Only `modules/accounting/journal.php` was changed for this protection fix. No accounting data or historical journal was rewritten.

Fix commit:

`8e3ee6fd7d95c0efef834a284ed428d125064bfc`

The corresponding targeted runtime/UI verification was subsequently completed during the final audit continuation. The existing journal tests and protected evidence were not unnecessarily repeated.

## 8.5 Legacy disbursement void protection — PASS

The legacy `void_batch` route in `modules/accounting/disbursements.php` was protected by `modules/accounting/disbursement_void_guard.php`.

The guard enforces authorization and CSRF, locks the batch, validates the original posted journal, creates a separate balanced `disbursement_void` reversal, preserves and voids the original journal, voids the linked transaction, updates the batch and records the audit event atomically.

Guard correction commit:

`0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

Existing controlled fixture:

- batch `11`
- transaction `23`
- reversal journal `43`

Final result: **PASS**. The fixture must not be recreated or rerun without genuine regression evidence.

## 8.6 Final accounting consistency sweep — COMPLETE

The final read-only consistency sweep returned no unbalanced journals, insufficient-line journals, duplicate transaction journals, duplicate transaction voids, duplicate disbursement reversals, duplicate payroll journals, orphan transaction journals, orphan transaction voids, orphan payroll journals, orphan manual voids or orphan voucher journals.

One historical anomaly appeared:

`ORPHAN_DISBURSEMENT_REVERSAL — disbursement_void — reference 3 — journal_id 7`

Targeted investigation established:

- journal `7` = `JE-REV-000003`;
- date `2026-08-18`;
- status `posted`;
- reference type `disbursement_void`;
- reference id `3`;
- description `قيد عكسي لإبطال صرف #3`;
- 2 lines;
- debit `195,000.00`;
- credit `195,000.00`;
- therefore balanced and structurally valid.

The audit log also contains the historical lifecycle of disbursement `#3`, including CREATE, SUBMIT, APPROVE, TRANSFER, AUTO_CLOSE, DATA_FIX and VOID events. The source operational row is now absent, but the reversal journal is legitimate historical accounting evidence.

**Classification:** historical/legacy orphaned disbursement reversal, not an active accounting-integrity defect.

Journal `7` was deliberately preserved. No deletion, recreation or historical rewrite was performed.

## 8.7 Accounting audit documentation state

`AHL_EL_KHEIR_ACCOUNTING_AUDIT.md` is now the single canonical accounting-audit source of truth. Dated accounting update supplements have been retired after their verified contents were consolidated into that document.

---

# 9. Transactions / Donations / Payments

**VERIFIED / IMPLEMENTED-PARTIAL**

`modules/transactions/` provides payment/transaction listing, creation, sponsor reporting and receipt handling.

The transaction workflow supports role-controlled access, filtering/search, pagination, posted/voided distinction, authorized voiding with reason, accounting journal linkage and audit logging. Supervisor visibility remains scope-controlled.

The organization-wide canonical donation/payment source map remains an open schema-governance item outside the completed Accounting Audit controls.

---

# 10. Projects

**VERIFIED / IMPLEMENTED-PARTIAL**

The project domain contains project creation/editing, portfolio/listing, detailed views, team visibility, section permissions, assignments, lifecycle management, budgets, funding allocations, expenses, beneficiaries, closure totals, protected documents and audit logging.

A dedicated project lifecycle structure coexists with legacy project status information. Canonical lifecycle selection remains technical debt until formally decided.

---

# 11. HR — Foundation and Integrity Phase CLOSED

**VERIFIED / IMPLEMENTED-PARTIAL**

The HR foundation includes employees, employment states/history, contracts, salary foundations, leave management, attendance, bulk attendance, payroll policy/calculation structures, payroll accounting integration and reversal support.

## 11.1 Employment-state model — VERIFIED

Canonical employment-state data uses:

- `hr_employment_states`
- `employees.employment_state_id`
- `employees.employment_state_changed_at`
- `hr_employee_state_history`

Attendance eligibility uses the effective employment state on the selected date. Canonical state categories distinguish `working`, `temporary_unavailable` and `separation`.

## 11.2 Leave and return model — VERIFIED

Approved leave remains the historical HR interval. Early return is a separate durable event represented by `hr_leave_returns` with `leave_id`, `employee_id`, `return_date` and `created_at`.

The original approved leave interval is not shortened or deleted when an employee returns early. Attendance eligibility uses the persistent effective-return event.

The unique `leave_id` relationship prevents an old return event from unlocking an unrelated later leave.

## 11.3 Attendance integrity — VERIFIED

Check-in, check-out, absence marking and bulk attendance operations use the canonical date-specific eligibility rules. UI state reflects the same rule and cannot bypass server-side validation.

Same-day return behavior was corrected so normal attendance remains eligible after the effective return date.

## 11.4 HR dashboard/navigation — CLOSED

`dashboard/hr_dashboard.php` is the canonical HR dashboard.

`modules/hr/index.php` is a compatibility redirect rather than a duplicate dashboard.

The canonical dashboard includes employees, employment states, attendance, leaves, payroll, contracts, payroll policy and payroll financial corrections. Sensitive payroll correction controls remain role-conditional for `hr_manager` and `admin`.

The HR action cards were finalized as an even two-row grid, with **التصحيحات المالية للرواتب** included in the same grid.

This HR dashboard/navigation work is closed and should not be reopened without concrete regression evidence.

## 11.5 HR phase continuation rule

The original attendance investigation, return-from-leave debugging, persistent leave-return correction, dashboard duplicate-entry-point investigation, dashboard navigation consolidation and final card-layout work are historical completed work.

Broader HR items remain partial, especially complete payroll/accounting reconciliation, organization-wide permission coverage, comprehensive audit-log coverage and production regression testing. They are future verification items, not reasons to reopen the closed HR phase without evidence.

---

# 12. Internal Messaging — VERIFIED

The messaging subsystem supports messages, role/user recipients, replies/threads, references, urgent messages, attachments, authorized attachment downloads, attachment deletion and individual message soft deletion.

## 12.1 Individual message deletion — VERIFIED / IMPLEMENTED

Normal users may delete only messages they personally sent; System Administrator access follows authorized scope. Server-side authorization is authoritative.

Messages are soft-deleted rather than physically removed. The `messages` table records `deleted_at` and `deleted_by`, preserving reply relationships and conversation position.

The UI displays `تم حذف هذه الرسالة` for deleted content, preserves replies and removes normal delete controls after successful deletion.

Deleted-message attachments are cleaned from messaging attachment records and physical storage through the implemented deletion workflow.

The implementation uses:

- `assets/js/messaging_message_delete.js`
- `modules/messages/delete_message.php`

The historical JSON contamination issue was corrected; documented API responses remain pure JSON.

The message-deletion implementation was verified in the 2026-09-02 milestone and is now part of the current system state rather than a future/open requirement.

---

# 13. Notifications

**VERIFIED / IMPLEMENTED-PARTIAL**

The repository contains notification infrastructure, shared notification widgets and mark-all-read behavior.

Remaining documentation item: complete event-to-notification catalog with recipient rules and retention/read behavior.

---

# 14. Search

**VERIFIED / IMPLEMENTED-PARTIAL**

Global search supports permission-aware search across configured domains. Authorization is server-side and UI filtering is supplemental.

The route-specific search UI behavior remains a regression boundary because API/JSON output must not be contaminated with HTML or JavaScript.

---

# 15. Reports and PDF Generation

**VERIFIED / IMPLEMENTED-PARTIAL**

Reports include financial, sponsorship, operational, HR, orphan/family exception, lost-contact and confirmed-disbursement reporting. Reports are role-filtered and date-aware where implemented. TCPDF is present for PDF generation.

Remaining documentation item: formal report/source/calculation catalog.

---

# 16. Settings

**VERIFIED / IMPLEMENTED-PARTIAL**

`modules/settings/index.php` provides system-level settings management. Settings remain subject to authorization and must not bypass module-level business validation.

---

# 17. System Administration

**VERIFIED / IMPLEMENTED-PARTIAL**

System utilities include database/system administration, backup, health checks, controlled sponsor import/synchronization and data-maintenance utilities.

These high-risk utilities must remain tightly permissioned and appropriately logged.

The authoritative database artifact is `database/ahl_el_kheir.sql`. Future schema changes must be captured in that artifact before cleanup of temporary implementation scripts.

---

# 18. Administration / Winback

**VERIFIED / IMPLEMENTED-PARTIAL**

The administration module contains a winback workflow. Its implementation should remain aligned with sponsorship/beneficiary status rules before future expansion.

---

# 19. Logs and Audit

**VERIFIED / IMPLEMENTED-PARTIAL**

The logs module provides audit/system-log views showing actor/action/entity and old/new values where recorded.

High-value workflows demonstrably write audit evidence, including login, sponsorship changes, project actions, group/disbursement financial actions and message deletion behavior.

Universal coverage across every sensitive CRUD/permission mutation has not been proven.

Confirmed local `audit_log` schema includes:

`id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at`

The accounting audit specifically confirmed that the action field is `audit_log.action`, not `action_type`.

---

# 20. Cross-Module Integrity Rules

The complete system must preserve these boundaries:

1. Family identity is not sponsorship identity.
2. Child-level sponsorship is not family-level sponsorship.
3. Operational group membership is not a family relationship.
4. Verification is not the same event as financial transfer.
5. Transfer is not the same event as beneficiary receipt confirmation.
6. Returned funds require explicit financial evidence.
7. Accounting journal state must remain distinguishable from operational workflow state.
8. HR approved leave history must not be rewritten to represent an early return.
9. A durable lifecycle event should be persisted explicitly.
10. Protected documents must not be made public as a display workaround.
11. API endpoints must return only their documented response format.
12. UI restrictions never replace server-side authorization.
13. Financial multi-step changes must be transaction-safe.
14. Legacy structures must not become authoritative merely because they still exist in configuration or old code.
15. Historical accounting anomalies must be preserved unless a concrete integrity defect and safe remediation are proven.

---

# 21. Current System Maturity Assessment — Consolidated

| Area | Current classification | Main remaining work |
|---|---|---|
| Authentication/session | VERIFIED / HARDENING | Session fixation and production cookie policy |
| Users/roles/departments | IMPLEMENTED-PARTIAL | Central permission matrix + audit coverage |
| Families/beneficiaries | IMPLEMENTED-PARTIAL | Final document/schema reconciliation |
| Sponsors | IMPLEMENTED-PARTIAL | Lifecycle + scope matrix |
| Sponsorships | IMPLEMENTED-PARTIAL | Final lifecycle/legacy-schema reconciliation |
| Supervisors/nannies | IMPLEMENTED-PARTIAL | Final record-scope policy |
| Verification/disbursement | IMPLEMENTED-PARTIAL | Final state matrix + workflow-path consolidation |
| Accounting | **AUDIT COMPLETE / IMPLEMENTED-PARTIAL** | Canonical financial-source map and future auditability/UI enhancements |
| Transactions/payments | IMPLEMENTED-PARTIAL | Organization-wide donation/payment model |
| Projects | IMPLEMENTED-PARTIAL | Canonical lifecycle + final finance mapping |
| HR | IMPLEMENTED-PARTIAL / FOUNDATION CLOSED | Payroll/accounting reconciliation + final audit matrix |
| Messaging | VERIFIED | Preserve current JSON/attachment/delete behavior |
| Notifications | IMPLEMENTED-PARTIAL | Event/recipient catalog |
| Search | IMPLEMENTED-PARTIAL | Complete record-level visibility audit |
| Reports/PDF | IMPLEMENTED-PARTIAL | Formal report catalog and reconciliation |
| Settings | IMPLEMENTED-PARTIAL | Final governance/permission review |
| System administration | IMPLEMENTED-PARTIAL | Harden high-risk utilities and production controls |
| Logs/audit | IMPLEMENTED-PARTIAL | Mandatory event/retention matrix |

---

# 22. Consolidated Historical Milestones — 2026-09-02 through 2026-09-12

This section replaces the separate chronological system-update files that previously recorded these milestones.

## 22.1 2026-09-02 — Messaging soft-delete milestone

Verified individual message deletion with server-side authorization, CSRF protection, soft-delete fields `deleted_at` / `deleted_by`, preserved reply structure, attachment cleanup, deleted-message previews and unread suppression.

Implementation references:

- `assets/js/messaging_message_delete.js`
- `modules/messages/delete_message.php`

The feature is now fully represented in Section 12 of this document.

## 22.2 2026-09-08 — HR foundation and continuation checkpoint

Verified the canonical employment-state and leave-return model, attendance eligibility, bulk attendance behavior and the canonical HR dashboard/navigation.

HR foundation work was closed for continuation purposes. Accounting became the next major audit phase.

## 22.3 2026-09-10 — Accountant Staff financial/reporting authorization

The organization-wide accounting/reporting surfaces were confirmed restricted from Accountant Staff, while `my_financial.php` remained the scoped read-only report. The confirmed-disbursements organization-wide report was removed from Accountant Staff access.

Fix commit:

`4750cb72ddd080ee604928e9aa1c67b9b11f70a9`

## 22.4 2026-09-11 — Accounting journal integrity and legacy disbursement void

Journal listing/filtering and journal detail/history consistency were verified. Automated journal reference protection was expanded to include `disbursement_void`, `item_return` and `payroll`.

Fix commit:

`8e3ee6fd7d95c0efef834a284ed428d125064bfc`

The legacy `void_batch` route was protected by an atomic guard and the existing batch 11 / transaction 23 / reversal journal 43 fixture passed.

Guard commit:

`0071aa0ed83f428e6c4ee61154eef106dcbb7b10`

## 22.5 2026-09-12 — Accounting audit closure

The final consistency sweep was completed without restarting earlier tests. All active consistency checks passed. The single historical `disbursement_void` orphan involving journal 7 was investigated through its accounting record and audit-log history and classified as a legitimate historical orphan.

No database cleanup was performed and journal 7 was preserved.

The detailed accounting evidence is now consolidated in `AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`.

---

# 23. Documentation Consolidation State

The documentation set now uses the following authoritative structure for current work:

### Canonical current-state document

- `AHL_EL_KHEIR_CURRENT_SYSTEM_STATUS_2026-09-08.md` — this file, now consolidated through 2026-09-12.

### Long-lived architecture layer

- `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`

### Canonical accounting audit

- `AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`

### Historical audit layer

- `AHL_EL_KHEIR_REPOSITORY_AUDIT.md`
- `AHL_EL_KHEIR_COMPLETE_REPOSITORY_AUDIT.md`

The separate system-update files dated 2026-09-02, 2026-09-08, 2026-09-10, 2026-09-11 and the dedicated 2026-09-11 disbursement-void supplement are retired after consolidation into this document.

Historical audit evidence itself is not deleted merely because it is old; the dedicated audit documents remain preserved.

---

# 24. Remaining Open Documentation / Architecture Work

Before a future major implementation phase is considered fully documented, the system should converge on:

1. Module/action permission matrix.
2. Workflow state-transition matrix.
3. Database table/relationship catalog.
4. Report/source/calculation catalog.
5. Canonical financial-source map.
6. Complete audit-event coverage matrix.
7. Future accounting source↔original↔reversal navigation enhancement.

These are future documentation/architecture tasks. They do not reopen completed Accounting Audit controls or the closed HR foundation phase.

---

# 25. Current Continuation Point — 2026-09-12

The project is an existing continuation. Do not restart completed HR or Accounting work.

Current state:

```text
HR FOUNDATION / INTEGRITY AUDIT
        ↓
      CLOSED
        ↓
ACCOUNTING AUDIT
        ↓
      COMPLETE
        ↓
CURRENT PROJECT WORK
        ↓
Proceed to the next non-completed system/module task
```

The completed Accounting Audit evidence is preserved in `AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`.

The current system-state context is preserved in this document.

For every future milestone:

**Code correct → behavior verified → documentation updated → session index updated → exact next continuation point clear.**

**Status:** Consolidated current system status through 2026-09-12. No application behavior or database data is changed by this document.