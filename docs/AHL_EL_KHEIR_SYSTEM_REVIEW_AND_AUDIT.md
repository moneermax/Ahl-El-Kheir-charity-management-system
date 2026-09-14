# Ahl El Kheir Charity Management System
## System Review and Audit — Consolidated Working Record

**Arabic name:** نظام أهل الخير لإدارة الجمعيات الخيرية  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Environment:** Windows / XAMPP / Apache / PHP 8.2 / MariaDB/MySQL  
**Application style:** Procedural PHP + Bootstrap 5.3 RTL + Vanilla JavaScript + Font Awesome 6 + Cairo  
**Document role:** Consolidated system analysis, functional/technical review, Accounting Audit, and Notification Audit.  
**Status:** Living document. The current repository and verified implementation are authoritative.

> This document replaces the previously separate `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`, `AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`, and `AHL_EL_KHEIR_NOTIFICATION_AUDIT.md`. Their overlapping system-review material is consolidated here; completed audit evidence and current continuation points are retained below. Do not restart completed audits or recreate protected test fixtures unless a genuine regression is found.

---

# 1. Project Purpose and Scope

Ahl El Kheir is an organizational management platform for a charitable association. It is intended to provide controlled digital workflows for beneficiary management, sponsorship, supervisor/nanny operations, monthly disbursements, donations and financial transactions, accounting, communication, notifications, HR, reporting, and accountability.

The system is not merely a CRUD application. Its governing principles are responsibility-based access, least privilege, separation of duties, traceability, database integrity, protected documents, transactional financial operations, and preservation of existing functionality.

In-scope areas include:

- Authentication and session management.
- Users, roles, permissions, and organizational administration.
- Families and children/beneficiaries.
- Sponsors and child-level sponsorships.
- Supervisors and nannies.
- Orphan/payment groups and monthly verification.
- Monthly disbursement batches and individual items.
- Payment confirmation, receipts, returned funds, and reconciliation.
- Donations, financial transactions, journals, and accounting reports.
- Internal messaging and attachments.
- Notifications.
- HR.
- Projects, search, reports/PDFs, settings, logs, backups, and administration.

External payment gateways, public donor portals, mobile applications, and external accounting integrations are not assumed to exist unless explicitly implemented.

---

# 2. Technology and Architecture Baseline

- PHP 8.2+; application code is procedural PHP, not OOP.
- MySQL/MariaDB.
- Apache through XAMPP during local development.
- HTML5/CSS3, Bootstrap 5.3 RTL, Vanilla JavaScript, Font Awesome 6, Cairo.
- TCPDF where PDF generation is implemented.
- Local project: `D:\xampp\htdocs\AhlElKheir`
- Local URL: `http://localhost:8081/AhlElKheir/`

The application is modular, with shared configuration/database/session/function libraries, module pages, action/API endpoints, assets, and protected storage. Existing application architecture must be preserved unless deliberately redesigned.

### Non-negotiable engineering rules

1. Server-side authorization is the security boundary; hiding UI elements is not authorization.
2. Apply least privilege and separation of duties.
3. Important actions must be traceable to user/time/workflow state.
4. Preserve foreign keys, constraints, and transactions.
5. **Never guess database schema, table names, or columns. Inspect the current schema/code first.**
6. Sensitive documents must remain behind authorized serving endpoints.
7. Multi-table financial mutations must be transactional.
8. A local fix must not regress unrelated modules.
9. Current repository code is authoritative; historical chat is context only.
10. Do not rewrite historical accounting evidence merely to make an audit query look clean.

---

# 3. Organizational Roles and Authorization Model

Current organizational roles include System Administrator, General Manager, Vice General Manager, Financial Manager/Accountant, Accountant Staff, Supervisor, Nanny, Administration, HR Manager, HR Staff, and Social Media. Code may normalize aliases; authorization must follow the application's actual role normalization.

Authorization is evaluated at:

- module level;
- action level;
- record/organizational scope;
- financial authorization level;
- server-side enforcement independently of UI visibility.

### Authoritative supervisor sponsor rule

A supervisor's sponsor responsibility is determined by the **sponsor's own first letter and sponsor gender** through the configured `supervisor_letters` matrix.

```text
Sponsor full name
      ↓
Sponsor first letter + sponsor gender
      ↓
Configured supervisor responsibility
```

Mother/family name, mother's first letter, family code, and orphan family are not proxies for sponsor ownership.

