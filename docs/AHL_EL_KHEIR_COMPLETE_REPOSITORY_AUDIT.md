# Ahl El Kheir Charity Management System
## Consolidated Repository Audit — Phase 2 and Final Cross-Module Review

**Audit branch:** `documentation/system-analysis-v1`  
**Known-good application baseline:** `b3267ba306c81b25cff06b32ff8f5719fa7565ac`  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Audit purpose:** Convert the existing conceptual/system documentation into a repository-grounded implementation audit and record verified behavior, partial implementations, legacy structures, security observations, and open organizational decisions.

> This document is an audit artifact. It does not authorize application-code changes. Findings marked **OPEN DECISION**, **LEGACY/UNVERIFIED**, or **IMPLEMENTED-PARTIAL** must not be silently converted into new behavior.

---

# 1. Audit Method and Evidence Rules

The audit follows:

`Inspect → Diagnose → Explain → Patch minimally → Test → Review diff → Commit → Push`

For documentation work, the equivalent rule is:

`Inspect repository → verify behavior → classify evidence → document → commit`

Evidence classifications:

- **VERIFIED** — directly confirmed in current repository code/configuration.
- **IMPLEMENTED-PARTIAL** — implementation exists, but the complete cross-module behavior still needs broader verification or organizational confirmation.
- **BUSINESS RULE** — organizational behavior explicitly established during project work.
- **INFERRED** — reasonable interpretation not sufficient to become a requirement without confirmation.
- **LEGACY/UNVERIFIED** — historical, duplicate, unused, or inconsistent implementation that must not be treated as authoritative.
- **OPEN DECISION** — requires an organizational or architectural decision.

The audit intentionally distinguishes source-code evidence from design intent. A filename, database constant, old SQL artifact, or previous discussion is not treated as proof of an active relationship unless current implementation evidence supports it.

---

# 2. Repository and Architecture

## 2.1 Verified structure

The repository contains the major application areas required by the current system:

- `config/` — configuration, database access, session and shared helpers.
- `modules/` — functional business modules.
- `dashboard/` — role-oriented dashboards.
- `includes/` — shared UI components.
- `assets/` — CSS/JavaScript/frontend assets.
- `database/` — authoritative database backup artifact.
- `storage/` — runtime file storage.
- `TCPDF/` — PDF-generation library.
- `docs/` — system documentation and audit material.

Current module families include accounting, administration, departments, deputy/general-management functions, families, HR, logs, messages, projects, reports, search, settings, sponsors and sponsorships.

## 2.2 Verified technical architecture

- Backend is procedural PHP using shared PDO helpers.
- Application data access is not based on an ORM.
- Frontend is server-rendered PHP/HTML with Bootstrap and JavaScript.
- Role checks are performed server-side in module entry points.
- CSRF tokens are implemented centrally and used by important state-changing forms.
- Audit logging is implemented in multiple high-value workflows.

`config/database.php` confirms PDO with exceptions, associative fetch mode and native prepared statements (`ATTR_EMULATE_PREPARES = false`).

## 2.3 Configuration

`config/config.php` dynamically derives the application directory/base path and constructs `APP_URL` from the current request environment. Therefore the application URL must remain environment-derived rather than hard-coded to the development machine.

Current configuration also indicates:

- development environment;
- displayed PHP errors in development;
- timezone `Africa/Khartoum`;
- centralized table-name constants;
- storage/log/backup paths below the application storage area.

**Deployment checkpoint:** the timezone and production error-display policy must be confirmed before deployment.

---

# 3. Authentication and Session Security

## 3.1 Login — VERIFIED

`index.php` performs username lookup through a prepared query, verifies passwords using `password_verify()`, checks `is_active`, records `last_login_at`, writes a `LOGIN` audit record and routes the user through `dashboard_for_role()`.

Passwords are stored/created with `password_hash(..., PASSWORD_DEFAULT)` in the user-management workflow.

## 3.2 Session — VERIFIED

`config/session.php` provides the session API and uses:

- custom session name `AhlElKheirSession`;
- `HttpOnly = true`;
- `SameSite = Lax`;
- session cookie path `/`;
- `secure = false`.

`secure = false` is suitable only for the current non-HTTPS development configuration.

## 3.3 Security finding — IMPLEMENTED-PARTIAL

The inspected login flow does not explicitly call `session_regenerate_id(true)` after successful authentication.

