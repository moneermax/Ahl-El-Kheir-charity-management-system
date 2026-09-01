# Ahl El Kheir Charity Management System
## Repository Implementation Audit — Phase 1

**Document type:** Repository audit / implementation verification / documentation correction register  
**Audit branch:** `documentation/system-analysis-v1`  
**Baseline under review:** `b3267ba306c81b25cff06b32ff8f5719fa7565ac`  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Audit status:** Phase 1 — verified implementation observations  
**Purpose:** Validate the system-analysis document against the current repository and explicitly identify confirmed facts, implementation details, and unresolved discrepancies.

---

# 1. Audit Method

This audit follows the project's required development method:

`Inspect → Diagnose → Explain → Patch minimally → Test → Review diff → Commit → Push`

For documentation work, the implementation is inspected first and the documentation is changed only where the repository provides evidence.

The following sources were inspected during Phase 1:

- `README.md`
- `config/config.php`
- `config/database.php`
- `config/session.php`
- `modules/sponsorships/create.php`
- `modules/sponsorships/view.php`
- `modules/messages/attachment.php`
- `database/ahl_el_kheir.sql` repository presence and metadata
- `database/messaging.sql`
- `database/messaging_attachments.sql`
- repository/module directory structure
- existing `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`

The audit deliberately does **not** treat a filename, old chat statement, or conceptual design as proof of an implemented relationship.

---

# 2. Verified Repository Structure

The current repository contains, among other top-level areas:

- `config/` — application configuration and shared infrastructure.
- `database/` — database scripts and SQL artifacts.
- `modules/` — functional application modules.
- `assets/` — frontend assets.
- `includes/` — shared UI/includes.
- `dashboard/` — dashboard area.
- `TCPDF/` — PDF-generation library.
- `docs/` — project documentation.
- `storage/` — runtime file storage used by the application.

The module tree currently includes functional areas such as accounting, administration, departments, deputy management, families, HR, logs, messages, projects, reports, search, settings, sponsors, and sponsorships. Additional modules should continue to be inventoried from the repository rather than inferred from the business plan.

---

# 3. Verified Technical Foundation

## 3.1 Application configuration

`config/config.php` confirms the following implementation characteristics:

- `APP_NAME = Ahl El Kheir`.
- Arabic application name is `أهل الخير`.
- Environment is currently configured as `development`.
- `APP_DIR` is calculated from the application directory rather than hard-coded to one Windows path.
- `APP_BASE_PATH` is calculated dynamically.
- `APP_URL` is constructed dynamically from protocol, host, and base path.
- Database access is configured through host, port, database name, user, and password constants.
- Application table-name constants are centralized.
- Backup and log directories are under the application storage area.
- The configured PHP timezone is `Africa/Khartoum`.
- Development mode enables displayed PHP errors and `E_ALL` reporting.

### Documentation consequence

The application URL should be described as dynamically generated, not as a permanent `localhost:8081` constant. The exact README URL is environment-specific and currently differs from the documented development URL.

The timezone must be treated as an explicit deployment/business setting. It should be confirmed with the organization before production deployment rather than silently changed as part of unrelated work.

---

# 4. Database Access Layer — Verified

`config/database.php` confirms a procedural PDO database layer.

The shared layer provides:

- `db()` — singleton-style PDO connection within the request.
- `dbFetchOne()` — one-row query helper.
- `dbFetchAll()` — multi-row query helper.
- `dbExecute()` — parameterized write helper.
- `dbLastInsertId()` — last inserted ID helper.

The PDO configuration explicitly uses:

- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
- `PDO::ATTR_EMULATE_PREPARES => false`

This confirms that prepared PDO statements are part of the current application infrastructure.

### Architectural implication

The system is not using an application-wide ORM. Database operations are written as procedural PHP using PDO and SQL statements.

---

# 5. Session Layer — Verified

`config/session.php` confirms a procedural compatibility layer implemented through a `Session` class with static methods.

The current session API includes:

- `Session::start()`
- `Session::isLoggedIn()`
- `Session::getUserRole()`
- `Session::getUserId()`
- `Session::getUserName()`
- flash-message helpers

The session cookie is configured with:

- `httponly = true`
- `samesite = Lax`
- `secure = false`
- cookie path `/`

### Security observation

`secure = false` is acceptable for the current non-HTTPS development configuration but must be reviewed before production. Production HTTPS deployment should use secure cookies.

This is a documented deployment-readiness item, not a reason to change the current development environment blindly.

---

# 6. Sponsorship Model — Critical Verification

The repository provides strong direct evidence that the current sponsorship model is **child-level**.

`modules/sponsorships/create.php` creates records using:

- `sponsor_id`
- `child_id`
- `monthly_amount`
- `currency_code`
- `start_date`
- `status`
- `notes`
- `created_by`

The same file validates active sponsorships using `sponsorships.child_id` and updates the corresponding `family_children.match_status`.

`modules/sponsorships/view.php` joins:

`sp.child_id → family_children.id → families.id`

This establishes the implemented relationship as:

`Sponsor → Sponsorship → Child → Family`

