# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-18

## START HERE

For every new ChatGPT session, read in order:

1. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — permanent continuation/development rules.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — high-level project status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — detailed master audit as needed.
4. `docs/FINA_SETTLEMENT_PROCESS.md` — authoritative Fina settlement policy, stages, runtime evidence, and current continuation point.

This index is the short handoff document. The repository and these documents are authoritative over old chat history.

## NON-NEGOTIABLE CONTINUATION RULES

- Do not restart the project or completed audits.
- Do not repeat passed tests, fixtures, or SQL verification unless genuine regression evidence appears.
- Do not invent SQL table/column names; inspect actual schema/code first.
- When asked to proceed/fix/do it, perform repository work directly when possible.
- Preserve intentional local uncommitted work and protected FM dashboard backups.
- Never use destructive reset/restore/clean/stash operations or force-push.
- Server-side authorization is the security boundary.
- Keep documentation current after meaningful milestones.

## FINA SETTLEMENT — CURRENT ACTIVE WORK

Fina is a protected third-party fund. It is not Ahl El Kheir revenue, sponsorship revenue, administrative-fee revenue, or an Ahl expense. Dedicated liability/control account: **`2300`**, and this account is **permanent**.

### Finalized Fina settlement cycle

- New approved Fina payments accumulate in the same permanent `2300` liability account.
- When Fina requests settlement, the **entire current outstanding Fina liability is settled in full**.
- **Partial settlement is not part of the production model.** No partial amount or allocation workflow is exposed.
- The settlement does not use Ahl operating treasury accounts `1100`, `1200`, or `1300`.
- Fina-held money is represented separately through the dedicated `Fina Al-Khair Held Funds` asset/control account created by the new settlement-model migration. This is a custody/control account for Fina money, not Ahl operating treasury.
- Full settlement posts `Dr 2300 / Cr Fina-held funds`.
- After successful settlement, `2300` returns to zero but remains permanently available.
- The next Fina payment increases the same `2300` again, repeating the cycle.
- Historical Fina collection journals remain unchanged.

All settlement processing is **Financial Manager (FM) only**. Supervisors, GM, and VGM do not gain settlement execution authority through links, notifications, or direct URLs.

### Completed stages

- Stage 0 — business rules: **COMPLETE**.
- Stage 1 — existing-system/schema inspection: **COMPLETE**.
- Stage 2 — original settlement data model: **COMPLETE**; original migration applied successfully to live DB on 2026-09-17.
- Stage 3 — original accounting engine: **COMPLETE / RUNTIME VERIFIED / CLOSED** on 2026-09-17 as historical development evidence.
- Revised settlement-model code/migration: **IMPLEMENTED IN REPOSITORY; CORE FINA SETTLEMENT ACCEPTANCE COMPLETE**. Two production cycles, reusable 2300 behavior, FM-only access, and original-journal preservation are runtime-verified.

### Historical Stage 3 test evidence — preserve, do not treat as current business settlement

- `FINA-SET-000001` — 50,000 SDG, full settlement of collection #3, closed, journal 56.
- `FINA-SET-000002` — 100,000 SDG, partial settlement of collection #4, closed, journal 57.
- Original collection #3/journal 54 and collection #4/journal 55 remain historical evidence.

The revised migration marks these two settlement records as historical test fixtures and creates compensating posted restore entries so their old partial-settlement test effect does not reduce the current production liability. Their original journals are not edited or deleted. Do not clean or recreate these fixtures.

### Revised Stage 4 — CORE ACCEPTANCE COMPLETE

Repository implementation now includes:

- `database/migrations/2026-09-17_fina_full_settlement_model.sql` — permanent 2300/full-cycle model, dedicated Fina-held-funds account, historical test-fixture neutralization, and historical collection-funds reclassification.
- `modules/accounting/fina_lib.php` — new Fina collections post to the dedicated Fina-held-funds asset instead of Ahl treasury accounts.
- `modules/accounting/fina_settlement_lib.php` — production settlement is full-current-balance only; no partial amount; no Ahl remitting account; settlement journal is `Dr 2300 / Cr Fina-held funds`.
- `modules/accounting/fina_settlements.php` — FM-only UI with no treasury-account selector and no partial-allocation controls.

### Database-structure decision at this checkpoint

