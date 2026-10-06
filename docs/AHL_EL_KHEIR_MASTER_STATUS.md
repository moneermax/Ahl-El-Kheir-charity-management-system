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