**Risk:** session fixation protection is weaker than it should be for production.

**Recommendation:** add session ID regeneration immediately after successful authentication, but do so as a controlled security change rather than as part of unrelated feature work.

## 3.4 Password policy — OPEN SECURITY IMPROVEMENT

The user-management page currently accepts passwords of six or more characters.

This is functional but weak for an organizational system.

Recommended future policy:

- minimum length appropriate to organizational policy;
- optional complexity rules only if they are practical;
- forced reset capability;
- preferably password-change history/expiration only if the organization requires it.

No password policy change is included in this audit branch.

---

# 4. Users, Roles, Departments and Permissions

## 4.1 User administration — VERIFIED

`modules/users/index.php` is restricted to `admin`.

It supports:

- user creation;
- role assignment;
- department assignment;
- manager assignment;
- activation/deactivation;
- password reset;
- gender field;
- accountant-to-nanny assignments.

The user-management page also performs runtime schema synchronization for several structures, including departments, roles, user organizational fields and assignment/document tables.

## 4.2 Current role set — VERIFIED/IMPLEMENTED-PARTIAL

Observed role codes include:

- `admin`
- `general_manager`
- `vice_general_manager`
- `financial_manager`
- `accountant`
- `accountant_staff`
- `supervisor`
- `nanny`
- `administration`
- `social_media`
- `hr_manager`
- `hr_staff`
- project-specific roles such as `projects_manager` and `project_supervisor`.

The exact final organizational role catalog must be reconciled against the authoritative database and organizational decision before production policy is declared final.

## 4.3 Authorization architecture — IMPLEMENTED-PARTIAL

The application uses several authorization patterns:

1. direct role arrays in page entry points;
2. centralized helpers such as `require_role()`;
3. module-specific policy helpers such as the project authorization library;
4. search-specific authorization.

This works, but permission definitions are not yet one single normalized matrix. Sponsorship create/view, projects, reports and accounting expose different role lists.

**Required future direction:** maintain a formal permission matrix by module/action, then gradually make the implementation converge on it.

## 4.4 User-management audit coverage — OPEN

Login and several business actions are audited, but the inspected user-management page does not demonstrate a complete audit trail for every user lifecycle action such as role changes, activation changes and password resets.

This should be included in the organization-wide audit coverage review.

---

# 5. Families and Beneficiaries

## 5.1 Domain relationship — VERIFIED

The implemented sponsorship path establishes the beneficiary hierarchy as:

`Family → Family Child → Sponsorship`

The child is the sponsorship target, not the family as a whole.

This supports siblings having different sponsors.

## 5.2 Group relationship — VERIFIED

The group-workflow implementation traverses:

`group_children.child_id → family_children.id → family_children.family_id → families.id`

There is no evidence in the inspected group workflow that `group_children` itself should be treated as having a direct authoritative `family_id` column.

## 5.3 Documents — IMPLEMENTED-PARTIAL

User-management runtime schema synchronization creates `family_documents` with:

- family ID;
- document type;
- file path;
- uploader;
- upload timestamp.

The authoritative SQL dump must be used to confirm whether this runtime-created structure is already part of the final schema and whether other document tables overlap with it.

---

# 6. Sponsorship Management

## 6.1 Core relationship — VERIFIED

Current create/view implementation uses:

`Sponsor → Sponsorship → Child → Family`

`modules/sponsorships/create.php` writes `sponsor_id`, `child_id`, monthly amount, currency, start date, status, notes and creator.

## 6.2 Sponsorship pivot — LEGACY/UNVERIFIED

`config/config.php` still defines `TABLE_SPONSORSHIP_CHILDREN = sponsorship_children`, but the inspected sponsorship workflow directly uses `sponsorships.child_id`.

Therefore `sponsorship_children` must not be treated as the authoritative relationship until the final schema/reference audit proves otherwise.

## 6.3 Sponsorship lifecycle — VERIFIED

Observed sponsorship states:

- `active`
- `paused`
- `completed`
- `cancelled`

Observed transitions include:

`active → paused`

`paused → active`

`active/paused → completed`

`active/paused → cancelled`

Completing a sponsorship returns the child to `unmatched`; cancellation marks the child as `lost_sponsor` in the inspected workflow.

## 6.4 Auto-matching — VERIFIED

