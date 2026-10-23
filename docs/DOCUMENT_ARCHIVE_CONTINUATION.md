# Document Archive — Continuation and Design

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Baseline:** 2026-10-23  
**Status:** Discovery and architecture design in progress. No application code or database schema has been changed for this task.

## Goal

Create a reliable, searchable and auditable archive for receipts and uploaded documents across the existing procedural PHP application. Preserve the current business modules, their record relationships, authorization rules, and established file-serving URLs while the archive is introduced gradually.

## Evidence inspected on main

Repository HEAD at the start of this work: `897cf344892669a64111879dc03d9836d8ea10fb`.

Canonical project documents and the operations/security guide were inspected, along with the architecture/data-model reference, documentation index, HR contract continuation, and representative file upload/serving sources.

Confirmed examples of distinct current document domains include:

- Family documents: `modules/families/documents.php`, `modules/families/document_file.php`, `modules/families/orphan_document_file.php`.
- Project documents and payment evidence: `modules/projects/serve_project_document.php`, `modules/projects/project_payment_receipt.php`, and project storage under `storage/documents/projects/`.
- HR contracts: `modules/hr/lib_contract_salary.php`, `modules/hr/view_contract.php`, and `storage/hr_contracts/`.
- Salary-advance receipts/evidence: `modules/hr/salary_advance_receipt.php` and related salary-advance evidence handlers.
- Transaction and sponsor-payment receipts: `modules/transactions/receipt_file.php`, `modules/transactions/receipt_sp.php`, and related transaction/sponsor-payment flows.
- Disbursement and Fina receipts: `modules/accounting/disbursement_receipt.php`, `modules/accounting/fina_receipt.php`, and `modules/accounting/serve_receipt.php`.
- Message attachments: `modules/messages/attachment.php` and `modules/messages/download_attachment.php`.

The repository has multiple storage roots and naming schemes rather than one common document model. The `storage/.htaccess` currently contains `Require all denied`; project storage and receipt storage also have local access-control/rewrite rules. Some modules already use authenticated serving endpoints with record-scope checks and canonical-path checks. These controls must be preserved and tested against the real Apache/hosting environment; source inspection alone does not prove deployment behavior.

### Specific implementation risks observed

- `modules/families/documents.php` contains runtime `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE` statements for `family_documents`. This conflicts with the project rule that schema changes belong in explicit migrations. Do not casually rewrite the module during discovery; include its schema contract in the migration plan and remove runtime DDL only in a justified, separately verified change.
- `modules/transactions/receipt_file.php` contains runtime `ALTER TABLE` statements for `transactions`. This is another existing schema-management risk directly adjacent to receipt retrieval; do not run it as part of archive discovery or use it to infer the authoritative schema.
- File-serving code is not uniform. Examples differ in role checks, record-scope checks, path-prefix enforcement, MIME validation, cache headers, and response filenames. A central archive must not become a generic path-serving endpoint or bypass existing business authorization.
- Some existing storage paths are stored in business tables and consumed by module-specific handlers. Moving files before all references and serving routes are understood could break historical evidence.

This is a source-level finding only. No local Apache behavior, live database schema, upload acceptance, backup restoration, or hosting capabilities were runtime-tested in this discovery pass.

## Recommended architecture

### 1. Preserve the business modules as the source of truth

Accounting, HR, families, projects, sponsorships, Fina and messaging continue to own their business workflows and permissions. The archive is a cross-module catalogue and lifecycle layer; it must not replace transaction, employee, family, project or message records.

The archive must not grant access merely because a document appears in a search result or because a caller knows an archive ID. Every read/search/download must resolve the document to its registered source record and invoke that domain's existing record-scope authorization.

### 2. Introduce the archive in two layers

**Archive catalogue (database):** document identity, category, original display name, detected MIME type, byte size, cryptographic checksum, uploader and upload timestamp, source-domain/type and source-record reference, current/version status, retention classification, and integrity/reconciliation state.

