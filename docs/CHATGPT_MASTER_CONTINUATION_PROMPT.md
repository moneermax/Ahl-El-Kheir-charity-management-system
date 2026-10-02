# LATEST WORKFLOW CHECKPOINT — 2026-09-29 — Salary Advance Processing Consolidation

The salary-advance FM review and accounting/disbursement workflows have been consolidated into one user-facing processing page.

Removed redundant pages:
- `modules/hr/salary_advance_fm_review.php`
- `modules/hr/salary_advance_processing.php`

Use only:
- `modules/hr/salary_advance_processing.php`

The unified page sequence is:
1. FM review/policy comparison/customization.
2. Accounting verification.
3. Disbursement and journal posting.
4. Disbursement result and repayment schedule.
5. Payment-receipt evidence.

Underlying role checks and business logic remain separated internally; only the user journey was consolidated. Voucher/receipt serving and printable voucher endpoints remain separate because they are evidence/document endpoints.

Dashboard links and employee approval-notification routing were updated. Accounting staff retain access through `dashboard/accountant_staff_dashboard.php`.

No schema change was introduced.

**Runtime verification of the consolidated page is pending.** PR #52 was already merged into main (merge commit 96ecf58871fe27b32aad2c5d62174c40991a5747). The current branch contains a follow-up workflow-consolidation change and is not yet merged.

### Current Stage 5 testing constraint

The user has already used all available employees for fresh salary-advance requests during testing. Do **not** instruct the user to consume another employee or create another request until the repository/current test data has been inspected for a safe reusable test path or a controlled development fixture.

Do not alter the protected SAR-2026-00007 evidence.

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


## Sponsor Request Conversion UX — 2026-09-19
- `modules/sponsors/requests.php` commit `637639eeb6d4f5dd1a47dbe9a04bcede067423ef` removes the always-visible sponsor gender selector from each request row.
- Gender is now requested only when the user explicitly clicks **تحويل**; a conversion modal carries the request ID and requires male/female before submitting the existing server-side `convert` action.
- No database/schema changes were made.
- Runtime verification is pending user confirmation.


## Sponsor Request Gender Workflow — 2026-09-19
- Live `sponsor_requests` schema confirmed `gender varchar(10) NULL`; no schema migration was required.
- Commit `2b95e6bf40f8f0520c0e778ca5eddb7caa2d6553` moves gender capture into the Add Sponsor Request form, validates it server-side, stores it in `sponsor_requests.gender`, and removes the conversion-time gender prompt.
- Conversion now reads the stored request gender and copies it to `sponsors.gender`; legacy requests with missing/invalid gender are blocked from conversion with a clear message rather than guessed.
- Runtime verification is pending user confirmation.


## Sponsor request deletion + parse-error correction — 2026-09-19

- Fixed the unmatched closing brace in `modules/sponsors/requests.php` that caused the PHP parse error at line 67.
- Added a server-side **Delete Request** action for sponsor-request records only while they remain `new` and have no `assigned_supervisor_id`.
- The deletion is intentionally blocked once the request has been marked `contacted` or has been assigned/transferred to a supervisor. The server re-checks these conditions; hiding the button is not the authorization boundary.
- Added Arabic/English confirmation labels for the permanent deletion action.
- No database schema change was made.
- Commit: `bf08ce524cfe8065b61e8234395242abf8940980`.
- Runtime verification is still pending; do not mark this change as tested until the user confirms the local page loads and the eligible/ineligible delete cases behave as expected.


## Sponsor request deletion verification + dashboard KPI actionability — 2026-09-19

- Runtime verification completed successfully: the sponsor-request page loads without the previous parse error; eligible new/unassigned requests can be permanently deleted; deletion is blocked once the request is contacted or assigned/transferred.
- The deletion implementation remains server-side protected and uses POST + CSRF. No schema change was made.
- Follow-up dashboard UX improvement: Administration/Staff/Social Media dashboard sponsor-request KPI cards now open the sponsor-request queue filtered to the corresponding status (`new` or `contacted`). Recent-request row actions also open the queue filtered to that row's current status.
- `modules/sponsors/requests.php` now accepts a validated GET `status` filter for `new`, `contacted`, `converted`, and `lost`, with an explicit all-requests option.
- Commits: `de34e1ff36f5fc06fbadc29acb6553a049f807e7` (status filtering), `bdb698d8d6fc0df3e50f8a628a0333a5ac08c9e3` (dashboard KPI/action links).
- Runtime verification of this new dashboard/filter change is pending.

### Current next review area
Continue the Administration/Staff/Social Media dashboard audit after verifying the new sponsor-request status filtering. The remaining documented review areas are Reports visibility, sidebar/dashboard consistency, role differences, Arabic/encoding/responsive UX, KPI semantics, and remaining runtime/link checks. Do not reopen completed Winback, orphan-form authorization, sponsorship-family header, sponsorship autocomplete, or accounting/HR audits unless a regression is found.


## Sponsor request filtered queues + reopen workflow — 2026-09-19
- The Administration/Staff/Social Media dashboard KPI links continue to use the single sponsor-request workflow page, but the page heading now reflects the active validated status filter so `status=new` and `status=contacted` are visibly distinct queues.
- Closed/incomplete sponsor requests (`status='lost'`) now expose an **إعادة فتح** action.
- Reopen is POST + CSRF protected and server-side restricted to records whose current status is `lost`.
- Reopened requests move to `contacted`, not `new`, so an old request cannot regain the pre-contact deletion privilege.
- No database schema change was made.
- Implementation commits: `92030f7604308f8d062ceae3d873dd12918c02bd`, `0491ee8c7982ac9fd8c50320d5eae064a676f211`, `49fbce22d917ecaf5c8ab5e0954d6068e6972f73`.
- Runtime verification is pending user confirmation.


## Dedicated sponsor-request dashboard views — 2026-09-19
- The Administration/Staff/Social Media dashboard no longer routes the two sponsor-request KPI cards to the same workflow URL with different query parameters.
- New sponsor requests now open the dedicated view `modules/sponsors/new_requests.php`.
- Contacted sponsor requests now open the dedicated view `modules/sponsors/contacted_requests.php`.
- Both dedicated views reuse `modules/sponsors/requests.php` as the single business-logic/rendering implementation through a validated forced status, avoiding duplicated workflow code.
- POST actions preserve the dedicated queue context where applicable; reopening a lost request from the contacted queue remains contextual.
- No database schema change was made.
- Implementation commits: `5a464785702dc4fee83268c497beefc29a0eb2a7`, `8591b4865a9fa790b7ba9f0b75a00dcb9b71072`, `b33fd2dfc39175459da749c600b2e3650e3f424e`, `4facf6602b07fcc2bbab61498ee72e9daf58f8ff`.
- Runtime verification is pending user confirmation.
- After verification, continue with the remaining dashboard audit areas: Reports visibility, sidebar/dashboard consistency, role differences, Arabic/encoding/responsive UX, KPI semantics, and remaining runtime/link checks.


## Dedicated sponsor-request dashboard views — runtime verification — 2026-09-19
- User confirmed the dedicated dashboard-view change passes runtime verification.
- The two KPI cards now behave as separate destinations: new requests use `modules/sponsors/new_requests.php`, and contacted requests use `modules/sponsors/contacted_requests.php`.
- This checkpoint is complete. No schema changes were made.
- Next audit area: Reports visibility and sidebar/dashboard consistency for Administration, Staff, and Social Media, followed by role differences, Arabic/encoding/responsive UX, KPI semantics, and remaining runtime/link checks.


