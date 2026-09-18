# Ahl El Kheir Charity Management System — Master Status

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-15

This is the single high-level **START HERE** status and continuation summary for the existing project. The detailed audit record is consolidated into `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`.

## 1. Project rule

This is an existing project and existing audit continuation. Do not restart completed work, repeat passed tests, recreate protected fixtures, or invent database schema names.

Working rule:

`Inspect → Understand → Verify → Identify risk → Fix narrowly → Test → Document`

## 2. Completed major areas

- HR foundation and HR audit — **COMPLETE / CLOSED at current boundary**.
- Accounting Audit — **COMPLETE / CLOSED at current documented boundary**.
- Notification Integrity Audit — **CLOSED at current evidence boundary**; current notification UI/live-behavior refinements are completed and preserved.
- Authentication/session hardening — **CODE CORRECT + RUNTIME VERIFIED**.
- Accountant Staff financial/reporting authorization and accounting integration — **COMPLETED at current checkpoint**.
- Accountant Staff Arabic dashboard encoding issue — **SOLVED / CLOSED**.
- FM dashboard treasury/admin-fee regression — **FIXED / CLOSED**.
- Supervisor sponsor ownership and restored sponsor-linked family access rule — **COMPLETED / PRESERVED**.
- HR dashboard navigation consolidation — **COMPLETED / CLOSED**.
- Supervisor lifecycle baseline — **DOCUMENTED / CURRENT GAPS PARKED**.

## 3. Supervisor rules that must not regress

### Authoritative Sponsor responsibility

Supervisor sponsor responsibility is determined by:

`Sponsor first-name letter + Sponsor gender → Supervisor`

This is the authoritative business rule for Sponsor responsibility. A Sponsor outside the Supervisor's letter+gender responsibility scope must not be visible or accessible to that Supervisor merely through a direct record URL or related list.

It is **not** determined by the orphan's family, mother's name, mother's first letter, family code, or orphan identity.

### Supervisor operational visibility

Within legitimate scope, the Supervisor may follow the operational chain:

`Sponsor → Sponsorship → Child/Orphan → Family`

subject to each destination's own server-side record-level authorization.

### Direct sponsor assignment clarification

The repository contains historical/direct assignment paths using `sponsor.supervisor_id`, and some existing routes have treated that assignment as an additional access path. The current business-rule clarification is that Sponsor Letter + Sponsor Gender is the authoritative responsibility rule. Therefore `sponsor.supervisor_id` must not be treated as a new independent authorization grant without confirming its documented operational purpose and current workflow usage.

Do not make a speculative change. Inspect the documented workflow and actual repository usage before changing any existing direct-assignment behavior.

### Family access

Family access is a separate concern. Existing implementation/documentation contains direct family assignment and sponsor-linked/matrix-based family/orphan access paths. Do not narrow family access to `families.supervisor_id` alone, and do not use family/mother information as a substitute for the Sponsor responsibility rule.

## 4. Supervisor dashboard boundary

The Supervisor dashboard/navigation is operational, not an Accounting control surface. It may expose scoped operational indicators and links for:

- Sponsors
- Families
- Orphan/child forms
- Sponsorships
- related operational follow-up

Dashboard counts and lists must use the Supervisor's legitimate record scope. A financial consequence of an operational workflow does not by itself grant Supervisor Accounting authority.

## 5. FM dashboard

The treasury row must contain:

1. Cash — `1100`
2. Bank — `1200`
3. Electronic wallet — `1300`
4. Total treasury
5. Administrative fees — `4200`

Fix commit: `783b160a60ce50f0f661a65a112aea7469979ca4`.

### Treasury total calculation — runtime verified 2026-09-15

Repository inspection confirmed that `modules/accounting/fm_dashboard.php` calculates the treasury row directly from posted journal lines, restricted to active accounts `1100`, `1200`, and `1300`:

- Cash = posted ledger balance of `1100` (`debit - credit`).
- Bank = posted ledger balance of `1200` (`debit - credit`).
- Electronic wallet = posted ledger balance of `1300` (`debit - credit`).
- Total treasury = posted ledger movement for those same three accounts.

Therefore the displayed total is mathematically `cash + bank + wallet`, and the query is executed live on the dashboard request rather than using a cached/stale total. Only `je.status = 'posted'` and active treasury accounts are included.

Runtime verification passed on 2026-09-15:

- Cash `1100`: `48,721,100`.
- Bank `1200`: `24,796,000`.
- Electronic wallet `1300`: `25,060,000`.
- Total treasury: `98,577,100`.
- Arithmetic: `48,721,100 + 24,796,000 + 25,060,000 = 98,577,100`.