**File storage:** opaque generated storage names; no user-controlled path segments. A logical document can have immutable versions. Replacing a file creates a new version and preserves the prior version and audit history; it does not silently overwrite bytes. SHA-256 checksums support integrity checks and duplicate warnings, not automatic merging or deletion.

Because source records span unrelated tables, any polymorphic source reference must be protected by an explicit application-level allowlist and domain adapter. Never interpolate a submitted table name into SQL. Do not invent foreign keys or schema details: inspect the actual schema and migration conventions before finalizing table definitions.

### 3. Keep physical files in place initially

Use a **register-first, move-later** migration strategy. Existing files remain at their current paths while their metadata and business references are inventoried and reconciled. Initial catalogue integration should reference existing module records and existing serving handlers; it must not bulk-move files or rewrite historical paths.

For new uploads, first make upload validation, generated storage names, checksum capture, metadata registration, and error/rollback behavior consistent. Move to a common physical root only after local XAMPP and the actual production host have demonstrated a suitable private storage location. Preferred storage is outside the public web root; if the host makes that impossible, a verified deny-by-default storage directory plus authenticated streaming is the fallback. Never assume `.htaccess` directives work on the production host without a direct test.

### 4. Search and lifecycle

Start with indexed metadata search: category, source record, reference number where the source has one, date, uploader, and filename. Add amount, employee, project, family or other business filters only where those fields are authoritatively available from the related record. Search results must apply the same record-level authorization as direct retrieval.

OCR/full-text extraction is a later optional phase, not a launch dependency. Scanned receipts often have poor OCR accuracy; extracted values must not be treated as verified accounting facts.

Do not hard-code automatic deletion or retention durations. Until the charity approves a retention schedule and applicable legal/accounting obligations are verified, preserve existing records and disable automatic purge. Retention, legal hold, archival export and eventual disposal need explicit policy and audit evidence.

### 5. Security and resilience requirements

- Authenticated upload and retrieval; role and record-level checks on the server for every operation.
- CSRF protection for state-changing actions.
- Extension allowlist plus detected MIME/content validation; reject upload errors, empty files, oversized files, path traversal and unsupported formats.
- Random internal storage identifiers; keep original names as display metadata only.
- Storage outside the public web root where supported; no public direct links or directory listing.
- Safe streaming headers, `X-Content-Type-Options: nosniff`, private/no-store caching for sensitive records, and validated paths constrained to the configured storage root.
- Append-only audit events for upload, version creation, metadata edits, archive access where justified, migration, integrity failure, restoration, retention and disposal.
- Database and file backups treated as one recoverable set; periodically test restoration and reconcile missing files, missing catalogue entries, and orphaned files.
- No automatic deduplication, overwrite, file deletion, or destructive migration.

## Proposed rollout

1. **Finish inventory:** enumerate every upload handler, file-serving route, storage root, schema column/table, extension/MIME/size policy, permission rule, and cleanup/deletion behavior. Classify each domain as verified, needs inspection, or runtime-only.
2. **Inspect actual schema and environment:** use `database/ahl_el_kheir.sql`, related migrations, and the user's actual `ahl_el_kheir` database to confirm types, keys, existing metadata, current file counts, and real filesystem paths. Do not apply a schema migration during this discovery step.
3. **Approve schema contract:** define the catalogue/version/audit structures, indexing strategy, relationship adapters, idempotency and transaction boundaries, retention policy placeholder, and reconciliation report.
4. **Build a small foundation:** explicit migration(s), shared procedural PHP storage/validation/checksum helpers, archive registration and safe retrieval integration, automated source/schema tests. No runtime DDL.
5. **Pilot one category:** choose a representative receipt domain after inventory; validate upload, existing record linkage, duplicate warning, versioning, permissions, failure cleanup, checksum, backup/restore, and rollback.
6. **Integrate gradually:** other domains one by one, retaining existing URLs and source records until each replacement path passes acceptance.
7. **Backfill and reconcile:** dry-run reports first; identify missing/orphaned/mismatched files; no automatic repair or deletion without a reviewed mapping.
8. **Later enhancements:** OCR, content search, retention-policy administration, export packages, and capacity/backup monitoring.