### Latest dashboard UX checkpoint — 2026-09-19

After completing the dedicated sponsor-request dashboard views and the Reports/sidebar consistency review, the next UX review covered Arabic/encoding and responsive behavior. Current source inspection found no concrete Arabic encoding issue in the Administration/Staff/Social Media dashboard, sidebar, or sponsor-request language files. The dashboard request table is already responsive and the KPI grid is responsive.

A concrete mobile header issue was fixed in `includes/header.php`: the top quick-action/user-control bar now stacks at <=768px instead of remaining a single non-wrapping row. Commit: `4536623afcea14852aac5d1ea1d3b2ec2f70e0b8`. Runtime verification is still required.

Next after runtime confirmation: continue the remaining dashboard review with KPI semantics/actionability and remaining linked-page/runtime checks. Do not repeat already-passed sponsor-request, Winback, orphan-form, or dedicated-queue tests unless regression evidence appears.


## Current mobile navigation checkpoint — 2026-09-19

The user reported that the application did not resemble a normal mobile website: the shared sidebar occupied/covered most of the mobile viewport and page elements overlapped. Repository inspection confirmed the root cause: the .sidebar was a 260px flex item even on narrow screens.

A shared off-canvas mobile navigation fix was implemented:

- includes/header.php: mobile menu button + off-canvas drawer/overlay responsive CSS below 992px.
- includes/footer.php: drawer open/close behavior, overlay/Escape close, mobile navigation auto-close, and desktop-resize cleanup.
- No database/schema, authorization, role, or workflow changes.

Commits: 726d766f8075b2a55a0dcfd0ccc1cb5ec75c64d1, 0530c69c6e80b320577ac50dcd852ea4633431f6.

Next required step: pull these commits locally and test the actual application on the user's mobile device. Verify that the sidebar is hidden by default, the menu button opens it as a drawer, the overlay closes it, the page uses the full mobile width, and dashboard cards/content no longer overlap. Do not mark this as passed until the user confirms.


### Final mobile navigation checkpoint — 2026-09-19

The user completed real-device testing over the LAN and confirmed the mobile navigation is now substantially improved. The shared sidebar is an off-canvas drawer on narrow screens, and the fixed `☰ القائمة` control is clearly visible and usable.

Final refinements:
- `ca88f2aa1d5ac0e41af1861905c4bd67cc5646c9`: replace the critical mobile menu icon dependency with a native visible `☰`.
- `0c7d51c640519b3814c8e0e6f9220cc41097e530`: make the mobile menu control fixed, prominent, and independent of the crowded header layout.
- `af03d9dd775930be865c2b03a470665f3dab610b`: lock background scrolling while the drawer is open.
- User confirmed the real-phone result is much better. A native-app-style redesign is deliberately deferred.

**Current continuation point:** mobile navigation is accepted for this phase. The next session should continue the broader system-wide changes/audit work, not reopen the mobile navigation unless new regression evidence appears.

## Shared floating sidebar UX — 2026-09-19

The latest shared sidebar handler refinement is temporarily accepted; continue with the next system-wide change rather than reopening its visual design unless the user asks.

The shared navigation remains a fixed floating/off-canvas sidebar. The handler is flush with the page edge, uses the shared navy header/sidebar color, has rounded inner corners, and was narrowed to 28px on desktop/mobile. The current implementation is in `includes/header.php`.

Commit: `97f5d394ee0370be436351a4d4696bbf5c0b5a5d` — **Refine sidebar handler width and page-edge alignment**.

The user explicitly considers this **temporary done** rather than a final visual design. Do not spend further work refining the handler unless the user returns to it. No database, schema, authorization, role, or workflow logic changed.

### Immediate continuation

Move to the next system-wide fix requested by the user. Inspect the relevant documentation and current repository code before making changes; do not reopen closed audit areas or repeat passed tests without regression evidence.


## Current continuation checkpoint — 2026-09-19

Continue the **existing** Ahl El Kheir project. Current active task: **system-wide Back/navigation audit and fix**. The entire `TCPDF/` directory is explicitly out of scope.

First inspect the current repository/docs state; do not restart completed work. The first navigation batch has already introduced `akGoBack(fallback)` in `includes/footer.php` and corrected contextual Back/list-return behavior across Families, Sponsors, Sponsorships, Projects, orphan/child navigation, returned Transactions, Search Center results, and Accounting Disbursements. Sponsor return handling now retains `link_status`.

Continue by auditing the remaining user-facing modules for: broken Back links, Back links that lose pagination/search/filter context, and contextual pages that genuinely need a Back action but do not have one. Do not use a blind global `history.back()` without a deterministic application fallback. Do not alter authorization, business workflows, database schema, or the shared sidebar unless a genuine regression requires it. After completion, runtime-test representative list → detail → back and nested/edit/form flows, then update all four required documentation files.


## Navigation acceptance rule — 2026-09-19
For the active system-wide Back/navigation audit, follow this explicit user rule: **every user-facing page must have a Back button returning to the previous/originating page, unless the page is a dashboard**. Do not treat index/list pages as exceptions. Preserve pagination/search/filter context where applicable. Use akGoBack(fallback) or an equivalent contextual mechanism with a deterministic application fallback; never use a blind global history.back() as the only destination. TCPDF remains out of scope, as do API/JSON endpoints, file streams, redirect-only compatibility entries, and print-only output.


### 2026-09-19 — final navigation sweep additions
A further sweep covered remaining user-facing HR integrity and system maintenance pages that render HTML. API/JSON actions, file streams, redirect-only compatibility endpoints, and print-only output remain excluded because they are not navigable HTML pages. The acceptance rule remains: every user-facing HTML page has Back unless it is a dashboard.


## 2026-09-19 — Nanny legal-age alert integration

The shared legal-age alert is now included on the Nanny Dashboard. Its query is server-side scoped to families assigned to the logged-in Nanny via families.nanny_id, while GM/VGM/Admin continue using the same shared implementation. The alert was verified to initialize reliably and show once per authenticated PHP session using sessionStorage keyed from the PHP session ID. The existing child suspension workflow was inspected and confirmed to set family_children.is_active = 0 and pause linked active sponsorships; therefore suspended children disappear from the shared legal-age popup after refresh on all dashboards using it. The Nanny Dashboard's separate near-legal-age card also uses is_active = 1. Temporary test DOBs were restored. No schema change was made.

Commits: 474741e4f094b264e033af89f690838765c7e4a5, 44e873fa548aa15142a4d3548437bae8371d1c6d, ab489dfa5ab5c00d52b76d3e0a8a97be2d2e6cd8. Documentation update: 8a3a963d01703db115faacccd23247f55016c7f1.


## 2026-09-19 — Back button top-left enhancement

The Back/navigation audit is closed and must not be restarted. The accepted layout now intentionally has two Back buttons on audited user-facing HTML pages: the existing bottom Back button plus a second top-left Back button. The top button is generated centrally from the existing contextual Back control in `includes/footer.php` and uses the same `akGoBack(fallback)` function. Do not introduce a second navigation implementation or change dashboard/excluded endpoint scope unless regression evidence or an explicit new request requires it.

Commit: `0557218daa28d2ce78ff8e9f0bbaf8bacd2f0769`.


## 2026-09-21 — Repository checkpoint after Claude/i18n/dashboard work

The latest `main` state includes six reviewed commits after `642f7397f48c8ddb84315130ae32cf22847476b1`:

- i18n compatibility bridges for legacy Arabic text, JavaScript/native dialogs, punctuation variants, and live-value patterns;
- page-scoped English→Arabic compatibility mappings;
- a CLI i18n gap report at `tools/i18n_gap.php`;
- a role-aware Home shortcut in the shared header, hidden on dashboards;
- shared blue-bar/white-text section-title styling for dashboards and applicable light/white card headers;
- removal of obsolete `sudo_dashboard.php`.

`docs/I18N.md` contains the detailed i18n architecture. New UI text should continue to use stable-key catalogs/`t()`; bridges are compatibility/migration layers. No database/schema changes were introduced by this change set. Runtime/visual acceptance still requires local verification.

## 2026-09-20 — Current continuation checkpoint: module-by-module Back-button consistency audit

The active task is now a **module-by-module Back-button consistency audit**. The earlier Back implementation is the baseline; do not assume it is currently consistent.

User-reported runtime classes to investigate:
- some pages have only one Back button;
- some pages have duplicated Back buttons;
- some pages have no Back button;
- some Back buttons can cause the page to become stuck/broken and prevent the shared sidebar from opening.

Required target for applicable user-facing HTML pages: **exactly two Back buttons — top-left and bottom-right**. Dashboards and previously excluded non-HTML/stream/redirect-only/print endpoints remain excluded unless explicitly brought into scope.

Work strictly one module at a time. Before changing a module, inspect its actual page set and the shared Back implementation (includes/footer.php, akGoBack(), related scripts) and classify the existing state. Fix the smallest responsible layer, preserve originating context, and avoid creating competing Back-button generators. After each fix, runtime-test Back navigation, sidebar opening, refresh, and relevant pagination/search/filter context before marking that module complete.

Do not use destructive Git commands. Do not change database/schema/business/authorization logic or redesign the sidebar/header as part of this audit unless a verified regression requires it.

The repository checkpoint currently being consolidated is 4a7982a2f7ca831318870393943f6612d583b0c7 (fix/fm-controls-stable), which preserves the last user-confirmed FM header/navigation state. The abandoned dashboard-card styling experiment is not part of the active work.

## PERMANENT HOSTING DATABASE RULE — 2026-09-21

**Never add MySQL/MariaDB triggers or views to the database again.** This is a permanent project rule for the hosting-compatible architecture. Trigger business logic must be implemented in procedural PHP with equivalent server-side validation and authorization. View/reporting logic must query the real underlying tables. Stored procedures, functions, and scheduled database events are also excluded unless this rule is explicitly revised.

Before any schema migration or database export, inspect it for `CREATE TRIGGER`, `CREATE VIEW`, `CREATE PROCEDURE`, `CREATE FUNCTION`, and `CREATE EVENT`. A migration/export containing these objects is not acceptable for the current hosting baseline.

When replacing an old trigger, do not merely remove it: inspect its business rule and verify that the responsible PHP workflow enforces the same rule.


## 2026-09-21 — Projects Module Audit Continuation

The Projects module is now an active audit area. Read `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md` before changing Projects code. First-pass findings include non-transactional project creation, inconsistent portfolio/detail funding totals, read-time lifecycle writes, count-based project codes, server-side validation gaps, upload-size validation, transaction-boundary review, and expense separation-of-duties review. Inspect current code/schema first; do not assume any finding is already fixed. No Projects code has been changed yet.

## PROJECTS MODULE — CURRENT WORKING PLAN (2026-09-22)

The immediate next task is a **complete Projects Module workflow and role audit**, not another isolated UI fix.

Authoritative role boundaries:
- Project Supervisor: operational/project data entry and execution follow-up.
- Projects Manager: reviews/follows up Supervisor work, manages readiness/workflow, prepares and submits for financial review.
- FM: financial review/control; approve/reject financially; operational sections must be read-only or quick-report summaries; after final approval, FM may perform authorized project payment/disbursement execution and print payment receipts where required, especially cash payments. FM must not enter operational project data.
- GM/VGM: final approval according to existing authority.
- Accountant: accounting execution/posting where applicable.

Audit every Projects page/action as:
A. pre-approval preparation
B. approval/financial review
C. post-approval execution
D. management/reporting

Before implementation, inspect:
- all Projects pages/endpoints and POST actions;
- project_lib.php permission helpers and all role checks;
- budget/funding/expense/payment/disbursement/receipt code;
- documents, labor, milestones, progress, beneficiaries, closure/reopen workflows;
- dashboards/lists/detail pages and direct URLs;
- notifications;
- accounting journal creation/posting/reversal semantics;
- actual schema/references.

Produce first:
1. page/action inventory;
2. role matrix;
3. lifecycle/state-action matrix;
4. section visibility/editability matrix;
5. payment/disbursement/receipt workflow map;
6. accounting-event map;
7. notification routing map;
8. contradictions/gaps;
9. safe implementation order.

Critical accounting question: determine whether the final-approval project journal represents an actual payment/expenditure event or a funding/allocation/reservation event. Do not conflate these, and verify that later expense/payment posting cannot recognize the same event twice.

FM target experience:
- pre-final-approval: financial review/control view with project summary, approved budget, budget lines, funding, financial warnings, read-only documents/operational summaries, approval history, Approve/Reject;
- post-final-approval: financial execution view with budget/funded/paid/expense/remaining amounts, pending payments, payment history, financial documents, authorized payment/disbursement and receipt actions.

Rules:
- Do not implement speculative role changes before the audit is complete.
- Do not invent tables/columns/statuses.
- No new triggers, views, stored procedures, functions, or events.
- Preserve existing accounting/test evidence.
- Use the smallest safe implementation units after the audit.
- Runtime verification is required before marking workflow changes complete.
- Create a safe Git checkpoint before implementation.
- Commit completed changes to main and update documentation.
- Do not use destructive Git commands.
- Do not ask for SQL unless truly unavoidable; inspect existing code/schema first.
- Preserve accepted header/sidebar/Back behavior unless a genuine Projects regression is demonstrated.

Start by reading:
- docs/CHATGPT_SESSION_INDEX.md
- docs/AHL_EL_KHEIR_MASTER_STATUS.md
- docs/AHL_EL_KHEIR_MASTER_AUDIT.md
- docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md

Then inspect the actual current repository. Do not restart completed work.


## 2026-09-25 — Latest continuation checkpoint

The current Projects workflow has been runtime-tested through final approval using PRJ-0010 / project ID 10 (اختبار المشاريع 2026). PM submission, FM approval, GM final-approval notification, PM final-approval confirmation, and existing FM post-final-approval notifications all worked.

Final approval is intentionally NOT project launch. GM/VGM approval leaves the project planned. PM must explicitly launch it. Launch requires final approval, planned lifecycle, and an active assigned Project Supervisor; it then changes the project/lifecycle to active, records history/audit, and sends the assigned supervisor a launch notification. The Project Supervisor must not see/access the project before launch.

The PM and Project Supervisor dashboards had a real runtime collation failure caused by mixed collations in the existing schema and COALESCE/status comparisons. This was fixed in dashboard/projects_dashboard.php using explicit binary comparisons. Final fix commit: b3c23386fc54d27705df6cc1e3546430935135cd. Both dashboards now open successfully.

### NEXT TASK — DO NOT SKIP AHEAD

