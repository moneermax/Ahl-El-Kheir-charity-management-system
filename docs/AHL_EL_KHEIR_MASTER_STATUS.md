# Ahl El Kheir Charity Management System — Master Status

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-21

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


### Cross-dashboard legal-age alert and notifications cleanup — 2026-09-18

Two common UX issues were corrected:
- The legal-age alert now uses browser `localStorage` rather than tab-scoped `sessionStorage`, so once the user closes the alert it does not automatically reappear after refresh or in another browser tab. The existing manual quick-action button remains available when the alert exists.
- The Notifications Center `حذف الكل` action now actually deletes notification records belonging to the current user only. It does not delete another user's notifications.

Runtime verification is required after pull.

Messaging send/reply is currently under investigation. The repository code shows both actions pass through `config/messaging.php` and the same POST endpoint in `modules/messages/index.php`; no messaging schema change will be invented until the actual runtime failure is identified.


## Cross-dashboard legal-age alert and notification menu refinement — 2026-09-18

- Legal-age alert quick action now remains available in the header after the modal is closed; the automatic modal remains suppressed across refreshes and additional tabs using a user-specific localStorage key.
- Full Notifications page retains the permanent **حذف الكل** action for the current user's notification history.
- Header notification dropdown no longer exposes a destructive delete-all action. Its **مسح** action only clears the dropdown display/interaction and does not delete notification records; users can use **عرض الكل** for the full notification history and permanent deletion.
- Internal messaging send/reply remains unresolved. No speculative messaging schema or database changes were made; runtime failure evidence is still required before changing the messaging persistence path.


## Internal messaging UX redesign — 2026-09-18

The internal messaging runtime was re-verified during the dashboard review:
- Direct send, receive, and reply are working correctly.
- The previously suspected legacy GM/Admin thread was inspected; message 26 belongs to root message 6 and the thread contains successful replies, including new replies created on 2026-09-18.
- The apparent failure was therefore identified as a conversation-view usability problem rather than a messaging persistence/send defect.

The Messages page was redesigned toward a professional Gmail/Outlook-style mail experience:
- stronger email-style visual hierarchy and contrast
- clearer message-list rows with sender, subject, preview, timestamp, unread state, and selection styling
- right-side reading pane on desktop instead of the previous centered modal presentation
- clearer conversation root and reply cards
- substantially clearer attachment presentation
- inline message-list search across sender, subject, and preview text
- existing send, receive, reply, attachment, read-state, broadcast, and font-size controls remain intact
- no messaging database/schema changes were introduced

Implementation commit:
- `285d390c00f983b82e8bb59248a555b7997cfd3e` — Redesign internal messages as a professional email inbox

Runtime verification after pulling this commit is required before considering the messaging UX redesign complete.


### Messaging visual redesign correction — 2026-09-18

The first messaging visual pass was rejected during runtime review because the conversation remained visually too close to the application background and the reading pane conflicted with the global application chrome. The design was revised again based on the runtime screenshot.

The current direction is a true enterprise-mail workspace:
- three-pane desktop layout: mailbox navigation, message list, and reading/conversation pane
- no full-screen floating conversation overlay on desktop
- stronger white/soft-gray surfaces and borders for clear message separation
- restrained enterprise blue as the interaction/accent color
- clear selected/unread states
- reading pane header, conversation count, root message, separated replies, and anchored reply composer
- attachment areas remain visibly bounded
- responsive behavior switches to an overlaid reading pane only on narrower screens
- no messaging send/receive/reply persistence changes

Implementation commits:
- `a454b912d8f992ec78ee8ae32f47b95af1d2b228`
- `c969c49ab6272eb44f25d1279ec98331f62bda20`

The current implementation requires runtime review before being called final.

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


## 10. Audit Log Retention / Cleanup — COMPLETE AT CURRENT BOUNDARY

The administrator audit-log page now provides controlled retention cleanup at `modules/logs/audit.php`.

