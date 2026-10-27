# Mobile role feature parity — evidence matrix

Last reviewed: 2026-10-26
Source of truth: current `main`, especially `config/functions.php::dashboard_for_role()`, `dashboard/admin_dashboard.php`, and the Flutter app under `mobile/ahl_el_kheir_app/`.

## Administrator / system developer

The web role mapping routes `admin`, `sudo`, and `super_admin` to `dashboard/admin_dashboard.php`. That page explicitly defines these control groups:

| Web dashboard group | Source tools | Mobile status after dedicated dashboard change |
|---|---|---|
| System administration | Users, departments, system settings | Users and departments have native screens; system settings now has a native form for the exact `admin` role. Logo upload remains unimplemented. Other roles do not gain access by normalization. |
| Core records | Sponsors, families, sponsorships, reports | Sponsors, families and sponsorships open native screens. Reports now has a native role-scoped overview and uncovered-families report; other reports remain unavailable natively. |
| Finance | Finance dashboard, chart of accounts, transaction ledger, monthly disbursements, Fina Al-Khair collection | Finance overview, chart of accounts, transaction ledger, role-scoped monthly-disbursement workflow (batch creation, transfer, item confirmation, return receipt, reopen and void), Fina dashboard, registration and review/history have native screens. Other FM accounting workflows remain open. No external page is opened. |
| Protection and maintenance | Database backup, database tools, audit log | Database tools now open a native read-only table/schema/sample-row browser; database backup has native create/list/authenticated-download/delete actions; both require the exact `admin` role. No browser or external page is launched. |
| Dashboard overview | Counts for users, sponsors, families, sponsorships; unread notifications; pending recovery requests; settings and audit counts; recent audit activity; PHP/database status | Authenticated `GET api/v1/admin/overview.php` returns record counts, recent audit entries, PHP version and database connectivity. Current mobile cards show eight metrics: users, sponsors, families, sponsorships, pending password-recovery requests, unread notifications, audit-record count, and settings count, plus recent audit activity. |

Additional existing mobile-native operational screens are included in the administrator dashboard: projects, employees, attendance, supervisors, sponsor requests, financial review queue, legal-age alerts, and sponsor win-back.

### Authorization boundary

`api/v1/admin/overview.php` requires the existing bearer-session authentication and restricts the response to `admin`, `sudo`, and `super_admin`. The mobile client never launches the browser or hands off to the website for web-only tools. Such selections display an in-app not-available notice; a WEB label is not native workflow coverage.

### Verification status

Finance overview and chart-of-accounts API/full-schema integration tests have passed; PHP lint, Flutter analysis, all widget tests, Android debug APK build and artifact publication have passed. No local XAMPP/database or physical-device runtime result is claimed.

## Other roles

The website routes each role to a distinct dashboard in `config/functions.php`: GM, VGM, FM, accountant, supervisor, nanny, staff/administration, HR, and projects. A role-specific mobile quick-tools section surfaces additional web-only placeholders derived from those dashboards. They are labelled **WEB** to distinguish them from native features, but selecting them shows an in-app unavailable notice and does not open a browser or website.

| Role | Existing native mobile capabilities | Additional web-only placeholders surfaced in the app | Remaining parity work |
|---|---|---|---|
| GM | Sponsors, families, sponsorships, sponsor requests, projects, transactions, legal-age alerts, native suspension case register, native GM reconciliation, native sponsor win-back, GM payroll-period history, personal leave requests/salary statements, native role metrics | Executive reports, uncovered-family/sponsorship/operational reports, incentive policy | Native attention queue and approval workflows; native implementation of remaining tools; no web handoff |
| VGM | Sponsors, families, sponsorships, sponsor requests, projects, supervisors with native activate/suspend controls, FM review queue, transactions, legal-age alerts, native suspension register/lift, native GM reconciliation, native sponsor win-back, native role metrics and sponsor assignment | Reports | Remaining VGM operational workflows; native implementation of remaining tools; no web handoff |
| FM | Projects, transactions, FM review queue, legal-age alerts, native role metrics, monthly disbursement workflow, Fina dashboard/registration/review, native report detail, native payroll period management and transfer proof/confirmation | Journal, vouchers, and remaining FM accounting operations | Native journal/voucher workflows and remaining accounting API parity |
| Accountant / Accountant Staff | Sponsorships/transactions where permitted, projects where permitted, personal leave requests/salary statements, native role metrics | Group disbursements, monthly disbursements, reports | Exact assignment-scoped native workflow parity |
| Supervisor | Sponsors, families, sponsorships, transactions, legal-age alerts, native role metrics | Fina review/history/report | Native Fina workflows and supervisor-specific dashboard/record scope |
| Nanny | Families, preliminary verification, group verification, role-scoped monthly disbursements with receipt confirmation/return, legal-age alerts, read-only suspension case register, personal leave requests/salary statements, native role metrics | Remaining reports | Native dashboard reconciliation and remaining operational workflows |
| Administration / Staff / Social Media | Sponsor requests, native sponsor win-back for Administration, personal leave requests/salary statements, native role metrics | Orphan forms; Administration additionally gets available families | Native beneficiary forms and follow-up workflows |
| HR Manager / HR Staff | Employees, employee detail, attendance, leave requests/approval queue, personal salary statements, native role metrics, attendance-policy management, native payroll management (period generation, draft edits/approval, account assignment, proof upload/view, legacy evidence and explicit transfer confirmation/accounting); role boundaries remain server-enforced | Contracts, payroll policy, incentive verification, and remaining HR workflows | Local XAMPP/API/database and physical-device acceptance; remaining HR workflows |
| Projects Manager / Project Supervisor | Projects, project detail, progress and milestone actions, native role metrics | Project register link | Remaining project-specific website workflows |

Do not infer parity from role-specific color or a shared module list. Server-side authorization remains authoritative for every endpoint and website page.


## 2026-10-25 — Role-aware mobile tools and employee self-service

Source commits on `main`:
- `8f659a0cec3ae9d565dd8bba98297da2f94b7639`: expose native leave requests and salary statements from the mobile workspace.
- `842e4382bcf922f9c67b82fcc414abdd7e72fba8` and `9e3044d842c1893bf685ef2bb87ae253a7d2c150`: add a role-specific quick-tools section derived from each existing web dashboard. Website-only links are labelled WEB and remain subject to website server-side authorization; this is navigation coverage, not native workflow parity.
- Historical correction: the later direct reachability evidence confirmed Apache listens on `8081` and has no listener on `881`; the old conclusion in commits `fbc30eb`/`a2eb57d` was incorrect. Current app/workflow defaults and the stale-URL migration are corrected by `da04d0fea8e73f505fa521546e653ca4e263e0c0` to use verified port `8081`.

The workspace now exposes the existing native personal leave-request page and finalized salary-statement page, and adds role-scoped quick links matching the real GM, VGM, FM, Accountant, Supervisor, Nanny, Administration/Staff, HR, and Projects dashboard routes. The API enforces leave-queue permissions and returns salary statements only for paid payrolls posted to accounting. No account without a linked employee profile is given another employee's records.

**Still open; do not claim full completion:** role-specific dashboard metrics and operational workflows are not all native yet; WEB-labelled placeholders do not open an external browser; native legal-age suspension source is implemented; automated analysis/tests/build and disposable-schema API verification have passed; local XAMPP/API/database and physical-device runtime remain pending; the mobile API migration must be applied to the intended local development database before API runtime testing. The newest source checks have now passed; see the verified CI milestone below.


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

## 2026-10-25 — Native role-specific dashboard metrics (source implementation; runtime gate pending)

