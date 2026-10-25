# Documentation Authority Notice — 2026-10-03

The canonical system description is now maintained in:
- docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md
- docs/AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
- docs/AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
- docs/AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
- docs/AHL_EL_KHEIR_OPERATIONS_SECURITY.md
- docs/AHL_EL_KHEIR_DOCUMENTATION_INDEX.md

This file remains the master status/audit history. Its dated evidence is retained; it must not be interpreted as a substitute for the canonical architecture/workflow documents. Later verified evidence supersedes older checkpoint statements.

---

# Ahl El Kheir Charity Management System — Master Status

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-10-06

This is the single high-level **START HERE** status and continuation summary for the existing project. The detailed audit record is consolidated into `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`.

**Current continuation boundary — 2026-10-04:**
- Projects Phase 5 controlled PRJ-0015 reconciliation and financial closure: **RUNTIME VERIFIED / CLOSED**.
- HR Salary Advance Stages 1–6: **DONE / RUNTIME VERIFIED / CLOSED at their documented acceptance boundary**.
- Fina core settlement acceptance: **COMPLETE at its documented boundary**.
- Current repository branch boundary: **main only**.


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


## Projects remediation update — 2026-09-21
- Atomic new-project creation added in `modules/projects/form.php`.
- Portfolio funding totals aligned with the authoritative posted-allocation exclusion rule.
- Project detail GET no longer mutates lifecycle/closure totals; closure synchronization remains in the closure POST workflow.
- Added server-side validation for project team sections, labor enumerations, progress range, beneficiary inputs, document rejection reason, and a 10 MB project-document limit.
- Runtime certification remains pending; project-code uniqueness/concurrency still requires verification against the actual deployed schema.
- Remediation commits: `251c7c4`, `5bb36f8`, `af59a1b`.

## 2026-09-22 — Projects Module Workflow / Role Audit accepted as working plan

The Projects Module audit has been expanded from isolated remediation items into a **complete workflow and role audit**.

The authoritative working boundaries are now:
- **Project Supervisor:** operational/project data entry and execution follow-up.
- **Projects Manager:** operational follow-up, workflow management, readiness, and submission to financial review.
- **FM:** financial review/control; approve or reject financially; operational sections must be read-only or concise quick-report summaries from the FM point of view. After final approval, FM may perform authorized project payment/disbursement execution and print payment receipts where required, especially cash payments.
- **GM/VGM:** final approval according to existing authority.
- **Accountant:** accounting execution/posting where applicable.

Every Projects page/action must be classified as pre-approval preparation, approval/financial review, post-approval execution, or management/reporting. The audit must verify server-side authorization, lifecycle guards, frontend visibility/editability, notifications, accounting integration, payment/disbursement behavior, and auditability together.

A key accounting question is explicitly open for verification: whether the final-approval project journal represents an actual payment/expenditure event or a funding/allocation/reservation event. These meanings must not be conflated, and later expense/payment posting must not duplicate the same economic event.

**Current status:** working plan accepted; next step is a repository-wide static Projects workflow/role audit before further implementation. Do not make speculative authorization changes before the audit produces the role matrix and section-visibility matrix.


## 2026-09-22 — Projects workflow audit checkpoint

Projects remediation Batch 1 is now implemented: pre-approval budget/budget-line preparation and funding allocation preparation are server-authorized to the Projects Manager, while the FM pre-approval funding UI is read-only and reserved for financial review/approve/reject. Runtime verification remains pending. See `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md` for the detailed change and remaining accounting/payment audit items.



## 2026-09-22 — Projects UI reference implementation

The Projects module UI is being aligned with `docs/code_artifact.html` and the functional requirements in `docs/code_artifact.md`, using the existing Bootstrap application framework rather than introducing a second frontend framework.

Implemented:
- scoped shared Projects UI stylesheet;
- redesigned project creation/edit form presentation;
- dynamic project-type guidance and non-financial budget templates;
- consistent styling for project workflow forms, action panels, tables, and project listing.

No schema/database changes were introduced. Runtime visual acceptance is still pending.

Important workflow note: the user clarified that **funding entry is not a Projects Manager task**. The current pre-approval funding ownership is therefore treated as unresolved until the workflow audit establishes the responsible role; no PM funding-entry responsibility should be added.


### Projects — 2026-09-22 operational role clarification
- Project Supervisor owns operational project data entry for documents/receipts/certificates, external labor/helpers, milestones, and progress updates.
- Projects Manager follows up and manages workflow; those operational sections are read-only for the Projects Manager.


## 2026-09-25 — Projects workflow runtime certification checkpoint

The Projects approval workflow has now passed a controlled notification/runtime test through final approval with PRJ-0010 / project ID 10 (اختبار المشاريع 2026). PM submission, FM financial approval, GM final-approval notification, PM final-approval confirmation, and the existing FM post-final-approval notifications were observed successfully.

The final approval lifecycle was corrected: GM/VGM approval no longer activates the project automatically. It leaves the project in planned; the Projects Manager must explicitly launch the project. Launch requires final approval, planned lifecycle, and an active assigned Project Supervisor, then changes the project/lifecycle to active, records status history/audit, and sends the assigned supervisor a launch notification.

Role/lifecycle enforcement now includes: PM general project editing and budget preparation limited to draft/rejected apart from explicit review/launch actions; Project Supervisor direct access blocked before final approval + launch; Project Supervisor dashboard only lists finally approved projects in operational states; PM dashboard includes a dedicated approved-but-not-launched queue.

Runtime dashboard collation issue is resolved. The cause was the mixed collations of other_projects.status and project_lifecycle.lifecycle_status, combined through COALESCE, plus unnormalized status IN comparisons. Dashboard comparisons were normalized explicitly. Final supervisor-dashboard fix: b3c23386fc54d27705df6cc1e3546430935135cd.

Current runtime status: PM dashboard and Project Supervisor dashboard both open successfully. The explicit PM launch → PS notification → PS dashboard appearance sequence remains the next controlled test and has not yet been certified.

No schema changes, triggers, views, stored procedures, functions, or events were introduced by these workflow/dashboard fixes.


## 2026-09-26 — Projects expense/payment workflow completed

The Project Supervisor project-expense/payment workflow is now runtime-tested and accepted on PRJ-0010 / project ID 10.

The permanent model is project-funded: the Project Supervisor records payments against the project budget already transferred/allocated to the supervisor. A payment receipt is required; saving the payment records the expense directly as `posted`, deducts it from the approved project budget, and does not create an organization cash/bank/e-wallet journal entry.

Older expenses created under the former draft workflow remain draft until the user explicitly records the payment and supplies the receipt through the existing **تعديل / تسجيل الدفع** action. No automatic retroactive posting is performed.

The assigned primary Project Supervisor is intentionally allowed to **تعديل** and **حذف** both `draft` and `posted` project-funded expenses. The rule is enforced in both UI visibility and server-side POST authorization.

The temporary standalone finalization page was removed. The obsolete organization-account Projects expense path remains blocked and must not be reintroduced.

Final implementation commit: `5dda62c4bc74662fe0e9c415ddbbed88e9263a94`.

Detailed checkpoint: `docs/PROJECTS_RUNTIME_CHECKPOINT_2026-09-26.md`.

### Current Projects stopping point

PRJ-0010 remains intentionally **open/not closed** because one remaining project task is still outstanding. PM closure must wait until that task is completed and the existing closure guards are satisfied.

Do not reopen or redo the completed project-expense workflow. The next session should continue from the remaining project task.


2026-09-27 — Projects closure and report-page cleanup checkpoint

PRJ-0010 project closure is now **RUNTIME VERIFIED / COMPLETE**. After applying `database/migrations/2026-09-27_project_closure_reason.sql`, PM successfully closed the project and GM/VGM received the expected closure notifications. Closure is no longer a pending runtime item.

Four report pages were also corrected and runtime-reviewed: `modules/reports/hr.php`, `financial.php`, `sponsorship.php`, and `operational.php`. Redundant page-local bottom Back buttons were removed in favor of the shared footer's standardized two-button mechanism, and the welcome-section title/subtitle text was explicitly forced to white for readable contrast. Shared header styling was not changed.

Relevant report commits are recorded in `docs/PROJECTS_RUNTIME_CHECKPOINT_2026-09-26.md`; checkpoint documentation commit: `2e7eb684878d2d45e0180a582ee3ff25bc0dbeff`.

### Current Projects continuation point
The completed closure, expense/payment, and report-page cleanup work must not be reopened without regression evidence. Continue the Projects deep audit from the current repository and `docs/PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md`, selecting the next unresolved post-approval/data-integrity/authorization item only after inspecting current code and schema.

## 2026-09-27 — Pre-HR Salary Advance Backup Checkpoint

The user confirmed that the current project/repository and database backups have been completed successfully before beginning the new HR Salary Advance feature. This is the recovery checkpoint for the new feature work.

No Salary Advance implementation or schema change has been made at this checkpoint. The agreed design direction is policy-driven: an annual FM-configured Salary Advance Policy provides defaults, while the FM may override/customize the policy terms for an individual request. The existing HR/payroll/accounting implementation must be inspected before any schema or code changes are made.


## 2026-09-29 — HR Salary Advance Stage 4 CLOSED

Stage 4 — Accounting Verification & Disbursement is DONE / RUNTIME VERIFIED / CLOSED.

Verified evidence: successful accounting verification and disbursement of SAR-2026-00001 for 10,000 SDG from 1100 — الصندوق; journal JE-000040; reference SAL-ADV-SAR-2026-00001; Dr 1410 / Cr 1100; outstanding balance 10,000 SDG; protected receipt upload; voucher/receipt ownership controls; duplicate-disbursement protection; mandatory receipt-replacement audit; and runtime-verified FM rejection closure for SAR-2026-00005.

The final receipt replacement test succeeded with the protected-storage confirmation. The superseded physical receipt is removed only after the DB replacement and mandatory audit record commit succeeds; on failure, the old receipt remains and the new upload is cleaned up.

No payroll repayment schedule, payroll deduction, direct repayment, or settlement logic was implemented in Stage 4.

**Next planned stage: Stage 5 — Repayment Schedule + Payroll Integration. NOT STARTED.**


# # 2026-09-29 — Salary Advance Processing Workflow Consolidation

The Stage 5 salary-advance UI workflow was consolidated into one processing page on `feature/hr-salary-advance-stage5-schedule`.

Removed redundant processing pages:
- `modules/hr/salary_advance_fm_review.php`
- `modules/hr/salary_advance_processing.php`

Unified page:
- `modules/hr/salary_advance_processing.php`

The page presents FM review, accounting verification, disbursement, and repayment-schedule result in sequence. Voucher/receipt endpoints remain separate evidence/document endpoints.

Dashboard links and employee approval-notification routing were updated. Accounting staff retain authorized access through their dashboard.

No database schema change was introduced by this consolidation.

Runtime verification of the consolidated page is still pending. PR #52 was already merged into main (merge commit 96ecf58871fe27b32aad2c5d62174c40991a5747). The current branch contains a follow-up workflow-consolidation change and is not yet merged.

2026-09-29 — Stage 5 Schedule Generation Runtime Checkpoint

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


## 2026-09-29 — HR Salary Advance Stage 5 Schedule Planning VERIFIED

Stage 5 schedule-planning is now DONE / RUNTIME VERIFIED. The remaining Stage 5 work is payroll integration only.

Verified gates: maximum monthly deduction, maximum repayment months, next_payroll, specified_month, duplicate schedule generation, full_eligible_salary planning, and failure/rollback safety. Runtime evidence includes SAR-2026-00008, SAR-2026-00009, and SAR-2026-00011; rollback-only verification is recorded by tools/run_salary_advance_stage5_schedule_tests.php.

The clean rollback-only harness rerun after commit 9b66315966174a3a892a5760847300007814a0e2 returned all three tests PASS with no CLI warning.

Do not reopen Stages 1–4 and do not start Stage 6. Continue with Stage 5 payroll deduction/application and accounting integration after inspecting existing payroll conventions and schema.


## 2026-09-29 — HR Salary Advance Stage 5 Payroll Integration Checkpoint

Stage 5 schedule planning is runtime verified. Payroll-integration implementation is committed on `feature/hr-salary-advance-stage5-schedule` and awaits controlled runtime verification. It applies scheduled deductions at payroll payment, records repayment allocations, reduces outstanding balance transactionally, updates schedule state, posts Cr 1410 for actual repayments, records skipped/partial outcomes, and writes audit-log evidence. Stage 6 remains NOT STARTED.


## 2026-09-29 — Stage 5 Payroll Integration Merge Checkpoint

The Stage 5 payroll-integration implementation was merged to `main` through PR #56. Merge commit: `7cb7a42a9bb4d90a07d23250f2940473806498c8`.

Status remains **Stage 5 IN PROGRESS / RUNTIME VERIFICATION PENDING**. The code is now on `main`; no Stage 6 behavior has been introduced. The next step is controlled local runtime verification of payroll repayment application, Cr 1410 accounting, balance/schedule updates, insufficient-salary handling, duplicate protection, and audit traceability.


## 2026-09-29 — Stage 5 Repayment Notification Checkpoint

PR #57 was merged to `main` with merge commit `0c01c3a378826b7b7cb260668f8f64b0e6648031`. Employee notifications are now emitted after a committed payroll repayment for applied, partial, skipped, and zero-balance completion outcomes. Notification delivery is informational and cannot roll back the financial transaction.

Stage 5 remains IN PROGRESS pending controlled runtime verification. Stage 6 remains NOT STARTED.


## 2026-09-30 — HR Salary Advance Stage 5 Edge-Case Checkpoint

Stage 5 remains **IN PROGRESS**. Core payroll repayment integration is runtime verified. The rollback-only edge-case harness was merged in PR #67 (merge commit `49ae654a25a8ec2a80901c86fe9aa009da83b96c`).

Runtime result:
- PASS — `available_salary` + fixed monthly: `SAR-2026-00004`, scheduled remaining 5,000 SDG, eligible salary 1 SDG, deduction 1 SDG, outcome `partial`.
- SKIP — `skip_month` + fixed monthly: no suitable existing disbursed fixture.
- SKIP — low-salary `full_eligible_salary`: no suitable existing disbursed fixture.
- PASS — rollback-only cleanup: no payroll/request/schedule/journal mutation committed.

The two SKIPs are fixture-availability gaps, not failures. Do not create a new real employee/request solely to manufacture test data. Stage 6 remains NOT STARTED.


## 2026-09-30 — Stage 5 Final Runtime Closure

**Status: DONE / RUNTIME VERIFIED / CLOSED**

Post-merge verification was completed on `main` at merge commit `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.

Final edge-case harness:
`tools/run_salary_advance_stage5_payroll_edge_tests.php`

Final runtime result:
- **PASS — available_salary + fixed_monthly:** `SAR-2026-00004`; scheduled remaining = 5,000 SDG; eligible salary = 1 SDG; deduction = 1 SDG; outcome = `partial`.
- **PASS — skip_month + fixed_monthly:** `SAR-2026-00007`; scheduled remaining = 10,000 SDG; eligible salary = 1 SDG; deduction = 0 SDG; outcome = `skipped`; rollback-only existing-request policy override.
- **PASS — full_eligible_salary with low eligible salary:** `SAR-2026-00007`; deduction = 1 SDG; outcome = `partial`; rollback-only existing-request method override.
- **PASS — rollback-only cleanup:** no payroll/request/schedule/journal mutation was committed.

These four edge gates passed both before merge and again after the PR was merged to `main`. The two previously unavailable policy branches were covered safely through SAVEPOINT-based rollback-only fixtures; no permanent employee, request, schedule, payroll, or accounting test data was created.

The previously verified Stage 5 core payroll gates remain valid, including payroll repayment application, schedule/balance updates, duplicate protection, balanced Cr 1410 repayment accounting, repayment trace/audit behavior, and repayment notification implementation/runtime coverage. No Stage 6 behavior was introduced.

**Stage 5 is now formally closed.**

Stage 6 — Direct Repayment & Settlement remains **NOT STARTED** and is the next planned work unit. Do not begin it in the same checkpoint unless explicitly proceeding with Stage 6.


## 2026-09-30 — Stage 5 UI / Final Regression Closure

The final Stage 5 processing-page UX cleanup is complete on `main`. Permanent process-guidance text was removed from the FM review, accounting verification, and disbursement panels; workflow progression is communicated through temporary toast feedback after successful actions. Existing business-state warnings and controls remain intact.

Final payroll edge harness was rerun after the UI changes and returned all four required cases as **PASS**:
- `available_salary` + fixed monthly — partial deduction.
- `skip_month` + fixed monthly — skipped deduction.
- `full_eligible_salary` with low eligible salary — partial deduction.
- rollback-only cleanup — no committed payroll/request/schedule/journal mutation.

Repayment-schedule behavior was not changed by the UI cleanup. Existing disbursed salary advances continue to display their generated schedules; schedules are generated atomically during disbursement.

**Stage 5 remains DONE / RUNTIME VERIFIED / CLOSED.** Do not reopen Stage 5 for the removed instructional text or schedule display unless new regression evidence appears.

**Next planned work: Stage 6 — Direct Repayment & Settlement. NOT STARTED.**

## 2026-09-30 — HR Salary Advance Stage 5 Closure / Repository Cleanup

**Stage 5 — Repayment Schedule + Payroll Integration: DONE / RUNTIME VERIFIED / CLOSED.**

Final runtime verification on `main` passed all four Stage 5 payroll edge cases, including:
- `available_salary` insufficient-salary partial repayment.
- `skip_month` insufficient-salary skip behavior.
- `full_eligible_salary` with low eligible salary.
- rollback-only cleanup with no permanent payroll/request/schedule/journal mutation.

The final Stage 5 UI cleanup also removed permanent workflow-instruction text from the unified salary-advance processing page and replaced successful workflow-state feedback with temporary toast notifications. Repayment schedule generation/display logic was not changed.

Stage 5 closure checkpoints:
- Final runtime merge checkpoint: `310f52aaae05cd9a86ce2b69c9ea278c3d1deb7d`.
- Final UI cleanup commits: `6bc8b94fdddff8593a8e9fe716865d302509a933`, `c04adaa1fe4ec27b187d7d080f85a8db0c593506`, `f8ab8272b3eaf352cd02cd59ffafee3a0ee1cfb6`.
- Documentation closure commits: `65cb44479c0218ae28b2c80e6c071eac0f1ddafa`, `0c8671ce8f4338fa90a290d3b9c8da6a7699ccf4`, `450581766bdf783c4cd32bc05dd33287de9fde32`.

### Git repository cleanup

The repository was audited for stale branches after Stage 5 closure. All obsolete remote branches were deleted and all obsolete local-only branches were deleted after reviewing their unique commits. No historical branch was merged back into `main`.

Final repository state:
- local branch: `main` only;
- remote development branch: `origin/main` only;
- `origin/HEAD -> origin/main`;
- working tree clean;
- local `main` synchronized with `origin/main`.

### Next work unit

**Stage 6 — Direct Repayment & Settlement: NOT STARTED.**

Stage 6 must begin in a fresh session. First inspect the current `main` repository, the master documents, the salary-advance continuation document, and the actual schema/code relevant to repayment/settlement. Do not implement Stage 6 behavior before the design/schema audit checkpoint.



## 2026-09-30 — Salary Advance Verification / Disbursement Lifecycle Hardening

**IMPLEMENTED — RUNTIME VERIFICATION PENDING**

Accounting verification can no longer be completed as a standalone state. For requests requiring accounting verification, verification is performed atomically with disbursement. A successful operation therefore reaches disbursed; failure rolls back the transaction.

Legacy approved + verified + not disbursed requests are returned to the accounting action queue as جاهزة للصرف and are excluded from processed history.

No schema change was required.

Current branch: fix/salary-advance-verification-disbursement-atomic
Next gate: runtime verification of SAR-2026-00009, then continue Stage 6 repayment tests.


## 2026-10-01 — Stage 6 Direct Repayment & Settlement CLOSED

**Stage 6 — Direct Repayment & Settlement: DONE / RUNTIME VERIFIED / CLOSED.**

Final clean runtime request: **SAR-2026-00019**, 20,000 SDG, direct repayment.

Verified end-to-end:
- FM approval.
- Atomic accounting verification + disbursement.
- No payroll repayment schedule for direct repayment.
- Partial repayment of 10,000 SDG through 1200 — البنك, JE-000049.
- Remaining 10,000 SDG through 1300 — المحافظ الإلكترونية, JE-000050.
- Correct receiving-account updates and accounting calculations.
- Outstanding balance reached 0.00 SDG.
- Request reached settled.
- Dedicated repayment evidence upload, viewing, and replacement passed for the repayment records.
- Multiple direct repayments against one advance were verified successfully.

Final Stage 6 hardening checkpoints:
- PR #86 merge: `cebd8df397d76738d56a6287b6ba85afba77b6c3`.
- PR #87 merge: `756debc716339470344428812f9684960a272588`.

Do not reopen Stage 6 without genuine regression evidence. The next work unit must be taken from the staged project plan after inspecting the current master documentation; do not invent a new stage.

## 2026-10-01 — Projects Phase 5 explicitly queued after Salary Advance closure

Salary Advance Stage 6 is now closed; do not reopen completed Salary Advance work without genuine regression evidence.

**Projects Module Phase 5 — Accounting Reconciliation / Post-Approval Financial Integrity Audit** is now explicitly **TO DO / NEXT**.

Business intent clarified for the next Projects audit: after the required FM-controlled financial approval, the full approved project budget is intended to come under Project Supervisor control; the organization's treasury is intended to be reduced by that amount and the amount treated as project expense at that financial/disbursement point. If the Project Supervisor saves part of the approved budget, the saved balance must later be reconciled and returned to the organization's accounts through an explicit, auditable financial event.

For now, do not change the Projects accounting implementation. Phase 5 must first reconcile this business intent against the existing chart of accounts, project funding/payment-evidence/expense workflow, and fresh database evidence. Do not invent an account, transfer model, journal type, or schema change. Historical phantom project-approval journals must be corrected only after the replacement accounting model is settled.



## 2026-10-01 — Projects Phase 5 implementation + closure/refund integration

**IMPLEMENTED — RUNTIME VERIFICATION REQUIRED.**

FM approval is the treasury-release event. When the primary Project Supervisor submits project closure, the PS responsibility ends. The system only notifies FM when a controlled unused balance exists; FM alone performs the savings/refund and accounting reconciliation. If the controlled balance is zero, no FM notification is generated. Final project closure remains blocked until any required financial reconciliation reaches zero, and FM notifies the Projects Manager after the refund is processed.

Runtime gate: apply the Phase 5 migration and test the complete FM release → GM approval → PS partial spending → PS closure request → conditional FM refund notification → FM refund (only when savings exist) → PM closure flow on a fresh controlled project. Historical phantom project-approval journal correction remains deferred until this flow passes.


## 2026-10-01 — Projects Approval Notification Timing Reconfirmed

The Projects approval notification timing was explicitly corrected during the current Phase 5 audit.

Authoritative behavior:
- PM submit/resubmit → FM receives submission notification.
- FM preliminary financial approval → no PM notification.
- FM final accounting confirmation / treasury release → GM/VGM receive the final-approval review notification **and Projects Manager receives exactly one notification**.
- GM/VGM final approval → **no additional Projects Manager notification**.
- GM/VGM rejection → FM receives the review/rejection notification; PM is not notified as a terminal rejection.
- FM rejection → PM receives the terminal rejection notification.

Historical documentation had incorrectly recorded PM notification after GM final approval. That statement is superseded. The old GM → PM notification was removed in commit `8524cea415347b6219440455d66860fae142b543`.

Current implementation correction is committed on the Projects Phase 5 audit branch:
`3dcc2acd3bf53cf40e49f78d3b03b0bf3c925100`.

Runtime verification of the corrected timing remains required before closure.


## 2026-10-01 — Projects approval-path audit correction

A deeper Projects workflow audit found a second layer of inconsistency beyond the already-corrected PM notification timing. The dedicated `modules/projects/view_fm.php` is the canonical Financial Manager workflow, but legacy FM POST handlers and duplicate FM UI remained in `modules/projects/view.php`. Because the legacy page supports a `role_view` bypass, those handlers were still directly reachable.

Correction on the Projects audit branch:
- blocked legacy FM approval/review/rejection actions in `view.php` so they cannot mutate workflow state;
- removed duplicate FM approval UI from `view.php`;
- corrected GM final-approval wording so it no longer implies a second journal/release;
- added the terminal Projects Manager notification on canonical FM rejection using `project_fm_rejection`.

The authoritative notification timing remains: PM is notified after FM final accounting confirmation, not after GM final approval. Runtime verification of the complete path is still required before this Projects audit gate is closed.


### Projects Phase 5 — 2026-10-01 continued static hardening
- Main remains the working branch; Projects Phase 5 is implemented but **runtime verification remains required**.
- Controlled-fund return concurrency was hardened in commit `c631ffe571e339fd8a61196ad046f1d7344b964c`: the return transaction now serializes on the project approval row and selected funding allocation before recalculating remaining balances, and validates the return date server-side.
- Documentation commits immediately following the code fix: `21281ca91408c3a0296a4c62f2d80a10ad1a3ff9`, `1d7022d64e85264ec2bf6a380177d37e2b1c2b5c`.


## 2026-10-01 — Projects approval notification gate — static re-audit correction

A deeper static re-audit found one stale notification path that survived the earlier PM timing correction: the shared transaction-review helper still sent project_gm_approval_pm after GM/VGM approval. This conflicted with the authoritative rule that the PM approval-chain notification occurs at FM final accounting confirmation and is not repeated at GM approval.

The stale GM → PM path was removed. Stale PM-notification branches for GM rejection and generic FM rejection were also removed from the shared helper; the canonical FM page now owns FM rejection notification, while GM rejection remains FM-only. akp_audit() is now side-effect free with respect to workflow notifications.

Runtime verification remains the gate for closing this correction.

## 2026-10-01 — Unified User / Employee Provisioning Workflow

**DONE / IMPLEMENTED / DOCUMENTED**

The user-account and employee-profile creation flow has been consolidated into one canonical workflow.

Authoritative design:
- **User = employee account identity**; a newly created user receives an employee profile automatically.
- Admin and HR Manager use the same canonical creation page: `modules/users/index.php`.
- The same backend provisioning function, `hrCreateUserWithEmployee()`, creates the user and linked employee profile transactionally.
- Optional personal fields may initially be empty and can be completed later through the employee/profile workflow.
- The employee relationship is established through `employees.user_id`.
- `modules/hr/employees.php` remains the employee-management/list/edit page, but no longer owns a second account-creation implementation.
- HR's former `?action=add` creation path now routes to the canonical users page.
- The shared creation form remains `modules/users/_create_user_form.php`; it is not a duplicate page and must not be removed.
- The Admin/HR manager-selection dropdown is maintained only in the canonical users page and now includes the established manager/head role/name patterns instead of the previous narrow hard-coded list.

This consolidation also addresses the earlier Projects employee-service inconsistency by ensuring new accounts cannot be created without their linked employee profile.

Relevant implementation commits:
- `b90791e2bfa49f0a6cab26e1844b951c52981d38` — canonical manager selection.
- `ca42000f4552eeeb4fea6fb493bb8008c29a40b9`, `ffed7719f241f7bcee1732da88e743e838296f2c8`, `6b36187460bc27246d9a5e89615f4c8b85cdf2d8` — removal of the duplicate HR account-creation path and obsolete dependency.

No Salary Advance or Leave business logic was changed as part of this consolidation.

## 2026-10-02 — Unified Account Provisioning + Temporary Password First-Login Flow CLOSED

**DONE / IMPLEMENTED / RUNTIME VERIFIED / CLOSED**

The unified Admin/HR user-creation workflow and its temporary-password first-login flow are now runtime verified.

Verified behavior:
- New accounts are created through the canonical user/employee provisioning path.
- The system generates the temporary password server-side; the creator does not enter a password.
- The password is stored only as a password_hash and password_change_required is set to 1.
- The generated temporary password is shown once after successful account creation.
- Login with the temporary password succeeds and forces the employee to the password-change page.
- Successful password change clears password_change_required and normal login then works.
- Credential-entry behavior was hardened so username/password fields remain stable when the surrounding page is displayed in either RTL or LTR direction.

Relevant checkpoints:
- 43f0a0ce846952e8dceaea72325807f7ac5b6c1f — password_change_required migration.
- c3b139cae1d08c67f00313b2bca50c8d20987afe — login credential-direction hardening.
- 745fe81d5c3dcc7773b84377e4d2ff5facc9dd88 — password-change credential-direction hardening.

The password workflow is closed. Do not reopen it without genuine regression evidence.

### Current continuation boundary
The repository remains an existing-project workflow. Continue from current main and the documented project/module checkpoint. Do not resurrect the historical Projects Phase 5 implementation that was later rolled back; use the rollback baseline and current source as truth before future Projects work.


## 2026-10-02 — Projects Phase 5 fresh accounting model implemented / runtime gate pending

The fresh database snapshot commit 3219852ad239b9ee4fc1d8e685b9462ff9ef7a47 confirmed the intended Phase 5 accounting event directly in the live-derived dump:

- other_projects.expense_account_id is the existing project expense-account mapping.
- PRJ-0011 uses 5100-11 — مصروفات مشروع: PH5 Accounting Reconciliation Test.
- Existing test journals JE-PRJ-REL-11-31..., ...32..., ...33... use the model: debit the project's existing expense account and credit the actual treasury source account.
- project_funding_returns already exists in the fresh database snapshot and records the auditable return event.

A fresh implementation was added on main without resurrecting the rolled-back Phase 5 files:

1. Added modules/projects/project_funding_accounting.php with procedural helpers for one-time FM-controlled funding release, payment-evidence synchronization, controlled-balance calculation, FM-only unused-fund return, and safe reversal of an unreconciled FM release before execution.
2. Added database/migrations/2026-10-02_project_funding_reconciliation.sql for project_funding_returns.
3. FM financial approval now performs the accounting release atomically with the approval transition.
4. GM final approval no longer creates a second funding/accounting event.
5. Project closure is blocked while a controlled unused balance remains.
6. A closure request with a non-zero controlled balance notifies FM for reconciliation; when reconciliation reaches zero, PM is notified.
7. Normal accounting post_expense is blocked after project funding has been released, preventing a second treasury reduction for the same controlled project funds.

The implementation is STATICALLY IMPLEMENTED — RUNTIME VERIFICATION REQUIRED. Do not mark Phase 5 closed and do not correct historical project journals until the fresh controlled-project runtime gate passes.


## 2026-10-02 — Projects Phase 5 PM handoff visibility gate corrected

A runtime-driven workflow audit found that the Projects Manager dashboard/list used the generic project-view authorization and therefore exposed a GM-approved project before the Financial Manager had completed the separate final funding-document confirmation. This contradicted the established handoff boundary.

Correction:
- Added the shared helper `akp_project_final_fm_confirmed()`, derived from the existing auditable `FM_CONFIRM_PAYMENT_EVIDENCE` event in `audit_log`.
- Projects Manager project visibility is now denied until that FM final-confirmation event exists.
- The existing FM final confirmation remains the single PM handoff/notification event; no new accounting event or schema state was introduced.
- Project Supervisor access remains governed by the existing approval + explicit launch lifecycle gate.
- PM project launch now uses the same shared FM-final-confirmation helper rather than duplicating the audit-log query.
- GM approval remains organizational approval only and does not notify the PM or create a second accounting release.

Implementation commits:
- `2ec62923ad554e5feb7c2bd261d3267cbdf9ca5e` — PM visibility gate.
- `ad097b53c15d0d2b3d2f8307164e1325b9f44d5f` — shared helper used by PM launch gate.

**Status: STATICALLY CORRECTED — RUNTIME VERIFICATION REQUIRED.**
The PRJ-0012 runtime gate must verify: GM approval alone leaves the project absent from the PM dashboard/detail access; FM final funding-document confirmation then makes it visible and sends the PM handoff notification; no duplicate accounting release is created.


## 2026-10-02 — Projects Phase 5 current documentation checkpoint

The Projects Phase 5 accounting model and PM handoff visibility correction are now documented as the current working boundary.

- Initial FM financial approval remains the accounting release point: Dr the project's existing expense account / Cr the selected treasury source account.
- GM approval is organizational approval only; it does not create another funding/accounting release and does not hand the project to PM/PS for execution.
- FM final funding-document confirmation is the execution handoff milestone and the single PM notification/visibility gate.
- PM project visibility and PM launch now require the auditable FM_CONFIRM_PAYMENT_EVIDENCE event through the shared akp_project_final_fm_confirmed() helper.
- No second accounting event is created by final FM confirmation.
- Historical project journals remain untouched pending completion of the replacement-model runtime gate.

Code checkpoints: 2ec62923ad554e5feb7c2bd261d3267cbdf9ca5e; ad097b53c15d0d2b3d2f8307164e1325b9f44d5f. Previous documentation checkpoint: 3ffbb4ad8598f37f5b1abf3a42d2983e4a20e7c9.

Status: STATICALLY CORRECTED — RUNTIME VERIFICATION STILL REQUIRED.

Required next runtime gate: on isolated project PRJ-0012, verify GM approval alone does not expose the project to PM; complete all funding evidence; perform FM final confirmation; verify the PM notification and dashboard/detail visibility appear only then; verify no duplicate accounting release was created; then continue the remaining Projects Phase 5 reconciliation/closure tests.


## 2026-10-02 — Projects Phase 5 runtime gate: PS notification timing correction
The controlled PRJ-0012 runtime gate exposed one remaining handoff-timing defect after the successful FM final-confirmation path: the assigned Project Supervisor was being notified at FM final funding-document confirmation even though the project remained `planned` and had not yet been launched by the Projects Manager. Repository review confirmed that `modules/projects/view.php` already contains the correct explicit PM `launch_project` gate and PS notification path. The fix removes the premature PS notification from `modules/projects/view_fm.php` and changes the FM success message to identify the PM-only handoff. Resulting code commit: `adecaf6c1e4b12017143d7a2f19bf8354ec1788d`. The intended sequence is now: FM final confirmation → PM notification/visibility → PM explicitly launches project → PS execution notification.



## 2026-10-02 — Projects Phase 5 workflow alignment / accounting integrity repair

A deep cross-page audit was completed against the agreed Projects workflow. The following real implementation gaps were corrected on `main`:

- FM financial approval remains the single project funding/accounting release point.
- GM approval is organizational only and no longer has any competing accounting-release semantics in the GM UI.
- GM rejection now atomically reverses the already-posted FM funding release and returns the project to FM financial review. The original release remains preserved as voided accounting history and a posted reversal is created.
- The funding-reversal helper now supports caller-owned transaction boundaries, preventing nested-transaction commits and making reversal + approval-state transition atomic.
- FM's pre-final-confirmation correction action now reverses funding and resets GM approval atomically.
- The legacy shared-view FM approval/rejection/reversal handlers were removed from `modules/projects/view.php`; FM actions are owned by the dedicated `view_fm.php` workflow.
- Generic project status changes can no longer set `active`. Project activation is available only through the explicit PM `launch_project` action.
- The Projects portfolio UI no longer offers `active` in the generic status selector.
- GM approval wording was corrected so it no longer implies creation of a financial/accounting release.
- Stale shared-view funding-posting wording was corrected to identify FM approval as the release point.

This repair is intended to close the previously identified competing workflow and transaction-atomicity gaps without reopening completed Salary Advance or account/password work.

Code repair commits:
- `9217b81fa71a5b3445ddef0ccbc829eb54fdd9d2`
- `331c3c9bdf144ebc7158538a99b1b67f75722c1d`
- `c93824e07b43c49fb90dff31522ee618220e3ab5`
- `80c6012023e68d513952ca30f175ae6e3ecf25c8`

**Status: WORKFLOW REPAIRED / RUNTIME VERIFICATION REQUIRED.**

Runtime verification must specifically cover:
1. FM approval creates exactly one release.
2. GM approval creates no release.
3. GM rejection reverses the FM release and returns the project to FM review atomically.
4. FM correction/void before final confirmation reverses the release and returns to FM review atomically.
5. After FM final confirmation, the correction/void action is unavailable both visually and server-side.
6. PM cannot see/launch before FM final confirmation.
7. PM launch is the only initial transition to `active`.
8. PS is notified only after PM launch.
9. No duplicate treasury reduction is created by any later approval/handoff milestone.
10. Existing controlled-balance/reconciliation and closure behavior remains intact.

Do not correct historical/phantom project journals until these replacement-model runtime tests pass.


## 2026-10-02 — Final static authorization correction

During the final static pass, one authorization mismatch was found before runtime testing: the shared funding-reversal helper itself still enforced the FM role, which would have blocked the newly added GM-rejection reversal path. The helper now leaves role authorization to its calling workflow; FM correction and GM rejection each enforce their own role before calling it.

Correction commit: `4d672f3df3848421900b04a53706551dcd0ba4ac`.

**Runtime verification has not started yet. Pull only after this checkpoint is complete.**


## Phase 5 runtime-gate preflight repair — 2026-10-02
- Corrected PM project visibility so the Projects Manager who created a project can still see that project during the pre-handoff approval workflow (draft, submitted, rejected, fm_approved). Final FM confirmation remains the normal PM handoff gate for general project visibility.
- Corrected project creation PRG behavior: successful creation/update now returns to the same project form instead of redirecting through the project view, and displays the existing project-specific success toast.
- Removed the project portfolio page's duplicate page-specific bottom back button; the shared global back-button installer now supplies the standard top and bottom pair.
- Code commits: aa8b26532293c3a1933c0b64f6021205c0703c43, 4cfe3f9175da05cc57d91bde, 448a913dc254cdbc72e61f5bd4e767a32251cc20, dd49b8896109faaa1f1635405eff3911f065c7b6.
- Runtime verification is still required after the user pulls these commits; the current newly created test project should be reused rather than recreated.


## 2026-10-02 — Projects Phase 5 deep pre-runtime review and workflow consolidation

A second deep repository/schema review was completed before requesting another runtime fixture. The previous controlled fixture PRJ-0013 was deleted by the user because it had been created before the preflight visibility correction; there is currently no fresh Phase 5 runtime fixture.

Confirmed and corrected on main:
- Removed the remaining legacy funding POST handlers (add_funding, edit_funding, delete_funding) from modules/projects/view.php. The dedicated modules/projects/view_fm.php is now the canonical FM funding workflow.
- Removed the corresponding duplicate funding-entry/edit UI from the shared project view. Shared project view now presents funding allocations read-only.
- Removed the obsolete shared-project active status option. Initial planned → active remains exclusively through the PM launch_project action.
- Gated FM budget approval/rejection to the actual financial-review states (submitted / rejected) instead of allowing an FM budget decision on an unsubmitted draft project.
- Rechecked the shared funding card markup after consolidation and restored its correct closing structure.

Current status: IMPLEMENTED / STATICALLY RE-AUDITED / RUNTIME VERIFICATION REQUIRED.

No historical project journals have been modified. No historical Phase 5 fixture has been reused. Do not mark Phase 5 closed until the fresh controlled runtime sequence passes.

Latest main commit: 52a930f697198dbe8f051bf3415e014a929e1f1b.


### Final static consistency follow-up — 2026-10-02
The shared project status mutation path was also aligned with the portfolio: the legacy other_projects status mirror now maps lifecycle-only under_review to planned rather than completed. This prevents a mixed legacy/lifecycle status representation. Final code commit: f3cf39c5aafcee876db5be4436a1703ad6040097.


## Phase 5 — Second Independent Deep Scan / Remediation — 2026-10-02

A second independent source-level review was performed against the post-`e04b35c` Projects module revision before any new Phase 5 runtime fixture was created.

### Confirmed defects found and remediated

- Project creation is now restricted to the Projects Manager; new projects always begin in `draft` approval.
- GM/VGM general-data editing is removed from existing-project authorization.
- Generic Project Supervisor lifecycle changes can no longer set `planned`; PM launch remains the only `planned → active` path.
- Generic lifecycle mutations are transactionally grouped with legacy-status mirroring and history.
- Posted execution expenses cannot be edited or deleted by the Project Supervisor.
- Posted labor-payment records cannot be edited/deleted through the labor helper path.
- Existing-project form saves now use one transaction across the multi-table update sequence.
- FM budget approval and funding-batch writes are transactionally protected and lock the approval row against concurrent workflow transitions.
- GM approval/rejection re-check and lock the approval row inside their transaction.
- PM launch re-checks approval/lifecycle under lock and verifies the lifecycle update affects exactly one row.
- FM final funding confirmation records its workflow event transactionally and re-checks the locked approval state, preventing false success and duplicate concurrent confirmations.
- Funding allocation `journal_entry_id` is synchronized on release and cleared when the release is reversed.
- Project close/reopen state changes are transactionally grouped with lifecycle/legacy-state/history updates.

### Static post-remediation review

Targeted source checks for the above findings pass on the current `main` revision.

### Runtime status

No XAMPP/MariaDB/browser runtime verification has been claimed. The fresh controlled fixture `PH5 Full Accounting Reconciliation Test` must not be created until local runtime verification is available and the complete Phase 5 runtime sequence is executed.

Historical fixtures PRJ-0011 and PRJ-0012 remain untouched.


## 2026-10-02 — Phase 5 final pre-runtime workflow consistency repair

Before any new runtime fixture/test, the complete current Projects user-facing page set and the Projects dashboard were re-read against the established Phase 5 workflow, and the repository database snapshot was inspected as schema/accounting evidence.

### Additional inconsistencies found and corrected

1. **Obsolete standalone FM budget-rejection action removed**
   - modules/projects/view_fm.php still exposed fm_reject_budget.
   - That action only stored a rejection reason while leaving project_approval.approval_status unchanged, creating a dead-end path that did not match the canonical FM financial rejection workflow.
   - The standalone action, button, and modal were removed.
   - FM rejection is now exclusively fm_reject_project, which transitions submitted -> rejected and notifies the Projects Manager.

2. **Shared project-view funding ownership wording corrected**
   - modules/projects/view.php contained stale wording saying funding allocations were prepared before submission and that FM only reviewed them.
   - The current canonical workflow maintains funding allocations through modules/projects/view_fm.php during FM financial review.
   - The shared project view now presents allocations read-only and directs the workflow boundary to the dedicated FM page.

3. **Payment-evidence wording corrected**
   - The shared project view previously described the accounting documentation boundary as though the GM approval itself made the funding payment/accounting event.
   - It now explicitly states that the accounting funding release occurs at FM financial approval; GM approval is organizational approval only; FM later documents the actual payment evidence without creating another accounting release.

4. **Final-confirmation concurrency hardening**
   - FM cash confirmation, bank/e-wallet receipt upload, and payment-evidence editing now lock the project approval row and re-check the final-confirmation event inside a transaction.
   - This prevents a payment-evidence mutation from racing with FM final confirmation and modifying a project after the final handoff lock.

### Repository/schema verification

- All current modules/projects PHP pages were included in the source review: form.php, index.php, project_funding_accounting.php, project_lib.php, project_payment_receipt.php, serve_project_document.php, view.php, view_fm.php, view_pm.php.
- dashboard/projects_dashboard.php was also reviewed because it is part of the documented PM/PS workflow.
- The repository database snapshot database/ahl_el_kheir.sql was inspected directly from its Git blob, including the project approval/funding/payment-evidence/accounting structures.
- The snapshot contains no CREATE VIEW, CREATE TRIGGER, CREATE PROCEDURE, CREATE FUNCTION, or CREATE EVENT definitions.
- The current project page set contains no request-time CREATE/ALTER/DROP TABLE/VIEW/TRIGGER/PROCEDURE/FUNCTION/EVENT operations.
- Targeted post-remediation source invariants pass for:
  - canonical FM rejection;
  - GM rejection reversal/atomic approval reset;
  - FM correction lock;
  - final confirmation lock;
  - payment-evidence mutation locking;
  - PM-only planned -> active launch;
  - PS notification only after launch;
  - GM approval without a second accounting release;
  - FM approval as the accounting release point;
  - allocation-to-journal linkage.

### Runtime boundary

This is a source/schema consistency gate, not a runtime certification. No new project fixture has been created and no XAMPP/MariaDB/browser test has been claimed. The existing controlled fixtures remain untouched.

**Current Phase 5 status: IMPLEMENTED / DEEPLY SOURCE-AUDITED / PRE-RUNTIME WORKFLOW CONSISTENCY PASS / RUNTIME VERIFICATION STILL REQUIRED.**


## Latest runtime checkpoint — 2026-10-02 — PRJ-0015 creation gate

Fresh controlled fixture established: **PRJ-0015 — PH5 Full Accounting Reconciliation Test**. Runtime state: approval `draft`, lifecycle/display `planned`, Project Supervisor `project supervisor`, budget `300,000.00 SDG`, 3 budget lines. Financial requirements, approved funding, and posted expenses were `0.00` at this point. The previously observed missing PM post-creation actions are now resolved sufficiently to establish the fixture.

Runtime findings still open:
- Numeric budget inputs change when the mouse wheel is used over them; `299999.98` was observed. This must be removed system-wide wherever the same numeric-input behavior exists, not only on the current project form.
- The save flow required a page refresh and a second Save click before the expected result appeared. The project was eventually created, but this remains runtime evidence requiring source inspection and verification.

Do not mark Phase 5 closed. Next gate after resolving/verifying these findings: PM submission (`draft` → `submitted`), then verify FM visibility and no accounting release at submission.


## 2026-10-02 — PRJ-0015 source verification checkpoint

- Remote `main` HEAD at inspection: `138a7b7d2206b3bbe2664c919462c54bd7026169`.
- Numeric-input finding: the repository already contains the intended system-wide fix in `assets/js/app.js`, commit `c80e1aa218aa5d8a6438d6e899482da3af5a7169`. The shared document-level `wheel` listener prevents default wheel changes only for `input[type="number"]`, with a non-passive listener, so no project-only workaround is required from this source review.
- Project save-flow finding: `modules/projects/form.php` currently performs one DB transaction for the create/update sequence, commits before PRG redirect, stores a project-specific success message in session, redirects to the project form, and disables the Save button only after client-side validation passes. The form has no source-level duplicate-submit path beyond the intentional submit-button lock.
- The prior PRG/toast repair commits `4cfe3f9175da05cc57e30d16f155851675d91bde` and `dd49b8896109faaa1f1635405eff3911f065c7b6` are present in the current history.
- **Runtime status:** source review alone does not prove browser behavior. No local XAMPP/MariaDB runtime certification is recorded by this checkpoint.
- **Next gate:** locally pull current `main`, verify the numeric wheel behavior and PRJ-0015 save flow in the browser, then continue with PM submission only after those runtime checks pass. Do not recreate PRJ-0015.


## 2026-10-02 — PRJ-0015 runtime pre-submission gate PASSED

The PRJ-0015 browser/runtime gate for the two previously open findings is now passed.

- The system-wide numeric-input mouse-wheel issue is fixed and runtime-confirmed by the user. Scrolling the mouse wheel while focused/hovered over the budget numeric field no longer changes the value. The fix is implemented centrally in `assets/js/app.js` using a capturing, non-passive window-level wheel listener limited to `input[type="number"]`, with default prevention and propagation stopping.
- The project save flow is now runtime-confirmed working: PRJ-0015 saved successfully and is ready for PM submission to FM approval. No project recreation was required.
- Code fix commit: `788228916d8d79b94ff9b705c2b12613ee8cd631`.

**Current runtime status:** PRJ-0015 is saved in `draft` and ready for the next workflow gate.

**Immediate next gate:** PM submits PRJ-0015 for FM approval. Verify that submission changes the project from `draft` to `submitted`, that the FM can see/review it, and that submission itself creates **no accounting/funding release**. Do not begin the FM accounting sequence until this submission gate passes.

Do not repeat the numeric-input or project-save investigation unless a genuine regression appears.

## 2026-10-02 — Projects Phase 5 PRJ-0015 PM submission gate PASSED

Controlled fixture **PRJ-0015 — PH5 Full Accounting Reconciliation Test** passed the PM submission gate in local runtime.

Verified state:
- approval: **submitted / مرسل للمراجعة**
- lifecycle/display: **planned / مخطط**
- budget: **300,000.00 SDG** across 3 lines
- approved funding: **0.00**
- posted expenses: **0.00**
- no funding allocation created by submission
- PM sees the project as waiting for FM financial review
- FM receives the project-review notification and can open the FM review page
- FM review page exposes funding-allocation tools only after budget approval

Conclusion: PM submission is a workflow handoff only and does **not** create an accounting/funding release.

### Current pause point — 2026-10-02

The Phase 5 accounting runtime sequence is intentionally paused here because the user wants to switch to an immediate project fix and address that issue completely before continuing with FM budget approval/accounting release testing.

Do not mark Phase 5 closed. Do not start the FM accounting gate until the immediate fix is implemented, runtime-verified, and documented.

PRJ-0015 must be preserved in its current submitted/planned state unless the immediate fix itself requires a controlled change.



## 2026-10-02 — Projects Phase 5 PM submission gate passed

Controlled fixture **PRJ-0015 — PH5 Full Accounting Reconciliation Test** is preserved in submitted/planned state:
- approval: **submitted / مرسل للمراجعة**
- lifecycle/display: **planned / مخطط**
- budget: **300,000.00 SDG** across 3 lines
- approved funding: **0.00**
- posted expenses: **0.00**

Runtime verification confirmed PM submission hands the project to FM financial review, generates the FM review notification, and creates **no funding allocation or accounting release**. FM can open the review page; allocation tools correctly remain gated until budget approval.

**Current Projects Phase 5 status:** IMPLEMENTED / DEEPLY SOURCE-AUDITED / PM SUBMISSION RUNTIME GATE PASSED / PAUSED BEFORE FM BUDGET APPROVAL.

Do not recreate PRJ-0015. The next runtime gate is FM budget approval and the subsequent accounting reconciliation sequence.

## 2026-10-02 — System-wide form layout refinement

The immediate form field sizing/label alignment issue was addressed centrally after reviewing the actual form structures across the repository. The global rule in `includes/footer.php` now uses individual field-container width rather than a fixed percentage of the whole form, keeps narrow/dense columns stacked, and applies semantic sizing to common field types.

Commit: **7856da749fc23e06fce9c7ecedfa5e92f061fd8f**. User runtime feedback: **much better for most pages**, with some remaining page-level imperfections.


## 2026-10-02 — Projects Phase 5 / system-wide form-layout checkpoint

### Projects Phase 5 current state
- Controlled fixture **PRJ-0015 — PH5 Full Accounting Reconciliation Test** is preserved and currently remains at the PM-submission gate: **approval = submitted**, lifecycle/display = **planned**.
- PM submission runtime gate **PASSED**.
- FM receives the pending-financial-review notification and can open the FM review page.
- PM submission created **no funding allocation and no accounting release**.
- Budget remains **300,000.00 SDG** across 3 lines; approved funding and posted expenses remain **0.00**.
- Phase 5 accounting runtime testing is intentionally paused here until the immediate project fix requested by the user is completed and runtime-verified.
- Do **not** recreate or replace PRJ-0015 and do not proceed to FM budget approval/funding allocation until the pause is lifted.
- Previously completed numeric-input wheel and project-save-flow gates remain closed; do not repeat those investigations unless a genuine regression appears.
- The established Phase 5 accounting sequence remains unchanged: FM approval → one release per allocation → GM approval without a second release → GM rejection reversal/atomic return to FM → FM re-approval → GM approval → FM pre-final correction/reversal → FM re-approval → GM approval → FM final confirmation/PM handoff → PM launch → PS notification only after launch.
- Phase 5 is **not closed** until the complete local XAMPP/MariaDB runtime sequence passes.

### System-wide form UI checkpoint
- The global form-layout issue was reviewed structurally across Bootstrap column forms, custom grids, and dense repeatable rows rather than continuing page-by-page sizing adjustments.
- The centralized solution now uses the actual field-container width: wide containers use a content-sized label with a small gap and flexible field; narrow containers remain stacked so labels are not stuffed into cramped columns; dense repeatable rows are protected from the inline-label rule.
- Field widths are now differentiated by input type instead of forcing every field to consume the same excessive width.
- Implemented centrally in `includes/footer.php`.
- User runtime feedback: **much better for most pages**; this UI milestone is accepted for continuation, with any remaining page-specific issues to be handled only when concrete evidence is provided.
- Commit: `7856da749fc23e06fce9c7ecedfa5e92f061fd8f`.

### Continuation rule
Start the next session by reading the four master documents and the current Projects Phase 5 checkpoint. Do not restart earlier audits or recreate historical/controlled fixtures.



## 2026-10-02 — Projects Phase 5 continuation checkpoint — UI fix accepted / FM gate next

The previously requested immediate project/UI fix is now completed and accepted for continuation.

### System-wide form UI fix — completed
- The form label/field sizing issue was addressed **centrally**, not page-by-page, in `includes/footer.php`.
- The solution was based on the repository's actual Bootstrap column forms, custom grids, and dense repeatable rows.
- Wide field containers use a content-sized label with a small gap and a flexible field; narrow/dense containers remain stacked; common field types use semantic sizing rather than forcing every field to the same width.
- Commit: **7856da749fc23e06fce9c7ecedfa5e92f061fd8f**.
- User runtime feedback: **"much better now for most pages"**.
- This UI milestone is accepted. Any remaining page-specific issue should be handled only when concrete runtime evidence appears; do not reopen the completed global sizing investigation.

### PRJ-0015 current state
- Fixture: **PRJ-0015 — PH5 Full Accounting Reconciliation Test**
- approval: **submitted / مرسل للمراجعة**
- lifecycle/display: **planned / مخطط**
- budget: **300,000.00 SDG** across 3 lines
- approved funding: **0.00**
- posted expenses: **0.00**
- no funding allocation/accounting release created by PM submission
- FM received the financial-review notification and can open the FM review page

### Next gate
The temporary pause after PM submission is now **lifted**. The next task is to resume Phase 5 at **FM financial review / budget approval**, then continue the established accounting reconciliation sequence.

Do **not** recreate PRJ-0015. Do not repeat the already-passed numeric-wheel or save-flow investigation unless a genuine regression appears.


## 2026-10-03 — Projects Phase 5 runtime reconciliation checkpoint — PRJ-0015

The previous documentation checkpoint was stale and incorrectly identified FM budget approval as the next runtime gate. A source-driven scan of the current Projects module and the user's subsequent browser/runtime evidence establishes that this gate and the downstream workflow have already been completed.

### Controlled fixture
- PRJ-0015 — PH5 Full Accounting Reconciliation Test
- Final approval: **approved / معتمد نهائياً**
- Lifecycle after PM launch: **active / قيد التنفيذ**
- Project Supervisor: **project supervisor**
- Approved budget: **300,000.00 SDG** across 3 approved budget lines
- Approved funding: **300,000.00 SDG**
- Posted expenses at the PS access checkpoint: **0.00 SDG**
- Remaining budget at that checkpoint: **300,000.00 SDG**

### Runtime gates now CLOSED
1. PM submission: draft → submitted; no funding/accounting release; FM notification delivered.
2. FM financial approval / funding release: previously runtime verified; FM approval is the accounting-release point.
3. GM approval: previously runtime verified; no second accounting release.
4. GM rejection/reversal and re-approval sequence: previously runtime verified and preserved by the Phase 5 workflow audit.
5. FM correction/void before final confirmation: previously runtime verified and preserved by the Phase 5 workflow audit.
6. FM final payment-document confirmation: runtime verified; no new accounting event; PM handoff/visibility enabled.
7. PM launch: runtime verified on PRJ-0015; lifecycle changed from مخطط to قيد التنفيذ at **2026-10-02 19:48:43** by projects manager.
8. PS notification timing: runtime verified. The assigned Project Supervisor received **تم إطلاق مشروع جديد للتنفيذ** for PRJ-0015 at **2026-10-02 19:48:43**, matching the PM launch event.
9. PS access/role boundary: runtime verified. The Project Supervisor can access the launched project and operational areas while no project funding-release, payment-confirmation, or accounting-posting controls are exposed.
10. PS access did not produce a new funding release: the displayed funding remains 300,000.00 SDG and expenses remain 0.00 SDG at the PS checkpoint.

### Current source-derived Projects page/action inventory
- dashboard/projects_dashboard.php — separate PM and PS dashboard scopes; PS dashboard requires approved project plus lifecycle active/reopened/completed/under_review/closed/cancelled and an active supervisor assignment.
- modules/projects/index.php — portfolio/list and generic status action; active/planned are not offered through the generic PS status selector because launch is a dedicated PM-only transition.
- modules/projects/form.php — project creation/editing and pre-approval project/budget data preparation.
- modules/projects/view.php — shared project workflow, including PM submission, GM approval/rejection, dedicated PM launch, PS operational expense/document/labor/milestone/progress actions, and closure/reopen request/approval actions.
- modules/projects/view_fm.php — FM financial review, budget approval, canonical funding allocation/release, funding reversal/return controls, payment-evidence workflow, final confirmation, and FM rejection/correction controls.
- modules/projects/project_funding_accounting.php — canonical project funding release/reversal/return accounting helpers; release is idempotency-protected by allocation/journal reference and creates a balanced two-line journal.
- modules/projects/project_payment_receipt.php — authenticated documented-payment receipt viewer with project authorization and path confinement.
- modules/projects/serve_project_document.php — authenticated project-document serving route.
- modules/projects/project_lib.php — centralized project role/section/lifecycle/final-FM-confirmation helpers and authoritative project totals.

### Authoritative workflow sequence
PM preparation → PM submission → FM financial approval/release → GM organizational approval → FM final funding-document confirmation → PM visibility → PM explicit launch → PS notification/access → operational execution → closure request/review → final closure/reopen workflow.

Final approval is not the launch event, and FM final payment-document confirmation is not a second accounting release.

### Documentation correction
The older continuation prompt that still said FM financial review / budget approval was the next gate is superseded by this checkpoint. Do not reopen FM approval, GM approval, FM final confirmation, PM launch, or PS launch-access gates unless concrete regression evidence appears.

### Next audit direction
Continue the remaining Projects Phase 5 reconciliation/closure checks from the current documented evidence, not from the stale pre-FM checkpoint. First inspect the current master audit for any still-unverified gate such as post-final-confirmation mutation locking, final financial reconciliation, controlled-balance/return behavior, or closure/reopen certification. Do not create a new project or repeat a closed gate.
\n## 2026-10-03 — Projects Phase 5 source audit: execution-expense boundary\n\nA source-driven audit of the current `main` implementation was completed before the next PRJ-0015 runtime action. The audit distinguishes the legacy accounting-expense workflow from the post-release controlled-funds execution workflow.\n\n### Current authoritative behavior\n- `modules/projects/view.php` provides a dedicated `add_ps_expense` action for the assigned Project Supervisor after the project has reached the launched/operational state.\n- The PS action validates the approved-budget ceiling against existing posted project expenses, inserts the execution expense as `posted`, records the acting user, and writes a `CREATE / project_expense` audit event.\n- This controlled-funds execution path deliberately does **not** create a second treasury journal. The released project funding is the treasury event; execution expenses consume the already-released project-controlled balance.\n- The older draft → submitted → approved → `post_expense` accounting path remains present for unreleased projects, but its server-side `post_expense` action explicitly blocks when posted project funding already exists, preventing a second treasury reduction.\n- `akp_project_totals()` treats `project_expenses.status='posted'` as the authoritative posted-expense total and calculates residual as total funded minus total expensed.\n\n### Runtime status\nThis source audit is **not** a runtime pass. No local database state was changed by this audit and no PRJ-0015 expense was created through repository tooling.\n\n### Next runtime gate\nUse the existing PRJ-0015 fixture to verify one controlled execution expense end-to-end: funding remains 300,000.00 SDG, a single PS expense is recorded as posted, total posted project expenses increases by exactly the test amount, residual decreases by exactly that amount, and no second funding-release/treasury journal is created. Do not invoke the legacy `post_expense` action after funding release.\n

## 2026-10-03 — Projects Phase 5 execution-expense runtime gate — PASS

PRJ-0015 was runtime-tested through the assigned Project Supervisor execution-payment path after final FM confirmation and PM launch.

User-supplied local XAMPP/browser evidence:
- Payment recorded and 50,000.00 SDG deducted from the project budget.
- Approved budget: 300,000.00 SDG.
- Recorded expenses: 50,000.00 SDG.
- Remaining project budget: 250,000.00 SDG.

The observed reconciliation is exact:
- funded balance remained 300,000.00 SDG;
- expense increased to 50,000.00 SDG;
- residual decreased to 250,000.00 SDG.

This matches the source-defined PS execution-expense model: spending is recorded against the already-released project-controlled balance and does not constitute a second organizational treasury release.

**Result: PASS / RUNTIME VERIFIED.**

The next genuinely open Projects runtime gate must now be identified from the current master audit; do not repeat completed workflow gates.


---

Projects checkpoint — 2026-10-03

PS status-control root cause was identified from the fresh database backup. PRJ-0015 is project id 15 and has active primary supervisor assignment id 14 to user 34 (`ps1`), who is a `project_supervisor`. The primary-supervisor authorization path therefore grants operations access without requiring a `project_team` operations row.

The missing PS status option was a UI exposure defect on `modules/projects/view.php`: the existing `change_status` server-side action supported `under_review`, `completed`, and `cancelled`, but the project-detail page had no form exposing it. A narrow PS-only selector was added; server-side authorization was not weakened.

Latest implementation commit: `0de84d5bd0b3c42e5b113499f551a96347b3f179`.

Runtime gate: PENDING until user pulls and verifies the PRJ-0015 detail-page selector. Do not repeat completed Projects Phase 5 gates.

Next open financial task: implement/verify return of the remaining controlled funding amount when PS execution does not fully consume the approved/released project budget, using the existing FM/accounting return mechanism and full reconciliation/audit evidence.

## 2026-10-03 — Projects closure proof UX and final-close guard

A source-driven review of the closure flow addressed two concrete UX defects without weakening the accounting controls.

### Closure proof field
The Project Supervisor closure form now uses one unified return_proof field:
- an existing funding-return proof is displayed with its current filename and authenticated view link;
- the same file chooser can optionally replace that proof when the next closure request is submitted;
- leaving the chooser empty preserves the existing proof;
- replacement updates the existing project_documents row instead of creating a duplicate;
- the original physical file is removed only after the database update succeeds;
- replacement remains restricted to an unverified proof before a pending closure request exists;
- audit action is UPLOAD for the first proof and UPDATE for a replacement.

The separate replace_funding_return_proof action/form was removed. Browser security is respected: the existing local file path is not inserted into the file input; the current file is shown beside the same chooser instead.

Implementation commits:
- be3ee1768f4834db48b5d0d4e299da776637c94d
- b86ff01bd045607c889d031190f97af13efa280d (latest main)

### Final-close guard
The PM final-close UI now calculates the same authoritative controlled balance and pending-expense conditions used by the server-side close_project action.

When closure is not currently permissible, the final-close button is visibly disabled and the reason is displayed. For PRJ-0015 at the current documented checkpoint, the controlled balance is 250,000.00 SDG, so final closure must remain blocked until the Financial Manager records the funding reconciliation/return.

The server-side balance check remains authoritative; this is a visibility/UX correction, not a relaxation of the closure rule.

Runtime status: code/source change completed; local XAMPP/browser verification of unified proof replacement/preservation and the visible final-close blocking state is still required. Do not mark this UI/runtime gate closed until the user supplies that evidence.

Next runtime action: on PRJ-0015, verify the PS closure form preserves the current proof when no new file is selected, replaces it through the same field when a new file is selected, and does not create a duplicate document. Then verify the PM closure card clearly shows the 250,000.00 SDG blocking balance and a disabled final-close button. After FM settlement, verify the guard clears and final closure can proceed subject to the remaining closure checks.


### Projects closure notification-sequence correction — commit 2aa16aca0e0ba326000fb65ec2132314777d9145
- Corrected the closure notification sequence in `modules/projects/view.php`.
- A PS closure request now notifies **only the Projects Manager (PM)**; the Financial Manager (FM) is no longer notified at the request stage.
- FM notification now occurs **only after the PM successfully performs final project closure**.
- The FM notification is informational and points to the FM project view for any subsequent financial follow-up; it does not alter the closure accounting guard.
- Runtime status: source fix completed; local XAMPP/browser verification of both notification boundaries is required.


### Closure notification syntax correction — commit cc1e2c9708f23305a8bb56897b0da952160f4db2
- Corrected the malformed try/catch structure introduced in the project-closure notification sequence change.
- The Projects Manager closure path now has one valid notification try/catch covering GM/VGM and post-closure FM notifications.
- Runtime verification is still required after pulling `main`.


## 2026-10-03 — Projects PM Settlement Visibility Checkpoint

- Commit: `c8863f953fa3ef82310a8d1ef3e764fbeacfad08`.
- Root cause of the reported PM UX gap: the project PM page already blocked final closure when the controlled balance was non-zero, but it did not render the financial-manager settlement ledger or the closure-request funding-return proof in the PM closure-review area.
- Fix: `modules/projects/view.php` now loads all `project_funding_returns` for the project and renders a dedicated PM section showing current controlled balance, recorded settlement total/status, settlement date/source account/amount/journal/actor, and the PS-provided settlement-proof document when present.
- The section explicitly distinguishes: no FM settlement yet, partial settlement, and full settlement. It does not create or modify accounting data.
- Server-side final-close guard remains authoritative: PRJ-0015 must remain blocked while the controlled balance is 250,000.00 SDG; after FM records the settlement and the balance reaches zero, the PM page should show the recorded settlement/proof and the final-close control should become eligible subject to all other closure guards.
- Runtime status: source fix committed; browser/runtime verification is still required. Do not claim the PM display as runtime-passed until verified locally.


## 2026-10-03 — PM Closure Review Settlement Visibility Correction

- Commit: `e03600cf27d3bfd9bcde532d0ffc7687bc9093cb`.
- The previous settlement/proof UI was inserted only inside the Project Supervisor branch of `modules/projects/view.php`, so it was not visible to the Projects Manager. This was a placement error, not a workflow/accounting error.
- Corrected placement: the Projects Manager closure-review section now displays the controlled balance, FM-recorded `project_funding_returns`, journal/source details, settlement status, and the PS-provided `funding_return_proof` document when a closure request is under review or a settlement exists.
- PRJ-0015 remains blocked at 250,000.00 SDG until FM records the settlement. Runtime verification is required after pull; source inspection alone is not a runtime pass.


## 2026-10-03 — Closure Balance FM Routing Correction

- Commit: `eb2c2b554a8bcb81cf1b6b3ad55d637b1278e48c`.
- Corrected the closure workflow gap: when the PS submits a closure request while a controlled project balance remains, the request now notifies both the Projects Manager and active Financial Manager recipients using the closure-request history ID as the event reference.
- The FM notification links directly to the FM project review page, where the existing funding-return control records the accounting return to the organization account.
- The PM closure-review message now explicitly states that the settlement request has been sent to FM and that final closure becomes eligible only after the controlled balance reaches zero.
- This does not bypass the accounting guard or create a second accounting release. FM remains the only role allowed to record the project funding return; PM performs final closure only after reconciliation is complete.
- Runtime verification is required: confirm FM receives the settlement notification, can see the return control for PRJ-0015, record the 250,000.00 SDG return, and that PM then sees zero controlled balance and an enabled final-close action.

## 2026-10-03 — Projects closure workflow correction: PM administrative close → FM financial close

A source-driven review superseded the earlier closure design that treated the FM return as a prerequisite to PM closure. The authoritative workflow is now:

1. **PS** submits the closure request and provides the return information/proof when unused project-controlled funds remain.
2. **PM** reviews the request and performs the **administrative/project closure with return**. The Projects Department ends its role here. PM does **not** post the accounting return and is not blocked by a non-zero controlled balance.
3. After PM closure, the project is administratively **closed** while financial closure may remain pending. The system notifies **FM only at this handoff point**.
4. **FM** opens the financial-closure task, reviews the PS return proof, records the actual return of the remaining controlled balance to the organization source account, and posts the accounting journal through the existing funding-return engine.
5. When the controlled balance reaches zero, FM records the explicit 'FM_FINANCIAL_CLOSURE' audit event and the system notifies **PM that financial closure is complete**. PS is not notified again because the Projects Department role ended at PM closure.

### Notification sequence — authoritative

PS closure request → PM notification → PM administrative close → FM financial-closure notification → FM return/accounting → FM financial-closure completion → PM completion notification

There is **no FM notification at the PS request stage**, and no second accounting release is created by either PM closure or FM financial closure. Notification event references remain event-specific and use the closure/financial-closure event keys rather than reusing an unrelated project ID event.

### Accounting/state boundary

- `project_lifecycle.lifecycle_status = 'closed'` represents the administrative/project closure performed by PM.
- A non-zero `akp_project_controlled_balance()` after that point represents **financial closure pending**, not an invalid project state.
- 'FM_FINANCIAL_CLOSURE' in `audit_log` represents completion of the financial-close stage without requiring a new schema column.
- `akp_return_project_funding()` is now restricted to an administratively closed project and requires the recorded PM `CLOSE` audit event. It remains FM-only and uses the existing balanced return journal.
- If the controlled balance is already zero, FM can explicitly confirm financial closure without creating a fictitious return transaction.

### Implementation commits

- `52622eebfd613fe242ff47a6c2bdfeee4e58f84c` — funding-return helper now requires administrative closure and prevents duplicate financial closure.
- `2a91f507200d1d41490fa946646517ccb5d26be7` — FM financial-closure action, post-close authorization, completion notification, and post-close UI routing.
- `5f1282424d14308f260ae13739b746d256e2f377` — removed duplicate FM closure panel after source review.
- `86c669f607482e08cf30705980955c6a567e25aa` — PM closure UI now explicitly explains the administrative-close → FM-financial-close handoff.

### Runtime status

**STATICALLY IMPLEMENTED — RUNTIME VERIFICATION PENDING.** The controlled PRJ-0015 scenario remains the next runtime gate. Do not claim financial closure or notification sequencing as runtime-passed until the user verifies it locally.

### Required runtime sequence for PRJ-0015

1. Pull the latest `main`.
2. As PS, submit/confirm the closure request with the existing return proof and verify the notification goes to **PM only** at this stage.
3. As PM, review the request and close the project even though the controlled balance is 250,000.00 SDG. Verify the project becomes administratively closed and PM receives no requirement to wait for FM.
4. Verify **FM** receives the financial-closure notification only after PM close and can open the FM financial-closure section.
5. As FM, verify the 250,000.00 SDG balance and PS proof, then record the return to the organization account. Verify the balanced return journal and controlled balance becomes zero.
6. Verify 'FM_FINANCIAL_CLOSURE' is recorded exactly once and **PM** receives the completion notification.
7. Verify PS is not re-notified and no second funding-release journal is created.


---


## 2026-10-03 — Projects closure workflow correction

The Projects closure model is separated into:

1. **Administrative/project closure — Projects Manager**
2. **Financial closure — Financial Manager**

For PRJ-0015, the remaining controlled balance is 250,000.00 SDG after the verified 50,000.00 SDG execution expense. PM may therefore close the project administratively without waiting for FM settlement.

Notification sequence:
- PS closure request → PM only.
- PM administrative close → FM financial-closure task.
- FM completes return and financial closure → PM completion notification.
- No FM notification is sent at the PS request stage, and no post-close accounting task is sent to PS.

Implementation commit: **e66d0452885a0f3c0270913cda698b0f522723f9**.

**Runtime status: pending.** Do not mark this workflow closed until PRJ-0015 is exercised locally through the complete PS → PM → FM → PM sequence.


---

## 2026-10-03 — Projects Phase 5 closure gate CLOSED

PRJ-0015 — PH5 Full Accounting Reconciliation Test has passed the administrative-to-financial closure runtime gate. PM completed administrative closure with 250,000.00 SDG remaining under project control; FM returned the remaining balance through the existing funding-return workflow to **1200 — البنك**; FM financial closure completed; and PM received the final **اكتمل الإغلاق المالي للمشروع** notification confirming that the remaining balance was returned to the organization's account.

The FM closed-state UI was also corrected to present the project as administratively and financially closed with controlled balance 0.00 SDG and no pending FM financial action. No second return or accounting release was created.

**Projects status:** this Phase 5 closure gate is **RUNTIME VERIFIED / CLOSED**. Do not repeat the return or recreate PRJ-0015 for this gate.

---

## 2026-10-03 — Projects Phase 5 final certification

The Projects Phase 5 controlled runtime sequence is now **RUNTIME VERIFIED / CLOSED** on PRJ-0015. The sequence reached the intended end state: 300,000.00 SDG released, 50,000.00 SDG consumed through the PS execution-expense path, 250,000.00 SDG returned by FM to **1200 — البنك**, controlled balance reduced to 0.00 SDG, FM financial closure completed, and PM received the final **اكتمل الإغلاق المالي للمشروع** notification.

This supersedes earlier checkpoints that described the Phase 5 accounting sequence as pending. Those entries remain historical audit records and must not be interpreted as the current status.

**Current Projects checkpoint: Phase 5 closed.** No further work is required on this controlled reconciliation path unless a concrete regression is found.

## 2026-10-05 — HR Salary Register and Employment-Scope Checkpoint

- Salary-register implementation commit: `7ef751180de91dc9aacdeb0efe8086cc791dc728`.
- Zero-placeholder correction commit: `7d0f5a43d6e3a18f7cc65ef123bb5314207b2c05`.
- The HR employee page now provides a dedicated **سجل الرواتب عند التعيين** view based on the earliest positive salary-history record for each employee, with employee count, total and average salary.
- The salary values used during the current payroll/salary-advance testing are intentionally test data. They were varied directly in the database rather than hard-coded into PHP.
- Non-working employees are now excluded from the salary register using the existing employment-state model: only states with `category = 'working'` and `is_active = 1` are included.
- This exclusion is a scope rule, not deletion. Historical employee, salary-history, payroll and accounting evidence remains preserved.
- Employment-state scope is aligned with the existing payroll rule that prevents non-working employees from entering a new payroll run.
- Latest source commit: `b8adb067c3de34441192e63fff56c6b707b3aa22`, **Exclude non-working employees from salary register**.
- Runtime status: user confirmed the salary distribution is now acceptable. The non-working employee exclusion is source-implemented; local browser verification of the filtered register should be performed when next convenient.

### HR salary-register test-data boundary

The current varied salaries are development/test values only. They must not be interpreted as historical payroll truth. The register is intended to demonstrate the first positive salary-history value for currently working employees while preserving employment lifecycle scope.

## 2026-10-05 — HR Attendance Policy Foundation

A new attendance-policy foundation is implemented on main. The versioned policy table is added by dated migration; HR Manager/Admin can manage future-effective policy versions; successful qualifying employee login can create the day's attendance while preserving the first check-in; and tools/finalize_daily_attendance.php provides the separate scheduled absence-finalization path. Initial policy examples are remote work and 07:00–16:00, stored as policy data rather than hard-coded rules.

Canonical attendance eligibility remains unchanged: active employee + effective working employment state + no blocking approved leave/return condition. Non-working employees and approved-leave employees are not auto-marked present or absent.

**Status: STATICALLY IMPLEMENTED / RUNTIME VERIFICATION PENDING.** Migration application, first policy creation, qualifying/repeated-login tests, exclusion tests and finalizer idempotency remain the next runtime gate.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.


## 2026-10-05 — Same-page POST navigation remediation

A repository-level audit confirmed that many operational pages use same-path HTTP POST forms that submit as full-document browser navigations. The previous `akGlobalScrollRestore` mechanism only repaired the viewport after that navigation and therefore could not eliminate the underlying visible jump.

Implemented on `main`:
- `assets/js/app.js`: centralized same-path POST interception using `fetch()`, replacing only the shared `.content` region and preserving the existing viewport; cross-page redirects remain normal navigations.
- `modules/hr/attendance.php`: attendance bulk refresh now uses the centralized in-place refresh; return-from-leave confirmation now re-enters the normal submit event so the centralized handler can process it.
- `includes/header.php`: removed the obsolete first-paint scroll-restoration guard because same-page POSTs are now kept in the existing document.

Commits: `412301e82718dba9a96146b4b9b42c4298469619`, `c7ade0903bf3aefbeed2d0e272c91a889d19038c`, `9e2e781ba465fd0c3ab4bdd41f0ac4e52f1b5b84`.

**Verification status:** code/repository review completed; local browser runtime verification is still required before marking the issue closed. Do not treat the change as runtime-verified until the attendance and representative same-page POST workflows are tested locally.


## 2026-10-06 — GM Salary Advance Waiver: First waiver-specific runtime gate passed

The local rollback-only waiver harness passed the transaction-composable remaining-balance/no-refund execution path for `SAR-2026-00004`. Temporary waiver decision, waiver journal, and future schedule-overlay rows were all rolled back; post-rollback counts returned to zero.

This is a verified waiver-specific milestone, not feature closure. The remaining waiver verification matrix is still open.

## 2026-10-06 — GM Salary Advance Waiver Verification

**Status: IMPLEMENTATION HARDENED / DEPENDENCY RUNTIME VERIFIED / WAIVER RUNTIME VERIFICATION OPEN.**

The existing rollback-only Stage 5 payroll harness was executed locally on `main`:
`php tools\run_salary_advance_stage5_payroll_tests.php`

Results:
- PASS — payroll repayment application for `SAR-2026-00004`; deduction 5,000 SDG; one repayment trace row; schedule status `paid`; outstanding balance 45,000 SDG; journal 116 balanced.
- PASS — duplicate repayment protection for the same request/payroll.
- PASS — rollback-only cleanup; no payroll/request/schedule/journal mutation was committed.

This establishes the real payroll repayment dependency required by the waiver. It does not establish that the GM waiver workflow itself is complete. The waiver-specific verification matrix remains open.

Do not reopen completed Salary Advance Stages 1–6 and do not mix this work with the unfinished attendance scroll-jump issue.

## 2026-10-06 — GM Salary Advance Waiver: Paid-Deduction Refund Gate Passed

The dedicated rollback-only refund harness passed locally on `main` using the real payroll/accounting/repayment and waiver functions.

- `SAR-2026-00004`: temporary payroll ID 24 produced a 5,000.00 SDG salary-advance repayment.
- GM waiver execution refunded the already-paid 5,000.00 SDG and waived the restored 50,000.00 SDG balance.
- Refund journal 119 matched Dr 1410 / Cr 1100 for 5,000.00 SDG.
- Waiver journal 120 matched Dr selected expense / Cr 1410 for 50,000.00 SDG.
- Historical repayment evidence remained applied and linked to its original payroll accounting entry; the request remained `disbursed` and final outstanding balance became zero inside the test transaction.
- Rollback cleanup passed: payroll rows 22, repayment rows 0, waiver decision rows 0, and waiver journal rows 0 after rollback.

**Verification status:** the paid-deduction refund gate is runtime verified. GM Salary Advance Waiver remains open pending the remaining verification matrix; this does not reopen Salary Advance Stages 1–6.



## 2026-10-06 — GM Salary Advance Waiver: Undistributed Cancellation Gate Passed

The dedicated rollback-only cancellation harness passed locally on `main` for `SAR-2026-00009` (previous status `approved`). FM preparation, GM approval, and FM execution resulted in `cancelled` with **zero accounting journals** and **zero waiver schedule overlays**. Rollback cleanup restored waiver decision/item/overlay/journal counts to zero and restored the request's original state.

**Verification status:** the undistributed-request cancellation gate is runtime verified. GM Salary Advance Waiver remains open pending the remaining verification matrix; this does not reopen Salary Advance Stages 1–6.



## 2026-10-06 — GM Salary Advance Waiver: Future-Deduction Blocking + Draft Refresh Gate Passed

The dedicated rollback-only harness passed locally on `main` for `SAR-2026-00004`.

- Temporary draft payroll ID 24 initially received a 5,000.00 SDG salary-advance deduction.
- After FM preparation, GM approval, and FM execution, the draft payroll was refreshed to **0.00 SDG** salary-advance deduction.
- The executed waiver created 10 future schedule-overlay rows, and the real payroll eligibility query returned no pending/partial repayment rows for the waived request/effective month.
- The request remained `disbursed` and its outstanding balance became zero inside the transaction.
- Rollback cleanup passed: the temporary payroll was removed and waiver decision/item/schedule-overlay counts returned to zero.

**Verification status:** the future-deduction blocking + draft-refresh gate is runtime verified. GM Salary Advance Waiver remains open pending approved-unpaid payroll protection, blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notifications and failure isolation, audit preservation, and final 1410 reconciliation. This does not reopen Salary Advance Stages 1–6.


## 2026-10-06 — GM Salary Advance Waiver: Approved-Unpaid Payroll Protection Gate Passed

The rollback-only runtime harness passed for `SAR-2026-00004`.

- Temporary approved payroll ID 24 carried a 5,000.00 SDG salary-advance deduction.
- FM preparation and GM approval succeeded.
- FM execution was **blocked before financial mutation** because the approved, unpaid payroll still contained the salary-advance deduction.
- Decision remained `approved_by_gm`; request and payroll state remained unchanged; waiver/refund journal count was zero.
- Rollback cleanup passed completely.

**Verification status:** approved-unpaid payroll protection is runtime verified. GM Salary Advance Waiver remains open for blanket/multiple-advance scope, concurrency/duplicate protection, post-commit notifications and failure isolation, audit preservation, and final 1410 reconciliation. Salary Advance Stages 1–6 remain closed.


## 2026-10-06 — GM Salary Advance Waiver: Blanket Scope Gate Passed

Rollback-only runtime verification passed for the blanket decision workflow:

- Effective month: `2026-10-01`
- Eligible requests: **6**
- Executed items: **6**
- Disbursed items: **5**
- Undistributed item cancelled: **1**
- Combined waiver amount: **190,000.00 SDG**
- Waiver journal: **122**
- Rollback cleanup: **PASS**

The live fixture had no employee with multiple eligible advances, so the system's blanket-all-eligible behavior is proven across multiple requests/employees, but the specific same-employee multiple-advance scenario is not yet runtime-proven.

**Verification status:** blanket scope/execution is closed. Multiple-advance-for-one-employee remains an explicit open test condition. Salary Advance Stages 1–6 remain closed.

## 2026-10-06 — GM Salary Advance Waiver: Duplicate Execution Gate Passed

The dedicated rollback-only duplicate-execution harness passed locally on `main`:

- `SAR-2026-00004`, decision ID `9`.
- First waiver execution succeeded.
- A second execution attempt against the same decision was rejected.
- No additional waiver/refund journal was created by the rejected duplicate attempt.
- Rollback cleanup returned waiver decision/item/schedule-overlay counts to zero.

This closes the sequential duplicate-execution runtime gate. The source inspection also confirms the decision row is locked with `SELECT ... FOR UPDATE` before the `approved_by_gm` status check, providing the transaction-level serialization point for concurrent attempts. A separate two-process concurrent-commit runtime test has not been claimed.

**Current GM Salary Advance Waiver status:** still open for post-commit notification behavior/failure isolation, audit preservation, final 1410 reconciliation, and the specific same-employee multiple-advance runtime fixture.

## 2026-10-06 — GM Salary Advance Waiver: Post-Commit Notification Gate Prepared

Source inspection confirms the required transaction boundary: waiver preparation, GM review, and FM execution invoke their notification helpers only after a function-owned commit. The shared notification helper catches delivery exceptions, so notification failure cannot roll back an already-completed business/financial action.

A rollback-safe-in-scope committed-fixture harness was added:
`tools\\run_salary_advance_gm_waiver_post_commit_notification_tests.php`

It will runtime-test the real committed preparation and GM-approval notification paths and then remove only its own committed test rows. FM financial execution is deliberately not performed by this harness.

**Runtime status: PENDING USER EXECUTION.**


## 2026-10-06 — GM Salary Advance Waiver: Post-Commit Notification Gate Passed

The dedicated committed-fixture notification harness passed locally on `main`:

`php tools\\run_salary_advance_gm_waiver_post_commit_notification_tests.php`

- PASS — FM preparation committed as `pending_gm` and produced the required active-GM notification.
- PASS — GM approval committed as `approved_by_gm` and produced the required preparing-FM notification.
- PASS — cleanup removed the test decision and test notifications.

The harness intentionally stopped before FM financial execution, so no waiver/refund accounting mutation was introduced by this notification test. The live installation uses the legacy notification schema, and the harness verified the notifications against that actual schema.

**Verification status:** the post-commit preparation and GM-approval notification gate is **RUNTIME VERIFIED / CLOSED**. GM Salary Advance Waiver as a whole remains open for execution-notification coverage/failure-isolation runtime evidence, audit preservation, final 1410 reconciliation, and the specific same-employee multiple-advance fixture.


## 2026-10-06 — GM Salary Advance Waiver: Current Runtime Verification Checkpoint

The GM Salary Advance Waiver has now passed the committed execution-notification runtime gate. This is an exceptional lifecycle extension and does not reopen Salary Advance Stages 1–6.

Runtime-verified / closed:
- Transaction-composable remaining-balance waiver execution.
- Paid-current-period deduction refund.
- Undistributed qualifying-request cancellation without accounting.
- Future-deduction blocking and draft-payroll refresh.
- Approved-but-unpaid payroll protection.
- Blanket scope/execution across multiple requests and employees.
- Sequential duplicate-execution protection.
- Post-commit FM preparation → active GM notification.
- Post-commit GM approval → preparing FM notification.
- Post-commit FM execution → approving GM notification.
- Post-commit FM execution → affected employee notification with an active linked account.

Latest execution-notification runtime evidence:
- Harness: tools\\run_salary_advance_gm_waiver_execution_notification_rollback_tests.php
- PASS: request SAR-2026-00004, decision ID 12, GM notification = 1, employee notification = 1, waiver journal = 124.
- PASS cleanup: decision rows = 0, test notifications = 0, test journals = 0.

Explicitly open evidence gates:
1. Notification failure isolation runtime: deliberately induce a controlled notification-delivery failure and prove the already-committed waiver execution is not rolled back.
2. Audit preservation runtime: verify historical disbursement, payroll repayment and original accounting evidence remain intact and waiver audit evidence is complete.
3. Final 1410 reconciliation: reconcile the salary-advance receivable/control account after waiver execution against the remaining live receivables.
4. Same-employee multiple eligible advances: no live fixture currently proves the consolidated employee-notification behavior for one employee with multiple affected advances.
5. True two-process concurrent commit: source-level row locking and sequential duplicate protection are proven; a dedicated concurrent-process runtime test has not been executed.

Do not repeat any closed waiver harness solely because a new session starts. Only repeat a closed test if a concrete regression, source change, schema change, or failed dependency makes it relevant again.

Exact next gate: notification failure isolation runtime. After that, proceed one gate at a time to audit preservation and final 1410 reconciliation, while retaining the two evidence gaps above.

## 2026-10-06 — GM Salary Advance Waiver: Notification Failure-Isolation Gate Prepared

The waiver runtime matrix remains active at the notification failure-isolation boundary. All earlier waiver gates remain closed and must not be repeated without regression evidence.

Direct source inspection confirmed:
- `ak_transaction_review_notify_event()` catches notification-delivery `Throwable`;
- the installed notification path supports the legacy notification schema through its existing fallback;
- no existing deterministic notification-failure test seam was available.

A narrow inert test-only delivery hook was added, plus:
`tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

The harness will use the real committed waiver execution path, force exactly one notification-delivery failure, verify the execution remains committed and a later notification still proceeds, then restore all test state.

**Runtime status: PENDING USER EXECUTION.**

Exact next command:
`php tools\\run_salary_advance_gm_waiver_notification_failure_isolation_tests.php`

After a PASS, the next gate is audit preservation, followed by final 1410 reconciliation. The same-employee multiple-advance and true two-process concurrent execution evidence gaps remain explicitly open.

## 2026-10-06 — GM Salary Advance Waiver: Notification Failure-Isolation PASSED

The notification failure-isolation runtime gate is **CLOSED/PASSED**.

Exact runtime evidence:
- request `SAR-2026-00004`
- decision `13`
- delivery attempts `2`
- injected failures `1`
- status `executed`
- outstanding `0`
- waiver journal `125`
- schedule overlays `10`
- cleanup restored notifications and request state with zero decision/journal residue.

Next gate: **audit preservation runtime verification**.

Do not repeat closed waiver tests. Open evidence gaps remain the multiple-eligible-advances-per-employee scenario and true two-process concurrent execution.


## 2026-10-06 — GM Salary Advance Waiver: final verification gates PASSED

The GM Salary Advance Waiver verification matrix has now passed its final two gates.

### Audit preservation — RUNTIME VERIFIED / CLOSED

Command:
`php tools\\run_salary_advance_gm_waiver_audit_preservation_tests.php`

Evidence:
- `SAR-2026-00004`
- decision `15`
- original disbursement journal `73`
- temporary payroll `24`
- temporary payroll journal `129`
- repayment `6`
- historical audit rows preserved `6`
- waiver audit rows `3`
- schedule overlays `9`
- cleanup: decision rows `0`, historical journals restored `1`, temporary payroll rolled back `1`, repayment rolled back `1`, schedules restored `1`, audit restored `1`.

The gate proves that waiver execution does not rewrite/delete historical disbursement, payroll repayment, payroll accounting or original schedule evidence. Waiver accounting and waiver-specific audit/schedule evidence are additive.

### Final 1410 reconciliation — RUNTIME VERIFIED / CLOSED

Command:
`php tools\\run_salary_advance_1410_reconciliation.php`

Result:
- ledger balance = `190000`
- live outstanding = `190000`
- total 1410 debit = `220000`
- total 1410 credit = `30000`
- disbursement debit = `220000`
- direct repayment credit = `30000`
- payroll credit = `0`
- waiver-refund debit = `0`
- waiver credit = `0`
- open requests = `5`
- journals/lines = `11 / 11`

The verified reconciliation is:

`1410 balance = disbursement debits + waiver-refund debits - payroll repayment credits - direct-repayment credits - waiver credits`

For the current evidence set:

`220000 - 30000 = 190000`

which exactly matches the live outstanding salary-advance receivable.

The reconciliation harness was narrowed to the actual accounting implementation after source inspection identified `salary_advance_direct_repayment` as an additional 1410 credit reference type. No production accounting logic was changed by the reconciliation fix.

### Waiver feature status

**GM Salary Advance Waiver: RUNTIME VERIFIED / CLOSED at the documented acceptance boundary.**

Do not repeat the completed waiver matrix without concrete regression evidence. Do not reopen Salary Advance Stages 1–6. The two explicit evidence gaps remain:
1. same employee with multiple eligible advances;
2. true two-process concurrent execution.

These are evidence gaps, not failures, and must not be silently relabeled as proven.

The attendance same-page POST scroll-jump issue remains unfinished and out of scope.

## 2026-10-06 — English i18n cleanup: RUNTIME VERIFIED / CLOSED

The i18n cleanup reached its acceptance boundary.

### Static verification
Command:
`php tools\\i18n_gap.php`

Result:
`Files with gaps: 0 | Untranslated Arabic strings: 0`

The scanner reports that every Arabic UI string in its scanned scope has an English compatibility translation.

### Runtime verification
The affected English-mode pages were opened and tested locally by the user using `?lang=en`, including:
- HR Attendance Policy
- HR Payroll
- HR Payroll Policy
- Salary Advance Waiver — FM
- Salary Advance Waiver — GM
- Accounting FM Dashboard
- HR Dashboard
- Reports Center

Runtime result: **PASS**. The user confirmed that everything displayed in English and the pages behaved normally.

This closes the current i18n cleanup item. Do not repeat the scan/runtime pass unless a concrete regression or newly introduced untranslated string is identified.

The GM Salary Advance Waiver remains closed separately; its explicit evidence gaps are unchanged and are not reopened by this i18n checkpoint.

## 2026-10-06 — Attendance Policy ↔ Payroll integration: source-audit defect found and targeted fix applied

The attendance/payroll integration remains **OPEN / PENDING RUNTIME VERIFICATION**.

Before runtime testing, the actual payroll calculation path was inspected. A concrete defect was found in `modules/hr/lib_salary_advance_payroll.php`: `hrSalaryAdvancePayrollRefreshDraft()` calculated `net_salary` as gross minus ordinary deductions and salary-advance deduction, but omitted `attendance_deduction`.

This was inconsistent with the already-implemented attendance-aware salary-advance eligibility calculation, which correctly subtracts attendance deduction when the policy basis is `net_before_advance`.

Targeted fix:
- `modules/hr/lib_salary_advance_payroll.php`
- commit `60adf7f4f428b1754f45b010c552ad93a2155323`

A rollback-only runtime harness was added:
- `tools\\run_hr_attendance_payroll_integration_tests.php`
- commit `20c8f23e5fc239fd3b19f26ec4cd424c08dc9d34`

The harness uses the real attendance-policy and payroll-policy calculation functions, creates only temporary payroll/attendance rows inside one transaction, verifies an explicit working-day absence produces the policy-calculated deduction and corresponding net-salary reduction, verifies a missing attendance row produces zero attendance deduction, then rolls everything back.

**Runtime status: PENDING USER EXECUTION.**

Exact next command:
`php tools\\run_hr_attendance_payroll_integration_tests.php`

If it fails, stop at the exact failure and inspect the root cause before changing anything else.

## 2026-10-06 — Attendance ↔ Payroll integration: RUNTIME VERIFIED / CLOSED

The Attendance Policy ↔ Payroll integration is now **RUNTIME VERIFIED / CLOSED** at the documented acceptance boundary.

Runtime coverage includes:
- working-day absence deduction;
- approved paid leave with zero deduction;
- approved unpaid leave using payroll policy treatment;
- non-working-day absence with zero deduction;
- missing attendance not inferred as absence;
- draft attendance deduction and net-salary refresh;
- payroll/attendance policy effective-date selection;
- attendance deduction reducing the `net_before_advance` salary basis before salary-advance recovery;
- final net salary subtracting attendance and salary-advance deductions;
- paid payroll immutability;
- rollback cleanup.

Final extended gate:
`php tools\\run_hr_attendance_payroll_extended_integration_tests.php`

PASS:
`policy_effective_dates=PASS | net_before_advance=PASS | employee=8 | request=SAR-2026-00007 | period=2026-10 | attendance_deduction=333.33 | salary_advance_deduction=9666.67 | net_salary=0 | paid_payroll_immutable=PASS | rollback=PASS`

The existing CLI warning for `$_SERVER['REQUEST_METHOD']` in `config/functions.php:52` remains a non-blocking observation; no production change was made solely to suppress it.

**Current status: CLOSED.**

The same attendance scroll-jump issue remains unfinished/out of scope.

## 2026-10-06 — FM UI consistency cleanup

A focused UI consistency pass corrected two visible inconsistencies in the FM dashboard quick-action section and the GM salary-advance waiver FM page.

- `modules/accounting/fm_dashboard.php`: the final two quick-action cards — **إدارة الرواتب** and **منظمة فينا الخير** — now have the same fixed-height/card treatment as the other FM quick-action cards, including their missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the top page title section now uses the same FM blue-gradient header treatment as the FM dashboard instead of the previous standalone white bordered card.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`

Runtime browser verification is still required on the local XAMPP application. Do not mark the UI fixes runtime-verified until the user confirms the affected FM pages visually.

## 2026-10-06 — System-wide same-page POST navigation policy: CLOSED

The same-page POST viewport-jump defect has been **runtime verified and closed system-wide**.

The authoritative mechanism is the global policy in `assets/js/app.js`: same-path POST forms are handled in place by default, with explicit opt-outs/exceptions for native navigation. The listener is registered in capture phase, and the response renderer preserves the current viewport while replacing the shared content region.

Runtime evidence: the attendance POST workflow was tested after removing the attendance-specific `data-ak-same-page="1"` attribute, and the user confirmed that the page now remains in place. This proves the global policy rather than a page-specific workaround.

Key commits: `af5fe03e3592972c61ec2821fa7ad0a7f4b7abfd`, `0a06fb57f687f4ac56c623251a7fa38d6dba083d`, `4496a8f6fb4d38a856483b25d856c640a81732ec`, `396576c6bc8d1f26af37a09e80c15909aac06e60`, `8389708b84a9853f164d3b73d6f810dbe4967ef5`.

**Acceptance:** system-wide same-page POST behavior is CLOSED. Do not reopen without a concrete regression. The next task must come from the current canonical documentation and the user's new requirement.

## 2026-10-06 — FM UI consistency cleanup: RUNTIME VERIFIED / CLOSED

The focused FM UI consistency cleanup is now **runtime verified / accepted**.

Verified/accepted changes:
- `modules/accounting/fm_dashboard.php`: the final FM quick-action cards now use the same fixed-height/card treatment, including the previously missing top-border variants and complete descriptions.
- `modules/hr/salary_advance_waiver_fm.php`: the FM salary-advance waiver page title/header now uses the FM blue-gradient header treatment consistently with the FM dashboard.

Implementation commits:
- `8d30f714e302c86c880e835bc5f3ce756189864d`
- `894ec59a82ff11e78cc7788f282df32c630cb840`
- `41d448abb9daa7d917ecedd324b81be7bf9077c0`
- `40d4b3ad552ac0f9a77720cbd112f41bee015aa6`

User runtime result: the FM UI is visually accepted. Do not reopen these UI issues absent a concrete regression.

## 2026-10-06 — Production-preparation database cleanup: RUNTIME VERIFIED / CLOSED

The production-preparation script at `database/production/prepare_production_database.sql` has been corrected and runtime-verified against a disposable copy of the current live database.

Implementation commit:
`5f92a919ec64127428fda5e0adf8b5243ad8554f`

The script preserves only the data explicitly protected by the original production-preparation contract:
- users
- employees
- families
- family children
- sponsors
- sponsorships
- sponsorship children
- sponsor-supervisor assignment history
- supervisor letters
- supervisor-letter assignment history
- roles
- departments
- currencies
- letters
- medical needs
- settings
- accounts, except the original exact eight test account codes

All other current base tables are treated as development/test data and are cleared. No tables are dropped and no physical storage files are deleted.

### Disposable runtime verification

A fresh dump of `ahl_el_kheir` was imported into disposable database `ahl_el_kheir_production_test`, then the corrected script was executed against that disposable database.

Verified preserved counts:
- users: 26
- employees: 25
- families: 2208
- family_children: 4910
- sponsors: 2858
- sponsorships: 3560
- sponsorship_children: 2965
- sponsor_supervisor_assignments: 270
- supervisor_letters: 56
- supervisor_letter_assignment_history: 6
- roles: 14
- departments: 8
- currencies: 4
- letters: 29
- medical_needs: 14
- settings: 18
- accounts: 38

Verified cleanup:
- all 83 non-preserved current tables returned zero rows;
- original test accounts remaining: 0;
- no SQL execution error or warning was reported.

**Disposition: RUNTIME VERIFIED / CLOSED at the disposable-database acceptance boundary.**

Do not rerun this destructive cleanup against the live production database as a verification step. The disposable database is the verified test target; live production execution is a separate deployment action.




## 2026-10-12 — Salary Advance Stage 7 isolated browser acceptance progress

The user submitted the FM rescheduling form for request 7 in the isolated local browser fixture. Read-only SQL against salary_advance_browser_test confirmed:
- plan 1 remains present with its five historical schedule rows and status superseded;
- new plan row ID 8 / plan number 2 is active, starts 2026-11-01, duration six months, and saved monthly cap 10,000.00;
- new plan has five installments totaling 50,000.00 (full recorded outstanding balance), maximum installment 10,000.00, scheduled November 2026–March 2027;
- audit row 2263 records HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE for request 7.

Notification regression root cause: success/error markup on modules/hr/salary_advance_reschedule.php was plain alert-success / alert-danger and did not match the transient-alert selectors in assets/js/notifications.js. Commit b546005f7f7996d1c4f1946de82e9c72563733f7 adds alert-dismissible to those two transient messages only. Local pull, PHP lint, and browser confirmation that the message appears as a toast are not yet evidenced. Payroll/accounting end-to-end acceptance remains outstanding; Stage 7 is OPEN. Canonical ahl_el_kheir was not modified by the test operation.


## 2026-10-12 — Stage 7 payroll acceptance progress and payroll table refinement

The isolated browser workflow has reached November 2026 draft payroll generation in `salary_advance_browser_test`. The application created 22 drafts using payroll policy version 2. Read-only SQL identified payroll row ID 46 for employee ID 8 / EMP-0021: basic salary 10,000.00, salary-advance deduction 10,000.00, net salary 0.00, status `draft`, accounting status `none`, with no accounting entry, journal entry, or payment account yet. This confirms the active November rescheduled installment is reflected in the draft. It does not yet prove repayment application or accounting posting. Do not use the period-wide “approve drafts” action; it affects all 22 records.

Read-only account lookup in the isolated database returned account ID 2 / code 1200 (Bank), ID 37 / code 1410 (Employee Salary Advances Receivable), and ID 14 / code 5200 (Salaries). The next acceptance gate is to approve and pay only payroll row 46 using the page’s per-row actions, then verify exactly one repayment application, schedule/payment linkage, request outstanding balance, paid payroll state, posted accounting entry, and a 10,000.00 credit to account 1410. The canonical `ahl_el_kheir` database must not be modified.

The user also requested a more compact payroll table. Commit `ae7d9fc6209a8ff1366afa96b9fb73720d614249` on `main` removes the Contract and Policy columns, removes redundant currency labels from amount cells, tightens input/typography spacing, adds distinct blue cell borders and a blue-tinted header, and removes now-unused joins from the month-list query. The shared policy summary above the table remains. The user later reviewed the compact layout locally and described it as much better. This is browser visual feedback only; no separate PHP syntax-check result is asserted here.

The user confirmed the rescheduling success/error feedback now appears as a toast after commit `b546005f7f7996d1c4f1946de82e9c72563733f7`. The isolated payroll repayment/accounting acceptance and temporary-environment cleanup are now complete; see the dated Stage 7 acceptance-closure entry below for verified results. Do not restore the temporary routing again or repeat the completed acceptance absent concrete regression evidence.

## 2026-10-12 — HR Salary Advance Stage 7: ACCEPTANCE CLOSED

The browser-driven rescheduling-to-payroll/accounting acceptance passed using the isolated `salary_advance_browser_test` database only. Request 7 (`SAR-2026-00007`, employee 8) was rescheduled to active plan 8 starting 2026-11-01, with five 10,000.00 installments. Payroll row 46 for November 2026 included the 10,000.00 salary-advance deduction and was individually approved and paid. It reached `paid`/`posted`, linked to journal entry 116 (`PAY-46`); journal lines debit salaries expense account 5200 by 10,000.00 and credit salary advances receivable account 1410 by 10,000.00. With net salary zero, no bank line was expected. Schedule row 42 is paid/applied for 10,000.00, one and only one repayment trace records payroll 46 as `applied`, and isolated request outstanding is 40,000.00.

The temporary routing override was removed safely. `.htaccess` now contains exactly `Options -Indexes`; the temporary probe is absent; the protected receipt image remains present and untouched. The local working tree was on `main` and reported only the protected receipt as untracked. Canonical `ahl_el_kheir` read-only checks confirm policy version 1 remains unchanged (`allow_fm_repayment_rescheduling=0`, maximum duration 6 months, monthly cap 10,000.00) and request 7 remains disbursed with 50,000.00 outstanding. Canonical account 1410 query returned a posted balance of 250,000.00; no canonical financial mutation was performed.

**Status: Stage 7 acceptance CLOSED at the verified boundary.** Do not rerun the successful acceptance without concrete regression evidence. Remaining evidence limitations: the same employee having multiple simultaneously eligible advances was not tested in local runtime, and true concurrent production execution was not locally runtime-proven. Do not describe either gap as proven. The compact payroll table was reviewed by the user as much better; this is visual feedback, not a substitute for a separate syntax check.


## 2026-10-12 — Employee incentives: source/design checkpoint

**User decision confirmed 2026-10-12:** direct sponsor creation must automatically set the sponsor's brought-by user to the logged-in employee; sponsor-request conversion must preserve the request's original `brought_by`; win-back rewards go to the employee who handled the successful win-back, only after first payment is confirmed. Existing conversion code already writes `sponsors.brought_by_user_id`, so verify the exact schema before adding any column; do not duplicate an existing field. Historical missing attribution must not be guessed or backfilled from `created_by`. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.

**Attribution rule implemented; local syntax/schema checks passed (2026-10-12):** direct sponsor creation now sets `sponsors.brought_by_user_id` from `Session::getUserId()`. Sponsor-request conversion still preserves the request's original `brought_by` user ID. The user synced `main`, ran `php -l modules\sponsors\create.php` successfully, and confirmed the nullable indexed `brought_by_user_id` column exists locally. A real sponsor-creation INSERT/runtime behavior test remains pending. No incentive policy/ledger/payroll implementation is complete yet. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.


A new requirement covers fixed-amount rewards for (1) financially confirmed monthly sponsorship collections, (2) new sponsor acquisition/reactivation only after the first payment is confirmed, and (3) successful project completion only after both administrative and FM financial closure. Governance and policy decisions are captured in `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`.

Current status at the original source/design checkpoint: implementation was not yet present. Subsequent commits added account 5500, the incentive ledger, policy administration, and the category/custom-field extension. These source changes still require local PHP lint and MariaDB/browser verification. Payroll accounting currently uses expense account 5200 and reconciles expense to `net_salary + salary_advance_deduction`; incentive accounting still requires explicit allocation to account 5500 and must preserve paid-payroll immutability. The data-bearing database snapshot must not be restored into the live database.


**Implementation update (2026-10-12):** direct sponsor creation now includes `brought_by_user_id = Session::getUserId()` in `modules/sponsors/create.php` (commits `aaa7189`, `3bc49b3`). Static inspection confirms 22 SQL placeholders match 22 bound parameters. The user subsequently synced `main`, ran `php -l modules\sponsors\create.php` successfully, and confirmed the local `sponsors.brought_by_user_id` column exists as nullable indexed `int(10) unsigned` with an FK to `users.id`. Local metadata also confirms relevant tables use InnoDB and identifies the source indexes/FKs; see `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. Actual sponsor INSERT behavior remains runtime-unverified. Full incentive policy/ledger/payroll/accounting implementation remains pending.

## 2026-10-12 — Employee Incentives active work

Employee Incentives is in progress. The account 5500 migration, incentive policy/event/award ledger migration, and FM/admin policy administration page have been committed to `main`. Local migration application, PHP syntax checks, runtime behavior, event attribution, project closure integration, payroll scheduling, and accounting posting remain unverified or unimplemented. The detailed continuation document is `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. This is not a completed milestone.

## 2026-10-12 — Salary and incentives portal navigation

The FM dashboard salary-related cards are consolidated into modules/hr/salary_portal.php. The portal presents role-filtered links to salary advances, salary-advance waivers, payroll, and incentive policy. Dashboard and portal changes are committed to main (8bc2be917afaa09d50a3c46f4d13a3922c42648b, 94f5c9c5666a84cea8fc4a161277dba3b707e2a1). Local lint/runtime verification is pending. Employee incentives remain in progress; custom incentive-type semantics need confirmation before schema changes because the current type columns are ENUMs and custom types such as Employee of the Month need a manual award/verification workflow.

## 2026-10-12 — Employee incentive policy continuation

- The incentive policy currency input has been removed; the page now uses system-wide `APP_CURRENCY_CODE=SDG`. The user confirmed the change is visible locally. Commit: `12af8e46b2a9e042322bddf939462b973dd612d0`. PHP lint and runtime validation remain to be recorded.
- Monthly employee performance recognition and certificate generation are explicitly deferred to a separate future feature. Do not implement this within the current incentive work.
- The user approved a database-driven category catalogue plus version-scoped custom policy fields. HR Manager/HR Staff and FM/Admin can create drafts and add fields; activation remains FM/Admin only. The GM/FM agreement remains recorded before activation, with no second GM approval.
- Source changes are committed in `eb92fa5` (catalogue/custom-field migration) and `68758c1` (policy page). Custom categories are manual-review only and do not yet have an award/payment workflow. Monthly performance recognition/certificates remain deferred.

## 2026-10-12 — Employee incentive policy continuation

The user confirmed the SDG currency input removal is visible on the local incentive policy page. Commit `12af8e46b2a9e042322bddf939462b973dd612d0`; local lint/runtime verification remains pending. Monthly performance recognition/scoring/certificates are deferred to a separate future feature. Current active requirement: extensible policy fields FM/HR can add when needed. Confirm HR's permitted draft/metadata actions before changing authorization; FM retains policy activation authority. Custom incentive award types remain a separate schema/workflow requirement because the current type columns are ENUMs.


## 2026-10-12 — Incentive category catalogue and extensible policy fields

Committed to `main`:
- `eb92fa5a42d00c086da0fe6a439511178d8c149b`: migration introduces `hr_incentive_types`, preserves the four built-in codes while converting ENUM-backed rule/event category columns to catalogue-backed strings with foreign keys, and adds `hr_incentive_policy_custom_fields`.
- `68758c17c6363083f798e019c6348bdb596acc8c`: policy page loads category records from the catalogue, supports manual-only category creation, lets HR/FM create policy drafts and add version-scoped custom fields, validates required field values before activation, and restricts activation to FM/Admin.

Verification status: **NOT LOCALLY VERIFIED**. The new migration has not yet been applied to local MariaDB, and PHP lint/browser checks have not been run. New category award issuance/payment is not implemented. Next gate is local sync, migration execution, PHP lint, and role/workflow checks before proceeding to incentive event and payroll/accounting integration.


Migration follow-up: `eb92fa5a42d00c086da0fe6a439511178d8c149b` adds a rerun guard around ENUM-to-VARCHAR conversion. Local MariaDB verification is still required.

## 2026-10-12 — Employee incentive policy category-code hardening
The policy category form no longer accepts a user-entered code. The UI displays a read-only preview derived from the English name, while PHP independently generates the authoritative code and appends a numeric suffix when a code already exists. This prevents manual code typos and ignores forged client-supplied code values. The same source update adds strict validation for the GM/FM agreement timestamp (round-trip date validation, reject future values) and a 2,000-character server-side notes limit.

Commit: `23bdf2a84d81cd86f753c283e462f06f828aa510` (generated-code hardening); the underlying feature and agreement validation are in `1a1ced379bf5f4942cd44d554bbfbbfc2c499bbe`.

User verified the existing category labels render correctly in Arabic and English. Local PHP lint and browser verification of the newly changed code-generation UI are still pending. No policy amounts have been approved; do not activate any test policy.


## 2026-10-13 — Incentive policy GM approval workflow (local verification pending)

GitHub main now contains the policy approval workflow and migration database/migrations/2026-10-13_hr_incentive_policy_gm_approval.sql. Agreement date/notes are no longer required by the new policy form or activation path; legacy columns/data remain untouched. Drafts are submitted by HR/FM, approved/rejected only by general_manager/gm, and activated separately by FM/Admin after approval. Rejection requires a reason, records durable history, and returns the version to editable draft. Full draft editing covers policy name, effective date, category enabled/amount values, and custom-field values.

Boundary: source commits exist, but local pull, migration application, PHP syntax check, and real XAMPP/MariaDB runtime/role tests have not yet been reported. Do not mark this workflow runtime-verified. Continue from docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md.

## 2026-10-13 — Incentive policy custom-field concurrency guard

Commit `c53a5dd6b32f6a737f7d6264df7b033e3383cb45` updates `modules/hr/incentive_policy.php`: policy-management permission is checked before opening a transaction; custom-field creation locks the policy-version row with `SELECT ... FOR UPDATE`, requires the row still be a draft, inserts the field, then commits. This serializes the custom-field insert against submission, which locks the same policy row while validating required fields. Existing exception handling rolls back an open transaction.

The GM approval migration is applied locally and the schema was inspected. PHP syntax checks for the policy page and GM dashboard passed, and the policy page loads. Full approval/rejection/activation runtime verification remains pending because the local database has zero policy versions, rules, and custom fields. No concurrent two-process test has been run; do not claim it has been proven. Award issuance, source-event integration, payroll scheduling, and incentive accounting remain unfinished. Next: pull `main`, inspect the exact diff, lint the changed page, then perform safe local role/state verification without inventing business reward amounts.

## Required future milestone — cross-module payroll/accounting integration audit

**Status: DEFERRED until the current incentive-policy GM approval workflow has completed acceptance testing.** No claim is made here that all payroll/advance/incentive operations are fully integrated with accounting.

Required follow-up: inspect actual source, schema, migrations, and accounting rules across salaries/payroll, additions and deductions, attendance, salary advances and repayment schedules/rescheduling, advance waivers/refunds/cancellations, implemented incentive award flows, journals/transactions, and reversals. Trace each supported flow end to end; independently recalculate gross/net and outstanding balances; reconcile operational records with balanced accounting entries and reports; test rounding, effective dates, period/status filtering, duplicate/idempotent processing, rollback, concurrency, and paid/posted immutability. Record unimplemented integration paths and unresolved accounting-policy questions instead of assuming them. Use isolated rollback-only fixtures in the real local XAMPP/MariaDB environment and document exact evidence. Detailed checklist: `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`.


## 2026-10-13 — Incentive policy role boundary clarified

The user confirmed this is a higher-management workflow involving only HR Manager, General Manager (GM), and Financial Manager (FM). `modules/hr/incentive_policy.php` now limits draft creation/editing, custom-field management, and category management to `hr_manager` and `financial_manager`; review/approval/rejection remains GM-only; activation is FM-only. `hr_staff` and `admin` are no longer authorized substitutes in this workflow. Authorization source change committed as `63534c5fd3aa5b0b3fc8bf640d3ecfb2280f314a`; UI alignment committed as `b065a93a98e1786a2e81702c0fa956b3a414f646`. The policy-creation form is now hidden from GM-only reviewers, and status guidance no longer names Admin as an activation actor.

**Verification boundary:** this is a source-level authorization correction only. Local PHP lint and real XAMPP/MariaDB role-based acceptance are still required. The three accounts previously identified are HR Manager user 32, GM user 2, and FM user 29, but their current login usability has not been established. Do not claim the workflow accepted until runtime tests pass. Do not use HR staff or Admin as test actors.

## 2026-10-14 — Incentive category form cleanup

Commit `e4b0675dbed130a1a5235851df6dbdb4f342b524` removes the visible automatic category-code field and its preview JavaScript from `modules/hr/incentive_policy.php`. Category codes remain server-generated from the English category name. Local PHP lint and browser verification are pending; incentive policy lifecycle acceptance remains in progress.


## 2026-10-14 — Required UI feedback rule: toast, not top-of-page banners

This is a project-wide implementation rule, not an optional page-specific preference. Success, error, warning, and action-result informational feedback must be shown as temporary toast notifications through the centralized `AKNotify.toast` path, near the active workflow/modal when applicable. Do not leave action feedback as persistent banners at the top of a page. For legacy Bootstrap feedback nodes that need centralized conversion on a full-page load, add `data-ak-transient-alert="1"`; server flash nodes may use the established `data-ak-flash` convention. Keep field validation beside the relevant field, and keep genuinely persistent instructional/context panels separate from action feedback (mark them explicitly persistent if their markup matches transient-alert selectors).

**Required page-completion check:** inspect every success/error/warning/info message introduced or modified; verify both full-document POST/redirect loads and same-page/AJAX responses use toast behavior; confirm no action-result banner remains at the page top. Do not assume a page follows this rule merely because another module does. This rule applies to new pages and existing-page changes across the repository.


## 2026-10-14 — Incentive policy toast and English-language coverage update

Source-level review and correction completed on `main`:
- All eight successful incentive-policy actions now obtain their feedback from the stable incentive translation catalog and render through the page's transient-alert markers; the centralized `assets/js/notifications.js` path converts success/error feedback to toast notifications on normal page loads and observes alerts inserted during same-page/AJAX updates.
- Added matching Arabic and English stable catalogs `lang/incentive_policy_ar.php` and `lang/incentive_policy_en.php` with 135 matching keys covering page labels, status/history terms, confirmations, validation messages, and action feedback.
- Localized field-type and Yes/No option labels server-side because the language bridge intentionally does not translate native `option` text nodes; category names, custom-field labels, and currency names now use their English value when English mode is active, falling back to Arabic only when an English custom-field label was not supplied.
- Dynamic validation messages use translated prefixes and localized category/custom-field labels. User-entered policy names, custom-field values, rejection reasons, and other business data remain unchanged rather than being guessed/transliterated.

Repository source checks confirmed both catalogs contain 135 unique keys with no key mismatch, every `t('incentives.*')` key referenced by the module exists in both catalogs, all eight success feedback calls use the catalog, and both success/error alert nodes have `data-ak-transient-alert="1"`. This is **source-level verification only**. Local PHP lint, `php tools\\i18n_gap.php`, and browser checks in Arabic and English—including one successful action toast and one validation-error toast—remain required before marking runtime acceptance complete. Preserve the existing V1 test policy; do not create another policy solely to test notifications.


## 2026-10-14 — Incentive policy review-page presentation follow-up

The user confirmed the Arabic/English language toggle works and supplied local PHP lint results: `modules/hr/incentive_policy.php`, `lang/incentive_policy_ar.php`, and `lang/incentive_policy_en.php` all pass syntax checks. The official `tools/i18n_gap.php` scan reports 85 untranslated strings across 12 other files; `modules/hr/incentive_policy.php` is not listed, so unrelated files remain out of scope. The existing GM view exposed two narrow presentation issues: the system-currency notice was hard-coded in Arabic, and policy category/amount summaries had no visual separation. Commit `e446adee1ea9ae4e76bc5b48ad227926fec442c2` routes the currency notice through `t('incentives.currency_notice')` and styles each category/amount summary as a separated bordered item. This source correction is committed to `main`; local PHP lint and browser confirmation of the updated presentation remain pending. The Arabic creator name is stored user data and was intentionally preserved. V1 remains a draft; do not submit, approve, or activate until the toast acceptance checks and updated presentation are confirmed.


## 2026-10-14 — Employee incentive policy workflow ownership corrected

The user-defined workflow is authoritative: HR Manager owns policy draft creation/editing and submission; GM alone performs final approval/rejection; FM cannot create or submit drafts and can only activate a GM-approved policy. Source commits `21c94362c4f11f4000e050ba5e14160eedbac0fe` and `675bdc36b67ea1b7ede11abef37cdbf5c2340b84` separate these role capabilities and wire post-commit notifications for submission to GM, GM decision to HR, approved-policy handoff to FM, and activation closure to HR/the approving GM.

Status: **SOURCE COMMITTED; LOCAL RUNTIME ACCEPTANCE PENDING.** Do not treat role restrictions or notifications as runtime-verified until tested on the user's XAMPP/MariaDB application. Keep the existing V1 test policy inactive; its reward amounts are not approved for production use.


## 2026-10-14 — Incentive policy rejection recovery and fallback confirmation

GM rejection returns the submitted policy version to an editable HR draft. The previous active policy remains in force unless FM successfully activates a GM-approved replacement; the existing activation transaction is the only path that retires the overlapping previous active version. Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes the page-level gate so FM can reach the activation UI, consistent with the existing activation action guard.

Status remains **source committed; local runtime acceptance pending**. Verify HR/GM/FM access, rejection reason and resubmission, notification handoffs, toast feedback, and fallback behavior. Do not activate the V1 test policy.


## 2026-10-14 — Incentive policy fallback rule

GM rejection returns the policy to HR as an editable draft. The previous active policy remains in force unless FM successfully activates a GM-approved replacement; the existing activation transaction retires the overlapping previous version. Commit `9430f3418afe03ed7fce871a1b369514e4dbbca0` fixes the page gate so FM can reach the activation UI. Status: source committed, local runtime acceptance pending. Verify roles, rejection/resubmission, notifications, toasts, and payroll effective-policy selection. Keep V1 inactive.

## 2026-10-14 — Hide unapproved incentive policies from FM

Commit `1ab2eac4173dc5f4f808865fc10c722058210bb3` changes `modules/hr/incentive_policy.php` so the FM policy list includes only `approved`, `active`, and `retired` versions. Drafts and `pending_gm` submissions are not disclosed to FM before GM approval; HR and GM retain the workflow views they need. This is a source-level correction only; local PHP lint and FM/HR/GM browser verification remain pending.

The existing V1 acceptance policy is still a draft and must remain unactivated. Do not delete it directly with SQL: its approval history and child records are acceptance evidence and may have referential constraints. Reuse V1 for HR edit/submit → GM reject with reason → HR revise/resubmit → GM approve. FM notification is expected at the GM approval handoff, not while the policy remains a draft. Verify actual recipient-role lookup and the persisted notification after approval before declaring delivery fixed. Payroll effective-policy selection remains a separate unverified gate.


## 2026-10-14 — Hide unsubmitted incentive-policy drafts from GM

Source audit found that `modules/hr/incentive_policy.php` filtered the policy list for FM only. Because the GM branch fell through to an unfiltered query, the GM could see HR drafts before submission. Commit `9a309f299c20aab963b5744eeee714bb5b2548b9` now applies explicit role-based visibility: HR sees all policy versions; GM sees `pending_gm`, `approved`, `active`, and `retired` versions but not `draft`; FM sees only `approved`, `active`, and `retired`. A GM-rejected version returned to `draft` is therefore hidden from GM again while remaining editable by HR. This changes list visibility only; it does not alter policy status, approval history, notifications, activation, or the prior-active-policy fallback.

Verification boundary: source change is committed to `main`; local PHP lint and browser acceptance are still required. Verify that the current draft is visible to HR but absent from GM and FM; then verify after HR submits it that it appears to GM as pending approval and remains hidden from FM. Keep the test policy inactive.

## 2026-10-14 — GM approval feedback simplified for clean acceptance test

Per the user's explicit direction, commit `0df1890e12a006cb1cae57c1bf906047178a39fd` removes the GM approval confirmation dialog and success toast from the incentive-policy approval action only. Authorization, transactional status/history recording, HR/FM notifications, and other workflow feedback remain unchanged. Approval success is to be checked through persisted status/history and notifications. Local lint/browser verification remains pending; the test policy cleanup was verified with no matching policy/notification rows and zero related rules, history, custom fields, or events. Do not recreate the policy until the code change is pulled and verified.

## 2026-10-14 — Incentive policy page: remove redundant currency/category notice

Commit `fa733524d67411e08d299c84e7568c79b601316a` removes the explanatory alert above the category section in `modules/hr/incentive_policy.php`. The application currency remains system-configured and continues to be stored with each policy rule; only the page-level notice was removed. The category management heading, category catalogue, and policy rule amount labels remain unchanged. Source change is committed on `main`; local pull, PHP lint, and browser verification are pending. This UI cleanup does not establish that the entire incentive lifecycle or incentive-to-payroll/accounting implementation is complete.


## 2026-10-14 — Employee incentive GM approval feedback correction

Current open verification: GM approval feedback for test policy R3. The approval handler restores the translated success flash/toast and bilingual confirmation. Only the GM approval form opts out of the shared same-page POST interceptor so the central confirmation handler can run before any request is sent; shared JavaScript and other module workflows are unchanged. Guarded test-only reset utility: `tools/reset_incentive_policy_gm_approval_acceptance.sql`. R3 must remain inactive. Source change is committed; local PHP lint, reset execution, cancellation behavior, success toast, persisted history, and HR/FM notification checks remain pending.


## 2026-10-14 — Incentive GM approval notification timestamp discrepancy

The GM approval confirmation and success toast were browser-confirmed, and R3 was approved but not activated. HR/FM notification timestamps predated the approval-history timestamp because the test reset failed to remove stale handoff notifications. Source evidence shows valid UTF-8 title bytes; the fragile test SQL relied on Arabic title literals that were misrepresented along the local PowerShell/CLI execution path. The existing helper suppresses an equivalent unread notification without refreshing its timestamp. No production notification-writer defect is established, so production workflow code remains unchanged.

Commit `75484be186fdf7b935e27151e07604456b2cfa76` changes the test-only reset to ASCII-only body/link matching, exact R3 policy guard, and HR/FM recipient IDs only; it preserves GM's original submission notification and never activates R3. Local execution of the corrected reset and one clean re-approval/timestamp comparison remain pending. Do not claim timestamp verification complete until the local results are supplied.


## 2026-10-14 — Incentive GM approval handoff runtime verified

The local confirmed-approval path is runtime-verified for test policy R3. GM confirmation and success toast appeared; database approval history records GM user 2 at `2026-10-14 13:54:58`; HR notification 175 and FM notification 176 were both created at that exact timestamp. Policy 3 is `approved` and remains inactive (`activated_by_user_id=NULL`, `activated_at=NULL`). The earlier timestamp discrepancy was caused by stale HR/FM test notifications surviving an encoding-fragile reset; the corrected test-only reset uses exact R3 body/link matching and recipient IDs, and the fresh retest aligned timestamps. No production notification-writer defect was established, so production notification code was not changed.

Verification boundary: this confirms the approve/confirm/toast/history/notification/timestamp path only. Confirmation cancellation was not explicitly reported; rejection/resubmission, FM activation, and payroll effective-policy selection remain separate verification gates. Keep R3 inactive and do not touch `ahl_el_kheir_production_test`.


## 2026-10-14 — Incentive FM activation confirmation acceptance

The initial FM activation ran at 14:12:23 despite the prompt disappearing. Source inspection traced the race to the window-capture same-page POST handler preceding document-level confirmation handling. Fix committed on main: `06b08d44afc48887b74a4588f1604e11f5d5e809`; the FM form now uses translated `data-confirm` and opts out of same-page AJAX POST with `data-ak-native-post="1"`.

Local PHP lint passed. After a guarded reset, the user confirmed the FM confirmation dialog and saw the success toast. Database evidence: R3 status `active`, actor FM user 29, timestamp `2026-10-14 14:18:10`. The reset utility's exact timestamp guard was updated in commit `a94080af39f82bcdaa56190c6c8eca264934d9f3`; the incentive continuation record was updated in `c0ed9d4d1c6bf680930dc40d3fc2ed58909b5bf0`.

Remaining gate: update/pull main, execute the guarded reset only against local `ahl_el_kheir`, verify R3 is `approved` with NULL activation fields, then test FM cancellation and verify no activation occurs. Approval history and notifications must remain preserved. Do not touch `ahl_el_kheir_production_test`. Payroll effective-policy selection and incentive payroll integration remain unverified.


## 2026-10-14 — Incentive policy rejection/recovery and GM approval handoff verified

Local UI/database acceptance for R3 policy 3 verified GM rejection with required reason, HR user 32's rejection notification at 14:36:36, HR resubmission (history row 10 at 14:38:35) and GM notification, then GM approval (history row 11 at 14:40:49). HR notification 181 and FM notification 182 were both persisted at 14:40:49 and reference the exact R3 policy. Final policy state is `approved`, not activated (`activated_by_user_id=NULL`, `activated_at=NULL`). The reset used for this retest removed the prior approval-history row and prior HR/FM handoff notifications while preserving submission history and the GM submission notification; document this limitation rather than treating the reset as a full rollback. Do not rerun closed FM activation acceptance without regression evidence. No production notification-writer defect was demonstrated. Incentive award issuance, payroll inclusion, effective-policy selection, and accounting treatment remain separate unimplemented/unverified work.


## 2026-10-14 — Employee incentive accounting separation: account migration committed

The chart-of-accounts/source audit confirmed the existing `5500` Employee Incentives Expense account and that `2300` is specifically reserved for Fina Al-Khair Payable. Added `database/migrations/2026-10-14_hr_employee_incentives_payable_account.sql` to idempotently provision `2400` Employee Incentives Payable as a separate liability. Migration commit: `a281b8cd695e1555dcec86b7c900b1bf691f1562`; continuation documentation commit: `f60545b6d70597875324aa48abe71c5cd1b8736d`.

**Status: source committed; local migration/runtime verification pending.** This does not implement award recognition, payroll inclusion, or settlement. Existing payroll accounting charges salary expense to `5200`; incentives must remain separately traceable through `5500` and payable `2400`, while salary-advance recovery continues through `1410`. Do not reuse `2100` general Payables or `2300` Fina Al-Khair Payable. Next gate: apply/verify the migration locally, then implement award-level recognition/settlement references and explicit payroll incentive linkage after auditing every draft refresh and payment/accounting path. Keep policy R3 inactive and do not touch `ahl_el_kheir_production_test`.


## 2026-10-14 — Incentive payroll/accounting schema extension committed

Migration `database/migrations/2026-10-14_hr_employee_incentives_payroll_accounting.sql` is committed as `211f1e033cf488f1a95e5d76956b4371183d6463`. It introduces a separate `payroll.incentive_amount` component and splits the award journal reference into `recognition_journal_entry_id` and `settlement_journal_entry_id`. This supports separate incentive expense/payable recognition and payable settlement without folding incentive expense into salary account `5200`.

**Status: source committed; local migration/runtime verification pending.** No PHP payroll or award lifecycle integration is claimed complete by this migration. Next gate is source integration with idempotent award recognition, currency and next-open-payroll scheduling, draft refresh, separate `5500`/`2400` accounting, and paid-payroll immutability. Apply migrations only to the intended local `ahl_el_kheir` database after inspecting the SQL; do not touch `ahl_el_kheir_production_test`.


## 2026-10-14 — Employee incentive accounting integration slice committed; runtime pending

The user applied and verified both incentive schema migrations locally: account `2400` is active liability, `5500` is active expense, payroll has `incentive_amount`, and awards have separate recognition/settlement journal references and indexes.

Initial procedural PHP integration is now committed on `main`: `modules/hr/lib_employee_incentives.php` implements award recognition and currency-checked draft scheduling; payroll accounting settles scheduled incentives against `2400` while retaining salary expense on `5200`; payroll UI displays incentive component and total expected payout separately. This is a partial source implementation only. Local PHP lint and runtime accounting tests have not been run, and source-event creation, manager verification/award creation, and employee notifications remain open. Do not describe incentive integration as complete; do not activate R3.



## 2026-10-14 — Employee incentive accounting rollback gate prepared

A focused rollback-only harness is committed at `tools/run_hr_employee_incentive_accounting_rollback_tests.php` (commits `68a005d9`, `5cb05574`). It is prepared but **not runtime-verified**. The test uses an existing policy version and a compatible draft payroll; it validates recognition Dr 5500 / Cr 2400, duplicate idempotency, currency hold, draft scheduling, settlement and award journal linkage, preservation of salary expense 5200 and salary-advance recovery 1410, paid-payroll refresh immutability, and rollback cleanup. It performs no DDL and must run only against local `ahl_el_kheir`.

Exact command after pulling `main`: `& "D:\\xampp\\php\\php.exe" "tools\\run_hr_employee_incentive_accounting_rollback_tests.php"`. Report exact output; do not treat a SKIP as PASS. No source-event hooks, manager award issuance, or employee award notifications are runtime-proven. R3 must remain approved/inactive and `ahl_el_kheir_production_test` untouched. Before concurrency-sensitive event integration, resolve the source-level MAX(JE suffix)+1 journal-code allocation race if the existing allocation convention confirms a safer narrow pattern.


## 2026-10-14 — Employee incentive accounting rollback gate prepared

A focused rollback-only harness is committed at `tools/run_hr_employee_incentive_accounting_rollback_tests.php` (commits `68a005d9`, `5cb05574`). It is prepared but **not runtime-verified**. The test uses an existing policy version and a compatible draft payroll; it validates recognition Dr 5500 / Cr 2400, duplicate idempotency, currency hold, draft scheduling, settlement and award journal linkage, preservation of salary expense 5200 and salary-advance recovery 1410, paid-payroll refresh immutability, and rollback cleanup. It performs no DDL and must run only against local `ahl_el_kheir`.

Exact command after pulling `main`: `& "D:\xampp\php\php.exe" "tools\run_hr_employee_incentive_accounting_rollback_tests.php"`. Report exact output; do not treat a SKIP as PASS. No source-event hooks, manager award issuance, or employee award notifications are runtime-proven. R3 must remain approved/inactive and `ahl_el_kheir_production_test` untouched. Before concurrency-sensitive event integration, resolve the source-level MAX(JE suffix)+1 journal-code allocation race if the existing allocation convention confirms a safer narrow pattern.


## 2026-10-14 — Incentive accounting rollback test passed

The user ran PHP lint and `tools/run_hr_employee_incentive_accounting_rollback_tests.php` on local `ahl_el_kheir`. Both passed. Runtime assertions passed for Dr 5500 / Cr 2400 recognition (SDG 123.45), duplicate-recognition protection, caller-owned currency hold, draft scheduling and repeated refresh, balanced settlement, preservation of salary expense 5200 and salary-advance recovery 1410, award settlement linkage, and rollback cleanup. Harness fixture IDs were payroll 21, employee 16, policy 3; two events, two awards, and two journals were rolled back. This verifies only the tested accounting slice. Business source-event creation, manager verification/award issuance, notifications, and concurrency are still open. R3 remains approved/inactive; `ahl_el_kheir_production_test` remains untouched.


## 2026-10-14 — Incentive journal-code collision retry committed; verification pending

Source inspection found that both incentive recognition and existing `ak_post_transaction_journal()` use the established MAX+1 `JE-######` convention, while `journal_entries` has a unique entry-code constraint. This leaves a concurrent candidate collision possibility. Commit `143aec005e916b9d9a5b1923b82b16b16ca9b447` adds a bounded five-attempt retry only for duplicate entry-code errors in incentive recognition; unrelated PDO errors still propagate. This is source-level hardening only. Rerun PHP lint on `modules/hr/lib_employee_incentives.php` and the rollback-only accounting harness before any event-source integration. Do not change policy R3 or use `ahl_el_kheir_production_test`.


## 2026-10-14 — Win-back reactivation attribution implementation committed; runtime gate pending

The active employee-incentive slice now includes migration `database/migrations/2026-10-14_winback_reactivation_attribution.sql`, helper `modules/administration/lib_winback.php`, integration in `modules/administration/winback.php`, and rollback-only harness `tools/run_winback_reactivation_rollback_tests.php`. Durable first-transition attribution is separated from mutable case handling. The sponsor and campaign state updates are transaction-protected; no award or source event is created at the campaign transition. Apply the migration once to local `ahl_el_kheir`, lint the two PHP files and harness, then run the harness. Local verification passed on 2026-10-14: all three PHP lint checks passed, and the rollback-only harness passed atomic sponsor/campaign state, durable actor/timestamp, repeated-transition blocking, and rollback cleanup (campaign 3, sponsor 1). Reactivation-payment qualification, ≥90-day lapse proof, HR Manager verification/award issuance, and notifications remain open. Keep R3 inactive and `ahl_el_kheir_production_test` untouched.


## 2026-10-14 — Win-back reactivation attribution passed local rollback-only acceptance

The user pulled commit `958a4644160071ea573db15f35e92f0f2d738c9c`, applied `database/migrations/2026-10-14_winback_reactivation_attribution.sql` to local `ahl_el_kheir`, and ran PHP 8.2 lint on `modules/administration/lib_winback.php`, `modules/administration/winback.php`, and `tools/run_winback_reactivation_rollback_tests.php`; all three reported no syntax errors. The rollback-only harness reported:

- `PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked`
- `PASS | rollback_cleanup=PASS | campaign=3 | sponsor=1`

This proves the harnessed atomic sponsor/campaign transition, durable actor/timestamp, blocked repeated transition, and rollback cleanup on the local database. It does not prove concurrent two-process execution or the full incentive lifecycle. Next work remains the reactivation source-event qualification at the financially confirmed payment boundary, using confirmed payment history to prove the 90-day lapse and durable reactivation actor, with idempotency and HR Manager verification. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation payment qualification committed; local acceptance pending

The confirmed-payment hook now checks a posted linked transaction and posted journal, a durable successful win-back transition, a previous confirmed monthly-sponsorship payment at least 90 days before the transition, and the first confirmed monthly-sponsorship payment created after the transition. The event remains `pending_verification`; it does not issue an award. Recipient user and employee IDs are snapshotted on source events so the win-back actor is distinct from the payment reviewer. The collection hook also snapshots the collector's user/employee relationship. Missing or ambiguous employee mappings produce no event. Same-second competing payment confirmations are conservatively held rather than risking duplicate awards.

New migration: `database/migrations/2026-10-14_hr_incentive_event_recipient_snapshot.sql`. New rollback-only harness: `tools/run_hr_employee_incentive_reactivation_rollback_tests.php`. The existing collection source-event harness now checks recipient snapshots. **Local acceptance is pending**: apply the recipient-snapshot migration once to `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php`, `modules/accounting/fm_review_queue.php`, and both incentive source-event harnesses, then run both harnesses. No local runtime result is claimed for this new slice. HR Manager verification/award issuance, employee notifications, new-sponsor/project hooks, and end-to-end lifecycle testing remain open. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`.


## 2026-10-14 — Reactivation incentive wiring paused for actor-attribution decision

Source review of the actual win-back UI found that its “confirm return” form has no field for the employee who actually reactivated the sponsor. The action records the logged-in confirmer, while `handled_by` is mutable and the contact outcomes do not identify a successful reactivator. Therefore the current confirmer must not automatically be treated as the incentive recipient. The reactivation qualification helper and rollback-only test are committed, but the production call from `modules/accounting/fm_review_queue.php` is intentionally withheld until this business attribution is decided. The collection hook remains separate and snapshots the collector. Do not apply the new recipient-snapshot migration or run the new reactivation harness yet; first decide whether the confirmer is, by definition, the reactivating employee, or the workflow must require a separate employee selection and preserve the confirmer independently. No runtime acceptance is claimed for the reactivation qualification slice.


## 2026-10-14 — Explicit win-back reactivator attribution and confirmed-payment hook (local gate pending)

The agreed recipient is the employee who actually reactivated the sponsor, not automatically the user who confirms the return. The win-back UI now requires selecting a uniquely mapped, currently working employee; server code revalidates the mapping and stores that user in `reactivated_by_user_id`. The confirmer is stored separately in `reactivation_confirmed_by_user_id`; `handled_by` remains mutable operational ownership. Legacy campaigns without the new confirmer snapshot are excluded from reactivation incentive qualification; the system does not assume their earlier actor attribution was correct. The FM confirmed-payment transaction now calls the reactivation qualification helper, which checks posted transaction/journal evidence and a 90-day prior-payment lapse and records only a pending-verification event. New migration: `database/migrations/2026-10-14_winback_reactivation_confirmer.sql`. Local acceptance is pending; see `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for exact migrations and rollback-only tests. Do not use `ahl_el_kheir_production_test` or activate policy R3.


## 2026-10-14 — New-sponsor incentive source event committed; local gate pending

`hrIncentiveRecordNewSponsorPaymentEvent()` is wired into the existing FM confirmed-payment transaction. It checks the linked posted transaction/journal, first confirmed monthly payment, sponsor attribution, unique employee mapping, and exclusion of a qualifying reactivation campaign. It records only a `pending_verification` event with recipient snapshots; no award is issued. Rollback-only harness: `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php`. Local lint/runtime acceptance remains pending; see `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md`. Project source/allocation, HR Manager verification/award issuance, notifications, and end-to-end acceptance remain open.

## 2026-10-14 — Employee incentives continuation boundary

The HR Manager-only incentive verification and award-issuance page is committed on `main` at `f0cef4f4`, with follow-up commits `02bb9eef` and `4e410198`; the continuation record was updated at `a4805b45`. The page and dashboard link are **not yet locally linted or runtime-verified**. Next: pull `main`, lint the two PHP files, and perform a safe rollback-only verification/award acceptance check on `ahl_el_kheir`. Project-completion events remain blocked pending approved allocation workflow; employee scheduling notifications remain open. Do not rerun closed incentive source-event/accounting tests absent regression evidence, and keep R3 inactive.


## 2026-10-14 — HR incentive verification rollback harness prepared (runtime pending)

Added `tools/run_hr_incentive_verification_rollback_tests.php` on `main` (commit `9d7ab5e2c442b7df8e72bb9b36b60afcdb7c2040`). The harness creates a synthetic collection event using an existing eligible positive collection rule, verifies the snapshotted active working employee relationship, performs the same pending-to-verified and award-insert sequence used by `modules/hr/incentive_verification.php`, invokes the production recognition helper, checks the balanced Dr 5500 / Cr 2400 journal and duplicate-recognition idempotency, and rolls back all synthetic rows/journals. It performs no DDL, policy mutation, payroll mutation, or commit. This is a rollback-only database integration harness, not an HTTP/session/CSRF browser test.

The user's local read-only inspection found policy version 3 is approved (not active), with an enabled collection rule of 1,000.00 SDG; accounts 5500 and 2400 are active with the expected expense/liability types; one active `hr_manager` user exists; and no pending events currently exist. These observations do not prove verification runtime behavior. PHP lint and execution of the new harness against local `ahl_el_kheir` remain pending. Run only `tools/run_hr_incentive_verification_rollback_tests.php`; do not rerun previously passed source-event/accounting harnesses without regression evidence. Keep policy R3 inactive and do not use `ahl_el_kheir_production_test`. Employee notification after award scheduling remains open, and project-completion verification remains blocked pending approved allocation workflow.


## 2026-10-14 — HR Manager verification/award rollback acceptance passed locally

The user pulled `main`, PHP-linted `tools/run_hr_incentive_verification_rollback_tests.php` successfully, and ran it against local `ahl_el_kheir`. Exact runtime evidence:

`PASS | hr_manager_fixture=available | recipient_eligibility=PASS | pending_to_verified=PASS`

`PASS | award_creation=PASS | recognition=Dr5500/Cr2400 1000 SDG | duplicate_recognition=PASS`

`PASS | rollback_cleanup=PASS | policy=3 | employee=1`

This verifies the tested database path: recipient/employment eligibility, pending-to-verified transition, award creation, posted balanced incentive recognition, duplicate-recognition idempotency, and cleanup after rollback. It is not an HTTP/browser/session/CSRF test and does not prove the employee notification requirement. Policy version 3 was approved and its enabled collection rule was used only as test input; the policy was not activated or changed. The synthetic rows and journals were rolled back. Do not rerun this passed harness absent regression evidence. The next open task is to inspect the actual notification table/helper contract and implement failure-isolated employee notification when an award becomes scheduled for an eligible draft payroll. Project-completion award verification remains blocked pending approved recipient allocations. Do not use `ahl_el_kheir_production_test` or activate policy R3.


## 2026-10-14 — Incentive payroll-scheduling notification outbox added; local acceptance pending

Added migration `database/migrations/2026-10-14_hr_incentive_notification_outbox.sql`, production logic in `modules/hr/lib_employee_incentives.php`, and `tools/run_hr_incentive_notification_outbox_tests.php`. When a verified award is newly assigned to a draft payroll, a unique outbox row is inserted in the same transaction as award scheduling. Delivery is attempted only after that transaction commits. The notification insert and outbox `sent` transition share their own transaction; a failure leaves the item pending, records/logs the failure where possible, and does not roll back the committed payroll/award scheduling. Repeated refreshes cannot create a second outbox item for the same award, and concurrent dispatchers serialize on the outbox row.

The implementation uses the base `notifications` columns confirmed in the user's local schema and does not require the optional `reference_id`/`reference_type` columns (the local read-only schema query did not show those columns). The notification is in-app only and says the incentive is scheduled in the stated payroll period, subject to normal payroll approval/payment. The acceptance harness injects one delivery failure, checks scheduling remains committed and retryable, retries successfully, checks recipient/amount/period and duplicate protection, then removes its temporary event/award/outbox/notification and restores the original draft payroll incentive total.

**Local acceptance is pending.** Pull `main`, apply the new migration once to local `ahl_el_kheir`, lint `modules/hr/lib_employee_incentives.php` and `tools/run_hr_incentive_notification_outbox_tests.php`, then run the new harness. It uses a safe eligible draft-payroll fixture and reports `SKIP` if none exists or an earlier pending outbox item needs investigation. Do not use `ahl_el_kheir_production_test`, activate policy R3, or rerun already-passed source-event/verification/accounting harnesses without regression evidence. Project-completion award verification remains blocked pending approved recipient allocations.


## 2026-10-14 — Incentive payroll-scheduling notification outbox: local acceptance PASSED

The user pulled `main`, applied `database/migrations/2026-10-14_hr_incentive_notification_outbox.sql` to local `ahl_el_kheir` (the table was initially absent), and PHP 8.2 lint passed for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_incentive_notification_outbox_tests.php`.

Exact local runtime evidence:

```
HR incentive scheduling notification failed for outbox 1: TEST_INJECTED_INCENTIVE_NOTIFICATION_FAILURE
PASS | scheduling_commit_survives_notification_failure=PASS | outbox_retryable=PASS
PASS | retry_delivery=PASS | correct_recipient_amount_period=PASS | repeated_dispatch_no_duplicate=PASS
PASS | cleanup=PASS | payroll_amount_restored=PASS
```

The injected delivery failure was isolated from the already-committed payroll/award scheduling. The outbox remained retryable; a subsequent dispatch delivered the correct in-app notification and repeated dispatch did not create a duplicate. Test cleanup passed and the original payroll incentive amount was restored. The warning line is the deliberately injected failure, not an acceptance failure.

**Acceptance boundary:** this proves the tested local database scheduling/outbox/retry/idempotency path. It does not prove browser/session/CSRF behavior, employee-facing browser UX, email/push delivery, true two-process concurrency, or the complete incentive lifecycle. The notification is in-app only. Project-completion verification remains blocked pending the approved recipient-allocation workflow. Keep policy R3 inactive, do not use `ahl_el_kheir_production_test`, and do not rerun already-passed incentive harnesses absent concrete regression evidence.

**Next open gate:** inspect/test the actual employee-facing notification UX in the local application and verify HR Manager page authorization in browser/session context, without reopening the passed rollback-only harnesses.

## 2026-10-15 — HR incentive policy notification boundary

The HR Manager policy creator is to receive in-app notification only for two outcomes: (1) GM rejection, including the rejection reason, and (2) FM activation. The GM-approval handler previously also notified the HR creator; that extra notification was removed in commit `2c47e5aa6691b735f996ebf7c0c2091926698d54`. The FM-awaiting-activation notification remains directed to FM users. The existing post-commit HR notification call after successful FM activation remains in place.

**Verification boundary:** this is a narrow source change; local PHP lint and browser/runtime verification are still required. Policy R3 is currently approved but unactivated; its effective date is 2026-10-14, so it cannot be activated on 2026-10-15 under the existing no-retroactive-activation guard. Do not directly update its status/date in the database. To test the successful activation notification, use a new policy through the normal HR → GM → FM workflow with a valid current/future effective date, then verify the committed active status and the notification row for the HR creator. If activation commits but the notification is absent, inspect delivery evidence before changing the shared notification helper. Do not use `ahl_el_kheir_production_test`.

## 2026-10-15 — HR incentive policy notification workflow: runtime acceptance PASSED

The user confirms the full policy-governance workflow was exercised locally and is working, including the HR creator's rejection notification with the GM's reason, no HR notification on GM approval, the FM notification that an approved policy awaits activation, and the HR creator's notification after successful FM activation. This closes the targeted policy-notification acceptance task; do not repeat its stages or reopen the source change without concrete regression evidence.

The source change removing the HR notification on GM approval is commit `2c47e5aa6691b735f996ebf7c0c2091926698d54`. The local FM reset script was run against `ahl_el_kheir`; its exact-state guard returned `guarded_activation_matches=0` and `reset_rows=0`, leaving policy R3 unchanged (`approved`, activation fields NULL, effective date `2026-10-14`). A read-only query found no approved policy with `effective_from >= CURRENT_DATE` at that checkpoint. The user subsequently completed and confirmed the valid full workflow; the successful workflow must not be conflated with the no-op R3 reset.

**Scope boundary:** this acceptance closes only the HR incentive policy governance/notification flow. It does not close the full employee-incentives lifecycle. Remaining open work includes the employee-facing notification UX and HR Manager authorization in browser/session context, plus project-completion verification/approved recipient allocations. Do not rerun already-passed rollback-only incentive harnesses or use `ahl_el_kheir_production_test` absent concrete regression evidence.



## 2026-10-15 — Incentive policy approval withdrawal to HR draft (verification pending)

Added a GM-only audited path to withdraw approval from an unactivated policy and return it to HR draft mode. A reason is mandatory; approval history records `returned_to_draft`; HR creator and FM notifications are sent after commit. Already-activated policies are explicitly excluded. Migration: `database/migrations/2026-10-15_hr_incentive_policy_approval_withdrawal.sql`. Source commit: `a9501f5f41e4f959a734d175cf14523c49c1e6d2`; migration commit: `cb522dcee4b2e78e03f8d4c97402a61ca7e23d6c`. **Not locally verified yet**: pull `main`, apply the migration to `ahl_el_kheir`, PHP-lint `modules/hr/incentive_policy.php`, then test GM withdrawal and HR editing in the browser. R3's effective date must be corrected through HR edit to a valid date and must not bypass the later R4 policy's activation chronology. Do not directly alter policy status or use `ahl_el_kheir_production_test`.


## 2026-10-15 — Employee incentive notification UX: browser acceptance PASSED
The user confirms live notification delivery was browser-verified. In the controlled employee test, the notification appeared in the live UI, and the notification-history page displayed the Arabic body correctly when inserted using UTF-8 bytes. The test row was safely removed: `rows_deleted=1`, `remaining_test_rows=0`, and the recipient's remaining notification/unread counts were both zero. This closes the employee-facing in-app notification UX gate; it does not prove email/push delivery, true two-process concurrency, or the complete incentive lifecycle. HR Manager browser/session authorization and project-completion recipient-allocation/verification remain open. Next local gate from the latest continuation checkpoint is the audited GM return-to-draft policy action and HR editing workflow. Do not rerun already-passed incentive harnesses without regression evidence or use `ahl_el_kheir_production_test`.


## 2026-10-15 — HR incentive verification authorization PASSED; active policy checkpoint corrected

The user reports the local browser/session authorization acceptance passed:
- Existing HR Manager account: the dashboard link was visible and the incentive verification page opened normally.
- Existing HR Staff account: the verification link was absent and direct access to `modules/hr/incentive_verification.php` was denied/redirected as expected.
- No additional details or failure symptoms were reported.

This closes only the HR Manager browser/session authorization gate. Do not rerun the already-passed source-event, accounting, verification rollback, or notification-outbox harnesses without concrete regression evidence.

**Policy-state correction from the user's actual local browser screen (2026-10-15):** the page shows one policy, `ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5`, Version V1, status active (`سارية`), effective date `2026-10-15`; its four enabled rule amounts are collection 1,000 SDG, new sponsor 2,000 SDG, reactivation 1,000 SDG, and project completion 1,500 SDG. The displayed approval history shows HR submission at `2026-10-15 09:19:58` and GM approval at `2026-10-15 09:20:10`. This is user-reported local UI evidence, not an independent database query.

This current R5 observation supersedes older continuation instructions that describe R3 as the active testing target or direct the next session to return R3 to draft. **Do not reset, edit, deactivate, or otherwise modify active R5; do not create another policy unless the user explicitly agrees.** Do not change policy status/effective dates directly in SQL. Preserve the prior historical R3/R4 notes as chronology only, not as current instructions.

**Next open engineering unit:** inspect the actual project-completion lifecycle/source integration and existing `hr_incentive_project_allocations` schema before proposing implementation. Project-completion incentive verification remains intentionally blocked until the approved recipient-allocation workflow is defined and implemented. Do not infer who qualifies, how percentages are assigned, who approves the allocation, or rounding/residual treatment from the schema alone. Preserve project lifecycle/approval evidence and existing accounting boundaries. No project-completion source changes or tests are claimed in this checkpoint. Keep `ahl_el_kheir_production_test` untouched.


## 2026-10-15 — Project-completion incentive source audit (design gate remains open)

Source inspected on `main`: `modules/hr/incentive_verification.php`, `modules/hr/lib_employee_incentives.php`, `modules/projects/view.php`, `modules/projects/project_lib.php`, `modules/accounting/fm_review_queue.php`, and `database/migrations/2026-10-12_hr_employee_incentives_ledger.sql`.

Findings:
- `modules/hr/incentive_verification.php` explicitly refuses to verify a `project_completion` event until the approved recipient-allocation workflow exists. Keep this guard.
- The ledger migration already defines `hr_incentive_project_allocations` with `event_id`, `employee_id`, `recipient_user_id`, `allocation_percent`, `reward_amount`, `currency_code`, `approved_by_user_id`, `approved_at`, and `allocation_note`. This schema alone does not define the authorized approver role or how allocations are proposed/calculated.
- The project page's `change_status` action updates `project_lifecycle.lifecycle_status` to `completed` and writes project status history in a transaction. In the inspected completion path, there is no incentive-event creation call and no separate formal-acceptance record established by that action.
- `modules/hr/lib_employee_incentives.php` permits `project_lifecycle` as the source table for a project-completion event, but the inspected project completion path does not call the event recorder. Do not claim that the hook exists.
- The project's current active team can be read from `project_team` (`unassigned_at IS NULL`), but team membership by itself does not establish eligibility for a completion reward.

**Implementation remains blocked on business-rule decisions, not on removing the guard:** define the authoritative formal-acceptance evidence/transition; who may propose recipients; whether recipients are manually selected or derived from some approved evidence; who approves the allocation and under which existing role; whether allocation percentages must total exactly 100%; and how currency rounding/remainder is assigned. Preserve existing audit/accounting boundaries. No project source code, schema, policy, or incentive data was changed in this source-audit step. Active R5 remains untouched; do not create another policy unless the user explicitly agrees. Do not use `ahl_el_kheir_production_test`.


### Additional existing project-completion acceptance constraint — preserve

Prior incentive-design discussion established that a project-completion award must remain blocked until **administrative project closure and FM financial closure are both evidenced**, eligible team recipients have explicit approved allocations, and those allocations are approved before award issuance. A project lifecycle status such as `completed` (or a project-screen stage/status alone) is not sufficient proof. This constraint is binding alongside the unresolved decisions above; do not implement event qualification from `lifecycle_status='completed'` alone.


## 2026-10-15 — Project-completion incentive automation implemented in source; local acceptance pending

The user confirmed the governing rule: PM administrative closure starts processing; awards are issued only after durable FM financial closure and a fresh controlled-balance check (existing tolerance 0.009). Each eligible employee on the active project team at PM closure receives the full active-policy project-completion amount (R5 currently shows 1,500 SDG per eligible employee). There is no separate per-award FM/HR approval. The allocation is per employee, not a shared pool. Active policy R5 was not changed.

Source commits on `main`:
- `6166c6f1` — migration `database/migrations/2026-10-15_hr_incentive_project_auto_allocation.sql` allows NULL approval metadata for system-generated allocations; it does not invent a human approver.
- `e1507598`, `1070775c`, `4e505fa6` — incentive helper snapshots active, working, unambiguously mapped team employees within the PM closure transaction; creates per-employee full-policy allocations; requires the durable `FM_FINANCIAL_CLOSURE` audit row, administrative closure, and a rechecked controlled balance before idempotent award/recognition processing; refreshes the earliest current/future draft payroll when available and relies on the existing notification outbox.
- `4cdcab41`, `896c6c40` — starts and snapshots the incentive event atomically with PM closure; a processing error rolls back that closure attempt instead of silently losing the recipient snapshot.
- `bc8b864c`, `9a879bae` — invokes processing from both FM financial-closure paths and retries processing on later FM project-page loads when durable closure evidence already exists.
- `2836d5ab` — updates the HR verification page's project-event text and reports automatic award totals.

The prior requirement for human-approved project allocations is superseded by the user's explicit newer instruction. The old approval columns remain for historical/manual rows but are NULL for automated rows. Project-completion awards are not processed by the HR Manager's manual verification form.

**Verification boundary:** these are source commits only. No PHP lint, migration application, local MariaDB runtime test, or browser acceptance has been run for this new slice. Do not describe the automation as verified. Next local gate: pull `main`; inspect and apply the new migration once to local `ahl_el_kheir`; PHP 8.2 lint the changed PHP files; then run a purpose-built rollback-only project closure/award test before exercising a real project. Never use `ahl_el_kheir_production_test`; never modify active policy R5; do not rerun already-passed incentive/accounting/notification harnesses absent regression evidence.


### Follow-up — project closure rollback harness added; local acceptance pending

Added `tools/run_hr_project_incentive_closure_rollback_tests.php` (commit `df40dc1c`). It exercises the automatic project allocation/award path inside an outer transaction and rolls back its fixture, journals, and payroll changes. The processor supports caller-owned transactions (commit `2bff519b`). A missing migration or safe project fixture results in `SKIP`, not PASS.

Next: pull `main`, inspect/apply `2026-10-15_hr_incentive_project_auto_allocation.sql` only if needed, lint the four changed PHP source files plus the harness with local PHP 8.2, and run only the new harness. Local lint/runtime acceptance remains pending. Keep active R5 unchanged; never use `ahl_el_kheir_production_test`.


## 2026-10-15 — Project incentive rollback harness safety refinement

The harness no longer skips all coverage solely because every eligible closed project has a positive controlled balance. It can use an existing eligible closed project to test that project-completion awards remain blocked while the controlled balance is positive, and that the temporary event, recipient snapshot, and audit evidence roll back. It never edits posted accounting records to manufacture a zero balance. The full award/recognition branch still requires a genuine eligible zero-balance project and must remain reported as SKIP when none exists. Local execution and PHP lint are pending after pulling this commit. Active policy R5 and live accounting data are unchanged.


### 2026-10-15 — Project incentive harness SQL failure: root cause and correction

The first local execution of `tools/run_hr_project_incentive_closure_rollback_tests.php` failed with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'currency_code' in 'field list'`. Source inspection isolated the invalid reference to `project_lifecycle.currency_code` in `hrIncentivePrepareProjectCompletion()`; project lifecycle does not expose a source currency field. Commit `920d4221122a65a8579e4bed5ef289e64a4934db` removes that column from the query and leaves `hr_incentive_events.source_currency_code` NULL for this non-monetary source event. The award currency remains sourced from the active policy rule and stored on the recipient allocation/award. This correction has not yet been locally linted or runtime-tested. Next: pull `main`, lint `modules/hr/lib_employee_incentives.php` and the project incentive rollback harness, then rerun only the rollback harness against `ahl_el_kheir_incentive_test_20261015_r1`. Do not alter active R5, live accounting, or fabricate funding returns. A later SQL failure must be diagnosed against the actual schema before further edits.


### Local verification result — project incentive positive-balance rollback harness (2026-10-15)

User ran the harness on local PHP 8.2 against disposable database `ahl_el_kheir_incentive_test_20261015_r1` after pulling the source correction. Both `php -l modules/hr/lib_employee_incentives.php` and `php -l tools/run_hr_project_incentive_closure_rollback_tests.php` passed. Runtime output:

- `PASS | project_team_snapshot=1 | no_approval_metadata=PASS | positive_balance_award_block=PASS`
- `PASS | rollback_cleanup=PASS | project=10 | controlled_balance=30000.00`
- `SKIP | award-recognition success branch | No existing eligible closed project has a zero controlled balance; no accounting records were fabricated or altered.`

This verifies the positive-controlled-balance block and transactional cleanup in the disposable DB only. It does **not** verify successful award creation or recognition journals, nor browser/runtime behavior on a real project. The success branch remains open pending a legitimate eligible closed project with controlled balance <= 0.009. Do not fabricate funding returns or edit posted accounting records to create one. Active policy R5 and live accounting were not used or changed by this test.

## 2026-10-15 — Project incentive successful-path harness (runtime pending)

The existing negative project-incentive harness remains passed: positive controlled balance blocks awards and all temporary changes roll back; its success branch was skipped because no existing eligible closed project had zero controlled balance. To avoid changing posted financial evidence, a separate harness was added on `main`: `tools/run_hr_project_incentive_success_rollback_tests.php`, commit `5b6fb97d975bdc3e591fa811ca55b2a214092bfc`.

The new harness is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, checks that the active policy is the protected R5 acceptance policy, and creates a synthetic project with no funding, posted expenses, or returns. It snapshots an eligible Project Supervisor before simulated administrative closure, verifies that a zero balance alone cannot issue an award, then tests the durable FM closure gate and the full configured reward's Dr 5500 / Cr 2400 recognition journal. Everything is inside a transaction that is rolled back. **Runtime status: PENDING local PHP lint and execution.** Do not claim award-recognition success until user-run output passes.

## 2026-10-15 — Project completion incentive recognition: RUNTIME VERIFIED

User locally ran `tools/run_hr_project_incentive_success_rollback_tests.php` on disposable database `ahl_el_kheir_incentive_test_20261015_r1`; PHP syntax and runtime both passed.

Evidence:
- Synthetic project had no funding, posted expenses, or returns.
- Eligible Project Supervisor was assigned before the closure-time recipient snapshot.
- Full R5 project-completion reward: `1500.00 SDG`.
- Without durable FM financial-closure evidence, award processing was blocked.
- With the FM gate satisfied and zero controlled balance, award recognition succeeded.
- Recognition journal passed the Dr 5500 / Cr 2400 full-amount check.
- Transaction rollback cleanup passed; no test project, award, journal, or temporary FM audit was left behind.

This closes only the project award/recognition successful-path test gate. Payroll inclusion, post-commit employee notification delivery, and the real browser PM/FM closure workflow remain separate unverified gates. Do not rerun this passed harness absent regression evidence.


## 2026-10-16 — FM sponsor-payment approval/incentive integration

Source checkpoint only: the approval operation is extracted into a shared no-DDL business function and a rollback-only integration harness covers success plus injected incentive-event failure. Local PHP syntax/runtime acceptance remains pending; do not report this gate as closed until the user runs the harness against `ahl_el_kheir_incentive_test_20261015_r1`. Active R5 and the live database must remain unchanged.


## 2026-10-16 — FM sponsor-payment approval/incentive integration CLOSED at harness boundary

User-reported local PHP 8.2 lint passed for the five relevant accounting files and harness. Against `ahl_el_kheir_incentive_test_20261015_r1`, output was:
```
PASS | shared_FM_approval=PASS | posted_transaction_journal=PASS
PASS | new_sponsor_pending_verification=PASS | no_premature_award=PASS | success_rollback=PASS
PASS | injected_incentive_failure=PASS | payment_transaction_journal_rollback=PASS
```
Do not rerun absent concrete regression evidence. Active R5 and live `ahl_el_kheir` remain unchanged.

New focused test added: `tools/run_hr_project_incentive_payroll_rollback_tests.php`, with its PHP syntax check added to CI. It targets project-specific draft payroll inclusion; local lint/runtime are pending. Next: run only that test against `ahl_el_kheir_incentive_test_20261015_r1`. The real PM-to-FM browser workflow remains open.


## 2026-10-16 — FM sponsor-payment approval/incentive integration CLOSED at harness boundary

User-reported local PHP 8.2 lint passed for the five relevant accounting files and harness. Against `ahl_el_kheir_incentive_test_20261015_r1`, output was:
```
PASS | shared_FM_approval=PASS | posted_transaction_journal=PASS
PASS | new_sponsor_pending_verification=PASS | no_premature_award=PASS | success_rollback=PASS
PASS | injected_incentive_failure=PASS | payment_transaction_journal_rollback=PASS
```
Do not rerun absent concrete regression evidence. Active R5 and live `ahl_el_kheir` remain unchanged.

New focused test added: `tools/run_hr_project_incentive_payroll_rollback_tests.php`, with its PHP syntax check added to CI. It targets project-specific draft payroll inclusion; local lint/runtime are pending. Next: run only that test against `ahl_el_kheir_incentive_test_20261015_r1`. The real PM-to-FM browser workflow remains open.


## 2026-10-16 — Project incentive payroll rollback harness updated; verification pending

The project payroll harness initially skipped because no eligible current/future draft payroll existed. Read-only diagnostics confirmed October 2026 salary history and working employment-state evidence for active Project Supervisors, plus payroll policy version 1 effective 2026-10-01. Commit `797f89afe848df39fee26a6f16bae6f2854c3c03` updates the harness to create a temporary draft payroll inside its existing rollback transaction when needed, using the production payroll INSERT shape and active effective policy. It guards against existing current-period payroll rows and other verified unscheduled awards and checks that the temporary payroll disappears after rollback. **Not yet locally linted or runtime-verified.** Next: pull `main`, lint the harness, and execute only `tools/run_hr_project_incentive_payroll_rollback_tests.php` against `ahl_el_kheir_incentive_test_20261015_r1`. Active R5 and live accounting remain untouched.
## 2026-10-16 — Project-completion incentive payroll integration: PASS

The user pulled `main`, confirmed PHP 8.2 syntax validation passed for `tools/run_hr_project_incentive_payroll_rollback_tests.php`, and ran the rollback-only harness against the disposable incentive test database. All reported checks passed: award-to-draft-payroll linkage, incentive amount increase, unchanged net salary, pending notification outbox row, cleanup, temporary payroll rollback, R5 reward of 1,500.00 SDG, and the included project award/FM/recognition checks.

**Status:** CLOSED at the rollback-harness boundary. Active policy R5 and live database were not changed by this test. The pending outbox row proves enqueue state only; actual employee notification delivery/experience and real PM-to-FM browser workflow remain separate, unverified acceptance gates. Do not rerun this test without regression evidence.

## 2026-10-16 — Project incentive financial-closure draft-allocation safety guard (processor rollback gate passed; FM closure-path verification pending)

A source audit found that the controlled balance intentionally counts posted funding allocations only, but FM financial closure could still be recorded while a draft allocation remained. The same gap could allow the project-completion incentive processor to proceed if a caller supplied a zero balance and durable closure audit evidence existed.

The targeted correction adds a reusable draft-allocation predicate, blocks explicit FM financial closure while any draft allocation remains, and lets a valid funding return complete while deferring automatic financial closure/PM completion notification when a draft allocation remains. The incentive processor independently locks/checks for a draft allocation before award or recognition-journal processing and preserves caller-owned transaction semantics.

Added rollback-only regression harness: `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. It is restricted to `ahl_el_kheir_incentive_test_20261015_r1`, requires the protected active R5 project-completion policy, reuses only an existing closed zero-posted-balance fixture with a draft allocation and pre-existing recipient snapshot, inserts temporary FM closure audit evidence inside a transaction, and rolls back. It does not change funding allocations, payment evidence, posted accounting, payroll, or R5.

**Verification evidence:** PHP 8.2 lint passed for `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, `modules/hr/lib_employee_incentives.php`, and the rollback harness before the latest testability refinement. The rollback-only harness first refused live `ahl_el_kheir`, then passed against `ahl_el_kheir_incentive_test_20261015_r1`:

`PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS`

`PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project=7 | policy=R5`

This closes the **processor rollback-harness gate**. A follow-up now extracts the explicit FM financial-closure rejection into `akp_assert_no_draft_funding_allocations_for_financial_closure()` and adds an assertion for that same guard to the rollback-only harness. **That latest helper/harness change still requires PHP lint and a fresh runtime harness pass.** It tests the shared guard used by the FM handler; it does not prove the browser route itself or the automatic closure deferral after a funding return at runtime. Do not post a funding return or change project 7's draft allocation/payment evidence. After pulling `main`, lint `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`, then run the harness with process-only `AHL_DB_NAME=ahl_el_kheir_incentive_test_20261015_r1`. Keep the already-passed incentive, payroll, notification, salary-advance, and Projects Phase 5 gates closed unless regression evidence appears.


## 2026-10-16 — Automatic FM closure decision: source/harness update, local verification pending

The FM return handler now calls `akp_project_financial_closure_outcome()` for its completion/defer/remaining-balance decision. The draft-funding rollback harness asserts all three outcomes and reports `automatic_closure_outcomes=PASS` only after those assertions run. Local PHP 8.2 lint and harness execution are still required after pulling `main`. Real browser POST acceptance and end-to-end employee notification delivery remain open; a pending outbox row alone is not delivery evidence. Preserve project 7, active policy R5, and closed tests.


## 2026-10-16 — Employee incentives new-sponsor acceptance checkpoint

**New-sponsor first-confirmed-payment rollback harness: RUNTIME VERIFIED / CLOSED at tested boundary.**

User-reported local output from `tools/run_hr_employee_incentive_new_sponsor_rollback_tests.php`, with both configured and connected database guards passing for the isolated database:

```text
SAFE_TARGET | ahl_el_kheir_incentive_test_20261015_r1
PASS | new_sponsor=first_confirmed_payment | recipient_snapshot=PASS
PASS | later_payment_rejected=PASS | no_award_before_HR_verification=PASS
PASS | rollback_cleanup=PASS | sponsor=2858 | employee=16
TEST_EXIT_CODE=0
```

Do not repeat this gate without regression evidence. Active policy R5 was not modified; live database `ahl_el_kheir` was not the test target. Remaining distinct employee-incentive gates include the real-browser PM-to-FM project-closure workflow and end-to-end employee notification delivery/visibility. See `docs/HR_EMPLOYEE_INCENTIVES_CONTINUATION.md` for detailed evidence and constraints.

## 2026-10-17 — Employee incentive notification browser acceptance CLOSED

The employee-facing notification inbox was opened in the real local application while the outbox harness was paused against disposable database `ahl_el_kheir_incentive_test_20261015_r1`. The browser displayed the expected scheduled-incentive notice for 12.34 SDG, payroll period 2026-09, with the expected Arabic title/body. The test notification was ID 200.

Cleanup was independently verified after releasing the harness: notification 200 no longer exists; payroll fixture 21 is restored to 0.00 incentive amount and draft status; the disposable test user's original password hash matches exactly; `.htaccess` matches its original backup and contains only `Options -Indexes`; `git status --short` is empty; test-only environment variables were cleared. The harness's final console PASS transcript was not captured, so the result is based on the observed browser state plus independent database/filesystem cleanup checks.

The notification link observed in this CLI-generated test row used `http://localhost/modules/notifications/index.php`. Source inspection indicates this is consistent with `APP_URL` being derived in CLI without the web request's host, document root, or script name. Treat this as a test-context artifact unless a browser-originated workflow reproduces it; do not alter production URL construction based solely on the CLI-created row.

Employee notification delivery/visibility is CLOSED at this tested acceptance boundary. The real-browser PM-to-FM project-closure workflow remains a separate open gate. Do not reopen closed incentive, payroll, notification, salary-advance, or accounting tests without concrete regression evidence.


### 2026-10-17 — Project financial-closure draft-funding guard verification

Latest local PHP 8.2 lint passed for `modules/projects/project_funding_accounting.php`, `modules/projects/view_fm.php`, and `tools/run_hr_project_incentive_draft_funding_guard_rollback_tests.php`. The rollback-only harness passed explicit FM closure guard, automatic closure outcomes, processor block with draft funding allocation, unchanged awards/journals/funding evidence, and rollback cleanup on disposable DB project 7 under active policy R5. No funding return or durable financial change was made.

The helper/rollback verification is closed at this boundary. The real-browser PM → FM project-closure workflow remains open and must be tested separately at `http://localhost:8081/AhlElKheir/` using a safe eligible test fixture. Preserve project 7 and R5.

## 2026-10-17 — Project-completion incentive browser acceptance blocker

The disposable-database browser acceptance flow recorded a Supervisor closure request for `PRJ-0006`, but the PM portfolio omitted the project. Source fixes were committed on `main`:
- `4258ebb656a3229b84d5d7729868ce92a683f921`: allow PM visibility for an approved, `under_review` project whose latest status is `closure_requested`.
- `f7f8bae9753694fd32b7f916443a1f2a8493a3c6`: add Supervisor closure-request confirmation.
- `ba5ed5cec327e45d396ecbfb333a6c6d27ea5637`: remove duplicate toast/scroll initialization after source inspection found an existing shared implementation.

The missing-toast/scroll symptom remains unverified; if it persists in the updated browser, inspect the session toast value, rendered toast element, Bootstrap availability, and scroll restoration timing before changing code. Status remains **local verification pending**. Resume at the existing project 6 request; do not submit it again or use the live database.


## 2026-10-17 — Project-completion browser acceptance: PM closure feedback fix

Read-only evidence from disposable DB `ahl_el_kheir_incentive_test_20261015_r1` confirms project 6 (`PRJ-0006`) has been administratively closed by PM user 33: `other_projects.status=completed`, `project_lifecycle.lifecycle_status=closed`, `closed_at=2026-10-17 08:27:50`, status-history row 22, and audit row 2479 (`CLOSE`). Project-completion event 28 exists under policy version 11 with status `pending_verification`. The read-only acceptance query returned no `FM_FINANCIAL_CLOSURE` audit row and no project-completion award. Active policy version 1/R5 remains enabled at 1,500.00 SDG.

The missing PM confirmation prompt and lost success toast were traced to the PM closure form lacking a confirmation handler and the success redirect returning to a closed project that the PM can no longer view. Fix committed on main in `986cef5d21d0ae972a1ec6dd9f624a619a851b40` and `b5879cb2cc69be7f3a30301eb72b4570efb12497`: confirmation added; successful PM closure redirects to the Projects dashboard and renders the one-time success toast there.

**Status: source changed, local PHP lint and real-browser regression verification pending.** Do not repeat PM closure for project 6. The FM financial-closure and resulting award path remains unverified; continue only through the normal FM UI on the disposable database. Preserve R5 and protected project 7 evidence.

## 2026-10-17 — Project-team assignment workflow added; local acceptance pending

A repository-wide local `git grep -n -i "project_team" -- "*.php"` showed that production PHP only read active team membership for authorization, display, and the project-completion incentive snapshot; the only explicit INSERTs were in rollback test harnesses. The current schema in `database/ahl_el_kheir.sql` already defines `project_team` with section codes `general`, `finance`, `operations`, `documents`, and `closure`, so no migration or runtime DDL is needed.

On `main`, `modules/projects/view.php` now exposes team membership using the existing `akp_can_edit_section('team', $id)` authorization boundary:
- active users can be assigned to a schema-valid section;
- duplicate active user/section assignments are rejected;
- unassignment sets `unassigned_at` instead of deleting history;
- assignment/unassignment audit rows are written in the same transaction;
- both operations reject closed projects and lock the lifecycle before the project row to align with the closure path;
- the page explains that primary-supervisor assignment is separate from `project_team`.

Source commits: `907d8be0c6f945bd030db45c8902d19427320c2b`, `5f0519f1e4418a367003849c76e1dcafa256fc64`.

**Verification status: SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER ACCEPTANCE PENDING.** Do not claim the workflow is verified. Next: pull `main`, run `php -l modules\projects\view.php`, then test add/duplicate-rejection/unassign/history through the normal browser UI on a newly created test project in `ahl_el_kheir_incentive_test_20261015_r1`. Do not modify PRJ-0007, PRJ-0018, active policy R5, or any funding/expense/payment/journal evidence. Keep the real PM-to-FM financial-closure incentive acceptance open until the browser path is genuinely verified.

## 2026-10-17 — Initial project team selection during creation (source committed; local verification pending)

Source commit: `f23f6252f37e287549e5dbc461d963021d9eef12` on `main`, file `modules/projects/form.php`.

The new-project form now offers optional repeatable initial team rows (active user, existing schema-valid section, optional notes). The primary supervisor remains a separate required assignment and is never implicitly added to `project_team`; only users explicitly selected in the new section become team members. Server-side validation checks active users, allowed section codes, duplicate users, and the existing 500-character notes limit. Team rows and matching `ASSIGN_TEAM_MEMBER` audit entries are inserted inside the existing project-creation transaction, so a failure rolls back project creation and team assignments together. No migration or schema change was needed; the existing `project_team` schema already defines the five allowed section codes.

**Verification status: SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER ACCEPTANCE PENDING.** After pulling `main`, run `php -l modules\\projects\\form.php`. On the disposable database `ahl_el_kheir_incentive_test_20261015_r1`, test creating a project with no initial team, then a separate project with two explicitly selected team members in valid sections. Verify the primary supervisor is not auto-assigned, selected rows and audit history are saved, and an invalid/duplicate submission is rejected without leaving a partial project. Do not modify PRJ-0019 or any protected project/funding/accounting evidence. Keep the real-browser PM-to-FM incentive closure acceptance paused until this change and the remaining normal workflow are verified. Preserve the local `.htaccess` database-routing setting until test cleanup is explicitly verified.


## 2026-10-17 — Move project-team editing into the project edit page (source committed; local acceptance pending)

The user tested creation of `PRJ-0020` (“اختبار حوافز المشاريع”) and confirmed that the two explicitly selected team members were saved (2 active assignments), but the detail page still displayed the add-member form and the success toast was not visible.

Source changes committed directly to `main`:
- `666e3d41ccfec087c17aba28e86818d20eabb3c9` — `modules/projects/form.php`: existing-project edit page now displays current active team members and, when the existing `akp_can_edit_section('team', $id)` permission allows it and the project is not closed, supports add/unassign actions. Each change is handled as a separate CSRF-protected POST, locks lifecycle then project, preserves the prior transaction/audit approach, and keeps unassignment history.
- `89414e7efe5ea4458a559ca30f3bbdd579214c13` — restricts the team editor card to existing projects, so it does not appear alongside initial-team selection on the create form.
- `ebd543b40b445af7858660f1291318805d7d8cae` — `modules/projects/view.php`: removed add/unassign controls from the project detail page; the active roster remains visible and eligible project managers get a link to manage the team from the edit page.
- `a92be7e0efd6e7d4dcfe0f83c99e001e18ffb3fe` — `modules/projects/view.php`: added a fallback path for project success feedback.
- `1d9f9809c530a025c1231a9d8198941263022f16` — prefers a SweetAlert toast for project success feedback, with Bootstrap and visible-markup fallbacks if needed.
- `a2ee0211b9b7443427465f56371be3870350f30b` — `modules/projects/form.php` now uses the same SweetAlert-first success-toast behavior for team changes made on the edit page.

**Verification boundary: source committed, not yet locally linted or browser-verified.** Pull `main`, preserve the local `.htaccess` override routing Apache to `ahl_el_kheir_incentive_test_20261015_r1`, then lint `modules\\projects\\form.php` and `modules\\projects\\view.php`. Browser-check `PRJ-0020` without changing its existing membership: the detail page must not show add/unassign forms; its edit page must list both active members and expose add/unassign controls only when authorized; verify add, duplicate rejection, unassign/history retention, and a visible success toast. Do not edit unrelated project/funding/accounting evidence. If the toast still does not appear, inspect browser console/network/runtime evidence rather than guessing. Keep the PM-to-FM financial-closure incentive acceptance open until separately verified.


### 2026-10-17 — Project edit-page team editor placement

The existing-project team editor was moved from above the project form into the form flow immediately after **البيانات الأساسية** and before **نطاق المشروع وأهدافه**, matching the initial-team section's placement during project creation. Team actions continue to use the existing early POST action handler; the client-side budget check is bypassed only for team actions, and team-member selection is validated only when adding a member. Source committed on `main`; local PHP lint and browser acceptance for this placement/action wiring are pending. Preserve the local `.htaccess` disposable-database override during pull/testing. Do not alter existing team assignments as part of this UI change.


### 2026-10-17 — Project detail team card placement

The read-only **فريق المشروع** card on `modules/projects/view.php` was moved to immediately after **ملخص المشروع** and before **التنفيذ والشركاء والتوريد**. Removed the duplicate team-specific edit link from that card; the existing primary **تعديل المشروع** action in the page banner remains the single edit entry point. No team assignments or lifecycle behavior were changed. Source committed on `main` as `2780a8dd14a54d91c1606b0d63d91d917a412f65`. Local PHP lint and browser verification are pending; do not describe this UI adjustment as runtime-verified until tested locally.


## 2026-10-17 — Employee incentive notification at earned stage (source committed; local verification pending)

Source commits on `main`:
- `62a98884d49938ccbcbe5fbba0f54f592802bcf3` — queue the employee notice atomically when a verified award is financially recognized; dispatch after commit without requiring a payroll row; stop creating the first notice during payroll-draft scheduling; use wording that confirms the reward is earned but not yet paid.
- `21dcef9eb784f6c636f486a59e3ae710b9a21f07` — update the outbox migration's explanatory comments to describe earned-stage notices. No schema/DDL change was made.
- `191410b5fa816b62320c527767c0bacb6d365b5a` — clarify earned-notice error/failure handling.

Root cause: the dispatcher joined `hr_incentive_awards` to `payroll`, while the unique outbox row was created only when the award was scheduled into a draft. Therefore an earned award without payroll linkage could not notify its employee. The dispatcher now reads the award directly; the existing unique `award_id` keeps delivery idempotent, and notification insertion remains atomic with outbox state after the award transaction commits. Delivery failure remains isolated and retryable.

The notice now says the employee has earned the reward, that it will be included in an eligible payroll and paid through the normal approved payroll process, and that the notice does not mean payment has already occurred. It is not a payment confirmation.

Awards 14 and 15 already have sent outbox rows and existing notifications 212/213. They were not rewritten, resent, or backfilled; the October 2026 payroll drafts remain untouched and unpaid. Active policy R5 remains unchanged. Arabic appearing as `???` in prior SQL-console output is not proof of stored-text corruption and remains a separate encoding check.

**Verification status: source committed; local PHP lint and fresh-award runtime/browser acceptance are pending.** After pulling `main`, lint `modules\\hr\\lib_employee_incentives.php`. Verify the next safe, new project-completion award through the normal workflow on `ahl_el_kheir_incentive_test_20261015_r1`: the outbox/employee notice must be created after recognition commits even before payroll linkage, retries must not duplicate it, and the employee inbox must show the earned-not-paid wording. Do not repeat closure for `PRJ-0020`, rewrite existing notifications, change R5, or approve/pay October payrolls. Preserve the local `.htaccess` disposable-database override.


## 2026-10-18 — Employee incentive immediate activity notices

Implementation committed on `main`: qualifying monthly collection submission, active new-sponsor creation, reactivation, and project-team snapshot at PM administrative closure now create immediate in-app activity notices. The wording is provisional and preserves all existing verification, FM financial-closure, award-recognition, and payroll-payment gates. The earned-stage notification remains distinct from this early activity notice. Source change and rollback-test assertion are committed, but local PHP lint, disposable-DB test execution, and browser inbox verification are still pending; this is **not yet runtime-verified**. Use only the existing `ahl_el_kheir_incentive_test_20261015_r1` database.


Latest immediate-notice wording clarification commit: `c0316a4d47b708dedd37e74da8ac2d884b840dff`. The early message describes a recorded activity and expected incentive, not final earned/paid status.


## 2026-10-18 — Project incentive notification timing

Employee project-completion notifications are now source-configured to wait for FM final financial closure: the PM closure path retains the event/recipient snapshot but no longer inserts an early employee notice. The existing award recognition/outbox flow remains downstream of the durable FM closure gate. Commits: `28578e3c9088fe8efdfccc671e1e2e7e7b99721f` and `2a1d7b05e899582e40a6a0e864159914cc09b8e7`. Verification is not complete until local PHP lint and `tools/run_hr_project_incentive_payroll_rollback_tests.php` pass on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`. Do not rerun closed unrelated suites, mutate live DB, or re-close/reopen PRJ-0024.


## 2026-10-18 — Rollback verification passed

Local PHP lint passed for both changed PHP files, and `tools/run_hr_project_incentive_payroll_rollback_tests.php` passed on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`. Key result: `project_notice_waits_for_FM=PASS`; rollback cleanup passed; policy R5 and 1,500 SDG reward remained unchanged. This does not yet prove the actual FM UI action or employee notification delivery. PRJ-0024 remains administratively closed; FM final closure is pending acceptance. Do not repeat PM closure/reopen or alter policy R5.


## 2026-10-18 — Project incentive supervisor recipient regression (verification pending)

A disposable-DB acceptance case found that PRJ-0024 had an active eligible Project Supervisor assignment before closure, but the incentive snapshot queried only `project_team`. Commits `dd5c072c34dcd40dd8232e8f5df3aeac9b748e30` (source fix) and `f2ff7bb7bd4b56011a45c03fc591a8aefbd43c82` (supervisor-only regression fixture) are on `main`. Local PHP lint passed for both changed PHP files, and the guarded rollback-only harness passed against `ahl_el_kheir_incentive_test_20261015_r1` (exit code 0). Supervisor-only recipient snapshot, FM gate, recognition journal, payroll linkage, pending outbox, wait-for-FM boundary, and rollback cleanup passed; policy R5/reward 1500.00 SDG unchanged. This verifies the synthetic regression scenario only, not historical recovery or actual notification delivery for PRJ-0024. Do not rerun closure or modify PRJ-0024.

### 2026-10-18 — Immediate incentive activity-notice acceptance harness (runtime pending)

Commits `69ac7eb1b7da82f7495ea12db71c59b2725c4540` and `bb292f02acfcce504b84e69c1f8179b13332fc63` add/tighten `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`. It exercises the shared provisional-notice helper for collection, new-sponsor, and reactivation categories and checks new row creation, intended recipient, expected reward context, provisional/no-final-approval-or-payment wording, unread state, and rollback cleanup. Source inspection confirms the corresponding call sites in collection submission, active sponsor creation, and win-back reactivation. PHP lint and runtime execution on disposable DB `ahl_el_kheir_incentive_test_20261015_r1` remain pending. This is not yet runtime acceptance and does not simulate the actual business POST routes. Preserve active R5 and avoid live financial data.

## 2026-10-18 — Immediate activity-notice helper acceptance passed

The user ran PHP lint for `modules/hr/lib_employee_incentives.php` and `tools/run_hr_employee_incentive_immediate_activity_notice_rollback_tests.php`; both reported no syntax errors. On the existing disposable database `ahl_el_kheir_incentive_test_20261015_r1`, the rollback-only harness passed:

```text
PASS | immediate_notice=collection,new_sponsor,reactivation | correct_recipient=PASS
PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS
```

The shared helper creates a new unread provisional notice for each tested activity type, includes expected reward context, and rollback removes the temporary policy/notifications. This is helper-level runtime evidence, not proof that each real business POST route calls it correctly or that the browser inbox renders the notice. Keep employee notification browser acceptance and other documented incentive UI acceptance gates open. Active R5 and live payroll/accounting were not changed.


## Deferred development point — collection incentive eligibility (recorded 2026-10-18)

The user raised a business-design concern after the real browser collection submission displayed a provisional “activity linked to an incentive” notice: routine monthly sponsorship collection is ordinary job responsibility and may not merit an additional incentive, especially where the same employee may also qualify for the separate new-sponsor acquisition incentive. Revisit this with the user's forthcoming idea before changing behavior. Candidate recommendation to evaluate later: disable or redesign the collection incentive category and its provisional activity notice, while retaining new-sponsor, reactivation, and project-completion incentives unless the user decides otherwise.

**Deferred, not approved for implementation.** Do not disable the category, change active policy R5, modify existing events/awards, delete notifications, reverse accounting, or change payroll now. Preserve historical evidence. The observed collection notice is provisional and does not prove an award was issued; before describing an employee as having received a new-sponsor incentive, inspect the actual event and award records. Continue the already-open acceptance work without repeating the completed collection/new-sponsor browser submissions or closed helper harness.


## 2026-10-18 — Browser evidence and next read-only incentive-state check

The user has now completed real-browser checks of two immediate activity-notice routes on disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:
- Active new-sponsor creation for user 19 created sponsor `SP-002859` (“اختبار الحوفز”); the inbox showed the provisional activity notice with expected reward 2,000 SDG and explicit pending-verification/not-paid wording (observed 19:16:45).
- Monthly sponsorship collection submission reported two payments submitted to FM for approval/posting; the inbox showed the provisional collection activity notice for expected reward 1,000 SDG (observed 19:29:40). Do not assume this submission inserted only one payment.

These browser results prove the activity notices rendered; they do **not** prove an award was issued. The user's concern that the same employee may already have received a new-sponsor incentive must be resolved from durable records, not notification wording. Next step: run a read-only query against the same disposable database for incentive events and joined awards for recipient user 19, especially `new_sponsor` and `collection`; report event status and award status/payroll/journal links before deciding whether any earned incentive exists. Do not mutate, reverse, delete, or rerun the already completed browser submissions. New-sponsor first-confirmed-payment acceptance from 2026-10-16 remains closed and must not be repeated.

The separate development point about whether routine collection should qualify for an incentive is deferred and recorded above; no category, active R5 policy, event, award, notification, payroll, or accounting behavior has been changed for that recommendation.



## 2026-10-19 — Routine collection incentive removal: VERIFIED / CLOSED

The local runtime gate is closed. Seven PHP lint checks passed for the four changed PHP source files and three updated rollback harnesses. All three rollback-only harnesses passed against disposable DB `ahl_el_kheir_incentive_test_20261015_r1`:

- Immediate activity notice: `immediate_notice=new_sponsor,reactivation`; `collection_notice=blocked`; correct recipient, provisional wording, reward context, unread state, and rollback cleanup all PASS.
- Source event: `collection_source_event=blocked`; `no_new_collection_award_path=PASS`; rollback cleanup PASS; policy 18.
- Verification: HR manager fixture, recipient eligibility, pending-to-verified transition, award creation, duplicate recognition, and rollback cleanup PASS; recognition journal Dr 5500 / Cr 2400 for 2,000 SDG; policy 11, employee 1.

The collection-incentive removal's rollback verification is **CLOSED**. Routine monthly collection no longer emits a provisional incentive notice or creates a new collection source event, and already-pending collection events cannot be verified into new awards. This is a forward-looking eligibility/issuance boundary, not historical data deletion: existing events, awards, notifications, payroll, accounting entries, and active policy R5 were not changed. New-sponsor, reactivation, and project-completion incentives remain intact. No further application tests or browser submissions are warranted without concrete regression evidence.



## 2026-10-19 — Employee incentives status and remaining acceptance gates

**Status: core paths implemented; selected runtime checks PASS; full module acceptance remains OPEN.**

Closed and evidence-backed:
- Routine collection incentive removal: seven PHP syntax checks and three rollback-only harnesses passed on the disposable DB. New routine collection incentive notices/source events are blocked, and pending legacy collection events cannot be verified into new awards. Existing history, active R5 and catalogue were preserved.
- New-sponsor first-confirmed-payment: browser-verified 2026-10-16; do not repeat without regression evidence.
- Shared immediate-notice helper: rollback-only test passed for new-sponsor and reactivation, with correct recipient, provisional wording, reward context, unread state and cleanup. The collection case is now intentionally blocked.
- Project completion: rollback-only harness verified the FM gate, recognition journal Dr 5500 / Cr 2400, draft-payroll linkage, outbox and rollback cleanup; supervisor-only recipient fixture passed.
- PRJ-0024 supervisor omission: one-time recovery passed on the disposable database only: allocation 15, award 24, journal 178, scheduled, outbox sent, notification 232 and audit recorded. Do not rerun the recovery.

**Remaining acceptance gates:** (1) real-browser earned-notification delivery after FM financial closure, specifically confirm the project supervisor and other snapshotted recipients receive exactly one earned notice and reload/retry does not duplicate it; (2) real POST/browser inbox acceptance for the retained new-sponsor/reactivation provisional notices; and (3) project-team assignment UI acceptance covering initial selection, add and duplicate rejection, unassign/history retention, and edit-page behavior. The synthetic rollback harness and one-time historical recovery do not prove these complete workflows. Existing runtime evidence is from disposable test state, not production/live financial data.

Do not describe the entire incentive module as fully done until those gates are evidenced. Do not repeat closed tests, re-close or recover PRJ-0024, recreate deleted disposable databases, modify active policy R5, or change historical payroll/accounting evidence without an explicit new requirement or concrete regression.

## 2026-10-19 — Immediate activity-notice helper check

The updated immediate activity-notice rollback harness was run by the user after syncing `main`. All six participating tables reported InnoDB; helper assertions for new-sponsor/reactivation notices, blocked collection notices, correct recipient and provisional content, unread state, and rollback cleanup passed. This does not close the full employee-incentives module: real POST-to-browser-inbox acceptance and the PM-to-FM project-completion earned-notice workflow remain open. No further mutating acceptance tests should target live `ahl_el_kheir`; preserve active R5 and historical financial records.

## 2026-10-19 — Project-team source syntax verification

The user ran PHP 8.2 syntax checks locally after syncing `main`; both commands passed:

- `php -l modules\\projects\\form.php` — `No syntax errors detected in modules\\projects\\form.php`.
- `php -l modules\\projects\\view.php` — `No syntax errors detected in modules\\projects\\view.php`.

This closes only the local PHP syntax-check item. Browser acceptance for initial team selection, edit-page display and authorization, add/duplicate rejection, unassignment/history retention, and success feedback remains pending. The user confirmed the two disposable incentive databases are deleted; do not recreate them and do not perform mutating acceptance tests against live `ahl_el_kheir`. No database rows or project assignments were changed as part of this syntax check.


## 2026-10-19 — Projects UI confirmation, feedback, and navigation remediation (source committed; local verification pending)

A targeted source audit of `modules/projects/*.php` and `dashboard/projects_dashboard.php` found that project actions used browser-native `confirm()`/`alert()` calls, and the shared `assets/js/app.js` same-page POST capture listener could start a request before a form's own confirmation handler ran. On the FM project page, custom funding/payment submit handlers also competed with the shared interceptor because their forms were not consistently opted out.

Source changes committed directly to `main`:
- `e2af93acef3fc68c451380fcb4de8a062897afb8` — added an opt-in SweetAlert confirmation helper for project action buttons.
- `3e8f237f19f3d1107f44dca6c90cfafc8d35bc2f` — replaced project-form native prompts/alerts with system dialogs.
- `c3769432261e75f44a865bc9a260178c72d3b823` and `ff108c2bf154b12d6e8d1acb47859408b1433c87` — replaced project-detail native confirmations and kept administrative closure on the project detail page so its post-action feedback remains visible.
- `cf3a95673debd5b78b588e7b430e00054c3d4b0a` and `d5952f3ec097eace6648e1b8a4643ea995c85fc2` — opted custom FM funding/payment handlers out of the shared POST interception and replaced native browser dialogs in those paths.

A post-change static scan found no remaining direct `confirm()` or `alert()` calls in `modules/projects/form.php`, `modules/projects/view.php`, `modules/projects/view_fm.php`, or `dashboard/projects_dashboard.php`. This is source inspection only: PHP syntax checks, JavaScript/browser behavior, button navigation, toast visibility, and FM funding/payment flows still require local verification. No database queries or mutations were performed, and no incentive eligibility, award, journal, payroll, or notification logic was changed.

Next: pull `main), run PHP syntax checks for the three changed project PHP pages, and verify in the real local application that project confirmations use the system dialog, cancel means no POST, confirmed actions execute once, project feedback is visible, FM funding/payment AJAX actions do not double-submit, and administrative closure returns to the project detail page. Do not perform a closure, funding, payment, or team-membership mutation merely to test UI; use safe non-mutating checks first and assess any live-data mutation separately.


### 2026-10-19 — Project form validation ordering follow-up

Follow-up source commit: `97ca44232ecec3bdb306992e46f5aec4ff6d770c`. The project create/edit form now opts out of the shared same-page POST interceptor so its existing client-side budget/team validation can run before submission. Server-side validation remains authoritative. This is still source-only verification; local PHP lint and browser verification are pending.

## 2026-10-19 — Employee salary statement (source committed; local verification pending)

A new employee-only salary statement route is implemented at `modules/hr/payroll_statement.php`. The paid-payroll transition calls a best-effort statement notification only after the existing payroll/accounting transaction commits. The statement is accessible only when the signed-in account resolves to the payroll's employee profile and the payroll is both paid and accounting-posted. It renders saved payroll fields (basic salary, allowances, overtime, general deductions, attendance deduction, salary-advance deduction, net salary, and incentives when positive), and separately shows total paid as `net_salary + incentive_amount`, matching existing payroll posting behavior. Salary currency is taken from the effective salary-history row for that payroll period. The print stylesheet is designed for A4 and browser Save as PDF; no database schema change was introduced.

**Verification boundary:** source inspection only. Local PHP 8.2 syntax checks and non-mutating browser access/print checks remain pending. Do not trigger a real payroll payment solely for testing. The existing attendance, salary-advance, incentive, and payroll-accounting acceptance suites are not being reopened.


## 2026-10-19 — Salary statement presentation follow-up

The employee confirmed that the post-commit salary-statement notification was delivered and opened successfully for the paid October 2026 payroll (EMP-0017, 10,000.00 SDG; accounting entry 157). The statement was then refined on `main` to open payroll-statement notifications in a new tab (notification dropdown, live toast, and full notification history), center the organization logo in the header, use the navy header color for the outer statement border, compact earnings and deductions into side-by-side panels with a compact payment summary for A4 printing, and render only the currently selected `AK_LANG` language. Employee-only authorization, the paid + accounting-posted guard, saved payroll figures, separate incentive line, and `net_salary + incentive_amount` total-paid formula are preserved.

**Verification boundary:** the user confirmed notification delivery and initial statement opening before these presentation changes. The revised new-tab behavior, Arabic/English layouts, and A4 print fit remain pending local browser/print-preview confirmation; no additional payroll disbursement or database mutation is required to verify these UI changes.

## 2026-10-19 — Salary statement screen width refinement

Reduced the on-screen salary statement container maximum width from 900px to 748px, matching approximately the 198mm printable width of A4 portrait with the existing `@page` 6mm margins. The existing print-specific rule continues to use the full available printable width. This is a CSS-only adjustment; payroll data, authorization, notification behavior, and accounting are unchanged. Local browser and print-preview verification remain with the user.

## 2026-10-19 — Salary statement heading placement

The salary statement header now places “بيان تفاصيل الراتب” and “كشف راتب رسمي” immediately after the logo and organization name as one grouped brand/title line, instead of positioning the title independently at the edge of the header. The browser document title is also “بيان تفاصيل الراتب” in Arabic (or “Salary Statement” in English). Existing responsive and A4 print styling remains in place. Source committed on `main`; local browser/print-preview confirmation remains pending.

## 2026-10-19 — Salary statement heading stacked under branding

Follow-up correction: the logo and “جمعية أهل الخير” remain grouped on the first header line; “بيان تفاصيل الراتب” and “كشف راتب رسمي” are now a separate centered row directly underneath, not beside the logo. Header CSS/markup source committed to `main`. Local browser and A4 print-preview confirmation remain pending.


## 2026-10-19 — Employee salary statement implementation CLOSED

The user approved the final heading arrangement: the logo and “جمعية أهل الخير” remain together on the first row, with “بيان تفاصيل الراتب” and “كشف راتب رسمي” centered on a separate row below. The source changes are committed on `main`; the existing paid October 2026 payroll acceptance confirmed statement-notification delivery and initial statement opening for EMP-0017 (10,000.00 SDG; accounting entry 157). The implementation preserves employee-only access, the paid + accounting-posted guard, saved payroll values, incentive separation, and the existing `net_salary + incentive_amount` total-paid formula.

**Disposition:** implementation and the requested presentation correction are closed at the agreed scope. Do not run another payroll payment, mutate financial data, or repeat closed acceptance suites for this task. The assistant has not independently observed the user's final local A4 print preview after the last heading correction; this is not represented as runtime verification. Reopen only if the user reports a concrete defect.


## 2026-10-19 — GM payroll period completion report (verification pending)
A new GM-only read/print report and idempotent completion notification have been committed to `main`. Completion requires all employees eligible under the payroll-generation period-end rules to have paid payroll rows, posted accounting status, and a journal entry ID. Checks run after committed payments and when the payroll management page loads a selected period, so October 2026 can be recognized without reissuing payment. Existing employee statement notifications and saved payroll/accounting rows are preserved. Local PHP syntax, live access, notification, totals, and print verification are still pending; see `docs/HR_GM_PAYROLL_PERIOD_REPORT_CONTINUATION.md`.


## 2026-10-19 — GM payroll payment history navigation
Added a dedicated GM payroll payment history page and a quick-access card on the GM dashboard. The history lists payroll periods with at least one payroll record in `paid` status, shows each period's eligible/paid-and-posted counts and posted payment totals, and links to the existing read-only period report. The GM sidebar entry now opens the history page; notification links still open their specific period report directly. The page is GM-only and read-only; it does not change payroll or accounting data.

**Verification boundary:** source committed to `main`; local PHP syntax and browser verification are pending. Do not pay, approve, regenerate, or otherwise mutate payroll records for this UI change.


## 2026-10-19 — GM payroll payment history navigation CLOSED
The user confirmed the GM payroll history navigation works as intended. The GM dashboard/sidebar link to the GM-only, read-only period history and its links to the detailed period report are accepted. No payroll/accounting rows were changed. Do not repeat verification or reopen without a concrete regression.

## 2026-10-19 — Active request: FM payroll table usability
Target: `modules/hr/payroll.php?lang=en`, viewed as FM. Requested outcomes: reduce the table's excessive width and horizontal scrolling; keep table headers visible during vertical scrolling; make row action buttons easier to use without losing the current row after POST; remove only display fields proven redundant by source review. Treat this as a UI/usability task; preserve payroll calculations, statuses, authorization, payment workflows, and accounting behavior. Root-cause inspection and local PHP/browser verification are pending.


## 2026-10-19 — FM payroll table usability (source committed; local verification pending)
Source changes are committed on `main`: `modules/hr/payroll.php` (`85269f123d042a08735680d487c78b0c7715783b`) and `assets/js/app.js` (`f9cf0c0a2e079ae297c1674a8d18b7b150d738f0`). The table now uses compact fixed column widths, sticky headers in a bounded scroller, a combined status/accounting cell retaining journal-entry IDs, and no repeated salary effective-date subline. Draft amount form markup is valid with the hidden form inside the employee cell (follow-up commit `fecd42a9edbae3ff91b1092f81bcb4dc8c06eeb8`), and same-page POST rendering preserves the marked table scroller's offsets. No business logic or financial data changed. Local PHP lint and focused browser verification remain pending. See `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`.


## 2026-10-19 — FM payroll refinement follow-up
The FM payroll page has compact auto-sized translated headings, full-label tooltips, a dark sticky header, English month names, and stable Arabic/English labels for visible controls, summaries, statuses, and actions. Draft rows can be selected individually or with select-all; the new selected-approval helper validates the selected period and draft status before changing selected drafts to approved/ready in one transaction. It does not record payment. Save remains an explicit action for edited salary components and is now beside the row actions. No schema change. Static source audit passed; local PHP lint and browser verification remain pending. See `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`.


### FM payroll workflow redesign — 2026-10-20 Step 2 audit

The account/proof workflow source audit and proposed data model are recorded in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. The existing account resolver validates active status only and defaults to active account `1200`; the current paid transition couples status change and accounting posting, and notification completion helpers do not expose reliable success. A period default and per-payroll proof record are design candidates only. The schema file could not be retrieved through the repository connector, so exact DDL and foreign-key details remain unverified. No application/schema/data changes were made; do not create a migration until the user provides targeted local schema output.


### FM payroll workflow redesign — 2026-10-20 Step 2 closed

The user supplied local `SHOW CREATE TABLE` output for payroll, accounts, employees, users and notifications. Schema and migration conventions were reviewed; the finalized proposed period-default/proof model, explicit transfer-confirmation gate, legacy evidence path, private stream requirements and notification/GM completion gates are recorded in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. Step 2 is design-only: no migration, source, schema or data was changed. Next step is authoring the migration and private-proof storage contract for review before application.

### FM payroll workflow redesign — 2026-10-20 Step 3 draft

Migration draft `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql` was added in commit `f1225411c429f0b0eb9486ccec03f6be02e1ed8a`. It creates a period default-account table and one-per-payroll proof metadata table; it does not alter payroll or backfill historical rows. Private storage/download, upload validation, legacy evidence and duplicate-handling requirements are in `docs/HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md`. The migration is not applied; no application source or database was changed. Review SQL and local migration/storage conventions before application.

### FM payroll workflow redesign — 2026-10-20 Step 4 source checkpoint
The source layer for period account defaults, explicit approved-row account overrides, private proof upload/download, and legacy evidence attachment is committed on `main` in `modules/hr/lib_payroll_transfer.php`, `modules/hr/payroll_proof_download.php`, and `modules/hr/payroll.php`. Uploads are capped at 10 MiB and allowlisted to PDF/JPG/PNG with detected MIME validation; upload/legacy evidence do not themselves mark payroll paid or post accounting. The migration remains unapplied. Local PHP lint/runtime verification is pending, and the existing paid/accounting transition still needs the separate explicit confirmation redesign before this workflow is ready for operational use.


### FM payroll workflow redesign — 2026-10-20 Step 5 source checkpoint
The source now has a dedicated CSRF-protected transfer-confirmation action; the generic legacy paid-status POST is rejected. Confirmation requires a readable prepared proof and an active saved account, then confirms proof + marks paid + posts accounting in the same transaction. Employee notification outcome is explicit, and GM period completion requires every eligible employee to be paid/posted/journaled, have confirmed or legacy evidence, and have a recorded statement notification. Account changes are blocked after proof attachment. Source commits are on `main`; local PHP lint/runtime checks remain pending and the migration remains unapplied. Do not treat this workflow as operational until those local gates pass.

## 2026-10-20 — Initial Employee Salary Capture

The shared user-creation workflow now requires a hire date and a positive initial monthly basic salary, and employee provisioning uses those values to create the initial salary-history record effective on the hire date. Payroll's existing effective-dated salary selection remains the monthly source of truth; salary does not need to be re-entered each month.

Source changes are committed on `main`. Local PHP syntax checks and runtime/browser verification remain pending. This does not repair historical September 2026 drafts: 18 employees were identified with zero payroll salary and zero matching salary-history salary, while positive records shown for most employees start on 2026-10-01. Do not backfill historical salaries without authoritative amounts.


## 2026-10-20 — HR employment contract builder: source implementation checkpoint

Added `modules/hr/contract_builder.php` and linked it from `modules/hr/contracts.php`. HR can select an existing employee, edit contract fields, and submit a CSRF-protected form to generate an A4 Arabic RTL PDF using the repository's bundled TCPDF library. The document contains core employment terms and signature blocks and avoids hard-coded statutory leave/probation/notice numbers. This stage makes no database schema changes and does not save draft text or alter employment, salary, payroll or accounting data.

Commits: `f0e5fdba4b32e179091478b092eccd5dd10f033f`, `ca14abbd85b490c5025f8367c1fe2d2caa43f88d`; continuation document: `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

**Status: SOURCE ADDED — LOCAL VERIFICATION PENDING.** Do not call the PDF workflow complete until local PHP lint and browser checks confirm role access, CSRF rejection, PDF generation, Arabic RTL rendering, pagination and print quality. Qualified Sudanese legal review is required before official use. The second task—integrating contract preparation and signed-copy upload into employee onboarding—must wait until this PDF workflow is accepted and the actual local schema/storage behavior is inspected.

### Follow-up — contract form input validation

Commit `eef4d17e02543e1327ed8cc3db9fee3b916aa11f` adds server-side field length, email and weekly-hours validation, requires an end date for fixed-term contracts, and removes the nationality assumption. Local lint and browser/PDF verification remain pending.

## 2026-10-20 — HR employment contract portal (source committed; runtime pending)

The new separate HR dashboard shortcut `بوابة عقود العمل` opens the existing contract PDF builder, now connected to an HR-maintained role-description catalogue. Migration: `database/migrations/2026-10-20_hr_job_description_catalogue.sql`. Catalogue UI: `modules/hr/job_description_catalogue.php`. Contract builder: `modules/hr/contract_builder.php`. Existing `العقود والرواتب` and salary-history workflow are preserved.

Role matching uses the employee's actual `employees.position`, not application permission roles. Existing positions are seeded with empty descriptions and `needs_review` status; no job duties are represented as approved without HR review. Role-specific clauses are maintained independently of shared PDF layout. Organization logo and contact details are read from existing settings when configured.

**Boundary:** source commits are present on `main`; local migration application, PHP/runtime/PDF/browser verification remain pending. Do not claim runtime success. Contract language requires review by a qualified Sudanese lawyer before official use. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.


## 2026-10-20 — HR job-description proposal drafts and contract prefill follow-up

Source follow-up adds `database/migrations/2026-10-20_hr_job_description_proposals.sql`: empty job-description fields receive proposed Arabic draft content, existing filled fields and approval statuses are preserved, and all seeded proposals remain subject to HR review. The `HR Test` catalogue entry is inactive but its employee record is preserved. The contract builder fills the supervisor field from the catalogue reporting line where available and explicitly labels it as proposed. Local migration/runtime verification is pending. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-20 — HR role-aware job-description follow-up

The employment-contract continuation now uses the linked employee's application role as contextual evidence alongside `employees.position`, rather than assuming a title alone identifies the employee's system work. New migrations: `2026-10-20_hr_job_description_system_roles.sql` and `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql`. HR can manage each responsibility as an individual add/remove field; the existing TEXT column stores newline-separated items and the contract PDF renders them as an indented list. Proposed role-specific content remains pending HR/supervisor confirmation. Local migration application, PHP syntax checks, and browser/PDF verification are still pending. See `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-20 — Job-description item fields and supervisor selectors

Source changes committed directly to `main`:
- `6aecdb28e7abfbd58a8242090137ddfb1e09c89e` — separate editable item rows for role purpose, responsibilities, qualifications and optional role-specific terms; add/remove controls; reporting-line options sourced from distinct existing employee position titles.
- `21ee08af28a642800236d75369a69931b52a93a3` — persist the item lists as newline-separated text in the existing TEXT fields (no schema migration or runtime DDL).
- `ab2079268dfe72551613475aaa0e3bbc72478fc4` and `e97a1b5a1623b194d9f29e6559a71bc43e761b05` — direct-supervisor selector sourced from existing employee records, no longer guessed from the catalogue reporting title; prevent selecting the contract employee as their own supervisor; purpose, responsibility and qualification items render as separate list entries in the contract preview/PDF.

The existing “الأوصاف حسب المسمى الوظيفي ودور الحساب” catalogue table remains in place. Existing proposed purpose/responsibility text is preserved; no unsupported qualifications or optional contract clauses are invented. Proposed descriptions remain subject to HR review and approval, and the contract remains subject to qualified Sudanese legal review.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** No local XAMPP runtime or PHP CLI was available to this session, so do not mark the feature runtime verified. The GitHub Actions run triggered by the latest source commit reported failure, but its job steps/logs were not retrievable through the available GitHub connection; this is not evidence of a PHP syntax failure or pass. Local verification must run PHP lint and focused browser checks before acceptance.


## 2026-10-20 — Contract builder repeatable duties and special terms

On `modules/hr/contract_builder.php`, the two requested contract fields are now repeatable lists: **المهام والمسؤوليات والوصف الوظيفي** and **الشروط الخاصة المتفق عليها / البنود المرتبطة بالمسمى**. Each row has its own text area and add/delete controls. When an employee is selected, existing job-description and role-specific-term lines populate separate rows; submitted rows are combined with newline separators for the existing PDF rendering. No schema change or runtime DDL was introduced. Source commits: `05171ee43e74269a9ae397e9176fbb59e13544d9` and `61fd28bb49528d823b8e41e8a1cd59ad69e76d29`.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** PHP lint and browser/PDF checks have not been run in the local XAMPP application. After pulling `main`, verify adding, deleting, changing employee selection, and confirming all retained entries appear in the generated PDF.


## 2026-10-20 — Repeatable contract fields: blank-selection edge case

Source commit `7b0f675700651ea295569e3c410b44c7e39a679d` clears both repeatable lists and resets the job-description status message when the employee selector is returned to its blank option, preventing duties or special terms from the previously selected employee remaining visible. The fix is source-committed only. Local PHP lint, browser interaction, and PDF rendering remain unverified; do not mark the contract builder complete until the focused local checks pass.

## 2026-10-20 — Role-based qualification drafts from researched NGO job profiles

Added data-only migration `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications.sql`. Apply it after the earlier 2026-10-20 job-description catalogue, proposals, system-role and generic-draft cleanup migrations, in filename order.

The migration:
- removes the known “مسودة مستندة إلى مسارات النظام” seed prefix from unapproved role-purpose drafts so the field reads as the actual proposed purpose;
- replaces only exact generic placeholder text for the generic `موظف` row and unknown-role fallback with useful draft wording;
- fills blank or exact generic-placeholder qualifications with role-specific, research-informed proposed qualifications for management, finance/accounting, HR, IT administration, project management/supervision, sponsor supervision, family/child support, administration, general staff and communications/social media;
- retains HR-authored non-empty qualifications, leaves approved catalogue records untouched, does not change approval states, and does not create runtime DDL.

Qualification wording is deliberately framed as a proposed profile (degree/training or equivalent experience where suitable; relevant skills and experience), not a mandatory legal or hiring condition. All modified descriptions remain pending review unless already approved, and approved rows are not changed by this migration.

Research references used to shape common qualification profiles:
- Saferworld, Finance Officer — Sudan (accounting/finance degree, accounting controls, NGO operations, software, confidentiality and communication): https://www.saferworld-global.org/downloadfile.php?filepath=downloads%2Ffinance-officer-sudan.pdffinance-officer-sudan-final-april-2021.pdf
- CARE Egypt, Project Accountant (finance/accounting degree, NGO experience, Excel/ERP and Arabic/English): https://care.org.eg/wp-content/uploads/2024/05/Project-Accountant_Upper-Egypt.pdf
- UN Careers, Human Resources Assistant (role-family reference): https://careers.un.org/jobSearchDescription/286436?language=en
- UN Careers, Information Technology Assistant (systems/network/security support reference): https://careers.un.org/jobSearchDescription/282210
- UN Careers, Communications Assistant (public information/communications reference): https://careers.un.org/jobSearchDescription/286125?language=en
- UN Careers, Programme Management Assistant (project coordination, reporting, stakeholder and budget tracking reference): https://careers.un.org/jobSearchDescription/278900

These sources are benchmarks, not a claim that Ahl El Kheir must adopt another organization's minimum criteria. HR and the direct supervisor must confirm actual day-to-day duties, required education and experience, reporting line and role-specific conditions before approval. A system role documents workflow access; it does not prove which tasks an employee has personally performed.

**Verification status: SOURCE COMMITTED; local migration application, PHP lint, catalogue browser check and contract PDF check remain pending.**


## 2026-10-20 — HR job-description qualifications UTF-8 repair (OPEN: LOCAL VERIFICATION)

The local diagnostic found 25 of 26 `hr_job_description_templates.qualifications` values beginning with literal question marks. There were zero approved rows and one `HR Test` row. `position_title`, `role_purpose`, `responsibilities`, and `qualifications` use `utf8mb4_unicode_ci`; only qualifications were confirmed to begin with question marks. The other three fields are to remain untouched.

Source changes on `main`:
- `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications.sql` now starts with `SET NAMES utf8mb4;` for future correct imports.
- `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications_utf8_repair.sql` restores role-specific Arabic qualification drafts only where qualifications begin with literal question marks, while excluding approved rows and `HR Test`.

The repair is data-only; no DDL or runtime schema changes. **Source committed; local repair application and verification are pending.** Do not rerun the original seed migration to repair non-empty question-mark values.


## 2026-10-20 — Job-description qualifications UTF-8 repair VERIFIED

The user applied the corrective migration with the MySQL client explicitly configured for utf8mb4. The verification query returned **0 remaining corrupted qualifications** for unapproved, non-`HR Test` rows. The qualifications hex previews show UTF-8 Arabic bytes, confirming the imported content is no longer replaced with ASCII question marks. The `HR Test` row remains excluded; its qualifications remain NULL. The PowerShell display of Arabic position titles is garbled, but display encoding is not proof of stored-byte corruption; no title repair is authorized without a separate byte-level check.

**Closed:** qualifications encoding repair at the database-data boundary. **Still pending:** catalogue/browser and contract-builder/PDF acceptance for the broader job-description draft task. Do not repeat the repair migration or rewrite other fields without evidence.


## 2026-10-20 — Employment contract purpose field correction

The latest contract-builder source change separates job purpose from responsibilities and exposes the catalogue's qualifications and reporting line as their own controls. The root cause was UI consolidation: `role_purpose` was folded into the combined duties textarea, leaving no `الغرض من الوظيفة` field. The employee selector now reloads these fields from the matching position + system-role template, with the generic position template as fallback. Commits: `73b91d1a40024564d7399a80ae91b011167dbf9c`, `8fc40930c4224f04dab7e1f8c9c96c4fdfafd4b7`, `ec565758660214ac371ef14b692b4265249fc2b7`, `4d3d030bc4b96537141eec16fc6e3e0e86bce05d`, `59332e20a7d186eae3f969a9a94c24b1e2225d44`, `59ecd7b5057d9fe2910ccc3314b073d305cc19d6`. Source committed; local lint/browser/PDF verification is pending. No schema or migration changes.


## 2026-10-20 — HR job-description fallback catalogue clarity

The employee-wide query returned active role-specific template matches for current linked employees except employee 21 (**HR Salary Test**), which has no active match. For **أخصائية شؤون الأمهات**, role-specific draft id 20 is populated while title-only fallback id 6 is empty. This is consistent with role-specific-first contract selection and the earlier cleanup of exact generic drafts; it is not evidence that the role-specific draft is missing.

Commit `6264c897b2bbadb460e0ba0d3e0c029b1443312d` improves `modules/hr/job_description_catalogue.php` labels and warnings for general fallback records and counts active role-specific records by title. Related continuation documentation was updated. Status: source committed; local PHP lint and browser verification remain pending. No database changes or migration reruns.


## Follow-up — null-safe HR fallback counts

Commit `4435226e37405e9a2963dd07af0d1c14f71e7f92` makes the catalogue employee count treat SQL NULL and empty `system_role_code` values consistently as general fallbacks, matching the contract builder's PHP normalization. Local PHP lint and browser verification remain pending.
## 2026-10-20 — HR job-description fallback draft content (SOURCE COMMITTED; LOCAL VERIFICATION PENDING)

The user identified that general title-only catalogue entries still opened with empty **الغرض من الوظيفة** and **المهام والمسؤوليات الأساسية** fields. Root cause: the earlier `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql` intentionally cleared exact generic proposals from these fallback records. Commit `5b6a553b22ff5a0be3d0a2b6430556ccf5138be9` adds `database/migrations/2026-10-20_hr_job_description_zzz_general_fallback_drafts.sql`, a data-only migration that restores distinct, broad drafts to active, unapproved, title-only records only when either field is blank. It preserves non-empty/HR-edited content, role-specific records, approval states, qualifications, reporting lines, employee data and schema; `HR Test` remains excluded. All drafts remain `needs_review`.

**Next gate:** user pulls `main`, applies only the new migration with UTF-8 enabled, runs read-only verification of the active general fallback rows and review statuses, PHP-lints `modules/hr/job_description_catalogue.php`, and checks catalogue display plus role-specific employee selection in the local contract builder. No completion claim until those local checks are returned.


## 2026-10-20 — Employment contract qualification baseline and item reordering

The HR job-description catalogue and contract builder now support drag-to-reorder for repeatable purpose, responsibility, qualification and optional-term rows, with automatic numbering. The data-only migration `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql` prepends a minimum high-school qualification and basic computer/office-program knowledge to active templates and returns changed descriptions to `needs_review`.

Source is committed to `main`. Local migration application, PHP lint, browser drag/reorder behavior, and PDF order remain pending user verification. Do not mark runtime verified until confirmed.


## 2026-10-20 — Employment contract automatic numbering

Source committed; local runtime verification pending. In modules/hr/contract_builder.php, generating a contract PDF now creates a draft row in the existing hr_employee_contracts register and uses its saved ID to produce the established CTR-YYYY-000001 number. The read-only number field is not client-submitted. The record is created transactionally with PDF rendering; a one-use session token blocks resubmission duplicates. Overlapping draft/active contracts are rejected consistently with the existing contract helper.

The builder does not call hrCreateEmployeeContract(), because that helper also changes salary history and may update the employee salary mirror/employment state for a current or past start date. Those effects remain behind the existing explicit activation workflow. No schema migration was required.

Commit: 8ed43dc8c7e0f2e2c5693e1777bfb8324bdc3115. Local PHP lint, browser/PDF check, draft register check, overlap rejection and confirmation that salary history/current salary/employment state are unchanged remain pending.


## 2026-10-20 — Unify contract creation and register workflow

Source changes committed directly to `main`:
- `315f221c9f43d4bbbe55b0f2acdce88e3b403296` — removed the duplicate `add_contract` creation path from `modules/hr/contracts.php`. New-contract links now open `modules/hr/contract_builder.php` with the selected employee. The old `?view=add` URL now explains the canonical workflow instead of presenting a second creation form.
- `b312f117f077f14e8c5f4f91bd80a4aeaf0c2ebf` — the builder's return link now returns to the employee's contract-register view, not the retired add form.
- `dd2b1dcc99762226e1ddf8a28000e6fc4a7d5190` — added an explicit optional probation-end date to the builder, validates it against the contract start date, includes it in the PDF text, and persists it to the draft's existing `probation_end_date` column so the register's existing activation/employment-state behavior remains available.

The register continues to manage contract status and salary history. It now uploads or replaces a signed copy against an existing contract record, rather than creating a new contract as a side effect of uploading a file. The upload validates that the contract belongs to the selected employee and retains the existing 10 MB / PDF-JPG-JPEG-PNG limits. Replacing a file updates the register path but deliberately does not delete older files.

No schema change or migration was introduced. The builder continues to reserve the official number and create a draft when PDF generation succeeds; this does not activate the contract or update salary history/employment state. The full editable clause text remains in the generated PDF, while the register stores its existing core fields.

**Verification status: SOURCE COMMITTED; PHP lint, browser interaction, signed-copy upload, draft activation, salary-history/employment-state behavior, and generated PDF are NOT runtime verified.** Pull `main`, lint both changed PHP files, then test one non-production/appropriate employee record through the configured XAMPP URL. Do not create a second draft for an employee/date period already covered by an active or draft contract. Sudanese legal review remains required before official use.


## 2026-10-21 — HR contract portal is the single dashboard entry point (source only)

Removed the separate `العقود والرواتب` card from `dashboard/hr_dashboard.php`; the dashboard now directs contract work through `modules/hr/contract_builder.php` only. Added portal-header navigation to the existing contract register/salary-history workflow and job-description catalogue. The legacy register remains a linked subordinate page because it contains signed-copy upload, contract activation/termination/status, and salary-history operations that have not been rebuilt into the builder. No contract records, signed files, schema, or migrations were changed.

Commits: `be9c2515ec0f387406f4e54180e103d2985869bf`, `f3263b20eb666de05d096bc6d30d0143e92b15b7`. **Local PHP syntax/browser verification pending.** Keep the old register file until capability parity and safe navigation are verified; do not claim the legacy implementation has been removed.

## Contract PDF preview follow-up — 2026-10-21
- Runtime report from the local contract builder showed an `Undefined array key "reporting_to"` warning during PDF generation. Source inspection found `reporting_to` was listed in the field-length validation limits but absent from the `$data` array.
- Corrected `modules/hr/contract_builder.php` on `main` by adding `reporting_to` to the validated data array (commit `86bb2343fd19ed1455420dcc9c4775a07cda4605`). This is source-committed but not yet locally linted or runtime-verified.
- The UI also reported an overlapping draft/active contract for the selected employee and period. Treat that as a separate safeguard; inspect the relevant employee's contract register before retrying, and do not bypass the overlap check.
- Next: user pulls `main`, PHP-lints the builder, then chooses an employee/period with no conflicting draft or active contract to generate and inspect the actual PDF design. If the conflict persists unexpectedly, capture the selected employee and inspect the register before changing logic.


## 2026-10-21 — Employment contract PDF design correction (source only)

The user rejected the first generated PDF because it did not reflect the previously agreed formal Arabic RTL navy-and-gold design. Source commit `ed3e4abf0bf4c3ea906ee21c30d9c2b6b465cc3c` updates `modules/hr/contract_builder.php` to add a distinct cover page with organization branding, contract number, employee/position and formatted dates; strengthen navy/gold section styling; isolate the signature/acknowledgment block on a dedicated final page; format dates as DD/MM/YYYY; and explicitly isolate Latin employee names, contract numbers and salary/currency text for more reliable bidirectional rendering. Existing Sudanese-law-first clause wording, NGO safeguarding terms, draft reservation, overlap guard and non-activation workflow remain intact. No schema change was made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY VERIFIED.** The reported PDF also showed an apparently blank contract number and inconsistent Latin employee-name order. The source now explicitly presents the reserved number and isolates Latin text, but only a fresh local PDF can confirm TCPDF renders these correctly. User must pull `main`, lint `modules/hr/contract_builder.php`, and generate a PDF for an employee/date range with no overlapping draft/active contract. Inspect cover, number, name consistency, currency/date direction, page breaks and signature page. Do not create another draft for a covered period, bypass overlap protection, or claim runtime success before evidence.

## 2026-10-21 — Contract PDF aligned to existing voucher layout (source committed; runtime pending)

The user explicitly requested the employment contract PDF follow the general layout/design of existing financial vouchers. The concrete source reference is `modules/accounting/voucher_print.php`: bilingual organization identity, boxed document-number/date panel, strong navy title bar, structured bordered/shaded information tables, consistent accent colors, and clear signature blocks. Commit `1cd7a318cb1f6075f66badfe235ba758aab4b527` updates `modules/hr/contract_builder.php` to remove the standalone decorative cover page and instead start the contract with a voucher-style title/number/date panel and employee/position/date summary, apply the voucher navy (#1b4d8f), muted shaded table labels and gold (#9a7628) accent to clause headings and signature tables, and use the same official Arabic/English organization names as the voucher print page. The legal clauses, draft reservation, overlap guard, and explicit activation workflow remain unchanged; no schema change was made.

**Verification status: SOURCE COMMITTED; PHP lint and actual local PDF rendering NOT verified.** User must pull `main`, lint `modules/hr/contract_builder.php`, then inspect a valid existing draft/eligible preview without generating another contract for an overlapping employee/date range. Confirm there is no oversized standalone cover, contract number/date panel renders correctly, header and names match the voucher branding, clause bars/tables are readable, no warning appears, and the final signature page remains usable. Do not claim visual acceptance until the local PDF has been inspected.

## 2026-10-21 — Employment contract branding correction (source committed; local PDF verification pending)

After the user reported that the revised PDF still looked unprofessional and had no visible organization logo, source inspection found the contract depended on TCPDF's generic header for the logo while the main contract body did not explicitly embed it. The PDF also included an internal HR job-description review warning inside the employee-facing contract. Source commits `7619773e52cb2e62bcde0721fa750b7913161689` and `19a79e00d2747616609edbd5a82b36a9b66cfc96` now use the same repository logo as the voucher print page (`assets/img/logo.png`), explicitly embed a bilingual branded letterhead in the contract body and signature page, avoid a duplicate generic TCPDF header, and remove the internal review warning from the contract PDF. HR review status remains a builder/catalogue concern; this change does not mark any job description approved. No database/schema or contract lifecycle changes were made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY LINTED OR RENDERED.** User must pull `main`, run PHP lint, and inspect the actual PDF before acceptance. Confirm the logo is visibly embedded, there is one clean organization letterhead (not duplicate branding), the contract number/date and Latin text render correctly, the internal review warning is absent from the employee-facing PDF, clause styling and page breaks remain readable, and the signature page is properly branded. Do not create a new contract solely to test if an overlapping draft/active contract already exists; preserve the overlap guard and existing records.


## 2026-10-21 — Employment contract PDF logo compatibility (runtime pending)

The contract PDF's direct HTML embedding of `assets/img/logo.png` triggered TCPDF's error requiring GD or Imagick for alpha-channel PNG images. Inspection of existing pages found the shared print header and accounting/salary-advance voucher pages use the PNG, while settings and the contract-builder portal honor `site_logo`. The user confirmed the existing `assets/img/logo.jpg` is the same organization logo. The PDF now uses this JPEG only in the TCPDF letterhead; no Apache/PHP configuration, database setting, schema, or contract lifecycle behavior was changed.

**Status: source change committed; local PHP lint and browser/PDF rendering remain pending.**


## 2026-10-21 — Second contract PDF design revision (voucher-style hierarchy; runtime pending)

Following user feedback that the employment contract still looked weaker than the existing vouchers, `modules/accounting/voucher_print.php` was re-read as the design reference. The contract's first-page composition has been revised to use a single bordered three-part letterhead (logo, centered bilingual organization identity, and boxed contract number/date), a full-width navy bilingual title bar, a compact employee/position and term summary, and a dedicated highlighted basic-salary band inspired by the voucher amount panel. Clause headings now use a pale-blue panel with navy text and a gold rule instead of a heavy filled bar, to improve readability across long legal text. Existing clauses, job-description content, signature page, logo JPEG compatibility fix, draft reservation/overlap guard, and activation workflow are preserved. No schema or accounting changes.

**Verification status: SOURCE COMMITTED; local PHP lint and actual PDF visual inspection are still pending.** The rendered PDF must be inspected before deciding whether this revision is accepted; do not create another overlapping contract draft just to preview the design.

## 2026-10-21 — Employment contract PDF layout follow-up

Source commit `ddffae6105a2da56bce1b5d706bc7d40e1f94ba2` removes the repeated organizational letterhead from the signature page and compacts the contract PDF layout toward a two-page total. This is source-only; local PHP lint, exact page count and text readability remain unverified. Continue with `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`; no database migration or contract lifecycle change was introduced.

## 2026-10-21 — Contract PDF header/layout follow-up

Commit `f30fd321ae7932daa2e87b7b70be8e3f97d437a3` adjusts the employment contract PDF toward the compact outer composition of `modules/accounting/voucher_print.php`, reducing logo/header height and body spacing to target two pages including signatures. Source committed; local lint and PDF page-count/readability are unverified.


## 2026-10-21 — Contract PDF header proportion correction

After the user reiterated that the contract PDF should follow the existing voucher design, source inspection found a concrete sizing mismatch in `modules/hr/contract_builder.php`: the logo was rendered at 24 mm inside a 10%-width header cell, making it wider than its own column and increasing the header row height. Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` reduces the logo to 14 mm, sets the logo cell to 12%, keeps the contract number/date panel at 24%, adjusts the organization cell to 64%, and reduces header-cell padding from 2 mm to 1 mm. This retains the existing voucher-style navy frame, organization identity, and boxed number/date panel. No schema, contract lifecycle, legal wording, or page-break logic changed.

**Verification status: SOURCE COMMITTED; local PHP lint and rendered PDF/page-count verification remain pending.** Pull `main`, lint `modules/hr/contract_builder.php`, then preview only an eligible existing contract period (do not create an overlapping draft). Confirm the logo fits inside its cell, the header is compact, the contract is readable, and the PDF totals exactly two pages. The source still has automatic page breaks for the contract body followed by an explicit signature-page break, so a two-page result is not yet proven.

## 2026-10-21 — Contract PDF header sizing (source only)

Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` corrects a logo/header proportion mismatch in `modules/hr/contract_builder.php`: the logo is reduced from 24 mm to 14 mm and now fits a 12% header cell; the organization and number/date columns are 64% and 24%, with tighter cell padding. This is a narrow presentation change based on `modules/accounting/voucher_print.php`. No schema or contract lifecycle change. Local PHP lint, rendered PDF layout, readability and two-page count remain unverified.

## 2026-10-21 — Contract-builder error feedback toast (source only)
A narrow presentation-only change replaces the in-flow error alert in `modules/hr/contract_builder.php` with a fixed-position RTL toast. No shared JS, database, token/CSRF, transaction, contract numbering, overlap, or PDF logic changed. Local PHP lint and browser verification are pending; do not claim runtime verified.


## 2026-10-21 — Contract-builder validation navigation correction (source only)
The fixed toast alone did not prevent the jump because the contract form targets a new tab and server-side validation HTML was displayed as a full document there. Commit `bd088d1f4d090506df48eecf666c270add50f91b` now handles submission locally: validation errors are toasted on the current form page and successful PDF responses still open in a new tab. No shared JS/database/business-rule change. PHP lint and local browser verification pending.


## 2026-10-21 — Contract Builder source rollback
The user reported contract viewing/creation became unusable after feedback-positioning changes. Inspection confirmed malformed `.cb-actions` markup in `e41cadc`. Commit `d074486b7cff1a204ab3bf353c829a59ecef32f7` restores the complete `modules/hr/contract_builder.php` source from the voucher-design checkpoint `e7f96e8`, removing the later toast/fetch changes. This restores the known source checkpoint, but local XAMPP lint/browser/PDF verification is not yet established. Details: `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

Correction to rollback record: the complete 799-line checkpoint source was re-applied and checked against the same 799-line source fetched from `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. Final source commit: `7df2bc435d2a6464692895db62cb97ec03a2f109`. The complete file ends with the shared footer include; the PDF submit button and return link are separate. This comparison establishes source equality with the checkpoint, not local PHP/browser runtime success.

## 2026-10-21 — Employment-contract PDF download filename hardening

The user reported that using Download in the browser PDF preview saved the contract with a `.php` extension. Source inspection confirmed the PDF response already used `Content-Type: application/pdf` and an inline `filename="Ahl-El-Kheir-Employment-Contract.pdf"`, so the root cause is not proven without inspecting the actual local HTTP response headers. Commit `1cf3fb0b753b30b49ba5f950498ff534fb802de2` makes the response more robust by clearing any active output buffers immediately before PDF headers/body, and supplying both the ASCII `filename` and RFC 5987 `filename*` parameters while preserving `inline` preview behavior. This is source-only. After pulling `main`, run PHP lint and generate a PDF in the local XAMPP app; use the PDF viewer's Download control and confirm the saved file ends in `.pdf`. If it still saves as `.php`, inspect the actual response headers in browser DevTools before changing more code. Do not claim runtime success before this check.

### 2026-10-21 — Contract Builder error-message placement

`modules/hr/contract_builder.php` commit `197f53d75cf4546e8efd6a483e9ea850a18916b5` routes caught PDF-generation errors from the `_blank` response back to the original page and displays a toast beside the clicked submit button, without navigating the original form page. Successful inline PDF response headers remain intact.

Status: **SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER VERIFICATION PENDING.**

### 2026-10-21 — Contract Builder error toast correction

Follow-up commit `890b9ef95390af64820083a8567c6e5e7fc2ccb0` corrects the previous approach that still displayed errors in the new tab. Form submission is handled by `fetch()` on the original page; error responses are JSON and become a toast beside the clicked button, while PDF responses continue to open in the preview tab.

Status: **SOURCE COMMITTED; LOCAL PHP LINT AND BROWSER VERIFICATION PENDING.**

## 2026-10-21 — Contract Builder restored before toast experiments

`modules/hr/contract_builder.php` was restored in commit `8af298793a4c86d38d303516b2b03992b7258405` to exact checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`, removing the recent toast/fetch changes. The file blob matches the checkpoint. Local PHP lint and browser/PDF runtime verification are still pending; do not treat the toast issue as fixed.

## 2026-10-21 — Authoritative Contract Builder continuation state

This checkpoint supersedes earlier entries that proposed further toast/fetch changes or asked to test those experiments. The latest documented rollback is commit `8af298793a4c86d38d303516b2b03992b7258405`: `modules/hr/contract_builder.php` was restored to the pre-toast voucher-style PDF checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`; its source blob was reported to match that checkpoint. Do not reintroduce or continue the toast-positioning/fetch experiment unless the user explicitly requests it.

This is source/checkpoint evidence only, not runtime acceptance. Local XAMPP PHP lint, existing-contract viewing, contract creation through the normal workflow, and generated PDF behavior have not been confirmed after the rollback. If verification is requested, use the configured app URL `http://localhost:8081/AhlElKheir/`, preserve the existing draft/active overlap guard, and do not create a duplicate contract merely to preview the PDF. Record only observed results; do not claim runtime success in advance. Continue from `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — Contract Builder toast handling with native PDF response

The user explicitly resumed the Contract Builder toast task and requested that its feedback match proven notification patterns elsewhere in the application. The existing `modules/hr/job_description_catalogue.php` pattern uses a fixed-position RTL toast with accessible live announcement, dismiss control, and five-second auto-dismiss. `modules/hr/contract_builder.php` now applies that toast presentation to caught generation errors on the original form page.

Source commit: `54d4cef36e58695faea509a61b2a0fc5232589bb`. The form targets a named preview window (`contractPdfPreview`); the server returns caught generation errors as a small same-origin response that posts the message to the opener and closes the preview window. Successful generation continues to return the original native `application/pdf` response with its existing inline filename headers, avoiding a fetch/blob preview that could alter download naming. Contract validation, one-use submission token, CSRF handling, transaction/rollback, overlap protection, and PDF generation logic were not intentionally changed. The interim fetch/blob implementation in commit `572ccf46ea16b856592885d492ee6b4b9fc0a30b` was superseded by this native-response approach.

**Verification boundary: SOURCE COMMITTED; local PHP lint and browser behavior are not yet verified.** On the configured XAMPP app (`http://localhost:8081/AhlElKheir/`), first test one controlled server-side validation failure and confirm the toast appears on the original form page without navigating it or leaving an error page open. Verify that the named preview window closes after delivering the message. Check PHP syntax. Test a successful PDF response only when creating the corresponding contract draft is intentional; do not create duplicate/overlapping drafts merely to test preview. Confirm the browser still receives the native PDF response and filename headers. Do not mark this fixed until actual local evidence is available.


## 2026-10-21 — Fixed-term missing end-date error stays on the form

After the user confirmed that the exact validation message `تاريخ نهاية العقد مطلوب للعقد محدد المدة.` still rendered in the preview tab, source inspection found that the submit handler opened `contractPdfPreview` before PHP performed this deterministic validation. The popup response's `window.opener.postMessage()` path had not delivered the message in the user's browser. Commit `c1b80acd47904f91e6d11e615e19de740138ddb5` adds a narrowly scoped pre-submit check for `contract_type=fixed_term` with an empty `end_date`; it prevents submission/opening the preview tab and uses the existing toast on the original page. The authoritative PHP validation remains unchanged.

**Verification boundary:** source committed to `main`; local PHP lint and browser behavior are not yet verified. Test this exact missing-end-date case first: the original page must remain in place, show the toast, and open no new tab. This change only covers the confirmed validation case; other server-side generation errors still need separate runtime diagnosis and must not be described as fixed by this narrow change. Successful PDF generation and existing contract safeguards were not intentionally changed.


## 2026-10-21 — Contract Builder error routing and stale-token recovery (supersedes earlier toast attempts)

User runtime feedback showed that the fixed `contractPdfPreview` browsing-context name could navigate the current tab when that tab already had the same browsing-context name; the resulting server error page was not consistently reaching the form toast. Source inspection also confirmed that successful PDF generation intentionally unsets `ak_contract_builder_submission_token`, leaving the still-open form with a consumed token until it is refreshed.

Commit `c89e22386f921c339b8965de1cf49fd07a11f25b` changes the form to open a fresh, unique preview-window name for each submission while retaining the native form POST and native PDF response (including the inline filename header). Server-side errors send the error and the current/reissued form token to the opener via same-origin `postMessage`; the original page displays the existing toast and refreshes its hidden token. The deterministic missing-end-date check remains before opening any preview window. The AJAX/blob implementation from intermediate commit `dc6a80b0dfc9c086c5cc0346d028b6b7bc872b4b` was superseded to preserve native PDF response/download behavior.

**Verification boundary:** static source inspection confirms the unique preview target, error message/token payload, client token refresh, unchanged native PDF `Content-Disposition`, and preserved one-time-token consumption after success. Local PHP lint and browser runtime are not available from this remote source session; do not call this resolved until the exact local acceptance test passes. Test (1) fixed-term contract with empty end date: toast on original page, no preview tab; (2) server-side token/validation error: toast on original page, error tab closes, no white/error page; (3) successful PDF: opens in separate tab with native PDF response/filename. Do not create duplicate contract drafts solely for testing.

## 2026-10-21 — Contract Builder toast task closed at user-requested boundary

The user confirmed that the error toast now appears after completely closing the page and logging out/in again. On a further check, the user clarified that the page does **not** jump/scroll upward; instead, there is a very brief refresh-like visual effect before the toast appears.

The user explicitly instructed us to stop here and make no further changes. **Task status: CLOSED BY USER REQUEST; residual visual behavior is documented, not diagnosed or claimed fixed.** No further source edits, experiments, or speculative scroll/focus/navigation workarounds are authorized for this task unless the user explicitly reopens it. Do not reopen automatically in a new session.

No cause has been established for the brief refresh-like effect. The current observed behavior is the acceptance boundary the user chose; it is not evidence that the underlying visual effect was technically eliminated. No local runtime or PHP-lint result is newly claimed by this documentation update.

For the next session, read the canonical project documentation and inspect current `main` to determine the next genuinely open task or follow the user's new requirement. Do not resume Contract Builder toast/scroll work unless explicitly requested.

## 2026-10-21 — HR contracts employee details moved into a modal

User request: on `modules/hr/contracts.php`, selecting an employee should open the employee's contract/salary area as a popup, and the employee list should expand to use the width previously occupied by the two-column list/details layout.

Source commit: `29f650e189f748777427ac90eafc630feac58a18` (on `main`).

Implemented in `modules/hr/contracts.php`:
- Replaced the narrow left-hand employee sidebar plus always-visible details panel with a full-width responsive employee-card grid.
- Selecting an employee loads that employee's existing `employee_id` URL and automatically opens a wide, scrollable Bootstrap modal.
- Moved the employee-specific tabs and existing sections into the modal: `العقود`, `سجل الرواتب`, `إعداد عقد جديد`, and `تغيير الراتب`.
- Kept the existing contract status actions, signed-copy upload, salary history, salary-change form, employee/contract queries, permissions, and business rules in place; no schema or migration change.
- Search continues to filter employee cards and now shows an empty-results message. The modal is reopened after the existing form POST reload so the user can see the result/error inside the employee context.

**Verification boundary:** source change committed to `main`; no local XAMPP/PHP lint or real-browser runtime verification is claimed from this session. After pulling, check PHP syntax and test employee selection, all four modal sections, search/no-results, contract actions/upload, salary history/change, and that success/error feedback remains visible inside the modal after POST. Do not claim runtime verified until these checks pass.


## 2026-10-21 — HR contracts modal parse error corrected

After the user pulled the employee-details modal change, local PHP lint reported an unexpected end of file on line 134, expecting `elseif`, `else`, or `endif`. Source inspection identified the exact cause: the employee-selection `if ($emp)` alternative-syntax block had no closing `endif`; the existing terminator closed the nested view switch instead. Commit `decb8a577df182396def4eea906220bd4b4b009a` adds the missing outer `endif` in `modules/hr/contracts.php` without changing application logic.

**Verification boundary:** source correction is committed to `main`; the user's local PHP lint has not yet been rerun, and browser/runtime behavior is not verified. After pulling, run `php -l modules\hr\contracts.php` and report the exact result before browser testing. Preserve the existing untracked `storage/hr_contracts/` directory; it was not modified or included in the commit.

## 2026-10-21 — HR contracts modal initialization

A targeted source fix (`4fe5b9baaa09830c3bfc06b37c18c3f080660f45`) defers employee search/modal initialization until `DOMContentLoaded`, after the shared footer loads Bootstrap JavaScript. Local PHP lint and browser verification remain pending; see `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR employee selector presentation correction

The employee selector now uses full-width list rows instead of cards (`7464fc22c31f613fcf26f3c33f32ca3d92be491c`). Modal initialization was separately deferred until `DOMContentLoaded` (`4fe5b9baaa09830c3bfc06b37c18c3f080660f45`). Local lint and browser verification pending; details in `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md`.

## 2026-10-21 — HR employee modal footer

Removed the redundant footer row inside the HR employee modal while preserving the modal header close control and the main-page footer (`7c724c77e65a055e9eb66da7605dfe69ec9ad300`). Local lint/browser verification pending.

## 2026-10-21 — HR employee modal navigation checkpoint

Source-only UI refinement in `modules/hr/contracts.php` (`40f1198a3b0c54b022b2154b55a12dfce345b730`): footer elements are suppressed within the employee popup; internal contract/salary tabs fetch and replace modal content without a full-page navigation. Contract Builder remains a normal separate-page link. Local lint and browser checks are pending, so runtime status is not yet verified.
Follow-up commit `889a10e6f7368190f8e78a9d356a7fabd638b1c6` also hides the shared page footer for the duration of the employee modal and restores it on close. Runtime verification remains pending.

## 2026-10-21 — Secure viewing of uploaded employment contract copies

Root cause confirmed from repository source: `storage/.htaccess` contains `Require all denied`, which correctly blocks direct HTTP access to `storage/hr_contracts/`. The contract register previously linked directly to `APP_URL . contract_file_path`, so Apache returned `403 Forbidden`.

Source changes committed on `main`:
- `773229e8753979ecfa66ec588b8626b96d1325a8` — change the contract-file link to `modules/hr/view_contract.php?contract_id=...`.
- `a6576eb379988d11f8a112f9c6c073a14ee84c2a` — add the protected viewer. It applies the same HR manager / HR staff / admin role gate as the contract register, resolves the file through the existing contract record, confines real paths to `storage/hr_contracts/`, validates allowed extension and detected MIME type, and streams supported files inline. The storage-wide deny rule is intentionally unchanged; no schema change or migration is needed.

**Verification status: SOURCE COMMITTED; local PHP lint and browser verification remain pending.** After pulling `main`, lint both `modules/hr/contracts.php` and `modules/hr/view_contract.php`, then open an uploaded PDF from the HR contract register while logged in with an allowed HR role. Also verify the raw `storage/hr_contracts/...` URL remains forbidden and a logged-out/non-HR request to the viewer is denied.


## 2026-10-21 — Contract preview, print, and download controls

The HR contract file route now defaults to an authorized HTML preview page rather than returning the file response as the top-level page. The preview embeds the protected file endpoint, provides an explicit **طباعة** action for PDFs and an explicit **تنزيل الملف** action, and retains **فتح الملف في تبويب جديد**. The file endpoint uses `Content-Disposition: inline` by default and switches to `attachment` only when the explicit download action is requested. Storage remains protected by `storage/.htaccess`; no database/schema changes were made.

Commit: `ac91f1b65f87300114f53ceeae98b28734f90d64`.

Verification boundary: source is committed to `main`; local PHP 8.2 lint and browser behavior are not yet verified. In particular, browser PDF handling can still depend on client settings, so confirm the embedded PDF preview and print/download controls in the local Chrome/XAMPP environment before calling this runtime-verified.


## 2026-10-21 — Reverted contract preview wrapper

At the user's request, removed the additional HTML preview wrapper and restored the prior direct authorized file response in `modules/hr/view_contract.php`. The HR contract register continues linking to that protected route; `storage/.htaccess` remains restrictive. The route sends the file with `Content-Disposition: inline`, allowing the browser's PDF viewer/settings to determine inline display versus download. No database/schema changes.

Commit: `e3b458eb57e618acaf8c8bb0a06df79cd02ece7e`. Local PHP 8.2 lint and browser behavior remain unverified.


---

## 2026-10-22 — Nanny group bulk verification

**Status: IMPLEMENTATION COMMITTED; VERIFICATION PENDING.**

The existing `modules/accounting/group_verification.php` now supports bulk marking of one verification check across selected families or marking all three checks for selected families. One-check operations preserve the other two flags. Per-family audit rows are written transactionally; bulk changes are restricted server-side to the Nanny's active group assignment and a group in `pending` verification state. Individual verification and group submission now also validate the assignment/scope at the server boundary.

No schema migration was needed. The existing `nanny_family_verifications` unique key is `(family_id, month)`, and the existing audit table supports before/after flags.

A dedicated continuation document is `docs/ACCOUNTING_NANNY_GROUP_VERIFICATION_CONTINUATION.md`. GitHub Actions run `39715384849` passed the `php-lint` job, which included this page. Local browser/runtime acceptance remains pending. The broad workflow's separate `migration-full-schema` job failed at its existing Stage 5 salary-advance payroll integration check (`يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع`); this is unrelated to the Nanny page and must not reopen the closed salary-advance scope.


## 2026-10-22 — Nanny group verification control consistency

Follow-up server-side guard committed as `e50686f0a8492506459e4465513d03778debc498`; final pending-only UI markup correction committed as `da5320d05e2656bb9d6e1f4a7a94b94b032fd177`. Individual verification requires active assignment, family membership, and `verification_status = 'pending'` server-side; the UI renders the individual action/modal only for pending groups. No group status or verification data was changed. PHP lint passed in GitHub Actions run `39716749893`; local browser/database verification remains pending.

## 2026-10-22 — Nanny preliminary monthly family verification

Added `modules/accounting/nanny_pre_verification.php` as a separate Nanny workspace and linked it from the Nanny sidebar and the existing group-verification page.

The page reuses `nanny_family_verifications` for the displayed payment month (`YYYY-MM`); it introduces no separate preliminary status and requires no schema migration. Added English compatibility translations for the new Arabic UI strings in `lang/bridge/ar_to_en.php`. Individual and bulk changes use the existing three flags and append per-family `verification_audit_log` records transactionally. Single-check bulk operations preserve the other flags; individual review can explicitly change or clear flags. The bulk selection is fully validated before writes.

Server-side scope is limited to the logged-in Nanny's active assignments and pending groups. Because the schema stores one verification row per family/month rather than per group, families also present in active non-pending groups are excluded to avoid modifying a shared record already used by a submitted/locked group. This page does not submit groups to Accounting.

**Verification boundary:** source committed to `main`; PHP 8.2 syntax lint PASS in GitHub Actions run `39719045605` (the `php-lint` job explicitly includes `modules/accounting/nanny_pre_verification.php`); local XAMPP/browser/database acceptance remains pending. Use the existing `ahl_el_kheir` database only; do not create a disposable database or reset operational data.


## 2026-10-22 — Nanny monthly verification scope and month selection

The preliminary verification page now accepts a validated `YYYY-MM` month and lists all active families with active children in active groups currently assigned to the logged-in Nanny, regardless of each group's global verification status. Eligibility and save-time authorization use the same assignment/family scope; monthly values remain in the existing `nanny_family_verifications` rows keyed by family and month, and all changes retain transactional per-family audit logging. No schema or existing data changes were made.

The group-verification page now uses the selected month for reads and writes, carries it through navigation and POST forms, and allows the assigned Nanny to review/edit that month's verification flags for a family in any active assigned group. The group itself can still be submitted only under the existing pending-state rule; downstream submission/accounting behavior was not changed.

Source commits: `53190c05f7f4ba4bcfd0952e8a5fc17129d8a1f3`, `fd5d9c70e7b1c0be75e7a10b8823982f79f30f95`, `23722801177e921a82850b9c8ff15fcd6838fe55`, `7198ef5a3a488a404dfb73b41bc2c72e73def6a1`. Static/runtime verification is pending; do not claim the change is runtime verified until PHP syntax and local XAMPP/browser acceptance are confirmed.


## 2026-10-22 — Shared family/month verification visibility correction

Source commit: `f108ac6f43a71b210518e8d932977e3f0a1ccd11` updates `modules/accounting/lib_group_workflow.php`. The existing schema stores one verification record per family/month, but `get_latest_verification_status()` also filtered by the last verifier's `nanny_id`. That made the same shared family/month record appear unverified when read by a different Nanny assigned to another group containing that family. The lookup now uses the family/month key; `nanny_id` remains attribution for the latest verification action, not a visibility filter.

No schema or data changes were made. This affects the preliminary Nanny page and group verification reads that share this helper. **Verification boundary:** source committed to `main`; PHP lint, relevant regression checks, and local XAMPP/browser acceptance are still pending. Verify that the same family's flags for a selected month remain consistent between the preliminary page and each assigned group, including when the record's last verifier differs, and that another month remains independent. Do not claim runtime acceptance until tested against the existing `ahl_el_kheir` database.


## 2026-10-22 — Monthly family verification selection follow-up

After the user reported that the bulk-verification UI still showed “لم يتم تحديد أسر” after selecting families, `modules/accounting/nanny_pre_verification.php` was updated in commit `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`.

The page now mirrors checked family IDs into hidden controls physically inside `bulkVerificationForm`, synchronizes that mirror when individual or select-all checkboxes change, and uses the synchronized selection count for confirmation. After confirmation it uses `requestSubmit()` where available so the shared same-page POST handler can process the request rather than bypassing the submit event with `form.submit()`.

No schema/database changes were made. Source-level implementation is committed; PHP syntax check, browser behavior, and real local XAMPP/database verification are **pending**. The user will pull `main` and perform the local acceptance steps; do not mark fixed until the bank-only operation succeeds and preserves the orphan flag.


## 2026-10-22 — Closed-group verification controls refined for Nanny workflow

The user clarified that a Nanny may reconnect with a family later and need to reverify that family for an older payment month, even when the group is already closed. Commit `821774a06aa6434a31bc4538bfe37217ebafe55c` supersedes the earlier overly restrictive closed-group UI rule in `modules/accounting/group_verification.php`:

- Individual family confirmation/edit actions remain available for any selected active group still assigned to the logged-in Nanny, including a closed group, and the individual POST guard permits this same assigned-group workflow. The selected payment month continues to scope the family/month verification record.
- Bulk verification controls and family-selection checkboxes remain available only when the group status is `pending`. Bulk POSTs still require pending status, so a closed/processed group cannot be bulk-modified.
- Group submission remains pending-only. No group status, schema, or verification data was changed by these source edits.

This correction supersedes the preceding note that all closed-group individual controls should be hidden. The user must pull `main`, run PHP lint on `modules/accounting/group_verification.php`, and browser-test: closed group + historical/future month shows individual actions but no bulk controls; pending group shows both individual and bulk controls; individual save affects only the selected month; bulk POST against a non-pending group is rejected. Local runtime acceptance remains pending.

## 2026-10-22 — Nanny monthly group verification acceptance CLOSED

The user pulled the latest `main` (`c3493831`), ran `D:\\xampp\\php\\php.exe -l modules\\accounting\\group_verification.php`, and confirmed **no PHP syntax errors**. The user then confirmed the updated workflow behaves as intended. This closes the current Nanny verification-control acceptance at the user's reported boundary:

- The selected payment month scopes the shared family/month verification record.
- Individual family verification remains available in an active group assigned to the Nanny, including a closed group, so a family can be reverified for a historical payment month.
- Bulk selection and bulk verification remain pending-group-only; non-pending bulk POSTs are rejected server-side.
- Group submission remains pending-only.
- No schema migration or database/data change was part of this correction.

Source correction: `821774a06aa6434a31bc4538bfe37217ebafe55c`. Bulk-selection synchronization fix: `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`. Related documentation has been updated to record this user-confirmed acceptance. Do not reopen this workflow without concrete regression evidence.

**Next task from the remaining open documentation:** continue the HR employment-contract portal's pending local verification, starting with the latest `docs/HR_EMPLOYMENT_CONTRACT_CONTINUATION.md` instructions and current `main` source. Do not resume the Contract Builder toast/brief-refresh visual issue; the user explicitly closed that task.

## 2026-10-22 — HR job-description migration safety review

Before asking the user to apply the pending job-description migrations, source review found a concrete data-preservation risk in `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql`: its qualification-baseline update could change an already `approved` active description and downgrade its status to `needs_review`, contrary to the documented preservation boundary. Commit `0e7ec314d4335d2ff3428640b76b6c18629fc86b` adds `AND description_status <> 'approved'` to that migration's update predicate. This is a migration-file correction only; no local database was accessed or changed. Existing approved descriptions are now excluded from this baseline update. Do not claim the migrations are applied or the portal runtime-verified. The remaining work is to inspect the entire migration sequence against the current schema and actual local database state, then perform the local application and focused HR portal checks without touching unrelated pages or existing contract files.

## 2026-10-22 — HR portal pre-migration safety guard

Source commits `4173bb3d1a42a2cc95e796e5d66a3d9e0134d0f9` (`modules/hr/contract_builder.php`) and `5258fa82988902811212544d2a01ef70d8e2e45b` (`modules/hr/job_description_catalogue.php`) add a read-only `information_schema.tables` preflight for `hr_job_description_templates`. If the migration has not been applied, Contract Builder now continues with its existing employee/contract fields and manual job-description entry, shows a warning that automatic catalogue prefill is unavailable, and avoids querying the missing table. The new catalogue page displays a clear HTTP 503 migration-required message instead of failing on downstream SQL. This guard performs no DDL and changes no business data. When the table exists, the normal catalogue query path is unchanged.

These are source-only changes committed on `main`; PHP lint and local XAMPP behavior are not yet verified. Next: check the relevant GitHub Actions run, then complete the migration-order/data-preservation audit and the local migration + HR portal acceptance. Do not mark runtime verified without local evidence.

## 2026-10-22 — HR job-description migration safety follow-up

- Audited the eight `2026-10-20_hr_job_description_*` migrations for preservation of approved catalogue records.
- Corrected `2026-10-20_hr_job_description_proposals.sql` so its proposed-field backfill and HR Test deactivation skip rows with `description_status = 'approved'`. The qualification baseline migration also excludes approved rows. No database was changed by this repository edit.
- Expanded `.github/workflows/php-syntax-check.yml` to PHP-lint `modules/hr/contract_builder.php` and `modules/hr/job_description_catalogue.php`.
- Latest full-schema CI run imported the checked-in schema and passed its migration/backfill step, but failed in the subsequent Stage 5 salary-advance regression step. This is not evidence of an HR migration failure; the exact regression failure remains to be diagnosed before treating the whole workflow as green.
- Local XAMPP/MariaDB migrations and browser/PDF verification remain pending. Back up `ahl_el_kheir` before applying migrations; do not run DDL from PHP. Preserve local untracked files.

## 2026-10-22 — HR migration CI coverage

- Added the `hr-job-description-migrations` CI job in `.github/workflows/php-syntax-check.yml`. It imports the checked-in schema into a disposable MariaDB 10.4.32 instance, applies all eight `2026-10-20_hr_job_description_*` migrations in filename sequence, and asserts that an approved HR-manager title-only catalogue row retains its approved status and blank content fields.
- The same workflow now PHP-lints `modules/hr/contract_builder.php` and `modules/hr/job_description_catalogue.php`.
- Repository-side safeguards are committed; local `ahl_el_kheir` remains untouched. Do not apply the migrations locally until the CI migration job completes successfully and a backup is available. Local page, prefill and PDF verification is still pending.

## 2026-10-22 — HR job-description proposals migration: local verification update

Applied locally: `database/migrations/2026-10-20_hr_job_description_proposals.sql` only. The user had backed up `ahl_el_kheir` first. The mysql command returned no reported SQL error. Follow-up read-only queries verified 23 active `needs_review` rows and zero blank reporting lines; approved rows 6 and 20 remain approved/active with reporting lines; `HR Test` row 11 is inactive and still `needs_review`.

**Status:** proposals migration's reporting-line result and approved-row guard are verified against the local database. Do not describe all eight job-description migrations as applied. Focused local browser/prefill/PDF acceptance is still pending; PHP syntax checks and the dedicated migration CI job were already passing. The separate full-schema CI workflow's Stage 5 salary-advance regression failure remains unrelated and must not be represented as an HR migration failure or as an overall green workflow.


## 2026-10-22 — AI assistant initial source milestone

Added a shared authenticated assistant panel, a role-filtered local employee-guide lookup, and an optional Gemini free-tier fallback for general non-confidential questions after per-question consent. The server resolves the active role from the database and enforces POST, session authentication, CSRF, input limits, and per-session rate limits. Internal documentation, source, database records, user identity, and role are not sent to Google. No schema change or business mutation capability was added.

Source files: includes/ai_assistant_widget.php, includes/footer.php, modules/ai_assistant/chat.php, .github/workflows/php-syntax-check.yml. Continuation and limitations: docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md. Source is committed; PHP CI, local runtime, and InfinityFree connectivity remain pending. Do not describe as runtime verified.


### 2026-10-22 — AI assistant Arabic retrieval follow-up
The first local browser test showed the assistant UI works, but the Arabic question about the first step after sign-in missed local retrieval and reached the unconfigured-Gemini fallback. Root cause: the canonical employee guide described sign-in only in English while retrieval is keyword-based. The guide now includes a reviewed Arabic/English post-login instruction: confirm the dashboard matches the user's job role, and complete a required password change if prompted. Retest the exact question locally before configuring Gemini. No application logic, schema, or privacy boundary changed.


### 2026-10-22 — AI assistant UI refinement
The assistant widget now omits the two introductory privacy notices from the panel, no longer renders source filenames below answers, shows a Font Awesome comments icon beside the launcher text, and starts with the Google-consent checkbox checked. The user's checkbox choice is retained between messages instead of being reset after each submission. The endpoint still performs local-first retrieval and still blocks internal/sensitive prompts from Google; a checked box does not bypass those filters. Source commit: `5149e20b6e42a393ca59fd6d84cec12edaf08372`. PHP lint and browser regression checks for this UI refinement are pending.


### 2026-10-22 — AI assistant chat history
The existing assistant now has source implementation for saved conversations, history listing/loading, and a new-chat action. Conversations and messages are scoped to the authenticated user in the endpoint; saved history is not supplied to Gemini. Migration `database/migrations/2026-10-22_ai_assistant_chat_history.sql` uses `users.id INT UNSIGNED`, confirmed by local `SHOW CREATE TABLE users`. Migration application and local runtime/security verification are pending. Do not describe this as runtime verified yet. Details: `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.

## 2026-10-22 — AI assistant follow-up acceptance status

The user reported that Arabic post-login guidance worked on a subsequent attempt, while the Arabic Nanny assigned-groups question missed local retrieval and on-device speech dictation failed. Follow-up source commits add Arabic Nanny navigation guidance, Arabic normalization, Arabic internal-workflow blocking from external AI, and visible browser speech error codes. These changes are committed on `main`; local lint and browser retest remain pending. Voice support must not be described as fixed until the exact browser error is diagnosed and successful dictation is verified. See `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.

## 2026-10-22 — AI assistant launch-readiness follow-up

A deterministic Arabic answer for the Nanny's assigned-groups workflow was committed to main (`dc0e7640fe98cb134590c0ec3e314c80133e16f4`), with role-code case normalization. It must still be linted and browser-tested locally. Voice input remains unresolved: the browser reports `language-not-supported` for on-device Arabic speech. Online voice recognition must not be enabled without the project owner's explicit privacy decision and a clear consent flow. See the AI assistant continuation document.


## 2026-10-22 — AI assistant online voice consent milestone
The owner approved online Arabic speech recognition with clear consent before audio processing and a typing fallback. Source commit `18bf40af2f905b5509396d92e17374c0c79ecd7c` adds an Arabic/English consent prompt before browser speech recognition, temporary page-only consent, editable transcripts, and non-technical failure messages. This is a source milestone only; local PHP lint and real-browser acceptance remain pending. Do not describe voice as runtime-verified until those checks pass. Detailed checklist: `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.


## 2026-10-22 — AI assistant voice diagnostics
The user reports voice recognition fails after consent. The widget previously hid the specific browser failure behind a generic message. Commit `753a3b225c1a992bcb3a6b1ce627ea365fef3092` adds plain-language handling for common speech-recognition errors. Runtime success is not claimed; local lint and browser retest are pending. Details: `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`.
## 2026-10-22 — AI assistant voice transcription implementation

Implemented the owner-approved direct Gemini audio-transcription path on `main` (`d5d91e4`, `d30efdb`). Audio recording requires explicit consent, is sent to Gemini through the authenticated PHP endpoint, is not stored in chat history, and returns editable text without auto-submission. API key remains server-side. PHP lint and local browser/runtime acceptance are pending; do not mark voice fixed until verified against the existing local app and database.


## 2026-10-22 — Gemini audio transcription diagnostic follow-up

The user-approved MediaRecorder → Gemini transcription path reached the endpoint but returned the generic upstream-failure response in the browser. Source audit found the endpoint was discarding both cURL transport diagnostics and Gemini's structured error response, preventing a root-cause diagnosis. Commit `130af192c49aafae11c1369e7e884e9367016ac8` now logs the HTTP status, cURL errno/error, and a bounded Gemini error status/message to the PHP error log while keeping the user-facing response generic and never logging the API key or audio payload. It also removes the undefined `$lang` reference from the transcription prompt, using one language-neutral bilingual prompt.

**Verification is pending.** The repository connector cannot run PHP lint or exercise the user's local Apache/PHP/Gemini request. Pull `main`, run `D:\\xampp\\php\\php.exe -l D:\\xampp\\htdocs\\AhlElKheir\\modules\\ai_assistant\\chat.php`, then make one short consented recording. If it fails, inspect `D:\\xampp\\apache\\logs\\error.log` for lines beginning `[Ahl El Kheir AI transcription]` and share only those lines; do not share the API key or audio. This is diagnostic instrumentation, not a claim that transcription now succeeds.

## 2026-10-22 — AI assistant voice transcription diagnostic status

The recorded-audio transcription endpoint returns an application-generated JSON `502` on cURL failure or a non-2xx Gemini response. The exact root cause remains unconfirmed; no matching log line was found in the initially checked Apache/PHP log folders. Commit `3b7cc562c57b87533678146412cf0f44f5b1ca7c` adds safe user-facing Arabic/English failure categories without exposing keys/audio. Status: **DIAGNOSIS IN PROGRESS — NOT FIXED OR RUNTIME VERIFIED**. Next gate is one short post-pull recording and capturing the displayed friendly error, then a targeted correction.

## 2026-10-22 — AI assistant voice transcription model correction

The observed user-facing error category was upstream NOT_FOUND. Source default gemini-2.5-flash is no longer available in the Gemini Developer API after its documented October 16, 2026 shutdown. Commit b4b7c6e7a3069a0c8d5eb254b37db9d9941f5927 changes the default to Google's documented speech-to-text model gemini-3.5-transcribe. An explicit GEMINI_MODEL environment variable or production config/hosting.php ai.gemini_model setting overrides this default and must not retain the retired model. Status: SOURCE CORRECTED; LOCAL LINT AND RUNTIME TEST PENDING.



## 2026-10-22 — Gemini transcription API contract correction

The user tested the updated dedicated model and received “No usable transcript was returned.” The endpoint had been changed to `gemini-3.5-transcribe` but still called the legacy `generateContent` endpoint and parsed the legacy `candidates[0].content.parts[0].text` response. Official Google documentation specifies that Gemini 3.5 Transcribe uses `POST /v1beta/interactions`, accepts audio content with `type`, `data`, and `mime_type`, and returns the transcript in `output_text` (or text content within response steps). This explains the observed successful upstream response with an empty transcript under the old parser.

Commit `7f1ab66a8e8b386eca3bfa4d0482138d82658241` changes the audio request to the Interactions API, uses its `transcription_config.mode=smart`, maps browser `audio/mp4` to the API's documented `audio/m4a` MIME type, and reads `output_text` with a steps-content fallback. It preserves explicit consent, the editable composer, server-side API-key handling, existing size/rate checks, and typing fallback. Official reference: https://ai.google.dev/gemini-api/docs/transcribe and https://ai.google.dev/api/interactions-api-v1.

**Verification status: source correction committed on main; local PHP lint and one real-browser Arabic/English transcription test are still pending.** Do not claim the transcription path is fixed until the user confirms that words appear in the editable composer without automatic submission. If the next test fails, diagnose the exact new user-facing response and existing server log evidence before changing code again.


## 2026-10-22 — Gemini transcription still returns empty text

After the Interactions API request/response correction, the owner tested both Arabic and English and still received the no-usable-transcript message. The precise provider response shape remains unverified. Commit `1b733cc0fef5aeca86b3753230afced29429509d` adds safe response-shape diagnostics only (JSON validity, top-level keys, interaction status, output text length, and step/output counts); it never logs the audio, API key, or transcript. The next action is one local pull, PHP lint, one short consented recording, then inspect only the matching `[Ahl El Kheir AI transcription] Empty transcript response shape:` line from Apache's error log. Diagnose that evidence before changing request structure again. Runtime success is not established.

## 2026-10-22 — AI assistant voice transcription follow-up

Commit 87c37e4238858b6e126382f41476db8f49929e18 changes Gemini 3.5 Transcribe to the documented REST flow: Files API upload followed by an Interactions API request using the returned file URI. Runtime verification is pending on local XAMPP: PHP 8.2 syntax check and one consented browser recording. The previous Apache log excerpt contained startup notices only and did not provide the application diagnostic. Do not report the feature as fixed until the transcript is inserted into the editable composer without automatic submission.


## 2026-10-23 — AI assistant text and voice status
Text chat received a source review. The general-text fallback default now uses `gemini-3.5-flash-lite`; the microphone UI is disabled and marked as future development. Local guide retrieval, consent/filter flow, and user-scoped history were reviewed at source level. Local PHP lint and browser/runtime acceptance remain pending, including checking whether `GEMINI_MODEL` or `hosting.php` overrides the default. Do not claim runtime verification yet.

## 2026-10-23 — AI assistant navigation continuity
`includes/ai_assistant_widget.php` now persists the active conversation ID in tab-scoped `sessionStorage`, restores that conversation from the existing user-scoped history endpoint after page navigation, resets the active ID on explicit New chat, and closes the panel on mouse leave. Commits `9db6c37945f2af5e775dc4f9bf651acee7d657b7` and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`. No schema change. Local syntax/browser verification is pending; do not claim runtime acceptance until the user tests navigation and mouseleave.

## 2026-10-23 — AI assistant text chat implementation boundary

The text-chat implementation task is closed at the source/documentation boundary. The shared assistant retains local-first role-limited guidance, authenticated/CSRF-protected text requests, existing Google fallback consent and safety filters, and user-scoped conversation history. The active conversation is persisted per browser tab and restored after page navigation using the existing owner-checked history endpoint. Explicit New chat clears the active conversation; the floating panel closes on mouseleave. Relevant commits: `2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b`, `75c52595eef9cd6dd2d8ab71756f06103601c1c8`, `9db6c37945f2af5e775dc4f9bf651acee7d657b7`, and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb`.

The owner confirmed a local-guide text answer works. Local PHP lint and the cross-page/mouseleave browser acceptance were not executed by this repository-editing session; do not describe those checks as passed unless the owner confirms them. If still outstanding, run the two PHP syntax checks and the short browser acceptance checklist documented in `docs/CHATGPT_AI_ASSISTANT_CONTINUATION.md`. Voice remains deferred and must not be folded into this task.

This is a completed implementation checkpoint, not a direction to reopen AI chat. Begin the next session by inspecting the canonical documents and current repository state to choose the next open task from evidence.

## 2026-10-23 — AI assistant account isolation and VGM guidance correction

A report from a second user exposed that active conversation storage was not isolated per account in the same browser tab. The server correctly rejected a conversation belonging to another user; the widget's key was shared and the failure path retained the stale ID. Commit `ad6d2c7ede03b7e665ff11b74af55949818b5a76` scopes the key by signed-in user ID and clears the active pointer on restore failure.

The reported question about assigning families to nannies was missing from local retrieval. `modules/deputy_gm/groups.php` confirms VGM-only group management, eligible child membership, and nanny-to-group assignment. Commits `fb030a08ba65093b51cf31cba4979189457e87bf` and `4fe4a98dc3bcf40e5122c04f069b1f3a40cb99dc` add the guide entry and a role-restricted deterministic answer. No schema change.

Status: source corrections committed; PHP lint and browser/runtime acceptance are pending. Do not claim full verification until account switching, same-user history, the VGM answer, and non-VGM role restrictions pass locally.


## 2026-10-23 — Final AI assistant acceptance preparation

A static acceptance audit of current `main` found one concrete regression that prevented the agreed voice-input path from being tested or used: the microphone button still had the HTML `disabled` attribute and the widget script explicitly disabled it with the old “planned for a future update” label, despite the consented MediaRecorder → Gemini transcription implementation being present. This stale deferral code made the transcription implementation unreachable from the UI.

The smallest correction removes the stale disabled state and future-development label while retaining the explicit consent dialog, recording limit, authenticated PHP endpoint, editable transcript, and no-auto-submit behavior. No schema/database changes.

**Status:** source-level root cause identified and correction committed on `main` in `67d26422ee41c63f644ba8e05cd6c5ee7d2e623e`. Still pending local PHP lint and real-browser acceptance; repository tooling cannot execute the user's XAMPP app, microphone, session, database, or configured Gemini credentials. The owner should be asked to pull/test only after the remaining source checks and documentation are committed. Required final browser matrix: account switching/history privacy; same-user navigation restore/New chat/mouseleave; VGM versus non-VGM local answer; no-consent and internal/sensitive-question blocking; eligible general fallback only with consent; voice consent decline, microphone permission, short Arabic/English recording, editable transcript/no auto-submit, and text fallback. Do not mark end-to-end complete until these local checks pass.


## 2026-10-23 — Systematic document archive design opened

The new independent task is a systematic archive for receipts and uploaded documents. Read `docs/DOCUMENT_ARCHIVE_CONTINUATION.md` before implementation. The current checkpoint is discovery/design only: no application code, database schema, stored files, or business records were changed. Source inspection found multiple storage roots and module-specific serving routes, plus runtime DDL in `modules/families/documents.php` and `modules/transactions/receipt_file.php`; preserve these as explicit audit findings and do not use runtime DDL. Recommended approach is a central searchable catalogue/version/audit layer, preserving domain authorization and business records, registering historical files in place first, and migrating physical files only after schema and host behavior are verified. Next: finish upload/serving/schema inventory, inspect the actual local `ahl_el_kheir` schema and hosting constraints, then finalize a migration design before implementation.


## 2026-10-23 — Document archive source inventory checkpoint

**Status: discovery/source audit advanced; no application code or schema changed.** Source files inspected on `main` include transaction/sponsor receipt submission and returned-record resubmission, FINA external collection upload and settlement evidence, monthly disbursement/family/return receipt upload, family document upload/delete/serve, project document and expense-receipt upload/replacement/serve, HR contract upload/serve, salary-advance payment and direct-repayment evidence upload/serve, and message attachment upload/delete/download.

### Confirmed workflow findings (source-level only)

- Transaction/sponsor receipts: `modules/transactions/create.php` writes shared physical receipts under `storage/receipts`; accepts JPG/JPEG/PNG/PDF by extension and 10 MB size, generates unique names, and supports separate line and unified receipts. Returned transaction/payment resubmission replaces the DB path then invokes an unreferenced-path cleanup helper after commit. Existing path-sharing in the DB means cleanup must be reviewed as a shared-reference rule, never blindly copied into archive code. The source does not perform detected MIME validation in these upload branches.
- FINA external receipts: `modules/transactions/fina_payment_create.php` stores under `storage/receipts` and removes the new file on DB transaction failure. Settlement transfer evidence uses `storage/receipts/fina_settlements`; its caller removes the uploaded file when transfer processing fails. Confirmed from source, not runtime.
- Disbursement receipts: `modules/accounting/disbursements.php` has separate family, return, and bank-transfer receipt upload paths. Most inspected branches validate detected MIME and a 10 MB cap. File writes and DB/journal updates span multi-step workflows, so cleanup and rollback behavior must be mapped per action before central integration.
- Family documents: `modules/families/documents.php` accepts PDF/JPG/JPEG/PNG by extension and 10 MB size, creates date/sequence names, inserts `family_documents` metadata, and logs upload. Its delete flow unlinks the physical file before deleting the DB row. A shared/versioned archive must change that lifecycle deliberately; no direct replacement is safe. Existing runtime DDL remains an unrelated pre-existing issue to address only in a justified scoped change.
- Project documents: `modules/projects/view.php` uses detected MIME allowlists, opaque random names, and a 10 MB limit. Replacing a document updates the stored path and then unlinks the prior path; expense receipt update/delete paths also unlink stored files. These are concrete archive-integration points because historical versions and shared references cannot be preserved by the current unlink behavior without a coordinated change.
- HR contracts: `modules/hr/contracts.php` accepts PDF/JPG/JPEG/PNG by extension with a 10 MB limit and opaque generated names, but the upload branch does not detect MIME. On replacing a contract file it updates `hr_employee_contracts.contract_file_path` without deleting the old file, so the prior physical file can become unreferenced. `modules/hr/view_contract.php` performs stronger path containment and detected-MIME validation during retrieval.
- Salary-advance receipt/evidence: `modules/hr/lib_salary_advance_accounting.php` validates detected MIME and a 5 MB limit, records audit metadata, replaces the current payment-receipt row in a transaction, and deletes the old file after commit. `modules/hr/lib_salary_advance_repayment_evidence.php` follows a similar row-replacement pattern and removes the prior file after success. The archive integration must preserve evidence history rather than silently discard replaced bytes.
- Message attachments: `modules/messages/attachment.php` uses extension plus detected-MIME allowlists, generated stored names, and removes a newly uploaded file if DB insertion fails. Its delete path removes the DB row then unlinks the file. `modules/messages/download_attachment.php` performs authenticated record lookup and streams the attachment.
- Serving routes are not uniform in caching, MIME source, path containment, role/scope checks, and disposition. Keep each domain's authorization adapter and existing URLs while introducing archive metadata; an archive ID or search result must never independently authorize access.

### Archive implications

1. Register historical files in place first; do not move/rename existing files during initial rollout.
2. Before the pilot, specify version creation and failure/rollback boundaries for every replacement path, and prevent physical deletion while any source record/version references the file.
3. Do not reuse existing unlink logic as an archive deletion API. Deletion must be a reviewed business action plus reference/version checks and audit evidence.
4. New upload integration needs consistent detected-MIME validation, opaque storage identity, checksum capture, CSRF, size limits, cleanup after failed DB work, and no-store/nosniff streaming where appropriate. Existing workflows vary; this is a design target, not a claim that every current endpoint is vulnerable.
5. No runtime verification of local Apache/production storage behavior was performed by this source pass. No code, schema, files, or business records were changed.

### Next exact action

Finish the remaining source/schema matrix for all source tables and deletion/reset workflows, then compare it with `database/ahl_el_kheir.sql` and the actual `ahl_el_kheir` schema before proposing explicit migration DDL. Do not implement the archive tables or change file lifecycle until that schema contract and the pilot category are justified by evidence.



## 2026-10-23 — Archive schema migration drafted (not applied)

A schema-only migration has been added on main:
`database/migrations/2026-10-23_document_archive_catalogue.sql`
Commit: `4b5a141511a4d2469f36a3e2df7af2ad31d990d7`

It defines four catalogue tables: `document_archive_blobs`, `document_archive_documents`, `document_archive_versions`, `document_archive_source_links`, and `document_archive_events` (five tables; the catalogue responsibilities remain stored blobs, logical documents, immutable versions, source links, and audit events). Internal foreign keys restrict deletion of referenced archive records. Source-record IDs are typed locators without guessed foreign keys to domain tables. User actor IDs intentionally have no FK so historical attribution is not coupled to user-row deletion. A unique storage key supports registering one shared physical path once; a unique source locator tracks the current source mapping; replacement history is represented by version rows plus audit events.

Evidence used: the repository SQL dump was retrieved via GitHub contents and parsed (5,691,158 characters); it defines the existing project tables and indexes, and contains no `document_archive_*` tables. The dump's project schema agrees with the supplied actual-DB column inventory for `project_documents` and `project_payment_evidence`. The migration follows the repository's existing InnoDB/utf8mb4/CREATE TABLE IF NOT EXISTS migration style.

**Important status:** migration file only. It has not been executed against `ahl_el_kheir`; no PHP integration, file backfill, or runtime test has occurred. No existing files or source records have been changed. Before applying it, verify the live DB still has no archive objects and run the DDL against a disposable schema copy or an isolated test environment. Then implement an idempotent, report-only project-file registration/backfill and the project-document/payment-evidence adapters. Do not start physical migration or change replacement/deletion behavior until those adapters and acceptance checks are ready.


---

## 2026-10-23 — Document archive checkpoint

The archive catalogue migration (`database/migrations/2026-10-23_document_archive_catalogue.sql`) is committed. The user verified the five archive tables, indexes, and foreign keys in local database `ahl_el_kheir`; this does not claim production deployment.

The active step is a read-only inventory of project document and payment receipt references via `tools/report_project_document_inventory.php`. It is committed but not yet locally executed, so no filesystem inventory findings or runtime verification are claimed. Continue using `docs/DOCUMENT_ARCHIVE_CONTINUATION.md`.


---

## 2026-10-23 — Project archive search integration and automatic upload registration (source implementation; runtime pending)

The user's design decision is to extend the existing role-based Global Search Center rather than create a separate archive search page.

Committed source changes:
- `config/search_permissions.php`: adds the `documents` search type to the existing role policy for roles that already have search access; HR roles previously blocked from Global Search and the social-media role do not gain document search access.
- `modules/search/index.php`: adds current archived project documents and documented payment receipts to Global Search. Search requires an active archive source link, a matching current business-row path, a present blob, and `akp_can_view_project(project_id)`. Payment receipts also respect the existing receipt endpoint role allowlist. Results open the existing permission-checked endpoints; archive IDs do not authorize downloads.
- `modules/projects/lib_project_document_archive.php`: adds a transaction-bound project-file archive adapter. It verifies source ownership/path, storage-root containment, actual MIME/size/SHA-256, creates or reuses a verified blob, adds immutable versions, updates source links, and writes archive events. A source-unlink helper preserves physical bytes.
- `modules/projects/view.php`: wires archive registration to general document uploads/replacements, expense receipts, labor-payment receipts, and funding-return proof. Replacement no longer deletes the prior file. Explicit deletion paths unlink archive source links and retain archived bytes.
- `modules/projects/view_fm.php`: wires registration to payment-receipt upload/replacement and preserves previous receipt bytes after replacement.

The catalogue migration is already applied in the user's local `ahl_el_kheir` database. Existing-document backfill remains a one-time migration operation, not a routine user workflow. The last reviewed dry-run reported 16 references and 16 planned registrations, with 3 missing-file references (test artifacts) and no invalid paths or metadata conflicts. Missing files must not be recreated.

**Status: SOURCE IMPLEMENTATION COMMITTED; LOCAL LINT / RUNTIME VERIFICATION PENDING.** Do not describe the feature as complete until PHP lint passes for the changed files, the user applies the reviewed backfill and confirms idempotence, and browser tests prove role-scoped search, authorized opening, denied cross-project access, upload registration, replacement version preservation, and receipt replacement behavior.


### Archive follow-up — historical versions searchable (2026-10-23; runtime pending)

Global Search now lists all present archive versions for active project source links, labels current versus previous versions, and opens a selected version through `modules/projects/project_archive_version.php`. The endpoint validates the archive version-to-source association, resolves the project from the source record (or archive event metadata for a deleted source row), rechecks `akp_can_view_project()`, applies the existing payment receipt role allowlist, enforces canonical project-storage containment, and verifies the blob size/SHA-256 before serving. Historical version search/retrieval is source-implemented but not yet linted or runtime-tested locally.


### Archive follow-up — searchable retained documents after source deletion (2026-10-23; runtime pending)

The integrated archive search now includes present versions for active links and for unlinked source links whose business source row was deleted. For the latter, the project association comes from the archive event metadata and must still pass `akp_can_view_project()`; payment evidence remains restricted to roles allowed by the existing receipt endpoint. No archive row or file is deleted by this search path. This source change has not yet been locally linted or runtime-tested.

 
### Archive pilot — catalogue registration verified locally; runtime acceptance pending (2026-10-23)

User-reported evidence: all six archive/search PHP syntax checks pass. Local backfill applied 16 references (8 `project_documents`, 8 `project_payment_evidence`); second dry run reports 16 already registered, 0 to register, 0 path mismatches, 0 blob metadata mismatches, 0 reconciliation cases, and 3 known missing physical payment-evidence files. This verifies idempotent catalogue registration only. Browser acceptance of Global Search, project/role authorization, current and historical version retrieval, and upload/replacement automation remains open.


## 2026-10-23 — Document archive pilot — implementation/backfill checkpoint and scope boundary

The original pilot scope is limited to project documents (`project_documents`) and project payment evidence (`project_payment_evidence`), integrated into the existing role-based Global Search Center. Source implementation and one-time metadata registration are complete for this scope, but the overall feature is **not fully acceptance-closed** because real Apache/browser verification has not been reported.

### Verified evidence
- User-reported PHP syntax checks passed for all six changed files: `config/search_permissions.php`, `modules/search/index.php`, `modules/projects/lib_project_document_archive.php`, `modules/projects/project_archive_version.php`, `modules/projects/view.php`, and `modules/projects/view_fm.php`.
- Against local `ahl_el_kheir`, the backfill registered 16 references (8 project documents and 8 project payment-evidence references). The subsequent dry run found all 16 already registered, with zero planned registrations, path mismatches, blob metadata mismatches, or reconciliation cases. This verifies idempotent catalogue registration for the inspected references only.
- Three existing project payment-evidence references point to missing physical test-artifact files: evidence ID 4/project 7, ID 1/project 8, and ID 3/project 8. They must remain recorded as missing; do not recreate files or claim they can be retrieved.
- These results do not prove browser search, download, authorization boundaries, automatic registration during live uploads, or replacement-version retrieval.

### Scope clarification: nanny/family sponsorship payment proofs
The user clarified that the receipts they expected to see are uploaded by a nanny as proof that she paid families or delivered monthly sponsorship to families. These are **not** the project payment-evidence records covered by the pilot. Search Center's empty archive result for that nanny does not prove those files are missing; the actual family-payment workflow has not yet been traced or integrated. Do not add the nanny role to the project-payment-evidence allowlist as a workaround. Any future extension must inspect the real upload handler, schema/path columns, serving route, and family-level permission rules, then integrate automatic registration and a safe backfill into the existing Global Search Center.

### Disposition / next acceptance gate
- Mark the **project archive implementation + existing-reference backfill phase** complete, with the above evidence and limitations.
- Keep **end-to-end acceptance** open until the user tests the existing Global Search in `http://localhost:8081/AhlElKheir/`: find a known present project document and receipt, open current and previous versions after a replacement, and confirm an unauthorized cross-project request is denied. Do not use the three missing files as test fixtures.
- Treat nanny/family sponsorship payment proofs as an explicit scope extension, not as a defect that can be solved by bypassing current project receipt permissions.
- Do not claim the entire document-archiving system is done. At the start of the next repository task, reread the canonical docs and inspect the real repository state before proceeding.


## 2026-10-23 — Sponsor QR + Android scanner source checkpoint

A new user requirement is now the active work unit: every sponsor should have a QR code, the QR should appear on orphan forms associated with that sponsor, and staff should be able to scan through an Android APK. The user chose authenticated/authorized access after scanning.

Source changes on `main`:
- `modules/sponsors/lib_sponsor_qr.php` generates a QR locally through the already bundled TCPDF 2D barcode library. QR payload is only the existing sponsor profile URL.
- `modules/sponsors/view.php` adds a QR card and distinct active sponsored-orphan count.
- `modules/families/orphan_form.php` and `modules/families/orphan_profile.php` add QR codes beside each linked sponsor.
- `android/sponsor-qr-scanner/` adds a native Android camera scanner using ZXing and opens sponsor profiles in a WebView using the existing website session/authentication.
- `.github/workflows/build-sponsor-qr-scanner.yml` builds an installable debug APK artifact.

**Status: SOURCE IMPLEMENTATION ONLY; NOT VERIFIED.** No PHP lint, Android build result, Apache/browser test, physical-device scan, database count comparison, or role/scope runtime test has yet been evidenced. Follow `docs/SPONSOR_QR_ANDROID_CONTINUATION.md` for exact next steps. No schema migration was introduced. The previously documented project archive browser-acceptance gap remains separate and unchanged.


## New active task — Sponsor QR + Android scanner (2026-10-23)

See `docs/SPONSOR_QR_ANDROID_CONTINUATION.md` for requirements, implementation inventory, and acceptance plan. Current Android dependency fix: `777f9b180ce61dade8c96649a8138e7f4bb85880`, excluding obsolete Kotlin `kotlin-stdlib-jdk7` / `kotlin-stdlib-jdk8` split artifacts that CI proved duplicate classes already included in `kotlin-stdlib:1.8.22`. Fix awaits the next Actions run; APK and runtime acceptance remain unverified.


### Sponsor QR Android scanner — BuildConfig fix (2026-10-23)
Actions job `120446032500` failed Java compilation due to missing generated `BuildConfig`. Since the app uses AGP 8.7.3 and `MainActivity.java` references `BuildConfig.DEBUG`, commit `ecdc34c19f1d5f5c370a95b3a902739693710b7f` enables BuildConfig generation in the app module. New Actions run and APK assembly are still unverified.


## 2026-10-23 — Sponsor QR host diagnosis and portrait scanner change

User confirmed that opening the existing system through the laptop LAN address and scanning the newly generated sponsor QR successfully opens the sponsor profile. The previously reported “رمز غير صالح” was caused by generating the QR while the website was opened at `localhost`; that URL points to the requesting device and does not match the phone scanner's configured laptop LAN host. No origin-validation bypass was added. For physical-phone testing, generate QR codes while browsing the site using the same LAN host/port/application path configured in the APK.

Portrait change committed to main:
- `android/sponsor-qr-scanner/app/src/main/AndroidManifest.xml`: lock `.MainActivity` to portrait.
- `android/sponsor-qr-scanner/app/src/main/java/org/ahlelkhier/qrscanner/MainActivity.java`: set ZXing `setOrientationLocked(true)`, so the capture screen follows the portrait-locked activity.
- `android/sponsor-qr-scanner/README.md`: document portrait behavior and the matching-host requirement.

Source changes are committed, but the updated APK and physical-device portrait behavior are not yet verified. The Android workflow should build a new debug APK from these commits. After it succeeds, install that artifact and verify scanning while holding the phone upright. Keep existing website authentication, sponsor permissions, supervisor scope checks, and same-origin validation unchanged.


### 2026-10-23 — Portrait scanner APK build verified

GitHub Actions run [39990143104](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/39990143104) completed successfully on source commit `ea50c4f4cc24ad287381967d6c1a2c7e45a4ffd4`. PHP QR integration lint, Android duplicate-class check, Java compilation, debug APK assembly, and artifact upload all passed. Artifact: `ahl-el-kheir-sponsor-qr-scanner-debug-apk`, artifact ID `12517556746`, SHA-256 `46453215f819c7cbfb1a8bb8fd091cddde533488cd2b652061f1c2e8314b2980`, expires 2026-11-22.

Remaining acceptance gate: install this newly built APK on the user's phone and confirm the scanner stays portrait while scanning, then scan a QR generated from the same LAN base URL and verify sponsor page opening. CI build success does not prove physical-device orientation behavior.


## 2026-10-23 — Sponsor QR scanner UI + in-system APK download

The sponsor QR hostname problem is resolved at the user-verified boundary: scanning works when the site is opened using the same laptop LAN address configured in the Android app.

New source changes on main:
- Cleaned the Android Arabic UI: removed the permanent server-settings button, added a single prominent scan action and a header download action.
- Kept server URL configuration available by long-pressing the app title.
- Added modules/sponsors/download_scanner_apk.php, which requires an authenticated session and streams only storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk; the existing storage deny rule remains intact.
- Added endpoint linting and workflow path coverage.

Pending acceptance: verify the GitHub Actions run, install the newly built APK, test portrait scanning, and test authenticated APK download. The APK must be copied to storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk on the system host; the endpoint intentionally reports a missing-file 404 until that deployment step is complete.


## 2026-10-23 — Sponsor QR scanner: portrait build correction and minimal home UI

The user confirmed that APK download and installation work, but the scanner camera still opens horizontally. CI run [40001005068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001005068) provided the root cause: ZXing's library manifest sets its separate `CaptureActivity` to `sensorLandscape`, and the app's explicit `portrait` setting caused a manifest merge failure. The Android manifest now uses `tools:replace="android:screenOrientation"` on that activity to override the dependency's value. A fresh successful build and physical-phone retest are still required.

The main Android screen has been reduced to the **مسح رمز QR للكفيل** action plus the WebView required for existing website login and sponsor pages. The branding/download header and connection-status strip are removed. Long-press the scan button to change the configured server URL. The authenticated website header continues to provide the APK download link.

Do not mark portrait fixed until a fresh APK build succeeds and the user verifies portrait orientation on the physical phone. Keep sponsor URL validation, login, permissions, and supervisor scope unchanged.


## 2026-10-23 — Corrected sponsor scanner APK build verified; physical-device acceptance pending

GitHub Actions run [40001446199](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40001446199) completed successfully on commit `29eee35eb4fc3bf9869913abcd4395fea77a93e9`. The manifest merge, Java compilation, debug APK assembly, and artifact upload passed after the ZXing capture activity portrait override was changed to `tools:replace="android:screenOrientation"`. Artifact ID `12522116796`, name `ahl-el-kheir-sponsor-qr-scanner-debug-apk`, expires 2026-11-22; artifact ZIP SHA-256 `2b4eb5b801662bb05c1791ba0d0c1a5081822506c73c05a554bd55713c26ad1a`.

The home UI now has only the **مسح رمز QR للكفيل** native action and the required WebView; the extra branding/download header and connection-status strip are removed. Long-press the scan action to change server settings. The website's authenticated header remains available for downloading the APK.

The user must still install this fresh artifact and verify physical portrait scanning and sponsor-profile opening. Do not describe device orientation as verified until the user confirms it.


## 2026-10-23 — Sponsor scanner deployment closure / orphan form QR layout

The user confirmed deployment of the latest sponsor QR scanner APK to the existing in-system download path and closed that work item. The authenticated endpoint continues to serve `storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk`; future builds must replace this exact file on the target server.

The next active UI task is the orphan form header layout. In `modules/families/orphan_form.php`, sponsor QR code(s) were moved from the sponsorship table rows to the upper-left header area, aligned horizontally with the organization logo and form title. Sponsor name/code and sponsorship dates remain in their existing table rows. Source commit: `1e29a29b85b4bf4ac0da2cd7aff226019a71cff2`. Status: source change committed and PHP syntax passed in GitHub Actions run [40003543066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40003543066), job `php-lint`. Browser/print verification remains pending. Do not claim full completion until that check passes.


### 2026-10-23 correction — orphan form design preservation

User reported the prior QR placement change disturbed the original orphan form design. Commit `efe2eaf9b6aeaa1d25b7f136f9b9958f74e519a4` restores the original header row layout CSS while retaining the sponsor QR at upper-left, aligned with the existing logo and form-title row. QR is still removed from sponsorship table rows. Verify PHP syntax and local browser/print output before closure; this is not yet runtime-accepted.

### 2026-10-23 follow-up — full-width sponsorship rows

Source commit `d31c1fd6c56f25ecca15adb5833131b425264935` adds `colspan="3"` to the value cells for “مدة الكفالة” and “قيمة الكفالة”. Those rows share a table with four-column sponsor rows; spanning the remaining columns makes both row borders extend across the same full table width without changing their content or the QR header. PHP syntax and local browser/print appearance verification are still pending.


## 2026-10-24 — Flutter app cloud-build path

The existing Flutter app foundation is being built through GitHub Actions so local Flutter installation is not required merely to produce an APK. Workflow: `.github/workflows/build-flutter-mobile-app.yml`; artifact: `ahl-el-kheir-flutter-android-debug-apk`. The workflow generates Android scaffolding on the runner, runs dependency resolution, Dart analysis, a widget smoke test, and debug APK compilation. **Acceptance status remains pending until the workflow succeeds and the artifact is verified.** This is not API runtime acceptance; the mobile session migration remains unapplied and API security controls must be reviewed before any public deployment.


## 2026-10-24 — Flutter login widget test

The first cloud run 40060794073 passed dependency resolution and Dart analysis but failed the widget test before APK compilation. The test was coupled to full-app startup and asynchronous secure-storage session restoration rather than just the login UI. It has been corrected to mount LoginPage directly in a test MaterialApp. Correction commit: b3216456948e71a80ae2629961efc78352769edf. Await the next Actions run; no APK success is established yet.


## 2026-10-24 — Mobile app physical-phone connection and branding
The Flutter debug app's emulator-only default was inappropriate for the user's physical phone. User confirmed laptop LAN IP `192.168.18.9`, Apache port `881`; Flutter defaults now use `http://192.168.18.9:881/AhlElKheir/api/v1`. Login/signed-in UI uses the existing navy theme and website logo asset. CI and device verification remain pending; mobile API migration/runtime checks are not confirmed.


## 2026-10-24 — Flutter brand update analyzer follow-up
The first CI run after the mobile theme/API host change failed at `flutter analyze` because the new UI used deprecated `Color.withOpacity` calls under the current Flutter 3.47 analyzer. Commit `6dbb17b7f4276eeeaad2e3f6c8c267d939ef35f6` replaces those calls with `withValues(alpha: ...)`; follow-up CI pending. No physical phone or API runtime verification yet.


## 2026-10-24 — Flutter login request never reaches Apache
The user retried login with the physical-phone APK and the latest Apache access-log tail still contained no `POST /AhlElKheir/api/v1/auth/login.php`; only the earlier browser `GET` (expected HTTP 405) was present. Source inspection found that `.github/workflows/build-flutter-mobile-app.yml` overwrote Flutter's generated debug `AndroidManifest.xml` with `usesCleartextTraffic="true"` but omitted `android.permission.INTERNET`. The debug manifest generation was corrected on `main` in commit `99a37d69bc19edaea42c28c9807a6560ac1bb8af` to include both the INTERNET permission and the cleartext setting. This is the identified source-level cause consistent with the absence of requests in Apache logs; verification is pending. Next: confirm the GitHub Actions run triggered by that commit passes and publishes a new `ahl-el-kheir-flutter-android-debug-apk`; install that new artifact, attempt login once, and check for a new Apache POST entry. Do not reuse the earlier artifact or claim device login fixed before that physical-device check. API migration is now confirmed applied by the user to `ahl_el_kheir`; continue to keep API/auth runtime acceptance separate from CI build success.


## 2026-10-24 — Flutter mobile notification inbox (implementation checkpoint)

- Physical-phone authentication has been confirmed by the user: deliberately invalid credentials fail and the user's real credentials sign in successfully against the local XAMPP API.
- The first business-data slice is the shared notification inbox. New authenticated routes: `GET /api/v1/notifications/index.php?page=1` and `POST /api/v1/notifications/mark_read.php` with JSON `notification_id`.
- Both routes derive the recipient from the authenticated bearer session. The query/update are scoped to `notifications.recipient_user_id = current user id`; the mobile client cannot select another recipient. No schema change or migration was added.
- Flutter's signed-in screen now links to an Arabic-first notification inbox with English support, unread/read presentation, pull-to-refresh, paging, and mark-one-read behavior. Added a widget test for the notification inbox.
- Verification boundary: source changes are committed to `main`; the latest Flutter GitHub Actions run must be checked for analyzer/test/APK status. PHP syntax and authenticated API runtime checks against the local XAMPP database remain pending; do not claim this module is runtime-verified until those checks pass.
- Next: inspect the exact CI run, then locally lint the two new PHP endpoints and test own-user listing/mark-read plus cross-user isolation using existing accounts/notifications. Do not create test notifications or modify business data unnecessarily. Continue by selecting the next real business module only after mapping its existing source authorization and schema.


## 2026-10-24 — Mobile project portfolio implementation checkpoint

- Added authenticated read-only endpoint `GET /api/v1/projects/index.php?page=1` and an Arabic-first/English Flutter project portfolio screen.
- The endpoint is limited to the same portfolio roles allowed by `modules/projects/index.php`. Project visibility follows the existing project-domain rules inspected in `modules/projects/project_lib.php`: global financial/executive visibility, PM creator/pre-handoff and closure-request rules, and the assigned project-supervisor launch/scope checks. The mobile client role check only controls whether the entry point is shown; server-side authorization remains authoritative.
- Portfolio data uses the existing project, approval, lifecycle, posted funding/expense, beneficiary, and active supervisor records. No schema changes or runtime DDL were introduced.
- Added a Flutter widget test for Arabic project portfolio rendering and added the endpoint to the PHP 8.2 syntax-check workflow.
- **Verification boundary at this checkpoint:** GitHub Actions runs for the new Flutter page/test and the PHP endpoint are in progress. Do not call the feature CI-verified until those runs finish. The endpoint has not yet been exercised against the local Apache/MariaDB database or tested with different role accounts. No physical-device test is requested at this stage.
- Next: inspect the actual Actions results; fix only concrete failures; then add the next project workflow slice after reviewing the existing project detail endpoint and its role/scope rules. Keep broader mobile modules queued until the project read path is verified.


## 2026-10-24 — Mobile project details slice

- Added `GET /api/v1/projects/detail.php?id=<project_id>`, using the existing `other_projects`, project approval/lifecycle, posted funding/expense, team assignment, milestone and status-history records. It is read-only and makes no schema changes.
- The endpoint checks the live bearer identity and role, then enforces project visibility for the executive/financial roles, the Projects Manager's existing creator/pre-handoff and closure-request rules, and the Project Supervisor's final-approval/launch plus active assignment scope. Client navigation is not treated as authorization.
- Added an Arabic-first/English project detail screen reachable from the portfolio, with status/approval, financial summary, active team, milestones and recent history. Added a widget test for the Arabic detail view.
- The PHP lint workflow now includes both project API endpoints.
- **Verification boundary:** The selected PHP files passed lint on commit `7e5f13b9f2e35833ca6dcdf3eb2b8bc47608ac23`, but that run did not yet include `api/v1/projects/detail.php`. A subsequent PHP lint workflow with the detail endpoint added is queued/running. The Flutter workflow for the detail screen and widget test was still running at the time of this note. No local Apache/MariaDB API runtime test or role-account matrix has been run, so this feature is not yet runtime-accepted.
- Next: inspect the latest Flutter Actions result and fix only concrete failures. Then continue the mobile project read workflow with the next useful, role-safe slice; do not ask the user to test until the broader app reaches the agreed release-readiness gate.


## 2026-10-24 — Mobile HR attendance register

- Added read-only `GET /api/v1/hr/attendance.php`, restricted server-side to `hr_manager`, `hr_staff`, and `admin`. It uses the existing effective employment-state roster and attendance/approved-leave records; it does not write attendance or mutate leave-return records.
- Missing attendance remains `missing` in the API and UI, never inferred as absence. Approved leave is shown as leave unless a persisted or legacy same-day return marker exists.
- Added an Arabic-first/English attendance register with date selection, refresh, employee/department rows, check-in/out display, and summary counts. Added a widget test specifically preserving the missing-vs-absent distinction.
- The PHP lint workflow now includes the attendance endpoint.
- **Verification boundary:** Flutter CI exposed and we corrected a widget-test visibility issue in the project detail test; the latest build covering that correction and the attendance screen is running. PHP lint for the new attendance endpoint is queued/running. No local API runtime test or HR-role account matrix has been performed.
- Next: close the latest Flutter/PHP CI checks, then continue with role-safe HR leave-request read views and subsequent modules using the same source-first approach.


## 2026-10-24 — Mobile leave-request views

- Added read-only `GET /api/v1/hr/leaves.php?view=mine` for an employee's own leave history, resolving the employee through the existing `hrGetEmployeeForUser()` identity helper. The endpoint returns an empty list if no unambiguous employee profile is linked.
- Added the authorized review queue: HR Manager/HR Staff see non-HR employees' requests; General Manager (including the existing `gm` compatibility role) sees HR employees' requests. Role and view are checked server-side; the mobile role controls only the available navigation.
- Added Arabic-first/English leave list UI, status filters and paging. This is a read-only slice; request submission, approval and rejection are not exposed by this endpoint and must be implemented separately against the existing workflow rules.
- PHP lint passed for the new HR attendance and leave endpoints in GitHub Actions run `40085047414`. The mobile build exposed two widget-test assumptions (an off-screen attendance row and an HR review-queue test that had not switched from “my requests”); the tests were corrected on commit `638130e98af2fdd237e93a042bfad37f8c5f650c`. Its Flutter run is pending/in progress.
- No local API runtime test or role-account matrix has been performed. Do not mark the mobile HR views runtime-accepted yet.
- Next: confirm the current Flutter CI run, then implement leave request submission and role-authorized approval/rejection as separate validated API actions before moving to the next domain.


## 2026-10-24 — Mobile leave-request workflow actions

- Added authenticated `POST /api/v1/hr/leaves/action.php` for submitting leave requests and approving/rejecting pending requests. It preserves the existing role split: HR approves non-HR requests; General Manager approves HR employees' requests; rejection is limited to the same role-owned queue.
- Request types, inclusive day calculation, strict date format, duplicate pending-request check, final `hr_approved` status, approver columns, attendance-row creation, and post-action notifications follow the existing `modules/hr/leaves.php` workflow. Notifications are isolated so a notification insert failure does not undo a committed leave decision.
- The Flutter leave page now supports creating requests and the authorized review queue's approve/reject actions. Server-side authorization remains authoritative.
- PHP lint passed for the new action endpoint in GitHub Actions run associated with commit `b3aff6ab25320b43fa033dcc38a3694d3097efc5`. The Flutter analysis/tests/APK build for this combined change are still pending. No live-database request/approval runtime test has been performed.
- This supersedes the prior checkpoint statement that mobile leave requests were read-only: the list endpoint remains read-only, and the separate action endpoint now handles writes.


## Mobile application — 2026-10-24
The Flutter app in `mobile/ahl_el_kheir_app/` is being developed incrementally against the existing PHP API and role/scope rules. Current screen and endpoint inventory plus exact verification gaps are in `docs/MOBILE_APP_CONTINUATION.md`. The latest transaction-register CI layout correction is commit `885ef809b431b174541d54c8c44ade3f97421172`; its test/APK run has not yet been verified. Full-app completion and live API role-matrix acceptance are not claimed.


## Mobile app — 2026-10-24 progress
GitHub Actions run `40091698003` passed analysis, widget tests, debug APK build, and artifact publication for commit `885ef809b431b174541d54c8c44ade3f97421172`. A read-only sponsorship register has since been added to current `main`; its CI verification is pending. This is a progress checkpoint, not a claim that the complete app or live API runtime acceptance is finished. See `docs/MOBILE_APP_CONTINUATION.md`.


## 2026-10-24 — Sponsor request register CI verified
- Flutter workflow run `40093088822` for app integration commit `e3ad346c1267736b4e725805e0c877e524e8f2ac` completed successfully: dependency resolution, Dart analysis, widget tests, debug APK build, and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` (artifact id `12559303403`) was confirmed present and unexpired at verification time.
- PHP verification run `40093091580` completed with the `php-lint` job passing, including the new `api/v1/sponsor_requests/index.php` endpoint. The independent Stage 5 payroll regression job failed at its existing acceptance check with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; the rescheduling, HR migration and MariaDB 10.4 jobs passed. This is not evidence of a mobile endpoint failure and does not reopen the closed salary-advance work.
- Sponsor request screen now has CI evidence. API runtime against local Apache/MariaDB, per-role authorization and phone UI checks remain unverified. Continue building the remaining app on GitHub; do not request device testing yet.


## 2026-10-24 — Mobile profile completeness and transaction date filtering
- Expanded the existing family detail API/client with profile fields already displayed by `modules/families/view.php`: alternate phone, registration number, city, address, mother's marital status, father's name/death details, monthly need and notes. Existing family authorization and supervisor/nanny scope checks remain unchanged.
- Expanded the existing sponsor detail API/client with fields already displayed by `modules/sponsors/view.php`: email, address, sponsor type and preferred payment method. Existing sponsor role allowlist and supervisor scope check remain unchanged.
- Added start/end date controls to the mobile transaction register and connected them to the existing `api/v1/transactions/index.php` `from`/`to` filters. Client rejects an inverted date range; clear-dates restores an unbounded range. No schema or workflow mutations were introduced.
- The expanded-profile Flutter run `40093704453` has passed Dart analysis and all widget tests; debug APK build/artifact publication is still pending at this documentation checkpoint. The family detail test was updated to scroll to the children and sponsorship sections after the expanded profile moved them beyond the initial viewport.
- PHP 8.2 lint passed in run `40093671534` for the family/sponsor endpoint changes. The separate `migration-full-schema` job continues to fail only at the closed Stage 5 payroll regression step with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; do not attribute that failure to mobile code or reopen closed salary-advance work.
- API runtime on local Apache/MariaDB, per-role live account matrix, and physical-device acceptance remain outstanding. Continue app development and CI verification before requesting device testing.

## 2026-10-24 — Mobile HR employee directory implementation
- Added authenticated read-only `GET /api/v1/hr/employees.php?q=&page=1`, restricted server-side to the exact `hr_manager`, `hr_staff`, and `admin` roles allowed by `modules/hr/employees.php`.
- The endpoint uses existing `employees`, `departments`, and `hr_employment_states` fields, with name/employee-code/position search and 20-row pagination. It intentionally omits national ID, personal contact/address details, salary, bank data, and employee-editing actions.
- Added the Arabic-first/English Flutter employee directory with search, refresh, pagination, department, position, hire date, and employment state. The entry point is shown only for the same HR/admin roles; the API independently enforces authorization.
- Added a widget test and included the API route in the PHP 8.2 lint workflow. No database migration, schema change, or runtime DDL was added.
- CI verification for this slice is pending until the Flutter analyzer/tests/APK artifact and PHP lint job finish. Live Apache/MariaDB API checks and role-matrix runtime verification remain outstanding; do not request device testing yet.

## 2026-10-24 — Mobile employee directory CI confirmation and leave-request guard
- Correction to the prior checkpoint: Flutter run [40094355923](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40094355923) completed successfully, including Dart analysis, widget tests, Android debug APK build, and artifact publication. Artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12560083920`, 77,209,735 bytes) was confirmed unexpired. PHP lint passed in run [40094355939](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40094355939).
- New follow-up: the existing leave API explicitly returns `employee_linked=false` when the authenticated user has no linked employee profile. The mobile leave screen previously ignored this signal and still displayed the request action, which would lead to a predictable server rejection. It now hides that action and displays an Arabic/English explanation for unlinked accounts.
- Added a widget test for the unlinked-account state. Current verification for this follow-up is running in Flutter [40095047706](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095047706) and PHP workflow [40095047697](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095047697); do not mark the follow-up verified until the checks finish.
- The independent `migration-full-schema` CI job still reports the known Stage 5 payroll acceptance failure; it remains unrelated to mobile changes and must not reopen the closed payroll work. Live Apache/MariaDB API role checks and device acceptance remain outstanding.

## 2026-10-24 — Leave-request guard follow-up verification checkpoint
- The first Flutter attempt `40095047706` failed at Dart analysis because the newly added widget test had been placed outside `main()`. The failure was confirmed from the job log; the test was moved into `main()` in commit `76b83f7b83c96ce03e9853b7d47e8f00e91c3609`.
- On rerun [40095123841](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123841), Dart analysis and all widget tests have passed. Android debug APK build is still in progress and no artifact has been published for this revision at this checkpoint.
- PHP lint passed in [40095123850](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123850). The separate full-schema job is still running at this checkpoint; continue to treat any known Stage 5 payroll acceptance failure as unrelated to the mobile work.
- The employee-link guard is based on the existing API's explicit `employee_linked=false` response; no database or approval behavior was changed. Live Apache/MariaDB API role checks and device acceptance remain outstanding.


## 2026-10-24 — Leave-request guard CI verified
- Flutter workflow [40095123841](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123841) completed successfully on `main`: Dart analysis, all widget tests, Android debug APK build, and artifact publication passed. APK artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12560951078`, 77,209,480 bytes) is present and unexpired.
- PHP workflow [40095123850](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40095123850): the `php-lint` job passed, as did the HR migration, MariaDB 10.4 migration and rescheduling jobs. The overall workflow remains failed only because its separate `migration-full-schema` job failed at the known Stage 5 payroll regression/account 1410 reconciliation step; do not attribute this to mobile work or reopen the closed payroll feature without new regression evidence.
- The leave-request guard is now CI-verified: when the existing API reports `employee_linked=false`, the mobile page hides the leave-request action and explains that HR must link an employee profile. The added widget test passes. No leave business rules or database schema were changed.
- Verification boundary remains explicit: no live Apache/MariaDB API role matrix or physical-device acceptance has been performed. Continue implementing and testing the broader app on GitHub before requesting device testing.


## 2026-10-24 — Mobile project progress updates verified
- Project detail API `api/v1/projects/detail.php` now includes up to 10 existing `project_progress_updates` rows, ordered newest first, with update date, completion percentage, summary, achievements, issues, next steps and submitter name. This is read-only and adds no schema changes or workflow actions.
- `mobile/ahl_el_kheir_app/lib/project_detail_page.dart` displays these records in an Arabic-first/English section; `test/project_detail_page_test.dart` covers the rendered content. Test scroll ordering was corrected after the first CI run exposed an off-screen assertion; production behavior was not altered to satisfy the test.
- Successful Flutter workflow [40097366358](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40097366358), commit `7ea27b76d59af2fda7451e0d9278cac895367b89`: Dart analysis, full Flutter widget tests, Android debug APK build and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12560489959`, 77,209,301 bytes, is present and unexpired.
- PHP lint passed in the paired workflow's `php-lint` job. Its separate `migration-full-schema` job remains failed at the known Stage 5 payroll acceptance/reconciliation issue; do not reopen the closed payroll feature as part of mobile work.
- Live Apache/MariaDB API role checks and physical-device acceptance have not been performed. Continue mobile development and GitHub CI before asking the user to test a device.


## 2026-10-24 — Project portfolio search improvement checkpoint
- The project list previously filtered only the currently loaded page on-device, so results on other pages could be missed. `api/v1/projects/index.php` now accepts optional `q` and filters project code/name after applying the existing role-specific visibility checks and before pagination; totals/pages reflect the filtered authorized result set.
- `projects_page.dart` sends the search term to the existing API with a 350 ms debounce and generation checks to prevent stale in-flight responses from overwriting newer search results. `main.dart` URL-encodes the query. The widget test now asserts that the search term reaches the page loader.
- Commits: API `011a96d6a3bf4eedc0440ac9393203b43bcad169`; Flutter debounce `461b46c97cf962d5116ca3d01704d342791b83c3`; API call wiring `d0ad325cc0e654c21ca94d58f58e50c7511effb3`; widget test `184d3e3c45cbaf06810a717c2ed4cbfa55abee6a`.
- Latest complete-revision Flutter workflow [40098149592](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40098149592): Dart analysis passed and all Flutter widget tests passed; Android debug APK build is still in progress at this checkpoint. Do not mark this search change fully verified or claim a new APK artifact until the run finishes successfully.
- PHP lint passed in the paired workflow's `php-lint` job. Its independent full-schema migration job is not evidence against this API change; the known Stage 5 payroll acceptance failure remains out of scope. Live Apache/MariaDB API role checks and physical-device acceptance remain outstanding.


## 2026-10-24 — Project portfolio search CI confirmation
- Follow-up polling confirmed workflow [40098149592](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40098149592) completed successfully at 2026-10-24T13:30:33Z. The complete Flutter job passed and published unexpired Android debug APK artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12561936059`, size 77,206,559 bytes.
- The portfolio search change is now fully CI-verified: Dart analysis, Flutter tests, Android debug APK build and artifact publication passed; paired PHP lint passed. Live Apache/MariaDB API role testing and physical-device acceptance remain outstanding.


## 2026-10-24 — Notification inbox failure-path coverage added (CI pending)
- Commit [e05824931378d4d8ac16712aa6c1005bb757fdae](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/e05824931378d4d8ac16712aa6c1005bb757fdae) adds Flutter widget coverage for two notification-inbox behaviors: a failed mark-as-read request displays the error without reloading the inbox, and tapping an already-read item does not send a mark-as-read request.
- This extends the prior unread-item test; production code and API behavior were not changed.
- Verification boundary: GitHub's available commit-status and commit-associated workflow lookups returned no status records/runs for this push. The new tests are **not yet CI-verified**. Do not claim analyzer, widget tests, APK build, or artifact publication passed until a real workflow result is available.
- Next: obtain actual Flutter workflow evidence for this commit (analyzer, complete widget suite, Android debug APK build, artifact publication). Keep live Apache/MariaDB role tests and device acceptance deferred. Do not touch closed payroll work.


## 2026-10-24 — Mobile sponsorship role allowlist aligned with web module

- Source comparison found a concrete permission mismatch: `modules/sponsorships/index.php` allows `admin`, `vice_general_manager`, `supervisor`, and `accountant`; the mobile API and home-screen entry had additionally exposed the module to `general_manager` while excluding no other web-authorized role.
- Corrected `api/v1/sponsorships/index.php` to enforce the same four-role allowlist, and corrected the Flutter home-screen entry to match. Server-side API authorization remains authoritative; no business data, schema, or workflow was changed.
- Commits: API `350d18f1f5307ef1c273630489be321a5066f505`; Flutter navigation `39b65a7d887376e7fa451692483621bc718be5b8`.
- Verification boundary: source comparison justifies the change, but PHP lint, Flutter analysis/tests/APK build, and live role-account runtime checks have not been confirmed for these commits. Do not claim the change is verified until actual CI evidence is available. No device testing requested yet.


## 2026-10-24 — Sponsorship detail API role parity follow-up

- Commit [bc39b23fdd036298f64ed80a3b09e2bb906c9653](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/bc39b23fdd036298f64ed80a3b09e2bb906c9653) aligned `api/v1/sponsorships/detail.php` with the sponsorship list API allowlist: `admin`, `vice_general_manager`, `supervisor`, and `accountant`; the supervisor responsibility-scope guard remains.
- Current `main` source was re-fetched and confirms the four-role allowlist. This is source verification only; PHP lint, Flutter analysis/tests/APK build, and live API role checks are still unverified for these latest commits. Do not request device testing yet.
- Audit note: `api/v1/sponsors/detail.php` retains its broader role set because it matches `modules/sponsors/view.php`; no change was justified there.


## 2026-10-24 — Mobile sponsor-request attribution correction (CI pending)
- Commits: Flutter card attribution `4999c0f4b82ecb50e72203d990488840486dabd1`; widget coverage `7f4f8f2159abe1e4f86d6864d75c75f5fb6587d4`; API attribution search `864608962c4ed3f9ac3ae2a1e86711f247170413`.
- Flutter sponsor-request cards now display `brought_by_name`, falling back to `brought_by_user`; widget test checks the preferred name wins over the fallback.
- API search now checks sponsor name, phone, source, `r.brought_by_name`, and joined `users.full_name`. The count query uses the same LEFT JOIN as the list query; five search placeholders have five matching parameters. No schema change was made.
- Verification boundary: source changes are committed on `main`; GitHub commit status currently reports no status records for the API commit, and the Flutter workflow has not yet been confirmed. Do not claim PHP lint, Flutter analysis/tests, Android APK build, or artifact publication passed until actual CI evidence is available. Live Apache/MariaDB acceptance and device testing remain deferred.


## 2026-10-24 — Sponsor-request attribution fix CI verified
- Flutter workflow [40106182906](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106182906) completed successfully at commit `91655d9cf8a1232eb30cad4786290a276270cb5b`. Dart analysis, the full widget suite, Android debug APK build and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` (ID `12564862356`, 77,208,880 bytes) is present and unexpired; SHA-256: `eab6bf7c5c9e833ce05fff19f3720913c3de41cadcedfb912fe64b093a1e514d`.
- Paired PHP workflow [40106183059](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106183059): PHP lint, HR job-description migrations, MariaDB 10.4 migration, and rescheduling-service tests passed. The separate full-schema Stage 5 payroll acceptance job failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; track it under deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), do not attribute it to the mobile change or reopen closed payroll work without root-cause evidence.
- Sponsor-request attribution UI/search change and preferred-name/fallback widget coverage are CI-verified. API live Apache/MariaDB role testing, and physical-device acceptance, remain outstanding. Continue the broader mobile app on GitHub; do not request phone testing yet.


## 2026-10-24 — Mobile sponsor-request status actions added (verification pending)
- Added authenticated `POST /api/v1/sponsor_requests/action.php` for the exact non-destructive status transitions already supported by `modules/sponsors/requests.php`: `new → contacted`, `new/contacted → lost`, and `lost → contacted`. The endpoint enforces the same six-role allowlist as the existing sponsor-request register, locks/rechecks the current status in a transaction, and rejects invalid/stale transitions. It does not expose delete or sponsor conversion; no schema changes or runtime DDL were added.
- Flutter sponsor-request cards now provide confirmation-protected actions for those transitions, local Arabic/English success feedback, and search hint text that includes referrer attribution. Added widget coverage for confirming the contacted transition and reloading the updated state. PHP lint workflow now includes the action endpoint.
- Source commits: API `7614893bf51c7fab7f5938c41356a0c833352113`; app wiring `0b8b71de2fc83c0d013cba54e40c4ae4eadf3dd9`; UI actions `40457577937138bab9116695a3878e5c33ec6594`; widget test `7d83fe6719d188bff84c93cdbcc4503e831f939c`; localization/hint `013c01042794c62a8f4a96c8d8c8dd268894739b`; analyzer correction `9240efbbae4969e66188f2d747b8854932d99dcc`.
- Verification: PHP lint passed in [workflow 40107450497](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450497). Flutter analyzer and widget tests passed in [workflow 40107450516](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450516); Android debug APK build/artifact publication were still running at the last check, so do not mark the slice fully CI-verified until confirmed. Earlier analyzer run [40107338619](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107338619) failed only because it reported an unused local variable; this was removed in the subsequent commit and the latest analyzer passed.
- The independent full-schema Stage 5 payroll job again failed with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.`; keep it in deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), unrelated to this mobile work. Live Apache/MariaDB role-matrix testing remains unperformed. Do not request phone testing yet.


## 2026-10-24 — Mobile supervisor directory added (CI pending)

- Added authenticated read-only `GET /api/v1/supervisors/index.php`, restricted server-side to the website module's exact `admin` and `vice_general_manager` roles. It supports bounded name/username/phone search, pagination, and an explicit include-departed/archived filter; the default excludes departed and archived supervisors, matching `modules/supervisors/index.php`.
- The response uses checked-in schema columns from `users`, `roles`, `supervisor_letters`, and `letters`. It returns current supervisor status, contact details, assigned letters/gender markers, and last-login timestamp. No schema changes or runtime DDL; no supervisor create/edit/status/exit actions were added.
- Added an Arabic-first/English mobile directory page, navigation visible only to admin and vice general manager, and widget coverage for supervisor rendering and the archived filter. Added the endpoint to PHP lint workflow.
- Commits: API `74fcb0550e5c057903712f10d320d06c7e49ceaa`; PHP lint workflow `6af0c6ffd5a35d18e88b914be2a02698e1f5256c`; Flutter page `98e444570207b102427fac79cc69e860115e1036`; app navigation/API wiring `8313b555b707829d24f08cb227c6669ec7ec8db1`; widget test `8397f8d7fe75d3a381bc00c9976f9c475b196640`.
- Verification at documentation time: Flutter workflow `40108888325` at the test commit is in progress. PHP workflow has started; its full-schema Stage 5 payroll job has historically failed independently, so inspect the PHP lint job specifically and keep the known payroll issue deferred under #94. Do not claim this slice CI-verified until Dart analysis/tests/APK and endpoint PHP lint results are confirmed. Live Apache/MariaDB role tests and device acceptance remain pending; do not request phone testing yet.


## 2026-10-24 — Mobile sponsor-request actions and supervisor directory CI verified

- Sponsor-request attribution finding [#95](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/95) is resolved and closed. `mobile/ahl_el_kheir_app/lib/sponsor_requests_page.dart` displays `brought_by_name`, falling back to `brought_by_user`; `api/v1/sponsor_requests/index.php` searches sponsor name, phone, source, `r.brought_by_name`, and joined `users.full_name`. Count/list queries share the join and the five search placeholders match five parameters. No schema change.
- Sponsor-request attribution Flutter CI: [run 40106182906](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106182906) passed analysis, widget tests, Android debug APK build and artifact publication. PHP workflow [40106183059](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40106183059) passed PHP lint; its unrelated full-schema Stage 5 payroll acceptance failure remains tracked under [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Sponsor-request status actions: Flutter workflow [40107450516](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450516) passed; PHP workflow [40107450497](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40107450497) passed PHP lint. Allowed status transitions remain limited to the existing web workflow; no schema changes.
- Supervisor directory: Flutter workflow [40108888325](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40108888325) passed analysis, tests, Android debug APK build and artifact publication.
- Acceptance boundary remains unchanged: CI is not live Apache/MariaDB role-matrix proof, and it is not physical-device acceptance. Do not request device testing until the broader mobile app reaches its release-readiness gate. Continue mobile work on `main`; keep issue #94 isolated and do not reopen closed payroll work.


## 2026-10-24 — Mobile project progress updates (CI partially verified)

- Added authenticated `POST /api/v1/projects/progress_update.php` based on the existing `project_progress_updates` insert and `audit_log` pattern. No schema changes or runtime DDL.
- Authorization mirrors the web `add_progress` action: admin or assigned primary project supervisor/active operations-section team member; closed projects are blocked. API checks authorization before revealing project state, rechecks project state/assignment during the transaction, and isolates audit failure after the progress commit.
- Project detail API returns `project.can_add_progress` for UI visibility only; POST authorization remains authoritative. Flutter now provides an Arabic/English form for summary, date, completion percentage, achievements, issues, and next steps, then refreshes detail after success. Widget tests cover submission, project ID, and refresh.
- Flutter run [40111507626](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40111507626): Dart analysis and Flutter tests passed at commit `4433f860e3e1ae7ae44ef9bec3224a12415b4a09`; APK build was still running at last check. PHP lint passed in run [40111571974](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40111571974) at commit `d42374f87d29a70155f2094107bffb5c6c74fb54`. The unrelated full-schema Stage 5 payroll failure remains tracked under [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Live Apache/MariaDB role-matrix verification and physical-device acceptance remain pending. Do not request device testing until the broader mobile app reaches release readiness.


### 2026-10-24 — Mobile milestone creation checkpoint
The mobile project detail flow now supports creating a project milestone through authenticated `POST /api/v1/projects/milestone_create.php`, using the existing milestone and project-status-history tables without schema changes. Server-side authorization is limited to admin or a project supervisor with an active primary/operations assignment; closed projects are rejected. Flutter form, API wiring, refresh behavior, widget coverage, and PHP lint workflow changes are committed on `main`. CI is pending; live role/runtime and device acceptance remain outstanding. Do not request phone testing yet.


## 2026-10-24 — Mobile milestone CI verified; Netlify noted for later deployment planning
- Flutter workflow [40112041575](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40112041575) completed successfully on commit `f571077c894dea7429375ba36768827cfd832095`: Dart analysis, full widget tests, Android debug APK build, and artifact publication all passed. The successful run includes the milestone form/widget test. Artifact name: `ahl-el-kheir-flutter-android-debug-apk`; artifact publication step succeeded.
- PHP workflow [40112106628](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40112106628): `php-lint`, MariaDB 10.4 migration, rescheduling-service tests, and HR job-description migration jobs passed. Its separate full-schema Stage 5 payroll job failed at the known rollback-only payroll/account 1410 regression; keep it isolated under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), unrelated to mobile milestones.
- User provided their Netlify team projects page as a possible option for later live testing: https://app.netlify.com/teams/moneer-ali-taha/projects. Consider it during deployment planning, not as a PHP/MariaDB backend host: Netlify can serve static front-end output (including a Flutter Web build), but this existing procedural PHP API and MariaDB database would need a separate compatible HTTPS host. Do not deploy or connect services without an explicit later deployment plan.
- Mobile milestone creation is CI-verified only. Local Apache/MariaDB role-matrix verification and device acceptance remain pending; do not request phone testing until the broader app reaches release readiness.


## 2026-10-24 — Mobile nanny group verification implementation checkpoint

- Inspected `modules/accounting/group_verification.php`, `modules/accounting/lib_group_workflow.php`, and the checked-in `database/ahl_el_kheir.sql` schema before implementation. Schema confirms the exact `nanny_family_verifications` columns and unique key `(family_id, month)`, plus the `verification_audit_log` columns used by the existing web workflow. No migration or schema change was required.
- Added authenticated `GET /api/v1/nanny/group_verification.php?month=YYYY-MM&group_id=<optional>` and `POST /api/v1/nanny/group_verification_action.php`. Both are restricted server-side to the `nanny` role and use the authenticated user ID to scope active group assignments.
- Read view follows the existing group workflow: active assigned groups, families whose children remain in the group, monthly verification state, and active sponsorship amounts for active family children. Writes support individual verification, bulk verification of up to 500 selected families, and group submission. Individual verification remains available for an assigned active group even when its workflow status is no longer pending; bulk verification and submission require `pending`. Each verification write and its audit row share one transaction; bulk audit failure rolls back the whole operation. No deletion, accounting/disbursement creation, or schema DDL was added.
- Added an Arabic-first/English Flutter page, nanny-only home entry, API wiring, widget coverage for opening an assigned group and saving an individual verification, and PHP lint workflow coverage.
- Commits: GET API `0d26f030340c6192f7a09fd17937f7a773a5ab52`; action API `f0edc26d3c2835c43ed20c1e3bc429a7314fdeb7`; action-response syntax correction `aaf8a39bdba54aa3c83d371da645c490291bebcd`; Flutter page `01c57deeea518f9972127a2aadd8288a85661b4f`; app wiring `1d092bf7427cd5d2885c8339375ffa30097f6ba3`; widget test `ef224b4070e5c06b312e318f984cfd371f1f28b0`; PHP lint workflow `90b55550a467bed32e7064943753b49aac92eb26`.
- Verification is pending at this checkpoint. GitHub Flutter run [40113312109](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113312109) and PHP run [40113317445](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113317445) were still running. A prior full-schema job failed only at the known Stage 5 payroll/account 1410 check; this is issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94), not evidence about this mobile feature. Await actual Flutter analyzer/widget/APK/artifact and PHP lint results before claiming CI verification.
- Live Apache/MariaDB nanny-role runtime checks and physical-device acceptance remain outstanding. Do not ask the user to test the phone yet; continue CI/source verification first.


## 2026-10-24 — Nanny group verification: first CI gate passed; closed-group coverage pending

- Flutter workflow [40113312109](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113312109) completed successfully on commit `ef224b4070e5c06b312e318f984cfd371f1f28b0`: Dart analysis passed, all 25 widget tests passed (including the new individual group-verification flow), Android debug APK build passed, and artifact publication succeeded. Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12567906074`, size 77,264,820 bytes, SHA-256 `403b36c3996e3ed98b6b3f6ed859d8bff37486c3496ebc414b691c8f7a051f4c`, is present and unexpired.
- PHP workflow [40113393725](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113393725) passed `php-lint` (including both new nanny endpoints), MariaDB 10.4 migration, rescheduling-service tests, and HR job-description migration jobs. The separate full-schema job failed at the known Stage 5 payroll prerequisite/account 1410 check: `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` Track only under deferred issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94); do not reopen the closed payroll feature or attribute this failure to mobile verification.
- Added a second widget test in commit `4f8a9cf4ef065c8e0c7161d1a16bae4048d77ae4` for the documented closed-group boundary: hide bulk verification and submission, but permit individual verification for a family in a still-assigned active group. Flutter workflow [40113705909](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113705909) is pending at this checkpoint. Do not claim that additional test passed until its run completes.
- The group-verification implementation has CI evidence for source lint, app analysis/tests (first test), APK build and artifact publication. The latest closed-group regression test, live Apache/MariaDB role/runtime checks, and physical-device acceptance remain pending. Do not ask the user to test the phone yet.


## 2026-10-24 — Nanny closed-group test passed; preliminary verification slice added (CI pending)

- Flutter workflow [40113705909](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113705909) completed successfully on commit `4f8a9cf4ef065c8e0c7161d1a16bae4048d77ae4`. The additional widget test passed: closed groups hide bulk verification/submission while allowing an individual verification for a family in a still-assigned active group. This test-only revision passed analyzer, full widget suite, APK build and artifact publication.
- Added authenticated nanny-only `GET /api/v1/nanny/pre_verification.php?month=YYYY-MM` and `POST /api/v1/nanny/pre_verification_action.php`, based on the exact existing `modules/accounting/nanny_pre_verification.php` scope. The read view includes active families with active children in the nanny's active assigned groups regardless of group workflow status, their assigned group names, and shared monthly checks. Individual and up-to-500-family bulk writes use the existing `nanny_family_verifications` and `verification_audit_log` tables; bulk verification only adds selected checks and preserves other checks. No schema changes or runtime DDL.
- Added the Arabic-first/English preliminary-verification Flutter page, nanny-only home entry, bearer-authenticated API wiring, widget tests for individual save and bulk action, and PHP lint workflow entries for both new endpoints.
- Source commits: preliminary GET API `5eca38ee14670ce85e2b7c411faf4c599c08922b`; action API `ca4457984acb435a5f8d1ee9693032cb2cec3036`; Flutter page `a3ecc1ac649643451a462aea1c571c583d6359a0`; app wiring `250f4eab0e5101e10c56c8335af9165c6bad0fa2`; PHP lint workflow `9d170e3839e26799fff3a8f64271509ed4ab56ca`; tests `23c81c2dd69c0bd47bdf21d39415dfb550e1685c`.
- PHP lint passed in workflow [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834) for both preliminary API endpoints. The remaining independent full-schema job is not a mobile acceptance test; it has historically failed at the known Stage 5 payroll prerequisite/account 1410 check under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Flutter workflows [40113978865](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113978865), [40113993153](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113993153), and [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) were still running/queued at this checkpoint. Do not mark the preliminary-verification UI/tests/APK verified until the latest test commit passes. Live Apache/MariaDB nanny-role checks and physical-device acceptance remain deferred; do not ask for phone testing yet.


## 2026-10-24 — Mobile nanny preliminary verification CI result

Flutter CI run [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) passed analysis, widget tests, Android debug APK build, and artifact publication for the preliminary-verification flow. PHP lint passed for both new endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). Latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014) had PHP lint, MariaDB 10.4 migration, and rescheduling tests passed; remaining jobs were still running at last check. This supersedes the older pending-CI note. Live API role-matrix and device acceptance remain pending.


## 2026-10-24 — Mobile employee details status

Employee detail endpoint, Flutter detail screen, and directory navigation are implemented. Android CI passed at commit `a78bc176` ([run 40115151758](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115151758)); later localization/test changes are pending latest CI [40115569057](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115569057). Live API role/database verification and explicit PHP lint coverage for the new endpoint remain pending. The separate full-schema Stage 5 payroll test reports a missing payroll-transfer migration prerequisite; investigate its setup before changing anything.


## 2026-10-24 — Employment-state localization refinement

Refined the employee detail screen to show the actual localized employment-state name instead of collapsing every `working` category into a generic label; this preserves distinctions such as probation. Updated the Arabic widget test fixture to include both Arabic and English state names. Latest Android build/test run [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) was queued at the last check. Earlier commit `a78bc176` passed analyzer, Flutter tests, and debug APK build; the newest revision still needs its own green result. PHP endpoint lint/runtime role tests remain outstanding, and the full-schema payroll migration prerequisite failure is separate.

## 2026-10-24 — Mobile app progress checkpoint

- Employee detail screen, directory navigation, localized employment-state labels, and widget tests are on `main`; the latest Flutter Android workflow [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) completed successfully.
- Corrected the mobile README to list current integrated mobile areas and clearly state that the app is not yet full web-module parity or production-ready.
- Remaining release-readiness items: test API role/record-scope behavior against the real local Apache/MariaDB app, add verified PHP lint coverage for the employee detail endpoint through an allowed repository change, and choose/configure a PHP+MariaDB host with HTTPS. Netlify static hosting alone is insufficient for the existing backend. Do not ask for phone testing until the broader acceptance gate is met.

## 2026-10-24 — Self-service mobile salary statements added (CI pending)

- Added authenticated `GET /api/v1/hr/my_salary_statements.php` and an Arabic-first/English Flutter screen linked from the signed-in home page. It resolves only the current user's employee profile through the existing `hrGetEmployeeForUser()` helper and returns only that employee's payroll rows where `status='paid'` and `accounting_status='posted'`, matching the web statement's availability boundary. Draft, approved-unpaid, and unposted rows are excluded by query conditions; no schema changes were introduced.
- Inspected `database/ahl_el_kheir.sql` directly through its Git blob: the `payroll`, `employees`, and `hr_employee_salary_history` columns used by the endpoint exist. The salary currency lookup follows the same effective-date selection used by `modules/hr/payroll_statement.php`.
- Source commit: [`7bcb340c83f42211bb6d65e80d57af3d52e95949`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/7bcb340c83f42211bb6d65e80d57af3d52e95949). The first Flutter run [40116469884](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116469884) found a widget-test fixture collision (two displayed amounts were both `100.00 SDG`); only the fixture was adjusted in [`dd226b794ca9136aa76359be62b927b018370bad`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/dd226b794ca9136aa76359be62b927b018370bad). Latest Flutter run [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126) was still in progress at this checkpoint; do not call the feature CI-verified until it finishes green.
- A PHP CLI syntax check of the new endpoint source returned `No syntax errors detected`. The existing PHP workflow's explicit lint list still does not include this new endpoint, and live Apache/MariaDB role-and-data testing remains outstanding. No device testing requested yet.


## 2026-10-24 — Mobile HR API PHP lint coverage expanded

- Added `api/v1/hr/employee_detail.php` and `api/v1/hr/my_salary_statements.php` to `.github/workflows/php-syntax-check.yml` so the two mobile HR endpoints are syntax-checked by CI, rather than relying only on a one-off CLI lint.
- Change committed directly to `main`: [`ebbf618117bab4d1235459621809ae824f0f863f`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/ebbf618117bab4d1235459621809ae824f0f863f). The workflow execution for this commit still needs confirmation; do not call this CI-verified until its PHP-lint job passes.
- The self-service salary statement Flutter revision passed Android workflow [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126): analyzer, widget tests, debug APK build, and artifact publication succeeded. This does not verify live API authorization/data behavior. Local Apache/MariaDB role-scope acceptance and a suitable HTTPS PHP/MariaDB deployment remain outstanding.


## 2026-10-24 — Mobile API syntax-check coverage completed (CI pending)

- Audited the checked-in `api/v1/` tree against `.github/workflows/php-syntax-check.yml`. All 26 PHP API endpoint files are now included in the selected PHP lint list, including authentication (`login`, `logout`, `me`), notifications (`index`, `mark_read`), HR, nanny, projects, sponsors, sponsorships, families, supervisors, transactions, and sponsor requests.
- Commit: [`eae29e5e210019ebdc6c8defd9ca9152e445e29b`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/eae29e5e210019ebdc6c8defd9ca9152e445e29b). Workflow run [40117147104](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117147104) was still running when checked; do not mark lint coverage CI-verified until `php-lint` concludes successfully. Other jobs in that workflow are separate existing test suites and should not be changed without their own concrete failure evidence.
- Live Apache/MariaDB authorization and record-scope acceptance is still outstanding. Do not treat source-level role checks or successful builds as proof of runtime security.

## 2026-10-24 — Mobile financial review queue (verification pending)
The Flutter app now has a read-only financial review queue for `financial_manager`, `fm`, `finance`, `admin`, `sudo`, and `general_manager`, matching the web queue's allowed-role list. It exposes pending `sponsor_payments` and the latest 20 approved/returned decisions. It intentionally does not expose approve/return actions or accounting posting. PHP lint workflow coverage and a Flutter widget test were added. Await CI before claiming build/lint verification; live Apache/MariaDB role and record-scope checks remain outstanding. No schema changes.

## 2026-10-24 — CI fixture alignment for payroll transfer prerequisite (verification pending)
Workflow run [40117632150](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117632150) failed in the full-schema job because the rollback-only Stage 5 payroll harness attempted the real `paid` transition without the required payroll-transfer schema and prepared private proof. The production guard behaved correctly. The workflow now applies `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql` to its disposable full-schema database before tests; Stage 5 payroll and rescheduling-concurrency harnesses now provide the required active payment account and prepared proof fixture. Proof bytes are cleaned up after test completion. Await fresh CI before marking this correction verified. No production payment rule or database schema was changed.

## 2026-10-24 — Follow-up CI failure: missing CLI audit actor (verification pending)
The next full-schema run passed the missing-schema/proof gate but failed while confirming proof metadata: `confirmed_by_user_id=0` violated the foreign key to `users`. In CLI, `Session::getUserId()` returns `0` without `$_SESSION['user_id']`; the test harness had not established an actor. The production FK rejection is correct. The Stage 5 and concurrency rollback-only harnesses now populate the session user ID from an existing test user. Fresh CI is required before closing this test-harness correction.

## 2026-10-24 — Mobile financial review queue and CI prerequisite correction verified
- Android workflow [40117632176](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117632176) completed successfully for `60883414`: analyzer, widget tests, debug APK build, and artifact publication passed.
- CI workflow [40118139681](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118139681) completed successfully across all five jobs. Its full-schema log confirms Stage 5 payroll edge cases, 1410 reconciliation, payroll/rescheduling lock timeout and retry, and rollback-only cleanup passed after the CI fixture applied the transfer migration and provided proof/audit actor fixtures.
- Financial review queue remains read-only. No approval/return action or live local API role/data-scope verification is claimed. Live Apache/MariaDB mobile API acceptance and HTTPS PHP/MariaDB hosting are still outstanding.

## 2026-10-24 — Mobile financial review queue actions (CI partial)
The Flutter financial review queue now supports approve-and-post and return-for-correction actions via api/v1/accounting/fm_review_queue_action.php. The API enforces the web queue's role allowlist; approval uses the existing atomic ak_approve_sponsor_payment() helper, while return validates a reason and uses a transaction/row lock, status guard, audit attempt, and isolated notification attempt. No schema changes. PHP lint plus all full-schema workflow jobs passed in 40118666512 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512). The matching Flutter build/test workflow 40118666504 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) was still running at last check. API runtime authorization and transaction outcome verification against local Apache/MariaDB remain outstanding.

## 2026-10-24 — Mobile financial review actions added; CI passed
The mobile financial review queue now supports approval and return. Approval calls the existing atomic `ak_approve_sponsor_payment()` helper, preserving transaction creation, journal posting, status update, and incentive-event behavior in one transaction. Return requires a reason, locks/rechecks pending status, records audit metadata, commits before notification, and cannot be replayed after status changes. General Manager sponsorship access was also aligned with the existing web permission in the mobile menu and both read APIs.

Verification: [Android workflow 40118666504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) passed analyzer, widget tests, debug APK build, and artifact publication. [PHP/database workflow 40118666512](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512) passed all five jobs; all 31 API endpoints are in the PHP lint list. Live local API authorization/record-scope and actual approval/return against the local DB remain unverified.

## 2026-10-24 — Project mobile API scope parity correction verified by CI
The mobile project detail API now treats a missing `project_approval` row as `draft`, matching the existing web `akp_can_view_project()` and portfolio API. Mobile milestone creation now enforces the same non-admin project supervisor operational boundary as the web view/progress API: approval must be `approved`, lifecycle must be one of `active`, `reopened`, `completed`, `under_review`, or `cancelled`, and `closed` remains blocked; assignment checks remain in place. The detail API's `can_add_milestone` flag uses the same boundary. Workflow [40119297504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40119297504) passed all five PHP/database jobs. This is source/CI verification, not live API acceptance.

## 2026-10-24 — Mobile project milestone maintenance checkpoint

Mobile project milestone edit/delete has been added on main using the existing project_milestones table and web workflow's editable fields. API authorization, project/milestone scoping, transaction locks, closed-project blocking, Flutter confirmation/refresh behavior, and a widget test are in source. Android run 40120100653 was pending at documentation time; do not claim CI success until checked. Live local API acceptance remains outstanding. See docs/MOBILE_APP_CONTINUATION.md for exact commits and gates.

## 2026-10-24 — Mobile milestone maintenance authorization parity

Mobile project milestone edit/delete now uses a dedicated `can_manage_milestones` capability, distinct from creation, matching the web workflow: admins can maintain milestones on non-closed projects; project supervisors require an active primary-supervisor or operations assignment. The mutation endpoint allows only assigned project supervisors, checks the milestone belongs to the requested project, and blocks closed projects. On source SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`, Dart analysis, all Flutter tests, debug APK build, and artifact publication passed in [Android workflow 40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219). PHP/database workflow [40121128872](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40121128872) passed all jobs on source SHA `2a664a66316319983dc32d48cdfa9024b770a71a`. The APK build predates the last PHP-only authorization alignment; Flutter source did not change afterward. Live local Apache/MariaDB authorization acceptance remains outstanding. Live local Apache/MariaDB authorization acceptance is not yet performed. See `docs/MOBILE_APP_CONTINUATION.md`.


## 2026-10-24 — Mobile API audit checkpoint

Current source-level review covered API authentication/session checks, project scope, sponsor/family/sponsorship access, HR employee/attendance/leave/self-service salary statements, nanny verification, notifications, sponsor requests, transaction listing, and FM review actions. No new concrete mobile-only authorization mismatch was confirmed; no production code change was made speculatively. Release readiness remains blocked on authenticated role/record-isolation and state-transition runtime acceptance against the actual local Apache/MariaDB app, confirmation of the intended DB migration state, and a confirmed HTTPS-capable PHP/MariaDB host. See `docs/MOBILE_APP_CONTINUATION.md` for the detailed checkpoint.


## 2026-10-24 — Mobile API migration CI gate
The PHP/database workflow now includes `api/v1/_bootstrap.php` in the selected PHP lint list and applies `database/migrations/2026-10-24_mobile_api_sessions.sql` twice in the disposable full-schema MariaDB 10.4.32 job. Change commit: `4fd136bb6646b960d8ea46548eab8757c65872d7`. CI verification is pending; do not call the gate passed until the run is green. This does not apply the migration to the local `ahl_el_kheir` database. Mobile release readiness still requires verified local migration state, controlled Apache/MariaDB authentication and authorization acceptance, and an HTTPS-capable PHP/MariaDB host. No phone testing yet.


## 2026-10-24 — Mobile auth migration CI gate passed
The PHP/database workflow passed after the mobile auth migration coverage change: [run 40122798655](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40122798655), source SHA `09a94183a9b2cb1e23feefeaf8733b02d97537c3`. All five jobs passed, including `php-lint` and `migration-full-schema`; the latter imported the checked-in schema, applied `2026-10-24_mobile_api_sessions.sql` twice to the disposable MariaDB 10.4.32 database, and completed the existing full-schema regressions. This proves the CI migration step succeeds in that disposable environment, not that the migration is applied locally or that API auth passes live runtime tests. Latest Android build remains successful at [run 40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219) on Flutter SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`; no Flutter source changed after that build. Remaining gates: local migration-state confirmation, controlled local Apache/MariaDB API auth/session and authorization tests, and HTTPS PHP/MariaDB hosting. Do not request phone testing yet.

## 2026-10-25 — Mobile API continuation checkpoint

A concrete configuration inconsistency was found: Flutter's `API_BASE_URL` fallback used port `881`, while the Android workflow default and prior local/phone reachability evidence use Apache port `8081`; the prior local check reported no listener on `881`. Corrected the fallback in `mobile/ahl_el_kheir_app/lib/main.dart` to `http://192.168.18.9:8081/AhlElKheir/api/v1` (commit `41cd2e0176ee4bf2254dfc92fadde6b8c3ba6665`). CI verification is pending. This does not close the live API authentication/authorization/record-scope acceptance gate. No production API or schema behavior changed.


## 2026-10-25 — Mobile Android CI verification

The Android workflow for fallback API URL correction commit `41cd2e0176ee4bf2254dfc92fadde6b8c3ba6665` completed successfully: [workflow run 40156431795](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40156431795). Flutter dependency resolution, Dart analysis, Flutter tests, debug APK build, and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,305,556 bytes), available until 2026-11-24. This is a debug APK build/test result, not production signing or live API acceptance. The corrected fallback remains `http://192.168.18.9:8081/AhlElKheir/api/v1`; verify the current LAN IP before using the artifact on a physical phone. Local `ahl_el_kheir` migration state, authenticated role/record-scope/action acceptance against Apache/MariaDB, and HTTPS PHP/MariaDB hosting remain open release gates. Do not claim the full app is complete or request final phone acceptance yet.


## 2026-10-25 — Mobile API authentication integration harness added

- Added `tools/test_mobile_api_auth_integration.php`, a live HTTP integration harness gated to the exact disposable database name `mobile_api_auth_test` and the explicit `MOBILE_API_AUTH_TEST=1` flag. It refuses other database names, uses the checked-in schema, and restores the selected user's original account fields while deleting test-created bearer sessions and test login audit rows.
- Added `.github/workflows/mobile-api-auth-integration.yml`. It imports `database/ahl_el_kheir.sql` into disposable MariaDB 10.4.32, applies the mobile-session migration twice to check repeatability, starts the real PHP API endpoints, and tests missing/invalid credentials, wrong HTTP method, valid login, hash-only token storage, `/me`, role-based HR endpoint denial/allowance, live role changes, account deactivation, password-change-required denial, expired/revoked sessions, and logout revocation.
- CI runtime result: GitHub Actions run `40157433470` completed with conclusion `success` on commit `f5dfe7f7b1ab118f259909d555d212634635da8c`. The job successfully imported the checked-in schema, applied the mobile-session migration twice, and completed the live HTTP integration script. Because the script exits nonzero on a failed assertion, this verifies the scripted authentication/session paths against disposable MariaDB. It does not verify every business endpoint, record-level scope, brute-force throttling, or live local XAMPP behavior.
- The harness uses only a disposable CI database. Do not run it against `ahl_el_kheir` or a production database.
- The existing `.github/workflows/php-syntax-check.yml` still runs its historical salary-advance regression jobs on every push to `main`; a commit adding this harness will trigger that workflow too. Do not interpret that automatic CI activity as a reason to reopen completed salary-advance work.


## 2026-10-25 — Mobile project portfolio approval fallback correction

A follow-up source audit found `api/v1/projects/index.php` still defaulted a missing `project_approval` row to `approved`, while `api/v1/projects/detail.php` and the supervisor/project-manager authorization helpers use `draft` when the approval row is absent. The portfolio endpoint’s row-visibility helper separately queries authoritative approval state for restricted roles, so the confirmed inconsistency was the portfolio response’s displayed `approval_status` (and any client behavior based on that response), not a proven authorization bypass. Changed the portfolio fallback to `draft` in commit `cb5d222e1f5a6c9b88ddd56bbfd6ae2fb859df77`. Verification: the PHP lint job passed in GitHub Actions run `40157943519` on commit `c6a525c2fad2462bd68bf2759e94e10d60ce25b2`, which includes this source correction; the disposable live HTTP authentication/session integration workflow passed in run `40157925602` on the code commit. The auth integration run does not exercise the project portfolio endpoint, so no dedicated project endpoint runtime assertion is claimed. No schema or database changes were made. No schema or database changes were made.

## 2026-10-25 — Mobile project portfolio and progress authorization integration tests

- Extended `tools/test_mobile_api_auth_integration.php` with disposable-CI-only business endpoint assertions. It creates a uniquely coded temporary project without a `project_approval` row and verifies authenticated `GET /api/v1/projects/index.php` returns `approval_status=draft`. It then creates approved/active project fixtures, verifies an assigned project supervisor can post a progress update, ends that supervisor assignment, and verifies the next progress update is denied with HTTP 403.
- Code commit: `7194871271a058005a19534caff9d8d3ab837d49`.
- Runtime integration verification: PASS in [GitHub Actions run 40158833255](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40158833255). Log assertions explicitly passed: `project portfolio defaults a missing approval row to draft`, `assigned project supervisor can add progress to an approved active project`, and `project supervisor cannot add progress after the assignment ends`. The complete existing auth/session integration suite also passed.
- The harness removes its temporary project, progress update, supervisor assignment, lifecycle row and approval row in cleanup. No schema or migration changes were made, and no local/production data was touched.
- Source audit inspected transaction-time assignment revalidation and web assignment locking; no additional confirmed concurrency defect was found, so no speculative production change was made.
- Do not run this harness against `ahl_el_kheir` or any production/development database; it explicitly requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`.


## 2026-10-25 — Mobile API authorization audit continuation
A concrete project-detail contract mismatch was found: `api/v1/projects/detail.php` selected `project_milestones.id` but did not serialize it, although Flutter edit/delete actions require it. Source correction committed on `main` as `20631106ae8fe9f8267779faf4c6e9a1d7608057`. Focused endpoint verification passed in disposable-database run `40160569643`; ended-assignment and cross-project no-mutation checks passed in run `40160867381`. Local migration-state confirmation, authenticated Apache/MariaDB role and record-scope/action acceptance, and HTTPS PHP/MariaDB hosting remain open release gates.


## 2026-10-25 — Mobile API milestone verification checkpoint

The project-detail milestone ID contract and assignment-revocation guard now have disposable-database HTTP integration coverage. Harness commit eebe53983eed73e9899ca9cf5d85754497e3e092 passed [Mobile API auth integration run 40160569643](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160569643). CI imported the checked-in schema, applied the mobile-session migration, and exercised the PHP endpoints against disposable MariaDB. Local XAMPP migration state and live local role/record-scope/action acceptance remain unverified. Do not run the harness against ahl_el_kheir.


## 2026-10-25 — Mobile notification isolation CI checkpoint
The disposable MariaDB HTTP integration harness now verifies that another user's notification is not returned to the authenticated account and cannot be marked read by that account. Test commit `ab2a6173e596f88ed4e7c2fb6e962e40e5429192`; [workflow run 40161671591](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161671591) completed successfully. No production endpoint/schema change. Local XAMPP migration state and full local role/record-scope/action acceptance remain open. Do not run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile sponsor-request authorization CI checkpoint
Disposable MariaDB live HTTP coverage now confirms sponsor-request role boundaries and a valid/invalid status transition: nanny listing/mutation is denied without changing `new`; admin may transition `new` to `contacted`; a repeated transition returns 409 without mutation. Test commit `f0dfa70a376771cbceeae04e73b57f2e6e40cb89`; [run 40161801824](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161801824) passed. No production code or schema changed. Local XAMPP migration state and full local role/record-scope/action acceptance remain open.


## 2026-10-25 — Mobile nanny family scope integration checkpoint
Disposable live HTTP coverage now verifies assigned-family detail access, foreign-family detail denial (403), and family-list inclusion/exclusion for a nanny. The first attempt timed out at the harness's five-second request limit while listing the checked-in schema's 2,208 families; the test helper now allows 20 seconds for this endpoint only. Updated harness commit `9312a7118ebe7876986dcecfda0bdcd0e3e594d9` passed [run 40162023286](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162023286). No production code/schema change. Local XAMPP migration state and full local role/record-scope/action acceptance remain open.


## 2026-10-25 — Mobile supervisor sponsor responsibility integration checkpoint
The disposable API harness now verifies the authoritative sponsor letter+gender rule: a fixture with `sponsor.supervisor_id` set to the supervisor but a first-letter outside the supervisor's configured matrix returns 403 on detail and is excluded from the sponsor listing. Test commit `de9af9c85a56f5f5f593ec404dda7b26753bfdb8`; [run 40162198710](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162198710) passed. This confirms the API does not treat the direct assignment field as an independent sponsor authorization grant. No production code/schema change; local XAMPP migration and runtime acceptance remain open.


## 2026-10-25 — Mobile API authorization audit checkpoint
The mobile API audit identified and narrowly corrected a project milestone lifecycle authorization inconsistency. `api/v1/projects/milestone_action.php` now requires non-admin callers to use an approved project in the same operational lifecycle states already required by `milestone_create.php`; closed-project protection and assignment checks remain. Production source commit: `bc6f6ee0104326ee536fdaa732f1e693de739eb8`. A disposable-CI regression assertion was added in `c6cc08b3b646c1906392019e116a646ab038a5df`, checking both denial and no mutation on a draft project. GitHub Actions run 40162518220 completed with conclusion `success` on test commit `c6cc08b3b646c1906392019e116a646ab038a5df`; the job imported the checked-in schema, applied the mobile-session migration and passed the live HTTP integration harness. The draft-project milestone denial/no-mutation regression is CI-verified, not locally runtime-verified. This does not close the mobile authorization audit or local XAMPP/MariaDB acceptance. Never run the harness against `ahl_el_kheir`.


## 2026-10-25 — Mobile HR leave approval integration coverage passed in CI
The disposable live-HTTP harness now covers HR leave authorization and state transitions. A nanny cannot approve a pending leave (HTTP 403; state remains pending). HR staff can approve a pending leave for a non-HR employee; the response and stored state become `hr_approved`, the approver ID is stored, and the matching attendance row becomes `on_leave` with the expected note. Repeating approval returns HTTP 409 and preserves the approved state. Exact test fixtures and linked notifications are cleaned up.

Harness commit: `fd7d9652c3d9cc1bc6b6b17900742ef0f7d1d30b`. GitHub Actions [run 40162902436](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162902436) completed successfully using the checked-in schema and disposable MariaDB. Review of `api/v1/hr/leaves/action.php` found its conditional pending-state update, affected-row check and transaction rollback already handle a stale/duplicate approval; no production fix was made. This closes only the new CI coverage gate, not the broader mobile API authorization audit. Local XAMPP migration-state confirmation and authenticated Apache/MariaDB acceptance remain open. Do not run `tools/test_mobile_api_auth_integration.php` against `ahl_el_kheir`; it requires `mobile_api_auth_test` and `MOBILE_API_AUTH_TEST=1`.


### Mobile API authorization audit checkpoint — 2026-10-25

The mobile API authorization audit remains open. Commit `fbf83136ace25fb80c3f51f82b529552aa311d85` adds four disposable-harness assertions that HR staff are denied nanny group/preliminary verification GET and action routes. Documentation of the checkpoint is in `docs/MOBILE_APP_CONTINUATION.md`. These assertions are verified by the successful live-HTTP integration run `40164371241`; see the latest CI verification checkpoint below. They are test coverage, not a newly confirmed production defect. Do not run `tools/test_mobile_api_auth_integration.php` against `ahl_el_kheir`; its guard requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`. Preserve local runtime acceptance as pending and continue source-grounded endpoint review.


### Mobile API source review — 2026-10-25

Additional source review covered the bearer-session bootstrap/login/me/logout path, family/sponsor/sponsorship detail scoping, HR attendance access, and employee salary-statement scoping. No new production authorization defect was confirmed in these paths. This is source inspection only. The assertions are verified by the successful live-HTTP integration run `40164371241`; the Flutter workflow is separate from the PHP harness. Continue treating disposable-database HTTP integration and local runtime acceptance as open. See `docs/MOBILE_APP_CONTINUATION.md` for the detailed checkpoint.


## 2026-10-25 — Mobile API role-denial integration assertions VERIFIED in CI

The earlier pending-verification notes for the new read/action role-denial assertions are superseded for these specific assertions. GitHub Actions run [40163578388](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40163578388) passed on test commit `d62656a87dd77f187254b7f342c425e6dac0dcbf`; run [40163733247](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40163733247) passed on test commit `fbf83136ace25fb80c3f51f82b529552aa311d85`. Both runs imported the checked-in schema, applied the mobile-session migration twice to disposable MariaDB 10.4.32, and passed the live PHP HTTP integration harness. Logs explicitly show all seven nanny read-endpoint denials and all four HR-staff denials for nanny verification read/action routes passing. No production endpoint or schema change was made in those test commits. This verifies the scripted assertions in CI only; it does not establish local XAMPP migration state or local Apache/MariaDB acceptance. Continue the finite source/schema-led audit and keep local runtime/release gates open. Never run the harness against `ahl_el_kheir`; it requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`.


## 2026-10-25 — Latest mobile API integration run passed

GitHub Actions run [40164371241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40164371241) passed on commit `428decce211ab7a9cff743375679ba16f3e1fae4`. The run imported the checked-in schema, applied the mobile-session migration, and ran `php tools/test_mobile_api_auth_integration.php` over live HTTP against disposable MariaDB. The log explicitly confirms the added checks `nanny is denied access to the HR leave approval queue` and `nanny cannot create project milestones` passed, along with the broader suite. The same commit's separate salary-advance verification workflow also passed (run `40164371167`). This verifies CI only; local XAMPP acceptance and the final full audit closure remain outstanding. The test harness is restricted to `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`; never run it against the real `ahl_el_kheir` database.


## 2026-10-25 — Finite mobile API source-review checkpoint

The current source pass covered the remaining project portfolio/detail authorization paths, sponsor-request role gates, notification ownership checks, HR leave request/approval boundaries, nanny verification role and active-assignment checks, and financial-review action gates. Existing tests and source checks were cross-referenced against the live-HTTP harness. No additional production authorization defect was confirmed in this pass, so no production code change was justified. `api/v1/sponsorships/index.php` does load/filter rows in PHP; this is a potential scalability concern, not a proven authorization bypass, and is not being changed as part of this audit without measured evidence and a separate performance scope.

The latest integration test commit `428decce211ab7a9cff743375679ba16f3e1fae4` passed [Mobile API auth integration run 40164371241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40164371241), including the new HR leave-queue and project-milestone role denials. The same commit's separate salary-advance verification workflow passed run `40164371167`. The CI gate is therefore green for the current harness. Remaining acceptance item is local XAMPP confirmation of the explicit `2026-10-24_mobile_api_sessions.sql` migration and a controlled local API smoke test; do not run the integration harness against `ahl_el_kheir`. The audit remains open only for that local acceptance gate and final reconciliation of the finite checklist, not because a production defect is currently confirmed.


### 2026-10-25 — Flutter app menu navigation fix (device verification pending)

Commit 49ef28ad185f72c4d484909d29a200797d66e718 corrects the app-level Flutter Navigator context used by home-screen menu callbacks. A dedicated GlobalKey<NavigatorState> is now bound to MaterialApp and handles the menu route pushes. Source re-fetch confirmed the expected call-site replacements. This is a mobile client navigation correction only; no API, database, or authorization behavior was changed. The automated build and phone acceptance test are still pending. Do not mark the issue closed until the rebuilt APK opens Projects and generates the expected Apache API request. See docs/MOBILE_APP_CONTINUATION.md.


## 2026-10-25 — Mobile home-screen design and personal-link grouping

The user performed a quick device test and confirmed login works with multiple user accounts and the menu links now open. This is preliminary acceptance only; the user has not completed detailed testing.

Source change: `296b318ae173e2be9383e2308266ec7235cc7cbc` updates `mobile/ahl_el_kheir_app/lib/main.dart` to bring the home screen closer to the existing web system's established navy/blue, Cairo, and light-neutral design tokens. The welcome panel now uses the existing navy gradient, menu cards share rounded bordered surfaces with reduced elevation, and the flat menu is divided into **Work areas** and **My account** sections. Salary statements, leave requests, and notifications are grouped under the personal/account section instead of being mixed into the operational modules.

The web stylesheet's root palette already matches the app's existing color values; the main mismatch identified was the mobile screen's generic flat composition and missing hierarchy, not evidence for inventing a new brand palette. Existing role visibility conditions, routes, API calls, and server authorization were deliberately left unchanged in this visual pass. The salary-statement API returns an empty, explicit unlinked-profile state for accounts without an employee profile; the leave API similarly scopes personal requests and restricts review queues server-side.

**Verification status:** source update committed to `main`; Flutter CI and device retest are pending. Do not call this redesign runtime-verified until the new APK build succeeds and the user checks the home screen with multiple role accounts. User's current feedback is quick-test evidence, not a full acceptance run.


## 2026-10-25 — Mobile visual redesign follow-up: compact app-bar actions

User feedback after the first design pass: only the login page/header felt on-brand, the rest of the screen remained too generic, notifications should be a bell icon at the top without explanatory copy, and personal links should be represented by an account icon at the top. The user prefers a broader redesign now that core app navigation is in place.

Source change: `f627d130fe36b4bfe4232891aff521f63af1b518` updates `mobile/ahl_el_kheir_app/lib/main.dart`:
- The signed-in app bar now shows the organization logo/name and icon-only notification bell, account, and language controls; the icons have accessibility tooltips, but no explanatory text beside them.
- Notification history is opened from the bell. Salary statements and leave requests were removed from the main work-area list and are now in the account bottom sheet alongside the signed-in user's name/role and sign-out action.
- Shared Flutter theme styling was strengthened for the brand color scheme, dividers, list tile typography/spacing, and icon-button surfaces, in addition to the existing branded scaffold, app-bar, card, and form themes. This theme is shared by the app's routed pages; individual screens may still need focused visual refinement after a device review.
- Existing routes, API payloads, role visibility conditions, and server-side authorization are unchanged.

**Verification status:** source committed to `main`; Flutter CI and device verification pending. The user has not yet accepted the new design and has not completed detailed feature testing. Do not describe the broader redesign as complete or runtime-verified until the new APK passes CI and the user reviews it on device.

## Mobile app — 2026-10-25 dashboard/login follow-up

User-selected visual direction is a modern professional dashboard with existing navy/blue branding. Changes on `main` now include responsive two-column work-area tiles, removal of the visible role pill, a 56-pixel app bar, password reveal and forgot-password actions, account-sheet navigator-context correction, notification details/related-page support, and access to the existing web orphan form from child records. See `docs/MOBILE_APP_CONTINUATION.md` for commit-level detail. Latest source commit at this checkpoint: `ca16265515612bfa18442dcd59a987baaa303e0e`. Flutter CI and physical-device verification are pending; these changes are not yet runtime-accepted.
### CI correction follow-up — 2026-10-25

The first Flutter analyzer run for this work exposed concrete integration errors: a duplicate notification-tile `onTap` and required callbacks missing from the existing widget tests after constructor changes. The notification tap now uses one handler, mark-read failure stops navigation (preserving the prior error behavior), and tests provide explicit no-op callbacks where external navigation is not under test.

Correction commits: `e622c2c98aa4dea9462da5d0a632fe795cca5b56` (notification handler) and test callback updates through `c30c10b8e1a6df3f9ac2e197dc4363748419df22`. The new Flutter workflow runs for these commits are still pending/in progress at documentation time. Do not claim analysis, tests, or APK build passed until the latest run confirms it.
### Final notification handler correction — 2026-10-25

The next analyzer pass isolated one remaining Dart error: the notification mark-read helper returned without a boolean when the widget was unmounted during an error. Corrected to return `false` explicitly so the detail route is not opened after an unsuccessful mark-read operation. Source commit: `b572e49403805f3057e4830c211002c0d4fbd0e6`. The matching Flutter workflow run `40183286267` is queued; analysis/tests/APK status remain unverified.

## 2026-10-25 — Mobile dashboard and notification feedback

User rejected the recent two-column mobile dashboard redesign. The dashboard is restored to full-width single-column module cards; a top app-bar sign-out icon is added beside the existing utility icons. Notification details stay in Flutter and no longer launch their related website link externally. A linked-page note is shown instead until a native destination is implemented. Changes are on `main`; latest Flutter analysis, widget tests, APK build/artifact, and physical-device visual acceptance must be verified separately.


## 2026-10-25 — Mobile header two-row correction (CI pending)

Committed on `main`: `ee917da2ea64e6e817422850c80e9393ccac78ba`.

Source change: `mobile/ahl_el_kheir_app/lib/main.dart` now separates the signed-in header into two logical rows: the existing organization logo/name on the first row, and notification, account, language, and sign-out icons on the second. The full-width single-column dashboard remains unchanged. Notification details continue to use the native in-app screen; this change does not add browser navigation from notifications. The unnecessary “Tools available to your account” / Arabic subtitle below the work-area heading has been removed without changing role visibility or API authorization.

Branding evidence: the web theme in `assets/css/style.css` defines the navy palette `#081527`, `#0A1F44`, `#0E2A5C`, `#123A7A`, `#1F4E8C`, `#2E63A8`, light background `#F0F2F5`, border `#E3E8EF`, and Cairo typography. The mobile app uses the checked-in `assets/organization_logo.jpg` and Cairo theme. No replacement brand asset or new color palette was introduced.

Verification status at commit time: source committed, but Flutter formatter/analyzer/widget tests, Android APK workflow, and physical-device verification are pending. Do not claim the two-row header is runtime-verified until the workflow passes and the APK is tested on a phone. The feature-parity matrix and date-range investigation remain open; the transaction API currently validates ISO dates and applies inclusive `transaction_date >= from` / `transaction_date <= to` filters, while Flutter rejects reversed selected ranges and resets pagination on date selection. These are source observations, not yet proof that all range cases pass end-to-end.


## 2026-10-25 — Login branding correction and major parity gaps confirmed

Source commit: `c0d73ca0342c460c67faab2e9b7c037ef44ae1a2`.

The mobile login no longer shows a separate subtitle under the organization name; Arabic now displays the requested full name `منظمة أهل الخير النسوية لكفالة الأيتام`, with a full English equivalent in English mode. The `AHL EL KHEIR` English footer was removed. Existing authentication, password visibility, recovery link, logo asset, and theme remain in place. Flutter formatting/analyzer/tests and APK workflow for this commit are pending; do not claim verified yet.

User rejected the current mobile design as inferior to the installed web app/PWA and emphasized that the dashboard must reflect each real role's distinct tools, workflows, and visual structure. Treat this as a broader product-design and parity correction, not a request for isolated cosmetic patches. Preserve the accepted full-width single-column module-card arrangement unless the source/PWA evidence justifies a better role-specific design; do not restore the rejected generic/two-column design.

Confirmed functional gap: `includes/age_alert.php` is a real core web feature. It selects active children approaching or past age 18 within the 90-day alert window (with a lower age bound of 21 years), and displays child/family/birth date plus links to family details and child sponsorship suspension. Web role scope is nanny (assigned families only), admin/sudo, vice general manager, and general manager. The Flutter app has no equivalent age-alert endpoint, model, home alert, native modal, or suspension action in its current `main.dart` feature inventory. Do not fake this with local-only filtering: inspect the real SQL/workflow and server-side permissions, then add an authenticated API response and secure action endpoint only if needed, preserving existing business logic and the web feature's exact role scope. A mobile native alert must keep child/family details in-app; no external browser links for notification detail navigation.

Confirmed dashboard parity issue: web roles route to distinct pages in `config/functions.php::dashboard_for_role()`, including `dashboard/admin_dashboard.php` for sudo/admin/super_admin, `dashboard/gm_dashboard.php`, `dashboard/vgm_dashboard.php`, `modules/accounting/fm_dashboard.php`, `dashboard/accountant_staff_dashboard.php`, `dashboard/supervisor_dashboard.php`, `dashboard/nanny_dashboard.php`, `dashboard/staff_dashboard.php`, `dashboard/hr_dashboard.php`, and `dashboard/projects_dashboard.php`. The Flutter home currently constructs a mostly shared list of module cards with role-code conditions; this does not replicate the web dashboards or all their actions. In particular, the web admin dashboard has dedicated administration tools, stats/attention links, and audit access, while the Flutter sudo view is not an equivalent operational dashboard. Build a documented role-by-role feature matrix from these actual pages, navigation, permissions, and API endpoints before expanding screens. The existing PWA is the visual/interaction reference and should be inspected against its real assets, CSS, manifest, and workflows; do not infer that manifest metadata alone accounts for its richer design.

Next priority: comprehensive role-aware dashboard and design pass, including the age-reach alert. Compare the existing web/PWA for every real role, map each available action to a native Flutter screen/API endpoint, then implement in justified increments with backend authorization, regression tests, documentation, and CI. Date-range investigation remains open and must not be marked fixed. Physical-device acceptance remains pending.


## Mobile app checkpoint — 2026-10-25 (partial implementation; verification pending)

Commits `1c51846a9c348f5fb09fd5fb9d551f10edf7cd62`, `9ddf86df2a145f18a553ab452e7577723408c71d`, `0253a88d3a6201c9a279b879dd2e5fa0fcfe1a46`, and `17af21974b9decf913954f4b7d96127ac34704b1` update the Flutter shell, add role-specific dashboard hero styling, add a bearer-authenticated legal-age alerts endpoint and native alert sheet, and include the endpoint in PHP lint CI. The endpoint mirrors the existing web alert's active-child/date window and nanny assignment filter. CI was observed in progress; no green build or runtime verification is claimed yet. Admin tool parity, native suspension action, full role-by-role dashboard parity, date-filter acceptance and physical-device testing remain open.


## Mobile administrator dashboard — 2026-10-25 (implementation committed; verification pending)

A dedicated Flutter dashboard now replaces the common role-card list for `admin`, `sudo`, and `super_admin`. New authenticated overview endpoint: `api/v1/admin/overview.php`; new screen: `mobile/ahl_el_kheir_app/lib/admin_dashboard_page.dart`. It includes role-specific visual design, real record-count metrics, recent audit activity, and categorized administration/core-record/operations/finance/protection tool groups. Native links are wired where native screens exist; system administration and other web-only tools currently open the existing website pages externally. This is not full native parity. See `docs/MOBILE_ROLE_FEATURE_PARITY.md`. Flutter CI/APK and PHP lint results for this latest change are pending; local runtime and device verification are not claimed.


## 2026-10-25 — Role-aware mobile tools and employee self-service

Source commits on `main`:
- `8f659a0cec3ae9d565dd8bba98297da2f94b7639`: expose native leave requests and salary statements from the mobile workspace.
- `842e4382bcf922f9c67b82fcc414abdd7e72fba8` and `9e3044d842c1893bf685ef2bb87ae253a7d2c150`: add a role-specific quick-tools section derived from each existing web dashboard. Website-only links are labelled WEB and remain subject to website server-side authorization; this is navigation coverage, not native workflow parity.
- `fbc30ebf5c1df671e6fcca6b07c39963abe3661e` and `a2eb57da68fde5a1cb206732ed4781df609c0382`: align the mobile API default and Android workflow default with the documented phone LAN endpoint `http://192.168.18.9:881/AhlElKheir/api/v1`. The previous 8081 default did not match the documented phone-facing Apache port and is superseded.

The workspace now exposes the existing native personal leave-request page and finalized salary-statement page, and adds role-scoped quick links matching the real GM, VGM, FM, Accountant, Supervisor, Nanny, Administration/Staff, HR, and Projects dashboard routes. The API enforces leave-queue permissions and returns salary statements only for paid payrolls posted to accounting. No account without a linked employee profile is given another employee's records.

**Still open; do not claim full completion:** role-specific dashboard metrics and operational workflows are not all native yet; website-only tools require a valid website session in the external browser; native legal-age suspension source is now implemented; automated and local runtime verification remain pending; transaction date-range end-to-end acceptance and physical-device runtime remain pending; the mobile API migration must be applied to the intended local development database before API runtime testing. The newest source checks have now passed; see the verified CI milestone below.


## 2026-10-25 — Native legal-age suspension action added

Source commits on `main` add `POST api/v1/age_alerts/suspend_child.php`, wire a confirmation action into the native legal-age alert sheet, and include the endpoint in PHP syntax CI. The endpoint mirrors the existing web child-suspension business operation: it permits admin/sudo/super_admin, VGM aliases, and nanny; rechecks that the child is active and within the web alert's 90-day-to-age-18 / age-21 lower window; enforces nanny family assignment; locks the child row; rejects an existing active suspension; inserts a `suspension_cases` record with reason `age_out`; marks the child inactive/disqualified; pauses active linked sponsorships; and records the existing audit-log event. The General Manager can see alerts but cannot initiate this action, matching the web page's hidden create form for GM.

The API action adds no runtime DDL or schema change. The redundant runtime `CREATE TABLE` block was removed from `modules/families/suspensions.php` because `suspension_cases` and its referenced columns are already present in `database/ahl_el_kheir.sql`; PHP syntax CI lints both suspension entry points, and the latest PHP workflow passed. Verification boundary: the endpoint/service passed PHP lint and a rollback-only integration test against the checked-in full schema on MariaDB 10.4.32; Flutter analyze, widget tests, APK build, and artifact publication also passed. Real local XAMPP/API and physical-device acceptance are still required. The CI integration harness verifies an eligible child, active linked sponsorship pausing, duplicate rejection, nanny scope rejection, GM denial, and rollback restoration against a disposable full-schema fixture. Do not test this on a real beneficiary record.


## 2026-10-25 — Mobile API role-alias authorization correction

The API bootstrap now normalizes only the role aliases explicitly routed to the same canonical dashboard by `config/functions.php::dashboard_for_role()`: `sudo`/`super_admin` → `admin`, `gm` → `general_manager`, `vgm` → `vice_general_manager`, and `fm`/`finance` → `financial_manager`. API authorization checks therefore evaluate the same canonical role as the web router, while `auth/me.php` returns the stored role code so the app retains the user's actual role identity. The `staff` alias is intentionally not normalized globally because its dashboard exposes a different subset of Administration tools.

This corrects concrete native-screen/API mismatches where the UI offered role-approved tools but endpoints rejected the equivalent alias account (including sponsor/family/sponsorship lists, supervisor management, HR screens, project portfolio, financial review, and transactions). A PHP regression test covers 14 canonical/alias cases and is run by `.github/workflows/php-syntax-check.yml`. Automated CI passed: the PHP workflow ran `PASS | mobile API role normalization | 14 cases`, and the Flutter APK workflow passed analyze, all widget tests, APK build, and artifact publication. Local XAMPP/mobile runtime acceptance is still required.


## 2026-10-25 — Verified CI milestone

- Flutter workflow run `40192383388` for source `c680777307d55513f01aff5dbe5536be497b2611`: **success**. Dart analysis, Flutter tests, debug APK build, and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk` (SHA-256 `9d407beda8a74d81c76655da2e6b74dcde015dba73ff237d5de9d0a9e41b782b`).
- PHP workflow run `40192899764` for source `5362b5dd250971db65b68f690560be208f16f43f`: **success**. Selected PHP lint passed, full-schema/migration jobs passed, and the role-alias regression reported `PASS | mobile API role normalization | 14 cases`.
- These CI runs verify source/build and disposable-schema checks only. They do not replace runtime verification against the user's local `ahl_el_kheir` database or physical Android device.


## 2026-10-25 — Suspension service integration and latest APK verified

- Flutter workflow `40193299239`, source `9e12cc7f491ed4e76649bdaf6f18a33d46f8e601`: **success** — Dart analysis, all Flutter widget tests, debug APK build, and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, SHA-256 `8ce5a5a2823be3a4be102a17894ee01032e8605bcc6333af4969b19f1e3384df`.
- PHP workflow `40193517552`, source `abc98c0a7e46dee67ce64daa87e59950812c397e`: **success** — selected PHP lint, full-schema migration suite, MariaDB 10.4.32 checks, and the rollback-only suspension service integration test passed. Exact service result: `PASS | mobile legal-age suspension service | success, linked sponsorship pause, duplicate guard, role/scope guards, rollback`.
- Role-alias regression: `PASS | mobile API role normalization | 14 cases` in PHP workflow `40192899764`.

Remaining acceptance boundary: these CI checks use disposable databases and cannot verify the user's running XAMPP server, actual local `ahl_el_kheir` schema alignment, the phone's network route, or a live authenticated session. The mobile API session migration must be applied to the intended local database before first use if it has not already been applied. Website-only tools remain labelled WEB and need a valid website session; they are not native parity claims.

## 2026-10-25 — Mobile dashboard parity update (verification pending)

A native role-metrics API and Flutter dashboard section have been added for the non-administrator roles routed to distinct web dashboards. The administrator/sudo/super_admin dashboard remains separate. Metrics use queries taken from the corresponding existing dashboards and are returned only after bearer authentication and role normalization. The API has been added to PHP syntax CI.

The role-tool route audit found one role mismatch: Supervisor was offered a Fina collection creation page that explicitly denies Supervisor access. That link has been removed and a widget regression test added.

**Not closed:** combined-revision CI, execution of role dashboard SQL against the local development database, physical-device acceptance, and full transaction date-filter acceptance. The latest APK CI success observed before these new commits is not evidence that this new revision is built. Website-only links still do not count as native parity.

## 2026-10-25 — Role-tool authorization follow-up

A second guard-level review corrected additional mobile quick-tool visibility:
- HR Manager-only pages (employment states, incentive verification, payroll reversal, attendance policy, payroll integrity, and incentive policy management) are now shown only to `hr_manager`; HR Staff retain pages whose real web guards permit them.
- Accountant-only role cards no longer surface monthly/group disbursement pages whose existing web guards deny the `accountant` role. Those links remain available to `accountant_staff`.
- Regression tests cover the HR Manager/HR Staff split, accountant disbursement restrictions, and the Supervisor Fina-creation restriction.

Commits: `98e2e48a724b0b403a8795988005f5c4f30cd311`, `d845ec9a67a6540d9f5764c54f6096c9bfceaf5a`, `07a8d34ca187a9b6c28692e32c813a0cc76e2166`, `7532b0f63fc24d7916003f54d8bfee9860ed72d9`. Latest tests/build after these changes are still pending. Continue auditing role tools against actual page guards; repository file existence alone is not sufficient.

## 2026-10-25 — Empty transaction results can now refresh

The transaction list's empty-result branch previously used a plain `ListView` without `AlwaysScrollableScrollPhysics`, while it is wrapped in a `RefreshIndicator`. When date/status/search filters returned no records, the short empty list could not reliably be pulled to refresh. The empty branch now uses `AlwaysScrollableScrollPhysics`, matching the populated list's refresh behavior.

Added widget coverage for multi-day inclusive date bounds persisting through pagination and refresh, and for rejecting a reversed range before making another request. The existing same-day range test remains. Source commits: `fa96f3a8451b86fc47b037d431bcc1bc23ec8a5e` and `64da0db9eef605a8a1442e9c623e1fa3a85f80e7`.

The mobile API already validates `YYYY-MM-DD`, rejects invalid calendar dates and reversed ranges, and applies inclusive SQL bounds (`transaction_date >= from`, `transaction_date <= to`). These source-level checks and widget tests do not replace a real authenticated API/database test with boundary records. Latest Flutter CI for this exact test revision is pending.

## 2026-10-25 — Role dashboard SQL now has a full-schema CI gate

The role-specific dashboard SQL was extracted to `api/v1/dashboard/overview_service.php` so it can be tested without bypassing endpoint authentication. `api/v1/dashboard/overview.php` now authenticates the caller, enforces the administrator endpoint split, invokes the service, and maps unavailable-role/database errors to bounded JSON responses.

Added `tools/test_mobile_role_dashboard_queries.php`. In the existing disposable MariaDB 10.4.32 full-schema CI job, it executes every role's metric query against the imported schema and validates the metric response structure without changing data. Both the endpoint/service and the harness are included in PHP lint. Commits: `fcc0c6f6e7554d46645319fb29b2c3cb92352e4d`, `56de647da473dec369102f2e619c98585308afc5`, `e186b3d30e610011ef6135ceab6dfe416ccbd4cd`, `7e6dc63be960efe612191a9b2e109dacd4f580a8`.

**Verification pending:** the new full-schema query gate must pass before the metric SQL can be described as schema-verified. This CI test uses the checked-in schema and a disposable database; it does not replace local `ahl_el_kheir` runtime checks.

## 2026-10-25 — Role dashboard full-schema query gate PASS

GitHub Actions run `40195397302` on source `7e6dc63be960efe612191a9b2e109dacd4f580a8` completed successfully. The PHP lint job passed, and the disposable MariaDB 10.4.32 full-schema job imported `database/ahl_el_kheir.sql`, applied the existing test migrations, then executed every native role-dashboard metric query successfully:
`PASS | mobile role dashboard SQL | 14 roles`.

Individual query counts passed for General Manager (10), VGM (7), FM (5), Accountant (5), Accountant Staff (5), Supervisor (6), Nanny (6), Administration (3), Staff (2), Social Media (2), HR Manager (5), HR Staff (5), Projects Manager (4), and Project Supervisor (4).

This proves the new role metric queries execute against the checked-in full schema on disposable MariaDB; it does not prove live values or permissions against the local `ahl_el_kheir` database. Physical-device and local API runtime checks remain open.

## 2026-10-25 — Flutter role dashboard and filter regression CI PASS

Flutter workflow run `40195228074` for source `64da0db9eef605a8a1442e9c623e1fa3a85f80e7` completed successfully. Dart analysis, the full Flutter widget test suite, debug APK build, and artifact publication all passed. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,875,054 bytes), available from [GitHub Actions run 40195228074](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40195228074).

The passing widget suite includes role-specific dashboard metrics/error recovery, HR Manager versus HR Staff tool visibility, accountant disbursement route restrictions, the Supervisor Fina-creation restriction, same-day date filters, multi-day bounds persisting through pagination/refresh, and reversed-range rejection. This artifact contains the Flutter source at the listed SHA; the later changes were PHP service/test and documentation changes, not Flutter source changes.

The PHP full-schema query gate separately passed in run `40195397302` for `7e6dc63be960efe612191a9b2e109dacd4f580a8`. Local XAMPP API/data verification and physical-device acceptance remain pending.

## 2026-10-25 — Native sponsor assignment workflow added

A native sponsor-assignment screen is now available to administrator roles and to GM/VGM from the mobile workspace. The new `GET api/v1/sponsors/assignment_options.php` returns the same 100-item unassigned-sponsor list and active-supervisor list used by the existing web page. The new `POST api/v1/sponsors/assign.php` restricts the action to admin/GM/VGM, validates both IDs, rechecks the supervisor is active, locks the selected records, runs the existing `recordSponsorAssignment()` history helper, updates the same sponsor assignment fields as `modules/sponsors/assign.php`, and commits atomically. No migration or runtime DDL was added. The VGM mobile tool was changed from an external website link to the native screen.

Verification:
- PHP lint passed for the new API/service and rollback harness.
- Full-schema CI run `40195979512`, source `9dadc1a7c04005e7d36954eec4d32c896ac39b7f`, passed the rollback-only service test: `PASS | mobile sponsor assignment service | sponsor=1 | supervisor=19 | rollback=PASS`. The test verified the sponsor update, actor fields, assignment history and full rollback restoration on disposable MariaDB.
- Flutter analysis and widget tests passed on run `40196042318`, source `ad8c97f0bbc61a2cc52ea6be1d707200446d43e5`; APK build/artifact publication is still in progress at this checkpoint.

Local XAMPP API testing and physical-device interaction remain pending. Do not describe those as verified from CI.

## 2026-10-25 — Native supervisor status controls added

The native supervisor list now exposes activate/suspend controls only when the caller is in the existing management entry points (admin or VGM). Each action requires an in-app confirmation and calls `POST api/v1/supervisors/action.php`. The endpoint permits only admin/VGM, accepts only the existing `toggle_status` action, rejects non-supervisor and archived accounts, updates `users.is_active`, and records the same best-effort audit event as `dashboard/vgm_dashboard.php`. It intentionally does not expose the destructive archive action; archive/edit and letter-assignment tools remain on their existing website pages.

Verification:
- PHP lint and full-schema CI run `40196421650` at source `f5fcc59570a44230e7971d7bbe486bdc6dd16357` passed. Rollback-only service test output: `PASS | mobile supervisor status service | supervisor=14 | rollback=PASS`.
- Flutter analysis and widget tests passed in run `40196556920` at source `d8a8e66e056d0f1535862bfefc1750592fe55a08`, including confirm/toggle/refresh behavior and read-only visibility. APK build/publication is still pending at this checkpoint.

The status service test verified the update and rollback against disposable MariaDB. Local XAMPP and physical-device acceptance remain open.


## 2026-10-25 — Mobile app continuation evidence

The mobile parity evidence matrix was refreshed. A repository route audit confirmed every website path in the mobile role-tools list exists, while noting these WEB links still depend on external-browser session continuity. Native role dashboard metrics passed full-schema MariaDB CI for 14 roles (run 40195397302); sponsor assignment and supervisor status service rollback tests passed (runs 40195979512 and 40196421650); the latest source with supervisor controls passed Flutter analyze/tests and APK publication (run 40196556920). A rollback-only transaction date-boundary harness passed PHP lint and full-schema MariaDB CI in run 40198548850: inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS. Local authenticated API/database and physical-device checks remain pending. Native financial, HR, beneficiary-form and Fina workflow parity remains incomplete; do not claim the mobile app is feature-complete.


## 2026-10-25 — Native suspension case management and date-filter integration

The mobile app now opens the suspension-case register natively from the existing suspension tool route. `GET api/v1/suspensions/index.php` returns the existing case list with nanny family scope; `POST api/v1/suspensions/lift.php` is restricted server-side to administrator/VGM roles and performs case, beneficiary, and linked sponsorship updates in one transaction, followed by best-effort audit logging. The native page supports refresh, active/lifted case visibility, bilingual reason labels, and a confirmation step before lifting. Non-age suspension creation and other non-native workflows remain outside this slice.

Full-schema PHP verification passed in run `40199009847`, including the rollback-only service output: `PASS | mobile legal-age suspension service | suspend/lift, linked sponsorship pause/resume, duplicate guard, role/scope guards, rollback`. The harness also checks administrator/nanny case-list visibility, nanny scope, and lift capability. Flutter analyze and the widget suite passed on source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6` in run `40199177461`; Android debug APK build/artifact publication was still in progress at the time of this documentation update.

Transaction date filtering also has a rollback-only full-schema test: run `40198548850` passed `PASS | mobile transaction date filter | inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS`.

Still open: authenticated API/database runtime against the intended local `ahl_el_kheir` instance, physical-device acceptance, website-session continuity for remaining WEB links, and full native parity for financial, HR, beneficiary-form and Fina workflows. Do not describe the app as feature-complete.


## 2026-10-25 — Native suspension workflow Android build PASS

Correction to the earlier in-progress checkpoint: GitHub Actions run [40199177461](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40199177461), source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6`, completed successfully. Dart analysis, the complete Flutter widget test suite, Android debug APK build, and artifact publication all passed. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,895,397 bytes). The only later `main` commit at the time of this note updates documentation, so the artifact contains the current mobile application source.

The PHP full-schema rollback-only suspension case/suspend/lift test passed in run `40199009847`; transaction date-boundary integration passed in run `40198548850`. This is automated CI evidence, not proof of live local XAMPP/API/database behavior or physical-device acceptance.

The mobile parity work is not complete: non-age suspension creation, native financial/HR/beneficiary-form/Fina workflows, and website-session continuity for remaining WEB links are still open. Do not claim full native feature parity until those gaps are implemented and verified.


## 2026-10-25 — Mobile role-tool parity continuation

Source-only continuation on `main` after the user reported that the mobile dashboards omitted many tools visible in the existing web system. The checked-in web dashboards remain the source of truth; do not replace them with a common role menu or infer permissions from role names alone.

This slice restores dashboard entries already evidenced by existing web dashboard routes:
- GM: sponsor assignment and the complete sponsor-request pipeline.
- VGM: sponsor assignment and supervisor management.
- Administration/Staff/Social Media: the complete sponsor-request pipeline alongside the existing new/contacted queues.
- Existing native sponsor-assignment, supervisor-list/status, and suspension workflows are routed into the app rather than launched in an external browser.

Commits: `2dc812eadedf47c600eb043e1cad84fbc37bfbad` (role tools), `651456187d01dc693dc666f2c8f1388b8f4e975c` (native routing), `35e9e3e350c996eeaf618987abc0458da33cf745` (widget tests).

Verification boundary: source edits are committed; Flutter analysis/widget CI for this exact revision and real-device acceptance are not yet confirmed. This is one incremental parity slice, not completion of all dashboards. Continue role-by-role against actual web dashboard links and route guards, preserve restricted-tool regression tests, and keep external WEB links clearly labelled until their authenticated session continuity is proven. Local XAMPP/API and physical-device verification remain separate acceptance gates.


## 2026-10-25 — Role-sidebar parity: omitted tools restored (source/CI pending)

Follow-up source audit compared `includes/sidebar.php` and each target page's real role guard with `mobile/ahl_el_kheir_app/lib/role_tools_section.dart`. The previous attempted additions of sponsor assignment, supervisor management, and the general sponsor-request pipeline to the supplemental role section were removed after confirming those are already present as native work-area cards; duplicate cards are not an acceptable fix.

The current change adds only tools present in the existing role sidebar and not otherwise exposed by the mobile workspace:
- GM: salary-advance waiver decisions (`modules/hr/salary_advance_waiver_gm.php`).
- VGM: orphan-group management (`modules/deputy_gm/groups.php`).
- FM: opening balance and assigned nannies (`modules/accounting/opening_balance.php`, `modules/accounting/my_nannies.php`).
- Accountant: vouchers, assigned nannies, and GM reconciliation; it deliberately does **not** expose journal, transaction, FM review, FM dashboard, or disbursement pages where the target page guards deny this role.
- Accountant Staff: vouchers, assigned nannies, and orphan forms.
- Supervisor and Nanny: orphan forms.

Source commit: `9bbc4dc8d4943dc3858c65c5e5898d3b27d939f3`. Widget coverage commit: `84e55aa12f1f13aea77659fe347be6edee447ac4`. The tests assert the added tools and preserve the accountant disbursement restriction.

**Verification boundary:** GitHub Actions for the exact latest Flutter source revision and a fresh APK are not yet confirmed at this checkpoint. WEB entries still launch the existing website and do not establish authenticated website-session continuity. Local XAMPP/API and physical-device acceptance remain pending. Continue the same route-by-route audit; do not describe overall mobile parity as complete.


## 2026-10-25 — Personal account options restored in mobile header

The existing website header exposes profile, account settings, and salary-advance request options to logged-in users. The mobile account sheet previously exposed only salary statements and leave requests. It now also exposes the existing profile and settings pages plus the salary-advance request page. These entries are explicitly labelled WEB; the salary-advance request notes that an employee profile is required. The links use the existing routes and do not duplicate or reimplement the web business logic.

Source commit: `16e6446c39ba3040a0ce4e7ab934c406ef2a23c7`. Flutter analyze/widget tests and APK build are queued/in progress for this exact revision at this checkpoint. Website session continuity is still not established; the app warns that website sign-in may be required. Local XAMPP/API and physical-device verification remain open.


## 2026-10-25 — Global search exposed in the mobile header

The existing website header exposes the role-aware global search at `modules/search/index.php`. The mobile header now includes a search action that opens that existing page, explicitly labelled WEB; it does not bypass the existing server-side search permissions or claim a native search implementation. As with all WEB routes, the external browser may require the user to sign in separately because mobile bearer authentication is not a website session handoff.

Source commit: `7c846968f7439fbf5db359374520671590d149c5`. Flutter analyze/widget tests and APK build for this exact revision are queued at this checkpoint. Local XAMPP and physical-device acceptance remain pending.


## 2026-10-25 — Admin recovery queue and mobile role-sidebar parity follow-up

The native Sudo/admin dashboard already displayed the pending password-recovery count but did not offer a direct action. It now includes a clearly labelled WEB tool routed to the existing `modules/users/recovery.php`, whose server-side guard permits only `admin` and `hr_manager`; this dashboard entry is shown only in the admin/Sudo workspace. A widget test asserts that tapping the tile dispatches the `recovery` route.

The latest role-sidebar parity slice remains in `mobile/ahl_el_kheir_app/lib/role_tools_section.dart`: GM waiver queue, VGM orphan-group management, FM opening balance/assigned nannies, accountant/accountant-staff permitted links, and orphan forms for Supervisor/Nanny. Restricted accountant pages remain hidden when the target web guard denies access. Personal profile/settings/salary-advance request and global role-aware search are also now discoverable from the mobile UI, labelled WEB where they still use the website.

Source commits: `45be3327f14e5129a9fb2dbb65daa618026dd648` (admin tile), `c19938979b181b53cc01237dce2d8c34bbfa6feb` (route), `12bd0f5dd017bc233e197b95cb4ef1814aeb9261` (widget test). Flutter analysis/widget CI and APK build for the newest source revision are pending. Website-session continuity, local XAMPP/API verification, and physical-device acceptance remain open. This is incremental tool discovery, not full native parity.


## 2026-10-25 — Authenticated mobile-to-website handoff implemented

Root cause confirmed: mobile requests authenticate with bearer tokens, but existing website pages require the PHP `AhlElKheirSession` browser session. Opening WEB tools directly therefore did not authenticate the browser. The mobile app now requests a short-lived handoff token from `api/v1/auth/web_handoff.php`; `mobile_web_handoff.php` validates and consumes it once, re-checks the active/unrevoked mobile session and account state, establishes the normal PHP session, and redirects only to the stored internal page/query. The app routes its existing WEB tools through this handoff. Native suspension and other native workflows remain native.

Security boundaries implemented:
- 256-bit random token; only its SHA-256 hash is stored.
- 60-second expiry; single-use consumption under a database transaction/row lock.
- Target paths restricted to internal `modules/` or `dashboard/` PHP pages; external URLs, traversal, backslashes, and duplicate slashes are rejected.
- Target query is bounded and stored server-side; the browser redirect does not carry the handoff token forward.
- Handoff is denied if the mobile session is revoked/expired or the account is inactive/forced to change password.
- No runtime DDL; migration is `database/migrations/2026-10-25_mobile_web_handoffs.sql`.

Integration evidence from GitHub Actions run `40201983559` (`d82559e9b3495233b0fdf219d7c0ed3356b2fbd5`): PASS for external-target rejection, token/hash/expiry storage, HttpOnly session creation, target redirect, consumption, replay rejection, and full mobile API auth integration. Flutter analyze and widget tests passed for `fb3e73ef3a575e3ff0e0e43585459ab960f1e0b9`; Android APK build is still running at this checkpoint. Local XAMPP/real-device acceptance remains outstanding.

**Required local deployment step after pulling:** apply `database/migrations/2026-10-25_mobile_web_handoffs.sql` to `ahl_el_kheir` before testing WEB links. Without the migration, handoff creation fails safely and the app displays an error. Do not run DDL from PHP requests.

Role-sidebar parity updates in this continuation also restore GM salary-advance waivers, VGM orphan-group management, FM finance dashboard/opening balance/assigned nannies, permitted accountant tools, and orphan forms for Supervisor/Nanny. Admin/Sudo now has a direct password-recovery tool, and global search, profile, settings, salary-advance request, and internal messages are discoverable. Website-only tools remain labelled WEB; full native workflow parity is still not complete.


## 2026-10-25 — Handoff negative-path stress tests and public recovery exception

The disposable MariaDB/PHP integration harness now additionally proves that an expired handoff token is rejected and a handoff issued by a mobile session is rejected after that session is revoked. Latest integration run `40202292241` on test commit `3140502d1a7596ab2ece0e099e7bdcef86de0fe8` completed **successfully**; output explicitly passed external-target rejection, one-time token issuance/hash storage, HttpOnly session creation, target redirect, consumed-token replay rejection, expiry rejection, revoked-session rejection, and the complete mobile API auth integration.

A deliberate exception is retained for `modules/users/password_recovery_request.php`: it is public and must remain openable from the logged-out mobile screen, so that one route opens directly without a bearer handoff. All protected website routes use the one-time handoff. This prevents the new authentication bridge from breaking the forgotten-password path.

Flutter analyze and widget tests passed on source commit `a00ec31729ff3c983838b7224e75ea80c00972f0`. The debug APK build/publish step is still in progress at this checkpoint. No local XAMPP migration or physical-device test has been performed from this environment.


## 2026-10-25 — Original role dashboards reachable from the mobile workspace

The role-specific mobile summary remains native, but users now also have a direct, clearly labelled WEB action to open their existing role-specific website dashboard with its complete original controls. The route map is sourced from `includes/sidebar.php` and verified against the target dashboard guards: Admin/Sudo, GM, VGM, FM, Accountant/Accountant Staff, Supervisor, Nanny, Administration/Staff/Social Media, HR Manager/HR Staff, and Projects Manager/Project Supervisor. Sudo/admin has the equivalent action in its dedicated control dashboard. Unknown role codes do not receive a guessed dashboard route.

The one-time website handoff normalizes legacy aliases (`sudo/super_admin`, `gm`, `vgm`, `fm/finance`) to the same canonical role codes as the mobile API before creating the PHP session. The public password-recovery page remains directly accessible from the logged-out screen.

Automated evidence: role-dashboard route mapping tests and the expanded mobile widget suite pass on the latest dashboard-link test run `40203004193` (commit `0fc03d7e2593f424fbe951e319f6d6dbbc47aa99`). The debug APK build step is in progress. The latest mobile API handoff integration run `40202879178` passed, including expired/revoked-token rejection and replay protection. Local migration/device acceptance remains outstanding.


## 2026-10-25 — Mobile API connection regression: wrong APK port corrected

Root cause confirmed from the exact build configuration: the latest push-triggered APK (Actions run `40203004193`) was compiled with the fallback API URL `http://192.168.18.9:881/AhlElKheir/api/v1`. Port `881` was the old/wrong port. The previously verified phone endpoint is `http://192.168.18.9:8081/AhlElKheir/api/v1`; laptop loopback verification used `http://127.0.0.1:8081/AhlElKheir/api/v1`. This explains the generic `تعذر الاتصال بخادم API` login message: the app's error handler hides transport/response failures behind one message.

Corrected both the Dart fallback in `mobile/ahl_el_kheir_app/lib/main.dart` and the GitHub Actions dispatch/fallback URL in `.github/workflows/build-flutter-mobile-app.yml` to port `8081`. Commits: `f88668ff93cf446e9cfe8d7c011b22721ff98787` and `c5d7b6f04d708e7503fc2d3d812394409f5842bc`. The first corrective run `40204712668` started before the workflow-file fallback was updated, so it still uses the stale port and must not be used. The authoritative corrected build is run `40204718850`, created from workflow commit `c5d7b6f04d708e7503fc2d3d812394409f5842bc`, whose workflow fallback and Dart fallback both specify port `8081`. Both builds passed Flutter analysis/tests; APK packaging was still running at the last check. Do not use artifacts from runs `40203004193` or `40204712668`; they have the wrong port. Acceptance remains pending until run `40204718850` publishes its APK and the existing login succeeds against the user's local server.


### 2026-10-25 — Mobile login API port regression

The physical-phone APK showed `تعذر الاتصال بخادم API`. Root cause was confirmed in `.github/workflows/build-flutter-mobile-app.yml`: the automatic build fallback targeted port 881 although the verified local Apache/API port is 8081. Fixed in `221937453f0df3606982b6ab0f7aa8fa10711c7d`. The new APK build is tracked at https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40205884086. Do not reuse the previous APK; wait for successful artifact publication. Physical-device login remains unverified until the new artifact is installed and a login succeeds against local Apache.

## 2026-10-25 — Mobile application UI and navigation regression checkpoint

User-reported defects: overflow warnings at the bottom of Sudo stat cards; all System Administration website links fail; too many workflows are WEB handoffs instead of native mobile pages; visual design/colors remain unacceptable to the user. Root cause found for the broken handoffs: `call()` returns the decoded `data` map, but `openWebPage()` double-indexed `response['data']`, so the handoff token was never read from the returned map. Fixed token extraction and changed the failure message to include diagnostic details. Updated admin/role stat-card sizing and refreshed the shared mobile palette toward teal/sea-green. Added a role-dashboard overflow regression assertion. Commits: `68ab7b57283b4fd3b052c860cb5cf6b64d73daf8`, `141662935f37f3898501eca3a279aa5fb4e949b4`, `c69eb19c122370ef41e1ee4a749977316809a627`, `8180d52e3e73a64d638f2cbb4b324c1ba3dc5891`, `d5ed6f1ce673354dad6c984e5e49941d0ff946e1`. Automated CI and physical-device verification are pending. This is a targeted correction, not completion of the user's larger native-app requirement. The WEB links were a bridge due to missing native API/screens and must not be represented as equivalent to full app functionality.


## 2026-10-25 — Mobile app-only navigation and native administration milestone

The Flutter client no longer uses external URL launching or the website handoff. Existing native workflows are dispatched to native screens; unsupported workflows stay inside the app and clearly indicate that native implementation is pending. This is not full mobile parity and is not yet physically device-accepted.

Added a server-authorized, read-only searchable/paginated mobile user directory and native department CRUD. Department rules mirror the existing web module: Arabic and English names required, maximum 100 characters, duplicate-name rejection, and no deletion while users or employees are assigned. A native recent-audit screen displays the latest six events from the existing admin overview.

Automated verification: full-schema rollback-only MariaDB test passed in Actions run [40210824853](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40210824853), output `PASS | mobile admin department service | create, list, duplicate guard, update, assigned-user delete guard, delete, rollback=PASS`. Flutter widget tests were added for user directory, department CRUD UI, audit activity, and the in-app pending-workflow notice. Physical-device verification remains open.

Remaining mobile parity includes full user-account actions, password recovery decisions, reports, settings, accounting/disbursements/Fina, backup/database administration, global search, profile/messages, orphan forms, and further HR/finance workflows. Do not describe the app as complete or request user acceptance testing until a consolidated native workflow slice is ready.


## 2026-10-25 — Native password recovery workflow added

The app now provides native password recovery review for canonical administrator/Sudo and HR Manager roles. The API lists the latest 50 requests and supports approve/reject only while pending. Approval atomically updates the password hash and recovery request, sets the forced-password-change flag and 24-hour expiry, and returns the temporary password only once in the response; notifications/audit are best-effort after commit. Rejection is recorded and sends a best-effort notification. Server-side authorization is authoritative; HR Staff is not granted this tool. No database migration was added.

Verification passed in Actions run [40211295992](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40211295992): rollback-only full-schema MariaDB recovery test and PHP lint. Flutter analyzer passed; the latest Flutter widget suite/APK publication is pending. No physical-device acceptance is claimed.



## 2026-10-25 — Mobile native-only navigation and Sudo administration checkpoint

The Flutter app's navigation policy is native-only: no browser, WebView, external URL, or website dashboard handoff is used as a substitute for an app workflow. The separate “Full website dashboard” action and duplicate administration-dashboard tile were removed. Routes that still lack a native implementation now show an in-app notice and do not open a placeholder page. The server-side website-handoff API now returns HTTP 410, and the old mobile_web_handoff.php redirect route also returns HTTP 410 without setting a browser session or redirecting; the integration test asserts both behaviors and confirms no handoff records are created. The old handoff migration/table is retained but is no longer used; no runtime DDL or destructive database change was introduced. These routes remain unfinished product work and must not be reported as implemented.

Native user administration was expanded using the existing HR employee provisioning workflow and current database schema:
- User directory now has native create and edit screens, with role/department/manager options from the existing roles, departments, and users data.
- Creation submits to the existing native API action and provisions the linked employee profile and initial salary history through hrCreateUserWithEmployee; the temporary password is shown in-app for one-time sharing.
- Editing submits identity/role/department/manager/status changes through the native API and calls hrSyncEmployeeIdentityFromUser to synchronize linked employee identity fields.
- The server-side user-action authorization list was aligned with the user-directory policy to include admin, sudo, super_admin, and hr_manager. Previously, Sudo could see the directory but was denied by the action endpoint; this was a concrete authorization mismatch found in source review.
- Native user create/edit widget tests and the rollback-only service test cover form payloads, validation, identity synchronization, status changes, password reset rules, and rollback. No local physical-device test has been performed in this checkpoint.

Related commits: cb2b809172b80a31ff0a7ee61ee84ddea5384c53, c8558ae6c6d354bba33973b743cdc04040b33d68, e47b5c0b6b19dd06fedfa8a09eba01c60090cfc8, 84efd7c2981065454a4b51ca7758b38a7c9d50d4, 11019d79b6dd39bcd63aa0c4ff96237cdcd03477, ecbf8e0520fd2940a34ece36616e390c6b54eb2d, 383714f576b8933daf7f54a38ddf74ec2a77139c, 3c7834b9a15a2863a71bd551deb781de4bc42e31, ce1822e7dae12c27bac62127e3d4bbec8bc463ff, 156e974991ba10e58dbc4c774cde95ae119a51e7.

Verification: Flutter analysis passed, all Flutter widget tests passed, and the Android debug APK was built and published by GitHub Actions run 40212763374 for app source commit 383714f576b8933daf7f54a38ddf74ec2a77139c. Download the artifact from https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40212763374 (artifact: ahl-el-kheir-flutter-android-debug-apk; retention through 2026-11-24). The native-only backend retirement was verified by mobile API integration run 40213109443: API handoff requests return HTTP 410, create no handoff records, and the retired browser route does not redirect or set a browser session. The rollback-only native user-action service test passed in integration run 40213296633: identity update, status toggle, password-reset hash/forced-change flag, short-password guard, self-action guard, and rollback all passed. This does not prove physical-device connectivity to the user's local XAMPP API; that remains unverified. Native parity remains incomplete for settings, reports, backup/database tools, finance dashboard/accounts/disbursements/Fina collection, orphan form, profile/settings, salary advance request, messages and system search. Do not send the user to test this as a finished app until the current build is green and the next consolidated native workflow slice is ready.



## 2026-10-25 — Configurable mobile API endpoint

The native login screen now exposes an API-server configuration control. It validates HTTP/HTTPS URLs that end in /api/v1, persists the selected endpoint in platform secure storage, displays the active endpoint on the login screen, and clears the old bearer token when the endpoint changes. This removes dependence on a single hard-coded LAN IP and lets a phone be configured for the computer's currently reachable local address without rebuilding the APK. It does not bypass Android cleartext policy, firewall rules, or host reachability requirements.

Verification checkpoint: Flutter analysis passed and all Flutter tests passed in GitHub Actions run 40213757297; APK compilation/publication was still in progress at this documentation point. Do not claim the new endpoint-configurable APK is ready until that run publishes its artifact.


## 2026-10-25 — Mobile dashboard checkpoint

The existing Flutter admin dashboard received a narrow-screen layout correction and a regression test. Source commit: `fa11cde81f98839175431dd2d86348ee363d2270`. Flutter analysis and widget tests passed in run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941); APK compilation/publication was pending at the checkpoint. This is not physical-device or live local API verification.

Remaining mobile work is still open: complete the native workflows currently marked unavailable, resolve role/dashboard visibility against the actual server authorization policy without widening permissions by assumption, and only then request consolidated device acceptance. No website/browser handoff is acceptable as a substitute for native functionality.


## 2026-10-25 — Native audit register and Android build confirmation

- Added authenticated read-only `GET api/v1/audit/index.php?page=1&q=...`. Authorization follows the existing `modules/logs/audit.php` guard: canonical administrator and General Manager roles can read the register; other roles receive 403. Search covers action, entity type, username and full name; results are paginated at 50 per page with parameterized search terms. The mobile API does not expose audit deletion and introduces no schema change/runtime DDL.
- Replaced the previous six-event-only native audit screen with a searchable/paginated register and added a widget test for search and page navigation. The PHP lint workflow now includes the endpoint.
- Source commits: `b0af7d3f98555582ee978843f89373fd1c8740a2` (API), `e0599319cc2a820de8b00731a2c14ceda4a27ccb` (app wiring), `97103af61e0b25f8efa09012db60725e4b3cee0b` (screen), `249a383786c08067657ae8bd705c269160d182b4` (widget test), `da50eab0685a9828bd456d2d0e9777bcfc403c9a` (PHP lint workflow).
- Correction to the prior pending-build checkpoint: Flutter workflow run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941) completed successfully, including Dart analysis, widget tests, debug APK build and artifact publication. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,487,052 bytes, SHA-256 `a02fbca24a2e9a14915bc895ec999ef4108f2854a3caf6e8d57ae5e3f5dc2d5b`, expires 2026-11-24.
- The audit-register change's own CI run is pending. No local XAMPP API/role-matrix or physical-device acceptance is claimed. Full native role parity remains incomplete; keep existing authorization boundaries and do not use external-browser handoff as a substitute for native features.
