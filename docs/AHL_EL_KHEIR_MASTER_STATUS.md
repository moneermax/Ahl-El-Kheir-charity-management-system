# Ahl El Kheir Charity Management System — Master Status

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-14

This is the single high-level status and continuation document for the project. Detailed domain audits remain in their dedicated documents. Historical duplicate status/audit files are intentionally retired.

## 1. Project rule

This is an existing project and existing audit continuation. Do not restart completed work, repeat passed tests, recreate protected fixtures, or invent database schema names.

Working rule:

`Inspect → Understand → Verify → Identify risk → Fix narrowly → Test → Document`

## 2. Completed major areas

- HR foundation and HR audit — **COMPLETE / CLOSED**.
- Accounting Audit through the current documented evidence boundary — **COMPLETE / CLOSED at current boundary**.
- Notification Integrity Audit — **CLOSED at current evidence boundary**.
- Authentication/session hardening — **CODE CORRECT + RUNTIME VERIFIED**.
- Accountant Staff financial/reporting authorization and accounting integration — **COMPLETED at current checkpoint**.
- Accountant Staff Arabic dashboard encoding issue — **SOLVED / CLOSED**.
- FM dashboard treasury/admin-fee regression — **FIXED / CLOSED**.
- Supervisor sponsor ownership and restored sponsor-linked family access rule — **COMPLETED / PRESERVED**.

## 3. Supervisor rules that must not regress

### Sponsor ownership

Supervisor responsibility is determined by:

`Sponsor first-name letter + Sponsor gender → Supervisor`

It is **not** determined by the orphan's family, mother's name, or mother's first letter.

### Family access

Family access is a separate concern and currently includes:

- direct family assignment → access;
- sponsor assigned to a supervisor → access to that sponsor and the sponsor's related family/orphan data;
- supervisor letter + gender responsibility matrix → linked sponsor/family/orphan access where applicable.

Restoration commit: `448fa0bfee7fdcd5ce3e7608304de6b1c7f9ba73`.

Files involved:
- `config/family_scope.php`
- `modules/families/index.php`

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

## 6. Accounting

Canonical detailed audit: `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`.

Protected completed accounting evidence includes the transaction/journal controls documented there. Do not modify completed evidence merely to make an audit query look clean.

Core accounting structures include:

- `accounts`
- `journal_entries`
- `journal_lines`
- transactions
- monthly disbursements/items

Accounting rule: inspect the actual schema/code before SQL. Never invent table or column names.

Parked accounting work includes direct original↔reversal/source navigation in journal detail and further mutation-route/auditability review after the established checkpoint.

## 7. HR

HR is complete at the current audit boundary. Payroll accounting hardening is part of the existing accounting/HR history. Reopen only for concrete cross-module regression evidence.

Dedicated HR navigation audit remains available as historical/specific evidence: `docs/HR_DASHBOARD_NAVIGATION_AUDIT_2026-09-08.md`.

## 8. Notifications

Notification audit is closed at its current evidence boundary. The legacy `config/messaging.php::send_system_notification()` helper remains an architectural note because reliable active caller evidence was not established; it was not modified speculatively.

Dedicated audit: `docs/AHL_EL_KHEIR_NOTIFICATION_AUDIT.md`.

## 9. Authentication/session

Session fixation hardening was applied and runtime verified through login/dashboard/logout/re-login.

Commit: `9cdf89d30fdee147a919c7cc1056457b1b9d20f3`.

## 10. Current open audit direction

**Supervisor Module Audit** remains the active audit direction.

Continue page-by-page from the actual Supervisor dashboard/navigation. The family-scope restoration itself is complete and must not be re-audited as unfinished.

Current governance items:

1. Formal organization-wide permission/action matrix.
2. Exact role/business scope for supervisor access to the general sponsor-request queue.
3. Controlled review of runtime schema synchronization such as `modules/sponsors/index.php` performing `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...` during normal page rendering.

Do not make speculative restrictions or schema rewrites for these parked items.

## 11. Other parked work

- Same-page Bootstrap modal UX for missing/stale receipt feedback.
- Formal report/source/calculation catalog.
- Final notification event/recipient catalog where not already captured.
- Broader accounting auditability after mutation-route coverage.
- Unified organizational assignment/history model as described in the organizational lifecycle audit.

## 12. Production/data status

The application is still under development. Development/test operational data is not to be treated as production financial data.

Production preparation remains governed by `docs/PRODUCTION_PREPARATION.md` and `database/production/prepare_production_database.sql`.

## 13. Internationalization

Arabic is the default language and English is the alternate language. Stable key-based i18n is authoritative. Detailed rules and audit command remain in `docs/I18N.md`.

## 14. Local Git safety

Intentional local backup files that must not be deleted/reset/stashed/overwritten:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Commit `f7051ded6fe4c8e346cf7bcb08f48d0535945d01` was inspected and does not represent an active rollback of the tracked Accountant Staff dashboard.

## 15. Authoritative documentation map

Use the smallest relevant document:

- **This file** — overall current status and continuation point.
- `AHL_EL_KHEIR_ACCOUNTING_AUDIT.md` — detailed Accounting evidence/history.
- `AHL_EL_KHEIR_NOTIFICATION_AUDIT.md` — detailed Notification audit.
- `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` — system architecture/design analysis.
- `ORGANIZATIONAL_LIFECYCLE_AUDIT_2026-09-03.md` — organizational lifecycle detail.
- `HR_DASHBOARD_NAVIGATION_AUDIT_2026-09-08.md` — completed HR navigation evidence.
- `I18N.md` — internationalization rules/audit.
- `PRODUCTION_PREPARATION.md` — production cleanup/release procedure.
- `CHATGPT_SESSION_INDEX.md` — short continuation index pointing back to this master status.

## 16. Continuation rule

Every meaningful milestone ends with:

`Code correct → behavior verified → documentation updated → exact next continuation point recorded`.

A new chat/session must continue from this master status rather than restarting the project or searching through obsolete dated status files.
