# Ahl El Kheir Charity Management System — Master Status

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-14

This is the single high-level **START HERE** status and continuation summary for the existing project. The detailed audit record is consolidated into `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`.

## 1. Project rule

This is an existing project and existing audit continuation. Do not restart completed work, repeat passed tests, recreate protected fixtures, or invent database schema names.

Working rule:

`Inspect → Understand → Verify → Identify risk → Fix narrowly → Test → Document`

## 2. Completed major areas

- HR foundation and HR audit — **COMPLETE / CLOSED at current boundary**.
- Accounting Audit — **COMPLETE / CLOSED at current documented boundary**.
- Notification Integrity Audit — **CLOSED at current evidence boundary**.
- Authentication/session hardening — **CODE CORRECT + RUNTIME VERIFIED**.
- Accountant Staff financial/reporting authorization and accounting integration — **COMPLETED at current checkpoint**.
- Accountant Staff Arabic dashboard encoding issue — **SOLVED / CLOSED**.
- FM dashboard treasury/admin-fee regression — **FIXED / CLOSED**.
- Supervisor sponsor ownership and restored sponsor-linked family access rule — **COMPLETED / PRESERVED**.
- HR dashboard navigation consolidation — **COMPLETED / CLOSED**.
- Supervisor lifecycle baseline — **DOCUMENTED / CURRENT GAPS PARKED**.

## 3. Supervisor rules that must not regress

### Sponsor ownership

Supervisor responsibility is determined by:

`Sponsor first-name letter + Sponsor gender → Supervisor`

It is **not** determined by the orphan's family, mother's name, or mother's first letter.

### Family access

Family access is a separate concern and includes:

- direct family assignment → access;
- sponsor assigned to a supervisor → access to that sponsor and the sponsor's related family/orphan data;
- supervisor letter + gender responsibility matrix → linked sponsor/family/orphan access where applicable.

Restoration commit: `448fa0bfee7fdcd5ce3e7608304de6b1c7f9ba73`.

Do not narrow this to `families.supervisor_id` only.

## 4. FM dashboard

The treasury row must contain:

1. Cash — `1100`
2. Bank — `1200`
3. Electronic wallet — `1300`
4. Total treasury
5. Administrative fees — `4200`

Fix commit: `783b160a60ce50f0f661a65a112aea7469979ca4`.

This regression is closed and must not be reopened without new runtime evidence.

## 5. Accountant Staff / ACC1

Known controlled assignment: Accountant Staff user `17` → nanny `16`.

The substantial ACC1/nanny accounting integration is already completed, including assignment-based authorization, disbursement management, reopen controls, receipt/return handling, and accounting integration.

The Arabic encoding issue is already solved. Do not restart old ACC1 tests unless a documented open item or genuine regression appears.

## 6. Current open audit direction

**Supervisor Module Audit** remains the active audit direction.

Continue page-by-page from the actual Supervisor dashboard/navigation. The family-scope restoration itself is complete and must not be re-audited as unfinished.

Current governance items:

1. Formal organization-wide permission/action matrix.
2. Controlled review of remaining supervisor sponsor/family/sponsorship route scope.
3. Runtime schema synchronization review — sponsor runtime DDL cleanup is implemented for the reviewed sponsor routes.
4. Verify/apply the explicit sponsor workflow migration locally before exercising assignment/request workflows.
5. VGM sponsor assignment/reassignment is confirmed as a VGM task. The VGM dashboard now exposes `modules/sponsors/assign.php`; Supervisor direct access is blocked and FM has no sponsor-assignment action. All three runtime checks passed.

### Sponsor runtime schema synchronization — completed code cleanup

The following request-time schema mutations were removed:

- `modules/sponsors/create.php` and `edit.php`: runtime `ALTER TABLE sponsors ... brought_by_name` removed; explicit migration retained.
- `modules/sponsors/view.php`: runtime `ALTER TABLE sponsors ... brought_by_name` removed.
- `config/sponsor_assignments.php`: runtime `CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments` removed.
- `modules/sponsors/assign.php`: no longer triggers request-time assignment-history table creation.
- `modules/sponsors/requests.php`: runtime `CREATE TABLE IF NOT EXISTS sponsor_requests` and dynamic `ALTER TABLE ... created_sponsor_id` removed.

Explicit migration: `database/migrations/2026-09-14_sponsor_workflow_runtime_ddl_cleanup.sql`.

The local database has already been verified to contain `sponsors.brought_by_name VARCHAR(255) NULL`.

### Sponsor request authorization/navigation

`modules/sponsors/requests.php` remains restricted to `admin`, `vice_general_manager`, `general_manager`, and `social_media`. The Supervisor role is not authorized for this general queue, and the sponsor list no longer displays the queue button to Supervisor. This is least-privilege alignment, not a change to the authoritative sponsor ownership matrix.

Do not make speculative restrictions or schema rewrites for these completed findings.

## 7. Other parked work

- Targeted accounting runtime verification of newly protected automated journal reference types.
- Remaining direct journal mutation callers and cross-module accounting auditability.
- Reliable caller evidence for the legacy generic notification helper.
- Unified organizational assignment/history model and controlled lifecycle transitions for non-supervisor users.
- Same-page Bootstrap modal UX for missing/stale receipt feedback.
- Formal report/source/calculation catalog.
- Production preparation and deployment hardening.

## 8. Production/data status

The application is still under development. Development/test operational data is not to be treated as production financial data.

Production preparation remains governed by `docs/PRODUCTION_PREPARATION.md` and `database/production/prepare_production_database.sql`.

## 9. Internationalization

Arabic is the default language and English is the alternate language. Stable key-based i18n is authoritative.

## 10. Local Git safety

Intentional local backup files that must not be deleted/reset/stashed/overwritten:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Commit `f7051ded6fe4c8e346cf7bcb08f48d0535945d01` was inspected and does not represent an active rollback of the tracked Accountant Staff dashboard.

## 11. Documentation map

- **`docs/AHL_EL_KHEIR_MASTER_AUDIT.md`** — **SINGLE MASTER AUDIT**: all detailed system review, Accounting, Notification, organizational lifecycle, HR navigation, completed evidence, open audit findings, and continuation rules.
- **This file** — high-level START HERE status and continuation summary.
- `CHATGPT_SESSION_INDEX.md` — short continuation index.
- `I18N.md` — internationalization reference.
- `PRODUCTION_PREPARATION.md` — production preparation procedure.

There should be no second detailed audit/checkpoint file for the same project-wide audit history.

## 12. Continuation rule

Every meaningful milestone ends with:

`Code correct → behavior verified → documentation updated → exact next continuation point recorded`.

A new chat/session must continue from this master status and the single master audit rather than restarting the project or searching through obsolete audit files.