Added `GET api/v1/dashboard/overview.php` and a native Flutter `RoleDashboardPage` section for General Manager, Vice General Manager, Financial Manager, Accountant/Accountant Staff, Supervisor, Nanny, Administration/Staff/Social Media, HR Manager/Staff, Project Manager and Project Supervisor. The API re-authenticates the bearer token on each request, uses the existing canonical role normalization, rejects administrator roles in favor of the dedicated administrator endpoint, and returns only metrics scoped to the authenticated role. Metric SQL was derived from the corresponding existing web dashboard queries.

Commits: `f6ac0b4490b712ae7bebd23474aa97d6ab93d165` (API), `1e74df5e3e601593808121a907a7023faede21ba` (native metrics section), `7c96953e298aa83b4d04149e91a2a55efa952283` (workspace integration), `4fe6df05679946968666959cfc6025921b28e1fd` (PHP lint CI).

The additional role-tool routes were checked against the current repository tree. The Supervisor-only “Record Fina collection” link was removed after source inspection confirmed `modules/transactions/fina_payment_create.php` permits only financial_manager/fm/admin. A widget regression test covers that permission boundary.

Automated verification for the final combined revision is pending. Do not claim the new dashboard SQL is runtime-verified against the local database until its workflow and local XAMPP/API request pass. Transaction date filters already exist in both the mobile UI and API, including inclusive `transaction_date >= from` and `transaction_date <= to`; same-day widget coverage exists, but the complete date-boundary, empty-result, reversed-date, pagination/refresh and authenticated API acceptance remains open. Website-only links remain website navigation, not native feature parity. Physical-device and local-database acceptance remain pending.

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


## 2026-10-25 — Mobile parity verification refresh

Repository audit on `main` confirmed that every literal website route currently listed in `mobile/ahl_el_kheir_app/lib/role_tools_section.dart` exists in the repository tree. These entries remain website links, not native workflow implementations; external-browser website-session continuity is still unverified.

Verified native/API milestones:
- Role dashboard metrics: full-schema MariaDB 10.4.32 CI passed for all 14 supported non-admin role variants in run `40195397302`.
- Role dashboard/filter widgets and Android debug APK: run `40195228074` passed at Flutter source `64da0db9eef605a8a1442e9c623e1fa3a85f80e7`.
- Native sponsor assignment: rollback-only full-schema service test passed in run `40195979512`.
- Native supervisor status controls: rollback-only full-schema service test passed in run `40196421650`; Flutter analyze/tests and APK artifact publication passed in run `40196556920` at source `d8a8e66e056d0f1535862bfefc1750592fe55a08`.
- Transaction date filtering: added `tools/test_mobile_transactions_date_filter.php`, which moves three existing records to isolated 2099 boundary dates inside a transaction, verifies inclusive start/end and same-day behavior plus exclusion outside the range, and rolls all changes back. PHP lint and the full-schema MariaDB test passed in run `40198548850`: `PASS | mobile transaction date filter | inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS`.

The API query test complements the Flutter widget tests for same-day/multi-day filters, pagination/refresh, and reversed-range rejection. It does not replace authenticated HTTP testing against the intended local `ahl_el_kheir` database or physical-device acceptance.

**Still open:** complete native implementations for several role-specific financial, HR, beneficiary-form and Fina workflows; native implementation of remaining WEB-labelled tools; no web handoff; local XAMPP/API/database verification; physical-device acceptance. Do not describe mobile parity as complete.


## 2026-10-25 — Native suspension case management and date-filter integration

The mobile app now opens the suspension-case register natively from the existing suspension tool route. `GET api/v1/suspensions/index.php` returns the existing case list with nanny family scope; `POST api/v1/suspensions/lift.php` is restricted server-side to administrator/VGM roles and performs case, beneficiary, and linked sponsorship updates in one transaction, followed by best-effort audit logging. The native page supports refresh, active/lifted case visibility, bilingual reason labels, and a confirmation step before lifting. Non-age suspension creation and other non-native workflows remain outside this slice.

Full-schema PHP verification passed in run `40199009847`, including the rollback-only service output: `PASS | mobile legal-age suspension service | suspend/lift, linked sponsorship pause/resume, duplicate guard, role/scope guards, rollback`. The harness also checks administrator/nanny case-list visibility, nanny scope, and lift capability. Flutter analyze and the widget suite passed on source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6` in run `40199177461`; Android debug APK build/artifact publication was still in progress at the time of this documentation update.

Transaction date filtering also has a rollback-only full-schema test: run `40198548850` passed `PASS | mobile transaction date filter | inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS`.

Still open: authenticated API/database runtime against the intended local `ahl_el_kheir` instance, physical-device acceptance, native implementation of remaining WEB-labelled tools; no web handoff, and full native parity for financial, HR, beneficiary-form and Fina workflows. Do not describe the app as feature-complete.


## 2026-10-25 — Native suspension workflow Android build PASS

Correction to the earlier in-progress checkpoint: GitHub Actions run [40199177461](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40199177461), source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6`, completed successfully. Dart analysis, the complete Flutter widget test suite, Android debug APK build, and artifact publication all passed. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,895,397 bytes). The only later `main` commit at the time of this note updates documentation, so the artifact contains the current mobile application source.

The PHP full-schema rollback-only suspension case/suspend/lift test passed in run `40199009847`; transaction date-boundary integration passed in run `40198548850`. This is automated CI evidence, not proof of live local XAMPP/API/database behavior or physical-device acceptance.

The mobile parity work is not complete: non-age suspension creation, native financial/HR/beneficiary-form/Fina workflows, and native implementation of remaining WEB-labelled tools; no web handoff are still open. Do not claim full native feature parity until those gaps are implemented and verified.


## 2026-10-25 — Administrator dashboard responsive checkpoint

The dedicated admin dashboard now has a narrow-phone layout regression test and more defensive metric-card, hero-control, status-pill, and tool-tile sizing. Flutter analysis and all widget tests passed in run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941); Android APK publication was still pending at the time of this note.

Tool availability labels now distinguish implemented native workflows from unfinished ones. Users, departments, password recovery, and recent audit activity have native page implementations, but API authorization is not uniform across the roles shown the admin dashboard. Departments is admin-only; password recovery is admin/HR-manager only; settings is still not native. Do not grant `sudo`/ `super_admin` access or change web guards without a confirmed role policy. No external-browser handoff is to be added as a workaround. Full native parity and local phone/API acceptance remain open.


## 2026-10-25 — Native audit register and Android build confirmation

- Added authenticated read-only `GET api/v1/audit/index.php?page=1&q=...`. Authorization follows the existing `modules/logs/audit.php` guard: canonical administrator and General Manager roles can read the register; other roles receive 403. Search covers action, entity type, username and full name; results are paginated at 50 per page with parameterized search terms. The mobile API does not expose audit deletion and introduces no schema change/runtime DDL.
- Replaced the previous six-event-only native audit screen with a searchable/paginated register and added a widget test for search and page navigation. The PHP lint workflow now includes the endpoint.
- Source commits: `b0af7d3f98555582ee978843f89373fd1c8740a2` (API), `e0599319cc2a820de8b00731a2c14ceda4a27ccb` (app wiring), `97103af61e0b25f8efa09012db60725e4b3cee0b` (screen), `249a383786c08067657ae8bd705c269160d182b4` (widget test), `da50eab0685a9828bd456d2d0e9777bcfc403c9a` (PHP lint workflow).
- Correction to the prior pending-build checkpoint: Flutter workflow run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941) completed successfully, including Dart analysis, widget tests, debug APK build and artifact publication. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,487,052 bytes, SHA-256 `a02fbca24a2e9a14915bc895ec999ef4108f2854a3caf6e8d57ae5e3f5dc2d5b`, expires 2026-11-24.
- The audit-register change's own CI run is pending. No local XAMPP API/role-matrix or physical-device acceptance is claimed. Full native role parity remains incomplete; keep existing authorization boundaries and do not use external-browser handoff as a substitute for native features.


## 2026-10-25 — Audit API role-guard correction and current Android artifact