not:

`Sponsor → Family`

and not a sponsorship-to-multiple-children model in the current create/view workflow.

## 6.1 Important discrepancy with legacy configuration

`config/config.php` still defines:

`TABLE_SPONSORSHIP_CHILDREN = sponsorship_children`

However, the current sponsorship create/view implementation directly uses `sponsorships.child_id` and does not use a sponsorship-child pivot for the core sponsorship operation inspected in Phase 1.

Therefore:

> `sponsorship_children` must currently be treated as **legacy/unverified** until the database schema and all references are audited.

No new code should be written against that pivot merely because the constant exists.

## 6.2 Business consequence

The child-level model correctly supports the rule that siblings in the same family may have different sponsors and different sponsorship records.

This is a core domain rule and should remain explicit in the main system documentation.

---

# 7. Sponsorship Lifecycle — Verified Implementation

`modules/sponsorships/view.php` currently implements these sponsorship states/actions:

- `active`
- `paused`
- `completed`
- `cancelled`

Observed transitions include:

`active → paused`

`paused → active`

`active/paused → completed`

`active/paused → cancelled`

The implementation also updates child matching state:

- completing a sponsorship returns the child to `unmatched`.
- cancelling a sponsorship marks the child as `lost_sponsor`.

The create page's matching engine considers children with:

- `unmatched`
- `waiting_list`
- `lost_sponsor`

and excludes children already having an active/paused sponsorship.

This is stronger evidence than the earlier generic statement that the sponsorship lifecycle still required definition.

### Documentation correction

The main system-analysis document should treat the above sponsorship states as **implemented states**, while still distinguishing them from the separate monthly-disbursement state machine.

---

# 8. Sponsorship Auto-Matching — Verified

The sponsorship creation workflow contains an auto-matching engine.

Observed priority order:

1. Critical cases (`is_critical`).
2. Children who lost a sponsor (`lost_sponsor`).
3. Older application date / FIFO ordering.
4. Child ID as a deterministic final tie-breaker.

The engine also supports supervisor scope using letter/gender rules derived from `supervisor_letters` and sponsor ownership/scope.

The create workflow validates scope again during POST processing rather than trusting only the UI suggestion list.

This is an important example of server-side authorization/business validation and should be retained in the system documentation.

---

# 9. Sponsorship Authorization — Verified Gap to Document

The sponsorship create page currently permits only these normalized roles:

- `admin`
- `vice_general_manager`
- `supervisor`

The sponsorship view page permits:

- `admin`
- `vice_general_manager`
- `general_manager`
- `supervisor`
- `accountant`

This means the current implementation does **not** expose one uniform sponsorship permission matrix across create/view/manage operations.

This should be treated as an authorization-design checkpoint rather than silently interpreted as a bug. The organization must decide which roles should create, view, pause, resume, complete, cancel, and record sponsorship payments.

---

# 10. Sponsorship Audit Logging — Verified

The inspected sponsorship create/view workflows write to `audit_log` when sponsorships are created or their status changes.

Recorded audit information includes, where implemented:

- user ID
- action
- entity type
- entity ID
- old values
- new values
- IP address
- user agent

This confirms that auditability is not merely a future design idea; at least the sponsorship workflow already implements it.

The next audit phase must determine coverage across the remaining high-risk modules.

---

# 11. Messaging Attachments — Verified Current Design

`modules/messages/attachment.php` confirms an operational attachment API.

The endpoint:

- requires login;
- returns JSON;
- supports attachment listing;
- supports attachment deletion;
- supports upload;
- verifies CSRF for state-changing POST actions;
- validates message access through `messaging_user_can_read()`;
- limits attachment size to 10 MB;
- validates extensions and MIME types;
- generates random stored names;
- stores attachments under `storage/message_attachments`;
- records metadata in `message_attachments`;
- removes the physical file when deleting an attachment;
- removes the physical file if database insertion fails.

The allowed file categories include PDF, Office documents, text files, common image formats, and ZIP files, subject to MIME validation.

The implementation therefore supports the previously documented conclusion that the attachment subsystem is currently functional and should not be unnecessarily rewritten.

---

# 12. Messaging API Response Integrity — Resolved Historical Issue

The attachment endpoint explicitly buffers output and uses a JSON-only response helper before exiting.

This aligns with the previously resolved issue where unrelated search UI injection contaminated JSON responses.

The route-specific search-injection rule must remain intact because API clients depend on strict response formats.

---

# 13. Database Artifacts — Verified Presence

The repository contains:

- `database/ahl_el_kheir.sql`
- `database/messaging.sql`
- `database/messaging_attachments.sql`

The primary database dump is large and must be treated as the source artifact for a dedicated schema audit. Phase 1 confirms its presence but does not claim that every table, column, index, trigger, and foreign key has been fully catalogued.

Therefore, a full schema inventory remains a Phase 2 task.

---

# 14. Current Documentation Corrections Required

The main system-analysis document should be progressively corrected using these findings:

### Correction A — Sponsorship relationship

State explicitly that the current implementation uses `sponsorships.child_id`.

