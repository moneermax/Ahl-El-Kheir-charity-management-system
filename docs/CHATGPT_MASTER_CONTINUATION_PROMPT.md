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

## CURRENCY POLICY — SDG ONLY

The system-wide application currency is:

- `APP_CURRENCY_CODE = 'SDG'`
- `APP_CURRENCY_NAME_AR = 'الجنيه السوداني'`
- `APP_CURRENCY_SYMBOL = 'ج.س'`

Individual transaction/Fina entry forms must not ask users to choose a currency when the application already has the system-wide currency policy.

For Fina:
- no visible currency selector;
- no redundant hidden browser currency field;
- server-side creation uses `APP_CURRENCY_CODE`;
- `fina_collections.currency_code` remains in the database for historical/accounting evidence.

## UI FORM STANDARD — CURRENT DIRECTION

When forms are created or touched during normal work:

- place label + field inline/horizontally where practical;
- size controls according to expected data length;
- keep short fields such as phone, date, amount, and IDs compact;
- give longer fields such as address, purpose, source details, and descriptions more width;
- preserve responsive/mobile usability.

Do **not** blindly redesign every form during the accounting audit. If a systematic/global redesign is later justified, inspect shared CSS/components first and implement it consistently rather than duplicating ad-hoc page CSS.

The Fina entry form is the first current implementation of this direction:

- `3d84d57e2de0acb3fefb1fe4fdcc2ed9c4b703c9` — remove redundant Fina currency field.
- `e2ea1ab741da709e6b5543c85556fcbc7a15c765` — improve Fina form field layout and sizing.

Runtime visual confirmation of the latest Fina form remains pending.

## CURRENT PROJECT BOUNDARY — 2026-09-17

At the current checkpoint:

- HR foundation/audit is closed at its documented boundary.
- Accounting Audit is closed at its documented boundary, with only targeted follow-up items documented in the master audit.
- Supervisor ↔ Accounting integration is PASS / CLOSED at the tested evidence boundary.
- Accountant Staff financial/reporting and disbursement authorization work is completed at the documented checkpoint.
- FM dashboard treasury/admin-fee regression is fixed and closed.
- Supervisor sponsor ownership and sponsor-linked family access restoration is completed and must be preserved.
- Supervisor sponsorship-list scope regression was fixed and its scope tests passed.
- VGM sponsor assignment/reassignment is confirmed as a VGM task; VGM, Supervisor-protection, and FM-isolation tests passed.
- Missing/stale transaction receipt handling regression was fixed and runtime-confirmed by the user.
- Fina standalone schema lifecycle hardening is implemented.
- Fina authenticated receipt viewer is runtime-confirmed working.
- Fina currency selector was removed and system-wide SDG is enforced by the entry workflow.
- Fina entry form compact horizontal label/field layout is implemented; visual runtime check remains pending.
- The live right-corner notification toast regression was fixed, and its persistence-until-read behavior is now restored in code; runtime verification remains pending.

## NOTIFICATION CHECKPOINT — 2026-09-17

The shared notification behavior is centralized and must be preserved:

- `modules/notifications/poll.php` provides authenticated polling for unread/recent notifications.
- The bell polls every 5 seconds, so new notifications appear without manual refresh or logout/login.
- Dynamic notification actions retain CSRF tokens.
- Applicable dashboards share the same unread visual behavior, including the red dot beside unread notification titles.
- `modules/notifications/index.php` provides full notification history and the bell has `عرض الكل`.
- `مسح الكل` is strictly **menu-only**. It must never delete records from `notifications`.
- `modules/notifications/clear_all.php` records a browser-local notification-ID cutoff instead of deleting rows.
- `assets/js/notification_unread_indicator.js` hides menu entries at/below the cutoff and recalculates the visible unread badge after live polling.
- Notification records remain available in full history for audit/history purposes.
- Real workflow scenarios already tested and working include HR leave approval/rejection and password recovery/change-request notifications.

### Live right-corner toast — restored to the original persistence behavior

The previous regression had two related symptoms: the toast implementation was missing, and the desired persistence behavior was absent. The required behavior is:

1. A newly received notification produces the small right/top-corner pop-up while the user is on an open dashboard/page.
2. If the notification remains **unread**, opening the dashboard again or refreshing the page must show that unread notification pop-up again.
3. The pop-up may be dismissed visually, but dismissing it does **not** mark the notification as read.
4. The pop-up stops reappearing only after that specific notification is actually opened/read.
5. Clicking the pop-up marks that specific notification as read through the existing CSRF-protected `mark_read.php` action and then follows its actionable destination.
6. New notifications discovered by the existing 5-second polling continue to produce a pop-up immediately.
7. This behavior applies to the shared notification widget, including the FM dashboard and other applicable dashboards; it is not a Fina-only behavior.

Implementation:

- `includes/notification_widget.php` provides the lightweight `AKNotify.toast()` implementation.
- Initial polling now displays unread notifications so an unread notification survives page refresh/new-tab navigation as a visible reminder.
- Toast click marks the specific notification read before following its destination.
- Close/dismiss only hides the current toast; it deliberately does not mark the notification read.

Latest commit:

- `908efe8c688316a3b7c4c637da4424fb6d6500af` — restore persistent unread notification toast behavior.

The earlier toast-only implementation commit was `e248ae74f6a69ea69ec1781df63a42b09cbd9699`; `908efe8c...` supersedes it for the complete required behavior.

### Immediate runtime verification — pending

Use the FM dashboard and a new Fina payment submitted by the Supervisor as the test scenario.

1. Have the Supervisor submit a new Fina payment that creates the normal FM notification.
2. Keep the FM dashboard open and wait for the existing polling interval.
3. Confirm the small right/top-corner pop-up appears without refreshing.
4. **Do not open/read the notification yet.** Refresh the FM dashboard.
5. Confirm the same unread notification pop-up appears again.
6. Open the dashboard in a new browser tab while the notification is still unread and confirm the pop-up appears again.
7. Close/dismiss the pop-up without opening the notification; refresh once more and confirm it still appears.
8. Finally click/open the notification.
9. Confirm it is marked read and the pop-up does not return after another refresh/new tab.
10. Confirm the notification remains correctly represented in the bell/history according to the existing rules.

This is a targeted regression test only. Do not repeat the closed notification audit scenarios.

## FINA STANDALONE PAYMENT

Fina is a third-party protected fund:

- Fina money is not Ahl El Kheir revenue.
- Fina share is a protected liability/control amount.
- Dedicated control/liability account: `2300`.
- Fina uses `fina_sources` and `fina_collections`.
- Entry: `modules/transactions/fina_payment_create.php`.
- Review: `modules/accounting/fina_payment_review.php`.
- Authenticated receipt serving: `modules/accounting/fina_receipt.php`.
- Normal request-time schema creation is removed; migration is authoritative.
- Supervisor is a primary operational user but remains read-only at FM approval/posting.

Do not confuse Fina liability with sponsor obligation. A sponsor obligation shortfall must remain a sponsor-accounting issue and must not be absorbed into Fina money.

## CLOSED ACCOUNTING / NOTIFICATION AREAS

Do not repeat unless genuine regression evidence appears:

- HR/payroll audit baseline.
- FM treasury calculation and admin-fee regression tests.
- Supervisor bank-transfer and mobile-wallet posting tests.
- Supervisor sponsor scope tests.
- VGM sponsor assignment/reassignment tests.
- Receipt-file regression test already confirmed by user.
- Fina receipt authorization test already confirmed by user.
- Fina authorization/scope tests already completed.
- Disbursement/reissue test batch #12.
- Transaction void test `TR-000016` / `JE-VOID-TXN-22`.
- Manual journal `JE-000027` and its balance/authorization checks.
- Existing notification audit scenarios, except the current missing-toast persistence regression test.

## NEXT ACCOUNTING AUDIT — JOURNAL CROSS-MODULE INTEGRITY

After the immediate notification-toast runtime check and Fina form visual check, continue the targeted remaining automated journal reference types:

- `payroll`
- `disbursement_void`
- `item_return`

Existing semantics are authoritative:

- `disbursement_void` is created by the batch-void workflow and references the monthly disbursement.
- `item_return` is created by the partial item-return workflow and references the disbursement item.
- `payroll` is created by `modules/hr/lib_payroll_accounting.php` and links to the payroll record.

The existing protection commit is `8e3ee6fd7d95c0efef834a284ed428d125064bfc`.

### Next workflow

1. Inspect actual current callers/workflows for the three reference types.
2. Inspect the actual schema before any SQL or test fixture creation.
3. Reuse existing evidence if a control is already genuinely proven.
4. Create only new controlled test data when a missing runtime control requires it.
5. Verify server-side protection against manual journal void/mutation.
6. Verify journal creation, linkage, balance, and source record state for each newly tested automated route.
7. Inspect remaining callers of `ak_void_journal_for_voucher()` and direct journal mutation routes.
8. Document each result in the single master audit and continuation index.

Do not relabel reference types or alter historical accounting evidence merely to make an audit query pass.

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
- last commit;
- current open task;
- exact next task;
- what must not be repeated;
- protected evidence/fixtures;
- important local Git warnings.

## FUTURE UI CHECKLIST — PARKED FOR LATER

The broader form UX improvement is intentionally parked for systematic handling later:

- labels + fields inline where practical;
- control widths matched to expected data;
- phone/date/amount/ID fields compact;
- long text/address/purpose fields wider;
- responsive/mobile behavior preserved;
- apply the standard when touching a form during normal work;
- if a global redesign is warranted, inspect shared CSS/components first instead of adding duplicated page-specific CSS.

Do not interrupt the accounting audit to redesign unrelated forms unless the user explicitly asks for that work.

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

## LATEST DOCUMENTATION CHECKPOINT — 2026-09-17

The handoff is synchronized with the latest Fina SDG-only policy, compact horizontal Fina entry-form layout, and the restored persistent-until-read right-corner notification toast. The immediate runtime checkpoint is to pull the latest repository changes and test that an unread FM notification reappears after refresh/new-tab until that specific notification is opened/read. After that, continue the targeted Accounting Journal Cross-Module Integrity review for `payroll`, `disbursement_void`, and `item_return`.


## DASHBOARD / NAVIGATION REVIEW — 2026-09-18

The current active development direction is dashboard UX and role-safe navigation, after parking the accounting audit.

### Universal Reports architecture

A single role-aware Reports Dashboard is now the intended entry point:

- modules/reports/index.php
- central registry: modules/reports/report_registry.php
- sidebar uses one Reports link rather than a report-specific dropdown;
- direct report URLs enforce the same central authorization;
- reports are separate from workflow authority;
- GM and VGM must retain access to organizational reports needed for management decisions;
- FM retains financial workflow/control authority;
- do not remove management reporting visibility merely because a report concerns finance.

Relevant commits include:
- e2a7fd77c6a98c60fa3ab52a22babfc023871e2b — central report registry.
- e78cc78311da10757ef9f48de9ad3c3790cd75e3 — universal Reports Dashboard.
- 75b75a7a551e9e721a94eae91b653dac7e928e80 — global header navigation cleanup.
- d8500d69d8361e74873408159bb8bd7079613a6e — sidebar navigation consistency.
- 075b255e4b0360612624ab5779858a7149f32c88 — remove VGM direct reconciliation shortcut in favor of Reports Center.

### VGM / FM navigation separation

VGM no longer receives FM workflow notification/action navigation for supervisor collection review. VGM retains management/reporting access, including reconciliation through the reporting architecture.

FM sidebar is intentionally streamlined:
لوحة التحكم المالية → اليومية → مشاريع المنظمة → الرصيد الافتتاحي → التحويلات الشهرية → الحاضنات المكلفات → طابور المراجعة المالية → التقارير → ملفي الشخصي → تسجيل الخروج

Do not reintroduce duplicate report/reconciliation links merely for convenience.

### Other dashboard review completed