Verified implementation boundary:
- deletion is restricted to `admin`;
- General Manager remains read-only;
- cleanup uses an explicit inclusive start/end date range;
- both dates are required and validated server-side;
- the start date cannot exceed the end date;
- a confirmation checkbox and final browser confirmation are required;
- matching rows are counted before deletion;
- deletion is followed by a remaining-row verification within the same range;
- the UI reports the actual pre-delete count instead of relying on PDO DELETE row-count behavior;
- large `old_values` / `new_values` JSON payloads are collapsed behind **عرض التفاصيل** and remain available as audit evidence;
- no SQL-debug/query panel was removed because the displayed JSON is audit evidence, not SQL.

Relevant implementation/documentation history:
- `88c721d2da4c1b27004531e9368b2f5cb124c8e3` — initial admin cleanup control
- `42f21d8ee7683057ce473f3c0b42b31cd5777a55` — hardened confirmation/verification
- `13c849f7cc77d7445f2e372da9e7418850d87682` — accurate cleanup count reporting
- `354a149b75bd8a26716358bab24adb8379dff998` — explicit date-range UX
- `4b2bdcb75faf0dc6c004c48c14fb9e0d44ac4b46` — final syntax-error correction

Runtime status: **PASS / CLOSED at this boundary**. The user confirmed the page opens and works after the final fix.

Do not repeat the cleanup tests or alter the retention behavior unless new regression evidence or an explicit new retention requirement appears.


## ORPHAN PROFILE — ADDITIONAL SPONSORSHIP UX — 2026-09-19

A targeted sponsorship UX improvement was implemented at `modules/families/orphan_profile.php`.

Commit: `b0ba3f92d1ee9ea542936f9ce8b381c03f964b45`.

An orphan may now have multiple sponsorship records, consistent with the actual `sponsorships.child_id` schema and the absence of a uniqueness constraint on that relationship. The orphan profile now lists all sponsorships and provides an inline **إضافة كفيل آخر** modal for authorized roles. The modal keeps the orphan context, captures sponsor/monthly amount/start date/notes, applies the existing supervisor sponsor-scope rule server-side, excludes already active/paused sponsors for that orphan, and writes the normal sponsorship audit record.

This is a workflow simplification only; it does not weaken sponsor authorization or replace the existing sponsorship editing workflow.

**Runtime verification pending after pull.**

### Additional Sponsorship — Searchable Sponsor Selector — 2026-09-19

The direct additional-sponsorship modal now uses a searchable sponsor selector instead of rendering the full sponsor list as a native dropdown. Search matches sponsor name and sponsor code. Only sponsors already present in the server-generated eligible list are searchable, so existing authorization, supervisor scope, and active/paused duplicate exclusion remain enforced.

Implementation commit: `95089efedd84f05c1f8753ca1aabfd9bff1f1711`.

Runtime verification pending after pull.


## 18. Additional Sponsorship Search — 2026-09-19

The additional-sponsorship workflow in `modules/families/orphan_profile.php` now uses a dedicated authorized autocomplete endpoint at `modules/families/sponsor_search.php`.

Latest implementation commit: `46c3b0213a2e769f07158214d87575c39ebaade5` — Improve multi-word sponsor autocomplete matching.

The search behavior was refined so multi-word Arabic sponsor names narrow progressively by name-word position instead of applying one broad contains match to the entire typed phrase. For example, `أمل` matches the first name word, `أمل ب` narrows the second word to a prefix beginning with `ب`, and additional characters continue narrowing the same sequence. Sponsor-code searches retain partial/contains matching.

The endpoint continues to enforce:
- authenticated authorized roles;
- Supervisor Letter + Gender responsibility scope;
- final `supervisorCanAccessSponsor()` authorization;
- active sponsor status;
- exclusion of sponsors already active/paused for the same orphan;
- submitted sponsor ID rather than free-text sponsor name.

Runtime evidence on 2026-09-19 confirmed the underlying additional-sponsorship submission works for orphan child `234`: sponsor **ابراهيم تاج السر ابراهيم**, code `IMP-SP-002363`, was added successfully with an active sponsorship of `2,000.00 ج.س` starting `2026-09-19`. Existing sponsorship **مؤيد محمد احمد محمد** remained active and intact.

The sponsorship creation result is confirmed. The multi-word autocomplete behavior itself must only be marked runtime-verified after the dedicated search keystroke tests are explicitly observed; do not infer search success from successful form submission.

