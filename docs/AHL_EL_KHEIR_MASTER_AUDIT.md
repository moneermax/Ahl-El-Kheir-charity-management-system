# Ahl El Kheir Charity Management System
## Master System Review and Audit

**Arabic name:** نظام أهل الخير لإدارة الجمعيات الخيرية  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-21  
**Environment:** Windows / XAMPP / Apache / PHP 8.2 / MariaDB/MySQL  
**Application style:** Procedural PHP + Bootstrap 5.3 RTL + Vanilla JavaScript + Font Awesome 6 + Cairo  
**Document role:** **Single master audit record** for system review, architecture, Accounting Audit, Notification Audit, organizational lifecycle, HR navigation, completed evidence, open findings, and continuation rules.  
**Status:** Living document. Current repository code and verified implementation are authoritative.

> This is the single master audit file. Previous overlapping audit/review records are consolidated here. Do not restart completed audits, repeat passed tests, recreate protected fixtures, or create another dated audit/checkpoint file unless a genuinely separate document is required.

---

# 1. Project Purpose and Scope

Ahl El Kheir is an organizational management platform for a charitable association. It provides controlled digital workflows for beneficiary management, sponsorship, supervisor/nanny operations, monthly disbursements, donations and financial transactions, accounting, communication, notifications, HR, reporting, and accountability.

The system is not merely a CRUD application. Its governing principles are responsibility-based access, least privilege, separation of duties, traceability, database integrity, protected documents, transactional financial operations, and preservation of existing functionality.

In-scope areas include authentication/session management, users/roles/permissions, families and children, sponsors and child-level sponsorships, supervisors and nannies, groups and monthly verification, disbursement batches/items, receipts and returned funds, donations/transactions/journals, messaging, notifications, HR, projects, search, reports/PDFs, settings, logs, backups, and administration.

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

The application is modular, with shared configuration/database/session/function libraries, module pages, action/API endpoints, assets, and protected storage.

## Non-negotiable engineering rules

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

Authorization is evaluated at module level, action level, record/organizational scope, financial authorization level, and server-side independently of UI visibility.

## 3.1 Authoritative supervisor sponsor rule

A supervisor's sponsor responsibility is determined by the **sponsor's own first letter and sponsor gender** through the configured `supervisor_letters` matrix.

```text
Sponsor full name
      ↓
Sponsor first letter + sponsor gender
      ↓
Configured supervisor responsibility
```

Mother/family name, mother's first letter, family code, and orphan family are not proxies for sponsor ownership.

A responsible supervisor may manage that sponsor's sponsorship relationships, follow up on dues and additional payments, and perform applicable sponsor workflows.

## 3.2 Family access rule

Sponsor ownership and family assignment are separate concepts. If a sponsor is assigned to a supervisor, that supervisor can access the sponsor and the sponsor's related family/orphan data. Direct family assignment also grants family access. The sponsor letter+gender responsibility matrix grants access to linked family/orphan data. These rules must not be narrowed to `families.supervisor_id` alone.

Nanny access remains based on the applicable direct operational family/disbursement scope.

Restoration commit: `448fa0bfee7fdcd5ce3e7608304de6b1c7f9ba73`.

---

# 4. Core Data and Business Relationships

The conceptual beneficiary relationship is:

`Family → Children → Sponsorships → Monthly Disbursement Items`

not `Family → Sponsor`.

A family can contain multiple children; siblings can have different sponsors and payment contexts. Groups are operational/payment constructs, not families. Code must traverse the actual child/family relationship rather than inventing a `family_id` on a table where the schema does not contain one.

### Groups and disbursement

The operational lifecycle is conceptually:

`Preparation → Verification → Group/Batch Review → Financial Processing → Transfer → Nanny Confirmation → Reconciliation → Closure`

### Returned funds rule

By the end of the monthly cycle, every payment record must have a final operational/accounting status. A nanny cannot retain funds indefinitely. If the family cannot be reached or the payment cannot be completed, the appropriate amount must be returned and recorded as an explicit financial event with reason, responsible user, timing, receipt/evidence where required, and accounting effect.

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

Internal messaging is separate from system notifications. Attachment downloads must be authorization-controlled and protected from direct public exposure.

## Notifications

Notifications surface workflow events requiring attention. The authoritative notification behavior is documented in Section 9.

## HR, projects, search, reports

HR behavior is governed by current implementation and the HR audit section below. Projects remain separate organizational initiatives unless explicitly integrated with accounting. Search is permission-aware and record visibility remains server-enforced. Reports/PDFs must use the actual implemented data relationships.

---

# 6. Accounting Audit — Completed Controls

Accounting Phase 1 completed and passed:

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

## 6.1 Protected accounting evidence

### TR-000014 — posting passed

- Transaction ID `20`.
- Creator `17` / ACC1.
- FM reviewer `29`.
- `general_donation`, `250,000.00 SDG`, mobile.
- Final status `posted`.
- Journal ID `36`.
- Balanced `250,000.00 / 250,000.00`.

### TR-000015 — returned → cancelled passed

- Transaction ID `21`.
- Creator `17` / ACC1; FM reviewer `29`.
- `project_donation`, `25,000.00`, bank transfer, reference `987654321`.
- Final status `cancelled`; `cancelled_by = 17`.
- Reason: `إلغاء من المنشئ بعد الإرجاع`.
- Sequence `1516 SUBMIT_FM`, `1517 FM_RETURN`, `1518 CANCEL_RETURNED`.
- No journal created.

Implementation: `modules/transactions/cancel_returned.php`; correction commit `62229c5a9a4be9ad2d55223402b5fe1f03bd81e4`.

### TR-000016 — posted → void + reversal passed

- Transaction ID `22`, `general_donation`, `1,000.00`, cash.
- Final transaction status `voided`.
- Original journal `JE-000026` / ID `37`, voided.
- Reversal `JE-VOID-TXN-22`, `transaction_void / 22`, posted and balanced.
- Void performed by Financial Manager.

Primary helper: `modules/accounting/lib_transaction_void.php`; hardening commit `99759d186cbb9507be631aa4df48cbef4d7e202b`.

### JE-000027 — manual journal passed

Manual journal controls include strict validation, active-account validation, duplicate-account rejection, one-sided-line rejection, positive amounts, minimum two lines, exact debit/credit equality, atomic handling, post-save verification, serialized numbering, uniqueness protection, and `reference_type = manual` / `reference_id = NULL`.

Controlled journal: `JE-000027`, `2026-09-09`, manual, `1,000.00`, posted, created by Financial Manager. The creator cannot void their own manual journal, and automated journal references are protected from the manual void route.

### Trial Balance — passed

Historical audit result:

- Total debit: `103,373,000.00`.
- Total credit: `103,373,000.00`.
- Difference: `0.00`.
- Posted journals: `24`.
- Posted lines: `54`.
- Result: `✓ الميزان متوازن — PASSED`.

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

Known assignment test context: Accountant Staff user `17` assigned to nanny `16`.

The current disbursement implementation already contains the single-family item reopen path with assigned-nanny authorization, required reason, state rollback, parent reopening when needed, audit logging, and nanny notification. Do not assume an old “next test” is still pending; re-read current code and audit evidence first.

---

# 8. Accounting Journal Integrity — Current Checkpoint

## 8.1 Protected reference types

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

The generic journal page must not expose automated/reversal journals to the manual-void route. The correction protecting `disbursement_void`, `item_return`, and `payroll` is commit `8e3ee6fd7d95c0efef834a284ed428d125064bfc`.

## 8.2 Reference semantics

`disbursement_void` is created by the batch-void workflow and references the monthly disbursement. `item_return` is created by the partial item-return workflow and references the disbursement item. `payroll` is created by `modules/hr/lib_payroll_accounting.php` and links to the payroll record. These relationships must not be relabeled merely to satisfy an audit query.

Current journal detail loads the actual header and lines, calculates debit/credit totals from actual lines, keeps voided originals inspectable, and keeps reversal entries separately inspectable.

### Remaining accounting work

1. Targeted runtime/UI verification for protected `payroll`, `disbursement_void`, and `item_return` types.
2. Inspect remaining callers of `ak_void_journal_for_voucher()` and direct journal mutation routes.
3. Verify duplicate/missing journal relationships only where a genuinely new route is discovered.
4. Continue cross-module accounting references and auditability.
5. Later consider direct original↔reversal/source navigation.

The cross-reference UI is a parked enhancement, not a journal-detail integrity failure.

---

# 9. Notification Audit — Completed and Current State

## Notification audit rule

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

`modules/users/recovery.php` notifies the requesting user on approval and rejection, uses an absolute application URL for forced password change, and only sends rejection after a successful pending→rejected state change.

Commits: `21362ef456db73303734acdede3e332f330aedf0`, `2559b05f757aa408ddab2a4b4c8660a6af68b97b`.

The GET-side recovery notification read mutation was removed. A duplicated `/AhlElKheir/AhlElKheir/` legacy-link regression was fixed in `mark_read.php` and `mark_all_read.php`; the user retested the existing recovery notification successfully at the correct application path.

## Transaction-review and sponsor-payment notifications — fixed

The transaction-review notification path uses the current notification schema, active FM roles, event/reference-aware delivery, and notification-error isolation. Supervisor sponsor-payment submission and resubmission paths notify the appropriate active Financial Manager recipients using payment-specific references and actionable review links.

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

## Notification read state

`modules/notifications/mark_read.php` is authenticated, POST-only, CSRF-protected, recipient-scoped, and safely normalizes legacy relative application links while rejecting external/protocol-relative redirects. Mark-all-read uses the same safe redirect normalization.

Relevant commits: `50b4d27991d85c329ffeab884a52cd5d3eb3bcc5`, `6d87c6d2496d33c1d4cb1c5f12c8b289906be50a`, `96ea51abbef748e1b5122de66f7b2d0e94369049`, `ac13745f7888347423614e38047e37348b6ffb5d`, `3a567bfc48f98f1ea645c51d2fb1e46d4f412b81`.

## Direct notification writers

Confirmed self-contained direct writers:

1. `modules/users/recovery.php`.
2. `modules/hr/leaves.php`.

Active accounting/disbursement/sponsor-payment review paths use the event/reference-aware notification infrastructure.

`config/messaging.php::send_system_notification()` remains a legacy/general compatibility helper. It accepts type/reference arguments but does not fully persist them. No reliable active production caller was established, so it has not been modernized speculatively.

### Project submission notification — fixed 2026-09-21

The Projects workflow had a specific notification gap: `modules/projects/view.php` changed `project_approval.approval_status` from `draft/rejected` to `submitted`, recorded the audit event, and flashed success, but did not create a system notification for Financial Manager users.

Root cause: the project submission handler had no notification call. The existing notification infrastructure was already capable of workflow/event references and active-recipient selection, so no new notification table or schema change was required.

Implementation:
- Reused `modules/accounting/lib_transaction_review.php::ak_transaction_review_notify_fm_event()` rather than creating a second notification mechanism.
- Recipients are selected by the existing helper as active users whose role code is `financial_manager`, `fm`, or `finance`.
- Event/reference: `project_submission` + the project ID.
- Notification identifies the project name and project code and links to `modules/projects/view.php?id=<project_id>`, where the existing FM review controls are displayed for a submitted project.
- Delivery is isolated from the completed project state transition. The existing event-aware helper suppresses an equivalent unread notification, while a later resubmission after the previous notification has been read can generate a fresh notification.

Code commit: `951dec73f88326f8b516dca7b5e4732329d53de7`.

Runtime certification remains dependent on the local XAMPP test described in the current continuation task; no browser/runtime result is claimed here.

## Notification next work

- Establish reliable caller evidence for the legacy generic helper if possible.
- Continue targeted source review only where repository evidence is reliable.
- Test only newly changed notification workflows.

---

# 10. Receipts, Documents, Messaging, and Security

`modules/accounting/serve_receipt.php` validates stored receipt paths using `realpath()` and `is_file()`, constrains them to the application base directory, and returns an application-level message for missing/stale files. It does not substitute an individual family receipt for a missing final batch receipt.

Relevant commits: `e6b6fd1f15f63a48c6224da3635efddc95ac38f2`, `65625215686027ce172425c611e40c1d039de35c`, `8cbf181071fc27de59abc26e1f4d4f0bf8f71a9e`.

Messaging attachments are functional. The historical JSON contamination issue was resolved by limiting search UI injection to the actual search route so API endpoints return pure JSON. Do not reopen this historical issue without a new symptom.

Security principles:

- authenticated context on protected pages/endpoints;
- server-side authorization for every protected action;
- CSRF for state-changing browser requests;
- parameterized SQL;
- output escaping appropriate to context;
- safe upload validation and protected serving;
- secure session configuration for production;
- no sensitive diagnostics in production;
- no GET-side workflow/read-state mutation;
- no open redirects.

---

# 11. Organizational Lifecycle Audit

## 11.1 Executive finding

The system has moved beyond a simple `active/inactive` model for supervisors. A dedicated supervisor lifecycle exists with states `active`, `on_leave`, `suspended`, `returning`, `departed`, and `archived`. Leave records and assignment-history mechanisms are present.

The organizational model remains asymmetric: the richer lifecycle exists primarily for supervisors, while general user management still exposes direct `is_active` controls. The next organizational phase should unify lifecycle concepts without destroying the existing supervisor implementation.

## 11.2 Implemented supervisor lifecycle

`config/supervisor_lifecycle.php` provides:

- lifecycle labels for active, leave, suspended, returning, departed, and archived;
- work-eligibility checking (`active` only);
- final-state protection for departed/archived supervisors;
- supervisor leave creation and activation;
- return-from-leave processing;
- permanent departure/archival;
- release of sponsor assignments on departure;
- preservation/release of supervisor letter assignments;
- transaction handling around lifecycle operations.

`database/migrations/2026-09-03_supervisor_lifecycle.sql` adds:

- `users.supervisor_status`;
- `supervisor_leaves`;
- assignment type/reason fields for sponsor-supervisor assignments;
- `supervisor_letter_assignment_history`.

`modules/users/supervisor_status.php` prevents inappropriate direct toggling while on leave/returning and prevents reactivation of final states.

`modules/users/supervisor_departure.php` archives the supervisor, revokes login access, releases operational assignments, and retains historical identity instead of deleting the user.

## 11.3 Current organizational gaps

### Gap A — General user management still uses `is_active` as a primary lifecycle control

`modules/users/index.php` still allows administrators to update `is_active` directly and provides a generic toggle action. This is acceptable for a basic account system but conflicts with richer organizational lifecycle semantics when applied indiscriminately.

### Gap B — Lifecycle is supervisor-specific

Other organizational users still rely mainly on role + department + manager + `is_active`.

### Gap C — Organizational assignment history is not unified

Supervisor sponsor and letter assignments have historical handling, but there is not yet one generic assignment-history model covering role, department, manager, and functional responsibility changes.

### Gap D — Role/reporting-line changes are not yet lifecycle events

The user management page can directly change `role_id`, `department_id`, and `manager_id`; these changes should eventually be recorded as organizational events.

### Gap E — Audit logging is not yet the sole source of organizational history

Some supervisor operations explicitly create audit entries, but ordinary user edits do not yet appear to create equivalent structured organizational history.

## 11.4 Target organizational model

Distinguish at least four dimensions:

1. **Identity** — stable person/user record.
2. **Account access** — whether the account can authenticate.
3. **Employment/organizational lifecycle** — active, leave, suspended, departed, archived, etc.
4. **Organizational assignment** — role, department, manager, and functional responsibilities with effective history.

These dimensions must not be collapsed into one boolean.

## 11.5 Safe implementation order

1. Keep the existing supervisor lifecycle intact and prevent accidental generic toggling for supervisors.
2. Introduce a general organizational assignment/history model only after inspecting the current schema and migration state.
3. Replace direct role/department/manager replacement with controlled transitions that close the previous assignment, create the new one, and record actor/reason.
4. Unify the user-management UI around controlled lifecycle and organizational actions.

This organizational audit is documentation/evidence; it does not itself authorize speculative production schema or PHP changes.

---

# 12. HR Dashboard Navigation Audit

## Canonical HR dashboard

`dashboard/hr_dashboard.php` is the **single canonical/main HR dashboard** and the authoritative HR navigation hub.

`modules/hr/index.php` has been converted to a compatibility redirect to the canonical dashboard, preserving legacy bookmarks/internal references without maintaining duplicate dashboard logic.

## User-facing HR navigation

The canonical dashboard links to:

- `employees.php`
- `employment_states.php`
- `attendance.php`
- `leaves.php`
- `payroll.php`
- `contracts.php`
- `payroll_policy.php`
- privileged payroll financial corrections via `payroll_reversal.php` and `payroll_integrity.php`

`bulk_attendance.php` is an API/JSON endpoint and HR `lib_*.php` files are internal libraries, not dashboard navigation items.

## Authorization

The HR dashboard is authorized for `hr_manager`, `hr_staff`, and `admin`.

`payroll_policy.php` remains available to the HR dashboard audience.

`payroll_reversal.php` and `payroll_integrity.php` are sensitive payroll/accounting controls shown only to `hr_manager` and `admin`; server-side authorization remains authoritative.

## UI decision

The seven normal HR navigation cards plus the privileged financial-corrections control are presented as one unified action-card grid. For privileged HR roles, eight controls form two balanced desktop rows of four.

No HR business logic, database schema, payroll workflow, leave workflow, attendance workflow, or authorization rules were intentionally changed by this navigation adjustment.

Latest implementation commit: `d20e83960cda8acb642e0035d1fe68aa1cb541e4`.

Earlier navigation commits:

- `9ca3e8ab26610702a256b9f495527890fa5eb71c` — compatibility redirect.
- `6b471457e8eaf33690da683189b6b9b5e4675c07` — canonical HR dashboard navigation.

## HR verification criteria

- `/dashboard/hr_dashboard.php` is the main HR dashboard.
- `/modules/hr/index.php` redirects to it.
- `hr_staff` can see Payroll Policy but not Payroll Reversal/Integrity.
- `hr_manager` and `admin` can see sensitive payroll controls.
- Privileged roles see the eight controls as two equal desktop rows.
- All dashboard links open their intended HR pages.

Architectural decision: there is one HR dashboard implementation. Future HR navigation changes belong in `dashboard/hr_dashboard.php`; `modules/hr/index.php` remains a compatibility redirect unless the architecture is deliberately changed and documented.

---

# 13. Authentication, Session, and Other Completed Cross-Cutting Work

Session fixation hardening was applied and runtime verified through login/dashboard/logout/re-login.

Commit: `9cdf89d30fdee147a919c7cc1056457b1b9d20f3`.

Accountant Staff Arabic dashboard encoding was solved and is closed. The current dashboard contains proper Arabic labels and must not be treated as an active encoding regression without new evidence.

FM dashboard treasury/admin-fee regression is fixed and closed. The treasury row is:

1. Cash `1100`.
2. Bank `1200`.
3. Electronic wallet `1300`.
4. Total treasury.
5. Administrative fees `4200`.

Fix commit: `783b160a60ce50f0f661a65a112aea7469979ca4`.

Supervisor sponsor ownership and sponsor-linked family access restoration is completed and preserved under commit `448fa0bfee7fdcd5ce3e7608304de6b1c7f9ba73`.

---

# 14. Protected Completed Evidence — Do Not Modify or Reuse

The following are protected audit evidence and must not be recreated merely to repeat old tests:

- TR-000014 / transaction `20`.
- TR-000015 / transaction `21`.
- TR-000016 / transaction `22`.
- JE-000027.
- JE-000026 / journal `37`.
- JE-VOID-TXN-22.
- Existing ACC1/nanny accounting fixtures and previously completed disbursement fixtures.
- Existing notification regression evidence.

Future tests must use new controlled data only when a genuine new test is required.

---

# 15. Current Open Audit Direction

## Active direction: Supervisor Module Audit

Continue page-by-page from the actual Supervisor dashboard/navigation and business responsibility model. The family-scope restoration itself is complete and must not be re-audited as unfinished.

Current governance items:

1. Formal organization-wide permission/action matrix.
2. Exact role/business scope for supervisor access to the general sponsor-request queue.
3. Controlled review of runtime schema synchronization such as `modules/sponsors/index.php` performing `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...` during normal page rendering.
4. Review supervisor sponsor/family/sponsorship routes for consistent enforcement of the authoritative scope rules.

Do not make speculative restrictions or schema rewrites for these parked items.

## Accounting parked work

- Targeted runtime verification of newly protected automated journal reference types.
- Remaining direct journal mutation callers.
- Cross-module accounting-history interaction review.
- Later original↔reversal/source navigation.

## Notification parked work

- Reliable caller evidence for `send_system_notification()`.
- Targeted source review only where evidence is reliable.
- Tests only for newly changed notification workflows.

## Organizational parked work

- Unified organizational assignment/history model.
- Controlled lifecycle transitions for non-supervisor organizational users.
- Organization-wide lifecycle/action matrix.

## Other parked work

- Same-page Bootstrap modal UX for missing/stale receipt feedback.
- Formal report/source/calculation catalog.
- Final notification event/recipient catalog where not already captured.
- Production preparation and deployment hardening.

---

# 16. Production and Data Status

The application is still under development. Development/test operational data is not to be treated as production financial data.

Production preparation remains governed by `docs/PRODUCTION_PREPARATION.md` and `database/production/prepare_production_database.sql`.

Arabic is the default language and English is the alternate language; stable key-based i18n remains authoritative.

---

# 17. Git and Local Safety Rules

Intentional local backup files that must not be deleted/reset/stashed/overwritten:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Commit `f7051ded6fe4c8e346cf7bcb08f48d0535945d01` was inspected and does not represent an active rollback of the tracked Accountant Staff dashboard.

Repository continuation must preserve local uncommitted work and must not reset/stash/delete the protected backup files.

---

# 18. Documentation Governance — SINGLE MASTER AUDIT

This file is the **single master audit record**.

The following former audit files are being retired because their audit information is now incorporated here:

- `AHL_EL_KHEIR_SYSTEM_REVIEW_AND_AUDIT.md`
- `ORGANIZATIONAL_LIFECYCLE_AUDIT_2026-09-03.md`
- `HR_DASHBOARD_NAVIGATION_AUDIT_2026-09-08.md`

The previous Accounting Audit and Notification Audit were already incorporated into the system-review record and are now part of this master audit as well.

The remaining documentation files are supporting/non-audit references only, such as:

- `AHL_EL_KHEIR_MASTER_STATUS.md` — high-level project status/START HERE summary.
- `CHATGPT_SESSION_INDEX.md` — short navigation index.
- `I18N.md` — internationalization reference.
- `PRODUCTION_PREPARATION.md` — production preparation procedure.

Do not create another audit file for ordinary continuation. Update this master audit after meaningful audit milestones.

---

# 19. Continuation Rule

Every meaningful milestone ends with:

`Code correct → behavior verified → documentation updated → exact next continuation point clear.`

This is a continuation of an existing project and existing audit.

**Never restart from zero.**  
**Never repeat completed tests without a regression reason.**  
**Never recreate protected fixtures unnecessarily.**  
**Never ask the user to manually edit repository files when the repository can be changed directly.**  
**Inspect current code/schema first, then make the smallest safe change.**


## DASHBOARD / NAVIGATION REVIEW — 2026-09-18

### Scope

Accounting audit is intentionally parked. This review covers dashboard UX, role-safe navigation, universal reporting access, and the current Administration/Staff/Social Media dashboard.

### Universal Reports

A universal Reports Dashboard was implemented at modules/reports/index.php, with role catalog/authorization centralized in modules/reports/report_registry.php. Sidebar report navigation was consolidated to one Reports entry. Direct report pages use the central guard.

The design rule is:
- reporting visibility is not the same as workflow authority;
- GM/VGM require broad organizational reporting visibility for decisions;
- FM retains financial workflow/control authority;
- VGM must not receive FM workflow actions/notifications merely because VGM can view management reports.

### Administration dashboard development

dashboard/staff_dashboard.php was previously a generic "under development" page. It was expanded for Administration/Staff/Social Media to show sponsor-request and winback operational indicators and recent sponsor requests.

Relevant implementation:
- sponsor request counts;
- contacted-request count;
- open winback count;
- uncovered-family count;
- latest sponsor-request table;
- role-relevant quick actions.

modules/administration/winback.php is the existing Administration winback workflow and was linked from the Administration sidebar/dashboard.

modules/sponsors/requests.php was updated to authorize administration and staff for the sponsor-request workflow.

### Schema evidence and corrections

The live database was inspected rather than guessing:

SHOW COLUMNS FROM sponsorships confirmed child_id but no family_id.

SHOW CREATE TABLE sponsorships confirmed:
sponsorships.child_id → family_children.id via foreign key fk_sponsorship_child.

SHOW COLUMNS FROM family_children confirmed family_id.

The children table does not exist.

The incorrect sponsorships.family_id queries were corrected in:
- dashboard/staff_dashboard.php;
- modules/administration/winback.php.

This is now the authoritative relationship for dashboard family sponsorship calculations:
families.id ← family_children.family_id ← family_children.id ← sponsorships.child_id

### Runtime status

The user tested the Administration dashboard after the corrections and reported:
- dashboard loads without errors;
- Winback no longer produces the previous schema error;
- the invalid direct Families link and unauthorized Reports shortcut were removed/repointed instead of bypassing authorization.

### Remaining dashboard work

The Administration dashboard remains OPEN / IN PROGRESS.

Next review items:
1. KPI semantics and usefulness.
2. Sponsor-request table/status/action UX.
3. Winback integration and role-appropriate actions.
4. Orphan-form navigation.
5. Role-specific differences between Administration, Staff, and Social Media.
6. Reports visibility according to the actual report registry.
7. Arabic labels/encoding.
8. Responsive/mobile layout.
9. Remaining schema assumptions and linked-page runtime behavior.

Do not repeat the completed sponsorship-family schema investigation unless new evidence indicates a regression.


### Winback dashboard/list UX refinement — 2026-09-18

The Administration dashboard Winback KPI links now use distinct anchors: open follow-ups use `#open-cases`, while uncovered families use `#uncovered`. In `modules/administration/winback.php`, the `أسر متاحة للتكليف حالياً` list was moved below `الكفلاء المتوقفون المؤهلون`, expanded to the full-width section, and its table header is sticky within a scrollable list area. The obsolete `القائمة الكاملة` self-link was removed because it only refreshed the same page. Relevant commits: `bbe945f10114e174081fc8e8f584bcac5633c704`, `8ddd2d6fb5b2e0c393e36100e4c3e964bfd36583`, `0bbb8ce2e39c508f6ec208a2029498fbddf24919`.


### Winback KPI destination and list UX refinement — 2026-09-18

The Administration dashboard no longer uses two anchors into the same Winback page for the two different KPI concepts. Open Winback follow-ups use the Winback open-cases section; uncovered families use a dedicated Administration page. The Winback available-family list was also refined with an action/view column and centered monthly-need values while retaining its current placement and sticky header.


### Administration sponsorship-safe family/orphan view — 2026-09-18

The dashboard/navigation review identified a business requirement: Administration must be able to review enough orphan/family information to make a complete sponsorship proposal and avoid later disputes about undisclosed sponsorship-relevant conditions, especially during Winback. That requirement does not justify unrestricted access to the full internal family case record.

A dedicated page was added:
`modules/administration/sponsorship_family_view.php`

The controlled profile reads only known existing fields from `families`, `family_children`, and `sponsorships`. It shows sponsorship-relevant family summary data and child information including health status, psychological state, education level, critical flag, current monthly sponsorship value, extra allowance, and the latest prior sponsorship amount/status/start date.

It does not expose direct family phone numbers, exact address, registration identifiers, family bank accounts, documents, internal family notes, or edit controls.

Navigation/authorization was aligned:
- available-family actions in `modules/administration/available_families.php` route to the controlled sponsorship profile;
- available-family actions in `modules/administration/winback.php` route to the controlled sponsorship profile;
- `administration` was removed from the unrestricted `modules/families/view.php` role list once the controlled page existed.

This keeps the business rule explicit: **sponsorship information visibility is broader than internal case-management authority, but narrower than unrestricted family-file access.**

Runtime acceptance is pending.


### Winback case lifecycle — reopening a previous decline — 2026-09-18

Business requirement: a sponsor may initially decline returning to sponsorship and later, after another contact, agree to return. The Winback workflow therefore must not treat `declined` as permanently closed.

Implemented behavior:
- `declined` cases show `إعادة فتح المتابعة`;
- reopening changes the same campaign row from `declined` to `open`;
- `closed_at` is cleared;
- the current user becomes the active handler;
- an `REOPEN` audit event is recorded;
- existing contact history remains intact;
- no duplicate Winback campaign is created for the sponsor.

This preserves one continuous campaign history while allowing a later contact cycle. The sponsor remains inactive until the normal `تأكيد العودة وتفعيل الكفيل` action is completed.

Implementation commit: `8ab5cbacdca806bd20c7afffb92dc1e826903aae`.

Runtime acceptance is pending.

# MESSAGING MODULE — ORIGINAL WORKING DESIGN RESTORED — 2026-09-18

The internal messaging UI is now restored to the exact version immediately before the user-identified redesign commit.

- Redesign commit used as the historical anchor: `285d390c00f983b82e8bb59248a555b7997cfd3e`
- Restored source: `9520bd3284bbf4060711f101be1f6518fbd6f087` — the immediate parent of `285d390...`
- Restoration commit: `baacaa5a9812ff4f89c498b2380623342c226d2e`
- Primary restored file: `modules/messages/index.php`

### Decision

The user rejected the later Gmail/custom visual redesigns and explicitly requested the original working design. The correct historical version was therefore restored exactly from the immediate parent of `285d390...`, rather than approximated through another CSS redesign.

**Do not redesign the messaging workspace again unless the user explicitly requests a new design.**

### Preserved functionality

The restored version retains the established messaging functionality and global application shell, including inbox/sent navigation, message list and conversation overlay, compose and reply, attachments, emoji button, font-size control, unread filters, mark-all-read, role broadcast, and the existing messaging backend actions.

The later CSS that hid the global application sidebar/header and converted messaging into a full-page Gmail-style workspace is no longer part of the restored version.

### Current status

**Messaging original-design restoration: COMPLETE / ACCEPTED by user.**

Future messaging work must preserve this visual baseline and address only explicitly requested changes.


# 16. Audit Log Retention and Audit-Detail Presentation — 2026-09-19

## 16.1 Controlled retention cleanup

`modules/logs/audit.php` now supports administrator-only bulk deletion of audit records within an explicit inclusive date range.

Control boundary:
- Admin may delete selected audit records.
- General Manager may view/filter audit records but cannot delete them.
- Both **من تاريخ** and **إلى تاريخ** are required.
- The server validates the YYYY-MM-DD values and requires start <= end.
- The selected dates are inclusive.
- A required confirmation checkbox and a final browser confirmation are both required.
- The implementation counts matching records before deletion and verifies the range is empty afterward.
- If records remain, the UI reports incomplete cleanup rather than claiming success.

The final syntax correction was committed as:
`4b2bdcb75faf0dc6c004c48c14fb9e0d44ac4b46` — Fix audit log cleanup parse error.

The user subsequently confirmed the audit page opens and works.

## 16.2 Audit detail presentation

The `old_values` and `new_values` fields contain JSON audit evidence. They are **not SQL code**. To keep the audit table usable, these payloads are collapsed by default behind **عرض التفاصيل** and expand on demand. Arabic JSON remains readable and safely escaped.

Relevant UI refinement:
`ff7b35eacc7059b0052e80dad1c8622ce638efb9` — Improve audit log detail display.

## 16.3 Cleanup history

The cleanup was progressively hardened through:
- `88c721d2da4c1b27004531e9368b2f5cb124c8e3` — initial admin cleanup
- `42f21d8ee7683057ce473f3c0b42b31cd5777a55` — confirmation and verification hardening
- `13c849f7cc77d7445f2e372da9e7418850d87682` — accurate count reporting
- `354a149b75bd8a26716358bab24adb8379dff998` — explicit date-range UX
- `4b2bdcb75faf0dc6c004c48c14fb9e0d44ac4b46` — syntax-error correction

**Status: COMPLETE / CLOSED at current boundary.**

Do not delete, recreate, or modify historical audit evidence outside an explicitly selected retention range. Do not treat `old_values` / `new_values` as SQL.


# 17. Additional Sponsorship Searchable Selector — 2026-09-19

The direct additional-sponsorship workflow in `modules/families/orphan_profile.php` is preserved. Its dedicated endpoint `modules/families/sponsor_search.php` was refined in commit `46c3b0213a2e769f07158214d87575c39ebaade5` so multi-word sponsor-name searches narrow by corresponding name-word prefixes rather than using one broad contains match for the whole phrase.

Examples of the intended behavior:
- `أمل` → first name word begins with `أمل`;
- `أمل ب` → first word remains `أمل`, second word begins with `ب`;
- `أمل بيومي` → the first two name words match the typed sequence;
- sponsor-code queries retain partial/contains matching.

Security/eligibility controls remain server-side: authenticated role check, Supervisor Letter + Gender scope, final `supervisorCanAccessSponsor()` validation, active sponsor status, and exclusion of existing active/paused sponsorships for the same orphan.

Runtime evidence on 2026-09-19 confirms the actual additional-sponsorship submission path worked for child `234`: **ابراهيم تاج السر ابراهيم**, sponsor code `IMP-SP-002363`, was added successfully at `2,000.00 ج.س` from `2026-09-19`, while the existing **مؤيد محمد احمد محمد** sponsorship remained active. This confirms sponsorship creation, not yet the separate autocomplete keystroke behavior.

**Status:** implementation complete; sponsorship submission runtime-confirmed; multi-word autocomplete runtime confirmation pending explicit keystroke testing.

## 18. Immediate Next Audit Direction — Administration Dashboard

The next task is the already-open UX/functional review of `dashboard/staff_dashboard.php` for Administration/Staff/Social Media. Do not restart the dashboard or repeat the completed sponsorship-family schema investigation. Inspect the current code and linked-page authorization first, then review KPI semantics, sponsor-request table/actions, Winback integration, orphan-form navigation, role separation, Reports visibility, Arabic labels/encoding, responsive behavior, and any runtime issues.


### Administration dashboard review — first linked-page finding — 2026-09-19

Repository inspection of `modules/administration/winback.php` found request-time schema mutation code that contradicted the documented runtime-DDL cleanup rule. The page was dropping legacy blacklist triggers/table and creating `winback_campaigns` / `winback_contacts` on normal page requests.

This has been corrected narrowly:
- `modules/administration/winback.php` no longer creates, drops, or alters schema during a web request.
- Explicit migration added: `database/migrations/2026-09-19_winback_schema.sql`.
- Implementation commits: `67576f02e2642ec018d5aeb053f6db75dae925bb` and `6ff62b70528d7a10738a73e90250be1b937ba7e2`.

**Runtime status:** code correction is committed; local migration application and Winback runtime verification are still pending. Do not mark the Administration dashboard review complete until the migration is applied and the linked Winback workflow is tested.

### Administration dashboard review — linked-page authorization finding — 2026-09-19

`dashboard/staff_dashboard.php` provides an **استمارات الأيتام** quick action for `administration`, `staff`, and `social_media`. Inspection of the target `modules/families/orphan_forms_index.php` showed its existing `$viewRoles` list omitted `staff`. This created a concrete navigation/authorization mismatch: Staff could see the dashboard action but the target page did not authorize the role.

**Fix:** added `staff` to `$viewRoles` only. Existing Staff restrictions remain intact: `$canEdit` is still limited to `admin`, `vice_general_manager`, `supervisor`, and `nanny`; `$canSeeFinancial` is still limited to `admin`, `vice_general_manager`, `general_manager`, `supervisor`, and `nanny`.

Commit: `223c462528cc0e69d62bcf5f76094cea6ce821e2`.

**Runtime verification:** pending user pull/test.

### Administration sponsorship-family header visual correction — 2026-09-19

The sponsorship-safe family profile child/orphan section header was styled directly in `modules/administration/sponsorship_family_view.php` using the application's actual `--navy` variable. The earlier `assets/css/style.css` change could not affect the rendered page because `includes/header.php` does not load that stylesheet.

Commit: `2e4673a9a432e80ffb9cdccddadb4cbffd08b450`.

**Runtime verification:** user confirmed the visual result is correct.

### Winback POST-action authorization hardening — 2026-09-19

During linked-page security review, three concrete workflow authorization gaps were found in `modules/administration/winback.php`: submitted sponsor IDs for `open_case` were not independently checked against queue eligibility; `add_contact` could target a campaign outside the active workflow states; and `mark_declined` could update a campaign without checking its current state.

The handlers now enforce the corresponding server-side conditions before mutation. The `open_case` check mirrors the queue's eligibility rule; contact logging and decline are restricted to `open`/`contacted` campaigns.

Commit: `776da4470010d27c8758fd116843d3d06f9c94b9`.

**Runtime verification:** pending.


### DASHBOARD REVIEW — Sponsor-request workflow actionability — 2026-09-19

Repository inspection found a concrete UX/functional gap in `modules/sponsors/requests.php`: the dashboard and request queue displayed the existing `new`, `contacted`, `converted`, and `lost` states, but the request page only provided conversion and had no normal UI action to move a live request from `new` to `contacted` or to close it as `lost`. This made the Administration/Staff/Social Media dashboard's contacted-request KPI dependent on status changes outside the visible workflow.

The request workflow was tightened without adding a new route or changing the database schema:
- `new` requests can now be marked **تم التواصل**.
- `new` and `contacted` requests can be closed as **مغلق / لم يكتمل**.
- conversion remains available for `new` and `contacted` requests.
- converted/closed requests no longer expose mutation controls.
- every status mutation is POST + CSRF protected and server-side validated against the current request state.
- Arabic and English labels/confirmation messages were added to the existing sponsor-request language files.

Commits:
- `c03b15cbc1830437657edd8bb2aed26bf60e2b64` — Make sponsor request statuses actionable.
- `65037e97c5acef92caf5290e9f8d8cb95be20d99` — Add Arabic sponsor request status action labels.
- `03eaf05faec5c90e4d61041ef04f83d03a5b6e2e` — Add English sponsor request status action labels.

**Runtime verification required:** after pulling current `main`, open the sponsor-request queue as Administration or Staff and verify a new request can be marked contacted, a new/contacted request can be closed, converted requests remain complete/read-only, and the dashboard contacted/new counts reflect the changed statuses. Do not create unnecessary test data if existing requests can exercise the workflow.


## Sponsor Request Conversion UX — 2026-09-19
- `modules/sponsors/requests.php` commit `637639eeb6d4f5dd1a47dbe9a04bcede067423ef` removes the always-visible sponsor gender selector from each request row.
- Gender is now requested only when the user explicitly clicks **تحويل**; a conversion modal carries the request ID and requires male/female before submitting the existing server-side `convert` action.
- No database/schema changes were made.
- Runtime verification is pending user confirmation.


## Sponsor Request Gender Workflow — 2026-09-19
- Live `sponsor_requests` schema confirmed `gender varchar(10) NULL`; no schema migration was required.
- Commit `2b95e6bf40f8f0520c0e778ca5eddb7caa2d6553` moves gender capture into the Add Sponsor Request form, validates it server-side, stores it in `sponsor_requests.gender`, and removes the conversion-time gender prompt.
- Conversion now reads the stored request gender and copies it to `sponsors.gender`; legacy requests with missing/invalid gender are blocked from conversion with a clear message rather than guessed.
- Runtime verification is pending user confirmation.


## Sponsor request deletion + parse-error correction — 2026-09-19

- Fixed the unmatched closing brace in `modules/sponsors/requests.php` that caused the PHP parse error at line 67.
- Added a server-side **Delete Request** action for sponsor-request records only while they remain `new` and have no `assigned_supervisor_id`.
- The deletion is intentionally blocked once the request has been marked `contacted` or has been assigned/transferred to a supervisor. The server re-checks these conditions; hiding the button is not the authorization boundary.
- Added Arabic/English confirmation labels for the permanent deletion action.
- No database schema change was made.
- Commit: `bf08ce524cfe8065b61e8234395242abf8940980`.
- Runtime verification is still pending; do not mark this change as tested until the user confirms the local page loads and the eligible/ineligible delete cases behave as expected.


## Sponsor request deletion verification + dashboard KPI actionability — 2026-09-19

- Runtime verification completed successfully: the sponsor-request page loads without the previous parse error; eligible new/unassigned requests can be permanently deleted; deletion is blocked once the request is contacted or assigned/transferred.
- The deletion implementation remains server-side protected and uses POST + CSRF. No schema change was made.
- Follow-up dashboard UX improvement: Administration/Staff/Social Media dashboard sponsor-request KPI cards now open the sponsor-request queue filtered to the corresponding status (`new` or `contacted`). Recent-request row actions also open the queue filtered to that row's current status.
- `modules/sponsors/requests.php` now accepts a validated GET `status` filter for `new`, `contacted`, `converted`, and `lost`, with an explicit all-requests option.
- Commits: `de34e1ff36f5fc06fbadc29acb6553a049f807e7` (status filtering), `bdb698d8d6fc0df3e50f8a628a0333a5ac08c9e3` (dashboard KPI/action links).
- Runtime verification of this new dashboard/filter change is pending.

### Current next review area
Continue the Administration/Staff/Social Media dashboard audit after verifying the new sponsor-request status filtering. The remaining documented review areas are Reports visibility, sidebar/dashboard consistency, role differences, Arabic/encoding/responsive UX, KPI semantics, and remaining runtime/link checks. Do not reopen completed Winback, orphan-form authorization, sponsorship-family header, sponsorship autocomplete, or accounting/HR audits unless a regression is found.


## Sponsor request filtered queues + reopen workflow — 2026-09-19
- The Administration/Staff/Social Media dashboard KPI links continue to use the single sponsor-request workflow page, but the page heading now reflects the active validated status filter so `status=new` and `status=contacted` are visibly distinct queues.
- Closed/incomplete sponsor requests (`status='lost'`) now expose an **إعادة فتح** action.
- Reopen is POST + CSRF protected and server-side restricted to records whose current status is `lost`.
- Reopened requests move to `contacted`, not `new`, so an old request cannot regain the pre-contact deletion privilege.
- No database schema change was made.
- Implementation commits: `92030f7604308f8d062ceae3d873dd12918c02bd`, `0491ee8c7982ac9fd8c50320d5eae064a676f211`, `49fbce22d917ecaf5c8ab5e0954d6068e6972f73`.
- Runtime verification is pending user confirmation.


## Dedicated sponsor-request dashboard views — 2026-09-19
- The Administration/Staff/Social Media dashboard no longer routes the two sponsor-request KPI cards to the same workflow URL with different query parameters.
- New sponsor requests now open the dedicated view `modules/sponsors/new_requests.php`.
- Contacted sponsor requests now open the dedicated view `modules/sponsors/contacted_requests.php`.
- Both dedicated views reuse `modules/sponsors/requests.php` as the single business-logic/rendering implementation through a validated forced status, avoiding duplicated workflow code.
- POST actions preserve the dedicated queue context where applicable; reopening a lost request from the contacted queue remains contextual.
- No database schema change was made.
- Implementation commits: `5a464785702dc4fee83268c497beefc29a0eb2a7`, `8591b4865a9fa790b7ba9f0b75a00dcb9b71072`, `b33fd2dfc39175459da749c600b2e3650e3f424e`, `4facf6602b07fcc2bbab61498ee72e9daf58f8ff`.
- Runtime verification is pending user confirmation.
- After verification, continue with the remaining dashboard audit areas: Reports visibility, sidebar/dashboard consistency, role differences, Arabic/encoding/responsive UX, KPI semantics, and remaining runtime/link checks.


## Dedicated sponsor-request dashboard views — runtime verification — 2026-09-19
- User confirmed the dedicated dashboard-view change passes runtime verification.
- The two KPI cards now behave as separate destinations: new requests use `modules/sponsors/new_requests.php`, and contacted requests use `modules/sponsors/contacted_requests.php`.
- This checkpoint is complete. No schema changes were made.
- Next audit area: Reports visibility and sidebar/dashboard consistency for Administration, Staff, and Social Media, followed by role differences, Arabic/encoding/responsive UX, KPI semantics, and remaining runtime/link checks.


## Administration dashboard UX — Arabic/encoding and responsive review — 2026-09-19

Reviewed the current `dashboard/staff_dashboard.php`, `includes/sidebar.php`, `includes/header.php`, and sponsor-request Arabic/English language files. The inspected source files contain valid Arabic text and the sponsor-request language files are UTF-8; no concrete encoding corruption was found in this dashboard area. The dashboard's main request table is already responsive through Bootstrap's `table-responsive`, and the KPI grid uses responsive column classes.

A concrete responsive defect was identified in the shared header: `.qa-top-bar` was explicitly non-wrapping and contained variable-length quick-action labels plus user controls. On narrow screens this could force compression or horizontal overflow. The fix in commit `4536623afcea14852aac5d1ea1d3b2ec2f70e0b8` adds a <=768px mobile layout that stacks the two header groups, gives them full width, centers their contents, and removes the desktop auto margins from the user-control group. This is presentation-only; no role, authorization, SQL, or workflow logic changed.

**Verification state:** code fix committed; runtime mobile-width verification remains pending.


## Mobile navigation responsive audit — 2026-09-19

### Final mobile navigation refinement

After real-device testing over the local network, the user confirmed the mobile navigation is now substantially improved and usable. The dedicated fixed `☰ القائمة` control is visible on the phone and opens the existing off-canvas sidebar.

A small follow-up UX refinement was added in commit `af03d9dd775930be865c2b03a470665f3dab610b`: while the mobile sidebar is open, the page background is locked against scrolling. This prevents accidental horizontal/vertical movement of the underlying page while the drawer is active. Closing the drawer restores normal page scrolling.

The user considers the current mobile layout acceptable for this audit phase. A more app-like mobile redesign is intentionally deferred to a later phase.

## Mobile navigation responsive audit — 2026-09-19

### Finding

The previous responsive header adjustment was insufficient. On narrow screens the sidebar still remained a 260px flex child, so the main content was compressed and the sidebar visually consumed most of the viewport. This is a concrete shared-layout defect, not merely a visual preference.

### Corrective implementation

The shared layout now treats the sidebar as an off-canvas drawer below 992px. The drawer slides from the appropriate side for RTL/LTR, the main area expands to the full viewport, a dark overlay prevents interaction with the page behind the drawer, and a dedicated mobile menu button provides the navigation entry point. Drawer state is closed by the overlay, Escape, navigation links on mobile, or resizing back to desktop.

No role, authorization, SQL, or workflow behavior was changed.

Code commits: 726d766f8075b2a55a0dcfd0ccc1cb5ec75c64d1, 0530c69c6e80b320577ac50dcd852ea4633431f6.

### Verification boundary

Real-device verification is still pending. Chrome device emulation is useful for an initial check, but this fix must not be marked runtime-verified until the user confirms the actual mobile layout.

## Shared floating sidebar UX — 2026-09-19

The shared sidebar floating navigation and its edge-mounted handler were refined; the handler is intentionally treated as temporary UX, not a closed final design.

The shared navigation remains a fixed floating/off-canvas sidebar. The handler is flush with the page edge, uses the shared navy header/sidebar color, has rounded inner corners, and was narrowed to 28px on desktop/mobile. The current implementation is in `includes/header.php`.

Commit: `97f5d394ee0370be436351a4d4696bbf5c0b5a5d` — **Refine sidebar handler width and page-edge alignment**.

The user explicitly considers this **temporary done** rather than a final visual design. Do not spend further work refining the handler unless the user returns to it. No database, schema, authorization, role, or workflow logic changed.

### Immediate continuation

Move to the next system-wide fix requested by the user. Inspect the relevant documentation and current repository code before making changes; do not reopen closed audit areas or repeat passed tests without regression evidence.


## System-wide Back/navigation audit — 2026-09-19

### Scope

The audit covers user-facing PHP application pages outside `TCPDF/`. TCPDF is explicitly out of scope for Back/navigation fixes. Infrastructure-only PHP is not treated as normal user navigation.

### Findings and fixes started

- Existing hard-coded Back links were found on contextual detail pages that always returned to page 1/default indexes.
- List/detail flows were found where pagination, search, and filters were not propagated into the detail URL.
- Sponsor navigation already had a return mechanism; its whitelist omitted `link_status`, so that filter could be lost on return. This was corrected.
- A shared `akGoBack(fallback)` helper was added to the common footer. It only uses browser history when the referrer is same-origin; otherwise it follows the supplied application fallback.
- Families, Sponsors, Sponsorships, Projects, orphan/child pages, returned Transactions, Search Center results, and Accounting Disbursements received the first contextual-navigation corrections.

### Security/navigation rule

Do not implement a blind global `history.back()` as the only destination mechanism. Contextual Back actions must have an application fallback, and user-controlled return targets must not become unrestricted external redirects.

### Remaining audit boundary

Continue reviewing the remaining user-facing modules for missing contextual Back actions and context loss. Do not modify TCPDF. Do not reopen completed business/authentication/accounting controls merely because a page links to them.


### 2026-09-19 — Back-button acceptance rule expanded
The user clarified that the required behavior is broader than detail pages: **every user-facing page gets a Back button unless it is a dashboard**. Therefore module index/list pages are no longer treated as automatic exceptions. The implementation uses akGoBack(fallback) with same-origin history plus an explicit application fallback; it does not rely on blind global history.back().

The second implementation batch added Back actions to the remaining user-facing HR, Users, Settings/System, Supervisors, Transactions, Reports, Accounting, Families, Sponsors, Sponsorships, Projects, Departments, Search, Notifications, Messages, and Logs pages. API/JSON endpoints, authenticated file streams, redirect-only compatibility entries, and printable/stream-only output are not treated as ordinary HTML pages.


### 2026-09-19 — final navigation sweep additions
A further sweep covered remaining user-facing HR integrity and system maintenance pages that render HTML. API/JSON actions, file streams, redirect-only compatibility endpoints, and print-only output remain excluded because they are not navigable HTML pages. The acceptance rule remains: every user-facing HTML page has Back unless it is a dashboard.


## 2026-09-19 — Nanny legal-age alert integration

The shared legal-age alert is now included on the Nanny Dashboard. Its query is server-side scoped to families assigned to the logged-in Nanny via families.nanny_id, while GM/VGM/Admin continue using the same shared implementation. The alert was verified to initialize reliably and show once per authenticated PHP session using sessionStorage keyed from the PHP session ID. The existing child suspension workflow was inspected and confirmed to set family_children.is_active = 0 and pause linked active sponsorships; therefore suspended children disappear from the shared legal-age popup after refresh on all dashboards using it. The Nanny Dashboard's separate near-legal-age card also uses is_active = 1. Temporary test DOBs were restored. No schema change was made.

Commits: 474741e4f094b264e033af89f690838765c7e4a5, 44e873fa548aa15142a4d3548437bae8371d1c6d, ab489dfa5ab5c00d52b76d3e0a8a97be2d2e6cd8. Documentation update: 8a3a963d01703db115faacccd23247f55016c7f1.


## 2026-09-19 — Top-left Back button enhancement

The completed navigation audit remains closed with its original acceptance rule. A presentation enhancement was added centrally in `includes/footer.php`: pages that already contain the audited contextual Back link now receive a second Back button at the top-left of the page content. The generated button is a clone of the existing contextual control and therefore calls the same `akGoBack(fallback)` logic. No second navigation mechanism, fallback rule, authorization rule, or page-specific navigation logic was introduced. The existing bottom Back button remains unchanged. Dashboards and the previously excluded API/JSON, file-stream, redirect-only, and print-only outputs remain unaffected.

Commit: `0557218daa28d2ce78ff8e9f0bbaf8bacd2f0769` — Add top-left Back button to pages with contextual Back.


## 2026-09-19 — Shared header action-toolbar redesign

The shared header redesign is now accepted at the current visual boundary. The organization name remains the header anchor, with centered controls directly beneath it using the available horizontal space. Role-aware quick actions and system controls remain directly visible. The current-user identity is a restored compact dropdown containing Profile, Settings, and Logout. The visible group labels **الوصول السريع** and **النظام** were removed; the controls remain grouped structurally without those text labels.

The existing global search, language switching, conditional password-recovery control, role-aware actions, sidebar behavior, and system-wide Back controls remain unchanged. Sidebar remains closed by default and must not be disturbed unless a regression is reported.

Code commits:
- ca3a30f7c26b3196b6a1a428a98ab5c8a06c2874 — initial centered header action toolbar.
- 894664fac80374f8d86c7192d8962a099b557719 — restore current-user dropdown.
- 2729522546929b09488ac244857d388ff8d51b1a — remove the visible action-group labels.

Runtime checkpoint: user confirmed the final header appearance is acceptable after removing the two labels. No database/schema, authorization, workflow, sidebar, or Back-navigation behavior was changed.

Next continuation point: proceed with the next system-wide/dashboard change requested by the user. Inspect the current documentation and repository state first; do not reopen completed audits or repeat passed tests without regression evidence.

## 2026-09-20 — Shared header role-action regression fix

A regression was found in the accepted shared header redesign: the shared role-aware quick-action map in `includes/header.php` had been reduced to an empty map, so roles such as Financial Manager lost their role-specific header actions even though the header layout itself remained visible. In addition, `dashboard/supervisor_dashboard.php` and `dashboard/hr_dashboard.php` still contained local CSS overrides that forcibly hid the shared action container.

The shared implementation is now corrected:
- role-aware header actions are restored centrally in `includes/header.php` for the currently supported roles and aligned with current role navigation/authorization rather than reviving obsolete links;
- FM header actions now include the current journal, projects, opening balance, monthly disbursements, assigned nannies, financial review queue, and Reports destinations;
- the Supervisor and HR dashboard-specific `display:none !important` overrides were removed so those dashboards no longer suppress the shared header actions;
- the accepted organization-name anchor, centered controls, user dropdown (Profile / Settings / Logout), Global Search, language switch, conditional password-recovery control, closed-by-default sidebar, and Back implementation remain unchanged.

Commits:
- `f38575e594c52978af79502a9707f9a078ba32be` — restore role-aware shared header actions.
- `0a0946bdbab09c3f2c6aeef0914378c9c153fa35` — keep shared header actions visible on Supervisor dashboard.
- `95d706de3bad5dbf6bc9a0ac248a9fc3c26f45c7` — keep shared header actions visible on HR dashboard.

Static repository verification confirmed the role-action map is no longer empty and no inspected primary dashboard contains a local rule that hides the shared header actions. Runtime verification is required after pulling current `main`, starting with FM and then spot-checking another dashboard role.


## 2026-09-20 — Back-button consistency corrective audit

The earlier Back/navigation implementation established akGoBack(fallback) and attempted to provide a top-left and bottom-right Back control on audited pages. Runtime review has now identified four concrete consistency classes: **single button, duplicate buttons, missing buttons, and Back actions that interfere with page/sidebar behavior**.

The audit is therefore reopened as a corrective phase, not as a restart of the navigation work. The target is exactly two controls per applicable user-facing HTML page: **top-left + bottom-right**, with one safe shared navigation mechanism and preserved originating context.

### Module-by-module method
- inspect the selected module's complete user-facing HTML page set;
- identify where each Back button is produced (page-local markup, shared footer, or both);
- trace akGoBack() and any page-specific JavaScript before editing;
- classify each page as missing / single / duplicate / correct / broken-on-navigation;
- fix the smallest responsible layer and avoid duplicate global generators;
- verify Back navigation plus sidebar/header interaction after navigation and refresh;
- commit the verified module before moving to the next module.

### Acceptance criteria
A module is not considered complete until every applicable page has exactly two Back buttons in the required positions, no duplicate generator is responsible for them, the Back destination preserves relevant list/search/filter context, and the shared sidebar still opens normally after navigation. Dashboards and previously excluded non-HTML endpoints remain out of scope.

This corrective phase supersedes the earlier "audit complete" wording until the affected modules are re-verified.


# 2026-09-21 — Repository changes review: i18n and dashboard visual consistency

A repository-level review was completed for the six commits added after `642f7397f48c8ddb84315130ae32cf22847476b1`. The changes are coherent and confined to shared UI/i18n behavior plus removal of an obsolete dashboard file.

## Internationalization implementation now present

The repository now has a compatibility layer for legacy/static Arabic UI text while stable-key translation remains authoritative. The implementation includes:

- `lang/bridge/ar_to_en.php` — large server-side Arabic→English compatibility bridge.
- `lang/bridge/ar_to_en_js.php` — smaller browser-side dictionary for JavaScript/native dialogs.
- `lang/bridge/ar_to_en_patterns.php` — patterns for text containing live values.
- `lang/bridge/en_to_ar.php` — page-scoped English→Arabic compatibility mappings.
- `lang/common_fixes_ar.php` and `lang/common_fixes_en.php` — common compatibility corrections.
- `config/lang.php` — cached dictionaries, server/browser bridge loading, punctuation-tolerant core lookup, and reverse page-scoped lookup.
- `assets/js/language.js` — matching punctuation-tolerant lookup and translation wrappers for native `alert()`, `confirm()`, and `prompt()` dialogs.
- `tools/i18n_gap.php` — CLI gap-report tool.

Stable-key catalogs remain the preferred source for new UI text. Compatibility bridges are a migration layer and must not be mistaken for a replacement for stable-key `t()` usage.

## Dashboard/header visual consistency

The shared header now provides a role-aware Home shortcut on non-dashboard pages. Dashboard pages are marked with `body.ak-dashboard`, allowing shared CSS to normalize section titles across the ten dashboards to a blue bar with white text. A subsequent shared rule also normalizes applicable `.card-header.bg-white`, `.card-header.bg-light`, and inline `#1b4d8f` section headers on other pages.

Outline buttons inside these blue title bars are explicitly kept white/contrasting rather than inheriting the blue title color. This is a presentation rule only; it does not change workflow authorization.

## Obsolete dashboard removal

`sudo_dashboard.php` was removed from the repository in the reviewed change set. It should not be treated as an active dashboard or used as a navigation target. Any future reference to it should be verified against current routing before being restored.

## Evidence boundary

This review establishes what is present in the repository. It does **not** mark the new i18n coverage, Home shortcut, or section-title changes as runtime-verified. Local runtime testing remains the authority for behavioral/visual acceptance.

# 15. Hosting Database Compatibility Rule — 2026-09-21

## Permanent rule: no database triggers or views

> **No MySQL/MariaDB triggers or views may ever be added to the Ahl El Kheir database again.**

Business logic that would otherwise be implemented through database triggers must be implemented and enforced in the appropriate procedural PHP workflow, with server-side validation and authorization preserved. Reporting/query logic that would otherwise use database views must remain in PHP/query code using the real underlying tables and relationships.

Stored procedures, stored functions, and scheduled database events are likewise not part of the hosting-compatible application architecture unless this rule is explicitly revised after a future hosting/platform decision.

### Hosting migration requirement

The database export used for hosting must contain tables, columns, indexes, constraints, required reference data, and application data, but must not attempt to create unsupported triggers or views.

The original development database must not silently lose business protections when database triggers are removed. Before removing or replacing any trigger-derived behavior, inspect the trigger definition and identify the corresponding PHP workflow that must enforce the same rule.

### Current 2026-09-21 inventory checkpoint

The current repository search found no application PHP page containing CREATE TRIGGER or CREATE VIEW, and the current database/ahl_el_kheir.sql export now contains no CREATE TRIGGER or CREATE VIEW statements. The previously observed attendance trigger import failure therefore belongs to the earlier database export and is not to be reintroduced into a future dump.

This rule applies to all future schema changes and migrations. A migration that introduces a trigger or view is considered incompatible with the project's hosting baseline and must not be added.


# 2026-09-21 — Projects Module Deep Audit Started

A dedicated page-by-page/static review of the Organization Projects module has started. The current repository was inspected rather than relying on older audit assumptions. The dedicated execution plan is documented in `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md`.

## Current findings

High-priority findings:
1. New-project creation is a multi-table write sequence without one enclosing transaction; partial project state is possible if a later write fails.
2. `modules/projects/index.php` calculates posted funding allocations differently from `modules/projects/project_lib.php`; the portfolio query does not exclude allocation rows already represented by posted transactions, while the shared helper does. This can produce inconsistent funded totals.

Medium-priority findings:
3. `modules/projects/view.php` performs lifecycle synchronization and closure-total updates during GET rendering, so a read can mutate `project_lifecycle`.
4. Project-code generation uses a count-based sequence and requires schema/constraint verification before deciding on a replacement.
5. General project editing can alter important project/financial basis fields after workflow activity; the intended business rule must be verified before adding restrictions.
6. Several project POST handlers require a full server-side validation review independent of HTML/JavaScript constraints.
7. Project document upload needs an explicit application-level size limit consistent with current deployment policy.
8. Rejection/return actions and required reasons require field-level server-side validation review.
9. Budget/funding/approval multi-step mutations require transaction-boundary review.
10. Project-expense separation of duties requires explicit role-matrix verification before any authorization change.

## Audit boundaries

No Projects code was changed as part of this first-pass audit. No schema change was made. Existing test evidence is protected. The permanent no-trigger/no-view/no-stored-routine rule remains in force.

## Planned sequence

Static completeness → schema/reference verification → smallest safe data-integrity fixes → validation/authorization hardening → runtime workflow → accounting reconciliation → documentation/acceptance.

Every fix must be runtime-verified before being marked complete. Existing Back/sidebar/header behavior is preserved unless a Projects-specific regression is demonstrated.


## Projects remediation update — 2026-09-21
- Atomic new-project creation added in `modules/projects/form.php`.
- Portfolio funding totals aligned with the authoritative posted-allocation exclusion rule.
- Project detail GET no longer mutates lifecycle/closure totals; closure synchronization remains in the closure POST workflow.
- Added server-side validation for project team sections, labor enumerations, progress range, beneficiary inputs, document rejection reason, and a 10 MB project-document limit.
- Runtime certification remains pending; project-code uniqueness/concurrency still requires verification against the actual deployed schema.
- Remediation commits: `251c7c4`, `5bb36f8`, `af59a1b`.


## 2026-09-21 — Projects FM → GM notification gap fixed

Runtime testing of Project 6 confirmed that Project Manager submission now notifies the active Financial Manager and the notification opens correctly. The next workflow gap was then confirmed in the repository: Financial Manager approval changed `submitted` → `fm_approved` but did not notify the General Manager.

The fix reuses the existing event-aware notification writer in `modules/accounting/lib_transaction_review.php`, targets active users with the existing `general_manager` role, uses project ID/reference type `project_fm_approval`, includes the project name/code, and links to the existing project view. Notification failure is isolated from the completed FM approval. No schema change was introduced.

Code commit: `b7a655512c1234fb1459c068aba07e494355f023` — **Notify GM when project is financially approved**.

Runtime verification is pending; Project 6 is the controlled test case for FM approval → GM notification.


## 2026-09-22 — Projects workflow audit checkpoint

Projects remediation Batch 1 is now implemented: pre-approval budget/budget-line preparation and funding allocation preparation are server-authorized to the Projects Manager, while the FM pre-approval funding UI is read-only and reserved for financial review/approve/reject. Runtime verification remains pending. See `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md` for the detailed change and remaining accounting/payment audit items.


## 2026-09-22 — Projects budget-view UI clarification

The project detail **الميزانية** section was reviewed against the current workflow. The inline budget-line entry controls were duplicating preparation/editing functionality already available through the project editing flow, while the short **اعتماد** label could be mistaken for FM's financial project approval.

The UI was corrected without changing business logic:
- removed the duplicate inline budget-line add form from the detail page;
- renamed the budget-version action to **اعتماد نسخة الميزانية**;
- added an explanatory note separating budget-version preparation/approval from FM's **اعتماد مالي** action.

The underlying distinction remains:
- **اعتماد نسخة الميزانية** = budget-version state change during preparation;
- **اعتماد مالي** = FM's project-level financial approval;
- **اعتماد نهائي** = final executive approval.

Implementation commit: `85dfa3f65725ade865be80824a7d340cdcbcf1db`.
Runtime verification remains pending.

## 2026-09-22 — Budget approval control removed from detail page

A follow-up workflow review corrected the previous budget-view change. The detail-page explanatory note and the **اعتماد نسخة الميزانية** control were removed because the page should not expose a separate approval action that can be confused with the FM financial approval workflow. The obsolete `approve_budget` POST branch in `modules/projects/view.php` was also removed. No database schema or accounting logic was changed.

Implementation commit: `6f02a8ed9e72a316c470e886b3c7658c83a24593`.

## 2026-09-22 — Projects dashboard metric correction

The Projects Manager dashboard `active_budget` metric was corrected to use the approved project budget for active/reopened projects, with `target_amount` only as fallback when no approved budget exists. This aligns the dashboard with the Projects portfolio calculation. No workflow, authorization, schema, or accounting behavior changed.

Implementation commit: `561c6d9f49ee5381e8846490ca57f5eb5b6b1816`. Runtime verification remains pending.

## 2026-09-22 — Project form repeatable detail fields

The project form now supports repeatable records for government requirements/fees, implementing partners, procurement methods, and contact details. Government fees are stored per requirement rather than as one unrelated total. The implementation uses new migration-managed child tables, preserves legacy scalar fields for compatibility, and provides user-controlled **إضافة** buttons for each repeatable group. No triggers, views, or runtime DDL were introduced.

Migration: `database/migrations/2026-09-22_project_repeatable_details.sql`.
Form commits: `06af8a25e460194ddef392ceb21f43b76e0636e1` and `daae330a0d4ede9c04d128cf81a8c2536e47111a`. Runtime verification remains pending.


## 2026-09-22 — Project repeatable-detail view support

Following the repeatable project form-field migration, `modules/projects/view.php` was audited. The edit form already handled the new child records; the detail page did not yet display them and still exposed only the legacy single **الشريك المنفذ** value.

The project view now displays the new read-only records for:
- government requirements with their individual fees and listed-fee total;
- implementing partners and roles;
- procurement methods and notes;
- contact records and their available contact details.

No new schema, trigger, view, runtime DDL, workflow action, or authorization change was introduced.

Implementation commit: `9cff782107e4c04abe7803d7d84830930dcc933b`. Runtime verification remains pending.


### 2026-09-22 — Project form category selector and budget activation guard
- Improved **تصنيف استرشادي** as a clearly identifiable dropdown and expanded the dynamic-template categories to include education/training, health/medical care, housing/rehabilitation, seasonal projects, plus **أخرى / مشروع مخصص** for projects outside the predefined categories.
- Dynamic budget templates now have matching category guidance/template definitions; the custom category remains available for non-listed project types.
- **الميزانية التقديرية** must be greater than zero before the budget template loader, budget-line fields, and add-line action become active.
- A zero or empty estimated budget keeps those budget-dependent controls locked and prevents saving until a positive budget is entered and the budget-line total matches it.
- No schema change, runtime DDL, trigger, or view was added.


## 2026-09-24 — Projects Approval Notification Workflow Checkpoint

The Projects approval notification chain is now aligned with the required two-way workflow:

- Approval: Projects Manager submit/resubmit → FM review/approve → GM/VGM final review/approve → Projects Manager receives final-approval notification.
- Rejection: GM/VGM reject → project returns to `submitted` and FM is notified → FM rejects → project becomes `rejected` and Projects Manager is notified → PM edits/resubmits or closes as rejected according to the existing workflow.

Implemented without schema changes by reusing the existing event-aware notification infrastructure. GM rejection no longer sends a terminal rejection notification directly to PM. Final GM approval now notifies PM.

Commits: `4d985d094f7bde4d6faa503fa704db55457b9e04`, `f0c37431ce67029b36defb7132e43db2c024fbe4`, `58f7c62f6df05f98da7d003fe470c0e616a41eb9`, documentation `e975fccaa4ebf741babde60831ef2bb825cd299a`.

Runtime certification is pending controlled local testing; no new fixture is required.


## 2026-09-25 — Projects workflow / launch-control audit checkpoint

Controlled runtime testing using PRJ-0010 / project ID 10 confirmed the approval notification path through final approval. The remaining lifecycle boundary is intentionally explicit: final GM/VGM approval is not the same thing as project launch.

### Verified design
- PM submission → FM review/approval.
- FM approval → GM/VGM final review/approval.
- GM/VGM final approval → project remains planned and PM is notified.
- PM must explicitly launch the project.
- Launch requires an active assigned Project Supervisor.
- Launch changes project/lifecycle state to active, records status history/audit, and notifies the assigned Project Supervisor.
- Project Supervisor access/listing is blocked before launch.

### Dashboard integrity finding and resolution

A runtime fatal error exposed a collation incompatibility in dashboard/projects_dashboard.php. The relevant schema design has other_projects.status using utf8mb4_general_ci while project_lifecycle.lifecycle_status and project_approval.approval_status use utf8mb4_unicode_ci. Expressions such as COALESCE(l.lifecycle_status,p.status) therefore require explicit normalization when compared with string literals in this dashboard path.

The fix was made systematically across the PM and Project Supervisor dashboard status/approval comparisons rather than by changing the database schema. Final supervisor-dashboard fix commit: b3c23386fc54d27705df6cc1e3546430935135cd.

### Remaining audit/test item

The next controlled runtime test is the explicit PM launch path for PRJ-0010, followed by Project Supervisor notification, dashboard visibility, direct project access, and operational-section permissions. After that, continue the post-approval payment/disbursement/receipt and accounting-event audit. Do not assume that notification success alone certifies payment or accounting behavior.


## 2026-09-26 — Projects project-funded expense workflow resolved

The Project Supervisor project-funded expense workflow has been completed and runtime-tested on PRJ-0010 / project ID 10.

Resolution:
- Payment is made from the project budget already transferred/allocated to the Project Supervisor.
- Payment registration requires a receipt and posts the project expense directly.
- The posted expense reduces the project's available approved budget.
- No organization expense account, payment account, cash/bank/e-wallet transaction, or organizational journal is involved.
- Legacy draft expenses are converted only when the user explicitly records the payment through the existing edit workflow with the required receipt.
- The assigned primary Project Supervisor may edit or delete both draft and posted project-funded expenses; both UI and server-side rules were updated consistently.
- The temporary standalone finalization page was removed.
- The obsolete organization-account expense path is blocked.

Final commit: `5dda62c4bc74662fe0e9c415ddbbed88e9263a94`.

The detailed checkpoint is `docs/PROJECTS_RUNTIME_CHECKPOINT_2026-09-26.md`.

This work unit is complete. PRJ-0010 is still not closed because one remaining project task is outstanding; closure is deliberately deferred until that task is completed and the normal closure guards pass.


2026-09-27 — Projects closure runtime verification and report-page cleanup

### Closure
PRJ-0010 closure was runtime-tested successfully after the repository/schema correction adding `project_lifecycle.close_reason`. PM closed the project without errors, and the existing closure notification flow delivered notifications to GM and VGM. This closes the previously pending closure verification item. Migration: `database/migrations/2026-09-27_project_closure_reason.sql`; migration commit `b7bd414fce050b271e9653e47c4becd304622398`.

### Reports UI cleanup
Four report pages were corrected:
- `modules/reports/hr.php`
- `modules/reports/financial.php`
- `modules/reports/sponsorship.php`
- `modules/reports/operational.php`

The redundant manual bottom Back controls were removed because `includes/footer.php` already provides the standardized top-left and bottom-right controls. The welcome-section report title/subtitle text was also made explicitly white for contrast. No shared header redesign or database change was introduced.

This is a completed UI cleanup; future Back-button work should continue to use the shared footer mechanism rather than adding page-local duplicate controls.

### Current audit direction
The Projects deep audit remains the active technical work area. Do not reopen completed expense/payment or closure fixes without regression evidence. The next implementation should be chosen from the unresolved items in the current Projects audit plan after fresh repository/schema inspection, with runtime verification before closure of each work unit.

## 2026-09-27 — Pre-HR Salary Advance Backup Checkpoint

The user confirmed that the current project/repository and database backups have been completed successfully before beginning the new HR Salary Advance feature. This is the recovery checkpoint for the new feature work.

No Salary Advance implementation or schema change has been made at this checkpoint. The agreed design direction is policy-driven: an annual FM-configured Salary Advance Policy provides defaults, while the FM may override/customize the policy terms for an individual request. The existing HR/payroll/accounting implementation must be inspected before any schema or code changes are made.


## 2026-09-29 — HR Salary Advance Stage 4 Closure Audit

Stage 4 reached its completion gate and is closed.

Evidence reviewed: runtime accounting/disbursement evidence for SAR-2026-00001; runtime rejection closure for SAR-2026-00005; successful protected receipt replacement; code-verified employee ownership checks; code-verified duplicate-disbursement protection before posting and after transactional locking; and mandatory receipt-replacement audit enforcement.

Receipt replacement lifecycle was confirmed: after successful DB/audit commit, the superseded physical receipt is removed. If the transaction fails, the old receipt remains and the newly uploaded file is cleaned up.

No artificial duplicate journal or cross-employee access scenario was manufactured because the existing request is already disbursed and the normal UI correctly provides no re-disbursement path; the endpoint authorization and duplicate protection were directly inspected.

Stage 4 is **DONE / CLOSED**. Stage 5 remains **NOT STARTED**.


# 2026-09-29 — Salary Advance Processing Workflow Consolidation Audit

The two former salary-advance processing pages were reviewed and consolidated into one user-facing workflow.

Removed:
- `modules/hr/salary_advance_fm_review.php`
- `modules/hr/salary_advance_processing.php`

Current unified processing page:
- `modules/hr/salary_advance_processing.php`

The consolidation is UI/workflow-level only. Existing FM decision logic, accounting verification, disbursement controls, journal posting, schedule generation, receipt handling, and role checks remain implemented in the existing procedural libraries.

The salary-advance portal and accounting-staff dashboard now route users to the unified processing page. Employee FM-approval notifications also route to the unified page.

Document/evidence endpoints remain separate:
- `modules/hr/salary_advance_voucher_print.php`
- `modules/hr/salary_advance_receipt.php`

These are not duplicate processing pages and were intentionally retained.

**Audit status:** implementation complete; runtime UI verification pending.

# 2026-09-29 — Stage 5 Schedule Generation Runtime Checkpoint

**Status:** Stage 5 IN PROGRESS — schedule generation/display RUNTIME VERIFIED; edge-case schedule-rule tests pending.

The Stage 5 migration was applied successfully locally with all 5 queries completing without errors. It added payroll.salary_advance_deduction, hr_salary_advance_repayment_schedule, and hr_salary_advance_payroll_repayments.

PR #52 — Stage 5: generate salary advance repayment schedules — was merged into main on 2026-09-29 (merge commit 96ecf58871fe27b32aad2c5d62174c40991a5747). The current branch is a follow-up consolidation branch and remains unmerged while the consolidated workflow is runtime-tested.

Implemented on feature/hr-salary-advance-stage5-schedule:
- modules/hr/lib_salary_advance_repayment.php
- atomic schedule generation from the existing disbursement transaction;
- fixed-monthly and full-eligible-salary schedule planning;
- approved start-rule handling;
- maximum monthly deduction and maximum repayment-month constraints;
- duplicate schedule-generation protection;
- schedule display on modules/hr/salary_advance_processing.php.

## Runtime PASS — SAR-2026-00007

Employee: هديل عثمان / EMP-0021

- Approved/disbursed amount: 50,000 SDG
- Method: قسط شهري ثابت
- Monthly installment: 10,000 SDG
- Approved start month: 2026-10-01
- Disbursement source: 1100 — الصندوق (نقدي)
- Journal: JE-000041
- Reference: SAL-ADV-SAR-2026-00007
- Outstanding balance: 50,000 SDG

Exactly 5 pending schedule rows were generated:
1. 2026-10-01 — 10,000 SDG
2. 2026-11-01 — 10,000 SDG
3. 2026-12-01 — 10,000 SDG
4. 2027-01-01 — 10,000 SDG
5. 2027-02-01 — 10,000 SDG

Total scheduled = 50,000 SDG. Applied = 0. This confirms the core disbursement → schedule-generation → schedule-display path.

SAR-2026-00004 / JE-000042 is a different request and is not a duplicate of SAR-2026-00007.

## Duplicate-disbursement safety checkpoint

The existing disbursement helper checks for an existing posted salary-advance disbursement journal, locks the request with FOR UPDATE inside the transaction, revalidates state, creates one balanced Dr 1410 / Cr source-account journal, changes the request to disbursed transactionally, and generates the schedule in the same transaction. A schedule-generation failure rolls the disbursement back. No artificial duplicate financial posting should be created merely to test this.

## Remaining Stage 5 runtime gates — NEXT

1. Maximum monthly deduction — verify every generated installment is at or below the saved policy maximum.
2. Maximum repayment months — verify an impossible schedule is rejected safely and does not leave an unscheduled disbursed request.
3. next_payroll — verify the first scheduled month is the first payroll month after disbursement.
4. specified_month — verify the approved start month is honored, but never before the first eligible payroll month after disbursement.
5. Duplicate schedule generation — verify an existing schedule remains one set of rows.
6. full_eligible_salary — verify planning respects the saved repayment horizon and maximum deduction; actual salary calculation remains deferred.
7. Failure/rollback — verify a schedule-generation failure rolls back the disbursement transaction.

## Explicit boundary

Stage 5 currently does NOT implement actual payroll deduction calculation, payroll repayment allocation, outstanding-balance reduction from payroll, payroll Cr 1410 accounting, repayment notifications, or Stage 6 direct repayment/settlement.

Use fresh controlled requests for new tests. Do not alter the already-passed SAR-2026-00007 evidence. If a test fails, stop and inspect the current repository/code/schema root cause before creating another test request.


# 2026-09-29 — Salary Advance Stage 5 Schedule-Planning Audit Closure

The schedule-planning portion of Stage 5 passed all seven defined runtime/verification gates. This closes the schedule-rule work unit but does not close Stage 5 as a whole.

Evidence: SAR-2026-00008 maximum-deduction cap; SAR-2026-00009 maximum-month rejection and rollback-safe disbursement attempt; SAR-2026-00011 next-payroll scheduling; rollback-only verification for specified-month, duplicate generation, and full-eligible-salary planning; and transaction/code evidence for failure rollback.

The CLI verification harness was hardened in commit 9b66315966174a3a892a5760847300007814a0e2 and reran cleanly.

Audit boundary: actual payroll deduction/application, payroll allocation, outstanding-balance reduction, payroll Cr 1410 posting, insufficient-salary handling, and repayment notifications remain to be implemented and runtime-verified in Stage 5. Stage 6 is not started.


## 2026-09-29 — HR Salary Advance Stage 5 Payroll Integration Checkpoint

Code audit checkpoint: payroll repayment application is transactional with payroll payment and accounting. Pending schedule rows are locked, the approved-policy deduction is recalculated, one repayment trace is written per request/payroll, schedule state and outstanding balance are updated, Cr 1410 is posted for actual repayment, and old/new balance and schedule values are written to audit_log. Runtime verification remains open; no Stage 6 behavior was introduced.


## 2026-09-29 — Stage 5 Payroll Integration Merge Checkpoint

The Stage 5 payroll-integration implementation was merged to `main` through PR #56. Merge commit: `7cb7a42a9bb4d90a07d23250f2940473806498c8`.

Status remains **Stage 5 IN PROGRESS / RUNTIME VERIFICATION PENDING**. The code is now on `main`; no Stage 6 behavior has been introduced. The next step is controlled local runtime verification of payroll repayment application, Cr 1410 accounting, balance/schedule updates, insufficient-salary handling, duplicate protection, and audit traceability.


## 2026-09-29 — Stage 5 Repayment Notification Checkpoint

PR #57 was merged to `main` with merge commit `0c01c3a378826b7b7cb260668f8f64b0e6648031`. Employee notifications are now emitted after a committed payroll repayment for applied, partial, skipped, and zero-balance completion outcomes. Notification delivery is informational and cannot roll back the financial transaction.

Stage 5 remains IN PROGRESS pending controlled runtime verification. Stage 6 remains NOT STARTED.


## 2026-09-30 — HR Salary Advance Stage 5 Edge-Case Checkpoint

Stage 5 remains **IN PROGRESS**. Core payroll repayment integration is runtime verified. The rollback-only edge-case harness was merged in PR #67 (merge commit `49ae654a25a8ec2a80901c86fe9aa009da83b96c`).

Runtime audit result:
- PASS — `available_salary` + fixed monthly: `SAR-2026-00004`, scheduled remaining 5,000 SDG, eligible salary 1 SDG, deduction 1 SDG, outcome `partial`.
- SKIP — `skip_month` + fixed monthly: no suitable existing disbursed fixture.
- SKIP — low-salary `full_eligible_salary`: no suitable existing disbursed fixture.
- PASS — rollback-only cleanup: no payroll/request/schedule/journal mutation committed.

The two SKIPs are fixture-availability gaps, not failures. Do not create a new real employee/request solely to manufacture test data. Stage 6 remains NOT STARTED.


## 2026-09-30 — Stage 5 Final Runtime Closure

**Status: DONE / RUNTIME VERIFIED / CLOSED**

Post-merge verification was completed on `main` at merge commit `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.

Final edge-case harness:
`tools/run_salary_advance_stage5_payroll_edge_tests.php`

Final runtime result:
- **PASS — available_salary + fixed_monthly:** `SAR-2026-00004`; scheduled remaining = 5,000 SDG; eligible salary = 1 SDG; deduction = 1 SDG; outcome = `partial`.
- **PASS — skip_month + fixed_monthly:** `SAR-2026-00007`; scheduled remaining = 10,000 SDG; eligible salary = 1 SDG; deduction = 0 SDG; outcome = `skipped`; rollback-only existing-request policy override.
- **PASS — full_eligible_salary with low eligible salary:** `SAR-2026-00007`; deduction = 1 SDG; outcome = `partial`; rollback-only existing-request method override.
- **PASS — rollback-only cleanup:** no payroll/request/schedule/journal mutation was committed.

These four edge gates passed both before merge and again after the PR was merged to `main`. The two previously unavailable policy branches were covered safely through SAVEPOINT-based rollback-only fixtures; no permanent employee, request, schedule, payroll, or accounting test data was created.

The previously verified Stage 5 core payroll gates remain valid, including payroll repayment application, schedule/balance updates, duplicate protection, balanced Cr 1410 repayment accounting, repayment trace/audit behavior, and repayment notification implementation/runtime coverage. No Stage 6 behavior was introduced.

**Stage 5 is now formally closed.**

Stage 6 — Direct Repayment & Settlement remains **NOT STARTED** and is the next planned work unit. Do not begin it in the same checkpoint unless explicitly proceeding with Stage 6.


## 2026-09-30 — Stage 5 Final UI Regression Closure

The Stage 5 processing-page UX cleanup is now complete on `main`. Permanent instructional text was removed from the FM review, accounting verification, and disbursement panels. Temporary toast feedback now communicates successful workflow transitions without leaving persistent instructional alerts on the page.

The final payroll edge harness was rerun after these UI changes with four PASS results: available-salary partial deduction, skip-month handling, low-salary full-eligible-salary partial deduction, and rollback-only cleanup with no committed financial or schedule mutation.

No accounting, payroll, schedule-generation, or repayment business logic was changed by this UI cleanup. Existing disbursed-record schedule display remains valid and was intentionally left unchanged.

**Final Stage 5 status: DONE / RUNTIME VERIFIED / CLOSED.** Stage 6 — Direct Repayment & Settlement remains NOT STARTED.

## 2026-09-30 — HR Salary Advance Stage 5 Closure / Repository Cleanup

**Stage 5 — Repayment Schedule + Payroll Integration: DONE / RUNTIME VERIFIED / CLOSED.**

Final runtime verification on `main` passed all four Stage 5 payroll edge cases, including:
- `available_salary` insufficient-salary partial repayment.
- `skip_month` insufficient-salary skip behavior.
- `full_eligible_salary` with low eligible salary.
- rollback-only cleanup with no permanent payroll/request/schedule/journal mutation.

The final Stage 5 UI cleanup also removed permanent workflow-instruction text from the unified salary-advance processing page and replaced successful workflow-state feedback with temporary toast notifications. Repayment schedule generation/display logic was not changed.

Stage 5 closure checkpoints:
- Final runtime merge checkpoint: `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.
- Final UI cleanup commits: `6bc8b94fdddff8593a8e9fe716865d302509a933`, `c04adaa1fe4ec27b187d7d080f85a8db0c593506`, `f8ab8272b3eaf352cd02cd59ffafee3a0ee1cfb6`.
- Documentation closure commits: `65cb44479c0218ae28b2c80e6c071eac0f1ddafa`, `0c8671ce8f4338fa90a290d3b9c8da6a7699ccf4`, `450581766bdf783c4cd32bc05dd33287de9fde32`.

### Git repository cleanup

The repository was audited for stale branches after Stage 5 closure. All obsolete remote branches were deleted and all obsolete local-only branches were deleted after reviewing their unique commits. No historical branch was merged back into `main`.

Final repository state:
- local branch: `main` only;
- remote development branch: `origin/main` only;
- `origin/HEAD -> origin/main`;
- working tree clean;
- local `main` synchronized with `origin/main`.

### Next work unit

**Stage 6 — Direct Repayment & Settlement: NOT STARTED.**

Stage 6 must begin in a fresh session. First inspect the current `main` repository, the master documents, the salary-advance continuation document, and the actual schema/code relevant to repayment/settlement. Do not implement Stage 6 behavior before the design/schema audit checkpoint.



## 2026-09-30 — Salary Advance Accounting Verification / Disbursement Lifecycle Hardening

**Status: IMPLEMENTED — RUNTIME VERIFICATION PENDING**

A lifecycle defect was identified: accounting could set accounting_status = verified as a standalone action while the request remained status = approved and was not disbursed.

The workflow is now hardened:
- Standalone accounting verification is blocked.
- Accounting rejection remains available before disbursement.
- For requests requiring accounting verification, the Disburse & Post action performs verification and disbursement inside one database transaction.
- If the transaction fails, both verification and disbursement roll back.
- Legacy approved + verified requests are shown as جاهزة للصرف in the accounting action queue, not processed history.
- Processed history contains only disbursed/settled/rejected/cancelled requests.
- No schema change was required.

Implementation branch: fix/salary-advance-verification-disbursement-atomic
Implementation commits: ea15f86f88e4166ecf97cdaac1e851cfe2e395de, 2769cf2eb5b0062945510a53de9908954e8a5c1f, 7adcffc014f62d98601387f8194b25f87a89f1ec, f2cb76b9043743b3ee855837e8cea3563b96cc30

**Runtime gate:** confirm SAR-2026-00009 appears as جاهزة للصرف, disburse it normally, and confirm it becomes تم الصرف and leaves the action queue. Then continue Stage 6 repayment testing.


## 2026-10-01 — Stage 6 Direct Repayment & Settlement Final Runtime Closure

**Status: DONE / RUNTIME VERIFIED / CLOSED**

Stage 6 — Direct Repayment & Settlement is now fully runtime verified on the current `main` lifecycle.

Final clean runtime fixture: **SAR-2026-00019**
- Employee: EMP-0002 / المدير العام.
- Approved amount: 20,000 SDG.
- Approved repayment method: direct repayment.
- FM approval completed successfully.
- Atomic accounting verification + disbursement completed successfully after the Stage 6 precheck correction.
- Outstanding balance after disbursement: 20,000 SDG.
- No payroll repayment schedule was created, as required for direct repayment.
- Direct repayment 1: 10,000 SDG received through **1200 — البنك**, accounting entry **JE-000049**, reference `SAL-ADV-REP-SAR-2026-00019-93`.
- Direct repayment 2: 10,000 SDG received through **1300 — المحافظ الإلكترونية**, accounting entry **JE-000050**, reference `SAL-ADV-REP-SAR-2026-00019-94`.
- Both repayments were reflected in the organization receiving accounts.
- Outstanding balance correctly reduced to **0.00 SDG** and the request reached **settled**.
- Both direct-repayment transactions accepted dedicated supporting evidence.
- Evidence display and replacement were runtime verified.
- The workflow therefore covers partial repayment, multiple repayments, different receiving accounts, accounting posting, balance reduction, evidence attachment/replacement, and final settlement.

Stage 6 hardening/fixes merged to `main`:
- PR #86 — atomic disbursement precheck correction: merge commit `cebd8df397d76738d56a6287b6ba85afba77b6c3`.
- PR #87 — direct repayment evidence request-column correction: merge commit `756debc716339470344428812f9684960a272588`.

No schema change was introduced by PR #86 or #87. The dedicated evidence table remains provided by the Stage 6 migration already merged earlier.

**Stage 6 is formally closed. Do not reopen it unless genuine regression evidence appears.**

## 2026-10-01 — Projects Phase 5 queued as next audit work

Salary Advance Stage 6 Direct Repayment & Settlement is closed and runtime verified. The next substantive audit unit is **Projects Module Phase 5 — Accounting Reconciliation / Post-Approval Financial Integrity Audit**.

The clarified Projects business requirement is that, after required FM-controlled financial approval, the full approved project budget comes under Project Supervisor control, the organization's treasury is reduced by that amount, and the amount is treated as project expense at that financial/disbursement point. Any genuine saving by the Project Supervisor must later be reconciled and returned to the organization's accounts as an explicit, auditable financial event.

Phase 5 remains **TO DO / NEXT**. Before implementation, inspect and reconcile the existing chart of accounts, funding allocations, payment evidence, project expenses, journal conventions, and fresh database evidence. The saved-budget return path must be designed from existing accounting conventions; do not invent accounts or schema. Historical phantom project-approval journals are not to be corrected until the replacement accounting model is settled.



## 2026-10-01 — Projects Phase 5 implementation checkpoint

**IMPLEMENTED — RUNTIME VERIFICATION REQUIRED.**

The Phase 5 accounting model is implemented on the isolated audit branch. FM financial approval releases each funding allocation once; GM final approval does not create a second release. PS closure requests automatically identify unused controlled funds and notify FM to process the audited return. Closure remains blocked until the controlled balance is zero.

Do not run historical project journal correction until runtime verification of the replacement model passes.


## 2026-10-01 — Phase 5 closure ownership clarification

The Phase 5 closure rule was clarified after implementation review: the primary Project Supervisor submits the closure request and has no further responsibility for savings reconciliation. FM is notified **only when a controlled unused balance exists** and FM alone performs the return/accounting reconciliation. When the controlled balance is zero, no FM notification is sent. PS is not asked to return, confirm, monitor, or complete any financial reconciliation after closure submission.


## 2026-10-01 — User / Employee Creation Architecture Consolidation

**STATUS: IMPLEMENTED / VERIFIED STATICALLY**

The Admin and HR Manager account-creation paths have been consolidated.

The canonical workflow is now `modules/users/index.php`, accessible to both Admin and HR Manager. It uses the shared `modules/users/_create_user_form.php` and the transactional `hrCreateUserWithEmployee()` provisioning function so every newly created account receives its linked employee profile automatically.

The previous duplicate HR creation implementation was removed from `modules/hr/employees.php`. That page remains responsible for employee management and now redirects its former `action=add` route to the canonical user/employee creation page.

The direct-manager dropdown is maintained only in the canonical page and was broadened to include the existing management/head patterns. No PM/PS-specific exception was introduced.

The shared form file is intentionally retained because it is the single reusable creation UI; it is not an extra creation page.

Relevant commits:
- `b90791e2bfa49f0a6cab26e1844b951c52981d38`
- `ca42000f4552eeeb4fea6fb493bb8008c29a40b9`
- `ffed7719f241f7bcee1732da88e743e838296f2c8`
- `6b36187460bc27246d9a5e89615f4c8b85cdf2d8`

Follow-up runtime verification should confirm Admin and HR both open the same creation UI and that a newly created account produces exactly one linked employee profile.

## 2026-10-02 — Unified Account Provisioning / Temporary Password Runtime Closure

The canonical Admin/HR account-creation workflow and temporary-password first-login flow were runtime verified.

Evidence:
- A controlled disposable account was created successfully through the unified creation page.
- The system generated a temporary password automatically and stored a 60-character password hash with password_change_required = 1.
- Login with the generated temporary password was successfully completed after credential-entry direction was corrected.
- The employee was forced into password change, changed the password successfully, and subsequently logged in using the new password.
- The backend authentication implementation was retained; the observed first-attempt issue was isolated to credential text-direction behavior while the page was RTL.
- Login and password-change credential fields were hardened with dir="ltr", while the surrounding application can remain RTL or LTR.

Commits:
- c3b139cae1d08c67f00313b2bca50c8d20987afe
- 745fe81d5c3dcc7773b84377e4d2ff5facc9dd88

**Status: CLOSED / RUNTIME VERIFIED.**

Do not reopen the password/authentication workflow without a new reproducible defect.

## 2026-10-02 — Projects Historical Checkpoint Clarification

The earlier Projects Phase 5 accounting implementation/audit attempt is not the current working baseline. It was explicitly rolled back to checkpoint 594e6c216757851526e63e80a8b2e8585416d94a before subsequent work. Future Projects work must begin from that rollback baseline plus unrelated changes subsequently merged to main; do not reintroduce the rolled-back Phase 5 implementation or its migrations/files without an explicit fresh design audit.


## 2026-10-02 — Projects Phase 5 fresh accounting model implemented / runtime gate pending

The fresh database snapshot commit 3219852ad239b9ee4fc1d8e685b9462ff9ef7a47 confirmed the intended Phase 5 accounting event directly in the live-derived dump:

- other_projects.expense_account_id is the existing project expense-account mapping.
- PRJ-0011 uses 5100-11 — مصروفات مشروع: PH5 Accounting Reconciliation Test.
- Existing test journals JE-PRJ-REL-11-31..., ...32..., ...33... use the model: debit the project's existing expense account and credit the actual treasury source account.
- project_funding_returns already exists in the fresh database snapshot and records the auditable return event.

A fresh implementation was added on main without resurrecting the rolled-back Phase 5 files:

1. Added modules/projects/project_funding_accounting.php with procedural helpers for one-time FM-controlled funding release, payment-evidence synchronization, controlled-balance calculation, FM-only unused-fund return, and safe reversal of an unreconciled FM release before execution.
2. Added database/migrations/2026-10-02_project_funding_reconciliation.sql for project_funding_returns.
3. FM financial approval now performs the accounting release atomically with the approval transition.
4. GM final approval no longer creates a second funding/accounting event.
5. Project closure is blocked while a controlled unused balance remains.
6. A closure request with a non-zero controlled balance notifies FM for reconciliation; when reconciliation reaches zero, PM is notified.
7. Normal accounting post_expense is blocked after project funding has been released, preventing a second treasury reduction for the same controlled project funds.

The implementation is STATICALLY IMPLEMENTED — RUNTIME VERIFICATION REQUIRED. Do not mark Phase 5 closed and do not correct historical project journals until the fresh controlled-project runtime gate passes.


## 2026-10-02 — Projects Phase 5 PM handoff visibility gate corrected

A runtime-driven workflow audit found that the Projects Manager dashboard/list used the generic project-view authorization and therefore exposed a GM-approved project before the Financial Manager had completed the separate final funding-document confirmation. This contradicted the established handoff boundary.

Correction:
- Added the shared helper `akp_project_final_fm_confirmed()`, derived from the existing auditable `FM_CONFIRM_PAYMENT_EVIDENCE` event in `audit_log`.
- Projects Manager project visibility is now denied until that FM final-confirmation event exists.
- The existing FM final confirmation remains the single PM handoff/notification event; no new accounting event or schema state was introduced.
- Project Supervisor access remains governed by the existing approval + explicit launch lifecycle gate.
- PM project launch now uses the same shared FM-final-confirmation helper rather than duplicating the audit-log query.
- GM approval remains organizational approval only and does not notify the PM or create a second accounting release.

Implementation commits:
- `2ec62923ad554e5feb7c2bd261d3267cbdf9ca5e` — PM visibility gate.
- `ad097b53c15d0d2b3d2f8307164e1325b9f44d5f` — shared helper used by PM launch gate.

**Status: STATICALLY CORRECTED — RUNTIME VERIFICATION REQUIRED.**
The PRJ-0012 runtime gate must verify: GM approval alone leaves the project absent from the PM dashboard/detail access; FM final funding-document confirmation then makes it visible and sends the PM handoff notification; no duplicate accounting release is created.


## 2026-10-02 — Projects Phase 5 PM handoff boundary — current audit checkpoint

The latest runtime-driven audit established a workflow boundary between organizational GM approval and the final FM-controlled execution handoff. A project being approved in project_approval is not, by itself, sufficient for PM execution visibility.

The authoritative handoff event is the existing auditable FM_CONFIRM_PAYMENT_EVIDENCE event. The shared helper akp_project_final_fm_confirmed() now gates PM visibility and PM launch. This reuses existing audit evidence rather than introducing another schema state.

The accounting event boundary remains unchanged: the full approved project funding is released once at FM financial approval, using the project's existing expense-account mapping and selected treasury source account. GM approval and FM final document confirmation do not create another treasury/accounting event.

Runtime verification remains open. Do not mark Projects Phase 5 closed or alter historical project journals until the controlled PRJ-0012 gate is completed.


## 2026-10-02 — Projects Phase 5 runtime gate: PS notification timing correction
Runtime evidence from PRJ-0012 confirmed that FM final funding-document confirmation correctly exposed the project to PM, but also revealed an incorrect early PS execution notification while the project was still `planned`. Static inspection of `modules/projects/view.php` confirmed the canonical lifecycle: PM must explicitly execute `launch_project`, which changes the project from `planned` to `active` and then notifies the assigned Project Supervisor. The defect was isolated to the PS notification block inside `modules/projects/view_fm.php`; that notification has been removed from FM final confirmation. Commit: `adecaf6c1e4b12017143d7a2f19bf8354ec1788d`. Phase 5 remains runtime verification pending until the corrected sequence is retested.



## 2026-10-02 — Projects Phase 5 deep audit repair checkpoint

The Projects module was re-audited page-by-page against the agreed workflow. The audit identified and corrected the following implementation risks:

### Corrected
- **GM rejection accounting gap:** GM rejection previously returned `fm_approved` to `submitted` without reversing the FM-posted funding release. It now performs a controlled accounting reversal and approval-state reset in one transaction.
- **Nested transaction risk:** `akp_reverse_project_funding_release()` previously owned its own transaction even when the caller needed to atomically update project approval. It now accepts a transaction-management flag so the caller can own the transaction when required.
- **FM correction atomicity:** FM's pre-final-confirmation return-to-review now reverses funding and invalidates GM approval in the same transaction.
- **Competing FM implementation:** obsolete FM handlers in the shared project view were removed. The dedicated FM page remains the sole FM action surface.
- **Activation bypass:** generic project status changes previously allowed `active`, which could bypass PM's explicit launch action. Both server-side and portfolio UI paths now exclude `active`; only `launch_project` can perform the initial planned→active transition.
- **GM accounting wording:** stale GM UI wording implying a financial/accounting action was corrected.

### Verified as existing and preserved
- PM handoff visibility is gated by the auditable FM final-confirmation event.
- PM launch requires final FM confirmation, GM approval, planned lifecycle, and an active assigned PS.
- PS access remains gated by approval + launched lifecycle.
- FM final confirmation notifies PM, not PS.
- PS execution notification is emitted only by PM `launch_project`.
- FM approval is the accounting release point and includes duplicate-release protection.
- FM final confirmation does not create another accounting release.
- Controlled project balance and unused-fund return remain FM-owned.

### Remaining runtime gate
Static alignment is complete. Phase 5 is **not closed**. Runtime evidence is still required for the corrected GM-rejection reversal path, FM correction atomicity, final-confirmation boundary, and launch/PS timing. Historical project journals remain untouched pending that evidence.


## 2026-10-02 — Final static authorization correction

During the final static pass, one authorization mismatch was found before runtime testing: the shared funding-reversal helper itself still enforced the FM role, which would have blocked the newly added GM-rejection reversal path. The helper now leaves role authorization to its calling workflow; FM correction and GM rejection each enforce their own role before calling it.

Correction commit: `4d672f3df3848421900b04a53706551dcd0ba4ac`.

**Runtime verification has not started yet. Pull only after this checkpoint is complete.**


## 2026-10-02 — Phase 5 runtime-gate preflight defect found and corrected
Static review immediately before runtime testing found two genuine integration defects that the earlier Phase 5 deep repair had not covered:
1. akp_can_view_project() blocked the PM who created a project from seeing it while it was still in the pre-handoff workflow, causing a successful create operation to appear to lose the project and redirect through the dashboard.
2. modules/projects/index.php contained a page-specific bottom back button in addition to the shared global back-button installer, producing a duplicate bottom button.

Corrections on main:
- project_lib.php: creator-PM pre-handoff visibility added without granting operational execution rights.
- form.php: successful create/update now uses PRG back to the same form and delivers the success message through the project-specific Bootstrap toast; duplicate success alert removed.
- index.php: duplicate page-specific back button removed; global standard pair remains.

Commits: aa8b26532293c3a1933c0b64f6021205c0703c43, 4cfe3f9175da05cc57d91bde, 448a913dc254cdbc72e61f5bd4e767a32251cc20, dd49b8896109faaa1f1635405eff3911f065c7b6.


## 2026-10-02 — Projects Phase 5 second deep pre-runtime audit

The Phase 5 implementation was re-audited after the PRJ-0013 preflight fixture exposed the need for another review before runtime testing. The review covered the shared project view, dedicated FM workflow, PM visibility/launch gates, status mutation paths, funding/accounting helpers, project schema relationships, and duplicate/legacy FM funding paths.

### Confirmed findings and fixes

1. Legacy FM funding workflow remained in the shared project view — CONFIRMED DEFECT.
   - modules/projects/view.php still contained legacy add_funding, edit_funding, and delete_funding handlers and their UI even though view_fm.php is the canonical FM workflow.
   - These handlers did not create the Phase 5 release journal themselves, but they created a competing funding-maintenance path and stale accounting language.
   - Removed the handlers and duplicate funding-entry/edit UI. Shared project view now displays funding allocations without mutation controls.

2. Shared portfolio still exposed the generic active status selector — CONFIRMED UI/workflow inconsistency.
   - Server-side project status handling already rejected active through the generic path, but the portfolio still rendered it as a selectable option.
   - Removed the option so the UI and server-side workflow agree: PM launch is the initial activation path.

3. FM budget decision actions were not explicitly tied to submitted/rejected financial review — CONFIRMED workflow gap.
   - Added server-side gates to FM budget approval/rejection so these actions are only available during the intended financial-review states.

4. Funding card markup was rechecked after removing the legacy UI — corrected before runtime testing.

### Schema review note

The repository schema contains the project-domain tables and project-linked accounting/evidence tables used by Phase 5. The current schema dump does not establish a direct foreign-key cascade from other_projects to all project-domain tables, so project test-fixture deletion must continue to be explicitly dependency-aware rather than relying on ON DELETE CASCADE assumptions.

### Current Phase 5 gate

The replacement accounting model remains STATICALLY IMPLEMENTED / STATICALLY RE-AUDITED / RUNTIME VERIFICATION REQUIRED. The controlled runtime sequence must be performed on a fresh project created after the latest corrections. Historical project journals remain protected until that sequence passes.

Latest main commit: 52a930f697198dbe8f051bf3415e014a929e1f1b.


### Final static consistency follow-up — 2026-10-02
The shared project status mutation path was also aligned with the portfolio: the legacy other_projects status mirror now maps lifecycle-only under_review to planned rather than completed. This prevents a mixed legacy/lifecycle status representation. Final code commit: f3cf39c5aafcee876db5be4436a1703ad6040097.


## Phase 5 — Second Independent Deep Scan / Remediation — 2026-10-02

A second independent source-level review was performed against the post-`e04b35c` Projects module revision before any new Phase 5 runtime fixture was created.

### Confirmed defects found and remediated

- Project creation is now restricted to the Projects Manager; new projects always begin in `draft` approval.
- GM/VGM general-data editing is removed from existing-project authorization.
- Generic Project Supervisor lifecycle changes can no longer set `planned`; PM launch remains the only `planned → active` path.
- Generic lifecycle mutations are transactionally grouped with legacy-status mirroring and history.
- Posted execution expenses cannot be edited or deleted by the Project Supervisor.
- Posted labor-payment records cannot be edited/deleted through the labor helper path.
- Existing-project form saves now use one transaction across the multi-table update sequence.
- FM budget approval and funding-batch writes are transactionally protected and lock the approval row against concurrent workflow transitions.
- GM approval/rejection re-check and lock the approval row inside their transaction.
- PM launch re-checks approval/lifecycle under lock and verifies the lifecycle update affects exactly one row.
- FM final funding confirmation records its workflow event transactionally and re-checks the locked approval state, preventing false success and duplicate concurrent confirmations.
- Funding allocation `journal_entry_id` is synchronized on release and cleared when the release is reversed.
- Project close/reopen state changes are transactionally grouped with lifecycle/legacy-state/history updates.

### Static post-remediation review

Targeted source checks for the above findings pass on the current `main` revision.

### Runtime status

No XAMPP/MariaDB/browser runtime verification has been claimed. The fresh controlled fixture `PH5 Full Accounting Reconciliation Test` must not be created until local runtime verification is available and the complete Phase 5 runtime sequence is executed.

Historical fixtures PRJ-0011 and PRJ-0012 remain untouched.


## 2026-10-02 — Phase 5 final pre-runtime workflow consistency repair

Before any new runtime fixture/test, the complete current Projects user-facing page set and the Projects dashboard were re-read against the established Phase 5 workflow, and the repository database snapshot was inspected as schema/accounting evidence.

### Additional inconsistencies found and corrected

1. **Obsolete standalone FM budget-rejection action removed**
   - modules/projects/view_fm.php still exposed fm_reject_budget.
   - That action only stored a rejection reason while leaving project_approval.approval_status unchanged, creating a dead-end path that did not match the canonical FM financial rejection workflow.
   - The standalone action, button, and modal were removed.
   - FM rejection is now exclusively fm_reject_project, which transitions submitted -> rejected and notifies the Projects Manager.

2. **Shared project-view funding ownership wording corrected**
   - modules/projects/view.php contained stale wording saying funding allocations were prepared before submission and that FM only reviewed them.
   - The current canonical workflow maintains funding allocations through modules/projects/view_fm.php during FM financial review.
   - The shared project view now presents allocations read-only and directs the workflow boundary to the dedicated FM page.

3. **Payment-evidence wording corrected**
   - The shared project view previously described the accounting documentation boundary as though the GM approval itself made the funding payment/accounting event.
   - It now explicitly states that the accounting funding release occurs at FM financial approval; GM approval is organizational approval only; FM later documents the actual payment evidence without creating another accounting release.

4. **Final-confirmation concurrency hardening**
   - FM cash confirmation, bank/e-wallet receipt upload, and payment-evidence editing now lock the project approval row and re-check the final-confirmation event inside a transaction.
   - This prevents a payment-evidence mutation from racing with FM final confirmation and modifying a project after the final handoff lock.

### Repository/schema verification

- All current modules/projects PHP pages were included in the source review: form.php, index.php, project_funding_accounting.php, project_lib.php, project_payment_receipt.php, serve_project_document.php, view.php, view_fm.php, view_pm.php.
- dashboard/projects_dashboard.php was also reviewed because it is part of the documented PM/PS workflow.
- The repository database snapshot database/ahl_el_kheir.sql was inspected directly from its Git blob, including the project approval/funding/payment-evidence/accounting structures.
- The snapshot contains no CREATE VIEW, CREATE TRIGGER, CREATE PROCEDURE, CREATE FUNCTION, or CREATE EVENT definitions.
- The current project page set contains no request-time CREATE/ALTER/DROP TABLE/VIEW/TRIGGER/PROCEDURE/FUNCTION/EVENT operations.
- Targeted post-remediation source invariants pass for:
  - canonical FM rejection;
  - GM rejection reversal/atomic approval reset;
  - FM correction lock;
  - final confirmation lock;
  - payment-evidence mutation locking;
  - PM-only planned -> active launch;
  - PS notification only after launch;
  - GM approval without a second accounting release;
  - FM approval as the accounting release point;
  - allocation-to-journal linkage.

### Runtime boundary

This is a source/schema consistency gate, not a runtime certification. No new project fixture has been created and no XAMPP/MariaDB/browser test has been claimed. The existing controlled fixtures remain untouched.

**Current Phase 5 status: IMPLEMENTED / DEEPLY SOURCE-AUDITED / PRE-RUNTIME WORKFLOW CONSISTENCY PASS / RUNTIME VERIFICATION STILL REQUIRED.**


## 2026-10-02 — Phase 5 PRJ-0015 runtime fixture

A fresh controlled project was created through the normal PM workflow: **PRJ-0015 — PH5 Full Accounting Reconciliation Test**. Approval is `draft`; lifecycle/display is `planned`; Project Supervisor is `project supervisor`; budget is `300,000.00 SDG` across 3 lines. Financial requirements, approved funding, and posted expenses were `0.00` at this point.

### Open runtime findings

1. **Numeric input mouse-wheel mutation:** scrolling over a numeric budget input changes its value; `299999.98` was observed. Treat this as a system-wide numeric-input defect and inspect shared/global handling before applying a project-only workaround.
2. **Save-flow reliability:** the user had to refresh the page and click Save twice before the expected project result appeared. The project eventually saved, but this interaction is not considered cleanly verified and requires source inspection.

The Phase 5 workflow remains runtime-verification-required. Do not delete/recreate PRJ-0015 merely to bypass these findings, and do not alter historical accounting journals. Resume at PM submission only after the input/save findings are addressed or explicitly verified as a separate boundary.


## 2026-10-02 — PRJ-0015 source verification checkpoint

- Remote `main` HEAD at inspection: `138a7b7d2206b3bbe2664c919462c54bd7026169`.
- Numeric-input finding: the repository already contains the intended system-wide fix in `assets/js/app.js`, commit `c80e1aa218aa5d8a6438d6e899482da3af5a7169`. The shared document-level `wheel` listener prevents default wheel changes only for `input[type="number"]`, with a non-passive listener, so no project-only workaround is required from this source review.
- Project save-flow finding: `modules/projects/form.php` currently performs one DB transaction for the create/update sequence, commits before PRG redirect, stores a project-specific success message in session, redirects to the project form, and disables the Save button only after client-side validation passes. The form has no source-level duplicate-submit path beyond the intentional submit-button lock.
- The prior PRG/toast repair commits `4cfe3f9175da05cc57e30d16f155851675d91bde` and `dd49b8896109faaa1f1635405eff3911f065c7b6` are present in the current history.
- **Runtime status:** source review alone does not prove browser behavior. No local XAMPP/MariaDB runtime certification is recorded by this checkpoint.
- **Next gate:** locally pull current `main`, verify the numeric wheel behavior and PRJ-0015 save flow in the browser, then continue with PM submission only after those runtime checks pass. Do not recreate PRJ-0015.


## 2026-10-02 — PRJ-0015 runtime pre-submission gate PASSED

The PRJ-0015 browser/runtime gate for the two previously open findings is now passed.

- The system-wide numeric-input mouse-wheel issue is fixed and runtime-confirmed by the user. Scrolling the mouse wheel while focused/hovered over the budget numeric field no longer changes the value. The fix is implemented centrally in `assets/js/app.js` using a capturing, non-passive window-level wheel listener limited to `input[type="number"]`, with default prevention and propagation stopping.
- The project save flow is now runtime-confirmed working: PRJ-0015 saved successfully and is ready for PM submission to FM approval. No project recreation was required.
- Code fix commit: `788228916d8d79b94ff9b705c2b12613ee8cd631`.

**Current runtime status:** PRJ-0015 is saved in `draft` and ready for the next workflow gate.

**Immediate next gate:** PM submits PRJ-0015 for FM approval. Verify that submission changes the project from `draft` to `submitted`, that the FM can see/review it, and that submission itself creates **no accounting/funding release**. Do not begin the FM accounting sequence until this submission gate passes.

Do not repeat the numeric-input or project-save investigation unless a genuine regression appears.

# 2026-10-02 — Projects Phase 5 PRJ-0015 PM submission runtime gate

## Controlled fixture

**PRJ-0015 — PH5 Full Accounting Reconciliation Test**

- approval: submitted
- lifecycle/display: planned
- Project Supervisor: project supervisor
- budget: 300,000.00 SDG across 3 lines
- approved funding: 0.00
- posted expenses: 0.00
- funding allocations: none at submission stage

## Runtime evidence

The PM successfully submitted PRJ-0015 for FM review.

The PM-facing result showed:
- مرسل للمراجعة
- the project remains مخطط
- the project is waiting for FM financial review/approval
- no funding allocation has been recorded

The FM-facing result showed:
- a new notification: **مشروع بانتظار المراجعة المالية**
- PRJ-0015 is available on the FM financial-review page
- the proposed budget is 300,000 SDG
- allocation tools are correctly deferred until budget approval

Therefore the PM submission gate passed. No accounting/funding release was created by submission.

## Accounting boundary confirmed

The next accounting event remains the established FM budget approval/funding-release step. GM approval is not an additional accounting release, and later correction/rejection/final-confirmation behavior remains governed by the previously documented Phase 5 workflow.

## Current pause point

At the user's request, Phase 5 accounting runtime testing is paused immediately after the successful PM submission gate so an **immediate project fix** can be handled first and addressed comprehensively.

Do not proceed to FM budget approval, funding allocation, or the remaining accounting reconciliation sequence until that immediate fix is implemented and runtime-verified.

Do not recreate PRJ-0015. Preserve the submitted/planned fixture for continuation unless the immediate fix explicitly requires a controlled modification.

**Phase 5 status: RUNTIME PM SUBMISSION GATE PASSED / ACCOUNTING SEQUENCE PAUSED FOR IMMEDIATE FIX.**



## 2026-10-02 — Projects Phase 5 / system-wide form-layout checkpoint

### Projects Phase 5 current state
- Controlled fixture **PRJ-0015 — PH5 Full Accounting Reconciliation Test** is preserved and currently remains at the PM-submission gate: **approval = submitted**, lifecycle/display = **planned**.
- PM submission runtime gate **PASSED**.
- FM receives the pending-financial-review notification and can open the FM review page.
- PM submission created **no funding allocation and no accounting release**.
- Budget remains **300,000.00 SDG** across 3 lines; approved funding and posted expenses remain **0.00**.
- Phase 5 accounting runtime testing is intentionally paused here until the immediate project fix requested by the user is completed and runtime-verified.
- Do **not** recreate or replace PRJ-0015 and do not proceed to FM budget approval/funding allocation until the pause is lifted.
- Previously completed numeric-input wheel and project-save-flow gates remain closed; do not repeat those investigations unless a genuine regression appears.
- The established Phase 5 accounting sequence remains unchanged: FM approval → one release per allocation → GM approval without a second release → GM rejection reversal/atomic return to FM → FM re-approval → GM approval → FM pre-final correction/reversal → FM re-approval → GM approval → FM final confirmation/PM handoff → PM launch → PS notification only after launch.
- Phase 5 is **not closed** until the complete local XAMPP/MariaDB runtime sequence passes.

### System-wide form UI checkpoint
- The global form-layout issue was reviewed structurally across Bootstrap column forms, custom grids, and dense repeatable rows rather than continuing page-by-page sizing adjustments.
- The centralized solution now uses the actual field-container width: wide containers use a content-sized label with a small gap and flexible field; narrow containers remain stacked so labels are not stuffed into cramped columns; dense repeatable rows are protected from the inline-label rule.
- Field widths are now differentiated by input type instead of forcing every field to consume the same excessive width.
- Implemented centrally in `includes/footer.php`.
- User runtime feedback: **much better for most pages**; this UI milestone is accepted for continuation, with any remaining page-specific issues to be handled only when concrete evidence is provided.
- Commit: `7856da749fc23e06fce9c7ecedfa5e92f061fd8f`.

### Continuation rule
Start the next session by reading the four master documents and the current Projects Phase 5 checkpoint. Do not restart earlier audits or recreate historical/controlled fixtures.



## 2026-10-02 — Projects Phase 5 continuation checkpoint — UI fix accepted / FM gate next

The previously requested immediate project/UI fix is now completed and accepted for continuation.

### System-wide form UI fix — completed
- The form label/field sizing issue was addressed **centrally**, not page-by-page, in `includes/footer.php`.
- The solution was based on the repository's actual Bootstrap column forms, custom grids, and dense repeatable rows.
- Wide field containers use a content-sized label with a small gap and a flexible field; narrow/dense containers remain stacked; common field types use semantic sizing rather than forcing every field to the same width.
- Commit: **7856da749fc23e06fce9c7ecedfa5e92f061fd8f**.
- User runtime feedback: **"much better now for most pages"**.
- This UI milestone is accepted. Any remaining page-specific issue should be handled only when concrete runtime evidence appears; do not reopen the completed global sizing investigation.

### PRJ-0015 current state
- Fixture: **PRJ-0015 — PH5 Full Accounting Reconciliation Test**
- approval: **submitted / مرسل للمراجعة**
- lifecycle/display: **planned / مخطط**
- budget: **300,000.00 SDG** across 3 lines
- approved funding: **0.00**
- posted expenses: **0.00**
- no funding allocation/accounting release created by PM submission
- FM received the financial-review notification and can open the FM review page

### Next gate
The temporary pause after PM submission is now **lifted**. The next task is to resume Phase 5 at **FM financial review / budget approval**, then continue the established accounting reconciliation sequence.

Do **not** recreate PRJ-0015. Do not repeat the already-passed numeric-wheel or save-flow investigation unless a genuine regression appears.


## 2026-10-03 — Projects Phase 5 runtime reconciliation correction — PRJ-0015

### Audit correction
A review of the current Projects source and the supplied browser/runtime evidence identified a documentation synchronization error: the previous master checkpoint continued to describe PRJ-0015 as submitted/planned with FM budget approval still pending, even though the runtime sequence had already advanced through FM approval, GM approval, FM final confirmation, PM launch, and PS notification/access.

This section is authoritative for the current Projects Phase 5 position and supersedes the stale checkpoint text without deleting historical audit records.

### Current runtime evidence
**PRJ-0015 — PH5 Full Accounting Reconciliation Test**
- Approval: **معتمد نهائياً** (approved)
- Lifecycle: **قيد التنفيذ** (active)
- Project Supervisor: **project supervisor**
- Approved budget: **300,000.00 SDG**
- Approved funding: **300,000.00 SDG**
- Posted expenses at PS checkpoint: **0.00 SDG**
- Remaining budget: **300,000.00 SDG**
- PM launch history: مخطط → قيد التنفيذ, actor projects manager, timestamp 2026-10-02 19:48:43
- PS launch notification: **تم إطلاق مشروع جديد للتنفيذ**, PRJ-0015, timestamp 2026-10-02 19:48:43

### Closed runtime workflow gates
The following are now treated as completed for PRJ-0015 or already-established controlled Phase 5 evidence and must not be reopened without regression evidence:

- PM submission and FM notification.
- FM financial approval as the sole initial accounting-release point.
- Canonical funding allocation/release and journal linkage.
- GM approval without a second accounting release.
- GM rejection reversal / atomic return to FM review.
- FM re-approval after reversal.
- FM correction/void before final confirmation with GM approval invalidation.
- FM final payment-document confirmation without a new accounting event.
- PM visibility after final FM confirmation.
- PM-only planned → active launch.
- PS notification only after PM launch.
- PS post-launch access and operational-role boundary.

### Current source invariants confirmed during page scan
- akp_project_final_fm_confirmed() derives the PM handoff gate from the auditable FM_CONFIRM_PAYMENT_EVIDENCE event.
- Project Supervisor access requires final approval plus a launched lifecycle state; assignment alone does not expose an unlaunched project.
- launch_project is a dedicated Projects Manager action and requires final approval plus final FM confirmation.
- Generic status handling does not provide the normal active transition; launch remains the controlled transition.
- FM funding release uses project_funding_release journal references and checks for an existing posted journal for the allocation before creating another release.
- Funding reversal voids the current release, posts a balanced reversal journal, and returns the allocation to draft for controlled re-approval.
- After final payment-evidence confirmation, FM return-to-review is blocked by fm_payment_evidence_finalized().
- Project receipt access is restricted to authorized project viewers and documented payment evidence, with filesystem path confinement.
- Project Supervisor operational expense entry is distinct from accounting posting; project expense posting is restricted to FM/accountant/admin.
- Closure/reopen request and execution are separated: PS requests, Projects Manager performs the administrative close/reopen action.

### Page/action inventory scanned
 dashboard/projects_dashboard.php, modules/projects/index.php, modules/projects/form.php, modules/projects/view.php, modules/projects/view_fm.php, modules/projects/project_lib.php, modules/projects/project_funding_accounting.php, modules/projects/project_payment_receipt.php, and modules/projects/serve_project_document.php.

Current POST/action families include project submission, GM approval/rejection, dedicated PM launch, PS operational expense/document/labor/milestone/progress actions, closure/reopen requests and execution, FM budget approval, funding batch/edit/delete, funding reversal/return, FM approval/rejection/correction, payment-evidence upload/edit/confirmation, and final payment-document confirmation.

### Remaining certification work
The page scan does not justify declaring the whole Projects Phase 5 closed. The remaining work must be selected from genuinely undocumented runtime gates, especially any still-open post-final-confirmation mutation lock, controlled-balance/funding-return reconciliation, final expense/accounting reconciliation, and closure/reopen runtime certification. Historical completed tests must be used rather than repeated.
\n## 2026-10-03 — Projects Phase 5 execution-expense boundary audit\n\nA source-first audit was performed against the current `main` implementation before continuing PRJ-0015 runtime certification. The audit found that the Projects module has two intentionally distinct expense paths and that they must not be conflated during testing.\n\n### 1. Post-release PS execution expense\n`modules/projects/view.php` action `add_ps_expense` is restricted server-side to the assigned `project_supervisor`. It requires a positive amount and description, verifies an approved project budget, and prevents the cumulative posted expense amount from exceeding the approved budget. It inserts the expense directly with status `posted`, records `submitted_by` and `posted_by` as the acting PS, optionally stores a receipt document, and writes an audit-log CREATE event.\n\nThe action intentionally does not create a `journal_entries` row or treasury credit/debit. The UI explicitly states that the expense is deducted from the already-released project-controlled balance and that no second treasury entry is created. This is consistent with the Phase 5 accounting boundary: FM financial approval/release is the treasury release event; subsequent execution expenses consume that released control balance.\n\n### 2. Legacy/unreleased accounting expense path\nThe same page retains the generic `draft → submitted → approved → post_expense` workflow. Its `post_expense` action is server-restricted to financial/accounting roles and creates a balanced two-line journal only when the project has **not** already received posted funding. If posted project funding exists, the server rejects the action and directs the user to the PS execution-expense path. Therefore this path must not be used as a second posting step for PRJ-0015.\n\n### 3. Reconciliation calculation\n`modules/projects/project_lib.php` calculates posted project expenses from `project_expenses.status='posted'` and calculates the project residual as:\n\n```text\nresidual = total_funded - total_expensed\n```\n\nThe same helper supplies the closure totals. Therefore a valid PRJ-0015 execution-expense runtime test should change the expense/residual figures exactly once while leaving the 300,000.00 SDG funding release unchanged.\n\n### Finding / status\n**STATICALLY VERIFIED — RUNTIME PENDING.** No source defect is being changed at this checkpoint. The correct next gate is runtime verification of one controlled PS execution expense and the absence of any duplicate treasury release/accounting event.\n

## 2026-10-03 — Projects Phase 5 execution-expense runtime certification

The previously documented execution-expense boundary is now runtime verified on the controlled fixture PRJ-0015.

The assigned Project Supervisor recorded a 50,000.00 SDG execution payment. The application reported:
- approved FM budget: 300,000.00 SDG;
- total recorded expenses: 50,000.00 SDG;
- remaining budget: 250,000.00 SDG.

The three values reconcile exactly: 300,000.00 - 50,000.00 = 250,000.00 SDG.

This runtime result confirms the intended post-release spending boundary already established by source inspection: the PS execution-payment action consumes the project-controlled balance represented by the released funding and records the project expense; it is not a second treasury funding-release event.

**Gate: PASS / RUNTIME VERIFIED.**

No source change was required for this gate. Do not repeat the test or reopen the gate without concrete regression evidence.


---

Projects Phase 5 — PS status-control authorization/UI gate (2026-10-03)

Fresh repository database backup generated 2026-10-03 was inspected. PRJ-0015 has an active `project_supervisor_assignments` row assigning supervisor user 34 (`ps1`) with `ended_at = NULL`. The `users` dump identifies user 34 as role `project_supervisor`. Therefore the authoritative `akp_is_primary_supervisor(15)` path is satisfied for PS1; no `project_team` operations row is required for the primary-supervisor path.

Source audit found the server-side `change_status` action already permits `under_review`, `completed`, and `cancelled`, but `modules/projects/view.php` exposed no corresponding status-control form. The portfolio page had a selector, but the project-detail page used by the PS did not expose the existing capability. This explains the reported missing option without weakening authorization.

Narrow fix: `modules/projects/view.php` now exposes a PS-only status selector when `akp_can_edit_section('operations', $id)` is true and the project is not closed. `planned`/`active` are displayed as the current state but disabled because initial launch remains PM-only; selectable transitions remain `under_review`, `completed`, and `cancelled`. The server-side authorization and existing transaction/audit path are unchanged.

Implementation commits: `bd31a974193a12b19324216f6e647b70789d06c8`, refined by `0de84d5bd0b3c42e5b113499f551a96347b3f179`.

Runtime verification remains pending: pull the final commit and confirm PS1 can see the status selector on PRJ-0015 detail page and select `under_review` without changing the project yet unless intentionally testing that transition.

Next financial task explicitly parked as the next Projects reconciliation item: return the remaining controlled amount of an approved/released project budget when the PS does not fully consume it. The return must use the existing FM/accounting funding-return path, reconcile the controlled balance to zero, preserve journal/audit evidence, and must not create a second funding release.

## 2026-10-03 — Projects closure proof UX and final-close guard

A source-driven review of the closure flow addressed two concrete UX defects without weakening the accounting controls.

### Closure proof field
The Project Supervisor closure form now uses one unified return_proof field:
- an existing funding-return proof is displayed with its current filename and authenticated view link;
- the same file chooser can optionally replace that proof when the next closure request is submitted;
- leaving the chooser empty preserves the existing proof;
- replacement updates the existing project_documents row instead of creating a duplicate;
- the original physical file is removed only after the database update succeeds;
- replacement remains restricted to an unverified proof before a pending closure request exists;
- audit action is UPLOAD for the first proof and UPDATE for a replacement.

The separate replace_funding_return_proof action/form was removed. Browser security is respected: the existing local file path is not inserted into the file input; the current file is shown beside the same chooser instead.

Implementation commits:
- be3ee1768f4834db48b5d0d4e299da776637c94d
- b86ff01bd045607c889d031190f97af13efa280d (latest main)

### Final-close guard
The PM final-close UI now calculates the same authoritative controlled balance and pending-expense conditions used by the server-side close_project action.

When closure is not currently permissible, the final-close button is visibly disabled and the reason is displayed. For PRJ-0015 at the current documented checkpoint, the controlled balance is 250,000.00 SDG, so final closure must remain blocked until the Financial Manager records the funding reconciliation/return.

The server-side balance check remains authoritative; this is a visibility/UX correction, not a relaxation of the closure rule.

Runtime status: code/source change completed; local XAMPP/browser verification of unified proof replacement/preservation and the visible final-close blocking state is still required. Do not mark this UI/runtime gate closed until the user supplies that evidence.

Next runtime action: on PRJ-0015, verify the PS closure form preserves the current proof when no new file is selected, replaces it through the same field when a new file is selected, and does not create a duplicate document. Then verify the PM closure card clearly shows the 250,000.00 SDG blocking balance and a disabled final-close button. After FM settlement, verify the guard clears and final closure can proceed subject to the remaining closure checks.


### Projects closure notification-sequence correction — commit 2aa16aca0e0ba326000fb65ec2132314777d9145
- Corrected the closure notification sequence in `modules/projects/view.php`.
- A PS closure request now notifies **only the Projects Manager (PM)**; the Financial Manager (FM) is no longer notified at the request stage.
- FM notification now occurs **only after the PM successfully performs final project closure**.
- The FM notification is informational and points to the FM project view for any subsequent financial follow-up; it does not alter the closure accounting guard.
- Runtime status: source fix completed; local XAMPP/browser verification of both notification boundaries is required.


### Closure notification syntax correction — commit cc1e2c9708f23305a8bb56897b0da952160f4db2
- Corrected the malformed try/catch structure introduced in the project-closure notification sequence change.
- The Projects Manager closure path now has one valid notification try/catch covering GM/VGM and post-closure FM notifications.
- Runtime verification is still required after pulling `main`.


## 2026-10-03 — Projects PM Settlement Visibility Checkpoint

- Commit: `c8863f953fa3ef82310a8d1ef3e764fbeacfad08`.
- Root cause of the reported PM UX gap: the project PM page already blocked final closure when the controlled balance was non-zero, but it did not render the financial-manager settlement ledger or the closure-request funding-return proof in the PM closure-review area.
- Fix: `modules/projects/view.php` now loads all `project_funding_returns` for the project and renders a dedicated PM section showing current controlled balance, recorded settlement total/status, settlement date/source account/amount/journal/actor, and the PS-provided settlement-proof document when present.
- The section explicitly distinguishes: no FM settlement yet, partial settlement, and full settlement. It does not create or modify accounting data.
- Server-side final-close guard remains authoritative: PRJ-0015 must remain blocked while the controlled balance is 250,000.00 SDG; after FM records the settlement and the balance reaches zero, the PM page should show the recorded settlement/proof and the final-close control should become eligible subject to all other closure guards.
- Runtime status: source fix committed; browser/runtime verification is still required. Do not claim the PM display as runtime-passed until verified locally.


## 2026-10-03 — PM Closure Review Settlement Visibility Correction

- Commit: `e03600cf27d3bfd9bcde532d0ffc7687bc9093cb`.
- The previous settlement/proof UI was inserted only inside the Project Supervisor branch of `modules/projects/view.php`, so it was not visible to the Projects Manager. This was a placement error, not a workflow/accounting error.
- Corrected placement: the Projects Manager closure-review section now displays the controlled balance, FM-recorded `project_funding_returns`, journal/source details, settlement status, and the PS-provided `funding_return_proof` document when a closure request is under review or a settlement exists.
- PRJ-0015 remains blocked at 250,000.00 SDG until FM records the settlement. Runtime verification is required after pull; source inspection alone is not a runtime pass.


## 2026-10-03 — Closure Balance FM Routing Correction

- Commit: `eb2c2b554a8bcb81cf1b6b3ad55d637b1278e48c`.
- Corrected the closure workflow gap: when the PS submits a closure request while a controlled project balance remains, the request now notifies both the Projects Manager and active Financial Manager recipients using the closure-request history ID as the event reference.
- The FM notification links directly to the FM project review page, where the existing funding-return control records the accounting return to the organization account.
- The PM closure-review message now explicitly states that the settlement request has been sent to FM and that final closure becomes eligible only after the controlled balance reaches zero.
- This does not bypass the accounting guard or create a second accounting release. FM remains the only role allowed to record the project funding return; PM performs final closure only after reconciliation is complete.
- Runtime verification is required: confirm FM receives the settlement notification, can see the return control for PRJ-0015, record the 250,000.00 SDG return, and that PM then sees zero controlled balance and an enabled final-close action.

## 2026-10-03 — Projects closure workflow correction: PM administrative close → FM financial close

A source-driven review superseded the earlier closure design that treated the FM return as a prerequisite to PM closure. The authoritative workflow is now:

1. **PS** submits the closure request and provides the return information/proof when unused project-controlled funds remain.
2. **PM** reviews the request and performs the **administrative/project closure with return**. The Projects Department ends its role here. PM does **not** post the accounting return and is not blocked by a non-zero controlled balance.
3. After PM closure, the project is administratively **closed** while financial closure may remain pending. The system notifies **FM only at this handoff point**.
4. **FM** opens the financial-closure task, reviews the PS return proof, records the actual return of the remaining controlled balance to the organization source account, and posts the accounting journal through the existing funding-return engine.
5. When the controlled balance reaches zero, FM records the explicit 'FM_FINANCIAL_CLOSURE' audit event and the system notifies **PM that financial closure is complete**. PS is not notified again because the Projects Department role ended at PM closure.

### Notification sequence — authoritative

PS closure request → PM notification → PM administrative close → FM financial-closure notification → FM return/accounting → FM financial-closure completion → PM completion notification

There is **no FM notification at the PS request stage**, and no second accounting release is created by either PM closure or FM financial closure. Notification event references remain event-specific and use the closure/financial-closure event keys rather than reusing an unrelated project ID event.

### Accounting/state boundary

- `project_lifecycle.lifecycle_status = 'closed'` represents the administrative/project closure performed by PM.
- A non-zero `akp_project_controlled_balance()` after that point represents **financial closure pending**, not an invalid project state.
- 'FM_FINANCIAL_CLOSURE' in `audit_log` represents completion of the financial-close stage without requiring a new schema column.
- `akp_return_project_funding()` is now restricted to an administratively closed project and requires the recorded PM `CLOSE` audit event. It remains FM-only and uses the existing balanced return journal.
- If the controlled balance is already zero, FM can explicitly confirm financial closure without creating a fictitious return transaction.

### Implementation commits

- `52622eebfd613fe242ff47a6c2bdfeee4e58f84c` — funding-return helper now requires administrative closure and prevents duplicate financial closure.
- `2a91f507200d1d41490fa946646517ccb5d26be7` — FM financial-closure action, post-close authorization, completion notification, and post-close UI routing.
- `5f1282424d14308f260ae13739b746d256e2f377` — removed duplicate FM closure panel after source review.
- `86c669f607482e08cf30705980955c6a567e25aa` — PM closure UI now explicitly explains the administrative-close → FM-financial-close handoff.

### Runtime status

**STATICALLY IMPLEMENTED — RUNTIME VERIFICATION PENDING.** The controlled PRJ-0015 scenario remains the next runtime gate. Do not claim financial closure or notification sequencing as runtime-passed until the user verifies it locally.

### Required runtime sequence for PRJ-0015

1. Pull the latest `main`.
2. As PS, submit/confirm the closure request with the existing return proof and verify the notification goes to **PM only** at this stage.
3. As PM, review the request and close the project even though the controlled balance is 250,000.00 SDG. Verify the project becomes administratively closed and PM receives no requirement to wait for FM.
4. Verify **FM** receives the financial-closure notification only after PM close and can open the FM financial-closure section.
5. As FM, verify the 250,000.00 SDG balance and PS proof, then record the return to the organization account. Verify the balanced return journal and controlled balance becomes zero.
6. Verify 'FM_FINANCIAL_CLOSURE' is recorded exactly once and **PM** receives the completion notification.
7. Verify PS is not re-notified and no second funding-release journal is created.


---


## 2026-10-03 — Closure workflow/root-cause correction

The previous closure guard conflated administrative project closure with financial closure. The required workflow is:

`PS closure request + return proof → PM administrative close → FM return + financial close`

### Authoritative boundaries

- **PS:** submits the closure request and return proof; no accounting return authority.
- **PM:** reviews/returns the request or closes the project administratively; no accounting return authority and no requirement to reduce the controlled balance to zero before administrative closure.
- **FM:** after PM closure, records the unused-fund return and completes financial closure.
- **PS is not notified of or responsible for the post-close accounting task.**

### Notification sequence

1. `project_closure_request` → active Projects Manager only.
2. `project_financial_closure_required` → active Financial Manager after successful PM `CLOSE`.
3. `project_financial_closure_completed` → active Projects Manager after FM completes the return/financial close.

Implementation commit: **e66d0452885a0f3c0270913cda698b0f522723f9**.

### Accounting invariant

PM closure does not create a journal. FM return continues through `akp_return_project_funding()`. The controlled balance is reduced by the recorded return, and `FM_FINANCIAL_CLOSURE` is the auditable completion event.

**Runtime gate: OPEN / NOT YET VERIFIED.**


---

## 2026-10-03 — Projects Phase 5 financial closure acceptance

The controlled PRJ-0015 closure sequence is now runtime-verified through the final PM notification boundary.

Evidence recorded:
- PM administrative closure completed while 250,000.00 SDG remained controlled.
- FM returned 250,000.00 SDG through the existing funding-return engine to **1200 — البنك**; the user confirmed the return completed without issue.
- FM financial closure completed and the PM received **اكتمل الإغلاق المالي للمشروع**, stating that the remaining balance had been returned to the organization's account and the financial-closure cycle had ended.
- The FM post-close page now presents **إغلاق المشروع المالي**, administrative status **مغلق**, financial status **مغلق مالياً**, and controlled balance **0.00 SDG**, with pending FM review controls hidden.
- No duplicate return, funding release, or second accounting transaction was created for this gate.

The authoritative financial-close completion marker remains the existing `FM_FINANCIAL_CLOSURE` audit event. The UI correction did not introduce a new lifecycle state or accounting mechanism.

**Result: PASS / CLOSED for this Phase 5 closure gate.** Any further Projects work must target a distinct reconciliation, security, workflow, or UX issue rather than reopening this completed gate.

---

## 2026-10-03 — Projects Phase 5 final certification / stale-checkpoint supersession

The remaining PRJ-0015 runtime gates identified earlier in the audit have now been exercised through the final financial-closure boundary. The controlled project reached a zero controlled balance after the single FM funding return, and PM received the final financial-closure-complete notification.

Earlier audit entries that say runtime verification is pending for the PRJ-0015 closure sequence are historical checkpoints and are superseded by the subsequent runtime evidence. They are intentionally retained for audit chronology.

**Final Phase 5 result: PASS / RUNTIME VERIFIED / CLOSED.** Historical project journals remain untouched.

## 2026-10-05 — HR Salary Register / Employment Scope

### Salary register

- `modules/hr/employees.php?action=salary_register` now provides a dedicated register of the first positive salary-history record for each employee.
- The register displays employee code, name, department, hire date, first salary effective date, salary and currency, plus total employee count and salary total/average.
- The initial salary-history placeholder of 0.00 created during automatic employee provisioning is intentionally ignored. The register therefore selects the earliest salary-history row with `basic_salary > 0`.
- Current salary values are test data used for payroll/salary-advance testing and were populated directly in the database. No salary amounts are hard-coded into the application.

### Employment scope correction

- Commit `b8adb067c3de34441192e63fff56c6b707b3aa22` changed the salary register to join the canonical `hr_employment_states` table and include only states where `category = 'working'` and `is_active = 1`.
- Suspended, terminated/separated and other non-working employees are therefore outside the current salary-register scope.
- This does not delete, hide from historical audit, or alter their existing salary/payroll/accounting records. It only prevents non-working employees from being treated as currently eligible salary-register/payroll scope.
- Payroll already applies the same working-state principle when generating a new payroll period.

### Data principle

Employment lifecycle is the authoritative boundary for current payroll/accounting eligibility. Historical financial evidence remains immutable and auditable after an employee becomes non-working.

## 2026-10-05 — HR Attendance Policy Foundation

A policy-driven attendance foundation was implemented on main.

Implementation:
- database/migrations/2026-10-05_hr_attendance_policy.sql adds the versioned policy table.
- modules/hr/lib_attendance_policy.php provides validation, effective-policy lookup and login-attendance helpers.
- modules/hr/attendance_policy.php provides HR Manager/Admin policy administration with future-version edit/delete protection.
- index.php invokes the attendance helper after successful authentication.
- tools/finalize_daily_attendance.php provides the separate CLI absence finalizer intended for Windows Task Scheduler.
- dashboard/hr_dashboard.php exposes the attendance-policy administration page to HR Manager/Admin.

Design boundary:
- 07:00–16:00 and remote work are initial policy values, not hard-coded attendance rules.
- First qualifying login creates/preserves the day's first check-in; repeated login does not overwrite it.
- Canonical attendance eligibility remains based on employment state and approved leave.
- Automatic absence is separate from authentication and is idempotent.
- No runtime DDL, triggers, views, stored procedures, functions or events were introduced.

Runtime status: STATICALLY IMPLEMENTED — RUNTIME VERIFICATION PENDING. The migration must be applied and the first policy/login/finalizer cycle verified locally before this gate is marked closed.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.


## 2026-10-05 — Same-page POST navigation remediation

A repository-level audit confirmed that many operational pages use same-path HTTP POST forms that submit as full-document browser navigations. The previous `akGlobalScrollRestore` mechanism only repaired the viewport after that navigation and therefore could not eliminate the underlying visible jump.

Implemented on `main`:
- `assets/js/app.js`: centralized same-path POST interception using `fetch()`, replacing only the shared `.content` region and preserving the existing viewport; cross-page redirects remain normal navigations.
- `modules/hr/attendance.php`: attendance bulk refresh now uses the centralized in-place refresh; return-from-leave confirmation now re-enters the normal submit event so the centralized handler can process it.
- `includes/header.php`: removed the obsolete first-paint scroll-restoration guard because same-page POSTs are now kept in the existing document.

Commits: `412301e82718dba9a96146b4b9b42c4298469619`, `c7ade0903bf3aefbeed2d0e272c91a889d19038c`, `9e2e781ba465fd0c3ab4bdd41f0ac4e52f1b5b84`.

**Verification status:** code/repository review completed; local browser runtime verification is still required before marking the issue closed. Do not treat the change as runtime-verified until the attendance and representative same-page POST workflows are tested locally.


## 2026-10-06 — GM Salary Advance Waiver Verification Evidence

### Controlled payroll dependency test

The existing Stage 5 rollback-only payroll integration harness was run locally on `main` with:
`php tools\run_salary_advance_stage5_payroll_tests.php`

Observed results:
- **PASS — Payroll repayment application:** request `SAR-2026-00004`; temporary payroll ID 24; deduction 5,000 SDG; one repayment trace row; schedule status `paid`; outstanding balance 45,000 SDG; journal 116 balanced.
- **PASS — Duplicate repayment protection:** the same request/payroll could not be applied twice.
- **PASS — Rollback-only cleanup:** no payroll/request/schedule/journal mutation was committed.

The test exercised the actual salary-advance payroll repayment/accounting path and therefore provides runtime evidence for the waiver's repayment dependency without consuming a real payroll period or changing permanent financial history.

### Boundary

The GM waiver feature remains **open for waiver-specific runtime verification**. Required remaining evidence includes FM preparation, GM approval/rejection, current-period paid-deduction refund, remaining-balance waiver, future deduction blocking, undistributed request cancellation, concurrency/duplicate execution safety, draft refresh, post-commit notifications, audit preservation and final 1410 reconciliation.

No production payroll-generation change was made as a result of the earlier read-only October simulation or this rollback-only test.

## 2026-10-06 — GM Salary Advance Waiver: Notification Failure-Isolation Gate Prepared

The next waiver verification gate is notification failure isolation. No previously closed waiver runtime gate was repeated.

### Direct source inspection

The production notification path was inspected in:
- `modules/accounting/lib_transaction_review.php`
- `ak_transaction_review_notify_event()`
- `modules/hr/lib_salary_advance_waiver.php`
- `hrSalaryAdvanceWaiverNotifyExecution()`

Confirmed implementation boundary:
1. FM preparation commits before its GM notification helper is called.
2. GM approval commits before its FM notification helper is called.
3. FM execution commits before its execution notification helper is called.
4. `ak_transaction_review_notify_event()` catches `Throwable` from notification delivery.
5. Workflow-reference notification attempts are already guarded by a nested fallback to the legacy notification schema when `reference_id` / `reference_type` are unavailable.
6. The employee execution query requires an active linked user account.

The installed legacy notification schema was not assumed to contain workflow-reference columns. Existing runtime harnesses already verified the actual installation behavior.

### Test-seam decision

Repository inspection found no existing deterministic notification-failure seam. Manufacturing a SQL failure by guessing column lengths, constraints, foreign keys or schema mutations would violate the project rules.

A narrow inert test-only seam was therefore added at the actual notification-delivery boundary:
`ak_notification_test_delivery_hook()`

It is invoked only when a test defines that function before the notification helper is loaded. Normal production execution has no such function and therefore follows the existing code path unchanged.

### New runtime harness

`tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

The harness:
- uses the real production FM-preparation, GM-approval and FM-execution functions;
- forces exactly one controlled notification-delivery Throwable on the first execution notification attempt;
- allows the subsequent execution notification attempt to continue;
- verifies the decision remains `executed`;
- verifies the disbursed request remains disbursed with outstanding balance zero;
- verifies the waiver journal exists and is balanced;
- verifies future schedule overlays remain committed;
- verifies waiver audit evidence remains committed;
- verifies the failed notification does not create a notification row;
- verifies the subsequent employee notification is still delivered;
- restores the request and removes only the test decision, journals, audit rows, schedule overlays and newly-created notifications.

Implementation commits:
- `2319ccc421fb8944dc3b59a874410868d658d333`
- `bcce74ec825e3e45193f3e44f58e67bcbe2aa43c`
- `0e072f9f05d2cbf5067126f1147eaa6dfb2e59c7`
- `5abfe6e36b7681cff9ff639e4e80e3d4680cd924`
- `446451977a23fb78c71406446ce7003705cebf14`
- `46aec64c840bd64007ccff242a2a1a9403f4c8e8`

**Runtime status: PENDING USER EXECUTION.**

The exact next gate is runtime execution of the new harness. If it passes, record the evidence and move to audit-preservation runtime verification. If it fails, stop and inspect the exact failure before any further change.

Salary Advance Stages 1–6 remain closed. The same-employee multiple-advance and true two-process concurrency evidence gaps remain open.

## 2026-10-06 — GM Salary Advance Waiver: Notification Failure-Isolation PASSED

Runtime command:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

Result:
- `PASS | Notification failure isolation | request=SAR-2026-00004 | decision_id=13 | delivery_attempts=2 | injected_failures=1 | status=executed | outstanding=0 | waiver_journal=125 | schedule_overlays=10`
- `PASS | Notification failure-isolation cleanup | decision_rows=0 | journals=0 | notifications_restored=1 | request_restored=1`

The test used the real FM-preparation -> GM-approval -> FM-execution path and injected one deterministic failure at the actual notification-delivery boundary. The financial/business execution remained committed, the subsequent notification attempt proceeded, and cleanup left no test residue.

**Notification failure-isolation gate is CLOSED.**

The next verification is audit preservation. No completed waiver gate is to be rerun without concrete regression evidence.

Open evidence gaps remain:
- same employee with multiple eligible advances;
- true two-process concurrent execution.


## 2026-10-06 — GM Salary Advance Waiver: final audit-preservation and 1410 gates CLOSED

The final two runtime verification gates for the GM Salary Advance Waiver have passed.

### Audit preservation runtime gate

Command:
`php tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`

PASS evidence:
- request `SAR-2026-00004`
- decision `15`
- original disbursement journal `73`
- temporary payroll `24`
- temporary payroll journal `129`
- repayment `6`
- historical audit rows preserved `6`
- waiver audit rows `3`
- schedule overlays `9`
- cleanup restored the complete pre-test evidence set.

The source and migration audit established that original disbursement/payroll/repayment/schedule evidence is preserved and waiver evidence is additive. The rollback-only runtime gate confirms that behavior on the real fixture without leaving temporary payroll/repayment residue.

### Final 1410 reconciliation runtime gate

Command:
`php tools\\run_salary_advance_1410_reconciliation.php`

PASS evidence:
- ledger balance `190000`
- live outstanding `190000`
- debit `220000`
- credit `30000`
- disbursement debit `220000`
- direct repayment credit `30000`
- payroll credit `0`
- waiver-refund debit `0`
- waiver credit `0`
- open requests `5`
- journals/lines `11 / 11`.

The reconciliation formula is based on the actual accounting implementation:

`1410 = disbursement debits + waiver-refund debits - payroll repayment credits - direct-repayment credits - waiver credits`

The live subledger agrees exactly:

`220000 - 30000 = 190000`.

The reconciliation correction added recognition of the existing `salary_advance_direct_repayment` 1410 credit reference type and initialized CLI request context to avoid an unrelated `REQUEST_METHOD` warning. This was a test-harness-only correction; production financial logic was not changed.

### Final waiver audit disposition

**GM Salary Advance Waiver — RUNTIME VERIFIED / CLOSED at the documented acceptance boundary.**

Completed gates include transaction-composable waiver execution, paid-deduction refund, undistributed cancellation, future-deduction blocking/draft refresh, approved-unpaid payroll protection, blanket scope, sequential duplicate protection, post-commit notifications, notification failure isolation, audit preservation and final 1410 reconciliation.

Explicit evidence gaps remain:
- same employee with multiple eligible advances;
- true two-process concurrent execution.

Neither gap is a failure, but neither should be represented as runtime-proven.

Do not rerun closed waiver tests absent concrete regression evidence. Do not reopen Salary Advance Stages 1–6. The unfinished attendance same-page POST scroll-jump issue remains out of scope.

## 2026-10-06 — i18n cleanup: final runtime verification

The English i18n cleanup is **RUNTIME VERIFIED / CLOSED** at the documented acceptance boundary.

Static gate:
`php tools\\i18n_gap.php`

Result:
`Files with gaps: 0 | Untranslated Arabic strings: 0`

Runtime gate:
The user tested the affected pages in English mode (`?lang=en`) covering attendance policy, payroll, payroll policy, salary-advance waiver FM/GM, accounting FM dashboard, HR dashboard and reports.

Observed result:
- all tested UI rendered in English;
- page behavior remained normal;
- no untranslated Arabic runtime defect was reported.

Interpretation: the scanner establishes static translation coverage, and the user runtime test establishes the corresponding browser rendering/behavior for the affected pages. This item is closed unless a later source change introduces a regression.

## 2026-10-06 — Attendance ↔ Payroll integration: pre-runtime source audit

The 2026-10-05 attendance/payroll integration was audited before runtime verification.

The authoritative implementation already had the intended separation:
- attendance policy determines working days and attendance facts;
- payroll policy determines absence/unpaid-leave monetary treatment;
- missing attendance rows are not inferred as absences;
- attendance deduction is used by the salary-advance eligibility calculator when the basis is `net_before_advance`.

A concrete downstream calculation defect was found: `hrSalaryAdvancePayrollRefreshDraft()` omitted `attendance_deduction` when recomputing `payroll.net_salary`. This could leave the displayed/payable draft net salary higher than the attendance-adjusted salary.

The production fix was narrowly applied in:
`modules/hr/lib_salary_advance_payroll.php`

The corrected formula is:
`gross - ordinary deductions - attendance deduction - salary-advance deduction`

A rollback-only harness was added at:
`tools\\run_hr_attendance_payroll_integration_tests.php`

It deliberately performs no DDL and does not commit its temporary fixture.

**Runtime verification remains PENDING USER EXECUTION.**

## 2026-10-06 — Attendance ↔ Payroll integration: final runtime verification

Runtime verification of the Attendance Policy ↔ Payroll integration is complete.

The first rollback-only harness passed the core attendance branches, including explicit absence, paid leave, unpaid leave, non-working-day absence, missing attendance, draft deduction refresh and rollback.

The extended rollback-only harness then passed:
- payroll-policy effective-date selection;
- attendance-policy effective-date selection;
- `net_before_advance` salary-advance interaction;
- paid payroll immutability;
- rollback cleanup.

Exact final runtime evidence:
`PASS | Attendance/payroll extended integration | policy_effective_dates=PASS | payroll_versions=3,4 | attendance_versions=2,3 | net_before_advance=PASS | employee=8 | request=SAR-2026-00007 | period=2026-10 | absence_date=2026-10-06 | attendance_deduction=333.33 | salary_advance_deduction=9666.67 | net_salary=0 | paid_payroll_immutable=PASS | rollback=PASS`

The previously fixed production defect in `hrSalaryAdvancePayrollRefreshDraft()` is therefore runtime-covered: the refresh path now subtracts `attendance_deduction` before salary-advance recovery.

The CLI warning at `config/functions.php:52` is an existing CLI-context warning and is not considered an integration failure.

**Audit disposition: Attendance ↔ Payroll integration RUNTIME VERIFIED / CLOSED.**

## 2026-10-06 — FM UI consistency cleanup

A focused UI consistency pass corrected two visible inconsistencies in the FM dashboard quick-action section and the GM salary-advance waiver FM page.

- `modules/accounting/fm_dashboard.php`: the final two quick-action cards — **إدارة الرواتب** and **منظمة فينا الخير** — now have the same fixed-height/card treatment as the other FM quick-action cards, including their missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the top page title section now uses the same FM blue-gradient header treatment as the FM dashboard instead of the previous standalone white bordered card.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`

Runtime browser verification is still required on the local XAMPP application. Do not mark the UI fixes runtime-verified until the user confirms the affected FM pages visually.

## 2026-10-06 — System-wide same-page POST scroll-jump remediation: FINAL AUDIT DISPOSITION

The previously observed browser jump-to-top after same-page POST actions is now **RUNTIME VERIFIED / CLOSED at the system-wide acceptance boundary**.

### Final architecture
The fix is centralized in `assets/js/app.js` and is **default-on / opt-out**, rather than requiring individual pages to opt in.

For a qualifying same-path POST, the global capture-phase submit handler prevents native full-document navigation and routes the request through the existing in-place response renderer. The renderer preserves the current viewport while replacing the shared `.content` region and restores focus/scroll state as appropriate.

The implementation also hardens form resolution by using DOM attributes rather than the potentially shadowed `form.action` property, and it respects effective submitter overrides.

### Intentional native-navigation boundaries
Native behavior remains available for multipart uploads, non-self targets, cross-path/cross-origin submissions, and explicit native-post opt-outs. This prevents the global policy from interfering with workflows that genuinely require navigation or a browser-managed upload/download flow.

### Runtime evidence
The attendance workflow was tested after the page-specific `data-ak-same-page="1"` attribute was removed. The user confirmed the page remained in place after the POST action.

This is the decisive evidence that the global mechanism is functioning system-wide rather than depending on an attendance-specific workaround.

### Final implementation commits
- `af5fe03e3592972c61ec2821fa7ad0a7f4b7abfd`
- `0a06fb57f687f4ac56c623251a7fa38d6dba083d`
- `4496a8f6fb4d38a856483b25d856c640a81732ec`
- `396576c6bc8d1f26af37a09e80c15909aac06e60`
- `8389708b84a9853f164d3b73d6f810dbe4967ef5`

**Audit conclusion:** the old attendance-specific scroll-jump defect is closed. The system-wide same-page POST policy is now the authoritative behavior. Do not introduce page-specific scroll restoration or opt-in POST interception to solve this issue again unless a concrete regression demonstrates a gap in the global policy.

## 2026-10-06 — FM UI consistency cleanup: RUNTIME VERIFIED / CLOSED

The focused FM UI consistency cleanup is now **runtime verified / accepted**.

Verified/accepted changes:
- `modules/accounting/fm_dashboard.php`: the final FM quick-action cards now use the same fixed-height/card treatment, including the previously missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the FM salary-advance waiver page title/header now uses the FM blue-gradient header treatment consistently with the FM dashboard.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`
- `40d4b3ad552ac0f9a77720cbd112f41bee015aa6`

User runtime result: the FM UI is visually accepted. Do not reopen these UI issues absent a concrete regression.

## 2026-10-06 — Production-preparation database cleanup: final audit disposition

The production-preparation cleanup logic was audited against the current 101-table database and the original script's documented preservation contract.

### Final semantics

The script preserves:
- users and employees;
- families and family children;
- sponsors, sponsorships and sponsorship children;
- sponsor-supervisor assignment history;
- supervisor letters and supervisor-letter assignment history;
- roles, departments, currencies, letters, medical needs and settings;
- accounts, except the original eight explicitly named development/test account codes.

Every other current base table is treated as development/test data and cleared with `TRUNCATE TABLE`.

The script does not drop tables, introduce runtime DDL, or delete physical files from `storage/`.

### Runtime evidence

Commit:
`5f92a919ec64127428fda5e0adf8b5243ad8554f`

Disposable database:
`ahl_el_kheir_production_test`

The test began from a fresh dump of the current live database and executed the corrected script against the disposable copy.

Observed results:
- preserved core and reference/configuration counts remained populated;
- all 83 cleanup-table counts were zero;
- original test-account remaining count was zero;
- the SQL execution completed without an error or warning.

The preserved-count evidence included users 26, employees 25, families 2208, family children 4910, sponsors 2858, sponsorships 3560, sponsorship children 2965, sponsor-supervisor history 270, supervisor letters 56, supervisor-letter history 6, roles 14, departments 8, currencies 4, letters 29, medical needs 14, settings 18, and accounts 38.

### Audit conclusion

**Production-preparation database cleanup is RUNTIME VERIFIED / CLOSED at the disposable-database acceptance boundary.**

The test artifacts `database/production/current_test_database.sql` and `database/production/prepare_production_database_test.sql` are disposable verification artifacts and are not part of the repository deliverable. They must not be uploaded/committed.




## 2026-10-12 — Stage 7 isolated rescheduling transaction and feedback markup audit

Read-only result verification against salary_advance_browser_test after the user's browser submission established the following for request 7:
- the legacy/current plan row ID 2, plan 1, was marked superseded and its five schedule rows remain;
- new plan row ID 8 (plan 2) is active with a 50,000.00 starting balance, start month 2026-11-01, six-month duration and 10,000.00 monthly cap;
- five new installments total 50,000.00; maximum installment is 10,000.00; months span 2026-11 through 2027-03;
- audit_log row 2263 records HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE.

The success message being rendered inline was traced to this page's success/error alerts not matching assets/js/notifications.js transient alert selectors. Commit b546005f7f7996d1c4f1946de82e9c72563733f7 adds alert-dismissible only to the success/error alerts, relying on the existing global converter rather than adding page-specific toast code or changing persistent instructions. Local runtime confirmation is pending. This verifies the isolated rescheduling transaction only; payroll/accounting path remains unverified and Stage 7 remains OPEN.


## 2026-10-12 — Stage 7 payroll acceptance progress and payroll table refinement

The isolated browser workflow has reached November 2026 draft payroll generation in `salary_advance_browser_test`. The application created 22 drafts using payroll policy version 2. Read-only SQL identified payroll row ID 46 for employee ID 8 / EMP-0021: basic salary 10,000.00, salary-advance deduction 10,000.00, net salary 0.00, status `draft`, accounting status `none`, with no accounting entry, journal entry, or payment account yet. This confirms the active November rescheduled installment is reflected in the draft. It does not yet prove repayment application or accounting posting. Do not use the period-wide “approve drafts” action; it affects all 22 records.

Read-only account lookup in the isolated database returned account ID 2 / code 1200 (Bank), ID 37 / code 1410 (Employee Salary Advances Receivable), and ID 14 / code 5200 (Salaries). The next acceptance gate is to approve and pay only payroll row 46 using the page’s per-row actions, then verify exactly one repayment application, schedule/payment linkage, request outstanding balance, paid payroll state, posted accounting entry, and a 10,000.00 credit to account 1410. The canonical `ahl_el_kheir` database must not be modified.

The user also requested a more compact payroll table. Commit `ae7d9fc6209a8ff1366afa96b9fb73720d614249` on `main` removes the Contract and Policy columns, removes redundant currency labels from amount cells, tightens input/typography spacing, adds distinct blue cell borders and a blue-tinted header, and removes now-unused joins from the month-list query. The shared policy summary above the table remains. The user later reviewed the compact layout locally and described it as much better. This is browser visual feedback only; no separate PHP syntax-check result is asserted here.

The user confirmed the rescheduling success/error feedback now appears as a toast after commit `b546005f7f7996d1c4f1946de82e9c72563733f7`. The isolated payroll repayment/accounting acceptance and temporary-environment cleanup are now complete; see the dated Stage 7 acceptance-closure entry below for verified results. Do not restore the temporary routing again or repeat the completed acceptance absent concrete regression evidence.

## 2026-10-12 — Stage 7 acceptance evidence and closure boundary

### Verified isolated browser journey
- Browser rescheduling created active plan 8 for request 7 (`SAR-2026-00007`) in `salary_advance_browser_test`, effective 2026-11-01, with five installments of 10,000.00; previous plan 2 was superseded and audit row 2263 recorded the reschedule.
- November payroll row 46 for employee 8 contained salary-advance deduction 10,000.00 and net salary 0.00. The user approved and paid only row 46; the other generated payroll rows remained drafts.
- Payroll row 46 ended `paid` with `accounting_status=posted`, linked to posted journal entry 116 (`PAY-46`). Journal lines: account 5200 debit 10,000.00; account 1410 credit 10,000.00. The journal is balanced. No bank 1200 line is expected because net pay was zero.
- Exactly one repayment trace exists for request 7 / schedule 42 / payroll 46, amount 10,000.00, outcome `applied`, accounting entry 116. Schedule 42 has 10,000.00 applied and status `paid`. Isolated request outstanding is 40,000.00, down from 50,000.00.

### Canonical-data and cleanup checks
Read-only queries against canonical `ahl_el_kheir` show policy version 1 still has `allow_fm_repayment_rescheduling=0`, maximum duration 6 months and monthly cap 10,000.00. Canonical request 7 remains `disbursed` with outstanding 50,000.00. The account 1410 posted-balance query returned 250,000.00; it is recorded as observed, not treated as a new reconciliation assertion. The temporary `.htaccess` override was restored from a validated backup; final contents are exactly `Options -Indexes`. The temporary probe file is absent. The protected receipt remains present and untouched; local `git status --short` showed only that receipt as untracked on `main`.

**Conclusion:** Stage 7 acceptance is closed at this boundary; no canonical policy or financial data was changed. Do not repeat the completed browser/payroll acceptance absent regression evidence. The same-employee/multiple-eligible-advance case remains unproven in local runtime, as does true concurrent production execution in local runtime. CI contention results are not a substitute for claiming those local-runtime scenarios proven.


## 2026-10-12 — Employee incentives source audit opened

Source-level evidence, local verification boundaries, and agreed business rules are documented in `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. The user verified sponsor attribution PHP syntax and local column existence on 2026-10-12. A live metadata audit confirmed relevant tables are InnoDB and exposed important constraints: sponsor attribution/payment/win-back indexes are non-unique, payroll has unique employee-period indexes, journal entry codes and account codes are unique, and the source tables do not provide a unique incentive idempotency key. Foreign keys include `sponsors.brought_by_user_id -> users.id`, `sponsor_payments.supervisor_id -> users.id`, `sponsor_payments.transaction_id -> transactions.id`, and `winback_campaigns.handled_by -> users.id`. FM payment posting, separate project closure gates, and payroll immutability remain the integration boundaries. Direct sponsor INSERT runtime behavior and the incentive feature remain unverified/incomplete. The latest backup commit `de397397432797c674a9bf06133e90d57bffb5b7` is a data-bearing snapshot and must not be restored into the live database.

At this historical checkpoint, no feature code/migration/live DB changes had been made. Later commits introduced the account, incentive ledger, category catalogue, and policy UI changes. They remain pending local XAMPP/MariaDB and browser verification.

## 2026-10-12 — Employee Incentives implementation checkpoint

A new active feature workstream has begun. Committed on `main`: account 5500, the incentive policy/event/award/project-allocation/adjustment schema, the policy administration page, and the category/custom-field extension. The page records GM/FM agreement details, creates draft versions, supports HR/FM draft/field administration, and keeps activation with FM/Admin without a second GM approval. The latest category migration and page changes have not yet been locally syntax/runtime verified. No historical incentive events are backfilled. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for business rules and verification gates.

## 2026-10-12 — Salary portal navigation change

modules/hr/salary_portal.php is now committed as a role-filtered entry point for salary-advance management, salary-advance waivers, payroll, and employee incentive policy. modules/accounting/fm_dashboard.php replaces the three standalone salary-related cards with one portal card; the card is shown only for roles that have a salary-portal destination. Each target page retains its own authorization check. Commits: 8bc2be917afaa09d50a3c46f4d13a3922c42648b and 94f5c9c5666a84cea8fc4a161277dba3b707e2a1. Changes are not yet locally linted or browser-verified. The requested extensible incentive type design remains open: existing ENUM constraints and the difference between source-driven types and manual awards must be handled before migration/page changes.

## 2026-10-12 — Incentive policy extensibility and deferred recognition feature

The system-wide currency adjustment is visible on the user's local policy page. Commit `12af8e46b2a9e042322bddf939462b973dd612d0` removed the per-rule currency input and derives new rule currency from `APP_CURRENCY_CODE` (SDG). Local lint/runtime validation is not yet evidenced.

The user deferred monthly employee performance recognition, automatic monthly scoring/ranking, co-winner selection, and certificate issuance to a separate future feature. The proposal is preserved in `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`; it is not part of the current implementation scope.

The current requirement is to allow FM/HR to add extra fields to incentive policy records and introduce categories without repeated schema ENUM edits. Source changes are now committed: a catalogue-backed category model replaces the two ENUM columns while preserving existing codes, and custom policy fields are stored per policy version. HR Manager/HR Staff and FM/Admin can create drafts/add fields; activation remains FM/Admin only. Custom categories are manual-review only and cannot generate awards/payments until a separate award workflow is implemented. Monthly performance recognition/certificates remain deferred.

## 2026-10-12 — Incentive policy extensibility and deferred recognition

The SDG currency input removal is visible on the user's local policy page (commit `12af8e46b2a9e042322bddf939462b973dd612d0`); local lint/runtime validation is not yet evidenced. Monthly employee performance scoring, ranking, co-winner handling, and certificates are deferred to a separate future feature. The current requirement is versioned, extensible policy metadata. Do not confuse custom policy fields with custom incentive award types: the current award-type columns are ENUMs and need a separate catalogue/workflow design. Preserve FM activation authority and the agreed no-second-GM-approval rule.


## 2026-10-12 — Incentive category catalogue/custom-field source audit checkpoint

Source changes committed on `main`:
- `eb92fa5a42d00c086da0fe6a439511178d8c149b`: new catalogue and custom-field migration; existing four incentive codes are seeded and retained, rule/event type columns are converted from ENUM to VARCHAR and linked to the catalogue, and custom policy field definitions/values are stored by policy version.
- `68758c17c6363083f798e019c6348bdb596acc8c`: policy UI uses database categories, permits HR/FM draft and custom-field actions, and keeps activation restricted to FM/Admin.

Security/financial boundary: newly created categories are `manual_review` and cannot issue awards automatically. Their presence in a policy is configuration only; nomination, verification, award creation, payroll scheduling and accounting posting are not thereby implemented. Existing built-in categories retain their codes and source-event mode.

Local migration, PHP lint, and browser/runtime checks are still pending. Do not claim the new source changes are runtime-verified or proceed to reward issuance until these gates pass.


Migration follow-up: commit `eb92fa5a42d00c086da0fe6a439511178d8c149b` guards the ENUM-to-VARCHAR conversion using live column metadata, reducing rerun risk after partial application. This is not a substitute for testing the migration on the target MariaDB 10.4 environment.

## 2026-10-12 — Incentive category code generation audit checkpoint
Source change on `main`: `modules/hr/incentive_policy.php` generates `hr_incentive_types.type_code` server-side from the English category name. The code preview is read-only and has no submitted `type_code` field; the server ignores any client-supplied code. Normalization lowercases English text, replaces non-alphanumeric runs with underscores, ensures an alphabetic prefix and minimum length, limits to 80 characters, and adds `_2`, `_3`, etc. on collisions. The database unique key `uq_hr_incentive_type_code` remains the final integrity barrier for duplicate codes; concurrent collision behavior still needs local test evidence. The update also includes strict GM/FM agreement timestamp and notes validation.

Commit `23bdf2a84d81cd86f753c283e462f06f828aa510`. Arabic/English rendering of existing categories was confirmed by the user. The new preview, server code generation, PHP lint, and collision behavior have not yet been runtime-verified locally. Reward amounts remain unapproved and no policy should be activated for testing.


## 2026-10-13 — Incentive policy GM approval workflow audit boundary

The policy page and migration have been committed on main:
- 44b6fdcb62e3eb54c86255fdea7b6f22709f22c8 — status enum and durable approval history migration.
- 38e79e0851eed31640de00553c843c2d2920bdce — workflow handlers and full draft editor.
- 6ad9a4308e19f36b0a53132ab39b2048b00f56a4 — removal of the old draft activation UI path.

Approval state path: draft → pending_gm → approved → active; GM rejection stores a mandatory reason in history and returns the policy to editable draft. Server-side role checks allow only general_manager/gm to approve/reject and only FM/Admin to activate. Agreement date/notes were removed from the new form and no longer gate activation; legacy columns/data are preserved. The editor updates policy name, effective date, category enablement/reward amounts, and custom-field values while status is draft.

No local runtime proof is available yet. Pending evidence: migration application and safe rerun, PHP lint, live-schema foreign-key compatibility, and controlled role/transition/browser tests. The old agreement workflow must not be treated as current policy.

## 2026-10-13 — Incentive policy custom-field concurrency guard

Commit `c53a5dd6b32f6a737f7d6264df7b033e3383cb45` updates `modules/hr/incentive_policy.php`: policy-management permission is checked before opening a transaction; custom-field creation locks the policy-version row with `SELECT ... FOR UPDATE`, requires the row still be a draft, inserts the field, then commits. This serializes the custom-field insert against submission, which locks the same policy row while validating required fields. Existing exception handling rolls back an open transaction.

The GM approval migration is applied locally and the schema was inspected. PHP syntax checks for the policy page and GM dashboard passed, and the policy page loads. Full approval/rejection/activation runtime verification remains pending because the local database has zero policy versions, rules, and custom fields. No concurrent two-process test has been run; do not claim it has been proven. Award issuance, source-event integration, payroll scheduling, and incentive accounting remain unfinished. Next: pull `main`, inspect the exact diff, lint the changed page, then perform safe local role/state verification without inventing business reward amounts.

## Deferred audit — payroll, deductions, advances, incentives, waivers, and accounting integration

**Timing:** Start only after the current employee-incentive GM approval workflow has passed its agreed end-to-end acceptance checks. This is a required future audit, not a claim that the integration is currently correct or complete.

**Objective:** Trace the real implemented business flows and prove, with source/schema evidence and reproducible local tests, that salary additions/deductions and related accounting entries reconcile without duplicate, missing, or mathematically incorrect amounts. Do not infer accounting rules; document any policy ambiguity for the user to decide before testing expected results.

### Required investigation checklist

- [ ] Read the canonical project/session documents and the continuation documents for payroll, salary advances/rescheduling, advance waivers, incentives, and accounting before starting.
- [ ] Inventory the actual source files, tables, columns, status enums, foreign keys, migrations, transaction boundaries, and existing test harnesses for payroll, attendance deductions, salary advances/repayment schedules, repayment rescheduling, advance waivers/refunds/cancellations, incentive policies/awards, accounting journals/transactions, and reversal/correction workflows. Treat `database/ahl_el_kheir.sql` and applied migrations as schema truth; do not guess.
- [ ] Build an evidence-backed end-to-end flow map for each supported operation: initiating record/event → validation and authorization → calculation → persisted snapshot/ledger → approval/state transition → payment or settlement → accounting posting → report/reconciliation → reversal/correction where supported.
- [ ] Independently recompute payroll gross additions, every deduction component, and net salary from source records. Check attendance/absence/leave policy effects, salary-advance installments, other existing deductions/additions, incentives only where an actual award-to-payroll path exists, rounding/precision, period boundaries, effective-dated policy versions, caps, partial amounts, and ordering of deductions. Do not assume an incentive policy by itself issues awards or schedules payroll.
- [ ] Trace salary advances from approval/disbursement through installment schedule, payroll deduction, repayment/rescheduling, cancellation, waiver, and refund. Check outstanding-balance arithmetic, paid versus future installments, duplicate processing, schedule/history preservation, and that waived/refunded/cancelled amounts cannot also be deducted or settled again.
- [ ] Trace each implemented salary/incentive/advance operation into the real accounting schema and posting code. Verify correct debit/credit direction and account mapping against documented accounting rules, equal debits and credits per journal, correct transaction date/period, source references, posting status, and reconciliation to the operational source ledger. Flag missing integrations explicitly instead of treating UI/workflow completion as accounting completion.
- [ ] Test idempotency/replay, duplicate requests, rollback after partial failure, transaction atomicity, row-lock/concurrency boundaries, reversal/correction behavior, and immutability of paid/posted records. Separate code-reviewed locking from genuinely tested concurrent-process behavior.
- [ ] Reconcile independent totals by employee and payroll period, advance and schedule, waiver/refund, incentive award (if implemented), accounting journal, and relevant financial reports. Identify orphaned/missing/doubled entries and differences caused by rounding or period/status filters.
- [ ] Use isolated disposable test fixtures or an explicitly rollback-only harness against the local XAMPP/MariaDB environment. Never create fictitious approved reward amounts or leave synthetic financial records in the live working database. Preserve existing user data and never use destructive Git or database cleanup.
- [ ] Run focused tests and the appropriate broader regression suite only where relevant; lint changed PHP, inspect SQL/migration compatibility, and capture exact commands, results, and expected-versus-actual arithmetic. Do not rerun closed suites without regression evidence.
- [ ] Produce a discrepancy register with severity, exact source/schema evidence, reproducible case, expected rule/source for the expected result, actual result, smallest justified fix, and regression test. Resolve accounting-policy questions before changing code.
- [ ] Close the audit only after every in-scope implemented flow is evidenced and reconciled. List unsupported or not-yet-built paths (including incentive award issuance/source-event, payroll scheduling, or accounting integration where still absent) as explicit gaps; never describe them as verified.


## 2026-10-13 — Incentive policy role boundary clarified

The user confirmed this is a higher-management workflow involving only HR Manager, General Manager (GM), and Financial Manager (FM). `modules/hr/incentive_policy.php` now limits draft creation/editing, custom-field management, and category management to `hr_manager` and `financial_manager`; review/approval/rejection remains GM-only; activation is FM-only. `hr_staff` and `admin` are no longer authorized substitutes in this workflow. Authorization source change committed as `63534c5fd3aa5b0b3fc8bf640d3ecfb2280f314a`; UI alignment committed as `b065a93a98e1786a2e81702c0fa956b3a414f646`. The policy-creation form is now hidden from GM-only reviewers, and status guidance no longer names Admin as an activation actor.

**Verification boundary:** this is a source-level authorization correction only. Local PHP lint and real XAMPP/MariaDB role-based acceptance are still required. The three accounts previously identified are HR Manager user 32, GM user 2, and FM user 29, but their current login usability has not been established. Do not claim the workflow accepted until runtime tests pass. Do not use HR staff or Admin as test actors.

## 2026-10-14 — Incentive category-code UI correction

The category creation form no longer displays the “رمز الفئة (تلقائي)” preview control. Commit `e4b0675dbed130a1a5235851df6dbdb4f342b524` removes the preview UI and its client-side JavaScript; server-side code generation and duplicate suffix handling remain authoritative. This change is committed on `main`, but local lint/browser verification has not yet been supplied. Policy approval lifecycle and downstream award/payroll/accounting integration are not thereby accepted.


## 2026-10-14 — Required UI feedback rule: toast, not top-of-page banners

This is a project-wide implementation rule, not an optional page-specific preference. Success, error, warning, and action-result informational feedback must be shown as temporary toast notifications through the centralized `AKNotify.toast` path, near the active workflow/modal when applicable. Do not leave action feedback as persistent banners at the top of a page. For legacy Bootstrap feedback nodes that need centralized conversion on a full-page load, add `data-ak-transient-alert="1"`; server flash nodes may use the established `data-ak-flash` convention. Keep field validation beside the relevant field, and keep genuinely persistent instructional/context panels separate from action feedback (mark them explicitly persistent if their markup matches transient-alert selectors).

**Required page-completion check:** inspect every success/error/warning/info message introduced or modified; verify both full-document POST/redirect loads and same-page/AJAX responses use toast behavior; confirm no action-result banner remains at the page top. Do not assume a page follows this rule merely because another module does. This rule applies to new pages and existing-page changes across the repository.


## 2026-10-14 — Incentive policy toast and English-language coverage update

Source-level review and correction completed on `main`:
- All eight successful incentive-policy actions now obtain their feedback from the stable incentive translation catalog and render through the page's transient-alert markers; the centralized `assets/js/notifications.js` path converts success/error feedback to toast notifications on normal page loads and observes alerts inserted during same-page/AJAX updates.
- Added matching Arabic and English stable catalogs `lang/incentive_policy_ar.php` and `lang/incentive_policy_en.php` with 135 matching keys covering page labels, status/history terms, confirmations, validation messages, and action feedback.
- Localized field-type and Yes/No option labels server-side because the language bridge intentionally does not translate native `option` text nodes; category names, custom-field labels, and currency names now use their English value when English mode is active, falling back to Arabic only when an English custom-field label was not supplied.
- Dynamic validation messages use translated prefixes and localized category/custom-field labels. User-entered policy names, custom-field values, rejection reasons, and other business data remain unchanged rather than being guessed/transliterated.

Repository source checks confirmed both catalogs contain 135 unique keys with no key mismatch, every `t('incentives.*')` key referenced by the module exists in both catalogs, all eight success feedback calls use the catalog, and both success/error alert nodes have `data-ak-transient-alert="1"`. This is **source-level verification only**. Local PHP lint, `php tools\\i18n_gap.php`, and browser checks in Arabic and English—including one successful action toast and one validation-error toast—remain required before marking runtime acceptance complete. Preserve the existing V1 test policy; do not create another policy solely to test notifications.


## 2026-10-14 — Incentive policy review-page presentation follow-up

The user confirmed the Arabic/English language toggle works and supplied local PHP lint results: `modules/hr/incentive_policy.php`, `lang/incentive_policy_ar.php`, and `lang/incentive_policy_en.php` all pass syntax checks. The official `tools/i18n_gap.php` scan reports 85 untranslated strings across 12 other files; `modules/hr/incentive_policy.php` is not listed, so unrelated files remain out of scope. The existing GM view exposed two narrow presentation issues: the system-currency notice was hard-coded in Arabic, and policy category/amount summaries had no visual separation. Commit `e446adee1ea9ae4e76bc5b48ad227926fec442c2` routes the currency notice through `t('incentives.currency_notice')` and styles each category/amount summary as a separated bordered item. This source correction is committed to `main`; local PHP lint and browser confirmation of the updated presentation remain pending. The Arabic creator name is stored user data and was intentionally preserved. V1 remains a draft; do not submit, approve, or activate until the toast acceptance checks and updated presentation are confirmed.


## 2026-10-14 — Incentive policy governance and notification handoffs

Source audit identified that the incentive policy page previously allowed both HR Manager and FM to create/edit/submit drafts, contrary to the clarified responsibility split, and had no incentive-policy notification calls at submit/approve/reject/activate transitions.

Implemented on `main`:
- Draft creation/editing, category management, custom fields, and submission are restricted to `hr_manager`.
- GM roles `general_manager`/`gm` remain the only approvers/rejectors.
- `financial_manager` retains activation authority only; it cannot create, edit, or submit drafts.
- After commit, submission notifies active GM-role users; approval notifies the HR policy creator and active FM-role users; rejection notifies the HR creator with the reason; activation notifies the HR creator and the GM who approved the policy.
- Notifications reuse `ak_transaction_review_notify_event()` and its delivery failure isolation. Activation's post-commit approver lookup is separately failure-isolated.

Commits: `21c94362c4f11f4000e050ba5e14160eedbac0fe`, `675bdc36b67ea1b7ede11abef37cdbf5c2340b84`.

No schema change was needed. **Verification is source-only; local PHP lint, server-side role denial/allowance tests, and notification recipient/appearance checks remain pending.** Keep the existing V1 acceptance policy inactive because its reward values are test values. A notification failure must never undo or falsely report failure for a committed policy transition.


## 2026-10-14 — Incentive policy rejection and active-policy fallback audit

Verified in `modules/hr/incentive_policy.php`: GM rejection writes a rejection-history row and returns the version to `draft`, allowing HR to edit and resubmit. Existing active policy versions are retired only inside the successful FM activation transaction. Therefore, if HR does not submit a replacement or FM does not activate an approved replacement, the prior active policy is not retired by these workflow steps. This source review does not yet prove payroll's runtime effective-policy selection; that must be verified against the actual payroll incentive integration before claiming payday fallback acceptance.

Found and fixed a role gate inconsistency: FM had permission for `activate_policy` but was excluded by the page-level access gate. Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` adds `$canActivatePolicies` to the gate. Success/error messages use transient-alert markers for toast conversion. Local syntax/runtime checks remain pending.


## 2026-10-14 — Incentive policy fallback audit

Source review confirms GM rejection records the reason and returns the version to `draft`, so HR can edit and resubmit. An overlapping prior active version is retired only in the successful FM activation transaction. If there is no replacement submission or FM does not activate an approved replacement, the previous active policy remains active. This does not yet prove payday selection in payroll; inspect and runtime-test the payroll incentive effective-policy lookup before closing fallback acceptance.

Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes the page-level access gate so FM can reach the existing activation action. Success/error action feedback nodes use `data-ak-transient-alert="1"` for centralized toast conversion. Local lint/runtime checks remain pending.

## 2026-10-14 — Hide unapproved incentive policies from FM

Commit `1ab2eac4173dc5f4f808865fc10c722058210bb3` changes `modules/hr/incentive_policy.php` so the FM policy list includes only `approved`, `active`, and `retired` versions. Drafts and `pending_gm` submissions are not disclosed to FM before GM approval; HR and GM retain the workflow views they need. This is a source-level correction only; local PHP lint and FM/HR/GM browser verification remain pending.

The existing V1 acceptance policy is still a draft and must remain unactivated. Do not delete it directly with SQL: its approval history and child records are acceptance evidence and may have referential constraints. Reuse V1 for HR edit/submit → GM reject with reason → HR revise/resubmit → GM approve. FM notification is expected at the GM approval handoff, not while the policy remains a draft. Verify actual recipient-role lookup and the persisted notification after approval before declaring delivery fixed. Payroll effective-policy selection remains a separate unverified gate.


## 2026-10-14 — Hide unsubmitted incentive-policy drafts from GM

Source audit found that `modules/hr/incentive_policy.php` filtered the policy list for FM only. Because the GM branch fell through to an unfiltered query, the GM could see HR drafts before submission. Commit `9a309f299c20aab963b5744eeee714bb5b2548b9` now applies explicit role-based visibility: HR sees all policy versions; GM sees `pending_gm`, `approved`, `active`, and `retired` versions but not `draft`; FM sees only `approved`, `active`, and `retired`. A GM-rejected version returned to `draft` is therefore hidden from GM again while remaining editable by HR. This changes list visibility only; it does not alter policy status, approval history, notifications, activation, or the prior-active-policy fallback.

Verification boundary: source change is committed to `main`; local PHP lint and browser acceptance are still required. Verify that the current draft is visible to HR but absent from GM and FM; then verify after HR submits it that it appears to GM as pending approval and remains hidden from FM. Keep the test policy inactive.

## 2026-10-14 — GM approval feedback simplified for clean acceptance test

Per the user's explicit direction, commit `0df1890e12a006cb1cae57c1bf906047178a39fd` removes the GM approval confirmation dialog and success toast from the incentive-policy approval action only. Authorization, transactional status/history recording, HR/FM notifications, and other workflow feedback remain unchanged. Approval success is to be checked through persisted status/history and notifications. Local lint/browser verification remains pending; the test policy cleanup was verified with no matching policy/notification rows and zero related rules, history, custom fields, or events. Do not recreate the policy until the code change is pulled and verified.

## 2026-10-14 — Incentive policy page: remove redundant currency/category notice

Commit `fa733524d67411e08d299c84e7568c79b601316a` removes the explanatory alert above the category section in `modules/hr/incentive_policy.php`. The application currency remains system-configured and continues to be stored with each policy rule; only the page-level notice was removed. The category management heading, category catalogue, and policy rule amount labels remain unchanged. Source change is committed on `main`; local pull, PHP lint, and browser verification are pending. This UI cleanup does not establish that the entire incentive lifecycle or incentive-to-payroll/accounting implementation is complete.


## 2026-10-14 — Incentive policy GM approval feedback: source correction, runtime pending

The user observed a successful GM approval with no confirmation prompt or success toast. The prior source intentionally removed both feedback elements, and the shared same-page POST interceptor runs at window capture before the confirmation listener in `assets/js/notifications.js`. The scoped correction restores the bilingual confirmation and translated success flash, and marks only the GM approval form as native POST to prevent a request from starting before confirmation. No global JavaScript behavior was changed. A narrowly guarded SQL reset utility targets only the named, unactivated R3 acceptance policy and preserves the original submitted history. Local lint and browser/database acceptance are still required; do not activate R3 or claim runtime success until verified.


## 2026-10-14 — GM incentive approval handoff timestamp retest

The corrected acceptance reset and fresh browser approval established that the earlier stale timestamp was test-data residue, not a demonstrated production notification-writer defect. The fragile reset had relied on Arabic title literals that did not compare reliably through the local PowerShell/CLI path; the encoding-independent reset targets only policy R3, the exact body/link, and HR/FM recipients 32/29, while preserving the GM submission notification. Following reset, approval history row 8 (GM user 2) and HR/FM notifications 175/176 all have `2026-10-14 13:54:58`. Policy 3 is `approved`; activation fields remain NULL. Production notification code was left unchanged.

Verified scope: GM confirmation and success toast, persisted approved status/history, HR/FM handoff notification creation, matching timestamps, and no activation. Confirmation cancellation was not explicitly reported and is not marked verified. This result does not close broader incentive-policy rejection/resubmission, FM activation, or payroll effective-policy selection acceptance.


## 2026-10-14 — FM incentive activation confirmation race and acceptance

Initial runtime evidence: policy 3 (R3) became `active` under FM user 29 at `2026-10-14 14:12:23` even though the confirmation prompt disappeared. This was an executed activation, not a cancellation test.

Root cause from source: `assets/js/app.js` intercepts same-page POST on window capture; the shared confirmation handler in `assets/js/notifications.js` listens on document. The FM form's inline `onsubmit=confirm(...)` did not opt out of same-page AJAX interception.

Fix commit: `06b08d44afc48887b74a4588f1604e11f5d5e809`. The form now uses the translated `incentives.confirm_activate` message through `data-confirm` and opts out of same-page AJAX POST with `data-ak-native-post="1"`. Local PHP lint passed. After the guarded reset, the user confirmed the prompt and observed the success toast; database evidence shows R3 active under FM user 29 at `2026-10-14 14:18:10`. Thus the confirmation/acceptance path is browser- and database-verified.

Test-only reset utility commit: `0f987a2dc367b4776525469ffefca05bb1c87bd6`. Its timestamp guard was updated in `a94080af39f82bcdaa56190c6c8eca264934d9f3` to match the latest exact activation timestamp. The reset preserves approval history and notifications and only targets the guarded R3 record.

Remaining verification: pull main, execute the updated reset only against local `ahl_el_kheir`, confirm R3 is approved with NULL activation fields, then test Cancel and verify it remains approved with no success toast. Do not touch `ahl_el_kheir_production_test`. No payroll-policy-selection or award-to-payroll integration is implied.


## 2026-10-14 — Incentive policy rejection/resubmission audit evidence

R3 policy 3 was rejected by GM user 2 with reason `ACCEPTANCE TEST: clarify the evidence required to confirm a qualifying collection before approval.`; HR user 32 received a rejection notification at 14:36:36. HR resubmitted the same policy; history row 10 records actor 32 at 14:38:35 and GM was notified. GM reapproved; history row 11 records actor 2 at 14:40:49. The final database query returned HR notification 181 and FM notification 182, both at 14:40:49, with the R3 policy in the body and the policy page as link. Policy status is `approved`; activation fields remain NULL.

Evidence caveat: the guarded test reset removed the previous approval-history row and previous HR/FM approval-handoff notifications, while preserving submission history and the GM submission notification. It was not a rollback-only reset and did not preserve the full pre-test history. The observed new notifications match the latest approval event. No production notification-writer defect is evidenced; no production notification code change is justified by these results. Award-to-payroll/accounting integration and effective-policy selection remain outside this acceptance and unverified.


## 2026-10-14 — Incentive payable account and payroll-accounting boundary

Source inspection of `modules/accounting/accounts.php`, `modules/accounting/lib.php`, `modules/accounting/fina_lib.php`, `modules/hr/lib_payroll_accounting.php`, and the incentive-ledger migration confirms that account `5500` is the dedicated incentive expense, `2100` is general Payables, and `2300` is guarded as Fina Al-Khair Payable. The local chart evidence supplied for this task showed no `2400` account; the existing liability code sequence and unique account-code constraint support a dedicated `2400` Employee Incentives Payable account.

Migration `database/migrations/2026-10-14_hr_employee_incentives_payable_account.sql` is committed as `a281b8cd695e1555dcec86b7c900b1bf691f1562`. It only inserts the account if code `2400` is absent; it does not execute DDL. Local application and account-row/type verification are pending.

Payroll accounting currently debits `5200` by `net_salary + salary_advance_deduction`, credits the payout account by `net_salary`, and credits `1410` for salary-advance recovery. Adding an incentive to `net_salary` without an explicit component would incorrectly mix incentive expense into salary expense. The award schema's single `journal_entry_id` is insufficient to safely represent both accrual recognition and payroll settlement; distinct journal references and explicit payroll incentive linkage must be designed before posting any award journals. No incentive award/event lifecycle has been runtime-proven. Keep this as a source-level design checkpoint, not a completed integration.


## 2026-10-14 — Separate incentive payout component and journal lifecycle schema

Migration `2026-10-14_hr_employee_incentives_payroll_accounting.sql` (commit `211f1e033cf488f1a95e5d76956b4371183d6463`) adds `payroll.incentive_amount` independently from salary `net_salary`, and distinguishes the award's recognition journal from its payroll-settlement journal. This avoids using one ambiguous award journal reference for two different entries and prepares accounting to recognize Dr `5500` / Cr `2400`, then settle Dr `2400` / Cr payout account.

This is a schema-only source milestone. It has not been applied or runtime-verified locally; award functions, payroll scheduling/refresh, payroll UI, journal posting, and rollback-only tests remain pending. Before application, inspect the migration against the current local schema, then verify the resulting columns/indexes. Never merge incentive amounts into salary `net_salary` without retaining the separate accounting component.


## 2026-10-14 — Initial incentive award accounting integration slice (unverified)

After the user applied both 2026-10-14 migrations and verified account 2400 plus the new payroll/award columns/indexes, source changes introduced:
- Award recognition journal: debit dedicated expense 5500 / credit dedicated payable 2400.
- Draft payroll scheduling: only verified events and recognized awards are considered; reward currency must match the applicable salary currency and system ledger currency; mismatches remain held without FX.
- Payroll settlement: salary expense stays on 5200; scheduled incentive settlement debits 2400; bank credit includes net salary plus incentive payout; salary-advance recovery continues through 1410.
- Draft refresh before individual/bulk approval, separate UI display of net salary/incentives/total payout, and distinct award recognition/settlement journal pointers.

This is not yet runtime-verified. No source-event hooks or manager verification/award-creation workflow has been implemented in this slice, so ordinary application activity cannot yet produce a complete incentive award lifecycle. PHP lint, rollback-only integration harnesses, and local XAMPP runtime tests are pending. Do not treat this as accounting acceptance.


## 2026-10-14 — Incentive accounting rollback gate prepared

Harness: `tools/run_hr_employee_incentive_accounting_rollback_tests.php`, commits `68a005d9` and `5cb05574`. Status is **prepared; local runtime pending**, not PASS. The harness uses existing policy/payroll fixtures, runs recognition Dr 5500 / Cr 2400, duplicate protection, caller-owned currency hold, draft scheduling, payroll settlement preserving 5200 and 1410 accounting, paid-payroll refresh immutability, and rollback cleanup inside one outer transaction. It performs no DDL.

After pulling `main`, run `& "D:\xampp\php\php.exe" -l "tools\run_hr_employee_incentive_accounting_rollback_tests.php"` then `& "D:\xampp\php\php.exe" "tools\run_hr_employee_incentive_accounting_rollback_tests.php"` only against local `ahl_el_kheir`. A SKIP is not a PASS. Keep R3 approved/inactive and leave `ahl_el_kheir_production_test` untouched. Source-event hooks, manager award creation, and employee notifications remain open. Assess MAX(JE suffix)+1 journal-code concurrency before concurrent award event integration.


## 2026-10-14 — Incentive accounting rollback test passed locally

User-provided evidence confirms PHP lint and runtime harness passed against local `ahl_el_kheir`. Assertions passed for balanced recognition Dr 5500 / Cr 2400 (SDG 123.45), duplicate recognition idempotency, currency-hold persistence within a caller-owned transaction, draft payroll scheduling and repeated refresh, balanced settlement preserving salary expense 5200 and salary-advance recovery 1410, award settlement linkage, and rollback cleanup. Fixture identifiers: payroll 21, employee 16, policy version 3. The test reported two event fixtures, two awards, and two journals removed by rollback. No claim is made for event-source integration, manager verification/award creation, employee notifications, concurrency, or full lifecycle acceptance. Leave R3 approved/inactive and do not use `ahl_el_kheir_production_test`. The next source task is to harden the incentive journal-code allocation against concurrent MAX+1 collisions before event hooks are introduced.


## 2026-10-14 — Incentive recognition journal-code collision retry (verification pending)

The existing accounting convention in `modules/accounting/lib.php` uses MAX+1 for `JE-######` codes; the `journal_entries` schema enforces uniqueness. Concurrent award recognitions can race for a candidate. Commit `143aec005e916b9d9a5b1923b82b16b16ca9b447` adds a five-attempt retry scoped to duplicate entry-code errors inside `hrIncentiveRecognizeAward()`. It does not alter other accounting paths and does not suppress unrelated database errors. Local lint and rollback-only runtime revalidation are pending; source-event hooks remain blocked until those pass.


## 2026-10-14 — Win-back reactivation attribution implementation committed; runtime gate pending

Root cause: `winback_campaigns.handled_by` changes as operational ownership changes, so it cannot safely identify the employee who reactivated a sponsor. The new migration adds `reactivated_by_user_id` and `reactivated_at` as durable first-transition attribution. The new helper locks the campaign and updates sponsor/campaign state within one transaction; audit logging runs after success. The campaign transition is not itself an incentive qualification event and creates no award.

Local acceptance passed on 2026-10-14: the migration was applied to `ahl_el_kheir`; all three PHP lint checks passed; the rollback-only harness reported atomic sponsor reactivation, durable actor/timestamp, blocked repeated transition, and rollback cleanup (campaign 3, sponsor 1). No two-process concurrency result is claimed. After this gate, source-event logic must separately establish a genuine ≥90-day lapse and a new financially confirmed payment, attribute the achievement to the durable actor, and remain pending HR Manager verification. Do not activate R3 or touch `ahl_el_kheir_production_test`.


## 2026-10-14 — Win-back reactivation attribution passed local rollback-only acceptance

The user pulled commit `958a4644160071ea573db15f35e92f0f2d738c9c`, applied `database/migrations/2026-10-14_winback_reactivation_attribution.sql` to local `ahl_el_kheir`, and ran PHP 8.2 lint on `modules/administration/lib_winback.php`, `modules/administration/winback.php`, and `tools/run_winback_reactivation_rollback_tests.php`; all three reported no syntax errors. The rollback-only harness reported:

- `PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked`
- `PASS | rollback_cleanup=PASS | campaign=3 | sponsor=1`

This proves the harnessed atomic sponsor/campaign transition, durable actor/timestamp, blocked repeated transition, and rollback cleanup on the local database. It does not prove concurrent two-process execution or the full incentive lifecycle. Next work remains the reactivation source-event qualification at the financially confirmed payment boundary, using confirmed payment history to prove the 90-day lapse and durable reactivation actor, with idempotency and HR Manager verification. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation payment qualification committed; local acceptance pending

The confirmed-payment hook now checks a posted linked transaction and posted journal, a durable successful win-back transition, a previous confirmed monthly-sponsorship payment at least 90 days before the transition, and the first confirmed monthly-sponsorship payment created after the transition. The event remains `pending_verification`; it does not issue an award. Recipient user and employee IDs are snapshotted on source events so the win-back actor is distinct from the payment reviewer. The collection hook also snapshots the collector's user/employee relationship. Missing or ambiguous employee mappings produce no event. Same-second competing payment confirmations are conservatively held rather than risking duplicate awards.

New migration: `database/migrations/2026-10-14_hr_incentive_event_recipient_snapshot.sql`. New rollback-only harness: `tools/run_hr_employee_incentive_reactivation_rollback_tests.php`. The existing collection source-event harness now checks recipient snapshots. **Local acceptance is pending**: apply the recipient-snapshot migration once to `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and both incentive source-event harnesses, then run both harnesses. No local runtime result is claimed for this new slice. HR Manager verification/award issuance, employee notifications, new-sponsor/project hooks, and end-to-end lifecycle testing remain open. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation incentive wiring paused for actor-attribution decision

Source review of the actual win-back UI found that its “confirm return” form has no field for the employee who actually reactivated the sponsor. The action records the logged-in confirmer, while `handled_by` is mutable and the contact outcomes do not identify a successful reactivator. Therefore the current confirmer must not automatically be treated as the incentive recipient. The reactivation qualification helper and rollback-only test are committed, but the production call from `modules/accounting/fm_review_queue.php` is intentionally withheld until this business attribution is decided. The collection hook remains separate and snapshots the collector. Do not apply the new recipient-snapshot migration or run the new reactivation harness yet; first decide whether the confirmer is, by definition, the reactivating employee, or the workflow must require a separate employee selection and preserve the confirmer independently. No runtime acceptance is claimed for the reactivation qualification slice.


## 2026-10-14 — Explicit win-back reactivator attribution and confirmed-payment hook (local gate pending)

The agreed recipient is the employee who actually reactivated the sponsor, not automatically the user who confirms the return. The win-back UI now requires selecting a uniquely mapped, currently working employee; server code revalidates the mapping and stores that user in `reactivated_by_user_id`. The confirmer is stored separately in `reactivation_confirmed_by_user_id`; `handled_by` remains mutable operational ownership. Legacy campaigns without the new confirmer snapshot are excluded from reactivation incentive qualification; the system does not assume their earlier actor attribution was correct. The FM confirmed-payment transaction now calls the reactivation qualification helper, which checks posted transaction/journal evidence and a 90-day prior-payment lapse and records only a pending-verification event. New migration: `database/migrations/2026-10-14_winback_reactivation_confirmer.sql`. Local acceptance is pending; see `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for exact migrations and rollback-only tests. Do not use `ahl_el_kheir_production_test` or activate policy R3.


## 2026-10-14 — New-sponsor first-payment qualification added; local verification pending

Source implementation adds a new-sponsor source-event helper and calls it inside the FM payment approval transaction after the linked transaction/journal and approved payment state are established. The helper locks the payment and sponsor, requires an approved monthly-sponsorship payment with posted transaction/journal evidence, requires the sponsor's `brought_by_user_id` to resolve to exactly one employee, rejects any earlier financially confirmed monthly payment, excludes a durable qualifying reactivation transition, and snapshots the recipient on a pending-verification event. No award or employee notification is created by this hook. Local lint and rollback-only test results have not yet been supplied; use `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php` and see the module continuation document for the exact acceptance gate.

## 2026-10-14 — HR Manager incentive verification source implementation

The HR Manager-only review page `modules/hr/incentive_verification.php` and dashboard entry point are committed on `main`. Source guards require a pending event, an enabled positive fixed-amount policy rule, a snapshotted recipient whose user/employee mapping still matches, and an active working employment state. Verification, award insertion, and recognition posting share one transaction. Project-completion events are explicitly blocked until approved allocation detail is implemented. The page does not yet send next-payroll notifications.

This is **source implementation only**. Local PHP lint and rollback-only runtime acceptance remain pending. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for exact commits and next checks.


## 2026-10-14 — HR incentive verification rollback harness prepared (runtime pending)

Added `tools/run_hr_incentive_verification_rollback_tests.php` on `main` (commit `9d7ab5e2c442b7df8e72bb9b36b60afcdb7c2040`). The harness creates a synthetic collection event using an existing eligible positive collection rule, verifies the snapshotted active working employee relationship, performs the same pending-to-verified and award-insert sequence used by `modules/hr/incentive_verification.php`, invokes the production recognition helper, checks the balanced Dr 5500 / Cr 2400 journal and duplicate-recognition idempotency, and rolls back all synthetic rows/journals. It performs no DDL, policy mutation, payroll mutation, or commit. This is a rollback-only database integration harness, not an HTTP/session/CSRF browser test.

The user's local read-only inspection found policy version 3 is approved (not active), with an enabled collection rule of 1,000.00 SDG; accounts 5500 and 2400 are active with the expected expense/liability types; one active `hr_manager` user exists; and no pending events currently exist. These observations do not prove verification runtime behavior. PHP lint and execution of the new harness against local `ahl_el_kheir` remain pending. Run only `tools/run_hr_incentive_verification_rollback_tests.php`; do not rerun previously passed source-event/accounting harnesses without regression evidence. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`. Employee notification after award scheduling remains open, and project-completion verification remains blocked pending approved allocation workflow.


## 2026-10-14 — HR Manager verification/award rollback acceptance passed locally

The user pulled `main`, PHP-linted `tools/run_hr_incentive_verification_rollback_tests.php` successfully, and ran it against local `ahl_el_kheir`. Exact runtime evidence:

`PASS | hr_manager_fixture=available | recipient_eligibility=PASS | pending_to_verified=PASS`

`PASS | award_creation=PASS | recognition=Dr5500/Cr2400 1000 SDG | duplicate_recognition=PASS`

`PASS | rollback_cleanup=PASS | policy=3 | employee=1`

This verifies the tested database path: recipient/employment eligibility, pending-to-verified transition, award creation, posted balanced incentive recognition, duplicate-recognition idempotency, and cleanup after rollback. It is not an HTTP/browser/session/CSRF test and does not prove the employee notification requirement. Policy version 3 was approved and its enabled collection rule was used only as test input; the policy was not activated or changed. The synthetic rows and journals were rolled back. Do not rerun this passed harness absent regression evidence. The next open task is to inspect the actual notification table/helper contract and implement failure-isolated employee notification when an award becomes scheduled for an eligible draft payroll. Project-completion award verification remains blocked pending approved recipient allocations. Do not use `ahl_el_kheir_production_test` or activate policy R3.


## 2026-10-14 — Incentive payroll-scheduling notification outbox added; local acceptance pending

Added migration `database/migrations/2026-10-14_hr_incentive_notification_outbox.sql`, production logic in `modules/hr/lib_employee_incentives.php`, and `tools/run_hr_incentive_notification_outbox_tests.php`. When a verified award is newly assigned to a draft payroll, a unique outbox row is inserted in the same transaction as award scheduling. Delivery is attempted only after that transaction commits. The notification insert and outbox `sent` transition share their own transaction; a failure leaves the item pending, records/logs the failure where possible, and does not roll back the committed payroll/award scheduling. Repeated refreshes cannot create a second outbox item for the same award, and concurrent dispatchers serialize on the outbox row.

The implementation uses the base `notifications` columns confirmed in the user's local schema and does not require the optional `reference_id`/`reference_type` columns (the local read-only schema query did not show those columns). The notification is in-app only and says the incentive is scheduled in the stated payroll period, subject to normal payroll approval/payment. The acceptance harness injects one delivery failure, checks scheduling remains committed and retryable, retries successfully, checks recipient/amount/period and duplicate protection, then removes its temporary event/award/outbox/notification and restores the original draft payroll incentive total.

**Local acceptance is pending.** Pull `main`, apply the new migration once to local `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php` and `tools/run_hr_incentive_notification_outbox_tests.php`, then run the new harness. It uses a safe eligible draft-payroll fixture and reports `SKIP` if none exists or an earlier pending outbox item needs investigation. Do not use `ahl_el_kheir_production_test`, activate policy R3, or rerun already-passed source-event/verification/accounting harnesses without regression evidence. Project-completion award verification remains blocked pending approved recipient allocations.


## 2026-10-14 — Incentive payroll-scheduling notification outbox: local acceptance PASSED

The user pulled `main`, applied `database/migrations/2026-10-14_hr_incentive_notification_outbox.sql` to local `ahl_el_kheir` (the table was initially absent), and PHP 8.2 lint passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_incentive_notification_outbox_tests.php`.

Exact local runtime evidence:

```
HR incentive scheduling notification failed for outbox 1: TEST_INJECTED_INCENTIVE_NOTIFICATION_FAILURE
PASS | scheduling_commit_survives_notification_failure=PASS | outbox_retryable=PASS
PASS | retry_delivery=PASS | correct_recipient_amount_period=PASS | repeated_dispatch_no_duplicate=PASS
PASS | cleanup=PASS | payroll_amount_restored=PASS
```

The injected delivery failure was isolated from the already-committed payroll/award scheduling. The outbox remained retryable; a subsequent dispatch delivered the correct in-app notification and repeated dispatch did not create a duplicate. Test cleanup passed and the original payroll incentive amount was restored. The warning line is the deliberately injected failure, not an acceptance failure.

**Acceptance boundary:** this proves the tested local database scheduling/outbox/retry/idempotency path. It does not prove browser/session/CSRF behavior, employee-facing browser UX, email/push delivery, true two-process concurrency, or the complete incentive lifecycle. The notification is in-app only. Project-completion verification remains blocked pending the approved recipient-allocation workflow. Keep policy R3 inactive, do not use `ahl_el_kheir_production_test`, and do not rerun already-passed incentive harnesses absent concrete regression evidence.

**Next open gate:** inspect/test the actual employee-facing notification UX in the local application and verify HR Manager page authorization in browser/session context, without reopening the passed rollback-only harnesses.

## 2026-10-15 — HR incentive policy notification boundary

The HR Manager policy creator is to receive in-app notification only for two outcomes: (1) GM rejection, including the rejection reason, and (2) FM activation. The GM-approval handler previously also notified the HR creator; that extra notification was removed in commit `2c47e5aa6691b735f996ebf7c0c2091926698d54`. The FM-awaiting-activation notification remains directed to FM users. The existing post-commit HR notification call after successful FM activation remains in place.

**Verification boundary:** this is a narrow source change; local PHP lint and browser/runtime verification are still required. Policy R3 is currently approved but unactivated; its effective date is 2026-10-14, so it cannot be activated on 2026-10-15 under the existing no-retroactive-activation guard. Do not directly update its status/date in the database. To test the successful activation notification, use a new policy through the normal HR → GM → FM workflow with a valid current/future effective date, then verify the committed active status and the notification row for the HR creator. If activation commits but the notification is absent, inspect delivery evidence before changing the shared notification helper. Do not use `ahl_el_kheir_production_test`.

## 2026-10-15 — HR incentive policy notification workflow: runtime acceptance PASSED

The user confirms the full policy-governance workflow was exercised locally and is working, including the HR creator's rejection notification with the GM's reason, no HR notification on GM approval, the FM notification that an approved policy awaits activation, and the HR creator's notification after successful FM activation. This closes the targeted policy-notification acceptance task; do not repeat its stages or reopen the source change without concrete regression evidence.

The source change removing the HR notification on GM approval is commit `2c47e5aa6691b735f996ebf7c0c2091926698d54`. The local FM reset script was run against `ahl_el_kheir`; its exact-state guard returned `guarded_activation_matches=0` and `reset_rows=0`, leaving policy R3 unchanged (`approved`, activation fields NULL, effective date `2026-10-14`). A read-only query found no approved policy with `effective_from >= CURRENT_DATE` at that checkpoint. The user subsequently completed and confirmed the valid full workflow; the successful workflow must not be conflated with the no-op R3 reset.

**Scope boundary:** this acceptance closes only the HR incentive policy governance/notification flow. It does not close the full employee-incentives lifecycle. Remaining open work includes the employee-facing notification UX and HR Manager authorization in browser/session context, plus project-completion verification/approved recipient allocations. Do not rerun already-passed rollback-only incentive harnesses or use `ahl_el_kheir_production_test` absent concrete regression evidence.



## 2026-10-15 — Approved incentive policy return-to-draft guard

Source audit found no supported way for GM to withdraw an already-granted approval from a policy still awaiting FM activation. Added a narrow action in `modules/hr/incentive_policy.php`: GM-only, mandatory reason, row lock, status must be `approved`, activation columns must remain NULL, history action `returned_to_draft`, and post-commit HR/FM notifications. A dated migration extends the approval-history action ENUM; no runtime DDL or direct database mutation is introduced. Commits: `cb522dcee4b2e78e03f8d4c97402a61ca7e23d6c` (migration), `a9501f5f41e4f959a734d175cf14523c49c1e6d2` (source). Local PHP lint, migration application, and browser acceptance are pending. Chronology caveat: R3's effective date is past, and the newer R4 effective date must remain respected; HR must edit the draft to a valid date and re-submit through GM/FМ governance. No direct SQL status edits.


## 2026-10-15 — Employee incentive notification UX: browser acceptance PASSED
The user confirms live notification delivery was browser-verified. The controlled employee notification was visible in the live UI; notification history rendered its Arabic body correctly when inserted using UTF-8 bytes. Cleanup was verified against local `ahl_el_kheir`: one exact test row deleted (ID 195), no matching test row remained, and recipient user 16 had zero notifications and zero unread notifications afterward. This closes only the employee-facing in-app notification UX gate. It does not prove email/push delivery, true two-process concurrency, or full lifecycle acceptance. HR Manager browser/session authorization and project-completion approved recipient allocations remain open. The next documented local gate is the GM-only audited return-to-draft action for the approved, unactivated incentive policy, followed by HR editing through the normal UI. Do not rerun completed incentive harnesses without regression evidence; do not use `ahl_el_kheir_production_test`.


## 2026-10-15 — HR incentive verification authorization PASSED; active policy checkpoint corrected

The user reports the local browser/session authorization acceptance passed:
- Existing HR Manager account: the dashboard link was visible and the incentive verification page opened normally.
- Existing HR Staff account: the verification link was absent and direct access to `modules/hr/incentive_verification.php` was denied/redirected as expected.
- No additional details or failure symptoms were reported.

This closes only the HR Manager browser/session authorization gate. Do not rerun the already-passed source-event, accounting, verification rollback, or notification-outbox harnesses without concrete regression evidence.

**Policy-state correction from the user's actual local browser screen (2026-10-15):** the page shows one policy, `ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5`, Version V1, status active (`سارية`), effective date `2026-10-15`; its four enabled rule amounts are collection 1,000 SDG, new sponsor 2,000 SDG, reactivation 1,000 SDG, and project completion 1,500 SDG. The displayed approval history shows HR submission at `2026-10-15 09:19:58` and GM approval at `2026-10-15 09:20:10`. This is user-reported local UI evidence, not an independent database query.

This current R5 observation supersedes older continuation instructions that describe R3 as the active testing target or direct the next session to return R3 to draft. **Do not reset, edit, deactivate, or otherwise modify active R5; do not create another policy unless the user explicitly agrees.** Do not change policy status/effective dates directly in SQL. Preserve the prior historical R3/R4 notes as chronology only, not as current instructions.

**Next open engineering unit:** inspect the actual project-completion lifecycle/source integration and existing `hr_incentive_project_allocations` schema before proposing implementation. Project-completion incentive verification remains intentionally blocked until the approved recipient-allocation workflow is defined and implemented. Do not infer who qualifies, how percentages are assigned, who approves the allocation, or rounding/residual treatment from the schema alone. Preserve project lifecycle/approval evidence and existing accounting boundaries. No project-completion source changes or tests are claimed in this checkpoint. Keep `ahl_el_kheir_production_test` untouched.


## 2026-10-15 — Project-completion incentive source audit (design gate remains open)

Source inspected on `main`: `modules/hr/incentive_verification.php`, `modules/hr/lib_employee_incentives.php`, `modules/projects/view.php`, `modules/projects/project_lib.php`, `modules/accounting/fm_review_queue.php`, and `database/migrations/2026-10-12_hr_employee_incentives_ledger.sql`.

Findings:
- `modules/hr/incentive_verification.php` explicitly refuses to verify a `project_completion` event until the approved recipient-allocation workflow exists. Keep this guard.
- The ledger migration already defines `hr_incentive_project_allocations` with `event_id`, `employee_id`, `recipient_user_id`, `allocation_percent`, `reward_amount`, `currency_code`, `approved_by_user_id`, `approved_at`, and `allocation_note`. This schema alone does not define the authorized approver role or how allocations are proposed/calculated.
- The project page's `change_status` action updates `project_lifecycle.lifecycle_status` to `completed` and writes project status history in a transaction. In the inspected completion path, there is no incentive-event creation call and no separate formal-acceptance record established by that action.
- `modules/hr/lib_employee_incentives.php` permits `project_lifecycle` as the source table for a project-completion event, but the inspected project completion path does not call the event recorder. Do not claim that the hook exists.
- The project's current active team can be read from `project_team` (`unassigned_at IS NULL`), but team membership by itself does not establish eligibility for a completion reward.

**Implementation remains blocked on business-rule decisions, not on removing the guard:** define the authoritative formal-acceptance evidence/transition; who may propose recipients; whether recipients are manually selected or derived from some approved evidence; who approves the allocation and under which existing role; whether allocation percentages must total exactly 100%; and how currency rounding/remainder is assigned. Preserve existing audit/accounting boundaries. No project source code, schema, policy, or incentive data was changed in this source-audit step. Active R5 remains untouched; do not create another policy unless the user explicitly agrees. Do not use `ahl_el_kheir_production_test`.


### Additional existing project-completion acceptance constraint — preserve

Prior incentive-design discussion established that a project-completion award must remain blocked until **administrative project closure and FM financial closure are both evidenced**, eligible team recipients have explicit approved allocations, and those allocations are approved before award issuance. A project lifecycle status such as `completed` (or a project-screen stage/status alone) is not sufficient proof. This constraint is binding alongside the unresolved decisions above; do not implement event qualification from `lifecycle_status='completed'` alone.


## 2026-10-15 — Project-completion incentive automation implemented in source; local acceptance pending

The user confirmed the governing rule: PM administrative closure starts processing; awards are issued only after durable FM financial closure and a fresh controlled-balance check (existing tolerance 0.009). Each eligible employee on the active project team at PM closure receives the full active-policy project-completion amount (R5 currently shows 1,500 SDG per eligible employee). There is no separate per-award FM/HR approval. The allocation is per employee, not a shared pool. Active policy R5 was not changed.

Source commits on `main`:
- `6166c6f1` — migration `database/migrations/2026-10-15_hr_incentive_project_auto_allocation.sql` allows NULL approval metadata for system-generated allocations; it does not invent a human approver.
- `e1507598`, `1070775c`, `4e505fa6` — incentive helper snapshots active, working, unambiguously mapped team employees within the PM closure transaction; creates per-employee full-policy allocations; requires the durable `FM_FINANCIAL_CLOSURE` audit row, administrative closure, and a rechecked controlled balance before idempotent award/recognition processing; refreshes the earliest current/future draft payroll when available and relies on the existing notification outbox.
- `4cdcab41`, `896c6c40` — starts and snapshots the incentive event atomically with PM closure; a processing error rolls back that closure attempt instead of silently losing the recipient snapshot.
- `bc8b864c`, `9a879bae` — invokes processing from both FM financial-closure paths and retries processing on later FM project-page loads when durable closure evidence already exists.
- `2836d5ab` — updates the HR verification page's project-event text and reports automatic award totals.

The prior requirement for human-approved project allocations is superseded by the user's explicit newer instruction. The old approval columns remain for historical/manual rows but are NULL for automated rows. Project-completion awards are not processed by the HR Manager's manual verification form.

**Verification boundary:** these are source commits only. No PHP lint, migration application, local MariaDB runtime test, or browser acceptance has been run for this new slice. Do not describe the automation as verified. Next local gate: pull `main`; inspect and apply the new migration once to local `ahl_el_kheir`; PHP 8.2 lint the changed PHP files; then run a purpose-built rollback-only project closure/award test before exercising a real project. Never use `ahl_el_kheir_production_test`; never modify active policy R5; do not rerun already-passed incentive/accounting/notification harnesses absent regression evidence.


### Follow-up — project closure rollback harness added; local acceptance pending

Added `tools/run_hr_project_incentive_closure_rollback_tests.php` (commit `df40dc1c`). It exercises the automatic project allocation/award path inside an outer transaction and rolls back its fixture, journals, and payroll changes. The processor supports caller-owned transactions (commit `2bff519b`). A missing migration or safe project fixture results in `SKIP`, not PASS.

Next: pull `main`, inspect/apply `2026-10-15_hr_incentive_project_auto_allocation.sql` only if needed, lint the four changed PHP source files plus the harness with local PHP 8.2, and run only the new harness. Local lint/runtime acceptance remains pending. Keep active R5 unchanged; never use `ahl_el_kheir_production_test`.


## 2026-10-15 — Project incentive rollback harness safety refinement

Inspection of the disposable database found project 10 has posted allocations totaling 260,000 SDG and a controlled balance of 30,000 SDG, with no recorded funding returns. The harness will not fabricate a return or alter posted accounting records merely to create a passing fixture. It now tests the positive-balance rejection path and rollback cleanup using a genuine eligible closed project where possible. The award/recognition success path remains gated on a genuine zero controlled balance; if no such fixture exists, that branch is explicitly SKIP, not PASS. This source/doc change does not touch the live database, project 7, or active policy R5. Local lint and disposable-DB execution remain required.


### 2026-10-15 — Project incentive harness SQL failure: root cause and correction

The first local execution of `tools/run_hr_project_incentive_closure_rollback_tests.php` failed with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'currency_code' in 'field list'`. Source inspection isolated the invalid reference to `project_lifecycle.currency_code` in `hrIncentivePrepareProjectCompletion()`; project lifecycle does not expose a source currency field. Commit `920d4221122a65a8579e4bed5ef289e64a4934db` removes that column from the query and leaves `hr_incentive_events.source_currency_code` NULL for this non-monetary source event. The award currency remains sourced from the active policy rule and stored on the recipient allocation/award. This correction has not yet been locally linted or runtime-tested. Next: pull `main`, lint `modules/hr/lib_employee_incentives.php` and the project incentive rollback harness, then rerun only the rollback harness against `ahl_el_kheir_incentive_test_20261015_r1`. Do not alter active R5, live accounting, or fabricate funding returns. A later SQL failure must be diagnosed against the actual schema before further edits.


### Local verification result — project incentive positive-balance rollback harness (2026-10-15)

User ran the harness on local PHP 8.2 against disposable database `ahl_el_kheir_incentive_test_20261015_r1` after pulling the source correction. Both `php -l modules/hr/lib_employee_incentives.php` and `php -l tools/run_hr_project_incentive_closure_rollback_tests.php` passed. Runtime output:

- `PASS | project_team_snapshot=1 | no_approval_metadata=PASS | positive_balance_award_block=PASS`
- `PASS | rollback_cleanup=PASS | project=10 | controlled_balance=30000.00`
- `SKIP | award-recognition success branch | No existing eligible closed project has a zero controlled balance; no accounting records were fabricated or altered.`

This verifies the positive-controlled-balance block and transactional cleanup in the disposable DB only. It does **not** verify successful award creation or recognition journals, nor browser/runtime behavior on a real project. The success branch remains open pending a legitimate eligible closed project with controlled balance <= 0.009. Do not fabricate funding returns or edit posted accounting records to create one. Active policy R5 and live accounting were not used or changed by this test.

## 2026-10-15 — Project incentive successful-path test method

The previous rollback-only project incentive harness proved the positive-controlled-balance block and rollback cleanup, but skipped award recognition because no existing eligible closed project had a zero controlled balance. No posted financial records were to be changed to manufacture a zero balance.

A separate harness was added to `main`: `tools/run_hr_project_incentive_success_rollback_tests.php` (commit `5b6fb97d975bdc3e591fa811ca55b2a214092bfc`). It is hard-blocked unless the selected database is exactly `ahl_el_kheir_incentive_test_20261015_r1`. It reads, but does not alter, the protected active R5 policy; creates a synthetic project without funding/expense/return activity; assigns an eligible Project Supervisor before closure; proves the FM audit gate; verifies the full policy amount and balanced recognition journal on accounts 5500/2400; and rolls back all fixture, award, audit and journal rows. PHP lint and local runtime execution remain pending. This is a test-method improvement, not evidence that the successful award path has passed.

## 2026-10-15 — Project incentive success-path harness: PASS

Local user evidence for `tools/run_hr_project_incentive_success_rollback_tests.php`:
```
PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS
PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS
PASS | rollback_cleanup=PASS | policy=R5 | reward=1500.00 SDG
```
PHP lint also passed. The test used only `ahl_el_kheir_incentive_test_20261015_r1`, a synthetic no-financial-activity project and an eligible Project Supervisor snapshotted before closure. The FM closure gate was tested both absent and present. The award recognition journal debited incentive expense account 5500 and credited incentive payable account 2400 for 1500.00 SDG. Rollback cleanup was confirmed. No production policy, posted financial records, or live project were modified.

**Acceptance boundary:** project award eligibility, snapshot, configured amount, FM gate, recognition journal and rollback are runtime verified. Payroll assignment/refresh, notification delivery, and real PM-to-FM browser workflow are not proven by this harness and must not be described as passed.


## 2026-10-16 — FM sponsor-payment approval integration source checkpoint

The shared approval operation preserves the transactional sequence (posted transaction and journal, guarded sponsor-payment approval, collection event, new-sponsor event, reactivation event) and leaves GM notifications after commit. Explicit prepared accounting helpers avoid DDL inside the operation; the FM approval POST no longer invokes the page's schema setup calls. A rollback-only harness checks the production operation's success path and injects a failure at new-sponsor event recording to assert financial and incentive writes roll back. This remains source-only evidence until local PHP lint and the harness pass against the disposable incentive test database.


## 2026-10-16 — FM sponsor-payment integration runtime evidence

User-supplied disposable-DB output verifies the shared FM approval path, posted transaction/journal, new-sponsor event left pending verification with no premature award, success rollback, and rollback after injected incentive-recording failure. PHP 8.2 lint passed for the five relevant files. Evidence is limited to `ahl_el_kheir_incentive_test_20261015_r1`; no live DB or active R5 changes are reported.

A separate `tools/run_hr_project_incentive_payroll_rollback_tests.php` now covers project-award assignment to a current/future draft payroll, increase of the incentive component, unchanged salary net, pending notification outbox creation, and rollback restoration. It is included in the PHP syntax workflow. This test is not yet locally linted/runtime-verified. Do not report it as passed until output is reviewed.


## 2026-10-16 — Project incentive payroll harness fixture diagnosis and source correction

The initial project incentive payroll harness returned `SKIP` because its selected Project Supervisor had no current/future draft payroll. User-provided read-only query evidence showed employee 22/user 34, employee 26/user 39, and employee 27/user 40 each have a salary-history row effective from 2026-10-05 at 10,000 SDG and a working employment category, with no October 2026 payroll row. The actual payroll-policy helper source (`modules/hr/lib_payroll_policy.php`) selects the latest version effective by the period end; the disposable database returned policy version 1 effective 2026-10-01. The earlier diagnostic query's `effective_to`/status assumptions were not used as production rules.

Commit `797f89afe848df39fee26a6f16bae6f2854c3c03` updates the rollback harness to seed a temporary current-period payroll inside the test transaction only when no eligible draft exists. The insert follows the production generation INSERT shape; salary history, historical employment state, currency, duplicate-period row, unscheduled award, and effective policy are guarded. Rollback must remove the temporary payroll; when an existing draft is used, the original payroll amounts must be restored. Local lint/runtime acceptance remains pending; no PASS is claimed.
## 2026-10-16 — Project incentive → draft payroll rollback evidence

User-reported local result for `tools/run_hr_project_incentive_payroll_rollback_tests.php` after successful PHP 8.2 lint:

```
PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS
PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS
PASS | project_award_draft_payroll_link=PASS | incentive_total=PASS | net_salary_unchanged=PASS | outbox_pending=PASS
PASS | rollback_cleanup=PASS | temporary_payroll_rolled_back=PASS | policy=R5 | reward=1500.00 SDG
```

The evidence supports award linkage to the proper draft payroll, incentive component increase without changing net salary, outbox enqueue, recognition journal checks, and full transactional cleanup including a temporary payroll fixture. The run was intended for the disposable incentive test database; no production payroll run is evidenced. R5 remains unchanged.

Residual boundary: no proof here of real browser PM-to-FM closure behavior or actual notification delivery to an employee. Keep those explicitly open until separately tested.

## 2026-10-16 — Project incentive financial-closure draft-allocation safety guard (processor rollback gate passed; FM closure-path verification pending)

A source audit found that the controlled balance intentionally counts posted funding allocations only, but FM financial closure could still be recorded while a draft allocation remained. The same gap could allow the project-completion incentive processor to proceed if a caller supplied a zero balance and durable closure audit evidence existed.

The targeted correction adds a reusable draft-allocation predicate, blocks explicit FM financial closure while any draft allocation remains, and lets a valid funding return complete while deferring automatic financial closure/PM completion notification when a draft allocation remains. The incentive processor independently locks/checks for a draft allocation before award or recognition-journal processing and preserves caller-owned transaction semantics.

Added rollback-only regression harness: `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. It is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, requires the protected active R5 project-completion policy, reuses only an existing closed zero-posted-balance fixture with a draft allocation and pre-existing recipient snapshot, inserts temporary FM closure audit evidence inside a transaction, and rolls back. It does not change funding allocations, payment evidence, posted accounting, payroll, or R5.

**Verification evidence:** PHP 8.2 lint passed for `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, `modules/hr/lib_employee_incentives.php`, and the rollback harness before the latest testability refinement. The rollback-only harness first refused live `ahl_el_kheir`, then passed against `ahl_el_kheir_incentive_test_20261015_r1`:

`PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS`

`PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5`

This closes the **processor rollback-harness gate**. A follow-up now extracts the explicit FM financial-closure rejection into `akp_assert_no_draft_funding_allocations_for_financial_closure()` and adds an assertion for that same guard to the rollback-only harness. **That latest helper/harness change still requires PHP lint and a fresh runtime harness pass.** It tests the shared guard used by the FM handler; it does not prove the browser route itself or the automatic closure deferral after a funding return at runtime. Do not post a funding return or change project 7's draft allocation/payment evidence. After pulling `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`, then run the harness with process-only `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Keep the already-passed incentive, payroll, notification, salary-advance, and Projects Phase 5 gates closed unless regression evidence appears.


## 2026-10-16 — Automatic financial-closure branch testability refinement (verification pending)

The return-handler closure decision was extracted into `akp_project_financial_closure_outcome()` and the production FM handler now uses that shared decision. The existing rollback-only harness asserts: zero balance plus draft allocation defers closure; zero balance without drafts permits closure; positive balance keeps closure pending. Its result line includes `automatic_closure_outcomes=PASS`. This does not invoke the browser POST handler or deliver a notification. Pull latest `main`, lint the affected PHP files, and run only the draft-funding rollback harness on `ahl_el_kheir_incentive_test_20261015_r1`. Do not alter project 7 funding/payment evidence or R5.


## 2026-10-16 — New-sponsor first-confirmed-payment incentive acceptance passed

The user ran the rollback-only harness `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php` locally. The temporary runner confirmed the configured and connected database were both `ahl_el_kheir_incentive_test_20261015_r1` before requiring the harness. Exact output:

```text
SAFE_TARGET | ahl_el_kheir_incentive_test_20261015_r1
PASS | new_sponsor=first_confirmed_payment | recipient_snapshot=PASS
PASS | later_payment_rejected=PASS | no_award_before_HR_verification=PASS
PASS | rollback_cleanup=PASS | sponsor=2858 | employee=16
TEST_EXIT_CODE=0
```

The new-sponsor first-confirmed-payment rollback-harness gate is runtime verified at this local disposable-database boundary. It proves the first confirmed payment event and recipient snapshot, rejection of a later payment as another qualifying first-payment event, no award before HR verification, and rollback cleanup. It does not prove the real-browser PM-to-FM project-closure workflow or end-to-end employee notification delivery/visibility. Do not repeat this closed harness without regression evidence. Active R5 was not modified and the live `ahl_el_kheir` database was not the test target. Preserve project 7 and its protected financial evidence.

## 2026-10-17 — Incentive notification browser acceptance evidence

**Result: employee inbox delivery/visibility verified; test cleanup independently verified.** Against the disposable database `ahl_el_kheir_incentive_test_20261015_r1`, the user opened the real local employee notification inbox while the outbox harness was paused. The expected Arabic notification for 12.34 SDG in payroll period 2026-09 appeared (test notification ID 200; timestamp 2026-10-17 06:54:52). The inbox showed 3 total notifications and 1 unread.

After the release file was created, process 4528 exited. Independent checks showed notification ID 200 absent, payroll fixture 21 restored to incentive_amount 0.00/status draft, original test-user password hash exactly restored, original `.htaccess` restored and hash-matched, no Git working-tree changes, and test-only environment variables cleared. The final harness console transcript was not captured; therefore the report relies on direct browser evidence and independent cleanup verification rather than claiming a captured final harness PASS line.

**URL observation:** The test-generated notification row contained `http://localhost/modules/notifications/index.php`. The production dispatcher concatenates `APP_URL` with the inbox path; `config/config.php` computes APP_URL from request variables and the base path from DOCUMENT_ROOT or SCRIPT_NAME. The CLI harness does not seed these web-context variables (other than REQUEST_METHOD), so this row is consistent with CLI APP_URL defaulting to the root localhost URL. This is not evidence by itself of a production browser URL defect. Keep production config unchanged unless a browser-originated workflow demonstrates the wrong link. A future harness refinement can seed representative local web server variables and assert the full app prefix.

The employee-facing notification acceptance gate is CLOSED at the verified boundary. Real-browser PM-to-FM project-closure acceptance remains separate and open; do not infer it from this test.


## 2026-10-17 — Latest project financial-closure draft-funding guard verification

User-provided local PHP 8.2 lint output confirms no syntax errors in `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. Latest rollback-only harness output:

```
PASS | fm_explicit_closure_guard=PASS | automatic_closure_outcomes=PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS
PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5
```

This confirms the latest shared explicit FM guard and automatic closure decision assertions, alongside processor rejection and preservation/rollback checks, on the disposable incentive test database. It does not exercise the actual browser POST route, and no funding return was posted. Project 7 financial evidence and active policy R5 remain unchanged. The distinct real-browser PM-to-FM project-closure workflow remains open.

## 2026-10-17 — Pending project closure visibility / Supervisor feedback

Audit evidence from disposable database `ahl_el_kheir_incentive_test_20261015_r1` for project 6 (`PRJ-0006`): `created_by=33`, `approval_status=approved`, `fm_final_confirmation_count=0`, latest history status `closure_requested`. The PM portfolio filters each project through `akp_can_view_project()`; the PM branch did not include this closure-review state, so the submitted request was hidden.

The source patch restricts the additional PM visibility path to the conjunction `approved + under_review + latest status closure_requested`; it does not change the close action's PM-only authorization or its pending-expense/closed-state checks. A confirmation prompt was added. Inspection found toast markup and a shared toast/scroll script already existed, so the newly added duplicate script was removed in `ba5ed5cec327e45d396ecbfb333a6c6d27ea5637`. The missing-toast/scroll symptom remains unverified and must be diagnosed from the browser if it persists.

Commits: `4258ebb656a3229b84d5d7729868ce92a683f921`, `f7f8bae9753694fd32b7f916443a1f2a8493a3c6`.

No local lint or browser verification has been observed yet. Treat both changes as unverified until user executes the real local checks.


## 2026-10-17 — Project 6 PM closure feedback defect and correction (runtime pending)

Database evidence from `ahl_el_kheir_incentive_test_20261015_r1`: project 6 / `PRJ-0006` was successfully administratively closed by PM user 33 (`other_projects.status=completed`, lifecycle `closed`, status-history row 22, `CLOSE` audit row 2479). Project-completion event 28 is `pending_verification` with the expected `system:project-recipient-snapshot-v1` marker. No FM financial-closure audit row or award was returned. Active policy R5/version 1 remains enabled at 1,500.00 SDG.

Source root cause: the `close_project` form in `modules/projects/view.php` had no confirmation prompt. The success handler stored `project_toast_success` but redirected to the closed project; PM access checks could redirect away before the view consumed the toast. Commits `986cef5d21d0ae972a1ec6dd9f624a619a851b40` and `b5879cb2cc69be7f3a30301eb72b4570efb12497` add the confirmation and move the success feedback to a one-time toast on `dashboard/projects_dashboard.php` after successful closure.

These are source-level changes only until the user pulls `main`, PHP-lints both files, and verifies the prompt/toast in the real browser. Do not repeat project 6 closure or infer that FM closure/incentive awarding has occurred.

## 2026-10-17 — Project-team assignment workflow added; local acceptance pending

A repository-wide local `git grep -n -i "project_team" -- "*.php"` showed that production PHP only read active team membership for authorization, display, and the project-completion incentive snapshot; the only explicit INSERTs were in rollback test harnesses. The current schema in `database/ahl_el_kheir.sql` already defines `project_team` with section codes `general`, `finance`, `operations`, `documents`, and `closure`, so no migration or runtime DDL is needed.

On `main`, `modules/projects/view.php` now exposes team membership using the existing `akp_can_edit_section('team', $id)` authorization boundary:
- active users can be assigned to a schema-valid section;
- duplicate active user/section assignments are rejected;
- unassignment sets `unassigned_at` instead of deleting history;
- assignment/unassignment audit rows are written in the same transaction;
- both operations reject closed projects and lock the lifecycle before the project row to align with the closure path;
- the page explains that primary-supervisor assignment is separate from `project_team`.

Source commits: `907d8be0c6f945bd030db45c8902d19427320c2b`, `5f0519f1e4418a367003849c76e1dcafa256fc64`.

**Verification status: SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER ACCEPTANCE PENDING.** Do not claim the workflow is verified. Next: pull `main`, run `php -l modules\projects\view.php`, then test add/duplicate-rejection/unassign/history through the normal browser UI on a newly created test project in `ahl_el_kheir_incentive_test_20261015_r1`. Do not modify PRJ-0007, PRJ-0018, active policy R5, or any funding/expense/payment/journal evidence. Keep the real PM-to-FM financial-closure incentive acceptance open until the browser path is genuinely verified.

## 2026-10-17 — Initial project team selection during creation (source committed; local verification pending)

Source commit: `f23f6252f37e287549e5dbc461d963021d9eef12` on `main`, file `modules/projects/form.php`.

The new-project form now offers optional repeatable initial team rows (active user, existing schema-valid section, optional notes). The primary supervisor remains a separate required assignment and is never implicitly added to `project_team`; only users explicitly selected in the new section become team members. Server-side validation checks active users, allowed section codes, duplicate users, and the existing 500-character notes limit. Team rows and matching `ASSIGN_TEAM_MEMBER` audit entries are inserted inside the existing project-creation transaction, so a failure rolls back project creation and team assignments together. No migration or schema change was needed; the existing `project_team` schema already defines the five allowed section codes.

**Verification status: SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER ACCEPTANCE PENDING.** After pulling `main`, run `php -l modules\\projects\\form.php`. On the disposable database `ahl_el_kheir_incentive_test_20261015_r1`, test creating a project with no initial team, then a separate project with two explicitly selected team members in valid sections. Verify the primary supervisor is not auto-assigned, selected rows and audit history are saved, and an invalid/duplicate submission is rejected without leaving a partial project. Do not modify PRJ-0019 or any protected project/funding/accounting evidence. Keep the real-browser PM-to-FM incentive closure acceptance paused until this change and the remaining normal workflow are verified. Preserve the local `.htaccess` database-routing setting until test cleanup is explicitly verified.


## 2026-10-17 — Move project-team editing into the project edit page (source committed; local acceptance pending)

The user tested creation of `PRJ-0020` (“اختبار حوافز المشاريع”) and confirmed that the two explicitly selected team members were saved (2 active assignments), but the detail page still displayed the add-member form and the success toast was not visible.

Source changes committed directly to `main`:
- `666e3d41ccfec087c17aba28e86818d20eabb3c9` — `modules/projects/form.php`: existing-project edit page now displays current active team members and, when the existing `akp_can_edit_section('team', $id)` permission allows it and the project is not closed, supports add/unassign actions. Each change is handled as a separate CSRF-protected POST, locks lifecycle then project, preserves the prior transaction/audit approach, and keeps unassignment history.
- `89414e7efe5ea4458a559ca30f3bbdd579214c13` — restricts the team editor card to existing projects, so it does not appear alongside initial-team selection on the create form.
- `ebd543b40b445af7858660f1291318805d7d8cae` — `modules/projects/view.php`: removed add/unassign controls from the project detail page; the active roster remains visible and eligible project managers get a link to manage the team from the edit page.
- `a92be7e0efd6e7d4dcfe0f83c99e001e18ffb3fe` — `modules/projects/view.php`: added a fallback path for project success feedback.
- `1d9f9809c530a025c1231a9d8198941263022f16` — prefers a SweetAlert toast for project success feedback, with Bootstrap and visible-markup fallbacks if needed.
- `a2ee0211b9b7443427465f56371be3870350f30b` — `modules/projects/form.php` now uses the same SweetAlert-first success-toast behavior for team changes made on the edit page.

**Verification boundary: source committed, not yet locally linted or browser-verified.** Pull `main`, preserve the local `.htaccess` override routing Apache to `ahl_el_kheir_incentive_test_20261015_r1`, then lint `modules\\projects\\form.php` and `modules\\projects\\view.php`. Browser-check `PRJ-0020` without changing its existing membership: the detail page must not show add/unassign forms; its edit page must list both active members and expose add/unassign controls only when authorized; verify add, duplicate rejection, unassign/history retention, and a visible success toast. Do not edit unrelated project/funding/accounting evidence. If the toast still does not appear, inspect browser console/network/runtime evidence rather than guessing. Keep the PM-to-FM financial-closure incentive acceptance open until separately verified.


### 2026-10-17 — Project edit-page team editor placement

The existing-project team editor was moved from above the project form into the form flow immediately after **البيانات الأساسية** and before **نطاق المشروع وأهدافه**, matching the initial-team section's placement during project creation. Team actions continue to use the existing early POST action handler; the client-side budget check is bypassed only for team actions, and team-member selection is validated only when adding a member. Source committed on `main`; local PHP lint and browser acceptance for this placement/action wiring are pending. Preserve the local `.htaccess` disposable-database override during pull/testing. Do not alter existing team assignments as part of this UI change.


### 2026-10-17 — Project detail team card placement

The read-only **فريق المشروع** card on `modules/projects/view.php` was moved to immediately after **ملخص المشروع** and before **التنفيذ والشركاء والتوريد**. Removed the duplicate team-specific edit link from that card; the existing primary **تعديل المشروع** action in the page banner remains the single edit entry point. No team assignments or lifecycle behavior were changed. Source committed on `main` as `2780a8dd14a54d91c1606b0d63d91d917a412f65`. Local PHP lint and browser verification are pending; do not describe this UI adjustment as runtime-verified until tested locally.


## 2026-10-17 — Employee incentive notification at earned stage (source committed; local verification pending)

Source commits on `main`:
- `62a98884d49938ccbcbe5fbba0f54f592802bcf3` — queue the employee notice atomically when a verified award is financially recognized; dispatch after commit without requiring a payroll row; stop creating the first notice during payroll-draft scheduling; use wording that confirms the reward is earned but not yet paid.
- `21dcef9eb784f6c636f486a59e3ae710b9a21f07` — update the outbox migration's explanatory comments to describe earned-stage notices. No schema/DDL change was made.
- `191410b5fa816b62320c527767c0bacb6d365b5a` — clarify earned-notice error/failure handling.

Root cause: the dispatcher joined `hr_incentive_awards` to `payroll`, while the unique outbox row was created only when the award was scheduled into a draft. Therefore an earned award without payroll linkage could not notify its employee. The dispatcher now reads the award directly; the existing unique `award_id` keeps delivery idempotent, and notification insertion remains atomic with outbox state after the award transaction commits. Delivery failure remains isolated and retryable.

The notice now says the employee has earned the reward, that it will be included in an eligible payroll and paid through the normal approved payroll process, and that the notice does not mean payment has already occurred. It is not a payment confirmation.

Awards 14 and 15 already have sent outbox rows and existing notifications 212/213. They were not rewritten, resent, or backfilled; the October 2026 payroll drafts remain untouched and unpaid. Active policy R5 remains unchanged. Arabic appearing as `???` in prior SQL-console output is not proof of stored-text corruption and remains a separate encoding check.

**Verification status: source committed; local PHP lint and fresh-award runtime/browser acceptance are pending.** After pulling `main`, lint `modules\\hr\\lib_employee_incentives.php`. Verify the next safe, new project-completion award through the normal workflow on `ahl_el_kheir_incentive_test_20261015_r1`: the outbox/employee notice must be created after recognition commits even before payroll linkage, retries must not duplicate it, and the employee inbox must show the earned-not-paid wording. Do not repeat closure for `PRJ-0020`, rewrite existing notifications, change R5, or approve/pay October payrolls. Preserve the local `.htaccess` disposable-database override.


## 2026-10-18 — Immediate employee incentive activity notification change

The employee requirement is immediate notice at the qualifying activity boundary, not a delayed notice at payroll scheduling or after all later management gates. Source changes on `main` add provisional activity notices for monthly sponsorship collection submission, active new-sponsor creation, reactivation, and the project-team snapshot at PM administrative closure. Existing financial qualification/verification gates remain authoritative; notices explicitly avoid claiming final earned/paid status. Relevant commits: `77da80e00daaf096c516198ab0935a8bc0515dfd`, `895b4ba2af347b25f1b289772b3e4d534a122140`, `6ccd172a70251deca4c55ee55b743035d56ca637`, `a3b6130b5808b49775e266d52ed2826b1215cd2c`, `04418b2f5da481993d0c1768b6981645ff24a2ac`, `50c433984b97bedee37c3bf22fa3b47fca5c58c5; `c0316a4d47b708dedd37e74da8ac2d884b840dff` (clarify notices are provisional activity updates)`. A rollback-only project test now asserts immediate notice creation and rollback cleanup. Runtime acceptance is pending; do not report the feature as verified until PHP lint, that harness, and employee inbox browser verification pass on the existing disposable DB.


## 2026-10-18 — PRJ-0024 one-shot closure safety review

Before the requested one-time administrative closure of PRJ-0024, source review found a concrete silent-notification-failure path: `hrIncentiveNotifyActivityRecorded()` swallowed INSERT errors, while the project snapshot marker could still commit and prevent a later retry. A missing effective project-completion policy could likewise yield no event without blocking closure.

Commit `66aa8a820c67d632f53cfa48319157ef21ae7a48` adds strict notification failure propagation for project-completion notices and makes the project snapshot helper fail closed when no effective enabled project-completion policy exists. In the PM transaction, any such failure now rolls back administrative closure and any notices already inserted in that same transaction. Non-project activity notice paths retain their existing isolated-failure behavior.

Status: **source committed; local verification and PRJ-0024 read-only eligibility/policy preflight pending. Do not click Close yet.** Required before the one-shot browser action: local PHP lint, the existing rollback-only harness on `ahl_el_kheir_incentive_test_20261015_r1`, and a read-only check of both active team members' unique employee mapping/working-state eligibility plus the effective active project-completion policy. No new project or repeated closure is authorized.


## 2026-10-18 — Project incentive notification boundary

**Finding and correction:** `hrIncentivePrepareProjectCompletion()` previously inserted a provisional employee-facing project-completion notification while PM administrative closure was being committed. That contradicted the clarified business boundary that employees are notified only after FM final financial closure. Commit `28578e3c9088fe8efdfccc671e1e2e7e7b99721f` removes that notification call while preserving the idempotent project source event and frozen recipient/reward snapshot. Commit `2a1d7b05e899582e40a6a0e864159914cc09b8e7` revises the rollback-only harness to assert no notice at PM closure and pending earned-notice outbox rows only after the FM-gated award path.

**Evidence status:** source-level change only; local syntax and runtime acceptance are pending. Targeted verification must use `ahl_el_kheir_incentive_test_20261015_r1`. The current project PRJ-0024 is already administratively closed; no repeated closure/reopen is authorized. Preserve the active R5 policy and do not touch production data.


## 2026-10-18 — Rollback evidence

The user ran local PHP syntax checks for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_project_incentive_payroll_rollback_tests.php`; both passed. The rollback-only project incentive/payroll harness passed on `ahl_el_kheir_incentive_test_20261015_r1`, with `project_notice_waits_for_FM=PASS`, `missing_FM_gate_blocks_award=PASS`, and `rollback_cleanup=PASS`. The run retained policy R5 and reward 1,500 SDG. Evidence is limited to the harness's synthetic rollback scenario. The real FM final-closure action and post-closure earned-notice delivery for PRJ-0024 are not yet verified.


## 2026-10-18 — Project-completion incentive recipient omission (open)

Disposable DB evidence: project `PRJ-0024` (ID 24) closed at `2026-10-18 09:47:23`. Active supervisor assignment 18 assigned PS1 (user 34) at `09:31:01`; user 34 was active and linked to working employee 22, but had no active `project_team` assignment. Verified event 34 carried `system:project-recipient-snapshot-v1`; allocations/awards/outbox existed only for users 16 and 28, whose notices were sent. The root cause is that `hrIncentivePrepareProjectCompletion()` queried only `project_team`, while the primary supervisor is stored separately in `project_supervisor_assignments`.

Commits `dd5c072c34dcd40dd8232e8f5df3aeac9b748e30` (include latest active primary supervisor in the recipient union) and `f2ff7bb7bd4b56011a45c03fc591a8aefbd43c82` (supervisor-only rollback fixture) are on `main`. The creation form already requires a primary supervisor server-side; this incident does not show that the project was created without one. Verification completed locally after integrating `origin/main`: PHP lint passed for both changed PHP files. The guarded rollback-only harness ran against `ahl_el_kheir_incentive_test_20261015_r1` with exit code 0. The supervisor-only snapshot, FM gate, recognition journal, payroll linkage, pending outbox, wait-for-FM boundary, and rollback cleanup passed (policy R5, reward 1500.00 SDG). Scope is the synthetic rollback scenario only; no historical award or notification for PRJ-0024 was repaired or proven delivered. Do not rerun closure or modify PRJ-0024; assess recovery separately.

### 2026-10-18 — Immediate activity-notice acceptance coverage added; not runtime verified

The helper call sites were source-reviewed in `modules/transactions/create.php` (monthly sponsorship collection), `modules/sponsors/create.php` (active new sponsor), and `modules/administration/winback.php` (recorded reactivation). Commits `69ac7eb1b7da82f7495ea12db71c59b2725c4540` and `bb292f02acfcce504b84e69c1f8179b13332fc63` add a rollback-only helper acceptance harness for those three categories. Assertions cover a newly inserted row, recipient, incentive type, expected reward context, provisional wording, unread state, and rollback cleanup. Local PHP lint and execution against `ahl_el_kheir_incentive_test_20261015_r1` have not yet been performed. This harness tests the shared helper, not the real browser routes; no runtime pass or module closure is claimed.

## 2026-10-18 — Immediate activity-notice helper acceptance passed

Evidence supplied by the user: PHP lint passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`. Running that harness with process-scoped `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1` returned:

```text
PASS | immediate_notice=collection,new_sponsor,reactivation | correct_recipient=PASS
PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS
```

Acceptance conclusion: shared helper runtime behavior and rollback cleanup are verified for collection, new sponsor, and reactivation. The harness calls the helper directly, so real POST-route invocation and browser inbox delivery remain separate acceptance boundaries. No live database, policy R5, payroll, or accounting state was changed by this test.


## Deferred development point — collection incentive eligibility (recorded 2026-10-18)

The user raised a business-design concern after the real browser collection submission displayed a provisional “activity linked to an incentive” notice: routine monthly sponsorship collection is ordinary job responsibility and may not merit an additional incentive, especially where the same employee may also qualify for the separate new-sponsor acquisition incentive. Revisit this with the user's forthcoming idea before changing behavior. Candidate recommendation to evaluate later: disable or redesign the collection incentive category and its provisional activity notice, while retaining new-sponsor, reactivation, and project-completion incentives unless the user decides otherwise.

**Deferred, not approved for implementation.** Do not disable the category, change active policy R5, modify existing events/awards, delete notifications, reverse accounting, or change payroll now. Preserve historical evidence. The observed collection notice is provisional and does not prove an award was issued; before describing an employee as having received a new-sponsor incentive, inspect the actual event and award records. Continue the already-open acceptance work without repeating the completed collection/new-sponsor browser submissions or closed helper harness.


## 2026-10-18 — Browser evidence and next read-only incentive-state check

The user has now completed real-browser checks of two immediate activity-notice routes on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
- Active new-sponsor creation for user 19 created sponsor `SP-002859` (“اختبار الحوفز”); the inbox showed the provisional activity notice with expected reward 2,000 SDG and explicit pending-verification/not-paid wording (observed 19:16:45).
- Monthly sponsorship collection submission reported two payments submitted to FM for approval/posting; the inbox showed the provisional collection activity notice for expected reward 1,000 SDG (observed 19:29:40). Do not assume this submission inserted only one payment.

These browser results prove the activity notices rendered; they do **not** prove an award was issued. The user's concern that the same employee may already have received a new-sponsor incentive must be resolved from durable records, not notification wording. Next step: run a read-only query against the same disposable database for incentive events and joined awards for recipient user 19, especially `new_sponsor` and `collection`; report event status and award status/payroll/journal links before deciding whether any earned incentive exists. Do not mutate, reverse, delete, or rerun the already completed browser submissions. New-sponsor first-confirmed-payment acceptance from 2026-10-16 remains closed and must not be repeated.

The separate development point about whether routine collection should qualify for an incentive is deferred and recorded above; no category, active R5 policy, event, award, notification, payroll, or accounting behavior has been changed for that recommendation.



## 2026-10-19 — Audit closeout: routine collection incentive removal VERIFIED

Seven PHP syntax checks passed for:
`modules/transactions/create.php`, `modules/accounting/lib_sponsor_payment_approval.php`, `modules/hr/lib_employee_incentives.php`, `modules/hr/incentive_verification.php`, `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`, `tools/run_hr_employee_incentive_source_event_rollback_tests.php`, and `tools/run_hr_incentive_verification_rollback_tests.php`.

All three rollback-only harnesses passed on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
- Immediate activity notice: retained new-sponsor/reactivation notices verified; collection notice blocked; recipient, wording, reward context, unread state, and rollback cleanup PASS.
- Source event: collection source event blocked, no new collection award path, rollback cleanup PASS (policy 18).
- Verification: recipient eligibility, pending-to-verified transition, award creation, Dr 5500 / Cr 2400 recognition for 2,000 SDG, duplicate-recognition protection, and rollback cleanup PASS (policy 11, employee 1).

Audit conclusion: the collection-incentive removal rollback verification is CLOSED. New routine collection notices/events/awards are blocked, including verification of pending legacy collection events. Existing historical records are preserved, not deleted or rewritten; active policy R5 and the catalogue entry remain unchanged. New-sponsor, reactivation, and project-completion flows remain enabled. No further runtime tests or browser submissions are required absent concrete regression evidence.



## 2026-10-19 — Employee incentives audit conclusion

The audit conclusion is **PARTIALLY ACCEPTANCE-VERIFIED; full module closure is still pending**. The routine collection incentive removal is closed at its rollback-only boundary: seven PHP syntax checks passed and the immediate-notice, source-event, and HR-verification harnesses passed on the disposable database. New routine collection notices/events are blocked, and pending legacy collection events cannot create new awards. Historical events, awards, notifications, payroll, accounting entries, catalogue data and active R5 remain unchanged.

Other evidence: new-sponsor first-confirmed-payment was browser-verified on 2026-10-16; the shared provisional-notice helper passed for new-sponsor/reactivation; project-completion rollback tests verified the FM gate, Dr 5500 / Cr 2400 recognition, draft-payroll linkage and outbox; and the one-time PRJ-0024 supervisor recovery recorded allocation 15, award 24, journal 178, sent outbox, notification 232 and an audit row on the disposable database. The supervisor recovery is complete and must not be rerun absent concrete regression evidence.

The following are **not yet proven end-to-end**: real-browser earned-notice delivery after FM financial closure to every snapshotted recipient (including PS1), duplicate-free reload/retry behavior, retained new-sponsor/reactivation POST-to-inbox acceptance, and project-team assignment UI behavior (initial selection, adding members, duplicate rejection, unassign/history retention, and edit page). Rollback-only harnesses and a historical recovery cannot substitute for these workflow tests. No claim of live/production database acceptance is justified by the evidence recorded here.

Preserve the evidence boundary: do not rerun completed harnesses or the PRJ-0024 recovery, do not recreate the removed disposable databases, and do not modify active R5 or historical accounting/payroll records merely to close documentation gaps. Close the module only after the remaining user-visible gates have concrete results.

## 2026-10-19 — Immediate activity-notice helper runtime evidence

User-supplied CLI output for `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php` shows all six preflight tables as InnoDB and all assertions passing: retained new-sponsor/reactivation provisional notices, routine collection notice blocked, correct recipient/reward context/provisional wording/unread state, and rollback cleanup. This is limited to the shared helper and its rollback fixture. It does not prove business-route POST-to-inbox delivery or end-to-end PM-to-FM earned-notice delivery. Keep the module partially acceptance-verified; do not repeat this harness absent regression evidence or perform further mutating acceptance tests against live data.

## 2026-10-19 — Project-team source syntax verification

The user ran PHP 8.2 syntax checks locally after syncing `main`; both commands passed:

- `php -l modules\\projects\\form.php` — `No syntax errors detected in modules\\projects\\form.php`.
- `php -l modules\\projects\\view.php` — `No syntax errors detected in modules\\projects\\view.php`.

This closes only the local PHP syntax-check item. Browser acceptance for initial team selection, edit-page display and authorization, add/duplicate rejection, unassignment/history retention, and success feedback remains pending. The user confirmed the two disposable incentive databases are deleted; do not recreate them and do not perform mutating acceptance tests against live `ahl_el_kheir`. No database rows or project assignments were changed as part of this syntax check.


## 2026-10-19 — Projects UI confirmation, feedback, and navigation remediation (source committed; local verification pending)

A targeted source audit of `modules/projects/*.php` and `dashboard/projects_dashboard.php` found that project actions used browser-native `confirm()`/`alert()` calls, and the shared `assets/js/app.js` same-page POST capture listener could start a request before a form's own confirmation handler ran. On the FM project page, custom funding/payment submit handlers also competed with the shared interceptor because their forms were not consistently opted out.

Source changes committed directly to `main`:
- `e2af93acef3fc68c451380fcb4de8a062897afb8` — added an opt-in SweetAlert confirmation helper for project action buttons.
- `3e8f237f19f3d1107f44dca6c90cfafc8d35bc2f` — replaced project-form native prompts/alerts with system dialogs.
- `c3769432261e75f44a865bc9a260178c72d3b823` and `ff108c2bf154b12d6e8d1acb47859408b1433c87` — replaced project-detail native confirmations and kept administrative closure on the project detail page so its post-action feedback remains visible.
- `cf3a95673debd5b78b588e7b430e00054c3d4b0a` and `d5952f3ec097eace6648e1b8a4643ea995c85fc2` — opted custom FM funding/payment handlers out of the shared POST interception and replaced native browser dialogs in those paths.

A post-change static scan found no remaining direct `confirm()` or `alert()` calls in `modules/projects/form.php`, `modules/projects/view.php`, `modules/projects/view_fm.php`, or `dashboard/projects_dashboard.php`. This is source inspection only: PHP syntax checks, JavaScript/browser behavior, button navigation, toast visibility, and FM funding/payment flows still require local verification. No database queries or mutations were performed, and no incentive eligibility, award, journal, payroll, or notification logic was changed.

Next: pull `main), run PHP syntax checks for the three changed project PHP pages, and verify in the real local application that project confirmations use the system dialog, cancel means no POST, confirmed actions execute once, project feedback is visible, FM funding/payment AJAX actions do not double-submit, and administrative closure returns to the project detail page. Do not perform a closure, funding, payment, or team-membership mutation merely to test UI; use safe non-mutating checks first and assess any live-data mutation separately.


### 2026-10-19 — Project form validation ordering follow-up

Follow-up source commit: `97ca44232ecec3bdb306992e46f5aec4ff6d770c`. The project create/edit form now opts out of the shared same-page POST interceptor so its existing client-side budget/team validation can run before submission. Server-side validation remains authoritative. This is still source-only verification; local PHP lint and browser verification are pending.

## 2026-10-19 — Employee salary statement source audit

Source change: `modules/hr/payroll_statement.php` provides an authenticated, employee-scoped, read-only statement for payrolls in `paid` state with `accounting_status='posted'`. It does not expose another employee's payroll by changing the query-string ID. It reads the saved payroll component fields and the salary currency effective for the payroll period. Incentives are itemized separately from net salary; total paid is displayed as `net_salary + incentive_amount`, matching the existing payroll journal's cash/payment amount and avoiding incentive double-counting. The print layout is Arabic-first bilingual, A4-oriented, and supports browser printing / Save as PDF.

`hrPayrollNotifyStatementAvailable()` is invoked only in the existing post-commit paid-transition hook. It checks the payroll remains paid/accounting-posted, targets an active linked user, deduplicates by recipient + link + title, and catches notification errors so an already-committed financial action is not reported as failed. No schema/migration was needed based on the inspected repository schema and existing `notifications` table.

Evidence status: source inspection only; local PHP lint and real-browser authorization/print verification are pending. No live payroll transition or database mutation was performed. Do not claim runtime acceptance until the user reports local results.


## 2026-10-19 — Salary statement presentation follow-up

The employee confirmed that the post-commit salary-statement notification was delivered and opened successfully for the paid October 2026 payroll (EMP-0017, 10,000.00 SDG; accounting entry 157). The statement was then refined on `main` to open payroll-statement notifications in a new tab (notification dropdown, live toast, and full notification history), center the organization logo in the header, use the navy header color for the outer statement border, compact earnings and deductions into side-by-side panels with a compact payment summary for A4 printing, and render only the currently selected `AK_LANG` language. Employee-only authorization, the paid + accounting-posted guard, saved payroll figures, separate incentive line, and `net_salary + incentive_amount` total-paid formula are preserved.

**Verification boundary:** the user confirmed notification delivery and initial statement opening before these presentation changes. The revised new-tab behavior, Arabic/English layouts, and A4 print fit remain pending local browser/print-preview confirmation; no additional payroll disbursement or database mutation is required to verify these UI changes.

## 2026-10-19 — Salary statement screen width refinement

Reduced the on-screen salary statement container maximum width from 900px to 748px, matching approximately the 198mm printable width of A4 portrait with the existing `@page` 6mm margins. The existing print-specific rule continues to use the full available printable width. This is a CSS-only adjustment; payroll data, authorization, notification behavior, and accounting are unchanged. Local browser and print-preview verification remain with the user.

## 2026-10-19 — Salary statement heading placement

The salary statement header now places “بيان تفاصيل الراتب” and “كشف راتب رسمي” immediately after the logo and organization name as one grouped brand/title line, instead of positioning the title independently at the edge of the header. The browser document title is also “بيان تفاصيل الراتب” in Arabic (or “Salary Statement” in English). Existing responsive and A4 print styling remains in place. Source committed on `main`; local browser/print-preview confirmation remains pending.

## 2026-10-19 — Salary statement heading stacked under branding

Follow-up correction: the logo and “جمعية أهل الخير” remain grouped on the first header line; “بيان تفاصيل الراتب” and “كشف راتب رسمي” are now a separate centered row directly underneath, not beside the logo. Header CSS/markup source committed to `main`. Local browser and A4 print-preview confirmation remain pending.


## 2026-10-19 — Employee salary statement implementation CLOSED

The user approved the final heading arrangement: the logo and “جمعية أهل الخير” remain together on the first row, with “بيان تفاصيل الراتب” and “كشف راتب رسمي” centered on a separate row below. The source changes are committed on `main`; the existing paid October 2026 payroll acceptance confirmed statement-notification delivery and initial statement opening for EMP-0017 (10,000.00 SDG; accounting entry 157). The implementation preserves employee-only access, the paid + accounting-posted guard, saved payroll values, incentive separation, and the existing `net_salary + incentive_amount` total-paid formula.

**Disposition:** implementation and the requested presentation correction are closed at the agreed scope. Do not run another payroll payment, mutate financial data, or repeat closed acceptance suites for this task. The assistant has not independently observed the user's final local A4 print preview after the last heading correction; this is not represented as runtime verification. Reopen only if the user reports a concrete defect.


## 2026-10-19 — GM payroll period completion report (source committed; runtime pending)
Source now contains a GM-only read/print payroll period report and an idempotent notification after all period-eligible employees have paid and accounting-posted payrolls. Eligibility reuses the effective salary history, hire-date, positive basic salary, and period-end working-state criteria from payroll generation. The report presents payroll components, payment date, journal entry ID, and `net_salary + incentive_amount` totals. No schema change, runtime DDL, payment replay, or modification of October payroll/accounting data was made. Local syntax and live runtime/access/print verification remain open. See `docs/HR_GM_PAYROLL_PERIOD_REPORT_CONTINUATION.md`.


## 2026-10-19 — GM payroll payment history navigation
Added a dedicated GM payroll payment history page and a quick-access card on the GM dashboard. The history lists payroll periods with at least one payroll record in `paid` status, shows each period's eligible/paid-and-posted counts and posted payment totals, and links to the existing read-only period report. The GM sidebar entry now opens the history page; notification links still open their specific period report directly. The page is GM-only and read-only; it does not change payroll or accounting data.

**Verification boundary:** source committed to `main`; local PHP syntax and browser verification are pending. Do not pay, approve, regenerate, or otherwise mutate payroll records for this UI change.


## 2026-10-19 — GM payroll payment history navigation disposition
User confirmed the GM payroll history navigation is satisfactory. The GM-only read-only history route and dashboard/sidebar access are accepted. No payroll/accounting data was mutated for this change. No additional testing is warranted absent a concrete regression.

## 2026-10-19 — FM payroll table usability request
A new UI request targets `modules/hr/payroll.php?lang=en` for FM. Investigate the wide 13-column payroll table, sticky header behavior, and loss of row position after action POSTs. Source inspection must determine whether existing centralized same-path POST interception is engaged and whether the form/table markup interferes with it. Only remove or combine fields where their information is genuinely redundant; do not remove accounting journal identifiers or payroll components needed for financial review. No root cause or fix is claimed yet.


## 2026-10-19 — FM payroll table usability source audit
Source inspection identified the relevant layout/scroll causes: the old horizontal-only responsive wrapper did not provide a bounded vertical scrollport for sticky headers, and the shared same-page POST renderer restored window scroll but not nested scroll containers it replaced. Draft amount editing also used a form spanning multiple table cells. The source change adds a marked bounded scroller, restores its scroll offsets after fragment replacement, and fixes the draft form association, with the hidden form placed inside the employee cell in follow-up commit `fecd42a9edbae3ff91b1092f81bcb4dc8c06eeb8`. Width reduction merges status/accounting display cells but retains both states and the journal ID, and removes only the repeated salary-effective date from the row. Commits: `f9cf0c0a2e079ae297c1674a8d18b7b150d738f0`, `85269f123d042a08735680d487c78b0c7715783b`. Source-only verification; local PHP syntax/browser verification pending. No financial data, schema, payroll calculations, or accounting behavior changed.


## 2026-10-19 — FM payroll refinement follow-up audit
The follow-up to `modules/hr/payroll.php` adds compact auto-sized column headers with full-label tooltips, a dark sticky header, and stable Arabic/English labels for the visible page. English month names are selected through `AK_LANG`. Draft checkboxes submit to a new selected-approval action. `hrPayrollApproveSelected()` strictly validates scalar numeric IDs, locks every selected row, confirms the selected rows are drafts in the requested month/year with positive basic salary, and updates only `status='approved'` and `accounting_status='ready'` in one transaction. The operation has no payment or journal-posting path. Save remains explicit because editable components require persistence and trigger the existing recalculation helpers; its control was moved to the Actions column and compacted. Static source audit found all literal page translation keys in both catalogs, no remaining fixed percentage widths, and no payment/posting logic in the selected-approval helper. Local PHP syntax/browser verification remains pending; see `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`.


### 2026-10-20 — FM payroll workflow redesign Step 2

Source evidence and constraints are documented in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. Current paid transition posts accounting without a transfer-proof gate; account resolution accepts any active account and defaults to `1200`; employee notification helper cannot distinguish existing notification from failure; GM completion checks paid+posted+journal only. A private per-payroll proof record and period default are proposed but not migrated because the SQL schema body and upload-serving conventions could not be verified through the connector. No code or data changed.


### 2026-10-20 — FM payroll workflow redesign Step 2

Schema evidence confirms payroll uses unsigned integer keys, InnoDB, existing per-row `payment_account_id`, and no proof or period-default table. `employees.user_id` is nullable; notification completion must account for missing linked users. The existing resolver accepts any active account and defaults to code 1200, so the redesign preserves that behavior absent a separate policy decision. Design and state-transition requirements are in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. No schema/source/data changed. Next: author migration and private proof storage contract; do not apply DDL before reviewing the exact migration.

### 2026-10-20 — FM payroll workflow redesign Step 3

A migration draft was committed as `f1225411c429f0b0eb9486ccec03f6be02e1ed8a`: `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql`. It adds period default-account settings and private proof metadata without modifying payroll rows. The continuation document records the storage security contract and state semantics. The migration has not been executed and no PHP/data changes were made. Review the draft and verify the local migration process before applying.

### 2026-10-20 — FM payroll workflow redesign Step 4 source audit
Committed the first source layer for period account defaults, row/group overrides, proof upload and authorized download. Source enforces active-account validation via the existing resolver, preserves explicit account selections, accepts only PDF/JPG/PNG with detected MIME and 10 MiB maximum, creates opaque random storage keys, rejects duplicate proof records, compensates file writes on DB insert failure, and restricts legacy evidence to paid+posted rows with an accounting entry. The existing storage deny-all rule protects proof bytes from direct web access; the PHP endpoint checks staff role or employee ownership/release state. New POST actions verify CSRF. No DDL or financial data was changed. This is not the final workflow: paid status still uses the legacy coupled accounting path pending the next explicit-confirmation stage. Local lint/runtime verification is pending.


### 2026-10-20 — FM payroll workflow redesign Step 5 audit
Source adds the explicit transfer confirmation gate and closes the generic paid-status bypass. Confirmation locks payroll/proof rows, requires prepared readable evidence and an active saved account, then updates proof confirmation, paid status, accounting journal and existing salary-advance application in the same transaction. Proof upload serializes against confirmation by locking payroll; account override is blocked after proof attachment. Employee notification helper returns reliable success/failure, with existing matching notification treated as success. GM completion now requires paid+posted+journal, confirmed/legacy proof, and successful employee statement notification for every eligible employee; retries can recover missing notifications, and GM notification remains idempotent. The migration is not applied and local PHP lint/runtime verification remains pending. No financial data was changed by source commits.

## 2026-10-20 — Salary Onboarding / Payroll Effective-Date Finding

Inspection of `modules/hr/lib_employee_provisioning.php`, `modules/users/index.php`, `modules/users/_create_user_form.php`, and `modules/hr/payroll.php` established the cause of the recurring zero-salary risk: automatic user provisioning hard-coded the employee's initial salary and salary-history entry to 0.00, while payroll correctly selects the history row effective at the end of the selected month. The account-creation form did not collect an initial salary or hire date.

Source correction on `main`: collect and validate a hire date and positive initial monthly salary at account creation; pass them through the existing transactional provisioning path; create initial salary history using the hire date. No new table/column or migration was required. Existing effective-dated salary changes and payroll selection are preserved.

Verification boundary: remote source updated; local PHP lint and runtime verification are not yet evidenced. September 2026 historical drafts remain unresolved because their actual historical salaries cannot be inferred from October values. Do not alter or approve those records until verified salary amounts are available.


## 2026-10-20 — HR employment contract builder source audit

The user clarified that the master employment contract should be an in-system page, not a separate Word document: HR fills editable fields, views the completed contract as PDF, and prints it for review/signature.

Source inspection found the existing `modules/hr/contracts.php`, `modules/hr/lib_contract_salary.php`, contract-file metadata (`contract_file_path`), bundled `TCPDF/tcpdf.php`, and `storage/.htaccess` denial of direct storage access. Existing contract upload/list code remains unchanged in this stage; its direct URL construction for stored contract files is a security concern to review before expanding signed-document handling.

Source added:
- `modules/hr/contract_builder.php` — authorized HR/admin editable contract form, CSRF-checked PDF POST, basic field/date/type/frequency/salary validation, A4 RTL PDF and signature areas.
- `modules/hr/contracts.php` — link to the builder.
- `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md` — scope and next verification steps.

Commits: builder `f0e5fdba4b32e179091478b092eccd5dd10f033f`; contracts-page link `ca14abbd85b490c5025f8367c1fe2d2caa43f88d`.

**Evidence boundary:** source inspection only. PHP lint and real local Apache/browser verification have not been performed. No schema migration or database mutation was made. The schema dump could not be retrieved through the repository connector due to its size, so local schema must be inspected before any subsequent persistence/onboarding integration. Legal text is a draft and must be reviewed by a qualified Sudanese lawyer before use.

### Follow-up — contract builder validation

A narrow source refinement in commit `eef4d17e02543e1327ed8cc3db9fee3b916aa11f` adds server-side field-length, email and weekly-hours checks, requires an end date for fixed-term contracts, and leaves nationality blank rather than assuming it. This does not change database or employment records. Local PHP lint and browser/PDF verification remain pending.

## 2026-10-20 — HR role-specific employment contracts and job-description catalogue

**Source status:** committed on `main`. **Local runtime status:** pending migration application and browser/PDF verification.

Implemented a new catalogue table via `database/migrations/2026-10-20_hr_job_description_catalogue.sql` and an authorized maintenance page `modules/hr/job_description_catalogue.php`. The catalogue stores position title, role purpose, responsibilities, qualifications, reporting line, optional role-specific contract terms, review status and activation state. It seeds distinct non-empty `employees.position` values without inventing content; new rows require HR review. HR Manager/Admin may mark descriptions internally approved. Renaming a catalogue title while employees still use it is blocked to preserve exact position matching.

The contract builder now matches against the selected employee's actual position, loads the corresponding description/terms, flags descriptions requiring review in the page/PDF, and reads organization logo/name/address/support phone/email from existing settings where populated. The HR dashboard exposes a separate `بوابة عقود العمل` shortcut and preserves `العقود والرواتب`.

Do not confuse employee positions with application roles. The role/workflow matrix is only broad role context and is not an approved detailed job-description source. No legal entitlement has been invented. Sudanese lawyer review remains mandatory before official use. Local migration and runtime/PDF checks are not yet verified.


## 2026-10-20 — HR catalogue proposal draft follow-up

A data-only migration `database/migrations/2026-10-20_hr_job_description_proposals.sql` provides proposed Arabic purpose/responsibility drafts for the known position inventory. It fills only empty content, preserves HR-authored text and existing approval status, leaves role-specific contract terms empty, and does not represent proposals as approved. `HR Test` is deactivated in the catalogue without deleting its employee record. Contract builder reporting-line prefill is sourced from `reporting_to` and labeled as needing HR confirmation. Source status only; local migration and browser/PHP verification remain pending. Legal wording requires qualified Sudanese counsel review before official use.

## 2026-10-20 — Employment contract role-aware description design

Source follow-up adds application-role context to the job-description catalogue. The authoritative linkage is `employees.user_id` → `users.role_id` → `roles.code`; `employees.position` remains the HR job title. Migration `2026-10-20_hr_job_description_system_roles.sql` adds the optional system-role dimension and seeds role-specific draft descriptions from documented workflows. Migration `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql` clears only exact, unchanged placeholders from earlier generic drafts. Responsibility items are individually editable in the catalogue and rendered as an indented list in the contract PDF. These are proposals based on documented workflow access, not evidence of each employee's actual activity; HR and the supervisor must confirm the job's real duties. Local runtime verification remains pending.

## 2026-10-20 — Job-description item fields and supervisor selectors

Source changes committed directly to `main`:
- `6aecdb28e7abfbd58a8242090137ddfb1e09c89e` — separate editable item rows for role purpose, responsibilities, qualifications and optional role-specific terms; add/remove controls; reporting-line options sourced from distinct existing employee position titles.
- `21ee08af28a642800236d75369a69931b52a93a3` — persist the item lists as newline-separated text in the existing TEXT fields (no schema migration or runtime DDL).
- `ab2079268dfe72551613475aaa0e3bbc72478fc4` and `e97a1b5a1623b194d9f29e6559a71bc43e761b05` — direct-supervisor selector sourced from existing employee records, no longer guessed from the catalogue reporting title; prevent selecting the contract employee as their own supervisor; purpose, responsibility and qualification items render as separate list entries in the contract preview/PDF.

The existing “الأوصاف حسب المسمى الوظيفي ودور الحساب” catalogue table remains in place. Existing proposed purpose/responsibility text is preserved; no unsupported qualifications or optional contract clauses are invented. Proposed descriptions remain subject to HR review and approval, and the contract remains subject to qualified Sudanese legal review.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** No local XAMPP runtime or PHP CLI was available to this session, so do not mark the feature runtime verified. The GitHub Actions run triggered by the latest source commit reported failure, but its job steps/logs were not retrievable through the available GitHub connection; this is not evidence of a PHP syntax failure or pass. Local verification must run PHP lint and focused browser checks before acceptance.


## 2026-10-20 — Contract builder repeatable duties and special terms

On `modules/hr/contract_builder.php`, the two requested contract fields are now repeatable lists: **المهام والمسؤوليات والوصف الوظيفي** and **الشروط الخاصة المتفق عليها / البنود المرتبطة بالمسمى**. Each row has its own text area and add/delete controls. When an employee is selected, existing job-description and role-specific-term lines populate separate rows; submitted rows are combined with newline separators for the existing PDF rendering. No schema change or runtime DDL was introduced. Source commits: `05171ee43e74269a9ae397e9176fbb59e13544d9` and `61fd28bb49528d823b8e41e8a1cd59ad69e76d29`.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** PHP lint and browser/PDF checks have not been run in the local XAMPP application. After pulling `main`, verify adding, deleting, changing employee selection, and confirming all retained entries appear in the generated PDF.


## 2026-10-20 — Repeatable contract fields: blank-selection edge case

Source commit `7b0f675700651ea295569e3c410b44c7e39a679d` clears both repeatable lists and resets the job-description status message when the employee selector is returned to its blank option, preventing duties or special terms from the previously selected employee remaining visible. The fix is source-committed only. Local PHP lint, browser interaction, and PDF rendering remain unverified; do not mark the contract builder complete until the focused local checks pass.

## 2026-10-20 — Role-based qualification drafts from researched NGO job profiles

Added data-only migration `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications.sql`. Apply it after the earlier 2026-10-20 job-description catalogue, proposals, system-role and generic-draft cleanup migrations, in filename order.

The migration:
- removes the known “مسودة مستندة إلى مسارات النظام” seed prefix from unapproved role-purpose drafts so the field reads as the actual proposed purpose;
- replaces only exact generic placeholder text for the generic `موظف` row and unknown-role fallback with useful draft wording;
- fills blank or exact generic-placeholder qualifications with role-specific, research-informed proposed qualifications for management, finance/accounting, HR, IT administration, project management/supervision, sponsor supervision, family/child support, administration, general staff and communications/social media;
- retains HR-authored non-empty qualifications, leaves approved catalogue records untouched, does not change approval states, and does not create runtime DDL.

Qualification wording is deliberately framed as a proposed profile (degree/training or equivalent experience where suitable; relevant skills and experience), not a mandatory legal or hiring condition. All modified descriptions remain pending review unless already approved, and approved rows are not changed by this migration.

Research references used to shape common qualification profiles:
- Saferworld, Finance Officer — Sudan (accounting/finance degree, accounting controls, NGO operations, software, confidentiality and communication): https://www.saferworld-global.org/downloadfile.php?filepath=downloads%2Ffinance-officer-sudan.pdffinance-officer-sudan-final-april-2021.pdf
- CARE Egypt, Project Accountant (finance/accounting degree, NGO experience, Excel/ERP and Arabic/English): https://care.org.eg/wp-content/uploads/2024/05/Project-Accountant_Upper-Egypt.pdf
- UN Careers, Human Resources Assistant (role-family reference): https://careers.un.org/jobSearchDescription/286436?language=en
- UN Careers, Information Technology Assistant (systems/network/security support reference): https://careers.un.org/jobSearchDescription/282210
- UN Careers, Communications Assistant (public information/communications reference): https://careers.un.org/jobSearchDescription/286125?language=en
- UN Careers, Programme Management Assistant (project coordination, reporting, stakeholder and budget tracking reference): https://careers.un.org/jobSearchDescription/278900

These sources are benchmarks, not a claim that Ahl El Kheir must adopt another organization's minimum criteria. HR and the direct supervisor must confirm actual day-to-day duties, required education and experience, reporting line and role-specific conditions before approval. A system role documents workflow access; it does not prove which tasks an employee has personally performed.

**Verification status: SOURCE COMMITTED; local migration application, PHP lint, catalogue browser check and contract PDF check remain pending.**


## 2026-10-20 — Job-description qualification import encoding finding

A read-only local query established: 26 rows in `hr_job_description_templates`; 25 `qualifications` values start with literal question marks; zero rows are approved; one row has title `HR Test`. The four relevant columns are `utf8mb4_unicode_ci`. A separate diagnostic found no values in `role_purpose`, `responsibilities`, or `position_title` starting with question marks. The confirmed corruption scope is therefore limited to the qualifications prefix check; other values must be preserved.

Corrective source is committed on `main`: explicit UTF-8 connection setup in the original seed migration and a separate data-only repair migration restricted to corrupted qualifications on unapproved non-`HR Test` rows. The repair uses the same Arabic role-specific drafts as the seed migration. No local application or post-repair result has yet been reported, so the issue remains open pending user execution and read-only verification.


## 2026-10-20 — Job-description qualification encoding repair: verified result

Post-repair local SQL reported `remaining_corrupted_qualifications = 0`. Hex previews of restored values begin with UTF-8 bytes for Arabic text (for example, after the leading bullet and space, `D985D8A4...`), not `3F` question-mark bytes. The `HR Test` row remained excluded and its qualification value remained NULL. PowerShell output displays Arabic position titles as mojibake; no byte-level diagnostic has established title corruption, so no title changes were made. Data repair is verified; page-level display and PDF acceptance remain open.


## 2026-10-20 — Contract-builder job-purpose field root cause and source correction

Audit finding: `modules/hr/contract_builder.php` used a single combined `duties` field populated by `akContractTemplateText()`, so the separately stored `role_purpose` was not exposed in a dedicated contract form field. This explains the missing `الغرض من الوظيفة` draft control without assuming catalogue data is absent. Source changes on `main` now load purpose, responsibilities, qualifications and reporting line separately for the selected employee's position/system-role template and reconstruct those sections for PDF output. Commits: `73b91d1a40024564d7399a80ae91b011167dbf9c`, `8fc40930c4224f04dab7e1f8c9c96c4fdfafd4b7`, `ec565758660214ac371ef14b692b4265249fc2b7`, `4d3d030bc4b96537141eec16fc6e3e0e86bce05d`, `59332e20a7d186eae3f969a9a94c24b1e2225d44`, `59ecd7b5057d9fe2910ccc3314b073d305cc19d6`. Runtime status remains unverified until local PHP lint and browser/PDF checks are reported. No database changes.


## 2026-10-20 — HR job-description fallback catalogue clarity

Audit evidence from the employee-wide query: linked employees with known system roles resolve to active role-specific draft records; employee 21 (**HR Salary Test**) has no active matching template. For **أخصائية شؤون الأمهات**, the populated nanny-specific draft (id 20) coexists with an empty title-only fallback (id 6). The contract builder's role-specific-first selection remains the intended behavior; the empty fallback should not be manually populated by copying role-specific content without an HR decision.

Source commit `6264c897b2bbadb460e0ba0d3e0c029b1443312d` clarifies the catalogue by identifying general fallback rows, showing active role-specific counts, and warning on empty general rows where role-specific descriptions exist. Related continuation/status/session documents were updated. Verification boundary remains source-only until local PHP lint and configured XAMPP browser checks pass. No database changes.


## Follow-up — null-safe HR fallback counts

Commit `4435226e37405e9a2963dd07af0d1c14f71e7f92` normalizes NULL and empty `system_role_code` values for the catalogue's general-fallback employee-count condition. This is a read/query behavior correction only; no database data or schema was changed. Local PHP lint and browser verification remain pending.
## 2026-10-20 — General fallback purpose/responsibility content gap

Audit root cause: the general title-only fallback rows were empty by design after `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql` removed exact generic proposal text. This was distinct from the populated role-specific draft for `أخصائية شؤون الأمهات`; the previous warning correctly described fallback selection but did not meet the user's request for editable starting content in every applicable form.

Source commit `5b6a553b22ff5a0be3d0a2b6430556ccf5138be9` adds the data-only migration `2026-10-20_hr_job_description_zzz_general_fallback_drafts.sql`. It restores separate broad position-level purpose/responsibility drafts only to active title-only rows awaiting review where either field is blank. It neither copies role-specific text nor overwrites non-empty content; approved/inactive rows, `HR Test`, qualifications, reporting lines, employee records and schema are preserved. This is source-committed only; local migration and UI verification remain pending.


## 2026-10-20 — Employment contract qualification baseline and reorderable rows

Change boundary: the job-description catalogue and employment contract builder now include drag handles for repeatable purpose, responsibility, qualification and optional-term rows. Reordering is supported through pointer interactions, and displayed numbering is recalculated after add/remove/reorder. Contract form arrays are submitted in DOM order so the generated PDF follows the current list order.

Data change: `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql` prepends these proposed baseline qualification items to active templates:
- `الحد الأدنى للمؤهل الأكاديمي: شهادة الثانوية العامة أو ما يعادلها.`
- `معرفة أساسية باستخدام الحاسوب وبرامج المكتب.`

Any modified description is marked `needs_review`; existing qualification text is preserved after the baseline items. This is not internal HR approval or legal approval. The migration must be applied after the existing job-description role-qualification and general-fallback migrations.

Verification: source committed on `main`; local migration application, PHP syntax checks, browser interaction and generated-PDF ordering are pending. No runtime success is claimed.


## 2026-10-20 — Automatic contract number reservation in the builder

Source change committed on main (8ed43dc8c7e0f2e2c5693e1777bfb8324bdc3115). The employment contract builder reserves its number through a saved draft row in hr_employee_contracts; number format follows the existing helper's CTR- + start year + six-digit record ID convention. The number input is read-only and no longer submits a client-controlled value. Draft insertion, numbering, and PDF rendering are kept in one transaction, so a rendering failure rolls the draft back. A one-use session token prevents the same rendered form from creating a second draft on resubmission. Existing overlap rules for draft/active contracts are preserved.

The builder intentionally bypasses hrCreateEmployeeContract() because that helper also changes salary history and may update the employee's current salary mirror and employment state for current/past start dates. The new builder path inserts only the draft contract record and optional weekly hours; it does not write salary history, update employees.basic_salary, or activate employment. Existing explicit activation behavior remains authoritative. No schema migration/runtime DDL was introduced.

Evidence boundary: source commit only. Local PHP syntax validation and real XAMPP/MariaDB/browser verification are still required for the insert, generated PDF number, rollback behavior, overlap rejection, duplicate-submit guard, and unchanged salary/employment data.


## 2026-10-20 — Unify contract creation and register workflow

Source changes committed directly to `main`:
- `315f221c9f43d4bbbe55b0f2acdce88e3b403296` — removed the duplicate `add_contract` creation path from `modules/hr/contracts.php`. New-contract links now open `modules/hr/contract_builder.php` with the selected employee. The old `?view=add` URL now explains the canonical workflow instead of presenting a second creation form.
- `b312f117f077f14e8c5f4f91bd80a4aeaf0c2ebf` — the builder's return link now returns to the employee's contract-register view, not the retired add form.
- `dd2b1dcc99762226e1ddf8a28000e6fc4a7d5190` — added an explicit optional probation-end date to the builder, validates it against the contract start date, includes it in the PDF text, and persists it to the draft's existing `probation_end_date` column so the register's existing activation/employment-state behavior remains available.

The register continues to manage contract status and salary history. It now uploads or replaces a signed copy against an existing contract record, rather than creating a new contract as a side effect of uploading a file. The upload validates that the contract belongs to the selected employee and retains the existing 10 MB / PDF-JPG-JPEG-PNG limits. Replacing a file updates the register path but deliberately does not delete older files.

No schema change or migration was introduced. The builder continues to reserve the official number and create a draft when PDF generation succeeds; this does not activate the contract or update salary history/employment state. The full editable clause text remains in the generated PDF, while the register stores its existing core fields.

**Verification status: SOURCE COMMITTED; PHP lint, browser interaction, signed-copy upload, draft activation, salary-history/employment-state behavior, and generated PDF are NOT runtime verified.** Pull `main`, lint both changed PHP files, then test one non-production/appropriate employee record through the configured XAMPP URL. Do not create a second draft for an employee/date period already covered by an active or draft contract. Sudanese legal review remains required before official use.


## 2026-10-21 — HR contract navigation consolidation audit (source only)

Inspected the dashboard, contract builder, register, job-description catalogue and current continuation documents. The dashboard previously presented two competing contract cards. The smallest justified change removes the register card, expands the remaining portal card description, and adds portal-header links to the register and catalogue. Existing register functions remain in `modules/hr/contracts.php`: signed-copy upload/replacement, contract status/activation/termination, and salary-history changes. The catalogue already links back to the portal. No business logic or database schema changed.

Commits: `be9c2515ec0f387406f4e54180e103d2985869bf`, `f3263b20eb666de05d096bc6d30d0143e92b15b7`. Runtime status is **not verified**: local PHP lint and browser checks must confirm one dashboard entry and working portal-to-register/catalogue navigation. The register must not be deleted until its capabilities are migrated and verified.

## Contract PDF preview follow-up — 2026-10-21
- Runtime report from the local contract builder showed an `Undefined array key "reporting_to"` warning during PDF generation. Source inspection found `reporting_to` was listed in the field-length validation limits but absent from the `$data` array.
- Corrected `modules/hr/contract_builder.php` on `main` by adding `reporting_to` to the validated data array (commit `86bb2343fd19ed1455420dcc9c4775a07cda4605`). This is source-committed but not yet locally linted or runtime-verified.
- The UI also reported an overlapping draft/active contract for the selected employee and period. Treat that as a separate safeguard; inspect the relevant employee's contract register before retrying, and do not bypass the overlap check.
- Next: user pulls `main`, PHP-lints the builder, then chooses an employee/period with no conflicting draft or active contract to generate and inspect the actual PDF design. If the conflict persists unexpectedly, capture the selected employee and inspect the register before changing logic.


## 2026-10-21 — Employment contract PDF design correction (source only)

The user rejected the first generated PDF because it did not reflect the previously agreed formal Arabic RTL navy-and-gold design. Source commit `ed3e4abf0bf4c3ea906ee21c30d9c2b6b465cc3c` updates `modules/hr/contract_builder.php` to add a distinct cover page with organization branding, contract number, employee/position and formatted dates; strengthen navy/gold section styling; isolate the signature/acknowledgment block on a dedicated final page; format dates as DD/MM/YYYY; and explicitly isolate Latin employee names, contract numbers and salary/currency text for more reliable bidirectional rendering. Existing Sudanese-law-first clause wording, NGO safeguarding terms, draft reservation, overlap guard and non-activation workflow remain intact. No schema change was made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY VERIFIED.** The reported PDF also showed an apparently blank contract number and inconsistent Latin employee-name order. The source now explicitly presents the reserved number and isolates Latin text, but only a fresh local PDF can confirm TCPDF renders these correctly. User must pull `main`, lint `modules/hr/contract_builder.php`, and generate a PDF for an employee/date range with no overlapping draft/active contract. Inspect cover, number, name consistency, currency/date direction, page breaks and signature page. Do not create another draft for a covered period, bypass overlap protection, or claim runtime success before evidence.

## 2026-10-21 — Contract PDF aligned to existing voucher layout (source committed; runtime pending)

The user explicitly requested the employment contract PDF follow the general layout/design of existing financial vouchers. The concrete source reference is `modules/accounting/voucher_print.php`: bilingual organization identity, boxed document-number/date panel, strong navy title bar, structured bordered/shaded information tables, consistent accent colors, and clear signature blocks. Commit `1cd7a318cb1f6075f66badfe235ba758aab4b527` updates `modules/hr/contract_builder.php` to remove the standalone decorative cover page and instead start the contract with a voucher-style title/number/date panel and employee/position/date summary, apply the voucher navy (#1b4d8f), muted shaded table labels and gold (#9a7628) accent to clause headings and signature tables, and use the same official Arabic/English organization names as the voucher print page. The legal clauses, draft reservation, overlap guard, and explicit activation workflow remain unchanged; no schema change was made.

**Verification status: SOURCE COMMITTED; PHP lint and actual local PDF rendering NOT verified.** User must pull `main`, lint `modules/hr/contract_builder.php`, then inspect a valid existing draft/eligible preview without generating another contract for an overlapping employee/date range. Confirm there is no oversized standalone cover, contract number/date panel renders correctly, header and names match the voucher branding, clause bars/tables are readable, no warning appears, and the final signature page remains usable. Do not claim visual acceptance until the local PDF has been inspected.

## 2026-10-21 — Employment contract branding correction (source committed; local PDF verification pending)

After the user reported that the revised PDF still looked unprofessional and had no visible organization logo, source inspection found the contract depended on TCPDF's generic header for the logo while the main contract body did not explicitly embed it. The PDF also included an internal HR job-description review warning inside the employee-facing contract. Source commits `7619773e52cb2e62bcde0721fa750b7913161689` and `19a79e00d2747616609edbd5a82b36a9b66cfc96` now use the same repository logo as the voucher print page (`assets/img/logo.png`), explicitly embed a bilingual branded letterhead in the contract body and signature page, avoid a duplicate generic TCPDF header, and remove the internal review warning from the contract PDF. HR review status remains a builder/catalogue concern; this change does not mark any job description approved. No database/schema or contract lifecycle changes were made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY LINTED OR RENDERED.** User must pull `main`, run PHP lint, and inspect the actual PDF before acceptance. Confirm the logo is visibly embedded, there is one clean organization letterhead (not duplicate branding), the contract number/date and Latin text render correctly, the internal review warning is absent from the employee-facing PDF, clause styling and page breaks remain readable, and the signature page is properly branded. Do not create a new contract solely to test if an overlapping draft/active contract already exists; preserve the overlap guard and existing records.


## 2026-10-21 — Contract PDF alpha-PNG rendering regression

Root cause narrowed to `modules/hr/contract_builder.php` embedding `assets/img/logo.png` into TCPDF HTML. Other current pages use that PNG in browser-rendered headers/vouchers, while `modules/settings/index.php` and the contract-builder portal honor the configured `site_logo`. Since the user confirmed the existing `assets/img/logo.jpg` is the same organization logo, the narrow source correction is to use the JPEG for the TCPDF letterhead only. No PHP/Apache extension configuration, site-logo setting, schema, or contract workflow was changed.

Verification is **SOURCE COMMITTED / LOCAL PHP LINT AND PDF RENDERING PENDING**. Do not claim the error is resolved in the actual XAMPP runtime until the generated PDF is confirmed.


## 2026-10-21 — Second contract PDF design revision (voucher-style hierarchy; runtime pending)

Following user feedback that the employment contract still looked weaker than the existing vouchers, `modules/accounting/voucher_print.php` was re-read as the design reference. The contract's first-page composition has been revised to use a single bordered three-part letterhead (logo, centered bilingual organization identity, and boxed contract number/date), a full-width navy bilingual title bar, a compact employee/position and term summary, and a dedicated highlighted basic-salary band inspired by the voucher amount panel. Clause headings now use a pale-blue panel with navy text and a gold rule instead of a heavy filled bar, to improve readability across long legal text. Existing clauses, job-description content, signature page, logo JPEG compatibility fix, draft reservation/overlap guard, and activation workflow are preserved. No schema or accounting changes.

**Verification status: SOURCE COMMITTED; local PHP lint and actual PDF visual inspection are still pending.** The rendered PDF must be inspected before deciding whether this revision is accepted; do not create another overlapping contract draft just to preview the design.

## 2026-10-21 — Employment contract PDF page-layout follow-up

`modules/hr/contract_builder.php` was updated in commit `ddffae6105a2da56bce1b5d706bc7d40e1f94ba2` so the organizational letterhead appears only on the first page and the legal body uses tighter typography/spacing with a target of two pages including the signature page. This is a source change only. Local PHP syntax and rendered PDF page-count/readability are pending verification; no schema, migration, accounting, or contract activation behavior changed.

## 2026-10-21 — Employment contract voucher-style layout follow-up

The voucher's actual outer composition was inspected in `modules/accounting/voucher_print.php`. Commit `f30fd321ae7932daa2e87b7b70be8e3f97d437a3` reduces the contract logo/header size, narrows header columns, compacts the number/date panel/title and tightens body spacing. The repeated signature-page header remains removed. Source-only; exact rendered page count and readability still need local verification.


## 2026-10-21 — Contract PDF header proportion correction

After the user reiterated that the contract PDF should follow the existing voucher design, source inspection found a concrete sizing mismatch in `modules/hr/contract_builder.php`: the logo was rendered at 24 mm inside a 10%-width header cell, making it wider than its own column and increasing the header row height. Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` reduces the logo to 14 mm, sets the logo cell to 12%, keeps the contract number/date panel at 24%, adjusts the organization cell to 64%, and reduces header-cell padding from 2 mm to 1 mm. This retains the existing voucher-style navy frame, organization identity, and boxed number/date panel. No schema, contract lifecycle, legal wording, or page-break logic changed.

**Verification status: SOURCE COMMITTED; local PHP lint and rendered PDF/page-count verification remain pending.** Pull `main`, lint `modules/hr/contract_builder.php`, then preview only an eligible existing contract period (do not create an overlapping draft). Confirm the logo fits inside its cell, the header is compact, the contract is readable, and the PDF totals exactly two pages. The source still has automatic page breaks for the contract body followed by an explicit signature-page break, so a two-page result is not yet proven.

## 2026-10-21 — Contract PDF header proportion finding

Source review identified a concrete layout defect: a 24 mm logo was placed in a header cell only 10% of the table width. Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` reduces the image to 14 mm, widens its cell to 12%, preserves the 24% contract number/date panel, adjusts the organization cell to 64%, and tightens padding. This is a source-only correction aligned with the voucher header composition. Local PDF output and the exact two-page acceptance condition remain unverified; automatic body pagination plus the forced signature page can still produce a third page if the body overflows.

## 2026-10-21 — Contract-builder error toast scope note
The expired/used form-token error and other caught builder errors are displayed in a fixed-position accessible toast rather than an in-flow alert. This is a presentation-only source change; all validation and contract lifecycle guards remain unchanged. Local lint/browser verification pending. Note: fixed positioning prevents the message from affecting document layout, but cannot prevent a full document navigation from naturally opening at its initial scroll position.


## 2026-10-21 — Contract-builder validation navigation regression
The reported jump was traced to `modules/hr/contract_builder.php` submitting with `target="_blank"`; the prior fixed toast was in the response document, not the original form page. Commit `bd088d1f4d090506df48eecf666c270add50f91b` keeps server-side error responses on the current form page via a page-local fetch handler while preserving new-tab PDF output. No changes to global same-page POST handling. Source committed; local syntax/browser verification pending.


## 2026-10-21 — Contract Builder regression and rollback
Audit finding: commit `e41cadc` introduced malformed action-area markup by leaving the PDF submit button incomplete and nesting the return link inside it. The source was restored by `d074486b7cff1a204ab3bf353c829a59ecef32f7` to the voucher-style PDF checkpoint `e7f96e8`, removing subsequent toast/fetch experiments. This is source-level evidence only; the restored form and PDF generation must still be verified in the local XAMPP runtime. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

Correction to rollback record: the complete 799-line checkpoint source was re-applied and checked against the same 799-line source fetched from `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. Final source commit: `7df2bc435d2a6464692895db62cb97ec03a2f109`. The complete file ends with the shared footer include; the PDF submit button and return link are separate. This comparison establishes source equality with the checkpoint, not local PHP/browser runtime success.

## 2026-10-21 — Employment-contract PDF download filename hardening

The user reported that using Download in the browser PDF preview saved the contract with a `.php` extension. Source inspection confirmed the PDF response already used `Content-Type: application/pdf` and an inline `filename="Ahl-El-Kheir-Employment-Contract.pdf"`, so the root cause is not proven without inspecting the actual local HTTP response headers. Commit `1cf3fb0b753b30b49ba5f950498ff534fb802de2` makes the response more robust by clearing any active output buffers immediately before PDF headers/body, and supplying both the ASCII `filename` and RFC 5987 `filename*` parameters while preserving `inline` preview behavior. This is source-only. After pulling `main`, run PHP lint and generate a PDF in the local XAMPP app; use the PDF viewer's Download control and confirm the saved file ends in `.pdf`. If it still saves as `.php`, inspect the actual response headers in browser DevTools before changing more code. Do not claim runtime success before this check.

### 2026-10-21 — Contract Builder toast placement audit note

Source inspection identified a concrete cause: `modules/hr/contract_builder.php` submitted the PDF form to `_blank` and rendered caught errors as a top-of-page HTML alert in that response. Commit `197f53d75cf4546e8efd6a483e9ea850a18916b5` now sends that error back to the opener and displays it as a toast near the clicked button. Successful PDF response behavior is preserved. This is source-level evidence only; local PHP lint and browser runtime verification remain outstanding.

### 2026-10-21 — Contract Builder toast correction audit

User runtime feedback disproved the prior opener-message implementation: the error still appeared in the new tab. Commit `890b9ef95390af64820083a8567c6e5e7fc2ccb0` removes that message-bridge approach. The original form now submits via fetch, server-side errors return JSON for AJAX requests, and the original page shows the toast adjacent to the clicked button. Successful PDF responses are loaded into the preview tab. Source-level only; local PHP lint and browser verification remain pending.

## 2026-10-21 — Contract Builder toast experiment rollback

The user reported an Apache “Not Found” error after the fetch-based toast change. Rather than speculate about the local URL, commit `8af298793a4c86d38d303516b2b03992b7258405` restores `modules/hr/contract_builder.php` exactly to pre-toast checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. The restored file blob matches the checkpoint; runtime verification on the user's XAMPP instance remains outstanding.

## 2026-10-21 — Authoritative Contract Builder continuation state

This checkpoint supersedes earlier entries that proposed further toast/fetch changes or asked to test those experiments. The latest documented rollback is commit `8af298793a4c86d38d303516b2b03992b7258405`: `modules/hr/contract_builder.php` was restored to the pre-toast voucher-style PDF checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`; its source blob was reported to match that checkpoint. Do not reintroduce or continue the toast-positioning/fetch experiment unless the user explicitly requests it.

This is source/checkpoint evidence only, not runtime acceptance. Local XAMPP PHP lint, existing-contract viewing, contract creation through the normal workflow, and generated PDF behavior have not been confirmed after the rollback. If verification is requested, use the configured app URL `http://localhost:8081/AhlElKheir/`, preserve the existing draft/active overlap guard, and do not create a duplicate contract merely to preview the PDF. Record only observed results; do not claim runtime success in advance. Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — Contract Builder toast handling with native PDF response

The user explicitly resumed the Contract Builder toast task and requested that its feedback match proven notification patterns elsewhere in the application. The existing `modules/hr/job_description_catalogue.php` pattern uses a fixed-position RTL toast with accessible live announcement, dismiss control, and five-second auto-dismiss. `modules/hr/contract_builder.php` now applies that toast presentation to caught generation errors on the original form page.

Source commit: `54d4cef36e58695faea509a61b2a0fc5232589bb`. The form targets a named preview window (`contractPdfPreview`); the server returns caught generation errors as a small same-origin response that posts the message to the opener and closes the preview window. Successful generation continues to return the original native `application/pdf` response with its existing inline filename headers, avoiding a fetch/blob preview that could alter download naming. Contract validation, one-use submission token, CSRF handling, transaction/rollback, overlap protection, and PDF generation logic were not intentionally changed. The interim fetch/blob implementation in commit `572ccf46ea16b856592885d492ee6b4b9fc0a30b` was superseded by this native-response approach.

**Verification boundary: SOURCE COMMITTED; local PHP lint and browser behavior are not yet verified.** On the configured XAMPP app (`http://localhost:8081/AhlElKheir/`), first test one controlled server-side validation failure and confirm the toast appears on the original form page without navigating it or leaving an error page open. Verify that the named preview window closes after delivering the message. Check PHP syntax. Test a successful PDF response only when creating the corresponding contract draft is intentional; do not create duplicate/overlapping drafts merely to test preview. Confirm the browser still receives the native PDF response and filename headers. Do not mark this fixed until actual local evidence is available.


## 2026-10-21 — Fixed-term missing end-date error stays on the form

After the user confirmed that the exact validation message `تاريخ نهاية العقد مطلوب للعقد محدد المدة.` still rendered in the preview tab, source inspection found that the submit handler opened `contractPdfPreview` before PHP performed this deterministic validation. The popup response's `window.opener.postMessage()` path had not delivered the message in the user's browser. Commit `c1b80acd47904f91e6d11e615e19de740138ddb5` adds a narrowly scoped pre-submit check for `contract_type=fixed_term` with an empty `end_date`; it prevents submission/opening the preview tab and uses the existing toast on the original page. The authoritative PHP validation remains unchanged.

**Verification boundary:** source committed to `main`; local PHP lint and browser behavior are not yet verified. Test this exact missing-end-date case first: the original page must remain in place, show the toast, and open no new tab. This change only covers the confirmed validation case; other server-side generation errors still need separate runtime diagnosis and must not be described as fixed by this narrow change. Successful PDF generation and existing contract safeguards were not intentionally changed.


## 2026-10-21 — Contract Builder error routing and stale-token recovery (supersedes earlier toast attempts)

User runtime feedback showed that the fixed `contractPdfPreview` browsing-context name could navigate the current tab when that tab already had the same browsing-context name; the resulting server error page was not consistently reaching the form toast. Source inspection also confirmed that successful PDF generation intentionally unsets `ak_contract_builder_submission_token`, leaving the still-open form with a consumed token until it is refreshed.

Commit `c89e22386f921c339b8965de1cf49fd07a11f25b` changes the form to open a fresh, unique preview-window name for each submission while retaining the native form POST and native PDF response (including the inline filename header). Server-side errors send the error and the current/reissued form token to the opener via same-origin `postMessage`; the original page displays the existing toast and refreshes its hidden token. The deterministic missing-end-date check remains before opening any preview window. The AJAX/blob implementation from intermediate commit `dc6a80b0dfc9c086c5cc0346d028b6b7bc872b4b` was superseded to preserve native PDF response/download behavior.

**Verification boundary:** static source inspection confirms the unique preview target, error message/token payload, client token refresh, unchanged native PDF `Content-Disposition`, and preserved one-time-token consumption after success. Local PHP lint and browser runtime are not available from this remote source session; do not call this resolved until the exact local acceptance test passes. Test (1) fixed-term contract with empty end date: toast on original page, no preview tab; (2) server-side token/validation error: toast on original page, error tab closes, no white/error page; (3) successful PDF: opens in separate tab with native PDF response/filename. Do not create duplicate contract drafts solely for testing.

## 2026-10-21 — Contract Builder toast task closed at user-requested boundary

The user confirmed that the error toast now appears after completely closing the page and logging out/in again. On a further check, the user clarified that the page does **not** jump/scroll upward; instead, there is a very brief refresh-like visual effect before the toast appears.

The user explicitly instructed us to stop here and make no further changes. **Task status: CLOSED BY USER REQUEST; residual visual behavior is documented, not diagnosed or claimed fixed.** No further source edits, experiments, or speculative scroll/focus/navigation workarounds are authorized for this task unless the user explicitly reopens it. Do not reopen automatically in a new session.

No cause has been established for the brief refresh-like effect. The current observed behavior is the acceptance boundary the user chose; it is not evidence that the underlying visual effect was technically eliminated. No local runtime or PHP-lint result is newly claimed by this documentation update.

For the next session, read the canonical project documentation and inspect current `main` to determine the next genuinely open task or follow the user's new requirement. Do not resume Contract Builder toast/scroll work unless explicitly requested.

## 2026-10-21 — HR contracts employee details moved into a modal

User request: on `modules/hr/contracts.php`, selecting an employee should open the employee's contract/salary area as a popup, and the employee list should expand to use the width previously occupied by the two-column list/details layout.

Source commit: `29f650e189f748777427ac90eafc630feac58a18` (on `main`).

Implemented in `modules/hr/contracts.php`:
- Replaced the narrow left-hand employee sidebar plus always-visible details panel with a full-width responsive employee-card grid.
- Selecting an employee loads that employee's existing `employee_id` URL and automatically opens a wide, scrollable Bootstrap modal.
- Moved the employee-specific tabs and existing sections into the modal: `العقود`, `سجل الرواتب`, `إعداد عقد جديد`, and `تغيير الراتب`.
- Kept the existing contract status actions, signed-copy upload, salary history, salary-change form, employee/contract queries, permissions, and business rules in place; no schema or migration change.
- Search continues to filter employee cards and now shows an empty-results message. The modal is reopened after the existing form POST reload so the user can see the result/error inside the employee context.

**Verification boundary:** source change committed to `main`; no local XAMPP/PHP lint or real-browser runtime verification is claimed from this session. After pulling, check PHP syntax and test employee selection, all four modal sections, search/no-results, contract actions/upload, salary history/change, and that success/error feedback remains visible inside the modal after POST. Do not claim runtime verified until these checks pass.


## 2026-10-21 — HR contracts modal parse error corrected

After the user pulled the employee-details modal change, local PHP lint reported an unexpected end of file on line 134, expecting `elseif`, `else`, or `endif`. Source inspection identified the exact cause: the employee-selection `if ($emp)` alternative-syntax block had no closing `endif`; the existing terminator closed the nested view switch instead. Commit `decb8a577df182396def4eea906220bd4b4b009a` adds the missing outer `endif` in `modules/hr/contracts.php` without changing application logic.

**Verification boundary:** source correction is committed to `main`; the user's local PHP lint has not yet been rerun, and browser/runtime behavior is not verified. After pulling, run `php -l modules\hr\contracts.php` and report the exact result before browser testing. Preserve the existing untracked `storage/hr_contracts/` directory; it was not modified or included in the commit.

## 2026-10-21 — HR contracts modal initialization finding

The employee-details modal's initialization ran before the Bootstrap bundle in `includes/footer.php`, causing card selection to reload the page without opening the modal. Commit `4fe5b9baaa09830c3bfc06b37c18c3f080660f45` defers the existing initialization to `DOMContentLoaded`. This is source-level evidence only; local lint and browser runtime acceptance remain pending. Detailed checkpoint: `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR employee selector list presentation

Following user feedback, the employee picker presentation was changed from cards to full-width list rows in commit `7464fc22c31f613fcf26f3c33f32ca3d92be491c`. The separate modal initialization timing fix is `4fe5b9baaa09830c3bfc06b37c18c3f080660f45`. Neither change has local PHP lint/browser verification yet. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR employee modal navigation checkpoint

A narrow source update in `modules/hr/contracts.php` (`40f1198a3b0c54b022b2154b55a12dfce345b730`) suppresses footer elements within the employee modal and changes its internal contract/salary tabs to fetch the selected server-rendered view and replace modal content without a full-page navigation. The Contract Builder link remains separate. This is source-committed only; local PHP lint and browser behavior are not yet verified.
Follow-up commit `889a10e6f7368190f8e78a9d356a7fabd638b1c6` hides the shared page footer while the employee modal is shown, restoring it when the modal closes. Runtime verification remains pending.

## 2026-10-21 — Secure viewing of uploaded employment contract copies

Root cause confirmed from repository source: `storage/.htaccess` contains `Require all denied`, which correctly blocks direct HTTP access to `storage/hr_contracts/`. The contract register previously linked directly to `APP_URL . contract_file_path`, so Apache returned `403 Forbidden`.

Source changes committed on `main`:
- `773229e8753979ecfa66ec588b8626b96d1325a8` — change the contract-file link to `modules/hr/view_contract.php?contract_id=...`.
- `a6576eb379988d11f8a112f9c6c073a14ee84c2a` — add the protected viewer. It applies the same HR manager / HR staff / admin role gate as the contract register, resolves the file through the existing contract record, confines real paths to `storage/hr_contracts/`, validates allowed extension and detected MIME type, and streams supported files inline. The storage-wide deny rule is intentionally unchanged; no schema change or migration is needed.

**Verification status: SOURCE COMMITTED; local PHP lint and browser verification remain pending.** After pulling `main`, lint both `modules/hr/contracts.php` and `modules/hr/view_contract.php`, then open an uploaded PDF from the HR contract register while logged in with an allowed HR role. Also verify the raw `storage/hr_contracts/...` URL remains forbidden and a logged-out/non-HR request to the viewer is denied.


## 2026-10-21 — Contract preview, print, and download controls

The HR contract file route now defaults to an authorized HTML preview page rather than returning the file response as the top-level page. The preview embeds the protected file endpoint, provides an explicit **طباعة** action for PDFs and an explicit **تنزيل الملف** action, and retains **فتح الملف في تبويب جديد**. The file endpoint uses `Content-Disposition: inline` by default and switches to `attachment` only when the explicit download action is requested. Storage remains protected by `storage/.htaccess`; no database/schema changes were made.

Commit: `ac91f1b65f87300114f53ceeae98b28734f90d64`.

Verification boundary: source is committed to `main`; local PHP 8.2 lint and browser behavior are not yet verified. In particular, browser PDF handling can still depend on client settings, so confirm the embedded PDF preview and print/download controls in the local Chrome/XAMPP environment before calling this runtime-verified.


## 2026-10-21 — Reverted contract preview wrapper

At the user's request, removed the additional HTML preview wrapper and restored the prior direct authorized file response in `modules/hr/view_contract.php`. The HR contract register continues linking to that protected route; `storage/.htaccess` remains restrictive. The route sends the file with `Content-Disposition: inline`, allowing the browser's PDF viewer/settings to determine inline display versus download. No database/schema changes.

Commit: `e3b458eb57e618acaf8c8bb0a06df79cd02ece7e`. Local PHP 8.2 lint and browser behavior remain unverified.


---

## 2026-10-22 — Nanny group bulk verification (verification pending)

Scope: improve Nanny throughput for the three monthly family checks (orphan eligibility, mother contact, and bank-account details) without weakening evidence or the Accounting boundary.

Implementation committed in `modules/accounting/group_verification.php`:
- bulk selection supports a single chosen check or all three checks;
- single-check bulk operations preserve the other two stored flags;
- selected IDs are validated against the assigned group before writes;
- bulk writes and each family's `verification_audit_log` record are atomic;
- group assignment and pending-state checks are server-side;
- individual verification and group submission also validate current assignment/scope.

Schema inspection confirmed the existing family/month unique key and audit columns; no migration was added. GitHub Actions run `39715384849` passed the `php-lint` job with this page included. Local application/database runtime verification remains pending. The separate `migration-full-schema` job failed at the existing Stage 5 salary-advance payroll integration check (`يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع`); this unrelated failure is recorded, not remediated under this task. This is an implementation checkpoint, not a runtime acceptance claim. Detailed test steps: `docs/ACCOUNTING_NANNY_GROUP_VERIFICATION_CONTINUATION.md`.


## 2026-10-22 — Nanny individual verification state guard

A reported UI inconsistency was confirmed by source inspection: bulk controls were restricted to pending groups, while individual controls were always rendered and the individual POST path omitted the pending-state condition. Commit `e50686f0a8492506459e4465513d03778debc498` adds the pending-state server guard; follow-up commit `da5320d05e2656bb9d6e1f4a7a94b94b032fd177` corrects the markup to suppress individual edit controls/modal for non-pending groups. This is a narrow authorization/workflow-state consistency correction; it does not modify stored verification records or group states. PHP lint passed in GitHub Actions run `39716749893`; local runtime verification remains outstanding.

## 2026-10-22 — Nanny preliminary monthly family verification

Added `modules/accounting/nanny_pre_verification.php` as a separate Nanny workspace and linked it from the Nanny sidebar and the existing group-verification page.

The page reuses `nanny_family_verifications` for the displayed payment month (`YYYY-MM`); it introduces no separate preliminary status and requires no schema migration. Added English compatibility translations for the new Arabic UI strings in `lang/bridge/ar_to_en.php`. Individual and bulk changes use the existing three flags and append per-family `verification_audit_log` records transactionally. Single-check bulk operations preserve the other flags; individual review can explicitly change or clear flags. The bulk selection is fully validated before writes.

Server-side scope is limited to the logged-in Nanny's active assignments and pending groups. Because the schema stores one verification row per family/month rather than per group, families also present in active non-pending groups are excluded to avoid modifying a shared record already used by a submitted/locked group. This page does not submit groups to Accounting.

**Verification boundary:** source committed to `main`; PHP 8.2 syntax lint PASS in GitHub Actions run `39719045605` (the `php-lint` job explicitly includes `modules/accounting/nanny_pre_verification.php`); local XAMPP/browser/database acceptance remains pending. Use the existing `ahl_el_kheir` database only; do not create a disposable database or reset operational data.


## 2026-10-22 — Nanny monthly verification scope and month selection

The preliminary verification page now accepts a validated `YYYY-MM` month and lists all active families with active children in active groups currently assigned to the logged-in Nanny, regardless of each group's global verification status. Eligibility and save-time authorization use the same assignment/family scope; monthly values remain in the existing `nanny_family_verifications` rows keyed by family and month, and all changes retain transactional per-family audit logging. No schema or existing data changes were made.

The group-verification page now uses the selected month for reads and writes, carries it through navigation and POST forms, and allows the assigned Nanny to review/edit that month's verification flags for a family in any active assigned group. The group itself can still be submitted only under the existing pending-state rule; downstream submission/accounting behavior was not changed.

Source commits: `53190c05f7f4ba4bcfd0952e8a5fc17129d8a1f3`, `fd5d9c70e7b1c0be75e7a10b8823982f79f30f95`, `23722801177e921a82850b9c8ff15fcd6838fe55`, `7198ef5a3a488a404dfb73b41bc2c72e73def6a1`. Static/runtime verification is pending; do not claim the change is runtime verified until PHP syntax and local XAMPP/browser acceptance are confirmed.


## 2026-10-22 — Shared family/month verification visibility correction

Source commit: `f108ac6f43a71b210518e8d932977e3f0a1ccd11` updates `modules/accounting/lib_group_workflow.php`. The existing schema stores one verification record per family/month, but `get_latest_verification_status()` also filtered by the last verifier's `nanny_id`. That made the same shared family/month record appear unverified when read by a different Nanny assigned to another group containing that family. The lookup now uses the family/month key; `nanny_id` remains attribution for the latest verification action, not a visibility filter.

No schema or data changes were made. This affects the preliminary Nanny page and group verification reads that share this helper. **Verification boundary:** source committed to `main`; PHP lint, relevant regression checks, and local XAMPP/browser acceptance are still pending. Verify that the same family's flags for a selected month remain consistent between the preliminary page and each assigned group, including when the record's last verifier differs, and that another month remains independent. Do not claim runtime acceptance until tested against the existing `ahl_el_kheir` database.


## 2026-10-22 — Monthly family verification selection follow-up

After the user reported that the bulk-verification UI still showed “لم يتم تحديد أسر” after selecting families, `modules/accounting/nanny_pre_verification.php` was updated in commit `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`.

The page now mirrors checked family IDs into hidden controls physically inside `bulkVerificationForm`, synchronizes that mirror when individual or select-all checkboxes change, and uses the synchronized selection count for confirmation. After confirmation it uses `requestSubmit()` where available so the shared same-page POST handler can process the request rather than bypassing the submit event with `form.submit()`.

No schema/database changes were made. Source-level implementation is committed; PHP syntax check, browser behavior, and real local XAMPP/database verification are **pending**. The user will pull `main` and perform the local acceptance steps; do not mark fixed until the bank-only operation succeeds and preserves the orphan flag.


## 2026-10-22 — Closed-group verification controls refined for Nanny workflow

The user clarified that a Nanny may reconnect with a family later and need to reverify that family for an older payment month, even when the group is already closed. Commit `821774a06aa6434a31bc4538bfe37217ebafe55c` supersedes the earlier overly restrictive closed-group UI rule in `modules/accounting/group_verification.php`:

- Individual family confirmation/edit actions remain available for any selected active group still assigned to the logged-in Nanny, including a closed group, and the individual POST guard permits this same assigned-group workflow. The selected payment month continues to scope the family/month verification record.
- Bulk verification controls and family-selection checkboxes remain available only when the group status is `pending`. Bulk POSTs still require pending status, so a closed/processed group cannot be bulk-modified.
- Group submission remains pending-only. No group status, schema, or verification data was changed by these source edits.

This correction supersedes the preceding note that all closed-group individual controls should be hidden. The user must pull `main`, run PHP lint on `modules/accounting/group_verification.php`, and browser-test: closed group + historical/future month shows individual actions but no bulk controls; pending group shows both individual and bulk controls; individual save affects only the selected month; bulk POST against a non-pending group is rejected. Local runtime acceptance remains pending.

## 2026-10-22 — Nanny monthly group verification acceptance CLOSED

The user pulled the latest `main` (`c3493831`), ran `D:\\xampp\\php\\php.exe -l modules\\accounting\\group_verification.php`, and confirmed **no PHP syntax errors**. The user then confirmed the updated workflow behaves as intended. This closes the current Nanny verification-control acceptance at the user's reported boundary:

- The selected payment month scopes the shared family/month verification record.
- Individual family verification remains available in an active group assigned to the Nanny, including a closed group, so a family can be reverified for a historical payment month.
- Bulk selection and bulk verification remain pending-group-only; non-pending bulk POSTs are rejected server-side.
- Group submission remains pending-only.
- No schema migration or database/data change was part of this correction.

Source correction: `821774a06aa6434a31bc4538bfe37217ebafe55c`. Bulk-selection synchronization fix: `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`. Related documentation has been updated to record this user-confirmed acceptance. Do not reopen this workflow without concrete regression evidence.

**Next task from the remaining open documentation:** continue the HR employment-contract portal's pending local verification, starting with the latest `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md` instructions and current `main` source. Do not resume the Contract Builder toast/brief-refresh visual issue; the user explicitly closed that task.

## 2026-10-22 — HR job-description migration safety review

Before asking the user to apply the pending job-description migrations, source review found a concrete data-preservation risk in `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql`: its qualification-baseline update could change an already `approved` active description and downgrade its status to `needs_review`, contrary to the documented preservation boundary. Commit `0e7ec314d4335d2ff3428640b76b6c18629fc86b` adds `AND description_status <> 'approved'` to that migration's update predicate. This is a migration-file correction only; no local database was accessed or changed. Existing approved descriptions are now excluded from this baseline update. Do not claim the migrations are applied or the portal runtime-verified. The remaining work is to inspect the entire migration sequence against the current schema and actual local database state, then perform the local application and focused HR portal checks without touching unrelated pages or existing contract files.

## 2026-10-22 — HR portal pre-migration safety guard

Source commits `4173bb3d1a42a2cc95e796e5d66a3d9e0134d0f9` (`modules/hr/contract_builder.php`) and `5258fa82988902811212544d2a01ef70d8e2e45b` (`modules/hr/job_description_catalogue.php`) add a read-only `information_schema.tables` preflight for `hr_job_description_templates`. If the migration has not been applied, Contract Builder now continues with its existing employee/contract fields and manual job-description entry, shows a warning that automatic catalogue prefill is unavailable, and avoids querying the missing table. The new catalogue page displays a clear HTTP 503 migration-required message instead of failing on downstream SQL. This guard performs no DDL and changes no business data. When the table exists, the normal catalogue query path is unchanged.

These are source-only changes committed on `main`; PHP lint and local XAMPP behavior are not yet verified. Next: check the relevant GitHub Actions run, then complete the migration-order/data-preservation audit and the local migration + HR portal acceptance. Do not mark runtime verified without local evidence.

## 2026-10-22 — HR job-description migration safety follow-up

- Audited the eight `2026-10-20_hr_job_description_*` migrations for preservation of approved catalogue records.
- Corrected `2026-10-20_hr_job_description_proposals.sql` so its proposed-field backfill and HR Test deactivation skip rows with `description_status = 'approved'`. The qualification baseline migration also excludes approved rows. No database was changed by this repository edit.
- Expanded `.github/workflows/php-syntax-check.yml` to PHP-lint `modules/hr/contract_builder.php` and `modules/hr/job_description_catalogue.php`.
- Latest full-schema CI run imported the checked-in schema and passed its migration/backfill step, but failed in the subsequent Stage 5 salary-advance regression step. This is not evidence of an HR migration failure; the exact regression failure remains to be diagnosed before treating the whole workflow as green.
- Local XAMPP/MariaDB migrations and browser/PDF verification remain pending. Back up `ahl_el_kheir` before applying migrations; do not run DDL from PHP. Preserve local untracked files.

## 2026-10-22 — HR migration CI coverage

- Added the `hr-job-description-migrations` CI job in `.github/workflows/php-syntax-check.yml`. It imports the checked-in schema into a disposable MariaDB 10.4.32 instance, applies all eight `2026-10-20_hr_job_description_*` migrations in filename sequence, and asserts that an approved HR-manager title-only catalogue row retains its approved status and blank content fields.
- The same workflow now PHP-lints `modules/hr/contract_builder.php` and `modules/hr/job_description_catalogue.php`.
- Repository-side safeguards are committed; local `ahl_el_kheir` remains untouched. Do not apply the migrations locally until the CI migration job completes successfully and a backup is available. Local page, prefill and PDF verification is still pending.

## 2026-10-22 — Local verification evidence: HR job-description proposals migration

Evidence from the user's local `ahl_el_kheir` database after applying `database/migrations/2026-10-20_hr_job_description_proposals.sql`:
- Active rows with `description_status='needs_review'`: 23.
- Active review rows with blank `reporting_to`: 0.
- Approved records: IDs 6 and 20 remain `approved`, `is_active=1`, and have nonblank reporting lines.
- Test record: ID 11 (`HR Test`) is `needs_review`, `is_active=0`.
The mysql command returned no reported SQL error. The user had created a database backup before applying the migration.

**Audit boundary:** this is direct evidence for the reporting-line backfill and the listed status/active safeguards, not a full before/after audit of every field changed by the SQL. It does not establish that the other seven migrations were applied, nor that the local Contract Builder/catalogue/browser/PDF workflow was runtime-tested. Do not infer Arabic text corruption from garbled console rendering alone; PowerShell/mysql literal encoding previously made direct Arabic-literal checks unreliable.


## 2026-10-22 — AI assistant security and verification boundary

Initial source adds a read-only role-aware assistant. Local retrieval is restricted to the matching role section in the employee user guide. Gemini receives only a user's eligible general question after an explicit per-question opt-in; basic internal-workflow and sensitive-pattern checks block likely internal or personal questions. The key remains server-side. No arbitrary SQL/PHP, record reads, business mutations, or chat-history storage were introduced.

Audit limitation: the sensitive-content filter is a conservative first pass, not a guarantee that every possible personal or confidential string can be detected. The feature has not yet passed PHP CI, local session/CSRF/role-boundary tests, or InfinityFree network/quota verification. Follow docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md before deployment; do not claim runtime acceptance.


## 2026-10-22 — Internal documentation web-access guard

Source review found the root .htaccess only disabled directory listings and docs had no local access-denial rule. Added docs/.htaccess with Apache 2.4 Require all denied. PHP filesystem retrieval remains available for the assistant, but direct HTTP access must be verified as denied on InfinityFree before deployment acceptance. This source safeguard has not been runtime-tested on the hosting account.


### 2026-10-22 — AI assistant Arabic retrieval follow-up
The first local browser test showed the assistant UI works, but the Arabic question about the first step after sign-in missed local retrieval and reached the unconfigured-Gemini fallback. Root cause: the canonical employee guide described sign-in only in English while retrieval is keyword-based. The guide now includes a reviewed Arabic/English post-login instruction: confirm the dashboard matches the user's job role, and complete a required password change if prompted. Retest the exact question locally before configuring Gemini. No application logic, schema, or privacy boundary changed.


## 2026-10-22 — AI assistant UI refinement
The assistant panel no longer displays the introductory privacy notices or local-guide filenames beneath answers. The launcher now includes a Font Awesome comments icon beside its label. Google consent starts checked and the user's choice persists across messages; server-side internal/sensitive prompt blocking remains in force. Source commit: `5149e20b6e42a393ca59fd6d84cec12edaf08372`. PHP lint and browser regression checks remain pending.


## 2026-10-22 — AI assistant persistent chat history
Source implementation adds user-owned conversation/message tables, a history list and load UI, and a new-conversation action. Endpoint ownership checks use the authenticated user ID for list/load/continuation; clients cannot access another user's conversation by supplying its ID. The history is not sent as context to Gemini. Migration: `database/migrations/2026-10-22_ai_assistant_chat_history.sql`; local schema confirms `users.id INT(10) UNSIGNED`. The migration has not yet been applied and PHP/browser runtime/security verification is pending. This checkpoint is implementation status only, not acceptance evidence. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.

## 2026-10-22 — AI assistant Arabic retrieval / voice audit update

Observed browser evidence: the post-login Arabic question received a local guide answer on retry; “كيف أستطيع عرض كل المجموعات المخصصه لي” returned the general-AI-unconfigured message; on-device dictation displayed a generic failure. Source review established that the approved guide's Nanny section was English-only, retrieval was token-overlap based, Arabic ta marbuta was not normalized, and the external-AI blocklist was primarily English. Arabic Nanny guidance and normalization/blocklist terms were added. The voice handler previously discarded the browser error code; it now surfaces it for root-cause diagnosis. No voice root cause is yet established and no network speech fallback was added. Local syntax/runtime verification of these latest changes is pending.

## 2026-10-22 — AI assistant launch-readiness audit update

The user reported repeated failure to answer a Nanny group-navigation question despite Arabic guide content. A deterministic role-scoped local answer was added in commit `dc0e7640fe98cb134590c0ec3e314c80133e16f4`; local syntax and browser verification remain pending. Voice testing returned the browser's `language-not-supported` error for on-device Arabic. This establishes that the selected on-device path does not support the requested locale in that environment; it does not establish a microphone-permission problem. Online speech recognition remains blocked pending an explicit privacy decision and consent design.


## 2026-10-22 — AI assistant online speech privacy and acceptance boundary
The project owner explicitly approved online Arabic speech recognition with clear user consent before audio processing and a typing fallback. Commit `18bf40af2f905b5509396d92e17374c0c79ecd7c` updates the existing assistant widget only. The consent prompt explains that the browser may send audio to its speech-recognition provider; recognition starts only after affirmative action. Consent is page-session-only, cancellation is available, and recognized text remains editable before the user submits it. The feature does not send audio to the PHP assistant endpoint. Actual provider behavior and browser availability remain browser-dependent. Local PHP syntax and runtime acceptance are pending; no success claim is made yet.


## 2026-10-22 — AI assistant voice failure reporting
A reported post-consent voice failure could not be diagnosed from the UI because the existing error handler collapsed most Web Speech API errors into one generic message. Commit `753a3b225c1a992bcb3a6b1ce627ea365fef3092` maps common error categories to staff-friendly messages and logs only the category to the browser console. This change improves observability but does not establish that the provider or recognition works. Local syntax and browser acceptance remain pending.
## 2026-10-22 — AI assistant voice privacy and verification boundary

The voice input implementation now uses explicit consent, browser MediaRecorder, and server-side Gemini transcription rather than browser-vendor SpeechRecognition. The PHP endpoint checks session authentication, CSRF, audio MIME type and size, and a per-session audio request limit; it returns transcript text only. Recording data is not persisted to chat history. Source commits: `d5d91e4`, `d30efdb`.

This is a source implementation, not a runtime-verified fix. Verify PHP syntax, consent/decline, permission denial, Arabic and English transcription, editable transcript/no automatic submission, failure handling, and typing fallback locally before closing the issue. Do not enable paid billing or expose the API key.


## 2026-10-22 — Gemini audio transcription diagnostic follow-up

The user-approved MediaRecorder → Gemini transcription path reached the endpoint but returned the generic upstream-failure response in the browser. Source audit found the endpoint was discarding both cURL transport diagnostics and Gemini's structured error response, preventing a root-cause diagnosis. Commit `130af192c49aafae11c1369e7e884e9367016ac8` now logs the HTTP status, cURL errno/error, and a bounded Gemini error status/message to the PHP error log while keeping the user-facing response generic and never logging the API key or audio payload. It also removes the undefined `$lang` reference from the transcription prompt, using one language-neutral bilingual prompt.

**Verification is pending.** The repository connector cannot run PHP lint or exercise the user's local Apache/PHP/Gemini request. Pull `main`, run `D:\\xampp\\php\\php.exe -l D:\\xampp\\htdocs\\AhlElKheir\\modules\\ai_assistant\\chat.php`, then make one short consented recording. If it fails, inspect `D:\\xampp\\apache\\logs\\error.log` for lines beginning `[Ahl El Kheir AI transcription]` and share only those lines; do not share the API key or audio. This is diagnostic instrumentation, not a claim that transcription now succeeds.

## 2026-10-22 — AI assistant audio transcription failure boundary

A browser request to the audio transcription endpoint returned JSON `HTTP 502`. Source inspection confirms this is set by the application when cURL fails or Gemini returns a non-2xx response. The precise cause is not yet evidenced; no matching diagnostic line was found in the first log locations checked. Commit `3b7cc562c57b87533678146412cf0f44f5b1ca7c` adds safe user-facing failure categories and retains detailed diagnostics server-side. This is not a verified transcription fix. Continue with one short recording after pulling `main`, then investigate based on the resulting user-facing category. Do not guess at the API key, model, or network as the cause.

## 2026-10-22 — Gemini transcription model availability

The endpoint's NOT_FOUND response is consistent with its source default gemini-2.5-flash. Current Google documentation records that model's Gemini Developer API shutdown on October 16, 2026, and documents gemini-3.5-transcribe as the dedicated transcription model supported by Generate Content. Commit b4b7c6e7a3069a0c8d5eb254b37db9d9941f5927 updates the default model. This is source-based root-cause evidence, not yet a local runtime proof; an explicit environment/deployment override may still supersede the default. Verify after pulling main.



## 2026-10-22 — Gemini transcription API contract correction

The user tested the updated dedicated model and received “No usable transcript was returned.” The endpoint had been changed to `gemini-3.5-transcribe` but still called the legacy `generateContent` endpoint and parsed the legacy `candidates[0].content.parts[0].text` response. Official Google documentation specifies that Gemini 3.5 Transcribe uses `POST /v1beta/interactions`, accepts audio content with `type`, `data`, and `mime_type`, and returns the transcript in `output_text` (or text content within response steps). This explains the observed successful upstream response with an empty transcript under the old parser.

Commit `7f1ab66a8e8b386eca3bfa4d0482138d82658241` changes the audio request to the Interactions API, uses its `transcription_config.mode=smart`, maps browser `audio/mp4` to the API's documented `audio/m4a` MIME type, and reads `output_text` with a steps-content fallback. It preserves explicit consent, the editable composer, server-side API-key handling, existing size/rate checks, and typing fallback. Official reference: https://ai.google.dev/gemini-api/docs/transcribe and https://ai.google.dev/api/interactions-api-v1.

**Verification status: source correction committed on main; local PHP lint and one real-browser Arabic/English transcription test are still pending.** Do not claim the transcription path is fixed until the user confirms that words appear in the editable composer without automatic submission. If the next test fails, diagnose the exact new user-facing response and existing server log evidence before changing code again.


## 2026-10-22 — Gemini transcription still returns empty text

After the Interactions API request/response correction, the owner tested both Arabic and English and still received the no-usable-transcript message. The precise provider response shape remains unverified. Commit `1b733cc0fef5aeca86b3753230afced29429509d` adds safe response-shape diagnostics only (JSON validity, top-level keys, interaction status, output text length, and step/output counts); it never logs the audio, API key, or transcript. The next action is one local pull, PHP lint, one short consented recording, then inspect only the matching `[Ahl El Kheir AI transcription] Empty transcript response shape:` line from Apache's error log. Diagnose that evidence before changing request structure again. Runtime success is not established.

## 2026-10-22 — AI assistant voice transcription request-flow correction

Source review of modules/ai_assistant/chat.php and Google's Gemini 3.5 Transcribe REST documentation found that the previous implementation sent inline audio data to Interactions, while Google's transcription REST example uploads the audio through Files API and passes the resulting URI to Interactions. Commit 87c37e4238858b6e126382f41476db8f49929e18 implements that documented flow with HTTPS host validation and bounded, non-sensitive upload diagnostics. The user-provided Apache log excerpt contained only startup messages, so it was not treated as evidence about the API response. Local syntax/runtime verification remains pending.
The consent prompt was separately updated in commit f89eaf85bc3b91389ab1c11abe541c02a21abfb4 to disclose Google's possible temporary retention of uploaded audio for up to 48 hours. Runtime verification of the updated voice flow remains pending.


## 2026-10-23 — AI assistant text-path source audit
Reviewed the assistant endpoint and widget. Text chat uses authenticated CSRF-protected requests, local-first role-limited guide retrieval, gated general Gemini fallback, internal/sensitive prompt filters, and user-scoped conversation history. The general-text model default was changed to `gemini-3.5-flash-lite`; voice input was disabled in the UI and deferred. No local runtime was available to this review, so PHP lint/browser checks and the effective deployment model override remain unverified. Preserve the text-only fallback and do not claim acceptance before local verification.

## 2026-10-23 — AI assistant cross-page state audit
The assistant widget is included by the shared footer on authenticated pages, so page navigation reloads its JavaScript state. The active conversation ID is now stored per tab and its message list is restored using the existing `history_load` action, whose SQL filters by both conversation ID and authenticated user ID. Explicit New chat removes the active ID. Panel close-on-mouseleave follows the sidebar's existing behavior. No database or permission changes. Commits `9db6c37945f2af5e775dc4f9bf651acee7d657b7`, `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`. Browser validation remains pending.

## 2026-10-23 — AI assistant text chat implementation checkpoint

The text-chat implementation requested in this work unit is closed at the source/documentation boundary. Changes cover tab-scoped active-conversation persistence, restoration after full-page navigation through the existing authenticated and owner-scoped `history_load` action, explicit New chat separation, and panel close-on-mouseleave. Local-guide retrieval remains first; general Gemini fallback retains per-question consent and internal/sensitive-question restrictions. The microphone control is disabled and voice is deferred. Relevant commits: `2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b`, `75c52595eef9cd6dd2d8ab71756f06103601c1c8`, `9db6c37945f2af5e775dc4f9bf651acee7d657b7`, and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`.

The owner confirmed a local-guide answer works. This editing session did not execute local PHP lint or browser acceptance for cross-page restore and mouseleave behavior; those checks remain unverified unless separately confirmed by the owner. Do not promote them to runtime PASS without evidence. This is not a reason to reopen implementation absent a concrete regression. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md` for the bounded optional local acceptance checklist and next-session instruction.

## 2026-10-23 — AI assistant account isolation and internal workflow retrieval

Observed failure: a second account in the same browser received a previous-conversation restore error, and a question about assigning families to nannies was not answered from the local guide.

Source root cause:
- `includes/ai_assistant_widget.php` used a single tab `sessionStorage` key regardless of signed-in user. The history endpoint correctly enforces conversation ownership, but the widget retained the failed ID after the rejection.
- `modules/ai_assistant/chat.php` had an Arabic shortcut for viewing assigned groups but no deterministic VGM answer for group/nanny assignment. `modules/deputy_gm/groups.php` is the authoritative VGM-only workflow source.

Corrections:
- `ad6d2c7ede03b7e665ff11b74af55949818b5a76`: namespace the key by signed-in user ID and clear the active pointer after restore failure.
- `fb030a08ba65093b51cf31cba4979189457e87bf`: document the workflow in the employee guide.
- `4fe4a98dc3bcf40e5122c04f069b1f3a40cb99dc`: deterministic answer restricted to VGM/vice-GM roles; it clarifies assignment is to a group and retains the child eligibility/current-month disbursement constraints.
- `9cd98ce9f1b86d521a34e2e8e55dcdc1dd982d40`: update the canonical roles/workflows document.

No schema changes. Source correction only; local PHP lint and browser tests remain pending. Acceptance must include two-account browser switching, same-user history restoration, a VGM question, and confirmation that a non-VGM user is not given VGM-only instructions.


## 2026-10-23 — Final AI assistant acceptance preparation

A static acceptance audit of current `main` found one concrete regression that prevented the agreed voice-input path from being tested or used: the microphone button still had the HTML `disabled` attribute and the widget script explicitly disabled it with the old “planned for a future update” label, despite the consented MediaRecorder → Gemini transcription implementation being present. This stale deferral code made the transcription implementation unreachable from the UI.

The smallest correction removes the stale disabled state and future-development label while retaining the explicit consent dialog, recording limit, authenticated PHP endpoint, editable transcript, and no-auto-submit behavior. No schema/database changes.

**Status:** source-level root cause identified and correction committed on `main` in `67d26422ee41c63f644ba8e05cd6c5ee7d2e623e`. Still pending local PHP lint and real-browser acceptance; repository tooling cannot execute the user's XAMPP app, microphone, session, database, or configured Gemini credentials. The owner should be asked to pull/test only after the remaining source checks and documentation are committed. Required final browser matrix: account switching/history privacy; same-user navigation restore/New chat/mouseleave; VGM versus non-VGM local answer; no-consent and internal/sensitive-question blocking; eligible general fallback only with consent; voice consent decline, microphone permission, short Arabic/English recording, editable transcript/no auto-submit, and text fallback. Do not mark end-to-end complete until these local checks pass.


## 2026-10-23 — Systematic document archive design opened

The new independent task is a systematic archive for receipts and uploaded documents. Read `docs/DOCUMENT_ARCHIVE_CONTINUATION.md` before implementation. The current checkpoint is discovery/design only: no application code, database schema, stored files, or business records were changed. Source inspection found multiple storage roots and module-specific serving routes, plus runtime DDL in `modules/families/documents.php` and `modules/transactions/receipt_file.php`; preserve these as explicit audit findings and do not use runtime DDL. Recommended approach is a central searchable catalogue/version/audit layer, preserving domain authorization and business records, registering historical files in place first, and migrating physical files only after schema and host behavior are verified. Next: finish upload/serving/schema inventory, inspect the actual local `ahl_el_kheir` schema and hosting constraints, then finalize a migration design before implementation.


## 2026-10-23 — Document archive source inventory checkpoint

**Status: discovery/source audit advanced; no application code or schema changed.** Source files inspected on `main` include transaction/sponsor receipt submission and returned-record resubmission, FINA external collection upload and settlement evidence, monthly disbursement/family/return receipt upload, family document upload/delete/serve, project document and expense-receipt upload/replacement/serve, HR contract upload/serve, salary-advance payment and direct-repayment evidence upload/serve, and message attachment upload/delete/download.

### Confirmed workflow findings (source-level only)

- Transaction/sponsor receipts: `modules/transactions/create.php` writes shared physical receipts under `storage/receipts`; accepts JPG/JPEG/PNG/PDF by extension and 10 MB size, generates unique names, and supports separate line and unified receipts. Returned transaction/payment resubmission replaces the DB path then invokes an unreferenced-path cleanup helper after commit. Existing path-sharing in the DB means cleanup must be reviewed as a shared-reference rule, never blindly copied into archive code. The source does not perform detected MIME validation in these upload branches.
- FINA external receipts: `modules/transactions/fina_payment_create.php` stores under `storage/receipts` and removes the new file on DB transaction failure. Settlement transfer evidence uses `storage/receipts/fina_settlements`; its caller removes the uploaded file when transfer processing fails. Confirmed from source, not runtime.
- Disbursement receipts: `modules/accounting/disbursements.php` has separate family, return, and bank-transfer receipt upload paths. Most inspected branches validate detected MIME and a 10 MB cap. File writes and DB/journal updates span multi-step workflows, so cleanup and rollback behavior must be mapped per action before central integration.
- Family documents: `modules/families/documents.php` accepts PDF/JPG/JPEG/PNG by extension and 10 MB size, creates date/sequence names, inserts `family_documents` metadata, and logs upload. Its delete flow unlinks the physical file before deleting the DB row. A shared/versioned archive must change that lifecycle deliberately; no direct replacement is safe. Existing runtime DDL remains an unrelated pre-existing issue to address only in a justified scoped change.
- Project documents: `modules/projects/view.php` uses detected MIME allowlists, opaque random names, and a 10 MB limit. Replacing a document updates the stored path and then unlinks the prior path; expense receipt update/delete paths also unlink stored files. These are concrete archive-integration points because historical versions and shared references cannot be preserved by the current unlink behavior without a coordinated change.
- HR contracts: `modules/hr/contracts.php` accepts PDF/JPG/JPEG/PNG by extension with a 10 MB limit and opaque generated names, but the upload branch does not detect MIME. On replacing a contract file it updates `hr_employee_contracts.contract_file_path` without deleting the old file, so the prior physical file can become unreferenced. `modules/hr/view_contract.php` performs stronger path containment and detected-MIME validation during retrieval.
- Salary-advance receipt/evidence: `modules/hr/lib_salary_advance_accounting.php` validates detected MIME and a 5 MB limit, records audit metadata, replaces the current payment-receipt row in a transaction, and deletes the old file after commit. `modules/hr/lib_salary_advance_repayment_evidence.php` follows a similar row-replacement pattern and removes the prior file after success. The archive integration must preserve evidence history rather than silently discard replaced bytes.
- Message attachments: `modules/messages/attachment.php` uses extension plus detected-MIME allowlists, generated stored names, and removes a newly uploaded file if DB insertion fails. Its delete path removes the DB row then unlinks the file. `modules/messages/download_attachment.php` performs authenticated record lookup and streams the attachment.
- Serving routes are not uniform in caching, MIME source, path containment, role/scope checks, and disposition. Keep each domain's authorization adapter and existing URLs while introducing archive metadata; an archive ID or search result must never independently authorize access.

### Archive implications

1. Register historical files in place first; do not move/rename existing files during initial rollout.
2. Before the pilot, specify version creation and failure/rollback boundaries for every replacement path, and prevent physical deletion while any source record/version references the file.
3. Do not reuse existing unlink logic as an archive deletion API. Deletion must be a reviewed business action plus reference/version checks and audit evidence.
4. New upload integration needs consistent detected-MIME validation, opaque storage identity, checksum capture, CSRF, size limits, cleanup after failed DB work, and no-store/nosniff streaming where appropriate. Existing workflows vary; this is a design target, not a claim that every current endpoint is vulnerable.
5. No runtime verification of local Apache/production storage behavior was performed by this source pass. No code, schema, files, or business records were changed.

### Next exact action

Finish the remaining source/schema matrix for all source tables and deletion/reset workflows, then compare it with `database/ahl_el_kheir.sql` and the actual `ahl_el_kheir` schema before proposing explicit migration DDL. Do not implement the archive tables or change file lifecycle until that schema contract and the pilot category are justified by evidence.



## 2026-10-23 — Archive schema migration drafted (not applied)

A schema-only migration has been added on main:
`database/migrations/2026-10-23_document_archive_catalogue.sql`
Commit: `4b5a141511a4d2469f36a3e2df7af2ad31d990d7`

It defines four catalogue tables: `document_archive_blobs`, `document_archive_documents`, `document_archive_versions`, `document_archive_source_links`, and `document_archive_events` (five tables; the catalogue responsibilities remain stored blobs, logical documents, immutable versions, source links, and audit events). Internal foreign keys restrict deletion of referenced archive records. Source-record IDs are typed locators without guessed foreign keys to domain tables. User actor IDs intentionally have no FK so historical attribution is not coupled to user-row deletion. A unique storage key supports registering one shared physical path once; a unique source locator tracks the current source mapping; replacement history is represented by version rows plus audit events.

Evidence used: the repository SQL dump was retrieved via GitHub contents and parsed (5,691,158 characters); it defines the existing project tables and indexes, and contains no `document_archive_*` tables. The dump's project schema agrees with the supplied actual-DB column inventory for `project_documents` and `project_payment_evidence`. The migration follows the repository's existing InnoDB/utf8mb4/CREATE TABLE IF NOT EXISTS migration style.

**Important status:** migration file only. It has not been executed against `ahl_el_kheir`; no PHP integration, file backfill, or runtime test has occurred. No existing files or source records have been changed. Before applying it, verify the live DB still has no archive objects and run the DDL against a disposable schema copy or an isolated test environment. Then implement an idempotent, report-only project-file registration/backfill and the project-document/payment-evidence adapters. Do not start physical migration or change replacement/deletion behavior until those adapters and acceptance checks are ready.


---

# Document Archive — 2026-10-23 checkpoint

A separate archive catalogue schema is committed in `database/migrations/2026-10-23_document_archive_catalogue.sql`. Local verification supplied by the user confirms five `document_archive_*` tables, expected indexes and foreign keys in `ahl_el_kheir`. This evidence is specific to the local database.

The first read-only pilot covers `project_documents` and `project_payment_evidence`. Live table definitions were supplied by the user. `tools/report_project_document_inventory.php` is committed to report file existence, project-storage containment, shared path references, available size/MIME inconsistencies and SHA-256 duplicate-content candidates. It has not yet been run locally; no file inventory or production-host behavior is asserted. No existing business paths or authorization endpoints were changed.


---

## 2026-10-23 — Archive pilot: Global Search integration and upload hooks (runtime pending)

The archive pilot now follows the user's direction to reuse the existing Global Search Center. The new `documents` search type is governed by the existing central search permission policy. Query results require a current matching source link and a present blob; each result is filtered through the project's existing `akp_can_view_project()` rule, with the payment receipt route's additional role allowlist applied before showing a link. Downloads continue through existing source endpoints rather than a generic archive-ID endpoint.

The new procedural project archive adapter registers verified blob metadata, logical documents, immutable versions, source links, and audit events within the source-record transaction. Project-document and project-payment-evidence upload/replacement flows were wired in the project manager/supervisor page and FM project page. Replacements preserve old physical bytes. Source deletion marks the archive link unlinked rather than deleting archived bytes.

No runtime success is asserted from this source change. Required local acceptance remains: lint all changed PHP files; run the reviewed existing-record backfill with `--apply`; rerun dry-run to confirm idempotence; browser-test authorized and unauthorized search/download; verify upload creates archive rows and replacement increments version while preserving the prior file. The known three missing payment-evidence paths are test artifacts and must not be reconstructed.


### Archive follow-up — version-aware search and retrieval (2026-10-23; runtime pending)

The integrated Global Search now enumerates each present version associated with an active project source link, distinguishing current from prior versions. A selected version is served by `modules/projects/project_archive_version.php`, which re-checks the source adapter, project access, payment-receipt role allowlist, canonical storage containment, and byte-size/SHA-256 integrity. This is a source-level implementation checkpoint only; local lint and authorized/unauthorized browser verification remain open.


### Archive follow-up — unlinked archive entries remain discoverable (2026-10-23; runtime pending)

Search now includes present archived versions for explicitly unlinked source records when their business row has been deleted. The project ID is recovered from archive event metadata, and project-level permission checks remain mandatory. This keeps retained evidence discoverable without treating archive metadata as an authorization grant. Local lint and runtime verification remain open.

 
### Archive pilot — evidence from local syntax/backfill runs (2026-10-23)

User verified `php -l` PASS for search permissions, Global Search, archive helper, archive-version endpoint, project profile and FM project page. The backfill `--apply` registered 16 inspected references; the subsequent dry run classified all 16 as already registered with no path or blob metadata mismatches and no reconciliation items. Three payment evidence source paths remain physically missing and are accurately represented as missing. This does not establish end-to-end runtime acceptance; browser authorization/search/upload/version tests are still required.


## 2026-10-23 — Document archive pilot — evidence, limitations, and scope boundary

The original pilot scope is limited to project documents (`project_documents`) and project payment evidence (`project_payment_evidence`), integrated into the existing role-based Global Search Center. Source implementation and one-time metadata registration are complete for this scope, but the overall feature is **not fully acceptance-closed** because real Apache/browser verification has not been reported.

### Verified evidence
- User-reported PHP syntax checks passed for all six changed files: `config/search_permissions.php`, `modules/search/index.php`, `modules/projects/lib_project_document_archive.php`, `modules/projects/project_archive_version.php`, `modules/projects/view.php`, and `modules/projects/view_fm.php`.
- Against local `ahl_el_kheir`, the backfill registered 16 references (8 project documents and 8 project payment-evidence references). The subsequent dry run found all 16 already registered, with zero planned registrations, path mismatches, blob metadata mismatches, or reconciliation cases. This verifies idempotent catalogue registration for the inspected references only.
- Three existing project payment-evidence references point to missing physical test-artifact files: evidence ID 4/project 7, ID 1/project 8, and ID 3/project 8. They must remain recorded as missing; do not recreate files or claim they can be retrieved.
- These results do not prove browser search, download, authorization boundaries, automatic registration during live uploads, or replacement-version retrieval.

### Scope clarification: nanny/family sponsorship payment proofs
The user clarified that the receipts they expected to see are uploaded by a nanny as proof that she paid families or delivered monthly sponsorship to families. These are **not** the project payment-evidence records covered by the pilot. Search Center's empty archive result for that nanny does not prove those files are missing; the actual family-payment workflow has not yet been traced or integrated. Do not add the nanny role to the project-payment-evidence allowlist as a workaround. Any future extension must inspect the real upload handler, schema/path columns, serving route, and family-level permission rules, then integrate automatic registration and a safe backfill into the existing Global Search Center.

### Disposition / next acceptance gate
- Mark the **project archive implementation + existing-reference backfill phase** complete, with the above evidence and limitations.
- Keep **end-to-end acceptance** open until the user tests the existing Global Search in `http://localhost:8081/AhlElKheir/`: find a known present project document and receipt, open current and previous versions after a replacement, and confirm an unauthorized cross-project request is denied. Do not use the three missing files as test fixtures.
- Treat nanny/family sponsorship payment proofs as an explicit scope extension, not as a defect that can be solved by bypassing current project receipt permissions.
- Do not claim the entire document-archiving system is done. At the start of the next repository task, reread the canonical docs and inspect the real repository state before proceeding.


## 2026-10-23 — Sponsor QR and Android scanner (source checkpoint; acceptance open)

The user requested a QR code for every sponsor, shown on the sponsor profile and on orphan forms linked to that sponsor, plus an installable Android APK for staff scanning. The user selected authorized access (Option B): QR scanning must not bypass existing authentication, role checks, or supervisor sponsor scope.

Repository source now contains a local QR helper based on the bundled TCPDF 2D barcode implementation. QR payloads encode only the existing `modules/sponsors/view.php?id={id}` URL, not sponsor contact fields. Sponsor profile source adds a distinct active sponsored-child count using the established `sponsorships.child_id` relation and `status='active'`. The orphan form/profile source adds a QR beside each linked sponsor.

A companion Android project at `android/sponsor-qr-scanner/` uses ZXing Android Embedded for camera scanning and loads the existing sponsor profile in a WebView. It stores a configurable server base URL, validates scanned sponsor URLs against that configured origin/path, and delegates login and authorization to the existing PHP application. Debug build configuration permits controlled local HTTP testing for private/local hosts; release configuration requires HTTPS. GitHub Actions workflow `.github/workflows/build-sponsor-qr-scanner.yml` is intended to produce a debug-signed test APK artifact.

**Acceptance remains open.** The repository source has not yet been reported as PHP-linted; Android compilation, APK installation, physical camera scan, sponsor count/database reconciliation, printer scannability, and role/scope denial tests remain unverified. Continue from `docs/SPONSOR_QR_ANDROID_CONTINUATION.md`. No database schema change is currently justified. This task does not close or alter the separately pending project archive browser acceptance.


## 2026-10-23 — Sponsor QR Android scanner build finding

GitHub Actions job `120438823455` (run `39961045578`) identified duplicate classes between `kotlin-stdlib:1.8.22` and legacy `kotlin-stdlib-jdk7/jdk8:1.6.21`. Dependency insight traced the older split artifacts through AndroidX lifecycle / `kotlinx-coroutines-android:1.6.4`; this is not evidence of an AppCompat or ZXing class conflict. Main commit `777f9b180ce61dade8c96649a8138e7f4bb85880` excludes only the obsolete split modules. Await CI verification before marking the APK build successful; PHP, Apache/browser, database-count, authorization, and device acceptance are still pending.


## 2026-10-23 — Android scanner BuildConfig generation
Actions job `120446032500` failed at `:app:compileDebugJavaWithJavac` with unresolved `BuildConfig` references in `MainActivity.java`. The inspected module declares Android Gradle Plugin `8.7.3`; AGP 8 requires BuildConfig generation to be explicitly enabled when the generated class is used. Commit `ecdc34c19f1d5f5c370a95b3a902739693710b7f` adds `buildFeatures { buildConfig true }` to the app module. This is a source fix pending CI verification, not a verified successful APK build.


## 2026-10-23 — Sponsor QR host diagnosis and portrait scanner change

User confirmed that opening the existing system through the laptop LAN address and scanning the newly generated sponsor QR successfully opens the sponsor profile. The previously reported “رمز غير صالح” was caused by generating the QR while the website was opened at `localhost`; that URL points to the requesting device and does not match the phone scanner's configured laptop LAN host. No origin-validation bypass was added. For physical-phone testing, generate QR codes while browsing the site using the same LAN host/port/application path configured in the APK.

Portrait change committed to main:
- `android/sponsor-qr-scanner/app/src/main/AndroidManifest.xml`: lock `.MainActivity` to portrait.
- `android/sponsor-qr-scanner/app/src/main/java/org/ahlelkhier/qrscanner/MainActivity.java`: set ZXing `setOrientationLocked(true)`, so the capture screen follows the portrait-locked activity.
- `android/sponsor-qr-scanner/README.md`: document portrait behavior and the matching-host requirement.

Source changes are committed, but the updated APK and physical-device portrait behavior are not yet verified. The Android workflow should build a new debug APK from these commits. After it succeeds, install that artifact and verify scanning while holding the phone upright. Keep existing website authentication, sponsor permissions, supervisor scope checks, and same-origin validation unchanged.


### 2026-10-23 — Portrait scanner APK build verified

GitHub Actions run [39990143104](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/39990143104) completed successfully on source commit `ea50c4f4cc24ad287381967d6c1a2c7e45a4ffd4`. PHP QR integration lint, Android duplicate-class check, Java compilation, debug APK assembly, and artifact upload all passed. Artifact: `ahl-el-kheir-sponsor-qr-scanner-debug-apk`, artifact ID `12517556746`, SHA-256 `46453215f819c7cbfb1a8bb8fd091cddde533488cd2b652061f1c2e8314b2980`, expires 2026-11-22.

Remaining acceptance gate: install this newly built APK on the user's phone and confirm the scanner stays portrait while scanning, then scan a QR generated from the same LAN base URL and verify sponsor page opening. CI build success does not prove physical-device orientation behavior.


## 2026-10-23 — Sponsor QR scanner presentation and APK distribution

Evidence update: the user confirmed scanning succeeds after generating the QR from the laptop LAN URL instead of localhost. No QR-origin validation was weakened.

Source changes on main:
- Android UI now has a cleaner Arabic header, a primary scan action, and an APK download action; permanent server-settings button removed. Long-press the title to access server configuration.
- New authenticated endpoint modules/sponsors/download_scanner_apk.php serves the fixed path storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk; no user-controlled path is accepted, and direct storage access remains denied.
- Android build workflow now lints the endpoint and runs when it changes.

Not yet proven: new CI run, installation of the newly built APK, physical portrait behavior, and end-to-end authenticated APK download. The actual APK must be deployed to the fixed storage path on the target server before downloads can succeed.


## 2026-10-23 — Sponsor QR scanner: portrait build correction and minimal home UI

The user confirmed that APK download and installation work, but the scanner camera still opens horizontally. CI run [40001005068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001005068) provided the root cause: ZXing's library manifest sets its separate `CaptureActivity` to `sensorLandscape`, and the app's explicit `portrait` setting caused a manifest merge failure. The Android manifest now uses `tools:replace="android:screenOrientation"` on that activity to override the dependency's value. A fresh successful build and physical-phone retest are still required.

The main Android screen has been reduced to the **مسح رمز QR للكفيل** action plus the WebView required for existing website login and sponsor pages. The branding/download header and connection-status strip are removed. Long-press the scan button to change the configured server URL. The authenticated website header continues to provide the APK download link.

Do not mark portrait fixed until a fresh APK build succeeds and the user verifies portrait orientation on the physical phone. Keep sponsor URL validation, login, permissions, and supervisor scope unchanged.


## 2026-10-23 — Corrected sponsor scanner APK build verified; physical-device acceptance pending

GitHub Actions run [40001446199](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001446199) completed successfully on commit `29eee35eb4fc3bf9869913abcd4395fea77a93e9`. The manifest merge, Java compilation, debug APK assembly, and artifact upload passed after the ZXing capture activity portrait override was changed to `tools:replace="android:screenOrientation"`. Artifact ID `12522116796`, name `ahl-el-kheir-sponsor-qr-scanner-debug-apk`, expires 2026-11-22; artifact ZIP SHA-256 `2b4eb5b801662bb05c1791ba0d0c1a5081822506c73c05a554bd55713c26ad1a`.

The home UI now has only the **مسح رمز QR للكفيل** native action and the required WebView; the extra branding/download header and connection-status strip are removed. Long-press the scan action to change server settings. The website's authenticated header remains available for downloading the APK.

The user must still install this fresh artifact and verify physical portrait scanning and sponsor-profile opening. Do not describe device orientation as verified until the user confirms it.


## 2026-10-23 — Sponsor QR scanner deployment closure and orphan-form QR layout

The user confirmed the updated Android scanner APK was deployed to the existing fixed download path and closed the scanner task. Preserve the authenticated download endpoint and fixed filename; no alternate APK-serving route is introduced.

A separate orphan-form presentation change was committed to `modules/families/orphan_form.php` (`1e29a29b85b4bf4ac0da2cd7aff226019a71cff2`): sponsor QR code(s) are now rendered in the upper-left header region, on the same horizontal header line as the organization logo and form title, instead of inside sponsorship rows. Sponsor identity/code and sponsorship dates remain in the table. PHP syntax passed in GitHub Actions run [40003543066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40003543066), job `php-lint`, including `modules/families/orphan_form.php`. Browser/print verification remains pending: inspect the form in the local Apache application and print/PDF preview, including a form with no sponsor and one with multiple active/paused sponsorships if available. Do not change unrelated form behavior or treat source commit alone as runtime acceptance.


### 2026-10-23 correction — restore original orphan form header

The first QR header implementation introduced flex sizing, a 27mm minimum row height, and horizontal padding, which changed the original design. Corrective source commit `efe2eaf9b6aeaa1d25b7f136f9b9958f74e519a4` removes those changes and restores the original `.ak-band-row{position:relative}` rule. QR remains at the upper-left; logo/title markup and other form styling remain unchanged. Syntax and browser/print verification are pending.

### 2026-10-23 follow-up — full-width sponsorship rows

Source commit `d31c1fd6c56f25ecca15adb5833131b425264935` adds `colspan="3"` to the value cells for “مدة الكفالة” and “قيمة الكفالة”. Those rows share a table with four-column sponsor rows; spanning the remaining columns makes both row borders extend across the same full table width without changing their content or the QR header. PHP syntax and local browser/print appearance verification are still pending.


## 2026-10-24 — Mobile Flutter CI verification boundary

The Flutter app is configured for cloud compilation through GitHub Actions. The build workflow performs Dart analysis, a login-screen widget smoke test, and Android debug APK compilation, with a downloadable artifact retained for 30 days. These checks establish source/build evidence only after a successful run; they do not establish that the app can authenticate against XAMPP or a hosted server. The API migration remains unapplied, local API runtime tests remain pending, and login throttling/security review is required before public exposure. Debug HTTP support is scoped to the generated debug manifest; production API traffic must use HTTPS.


## 2026-10-24 — Flutter widget test failure evidence

GitHub Actions run 40060794073 completed dependency resolution and Dart analysis successfully, then failed at the Arabic login text assertion in login_screen_test.dart. The test launched the whole app, which begins asynchronous secure-storage session restoration, so the test was not isolated to the login form. The smallest correction was test-only: mount LoginPage directly with RTL directionality. Commit: b3216456948e71a80ae2629961efc78352769edf. The follow-up run must prove the test and APK build.


## 2026-10-24 — Flutter physical-device connection and brand alignment
Finding: the first APK defaulted to `10.0.2.2:8081`, which is an Android-emulator host, not the user's physical handset. User confirmed local laptop host `192.168.18.9`, Apache port `881`. Source now targets that LAN URL and uses the existing website logo and navy design tokens. This is a source-level correction only; CI, handset connectivity, API migration, and runtime authentication remain unverified.


## 2026-10-24 — Flutter theme CI analyzer finding
Run `40062960757` reached `flutter analyze` and failed only on four `deprecated_member_use` infos for newly introduced `Color.withOpacity` calls (Flutter 3.47); build/test steps were skipped. Replaced the calls with `withValues(alpha: ...)` in commit `6dbb17b7f4276eeeaad2e3f6c8c267d939ef35f6`. Await the next run for actual verification; API reachability and migration/runtime remain unverified.


## 2026-10-24 — Flutter login request never reaches Apache
The user retried login with the physical-phone APK and the latest Apache access-log tail still contained no `POST /AhlElKheir/api/v1/auth/login.php`; only the earlier browser `GET` (expected HTTP 405) was present. Source inspection found that `.github/workflows/build-flutter-mobile-app.yml` overwrote Flutter's generated debug `AndroidManifest.xml` with `usesCleartextTraffic="true"` but omitted `android.permission.INTERNET`. The debug manifest generation was corrected on `main` in commit `99a37d69bc19edaea42c28c9807a6560ac1bb8af` to include both the INTERNET permission and the cleartext setting. This is the identified source-level cause consistent with the absence of requests in Apache logs; verification is pending. Next: confirm the GitHub Actions run triggered by that commit passes and publishes a new `ahl-el-kheir-flutter-android-debug-apk`; install that new artifact, attempt login once, and check for a new Apache POST entry. Do not reuse the earlier artifact or claim device login fixed before that physical-device check. API migration is now confirmed applied by the user to `ahl_el_kheir`; continue to keep API/auth runtime acceptance separate from CI build success.


## 2026-10-24 — Flutter mobile notification inbox (implementation checkpoint)

- Physical-phone authentication has been confirmed by the user: deliberately invalid credentials fail and the user's real credentials sign in successfully against the local XAMPP API.
- The first business-data slice is the shared notification inbox. New authenticated routes: `GET /api/v1/notifications/index.php?page=1` and `POST /api/v1/notifications/mark_read.php` with JSON `notification_id`.
- Both routes derive the recipient from the authenticated bearer session. The query/update are scoped to `notifications.recipient_user_id = current user id`; the mobile client cannot select another recipient. No schema change or migration was added.
- Flutter's signed-in screen now links to an Arabic-first notification inbox with English support, unread/read presentation, pull-to-refresh, paging, and mark-one-read behavior. Added a widget test for the notification inbox.
- Verification boundary: source changes are committed to `main`; the latest Flutter GitHub Actions run must be checked for analyzer/test/APK status. PHP syntax and authenticated API runtime checks against the local XAMPP database remain pending; do not claim this module is runtime-verified until those checks pass.
- Next: inspect the exact CI run, then locally lint the two new PHP endpoints and test own-user listing/mark-read plus cross-user isolation using existing accounts/notifications. Do not create test notifications or modify business data unnecessarily. Continue by selecting the next real business module only after mapping its existing source authorization and schema.


## 2026-10-24 — Mobile project portfolio implementation checkpoint

- Added authenticated read-only endpoint `GET /api/v1/projects/index.php?page=1` and an Arabic-first/English Flutter project portfolio screen.
- The endpoint is limited to the same portfolio roles allowed by `modules/projects/index.php`. Project visibility follows the existing project-domain rules inspected in `modules/projects/project_lib.php`: global financial/executive visibility, PM creator/pre-handoff and closure-request rules, and the assigned project-supervisor launch/scope checks. The mobile client role check only controls whether the entry point is shown; server-side authorization remains authoritative.
- Portfolio data uses the existing project, approval, lifecycle, posted funding/expense, beneficiary, and active supervisor records. No schema changes or runtime DDL were introduced.
- Added a Flutter widget test for Arabic project portfolio rendering and added the endpoint to the PHP 8.2 syntax-check workflow.
- **Verification boundary at this checkpoint:** GitHub Actions runs for the new Flutter page/test and the PHP endpoint are in progress. Do not call the feature CI-verified until those runs finish. The endpoint has not yet been exercised against the local Apache/MariaDB database or tested with different role accounts. No physical-device test is requested at this stage.
- Next: inspect the actual Actions results; fix only concrete failures; then add the next project workflow slice after reviewing the existing project detail endpoint and its role/scope rules. Keep broader mobile modules queued until the project read path is verified.


## 2026-10-24 — Mobile project details slice

- Added `GET /api/v1/projects/detail.php?id=<project_id>`, using the existing `other_projects`, project approval/lifecycle, posted funding/expense, team assignment, milestone and status-history records. It is read-only and makes no schema changes.
- The endpoint checks the live bearer identity and role, then enforces project visibility for the executive/financial roles, the Projects Manager's existing creator/pre-handoff and closure-request rules, and the Project Supervisor's final-approval/launch plus active assignment scope. Client navigation is not treated as authorization.
- Added an Arabic-first/English project detail screen reachable from the portfolio, with status/approval, financial summary, active team, milestones and recent history. Added a widget test for the Arabic detail view.
- The PHP lint workflow now includes both project API endpoints.
- **Verification boundary:** The selected PHP files passed lint on commit `7e5f13b9f2e35833ca6dcdf3eb2b8bc47608ac23`, but that run did not yet include `api/v1/projects/detail.php`. A subsequent PHP lint workflow with the detail endpoint added is queued/running. The Flutter workflow for the detail screen and widget test was still running at the time of this note. No local Apache/MariaDB API runtime test or role-account matrix has been run, so this feature is not yet runtime-accepted.
- Next: inspect the latest Flutter Actions result and fix only concrete failures. Then continue the mobile project read workflow with the next useful, role-safe slice; do not ask the user to test until the broader app reaches the agreed release-readiness gate.


## 2026-10-24 — Mobile HR attendance register

- Added read-only `GET /api/v1/hr/attendance.php`, restricted server-side to `hr_manager`, `hr_staff`, and `admin`. It uses the existing effective employment-state roster and attendance/approved-leave records; it does not write attendance or mutate leave-return records.
- Missing attendance remains `missing` in the API and UI, never inferred as absence. Approved leave is shown as leave unless a persisted or legacy same-day return marker exists.
- Added an Arabic-first/English attendance register with date selection, refresh, employee/department rows, check-in/out display, and summary counts. Added a widget test specifically preserving the missing-vs-absent distinction.
- The PHP lint workflow now includes the attendance endpoint.
- **Verification boundary:** Flutter CI exposed and we corrected a widget-test visibility issue in the project detail test; the latest build covering that correction and the attendance screen is running. PHP lint for the new attendance endpoint is queued/running. No local API runtime test or HR-role account matrix has been performed.
- Next: close the latest Flutter/PHP CI checks, then continue with role-safe HR leave-request read views and subsequent modules using the same source-first approach.


## 2026-10-24 — Mobile leave-request views

- Added read-only `GET /api/v1/hr/leaves.php?view=mine` for an employee's own leave history, resolving the employee through the existing `hrGetEmployeeForUser()` identity helper. The endpoint returns an empty list if no unambiguous employee profile is linked.
- Added the authorized review queue: HR Manager/HR Staff see non-HR employees' requests; General Manager (including the existing `gm` compatibility role) sees HR employees' requests. Role and view are checked server-side; the mobile role controls only the available navigation.
- Added Arabic-first/English leave list UI, status filters and paging. This is a read-only slice; request submission, approval and rejection are not exposed by this endpoint and must be implemented separately against the existing workflow rules.
- PHP lint passed for the new HR attendance and leave endpoints in GitHub Actions run `40085047414`. The mobile build exposed two widget-test assumptions (an off-screen attendance row and an HR review-queue test that had not switched from “my requests”); the tests were corrected on commit `638130e98af2fdd237e93a042bfad37f8c5f650c`. Its Flutter run is pending/in progress.
- No local API runtime test or role-account matrix has been performed. Do not mark the mobile HR views runtime-accepted yet.
- Next: confirm the current Flutter CI run, then implement leave request submission and role-authorized approval/rejection as separate validated API actions before moving to the next domain.


## 2026-10-24 — Mobile leave-request workflow actions

- Added authenticated `POST /api/v1/hr/leaves/action.php` for submitting leave requests and approving/rejecting pending requests. It preserves the existing role split: HR approves non-HR requests; General Manager approves HR employees' requests; rejection is limited to the same role-owned queue.
- Request types, inclusive day calculation, strict date format, duplicate pending-request check, final `hr_approved` status, approver columns, attendance-row creation, and post-action notifications follow the existing `modules/hr/leaves.php` workflow. Notifications are isolated so a notification insert failure does not undo a committed leave decision.
- The Flutter leave page now supports creating requests and the authorized review queue's approve/reject actions. Server-side authorization remains authoritative.
- PHP lint passed for the new action endpoint in GitHub Actions run associated with commit `b3aff6ab25320b43fa033dcc38a3694d3097efc5`. The Flutter analysis/tests/APK build for this combined change are still pending. No live-database request/approval runtime test has been performed.
- This supersedes the prior checkpoint statement that mobile leave requests were read-only: the list endpoint remains read-only, and the separate action endpoint now handles writes.


## 2026-10-24 — Mobile application audit checkpoint
The existing Flutter client is being expanded without replacing the procedural PHP system. Server-side authorization and record scopes remain authoritative. The transaction-register CI failure was an 8-pixel trailing-row RenderFlex overflow; commit `885ef809b431b174541d54c8c44ade3f97421172` moves the amount to the subtitle and leaves only the status chip trailing. Verify the matching Flutter CI run before marking this correction passed. Live API/database runtime testing remains separate from CI.


## 2026-10-24 — Mobile transaction build passed; sponsorship work in progress
The transaction register row-layout correction passed the full Flutter CI workflow at commit `885ef809b431b174541d54c8c44ade3f97421172`; the debug APK artifact was verified present and unexpired. A read-only sponsorship register was then added using the existing web module's role allowlist and supervisor scope helper. Its newer CI workflow remains pending. Live API/database and per-role runtime checks are not implied by CI.


## 2026-10-24 — Sponsor request register CI verified
- Flutter workflow run `40093088822` for app integration commit `e3ad346c1267736b4e725805e0c877e524e8f2ac` completed successfully: dependency resolution, Dart analysis, widget tests, debug APK build, and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` (artifact id `12559303403`) was confirmed present and unexpired at verification time.
- PHP verification run `40093091580` completed with the `php-lint` job passing, including the new `api/v1/sponsor_requests/index.php` endpoint. The independent Stage 5 payroll regression job failed at its existing acceptance check with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; the rescheduling, HR migration and MariaDB 10.4 jobs passed. This is not evidence of a mobile endpoint failure and does not reopen the closed salary-advance work.
- Sponsor request screen now has CI evidence. API runtime against local Apache/MariaDB, per-role authorization and phone UI checks remain unverified. Continue building the remaining app on GitHub; do not request device testing yet.


## 2026-10-24 — Mobile profile completeness and transaction date filtering
- Expanded the existing family detail API/client with profile fields already displayed by `modules/families/view.php`: alternate phone, registration number, city, address, mother's marital status, father's name/death details, monthly need and notes. Existing family authorization and supervisor/nanny scope checks remain unchanged.
- Expanded the existing sponsor detail API/client with fields already displayed by `modules/sponsors/view.php`: email, address, sponsor type and preferred payment method. Existing sponsor role allowlist and supervisor scope check remain unchanged.
- Added start/end date controls to the mobile transaction register and connected them to the existing `api/v1/transactions/index.php` `from`/`to` filters. Client rejects an inverted date range; clear-dates restores an unbounded range. No schema or workflow mutations were introduced.
- The expanded-profile Flutter run `40093704453` has passed Dart analysis and all widget tests; debug APK build/artifact publication is still pending at this documentation checkpoint. The family detail test was updated to scroll to the children and sponsorship sections after the expanded profile moved them beyond the initial viewport.
- PHP 8.2 lint passed in run `40093671534` for the family/sponsor endpoint changes. The separate `migration-full-schema` job continues to fail only at the closed Stage 5 payroll regression step with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; do not attribute that failure to mobile code or reopen closed salary-advance work.
- API runtime on local Apache/MariaDB, per-role live account matrix, and physical-device acceptance remain outstanding. Continue app development and CI verification before requesting device testing.

## 2026-10-24 — Mobile HR employee directory implementation
- Added authenticated read-only `GET /api/v1/hr/employees.php?q=&page=1`, restricted server-side to the exact `hr_manager`, `hr_staff`, and `admin` roles allowed by `modules/hr/employees.php`.
- The endpoint uses existing `employees`, `departments`, and `hr_employment_states` fields, with name/employee-code/position search and 20-row pagination. It intentionally omits national ID, personal contact/address details, salary, bank data, and employee-editing actions.
- Added the Arabic-first/English Flutter employee directory with search, refresh, pagination, department, position, hire date, and employment state. The entry point is shown only for the same HR/admin roles; the API independently enforces authorization.
- Added a widget test and included the API route in the PHP 8.2 lint workflow. No database migration, schema change, or runtime DDL was added.
- CI verification for this slice is pending until the Flutter analyzer/tests/APK artifact and PHP lint job finish. Live Apache/MariaDB API checks and role-matrix runtime verification remain outstanding; do not request device testing yet.

## 2026-10-24 — Mobile employee directory CI confirmation and leave-request guard
- Correction to the prior checkpoint: Flutter run [40094355923](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40094355923) completed successfully, including Dart analysis, widget tests, Android debug APK build, and artifact publication. Artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12560083920`, 77,209,735 bytes) was confirmed unexpired. PHP lint passed in run [40094355939](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40094355939).
- New follow-up: the existing leave API explicitly returns `employee_linked=false` when the authenticated user has no linked employee profile. The mobile leave screen previously ignored this signal and still displayed the request action, which would lead to a predictable server rejection. It now hides that action and displays an Arabic/English explanation for unlinked accounts.
- Added a widget test for the unlinked-account state. Current verification for this follow-up is running in Flutter [40095047706](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095047706) and PHP workflow [40095047697](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095047697); do not mark the follow-up verified until the checks finish.
- The independent `migration-full-schema` CI job still reports the known Stage 5 payroll acceptance failure; it remains unrelated to mobile changes and must not reopen the closed payroll work. Live Apache/MariaDB API role checks and device acceptance remain outstanding.

## 2026-10-24 — Leave-request guard follow-up verification checkpoint
- The first Flutter attempt `40095047706` failed at Dart analysis because the newly added widget test had been placed outside `main()`. The failure was confirmed from the job log; the test was moved into `main()` in commit `76b83f7b83c96ce03e9853b7d47e8f00e91c3609`.
- On rerun [40095123841](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123841), Dart analysis and all widget tests have passed. Android debug APK build is still in progress and no artifact has been published for this revision at this checkpoint.
- PHP lint passed in [40095123850](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123850). The separate full-schema job is still running at this checkpoint; continue to treat any known Stage 5 payroll acceptance failure as unrelated to the mobile work.
- The employee-link guard is based on the existing API's explicit `employee_linked=false` response; no database or approval behavior was changed. Live Apache/MariaDB API role checks and device acceptance remain outstanding.


## 2026-10-24 — Leave-request guard CI verified
- Flutter workflow [40095123841](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123841) completed successfully on `main`: Dart analysis, all widget tests, Android debug APK build, and artifact publication passed. APK artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12560951078`, 77,209,480 bytes) is present and unexpired.
- PHP workflow [40095123850](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123850): the `php-lint` job passed, as did the HR migration, MariaDB 10.4 migration and rescheduling jobs. The overall workflow remains failed only because its separate `migration-full-schema` job failed at the known Stage 5 payroll regression/account 1410 reconciliation step; do not attribute this to mobile work or reopen the closed payroll feature without new regression evidence.
- The leave-request guard is now CI-verified: when the existing API reports `employee_linked=false`, the mobile page hides the leave-request action and explains that HR must link an employee profile. The added widget test passes. No leave business rules or database schema were changed.
- Verification boundary remains explicit: no live Apache/MariaDB API role matrix or physical-device acceptance has been performed. Continue implementing and testing the broader app on GitHub before requesting device testing.


## 2026-10-24 — Mobile project progress updates verified
- Project detail API `api/v1/projects/detail.php` now includes up to 10 existing `project_progress_updates` rows, ordered newest first, with update date, completion percentage, summary, achievements, issues, next steps and submitter name. This is read-only and adds no schema changes or workflow actions.
- `mobile/ahl_el_kheir_app/lib/project_detail_page.dart` displays these records in an Arabic-first/English section; `test/project_detail_page_test.dart` covers the rendered content. Test scroll ordering was corrected after the first CI run exposed an off-screen assertion; production behavior was not altered to satisfy the test.
- Successful Flutter workflow [40097366358](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40097366358), commit `7ea27b76d59af2fda7451e0d9278cac895367b89`: Dart analysis, full Flutter widget tests, Android debug APK build and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12560489959`, 77,209,301 bytes, is present and unexpired.
- PHP lint passed in the paired workflow's `php-lint` job. Its separate `migration-full-schema` job remains failed at the known Stage 5 payroll acceptance/reconciliation issue; do not reopen the closed payroll feature as part of mobile work.
- Live Apache/MariaDB API role checks and physical-device acceptance have not been performed. Continue mobile development and GitHub CI before asking the user to test a device.


## 2026-10-24 — Project portfolio search improvement checkpoint
- The project list previously filtered only the currently loaded page on-device, so results on other pages could be missed. `api/v1/projects/index.php` now accepts optional `q` and filters project code/name after applying the existing role-specific visibility checks and before pagination; totals/pages reflect the filtered authorized result set.
- `projects_page.dart` sends the search term to the existing API with a 350 ms debounce and generation checks to prevent stale in-flight responses from overwriting newer search results. `main.dart` URL-encodes the query. The widget test now asserts that the search term reaches the page loader.
- Commits: API `011a96d6a3bf4eedc0440ac9393203b43bcad169`; Flutter debounce `461b46c97cf962d5116ca3d01704d342791b83c3`; API call wiring `d0ad325cc0e654c21ca94d58f58e50c7511effb3`; widget test `184d3e3c45cbaf06810a717c2ed4cbfa55abee6a`.
- Latest complete-revision Flutter workflow [40098149592](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40098149592): Dart analysis passed and all Flutter widget tests passed; Android debug APK build is still in progress at this checkpoint. Do not mark this search change fully verified or claim a new APK artifact until the run finishes successfully.
- PHP lint passed in the paired workflow's `php-lint` job. Its independent full-schema migration job is not evidence against this API change; the known Stage 5 payroll acceptance failure remains out of scope. Live Apache/MariaDB API role checks and physical-device acceptance remain outstanding.


## 2026-10-24 — Project portfolio search CI confirmation
- Follow-up polling confirmed workflow [40098149592](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40098149592) completed successfully at 2026-10-24T13:30:33Z. The complete Flutter job passed and published unexpired Android debug APK artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12561936059`, size 77,206,559 bytes.
- The portfolio search change is now fully CI-verified: Dart analysis, Flutter tests, Android debug APK build and artifact publication passed; paired PHP lint passed. Live Apache/MariaDB API role testing and physical-device acceptance remain outstanding.


## 2026-10-24 — Notification inbox failure-path coverage added (CI pending)
- Commit [e05824931378d4d8ac16712aa6c1005bb757fdae](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/e05824931378d4d8ac16712aa6c1005bb757fdae) adds Flutter widget coverage for two notification-inbox behaviors: a failed mark-as-read request displays the error without reloading the inbox, and tapping an already-read item does not send a mark-as-read request.
- This extends the prior unread-item test; production code and API behavior were not changed.
- Verification boundary: GitHub's available commit-status and commit-associated workflow lookups returned no status records/runs for this push. The new tests are **not yet CI-verified**. Do not claim analyzer, widget tests, APK build, or artifact publication passed until a real workflow result is available.
- Next: obtain actual Flutter workflow evidence for this commit (analyzer, complete widget suite, Android debug APK build, artifact publication). Keep live Apache/MariaDB role tests and device acceptance deferred. Do not touch closed payroll work.


## 2026-10-24 — Mobile sponsorship role allowlist aligned with web module

- Source comparison found a concrete permission mismatch: `modules/sponsorships/index.php` allows `admin`, `vice_general_manager`, `supervisor`, and `accountant`; the mobile API and home-screen entry had additionally exposed the module to `general_manager` while excluding no other web-authorized role.
- Corrected `api/v1/sponsorships/index.php` to enforce the same four-role allowlist, and corrected the Flutter home-screen entry to match. Server-side API authorization remains authoritative; no business data, schema, or workflow was changed.
- Commits: API `350d18f1f5307ef1c273630489be321a5066f505`; Flutter navigation `39b65a7d887376e7fa451692483621bc718be5b8`.
- Verification boundary: source comparison justifies the change, but PHP lint, Flutter analysis/tests/APK build, and live role-account runtime checks have not been confirmed for these commits. Do not claim the change is verified until actual CI evidence is available. No device testing requested yet.


## 2026-10-24 — Sponsorship detail API role parity follow-up

- Commit [bc39b23fdd036298f64ed80a3b09e2bb906c9653](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/bc39b23fdd036298f64ed80a3b09e2bb906c9653) aligned `api/v1/sponsorships/detail.php` with the sponsorship list API allowlist: `admin`, `vice_general_manager`, `supervisor`, and `accountant`; the supervisor responsibility-scope guard remains.
- Current `main` source was re-fetched and confirms the four-role allowlist. This is source verification only; PHP lint, Flutter analysis/tests/APK build, and live API role checks are still unverified for these latest commits. Do not request device testing yet.
- Audit note: `api/v1/sponsors/detail.php` retains its broader role set because it matches `modules/sponsors/view.php`; no change was justified there.


## 2026-10-24 — Mobile sponsor-request attribution correction (CI pending)
- Commits: Flutter card attribution `4999c0f4b82ecb50e72203d990488840486dabd1`; widget coverage `7f4f8f2159abe1e4f86d6864d75c75f5fb6587d4`; API attribution search `864608962c4ed3f9ac3ae2a1e86711f247170413`.
- Flutter sponsor-request cards now display `brought_by_name`, falling back to `brought_by_user`; widget test checks the preferred name wins over the fallback.
- API search now checks sponsor name, phone, source, `r.brought_by_name`, and joined `users.full_name`. The count query uses the same LEFT JOIN as the list query; five search placeholders have five matching parameters. No schema change was made.
- Verification boundary: source changes are committed on `main`; GitHub commit status currently reports no status records for the API commit, and the Flutter workflow has not yet been confirmed. Do not claim PHP lint, Flutter analysis/tests, Android APK build, or artifact publication passed until actual CI evidence is available. Live Apache/MariaDB acceptance and device testing remain deferred.


## 2026-10-24 — Sponsor-request attribution fix CI verified
- Flutter workflow [40106182906](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106182906) completed successfully at commit `91655d9cf8a1232eb30cad4786290a276270cb5b`. Dart analysis, the full widget suite, Android debug APK build and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12564862356`, 77,208,880 bytes) is present and unexpired; SHA-256: `eab6bf7c5c9e833ce05fff19f3720913c3de41cadcedfb912fe64b093a1e514d`.
- Paired PHP workflow [40106183059](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106183059): PHP lint, HR job-description migrations, MariaDB 10.4 migration, and rescheduling-service tests passed. The separate full-schema Stage 5 payroll acceptance job failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; track it under deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), do not attribute it to the mobile change or reopen closed payroll work without root-cause evidence.
- Sponsor-request attribution UI/search change and preferred-name/fallback widget coverage are CI-verified. API live Apache/MariaDB role testing, and physical-device acceptance, remain outstanding. Continue the broader mobile app on GitHub; do not request phone testing yet.


## 2026-10-24 — Mobile sponsor-request status actions added (verification pending)
- Added authenticated `POST /api/v1/sponsor_requests/action.php` for the exact non-destructive status transitions already supported by `modules/sponsors/requests.php`: `new → contacted`, `new/contacted → lost`, and `lost → contacted`. The endpoint enforces the same six-role allowlist as the existing sponsor-request register, locks/rechecks the current status in a transaction, and rejects invalid/stale transitions. It does not expose delete or sponsor conversion; no schema changes or runtime DDL were added.
- Flutter sponsor-request cards now provide confirmation-protected actions for those transitions, local Arabic/English success feedback, and search hint text that includes referrer attribution. Added widget coverage for confirming the contacted transition and reloading the updated state. PHP lint workflow now includes the action endpoint.
- Source commits: API `7614893bf51c7fab7f5938c41356a0c833352113`; app wiring `0b8b71de2fc83c0d013cba54e40c4ae4eadf3dd9`; UI actions `40457577937138bab9116695a3878e5c33ec6594`; widget test `7d83fe6719d188bff84c93cdbcc4503e831f939c`; localization/hint `013c01042794c62a8f4a96c8d8c8dd268894739b`; analyzer correction `9240efbbae4969e66188f2d747b8854932d99dcc`.
- Verification: PHP lint passed in [workflow 40107450497](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450497). Flutter analyzer and widget tests passed in [workflow 40107450516](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450516); Android debug APK build/artifact publication were still running at the last check, so do not mark the slice fully CI-verified until confirmed. Earlier analyzer run [40107338619](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107338619) failed only because it reported an unused local variable; this was removed in the subsequent commit and the latest analyzer passed.
- The independent full-schema Stage 5 payroll job again failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; keep it in deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), unrelated to this mobile work. Live Apache/MariaDB role-matrix testing remains unperformed. Do not request phone testing yet.


## 2026-10-24 — Mobile supervisor directory added (CI pending)

- Added authenticated read-only `GET /api/v1/supervisors/index.php`, restricted server-side to the website module's exact `admin` and `vice_general_manager` roles. It supports bounded name/username/phone search, pagination, and an explicit include-departed/archived filter; the default excludes departed and archived supervisors, matching `modules/supervisors/index.php`.
- The response uses checked-in schema columns from `users`, `roles`, `supervisor_letters`, and `letters`. It returns current supervisor status, contact details, assigned letters/gender markers, and last-login timestamp. No schema changes or runtime DDL; no supervisor create/edit/status/exit actions were added.
- Added an Arabic-first/English mobile directory page, navigation visible only to admin and vice general manager, and widget coverage for supervisor rendering and the archived filter. Added the endpoint to PHP lint workflow.
- Commits: API `74fcb0550e5c057903712f10d320d06c7e49ceaa`; PHP lint workflow `6af0c6ffd5a35d18e88b914be2a02698e1f5256c`; Flutter page `98e444570207b102427fac79cc69e860115e1036`; app navigation/API wiring `8313b555b707829d24f08cb227c6669ec7ec8db1`; widget test `8397f8d7fe75d3a381bc00c9976f9c475b196640`.
- Verification at documentation time: Flutter workflow `40108888325` at the test commit is in progress. PHP workflow has started; its full-schema Stage 5 payroll job has historically failed independently, so inspect the PHP lint job specifically and keep the known payroll issue deferred under #94. Do not claim this slice CI-verified until Dart analysis/tests/APK and endpoint PHP lint results are confirmed. Live Apache/MariaDB role tests and device acceptance remain pending; do not request phone testing yet.


## 2026-10-24 — Mobile sponsor-request actions and supervisor directory CI verified

- Sponsor-request attribution finding [#95](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/95) is resolved and closed. `mobile/ahl_el_kheir_app/lib/sponsor_requests_page.dart` displays `brought_by_name`, falling back to `brought_by_user`; `api/v1/sponsor_requests/index.php` searches sponsor name, phone, source, `r.brought_by_name`, and joined `users.full_name`. Count/list queries share the join and the five search placeholders match five parameters. No schema change.
- Sponsor-request attribution Flutter CI: [run 40106182906](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106182906) passed analysis, widget tests, Android debug APK build and artifact publication. PHP workflow [40106183059](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106183059) passed PHP lint; its unrelated full-schema Stage 5 payroll acceptance failure remains tracked under [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Sponsor-request status actions: Flutter workflow [40107450516](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450516) passed; PHP workflow [40107450497](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450497) passed PHP lint. Allowed status transitions remain limited to the existing web workflow; no schema changes.
- Supervisor directory: Flutter workflow [40108888325](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40108888325) passed analysis, tests, Android debug APK build and artifact publication.
- Acceptance boundary remains unchanged: CI is not live Apache/MariaDB role-matrix proof, and it is not physical-device acceptance. Do not request device testing until the broader mobile app reaches its release-readiness gate. Continue mobile work on `main`; keep issue #94 isolated and do not reopen closed payroll work.


## 2026-10-24 — Mobile project progress updates (CI partially verified)

- Added authenticated `POST /api/v1/projects/progress_update.php` based on the existing `project_progress_updates` insert and `audit_log` pattern. No schema changes or runtime DDL.
- Authorization mirrors the web `add_progress` action: admin or assigned primary project supervisor/active operations-section team member; closed projects are blocked. API checks authorization before revealing project state, rechecks project state/assignment during the transaction, and isolates audit failure after the progress commit.
- Project detail API returns `project.can_add_progress` for UI visibility only; POST authorization remains authoritative. Flutter now provides an Arabic/English form for summary, date, completion percentage, achievements, issues, and next steps, then refreshes detail after success. Widget tests cover submission, project ID, and refresh.
- Flutter run [40111507626](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40111507626): Dart analysis and Flutter tests passed at commit `4433f860e3e1ae7ae44ef9bec3224a12415b4a09`; APK build was still running at last check. PHP lint passed in run [40111571974](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40111571974) at commit `d42374f87d29a70155f2094107bffb5c6c74fb54`. The unrelated full-schema Stage 5 payroll failure remains tracked under [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Live Apache/MariaDB role-matrix verification and physical-device acceptance remain pending. Do not request device testing until the broader mobile app reaches release readiness.


### 2026-10-24 — Mobile milestone creation checkpoint
Mobile project detail now supports authenticated milestone creation via `api/v1/projects/milestone_create.php`, using existing milestone/history tables without schema changes. Server-side permission checks match the web operation boundary (admin or assigned primary/operations project supervisor) and reject closed projects. Flutter form/API wiring, refresh behavior, widget test and PHP lint workflow coverage are committed on `main`. CI is pending; live role/runtime and device acceptance remain outstanding. Do not request phone testing yet.


## 2026-10-24 — Mobile milestone CI verified; Netlify noted for later deployment planning
- Flutter workflow [40112041575](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40112041575) completed successfully on commit `f571077c894dea7429375ba36768827cfd832095`: Dart analysis, full widget tests, Android debug APK build, and artifact publication all passed. The successful run includes the milestone form/widget test. Artifact name: `ahl-el-kheir-flutter-android-debug-apk`; artifact publication step succeeded.
- PHP workflow [40112106628](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40112106628): `php-lint`, MariaDB 10.4 migration, rescheduling-service tests, and HR job-description migration jobs passed. Its separate full-schema Stage 5 payroll job failed at the known rollback-only payroll/account 1410 regression; keep it isolated under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), unrelated to mobile milestones.
- User provided their Netlify team projects page as a possible option for later live testing: https://app.netlify.com/teams/moneer-ali-taha/projects. Consider it during deployment planning, not as a PHP/MariaDB backend host: Netlify can serve static front-end output (including a Flutter Web build), but this existing procedural PHP API and MariaDB database would need a separate compatible HTTPS host. Do not deploy or connect services without an explicit later deployment plan.
- Mobile milestone creation is CI-verified only. Local Apache/MariaDB role-matrix verification and device acceptance remain pending; do not request phone testing until the broader app reaches release readiness.


## 2026-10-24 — Mobile nanny group verification implementation checkpoint

- Inspected `modules/accounting/group_verification.php`, `modules/accounting/lib_group_workflow.php`, and the checked-in `database/ahl_el_kheir.sql` schema before implementation. Schema confirms the exact `nanny_family_verifications` columns and unique key `(family_id, month)`, plus the `verification_audit_log` columns used by the existing web workflow. No migration or schema change was required.
- Added authenticated `GET /api/v1/nanny/group_verification.php?month=YYYY-MM&group_id=<optional>` and `POST /api/v1/nanny/group_verification_action.php`. Both are restricted server-side to the `nanny` role and use the authenticated user ID to scope active group assignments.
- Read view follows the existing group workflow: active assigned groups, families whose children remain in the group, monthly verification state, and active sponsorship amounts for active family children. Writes support individual verification, bulk verification of up to 500 selected families, and group submission. Individual verification remains available for an assigned active group even when its workflow status is no longer pending; bulk verification and submission require `pending`. Each verification write and its audit row share one transaction; bulk audit failure rolls back the whole operation. No deletion, accounting/disbursement creation, or schema DDL was added.
- Added an Arabic-first/English Flutter page, nanny-only home entry, API wiring, widget coverage for opening an assigned group and saving an individual verification, and PHP lint workflow coverage.
- Commits: GET API `0d26f030340c6192f7a09fd17937f7a773a5ab52`; action API `f0edc26d3c2835c43ed20c1e3bc429a7314fdeb7`; action-response syntax correction `aaf8a39bdba54aa3c83d371da645c490291bebcd`; Flutter page `01c57deeea518f9972127a2aadd8288a85661b4f`; app wiring `1d092bf7427cd5d2885c8339375ffa30097f6ba3`; widget test `ef224b4070e5c06b312e318f984cfd371f1f28b0`; PHP lint workflow `90b55550a467bed32e7064943753b49aac92eb26`.
- Verification is pending at this checkpoint. GitHub Flutter run [40113312109](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113312109) and PHP run [40113317445](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113317445) were still running. A prior full-schema job failed only at the known Stage 5 payroll/account 1410 check; this is issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), not evidence about this mobile feature. Await actual Flutter analyzer/widget/APK/artifact and PHP lint results before claiming CI verification.
- Live Apache/MariaDB nanny-role runtime checks and physical-device acceptance remain outstanding. Do not ask the user to test the phone yet; continue CI/source verification first.


## 2026-10-24 — Nanny group verification: first CI gate passed; closed-group coverage pending

- Flutter workflow [40113312109](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113312109) completed successfully on commit `ef224b4070e5c06b312e318f984cfd371f1f28b0`: Dart analysis passed, all 25 widget tests passed (including the new individual group-verification flow), Android debug APK build passed, and artifact publication succeeded. Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12567906074`, size 77,264,820 bytes, SHA-256 `403b36c3996e3ed98b6b3f6ed859d8bff37486c3496ebc414b691c8f7a051f4c`, is present and unexpired.
- PHP workflow [40113393725](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113393725) passed `php-lint` (including both new nanny endpoints), MariaDB 10.4 migration, rescheduling-service tests, and HR job-description migration jobs. The separate full-schema job failed at the known Stage 5 payroll prerequisite/account 1410 check: `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` Track only under deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94); do not reopen the closed payroll feature or attribute this failure to mobile verification.
- Added a second widget test in commit `4f8a9cf4ef065c8e0c7161d1a16bae4048d77ae4` for the documented closed-group boundary: hide bulk verification and submission, but permit individual verification for a family in a still-assigned active group. Flutter workflow [40113705909](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113705909) is pending at this checkpoint. Do not claim that additional test passed until its run completes.
- The group-verification implementation has CI evidence for source lint, app analysis/tests (first test), APK build and artifact publication. The latest closed-group regression test, live Apache/MariaDB role/runtime checks, and physical-device acceptance remain pending. Do not ask the user to test the phone yet.


## 2026-10-24 — Nanny closed-group test passed; preliminary verification slice added (CI pending)

- Flutter workflow [40113705909](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113705909) completed successfully on commit `4f8a9cf4ef065c8e0c7161d1a16bae4048d77ae4`. The additional widget test passed: closed groups hide bulk verification/submission while allowing an individual verification for a family in a still-assigned active group. This test-only revision passed analyzer, full widget suite, APK build and artifact publication.
- Added authenticated nanny-only `GET /api/v1/nanny/pre_verification.php?month=YYYY-MM` and `POST /api/v1/nanny/pre_verification_action.php`, based on the exact existing `modules/accounting/nanny_pre_verification.php` scope. The read view includes active families with active children in the nanny's active assigned groups regardless of group workflow status, their assigned group names, and shared monthly checks. Individual and up-to-500-family bulk writes use the existing `nanny_family_verifications` and `verification_audit_log` tables; bulk verification only adds selected checks and preserves other checks. No schema changes or runtime DDL.
- Added the Arabic-first/English preliminary-verification Flutter page, nanny-only home entry, bearer-authenticated API wiring, widget tests for individual save and bulk action, and PHP lint workflow entries for both new endpoints.
- Source commits: preliminary GET API `5eca38ee14670ce85e2b7c411faf4c599c08922b`; action API `ca4457984acb435a5f8d1ee9693032cb2cec3036`; Flutter page `a3ecc1ac649643451a462aea1c571c583d6359a0`; app wiring `250f4eab0e5101e10c56c8335af9165c6bad0fa2`; PHP lint workflow `9d170e3839e26799fff3a8f64271509ed4ab56ca`; tests `23c81c2dd69c0bd47bdf21d39415dfb550e1685c`.
- PHP lint passed in workflow [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834) for both preliminary API endpoints. The remaining independent full-schema job is not a mobile acceptance test; it has historically failed at the known Stage 5 payroll prerequisite/account 1410 check under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Flutter workflows [40113978865](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113978865), [40113993153](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113993153), and [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) were still running/queued at this checkpoint. Do not mark the preliminary-verification UI/tests/APK verified until the latest test commit passes. Live Apache/MariaDB nanny-role checks and physical-device acceptance remain deferred; do not ask for phone testing yet.


## 2026-10-24 — Mobile nanny preliminary verification CI result

Flutter CI run [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) passed analysis, widget tests, Android debug APK build, and artifact publication for the preliminary-verification flow. PHP lint passed for both new endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). Latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014) had PHP lint, MariaDB 10.4 migration, and rescheduling tests passed; remaining jobs were still running at last check. This supersedes the older pending-CI note. Live API role-matrix and device acceptance remain pending.


## 2026-10-24 — Mobile employee detail implementation checkpoint

Employee detail API and Flutter UI/navigation plus Arabic widget coverage have been added. Latest Android build/test run [40115161999](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115161999) remained in progress at last inspection. New PHP endpoint is not yet in the explicit PHP-lint workflow list, and live API authorization/runtime checks have not been performed. The full-schema Stage 5 payroll integration failure is separate: its test reported the payroll-transfer migration prerequisite missing; inspect setup before any unrelated fix.


## 2026-10-24 — Employee details verification evidence

Android CI passed for employee details/navigation at commit `a78bc176` (run [40115151758](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115151758)): analyzer clean, tests completed, debug APK built and published. Subsequent changes added an Arabic detail-page test and localized employment-state name; latest CI run [40115569057](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115569057) was still in progress. The new PHP endpoint is not yet in the explicit PHP lint list, and live authorization/DB tests remain pending. A separate full-schema Stage 5 payroll test fails because its payroll-transfer migration prerequisite is missing; investigate setup independently.


## 2026-10-24 — Employment-state localization refinement

Refined the employee detail screen to show the actual localized employment-state name instead of collapsing every `working` category into a generic label; this preserves distinctions such as probation. Updated the Arabic widget test fixture to include both Arabic and English state names. Latest Android build/test run [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) was queued at the last check. Earlier commit `a78bc176` passed analyzer, Flutter tests, and debug APK build; the newest revision still needs its own green result. PHP endpoint lint/runtime role tests remain outstanding, and the full-schema payroll migration prerequisite failure is separate.

## 2026-10-24 — Mobile employee detail verification evidence

The current employee-detail Flutter revision passed GitHub Actions run [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291): `analyze-and-build` concluded success. This verifies Flutter analysis, widget tests, Android debug build and artifact publication for that revision; it does not prove live API/database behavior. The mobile README now reflects actual implemented areas rather than claiming only login is implemented. Audit gaps remain: live role/scope tests for APIs, explicit PHP syntax coverage for `api/v1/hr/employee_detail.php`, and production hosting/HTTPS. Continue source/schema-led, without reopening closed modules.

## 2026-10-24 — Self-service mobile salary statements added (CI pending)

- Added authenticated `GET /api/v1/hr/my_salary_statements.php` and an Arabic-first/English Flutter screen linked from the signed-in home page. It resolves only the current user's employee profile through the existing `hrGetEmployeeForUser()` helper and returns only that employee's payroll rows where `status='paid'` and `accounting_status='posted'`, matching the web statement's availability boundary. Draft, approved-unpaid, and unposted rows are excluded by query conditions; no schema changes were introduced.
- Inspected `database/ahl_el_kheir.sql` directly through its Git blob: the `payroll`, `employees`, and `hr_employee_salary_history` columns used by the endpoint exist. The salary currency lookup follows the same effective-date selection used by `modules/hr/payroll_statement.php`.
- Source commit: [`7bcb340c83f42211bb6d65e80d57af3d52e95949`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/7bcb340c83f42211bb6d65e80d57af3d52e95949). The first Flutter run [40116469884](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116469884) found a widget-test fixture collision (two displayed amounts were both `100.00 SDG`); only the fixture was adjusted in [`dd226b794ca9136aa76359be62b927b018370bad`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/dd226b794ca9136aa76359be62b927b018370bad). Latest Flutter run [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126) was still in progress at this checkpoint; do not call the feature CI-verified until it finishes green.
- A PHP CLI syntax check of the new endpoint source returned `No syntax errors detected`. The existing PHP workflow's explicit lint list still does not include this new endpoint, and live Apache/MariaDB role-and-data testing remains outstanding. No device testing requested yet.


## 2026-10-24 — Mobile HR API PHP lint coverage expanded

- Added `api/v1/hr/employee_detail.php` and `api/v1/hr/my_salary_statements.php` to `.github/workflows/php-syntax-check.yml` so the two mobile HR endpoints are syntax-checked by CI, rather than relying only on a one-off CLI lint.
- Change committed directly to `main`: [`ebbf618117bab4d1235459621809ae824f0f863f`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/ebbf618117bab4d1235459621809ae824f0f863f). The workflow execution for this commit still needs confirmation; do not call this CI-verified until its PHP-lint job passes.
- The self-service salary statement Flutter revision passed Android workflow [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126): analyzer, widget tests, debug APK build, and artifact publication succeeded. This does not verify live API authorization/data behavior. Local Apache/MariaDB role-scope acceptance and a suitable HTTPS PHP/MariaDB deployment remain outstanding.


## 2026-10-24 — Mobile API syntax-check coverage completed (CI pending)

- Audited the checked-in `api/v1/` tree against `.github/workflows/php-syntax-check.yml`. All 26 PHP API endpoint files are now included in the selected PHP lint list, including authentication (`login`, `logout`, `me`), notifications (`index`, `mark_read`), HR, nanny, projects, sponsors, sponsorships, families, supervisors, transactions, and sponsor requests.
- Commit: [`eae29e5e210019ebdc6c8defd9ca9152e445e29b`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/eae29e5e210019ebdc6c8defd9ca9152e445e29b). Workflow run [40117147104](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117147104) was still running when checked; do not mark lint coverage CI-verified until `php-lint` concludes successfully. Other jobs in that workflow are separate existing test suites and should not be changed without their own concrete failure evidence.
- Live Apache/MariaDB authorization and record-scope acceptance is still outstanding. Do not treat source-level role checks or successful builds as proof of runtime security.

## 2026-10-24 — Mobile financial review queue source audit
Source reviewed: `modules/accounting/fm_review_queue.php`, `database/ahl_el_kheir.sql` (`sponsor_payments` schema), and the mobile API bootstrap. New GET endpoint enforces the web queue's allowed-role set server-side and returns only pending rows plus the same recent-history boundary (approved/returned, latest 20). Receipt paths are reduced to boolean presence flags. No mutation or DDL. Automated CI and live role/data isolation verification remain pending; do not treat source review as runtime acceptance.

## 2026-10-24 — CI failure root cause: payroll transfer proof prerequisite
Evidence: the failing full-schema workflow log showed `tools/run_salary_advance_stage5_payroll_tests.php` failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` Source `modules/hr/lib_payroll_accounting.php::hrPayrollChangeStatus()` confirms that paying an approved payroll requires `hr_payroll_period_settings`, `hr_payroll_payment_proofs`, a `prepared` proof whose private file exists, and a valid saved payment account. The CI full-schema job imported `database/ahl_el_kheir.sql` and the salary-advance migration but omitted `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql`; the test harness also lacked a prepared proof fixture. Corrected the disposable CI setup and rollback-only test fixtures without weakening production validation. Fresh CI and proof-file cleanup remain to be confirmed.

## 2026-10-24 — CLI audit actor in payment-proof fixture
Second failure evidence from run [40117930844](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117930844): proof confirmation attempted to save `confirmed_by_user_id=0`, rejected by `fk_hr_payroll_payment_proofs_confirmed_by`. `config/session.php::Session::getUserId()` returns integer `0` when `$_SESSION['user_id']` is absent. Both CLI harnesses now select an existing user from the disposable imported schema and set `$_SESSION['user_id']` before calling the real paid transition. No production logic was relaxed; await new CI evidence.

## 2026-10-24 — Verification closure for CI fixture alignment
Evidence: [workflow 40118139681](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118139681) passed `php-lint`, `migration-mariadb-10-4`, `hr-job-description-migrations`, `rescheduling-service-tests`, and `migration-full-schema`. Full-schema logs contain PASS results for Stage 5 payroll edge behavior, 1410 ledger/live-outstanding reconciliation, payroll paid-transition lock blocking and retry after rescheduling, and rollback-only fixture cleanup. [Flutter workflow 40117632176](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117632176) passed analyzer, widget tests, Android debug build, and artifact publication for the mobile queue change. This verifies CI only, not live local API authorization or deployment readiness.

## 2026-10-24 — Mobile financial review queue mutation boundary
Inspected modules/accounting/fm_review_queue.php, modules/accounting/lib_sponsor_payment_approval.php, and modules/accounting/lib_transaction_review.php. The mobile action API uses the same server-side role set as the web queue. Approval calls the shared ak_approve_sponsor_payment() within an explicit PDO transaction and commits only after it succeeds; post-commit General Manager notification failures are isolated. Return requires a reason, locks the payment row with FOR UPDATE, rejects missing/non-pending records, updates only rows still pending, attempts the same audit_log write as the web workflow, commits before notifying the supervisor, and isolates notification failure. It does not accept client-supplied reviewer IDs. No schema change or runtime DDL. PHP/full-schema CI passed; no local live API authorization or mutation test has been performed, so runtime acceptance remains open.

## 2026-10-24 — Financial review mobile actions and sponsorship role parity
Source review mapped `modules/accounting/fm_review_queue.php`, `modules/accounting/lib_sponsor_payment_approval.php::ak_approve_sponsor_payment()`, and `modules/accounting/lib_transaction_review.php::ak_transaction_review_notify_event()`. The new mobile approval action reuses the existing atomic approval function and notifies General Manager users after commit. The return action requires a reason, uses a row lock and conditional `pending` update, attempts audit metadata within the transaction, commits before supervisor notification, and rejects non-pending records. The API role allowlist matches the existing web financial-review queue. The `general_manager` role was restored to mobile sponsorship list/detail API allowlists and navigation because the existing web sponsorship module already permits it.

CI evidence: Android [40118666504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) and PHP/database [40118666512](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512) both passed. This is source/CI verification only; live Apache/MariaDB role-scope and payment-action acceptance remains open.

## 2026-10-24 — Project API approval/lifecycle scope audit
Evidence from `modules/projects/project_lib.php::akp_can_view_project()` showed missing `project_approval` rows default to `draft`; `api/v1/projects/index.php` also uses `draft`. `api/v1/projects/detail.php` had used `approved`, potentially making an assigned supervisor's project visible when no approval record existed. Changed fallback to `draft`. `modules/projects/view.php` guards access through `akp_can_view_project()` before its `add_milestone` action, and `api/v1/projects/progress_update.php` requires approved status and an operational lifecycle for non-admin supervisors. `api/v1/projects/milestone_create.php` had lacked that state gate. Added the matching approved/lifecycle check and aligned `can_add_milestone` in detail response. CI [40119297504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40119297504) passed all five jobs including lint. No schema changes; runtime authorization proof remains outstanding.

## 2026-10-24 — Mobile project milestone maintenance checkpoint

Mobile project milestone edit/delete has been added on main using the existing project_milestones table and web workflow's editable fields. API authorization, project/milestone scoping, transaction locks, closed-project blocking, Flutter confirmation/refresh behavior, and a widget test are in source. Android run 40120100653 was pending at documentation time; do not claim CI success until checked. Live local API acceptance remains outstanding. See docs/MOBILE_APP_CONTINUATION.md for exact commits and gates.

## 2026-10-24 — Mobile milestone maintenance authorization parity

Evidence-driven correction: the web module's edit/delete gate is `akp_can_edit_section('operations', $id) || $closed`, and `akp_can_edit_section('operations')` is limited to assigned project supervisors. Therefore the mobile mutation API allows `admin` on non-closed projects, and requires `project_supervisor` accounts to have an active primary-supervisor or operations assignment; all mutations scope the milestone to the requested project and block closed projects. The detail API exposes a separate `can_manage_milestones` flag so the UI does not incorrectly show edit/delete controls merely because creation is available. On source SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`, Dart analysis, all Flutter tests, debug APK build, and artifact publication passed in [Android workflow 40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219). PHP/database workflow [40121128872](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40121128872) passed all jobs on source SHA `2a664a66316319983dc32d48cdfa9024b770a71a`. The APK predates the final PHP-only authorization alignment; Flutter source did not change afterward. Live local Apache/MariaDB role and record-scope verification remains outstanding.


## 2026-10-24 — Mobile API source review checkpoint

Source-level review compared the current mobile API authentication, project authorization, sponsor/family/sponsorship scopes, HR employee/attendance/leave/salary access, nanny verification, notifications, sponsor requests, transaction listing, and FM review actions with their corresponding web workflows. No new concrete mobile-only authorization defect was established during this pass, so no speculative production change was made. This does not establish live API security: authenticated role-denial, cross-record isolation, state-transition/concurrency, and audit/notification failure-boundary checks still require the real local Apache/MariaDB environment. Database migration state must be confirmed before those tests, and deployment still requires an HTTPS-capable PHP/MariaDB host.
