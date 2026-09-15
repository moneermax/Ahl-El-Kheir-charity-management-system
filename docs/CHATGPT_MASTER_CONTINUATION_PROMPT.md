# Ahl El Kheir Charity Management System — Master ChatGPT Continuation Prompt

Use this prompt when starting a new ChatGPT session for the existing Ahl El Kheir project.

## PROJECT IDENTITY

Project: **Ahl El Kheir Charity Management System — نظام أهل الخير لإدارة الجمعيات الخيرية**  
Repository: `moneermax/Ahl-El-Kheir-charity-management-system`  
Branch: `main`  
Local path: `D:\xampp\htdocs\AhlElKheir`  
Local URL: `http://localhost:8081/AhlElKheir/`  
Database: `ahl_el_kheir`  
Environment: Windows + XAMPP + Apache + PHP 8.2 + MariaDB/MySQL

Architecture:
- Backend: procedural PHP only — **NO OOP**
- Frontend: HTML/CSS + Bootstrap 5.3 RTL + Vanilla JavaScript
- UI language: Arabic by default
- Icons: Font Awesome 6
- Font: Cairo

## CONTINUATION, NOT RESTART

This is an existing project and an existing audit/development history.

**Do not restart the project, restart an audit, recreate completed fixtures, repeat passed tests, or re-investigate closed areas unless current repository evidence shows a genuine regression or an explicitly requested dependency.**

The current repository and these documents are authoritative over old chat history:

1. `docs/CHATGPT_SESSION_INDEX.md` — short handoff/current checkpoint.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — high-level START HERE status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — single detailed master audit.
4. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — these permanent continuation rules.
5. Read only the module-specific documentation relevant to the immediate task.

Do not search obsolete dated audit files unless the master documents explicitly point to them as still relevant.

## FIRST ACTION IN EVERY NEW SESSION

Before proposing work:

1. Read `docs/CHATGPT_SESSION_INDEX.md`.
2. Read `docs/AHL_EL_KHEIR_MASTER_STATUS.md`.
3. Read the relevant section of `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`.
4. Inspect the actual current repository code for the requested area.
5. Inspect the current schema/code before making any database assumption.
6. Continue from the recorded checkpoint.

Do not ask the user to repeat information already present in those documents.

## REQUIRED DEVELOPMENT WORKFLOW

Use this workflow for actual development work:

`Inspect → Understand → Implement → Verify → Commit → Document → Tell user to Pull → User tests`

When the user says **"proceed"**, **"do it"**, **"fix it"**, or gives an implementation request:

- perform the repository work directly;
- do not merely describe a plan;
- do not ask the user to manually edit code when the change can be made in the repository;
- do not claim a fix before the change is actually implemented;
- inspect the resulting diff/code for unintended changes;
- commit the completed change to the repository;
- update the relevant project documentation;
- give the user the exact safe command to pull the commit;
- then give concise local testing steps.

The user is nontechnical. Keep testing instructions simple and explicit.

## GIT SAFETY

Never use destructive commands such as reset, restore, clean, force-push, or broad stash operations without first inspecting the repository state and confirming what local work would be affected.

Assume uncommitted local work may be intentional.

Known protected local FM dashboard backups:
- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Never delete, overwrite, reset, stash, or clean these backups unless the user explicitly requests it.

When giving a pull command, use a safe sequence appropriate to the user's actual local Git state. Do not tell the user to force-reset their branch merely to obtain a normal update.

## DATABASE / SQL SAFETY

**Never invent table names, column names, relationships, status values, or SQL assumptions.**

Before proposing or executing SQL:

1. inspect the actual schema or current code;
2. confirm the exact table and column names;
3. confirm foreign-key/relationship meaning where relevant;
4. use existing known test fixtures when possible;
5. do not recreate protected audit fixtures unnecessarily.

Prefer repository/code changes and controlled test-data setup over asking the user to manually edit application files.

### Permanent database architecture rule — NO TRIGGERS / NO VIEWS

**This project must not create or use database triggers or database views.**

- Do not add `CREATE TRIGGER`, trigger-based business rules, or trigger-based workflow enforcement to migrations or the live database.
- Do not add `CREATE VIEW` or database-view-based application logic to migrations or the live database.
- Business validation, protected-funds enforcement, workflow sequencing, authorization, and related business rules must be implemented in the procedural PHP application layer.
- Database migrations may create/alter tables, columns, indexes, foreign keys, and other ordinary schema structures required by the application, but must not introduce triggers or views.
- If an older migration introduced a trigger or view, revise the migration so a fresh installation will not create it, and provide a one-time cleanup migration when an already-upgraded development database needs the obsolete object removed.
- When auditing the database, explicitly check for accidental trigger/view introduction before proceeding with further schema work.

