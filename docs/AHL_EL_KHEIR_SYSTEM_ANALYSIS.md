# Ahl El Kheir Charity Management System
## Comprehensive System Analysis, Functional Specification & Technical Documentation

**Arabic name:** نظام أهل الخير لإدارة الجمعيات الخيرية  
**Document type:** System Analysis + Functional Specification + Technical Architecture + Developer/AI Handoff  
**Documentation version:** 1.0  
**Baseline implementation:** `b3267ba306c81b25cff06b32ff8f5719fa7565ac`  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Current development environment:** Windows / XAMPP / Apache / PHP / MariaDB/MySQL  
**Application style:** Procedural PHP + Vanilla JavaScript + Bootstrap 5.3 RTL  
**Document status:** Living document; implementation status must be revalidated as development continues.

---

# 1. Document Purpose

This document is the authoritative working analysis of the Ahl El Kheir Charity Management System. It is intended to serve three complementary purposes:

1. **Organizational documentation** — explain what the system does, who uses it, and how charitable operations are represented and controlled.
2. **Technical documentation** — explain the implemented architecture, modules, data relationships, workflows, security controls, and development conventions.
3. **Developer / AI handoff specification** — provide sufficient context for another developer or AI assistant to continue development without reverting to assumptions from earlier project versions.

The document distinguishes, where possible, between implemented behavior, explicit business rules, and items that still require verification. It must not be treated as permission to invent missing schema, workflows, or organizational policies.

---

# 2. Project Overview

Ahl El Kheir is an organizational management platform for a charitable association. The system is intended to move the organization away from fragmented spreadsheets and manual processes toward a controlled digital workflow covering beneficiary management, sponsorship, financial operations, disbursement, communication, reporting, and accountability.

The system is not intended to be merely a CRUD application. Its target architecture is an organizational information system in which users operate according to responsibilities and permissions, financial actions are traceable, documents are controlled, and important operational events can be audited.

## 2.1 Primary objectives

- Maintain a reliable central record of families and beneficiaries.
- Maintain children/orphan records independently of family records where business rules require child-level relationships.
- Manage sponsors and sponsorship assignments.
- Organize sponsored children into operational/payment groups.
- Manage supervisors and nannies responsible for operational follow-up.
- Generate and control monthly sponsorship disbursements.
- Record payment confirmation and receipt evidence.
- Handle partial payment, returned amounts, and reconciliation workflows.
- Maintain donations and financial transactions.
- Provide role-based internal communication.
- Provide notifications and operational alerts.
- Support reports and PDF output.
- Maintain accountability through authorization and audit trails.
- Provide a maintainable foundation for future organizational expansion.

---

# 3. Scope

## 3.1 In scope

The system includes or is designed to include:

- Authentication and session management.
- Users, roles, and permissions.
- Family and beneficiary management.
- Children/orphan management.
- Sponsors and sponsorships.
- Supervisor and nanny workflows.
- Orphan/payment groups.
- Monthly verification and preparation.
- Monthly disbursement batches and items.
- Payment confirmation and receipt management.
- Returned funds and related accountability.
- Donations and financial transactions.
- Accounting and journal-related functionality.
- Internal messaging and attachments.
- Notifications.
- HR-related organizational functions.
- Projects.
- Search.
- Reports and PDF generation.
- System settings, logs, backups, and administration.

## 3.2 Out of scope unless explicitly added

External payment gateways, public donor portals, mobile applications, external accounting integrations, and other third-party integrations should not be assumed to exist merely because the business concept could support them.

---

# 4. Technology Stack and Architecture

## 4.1 Backend

- PHP 8.2+
- Procedural PHP for application code.
- MySQL / MariaDB.
- Apache through XAMPP during local development.

## 4.2 Frontend

- HTML5.
- CSS3.
- Bootstrap 5.3 RTL.
- Vanilla JavaScript.
- Font Awesome 6.
- Cairo font for Arabic presentation.

Frontend frameworks should not be introduced unless the architecture is deliberately reconsidered.

## 4.3 PDF/reporting

TCPDF is present in the repository and is used as the PDF-generation library where applicable.

## 4.4 Environment

Local working directory:

`D:\xampp\htdocs\AhlElKheir`

Typical local URL:

`http://localhost:8081/AhlElKheir/`

The current configuration dynamically detects protocol, host, and application base path rather than permanently hard-coding the local URL.

## 4.5 Application architecture

The application follows a modular PHP structure with shared configuration, database/session/function libraries, module pages, action/API endpoints, assets, and storage. Existing application code is procedural; reusable third-party libraries such as TCPDF are treated separately from the application's own coding style.

---

# 5. Core Architectural Principles

1. **Role-based access is authoritative.** UI hiding is not a security boundary; server-side authorization must enforce every protected action.
2. **Least privilege.** Users should receive only the operational access required by their role.
3. **Separation of duties.** Preparation, approval, financial transfer, physical/operational confirmation, and reconciliation should not be casually collapsed into one permission.
4. **Traceability.** Important operational and financial actions should identify who performed them and when.
5. **Database integrity.** Foreign keys, transactions, constraints, and authoritative relationships must be preserved.
6. **No schema guessing.** Developers must inspect the actual database definition before writing SQL against uncertain columns or relationships.
7. **Protected documents remain protected.** Sensitive files should be served through authorized endpoints rather than made publicly readable merely for convenience.
8. **Financial operations are transactional.** Multi-table financial changes should use database transactions and rollback on failure.
9. **Existing functionality must be preserved.** A local fix must not introduce regressions into unrelated modules.
10. **Current repository code is authoritative.** Historical chat discussions are context, not a substitute for inspecting the current implementation.

---

# 6. Organizational Roles

The current organizational model includes the following roles:

| Role | Typical responsibility |
|---|---|
| System Administrator | Technical/system administration, configuration, privileged maintenance |
| General Manager | Executive oversight, high-level approvals and organizational decisions |
| Vice General Manager | Executive support, delegated management and approvals |
| Financial Manager / Accountant | Financial oversight, accounting control, reconciliation and financial approvals |
| Accountant Staff | Operational accounting and financial processing under controlled permissions |
| Supervisor | Operational supervision of beneficiaries/groups and related follow-up |
| Nanny | Direct operational follow-up and monthly beneficiary/payment confirmation |
| Administration | Administrative records and organizational operations |
| HR Manager | Human resources management and oversight |
| HR Staff | HR operational processing |
| Social Media | Social-media-related organizational work |

Role names in code may have normalized aliases. Authorization logic must use the application's actual role normalization rules rather than assuming that display names are database values.

---

# 7. Role and Permission Model

Authorization should be evaluated at multiple levels:

### 7.1 Module-level authorization
Determines whether the role can enter or use a functional area.

### 7.2 Action-level authorization
Determines whether the role may create, edit, delete, approve, transfer, confirm, return, reopen, or otherwise change a record.

### 7.3 Record-level visibility
A role may have access to a module but still be restricted to records relevant to its organizational responsibility.

### 7.4 Financial authorization
Financial actions require stronger controls than ordinary data entry. Transfer, journal creation, confirmation, reversal, and reconciliation must not be granted merely because a user can view the corresponding page.

### 7.5 UI versus server enforcement
Buttons, links, and menu items may be hidden for usability, but the backend must independently reject unauthorized requests.

---

# 8. Functional Module Architecture

## 8.1 Authentication and sessions

Responsibilities:

- Login.
- Logout.
- Session initialization.
- Current-user identification.
- Role identification.
- Session-protected routes.
- Password verification and account access control.

Authentication is foundational: every protected module should operate on an authenticated user context.

## 8.2 Users and organizational administration

Responsibilities include:

- User accounts.
- Role assignment.
- Account status.
- Profile information.
- Organizational responsibility.
- Administrative control.

User changes should be auditable because role assignment directly affects access to sensitive information and financial operations.

## 8.3 Families

Families represent the household/case-management level. A family can contain multiple children and associated information/documents.

Important principle: family-level identity and child-level sponsorship must not be conflated.

## 8.4 Children / beneficiaries / orphans

Children are the beneficiary level used by sponsorship and monthly operational workflows.

A child may belong to a family while independently having sponsorship, payment, group, or operational state.

## 8.5 Sponsors