## Acceptance gates

- No runtime DDL and no invented schema relationships.
- Existing upload/retrieval workflows continue to work.
- Unauthorized users cannot list, preview, download or infer restricted document metadata.
- A known document cannot be retrieved by path traversal or direct public URL.
- Upload failure leaves no unreferenced partial file or incorrect catalogue row.
- File bytes, checksum, source-record reference and version history reconcile.
- Existing historical paths and records are preserved until migration is proven.
- Backups can restore both database references and file content.
- Local Apache/XAMPP and production hosting are verified independently.
- Documentation states exactly which gates are source-verified and which are runtime-verified.

## Current boundary and next action

This is a design/discovery checkpoint, not an implementation-complete claim. The next task is to finish the source inventory and inspect the exact schema for each upload domain, starting with the receipt/document categories and their upload handlers. The local database and production hosting must be consulted before finalizing the storage root or migration DDL. No files have been moved, no business records changed, no schema changed, and no runtime tests were run for this task.


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


## Additional source review — 2026-10-23

Further targeted inspection covered project funding evidence, disbursement reopening/reset flows, FINA settlement evidence retrieval, and salary-advance evidence replacement.

- **Project funding payment evidence** (`modules/projects/view_fm.php`): financial-manager-only upload/edit handlers validate detected MIME (PDF/JPG/PNG), enforce 10 MB, write beneath `storage/documents/projects/<project_id>/payments/`, and remove newly uploaded files when the DB transaction fails. Replacing an existing receipt commits the new path and then unlinks the old file. This is another version-history integration point. The authenticated route `modules/projects/project_payment_receipt.php` constrains the path to that project's payments directory.
- **Project evidence reset** (`modules/projects/project_funding_accounting.php`): pending `project_payment_evidence` rows can be deleted when funding allocations are removed. The source matrix must distinguish a pending row with no file from a documented row that carries receipt bytes; deletion behavior must not be generalized without reviewing those state predicates.
- **Disbursement reopen** (`modules/accounting/disbursements.php`): reopening a family item sets `disbursement_items.receipt_file_path = NULL` as part of the DB reset. The inspected branch does not unlink the former receipt path there, so that path may become unreferenced by this column; confirm all other references before any cleanup. Final batch receipt upload writes a new path to `monthly_disbursements.receipt_file_path`; the inspected branch does not preserve an explicit prior-version reference.
- **FINA settlement evidence serving** (`modules/accounting/fina_settlement_evidence.php`): access is restricted to the financial manager; retrieval resolves a settlement ID or stored path against `fina_settlements.evidence_path`, then constrains the canonical path to `storage/receipts/fina_settlements/`. It uses extension-derived MIME and private cache headers, not detected MIME or `no-store`.
- **Salary advance evidence**: both payment receipt and direct repayment evidence are single-current-row replacement workflows. They validate detected MIME and size, write a new uniquely named file, update/insert metadata and audit inside a transaction, remove the new file on DB failure, and unlink the previous file after successful commit. This is robust failure cleanup for the current model, but it intentionally does not preserve prior file versions.

### Schema inspection limitation and boundary

The actual local database inventory supplied during this task confirms the listed source tables, path columns, key relationships, and column types. The repository SQL dump `database/ahl_el_kheir.sql` was requested through the repository file reader, but that reader returned an empty body for this large SQL file; no claim is made that its CREATE TABLE definitions or indexes were independently parsed in this pass. Before authoring migration DDL, retrieve the relevant CREATE TABLE definitions/indexes from a supported source (the actual `ahl_el_kheir` database output is acceptable) and compare them with the migration conventions. No DDL has been drafted or applied.

### Updated next action

