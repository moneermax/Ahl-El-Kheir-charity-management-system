# Ahl El Kheir — Production Database Preparation Guide

## Purpose

This guide describes the exact procedure to use when the current development database is ready to be converted into the real production database.

The production-preparation script is:

`database/production/prepare_production_database.sql`

It was runtime-verified against a disposable copy of the current database before production use.

**IMPORTANT:** The script intentionally clears development/test operational data. It must not be run until the final pre-cleanup database backup has been successfully created and verified.

---

## 1. Preconditions

Before running the production cleanup:

- Confirm the application is ready for production.
- Confirm the working tree is clean and `main` is current.
- Stop all users from using the system.
- Stop **Apache** in XAMPP so nobody can access the application during the cleanup.
- **Do not stop MySQL/MariaDB**, because the backup and cleanup commands need the database server running.
- Make sure there is enough disk space for the backup.
- Do not modify the production-preparation SQL manually immediately before execution.

The production database name is:

`ahl_el_kheir`

The project path is:

`D:\xampp\htdocs\AhlElKheir`

---

## 2. Confirm the code is current

Open PowerShell:

```powershell
cd D:\xampp\htdocs\AhlElKheir
git checkout main
git pull origin main
git status --short
git log -1 --oneline
```

Expected:

- Branch: `main`
- Up to date with `origin/main`
- `git status --short` returns no output.

Do not proceed if there are unexpected local changes.

---

## 3. Create the final pre-cleanup database backup

This backup is the rollback/safety copy of the database immediately before production cleanup.

Use `cmd.exe /c` around `mysqldump`. This avoids the PowerShell native-command redirection encoding problem that can produce UTF-16 output.

Run:

```powershell
cd D:\xampp\htdocs\AhlElKheir

cmd.exe /c ""D:\xampp\mysql\bin\mysqldump.exe" -u root -p --default-character-set=utf8mb4 --single-transaction --routines=false --triggers=false --events=false ahl_el_kheir > "D:\xampp\htdocs\AhlElKheir\database\production\ahl_el_kheir_before_production_cleanup.sql""
```

Enter the MySQL root password when prompted.

### Verify the backup exists

```powershell
Get-Item .\database\production\ahl_el_kheir_before_production_cleanup.sql
```

Then verify its size:

```powershell
(Get-Item .\database\production\ahl_el_kheir_before_production_cleanup.sql).Length
```

The size must be greater than zero and should be substantial for the current database.

Optionally inspect the beginning:

```powershell
Get-Content .\database\production\ahl_el_kheir_before_production_cleanup.sql -TotalCount 20
```

**Do not continue if the backup is missing, empty, or clearly invalid.**

Copy this backup to a safe location outside the project directory as an additional precaution.

---

## 4. Run the production-preparation script

Only after the backup has been verified, run:

```powershell
cd D:\xampp\htdocs\AhlElKheir

cmd.exe /c ""D:\xampp\mysql\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 ahl_el_kheir < "D:\xampp\htdocs\AhlElKheir\database\production\prepare_production_database.sql""
```

Enter the MySQL root password.

This runs the script directly against the real `ahl_el_kheir` database.

---

## 5. Understand what the script preserves and clears

The original production-preparation contract preserves:

- users
- employees
- families
- family children
- sponsors
- sponsorships
- sponsorship children
- sponsor-supervisor assignment history
- supervisor letters
- supervisor-letter assignment history
- roles
- departments
- currencies
- letters
- medical needs
- settings
- accounts, except the original eight explicitly designated test accounts

All other current tables are treated as development/test operational data and are cleared by the current script.

The script:

- does **not** drop tables
- does **not** delete physical files from `storage/`
- preserves all account rows except the exact original eight test-account codes
- resets the accounts auto-increment to `19`

The exact account codes removed are:

```text
4400-1
5100-1
4400-2
5100-2
4400-3
5100-3
4400-4
5100-4
```

No broad account-code pattern is used.

---

## 6. Check the script verification output

The SQL script performs its own verification after cleanup.

Look for these sections:

```text
CORE DATA PRESERVED
REFERENCE DATA PRESERVED
TEST DATA CLEARED
ORIGINAL TEST ACCOUNTS REMAINING (MUST BE 0)
```

The required result is:

- preserved core/reference data still has counts
- every cleaned-table count is `0`
- `ORIGINAL TEST ACCOUNTS REMAINING` is `0`
- no SQL errors are reported

If the command reports an SQL error, **stop immediately**. Do not start using the application until the cause has been investigated.

---

## 7. Create a post-cleanup production backup

After the cleanup succeeds, create a second backup representing the clean production database:

```powershell
cd D:\xampp\htdocs\AhlElKheir

cmd.exe /c ""D:\xampp\mysql\bin\mysqldump.exe" -u root -p --default-character-set=utf8mb4 --single-transaction --routines=false --triggers=false --events=false ahl_el_kheir > "D:\xampp\htdocs\AhlElKheir\database\production\ahl_el_kheir_production_clean.sql""
```

Verify it:

```powershell
Get-Item .\database\production\ahl_el_kheir_production_clean.sql
```

Keep both backups:

```text
ahl_el_kheir_before_production_cleanup.sql
    = database immediately before cleanup

ahl_el_kheir_production_clean.sql
    = database immediately after successful cleanup
```

Store copies of both somewhere safe outside the project directory.

---

## 8. Start the application again

After the database cleanup and verification are complete:

1. Start Apache in XAMPP.
2. Open:

`http://localhost:8081/AhlElKheir/`

3. Perform a production smoke test.

---

## 9. Production smoke test

Verify at minimum:

1. Login works.
2. Existing production users are present.
3. Existing employees are present.
4. Families are present.
5. Sponsors are present.
6. Sponsorships are present.
7. Reference/configuration data is present.
8. The dashboard loads.
9. Main navigation works.
10. No page reports missing tables or missing required data.

Do not begin large-scale real operations until this smoke test passes.

---

## 10. Final production sequence

The complete sequence is:

```text
FINAL DEVELOPMENT DATABASE
        |
        v
Confirm main is current + working tree clean
        |
        v
Stop application access / stop Apache
        |
        v
Create FINAL PRE-CLEANUP BACKUP
        |
        v
Verify backup exists and is non-empty
        |
        v
RUN prepare_production_database.sql
        |
        v
Check SQL verification output
        |
        v
If successful:
create POST-CLEANUP production backup
        |
        v
Start Apache
        |
        v
Run production smoke test
        |
        v
GO LIVE
```

---

## 11. Emergency rollback principle

If a serious problem is discovered after cleanup, do **not** attempt to reconstruct deleted data manually.

The intended recovery source is:

`ahl_el_kheir_before_production_cleanup.sql`

That backup represents the database immediately before the destructive cleanup operation.

Restoring it is a database-recovery operation and should be performed only after stopping application access again and confirming the recovery plan.

---

## Important safety rule

**Never run `prepare_production_database.sql` against the real database without first creating and verifying the final pre-cleanup backup.**

The disposable-database verification already performed proves that the current script behaves correctly against a copy of the current database. It does not eliminate the need for a fresh backup immediately before the real production cleanup.