A supervisor responsible for a sponsor may manage that sponsor's sponsorship relationships, follow up on dues and additional payments, and perform the applicable sponsor workflows.

### Family access rule

Sponsor ownership and family assignment are separate concepts. If a sponsor is assigned to a supervisor, that supervisor can access the sponsor and the sponsor's related family/orphan data. Direct family assignment also grants family access. The sponsor letter+gender responsibility matrix grants access to linked family/orphan data. These rules must not be narrowed to `families.supervisor_id` alone.

Nanny access remains based on the applicable direct operational family/disbursement scope.

---

# 4. Core Data and Business Relationships

The conceptual beneficiary relationship is:

`Family → Children → Sponsorships → Monthly Disbursement Items`

not `Family → Sponsor`.

A family can contain multiple children; siblings can have different sponsors and payment contexts. Groups are operational/payment constructs, not families. Code must traverse the actual child/family relationship rather than inventing a `family_id` on a table where the schema does not contain one.

### Sponsorship

A sponsorship is a child-level relationship between sponsor and beneficiary. Supervisor sponsorship scope is sponsor-scoped through the sponsor's letter+gender responsibility matrix.

### Groups and disbursement

Groups organize sponsored children for monthly operational processing. A batch may contain multiple groups and a group may contain multiple disbursement items.

The operational lifecycle is conceptually:

`Preparation → Verification → Group/Batch Review → Financial Processing → Transfer → Nanny Confirmation → Reconciliation → Closure`

### Returned funds rule

By the end of the monthly cycle, every payment record must have a final operational/accounting status. A nanny cannot retain funds indefinitely. If the family cannot be reached or the payment cannot be completed, the appropriate amount must be returned and recorded as an explicit financial event with its reason, responsible user, timing, receipt/evidence where required, and accounting effect.

---

# 5. System Review — Functional Architecture

## Authentication and users

Authentication establishes the protected user context for every module. User accounts, role assignments, active/inactive status, and organizational responsibility affect authorization and must be treated as sensitive/auditable.

## Families and children

Families are household/case-management records. Children are the beneficiary level used by sponsorship and monthly operational workflows. Family identity must not be conflated with child-level sponsorship.

## Sponsors and sponsorships

Sponsors are independent business entities. Sponsorships connect sponsors to children and carry the relevant support/financial commitment.

## Supervisors and nannies

Supervisors handle sponsor relationship management and explicitly assigned operational responsibility. Nannies perform direct beneficiary follow-up and monthly payment confirmation within their assigned scope.

## Monthly verification and disbursement

Verification establishes readiness for a monthly cycle. Financial review and transfer are separate control stages. Nanny confirmation must authenticate the actor, verify scope, update item state, record confirmation time, and retain receipt evidence.

Partial payment must not be treated as successful full disbursement. Returned/unpaid amounts must be explicitly handled.

## Donations, transactions, and accounting

Financial records must retain source, purpose, date, amount, responsible workflow, and audit history. Journal headers and lines must remain internally consistent and balanced.

## Messaging and attachments

Internal messaging is separate from system notifications and uses the message/attachment architecture. Attachment downloads must be authorization-controlled and protected from direct public exposure.

## Notifications

Notifications surface workflow events requiring attention. The authoritative notification behavior is documented in Section 9.

## HR, projects, search, reports

HR behavior is governed by current implementation and its audit records. Projects remain separate organizational initiatives unless explicitly integrated with accounting. Search is permission-aware and record visibility remains server-enforced. Reports/PDFs must use the actual implemented data relationships.

---

# 6. Accounting Audit — Completed Controls

Accounting Phase 1 completed and passed the following controls:

1. Creator → FM review workflow.
2. FM return with mandatory reason.
3. Creator edit/resubmit of returned transaction.
4. FM approval → posted transaction + balanced journal.
5. Returned transaction → creator cancellation with no journal.
6. Posted transaction → authorized void + balanced reversal journal.
7. Manual Journal Entry integrity.
8. Trial Balance integrity.
9. Accountant Staff financial/reporting authorization surface audit.
10. Accountant Staff disbursement authorization.
11. Existing disbursement receipt/closure workflow.
12. Journal listing/filtering integrity.
13. Journal detail/history consistency.

Do not repeat these tests unless new code evidence indicates regression.

## Protected accounting evidence

### TR-000014 — posting passed

- Transaction ID `20`.
- Creator user `17` / ACC1.
- FM reviewer user `29`.
- `general_donation`, `250,000.00 SDG`, mobile.
- Final status `posted`.
- Journal ID `36`.
- Journal balanced `250,000.00 / 250,000.00`.

