# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-14

## START HERE

For every new ChatGPT session, read these in order:

1. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — permanent development/continuation rules.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — current high-level project status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — detailed master audit, only as needed for the current task.

This index is the short handoff document. The repository and these documents are authoritative over old chat history.

## CURRENT ACTIVE AUDIT

**Supervisor Module Audit**.

Continue page-by-page from the actual Supervisor dashboard/navigation. The restored supervisor sponsor/family scope rule is completed and must be preserved.

Current governance items are documented in the master status/audit. Do not treat parked items as bugs without current code/evidence.

## NON-NEGOTIABLE CONTINUATION RULES

- Do not restart the project.
- Do not restart completed audits.
- Do not repeat completed tests, fixtures, or SQL verification unless a genuine regression requires it.
- Do not invent SQL table or column names; inspect schema/code first.
- Do not ask the user to manually edit repository files when repository changes can be made directly.
- When the user says proceed/do it/fix it, perform the repository work directly rather than repeatedly describing a plan.
- Preserve intentional local uncommitted work and protected FM dashboard backup files.
- Use server-side authorization as the security boundary.
- Keep project documentation under `docs/` current.

## CURRENT CHECKPOINT

### Last completed

- FM dashboard treasury/admin-fee regression fixed and closed: `783b160a60ce50f0f661a65a112aea7469979ca4`.
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization was centralized across sponsor/sponsorship routes.
- Family orphan sponsorship status display regression fixed: `9901c6318225163ca851fbaeb92514d1774bb681`.
- Master continuation prompt added to `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md`.

### Current open task

Continue the **Supervisor Module Audit** from the actual current repository code and dashboard/navigation.

Priority order:
1. formal organization-wide permission/action matrix;
2. exact supervisor scope for the general sponsor-request queue;
3. controlled review of runtime schema synchronization in remaining sponsor routes;
4. consistent scope enforcement across supervisor sponsor/family/sponsorship routes.

### Do not repeat

Do not rerun closed HR, Accounting Phase 1, notification, ACC1, FM dashboard, or restored family/sponsor-scope audits unless current evidence shows a regression.

### Protected local files

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

### Session handoff rule

After every meaningful milestone, update this file with the new exact checkpoint and update the master status/audit when the project-level state changes.

A new chat can then start with only:

> Continue the Ahl El Kheir project from the repository. Read `docs/CHATGPT_SESSION_INDEX.md` and the master status first. Do not restart completed work. Continue from the current checkpoint. My immediate task is: **[task]**.