### Correction B — Sponsorship pivot constant

Do not present `sponsorship_children` as an active authoritative relationship until the schema/reference audit proves it is active.

### Correction C — Sponsorship lifecycle

Document `active`, `paused`, `completed`, and `cancelled` as currently implemented sponsorship states.

### Correction D — Matching workflow

Document the current auto-matching priority and supervisor scope behavior as implemented functionality.

### Correction E — Authorization

Record that sponsorship create and sponsorship view currently have different role lists. Final organizational permissions remain to be decided/standardized.

### Correction F — Configuration

Document the dynamic URL mechanism, development environment, and current timezone as verified implementation details.

### Correction G — Session security

Record `secure=false` as a development configuration that requires production review.

### Correction H — Database audit status

Distinguish between the presence of the SQL dump and a fully verified schema catalog. The latter has not yet been completed.

---

# 15. Phase 2 Audit Plan

The next repository audit should be performed in the following order.

## Phase 2A — Database schema inventory

Build a verified table catalog containing:

- table name
- purpose
- primary key
- important columns
- foreign keys
- indexes
- status/state columns
- monetary columns
- audit columns
- legacy/duplicate candidates

Special focus:

- families
- family_children
- sponsors
- sponsorships
- sponsorship_children
- orphan_groups
- group_children
- monthly_disbursements
- disbursement_items
- transactions
- journal_entries
- journal_lines
- donations
- users
- roles
- permissions
- audit_log

## Phase 2B — Disbursement workflow

Trace every page/action involved in:

`Preparation → Verification → Review → Transfer → Confirmation → Return → Reconciliation → Closure`

Document the exact status values and allowed transitions from source code and schema.

## Phase 2C — Financial architecture

Determine which tables are authoritative for:

- donations
- transactions
- safe/cash balances
- journals
- disbursement transfers
- returned money
- reversals

Identify any overlapping legacy structures.

## Phase 2D — Role/permission matrix

Build a matrix for every important module and action:

- view
- create
- edit
- delete
- approve
- transfer
- confirm
- reverse
- reconcile
- export
- administer

## Phase 2E — HR and organizational structure

Audit actual HR tables/pages/workflows and compare them with the intended organizational hierarchy.

## Phase 2F — Security audit

Review:

- authentication
- authorization
- CSRF
- uploads
- protected storage
- download endpoints
- session configuration
- error handling
- audit logging
- direct endpoint access

---

# 16. Documentation Status Model

Future documentation should use these labels:

### VERIFIED
Directly confirmed by current repository code/schema.

### IMPLEMENTED — PARTIAL
Implemented in code, but not yet fully verified across all related modules.

### BUSINESS RULE
Explicitly agreed organizational behavior, even if implementation is incomplete.

### INFERRED
Reasonable interpretation that still requires confirmation.

### LEGACY / UNVERIFIED
Historical, unused, duplicated, or uncertain implementation that must not be treated as authoritative.

### OPEN DECISION
Requires an organizational or architectural decision before implementation.

This classification is intended to prevent historical assumptions from becoming accidental system requirements.

---

# 17. Current Audit Conclusion

Phase 1 confirms that the repository has a more mature implementation than a simple CRUD description suggests.

Important verified characteristics include:

- procedural PDO infrastructure;
- centralized dynamic application configuration;
- session/authentication infrastructure;
- child-level sponsorship records;
- sponsorship lifecycle management;
- automatic sponsorship matching;
- supervisor scope controls;
- sponsorship audit logging;
- operational messaging attachments;
- CSRF-protected attachment mutations;
- controlled attachment storage;
- route-safe JSON API behavior.

At the same time, several areas must remain explicitly open until deeper inspection is complete, especially the complete database schema, monthly disbursement state machine, financial architecture, organizational role matrix, and HR workflow.

The correct next step is therefore **not** a broad rewrite of the application. The correct next step is a controlled repository audit that converts each important domain from conceptual description into verified implementation documentation.

---

# Appendix A — Key Verified Relationships

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
```

The inspected sponsorship implementation directly follows this relationship.

---

# Appendix B — Key Verified Sponsorship State Flow

```text
                 ┌──────────────┐
                 │    active    │
                 └──────┬───────┘
                        │
              ┌─────────┴─────────┐
              ▼                   ▼
        ┌───────────┐       ┌────────────┐
        │  paused   │       │ cancelled  │
        └─────┬─────┘       └────────────┘
              │
              ▼
        ┌───────────┐
        │  active   │
        └─────┬─────┘
              │
              ▼
        ┌────────────┐
        │ completed  │
        └────────────┘
```

Cancellation also marks the child as `lost_sponsor`; completion returns the child to `unmatched`.

---

# Appendix C — Handoff Rule

When a future developer or AI assistant encounters a conflict between:

1. an old conversation;
2. an old documentation statement;
3. a filename/constant suggesting a relationship; and
4. the current executable source/schema;

priority should be given to the current executable implementation and actual database schema, followed by explicit current business decisions.

Historical material should be preserved as context but clearly labeled as historical or unverified.