- Flutter build workflow run [40217046629](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40217046629) completed successfully for app source `69c35a22978976db13b2d62942f08a87a7ee18d9`: Dart analysis, widget tests, debug APK build and artifact publication all passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, 77,483,457 bytes, SHA-256 `166dcafc2b9f3c51cc6330231b4e6b5ca3a789f03bf7dcd1e789bc8f775d813c`, expires 2026-11-24.
- Source review found a concrete authorization mismatch in the newly added audit API: `ak_api_authenticated_user()` normalizes `sudo` and `super_admin` to `admin`, but the existing `modules/logs/audit.php` checks the persisted role exactly and permits only `admin` and `general_manager`. The API now checks `raw_role_code` so it matches the web guard; no permissions were broadened. Correction commit: `b54fc8bd6af618313f20a16adf7e5283c63eccf4`.
- The corrected API endpoint still needs its own PHP lint and role-matrix CI confirmation. No local XAMPP/API or physical-device verification is claimed. Continue native parity from current `main`; keep existing role guards authoritative and avoid external-browser handoffs.


## 2026-10-25 — Native system settings implementation

- Added `GET/POST api/v1/admin/settings.php` and `mobile/ahl_el_kheir_app/lib/admin_settings_page.dart` for organization, financial, SMTP, system settings, and administrator-only organization-logo upload. Logo uploads accept detected JPG/PNG/GIF/WebP content up to 2 MB, update the existing `settings.site_logo` value, and audit the change. Disposable API integration passed upload success, unauthenticated denial, and nanny-role denial in run `40299909395`; Flutter analysis/widget verification for the new picker UI was pending at this checkpoint.
- Authorization checks the persisted `raw_role_code` and allows only exact `admin`, matching `modules/settings/index.php`; `sudo`/`super_admin` are not granted settings access by normalized routing roles. The API rejects unknown setting keys, validates email/select/number values, does not return stored setting values outside the allowlist, never returns the SMTP password, preserves an existing SMTP password when the field is blank, and writes a masked best-effort audit event for changed values. No schema change or runtime DDL.
- Added a Flutter widget test for loading/editing/saving settings and password omission, and added the endpoint to the PHP syntax workflow. Dart analysis passed; the first widget-test attempt revealed the test tapped a save button below the viewport. The test now scrolls the button into view before tapping. Latest Flutter run [40218216549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40218216549) has passed analysis and widget tests; Android APK build/artifact publication was still running at this documentation point.
- Native settings is available only to the exact admin role. The administrator dashboard labels that restriction rather than implying that `sudo` has access. Local XAMPP/API role-matrix and physical-device acceptance remain unverified.
- CI note: the separate full-schema migration workflow's repeated failure is the known harness guard `Refusing to run outside mobile_api_auth_test with MOBILE_API_AUTH_TEST=1`; the PHP lint, MariaDB 10.4 migration, HR migration and rescheduling jobs passed in run `40218041093`. No closed payroll code or production behavior was changed in response to that unrelated harness failure.

**Native-only navigation correction:** the Flutter app's `openNativeTool()` explicitly never launches a website, browser, or external application. Any website-only route not mapped to a native screen displays an in-app unavailable message. Older parity text that describes WEB links as opening the external browser is stale; WEB labels are placeholders, not completed workflows.


## 2026-10-25 — Native settings verification and Android APK published

Correction to the previous pending-build note: Flutter workflow run [40218216549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40218216549) completed successfully for app source `d2566a3130d89d7ba04b510063a49cbac410a05c`. Dart analysis, the complete Flutter widget-test suite (including native settings form test), debug APK build and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,491,114 bytes, SHA-256 `26e427538cca26cb9573ab0ce3e4b1c3a0f25175ea3f164dba553beb4e164362`, expires 2026-11-24. Download through [GitHub Actions run 40218216549](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40218216549).

The PHP lint job passed in run `40217995457`; the mobile API auth integration job passed in run `40217995530`. The separate full-schema workflow still fails only at its existing invocation guard `Refusing to run outside mobile_api_auth_test with MOBILE_API_AUTH_TEST=1`; its PHP lint, MariaDB 10.4 migration, HR migration and rescheduling jobs passed. This does not establish a settings DB role-matrix test. Local XAMPP API/database behavior and physical-device acceptance remain unverified.


## 2026-10-25 — Native reports overview and uncovered-families report

- Added `GET api/v1/reports/overview.php`, guarded against the exact persisted role using `modules/reports/report_registry.php`. It returns the same core overview metrics from the existing report center and the caller's report catalog; the uncovered-families count uses the authoritative `family_children` relationship and active sponsorship link.
- Added `GET api/v1/reports/orphaned_families.php` with role guard from the same registry, city/status filters, family/child counts and monthly need values. The native screen opens existing family detail inside the app. It does not create a browser handoff. Report pages not implemented natively remain disabled and labelled as not ready.
- Added native reports center and uncovered-families screens, in-app routing for the existing reports tool, and widget tests. PHP lint passed in workflow run `40218906611`. Flutter analysis and widget tests passed in run [40219066747](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40219066747); Android debug APK build/artifact publication was still running at this checkpoint.
- The endpoints use the report registry's exact role list, not normalized dashboard roles. Local XAMPP API/data-role verification and physical-device acceptance remain unverified. Native parity remains incomplete; do not install an intermediate APK or treat the reports hub as implementation of every listed report.

## 2026-10-26 — Admin dashboard authorization visibility correction

The administrator dashboard no longer presents Departments, Settings, Password Recovery, Reports or Audit as actionable links to `sudo`/`super_admin` when the existing endpoint guards deny those roles. `AdminDashboardPage` receives the raw persisted role code. User Accounts remains available to Sudo under its current API guard. The Reports tile text now accurately describes the native reports center. A widget test covers the visibility contract. No permissions were broadened; current CI and device verification remain pending.



## 2026-10-26 — Admin dashboard role visibility CI verified

The role-visibility test and Android build are now CI PASS. Workflow [40273273719](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40273273719) passed Dart analysis, the full Flutter widget suite, APK build and publication at source `9c57765ab408afa43c1d81823e2aa7a5af1ec703`. Sudo sees and can open native User Accounts; exact-admin-only Departments, Settings, Password Recovery, Reports and Audit are absent. No backend permission change was made. Artifact `ahl-el-kheir-flutter-android-debug-apk` (77,507,377 bytes, SHA-256 `0c195da7469284f95aa8d7664874b2c62534ed096060e88f263844c9ce62279c`) expires 2026-11-25. This proves automated CI only. Authenticated local XAMPP/API/database and physical-device acceptance remain unverified; the other native parity gaps listed above remain open.


## 2026-10-26 — Native administrator finance overview checkpoint

The administrator Finance dashboard entry now opens a native read-only overview instead of the previous not-ready placeholder. It shows treasury, posted monthly flow, administrative fees, disbursement status/value and operational counts. The endpoint `GET api/v1/admin/finance_overview.php` uses the existing FM dashboard calculations and exact raw role `admin`; no permissions or schema were broadened. Chart of accounts, read-only monthly-disbursement register, Fina dashboard/registration/review, database backup, and database browser are native for their existing permitted roles; Sudo does not gain access to administrator-only tools. API/full-schema integration passed in [40274853455](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274853455); PHP lint passed in [40274952065](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952065); Flutter analysis/widget tests passed in [40274952027](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952027). APK publication and local/device acceptance remain pending.


Correction — 2026-10-26: Flutter workflow [40274952027](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952027) has now completed successfully. Dart analysis, the full widget suite, Android debug APK build and artifact publication all passed for app source `89def08b6adde95bec648cb1349f18860b945ef5`. Published artifact `ahl-el-kheir-flutter-android-debug-apk`: 77,523,067 bytes; SHA-256 `7800554326cf99174b90550284ede7e1d44f634d48cc895dfb522f85335742a0`; expires 2026-11-25. Download via the workflow run’s Artifacts section. This is automated CI evidence only; authenticated runtime against the user’s local XAMPP/API/database and physical-device acceptance remain outstanding. The native finance page is read-only; chart of accounts, monthly disbursements and Fina collection remain unfinished natively.