The following dashboards were reviewed/updated:
- Accountant Staff: direct my_financial.php shortcut replaced by universal Reports entry.
- Nanny: direct sponsor-monthly-report shortcut replaced by universal Reports entry.
- Admin: Reports shortcut added while preserving system/admin tools.
- HR dashboard: Reports shortcut added; duplicate HR Staff sidebar Reports entry removed.
- Projects Manager / Project Supervisor: retained as dedicated project workspace; no speculative Reports access was added because their current role catalog does not authorize it.
- Administration/Staff/Social Media: dashboard/staff_dashboard.php was developed beyond its former "under development" placeholder for Administration/Staff/Social Media operational needs.

### Administration / Staff dashboard — current state

dashboard/staff_dashboard.php now provides Administration/Staff/Social Media operational indicators and recent sponsor-request visibility, plus quick access to:
- sponsor requests;
- winback follow-ups;
- orphan forms;
- Reports only where the role's report authorization actually permits it.

The Administration sidebar includes:
- Dashboard;
- sponsor requests;
- orphan forms;
- winback follow-ups.

modules/sponsors/requests.php was updated to allow administration and staff in addition to its existing authorized roles:
admin, vice_general_manager, general_manager, administration, staff, social_media.

### Schema corrections made during Administration dashboard work

The live schema established:
- sponsorships.child_id references family_children.id;
- family_children.family_id references the family;
- sponsorships has no family_id;
- there is no children table in the current database.

Therefore family sponsorship queries must use:
sponsorships → family_children → families.

This was applied to:
- dashboard/staff_dashboard.php;
- modules/administration/winback.php.

Latest relevant commits:
- 8a2dabea8b7a7a6854c2a08ecad2c9d106e36a86 — Administration dashboard sponsorship-family query correction.
- 0d26093b53864b205ee42e4d91fe04cc7dfaee2c — Winback sponsorship-family query correction.
- 1257b54f8718c5c4546f6006191f51921518836f — Administration dashboard link/role alignment.

The user runtime-tested the Administration dashboard after these fixes and confirmed it now loads without errors. The remaining dashboard work is not closed: the next session must continue reviewing the same dashboard for UX, functional correctness, role-appropriate links, labels, statistics, and any remaining runtime issues.

### Immediate next task

Continue the Administration/Staff/Social Media dashboard review from the current working state. Do not restart the dashboard implementation. First inspect the current repository code and preserve the existing fixes. Then address the remaining issues the user identifies, verifying actual schema/code before any SQL assumption.


### LATEST DASHBOARD REFINEMENT — 2026-09-18

Commit: `916758bf205bebbd43805eee010f1dc6163dbe7e` — `Refine administration staff social dashboard role-specific UX`.

`dashboard/staff_dashboard.php` now keeps the shared operational dashboard role-aware:
- Administration sees Winback/uncovered-family indicators and Winback actions.
- Staff and Social Media do not see Winback-only indicators/actions from this shared dashboard.
- Sponsor-request status values are presented with Arabic labels.
- Sponsor phone numbers are clickable when available.
- Recent sponsor-request rows include an action back to the authorized sponsor-request workflow.
- Quick actions remain limited to workflows available to the current role.

The user must pull and runtime-test this refinement. Do not treat the dashboard as finished yet. Continue reviewing KPI meaning, sponsor-request workflow usability, orphan-form navigation, role separation, sidebar consistency, Arabic encoding, responsive behavior, and remaining runtime issues.

Documentation was updated in `docs/CHATGPT_SESSION_INDEX.md` by commit `a14ee88f7e26844f7bcbfad434231f8ac21d31ad`.

## MESSAGING MODULE — ORIGINAL WORKING DESIGN RESTORED — 2026-09-18

The internal messaging UI is now restored to the exact version immediately before the user-identified redesign commit.

- Redesign commit used as the historical anchor: `285d390c00f983b82e8bb59248a555b7997cfd3e`
- Restored source: `9520bd3284bbf4060711f101be1f6518fbd6f087` — the immediate parent of `285d390...`
- Restoration commit: `baacaa5a9812ff4f89c498b2380623342c226d2e`
- Primary restored file: `modules/messages/index.php`

### Decision

The user rejected the later Gmail/custom visual redesigns and explicitly requested the original working design. The correct historical version was therefore restored exactly from the immediate parent of `285d390...`, rather than approximated through another CSS redesign.

**Do not redesign the messaging workspace again unless the user explicitly requests a new design.**