Lifecycle passed: ACC1 creates → FM returns → ACC1 edits/resubmits → FM approves → transaction posts → balanced journal posts.

### TR-000015 — returned → cancelled passed

- Transaction ID `21`.
- Creator `17` / ACC1; FM reviewer `29`.
- `project_donation`, `25,000.00`, bank transfer, reference `987654321`.
- Final status `cancelled`; `cancelled_by = 17`.
- Reason: `إلغاء من المنشئ بعد الإرجاع`.
- Sequence `1516 SUBMIT_FM`, `1517 FM_RETURN`, `1518 CANCEL_RETURNED`.
- No journal was created.

Implementation: `modules/transactions/cancel_returned.php`; correction commit `62229c5a9a4be9ad2d55223402b5fe1f03bd81e4`.

### TR-000016 — posted → void + reversal passed

- Transaction ID `22`, `general_donation`, `1,000.00`, cash.
- Final transaction status `voided`.
- Original journal `JE-000026` / ID `37`, voided.
- Reversal `JE-VOID-TXN-22`, `transaction_void / 22`, posted and balanced.
- Void performed by Financial Manager.

The atomic result is original journal voided + balanced reversal posted + transaction voided. Duplicate reversal protection and rollback protection were verified.

Primary helper: `modules/accounting/lib_transaction_void.php`; hardening commit `99759d186cbb9507be631aa4df48cbef4d7e202b`.

### JE-000027 — manual journal passed

Manual journal controls include strict validation, active-account validation, duplicate-account rejection, one-sided-line rejection, positive amounts, minimum two lines, exact debit/credit equality, atomic handling, post-save verification, serialized numbering, uniqueness protection, and `reference_type = manual` / `reference_id = NULL`.

Controlled journal: `JE-000027`, `2026-09-09`, manual, `1,000.00`, posted, created by Financial Manager. The creator cannot void their own manual journal, and automated journal references are protected from the manual void route.

### Trial Balance — passed

Verified historical audit result:

- Total debit: `103,373,000.00`.
- Total credit: `103,373,000.00`.
- Difference: `0.00`.
- Posted journals: `24`.
- Posted lines: `54`.
- Result: `✓ الميزان متوازن — PASSED`.

Trial Balance uses posted journal entries and excludes voided journals. It is distinct from `modules/accounting/gm_reconciliation.php`.

---

# 7. Accountant Staff / Disbursement Accounting Review

The Accountant Staff financial/reporting and disbursement authorization audit is completed. Confirmed restricted surfaces include journal, ledger, trial balance, and organization-wide financial reports.

Established workflow:

```text
Vice General Manager creates batch
→ assigns batch/group to Accountant Staff
→ Accountant Staff manages only assigned nanny/batch scope
```

Assigned visibility, restricted financial actions, reopen controls, nanny receipt confirmation, batch void, and existing receipt/closure workflow were passed. Accountant Staff is not a batch-creation role.

Current known assignment test context includes Accountant Staff user `17` assigned to nanny `16`. Existing disbursement implementation already contains the single-family item reopen path with assigned-nanny authorization, required reason, state rollback, parent reopening when needed, audit logging, and nanny notification.

Do not assume an old “next test” is still pending. Re-read the current code and audit record before selecting the next test.

---

# 8. Accounting Journal Integrity — Current Checkpoint

## 8.1 Journal protection set

The generic journal page must not expose automated/reversal journals to the manual-void route.

Current protected reference types are:

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

The current repository correction protecting `disbursement_void`, `item_return`, and `payroll` is commit `8e3ee6fd7d95c0efef834a284ed428d125064bfc`.

## 8.2 Automated reference semantics

`disbursement_void` is created by the batch-void workflow and references the monthly disbursement. It must not be manually voided through the generic journal route.

`item_return` is created by the partial item-return workflow and references the disbursement item. Its relationship must not be relabeled simply to satisfy an audit query.

`payroll` is created by `modules/hr/lib_payroll_accounting.php`, references the payroll record, and is linked through the payroll accounting-entry relationship. Generic journal voiding must not bypass payroll state/linkage integrity.

## 8.3 Journal detail/history

Current detail loads the actual journal header and lines, calculates debit/credit totals from actual lines, keeps voided originals inspectable, and keeps reversal entries separately inspectable.

## 8.4 Remaining accounting work

