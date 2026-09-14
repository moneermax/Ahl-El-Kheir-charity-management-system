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

### Authoritative Sponsor responsibility

Supervisor sponsor responsibility is determined by:

`Sponsor first-name letter + Sponsor gender → Supervisor`

This is the authoritative business rule for Sponsor responsibility. A Sponsor outside the Supervisor's letter+gender responsibility scope must not be visible or accessible to that Supervisor merely through a direct record URL or related list.

It is **not** determined by the orphan's family, mother's name, mother's first letter, family code, or orphan identity.

### Supervisor operational visibility

Within legitimate scope, the Supervisor may follow the operational chain:

`Sponsor → Sponsorship → Child/Orphan → Family`

subject to each destination's own server-side record-level authorization.

### Direct sponsor assignment clarification

The repository contains historical/direct assignment paths using `sponsor.supervisor_id`, and some existing routes have treated that assignment as an additional access path. The current business-rule clarification is that Sponsor Letter + Sponsor Gender is the authoritative responsibility rule. Therefore `sponsor.supervisor_id` must not be treated as a new independent authorization grant without confirming its documented operational purpose and current workflow usage.

Do not make a speculative change. Inspect the documented workflow and actual repository usage before changing any existing direct-assignment behavior.

### Family access

Family access is a separate concern. Existing implementation/documentation contains direct family assignment and sponsor-linked/matrix-based family/orphan access paths. Do not narrow family access to `families.supervisor_id` alone, and do not use family/mother information as a substitute for the Sponsor responsibility rule.

## 4. Supervisor dashboard boundary

The Supervisor dashboard/navigation is operational, not an Accounting control surface. It may expose scoped operational indicators and links for:

- Sponsors
- Families
- Orphan/child forms
- Sponsorships
- related operational follow-up

Dashboard counts and lists must use the Supervisor's legitimate record scope. A financial consequence of an operational workflow does not by itself grant Supervisor Accounting authority.

## 5. FM dashboard

The treasury row must contain:

1. Cash — `1100`
2. Bank — `1200`
3. Electronic wallet — `1300`
4. Total treasury
5. Administrative fees — `4200`

Fix commit: `783b160a60ce50f0f661a65a112aea7469979ca4`.

This regression is closed and must not be reopened without new runtime evidence.

## 6. Accountant Staff / ACC1

Known controlled assignment: Accountant Staff user `17` → nanny `16`.

The substantial ACC1/nanny accounting integration is already completed, including assignment-based authorization, disbursement management, reopen controls, receipt/return handling, and accounting integration.

The Arabic encoding issue is already solved. Do not restart old ACC1 tests unless a documented open item or genuine regression appears.

## 7. Current open audit direction

**Supervisor ↔ Accounting integration review** is now the immediate active direction within the broader Supervisor Module Audit boundary.

The Supervisor module itself has been working correctly in the tested areas. Do not broaden this into a fresh Supervisor-module audit unless a real integration dependency requires it.

The next review must inspect actual repository integration points and answer:

1. Can Supervisor access any Accounting page/action directly or indirectly?
2. Does any Supervisor operational workflow create, submit, return, or otherwise mutate an Accounting-controlled record?
3. What financial status/result is appropriate for Supervisor to see without granting Accounting authority?
4. Are Supervisor submissions routed to FM/Accounting using the correct actor and scope rules?
5. Are Accounting notifications/results exposed only to the correct Supervisor?
6. Does any Accounting query accidentally expose data outside the Supervisor's legitimate Sponsor scope?
7. Does any Supervisor dashboard KPI/summary expose Accounting data broader than the Supervisor's operational scope?

Do not repeat the closed Accounting Audit. Reuse its findings as the baseline and investigate only the integration boundary.

## 8. Recent completed Supervisor findings

### VGM sponsor assignment/reassignment

VGM is the operational owner of `modules/sponsors/assign.php`. VGM dashboard exposes the task. Supervisor direct access is blocked. FM has no sponsor-assignment action. All three runtime checks passed.