This rule exists because hidden database behavior previously caused development/runtime problems and made the application's business rules harder to inspect and test.

Runtime schema mutation such as `ALTER TABLE` during normal page rendering is a review concern and must not be introduced casually.

## AUTHORIZATION AND SECURITY

Server-side authorization is the security boundary. UI visibility alone is never sufficient.

Preserve:
- least privilege;
- separation of duties;
- record/organizational scope;
- CSRF protection where required;
- auditability and actor attribution;
- protected document serving;
- transactional financial mutations;
- existing authentication/session hardening.

Do not weaken authorization merely to make a page accessible.

## BUSINESS RULES THAT MUST NOT REGRESS

### Supervisor sponsor responsibility — authoritative rule

`Sponsor first-name letter + Sponsor gender → Supervisor`

Supervisor sponsor responsibility is determined by the sponsor's own first-name letter and sponsor gender through the configured responsibility matrix.

A Sponsor outside that Supervisor's letter+gender responsibility scope must not be visible or accessible to that Supervisor merely through a direct record URL or related list.

It is not based on the orphan, family, mother, mother's first letter, or family code.

The repository also contains historical/direct sponsor assignment paths using `sponsor.supervisor_id`. Do not automatically treat that field as a separate business-rule authorization grant. Before changing such logic, inspect its documented workflow and actual current usage to determine whether it is an operational assignment mechanism or an authorization grant.

### Supervisor data visibility

Within legitimate scope, the Supervisor may access the operational chain:

`Sponsor → Sponsorship → Child/Orphan → Family`

subject to each destination's own server-side record authorization.

### Supervisor vs Accounting

Supervisor is an operational oversight role, not an Accounting role. A workflow having a financial consequence does not by itself grant Supervisor Accounting permissions.

Supervisor must not gain Accounting journal/ledger/financial-control authority merely because Supervisor creates or submits an operational sponsorship/payment workflow. Any Supervisor → Accounting integration must preserve separation of duties and route financial approval/posting through the authorized Accounting roles.

### Disbursement / returned funds

By the end of the monthly cycle, every payment record must have an operational/accounting status.

A nanny cannot keep funds indefinitely. If a family cannot be reached or payment cannot be completed, the applicable amount must be returned and recorded as an explicit financial event with the required reason/evidence/accounting effect.

### Accounting

Do not rewrite or relabel historical accounting evidence merely to satisfy a query. Preserve actual reference semantics, balanced journals, reversal relationships, and auditability.

Development/test accounting data is not production financial data.

## CURRENT PROJECT BOUNDARY — 2026-09-15

At the current checkpoint:

- HR foundation/audit is closed at its documented boundary.
- Accounting Audit is closed at its documented boundary, with only targeted follow-up items documented in the master audit.
- Notification Audit is closed at its documented evidence boundary.
- Accountant Staff financial/reporting and disbursement authorization work is completed at the documented checkpoint.
- FM dashboard treasury/admin-fee regression is fixed and closed.
- Supervisor sponsor ownership and sponsor-linked family access restoration is completed and must be preserved.
- Supervisor sponsorship-list scope regression was fixed at `76be77a4d2e6e337485d3f3c9980780b9de73df0` and the three scope tests passed.
- VGM sponsor assignment/reassignment is confirmed as a VGM task; VGM, Supervisor-protection, and FM-isolation tests passed at commit `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.
- Missing/stale transaction receipt handling regression was fixed at `modules/transactions/receipt_file.php` and runtime-confirmed by the user. Code commit: `ddd5e91e6f90935cd3a7e328f480ae1f8d906e34`; documentation commit: `cd78fe373c72ce4063ff0b1a9c9a66e59a245961`.
- Shared notification live behavior, unread visual indicator, full notification history link, and non-destructive menu clearing are completed and user-confirmed.

## NOTIFICATION CHECKPOINT — 2026-09-15

The shared notification behavior is centralized and must be preserved:

- `modules/notifications/poll.php` provides authenticated polling for unread/recent notifications.
- The bell polls every 5 seconds, so new notifications appear without manual refresh or logout/login.
- Dynamic notification actions retain CSRF tokens.
- Applicable dashboards share the same unread visual behavior, including the red dot beside unread notification titles.
- `modules/notifications/index.php` provides full notification history and the bell has `عرض الكل`.
- `مسح الكل` is strictly **menu-only**. It must never delete records from `notifications`.
- `modules/notifications/clear_all.php` now records a browser-local notification-ID cutoff instead of deleting rows.
- `assets/js/notification_unread_indicator.js` hides menu entries at/below the cutoff and recalculates the visible unread badge after live polling.
- Notification records remain available in full history for audit/history purposes.
- Real workflow scenarios already tested and working include HR leave approval/rejection and password recovery/change-request notifications.

Relevant commits:

- `ecc82aa6af0a8201adbb6d6de0c7dbb087e77305` — polling endpoint.
- `b82bd4effca75ffbde5a2f8e1930f326fc3b9664` — live shared widget.
- `2ff8f331e575fab9dce9916c783ad3d7d3e63097` — dynamic notification CSRF fix.
- `c9b151b960b962c6cddbb1b135ceef6ddd5b4e16` — unread red-dot indicator.
- `01f161ac59c97e033626ba5c447e7793fe52da4c` — full notifications page.
- `0e830c3851eb2efd83f27881ff0b6c998f2d27ca` — bell `عرض الكل` link.
- `391474e6ea894812b9b3eba6e636bba238e8c66a` — non-destructive clear-all backend.
- `ddd9796896c49f4e2aaa150c3182253a6fa42d05` — menu filtering after clear-all.

## NEXT TASK — SUPERVISOR ↔ ACCOUNTING INTEGRATION

Inspect the actual repository and documentation for every real integration point between Supervisor operational workflows and Accounting.

Answer these questions from code/evidence before changing anything:

1. Can Supervisor access any Accounting page/action directly or indirectly?
2. Does any Supervisor operational workflow create, submit, return, or otherwise mutate an Accounting-controlled record?
3. What financial status/result is appropriate for Supervisor to see without granting Accounting authority?
4. Are Supervisor submissions routed to FM/Accounting using the correct actor and scope rules?
5. Are Accounting notifications/results exposed only to the correct Supervisor?
6. Does any Accounting query accidentally expose data outside the Supervisor's operational Sponsor scope?
7. Does any dashboard KPI or summary shown to Supervisor contain Accounting data that is broader than the Supervisor's legitimate operational scope?

Do not repeat the closed Accounting Audit. Reuse its findings as the baseline and investigate only the integration boundary.

## DOCUMENTATION RULES

Documentation is part of the implementation, not optional cleanup.

Keep project documentation under `docs/` up to date.

Maintain:

- `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` as the **single detailed master audit**;
- `docs/AHL_EL_KHEIR_MASTER_STATUS.md` as the high-level current status;
- `docs/CHATGPT_SESSION_INDEX.md` as the short handoff/checkpoint;
- `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` as the permanent continuation rules.

Do not create a new dated session-status or duplicate master-audit file for every chat.

After every meaningful milestone:

`Code correct → behavior verified → documentation updated → exact next continuation point recorded`

The checkpoint should make clear:
- current date;
- active phase/module;
- last completed work;
- current open task;
- exact next task;
- what must not be repeated;
- protected evidence/fixtures;
- important local Git warnings.

## REGRESSION RULE

If a closed area breaks because of new work:

1. treat it as a regression;
2. identify the actual root cause;
3. make the smallest correct fix;
4. verify the regression and the affected integration;
5. document the regression and fix;
6. do not restart the entire old audit.

## CODE QUALITY RULES

Prefer narrow, maintainable fixes that follow existing project conventions.

Do not introduce OOP into procedural modules.
Do not duplicate authorization logic when an established authoritative helper exists.
Do not create speculative abstractions merely for theoretical future use.
Do not silently change unrelated behavior.

When an authoritative scope/helper exists, reuse it rather than implementing a conflicting second rule.

## USER COMMUNICATION RULE

The user does not want repeated explanations or long plans.

For completed work, communicate briefly:

- **Found:** what was actually wrong.
- **Root cause:** why it happened.
- **Changed:** what was implemented in the repository.
- **Commit:** exact commit hash/message.
- **Pull:** exact safe command.
- **Test:** exact simple test steps.
- **Next:** the next checkpoint/task.

If more investigation is genuinely required, explain only the specific missing evidence and why it is needed.

Never ask the user to repeat already documented project history.

## EMERGENCY NEW-CHAT MODE

If the previous ChatGPT session ended unexpectedly, use the repository as the recovery mechanism.

Read the four continuation documents listed above, inspect current Git/repository state, and continue from the recorded checkpoint.

The user should only need to provide the immediate new request, for example:

> Continue the Ahl El Kheir project from the repository. Read `docs/CHATGPT_SESSION_INDEX.md` and the master status first. Do not restart completed work. Continue from the current checkpoint. My immediate task is: **[task]**.

Do not require the user to paste the entire historical audit into the new chat.

## LATEST DOCUMENTATION CHECKPOINT — 2026-09-15

The documentation handoff has been synchronized with the latest confirmed notification work: live polling, shared unread red-dot indicator, full notification history access, CSRF-safe dynamic notification actions, and non-destructive `مسح الكل`. The next substantive task remains the focused Supervisor ↔ Accounting integration review.