## 2026-10-26 — Native finance and chart-of-accounts checkpoint

The administrator dashboard now has native read-only Finance Overview and native Chart of Accounts screens. The latter lists account balances based on posted journal lines and supports account creation and active-status toggling using existing account columns; no runtime DDL, schema migration, or accounting-entry mutation was added. The exact-role API endpoints are `GET api/v1/admin/finance_overview.php`, `GET api/v1/admin/accounts.php`, and `POST api/v1/admin/accounts_action.php`. Live full-schema integration passed for finance metrics and chart account listing/create/toggle/zero-balance behavior in [run 40275754236](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40275754236). Flutter analysis and all widget tests passed on current app source `27ff209075056f606db2a85c6c1a16259cac9316` in [run 40276057511](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40276057511); APK build/publication is still in progress at this checkpoint, so no new APK is available yet. Local XAMPP/API/database and physical-device acceptance remain open. Chart of accounts, monthly disbursements and Fina collection are not yet full native feature parity.


Correction — 2026-10-26: The Android build has now completed successfully. Workflow [40276057511](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40276057511) passed Dart analysis, all Flutter widget tests, debug APK build and publication for source `27ff209075056f606db2a85c6c1a16259cac9316`. Artifact `ahl-el-kheir-flutter-android-debug-apk`: 77,526,098 bytes; SHA-256 `798f2756a91c19f0a0448165907b2dbfa364c26d3465925784cbcab96e3bf144`; expires 2026-11-25. Download from the workflow run’s Artifacts section. Live API integration passed on the same backend/test source line in [run 40275754236](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40275754236). This proves CI/disposable-schema behavior only; local XAMPP/API/database and physical-device acceptance remain open. Full native feature parity is not complete.

## 2026-10-26 — Native administrator monthly disbursement register (verification checkpoint)

Added a read-only native monthly-disbursement register and batch-detail view in the Flutter app. The new authenticated `GET api/v1/admin/disbursements.php` endpoint supports optional month/status/search filters, paginated batches, and paginated family items. It authorizes only the exact persisted `admin` role, follows the existing `modules/accounting/disbursements.php` query fields/statuses, and adds no schema changes or financial mutations. The admin dashboard now labels Chart of Accounts and the read-only disbursement register as available in-app; no external browser handoff was added.

At source revision `dea8c633ef446690f6ec91bc7200d859959b5135`, Flutter analysis and all widget tests passed in [workflow 40278814068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40278814068); Android debug APK build/publication is still pending at this checkpoint. The separate PHP lint job passed its configured file list, but this new endpoint is not yet in that list; do not claim endpoint PHP lint or live/full-schema query verification. Local XAMPP/API/database and physical-device acceptance remain open. Disbursement actions (create/transfer/confirm/return/reopen/void) and native Fina collection are not implemented by this read-only slice; mobile role parity remains incomplete.


## 2026-10-26 — Native Fina collection workflow and exact web-role guards

The native Finance Manager tools now expose Fina collection registration plus pending review/history. The create page is permitted by the existing web guard only for persisted roles `admin`, `financial_manager`, and `fm`; the review page allows persisted `admin`, `financial_manager`, `fm`, `supervisor`, and `vice_general_manager`, with review actions only for the existing finance reviewer roles. `sudo`/`super_admin` normalization must not silently grant Fina access, and the `finance` alias is not permitted by the actual Fina page guard. The Fina API now checks `raw_role_code` against those exact page guards, and the mobile create-page gate/quick-tool visibility matches them. Receipt access follows the existing Fina dashboard/review visibility instead of granting unrelated accountant roles.

Disposable MariaDB API integration run [40289622000](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40289622000) passed collection authentication/validation, pending creation without journal, review queue visibility, and creator self-approval denial. The subsequent exact-role correction is in commit `0e037b009dc9eb49385f79097a16975df5066b5e`; its role-denial integration and current Flutter widget/build checks are pending in [run 40290143225](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40290143225) and [run 40290143259](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40290143259). No local XAMPP/API or physical-device acceptance is claimed.

Correction — 2026-10-26: Native monthly-disbursement actions, beneficiary/orphan forms, and HR/payroll management have since been implemented and verified in later checkpoints below. **Still open:** remaining role-specific finance/HR workflows and reports, local XAMPP/API/database verification, and physical-device acceptance. Do not describe mobile role parity as complete.


## 2026-10-26 — Native read-only database browser (verification pending)

The administrator dashboard's native read-only database browser uses exact persisted `raw_role_code === admin`, validates table names against `information_schema`, performs no DDL or writes, limits each result to 50 rows, and sanitizes binary/oversized values. The authenticated native backup API creates a real SQL dump, delivers it as binary, and supports deletion. Disposable MariaDB integration passed these database/backup operations and nanny denial in [run 40291447241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40291447241). Flutter analysis, widget tests, APK build and publication remain pending at [run 40291627186](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40291627186).

Correction — 2026-10-26: Native database backup is implemented and has passed disposable MariaDB integration, as documented above. Do not claim full mobile parity: other role-specific finance/HR workflows and reports remain open; local XAMPP/API/database and physical-device acceptance remain separate gates.


## 2026-10-26 — Native Fina dashboard summary

Added a native read-only Fina dashboard for the exact persisted roles allowed by `modules/accounting/fina_dashboard.php`: `financial_manager`, `fm`, `admin`, `general_manager`, `vice_general_manager`, and `supervisor`. `GET api/v1/fina/dashboard.php` reports pending, approved, returned, approved amount, and liability-account configuration without creating an account or mutating accounting data on GET. Native review navigation appears only for roles accepted by the existing review page. The administrator dashboard now exposes separate native Fina overview and collection-registration entries; the Finance Manager quick tools retain overview, registration and review. Integration and widget/build verification are pending in the workflows triggered by the latest source commit.


## 2026-10-26 — Native administrator database backup workflow — API integration PASS

The admin-only Database backup entry now has a native screen for listing backup files, creating a SQL dump through the existing server-side mysqldump capability, downloading a selected file through authenticated binary delivery, and deleting a selected backup with confirmation. `GET/POST api/v1/admin/backups.php` checks exact persisted role `admin`, accepts only generated/legacy `backup_*.sql` filenames, uses shell-escaped DB configuration, writes into the already-protected `storage/backups` directory, and preserves the existing EXPORT/DELETE audit-log events. No schema changes or runtime DDL. The app uses file_picker's save-file flow so users can choose where the SQL file is saved on their device. Integration run [40291131451](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40291131451) passed: the disposable CI database backup was created, downloaded through authenticated binary delivery, deleted, and checked absent; nanny list/create requests were denied. Flutter analyzer/tests/APK build remain pending because a malformed duplicated tail in main.dart was removed in commit `7d89192600db7236f7700995c9f14a9f20cc7bd3` and must be rechecked by [run 40291303032](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40291303032).


## 2026-10-26 — Native report detail screens (verification pending)

Added `GET api/v1/reports/detail.php` and a shared native detail page for the existing report registry's financial, sponsorship, operational, and HR report families. The endpoint re-authenticates every request, canonicalizes only the repository's existing role aliases, checks the exact report registry before reading data, validates the date range, rejects unsupported report types, uses bound parameters for user-supplied dates, and returns summary cards plus rows for a native table. The detail screen supports report-type selection and date filtering and does not open a website. `api/v1/reports/overview.php` remains the source of the role-specific catalog, and the uncovered-families report keeps its existing dedicated screen.