Complete a concise matrix of each source table's file columns, source-record authorization adapter, upload validation, replacement behavior, delete/reset behavior, and serving route. Then finalize the catalogue/version/link/audit schema against verified CREATE TABLE and index definitions. Continue to register existing files in place first; no physical moves, deletes, or schema changes during discovery.



## 2026-10-23 — Archive schema contract and source-link matrix (design only)

This section records the next design boundary after targeted source inspection. It is not a migration and does not assert that the proposed table names already exist.

### Expanded source matrix

| Source domain | Source path field(s) / table | Upload or replacement behavior observed | Existing retrieval authorization route |
|---|---|---|---|
| Transactions and sponsor payments | transactions.receipt_path, transactions.unified_receipt_path; sponsor_payments.receipt_file_path, sponsor_payments.unified_receipt_path | New unique receipt paths; returned-record resubmission can replace a DB path and run cleanup; shared paths exist in real data | modules/transactions/receipt_file.php, receipt_sp.php; keep their source-record/supervisor scope checks |
| FINA collection receipt | fina_collections.receipt_path | External collection receipt upload; removes newly stored file if its DB transaction fails | modules/accounting/fina_receipt.php; preserve FINA record ownership/supervisor restrictions |
| FINA settlement evidence | fina_settlements.evidence_path | Upload handled by settlement helper; caller cleans new file on failed processing | modules/accounting/fina_settlement_evidence.php; financial-manager-only and settlement/path containment checks |
| Family/return/disbursement receipts | disbursement_items.receipt_file_path; monthly_disbursements.receipt_file_path, return_receipt_file_path | Multiple receipt classes; reopen/reset can null a reference; uploads may occur in multi-step accounting flows | modules/accounting/disbursement_receipt.php, serve_receipt.php; preserve role, assigned-nanny and record-level checks |
| Family documents | family_documents.file_path | Insert plus audit; current delete flow unlinks physical file before deleting row | modules/families/document_file.php; preserve family scope checks |
| Orphan supporting documents | orphan_documents.file_path | Table exists but actual inventory reported zero rows; no upload writer was found in the inspected paths | modules/families/orphan_document_file.php; confirm this workflow before onboarding data |
| Orphan photos | family_children.photo_path | orphan_form.php and orphan_profile.php write storage/photos; remove action clears DB path but the inspected branch does not unlink prior photo | orphan_form.php?photo=<child id>; this is a separate child-photo source, not orphan_documents |
| Project documents | project_documents.file_path | Replacement updates path and unlinks prior file | modules/projects/serve_project_document.php; project permission check and canonical path containment |
| Project funding evidence | project_payment_evidence.receipt_file_path | FM upload/edit; replacement unlinks prior file after DB commit; pending evidence rows may be deleted during allocation removal | modules/projects/project_payment_receipt.php; role allowlist plus project view permission and path containment |
| HR contracts | hr_employee_contracts.contract_file_path | Replacement updates path; inspected upload does not remove prior file, so unreferenced files are possible | modules/hr/view_contract.php; employee/role authorization, constrained storage root, detected MIME and no-store headers |
| Salary-advance payment receipt | hr_salary_advance_documents.file_path | One current row per request/type; replace row transactionally, remove new file on failure, unlink prior file after commit | modules/hr/salary_advance_receipt.php; employee/role checks and path containment |
| Direct repayment evidence | hr_salary_advance_direct_repayment_documents.file_path | One current row per repayment; replacement cleans old file after successful commit | modules/hr/salary_advance_repayment_evidence.php; repayment-record access checks and path containment |
| Message attachments | message_attachments.stored_name (stored as basename) | Upload cleanup on insert failure; delete removes DB row then unlinks file | modules/messages/download_attachment.php; message-read authorization; delete checks uploader/admin |

The child-photo paths were added to scope because they are uploaded documents/images in the same application and are not represented in the earlier 13-table path inventory. They should be treated as a distinct source adapter in the catalogue plan. Do not silently include unrelated user avatars or other storage types without a matching requirement and source inventory.

### Proposed conceptual schema contract (names remain provisional)

