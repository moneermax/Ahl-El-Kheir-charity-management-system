# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-15

## START HERE

For every new ChatGPT session, read these in order:

1. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — permanent development/continuation rules.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — current high-level project status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — detailed master audit, only as needed for the current task.

This index is the short handoff document. The repository and these documents are authoritative over old chat history.

## CURRENT ACTIVE AUDIT

**Supervisor ↔ Accounting integration review**, within the broader Supervisor Module Audit boundary.

The immediate goal is NOT to re-audit the Supervisor module. The Supervisor module has been working correctly in the tested areas. The next work is to inspect only the actual integration points between Supervisor operational workflows and Accounting authorization/visibility.

## AUTHORITATIVE SUPERVISOR SCOPE RULE

Supervisor sponsor responsibility is determined by:

`Sponsor first-name letter + Sponsor gender → Supervisor`

This is the authoritative business rule for sponsor responsibility. A Sponsor outside the Supervisor's letter+gender responsibility scope must not be visible or accessible to that Supervisor merely through a direct record URL or related list.

Sponsor/family/orphan data follows the legitimate relationship from the in-scope Sponsor through Sponsorship → Child/Orphan → Family, subject to each destination's own record-level authorization.

Do **not** treat family name, mother's name, mother's first letter, family code, or orphan identity as a substitute for Sponsor responsibility.

A historical implementation also contains `sponsor.supervisor_id` direct-assignment paths. Those paths must not be treated as a new independent business rule without confirming the documented workflow. The current audit must distinguish operational assignment mechanisms from the authoritative Sponsor Letter + Gender responsibility rule before changing any code.

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
- After every repository change, tell the user exactly what changed, why, how to test it, the expected result, and what to report.

## COMPLETED CURRENT-CHECKPOINT WORK