Continue testing the same PRJ-0010. Do not create a new project.
1. Log in as PM and verify PRJ-0010 is in مشاريع معتمدة بانتظار الإطلاق.
2. Open it and verify the explicit إطلاق المشروع action.
3. Confirm the assigned active Project Supervisor.
4. PM launches the project.
5. Verify the Project Supervisor receives the launch notification.
6. Verify PRJ-0010 appears on the Project Supervisor dashboard only after launch.
7. Verify direct access and operational permissions for the assigned supervisor.
8. Then continue the post-approval payment/disbursement/receipt and accounting-event audit.

Do not treat final-approval notification success as payment/accounting certification. Preserve the established boundary: final approval is funding reservation/allocation, not actual spend; actual money movement occurs later through posted project expenses/payment workflows.

### IMPORTANT CURRENT STATE

- No new project is required for the next test.
- No schema change is required for the launch test.
- No triggers/views/stored procedures/functions/events are allowed.
- Main branch only; do not create branches.
- Never use destructive Git commands.
- Root-cause-first: if a runtime error occurs, inspect the exact current main code/query and related schema evidence before changing anything; do not guess with repeated one-line fixes.
- Do not ask the user to run SQL diagnostics unless truly unavoidable; inspect the repository/schema evidence first.


## 2026-09-26 — Latest continuation checkpoint: project expense/payment completed

The Projects project-expense/payment work is now complete and runtime-tested on PRJ-0010 / project ID 10. Read `docs/PROJECTS_RUNTIME_CHECKPOINT_2026-09-26.md` for the detailed record.

Permanent expense rule:
- Project Supervisor payments are paid from the project budget already transferred/allocated to the supervisor.
- A payment receipt is required.
- Saving the payment records a `posted` project expense immediately and deducts it from the approved project budget.
- No organization cash/bank/e-wallet account is selected and no organizational journal entry is created.
- Older draft expenses are not auto-posted; they are converted through the existing **تعديل / تسجيل الدفع** workflow when the payment is actually recorded and the receipt is supplied.
- The assigned primary Project Supervisor can edit/delete both `draft` and `posted` project-funded expenses; this is enforced server-side as well as in the UI.
- Do not revive the removed standalone `project_expense_finalize.php` page.
- Do not revive the obsolete organization-account Projects expense-entry path.

Final expense-workflow commit: `5dda62c4bc74662fe0e9c415ddbbed88e9263a94`.

### CURRENT NEXT STEP — continue from here

PRJ-0010 is **not closed yet** because one remaining project task is still outstanding.

Do **not** restart the completed expense/payment work and do **not** create another project.

In the new session:
1. Read the current documentation first, especially `docs/PROJECTS_RUNTIME_CHECKPOINT_2026-09-26.md`, `docs/CHATGPT_SESSION_INDEX.md`, `docs/AHL_EL_KHEIR_MASTER_STATUS.md`, `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`, and `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md`.
2. Inspect the actual current `main` repository/code before changing anything.
3. Continue with the **remaining project task that is still marked TODO** on PRJ-0010.
4. Only after that task is completed, verify the PM closure workflow and close the project when the existing closure guards are satisfied.
5. Do not invent schema/tables/columns or ask the user to run SQL unless genuinely unavoidable; inspect the repository/schema evidence first.
6. Preserve the established project/accounting boundary: final approval is funding reservation/allocation, while actual spending occurs through posted project expenses.
7. No triggers, views, stored procedures, functions, or events. No destructive Git commands.
8. Use a safe checkpoint before any risky code change, make the smallest root-cause fix, commit to `main`, and update documentation after verified completion.


## 2026-09-29 — New-session continuation: HR Salary Advance Stage 5

Continue the existing Ahl El Kheir project from the documented salary-advance checkpoint.

Completed boundary:
- Stages 1–3: DONE / RUNTIME VERIFIED.
- Stage 4 — Accounting Verification & Disbursement: DONE / RUNTIME VERIFIED / CLOSED.
- Do not reopen Stage 4 without genuine regression evidence.

Stage 4 final evidence includes SAR-2026-00001 disbursement (10,000 SDG, 1100, JE-000040, Dr 1410 / Cr 1100), protected receipt upload/replacement, mandatory receipt-replacement audit, duplicate-disbursement protection, and runtime-verified FM rejection closure for SAR-2026-00005.

Immediate task: start Stage 5 — Repayment Schedule + Payroll Integration with an audit/design pass only.

1. Read docs/HR_SALARY_ADVANCE_CONTINUATION.md, docs/AHL_EL_KHEIR_MASTER_STATUS.md, docs/AHL_EL_KHEIR_MASTER_AUDIT.md, and docs/CHATGPT_SESSION_INDEX.md.
2. Inspect the current main repository and actual payroll/accounting schema/code before proposing changes.
3. Determine the existing payroll deduction mechanism and accounting conventions; do not invent tables, columns, accounts, or statuses.
4. Define repayment from the already-posted employee receivable in 1410 through payroll and eventual settlement.
5. Map fixed monthly repayment, full eligible salary repayment, maximum monthly deduction, repayment start rule, and insufficient-salary handling.
6. Produce the Stage 5 page/action inventory, role matrix, lifecycle/state-action matrix, payroll integration map, accounting-event map, and notification/audit map before implementation.
7. Use migrations only for required schema changes; no runtime DDL.
8. No triggers, views, stored procedures, functions, or events.
9. Make a safe Git checkpoint before implementation and proceed in small runtime-verifiable units.
10. Do not implement Stage 6 direct repayment/settlement behavior early.


# 2026-09-29 — Stage 5 Schedule Generation Runtime Checkpoint

**Status:** Stage 5 IN PROGRESS — schedule generation/display RUNTIME VERIFIED; edge-case schedule-rule tests pending.

The Stage 5 migration was applied successfully locally with all 5 queries completing without errors. It added payroll.salary_advance_deduction, hr_salary_advance_repayment_schedule, and hr_salary_advance_payroll_repayments.

PR #52 — Stage 5: generate salary advance repayment schedules — was merged into main on 2026-09-29 (merge commit 96ecf58871fe27b32aad2c5d62174c40991a5747). The current branch is a follow-up consolidation branch and remains unmerged while the consolidated workflow is runtime-tested.

Implemented on feature/hr-salary-advance-stage5-schedule:
- modules/hr/lib_salary_advance_repayment.php
- atomic schedule generation from the existing disbursement transaction;
- fixed-monthly and full-eligible-salary schedule planning;
- approved start-rule handling;
- maximum monthly deduction and maximum repayment-month constraints;
- duplicate schedule-generation protection;
- schedule display on modules/hr/salary_advance_processing.php.

## Runtime PASS — SAR-2026-00007

Employee: هديل عثمان / EMP-0021

- Approved/disbursed amount: 50,000 SDG
- Method: قسط شهري ثابت
- Monthly installment: 10,000 SDG
- Approved start month: 2026-10-01
- Disbursement source: 1100 — الصندوق (نقدي)
- Journal: JE-000041
- Reference: SAL-ADV-SAR-2026-00007
- Outstanding balance: 50,000 SDG

Exactly 5 pending schedule rows were generated:
1. 2026-10-01 — 10,000 SDG
2. 2026-11-01 — 10,000 SDG
3. 2026-12-01 — 10,000 SDG
4. 2027-01-01 — 10,000 SDG
5. 2027-02-01 — 10,000 SDG

Total scheduled = 50,000 SDG. Applied = 0. This confirms the core disbursement → schedule-generation → schedule-display path.

SAR-2026-00004 / JE-000042 is a different request and is not a duplicate of SAR-2026-00007.

