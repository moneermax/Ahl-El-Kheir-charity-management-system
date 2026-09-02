# Database Management

## Purpose

This directory contains the database definition and migration history for the Ahl El Kheir Charity Management System.

## Files

### `ahl_el_kheir.sql`

This is a legacy full database snapshot. It contains database structure and inserted development data, and may contain legacy MySQL triggers/views from the snapshot date.

**Do not import this file into an existing live/development database unless you intentionally want to restore that snapshot.** It is not the authoritative mechanism for applying incremental schema changes.

The current application architecture uses the migration files below and explicit PHP business logic for the integrity rules that were previously implemented by MySQL triggers.

### `migrations/`

Migration files are applied in chronological order when a database change is required.

- `2026-09-01_add_message_soft_delete.sql` — adds message soft-delete support.
- `2026-09-02_replace_integrity_triggers_views_with_php.sql` — removes the legacy family/child/supervisor triggers and legacy reporting views after reconciling the affected data.
- `2026-09-02_verify_php_integrity_migration.sql` — verification queries for the trigger/view migration and related integrity checks.

## Current integrity architecture

The following former database-trigger responsibilities are now owned by application code in `config/data_integrity.php`:

- Synchronizing `families.children_count` from `family_children`.
- Reconciliation of non-manually-overridden sponsor supervisor assignments from `supervisor_letters`.

This keeps business rules explicit, testable, auditable, and consistent with the procedural PHP architecture.

## Database safety rules

1. Never run `SET FOREIGN_KEY_CHECKS=0` as a shortcut for cleanup or migration.
2. Never delete accounting, transaction, voucher, disbursement, family, sponsor, or user records simply to make an integrity check pass.
3. Prefer a migration for permanent schema changes.
4. Keep real database backups outside the source repository.
5. Never commit production credentials, database passwords, uploaded files, or real organizational database dumps to the repository.
6. Before restoring any SQL snapshot, make a separate backup of the current database and verify the target database/environment.

## Backup policy

Development/production database dumps should be stored outside the Git repository. If a temporary dump is needed locally, place it under `database/backups/`; that directory is ignored by Git.

If a repository snapshot is ever found to contain real organizational data, do not rewrite or delete Git history automatically. Preserve a secure copy first, then handle repository-history cleanup as a separate, explicitly approved security task.