The creation workflow includes automatic matching based on:

1. critical cases;
2. children who lost a sponsor;
3. older application date / FIFO;
4. child ID as deterministic tie-breaker.

Supervisor scope and letter/gender restrictions are considered, and server-side validation repeats the scope check instead of trusting only the UI.

## 6.5 Authorization — IMPLEMENTED-PARTIAL

Sponsorship create and sponsorship view currently have different role lists.

This is a permission-design inconsistency that requires a formal decision, not a blind code change.

## 6.6 Audit logging — VERIFIED

Sponsorship create/status changes write audit records containing user/entity/action and before/after values where implemented.

---

# 7. Supervisors and Nannies

## 7.1 Supervisor scope — VERIFIED

The sponsorship matching implementation applies supervisor letter/gender rules and validates scope during POST processing.

## 7.2 Nanny/group assignments — VERIFIED/IMPLEMENTED-PARTIAL

The repository contains nanny/group assignment structures and the group workflow resolves the active nanny assigned to a group.

## 7.3 Accountant-to-nanny assignment — VERIFIED

The user-management page maintains `accountant_nanny_assignments` with a uniqueness constraint on accountant/nanny pairs.

This supports an organizational division of work between accountant staff and assigned nannies.

## 7.4 Final scope matrix — OPEN DECISION

The exact rule for which accountant staff can see/manage which nanny/group/family records should be formalized into the final permission matrix.

---

# 8. Monthly Disbursement Workflow

## 8.1 Workflow — VERIFIED/IMPLEMENTED-PARTIAL

The repository implements the intended operational chain:

`Family Verification → Group Submission → Accountant Review/Batch Creation → Transfer → Nanny Confirmation → Return/Reversal → Reconciliation/Closure`

## 8.2 Family verification — VERIFIED

`lib_group_workflow.php` defines complete verification as all three being true:

- orphan verification;
- mother/contact verification;
- bank verification.

A family is eligible for submission only when all three are verified.

## 8.3 Batch creation — VERIFIED

The group-disbursement workflow creates `monthly_disbursements` from fully verified families and creates `disbursement_items` with initial status `pending`.

The batch is tied to month, nanny and group.

## 8.4 Transfer — VERIFIED

The transfer workflow supports moving an approved/pending-approval batch to `transferred`, recording transfer time and transfer receipt information.

## 8.5 Nanny confirmation — IMPLEMENTED-PARTIAL

`modules/accounting/disbursements.php` contains the nanny-facing receipt flow and item-level status handling.

The intended item transition is:

`pending → paid`

with confirmation timestamp and receipt evidence.

## 8.6 Partial confirmation and return — VERIFIED

The implementation contains a controlled partial-close workflow:

- identifies pending items;
- calculates their total amount;
- requires a return receipt;
- creates a reversal journal entry;
- debits the cash account;
- credits the original expense account;
- marks pending items as `returned`;
- stores return reason/date/user;
- stores return receipt;
- stores reversal journal ID;
- changes the monthly disbursement to `returned`;
- records returned amount;
- closes the active orphan group.

This directly supports the organizational requirement that unspent money be returned to the organization's cash/safe and evidenced.

## 8.7 Important implementation inconsistency — LEGACY/TECHNICAL DEBT

`lib_group_workflow.php` still contains functions that accept a raw `$pdo` argument and call `log_group_workflow_action()`.

`modules/accounting/disbursements.php` contains a self-contained replacement `log_group_action()` because the older pattern expected `$GLOBALS['pdo']`, which was not reliably populated.

Therefore the centralized workflow library is not currently the sole authoritative execution path.

**Rule:** do not refactor these functions casually. First identify every caller and establish one authoritative workflow implementation.

## 8.8 State matrix — IMPLEMENTED-PARTIAL

Observed monthly-disbursement statuses include:

- `pending_approval`
- `approved`
- `transferred`
- `received`
- `returned`
- `voided`
- `cancelled`

Observed item status includes at least:

- `pending`
- `paid`
- `returned`

The final allowed-transition matrix should be explicitly documented from the complete code + authoritative SQL before further workflow expansion.

---

# 9. Accounting and Financial Controls

## 9.1 Chart of accounts — VERIFIED

The financial-manager dashboard operates against `accounts` and journal balances. It explicitly identifies cash/bank/electronic-wallet accounts by account codes including:

- `1100` — cash/treasury;
- `1200` — bank;
- `1300` — electronic wallet.

Project financing logic also uses expense account `5110` in the inspected approval workflow and disbursement logic includes configurable/fallback expense account handling.

## 9.2 Journal architecture — VERIFIED

Financial calculations use:

`journal_entries → journal_lines → accounts`

with posted journal entries included in balance calculations.

The `entry_code` field is used as the journal-entry identifier/code, while `journal_lines.entry_id` establishes the line-to-entry relationship.

## 9.3 Financial manager oversight — VERIFIED

The financial-manager dashboard includes:

- treasury balances;
- monthly income/outgoing flow;
- disbursement status monitoring;
- open transferred batches;
- project budget review;
- project funding allocation validation;
- audit logging of project budget decisions.

The inspected project-budget approval path requires total funding allocations to equal the proposed budget and checks available balances before approval.

## 9.4 Segregation of duties — IMPLEMENTED-PARTIAL

The application distinguishes financial-manager, accountant and general-manager responsibilities in multiple workflows.

However, because several modules implement role lists independently, the final separation-of-duties matrix is not yet one centralized authoritative policy.

## 9.5 Financial tables — OPEN / SCHEMA AUDIT REQUIRED

The codebase references multiple financial structures including:

- `accounts`
- `journal_entries`
- `journal_lines`
- `transactions`
- `monthly_disbursements`
- `disbursement_items`
- `project_funding_allocations`
- `project_expenses`
- project budget tables.

The authoritative SQL dump must determine which structures are canonical and which are historical/overlapping.

---

# 10. Projects, Donations and Transactions

## 10.1 Projects — VERIFIED/IMPLEMENTED-PARTIAL

Projects have a dedicated domain library and support:

- project creation;
- portfolio view;
- project/team visibility;
- section-level permissions;
- lifecycle state management;
- supervisor assignments;
- project budgets;
- funding allocations;
- project expenses;
- beneficiaries;
- closure totals;
- audit logging.

## 10.2 Project lifecycle — VERIFIED

Observed lifecycle statuses include:

- `planned`
- `active`
- `under_review`
- `completed`
- `cancelled`
- `closed`
- `reopened`

The application maintains a dedicated `project_lifecycle` record in addition to legacy status information in `other_projects`.

This dual-status architecture must remain synchronized and should be treated as technical debt until the final schema/workflow decision is made.

## 10.3 Project permissions — VERIFIED

`project_lib.php` provides section-specific authorization for:

- general information;
- finance;
- operations;
- documents;
- closure;
- team.

Project team membership can grant section-level access, while executives and project-management roles receive broader access.

## 10.4 Project financial totals — VERIFIED

The project library calculates:

- approved budget;
- funding allocations;
- donations/transactions;
- total funded;
- total expenses;
- variance;
- variance percentage;
- residual amount.

## 10.5 Donations/transactions — IMPLEMENTED-PARTIAL

Project reports and project totals consume posted `transactions` records as donation/funding data.

A complete organization-wide transaction/donation lifecycle is not claimed here until the relevant transaction entry screens and authoritative schema definitions are reconciled.

---

# 11. HR

## 11.1 HR module — VERIFIED/IMPLEMENTED-PARTIAL

`modules/hr/index.php` is restricted to:

- `hr_manager`
- `hr_staff`
- `admin`

The dashboard references:

- employees;
- attendance;
- leaves;
- contracts;
- payroll.

It calculates active employees, pending leave requests, expiring contracts, active employee salary totals, and remote/onsite attendance counts.

## 11.2 HR workflow maturity — IMPLEMENTED-PARTIAL

The repository contains a functional HR module, but the inspected dashboard alone does not prove that every HR lifecycle is fully controlled with the same auditability and approval rigor as sponsorship/disbursement workflows.

Final HR audit should verify:

- employee lifecycle;
- leave approval chain;
- attendance correction authority;
- payroll preparation/approval/payment separation;
- contract changes;
- HR document protection;
- audit coverage.

No HR behavior should be redesigned merely from the dashboard.

---

# 12. Internal Messaging and Attachments

## 12.1 Messaging — VERIFIED

The messaging subsystem supports internal communication and attachments.

## 12.2 Attachments — VERIFIED

The attachment endpoint:

- requires authentication;
- checks message access;
- validates CSRF on state-changing operations;
- validates extension/MIME type;
- limits attachment size to 10 MB;
- generates random stored names;
- stores metadata in `message_attachments`;
- deletes physical files when attachment records are deleted;
- cleans up a physical file if DB insertion fails.

## 12.3 JSON integrity bug — RESOLVED

A historical issue appended search UI JavaScript to JSON API responses. `config/search_permissions.php` now limits the shutdown HTML injection to the search route itself.

This fix is critical and should not be reverted. API endpoints must continue to return strict JSON without appended HTML/script output.

---

# 13. Documents and File Storage

## 13.1 Protected storage — VERIFIED

`storage/.htaccess` contains:

`Require all denied`

This means direct browser access to storage files is intentionally blocked.

## 13.2 Security principle — VERIFIED/BUSINESS RULE

Protected documents should not become publicly browsable merely to make an `<img>` or download link work.

Files should be served through authenticated/authorized endpoints when protection is required.

## 13.3 Avatar issue — KNOWN IMPLEMENTATION ISSUE

The previously observed avatar problem was caused by the protected storage policy returning HTTP 403 for direct avatar URLs.

The correct architectural fix is not to remove storage protection globally. It is to provide an authorized file-serving path or a deliberately public/non-sensitive storage area for avatars.

## 13.4 Upload policy — IMPLEMENTED-PARTIAL

The disbursement workflow demonstrates good patterns including MIME inspection, size limits, generated names and controlled storage. These patterns should become the common upload standard across the system.

---

# 14. Search and Authorization

## 14.1 Search authorization — VERIFIED

`config/search_permissions.php` centralizes allowed search domains by role.

Supported search domains include:

- families;
- sponsors;
- sponsorships;
- payments.

The `all` search option is permitted only for roles that are allowed every search domain.

## 14.2 Tamper resistance — VERIFIED

The search route checks the requested type server-side and returns HTTP 403 for unauthorized types. UI filtering is supplemental and not authoritative.

## 14.3 Record-level visibility — IMPLEMENTED-PARTIAL

The search policy explicitly distinguishes domain-level authorization from record-level visibility. The latter remains dependent on the individual search queries and therefore requires continued module-specific verification.

---

# 15. Reports and PDF Generation

## 15.1 Reports — VERIFIED

The reports center is role-filtered and includes categories for:

- overview;
- financial;
- sponsorship;
- operational;
- HR;
- families without sponsorship.

The report center supports date ranges and uses role-specific tab visibility.

## 15.2 Reporting architecture — IMPLEMENTED-PARTIAL

The report center is functional, but there is not yet one formal reporting catalog defining each report's:

- authoritative source tables;
- access roles;
- calculation definitions;
- date semantics;
- reconciliation rules.

This should be documented before the system is relied upon for official organizational reporting.

## 15.3 PDF — VERIFIED

The repository contains TCPDF and existing PDF/report functionality. PDF outputs should be treated as generated views of authoritative application data, not as a separate data store.

---

# 16. Audit Logging and Accountability

## 16.1 Audit viewer — VERIFIED

`modules/logs/audit.php` is restricted to `admin` and `general_manager`.

It supports filtering by:

- action;
- user;
- date range;

and displays entity, old values, new values and IP address.

## 16.2 Coverage — IMPLEMENTED-PARTIAL

Audit logging is demonstrably present in:

- login;
- sponsorship changes;
- project lifecycle actions;
- project budget approvals/rejections;
- disbursement return/reversal actions;
- group verification changes.

However, the repository does not yet demonstrate universal audit coverage for every sensitive CRUD and authorization operation.

## 16.3 Required final policy — OPEN DECISION

The organization should define which events are legally/operationally mandatory to retain and the retention period. At minimum, high-risk financial, beneficiary, sponsorship, permission and document actions should remain attributable.

---

# 17. Cross-Module Organizational Workflow

The current implementation is moving toward a real organizational workflow rather than a simple CRUD application.

The major business chain can be represented as:

```text
User / Role
   │
   ├── Family & Child Case Management
   │       │
   │       └── Sponsorship
   │               │
   │               └── Monthly Commitment
   │
   ├── Supervisor / Nanny Verification
   │       │
   │       └── Group Submission
   │               │
   │               └── Accountant Batch
   │                       │
   │                       ├── Financial Review
   │                       ├── Transfer
   │                       ├── Nanny Receipt Confirmation
   │                       └── Return / Reversal / Reconciliation
   │
   ├── Projects
   │       ├── Approval
   │       ├── Funding
   │       ├── Execution
   │       └── Closure
   │
   ├── HR
   │
   ├── Internal Messaging
   │
   └── Reports / Audit
```