## Duplicate-disbursement safety checkpoint

The existing disbursement helper checks for an existing posted salary-advance disbursement journal, locks the request with FOR UPDATE inside the transaction, revalidates state, creates one balanced Dr 1410 / Cr source-account journal, changes the request to disbursed transactionally, and generates the schedule in the same transaction. A schedule-generation failure rolls the disbursement back. No artificial duplicate financial posting should be created merely to test this.

## Remaining Stage 5 runtime gates — NEXT

1. Maximum monthly deduction — verify every generated installment is at or below the saved policy maximum.
2. Maximum repayment months — verify an impossible schedule is rejected safely and does not leave an unscheduled disbursed request.
3. next_payroll — verify the first scheduled month is the first payroll month after disbursement.
4. specified_month — verify the approved start month is honored, but never before the first eligible payroll month after disbursement.
5. Duplicate schedule generation — verify an existing schedule remains one set of rows.
6. full_eligible_salary — verify planning respects the saved repayment horizon and maximum deduction; actual salary calculation remains deferred.
7. Failure/rollback — verify a schedule-generation failure rolls back the disbursement transaction.

## Explicit boundary

Stage 5 currently does NOT implement actual payroll deduction calculation, payroll repayment allocation, outstanding-balance reduction from payroll, payroll Cr 1410 accounting, repayment notifications, or Stage 6 direct repayment/settlement.

Use fresh controlled requests for new tests. Do not alter the already-passed SAR-2026-00007 evidence. If a test fails, stop and inspect the current repository/code/schema root cause before creating another test request.


# LATEST CONTINUATION PROMPT — 2026-09-29 — Stage 5 Edge-Case Schedule Tests

Continue the existing Ahl El Kheir Charity Management System. Do not rebuild or start a new project.

Environment: repository moneermax/Ahl-El-Kheir-charity-management-system; local path D:\xampp\htdocs\AhlElKheir; local URL http://localhost:8081/AhlElKheir/; database ahl_el_kheir; Windows/XAMPP/Apache/PHP 8.2/MariaDB; Arabic RTL; Bootstrap 5.3 RTL; vanilla JS; procedural PHP only.

Completed: Stages 1–3 DONE/RUNTIME VERIFIED. Stage 4 Accounting Verification & Disbursement DONE/RUNTIME VERIFIED/CLOSED. Do not reopen closed stages without genuine regression evidence.

Current: Stage 5 Repayment Schedule + Payroll Integration IN PROGRESS. The migration was successfully applied locally with all 5 queries completing without errors. PR #52 is OPEN on feature/hr-salary-advance-stage5-schedule and must not be merged yet.

Already implemented: repayment schedule table, payroll repayment allocation table, payroll salary_advance_deduction field, procedural schedule generator, atomic schedule generation during disbursement, schedule display, fixed-monthly planning, full-eligible-salary planning, start-rule handling, maximum deduction/month constraints, and duplicate schedule protection.

Already passed: SAR-2026-00007, employee هديل عثمان / EMP-0021, 50,000 SDG, fixed monthly 10,000 SDG, start 2026-10-01, source 1100, JE-000041. The displayed schedule is exactly five pending installments: 2026-10, 2026-11, 2026-12, 2027-01, 2027-02, each 10,000 SDG, total 50,000 SDG. Do not disturb this evidence. SAR-2026-00004 / JE-000042 is a different request.

IMPORTANT: do not start payroll deduction/application, payroll accounting, balance reduction, repayment notifications, or Stage 6 direct repayment/settlement until the schedule-planning tests below are complete.

## Pull the current files first

Use PowerShell:

cd D:\xampp\htdocs\AhlElKheir

git checkout feature/hr-salary-advance-stage5-schedule
git pull origin feature/hr-salary-advance-stage5-schedule

Never use reset --hard, clean, restore, force-push, or other destructive Git commands.

## Test 1 — Maximum monthly deduction

1. Create a fresh salary-advance request.
2. Use a repayment amount/monthly installment that exceeds the policy maximum monthly deduction.
3. Complete the normal FM approval.
4. Complete accounting verification.
5. Disburse through the existing accounting workflow.
6. Open the disbursed request details.
7. Inspect every generated schedule row.
8. PASS: no scheduled installment exceeds the saved policy maximum monthly deduction.
9. Record the request number and schedule result.

## Test 2 — Maximum repayment months

1. Create a fresh controlled request whose balance cannot be completed within the saved maximum repayment months after applying the maximum monthly deduction.
2. Complete FM approval and accounting verification normally.
3. Attempt disbursement.
4. PASS: the impossible schedule is rejected safely and the transaction does not leave a disbursed request without its required schedule.
5. If it fails, capture the exact application message and stop. Do not keep retrying.

## Test 3 — next_payroll

1. Create a fresh request using repayment start rule next_payroll.
2. Complete FM approval.
3. Complete accounting verification.
4. Disburse normally.
5. Open the generated schedule.
6. PASS: the first scheduled month is the first eligible payroll month after the actual disbursement month, never the disbursement month itself.

## Test 4 — specified_month

1. Create a fresh request using specified_month and an approved future start month.
2. Complete FM approval.
3. Complete accounting verification.
4. Disburse normally.
5. Open the generated schedule.
6. PASS: the schedule starts at the approved month, except that a start earlier than the first eligible payroll month must be safely moved forward to that first eligible month.

## Test 5 — Duplicate schedule generation

1. Use already-passed SAR-2026-00007.
2. Do NOT disburse it again and do NOT create another journal.
3. Verify that the schedule-generation path is idempotent.
4. PASS: SAR-2026-00007 remains exactly five rows totaling 50,000 SDG; no second set of schedule rows appears.
5. Do not manufacture a duplicate payment. The existing disbursement helper already checks existing posted journals and locks the request transactionally.

## Test 6 — full_eligible_salary

1. Create a fresh request using full_eligible_salary.
2. Ensure the saved policy has the required maximum repayment-month horizon.
3. Complete FM approval.
4. Complete accounting verification.
5. Disburse normally.
6. Open the generated schedule.
7. PASS: planning respects the saved maximum repayment horizon and maximum monthly deduction.
8. Actual eligible-salary calculation is NOT part of this test; that belongs to payroll integration.

## Test 7 — Failure/rollback safety

1. Use a fresh controlled case that exercises a schedule-generation validation failure.
2. Complete approval/verification only as necessary.
3. Attempt disbursement.
4. PASS: when schedule generation fails, the entire disbursement transaction rolls back; there is no posted financial disbursement left without the required schedule.
5. Do not intentionally corrupt the database or create a destructive failure. Inspect the current implementation first and use the safest existing validation case.

## Rules while testing

- Use fresh controlled requests for Tests 1, 2, 3, 4, 6, and 7.
- Do not alter the already-passed SAR-2026-00007 evidence.
- Do not run ad-hoc SQL unless genuinely unavoidable; inspect the repository/schema first.
- If an error occurs, stop. Inspect the exact current code/schema root cause before another test.
- Do not invent tables, columns, statuses, accounts, or workflows.
- No runtime DDL.
- No triggers, views, stored procedures, functions, or events.
- Keep accounting test data as development/test data only.

## What to report after each test

Send the request number plus the visible result, including amount, repayment method, monthly amount, start rule/month, disbursement result, journal number if any, generated schedule rows/totals, and whether the PASS condition was met.