The sponsor domain stores sponsor information and supports sponsorship relationships.

Sponsor and child are distinct business entities. A sponsor should not be represented as a property of the family itself when the underlying business rule is child-level sponsorship.

## 8.6 Sponsorships

A sponsorship represents the relationship and financial/support commitment between a sponsor and a sponsored child.

The authoritative business relationship is child-level. This allows siblings in the same family to have different sponsors and therefore different sponsorship/payment contexts.

## 8.7 Supervisors and nannies

Supervisors provide operational oversight. Nannies perform direct beneficiary follow-up and, where assigned, monthly payment confirmation.

The exact operational scope of each role must be enforced server-side.

## 8.8 Groups

Groups organize sponsored children for operational processing, particularly monthly disbursement.

A group must not be interpreted as a family. Children from different families can be operationally grouped, while siblings can potentially belong to different sponsorship/payment groups.

## 8.9 Monthly verification

Monthly verification is a control stage before payment processing. The purpose is to establish that the beneficiary/sponsorship is still eligible for the relevant monthly cycle and that the operational record is ready for disbursement.

Verification should be traceable to the responsible user and month where the implementation supports such tracking.

## 8.10 Monthly disbursements

The disbursement architecture includes monthly batches, groups, and individual disbursement items.

A simplified lifecycle is:

`Preparation → Verification → Group/Batch Review → Financial Processing → Transfer → Nanny Confirmation → Reconciliation → Closure`

A batch can contain multiple groups and a group can contain multiple disbursement items.

## 8.11 Donations and transactions

The financial domain records incoming and outgoing monetary activity. Financial records should remain traceable to their source, purpose, date, amount, and responsible workflow.

## 8.12 Accounting

The project contains accounting-related structures and journal processing. Where journal entries are used, the journal header and journal lines form the accounting record and must remain internally consistent.

Existing implementation rules identify `journal_entries.entry_code` as authoritative for the entry code and `journal_lines.entry_id` as authoritative for the relationship to the journal entry.

## 8.13 Internal messaging

The internal messaging subsystem supports:

- User-to-user messages.
- Role recipients.
- Replies/threads.
- Message references.
- Urgent messages.
- Attachments.
- Attachment download authorization.

Current confirmed attachment architecture uses `messages` and `message_attachments`, with foreign keys from attachments to both messages and users.

## 8.14 Notifications

Notifications should surface events requiring user attention, particularly workflow transitions, financial actions, urgent messages, approvals, and operational exceptions.

## 8.15 HR

HR functionality is part of the organizational direction of the project. Detailed HR behavior must be documented from the actual current implementation rather than inferred from filenames alone.

## 8.16 Projects

Projects represent organizational initiatives separate from individual sponsorships. Their exact financial and workflow integration must follow the implemented database and business rules.

## 8.17 Search

Global search is permission-aware. Search domains are centrally controlled and record-level visibility remains separately enforced by search queries.

The current search authorization policy normalizes role aliases and controls allowed search domains. The UI filtering is intentionally limited to the search route so that JSON/API responses are not polluted with HTML/JavaScript.

---

# 9. Beneficiary and Sponsorship Data Model

The system must preserve the following fundamental conceptual separation:

`Family → Children → Sponsorships → Monthly Disbursement Items`

rather than:

`Family → Sponsor`

This is important because:

- One family can contain multiple children.
- Different children can have different sponsors.
- Sponsorship amount is associated with the sponsorship/child context.
- Monthly payment groups operate on sponsored children/items, not on families as a single financial unit.

When a group contains a child, code must not assume that a `family_id` exists directly on `group_children` if the current schema does not contain that column. Where required, the relationship is traversed through the appropriate child/family tables.

---

# 10. Monthly Disbursement Business Workflow

## 10.1 Preparation

The organization prepares the monthly sponsorship cycle. Eligible sponsorships/children are identified and grouped for processing.

## 10.2 Verification

Operational staff verify beneficiary status and relevant monthly conditions.

## 10.3 Financial review

Financial personnel review the resulting amounts and the financial impact before transfer.

## 10.4 Transfer

Once approved according to the implemented workflow, the monthly batch is transferred/processed financially.

