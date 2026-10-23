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