Source commit `820d1dfb440d0fc81619e43b0e6bf9a0270c68eb`; follow-up Dart syntax/analyzer cleanup `cfa745109f39c58ab1979c40583108d352c26b5e`, `7fcf8e923a4b56925c5c0050b170d38eb8dc8e46`, and `9ab66cd9ff69ceb1553cb69f2d91e066cc4cd34c`. Disposable API coverage for all four report families is in `084cf2226145a7fc7e09369846343eb1b0251297`. Verification update: PHP lint passed in workflow `40298607484`; Flutter analysis, widget tests, Android debug APK build, and artifact publication passed in run `40298607464` for app source `9ab66cd9ff69ceb1553cb69f2d91e066cc4cd34c`. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (run URL: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40298607464). Disposable MariaDB API integration passed in run `40298934944`, including all four report families, reversed-date rejection, backup creation/list/download/delete, and role-denial checks. Local XAMPP/database and physical-device acceptance remain separate gates.


## 2026-10-26 — Native parity correction: disbursements and attendance policy

The earlier rows above are corrected by the current source:
- Monthly disbursements are no longer a read-only register. `api/v1/admin/disbursements.php` and `api/v1/admin/disbursements_action.php` expose role-specific actions for admin, financial manager, GM, accountant staff and nanny; nanny batches are scoped to the authenticated nanny, and accountant-staff scope is restricted to assigned nannies. The existing web page's role/action boundary remains authoritative.
- Disposable MariaDB integration passed create/duplicate protection, transfer receipt + balanced journal, item reopen, batch reopen, journal reversal/void, nanny own-batch scope, and nanny return receipt in [run 40310902190](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40310902190). Flutter action coverage is in `mobile/ahl_el_kheir_app/test/admin_disbursements_page_test.dart`.
- Native attendance-policy management is available in `mobile/ahl_el_kheir_app/lib/attendance_policy_page.dart` backed by `api/v1/hr/attendance_policy.php`. The API uses exact persisted-role checks and mirrors existing effective-date/attendance-lock rules. Integration in run 40310902190 passed create/read/update, invalid time-order rejection, delete, and nanny denial.
- The Android API default had regressed to port 8081. Commits `486912b34bedc580584022653aa0d2ec7ec11cd7` and `4952889a754138467d8af61077090e879096f028` restore the phone-facing default/workflow fallback to port 881. Run [40314527960](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40314527960) passed Dart analysis, the full Flutter widget suite, Android debug APK build and artifact publication for source `4952889a754138467d8af61077090e879096f028`. Published artifact `ahl-el-kheir-flutter-android-debug-apk` is 102,203,303 bytes and expires 2026-11-25.
- Local XAMPP/API/database and physical-device acceptance remain open. Native payroll operations and remaining role-specific workflows are still incomplete; no external-browser handoff is permitted.

## 2026-10-26 — Native HR payroll management implemented; verification in progress

- The native payroll management API and Flutter screen now cover payroll periods, generation/draft refresh, draft amount editing, approval, active payment account assignment, private proof upload and in-app preview, legacy evidence, and explicit proof-backed transfer confirmation.
- API integration/PHP lint passed in [run 40318619066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318619066) and [run 40318633490](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318633490). These runs verified proof upload is not payment, role denial for prepared-proof viewing, binary private proof streaming, transactional balanced accounting with actor attribution, duplicate confirmation protection, and legacy evidence immutability.
- Flutter analysis and all 105 widget tests, Android debug APK build and publication passed in [run 40318833316](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318833316) for source `339440d1747abdf4388188bcb8809cead65c3df7`. Artifact ID `12647679265` is 102,218,468 bytes and expires 2026-11-25; uploaded ZIP SHA-256 `c30a9d0fe19579c72ebc25cfd75f027d37dc2553f82631b0d81b03e2bc9916f0`. Expanded draft-edit/approval API tests passed in [run 40319313245](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40319313245).
- Native role parity remains incomplete. Local XAMPP/API/database and physical-device acceptance have not been verified. No external-browser handoff is allowed.



## 2026-10-26 — Distinct native visual identity and next parity item

The user explicitly requested that the mobile app's general theme, style, and colors differ from the original web system. The app-wide Flutter theme and prominent shared native surfaces were updated to a distinct deep-teal (`#115E59`), bright-teal (`#20A39E`), coral (`#E07A5F`), slate, and cool-light palette. This intentionally does not mirror the website's navy/blue styling. Arabic RTL, English LTR, Cairo font, organization identity, and server-side role checks are preserved. The palette refresh's Flutter analysis/widget/build CI must be confirmed; no local/device visual acceptance is claimed.

**Next parity priority:** replace the home-screen `Global search (WEB)` placeholder with native search. The authoritative scope matrix is `config/search_permissions.php::ak_search_allowed_types()` / `ak_search_can_type()`, and record-level visibility rules live in `modules/search/index.php`. The native API must not widen roles or record visibility, must use the actual schema, and must never perform a browser handoff. Search types without a safe native implementation must be explicitly disabled rather than queried speculatively.


## 2026-10-26 — Native search center (initial supported scopes)

The home-screen search icon now opens a native search center for families, sponsors, and sponsorships. It calls the existing authenticated endpoints and deliberately shows only scopes supported by those endpoints for the current role, so it does not broaden access based on the broader web search matrix. Search results open native family/sponsor/sponsorship detail screens. Monthly disbursement and archived project-document search remain incomplete in the mobile search center; do not describe global search as full parity until those domains and the broader config/search_permissions.php matrix are implemented with equivalent record-level visibility.


## 2026-10-26 — Native global search: monthly disbursement scope added

- Added a native monthly-disbursement search category using the existing authenticated `api/v1/admin/disbursements.php` endpoint. The screen only exposes this category for persisted roles accepted by that endpoint: `admin`, `financial_manager`, `general_manager`, `accountant_staff`, and `nanny`. It deliberately checks the persisted role for this endpoint rather than assuming all normalized aliases are accepted.
- Selecting a result opens the existing native monthly-disbursement register with the exact batch ID prefilled as the query; it does not open a browser or create a second financial-data path.
- Added widget coverage for Finance Manager visibility and native result routing. Dart analysis and the full widget suite passed in workflow [40325180234](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40325180234) at app source `17feccacfa312ba1982fdb0e0549fce1809c42d7`. Android APK build/publication was still running at the time of this documentation update; do not claim artifact publication until that run completes.
- Still open: archived project-document search/preview and broader role parity with `config/search_permissions.php`. Local XAMPP/API/database and physical-device acceptance remain unverified.

## 2026-10-26 — API connection regression and archive search checkpoint

The app/workflow default had regressed to port 881, and startup migration changed the known correct `192.168.18.9:8081` endpoint back to 881. Commit `da04d0fea8e73f505fa521546e653ca4e263e0c0` restores 8081 and updates diagnostic tests. Automated verification for this correction is pending; physical-device connectivity remains unverified.

Native global search currently includes families, sponsors, sponsorships, role-limited monthly disbursements, and archived project documents with in-app private file retrieval. Archive search/streaming integration passed in run `40335923994`. Do not call search full web parity: remaining role/category visibility parity and other role-specific native workflows are still open. No external browser handoffs. Current app visual identity is violet/amber with lavender surfaces, intentionally different from the original web palette.

## 2026-10-26 — Vice General Manager native disbursement read access

Source audit confirmed the existing web monthly-disbursement page allows `vice_general_manager` to view the register, while the native GET API omitted that role. Commit `1fb5efe34b85a6f484c50e7420e4218b74e7031c` adds VGM to the read endpoint and native global-search category only. All mutation capabilities remain false for VGM, and action endpoints were not widened. Added a widget test for VGM search visibility, disposable-MariaDB integration asserting read access and no create/transfer/void/reopen/confirm capabilities, and explicit PHP lint for the endpoint. CI is pending; no device acceptance is claimed.


## 2026-10-26 — Mobile API error traced to stale APK artifact