## 10.5 Nanny confirmation

The nanny confirms receipt of the amount and uploads supporting receipt evidence.

The confirmation operation should:

- Require authentication.
- Verify the user is authorized for the item/group.
- Change the item state appropriately.
- Record confirmation time.
- Store the receipt path/reference.
- Preserve auditability.

## 10.6 Partial confirmation

If a group is only partially paid, the system must not treat the unpaid portion as successfully disbursed.

Where the operational workflow closes a partially paid group, the unpaid/returned amount must be calculated accurately and handled through the return-to-accountant/safe workflow rather than silently discarded.

## 10.7 Returned funds

Returned funds should be recorded with:

- Amount.
- Month/cycle.
- Reason.
- Date/time.
- Responsible user.
- Return receipt/document where required.
- Financial effect on the organization's cash/safe balance.

The return should be an explicit financial event, not merely a text note.

## 10.8 Closure

A disbursement cycle/group should be considered closed only when its financial and operational state satisfies the configured closure rules.

---

# 11. Disbursement State Model

The implementation currently uses item-level statuses including `pending` and `paid`, with additional fields supporting confirmation, return, and reversal behavior.

Conceptually:

`pending → paid`

and, where a payment must be reversed/returned, the system must preserve the appropriate return/reversal evidence instead of overwriting history.

Relevant fields include receipt path, confirmation timestamp, return reason/timestamps/users, returned timestamp/user, and reversal journal linkage. Exact allowed transitions must remain aligned with the current code and database constraints.

---

# 12. Financial Control Principles

Financial functionality requires stronger safeguards than ordinary CRUD operations.

## 12.1 Every monetary event should answer

- What happened?
- How much?
- Why?
- For which month/cycle?
- Which account/fund was affected?
- Who performed it?
- When?
- What supporting document exists?
- What prior event does it reverse or relate to?

## 12.2 Transactions

Operations affecting multiple financial tables should use database transactions:

`START TRANSACTION → validate → write all related records → COMMIT`

Any failure should result in rollback.

## 12.3 Reconciliation

Disbursement totals, paid totals, pending totals, returned totals, and financial journal effects should reconcile mathematically.

## 12.4 Separation of duties

Where organizationally appropriate:

- operational preparation should be separate from financial approval;
- transfer should be separate from beneficiary confirmation;
- confirmation should be separate from final reconciliation;
- system administration should not automatically imply authority over every financial decision.

---

# 13. Database Integrity Rules

The database is a critical part of the application's business logic.

Rules:

1. Inspect actual table definitions before proposing schema changes.
2. Inspect indexes and foreign keys before changing relationships.
3. Do not add duplicate foreign keys simply because an old discussion mentioned one.
4. Preserve InnoDB relationships where they represent real business ownership.
5. Use prepared statements for user-supplied values.
6. Avoid destructive SQL unless explicitly requested and precisely scoped.
7. Never use `DELETE` without a deliberate `WHERE` clause in operational instructions.
8. Never assume a column exists based on a conceptual model alone.
9. Keep financial writes atomic.
10. Preserve historical records required for auditability.

Known messaging relationships include:

- `message_attachments.message_id → messages.id`
- `message_attachments.uploader_user_id → users.id`

Both tables use InnoDB in the confirmed current design.

---

# 14. Internal Messaging and Attachment Architecture

The messaging subsystem is confirmed operational at the current baseline.

Tables:

### messages

Key fields include:

- `id`
- `sender_id`
- `recipient_user_id`
- `recipient_role`
- `subject`
- `body`
- `parent_id`
- `reference_type`
- `reference_id`
- `is_urgent`
- `created_at`

### message_attachments

Key fields include:

- `id`
- `message_id`
- `uploader_user_id`
- `original_name`
- `stored_name`
- `mime_type`
- `size_bytes`
- `created_at`

Attachment download is performed through an authorized endpoint rather than relying on direct public access to the stored file.

### Important resolved issue

The attachment upload/database operation was working, but a global shutdown handler in `config/search_permissions.php` appended search UI JavaScript to API responses. This caused `response.json()` parsing to fail because valid JSON was followed by HTML/JavaScript.