## 19. Immediate Next Task — Administration/Staff/Social Media Dashboard

The next active work remains the open dashboard UX/functional review of `dashboard/staff_dashboard.php` and its linked Administration/Staff/Social Media workflows.

Start by inspecting the current repository code and actual linked-page authorization. Review KPI meaning, sponsor-request table/action usability, Winback integration, orphan-form navigation, role differences, Reports visibility, Arabic labels/encoding, responsive behavior, and remaining runtime errors. Preserve all completed schema and authorization fixes.


### Administration dashboard review — first linked-page finding — 2026-09-19

Repository inspection of `modules/administration/winback.php` found request-time schema mutation code that contradicted the documented runtime-DDL cleanup rule. The page was dropping legacy blacklist triggers/table and creating `winback_campaigns` / `winback_contacts` on normal page requests.

This has been corrected narrowly:
- `modules/administration/winback.php` no longer creates, drops, or alters schema during a web request.
- Explicit migration added: `database/migrations/2026-09-19_winback_schema.sql`.
- Implementation commits: `67576f02e2642ec018d5aeb053f6db75dae925bb` and `6ff62b70528d7a10738a73e90250be1b937ba7e2`.

**Runtime status:** code correction is committed; local migration application and Winback runtime verification are still pending. Do not mark the Administration dashboard review complete until the migration is applied and the linked Winback workflow is tested.

### Administration dashboard review — Staff orphan-forms authorization correction — 2026-09-19

A linked-page authorization mismatch was found and fixed. `dashboard/staff_dashboard.php` intentionally exposes **استمارات الأيتام** to Administration, Staff, and Social Media, but `modules/families/orphan_forms_index.php` allowed Administration and Social Media while omitting `staff`. The Staff dashboard link therefore did not have matching server-side page authorization.

Fixed narrowly by adding `staff` to the existing `$viewRoles` list in `modules/families/orphan_forms_index.php`.

Commit: `223c462528cc0e69d62bcf5f76094cea6ce821e2`.

No edit or financial-profile privileges were added to Staff; the existing `$canEdit` and `$canSeeFinancial` rules remain unchanged.

### Administration sponsorship-family header visual fix — 2026-09-19

The orphan/child section header on `modules/administration/sponsorship_family_view.php` now uses the same actual application header background (`--navy`) directly on the page. The earlier shared-CSS attempt had no effect because `includes/header.php` does not load `assets/css/style.css`.

Commit: `2e4673a9a432e80ffb9cdccddadb4cbffd08b450`.

The user confirmed the visual result is correct.

### Winback POST-action authorization hardening — 2026-09-19

A security audit of `modules/administration/winback.php` found that POST handlers trusted submitted IDs without independently enforcing the workflow state/eligibility represented by the page.

Fixed narrowly:
- `open_case` now re-checks the same inactive/suspended/cancelled + 90-day lapsed + no-open-follow-up eligibility used by the displayed queue before creating a campaign.
- `add_contact` now requires the campaign to be `open` or `contacted` before inserting a contact.
- `mark_declined` now requires the campaign to be `open` or `contacted` before closing it.

Commit: `776da4470010d27c8758fd116843d3d06f9c94b9`.

Runtime verification is pending. No schema changes were introduced.


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


## Administration dashboard UX review — 2026-09-19

The Administration/Staff/Social Media dashboard review continued into Arabic/encoding and responsive UX. Source inspection found no current UTF-8/Arabic encoding defect in the inspected dashboard/sidebar/sponsor-request language files. The dashboard request table already uses `table-responsive` and the KPI cards use responsive Bootstrap grid classes.

A concrete shared-header mobile responsiveness issue was found and fixed: `includes/header.php` now stacks the top quick-action/user-control bar on screens up to 768px instead of keeping the desktop row layout. Commit: `4536623afcea14852aac5d1ea1d3b2ec2f70e0b8`.

Runtime verification is pending user confirmation.


## Mobile navigation UX — 2026-09-19

A genuine responsive defect was identified during mobile testing: the shared 260px sidebar stayed inside the flex layout on narrow screens, leaving too little room for the page and causing the sidebar to cover/consume most of the viewport. The shared layout was corrected to use an off-canvas mobile drawer below 992px, with a header menu button, overlay, Escape-to-close, automatic close after navigation, and full-width main content.