The user's reported login error was investigated against the actual current source and the APK previously supplied. The APK link previously provided pointed to workflow run `40318833316`, built from source commit `339440d1747abdf4388188bcb8809cead65c3df7`. The current `main` was already 95 commits ahead at `4a628f789d717273517a6fd42ef2bb6d34673a56`; comparing those refs shows `mobile/ahl_el_kheir_app/lib/main.dart` changed by 427 lines (362 additions, 65 deletions), and the older artifact source lacks `probeApiEndpoint`, `migrateKnownLegacyApiBaseUrl`, and the current host/port-specific connection diagnostics. Therefore the previous device test used a stale APK; it did not test the then-current source. This was an artifact handoff mistake, not evidence that the current source's API fix failed.

Corrective changes now on `main`:
- `c04ebc24e18aea00f75df6598b312e455bea0e48`: Flutter APK build embeds `MOBILE_BUILD_REVISION=$GITHUB_SHA` and publishes `mobile-build-info.txt` alongside the APK.
- `5216d8f8230688935c6eaeccbc20178d8a6b0d1c`: login screen displays the shortened source revision so a stale installation can be identified before testing.
- `a24cbdd88a0b4a112bbb4fc631337e1c44f08942` and `df18877c5a99c4c12ad5dc2dc59cade622e1f64d`: update API diagnostic regression tests and expected build label.

The source still defaults to `http://192.168.18.9:8081/AhlElKheir/api/v1` and migrates only the exact previously saved `192.168.18.9:881/AhlElKheir/api/v1` URL. The build/provenance workflow and login revision indicator are committed; CI analysis, tests, APK publication, and actual phone connectivity for this new head must be checked before sending another APK for acceptance. Do not ask the user to retest the stale artifact. No external-browser handoff is permitted. The app intentionally uses a distinct violet/amber/pink visual identity rather than copying the web palette.


## 2026-10-26 — Native account password settings and forced-change flow

The mobile app now includes a native Account Settings screen for changing a signed-in user's password, plus an in-app forced-password-change dialog when login returns the existing `password_change_required` response. The app retries login with the new password after the server confirms the forced change; it does not direct the user to the website.

Backend additions on `main`:
- `api/v1/auth/change_password.php`: bearer-authenticated self-service password change; checks the current password, enforces the existing six-character minimum and matching confirmation, updates `password_hash` and clears the required flag, and preserves the existing approved-recovery completion and audit behavior. It performs no DDL.
- `api/v1/auth/complete_forced_password_change.php`: pre-login change path restricted to active accounts that are actually flagged for a forced change; requires username and current password, validates the new password, clears the required flag, and records the existing audit/recovery side effects without issuing a session or token. It performs no DDL.
- `api/v1/auth/login.php` now describes the forced change as an in-app step rather than a website requirement.

Native UI: `mobile/ahl_el_kheir_app/lib/account_settings_page.dart`; account-menu routing and login retry are in `main.dart`. Widget tests cover normal password-change submission and local mismatch validation. The disposable API integration harness now covers invalid current password, mismatched confirmation, successful hash update, old-password rejection, new-password login, forced-change completion and flag clearing, and cleanup of test audit/recovery/session state. The API workflow explicitly lints both new endpoints and the login endpoint. CI results for the newest source must be checked before claiming these new paths verified; no local XAMPP/device result is claimed.

Remaining account parity gaps are still open: personal profile editing/photo management and password-recovery request are not yet native. Do not label the account area fully complete. No external browser handoff is allowed. The overall app uses a distinct violet/amber/pink palette and should not be changed to copy the original web theme.


## 2026-10-26 — Native password recovery and accounting journal workflows added

Further native parity work has been committed on `main` after the stale-APK/API diagnosis and account password work.

### Password recovery
- `api/v1/auth/password_recovery_request.php` creates a pending request for an active matching username/email, avoids duplicate pending requests, and notifies active `admin`/`hr_manager` users best-effort. The response is identical for known and unknown accounts to avoid account enumeration. No DDL is performed.
- `mobile/ahl_el_kheir_app/lib/password_recovery_page.dart` replaces the login screen's former unavailable native workflow; the app does not open the website.
- API integration coverage checks request creation, duplicate protection, and the same generic response for unknown identities. The fixture records and removes the test request and its recovery notifications.

### Accounting journal
- `api/v1/accounting/journal.php` now supports role-protected read/list/detail, date filters, active-account listing for authorized creators, manual entry creation with active-account validation, unique account lines, decimal precision checks, balanced debit/credit totals, transactional persistence and a journal-numbering lock.
- Manual voiding follows the existing web workflow's restrictions: only admin/Financial Manager, no automated/reversal entries, no creator self-void, no duplicate reversal, and a balanced reversing entry plus preserved original void metadata. No runtime DDL is used.
- Native screens: `journal_page.dart` and `journal_manual_entry_page.dart`; role tools now route the journal inside Flutter. API integration coverage includes role denial, invalid date filters, detail/list, unbalanced-create rollback, successful manual create, creator self-void denial, different-reviewer void, balanced reversal, duplicate-void rejection, and cleanup. Widget tests cover list/detail, manual entry validation/submission, and reviewer void UI.

These changes are committed and the workflows are configured to run, but the current GitHub Actions outcomes have not been independently retrieved in this session. Do not claim these newest paths passed CI until the actual run results are checked. No local XAMPP or physical-device result is claimed. The mobile app remains unfinished overall: full role-by-role parity, profile/photo editing, and other remaining web workflows still require audit and implementation. Keep the distinct violet/amber/pink app design; do not copy the original web theme or introduce browser handoffs.


## Native voucher issue/void workflow — 2026-10-26

- The mobile voucher API now supports authorized receipt/payment issuance and voucher voiding, using the existing `modules/accounting/lib_vouchers.php` numbering lock and role allowlists. The API also returns active revenue/expense account choices for the native form.
- Issuance preserves the web rules: non-future date, positive bounded amount, cash account restricted to the existing cash/bank/wallet codes, active revenue counter-account for receipts or expense counter-account for payments, sufficient cash balance for payments, two balanced journal lines, immediate posting, and best-effort audit logging in the same transaction.
- Voiding preserves the existing posted original entry and creates a balanced reversing entry, checks linked-entry integrity and duplicate reversal, and requires the selected cash balance to cover a receipt reversal. It does not delete accounting history or use runtime DDL.
- The disposable MariaDB integration harness now exercises issuance, balanced posting, void reason validation, balanced reversal, duplicate-void rejection, role denial, and cleanup. Correction: backend integration passed in run 40366312607; Flutter analysis, 138 widget tests, and APK publication passed in run 40369415336. Current main's test-only follow-up also passed analysis/tests in run 40369875925; its redundant APK build was still running at this documentation point. No local XAMPP or physical-device result is claimed.

## 2026-10-26 — Native receipt/payment voucher actions

- Native Flutter voucher register now supports authorized receipt/payment issuance and authorized voiding with a required reason. It uses the existing authenticated api/v1/accounting/vouchers.php endpoint; the server remains authoritative for role access, dates, amount bounds, eligible cash and counter-accounts, payment balance, posting, reversal integrity, duplicate void prevention, and audit records.
- Issuance form sends the server-defined payload (action=issue_voucher, voucher type/date/amount, cash account, counter-account, party, description, optional reference). The void dialog owns its TextEditingController and disposes it with its own widget state; this was corrected after CI exposed premature disposal during the dialog exit transition.
- Disposable MariaDB API integration passed in run 40366312607: issuance, balanced posting, void reason validation, balanced reversal, duplicate-void rejection, role denial, and cleanup.
- Flutter analysis and all 138 widget tests passed, including issue and void interaction tests, in run 40369415336. The Android debug APK built and was published in the same run from app-source commit 69bb50391c3509906279e526f4a3a12aed2a25fd; artifact ahl-el-kheir-flutter-android-debug-apk, ID 12672886503, 102,394,809 bytes, expires 2026-11-25. Workflow: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40369415336
- Current main test-only follow-up is 39f8c20baade5d7209c6928da74edaed51443633; its Flutter analysis and all widget tests also passed in run 40369875925. That run's redundant APK build is still in progress at this documentation point. App Dart source is unchanged from the successful APK build above.
- No local XAMPP/API/database or physical-device acceptance is claimed. Do not ask the user to test until the latest workflow result is checked and final pull/install instructions are ready.