### Preserved functionality

The restored version retains the established messaging functionality and global application shell, including inbox/sent navigation, message list and conversation overlay, compose and reply, attachments, emoji button, font-size control, unread filters, mark-all-read, role broadcast, and the existing messaging backend actions.

The later CSS that hid the global application sidebar/header and converted messaging into a full-page Gmail-style workspace is no longer part of the restored version.

### Current status

**Messaging original-design restoration: COMPLETE / ACCEPTED by user.**

Future messaging work must preserve this visual baseline and address only explicitly requested changes.


## AUDIT LOG CLEANUP — COMPLETED 2026-09-19

The audit-log retention control at `modules/logs/audit.php` is complete at the current boundary.

Current behavior:
- admin-only deletion;
- explicit inclusive start/end date range;
- required checkbox + browser confirmation;
- server validation;
- pre-delete count + post-delete verification;
- accurate result messaging;
- General Manager remains read-only;
- `old_values` / `new_values` remain available as collapsed JSON audit evidence.

Final fix:
`4b2bdcb75faf0dc6c004c48c14fb9e0d44ac4b46`.

The user has confirmed the page now opens and works. Do not repeat this audit unless a regression or new retention requirement is reported.

## CURRENT ACTIVE DIRECTION

Continue from the latest repository/documentation checkpoint. The completed audit-log cleanup is not the next audit area. When the user identifies the next dashboard/module to audit, read the session index and relevant master-audit section, inspect the current code and actual schema, and continue that area without reopening closed work.


## ORPHAN PROFILE SPONSORSHIP UX — 2026-09-19

A direct additional-sponsorship workflow is now implemented in `modules/families/orphan_profile.php`.

Do not regress this behavior: when an orphan already has one or more sponsorships, authorized users should be able to add another sponsor from the same orphan profile through **إضافة كفيل آخر**, without leaving the profile to search for the orphan again.

Preserve:
- multiple sponsorship rows per orphan;
- server-side sponsor authorization;
- Supervisor scope based on Sponsor first-name letter + Sponsor gender via `supervisorCanAccessSponsor()`;
- exclusion/rejection of sponsors already active/paused for the same orphan;
- SDG system currency policy;
- sponsorship code generation and audit logging;
- existing sponsorship edit/view workflow.

Implementation commit: `b0ba3f92d1ee9ea542936f9ce8b381c03f964b45`.

Runtime verification is pending after the user pulls the commit.

### Additional Sponsorship — Searchable Sponsor Selector

The **إضافة كفيل آخر** modal in `modules/families/orphan_profile.php` uses a searchable sponsor selector. Preserve this UX rather than reverting to a long native `select`. Search must match sponsor name or sponsor code, while the submitted value remains the sponsor database ID. Do not weaken the server-side sponsor authorization, Supervisor scope, or duplicate active/paused sponsorship checks.

Implementation commit: `95089efedd84f05c1f8753ca1aabfd9bff1f1711`.
\n\n### LATEST SPONSOR AUTOCOMPLETE REFINEMENT — 2026-09-19\n\nCommit: `46c3b0213a2e769f07158214d87575c39ebaade5` — `Improve multi-word sponsor autocomplete matching`.\n\nThe authorized sponsor autocomplete endpoint now performs progressive multi-word name matching: each typed word must match the beginning of the corresponding sponsor-name word, while sponsor-code searches retain partial/contains matching. Supervisor authorization, Letter + Gender scope, final `supervisorCanAccessSponsor()` check, and active/paused duplicate exclusion remain enforced.\n\nRuntime confirmation currently covers successful additional sponsorship creation for child `234` (ابراهيم تاج السر ابراهيم / `IMP-SP-002363` / 2,000.00 SDG / active / 2026-09-19). Do not claim the autocomplete keystroke test itself is runtime-verified until it is explicitly observed.\n\n### NEXT TASK\n\nContinue the open Administration/Staff/Social Media dashboard review at `dashboard/staff_dashboard.php`. Inspect the current code and linked pages before changing anything. Focus on KPI semantics, sponsor-request table/action UX, Winback integration, orphan-form navigation, role-specific visibility, Reports authorization, Arabic encoding, responsive UX, and runtime issues.\n

