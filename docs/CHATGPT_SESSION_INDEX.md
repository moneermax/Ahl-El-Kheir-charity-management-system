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

Continue page-by-page from the actual Supervisor dashboard/navigation. Authoritative sponsor responsibility is **Sponsor first-name letter + sponsor gender → Supervisor**, with direct sponsor assignment retained as an access path. Family access is separate and may follow direct family assignment or sponsor-linked/matrix responsibility.

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
- Local database verification confirmed `sponsors.brought_by_name` exists as `VARCHAR(255) NULL`.
- Remaining sponsor runtime-DDL cleanup completed: `modules/sponsors/view.php` no longer alters the `sponsors` table at request time; `config/sponsor_assignments.php` no longer creates `sponsor_supervisor_assignments` at request time; `modules/sponsors/assign.php` no longer depends on request-time table creation; `modules/sponsors/requests.php` no longer creates/alters `sponsor_requests` during normal requests. Explicit migration added at `database/migrations/2026-09-14_sponsor_workflow_runtime_ddl_cleanup.sql`.
- Sponsor list navigation was aligned with the request-route authorization: the Supervisor no longer sees the Sponsor Requests button because the route does not authorize Supervisor. Management roles that can access the queue retain the button.
- VGM dashboard now exposes the sponsor reassignment task at `modules/sponsors/assign.php`. VGM, Supervisor-protection, and FM-isolation tests all passed. Commit: `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.
- **New Supervisor scope regression fixed:** `modules/sponsorships/index.php` previously filtered Supervisor sponsorships only by the letter+gender matrix and omitted direct sponsor assignment. It now applies **direct sponsor assignment OR letter+gender responsibility**, matching the Sponsor list, Family list, and Supervisor dashboard. Commit: `76be77a4d2e6e337485d3f3c9980780b9de73df0`.

## REQUIRED TEST FOR THE LATEST FIX

After pulling `main`:

1. Log in as Supervisor.
2. Open `http://localhost:8081/AhlElKheir/modules/sponsorships/index.php`.
3. Confirm the page loads normally.
4. Confirm a sponsorship whose sponsor is **directly assigned to this Supervisor** remains visible even if that sponsor is outside the Supervisor's current letter+gender matrix.
5. Confirm matrix-authorized sponsorships remain visible.
6. Confirm sponsorships outside both direct assignment and the letter+gender matrix remain hidden.

Report PASS/FAIL for these three scope cases. Do not create new test data unless an existing controlled record cannot demonstrate the behavior.

## NEXT AUDIT DIRECTION

After this sponsorship-list scope fix passes, continue checking individual sponsorship/family routes for the same authoritative Supervisor scope, especially direct-record access versus list filtering. Do not alter the business rule unless current code/evidence requires it.

## PROTECTED LOCAL FILES

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`