The current implementation limits the UI injection to the actual search route (`modules/search/index.php`). This route-specific guard must be preserved. Messaging attachment endpoints must return pure JSON.

**Do not modify the attachment system merely because the historical bug is documented here. It is currently confirmed working.**

---

# 15. File and Document Security

The application stores several classes of uploaded files:

- User avatars.
- Messaging attachments.
- Payment/disbursement receipts.
- Returned-funds receipts.
- Other organizational documents.

Security principles:

1. Physical storage location is not itself an authorization mechanism.
2. Sensitive files should not become publicly accessible simply to make `<img>` or download links work.
3. Protected files should be served by authenticated/authorized PHP endpoints when necessary.
4. File names should not be trusted as authorization credentials.
5. Upload validation should consider MIME type, extension, size, and safe storage naming.
6. Download endpoints must validate both authentication and access to the referenced record.

---

# 16. Search Authorization

The global search policy centrally determines which domains a role may search.

Current broad role groups include access to some or all of:

- Families.
- Sponsors.
- Sponsorships.
- Payments.

Some roles intentionally have no global-search domain.

The system distinguishes domain authorization from record-level visibility. A user being permitted to search a domain does not automatically mean every record in that domain is visible.

The search UI script is route-specific. It must not be injected into unrelated responses, especially JSON endpoints.

---

# 17. Security Architecture

## 17.1 Authentication

All protected pages and endpoints must establish an authenticated user context.

## 17.2 Authorization

Every protected action must enforce role/permission rules server-side.

## 17.3 CSRF

State-changing browser requests should use the application's CSRF protection where implemented.

## 17.4 SQL injection

Use parameterized/prepared SQL statements. Never concatenate untrusted values directly into SQL.

## 17.5 XSS

Escape output according to its HTML context. Do not treat database text as trusted HTML without explicit sanitization/design.

## 17.6 File uploads

Validate uploads and store them with controlled names. Sensitive files should use authorized serving endpoints.

## 17.7 Session security

Production deployment should use secure cookie/session settings appropriate to HTTPS and should minimize unnecessary session exposure.

## 17.8 Error handling

Development may display detailed errors. Production should not expose database credentials, file paths, SQL statements, stack traces, or other sensitive diagnostics.

---

# 18. Audit and Accountability

The system contains an audit-log concept and should progressively use it for high-value operations.

At minimum, auditability should be considered for:

- User creation/deactivation.
- Role changes.
- Beneficiary eligibility changes.
- Sponsorship creation/change/closure.
- Monthly verification.
- Disbursement preparation/approval/transfer.
- Payment confirmation.
- Returned funds.
- Financial journal operations.
- Document replacement/deletion.
- Important administrative changes.

An audit record should, where supported, identify the actor, action, target record, timestamp, and relevant before/after information.

---

# 19. Reporting Requirements

The reporting layer should support operational and financial decision-making.

Potential report categories:

### Beneficiary reports
- Families.
- Children.
- Eligibility/status.
- Sponsorship coverage.

### Sponsorship reports
- Active sponsorships.
- Sponsor/child relationships.
- Sponsorship amounts.
- Sponsorship status.

### Disbursement reports
- Monthly batch totals.
- Group totals.
- Paid/pending counts.
- Returned amounts.
- Receipt completeness.

### Financial reports
- Donations.
- Transactions.
- Cash/fund movement.
- Journal activity.
- Reconciliation differences.

### Organizational reports
- Workload by supervisor.
- Nanny assignments.
- User/role activity.
- HR information where implemented.

Reports must respect the requesting user's authorization.

---

# 20. PDF and Document Generation

TCPDF is included for PDF generation. PDF output should:

- Preserve Arabic text and RTL presentation.
- Present organization-approved headings and metadata.
- Include relevant reporting period/date.
- Avoid exposing data outside the user's authorization scope.
- Clearly distinguish generated reports from original uploaded documents.

---

# 21. Configuration and Environment Management

The central configuration defines application identity, environment, filesystem root, dynamic application URL, database settings, tables, storage locations, timezone, error behavior, and language loading.

The current application dynamically determines the base URL from the server environment. This supports both local subdirectory deployment and a live root/subdirectory deployment without hard-coding the local development URL throughout the application.