After all seven gates pass, update the salary-advance continuation document, master status/audit, session index, and this master continuation prompt with exact results and commit/PR state. Then evaluate PR #52 for merge. Do not mark Stage 5 complete until payroll integration and its runtime verification are actually finished.


# LATEST CONTINUATION PROMPT — 2026-09-29 — Stage 5 Payroll Integration

Continue the existing Ahl El Kheir Charity Management System. Do not rebuild or start a new project.

Environment: repository moneermax/Ahl-El-Kheir-charity-management-system; local path D:\xampp\htdocs\AhlElKheir; local URL http://localhost:8081/AhlElKheir/; database ahl_el_kheir; Windows/XAMPP/Apache/PHP 8.2/MariaDB; Arabic RTL; Bootstrap 5.3 RTL; vanilla JS; procedural PHP only.

## Current status
- Salary Advance Stages 1–4: DONE / RUNTIME VERIFIED / CLOSED. Do not reopen without genuine regression evidence.
- Stage 5 schedule-planning: DONE / RUNTIME VERIFIED.
- Stage 5 payroll integration: NEXT / IN PROGRESS.
- Stage 6: NOT STARTED. Do not start it.
- Current branch: feature/hr-salary-advance-stage5-schedule; not yet merged to main.
- Latest cleanup commit: 9b66315966174a3a892a5760847300007814a0e2.

## Verified Stage 5 schedule gates
1. Maximum monthly deduction — PASS: SAR-2026-00008; 50,000 SDG; policy maximum 10,000; generated installments capped at 10,000; JE-000043.
2. Maximum repayment months — PASS: SAR-2026-00009; impossible 7-month repayment against 6-month policy maximum was blocked; no successful disbursement/journal/schedule.
3. next_payroll — PASS: SAR-2026-00011; 30,000 SDG; JE-000044; schedule starts 2026-10-01 after 2026-09-29 disbursement, then 2026-11-01 and 2026-12-01.
4. specified_month — PASS via rollback-only harness.
5. duplicate schedule generation — PASS via rollback-only harness; second generation added no rows.
6. full_eligible_salary planning — PASS via rollback-only harness; cap and saved horizon respected.
7. failure/rollback — PASS via protected validation failure plus transactional code verification.

The rollback-only harness is tools/run_salary_advance_stage5_schedule_tests.php. Its CLI warning was fixed in 9b66315966174a3a892a5760847300007814a0e2; the clean rerun returned all three harness tests PASS.

## Protected evidence
Do not alter or redisburse SAR-2026-00008, SAR-2026-00009, or SAR-2026-00011. Do not manufacture another salary-advance request just to continue testing; prefer the current temporary rollback-only fixture mechanism.

## Next work — Stage 5 payroll integration
Before coding, inspect the existing repository/schema and identify the canonical payroll run/deduction flow. Then implement and verify, in controlled steps:
1. Apply a pending salary-advance schedule row during payroll.
2. Record the corresponding hr_salary_advance_payroll_repayments allocation.
3. Update the salary-advance outstanding balance and schedule applied state atomically.
4. Use the existing payroll.salary_advance_deduction field according to current payroll conventions.
5. Post the repayment accounting entry with Cr 1410 using existing payroll/accounting transaction conventions.
6. Handle insufficient eligible salary according to the configured insufficient_salary_rule (available_salary or skip_month).
7. Implement required notifications and audit trail for successful, skipped, or partially applied repayments.

Do not invent schema, accounts, statuses, or payroll workflows. Inspect actual tables/columns and existing payroll implementation first. Schema changes only through migration files. No runtime CREATE/ALTER, triggers, views, stored procedures, functions, or events. No destructive Git commands (reset --hard, clean, restore, force-push).

Keep the current feature branch until all Stage 5 work is complete and runtime verified. Commit logical changes, keep the branch pullable, and merge to main only after the Stage 5 completion gate. After each verified work unit, update the five project documents with exact runtime evidence and the new continuation checkpoint.


# LATEST CONTINUATION PROMPT — 2026-09-29 — Stage 5 Payroll Application Verification

Continue the existing Ahl El Kheir project. Do not rebuild or start a new project.

Environment: repository \`moneermax/Ahl-El-Kheir-charity-management-system\`; local path \`D:\xampp\htdocs\AhlElKheir\`; Windows/XAMPP/PHP 8.2/MariaDB; Arabic RTL; procedural PHP only.

Current status:
- Salary Advance Stages 1–4: DONE / RUNTIME VERIFIED / CLOSED.
- Stage 5 schedule planning: DONE / RUNTIME VERIFIED.
- Stage 5 payroll integration: IMPLEMENTED / RUNTIME VERIFICATION PENDING.
- Stage 6: NOT STARTED.
- Current branch: \`feature/hr-salary-advance-stage5-schedule\`.

Implemented payroll integration:
- draft salary-advance deduction from the approved schedule/policy;
- transactional repayment allocation at payroll payment;
- schedule applied/partial/skipped state updates;
- outstanding-balance reduction by actual repayment;
- \`hr_salary_advance_payroll_repayments\` trace;
- payroll accounting Cr 1410 for actual repayment with cash credit for actual net paid;
- insufficient-salary \`available_salary\` / \`skip_month\` behavior;
- duplicate repayment protection;
- audit-log evidence;
- account 1410 ensured by the existing accounting seed mechanism.

Before merge/closure, run controlled runtime verification for fixed-monthly repayment, available-salary partial repayment, skip-month, balance reduction, schedule state, duplicate protection, payroll regeneration safety, and balanced accounting/traceability. Do not use protected salary-advance requests for destructive testing and do not start Stage 6.

After runtime verification, update the five project documents with exact evidence, then merge the completed Stage 5 branch to \`main\`. Review all remaining remote branches; branches already merged/obsolete should be removed where possible, and any branch that cannot be deleted through the available tooling should be deleted manually from GitHub/local Git without destructive history rewriting.


## 2026-09-29 — Stage 5 Payroll Integration Merge Checkpoint

The Stage 5 payroll-integration implementation was merged to `main` through PR #56. Merge commit: `7cb7a42a9bb4d90a07d23250f2940473806498c8`.

Status remains **Stage 5 IN PROGRESS / RUNTIME VERIFICATION PENDING**. The code is now on `main`; no Stage 6 behavior has been introduced. The next step is controlled local runtime verification of payroll repayment application, Cr 1410 accounting, balance/schedule updates, insufficient-salary handling, duplicate protection, and audit traceability.


## 2026-09-29 — Stage 5 Repayment Notification Checkpoint

PR #57 was merged to `main` with merge commit `0c01c3a378826b7b7cb260668f8f64b0e6648031`. Employee notifications are now emitted after a committed payroll repayment for applied, partial, skipped, and zero-balance completion outcomes. Notification delivery is informational and cannot roll back the financial transaction.

Stage 5 remains IN PROGRESS pending controlled runtime verification. Stage 6 remains NOT STARTED.


# LATEST CONTINUATION PROMPT — 2026-09-30 — Stage 5 Final Runtime Gates

Continue the existing Ahl El Kheir Charity Management System. Do not rebuild or start a new project.

Environment: repository `moneermax/Ahl-El-Kheir-charity-management-system`; local path `D:\\xampp\\htdocs\\AhlElKheir`; local URL `http://localhost:8081/AhlElKheir/`; database `ahl_el_kheir`; Windows/XAMPP/Apache/PHP 8.2/MariaDB; Arabic RTL; Bootstrap 5.3 RTL; vanilla JS; procedural PHP only.

