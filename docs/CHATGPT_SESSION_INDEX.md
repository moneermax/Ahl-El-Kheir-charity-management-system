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
- Supervisor dashboard sponsor KPI scope aligned with the authoritative direct-assignment + letter/gender matrix rule: `2a82dd87482544ecd6f5edbf61b095b2c23b39f8`.
- General sponsor-request queue authorization narrowed: Supervisor is no longer an authorized role for `modules/sponsors/requests.php`; the route remains available to its designated management/social-media roles. Commit: `ba21c3415158787e2fb294eaf746cf37d7d845bc`.
- Runtime schema synchronization review found sponsor create/edit routes were executing `ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS brought_by_name ...` during normal page requests. The DDL was removed from both routes, and an explicit idempotent migration was added at `database/migrations/2026-09-14_sponsors_brought_by_name.sql`. Commits: `cd23c78e6f72faba60a5e71c1cc024d4000ca7ff`, `a7228ab22d22f1a55ef077f7a31ebe267a45c356`, `30fed415857a4189fe8487652ad1c44f1e56a1b6`.

### Current open task

Continue the **Supervisor Module Audit** from the actual current repository code and dashboard/navigation.

Priority order:
1. controlled review of remaining sponsor/family/sponsorship routes for runtime schema synchronization or other unsafe request-time DDL;
2. consistent scope enforcement across supervisor sponsor/family/sponsorship routes;
3. formal organization-wide permission/action matrix;
4. revisit sponsor-request access only if new business/code evidence indicates another role scope is required.

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
