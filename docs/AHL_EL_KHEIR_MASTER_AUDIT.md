# Ahl El Kheir Charity Management System
## Master System Review and Audit

**Arabic name:** نظام أهل الخير لإدارة الجمعيات الخيرية  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-14  
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

### Notification next work

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