The wallet had previously been `25,050,000`; the fresh mobile-wallet accounting test increased it to `25,060,000`, exactly `10,000`. The displayed total includes that posted movement.

The reconciliation section's `397,000` inflow, `2,419,900` outflow, `98,577,100` net treasury movement, and `-2,022,900` difference are a separate flow/reconciliation view and must not be confused with the current treasury asset balance. This is **not a defect**.

**Result: PASS / CLOSED.** Do not modify or rerun this treasury calculation test unless new regression evidence appears.

This regression is closed and must not be reopened without new runtime evidence.

## 6. Accountant Staff / ACC1

Known controlled assignment: Accountant Staff user `17` → nanny `16`.

The substantial ACC1/nanny accounting integration is already completed, including assignment-based authorization, disbursement management, reopen controls, receipt/return handling, and accounting integration.

The Arabic encoding issue is already solved. Do not restart old ACC1 tests unless a documented open item or genuine regression appears.

## 7. Current active direction

**Supervisor ↔ Accounting integration review** remains the broader audit direction. The latest completed work includes fresh Supervisor bank-transfer and mobile-wallet/payment-method/accounting-path tests, including the administrative-fee policy check for the bank-transfer transaction and the FM treasury reconciliation test.

The next substantive audit work must inspect actual repository integration points and answer:

1. Can Supervisor access any Accounting page/action directly or indirectly?
2. Does any Supervisor operational workflow create, submit, return, or otherwise mutate an Accounting-controlled record?
3. What financial status/result is appropriate for Supervisor to see without granting Accounting authority?
4. Are Supervisor submissions routed to FM/Accounting using the correct actor and scope rules?
5. Are Accounting notifications/results exposed only to the correct Supervisor?
6. Does any Accounting query accidentally expose data outside the Supervisor's legitimate Sponsor scope?
7. Does any Supervisor dashboard KPI/summary expose Accounting data broader than the Supervisor's operational scope?

The fresh bank-transfer, mobile-wallet, admin-fee `none`, and FM treasury calculation tests are now closed and should not be repeated. Their verified payment methods, posting accounts, balance reconciliations, and treasury-total evidence are recorded below.

## 8. Recent completed Supervisor findings

### VGM sponsor assignment/reassignment

VGM is the operational owner of `modules/sponsors/assign.php`. VGM dashboard exposes the task. Supervisor direct access is blocked. FM has no sponsor-assignment action. All three runtime checks passed.