## 2026-10-27 — Native GM reconciliation and sponsor win-back

The following role-tool paths are now native and have been verified against the existing web permission boundaries:

- `modules/accounting/gm_reconciliation.php`: native read-only reconciliation screen for admin, General Manager, Vice General Manager, and Financial Manager. The existing web report registry denies Accountant access; the native tool list and API preserve that denial.
- `modules/administration/winback.php`: native sponsor win-back screen for admin, General Manager, Vice General Manager, and Administration. The API denies Accountant access and preserves the existing eligible-sponsor, contact, decline/reopen, and reactivation workflow.

API/disposable-MariaDB integration passed in run `40452694704`, including queue eligibility, duplicate-open rejection, contact logging, case detail, decline/reopen, successful reactivation attribution, and duplicate-return rejection. Flutter analysis, 141 tests, Android debug APK build, and artifact publication passed in run `40452694443`. App source revision `d6cab6772b3e2683434a1bb927d62ec55c5be9c6`; artifact ID `12710904687`; SHA-256 `eaf1ecc57262b1a71329dbcdb85dfb23121d478912a81013b6d3225df3e71fd8`.

No local XAMPP/API or physical-device result is claimed. Incentive Policy remains a separate open native workflow and must be implemented only after auditing its current versioning, approval, activation, and protected active R5 rules.


## 2026-10-27 — Incentive Policy native route verified; GM waiver is next

The earlier entry listing Incentive Policy as an unimplemented native gap is superseded for native-screen/CI status by the following evidence:
- `api/v1/hr/incentive_policy.php` and `mobile/ahl_el_kheir_app/lib/incentive_policy_page.dart` now back the native workflow.
- HR Manager, General Manager, and Financial Manager actions remain separated by the existing server-side permission boundary.
- Flutter workflow [40455075748](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40455075748), run #605, passed analysis, widget tests, Android debug APK build, provenance, and artifact publication at revision `729030ff00536cb567946ef41a73ddb6ea49feaa`.
- Latest main head `7e68238bb506f08779e4055b91d01cb9981f8d3a` also passed the Salary Advance Verification and Mobile API Auth Integration workflows.

This is **native route/build verification only**. It does not close the broader employee-incentives module's previously documented real-browser acceptance gaps, and it is not permission to alter active R5 or historical incentive/accounting/payroll evidence. No local XAMPP or physical-device acceptance is claimed.

Next audited native gap: **GM Salary Advance Waiver**. Implement only after mapping the existing FM preparation → GM approve/reject → FM execution flow, role guards, decision/item/schedule tables, journal behavior, notifications, and payroll interaction. The existing waiver implementation and its closed acceptance boundary must be reused; do not re-run closed financial harnesses or change business semantics merely to support the mobile UI.


## 2026-10-27 — GM Salary Advance Waiver native implementation submitted for CI

The native layer now reuses the existing waiver business service rather than reimplementing financial behavior:
- Authenticated API: `api/v1/hr/salary_advance_waiver.php`.
- Flutter page: `mobile/ahl_el_kheir_app/lib/salary_advance_waiver_page.dart`.
- Existing GM and FM role-tool paths are marked IN APP and route to the same native screen, with separate role-appropriate actions. FM prepares and executes; GM reviews/approves/rejects; server-side role checks remain authoritative.
- No migration, runtime DDL, new accounting rules, or changes to historical waiver logic are included.

Status at this checkpoint: **implementation submitted for CI; not yet verified**. The existing waiver financial acceptance remains closed and must not be rerun without regression evidence. Multiple eligible advances for one employee and true two-process concurrent execution remain explicit evidence gaps. No local XAMPP or physical-device acceptance is claimed.


## 2026-10-27 — Waiver page analyzer correction pending re-verification