1. Targeted local runtime/UI verification for the protected `payroll`, `disbursement_void`, and `item_return` types.
2. Inspect remaining callers of `ak_void_journal_for_voucher()` and direct journal mutation routes.
3. Verify duplicate/missing journal relationships only where a genuinely new route is discovered.
4. Continue cross-module accounting references and auditability.
5. Later consider direct original↔reversal/source navigation.

The cross-reference UI is currently a parked enhancement, not a journal-detail integrity failure.

---

# 9. Notification Audit — Completed and Current State

## Notification audit rule

For every workflow:

```text
Action
→ business-state change
→ required recipient(s)
→ active recipient scope
→ exactly-once delivery
→ correct event/reference
→ actionable link
→ read state
→ next workflow action
```

Notifications are classified as required workflow notifications, operational notifications, optional/self confirmations, or no notification where no recipient action is required.

### Current architecture

- System notifications use `notifications` and current recipient/title/body/link/read/reference fields where supported.
- Internal messaging is separate (`messages` / `message_reads`).
- Bell mark-all-read is POST+CSRF.
- Individual mark-read is POST+CSRF before redirecting to the actionable link.
- Accounting transaction-review notifications use event/reference-aware helpers and deduplication.

## Password recovery — fixed

`modules/users/recovery.php` now:

- notifies the requesting user when recovery is approved;
- uses an absolute application URL for forced password change;
- notifies the requesting user when recovery is rejected;
- sends rejection only when the pending request actually changes to rejected.

Commits: `21362ef456db73303734acdede3e332f330aedf0`, `2559b05f757aa408ddab2a4b4c8660a6af68b97b`.

The GET-side recovery notification read mutation was removed. Opening the recovery page no longer changes notification read state.

A later regression involving duplicated `/AhlElKheir/AhlElKheir/` links was fixed in `modules/notifications/mark_read.php` and `mark_all_read.php`; the user retested the existing recovery notification successfully at the correct application path.

## Transaction-review and sponsor-payment notifications — fixed

The transaction-review notification path uses the current notification schema, active FM roles, event/reference-aware delivery, and notification-error isolation. Supervisor sponsor-payment submission and resubmission paths were repaired and now notify the appropriate active Financial Manager recipients using payment-specific references and actionable review links.

Relevant commits include `3ca3814cf01a676536c2cf91b89f3842fc07cbe5`, `717ed19104bf94ea1a6a4114bad7c5dce8b57898`, `0d06152736b2b905d63c07ee9f6fc7e49614d5cb0`, and `5f7fc01e2c884f42d604f4b12be67027613e6377`.

## HR leave notifications — fixed

`modules/hr/leaves.php::notifyLeaveRoleUsers()` restricts recipients to active users (`u.is_active = 1`). The local writer is exception-isolated so notification failure cannot change or invalidate an already completed leave state transition.

Fix commit: `ff0e9e2c74bdde820be4a72e1d15bc04629401cc`.

## Disbursement notification matrix

| Action | State change | Recipient | Required behavior |
|---|---|---|---|
| Create batch | → `pending_approval` | none established for nanny | Do not notify nanny |
| Transfer with receipt | `pending_approval` → `transferred` | assigned nanny | Must notify `disbursement_transferred` |
| Confirm family item | item `pending` → `paid` | no downstream actor | Actor confirmation only |
| Fully close batch | `transferred` → `received`; group → `closed` | responsible assigned accounting users | Must notify after commit |
| Close with return | `transferred` → `returned`; pending items returned; reversal posted; group closed | active FM responsibility | Must notify `disbursement_returned` |
| Reopen whole batch | `received`/`returned` → `transferred`; group reopened | assigned nanny | Must notify `disbursement_reopened` |
| Reopen family item | item `paid`/`returned` → `pending`; parent may reopen | assigned nanny | Must notify `disbursement_item_reopened` |
| Void batch | active batch → `voided` | assigned nanny when old state was actionable | Must notify only from `transferred`/`received` |

Notifications occur after the relevant business transition commits and are isolated so notification insertion failure does not roll back the completed accounting/disbursement action.

## Payroll notifications

No separate mandatory human recipient was established for payroll approval/payment. This remains an architectural decision point rather than a confirmed defect.

## Notification read state

`modules/notifications/mark_read.php` is authenticated, POST-only, CSRF-protected, recipient-scoped, and safely normalizes legacy relative application links while rejecting external/protocol-relative redirects. The widget submits individual mark-read actions. Mark-all-read uses the same safe redirect normalization.