Commit: `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.

### Sponsor request authorization/navigation

`modules/sponsors/requests.php` remains restricted to `admin`, `vice_general_manager`, `general_manager`, and `social_media`. Supervisor is not authorized for this general queue, and the Sponsor list no longer displays the queue button to Supervisor.

Commit: `ba21c3415158787e2fb294eaf746cf37d7d845bc`.

### Sponsor runtime schema synchronization — completed code cleanup

The following request-time schema mutations were removed:

- `modules/sponsors/create.php` and `edit.php`: runtime `ALTER TABLE sponsors ... brought_by_name` removed; explicit migration retained.
- `modules/sponsors/view.php`: runtime `ALTER TABLE sponsors ... brought_by_name` removed.
- `config/sponsor_assignments.php`: runtime `CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments` removed.
- `modules/sponsors/assign.php`: no longer triggers request-time assignment-history table creation.
- `modules/sponsors/requests.php`: runtime `CREATE TABLE IF NOT EXISTS sponsor_requests` and dynamic `ALTER TABLE ... created_sponsor_id` removed.

Explicit migration: `database/migrations/2026-09-14_sponsor_workflow_runtime_ddl_cleanup.sql`.

The local database has already been verified to contain `sponsors.brought_by_name VARCHAR(255) NULL`.

### Supervisor sponsorship-list scope regression

The Supervisor sponsorship list and sponsor routes were aligned with the authoritative Letter + Gender rule; direct `sponsor.supervisor_id` is not an independent authorization grant.

Commits: `e2a5a23b2aa3b5797cb7298641c5fd5bc85e76b6`, `5f8e5e0848261ab6cd6edf841a21e9193f1bc123`.

The user has previously completed the relevant scope tests. Do not rerun them unless a genuine regression appears.

### Receipt-file regression — fixed and runtime-confirmed

On 2026-09-15, a genuine regression was identified in `modules/transactions/receipt_file.php`: a transaction with no receipt attachment returned a bare `404 Not found` page. The route was narrowed so missing or stale receipt files now use the normal application UI and Arabic system message, while authentication, role checks, Supervisor Sponsor-scope authorization, and valid receipt streaming remain protected.

Code commit: `ddd5e91e6f90935cd3a7e328f480ae1f8d906e34`  
Documentation commit: `cd78fe373c72ce4063ff0b1a9c9a66e59a245961`

The user confirmed the local regression test passed. Do not repeat the old receipt regression test unless new evidence indicates another regression.

## 9. Notification UI/live-behavior checkpoint — 2026-09-15

The shared notification system is now confirmed to behave consistently across applicable dashboards through the common header/footer notification widget.

### Live notifications

- `modules/notifications/poll.php` provides authenticated JSON polling for unread notifications and recent items.
- The shared notification widget polls every 5 seconds, so newly created workflow notifications appear without manual refresh or logout/login.
- Existing real workflow notifications were tested and confirmed, including HR leave approval/rejection and password recovery/change-request flows.
- The notification click flow retains CSRF protection, including dynamically rendered live-polling notification forms.

Relevant commits:

- `ecc82aa6af0a8201adbb6d6de0c7dbb087e77305` — notification polling endpoint.
- `b82bd4effca75ffbde5a2f8e1930f326fc3b9664` — shared live notification widget/polling.
- `2ff8f331e575fab9dce9916c783ad3d7d3e63097` — CSRF token preserved in dynamically rendered notification actions.

### Unread visual indicator

Unread notifications in the shared bell menu show a clear red dot icon next to the notification title, plus the existing unread visual treatment. This behavior is centralized so applicable dashboards use the same indicator.

Commit: `c9b151b960b962c6cddbb1b135ceef6ddd5b4e16`.

### Full notification history

A full notifications page was added at `modules/notifications/index.php`, with history, unread count, mark-all-read, and navigation to notification destinations. The bell menu also provides `عرض الكل`.

Commits:

- `01f161ac59c97e033626ba5c447e7793fe52da4c` — full notifications page.
- `0e830c3851eb2efd83f27881ff0b6c998f2d27ca` — bell `عرض الكل` link.

### Clear-all behavior — menu only, never destructive

The user explicitly confirmed that **مسح الكل** must clear the bell menu without deleting notification records. The implementation was corrected:

- `modules/notifications/clear_all.php` no longer executes `DELETE FROM notifications`.
- It records a browser-local notification-ID cutoff in `ak_notif_menu_cleared_before`.
- `assets/js/notification_unread_indicator.js` hides cleared menu entries and recalculates the visible unread badge after live polling.
- Notification records remain intact and available in the full notification history page for audit/history purposes.

Commits:

- `391474e6ea894812b9b3eba6e636bba238e8c66a` — non-destructive clear-all behavior.
- `ddd9796896c49f4e2aaa150c3182253a6fa42d05` — client-side menu filtering after clear-all.

The user tested this behavior and **confirmed it is correct**.

## 10. Other parked work

- Targeted accounting runtime verification of newly protected automated journal reference types.
- Remaining direct journal mutation callers and cross-module accounting auditability.
- Reliable caller evidence for the legacy generic notification helper.
- Unified organizational assignment/history model and controlled lifecycle transitions for non-supervisor users.
- Same-page Bootstrap modal UX for missing/stale receipt feedback.
- Formal report/source/calculation catalog.
- Production preparation and deployment hardening.

## 11. Production/data status

The application is still under development. Development/test operational data is not to be treated as production financial data.

Production preparation remains governed by `docs/PRODUCTION_PREPARATION.md` and `database/production/prepare_production_database.sql`.

## 12. Internationalization

Arabic is the default language and English is the alternate language. Stable key-based i18n is authoritative.

## 13. Local Git safety

Intentional local backup files that must not be deleted/reset/stashed/overwritten:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

Commit `f7051ded6fe4c8e346cf7bcb08f48d0535945d01` was inspected and does not represent an active rollback of the tracked Accountant Staff dashboard.

## 14. Documentation map

- **`docs/AHL_EL_KHEIR_MASTER_AUDIT.md`** — **SINGLE MASTER AUDIT**: all detailed system review, Accounting, Notification, organizational lifecycle, HR navigation, completed evidence, open audit findings, and continuation rules.
- **This file** — high-level START HERE status and continuation summary.
- `CHATGPT_SESSION_INDEX.md` — short continuation index.
- `I18N.md` — internationalization reference.
- `PRODUCTION_PREPARATION.md` — production preparation procedure.

There should be no second detailed audit/checkpoint file for the same project-wide audit history.

## 15. Continuation rule

Every meaningful milestone ends with:

`Code correct → behavior verified → documentation updated → exact next continuation point recorded`.

A new chat/session must continue from this master status and the single master audit rather than restarting the project or searching through obsolete audit files.

## 16. Latest checkpoint — 2026-09-15

- Active phase: **Supervisor ↔ Accounting integration review**.
- Supervisor payment-method persistence defect: **FIXED / RUNTIME VERIFIED**.
- Fresh bank-transfer test `SP-000010` / transaction ID `28`: **PASS**.
- Bank posting: account `1200` debit `10,000.00`; sponsorship revenue account `4100` credit `10,000.00`; journal `JE-000029` / ID `52` posted and balanced.
- Bank balance increased exactly `10,000` during FM confirmation (`24,786,000` → `24,796,000`).
- Administrative-fee check for this transaction: **PASS** — `admin_fee_method = none`, `admin_fee_amount = 0`, `net_amount = 10,000`, `admin_fee_policy_id = NULL`, and no `4200` line. No historical fee/method inference or manual correction is permitted.
- **Fresh mobile-wallet test: PASS.** E-wallet balance increased from `25,050,000` before FM confirmation to `25,060,000` after FM confirmation, exactly `10,000`. This confirms the Supervisor-selected `mobile` payment method reaches Electronic Wallet account `1300` through FM confirmation/accounting posting.
- **FM treasury calculation: PASS / CLOSED.** The FM dashboard reads live posted-ledger balances for `1100`, `1200`, and `1300`; runtime values were Cash `48,721,100`, Bank `24,796,000`, E-wallet `25,060,000`, Total Treasury `98,577,100`, and the displayed total exactly equals their sum. The reconciliation flow figures are a separate report and are not the current treasury balance.
- Do not rerun closed bank-transfer, mobile-wallet, admin-fee `none`, FM treasury calculation, notification, receipt, Supervisor ownership/scope, or completed Accounting tests unless genuine regression evidence appears.
- **Next audit work:** continue with the remaining Supervisor ↔ Accounting integration controls, starting with Supervisor payment-history scope and direct/indirect exposure of Accounting-only journal/ledger/approval/posting controls. Inspect repository code before creating any new test data or SQL.


## 17. Dashboard / Navigation Review — 2026-09-18

The current active development phase is dashboard UX and role-safe navigation. Accounting audit work is intentionally parked.

### Universal Reports architecture

The system now uses a universal Reports Dashboard at modules/reports/index.php, backed by modules/reports/report_registry.php. Sidebar navigation exposes one Reports entry rather than a report-specific dropdown, while direct report URLs enforce the same centralized authorization.

Management reporting must remain distinct from workflow authority:
- GM/VGM require broad organizational report visibility for management decisions.
- FM retains financial workflow/control authority.
- Removing FM workflow access from VGM must not remove VGM's management reporting access.

### Administration / Staff / Social Media dashboard

dashboard/staff_dashboard.php was developed from the former placeholder into an operational Administration/Staff/Social Media dashboard.

The user runtime-tested the current Administration dashboard and confirmed it loads without errors.

The dashboard currently includes:
- new sponsor requests;
- contacted sponsor requests;
- open winback follow-ups;
- families without an active sponsorship;
- latest sponsor requests;
- role-relevant quick actions.

The Administration sidebar now includes Dashboard, sponsor requests, orphan forms, and Winback follow-ups.

modules/sponsors/requests.php now authorizes Administration and Staff in addition to its previously authorized roles.

### Verified schema used by the dashboard

The live database inspection established:
- sponsorships.child_id → family_children.id;
- family_children.family_id → the family record;
- sponsorships.family_id does not exist;
- children table does not exist.

All current dashboard/Winback family-sponsorship queries must follow the verified sponsorships → family_children → families relationship.

### Recent commits

- 11c978de7da1f90166c0aad9677f69989be2d105 — Administration dashboard development.
- ba934c6cce0bf17eced9f9d1769a2a5908df0915 — sponsor request role authorization.
- 754a53b467e8e027543c1e089ae5e74586b10e — Administration Winback sidebar link.
- 8a2dabea8b7a7a6854c2a08ecad2c9d106e36a86 — dashboard sponsorship-family query correction.
- 0d26093b53864b205ee42e4d91fe04cc7dfaee2c — Winback sponsorship-family query correction.
- 1257b54f8718c5c4546f6006191f51921518836f — Administration dashboard role/link alignment.

### Open dashboard work

This phase is in progress, not closed.

Continue with:
- Administration dashboard UX review;
- KPI/business meaning review;
- sponsor-request table/action review;
- Winback integration;
- orphan-form navigation;
- role-specific differences for Administration/Staff/Social Media;
- Reports visibility according to actual registry authorization;
- Arabic labels/encoding;
- responsive layout;
- remaining runtime/schema issues.

Do not weaken authorization simply to make a link work. Do not invent schema. Do not repeat the completed sponsorship-family schema investigation.


### Winback dashboard/list UX refinement — 2026-09-18

The Administration dashboard Winback KPI links now use distinct anchors: open follow-ups use `#open-cases`, while uncovered families use `#uncovered`. In `modules/administration/winback.php`, the `أسر متاحة للتكليف حالياً` list was moved below `الكفلاء المتوقفون المؤهلون`, expanded to the full-width section, and its table header is sticky within a scrollable list area. The obsolete `القائمة الكاملة` self-link was removed because it only refreshed the same page. Relevant commits: `bbe945f10114e174081fc8e8f584bcac5633c704`, `8ddd2d6fb5b2e0c393e36100e4c3e964bfd36583`, `0bbb8ce2e39c508f6ec208a2029498fbddf24919`.