Database credentials and environment-specific values should eventually be moved to an appropriate production configuration mechanism rather than committed with development defaults.

---

# 22. Storage Architecture

The application uses an application storage area for categories including backups, logs, avatars, receipts, and messaging/document uploads.

Storage must be divided conceptually into:

- Publicly safe assets.
- Authenticated assets.
- Highly sensitive documents.
- Internal system files such as logs/backups.

A `.htaccess` restriction on a protected storage directory should not be removed simply because an image or document fails to display. Instead, determine whether the resource is supposed to be public or should be delivered through a controlled endpoint.

---

# 23. Development and Change-Control Rules

Before modifying the system:

1. Check the current Git branch and status.
2. Confirm the current baseline/commit.
3. Inspect the actual relevant source files.
4. Inspect the actual database schema for database-dependent changes.
5. Trace the complete request/data flow.
6. Identify the root cause before changing code.
7. Prefer the smallest safe change.
8. Preserve unrelated working functionality.
9. Test the changed workflow and relevant regression paths.
10. Review the diff before committing.
11. Commit with a meaningful message.
12. Synchronize with the intended remote branch.

Do not blindly replace an entire file when a localized correction is sufficient.

---

# 24. Testing Strategy

Every feature should be tested at four levels where applicable:

## 24.1 Functional test

Does the requested feature work for the authorized user?

## 24.2 Negative authorization test

Does an unauthorized user fail safely when manually requesting the endpoint or changing parameters?

## 24.3 Data-integrity test

Are all affected database records correct, with no orphaned or duplicated data?

## 24.4 Regression test

Does the change leave related modules working?

For financial features, additionally verify:

- totals;
- status transitions;
- journal impact;
- receipt/document state;
- rollback behavior;
- reconciliation.

For API endpoints, verify that the response contains only the documented response format. JSON endpoints must return valid JSON without appended HTML, warnings, or scripts.

---

# 25. Current Known-Good Baseline

At documentation time, the following state is explicitly established:

- Repository baseline: `b3267ba306c81b25cff06b32ff8f5719fa7565ac`.
- Local and `origin/main` were synchronized at that baseline during the handoff.
- Working tree was reported clean.
- Internal messaging is functional.
- Messaging attachments are functional.
- Attachment sending, receiving, listing, storage, database persistence, and downloads were tested.
- The previous JSON contamination issue was resolved by making search UI injection route-specific.
- Message attachment foreign-key relationships are confirmed.
- The current search permission implementation contains server-side domain authorization and route-specific UI filtering.

This section should be updated whenever the known-good baseline changes materially.

---

# 26. Known Areas Requiring Continued Verification

The previous analysis identified several areas that should be verified against the implementation before being treated as final requirements:

- Exact HR workflow and data model.
- Full financial-manager permissions across all financial modules.
- Any overlap among historical/legacy financial tables.
- Final sponsorship lifecycle states.
- Exact disbursement state-transition matrix.
- Complete return/reversal accounting implementation.
- Complete audit-log coverage.
- Production deployment and secret-management strategy.
- Final reporting catalog.

These are not reasons to redesign working code prematurely. They are documentation checkpoints for future analysis.

---

# 27. Technical Debt / Improvement Roadmap

## Priority 1 — Security and integrity

- Complete authorization review for all sensitive endpoints.
- Review upload validation and protected-file serving.
- Review CSRF coverage.
- Review production error handling.
- Verify financial transactions are atomic.
- Verify audit logging of critical financial and administrative events.

## Priority 2 — Organizational workflow completeness

- Finalize role/permission matrix.
- Finalize approval and separation-of-duty rules.
- Complete monthly disbursement state machine.
- Complete partial-payment and return workflow.
- Complete reconciliation rules.

## Priority 3 — Data architecture

- Review legacy/overlapping financial structures.
- Document all authoritative relationships.
- Remove or isolate dead/obsolete code only after confirming it is unused.
- Document indexes and critical constraints.

## Priority 4 — Reporting and operational visibility

- Standardize reports.
- Add reconciliation dashboards.
- Add exception reports.
- Improve operational notifications.

## Priority 5 — Production readiness