This architecture supports the intended organizational direction, but the final permission/separation-of-duties matrix must bind the workflows together explicitly.

---

# 18. Security Findings Register

| ID | Finding | Classification | Priority | Action |
|---|---|---|---|---|
| SEC-01 | Session cookie `secure=false` | VERIFIED | High for production | Enable under HTTPS deployment |
| SEC-02 | No explicit session ID regeneration after login | IMPLEMENTED-PARTIAL | High | Add controlled session regeneration |
| SEC-03 | Password minimum is six characters | VERIFIED | Medium | Define organizational password policy |
| SEC-04 | Storage is globally denied for direct access | VERIFIED | Correct security posture | Preserve; use authorized serving endpoints |
| SEC-05 | Upload security varies by module | IMPLEMENTED-PARTIAL | High | Standardize MIME/size/name/storage validation |
| SEC-06 | Audit coverage varies by module | IMPLEMENTED-PARTIAL | High | Establish mandatory audit-event matrix |
| SEC-07 | Role checks are distributed across modules | IMPLEMENTED-PARTIAL | High | Formalize permission matrix and converge implementation |
| SEC-08 | Development error display is enabled | VERIFIED | High for production | Disable displayed errors in production |

---

# 19. Technical Debt and Inconsistency Register

| ID | Area | Finding | Classification |
|---|---|---|---|
| TD-01 | Sponsorship | `sponsorship_children` constant exists but direct implementation uses `sponsorships.child_id` | LEGACY/UNVERIFIED |
| TD-02 | Disbursement | `lib_group_workflow.php` contains raw-PDO workflow functions while main page contains self-contained replacements | LEGACY/UNVERIFIED |
| TD-03 | Authorization | Role lists are distributed and differ by page/action | IMPLEMENTED-PARTIAL |
| TD-04 | Projects | Legacy project status and dedicated lifecycle status coexist | IMPLEMENTED-PARTIAL |
| TD-05 | Schema | Some pages perform runtime schema creation/alteration | IMPLEMENTED-PARTIAL |
| TD-06 | Documents | Multiple document/storage concepts require final schema reconciliation | OPEN |
| TD-07 | Financial model | Multiple transaction/funding/disbursement structures require authoritative-source mapping | OPEN |
| TD-08 | Reports | No single formal report-source/calculation catalog | OPEN |
| TD-09 | Audit | Sensitive actions are not yet proven universally audited | IMPLEMENTED-PARTIAL |
| TD-10 | Deployment | README development URL differs from current configured development port | DOCUMENTATION |

---

# 20. Database Schema Audit Status

## 20.1 Authoritative artifact

The authoritative repository database backup is:

`database/ahl_el_kheir.sql`

Current repository metadata confirms the file exists on the audit branch and is approximately 6.3 MB.

## 20.2 Important limitation

The complete SQL dump is too large for the available repository-file reader to expose as a single textual response. Therefore this audit does **not** falsely claim that every table, column, index, trigger and foreign key in the dump has been manually catalogued.

The code-level audit has nevertheless verified many schema relationships through live SQL usage, including the major family, child, sponsorship, group, disbursement, journal, project and user structures.

## 20.3 Final schema work required before production freeze

Use the complete `database/ahl_el_kheir.sql` file as the authoritative schema source to produce a final catalog containing:

- table name;
- purpose;
- primary key;
- important columns;
- nullable/required fields;
- foreign keys;
- indexes;
- enum/status values;
- monetary fields;
- audit fields;
- duplicate/legacy candidates.

Special attention must be given to:

`families`, `family_children`, `sponsors`, `sponsorships`, `sponsorship_children`, `orphan_groups`, `group_children`, `monthly_disbursements`, `disbursement_items`, `nanny_family_verifications`, `nanny_group_assignments`, `transactions`, `accounts`, `journal_entries`, `journal_lines`, `project_*`, `users`, `roles`, `permissions`, `audit_log`, document tables and HR tables.

This is a schema-reconciliation task, not a reason to modify application code prematurely.

