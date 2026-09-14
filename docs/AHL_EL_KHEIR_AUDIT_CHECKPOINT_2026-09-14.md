# Ahl El Kheir Charity Management System — Audit Checkpoint 2026-09-14

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Purpose:** Consolidated continuation checkpoint after the latest accounting, authorization, dashboard, and supervisor-scope work.

> Existing-project continuation record. It does not restart any audit or replace historical audit records.

## Current status

### Closed / completed
- HR foundation and HR audit.
- Core Accounting Audit controls completed through the established checkpoint.
- Notification Integrity Audit at its current evidence boundary.
- Authentication/session hardening and runtime verification.
- Accountant Staff financial/reporting authorization audit, including the previously fixed Arabic dashboard encoding issue.
- FM dashboard treasury/admin-fee regression fix.
- Returned-funds accounting workflow and related disbursement controls already completed before this checkpoint.
- Supervisor sponsor ownership rule: sponsor responsibility is determined by the sponsor's own first-name letter + gender, not by the orphan family or mother's name.

## Authoritative supervisor family/sponsor rule

Family access and sponsor ownership are separate concerns.

For supervisor family access, the restored authoritative rule is:

- direct family assignment grants family access;
- explicit sponsor assignment to a supervisor also allows that supervisor to access the sponsor and the sponsor's related family/orphan data;
- the supervisor letter + gender responsibility matrix grants access to linked sponsor/family/orphan data where applicable;
- sponsor ownership must never be inferred from the mother's name or family first letter.

Restoration commit: `448fa0bfee7fdcd5ce3e7608304de6b1c7f9ba73` — `Restore supervisor sponsor-linked family access rule`.

Do not narrow this rule back to `families.supervisor_id` only.

## FM dashboard regression — closed

Expected treasury row:
1. Cash — `1100`
2. Bank — `1200`
3. Electronic wallet — `1300`
4. Total treasury
5. Administrative fees — `4200`

Fix commit: `783b160a60ce50f0f661a65a112aea7469979ca4` — `Fix FM dashboard treasury admin-fee card rendering`.

The fifth-card regression is closed. Do not reopen without new runtime regression evidence.

## Accountant Staff / ACC1 status

The Accountant Staff Arabic encoding issue was solved and is closed. Current tracked `dashboard/accountant_staff_dashboard.php` contains valid Arabic labels and the intended financial routing.

ACC1/nanny accounting integration was substantially completed, including assignment-based authorization, disbursement management, reopen controls, receipt/return handling, and related accounting integration.

Known controlled assignment: Accountant Staff user `17` → nanny `16`.

Do not restart old ACC1 tests from the beginning. Resume only from a documented open item or new regression.

## Accounting preservation

The canonical detailed accounting evidence remains `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md`.

Do not rerun completed accounting controls or recreate protected fixtures unless a genuine regression or newly introduced mutation route requires it. Do not invent accounting schema names; inspect actual schema/code before SQL.

Core accounting structures include `accounts`, `journal_entries`, and `journal_lines`.

## HR status

HR audit/foundation work is complete and closed. Do not reopen unless a current cross-module audit produces concrete regression evidence.

## Notification status

The System-Wide Notification Integrity Audit is closed at its current evidence boundary. The legacy `config/messaging.php::send_system_notification()` helper remains an architectural note because reliable active caller evidence was not established; it was intentionally not modified speculatively.

## Authentication/session status

Session fixation hardening was applied to `config/session.php` and runtime verified through login/dashboard/logout/re-login.

Fix commit: `9cdf89d30fdee147a919c7cc1056457b1b9d20f3`.

## Git/local safety

Current branch: `main`.

Intentional local backup files that must not be deleted/reset/stashed/overwritten:
- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Commit `f7051ded6fe4c8e346cf7bcb08f48d0535945d01` was inspected; its change concerns a deleted artifact named `links to accountant and nanny dashboards` and is not an active rollback of the tracked Accountant Staff dashboard.

## Current open audit direction

The active direction remains the **Supervisor Module Audit**, continuing page-by-page from the actual Supervisor dashboard navigation.

Previously identified next page: `modules/families/index.php`.

The family-scope restoration itself is completed and must be preserved; do not repeat its completed authorization work unless a regression appears.

Remaining Permission Governance items:
- formal organization-wide permission/action matrix;
- exact role/business scope for supervisor access to the general sponsor-request queue;
- controlled review of runtime schema synchronization such as `modules/sponsors/index.php` performing `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...` during normal page rendering.

No speculative restriction or schema rewrite should be introduced for these parked items.

## Parked future work

1. Direct original ↔ reversal/source navigation in journal detail.
2. Same-page Bootstrap modal UX for missing/stale receipt feedback.
3. Broader accounting auditability enhancements after mutation-route coverage is complete.
4. Formal organization-wide permission/action matrix.
5. Formal report/source/calculation catalog.
6. Final notification event/recipient catalog where not already captured.

## Documentation rule

`Code correct → behavior verified → documentation updated → session index updated → exact next continuation point recorded`.

Do not restart the project or any completed audit merely because a new chat/session begins.