- Environment-specific configuration.
- HTTPS.
- Secure sessions/cookies.
- Backup strategy.
- Logging/monitoring.
- Deployment procedure.
- Recovery procedure.

---

# 28. AI Developer Handoff Specification

Any future AI assistant working on this repository must follow these rules.

### Context

The project is an Arabic RTL charity organizational management system. It is implemented primarily in procedural PHP with Vanilla JS and Bootstrap 5.3 RTL.

### Non-negotiable constraints

- Do not introduce a PHP OOP architecture into existing application code without an explicit architectural decision.
- Do not introduce a frontend framework.
- Do not replace working modules merely to make a local change easier.
- Do not guess database columns.
- Do not invent foreign keys.
- Do not bypass authorization for convenience.
- Do not make sensitive files publicly readable as a shortcut.
- Do not use destructive SQL without explicit, scoped approval.
- Do not assume historical chat code is still current.
- Inspect the repository before modifying code.

### Financial rules

- Treat financial writes as high-risk.
- Use transactions for multi-step financial operations.
- Preserve history.
- Never silently change paid amounts/statuses without understanding the workflow.
- Returned money must have an explicit financial effect and supporting evidence.

### Messaging rules

- The current messaging attachment system is working.
- Do not reopen the historical attachment bug unless a new symptom is reported.
- Messaging API endpoints must return pure JSON when documented as JSON.
- Do not reintroduce global shutdown output into API responses.

### Database rules

- Inspect schema first.
- Use authoritative table/column names from the current repository/database.
- Respect foreign keys.
- Do not create redundant relationships merely because a conceptual model seems simpler.

### Change workflow

`Inspect → Diagnose → Explain → Patch minimally → Test → Review diff → Commit → Push`

---

# 29. Recommended Documentation Maintenance

This document should be updated after significant architectural changes, not after every small bug fix.

A significant change includes:

- New module.
- New organizational role.
- New financial workflow.
- Database relationship change.
- Authentication/authorization change.
- Major storage/security change.
- New integration.
- Major reporting architecture change.

Each update should record the implementation baseline/commit where practical.

---

# 30. Final System Vision

The intended final state of Ahl El Kheir is a controlled organizational platform in which:

- Every beneficiary has a reliable identity and case history.
- Every sponsorship is traceable to the correct child and sponsor.
- Operational responsibilities are assigned to appropriate staff.
- Monthly financial assistance is generated through a controlled workflow.
- Financial approval, transfer, confirmation, return, and reconciliation are distinguishable events.
- Receipts and supporting documents are securely stored and traceable.
- Users communicate internally without losing organizational context.
- Management can obtain reliable reports.
- Sensitive information is protected according to role.
- Important actions are auditable.
- Database relationships represent actual business rules.
- The application can be maintained by future developers without depending on undocumented assumptions.

The objective is therefore not simply a working website. The objective is a **reliable digital operating system for the charity's organizational, beneficiary, sponsorship, communication, and financial workflows**.

---

# Appendix A — Core Conceptual Relationship Map

```text
Users / Roles
     │
     ├── Administration / HR / Management
     │
     └── Operational Staff
             │
             ├── Supervisors
             │       └── Groups / Beneficiary follow-up
             │
             └── Nannies
                     └── Monthly confirmation

Families
   │
   └── Children / Beneficiaries
            │
            └── Sponsorships
                    │
                    └── Monthly eligibility / disbursement
                              │
                              └── Disbursement Item
                                      │
                                      ├── Pending
                                      ├── Paid + Receipt
                                      └── Return / Reversal evidence

Sponsors
   │
   └── Sponsorships ───────────────┘

Financial Domain
   │
   ├── Donations / Transactions
   ├── Journal Entries
   ├── Journal Lines
   └── Reconciliation

Communication Domain
   │
   └── Messages
          │
          └── Message Attachments
```

# Appendix B — Current Repository Reference

Repository: `moneermax/Ahl-El-Kheir-charity-management-system`  
Known-good baseline: `b3267ba306c81b25cff06b32ff8f5719fa7565ac`  
Documentation branch: `documentation/system-analysis-v1`

This document was added as a documentation-only change and does not intentionally modify application behavior.