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

## HIGH-PRIORITY FOLLOW-UP — LEGACY HR DATABASE TRIGGERS

**Immediately after the current Fina Al-Khair work is finished, audit and remove/replace the remaining legacy HR-related database triggers in a controlled manner. This is a highly required follow-up, not optional cleanup.**

The HR audit itself is already **CLOSED** and must not be restarted. This task is specifically an architectural cleanup of the remaining database triggers so their business behavior is moved into the procedural PHP application layer without breaking the already-verified HR workflows.

Required sequence:

1. Inventory every remaining database trigger and identify its source migration/history.
2. Inspect the HR PHP callers and determine exactly what behavior each trigger currently provides.
3. Preserve equivalent validation/synchronization behavior in procedural PHP.
4. Revise the original migrations so fresh installations do not recreate the triggers.
5. Add controlled one-time cleanup migrations for already-upgraded development databases.
6. Run only targeted HR regression tests required to prove the trigger replacement; do not restart the completed HR audit.

**Do not start this HR trigger cleanup until the current Fina Al-Khair integration work is complete.**

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
- Supervisor sponsor ownership and scope is completed and must be preserved.
- Supervisor ↔ Accounting integration boundary is PASS / CLOSED at the tested evidence boundary.
- The active substantive work is the Fina Al-Khair integration and accounting-control implementation/audit.

## FINA AL-KHAIR INTEGRATION — CURRENT WORK

Fina Al-Khair is a joint operational/accounting partner. Its share of a sponsor payment is third-party protected funds and **never Ahl El Kheir revenue**. Ahl administrative fees apply only to the Ahl share. Settlement to Fina reduces the Fina payable and is not an Ahl expense.

Current foundation includes:

- liability account `2300` — `مستحقات لصالح فينا الخير`;
- structured Fina payment intake/allocation tables;
- sponsor monthly obligation model separate from actual payments and payment allocation;
- Fina settlement and settlement-allocation structures;
- procedural PHP Fina-aware transaction/journal logic;
- Fina payment Supervisor intake and FM approval workflow;
- Fina outstanding and settlement/evidence pages;
- one-time cleanup migration removing the obsolete Fina database triggers.

**No database triggers and no database views are permitted.** Fina protected-funds enforcement and workflow rules must remain in procedural PHP.

Current Fina runtime audit order:

1. Supervisor Fina intake.
2. FM approval/posting and balanced Fina-aware journal.
3. Sponsor obligation partial-payment then completion behavior.
4. Fina outstanding balance.
5. Fina settlement separation of duties and settlement journal.
6. Settlement evidence.
7. Protected-funds enforcement in PHP.
8. Disbursement transaction validation in PHP.
9. Targeted non-Fina regression only where needed.
10. Access/security checks.

Do not jump ahead or recreate completed Supervisor/Accounting fixtures. Inspect the actual current schema/code before any new SQL or test-data setup.

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

The documentation handoff is now synchronized with the Fina Al-Khair foundation work and the permanent no-trigger/no-view architecture rule. The remaining legacy HR database triggers are explicitly marked as a **HIGH-PRIORITY / HIGHLY REQUIRED follow-up immediately after the current Fina work is completed**. The HR audit remains closed and must not be restarted; only the trigger architectural cleanup and targeted regression tests remain.