Commits: 726d766f8075b2a55a0dcfd0ccc1cb5ec75c64d1, 0530c69c6e80b320577ac50dcd852ea4633431f6.

Status: CODE IMPLEMENTED; REAL MOBILE RUNTIME VERIFICATION PENDING.


## Mobile navigation UX — final runtime checkpoint — 2026-09-19

The user tested the mobile navigation on a real phone over the local network and confirmed the shared mobile navigation is now substantially improved and usable.

Final behavior:
- Below 992px the sidebar operates as an off-canvas drawer rather than consuming the mobile viewport.
- A dedicated fixed `☰ القائمة` control is visible and opens the drawer.
- The drawer overlay and existing close behaviors remain active.
- The mobile menu control was made independent of Font Awesome rendering and given a fixed, prominent mobile position.
- While the drawer is open, background page scrolling is locked; closing the drawer restores normal scrolling.
- The user considers the current mobile layout acceptable for this audit phase. A more app-like mobile redesign is intentionally deferred to a later system-wide UX phase.

Final relevant commits:
- `726d766f8075b2a55a0dcfd0ccc1cb5ec75c64d1`
- `0530c69c6e80b320577ac50dcd852ea4633431f6`
- `ca88f2aa1d5ac0e41af1861905c4bd67cc5646c9`
- `0c7d51c640519b3814c8e0e6f9220cc41097e530`
- `af03d9dd775930be865c2b03a470665f3dab610b`

No database, schema, authorization, role, or workflow logic changed.

Next direction: defer further mobile-app-style redesign and continue the remaining system-wide changes/audit work. Avoid repeating already-passed mobile navigation, sponsor-request, Winback, orphan-form, and dedicated-queue tests unless regression evidence appears.

## Shared floating sidebar UX — 2026-09-19

The shared sidebar floating navigation and its edge-mounted handler were refined and are temporarily accepted by the user for the current phase.

The shared navigation remains a fixed floating/off-canvas sidebar. The handler is flush with the page edge, uses the shared navy header/sidebar color, has rounded inner corners, and was narrowed to 28px on desktop/mobile. The current implementation is in `includes/header.php`.

Commit: `97f5d394ee0370be436351a4d4696bbf5c0b5a5d` — **Refine sidebar handler width and page-edge alignment**.

The user explicitly considers this **temporary done** rather than a final visual design. Do not spend further work refining the handler unless the user returns to it. No database, schema, authorization, role, or workflow logic changed.

### Immediate continuation

Move to the next system-wide fix requested by the user. Inspect the relevant documentation and current repository code before making changes; do not reopen closed audit areas or repeat passed tests without regression evidence.


## 10. System-wide Back/navigation audit — 2026-09-19

A system-wide navigation audit was started to correct broken/context-losing Back actions and identify contextual pages that need a return action. The scope explicitly excludes the entire `TCPDF/` directory and infrastructure-only files.

Implementation now uses a shared client-side `akGoBack(fallback)` helper in `includes/footer.php`: when a same-origin previous page exists, the browser returns to the actual originating page; otherwise the page uses its explicit safe fallback. This avoids blindly sending users to a generic index while retaining a deterministic destination for direct access.

The first navigation-fix batch preserves list/search context for Families, Sponsors, Sponsorships, Projects, returned Transactions, child/orphan navigation, Search Center results, and Accounting Disbursements. Existing Sponsor return handling was also corrected to retain `link_status`.

No database schema, authorization rules, business workflow, or sidebar behavior was changed by this navigation work.

The audit remains active until the remaining user-facing modules are reviewed and runtime checks confirm the important list → detail → back, filtered/paginated list → detail → back, nested detail → parent, and edit/form → parent flows.


### 2026-09-19 navigation rule clarified and second Back-button batch
The user clarified the acceptance rule for this audit: **every user-facing page must provide a Back button that returns to the originating/previous page, unless the page is a dashboard**. This applies to module list/index pages as well; dashboards and non-HTML endpoints/streams remain excluded. The audit was expanded accordingly.