Relevant commits: `50b4d27991d85c329ffeab884a52cd5d3eb3bcc5`, `6d87c6d2496d33c1d4cb1c5f12c8b289906be50a`, `96ea51abbef748e1b5122de66f7b2d0e94369049`, `ac13745f7888347423614e38047e37348b6ffb5d`, `3a567bfc48f98f1ea645c51d2fb1e46d4f412b81`.

## Direct notification writers

Confirmed self-contained direct writers:

1. `modules/users/recovery.php`.
2. `modules/hr/leaves.php`.

Active accounting/disbursement/sponsor-payment review paths use the event/reference-aware notification infrastructure.

`config/messaging.php::send_system_notification()` remains a legacy/general compatibility helper. It accepts type/reference arguments but its current implementation does not fully persist them. No reliable active production caller was established, so it has intentionally not been modernized speculatively.

### Notification next work

- If reliable caller evidence for `send_system_notification()` appears, audit each caller before modernization/retirement.
- Continue source-level notification-writer sweep only where repository evidence is complete and reliable.
- Run targeted end-to-end tests only for newly changed notification workflows.

---

# 10. Receipts and Protected Documents

`modules/accounting/serve_receipt.php` validates stored receipt paths using `realpath()` and `is_file()`, constrains them to the application base directory, and returns an application-level message for missing/stale files. It does not substitute an individual family receipt for a missing final batch receipt.

Relevant commits: `e6b6fd1f15f63a48c6224da3635efddc95ac38f2`, `65625215686027ce172425c611e40c1d039de35c`, `8cbf181071fc27de59abc26e1f4d4f0bf8f71a9e`.

A UI enhancement to display missing/stale receipt messages in a same-page Bootstrap modal remains deferred; server-side path/authorization controls must not be weakened.

---

# 11. Security and Integrity Review Principles

- Session and authentication controls must remain server-enforced.
- CSRF protection is required for state-changing POST actions.
- GET requests must not mutate workflow/read state.
- File downloads must verify ownership/authorization and safe paths.
- Notification redirects must not become open redirects.
- Financial mutations must be atomic.
- Automated journal entries must not be manually mutated through generic journal routes.
- Role scope must be enforced in SQL/backend logic, not only menus.
- Do not trust historical filenames or old documentation over current code/schema.
- Do not invent SQL columns or table names.

---

# 12. Protected Completed Evidence — Do Not Modify or Reuse

- TR-000014 / transaction `20`.
- TR-000015 / transaction `21`.
- TR-000016 / transaction `22`.
- JE-000027.
- JE-000026 / journal `37`.
- JE-VOID-TXN-22.

These records are audit evidence. Future tests must use new controlled data where a test fixture is genuinely required.

---

# 13. Current Continuation Roadmap

The project is an existing implementation and an existing audit. Continue from the current repository state.

### Accounting

1. Targeted runtime verification of the newly protected automated journal reference types.
2. Audit remaining direct journal mutation callers.
3. Continue cross-module accounting-history interaction review.
4. Later consider original↔reversal/source navigation.

### Notifications

1. Establish reliable caller evidence for the legacy generic notification helper if possible.
2. Continue targeted source review only where evidence is reliable.
3. Test only newly changed notification workflows.

### System-wide review

Continue reviewing modules according to actual business responsibility, authorization scope, state transitions, auditability, and integration with the already-audited financial/notification workflows. Do not reopen completed areas without a regression signal.

---

# 14. Documentation Governance

The documentation structure is intentionally consolidated to avoid multiple overlapping audit/checkpoint files.

- `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — high-level **START HERE** status and current project checkpoint.
- `docs/AHL_EL_KHEIR_SYSTEM_REVIEW_AND_AUDIT.md` — this consolidated system review and audit record.
- `docs/CHATGPT_SESSION_INDEX.md` — short navigation index.
- Other dedicated documents remain only where they contain genuinely distinct subject matter, such as I18N, production preparation, organizational lifecycle, and specific HR navigation evidence.

Do not create another dated system-status/audit file for ordinary continuation. Update this document and the master status when a meaningful milestone changes the project state.

After every meaningful milestone:

**Code correct → behavior verified → documentation updated → exact next continuation point clear.**

---

# 15. Final Continuation Rule

This is a continuation of an existing project and existing audit.

**Never restart from zero.**  
**Never repeat completed tests without a regression reason.**  
**Never recreate protected fixtures unnecessarily.**  
**Never ask the user to manually edit repository files when the repository can be changed directly.**  
**Inspect current code/schema first, then make the smallest safe change.**