The existing `fina_settlements` and `fina_settlement_allocations` tables are **retained intentionally**. They are current dependencies of the implemented settlement engine/UI and provide settlement history/audit evidence. Do not drop, recreate, or refactor them merely for cleanup. Leaving them in place does not alter the finalized full-settlement business flow.

The obsolete item was the separate old migration file and the old production partial-settlement behavior; the old migration definition has already been consolidated into the final full-settlement migration. No triggers or views are being introduced by this work.

### Runtime acceptance — CORE ACCEPTANCE COMPLETE

1. Check local Git status before synchronization; preserve intentional uncommitted files. — **COMPLETE**.
2. Confirm the local database is at the finalized settlement-model state; do not drop the existing settlement tables. — **COMPLETE**.
3. Verify the dedicated Fina-held-funds account exists and is an asset/control account. — **COMPLETE** (`1401`).
4. Verify current production Fina liability is **250,000 SDG** despite the retained Stage 3 test records. — **COMPLETE**.
5. Verify 2300 represents the current 250,000 SDG liability. — **COMPLETE**.
6. Verify no Ahl treasury account is offered/required by the settlement UI. — **COMPLETE during `FINA-SET-000003` creation**.
7. Create one full settlement for exactly 250,000 SDG — **COMPLETE** (`FINA-SET-000003`).
8. Verify the settlement journal is balanced `Dr 2300 / Cr Fina-held funds` and 2300 becomes zero. — **COMPLETE** (`JE-000035`; both `1401` and `2300` are 0 afterward).
9. Verify the historical Stage 3 records remain present and identifiable as test evidence. — **COMPLETE**.
10. Create/approve a new Fina payment after settlement and verify the same 2300 account increases again. — **COMPLETE** (`JE-000036`, collection #5, 600,000 SDG).
11. Verify FM-only authorization and evidence/reference/lifecycle controls. — **COMPLETE**; Supervisor `sv2` direct access was denied.
12. Verify original Fina collection journals remain unchanged. — **COMPLETE**; journals #54 and #55 remain posted and unchanged.

### Future development — workflow simplification

The current production lifecycle remains intentionally unchanged for this audit:

`مسودة → معتمدة للتحويل → تم التحويل → تمت المطابقة → مغلقة`

After the settlement process and acceptance work are fully completed, evaluate a shorter user-facing workflow, potentially `مسودة → معتمدة → مغلقة`, while retaining transfer/reconciliation information as audit data/events. This is a future UX/workflow simplification and must not interrupt the current acceptance work.

**Core Fina settlement accounting acceptance is complete. No further financial transactions are required for this audit area.**

## FINA STANDALONE PAYMENT

Existing Fina collection flow remains authoritative:

- Entry: `modules/transactions/fina_payment_create.php`.
- Review: `modules/accounting/fina_payment_review.php`.
- Receipt: `modules/accounting/fina_receipt.php`.
- Dashboard summary: `modules/accounting/fina_dashboard_summary.php`.
- Collections use `fina_sources` and `fina_collections`.
- System currency is SDG only via `APP_CURRENCY_CODE`.

Do not alter historical Fina collection amounts or original collection journals merely to support settlement.

## OTHER COMPLETED CURRENT-CHECKPOINT WORK

- FM treasury/admin-fee dashboard regression fixed and closed.
- FM treasury calculation runtime-verified and closed.
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization follows Sponsor first-name letter + Sponsor gender responsibility rule.
- VGM sponsor assignment/reassignment confirmed as a VGM task; Supervisor direct access blocked.
- Receipt-file regression fixed and runtime-confirmed.
- Accountant Staff financial/reporting and disbursement authorization work completed at the documented boundary.
- Disbursement/reissue test batch #12 completed; do not recreate unless regression requires it.
- Transaction void test `TR-000016` / `JE-VOID-TXN-22` completed.
- Manual journal `JE-000027` balance/authorization checks completed.
- Shared notification system and non-destructive clear-all behavior remain preserved.

## PARKED / LATER AUDIT WORK

The accounting journal cross-module integrity review remains a separate parked direction unless explicitly resumed after the Fina settlement work. Previously completed Supervisor ↔ Accounting integration tests and other closed runtime tests must not be repeated without regression evidence.

## LOCAL GIT SAFETY

The connected GitHub view cannot inspect the user's Windows working tree. Before pulling locally:

```powershell
cd D:\xampp\htdocs\AhlElKheir
git status --short --branch
```

Then use a safe pull appropriate to the actual local state. Never discard local work merely to obtain latest `main`.

## CURRENT CONTINUATION POINT

**Stage 3 original Fina accounting engine: COMPLETE / RUNTIME VERIFIED / CLOSED as historical evidence.**  
**Stage 4 revised Fina settlement model/UI: IMPLEMENTED IN REPOSITORY; CORE ACCEPTANCE COMPLETE — two production cycles verified through close, permanent `2300`, separate Fina-held funds, no partial settlement, FM-only authorization, and original collection journals preserved.**  
**Database cleanup decision: retain `fina_settlements` and `fina_settlement_allocations`; do not spend additional audit time dropping them. Next work is targeted runtime acceptance.**


## DASHBOARD / NAVIGATION REVIEW — CURRENT HANDOFF 2026-09-18

Accounting audit is intentionally parked. Current active work is the dashboard UX and role-safe navigation review, with special attention to the Administration/Staff/Social Media dashboard.

### Universal Reports

The project now uses one universal Reports Dashboard at modules/reports/index.php, backed by modules/reports/report_registry.php. Sidebar report navigation is a single Reports entry, not a dropdown. Direct report URLs use the same central authorization.

Important business rule:
- GM and VGM need broad organizational reporting visibility for management decisions, including financial reports where authorized by the report catalog.
- FM keeps financial workflow/control authority.
- Reporting access and workflow authority must not be conflated.

### Current Administration dashboard

dashboard/staff_dashboard.php was developed from its previous placeholder into an Administration/Staff/Social Media operational dashboard.

The user has now runtime-tested it and confirmed it loads without errors after the latest fixes.

Verified database relationship:
families.id ← family_children.family_id ← family_children.id ← sponsorships.child_id

There is no sponsorships.family_id and no children table.

The dashboard and Winback page were corrected to use the real relationship.

Current related commits:
- 11c978de7da1f90166c0aad9677f69989be2d105 — Administration dashboard development.
- 6c7a08fed514a2090a56e3ee30b16edafd6c134c — Administration sidebar Dashboard entry.
- ba934c6cce0bf17eced9f9d1769a2a5908df0915 — sponsor-request role authorization.
- 754a53b467e8e027543c1e089ae5e74586b10e — Administration Winback sidebar link.
- 8a2dabea8b7a7a6854c2a08ecad2c9d106e36a86 — dashboard family sponsorship query correction.
- 0d26093b53864b205ee42e4d91fe04cc7dfaee2c — Winback family sponsorship query correction.
- 1257b54f8718c5c4546f6006191f51921518836f — role/link alignment and removal of invalid Administration Reports/families shortcuts.

Runtime status:
- Administration dashboard: loads without errors.
- Winback link/page: no longer errors after the relationship fix.
- Invalid direct Families link and unauthorized Reports shortcut were removed/repointed rather than weakening authorization.

### OPEN WORK — DO NOT MARK COMPLETE

The Administration dashboard still needs UX/functional review. The user explicitly said there are more fixes/work to do on the same dashboard.

Next session should inspect:
1. KPI meanings and whether each number is useful/actionable for Administration.
2. Sponsor-request table fields, status presentation, and links/actions.
3. Winback workflow integration and whether the dashboard exposes the right Administration actions.
4. Orphan-form workflow link and whether it is valid for the role.
5. Reports visibility for Administration/Staff/Social Media based on the actual report catalog; do not add unauthorized links just to fill space.
6. Sidebar/dashboard consistency.
7. Role differences between administration, staff, and social_media so users do not see inappropriate actions.
8. Any remaining schema assumptions in the dashboard. Verify actual schema before SQL.
9. Arabic labels/encoding and responsive UX.
10. Any runtime errors the user reports.
11. Role-specific dashboard behavior: Administration retains Winback/uncovered-family actions; Staff and Social Media must not see or reach Winback-only actions from this shared dashboard.
12. Sponsor-request status labels and row actions should be directly useful and Arabic; do not invent an individual-request route that the workflow does not implement.

Do not restart the dashboard. Do not repeat the already-fixed sponsorship-family schema investigation.

### CONTINUATION RULE

Start by reading this index, then master status, then relevant master-audit sections, and inspect the current dashboard/staff_dashboard.php and linked pages before changing anything.


### Latest Administration dashboard refinement — 2026-09-18

Commit: `916758bf205bebbd43805eee010f1dc6163dbe7e` — `Refine administration staff social dashboard role-specific UX`.

The shared `dashboard/staff_dashboard.php` was narrowed by role without changing server-side authorization:
- Administration retains the Winback and uncovered-family KPIs/actions.
- Staff and Social Media no longer receive Winback-only KPIs or links they cannot use.
- Sponsor-request status labels on the dashboard are now Arabic rather than raw internal status values.
- Sponsor phone numbers are actionable `tel:` links when present.
- The recent-request table now includes a clear action back to the authorized sponsor-request workflow.
- Quick actions remain role-appropriate; Winback is shown only to Administration.

This is a dashboard UX/role-separation refinement, not a reopening of the completed schema investigation. Runtime verification by the user is still required after pulling the commit.


### Winback dashboard/list UX refinement — 2026-09-18

The Administration dashboard Winback KPI links now use distinct anchors: open follow-ups use `#open-cases`, while uncovered families use `#uncovered`. In `modules/administration/winback.php`, the `أسر متاحة للتكليف حالياً` list was moved below `الكفلاء المتوقفون المؤهلون`, expanded to the full-width section, and its table header is sticky within a scrollable list area. The obsolete `القائمة الكاملة` self-link was removed because it only refreshed the same page. Relevant commits: `bbe945f10114e174081fc8e8f584bcac5633c704`, `8ddd2d6fb5b2e0c393e36100e4c3e964bfd36583`, `0bbb8ce2e39c508f6ec208a2029498fbddf24919`.


### Winback dashboard/list UX refinement — 2026-09-18

The previous anchor-only KPI destinations were confusing because both KPIs still opened the same Winback page. The `أسر بلا كفالة نشطة` KPI now opens the dedicated `modules/administration/available_families.php` page, while `متابعات استرجاع مفتوحة` opens the Winback open-cases section. The available-family table on Winback remains in its current location below the eligible stopped sponsors, keeps the sticky header, and now has an `إجراء` column with a family `عرض` action; `الاحتياج الشهري` and its values are centered. Dedicated page commit: `b28a334019ba0a6ce9620c88fb5ae46082348c13`; Winback table commit: `48036218e776e1b366e5e7161634c3878b541cb8`; dashboard target commit: `fc7b55e506fd98ad2f99dfbf1f13e5901310af58`.


### Administration sponsorship-safe family profile — 2026-09-18

A dedicated sponsorship-review page now exists at `modules/administration/sponsorship_family_view.php` for `admin`, `general_manager`, `vice_general_manager`, and `administration`.

Purpose: Administration needs enough information to truthfully present a proposed orphan/family to a new or returning sponsor, including factors that can affect acceptance (health status, psychological state, education level, critical flag, current sponsorship value, extra allowance, and latest previous sponsorship value/status/start date), without opening the complete confidential family case file.

Privacy boundary preserved:
- shown: family code, mother name, general city, family monthly need, child identity/basic demographics, health/psychological/education information relevant to sponsorship, current sponsorship requirement, extra allowance, latest prior sponsorship amount/status/start;
- not shown: mother phone/alternate phone, exact address, registration number, bank accounts, family documents, internal family notes, internal case-management controls.

Routing changes:
- `modules/administration/available_families.php` now opens the sponsorship-safe profile.
- the available-family list inside `modules/administration/winback.php` now opens the sponsorship-safe profile.
- `administration` was removed again from the unrestricted `modules/families/view.php` authorization after the dedicated profile was introduced.

Relevant commits:
- `f30656098422e9bf6e107a6c18c44daa4d8bebd6` — add sponsorship-safe family profile.
- `783e1aae150e81c20186182716ab1d74104ccd56` — route available families to sponsorship profile.
- `6604dcaa3c17c51c08ffcae6a6495bec3a3c8a5e` — keep Administration out of the full family case profile.
- `90fbc18cf40cbe4aff58ea3b0c2fffb04161861c` — route Winback family review to sponsorship profile.

Runtime verification is still required after the user pulls current `main`.


### Winback declined-case reopening — 2026-09-18

Winback now supports reopening an existing `declined` campaign when a sponsor who previously declined later agrees to reconsider. The existing campaign is reopened to `open`, `closed_at` is cleared, the current handler is recorded, and an audit entry is written with action `REOPEN`. This avoids creating a duplicate campaign for the same sponsor and preserves the original contact history.

The Winback case detail and history list provide an `إعادة فتح المتابعة` action for declined cases.

Implementation commit: `8ab5cbacdca806bd20c7afffb92dc1e826903aae`.

Runtime verification is pending.

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


## ADMIN CONTROL PANEL — 2026-09-18

The active dashboard work has now moved to the dedicated `dashboard/admin_dashboard.php` for the `admin` role.

### Direction

This dashboard is intentionally different from the operational dashboards. It is the system owner's control panel, inspired by hosting/control-panel patterns: high-level system statistics, action-required items, grouped tools, infrastructure status, and direct administration controls.

The dashboard keeps the existing application shell and server-side admin guard, but the dashboard surface itself uses a distinct dark control-panel hero, neutral/slate panels, colored status/stat accents, grouped tool grids, and responsive behavior.

### Implementation

Commit:
- `ebc6ba9278b9c7efdb9293d24b5b3a4acd8b43d8` — Redesign admin dashboard as dedicated control panel

The new dashboard:
- remains restricted to `admin`;
- shows safe counts for users, sponsors, families, sponsorships, settings, and audit records;
- shows the current admin's unread notifications;
- shows pending password-recovery requests;
- checks basic database connectivity with `SELECT 1`;
- provides grouped controls for system administration, core data, finance, and protection/maintenance;
- preserves existing authorized destinations rather than creating new workflow endpoints;
- removes the previous generic system-information table that exposed internal path/database-user details unnecessarily;
- is responsive for tablet/mobile widths;
- keeps the existing application footer/age-alert behavior.

### Important verification state

Repository implementation is complete for this first Admin Control Panel pass.

**Runtime verification is still required by the user after pulling `main`.**

Do not mark the Admin Control Panel finished until the user verifies:
1. admin dashboard loads without PHP/SQL errors;
2. the new visual hierarchy works as intended;
3. every control-panel link opens the expected existing page;
4. counts render correctly;
5. pending password-recovery and unread-notification cards behave correctly;
6. database health shows the expected connected state;
7. responsive layout is usable;
8. the dashboard remains inaccessible to non-admin roles.

### Next Admin dashboard review

After runtime verification, continue the audit from the actual screenshot/behavior. Focus on:
- whether the control-panel grouping matches the administrator's real daily workflow;
- whether any important system-control area is missing;
- whether any displayed KPI is misleading or too low-value;
- whether any linked page is inappropriate for admin or needs a better destination;
- Arabic encoding and typography;
- desktop/mobile spacing;
- sidebar/header relationship with the new control-panel surface;
- whether additional system-health indicators can be added only after verifying their real data sources.


### Admin Control Panel broken-link correction — 2026-09-18

During runtime review of `dashboard/admin_dashboard.php`, the dashboard tile `الأدوار والصلاحيات` was found to target the nonexistent `modules/users/roles.php`. Repository inspection confirmed that role assignment is already implemented inside the admin-only `modules/users/index.php`, where administrators load the existing roles from the `roles` table and assign them while creating/editing users. No separate roles page exists in the current repository.

The dashboard was corrected without creating a duplicate roles page or changing the permission model:
- tile renamed to `أدوار المستخدمين`;
- description now reflects the actual function: assigning available user roles/access levels;
- destination changed to the existing `modules/users/index.php` user-management page.

Commit: `124c8970b6511ae930ddd025da96f7fb555ffe1b`.

Runtime verification is required after pulling this commit. Continue checking the remaining Admin Control Panel links against actual existing repository destinations; do not invent missing endpoints.


### Admin Control Panel duplicate-destination correction — 2026-09-18

During continued runtime review, the user identified that the separate tiles `المستخدمون` and `أدوار المستخدمين` both opened the same `modules/users/index.php` page. Repository inspection confirmed there is no separate roles-management destination, so keeping two tiles would be misleading. The duplicate `أدوار المستخدمين` tile was removed rather than inventing another endpoint. The `إدارة النظام` group now contains the three real destinations: users, departments, and system settings, with a three-column desktop layout for that group.

Commit: `b40b8fd8e3ead0ad17c24c3af08a11f01b0adc21` — `Remove duplicate admin roles destination`.

Runtime verification is still required after pulling the latest `main`.


### Admin Control Panel navigation-duplication cleanup — 2026-09-18

After reviewing the actual admin sidebar together with `dashboard/admin_dashboard.php`, the dashboard was found to duplicate too much of the sidebar's navigation. The sidebar already provides the administrator's complete navigation for users, departments, sponsors, families, sponsorships, audit, database management, settings, reports, accounting, and related workflows.

The Admin Control Panel was therefore narrowed to preserve its role as a **control/overview surface rather than a second navigation menu**:

- The four overview KPI cards remain, but are now informational cards rather than navigation links.
- The large duplicated groups for system administration, core data, and finance were removed from the dashboard.
- Audit/database/settings and other sidebar destinations are no longer repeated as dashboard tiles.
- Action-required cards remain for pending password-recovery requests and unread administrator notifications because these represent attention items rather than general navigation.
- A small **أدوات إدارية خاصة** section remains only for the backup function, which is an administrator maintenance action and is not currently represented in the admin sidebar.
- The existing admin-only guard, statistics queries, database health check, application shell, and responsive behavior were preserved.

Commit:
- `ea1b2a02c4a2cb8e3138233f44c433f7ed7af2f5` — `Reduce admin dashboard navigation duplication`

### Runtime verification required

After pulling the latest `main`, verify:

1. `dashboard/admin_dashboard.php` loads without PHP/SQL errors.
2. The sidebar remains the primary navigation and is not duplicated by a large dashboard menu.
3. The four KPI cards display correctly and no longer behave as navigation links.
4. Password-recovery and unread-notification action cards still open their existing authorized destinations.
5. The backup action opens the existing backup page.
6. The admin-only dashboard remains inaccessible to non-admin roles.
7. Arabic text, spacing, desktop layout, and responsive behavior remain correct.

Do not reopen the removed dashboard navigation groups unless a specific missing administrator control is identified from the real system workflow.

### Admin Control Panel system-health enhancement — 2026-09-18

The administrator dashboard was enhanced using selected concepts from the newly added root-level sudo_dashboard.php, while deliberately adapting them to the real Ahl El Kheir architecture instead of copying its unrelated schema or workflows.

Added to dashboard/admin_dashboard.php:
- system/DB connectivity status;
- PHP version;
- available/total disk space and usage percentage;
- PHP memory limit;
- PHP upload limit;
- PHP execution-time limit;
- current HTTPS state;
- explicit admin-only dashboard protection indicator;
- a compact preview of the six most recent audit_log events with a link to the existing full audit log.

The dashboard remains the main Admin Control Panel and the sidebar remains intentionally minimal. No duplicate settings, backup, database-management, user-management, or other workflow screens were introduced.

Implementation commit:
- 07f87ade2c36105887292ec80846f3ed98371df4 — Enhance admin dashboard with system health and recent activity

Runtime verification is required after pulling. Pay particular attention to:
1. no PHP/SQL errors;
2. disk-space values;
3. PHP limits;
4. HTTPS status under the local XAMPP HTTP URL;
5. recent audit events rendering correctly;
6. mobile/tablet layout;
7. existing dashboard navigation remaining intact.


### Admin Control Panel navigation correction — 2026-09-18

The previous navigation-duplication cleanup was corrected after clarifying the intended UX direction.

The intended design is:
- **Admin Control Panel dashboard = the main admin control/navigation surface.**
- **Admin sidebar = minimal shell navigation**, not a second copy of the control panel.

The earlier navigation-duplication cleanup commit was reversed because it moved too many destinations out of the dashboard.

Current implementation:
- dashboard/admin_dashboard.php restored the full control-panel groups from the accepted prior baseline.
- includes/sidebar.php admin menu was reduced to the Admin Dashboard entry; profile/logout remain global personal controls.
- This removes the duplicated admin navigation from the sidebar while preserving the richer control-panel dashboard.
- The nonexistent modules/users/roles.php remains removed; the dashboard uses the real modules/users/index.php destination for user/role administration.

Commits:
- 0ba244d46b6bcea14070baf21cbafe94fd8b9875 — Restore full admin control panel navigation surface
- ddc680bec703747239f6161c4dbaff04b2c8ae98 — Remove admin sidebar links duplicated by control panel

Runtime verification is required after pulling. For the admin role, verify that the sidebar is intentionally minimal and that the dashboard itself exposes the expected admin control-panel destinations.


### Audit Log Retention / Cleanup
The audit log page at `modules/logs/audit.php` now includes an **administrator-only bulk cleanup control** for retention management. The control deletes audit records on or before a selected date, uses a required confirmation checkbox plus a final browser confirmation, verifies that no records remain within the requested cutoff after deletion, and leaves General Manager access read-only. The cleanup is intentionally date-based rather than an unrestricted “clear everything” button.
The existing audit-log viewing/filtering/pagination behavior remains unchanged.
The separate request to remove the SQL/query display from the audit page is **not yet implemented** because the current repository version of `modules/logs/audit.php` does not contain a SQL-debug/query panel; it renders the audit record's previous/new values instead. Do not remove those audit details by assumption. Runtime screenshot/source comparison is required before changing that part.


### Audit Log detail-display refinement — 2026-09-19

The audit-log cleanup control remains administrator-only with date-based deletion, required checkbox confirmation, final browser confirmation, and post-delete verification.

The audit record `old_values` / `new_values` content was also refined for readability. It is JSON audit data, not an SQL/query panel. The detailed before/after payload is now collapsed by default behind **عرض التفاصيل**, so the main audit table remains compact while the original audit evidence is still available on demand.

Implementation commit:
- ff7b35eacc7059b0052e80dad1c8622ce638efb9 — Improve audit log detail display

Runtime verification required after pulling:
1. Audit log opens without PHP/JS errors.
2. The Previous/Next columns show **عرض التفاصيل** rather than dumping large JSON blocks into the table.
3. Clicking **عرض التفاصيل** expands the corresponding JSON safely and preserves Arabic text.
4. Audit filtering and pagination remain unchanged.
5. Admin-only old-log cleanup remains available; General Manager remains read-only.


### Audit Log cleanup count-message correction — 2026-09-19

The audit cleanup result message was corrected so it no longer reports **0 deleted** merely because the database driver's DELETE row-count is not the desired reporting source. The cleanup now counts matching audit records before deletion, performs the same verified deletion, then checks the remaining count.

Result behavior:
- records existed before cleanup → reports the number that was actually in the deletion range;
- no records existed → reports that there was nothing new to delete;
- records remain afterward → reports the before-count and remaining-count as an incomplete cleanup.

Implementation commit:
- 13c849f7cc77d7445f2e372da9e7418850d87682 — Report audit cleanup counts accurately


### Audit Log cleanup date-range UX — 2026-09-19

The administrator audit-log cleanup control was changed from a single cutoff date to an explicit inclusive date range:
- **من تاريخ**
- **إلى تاريخ**

Both dates are required. The server validates the date format and rejects a range where the start date is after the end date. Deletion and post-delete verification are limited strictly to the selected inclusive range. The confirmation text now explicitly states that both boundary dates are included.

Implementation commit:
- 354a149b75bd8a26716358bab24adb8379dff998 — Use explicit audit cleanup date range


### Audit Log Cleanup — final current state 2026-09-19

The audit log cleanup feature at `modules/logs/audit.php` is now implemented and the page syntax/runtime issue introduced during the final date-range change has been corrected.

Current behavior:
- Admin-only bulk deletion.
- Explicit inclusive **من تاريخ / إلى تاريخ** range.
- Required confirmation checkbox plus final browser confirmation.
- Server-side validation rejects invalid dates and start > end.
- Counts matching records before deletion rather than relying on PDO DELETE rowCount().
- Verifies that no records remain inside the selected range after deletion.
- General Manager remains read-only.
- Audit `old_values` / `new_values` remain available as collapsed **عرض التفاصيل** JSON evidence; they are not SQL code and were not removed.
- The confirmation checkbox wording now correctly refers to the selected period.

Final syntax-fix commit:
- `4b2bdcb75faf0dc6c004c48c14fb9e0d44ac4b46` — Fix audit log cleanup parse error

**Runtime status:** User confirmed the page now opens and works after pulling the fix.

Do not reopen this cleanup work unless a genuine regression appears or the user explicitly requests another retention-policy change.


## ORPHAN PROFILE — ADDITIONAL SPONSORSHIP UX — 2026-09-19

The orphan profile at `modules/families/orphan_profile.php` was enhanced so authorized users can add an additional sponsor directly from the orphan profile instead of leaving the page and manually restarting the sponsorship creation workflow.

Implementation commit: `b0ba3f92d1ee9ea542936f9ce8b381c03f964b45` — `Simplify adding additional orphan sponsorships`.

Current behavior:
- The profile displays all sponsorship records for the orphan, with sponsor, code, monthly amount, start date, status, and edit action.
- Active sponsorships are totaled as a monthly amount for visibility.
- `admin`, `vice_general_manager`, and `supervisor` can use **إضافة كفيل آخر** directly on the profile.
- The add form is a modal tied to the current orphan; the user does not need to search for the orphan again.
- The form captures sponsor, monthly amount, start date, and optional notes.
- Server-side authorization is enforced. Supervisor sponsor selection uses the authoritative Sponsor first-name letter + Sponsor gender responsibility rule through `supervisorCanAccessSponsor()`.
- Sponsors already having an active/paused sponsorship for the same orphan are excluded from the selector and rejected server-side if submitted directly.
- New sponsorships use the application's SDG currency policy and create the normal sponsorship code/audit record.
- Existing sponsorship editing remains available through the existing sponsorships workflow.

Database verification completed before implementation: `sponsorships` has `child_id` as an indexed nullable foreign key to `family_children.id`, with no unique constraint preventing multiple sponsorship rows for one orphan. Therefore multiple sponsorship relationships are structurally supported by the current schema.

**Runtime verification pending:** after pulling the commit, test the orphan profile with a real orphan that already has sponsorship #2713 (the user's example is orphan #1582), add a second sponsor, confirm both sponsorships remain visible, verify the combined active monthly total, and verify the new sponsorship appears in the existing sponsorship list/workflow.

### Additional Sponsorship — Searchable Sponsor Selector — 2026-09-19

Follow-up UX fix: the inline **إضافة كفيل آخر** modal no longer uses a long native sponsor dropdown. The sponsor field is now a searchable selector that filters active, authorized, and otherwise-eligible sponsors by sponsor name or sponsor code while typing. The selected sponsor ID remains the actual submitted value, and server-side validation/authorization is unchanged.

Implementation commit: `95089efedd84f05c1f8753ca1aabfd9bff1f1711`.

Runtime verification remains pending: open orphan #1582, open **إضافة كفيل آخر**, search by a sponsor name/code, select a result, and verify the selected sponsor can be saved.

### DASHBOARD REVIEW — 2026-09-19 — Staff orphan-forms authorization correction

Repository inspection found a concrete role mismatch: `dashboard/staff_dashboard.php` exposes the orphan-forms workflow to `staff`, but `modules/families/orphan_forms_index.php` did not include `staff` in its `$viewRoles`, so a Staff user could be sent to a page that redirected them away. Fixed in commit `223c462528cc0e69d62bcf5f76094cea6ce821e2`.

The sponsorship-family orphan header visual fix is also complete in commit `2e4673a9a432e80ffb9cdccddadb4cbffd08b450`; it uses the actual application header `--navy` background directly on the page because `assets/css/style.css` is not loaded by `includes/header.php`.

**Next runtime checks:** pull current `main`, then verify Staff can open `modules/families/orphan_forms_index.php` from the dashboard while Administration and Social Media retain their existing behavior. Continue the dashboard review afterward; do not reopen completed Winback schema or sponsorship-family investigations.

### DASHBOARD REVIEW — Winback workflow authorization hardening — 2026-09-19

Security review of the Administration Winback linked workflow found three POST authorization gaps. `open_case`, `add_contact`, and `mark_declined` now validate the sponsor/campaign eligibility or current workflow state server-side before mutation.

Commit: `776da4470010d27c8758fd116843d3d06f9c94b9`.

Runtime verification is the next required step before continuing to other dashboard UX findings.


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