A second batch added contextual Back actions across remaining HR, Users, Settings/System, Supervisors, Transactions, Reports, Accounting, Families, Sponsors, Sponsorships, Projects, Departments, Search, Notifications, Messages, and Logs pages. akGoBack(fallback) remains the common navigation mechanism so same-origin referrer context is preserved while direct access still has a deterministic fallback. TCPDF remains explicitly out of scope.


### 2026-09-19 — final navigation sweep additions
A further sweep covered remaining user-facing HR integrity and system maintenance pages that render HTML. API/JSON actions, file streams, redirect-only compatibility endpoints, and print-only output remain excluded because they are not navigable HTML pages. The acceptance rule remains: every user-facing HTML page has Back unless it is a dashboard.

## Legal-age alert — Nanny Dashboard — 2026-09-19

The shared legal-age alert was extended to the Nanny Dashboard without creating a second implementation.

- dashboard/nanny_dashboard.php now includes includes/age_alert.php before the shared footer.
- The shared alert applies the Nanny's scope server-side using families.nanny_id = current_user_id().
- The alert therefore never loads legal-age children belonging to another Nanny.
- The existing child suspension workflow in modules/families/suspensions.php was verified: suspending a child sets family_children.is_active = 0 and pauses active sponsorships linked to that child.
- Because the shared legal-age query requires fc.is_active = 1, a suspended child disappears from the legal-age popup after refresh on all dashboards using the shared alert (Nanny, GM, VGM, and Admin where applicable).
- The Nanny Dashboard's existing separate near-legal-age card also uses fc.is_active = 1, so the suspended child is removed there as well.
- The popup now shows once per authenticated PHP session using sessionStorage keyed by a hash of the PHP session ID, rather than persisting indefinitely in localStorage. Bootstrap initialization is immediate with a bounded retry loop because the alert is rendered near the bottom of the page.

Implementation commits:
- 474741e4f094b264e033af89f690838765c7e4a5 — Add legal age alert to nanny dashboard.
- 44e873fa548aa15142a4d3548437bae8371d1c6d — Improve legal age alert popup initialization.
- ab489dfa5ab5c00d52b76d3e0a8a97be2d2e6cd8 — Make legal age alert show once per login session.

Temporary legal-age test DOBs were restored to their original values after verification. No schema change was made. The feature is COMPLETE / CLOSED at the current boundary; do not reopen unless regression evidence appears.


## 2026-09-19 — Second Back button at top-left

The existing contextual Back-button implementation remains unchanged. A shared enhancement was added in `includes/footer.php`: any user-facing HTML page that already contains the audited contextual Back button now receives a second cloned Back button at the top-left of the page content. Both buttons use the same existing `akGoBack(fallback)` behavior, so originating-page context and deterministic fallback remain identical. Dashboards and excluded non-HTML/stream/print endpoints are unaffected. The existing bottom Back button remains in place.

Commit: `0557218daa28d2ce78ff8e9f0bbaf8bacd2f0769` — Add top-left Back button to pages with contextual Back.


## 2026-09-19 — Back-button coverage correction

The first centralized enhancement only added a top button where an existing contextual Back button was already present. That left some audited HTML pages without either button. The implementation was corrected in `includes/footer.php`: non-dashboard HTML pages now receive both a top-left and bottom Back button when they do not already contain an audited contextual Back control; pages with an existing contextual control receive the top copy while retaining their existing bottom control. The generated controls use the role's existing dashboard route as deterministic fallback and the same `akGoBack()` history/context behavior. Dashboards remain excluded.

Commit: `68d5db9e76add648933b2874fb5f349bdee0e535` — Ensure two Back buttons on all audited HTML pages.


## Shared header action toolbar — 2026-09-19

The shared application header was redesigned so the full header action area is centered directly beneath the organization name banner.

The previous split top-bar arrangement was replaced with a centered action toolbar. The current-user identity now uses a compact dropdown for Profile, Settings, and Logout. Role-aware quick actions, global search, language switching, and pending password-recovery access remain direct controls. Profile, Settings, and Logout are restored inside the current-user dropdown.

The change is presentation/navigation only. Existing destination URLs, role-aware quick-action generation, search permissions, language switching, password-recovery visibility, avatar loading, authentication/logout behavior, and sidebar/back-button logic remain unchanged.