## Current status
- Salary Advance Stages 1–4: **DONE / RUNTIME VERIFIED / CLOSED**. Do not reopen without genuine regression evidence.
- Stage 5 schedule planning: **DONE / RUNTIME VERIFIED**.
- Stage 5 payroll integration: **IMPLEMENTED / CORE RUNTIME VERIFIED**.
- Stage 5 edge-case verification: **IN PROGRESS**.
- Stage 6: **NOT STARTED**. Do not start it.

Latest main checkpoint: PR #67 merged; merge commit `49ae654a25a8ec2a80901c86fe9aa009da83b96c`. Edge-case harness: `tools/run_salary_advance_stage5_payroll_edge_tests.php`.

## Latest runtime result
```
PASS | Insufficient salary — available_salary | request=SAR-2026-00004 | scheduled_remaining=5000 | eligible_salary=1 | deduction=1 | outcome=partial
SKIP | Insufficient salary — skip_month | No existing disbursed fixture matches this policy rule.
SKIP | Full eligible salary with low eligible salary | No existing disbursed fixture matches the required period.
PASS | Rollback-only cleanup | no payroll/request/schedule/journal mutation was committed.
```

The two SKIPs are fixture-availability gaps, not failures. Do not create a new real employee/request solely to manufacture fixtures.

## Remaining work
1. Inspect current repository/schema/policy fixtures for a safe existing `skip_month` fixture.
2. Inspect for a safe existing low-salary `full_eligible_salary` fixture.
3. If neither exists, use only a rollback-only fixture strategy that leaves no permanent employee/request/schedule/journal mutation.
4. Verify the final payroll repayment notification/audit end-to-end gate if not already evidenced.
5. Do not mark Stage 5 complete until all remaining runtime gates are genuinely verified.
6. After Stage 5 closure, update all project documentation and prepare the next continuation checkpoint.
7. Do not start Stage 6.

## Working rules
- Inspect repo/schema/docs first; do not guess table/column/status names.
- Schema changes only through migrations; no runtime CREATE/ALTER.
- No triggers, views, stored procedures, functions, or events.
- No destructive Git commands: no `reset --hard`, `clean`, `restore`, or force-push.
- Do not repeat already-passed tests unless a genuine regression appears.
- Keep accounting/test data controlled and auditable.
- Commit logical changes and merge only after verification.


# LATEST CONTINUATION PROMPT — 2026-09-30 — Stage 5 CLOSED / Stage 6 Ready

Continue the existing Ahl El Kheir Charity Management System. Do not rebuild or start a new project.

Environment: repository `moneermax/Ahl-El-Kheir-charity-management-system`; local path `D:\xampp\htdocs\AhlElKheir`; local URL `http://localhost:8081/AhlElKheir/`; database `ahl_el_kheir`; Windows/XAMPP/Apache/PHP 8.2/MariaDB; Arabic RTL; Bootstrap 5.3 RTL; vanilla JS; procedural PHP only.

## Current status
- Salary Advance Stages 1–4: **DONE / RUNTIME VERIFIED / CLOSED**.
- Stage 5 schedule planning: **DONE / RUNTIME VERIFIED / CLOSED**.
- Stage 5 payroll integration and edge-case verification: **DONE / RUNTIME VERIFIED / CLOSED**.
- Stage 6 — Direct Repayment & Settlement: **NOT STARTED**.

## Final Stage 5 evidence
Main was updated to merge commit `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.

The final post-merge run of `tools/run_salary_advance_stage5_payroll_edge_tests.php` returned:
- PASS — available_salary + fixed_monthly: SAR-2026-00004; scheduled remaining 5,000 SDG; eligible salary 1 SDG; deduction 1 SDG; outcome partial.
- PASS — skip_month + fixed_monthly: SAR-2026-00007; scheduled remaining 10,000 SDG; eligible salary 1 SDG; deduction 0 SDG; outcome skipped; rollback-only policy override.
- PASS — full_eligible_salary with low eligible salary: SAR-2026-00007; deduction 1 SDG; outcome partial; rollback-only method override.
- PASS — rollback-only cleanup: no payroll/request/schedule/journal mutation was committed.

The two previously unavailable policy branches were verified through SAVEPOINT-based rollback-only fixtures. No permanent test data was created.

Core Stage 5 runtime evidence remains valid for schedule planning, payroll repayment application, balance/schedule updates, duplicate protection, Cr 1410 accounting, audit traceability, and repayment notifications. No Stage 6 behavior was introduced.

## Next work
Stage 5 is closed. Do not reopen it without genuine regression evidence.

The next work unit is Stage 6 — Direct Repayment & Settlement. Before coding, inspect the current repository, actual schema, accounting conventions, and existing salary-advance lifecycle. Define the Stage 6 lifecycle and role boundaries first. Do not invent tables, columns, statuses, accounts, or workflows.

Maintain all project rules:
- schema changes only through migrations;
- no runtime CREATE/ALTER;
- no triggers, views, stored procedures, functions, or events;
- no destructive Git commands;
- procedural PHP only;
- preserve existing accounting/audit architecture;
- do not disturb protected Stage 5 evidence.


## LATEST CONTINUATION CHECKPOINT — 2026-09-30 — Verification/Disbursement Atomicity Fix

The salary-advance accounting lifecycle has been hardened.

Previously, accounting could mark an FM-approved request as accounting_status = verified without performing financial disbursement, creating an approved + verified + not disbursed state.

Current rule for salary advances requiring accounting verification:

FM approval -> accounting verification + disbursement atomically -> disbursed

There is no standalone verification action. Accounting rejection remains available before disbursement. Legacy approved + verified + not disbursed records are treated as ready for disbursement, not processed history.

Processed history contains only disbursed, settled, rejected, and cancelled requests.

Branch: fix/salary-advance-verification-disbursement-atomic
Commits: ea15f86f88e4166ecf97cdaac1e851cfe2e395de, 2769cf2eb5b0062945510a53de9908954e8a5c1f, 7adcffc014f62d98601387f8194b25f87a89f1ec, f2cb76b9043743b3ee855837e8cea3563b96cc30

Immediate runtime gate:
1. Confirm SAR-2026-00009 appears as جاهزة للصرف and not in processed history.
2. Disburse SAR-2026-00009.
3. Confirm it becomes disbursed / تم الصرف, leaves the action queue, and appears in processed history.
4. Only then continue Stage 6 direct-repayment tests.

Do not reopen Stages 1–5. Do not treat approved + verified as a completed business outcome.


# LATEST CONTINUATION CHECKPOINT — 2026-10-01 — Stage 6 CLOSED

Continue the existing Ahl El Kheir Charity Management System. Do not rebuild or start a new project.

Environment: repository `moneermax/Ahl-El-Kheir-charity-management-system`; local path `D:\\xampp\\htdocs\\AhlElKheir`; local URL `http://localhost:8081/AhlElKheir/`; database `ahl_el_kheir`; Windows/XAMPP/Apache/PHP 8.2/MariaDB; Arabic RTL; Bootstrap 5.3 RTL; vanilla JS; procedural PHP only.

## Current salary-advance status
- Stages 1–5: **DONE / RUNTIME VERIFIED / CLOSED**. Do not reopen without genuine regression evidence.
- Stage 6 — Direct Repayment & Settlement: **DONE / RUNTIME VERIFIED / CLOSED**.