Commit: `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.

### Sponsor request authorization/navigation

`modules/sponsors/requests.php` remains restricted to `admin`, `vice_general_manager`, `general_manager`, and `social_media`. Supervisor is not authorized for this general queue, and the Sponsor list no longer displays the queue button to Supervisor.

Commit: `ba21c3415158787e2fb294eaf746cf37d7d845bc`.

### Sponsor runtime schema synchronization — completed code cleanup

The following request-time schema mutations were removed:

- `modules/sponsors/create.php` and `edit.php`: runtime `ALTER TABLE sponsors ... brought_by_name` removed; explicit migration retained.
- `modules/sponsors/view.php`: runtime `ALTER TABLE sponsors ... brought_by_name` removed.
- `config/sponsor_assignments.php`: runtime `CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments` removed.
- `modules/sponsors/assign.php`: no longer triggers request-time assignment-history table creation.
- `modules/sponsors/requests.php`: runtime `CREATE TABLE IF NOT EXISTS sponsor_requests` and dynamic `ALTER TABLE ... created_sponsor_id` removed.

Explicit migration: `database/migrations/2026-09-14_sponsor_workflow_runtime_ddl_cleanup.sql`.

The local database has already been verified to contain `sponsors.brought_by_name VARCHAR(255) NULL`.

### Supervisor sponsorship-list scope regression

`modules/sponsorships/index.php` previously filtered Supervisor sponsorships only by the letter+gender matrix and omitted direct sponsor assignment. It was aligned with the existing implementation by adding direct assignment as an access path alongside the matrix.

Commit: `76be77a4d2e6e337485d3f3c9980780b9de73df0`.

The user tested the three scope cases after that fix:

1. Direct-assignment case — PASS.
2. Letter+gender matrix case — PASS.
3. Outside both scopes — PASS.

This test is complete. Because the user subsequently clarified that Letter + Gender is the authoritative business rule, the direct-assignment path must now be treated as a documented behavior requiring workflow clarification, not automatically as a separate business rule.

## 9. Other parked work

- Targeted accounting runtime verification of newly protected automated journal reference types.
- Remaining direct journal mutation callers and cross-module accounting auditability.
- Reliable caller evidence for the legacy generic notification helper.
- Unified organizational assignment/history model and controlled lifecycle transitions for non-supervisor users.
- Same-page Bootstrap modal UX for missing/stale receipt feedback.
- Formal report/source/calculation catalog.
- Production preparation and deployment hardening.

## 10. Production/data status

The application is still under development. Development/test operational data is not to be treated as production financial data.

Production preparation remains governed by `docs/PRODUCTION_PREPARATION.md` and `database/production/prepare_production_database.sql`.

## 11. Internationalization

Arabic is the default language and English is the alternate language. Stable key-based i18n is authoritative.

## 12. Local Git safety

Intentional local backup files that must not be deleted/reset/stashed/overwritten:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Commit `f7051ded6fe4c8e346cf7bcb08f48d0535945d01` was inspected and does not represent an active rollback of the tracked Accountant Staff dashboard.

## 13. Documentation map

- **`docs/AHL_EL_KHEIR_MASTER_AUDIT.md`** — **SINGLE MASTER AUDIT**: all detailed system review, Accounting, Notification, organizational lifecycle, HR navigation, completed evidence, open audit findings, and continuation rules.
- **This file** — high-level START HERE status and continuation summary.
- `CHATGPT_SESSION_INDEX.md` — short continuation index.
- `I18N.md` — internationalization reference.
- `PRODUCTION_PREPARATION.md` — production preparation procedure.

There should be no second detailed audit/checkpoint file for the same project-wide audit history.

## 14. Continuation rule

Every meaningful milestone ends with:

`Code correct → behavior verified → documentation updated → exact next continuation point recorded`.

A new chat/session must continue from this master status and the single master audit rather than restarting the project or searching through obsolete audit files.