### Winback KPI destination and list UX refinement — 2026-09-18

The two Administration dashboard Winback-related KPIs now have distinct destinations. `متابعات استرجاع مفتوحة` continues to the Winback open-cases section, while `أسر بلا كفالة نشطة` opens the dedicated `modules/administration/available_families.php` page. The Winback available-family list remains below the eligible stopped-sponsor list, with a sticky table header, centered `الاحتياج الشهري`, and a new `إجراء` column linking each family to its family view. The dedicated page provides the complete available-family list with the same verified sponsorship-family relationship.


## 18. Administration sponsorship-information boundary — 2026-09-18

Administration sponsorship/Winback work now uses a purpose-built sponsorship-safe family profile instead of granting Administration unrestricted access to the internal family case page.

New page:
- `modules/administration/sponsorship_family_view.php`

Authorized roles:
- admin
- general_manager
- vice_general_manager
- administration

The page intentionally exposes sponsorship-decision information already present in the existing family/orphan data model, including child health/psychological/education state, critical status, current sponsorship value, extra allowance, and latest prior sponsorship amount/status/start date. This supports informed sponsor acceptance, especially for Winback cases where the new proposed sponsorship may differ from a sponsor's previous sponsorship.

The page intentionally excludes private/internal case data such as direct family phones, exact address, registration number, bank accounts, documents, and internal notes.