---

# 21. Final Organizational Permission Matrix — Required Target

The system should ultimately document permissions by role and action rather than only by page.

Required action categories:

- view;
- create;
- edit;
- delete;
- approve;
- reject;
- submit;
- transfer;
- confirm;
- return;
- reverse;
- reconcile;
- export;
- administer.

High-risk separation of duties should be explicitly addressed for:

- creating financial records;
- approving budgets;
- transferring money;
- confirming receipt;
- recording returns;
- posting/reversing journals;
- changing beneficiary/sponsorship status;
- changing user roles;
- accessing protected documents.

---

# 22. Final Development Rules for Future AI/Developer Work

Any future change to this repository should follow these rules:

1. Inspect the current repository before proposing code.
2. Identify the authoritative table and relationship before writing SQL.
3. Identify the authoritative status field before adding a new status.
4. Reuse existing helpers and architecture where appropriate.
5. Do not introduce OOP/ORM/frontend frameworks into areas explicitly designed as procedural PHP/Vanilla JS.
6. Do not create duplicate tables merely because a requested concept is missing from one module.
7. Do not remove storage protection to solve a file-display problem.
8. Do not bypass server-side authorization because a UI control is hidden.
9. Keep API endpoints strict: JSON APIs must return JSON only.
10. Use transactions for multi-step financial state changes.
11. Record audit evidence for sensitive workflow transitions.
12. For financial reversals, preserve the original account relationship whenever possible rather than hard-coding a replacement account.
13. After changes, review the diff before commit.
14. Keep the application baseline stable; documentation/audit work belongs on `documentation/system-analysis-v1` until approved.

---

# 23. Final Audit Conclusion

The repository is materially more mature than a basic CRUD charity application. It already contains organizational concepts, child-level sponsorship, supervisor scope controls, automated matching, monthly verification/disbursement workflows, financial journals, project governance, HR, messaging, reports and audit logging.

The most important remaining challenge is **consistency and governance**, not simply adding more screens.

The system now needs a controlled convergence toward:

```text
Authoritative Data Model
        ↓
Authoritative Permission Matrix
        ↓
Authoritative Workflow State Machines
        ↓
Authoritative Financial Posting Rules
        ↓
Consistent Audit Coverage
        ↓
Secure Document/File Serving
        ↓
Reconciled Reporting
        ↓
Production Deployment Controls
```

The application should not undergo a broad rewrite before these authoritative definitions are finalized.

The correct engineering strategy is incremental convergence: preserve working functionality, eliminate duplicate/legacy paths one at a time, strengthen authorization and auditability, and keep financial state transitions transaction-safe.

---

# Appendix A — Verified Core Relationships

```text
Sponsor
   │
   └── sponsorships
          │
          └── child_id
                │
                └── family_children
                       │
                       └── family_id
                              │
                              └── families

orphan_groups
   │
   └── group_children
          │
          └── family_children
                 │
                 └── families

monthly_disbursements
   │
   └── disbursement_items
          │
          └── family_id

Financial Posting
   │
   └── journal_entries
          │
          └── journal_lines
                 │
                 └── accounts
```

---

# Appendix B — Disbursement State Model Observed in Code

```text
Group / Verification

pending
   ↓
submitted
   ↓
approved
   ↓
transferred
   ├──────────────→ received / closed path
   │
   └── partial confirmation
           ↓
      pending items
           ↓
        returned
           ↓
   reversal journal + receipt
           ↓
         closure
```

The exact final state transition matrix remains a controlled documentation task because multiple legacy functions still coexist.

---

# Appendix C — Repository Documentation Set

The audit branch now contains:

- `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` — main system analysis / functional and technical specification.
- `docs/AHL_EL_KHEIR_REPOSITORY_AUDIT.md` — Phase 1 repository implementation audit and verification register.
- `docs/AHL_EL_KHEIR_COMPLETE_REPOSITORY_AUDIT.md` — this consolidated cross-module audit.

The branch also removes the obsolete task-specific messaging SQL files that had already been superseded by the authoritative complete database backup.

---

**Audit status:** Consolidated cross-module audit completed to the level supported by repository-accessible source evidence.  
**Application code changed by this audit:** No.  
**Application baseline protected:** Yes.  
**Next implementation phase:** User review of documentation, followed by targeted decisions/changes rather than a broad rewrite.