Use four logical responsibilities, with exact physical table names finalized only after schema/index review:

1. **Stored file/blob** — one row per unique physical relative storage key; capture detected MIME, byte size, SHA-256, original/display filename, storage state (present/missing/quarantined), and creation time. A shared path must resolve to one blob row, not duplicate blob rows.
2. **Document/version** — a logical document identity and immutable version entries. Each version points to a stored blob and records uploader, original filename, detected MIME, size/hash snapshot, creation time, and optional previous version. A replacement creates a new version; it does not overwrite bytes or delete the previous version. A single blob/version may be linked from multiple source records where existing data shares one physical file.
3. **Source link** — typed source locator (source_table, numeric source record ID, source path-column/key) and current archive version/blob reference, plus link timestamps/actor. Source links are pointers, not authorization grants. Because the source tables differ and some relationships are application-level only, do not add guessed polymorphic foreign keys. Per-domain adapters must re-check source-record authorization before search details or download.
4. **Audit event** — append-only event records for registration, upload, new version, link/unlink, failed reconciliation, access-sensitive lifecycle actions, and reviewed archival actions; include actor (nullable only when a documented system operation needs it), event time, object/version/link identifiers, and bounded metadata. Never store raw file bytes or sensitive content in event metadata.

Integrity rules for the design:

- Existing paths are registered in place first. Initial registration is metadata-only and does not move, rename, rewrite, or delete files.
- Same path referenced by several rows maps to one stored blob and several source links. Equal hashes at different paths remain separate blobs unless a reviewed process explicitly merges identity; hashes are not deletion instructions.
- Physical bytes are immutable after registration. New uploads use opaque generated storage keys and create new blob/version rows. Source records continue using their existing path columns and routes during the compatibility phase.
- Link changes and file/version metadata changes are transactional where possible. If filesystem writes precede a DB commit, failure cleanup removes only the newly created unreferenced file. Never remove the prior version after successful replacement.
- A source unlink/deletion must not physically delete a blob while any current or historical version/link references it. Retention and physical purge are a separate future policy, disabled by default.
- Search is an index over metadata and authorized source links, not a bypass around existing domain access checks. No generic endpoint accepts an archive ID and serves bytes without the source adapter.
- Reconciliation is explicit and report-only initially: missing file, unreferenced path, path escape, size/hash mismatch, shared-path groups, and duplicate-content candidates. No automatic deduplication or cleanup.
- New upload paths should use CSRF checks, extension plus detected-content MIME allowlists, explicit size limits, opaque names, no-store/nosniff delivery, and safe cleanup. Roll out per source adapter to avoid unrelated workflow changes.

### Migration and implementation boundary

No archive table names, column types, foreign keys, or indexes are approved for DDL yet. The repository reader cannot currently provide the full large database/ahl_el_kheir.sql dump body. The supplied live inventory does establish source column names/types and several FKs, but before writing the migration we still need verified current definitions/indexes for (a) archive-name collision, (b) user ID type and deletion semantics, (c) source column path limits, (d) installed migration style and index names, and (e) whether any archive-named objects already exist. The next implementation artifact should be a reviewable schema proposal with exact DDL and a rollback/backfill plan, not runtime DDL and not a live migration.

### Recommended pilot boundary

Pilot **project documents and project payment evidence** first because both have explicit project authorization checks and known source/serving routes, but keep them as separate source adapters. The pilot must be metadata-only for existing files and preserve the current URLs. Do not simultaneously retrofit all receipt, HR, family, and message flows. Before pilot code changes, inspect every read/write/delete path for these two domains and verify current table definitions and migration conventions.



## 2026-10-23 — Pilot domain lifecycle review

The recommended pilot remains project documents plus project payment evidence, but inspection of all major project handlers shows that this pilot is not a single upload hook. The following lifecycle operations must be covered before code changes.

### project_documents lifecycle in modules/projects/view.php