Both `modules/administration/available_families.php` and the available-family list in `modules/administration/winback.php` now route to this controlled profile. Administration was removed from the unrestricted `modules/families/view.php` authorization after the controlled profile was added.

The user runtime-tested the controlled profile flow after pull; no authorization bypass was introduced.


## Winback declined-case reopening — 2026-09-18

A closed Winback case with status `declined` can now be reopened when a sponsor later changes their decision. The system reuses the original `winback_campaigns` row, changes its status back to `open`, clears `closed_at`, assigns the current handler, and records an `REOPEN` audit event. No duplicate campaign is created.

The action is available from the declined case detail and the Winback history list. The user runtime-tested the declined-case reopening workflow and confirmed the test passed.


### GM executive dashboard redesign — 2026-09-18

The General Manager dashboard was redesigned as an executive decision surface rather than a second operational navigation hub.

Changes:
- replaced the dense KPI/chart dashboard with a focused executive snapshot;
- retained high-value organizational KPIs and clearly defined the financial KPI as posted transaction totals;
- changed family coverage to use active + pending families as the population, avoiding the previous denominator mismatch;
- added a management attention panel for uncovered families, paused sponsorships, pending families, open Winback cases, unassigned supervisor letters, and open sponsor requests;
- removed low-value operational charts (letter distribution, supervisor collection ranking, medical-needs overlap) from the GM dashboard;
- retained only the collection trend, family-status, and sponsorship-status views as strategic trend/context visuals;
- added direct executive drill-down cards into the centralized financial, sponsorship, operational, HR, and reconciliation reports;
- centralized report visibility is respected through the existing report registry;
- simplified the GM sidebar to Dashboard, Organization Projects, and Reports. Accounting workflow pages and specialized operational pages are no longer repeated in the GM primary navigation; management access remains available through the dashboard and authorized Reports Center;
- no accounting workflow authority was granted to the GM by this redesign.

Commits:
- `df5132b6f6d07a4ea17acd07aa2cde6c990cd6d6` — redesign GM dashboard for executive UX.
- `160fad609b953578b4a6660ece0eaae3a1d04f01` — simplify GM sidebar to executive navigation.
- `[follow-up commit]` — load centralized report authorization on the GM dashboard.

Runtime verification after pull is the next required step. Do not add SQL or schema changes for this dashboard work unless runtime evidence identifies a real data-definition issue.