Implementation:
- includes/header.php
- Commit: ca3a30f7c26b3196b6a1a428a98ab5c8a06c2874

The sidebar remains closed by default when a new page loads, and the shared Back buttons remain active. Further visual tuning should wait for runtime review of the new centered header on desktop and narrow screens.

## 2026-09-19 — Shared header action-toolbar redesign

The shared header redesign is now accepted at the current visual boundary. The organization name remains the header anchor, with centered controls directly beneath it using the available horizontal space. Role-aware quick actions and system controls remain directly visible. The current-user identity is a restored compact dropdown containing Profile, Settings, and Logout. The visible group labels **الوصول السريع** and **النظام** were removed; the controls remain grouped structurally without those text labels.

The existing global search, language switching, conditional password-recovery control, role-aware actions, sidebar behavior, and system-wide Back controls remain unchanged. Sidebar remains closed by default and must not be disturbed unless a regression is reported.

Code commits:
- ca3a30f7c26b3196b6a1a428a98ab5c8a06c2874 — initial centered header action toolbar.
- 894664fac80374f8d86c7192d8962a099b557719 — restore current-user dropdown.
- 2729522546929b09488ac244857d388ff8d51b1a — remove the visible action-group labels.

Runtime checkpoint: user confirmed the final header appearance is acceptable after removing the two labels. No database/schema, authorization, workflow, sidebar, or Back-navigation behavior was changed.

Next continuation point: proceed with the next system-wide/dashboard change requested by the user. Inspect the current documentation and repository state first; do not reopen completed audits or repeat passed tests without regression evidence.


## 2026-09-20 — Shared header role-action regression fix

A regression was found in the accepted shared header redesign: the shared role-aware quick-action map in `includes/header.php` had been reduced to an empty map, so roles such as Financial Manager lost their role-specific header actions even though the header layout itself remained visible. In addition, `dashboard/supervisor_dashboard.php` and `dashboard/hr_dashboard.php` still contained local CSS overrides that forcibly hid the shared action container.

The shared implementation is now corrected:
- role-aware header actions are restored centrally in `includes/header.php` for the currently supported roles and aligned with current role navigation/authorization rather than reviving obsolete links;
- FM header actions now include the current journal, projects, opening balance, monthly disbursements, assigned nannies, financial review queue, and Reports destinations;
- the Supervisor and HR dashboard-specific `display:none !important` overrides were removed so those dashboards no longer suppress the shared header actions;
- the accepted organization-name anchor, centered controls, user dropdown (Profile / Settings / Logout), Global Search, language switch, conditional password-recovery control, closed-by-default sidebar, and Back implementation remain unchanged.

Commits:
- `f38575e594c52978af79502a9707f9a078ba32be` — restore role-aware shared header actions.
- `0a0946bdbab09c3f2c6aeef0914378c9c153fa35` — keep shared header actions visible on Supervisor dashboard.
- `95d706de3bad5dbf6bc9a0ac248a9fc3c26f45c7` — keep shared header actions visible on HR dashboard.

Static repository verification confirmed the role-action map is no longer empty and no inspected primary dashboard contains a local rule that hides the shared header actions. Runtime verification is required after pulling current `main`, starting with FM and then spot-checking another dashboard role.


## 2026-09-20 — Back-button consistency corrective audit (new active phase)

The previously accepted two-button Back layout is now reopened for a **corrective consistency audit** because runtime review has identified inconsistent results across user-facing pages:

- some pages show only one Back button;
- some pages show the Back button twice;
- some pages have no Back button;
- some Back implementations can leave the page in a broken/stuck state where the shared sidebar will not open.

The required target for this phase is explicit: **every user-facing HTML page must have exactly two Back buttons — one at the top-left of the page content and one at the bottom-right — unless the page is a dashboard or another previously excluded non-page endpoint.** Both controls must use one safe, shared navigation mechanism and must not interfere with sidebar/header JavaScript.