The first Flutter run for the new waiver page (#606) failed at Dart analysis with four deprecated `DropdownButtonFormField.value` diagnostics. These were corrected to `initialValue`; the replacement build/test run remains pending. The API-auth workflow #217 and Salary Advance Verification workflow #3530 both passed on the original feature commit; those results do not substitute for the pending Flutter tests/APK build.


## 2026-10-27 — Waiver widget-test failure diagnosis

Flutter run #607 completed with Dart analysis passing, but widget tests failed (148 passed, 2 failed), so APK build/provenance/publication were skipped. The exact failures are in `salary_advance_waiver_page_test.dart`: the GM test tapped the approval action while its card control was outside the test viewport, and the FM test looked for the execution action before scrolling the long preparation form/decision list into view. Source inspection shows the production page places decision cards after the introductory panel and, for FM, the preparation form; these are `ListView` children and later controls may not yet be built by the lazy viewport. The narrow correction is to use `scrollUntilVisible` for the actual GM approval and FM execution controls before tapping/asserting them. No production UI, API, or financial service logic is changed. Await the next sequential Flutter run before claiming test/build success; do not overlap runs or rerun closed financial waiver harnesses.


## 2026-10-27 — Native GM attention queue (first slice)

The GM mobile dashboard now presents actionable attention cards for positive counts already exposed by the role dashboard API: uncovered families, paused sponsorships, open sponsor win-back cases, unassigned supervisor letters, and open sponsor requests. Each card routes to an existing native screen/report through the native-only dispatcher. No web handoff, new API endpoint, schema change, or financial behavior was added. The app's distinct violet/amber/pink theme is preserved.

Known remaining parity gap: the web GM dashboard includes pending families, but the current mobile API response does not include its existing `pending_families` query value in the returned metric list. This first slice intentionally does not infer that count. Add and verify the metric in a separate sequential change before claiming complete attention-queue parity. CI and device acceptance are pending.

## 2026-10-27 — GM pending-family API metric (backend stage)

The existing General Manager SQL already counts `families.status='pending'`, matching `dashboard/gm_dashboard.php`; the gap was that `ak_mobile_dashboard_metrics()` did not include that selected value in its returned metric list. The API now exposes `pending_families` with the web dashboard's Arabic label and an English label. No query semantics, schema, permissions, or financial behavior changed.

The mobile role-dashboard query harness now asserts that the General Manager metric is present exactly once and numeric. The Mobile API Auth Integration workflow runs this harness against its imported disposable MariaDB schema and lints the overview endpoint/service. Flutter attention-card wiring remains the next sequential step; full GM attention parity is not yet complete.

## 2026-10-27 — GM pending-family attention card (Flutter CI pending)

The API-side metric change passed Mobile API Auth Integration run #218 (40469806870), including the role-dashboard query harness against disposable MariaDB and the API syntax checks. The Flutter GM attention queue now consumes 'pending_families' and adds an actionable card using the existing web-dashboard destination 'modules/reports/operational.php'. Its widget test supplies a positive pending-family count and verifies the card routes through the native dispatcher. The Android workflow for this UI follow-up has not yet been verified; local XAMPP/API and physical-device acceptance remain pending.

## 2026-10-27 — Flutter GM attention widget-test selector correction (CI pending)

Android workflow #610 (run ID 40470131963) failed in the new GM attention test, not during Dart analysis or APK compilation. The exact failure was `Bad state: Too many elements` from `tester.ensureVisible(find.text('Families awaiting action'))` because the text finder matched more than one widget. The test now selects the matching `ListTile` ancestor and uses that tile for visibility and tap, avoiding ambiguous text selection. No production app behavior changed in this correction. Await the next sequential Flutter workflow before claiming the test/build passes; do not push another code change until it finishes.


## 2026-10-27 — Native VGM orphan-group management implementation submitted for CI

The next mobile parity slice adds a bearer-authenticated API at `api/v1/deputy_gm/groups.php` and a native Flutter screen at `mobile/ahl_el_kheir_app/lib/orphan_groups_page.dart`. The existing VGM role tool now dispatches to this screen and is marked IN APP in the source. The API authorizes only the canonical Vice General Manager role; the UI role label is not treated as authorization.

The implementation follows the existing `modules/deputy_gm/groups.php` workflow and the checked-in `orphan_groups`, `group_children`, `nanny_group_assignments`, and `monthly_disbursements` schema. It adds no migration, runtime DDL, or financial logic. It preserves group/member/nanny history, blocks member changes when a current-month disbursement exists, requires active-family/active-sponsorship eligibility for additions, keeps the transfer history, and allows permanent deletion only for never-used groups. Group lifecycle actions are restricted to the actions exposed by the existing web UI.

Regression coverage has been added for VGM-only API access, list/detail, draft creation/edit, rejecting activation of an empty group, deleting a never-used draft, the native role-tool route, group/member display, and the current-month disbursement UI lock.

**CI status (2026-10-27):** Flutter APK workflow #615 passed Dart analysis, Flutter tests, and debug APK build on commit `bb929687b16cbec1fa66d2fe8b39adb7e73d9d90`. Mobile API auth integration #221, salary advance verification #3534, and Sponsor QR Scanner APK #214 passed on preceding commit `6e82f4b36c79c9cf47737a9a94a44fb7a423ac9b`; those workflows were not rerun by the Flutter-only follow-up commit. CI does not establish local XAMPP/API or physical-device acceptance. The existing group workflow and financial acceptance boundaries have not been changed.


## 2026-10-27 — Native VGM group screen fixes verified in Flutter CI

The initial VGM group screen CI run (#612) exposed three test failures. The first focused correction moved group create/edit text controllers into the dialog-owned StatefulWidget lifecycle and corrected the ambiguous current-members label matcher. The repaired tree was checked after the follow-up commit to ensure the API endpoint and feature files remained present.

Flutter APK run #614 then narrowed the remaining failure to a stale test assertion: the implementation renders “Member history”, while the test expected “Membership history”. Source review also confirmed a web/mobile parity gap: closed/terminal groups could return current members from the API but the Flutter UI hid the entire member card when member changes were prohibited.

Commit `bb929687b16cbec1fa66d2fe8b39adb7e73d9d90` fixes the assertion and keeps current group members visible in read-only mode. Nanny assignment, member transfer/removal, and add-member controls remain conditional on member editability; member popup actions also remain hidden under the current-month disbursement lock. A widget regression test covers a closed group with a visible historical member and no mutation controls.

**Verification: PASS in Flutter APK workflow #615** — Dart source analysis, Flutter tests, and debug APK build all completed successfully. Earlier commit `6e82f4b36c79c9cf47737a9a94a44fb7a423ac9b` also had Mobile API auth integration #221 PASS, salary advance verification #3534 PASS, and Sponsor QR Scanner APK #214 PASS. Those workflows were not rerun for the Flutter-only follow-up commit. Local XAMPP/API acceptance and physical-device acceptance remain unverified; do not claim them. Next step: inspect the current role-feature parity backlog and proceed with the next uncompleted, evidence-backed mobile feature only after reviewing the current source and schema. Do not repeat closed payroll/HR acceptance suites without regression evidence.


## 2026-10-27 — Notification navigation checkpoint

Notification detail navigation is now independent of mark-as-read success. Supported module links can invoke an existing native app route through the app's existing role-aware router; arbitrary website URLs are not opened. Links to destinations without a native implementation remain a documented parity gap. Workflow #622 passed Dart analysis, all widget tests, Android debug APK build, and artifact publication for `e2e3873cb2a2650f373f155dbdd770bed23e1e83`. No physical-device acceptance is claimed.

Online deployment remains a release gate: the artifact is debug-only and points to the local XAMPP test API. Production must use a verified HTTPS API host and must not expose XAMPP publicly.


## 2026-10-27 follow-up — Notification details only

Removed notification related-page/link actions. Tapping a notification shows its details in the app and does not navigate to the payload URL. Mark-as-read failure remains independent of opening details. Widget coverage verifies no related-page action is displayed. Workflow #623 passed analysis, Flutter tests, Android debug APK build, provenance and artifact publication. Artifact ID `12733593790`, SHA-256 `a2d93c054e2969e639618e0cc568bb27161011a2e05b9c16a6d3a1f920542013`, expires 2026-11-26.


## 2026-10-27 — Native suspension creation and notification-detail verification

- Notification details-only follow-up is verified: workflow [#623](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40503179161) passed Dart analysis, the full Flutter widget-test suite, debug APK build, provenance and artifact publication for source `82a04b7d9daac51b657c6b7cba6c39c2ecb632db`. Artifact ID `12733593790`; archive SHA-256 `a2d93c054e2969e639618e0cc568bb27161011a2e05b9c16a6d3a1f920542013`; expires 2026-11-26.
- Native suspension creation now covers searching families/children, selecting one, choosing one of the web workflow's existing reasons, optional notes, confirmation, duplicate protection, nanny scope checks, and family/child linked sponsorship pausing. Existing case-list/lift behavior remains intact.
- Initial Flutter workflow #625 failed one create-dialog widget test: the dialog overflowed by 22 pixels and its disposed `TextEditingController` was still attached. The source was corrected in commit `1545496e0caaee0371de81373f30f63a45fb4f63` by removing the unsafe controller lifecycle and making the dialogs scrollable.
- Verification: Mobile API auth integration workflow [40507040144](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40507040144) passed PHP lint, the rollback-only full-schema suspension service harness (including generic child/family creation, duplicate and nanny-scope guards), and API integration on source `adaef343c9fa23d53511ab62ba4f1a0b395f686c`. Flutter workflow [#626](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40508054070) passed Dart analysis, all Flutter tests, Android debug APK build, provenance and artifact publication for source `1545496e0caaee0371de81373f30f63a45fb4f63`. Artifact ID `12736527104`; archive SHA-256 `3a3dc777f1ec4ceff90812675d1266f25bff760296063cdcbf6ed3341403389d`; expires 2026-11-26.
- These are CI/disposable-database results only. Local XAMPP/API acceptance and physical-device testing remain for the user. The debug APK still targets the local development API; do not expose XAMPP publicly. Release requires a verified HTTPS API host.



## 2026-10-27 — Native assigned-nanny overview verified

modules/accounting/my_nannies.php now has a native route and Flutter page backed by GET api/v1/accounting/assigned_nannies.php. The page covers the existing month filter, nanny summary, active-family details, contact/bank fields, month-specific verification state/notes, and latest disbursement status. Server authorization matches the web guard exactly (admin, accountant, accountant_staff, financial_manager); Accountant Staff is restricted to nannies assigned through accountant_nanny_assignments. No migration, runtime DDL, or accounting mutation was added.

Verification PASS: API integration #225 / run 40513189589 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40513189589) passed unauthenticated denial, administrator summary/detail, and Accountant Staff scope. Flutter APK #629 / run 40513730679 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40513730679) passed Dart analysis, all widget tests, debug APK build, provenance, and publication. Artifact ID 12737979531, SHA-256 a2c819e4d0cd5afe8edd20b56b42ca33c12c56f78a24aefda151398d1b1fb7ca, expires 2026-11-26.

Local XAMPP/API and physical-device acceptance remain unverified. The artifact targets the local development API and is debug-only. Do not expose XAMPP publicly; online release requires a verified HTTPS API host. Native role parity remains incomplete; continue from current NOT READY tool paths only after reviewing their exact source and guards.