- Read/list: project document list is scoped by project ID; the serving endpoint loads a document by ID then calls akp_can_view_project(document.project_id) before path containment and streaming.
- Expense receipt create: stores a new file and inserts project_documents metadata. On transaction failure it removes only the newly uploaded file.
- Expense receipt edit: can update an existing receipt row or insert a new receipt row. On success, it unlinks the old physical file when its path changes. On failure, it cleans the newly uploaded file.
- Expense receipt delete: deletes the project_documents row and then unlinks the file. This needs a version/link-aware archive action, not a direct physical deletion.
- General project document upload: inserts project_documents metadata and cleans the new file if the database operation fails.
- General project document replacement: updates the row to a new path and then unlinks the previous file. The row keeps only current file metadata, so history is not preserved in the existing source table.
- General document deletion: only permits deletion of unverified documents, deletes the row, then unlinks the physical file.
- Labor payment receipt: stores receipt metadata in project_documents; cleanup occurs if its DB operation fails. Labor-comment/payment deletion can also delete the linked document row; this path needs a dedicated review before inclusion in the pilot.
- Funding-return proof: stores/updates a project_documents row and unlinks the previous proof after a successful update. This is another replacement path under the same table and must not be missed.
- CSRF verification is present in the inspected project POST handler branches. Do not remove or bypass it during integration.

### project_payment_evidence lifecycle

- Upload and edit are in modules/projects/view_fm.php, not in the receipt-serving endpoint. The handlers require CSRF verification and check the project/financial workflow. They detect MIME from content, enforce a 10 MB limit, and generate a stored path under the project payments directory.
- First upload updates a pending row to documented and stores receipt path, original name and MIME. If the DB transaction fails, the newly uploaded file is removed.
- Editing payment evidence may replace the receipt path; after success the previous physical receipt is unlinked. Preserve the prior file as a historical archive version instead.
- project_funding_accounting.php deletes pending evidence rows when a funding allocation reset/removal occurs. The current sync helper also updates or inserts pending evidence rows from funding allocations. A row may have no file while pending; never treat row deletion alone as permission to purge a file without checking its path and archive references.
- project_payment_receipt.php only serves documented evidence, checks an explicit role allowlist, verifies akp_can_view_project(project_id), constrains the real path to the expected project payments storage area, and sends nosniff. Keep those checks in place.
- FM evidence upload and edit create audit_log events. Archive events must complement, not replace, existing financial/business audit events.

### Schema facts and unresolved schema verification

- Live DB inventory provided for this task reports project_documents.file_path VARCHAR(500) NOT NULL and project_payment_evidence.receipt_file_path VARCHAR(255) NULL; the project_payment_evidence CREATE TABLE migration confirms its current migration shape, unique funding-allocation key, and project/status/method indexes.
- No dedicated project_documents creation migration was found in the inspected migration directory; its complete current CREATE TABLE definition and indexes were not retrieved because the repository SQL dump reader returned an empty body.
- The source confirms project_payment_evidence and project_documents are separate business concepts even though they both store project-related files. Keep separate adapters and retain their business-specific metadata and audit.
- The source scan did not execute PHP, inspect the local filesystem, query the live database, or run a browser test. These are source-level findings only.

### Pilot acceptance boundary before implementation

1. Verify current SHOW CREATE TABLE and SHOW INDEX output for project_documents and project_payment_evidence in the actual ahl_el_kheir database, plus any archive-named objects.
2. Define exact migration DDL and rollback/backfill plan; use no runtime DDL, triggers, views, stored procedures, or events.
3. Register existing project file paths in place and idempotently. Shared physical paths must not produce duplicate blob records.
4. Integrate all listed upload/replace/delete/reset branches as one reviewed pilot; replacement creates a new immutable version, source deletion unlinks only the source link, and physical bytes remain.
5. Keep existing source columns, URLs, authorization checks, CSRF behavior, and financial audit events intact. New archive search/download must route through project authorization.
6. Test rollback on a disposable copy or transactionally isolated fixture only after the migration contract is approved; do not touch the live project records or create another ad hoc database as part of discovery.
