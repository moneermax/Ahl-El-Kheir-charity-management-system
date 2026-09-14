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