- FM dashboard treasury/admin-fee regression fixed and closed: `783b160a60ce50f0f661a65a112aea7469979ca4`.
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization was centralized across sponsor/sponsorship routes.
- Family orphan sponsorship status display regression fixed: `9901c6318225163ca851fbaeb92514d1774bb681`.
- Supervisor dashboard sponsor KPI scope aligned with the established sponsor scope rule: `2a82dd87482544ecd6f5edbf61b095b2c23b39f8`.
- General sponsor-request queue authorization narrowed: Supervisor is not authorized for `modules/sponsors/requests.php`; commit: `ba21c3415158787e2fb294eaf746cf37d7d845bc`.
- Sponsor runtime schema synchronization cleanup completed; explicit migration added for sponsor workflow schema requirements.
- VGM sponsor assignment/reassignment is confirmed as a VGM task. VGM dashboard exposes `modules/sponsors/assign.php`; Supervisor direct access is blocked and FM has no sponsor-assignment action. All three runtime checks passed. Commit: `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.
- **Supervisor sponsorship-list scope regression fixed:** `modules/sponsorships/index.php` had omitted direct assignment while filtering Supervisor sponsorships. The route was aligned with the established implementation pending final business-rule clarification. Commit: `76be77a4d2e6e337485d3f3c9980780b9de73df0`.
- User tested the sponsorship list after that fix: direct-assignment case PASS, matrix-authorized case PASS, outside-both-scopes case PASS.
- **Receipt-file regression fixed and runtime-confirmed:** `modules/transactions/receipt_file.php` now presents a normal Arabic application message when a transaction has no receipt attachment or its referenced file is unavailable, while preserving authorization and valid receipt streaming. Code commit: `ddd5e91e6f90935cd3a7e328f480ae1f8d906e34`; documentation commit: `cd78fe373c72ce4063ff0b1a9c9a66e59a245961`.

## NOTIFICATION CHECKPOINT — 2026-09-15

The shared notification behavior is now confirmed and should not be changed casually:

- Live polling is centralized in the shared notification widget and refreshes every 5 seconds, so new workflow notifications appear without manual page refresh or logout/login.
- Dynamic notification click forms preserve CSRF protection.
- Applicable dashboards share the same unread visual behavior, including a clear red dot beside unread notification titles.
- A full notification history page exists at `modules/notifications/index.php`, and the bell includes `عرض الكل`.
- `مسح الكل` is **menu-only** and **must never delete notification records**. `clear_all.php` records a browser-local cutoff and the shared indicator hides those cleared menu entries while keeping the records available in full history.
- Real notification scenarios already tested include HR leave approval/rejection and password recovery/change-request flows; both work correctly.

Relevant commits:

- `ecc82aa6af0a8201adbb6d6de0c7dbb087e77305` — polling endpoint.
- `b82bd4effca75ffbde5a2f8e1930f326fc3b9664` — live shared notification widget.
- `2ff8f331e575fab9dce9916c783ad3d7d3e63097` — CSRF fix for dynamically rendered notifications.
- `c9b151b960b962c6cddbb1b135ceef6ddd5b4e16` — unread red-dot indicator.
- `01f161ac59c97e033626ba5c447e7793fe52da4c` — full notifications page.
- `0e830c3851eb2efd83f27881ff0b6c998f2d27ca` — bell `عرض الكل` link.
- `391474e6ea894812b9b3eba6e636bba238e8c66a` — non-destructive clear-all backend.
- `ddd9796896c49f4e2aaa150c3182253a6fa42d05` — client-side menu filtering after clear-all.

The user has explicitly tested and confirmed the latest clear-all behavior as correct. Do not rerun these notification tests unless a genuine regression appears.

## IMPORTANT BUSINESS-RULE CLARIFICATION FROM USER — 2026-09-14

The user explicitly confirmed that the system must follow the **Sponsor Letter + Sponsor Gender** responsibility rule as the authoritative Supervisor business rule:

> If a Sponsor is not assigned to the Supervisor's responsibility scope, the Supervisor must not see that Sponsor's data.

Therefore the next audit must not assume that `sponsor.supervisor_id` is an independent authorization rule. Before changing the latest sponsorship-list implementation, inspect the documented business workflow and current repository usage of direct assignment and determine whether it is only an operational assignment mechanism or an actual authorization grant. Do not make a speculative change.

## REQUIRED TESTS ALREADY COMPLETED

The latest sponsorship-list scope tests are complete and PASS:

1. Direct-assignment case — PASS.
2. Letter+gender matrix case — PASS.
3. Outside both scopes — PASS.

Do not repeat these tests unless a genuine regression or new business-rule clarification requires it.

## NEXT AUDIT DIRECTION — SUPERVISOR ↔ ACCOUNTING

Do not continue broad Supervisor-module auditing unless a real integration dependency requires it.

Next inspect the repository for actual Supervisor → Accounting integration points and answer:

1. Can Supervisor access any Accounting page/action directly or indirectly?
2. Does any Supervisor operational workflow create, submit, return, or otherwise mutate an Accounting-controlled record?
3. What financial status/result is appropriate for Supervisor to see without granting Accounting authority?
4. Are Supervisor submissions routed to FM/Accounting using correct actor and scope rules?
5. Are Accounting notifications/results exposed to the correct Supervisor only?
6. Does any Accounting query accidentally expose data outside the Supervisor's legitimate Sponsor scope?

Use existing code and documentation first. Do not recreate old Accounting tests or fixtures unless a genuine integration regression is found.

## LATEST CHECKPOINT — 2026-09-15

Last completed item: notification menu clearing was corrected so **مسح الكل** no longer deletes database notification records; the user confirmed the corrected behavior.

Current active task: continue the focused Supervisor ↔ Accounting integration review by inspecting the remaining real integration points and selecting the next genuinely untested control.

Do not rerun the receipt regression, notification workflow tests, or any other closed audit test unless new evidence shows a regression.

## PROTECTED LOCAL FILES

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`
