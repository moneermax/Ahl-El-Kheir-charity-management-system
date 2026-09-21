# Production Preparation

This document records the production-baseline procedure for the Ahl El Kheir Charity Management System.

## Environment-aware deployment configuration

The application now derives its environment automatically when no explicit environment variable is supplied:

- local hosts (`localhost`, `127.0.0.1`, `::1`) default to `development`;
- non-local hosts default to `production`;
- `AHL_ENV` may explicitly select `development` or `production` when a controlled staging/development environment requires it.

Production database connection values are read from `AHL_DB_HOST`, `AHL_DB_PORT`, `AHL_DB_NAME`, `AHL_DB_USER`, and `AHL_DB_PASS`. The existing XAMPP defaults remain available for local development. A production configuration without a database password is rejected rather than silently using the local `root`/empty-password configuration.

Session cookies automatically use the `Secure` flag in production, while remaining compatible with the current local HTTP/XAMPP environment. PHP error details and database exception details are shown only in development; production returns generic error messages.

Before the first production deployment, configure the hosting environment with the real database credentials and verify that HTTPS is active. No application PHP file should need to be edited merely because the hostname, port, or database credentials change.

## Current policy

The application is still under development. The development database contains a mixture of real master data and test operational data.

### Data that must be preserved

- Users and employees
- Families and family/child master data
- Sponsors
- Sponsorships, including the real sponsorship amounts
- Sponsor-supervisor assignment history
- Current supervisor letter assignments
- Supervisor letter assignment history
- Required reference/configuration data

### Data that is development/test data in the current baseline

- Orphan groups and group assignments
- Disbursement batches and items
- Payments and accounting transactions
- Test project records
- Family documents currently stored in the database
- Accountant receipts and related test operational records
- Development messaging/attachments
- Development notifications, audit and verification records
- Other records explicitly classified as test operational data by the cleanup procedure

## Cleanup procedure

The one-time production cleanup script is:

`database/production/prepare_production_database.sql`

It removes records from test/operational areas without dropping production tables. Foreign-key checks are temporarily disabled during the controlled cleanup and restored at the end.

## Recommended production transition

1. Take a complete backup of the final development database.
2. Restore that backup into a separate test database.
3. Run `database/production/prepare_production_database.sql` against the test database.
4. Verify the script's result sets and test the application against the cleaned database.
5. Keep the verified cleaned database as the production baseline.
6. Restore the development backup and continue development until release.
7. Before production deployment, apply all schema migrations created after the baseline.
8. Take a final production backup before any production initialization.
9. Initialize the real opening balance and begin production operations.

## Physical storage

The SQL cleanup removes database records only. Files physically stored under `storage/` are handled separately. Before production, test documents, receipts, messaging attachments, debug logs, and other development artifacts must be removed from physical storage while preserving anything explicitly classified as real production data.

## Database evolution

The production baseline is not a second development database. Schema changes must be recorded as migrations under `database/migrations/`. When the application reaches production, the migration history is applied to the production baseline so its schema remains synchronized with the code.

## Fina production transition

Fina is a protected third-party liability and must remain separate from Ahl El Kheir revenue and expenses. The production Fina workflow uses liability/control account `2300`.

Before production, the organization must define and approve the actual settlement agreement with Fina. The software must record that real agreement rather than invent a settlement date, threshold, or frequency.

The current recommended design is documented in:

`docs/FINA_SETTLEMENT_PROCESS.md`

The recommended default is monthly settlement with controlled early settlement when operationally justified. Settlement is a separate liability-reduction event and must not delete or rewrite historical Fina collection records.

## Repository hygiene

Database backups and runtime-generated files must not be committed to the repository. Local backup locations remain available through `.gitignore`; the repository should contain schema/migration scripts and the production cleanup procedure, not live database dumps or runtime logs.

## Documentation checkpoint — 2026-09-21

Environment-aware production hardening was added without changing business workflows or database schema. A remote pre-change checkpoint branch was created from the previous `main` commit before the configuration changes.

## Documentation checkpoint — 2026-09-17

This document remains the authoritative production-preparation procedure. No production cleanup, deployment, or Fina settlement action was performed as part of the current work. Development/test accounting data remains non-production data.

## 2026-09-21 — Hosting database object restriction

For the current hosting target, the database architecture is permanently restricted to hosting-compatible objects. **No MySQL/MariaDB triggers or views may ever be added again.** Stored procedures, stored functions, and scheduled database events are also excluded from the hosting-compatible baseline unless the platform decision is formally changed.

The current `database/ahl_el_kheir.sql` export and repository SQL migration set were scanned and contain no `CREATE TRIGGER`, `CREATE VIEW`, `CREATE PROCEDURE`, `CREATE FUNCTION`, or `CREATE EVENT` statements. The earlier `attendance` trigger import failure came from an older export. Future exports and migrations must be scanned before deployment.

Trigger-derived business rules that remain required must be enforced by the appropriate PHP workflow rather than recreated as database triggers.
