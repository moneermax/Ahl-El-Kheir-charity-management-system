# Ahl El Kheir Charity Management System
## Current System Status & Cross-Module Documentation Revalidation — 2026-09-08

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`
**Authoritative branch:** `main`
**Revalidation date:** 2026-09-08
**Purpose:** Provide a current repository-grounded status of the complete system, not only the HR module.

> This document is a current-state companion to the historical audit documents. It does not erase historical findings. It records what is currently present in the repository, what is implemented, what remains partial, and what must remain an open decision.

## 1. Repository Revalidation

The current repository tree was scanned recursively. The active application contains the following business/module areas:

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

The repository also contains shared configuration, language resources, storage controls, PDF generation through TCPDF, and the authoritative database dump under `database/ahl_el_kheir.sql`.

The previously applied migration scripts have been removed from `database/migrations/`. The current database state is represented by the authoritative SQL/database artifact and the application code that consumes it.

## 2. Current Architecture

**VERIFIED**

- Procedural PHP application code.
- Vanilla JavaScript.
- Bootstrap 5.3 RTL presentation.
- MariaDB/MySQL through PDO.
- Prepared SQL statements are used through shared database helpers.
- Server-side authentication and authorization are used throughout protected modules.
- CSRF protection is present on important state-changing operations.
- Sensitive storage remains protected and controlled serving endpoints are used where appropriate.

The architecture must remain procedural PHP + Vanilla JS unless an explicit future architectural decision changes that constraint.

## 3. Authentication, Users, Roles and Departments

### Authentication / sessions — VERIFIED

The application has login/logout, authenticated sessions, role resolution, password hashing/verification, last-login tracking and audit logging for login.

### User administration — VERIFIED / IMPLEMENTED-PARTIAL

`modules/users/index.php` manages users, roles, departments, managers, activation state, password operations, gender and accountant-to-nanny assignments.

### Departments — VERIFIED / IMPLEMENTED-PARTIAL

`modules/departments/index.php` and `sync.php` provide department administration/synchronization. Department relationships are also used by user organizational data.

### Permission governance — IMPLEMENTED-PARTIAL

Role checks exist at page/action level and module-specific policy helpers exist, but a single organization-wide action matrix is still required. This remains a governance/documentation task before broad authorization refactoring.

## 4. Families and Beneficiaries

**VERIFIED / IMPLEMENTED-PARTIAL**

The family domain provides family creation/edit/view, child/orphan records, documents, orphan forms/profiles, suspensions and monthly verification.

The authoritative conceptual relationship remains:

`Family → Family Child → Sponsorship`

Child-level sponsorship is intentional and allows siblings to have different sponsors.

Family verification participates in the monthly sponsorship/disbursement workflow. The current group workflow requires the relevant orphan, mother/contact and bank verification conditions before submission.

Document storage remains protected; direct public exposure must not be introduced as a shortcut.

## 5. Sponsors

**VERIFIED / IMPLEMENTED-PARTIAL**

The sponsor module contains sponsor listing, creation, editing, viewing, assignment and sponsor-request workflows.

Sponsor records are distinct from sponsorship records. Sponsor-to-child support is represented through sponsorships rather than treating a sponsor as a property of the family.

The repository also contains sponsor-assignment support in shared configuration and current workflows use supervisor/scope information when applicable.

Remaining documentation item: finalize the complete sponsor lifecycle/status matrix and reconcile any legacy sponsor structures against the authoritative database.

## 6. Sponsorships

**VERIFIED / IMPLEMENTED-PARTIAL**

Current sponsorship workflows support creation, listing and detailed viewing. The implemented relationship is:

`Sponsor → Sponsorship → Child → Family`

Observed sponsorship lifecycle states include:

- `active`
- `paused`
- `completed`
- `cancelled`

The creation/matching workflow supports prioritized matching including critical cases and children who lost sponsors, with deterministic FIFO/tie-breaking behavior. Supervisor scope and applicable letter/gender restrictions are validated server-side.

A legacy `sponsorship_children` concept remains documented as unverified and must not be assumed authoritative merely because an old configuration constant exists.

## 7. Supervisors and Nannies

**VERIFIED / IMPLEMENTED-PARTIAL**

The supervisor domain contains creation/edit/listing, sponsor visibility, assignment letters, reassignment, returns and payment-related workflows.

Supervisor scope is used by sponsorship matching and operational access rules.

Nanny/group relationships are used by monthly group/disbursement processing. Accountant-to-nanny assignments are maintained with uniqueness protection.

Remaining documentation item: finalize the organization-wide scope matrix describing exactly which supervisor/accountant/nanny can view and mutate each record class.

## 8. Monthly Verification and Disbursement

**VERIFIED / IMPLEMENTED-PARTIAL**

The current business chain is:

`Family Verification → Group Submission → Accountant Review/Batch Creation → Transfer → Nanny Confirmation → Return/Reversal → Reconciliation/Closure`

The repository contains:

- group verification;
- group disbursement creation;
- monthly batches;
- disbursement items;
- transfer workflow;
- nanny confirmation;
- receipts;
- returned amounts;
- reversal journals;
- closure behavior.

Observed item statuses include `pending`, `paid` and `returned`.

The return workflow explicitly calculates unspent/pending amounts, records return evidence, creates the reversal journal and returns the financial effect to the cash/safe side of the workflow.

A known technical-debt point remains: some older group-workflow functions and the current disbursement page contain overlapping logging/execution patterns. This must be consolidated only after caller-by-caller verification.

## 9. Accounting

**VERIFIED / IMPLEMENTED-PARTIAL**

The accounting module contains:

- chart of accounts;
- journal listing;
- journal creation;
- opening balances;
- vouchers;
- receipt serving/printing;
- financial-manager dashboard/review;
- group verification/disbursement integration;
- reconciliation views;
- accounting reports.

The principal accounting relationship is:

`journal_entries → journal_lines → accounts`

Posted journals feed financial balance calculations.

Known account conventions include cash/treasury, bank and electronic-wallet accounts, with project/disbursement expense accounts used by the relevant workflows.

The final authoritative financial-source map remains an open schema-governance task because transactions, journals, project funding and disbursement structures coexist.

## 10. Transactions / Donations / Payments

**VERIFIED / IMPLEMENTED-PARTIAL**

`modules/transactions/` provides payment/transaction listing, creation, sponsor reporting and receipt handling.

The current transaction list:

- supports role-controlled access;
- provides filtering/search and pagination;
- distinguishes `posted` and `voided` transactions;
- allows authorized users to void posted transactions with a reason;
- creates/links the corresponding accounting journal behavior;
- writes an audit record for the void operation;
- restricts supervisor visibility to the applicable sponsor scope.

The organization-wide donation lifecycle and canonical relationship between all transaction/funding structures still require final schema reconciliation.

## 11. Projects

**VERIFIED / IMPLEMENTED-PARTIAL**

The project domain contains:

- project creation and editing;
- portfolio/listing;
- detailed project view;
- project/team visibility;
- section-specific permissions;
- supervisor/project assignments;
- lifecycle management;
- budgets;
- funding allocations;
- project expenses;
- beneficiaries;
- closure totals;
- protected project documents;
- audit logging.

Observed lifecycle concepts include `planned`, `active`, `under_review`, `completed`, `cancelled`, `closed` and `reopened`.

A dedicated project lifecycle structure coexists with legacy project status information. This is documented as technical debt until the canonical lifecycle source is formally selected.

Project financial controls validate funding allocations against the approved/proposed budget and available balances before relevant approval actions.

## 12. HR

**VERIFIED / IMPLEMENTED-PARTIAL**

The current HR foundation includes employees, employment states/history, contracts, salary foundations, leave management, attendance, bulk attendance, payroll policy/calculation structures, payroll accounting integration and reversal support.

The 2026-09-08 HR documentation update established the durable return-from-leave model using `hr_leave_returns`. Attendance eligibility now considers effective employment state, approved leave and the persistent return date.

The HR module is therefore materially advanced, but final payroll/accounting reconciliation, complete permission coverage and full audit coverage remain open.

## 13. Internal Messaging

**VERIFIED**

The messaging subsystem supports:

- internal messages;
- user and role recipients;
- replies/threads;
- message references;
- urgent messages;
- attachments;
- authorized attachment downloads;
- attachment deletion;
- individual message soft deletion;
- deletion audit information;
- realtime/reload support.

Message deletion is a soft-delete lifecycle rather than physical row deletion. Reply structure is preserved.

The historical JSON contamination bug is resolved: API responses must remain pure JSON and the search UI shutdown output is restricted to the search route.

Attachment storage uses controlled filenames and protected download handling.

## 14. Notifications

**VERIFIED / IMPLEMENTED-PARTIAL**

The repository contains notification infrastructure, shared notification widgets and a mark-all-read endpoint.

Notifications are integrated with operational UI and should be used for workflow events requiring attention.

Remaining documentation item: produce a complete event-to-notification catalog showing which business events create notifications, recipient rules and read/retention behavior.

## 15. Search

**VERIFIED / IMPLEMENTED-PARTIAL**

The global search workspace supports permission-aware search across configured domains including families, sponsors, sponsorships and payments.

Authorization is enforced server-side. UI filtering is supplemental.

Record-level visibility must remain aligned with each domain's organizational scope.

The route-specific search UI behavior is an important regression boundary because global output must never contaminate JSON/API responses.

## 16. Reports and PDF Generation

**VERIFIED / IMPLEMENTED-PARTIAL**

The reports module currently contains:

- report center/index;
- financial reports;
- sponsorship reports;
- operational reports;
- HR reports;
- orphaned/family exception reporting;
- lost-contact reporting;
- confirmed-disbursement reporting.

Reports are role-filtered and date-aware where implemented.

TCPDF is present for PDF generation.

Remaining documentation item: establish a formal report catalog defining authoritative source tables, calculations, date semantics, permissions and reconciliation expectations for every official report.

## 17. Settings

**VERIFIED / IMPLEMENTED-PARTIAL**

`modules/settings/index.php` provides system-level settings management. Settings must remain controlled by authorization and must not be used to bypass business-rule validation in individual modules.

## 18. System Administration

**VERIFIED / IMPLEMENTED-PARTIAL**

The system module contains utilities for:

- database/system administration;
- backup;
- health checks;
- controlled sponsor import/synchronization;
- data-maintenance utilities such as gender backfill/splitting tools.

These utilities are high-risk and should remain tightly permissioned, logged where appropriate, and excluded from ordinary staff access.

The repository retains `database/ahl_el_kheir.sql` as the authoritative database artifact. Applied migration scripts have been removed after completion; future schema changes must be documented and captured in the authoritative database artifact before cleanup of temporary migration files.

## 19. Administration / Winback

**VERIFIED / IMPLEMENTED-PARTIAL**

The administration module contains a winback workflow. Its current implementation should be treated as an operational recovery/return-to-support function and should be reconciled with sponsorship/beneficiary status rules before any future expansion.

## 20. Logs and Audit

**VERIFIED / IMPLEMENTED-PARTIAL**

The logs module provides audit/system log views. The audit viewer supports filtering and displays actor/action/entity and old/new values where recorded.

High-value workflows demonstrably write audit evidence, including login, sponsorship changes, project actions, group/disbursement financial actions and relevant message deletion behavior.

Universal coverage across every sensitive CRUD/permission mutation has not yet been proven.

## 21. Cross-Module Integrity Rules

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

## 22. Current System Maturity Assessment

| Area | Current classification | Main remaining work |
|---|---|---|
| Authentication/session | VERIFIED / HARDENING | Session fixation and production cookie policy |
| Users/roles/departments | IMPLEMENTED-PARTIAL | Central permission matrix + audit coverage |
| Families/beneficiaries | IMPLEMENTED-PARTIAL | Final document/schema reconciliation |
| Sponsors | IMPLEMENTED-PARTIAL | Lifecycle + scope matrix |
| Sponsorships | IMPLEMENTED-PARTIAL | Final lifecycle/legacy-schema reconciliation |
| Supervisors/nannies | IMPLEMENTED-PARTIAL | Final record-scope policy |
| Verification/disbursement | IMPLEMENTED-PARTIAL | Final state matrix + workflow-path consolidation |
| Accounting | IMPLEMENTED-PARTIAL | Canonical financial-source map + reconciliation |
| Transactions/payments | IMPLEMENTED-PARTIAL | Organization-wide donation/payment model |
| Projects | IMPLEMENTED-PARTIAL | Canonical lifecycle + final finance mapping |
| HR | IMPLEMENTED-PARTIAL | Payroll/accounting reconciliation + final audit matrix |
| Messaging | VERIFIED | Preserve current JSON/attachment/delete behavior |
| Notifications | IMPLEMENTED-PARTIAL | Event/recipient catalog |
| Search | IMPLEMENTED-PARTIAL | Complete record-level visibility audit |
| Reports/PDF | IMPLEMENTED-PARTIAL | Formal report catalog and reconciliation |
| Settings | IMPLEMENTED-PARTIAL | Final governance/permission review |
| System administration | IMPLEMENTED-PARTIAL | Harden high-risk utilities and production controls |
| Logs/audit | IMPLEMENTED-PARTIAL | Mandatory event/retention matrix |

## 23. Documentation Maintenance Decision

The project documentation set should now be understood as three layers:

### Historical audit layer

- `AHL_EL_KHEIR_REPOSITORY_AUDIT.md`
- `AHL_EL_KHEIR_COMPLETE_REPOSITORY_AUDIT.md`

These preserve the evidence and conclusions of the audits in which they were produced.

### Long-lived architecture layer

- `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`

This remains the architectural/functional handoff document and should receive a major revision when the current-state model is formally frozen.

### Chronological/current-state layer

- `AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-02.md`
- `AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-08.md`
- this document: `AHL_EL_KHEIR_CURRENT_SYSTEM_STATUS_2026-09-08.md`

This current-state document exists specifically to prevent the documentation from becoming HR-only while the application continues to evolve across modules.

## 24. Next Documentation Phase

Before the next major implementation phase, the documentation should converge on four authoritative matrices:

1. **Module/action permission matrix**
2. **Workflow state-transition matrix**
3. **Database table/relationship catalog**
4. **Report/source/calculation catalog**

Once those are frozen, the main `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` should receive a major versioned revision incorporating this current state, while the historical audit files remain preserved.

**Status:** Complete-system repository revalidation recorded on 2026-09-08. No application behavior is changed by this document.