### Work plan
1. **Freeze and verify the repository checkpoint** before touching Back-button code.
2. **Audit one module at a time**, starting with the module selected by the user.
3. For every page in that module, record top-left presence, bottom-right presence, destination/context behavior, sidebar/header behavior after Back, and preservation of pagination/search/filter/return context where applicable.
4. **Trace the actual implementation before changing it**: page-local Back markup, shared footer, akGoBack(), scripts, and any module-specific navigation code.
5. **Fix the smallest responsible layer**. Do not add another global Back generator when an existing page-local/shared control can be corrected safely.
6. Keep **one navigation implementation** for the behavior; do not create competing Back handlers.
7. Runtime-test the repaired page, including opening the sidebar, navigating Back, returning to the originating page, and refreshing.
8. Only after the module passes, commit it and move to the next module.
9. At the end of the system-wide audit, update the documentation and close only the verified scope.

### Safety rules for this phase
- Do not use destructive Git commands.
- Do not alter database/schema/business logic/authorization unless a separate defect proves it is required.
- Do not redesign the sidebar/header while fixing Back buttons.
- Do not assume a missing/duplicate button is caused by the shared footer; inspect the page first.
- Do not declare a Back fix complete until runtime behavior is verified.
- TCPDF, API/JSON endpoints, file streams, redirect-only compatibility endpoints, print-only output, and dashboards remain excluded unless the user explicitly changes the scope.

This phase supersedes the earlier documentation statement that the Back-button audit was fully closed; the earlier implementation remains the baseline to inspect, not a reason to assume current runtime consistency.


## 2026-09-21 — Claude/i18n and dashboard visual consistency checkpoint

Repository review of the changes added after the 2026-09-20 dashboard spacing work found six commits on `main` after checkpoint `642f7397f48c8ddb84315130ae32cf22847476b1`:

- `42a107dd871b36bfaa165df3115895b6cafbf788` — expanded i18n compatibility handling with Arabic→English bridges, punctuation-tolerant lookup, native dialog translation, and the i18n gap-report tooling.
- `f4a44d6b32c628366e41b4c3fd077e51bb8236d2` — translated Arabic text that mixes fixed UI text with live values through dedicated patterns.
- `e78b5c7ba4406b10abbbad125770bc0c0963e31e` — added a role-aware Home shortcut icon to the shared header, hidden on dashboard pages.
- `9e815399232c9b36813d2e0ceb495aa0b5617698` — unified dashboard section titles as blue bars with white text and marked dashboard pages with `body.ak-dashboard`.
- `681134433e52ef14f87ce98724a25d94ac9f57d7` — extended the blue-bar/white-text section-title treatment to applicable white/light card headers across non-dashboard pages.
- `4994389906d29cb59eb71120a8925b34dab737ee` — removed the obsolete `sudo_dashboard.php` page.

The current i18n implementation also includes `lang/bridge/ar_to_en.php`, `ar_to_en_js.php`, `ar_to_en_patterns.php`, `en_to_ar.php`, common-fix dictionaries, punctuation-aware lookup in `config/lang.php` and `assets/js/language.js`, and `tools/i18n_gap.php`. `docs/I18N.md` was already updated by this work and remains the detailed i18n reference.

No database/schema change is represented by these six commits. The changes are application/UI/i18n/documentation-related. Runtime status for the newly added behavior should be recorded only after local testing; repository inspection alone does not constitute runtime verification.

## 2026-09-21 — Permanent hosting database compatibility rule

A permanent hosting-compatibility rule is now in force: **no MySQL/MariaDB triggers or views may ever be added to the database again**. Business rules formerly enforced by triggers must be enforced in procedural PHP workflows, and view-style reporting must use the underlying tables directly. Stored procedures/functions/events are also excluded from the hosting-compatible architecture unless the rule is explicitly revised.

The current repository scan found no `CREATE TRIGGER` or `CREATE VIEW` statements in the application SQL/PHP or current database export. The previously observed InfinityFree `attendance` trigger import failure came from the earlier export and must not be reintroduced.


## 2026-09-21 — Projects Module Deep Audit

The Organization Projects module has entered a dedicated deep audit. The first static pass identified two high-priority integrity findings and several medium-priority validation, transaction-boundary, authorization, and read-side-effect items. No Projects code or database schema has been changed yet. The detailed execution plan is `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md`. Runtime certification has not started.
