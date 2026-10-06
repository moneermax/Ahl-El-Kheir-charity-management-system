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