### Administration dashboard review — first linked-page finding — 2026-09-19

Repository inspection of `modules/administration/winback.php` found request-time schema mutation code that contradicted the documented runtime-DDL cleanup rule. The page was dropping legacy blacklist triggers/table and creating `winback_campaigns` / `winback_contacts` on normal page requests.

This has been corrected narrowly:
- `modules/administration/winback.php` no longer creates, drops, or alters schema during a web request.
- Explicit migration added: `database/migrations/2026-09-19_winback_schema.sql`.
- Implementation commits: `67576f02e2642ec018d5aeb053f6db75dae925bb` and `6ff62b70528d7a10738a73e90250be1b937ba7e2`.

**Runtime status:** code correction is committed; local migration application and Winback runtime verification are still pending. Do not mark the Administration dashboard review complete until the migration is applied and the linked Winback workflow is tested.

### LATEST DASHBOARD REVIEW CHECKPOINT — 2026-09-19

The Administration/Staff/Social Media dashboard review found and fixed a linked-page authorization mismatch. `dashboard/staff_dashboard.php` exposes the orphan-forms workflow to `administration`, `staff`, and `social_media`, while `modules/families/orphan_forms_index.php` previously omitted `staff` from `$viewRoles`. Commit `223c462528cc0e69d62bcf5f76094cea6ce821e2` adds `staff` without changing Staff edit or financial-profile privileges.

The sponsorship-safe family profile orphan header visual issue is also resolved in commit `2e4673a9a432e80ffb9cdccddadb4cbffd08b450`; the fix is page-local because `includes/header.php` does not load `assets/css/style.css`.

**Immediate next runtime check:** pull current `main` and verify Staff can open the orphan-forms page from the dashboard. Then continue the same dashboard review for remaining KPI, sponsor-request, Winback, role-separation, Reports, Arabic/encoding, responsive, and runtime issues. Do not restart completed investigations.

### LATEST WINBACK SECURITY CHECKPOINT — 2026-09-19

The Administration dashboard linked Winback workflow received a narrow server-side authorization hardening. `open_case` now rechecks the same queue eligibility before creating a case; `add_contact` only accepts `open`/`contacted` campaigns; `mark_declined` only accepts `open`/`contacted` campaigns.

Commit: `776da4470010d27c8758fd116843d3d06f9c94b9`.

**Next:** pull and runtime-test the Winback workflow. Do not move to unrelated dashboard UX findings until this linked-page correction is verified.


### DASHBOARD REVIEW — Sponsor-request workflow actionability — 2026-09-19

Repository inspection found a concrete UX/functional gap in `modules/sponsors/requests.php`: the dashboard and request queue displayed the existing `new`, `contacted`, `converted`, and `lost` states, but the request page only provided conversion and had no normal UI action to move a live request from `new` to `contacted` or to close it as `lost`. This made the Administration/Staff/Social Media dashboard's contacted-request KPI dependent on status changes outside the visible workflow.

The request workflow was tightened without adding a new route or changing the database schema:
- `new` requests can now be marked **تم التواصل**.
- `new` and `contacted` requests can be closed as **مغلق / لم يكتمل**.
- conversion remains available for `new` and `contacted` requests.
- converted/closed requests no longer expose mutation controls.
- every status mutation is POST + CSRF protected and server-side validated against the current request state.
- Arabic and English labels/confirmation messages were added to the existing sponsor-request language files.

Commits:
- `c03b15cbc1830437657edd8bb2aed26bf60e2b64` — Make sponsor request statuses actionable.
- `65037e97c5acef92caf5290e9f8d8cb95be20d99` — Add Arabic sponsor request status action labels.
- `03eaf05faec5c90e4d61041ef04f83d03a5b6e2e` — Add English sponsor request status action labels.

**Runtime verification required:** after pulling current `main`, open the sponsor-request queue as Administration or Staff and verify a new request can be marked contacted, a new/contacted request can be closed, converted requests remain complete/read-only, and the dashboard contacted/new counts reflect the changed statuses. Do not create unnecessary test data if existing requests can exercise the workflow.
