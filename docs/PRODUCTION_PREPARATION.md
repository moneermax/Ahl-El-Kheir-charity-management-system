# Production Preparation

This document records the production-baseline procedure for the Ahl El Kheir Charity Management System.

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

## Repository hygiene

Database backups and runtime-generated files must not be committed to the repository. Local backup locations remain available through `.gitignore`; the repository should contain schema/migration scripts and the production cleanup procedure, not live database dumps or runtime logs.
