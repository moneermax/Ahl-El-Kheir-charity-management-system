# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-14

## START HERE

For every new ChatGPT session, read:

`docs/AHL_EL_KHEIR_MASTER_STATUS.md`

That file is the single high-level continuation point. Do not search through old dated status files or restart completed audits.

## Detailed documents

Use only the document relevant to the task:

- `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — overall current status, completed work, open work, business rules, and continuation point.
- `docs/AHL_EL_KHEIR_ACCOUNTING_AUDIT.md` — detailed accounting evidence and accounting-specific continuation.
- `docs/AHL_EL_KHEIR_NOTIFICATION_AUDIT.md` — notification audit.
- `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` — architecture/system analysis baseline.
- `docs/ORGANIZATIONAL_LIFECYCLE_AUDIT_2026-09-03.md` — organizational lifecycle detail.
- `docs/HR_DASHBOARD_NAVIGATION_AUDIT_2026-09-08.md` — completed HR navigation evidence.
- `docs/I18N.md` — internationalization rules.
- `docs/PRODUCTION_PREPARATION.md` — production preparation and cleanup procedure.

## Current active audit

**Supervisor Module Audit**.

Continue page-by-page from the actual Supervisor dashboard/navigation. The restored supervisor sponsor/family scope rule is completed and must be preserved.

## Non-negotiable continuation rules

- Do not restart the project.
- Do not restart completed audits.
- Do not repeat completed tests/fixtures/SQL verification unless a genuine regression requires it.
- Do not invent SQL table or column names; inspect schema/code first.
- Do not ask the user to manually edit repository files when repository changes can be made directly.
- Preserve the intentional local FM dashboard backup files documented in the master status.

## Supervisor rule to preserve

Sponsor ownership:

`Sponsor first-name letter + Sponsor gender → Supervisor`

Family access remains broader than direct `families.supervisor_id` only: direct family assignment, sponsor assignment to the supervisor, and the established letter+gender responsibility matrix can grant access to linked family/orphan data.

Restoration commit: `448fa0bfee7fdcd5ce3e7608304de6b1c7f9ba73`.

## Latest closed regressions

- FM dashboard treasury/admin-fee card fix: `783b160a60ce50f0f661a65a112aea7469979ca4`.
- Accountant Staff Arabic encoding issue: solved/closed.
- Authentication/session fixation hardening: `9cdf89d30fdee147a919c7cc1056457b1b9d20f3`.

## Documentation hygiene

Historical duplicate status/audit documents have been consolidated. Do not create a new dated session-index or status file for every chat. Update the master status and this short index when a meaningful project milestone changes the continuation point.