## Final Stage 6 runtime evidence
Clean fixture: **SAR-2026-00019**.
- Approved amount: 20,000 SDG.
- Direct repayment method.
- FM approval completed.
- Atomic accounting verification + disbursement completed successfully.
- No payroll repayment schedule was created.
- 10,000 SDG repayment through 1200 — البنك → JE-000049 → reference `SAL-ADV-REP-SAR-2026-00019-93`.
- 10,000 SDG repayment through 1300 — المحافظ الإلكترونية → JE-000050 → reference `SAL-ADV-REP-SAR-2026-00019-94`.
- Both repayments were reflected in the organization receiving accounts.
- Outstanding balance reached 0.00 SDG and request status became settled.
- Repayment evidence upload, viewing, and replacement were successfully tested.
- Multiple repayments against one advance and different receiving accounts were verified.

## Final Stage 6 code checkpoints
- PR #86 atomic disbursement precheck correction: `cebd8df397d76738d56a6287b6ba85afba77b6c3`.
- PR #87 direct repayment evidence query correction: `756debc716339470344428812f9684960a272588`.

## Next session
First inspect the current master documents and staged working plan to identify the **next explicitly defined work unit**. Do not invent a new stage and do not begin implementation before the normal repository/schema/design audit checkpoint.

Maintain all existing project rules:
- inspect repo/docs/schema first;
- no invented tables/columns/statuses;
- schema changes only through migrations;
- no runtime CREATE/ALTER;
- no triggers, views, stored procedures, functions, or events;
- procedural PHP only;
- no destructive Git commands;
- preserve completed salary-advance stages;
- update documentation after each completed milestone;
- record exact merge/checkpoint SHAs;
- runtime-test before declaring a stage complete.

## 2026-10-01 — Projects Phase 5 queued next

Salary Advance Stage 6 Direct Repayment & Settlement is closed and runtime verified. Do not reopen it without genuine regression evidence.

Next: **Projects Module Phase 5 — Accounting Reconciliation / Post-Approval Financial Integrity Audit** — TO DO / NEXT.

Projects business intent clarified: after required FM-controlled financial approval, the full approved project budget comes under Project Supervisor control, organizational treasury is reduced by that amount, and the amount is treated as project expense at that financial/disbursement point. If the Project Supervisor saves part of the approved budget, the saved balance must later be reconciled and returned to the organization's accounts through an explicit, auditable financial event.

When continuing Projects, inspect the existing repository/schema/chart of accounts and fresh database evidence first. Do not invent an account, transfer mechanism, journal type, or schema change. Resolve the accounting event boundary before implementation so the same financial event cannot be recognized twice. Correct historical phantom project-approval journals only after the replacement accounting model is settled.



## 2026-10-01 — Latest Checkpoint: Unified User / Employee Creation

The Admin/HR account-creation architecture is now consolidated.

Canonical page:
`modules/users/index.php`

Canonical shared form:
`modules/users/_create_user_form.php`

Canonical provisioning:
`modules/hr/lib_employee_provisioning.php` → `hrCreateUserWithEmployee()`

Rules:
- Admin and HR Manager use the same creation page, form, validation, and provisioning path.
- Creating a user automatically creates and links one employee profile through `employees.user_id`.
- Personal fields may be empty initially and may be completed later.
- `modules/hr/employees.php` is employee management only; its former add/create path redirects to the canonical page.
- Do not recreate a second HR-specific account creation page.
- Do not remove `_create_user_form.php`; it is the shared canonical form.
- Manager selection is maintained only in `modules/users/index.php`.
- No PM/PS-specific exception is permitted.
- Do not modify Leave or Salary Advance behavior unless a concrete regression is demonstrated.

Implementation/documentation commits:
- `b90791e2bfa49f0a6cab26e1844b951c52981d38`
- `ca42000f4552eeeb4fea6fb493bb8008c29a40b9`
- `ffed7719f241f7bcee1732da88e743e838296f2c8`
- `6b36187460bc27246d9a5e89615f4c8b85cdf2d8`
- documentation: `724e0d6e08451d828c6fa067627bfff6b9d7674d`, `5ebfdb10883b9eb03eec6d2389877c15ffa0a470`, `0cd4cc8ba058de752d4f9b61e9f48a276140dca5`

Next verification gate: runtime-test the canonical creation page from both Admin and HR Manager and verify automatic one-to-one user→employee creation using controlled test data. Then continue from the explicitly documented next work unit; do not invent a new stage.

## LATEST CONTINUATION CHECKPOINT — 2026-10-02 — Account Provisioning + Password Workflow CLOSED

Continue the existing Ahl El Kheir Charity Management System. Do not rebuild or start a new project.

Environment: repository moneermax/Ahl-El-Kheir-charity-management-system; local path D:\xampp\htdocs\AhlElKheir; local URL http://localhost:8081/AhlElKheir/; database ahl_el_kheir; Windows/XAMPP/Apache/PHP 8.2/MariaDB; Arabic RTL; Bootstrap 5.3 RTL; vanilla JS; procedural PHP only.

## Current status
- Salary Advance Stages 1–6: **DONE / RUNTIME VERIFIED / CLOSED**.
- Unified User / Employee provisioning: **DONE / IMPLEMENTED / DOCUMENTED**.
- Temporary-password first-login workflow: **DONE / RUNTIME VERIFIED / CLOSED**.
- Login/password credential-direction hardening: **DONE / RUNTIME VERIFIED / CLOSED**.

## Password workflow evidence
- Account creation generates a temporary password server-side; no password field is provided to Admin/HR.
- The password is stored only as a hash and password_change_required = 1.
- The generated temporary password is displayed once after successful account creation.
- First login with that password reaches the forced password-change flow.
- Successful password change clears the forced-change flag.
- Subsequent normal login with the new password succeeds.
- Credential fields use stable LTR character direction so login/password changes remain usable whether the surrounding page is RTL or LTR.

Relevant commits:
- Migration: 43f0a0ce846952e8dceaea72325807f7ac5b6c1f
- Login direction hardening: c3b139cae1d08c67f00313b2bca50c8d20987afe
- Password-change direction hardening: 745fe81d5c3dcc7773b84377e4d2ff5facc9dd88

## Important Projects baseline
The historical Projects Phase 5 accounting implementation/audit attempt was explicitly rolled back. The rollback baseline is commit 594e6c216757851526e63e80a8b2e8585416d94a. Do not treat the rolled-back Phase 5 implementation, migrations, or files as current. Any future Projects work must first inspect the rollback baseline plus current main and perform a fresh design/schema audit.

## Next session instructions
1. Start on main; do not create branches.
2. Inspect the current master status/audit/session index and this continuation document before making changes.
3. Identify the next explicitly agreed work item from the current project checkpoint; do not invent a new stage.
4. Inspect the actual current source and schema relevant to that work before coding.
5. Preserve all completed Salary Advance and account/password workflow behavior unless a concrete regression is demonstrated.
6. For Projects, use the rollback baseline as the historical implementation boundary; do not resurrect the rejected Phase 5 implementation by assumption.
7. Keep all existing project rules: no guessed schema, migrations only for schema changes, no runtime CREATE/ALTER, no triggers/views/stored procedures/functions/events, procedural PHP only, no destructive Git commands, and no branches.
8. Update documentation and continuation checkpoint after the next milestone.

## User's preferred working style
- Inspect the repository directly rather than asking the user to run routine inspection commands when the connector can provide the evidence.
- Make changes directly on main; do not create branches.
- Do not rebuild existing modules.
- Explain findings before changing established workflows.
- Runtime-test changes before declaring them complete.
