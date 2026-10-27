# Ahl El Kheir Mobile App — Continuation

Checkpoint: 2026-10-24
Repository: `moneermax/Ahl-El-Kheir-charity-management-system`
Branch: `main`
Status: API authentication foundation source added; local PHP syntax/runtime verification and migration application are still required.

## Product direction

- Build a polished Flutter application for Android and iOS inside this existing repository.
- Preserve the current procedural PHP backend and MariaDB/MySQL database as the source of truth.
- Arabic-first UI with Arabic/English toggle and correct RTL/LTR layout.
- Existing role permissions, record-level scopes, and business workflows remain authoritative. A role returned by login is informational for navigation only; every data endpoint must enforce server-side authorization and existing scope rules.
- Keep XAMPP for local development. InfinityFree Free may block native API requests behind its browser verification layer; do not bypass hosting controls. The API must remain host-portable, and remote mobile testing should use an API-compatible HTTPS host.
- Do not expose XAMPP directly to the public internet.

## Initial API slice

- `api/v1/_bootstrap.php`: JSON response helpers, bounded JSON input, bearer parsing, live account/role lookup, token expiry/revocation checks.
- `api/v1/auth/login.php`: verifies existing username/password hashes, active-account state and password-change requirement; records existing successful-login side effects; issues a random opaque bearer token.
- `api/v1/auth/me.php`: returns the current live account identity and role from the database.
- `api/v1/auth/logout.php`: revokes the current token.
- `database/migrations/2026-10-24_mobile_api_sessions.sql`: dedicated token store; the legacy browser `user_sessions` table remains unchanged.
- Tokens are 32 random bytes; only SHA-256 hashes are stored. Initial access-token lifetime is 12 hours. The client must store tokens in platform secure storage and clear them on sign-out/expiry.

## Important current limits

- No refresh-token flow, password-reset/change API, persistent login throttling, device/session-management UI, or account-wide mobile-session revocation endpoint is included in this first slice.
- The API does not yet expose business-module data. Do not treat a successful login as permission to access any module.
- The mobile API migration has not been applied to any database. Do not run the endpoints until the migration is explicitly applied to the intended development database.
- PHP 8.2 syntax checks for all four API PHP files passed in the existing sponsor-scanner GitHub Actions workflow on commit `20fda29d99c8fe57995aee165f2fa166ded1e2c6`. Local runtime requests, token expiry/revocation tests, mobile app builds, and remote-host compatibility remain unverified.
- Review token lifetime and brute-force/rate-limiting controls before production deployment.

## Local verification plan

1. Pull `main` and run PHP 8.2 syntax checks on the four new API PHP files.
2. Review and apply the migration manually to the local `ahl_el_kheir` database only after taking the normal development backup and confirming the target DB.
3. Test login success/failure, inactive account, password-change-required account, `/me`, logout revocation, expired token, changed role/disabled account, and malformed/oversized JSON.
4. Confirm no raw token is stored in the database and no API request creates or alters schema.
5. Confirm the website browser login/session still works unchanged.
6. After local verification, build the Flutter shell and connect only to these verified API routes.

## API routes in this slice

- `POST /api/v1/auth/login.php` JSON body: `{"username":"…","password":"…"}`
- `GET /api/v1/auth/me.php` header: `Authorization: Bearer <access_token>`
- `POST /api/v1/auth/logout.php` header: `Authorization: Bearer <access_token>`

Responses use `{"ok":true,"data":...}` or `{"ok":false,"error":{"code":"…","message":"…"}}`. Always use HTTPS outside local development.

## Flutter foundation added (2026-10-24)

- Added `mobile/ahl_el_kheir_app/` with a Flutter manifest, a minimal Arabic-first login screen, Arabic/English toggle, RTL/LTR direction, secure bearer-token storage, session restoration, logout, and a minimal signed-in screen.
- The client targets the existing `api/v1/auth/login.php`, `me.php`, and `logout.php` routes. It does not expose business data or make client-side role checks an authorization boundary.
- A temporary API/network failure during session restoration now preserves the token; explicit 401/403 responses clear it.
- The source has not yet been compiled, analyzed, tested, or run on Android/iOS. Flutter platform scaffolding must be generated locally and dependencies resolved.
- Canonical master documents were restored from parent commit `8193a2b32443482cd26fe36c8f1123d8bc374292` after a previous edit accidentally truncated them. Their historical contents are preserved.



## GitHub Actions Flutter Android build (2026-10-24)

- Added `.github/workflows/build-flutter-mobile-app.yml` on `main`. It provisions Flutter/Java on a GitHub-hosted runner, generates Android platform scaffolding in the runner workspace (without overwriting the PHP app or the checked-in Dart source), runs `flutter pub get`, `flutter analyze`, the widget smoke test, and builds an Android debug APK artifact.
- Artifact name: `ahl-el-kheir-flutter-android-debug-apk`; retention is 30 days.
- The workflow runs for relevant pushes to `main` and supports manual dispatch with an `api_base_url` input. The default is the Android emulator URL `http://10.0.2.2:8081/AhlElKheir/api/v1`; physical-phone testing requires the computer's reachable LAN URL, while hosted APIs must use HTTPS.
- Cleartext HTTP is enabled only in the generated debug manifest for local testing. Do not make an HTTP release build or expose XAMPP publicly.
- Added `mobile/ahl_el_kheir_app/test/login_screen_test.dart` as a minimal Arabic login-screen smoke test.
- Initial source commits: workflow `a523843ef93912d8759b4ba6174ba2c6680968ca`, smoke test `55a6daa1aa6cb3251e92a7193192801a1bb086ab`; workflow host configurability update `848e819729a10545470648757ffab32bbcdfd90c`.

**Build status at documentation time: PENDING GitHub Actions completion.** Do not claim the APK compiled until the matching workflow run succeeds and its artifact is present. APK build success does not prove API runtime/authentication. The mobile API migration has not been applied and API security/runtime acceptance remains pending.


## Flutter widget-test correction (2026-10-24)

- GitHub Actions run 40060794073 confirmed dependency resolution and Dart analysis passed, but the widget test failed before APK build.
- The failed test launched the entire AhlElKheirApp and immediately expected the login form. App startup first performs asynchronous FlutterSecureStorage session restoration, so that UI test was coupled to a platform-storage startup path it did not need to validate.
- Corrected mobile/ahl_el_kheir_app/test/login_screen_test.dart to mount the actual LoginPage directly inside MaterialApp with RTL directionality. This isolates the test to the Arabic login form without changing production behavior just to satisfy a test.
- Correction commit: b3216456948e71a80ae2629961efc78352769edf. The subsequent GitHub Actions run must pass the test and publish the APK before build success can be claimed. API runtime/authentication remains unverified.


## 2026-10-24 — Physical-phone API URL and original branding correction

- Historical correction: this checkpoint incorrectly recorded port `881`. Later direct evidence in this document confirmed Apache listens on port `8081`, port `881` has no listener, and the phone browser receives the expected JSON HTTP 405 from `http://192.168.18.9:8081/AhlElKheir/api/v1/auth/login.php`. Current app/workflow defaults are port `8081`; commit `da04d0fea8e73f505fa521546e653ca4e263e0c0` also fixes the subsequent regression that rewrote saved port-8081 URLs to 881.
- Reworked the Flutter login and signed-in shell to use the website's navy palette (`#0A1F44`, `#1F4E8C`), Arabic RTL-first branded card layout, improved field/button styling, and the actual organization logo source. The build workflow copies the existing website asset `assets/img/logo.jpg` into Flutter assets before dependency resolution/tests/build; it does not create a replacement logo.
- The API URL alone does not prove connectivity or successful login. Phone and PC must share the network, Windows Firewall must permit Apache on port 8081, and the URL must be reachable from the phone. The user later confirmed the `mobile_api_sessions` migration was applied to `ahl_el_kheir`; do not repeat it. Physical-device acceptance of the latest APK remains open.
- Verification status: source change committed; GitHub Actions analysis/test/build and artifact upload pending. Physical-device UI and HTTP checks remain pending.


### Flutter theme build follow-up — analyzer correction
The first branding build run `40062960757` stopped at `flutter analyze`: Flutter 3.47 reports `Color.withOpacity` as deprecated and this repository's analyzer treats infos as a failing result. Replaced the four new calls with `withValues(alpha: ...)` in commit `6dbb17b7f4276eeeaad2e3f6c8c267d939ef35f6`. Follow-up build on that commit is pending. This was a source compatibility issue; no API/network conclusion can be drawn from that run.

## Local mobile API reachability checkpoint (2026-10-24)

- Confirmed Apache listens on HTTP port 8081 and HTTPS port 4433; port 881 has no listener.
- Confirmed the API login endpoint returns the expected JSON HTTP 405 for GET requests through both `http://127.0.0.1:8081/AhlElKheir/api/v1/auth/login.php` and `http://192.168.18.9:8081/AhlElKheir/api/v1/auth/login.php`.
- Confirmed the same expected JSON HTTP 405 from the phone browser at `http://192.168.18.9:8081/AhlElKheir/api/v1/auth/login.php`. This verifies phone-to-laptop HTTP reachability and PHP endpoint routing, not login/database readiness.
- Updated the Flutter source fallback and GitHub Actions workflow defaults to `http://192.168.18.9:8081/AhlElKheir/api/v1`. The previous default used port 881, where no listener exists.
- Next: wait for the Flutter GitHub Actions build triggered by the source/workflow changes, download its APK artifact, install it, then test login only after confirming the `mobile_api_sessions` migration is applied to the intended development database and the API auth flow is runtime-ready. Do not use real credentials before that gate.
- Local development HTTP is only for the trusted local Wi-Fi network. Do not expose XAMPP publicly; use a verified HTTPS API host for remote use.



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


## 2026-10-24 — Transaction register CI layout correction

- GitHub Actions run `40086755903` passed `flutter analyze` and 10 widget tests, then failed the transaction-register test because the amount text and status chip together overflowed the trailing `ListTile` area by 8 pixels.
- Commit `885ef809b431b174541d54c8c44ade3f97421172` moves the amount into the transaction subtitle and leaves the status chip in the trailing slot. A new Flutter workflow run is queued; verify its test and APK steps before treating this correction as passed.
- The transaction register is read-only. No approve/return/cancel/void or receipt-file actions are exposed in the mobile client. Live API/database and role-scope runtime tests remain outstanding.


## 2026-10-24 — Transaction register CI passed; sponsorship register added

- Flutter run `40091698003` for commit `885ef809b431b174541d54c8c44ade3f97421172` completed successfully; Dart analysis, all widget tests, debug APK build and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` was present and unexpired at verification time. This verifies that code checkpoint only, before the sponsorship screen was added.
- Added read-only authenticated `GET /api/v1/sponsorships/index.php` and an Arabic-first/English Flutter sponsorship register with the same search-field/status filters, active count/monthly summary, paging, and supervisor sponsor-scope helper as the existing web list. The web module's actual role allowlist is preserved. No sponsorship create/pause/cancel actions are exposed.
- Sponsorship screen widget test and PHP lint inclusion have been added. A newer Flutter workflow is running for the screen integration; wait for analysis, widget tests, APK build and artifact publication before marking it passed.
- The sponsorship endpoint follows the actual `modules/sponsorships/index.php` SELECT and workflow semantics. API/database runtime and account role-matrix testing remain unverified.


## 2026-10-24 — Transaction register CI passed; sponsorship register added

- Flutter run `40091698003` for commit `885ef809b431b174541d54c8c44ade3f97421172` completed successfully; Dart analysis, all widget tests, debug APK build and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk` was present and unexpired at verification time. This verifies that code checkpoint only, before the sponsorship screen was added.
- Added read-only authenticated `GET /api/v1/sponsorships/index.php` and an Arabic-first/English Flutter sponsorship register with the existing web list's search-field/status filters, active count/monthly summary, paging, and supervisor sponsor-scope helper. The web module's actual role allowlist is preserved. No sponsorship create/pause/cancel actions are exposed.
- Sponsorship screen widget test and PHP lint inclusion have been added. A newer Flutter workflow is running for the screen integration; wait for analysis, widget tests, APK build and artifact publication before marking it passed.
- API/database runtime and account role-matrix testing remain unverified.


## 2026-10-24 — Mobile sponsor request register added

- Added authenticated read-only `GET /api/v1/sponsor_requests/index.php?q=&status=&page=1`. The endpoint follows the role allowlist in `modules/sponsors/requests.php` (`admin`, `vice_general_manager`, `general_manager`, `administration`, `staff`, `social_media`) and returns paginated request data with search and status filtering. It exposes no create, delete, conversion, reopen, or status-changing action.
- Added an Arabic-first/English Flutter sponsor-request register with search, status filtering, refresh and paging, then connected it to the signed-in home screen only for the same authorized role set.
- Added a widget test for Arabic request/status rendering and included the PHP endpoint in the PHP 8.2 lint list. No schema migration or runtime DDL was added.
- Relevant commits: API `25e53e3b83482cf9e1964c313d42680f73abcfb1`; screen `24dca15bcee28b37e9a655f95582b4757197df7a`; widget test `6e8dc40f56489290cb8c7c3a4f0da84087b51528`; app navigation `e3ad346c1267736b4e725805e0c877e524e8f2ac`; PHP lint inclusion `66d15c6661ea153a0a61041e8cab439ee0744f63`.
- **Verification status at this checkpoint:** Flutter workflow run `40093088822` passed analysis, widget tests, APK build and artifact publication; PHP lint passed in run `40093091580`. See the dated verified checkpoint at the end of this document. Do not call the new screen CI-verified until Flutter analysis, all widget tests, APK build and artifact publication pass, and the new endpoint's PHP lint step passes.
- The PHP workflow's independent Stage 5 payroll integration job has been failing on earlier commits with `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` The PHP lint job itself passed on the prior run, but this unrelated closed salary-advance workflow failure must be investigated separately and must not be attributed to the mobile sponsor-request change or silently reopened as part of it.
- No live Apache/MariaDB endpoint or role-account runtime test has been run for this new register. Do not ask for physical-device testing yet; continue building and verifying the remaining app on GitHub first.


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
- The earlier portfolio search checkpoint is now fully CI-verified: Dart analysis, Flutter tests, Android debug APK build and artifact publication passed; paired PHP lint passed. This does not replace live Apache/MariaDB API role testing or physical-device acceptance.


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

- Commit [bc39b23fdd036298f64ed80a3b09e2bb906c9653](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/bc39b23fdd036298f64ed80a3b09e2bb906c9653) aligned `api/v1/sponsorships/detail.php` with the sponsorship list API's four-role allowlist: `admin`, `vice_general_manager`, `supervisor`, and `accountant`. The supervisor responsibility-scope check remains in place.
- Source re-fetch on `main` confirms the detail endpoint allowlist. This is source verification only: PHP lint, Flutter analysis/tests/APK build, and live API role checks remain unverified for these latest commits. Do not request device testing yet.
- Audit note: `api/v1/sponsors/detail.php` intentionally has a different role set from `api/v1/sponsors/index.php`; the web sponsor detail page has the same broader role set as the detail API, so no change was made there.


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

- Added authenticated `POST /api/v1/projects/progress_update.php` using the existing `project_progress_updates` fields and `audit_log` pattern. No schema changes or runtime DDL.
- Permission model follows the existing web `add_progress` action: admin or the assigned primary project supervisor / active operations-section team member; closed projects are blocked. The endpoint independently rechecks authorization and project state during the write transaction. Project existence/state checks follow authorization to avoid leaking state to unauthorized callers. Audit recording occurs after the progress row commits and audit failures are isolated, matching the web helper's failure boundary.
- `GET /api/v1/projects/detail.php` now returns `project.can_add_progress` for UI affordance only; the POST endpoint remains the authorization boundary. Flutter project detail includes an Arabic/English form for summary, date, completion percentage, achievements, issues, and next steps; successful submission refreshes the project detail.
- Added the endpoint to `.github/workflows/php-syntax-check.yml` and a widget test covering form submission/project ID/refresh. Flutter run [40111507626](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40111507626): Dart analysis and Flutter tests passed at commit `4433f860e3e1ae7ae44ef9bec3224a12415b4a09`; Android debug APK build was still running at last check, so full Flutter workflow success is not yet claimed. PHP lint passed in run [40111571974](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40111571974) at commit `d42374f87d29a70155f2094107bffb5c6c74fb54`; remaining unrelated full-schema Stage 5 payroll failure continues under issue [#94](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/94).
- Live Apache/MariaDB role-matrix verification is not yet performed. No phone testing requested; wait for broader mobile release-readiness gate.


## 2026-10-24 — Mobile project milestone creation (CI pending)
- Added authenticated `POST /api/v1/projects/milestone_create.php` using existing `project_milestones` and `project_status_history` records; no schema changes or runtime DDL.
- Permission boundary follows the web milestone-create action: admin or assigned primary project supervisor/active operations-section member; closed projects are blocked. The endpoint rechecks lifecycle and assignment in the transaction, derives status from completion percentage using the existing web rule, writes status history, and isolates audit failure after commit.
- Project detail now exposes `can_add_milestone`; Flutter has an Arabic/English milestone form for title, description, planned date, completion percentage and notes. Successful submission reloads project detail. Added a widget test and PHP lint workflow coverage.
- Commits: endpoint `4e7ce4759aa93d7ec3c8ab6118bcca926be67466`; detail capability `cfce5dcc8c9fe8a264919fc19c8a8da0e0eac265`; PHP lint workflow `728cc0c03013ce9e5adac53d03dbb15f0445c161`; Flutter API `9b7e05aa62e65683a38e0081b7551204ec6ed7ba`; portfolio callback `15204bdc66880b76170c48c3b681923ece84e913`; detail UI `a13438469c69f52ef574ef934fe9ccd29e7798d8`; widget test `edfef211f6607da19ace561c03a536c212884745`; portfolio test callback `f571077c894dea7429375ba36768827cfd832095`.
- CI was triggered but is still queued/in progress at this checkpoint. Do not claim verification until the latest Flutter analysis/tests/APK artifact and PHP lint results are available. Live Apache/MariaDB role checks and device acceptance remain deferred; do not request phone testing yet.


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


## 2026-10-24 — Mobile nanny preliminary verification CI result correction

- The preliminary-verification Flutter test commit `23c81c2dd69c0bd47bdf21d39415dfb550e1685c` completed successfully in GitHub Actions run [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559): analyzer, widget tests, Android debug APK build, and artifact publication passed. The app-wiring commit `250f4eab0e5101e10c56c8335af9165c6bad0fa2` also passed Flutter CI in [40113993153](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113993153).
- PHP syntax job passed for the two preliminary-verification endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). The latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014) again reports `php-lint`, MariaDB 10.4 migration, and rescheduling-service tests as passed; the independent full-schema and HR job-description migration jobs were still running when checked. Do not treat the separate full-schema payroll failure from earlier runs as a mobile feature failure.
- This supersedes the earlier pending-status note above. No live Apache/MariaDB nanny-role runtime matrix has been executed, and physical-device acceptance remains deferred. CI success is not runtime acceptance.
- Next work must remain source/schema-led. Do not duplicate the existing nanny verification workflows; inspect the next existing web workflow and its role/scope rules before exposing it in the mobile API. Do not ask the user for phone testing until the agreed broader-app release-readiness gate.


## 2026-10-24 — Mobile nanny preliminary verification CI results

- Flutter workflow [40114021559](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114021559) passed analyzer, widget tests, Android debug APK build, and artifact publication for commit `23c81c2dd69c0bd47bdf21d39415dfb550e1685c`. App wiring also passed in [40113993153](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40113993153).
- PHP lint passed for both preliminary-verification endpoints in [40114002834](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114002834). In the latest PHP workflow [40114100014](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114100014), `php-lint`, MariaDB 10.4 migration, and rescheduling-service tests passed; full-schema and HR job-description migration jobs were still in progress at last inspection.
- This supersedes the earlier pending CI note for preliminary verification. Live Apache/MariaDB nanny-role matrix checks and physical-device acceptance remain unperformed. CI is not runtime acceptance.
- Next: continue from the next existing web workflow only after source/schema and authorization-scope inspection. Avoid duplicating existing nanny verification flows. Do not ask for device testing until broader-app release readiness.


## 2026-10-24 — Employee details mobile work: incomplete / CI findings

- Added `api/v1/hr/employee_detail.php` with the same HR-manager, HR-staff, and admin role gate as `api/v1/hr/employees.php`, and a Flutter `EmployeeDetailPage` scaffold plus authenticated API loader. These are committed on `main` (API: commit `2040bf19`; page: `88c218ad`; loader: `dc5729b1`).
- The directory list tap has **not** been wired to the detail screen. GitHub write attempts for `employees_page.dart` were rejected; do not describe the screen as an end-to-end feature yet. Current Flutter CI run [40115002247](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115002247) failed analysis because `main.dart` imports `employee_detail_page.dart` without using it. This must be corrected before treating the mobile build as green.
- PHP lint passed in run [40114846063](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40114846063). Its separate `migration-full-schema` job failed in the existing Stage 5 payroll integration test with: `يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` This is a payroll-migration prerequisite failure, not evidence of an employee API defect; inspect the exact migration/test setup before changing anything. Other jobs (PHP lint, MariaDB 10.4 migration, HR job-description migrations, rescheduling service tests) passed in that run.
- API acceptance against a live app/database and role matrix remain unperformed. The employee detail API has not yet been added to the explicit PHP-lint file list, and the new detail page has no test in `main` yet.


## 2026-10-24 — Employee detail screen wired; CI pending

The employee directory now calls the new authenticated detail endpoint through the Flutter detail screen. Added an Arabic widget test for the detail screen and updated the directory test for its navigation callback. Latest Flutter run: [40115161999](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115161999), still in progress at the last check. The PHP lint workflow still does not explicitly include `api/v1/hr/employee_detail.php`; live API role-based runtime verification remains pending. The separate full-schema Stage 5 payroll integration job previously failed because its payroll-transfer migration prerequisite was missing; this is not an employee-detail failure.


## 2026-10-24 — Employee details build evidence update

- Employee detail API and Flutter screen/navigation are present on `main`. The Android workflow at commit `a78bc176` completed successfully: `flutter analyze` reported `No issues found!`, `flutter test` completed, debug APK built, and artifact `ahl-el-kheir-flutter-android-debug-apk` was uploaded. Run: [40115151758](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115151758).
- Subsequent commits added the Arabic detail-page widget test and localized English employment-state label. Latest build/test run for commit `b5604f0a` is [40115569057](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115569057), still in progress at the last status check; wait for it before declaring the latest revision green.
- SQL schema was inspected: `employees`, `departments`, and `hr_employment_states` contain the fields used by the endpoint, including localized state names. Endpoint runtime authorization and real-database response testing are still pending. The existing PHP lint job passed, but its explicit file list does not yet include `api/v1/hr/employee_detail.php`; an attempted workflow-file edit was rejected, so do not claim that endpoint was linted by CI.
- The full-schema Stage 5 payroll integration job remains a separate failure: `FAIL | Stage 5 payroll integration | يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` Inspect the test/migration setup before changing anything; do not alter payroll production code to mask this prerequisite issue.


## 2026-10-24 — Employment-state localization refinement

Refined the employee detail screen to show the actual localized employment-state name instead of collapsing every `working` category into a generic label; this preserves distinctions such as probation. Updated the Arabic widget test fixture to include both Arabic and English state names. Latest Android build/test run [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) was queued at the last check. Earlier commit `a78bc176` passed analyzer, Flutter tests, and debug APK build; the newest revision still needs its own green result. PHP endpoint lint/runtime role tests remain outstanding, and the full-schema payroll migration prerequisite failure is separate.

## 2026-10-24 — Employee details CI closed; release-readiness boundary

- Confirmed Flutter Android workflow [40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291) completed with `analyze-and-build` success for feature commit `ed9e169ab8bf75be8592d6765181314679b1dd06`. This supersedes the earlier queued/pending note for that run.
- Updated `mobile/ahl_el_kheir_app/README.md` because it incorrectly described the app as only a minimal signed-in screen. It now documents the integrated modules and clearly distinguishes incremental mobile coverage from full web parity.
- Current release blockers are still explicit: live API/database role-and-scope acceptance, PHP lint coverage for `api/v1/hr/employee_detail.php`, and a confirmed HTTPS-compatible PHP/MariaDB hosting target. Netlify static hosting alone does not host this PHP/MariaDB backend. Do not ask for physical-device testing yet.
- Next development must be selected from an existing web workflow not already covered by mobile, after inspecting its actual role/scope and schema. Do not duplicate the existing nanny verification workflows or reopen closed attendance/payroll work.

## 2026-10-24 — Self-service mobile salary statements added (CI pending)

- Added authenticated `GET /api/v1/hr/my_salary_statements.php` and an Arabic-first/English Flutter screen linked from the signed-in home page. It resolves only the current user's employee profile through the existing `hrGetEmployeeForUser()` helper and returns only that employee's payroll rows where `status='paid'` and `accounting_status='posted'`, matching the web statement's availability boundary. Draft, approved-unpaid, and unposted rows are excluded by query conditions; no schema changes were introduced.
- Inspected `database/ahl_el_kheir.sql` directly through its Git blob: the `payroll`, `employees`, and `hr_employee_salary_history` columns used by the endpoint exist. The salary currency lookup follows the same effective-date selection used by `modules/hr/payroll_statement.php`.
- Source commit: [`7bcb340c83f42211bb6d65e80d57af3d52e95949`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/7bcb340c83f42211bb6d65e80d57af3d52e95949). The first Flutter run [40116469884](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116469884) found a widget-test fixture collision (two displayed amounts were both `100.00 SDG`); only the fixture was adjusted in [`dd226b794ca9136aa76359be62b927b018370bad`](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/commit/dd226b794ca9136aa76359be62b927b018370bad). Latest Flutter run [40116509126](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40116509126) was still in progress at this checkpoint; do not call the feature CI-verified until it finishes green.
- A PHP CLI syntax check of the new endpoint source returned `No syntax errors detected`. The existing PHP workflow's explicit lint list still does not include this new endpoint, and live Apache/MariaDB role-and-data testing remains outstanding. No device testing requested yet.

## 2026-10-24 — Mobile financial review queue implementation (CI pending)
- Inspected the existing web queue `modules/accounting/fm_review_queue.php`, its role allowlist, and `database/ahl_el_kheir.sql` `sponsor_payments` definition before implementing the mobile read view.
- Added `GET /api/v1/accounting/fm_review_queue.php`, restricted to the same allowed role codes (`financial_manager`, `fm`, `finance`, `admin`, `sudo`, `general_manager`). It returns all pending supervisor collections and the latest 20 approved/returned reviews, matching the web queue's current display boundary. It returns receipt-presence flags, not filesystem paths.
- Added Arabic-first/English Flutter `FmReviewQueuePage`, role-aware home navigation, widget coverage, and PHP lint workflow coverage. This slice is read-only: no approve/return action, accounting posting, schema change, or migration.
- Source commits: API `d9510b6eefe45ebb1ad533b4f1a5c12fdb2285f3`; page `704b8b47a057ab60cb6b0e81d695d0d19b332997`; app wiring `869aada089fa8c3471df4405a081bdd0085a8f4b`; widget test `f4c0ec3fe56fe160bc2d44186d3e37d922b66a64`; PHP lint workflow `a7d001a2fef5fbc5501f821b78872d3ec622b84e`.
- Verification: awaiting latest Flutter analyzer/widget/APK/artifact workflow and PHP lint job. No live Apache/MariaDB runtime authorization test has been performed. Do not expose review mutations until the existing `ak_approve_sponsor_payment()` atomic accounting workflow and return/audit behavior are separately mapped and verified.

## 2026-10-24 — CI verification checkpoint
During continued mobile work, the existing full-schema CI job failed in its Stage 5 payroll harness because the disposable database did not apply the required payroll-transfer migration and the harness had no prepared proof fixture. This was traced to the exact production guard and corrected in the CI-only migration setup and rollback-only test fixtures. No production payroll behavior changed. Fresh CI results are pending; do not call the overall pipeline green until every required job concludes successfully.

## 2026-10-24 — Follow-up CI fixture correction
After the payroll-transfer migration/proof setup, CI exposed one more fixture issue: the CLI transition had no authenticated session user, so the proof confirmation FK correctly rejected actor ID 0. The rollback-only Stage 5 and concurrency harnesses now set a valid actor from the disposable DB. This is not a mobile API or production payroll code change. Fresh full-schema CI remains pending.

## 2026-10-24 — Latest CI verification
- Mobile financial review queue (`60883414`) passed Android workflow [40117632176](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40117632176): analyzer, widget tests, debug APK build, artifact publication.
- Payroll-transfer CI fixture alignment passed [40118139681](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118139681), all five jobs green. Full-schema output explicitly passed rollback-only Stage 5 payroll behavior, account 1410 reconciliation, payroll/rescheduling contention and retry, and fixture cleanup.
- This is CI evidence only. API authorization and record-scope still need runtime verification against local Apache/MariaDB; no production host selected/configured yet.

## 2026-10-24 — Mobile financial review actions added (PHP CI passed; Flutter CI pending)
- Extended the financial review queue from read-only to approve-and-post and return-for-correction actions. New authenticated endpoint: POST api/v1/accounting/fm_review_queue_action.php, with action, payment_id, and required return_note for returns.
- The server derives reviewer identity from the authenticated bearer user and enforces the same allowed role set as modules/accounting/fm_review_queue.php. Approval reuses ak_approve_sponsor_payment() inside a PDO transaction; the helper creates the accounting transaction/journal and updates the pending payment, with rollback on failure. Return locks the row (FOR UPDATE), requires current pending status and a non-empty reason, updates reviewer/status/time, attempts an audit_log entry, commits, then attempts the supervisor notification. Notification/audit failures are isolated in line with the existing web workflow's behavior.
- Flutter queue now provides approve-and-post and return controls with required-reason validation, per-payment busy state, feedback, and refresh. A widget test covers action invocation and return-reason display. No schema changes.
- Source commits: shared-helper action API 624ce7b6636bbf235b50dcd5a0850e32cd8bc688; include-path correction b774c3ae2b0bde1f177bfded9f80d7c110511e72; UI controls fd64acc3a641c23813aaba040a10ee4944cc0586; API wiring cf76e9526bc9fa93bc159ebd8ad5fb4b95b35e27; PHP lint 440ff064922bbe3aaf7f9c36aa4b0b7eddcebee8; test 734df683f94c35057b5373f8911a2c342335262d.
- PHP lint and all PHP/database CI jobs passed in 40118666512 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512). Flutter run 40118666504 (https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) was still in progress at last check. Runtime API tests against local Apache/MariaDB—including role denial, double-approval, stale return, and audit/notification failure boundaries—remain outstanding. No phone testing requested until release-readiness gate.

## 2026-10-24 — Financial review actions and sponsorship role parity (CI passed)
- Added `POST api/v1/accounting/fm_review_queue_action.php` and Flutter approve/return controls. Approval delegates to the existing atomic `ak_approve_sponsor_payment()` helper. Return requires a reason and conditionally transitions a locked pending payment to `returned`, records audit information, and sends supervisor notification after commit.
- Added widget test coverage for requiring a return reason and forwarding the return action. General Manager sponsorship access now matches the web module in mobile navigation and sponsorship list/detail API guards.
- Android [40118666504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666504) passed analyzer, tests, APK build, artifact publication. PHP/database [40118666512](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40118666512) passed all five jobs. All 31 PHP API endpoints are explicitly linted.
- Still outstanding: live local Apache/MariaDB role/record-scope verification and real-data approve/return acceptance. Do not claim runtime verified from CI.

## 2026-10-24 — Project API operational-scope parity correction
- `api/v1/projects/detail.php` now defaults a missing approval record to `draft`, matching web `akp_can_view_project()` and the portfolio API.
- `api/v1/projects/milestone_create.php` now blocks non-admin supervisor writes unless approval is `approved` and lifecycle is operational (`active`, `reopened`, `completed`, `under_review`, or `cancelled`); assignment checks and closed-project blocking remain. The project detail `can_add_milestone` flag now matches that gate.
- PHP/database workflow [40119297504](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40119297504) passed all five jobs. Source/CI verification only; live local API role-scope acceptance remains outstanding.

## 2026-10-24 — Mobile project milestone maintenance — implementation checkpoint

- Added authenticated `POST /api/v1/projects/milestone_action.php` for edit/delete. It accepts only `edit` or `delete`, requires both project and milestone IDs, scopes the milestone lookup/update/delete to that project, locks the project and milestone rows during the transaction, permits `admin` and `project_supervisor` roles, blocks closed projects, and rechecks active primary-supervisor/operations assignment for supervisor accounts. This mirrors the web gate: admin is allowed on non-closed projects; supervisors need the relevant assignment. No additional approval/lifecycle restriction is applied beyond the web module's closed-project check. Edit follows the web module's existing editable fields and derives milestone status from completion percentage. No schema change or runtime DDL. The first Android widget runs exposed two test issues: the test tapped the wrong control finder, then the maintenance capability was missing from the new test's fixture. The test now targets stable control keys, ensures visibility, and has the correct fixture. On source SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`, Dart analysis, all Flutter tests, debug APK build, and artifact publication passed in Android workflow [40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219). The later PHP/database workflow [40121128872](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40121128872) passed all jobs on source SHA `2a664a66316319983dc32d48cdfa9024b770a71a`, including PHP lint for the corrected API authorization. The APK was built before the last PHP-only authorization alignment; Flutter source did not change afterward. The separate `can_manage_milestones` capability keeps edit/delete visibility aligned to the web permission boundary (assigned project supervisors, non-closed projects) rather than the distinct create capability. Live local Apache/MariaDB authorization acceptance remains outstanding.
- Project detail now includes milestone IDs; the Flutter detail page exposes Edit/Delete only when the server's milestone capability is true and the action callback is wired. Delete requires confirmation; successful actions refresh the detail. Added a widget test for action payloads.
- Source commits: API `329291d150eec4cf0315fba1d81c189e90e0ce32`; PHP lint workflow `bfb0799ffe7645fefcce7e6b5d85e153e543c824`; API wiring `7915b0767bb0b3bb94919463c86fcbd88bd656f4`; portfolio wiring `1951d3ba0688a3af9ec88899b44714f7dc16d2a0`; home wiring `8f9f0cbc56bb2a4c47b173de31ea8274b0877dd4`; UI `3e837fef13e1c04c62605944bbcb238e99fe6fc3`; milestone ID response `99e95e09e4dc069dd6ea32688c720eccc56c3bc2`; widget test `88d0df39cf92efcb3919ac0fea5d6a373fa7e2b7`.
- Android workflow `40120100653` was in progress at this checkpoint. Do not call analyzer, widget tests, APK build or artifact publication passed until it finishes. The PHP/database workflow for the new endpoint and live local Apache/MariaDB role-and-record-scope acceptance also remain unverified. Do not request physical-device testing yet.


## 2026-10-24 — Mobile API source-level authorization review checkpoint

- Reviewed current API role gates and record scopes across bearer authentication, projects, sponsors/sponsorships/families, HR employee/attendance/leave/salary endpoints, nanny verification, notifications, sponsor requests, transaction listing, and FM review actions against corresponding web workflows.
- No new, unambiguous mobile-only authorization or record-scope mismatch was confirmed in this pass. No production code was changed speculatively.
- This is not runtime acceptance or release sign-off. Remaining blockers: authenticated role-denial, cross-record isolation, state-transition/concurrency, and notification/audit-boundary checks against the actual local Apache/MariaDB app; verify the API schema/migrations on the intended local database before tests; rebuild the APK after any later Flutter changes; confirm an HTTPS-capable PHP/MariaDB hosting target. Netlify static hosting alone does not host this backend.
- Continue with remaining concrete source checks, then prepare a repeatable, non-destructive local API acceptance harness. Do not test against live user data or request physical-device testing before the broader release-readiness gate.


## 2026-10-24 — Mobile auth migration CI coverage

- The current mobile API auth foundation depends on `database/migrations/2026-10-24_mobile_api_sessions.sql`; the checked-in base schema intentionally does not include the deployment migration. Application requests must never create this table.
- Updated `.github/workflows/php-syntax-check.yml` to lint `api/v1/_bootstrap.php` and apply the mobile-session migration twice to the existing disposable MariaDB 10.4.32 full-schema CI database after importing the base schema and required payroll-transfer migration. This checks DDL compatibility with the checked-in schema and verifies the migration's declared repeat-run behavior without touching any local/user database.
- Workflow change commit: `4fd136bb6646b960d8ea46548eab8757c65872d7`. CI has not yet completed for this change; do not claim the new checks passed until the resulting workflow run is green.
- Source review of `_bootstrap.php`, login, `/me`, and logout found no new concrete defect in this pass. Known release gates remain: local migration-state confirmation, real Apache/MariaDB auth/session acceptance, role/record-scope/action acceptance, and an HTTPS-capable PHP/MariaDB hosting target. Rate limiting and refresh/session-management capabilities remain documented future security/product gaps; do not expose XAMPP publicly or request phone testing before the release-readiness gate.


## 2026-10-24 — Mobile auth migration CI gate passed
The PHP/database workflow passed after the mobile auth migration coverage change: [run 40122798655](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40122798655), source SHA `09a94183a9b2cb1e23feefeaf8733b02d97537c3`. All five jobs passed, including `php-lint` and `migration-full-schema`; the latter imported the checked-in schema, applied `2026-10-24_mobile_api_sessions.sql` twice to the disposable MariaDB 10.4.32 database, and completed the existing full-schema regressions. This proves the CI migration step succeeds in that disposable environment, not that the migration is applied locally or that API auth passes live runtime tests. Latest Android build remains successful at [run 40120835219](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40120835219) on Flutter SHA `b061ec3da52f6e949b663ee5d14eb3b5d0e00b23`; no Flutter source changed after that build. Remaining gates: local migration-state confirmation, controlled local Apache/MariaDB API auth/session and authorization tests, and HTTPS PHP/MariaDB hosting. Do not request phone testing yet.


## 2026-10-24 — Physical-phone API default URL alignment (CI pending)
- Source review found the Flutter compile-time fallback in `mobile/ahl_el_kheir_app/lib/main.dart` used port `8081`, while the documented physical-phone Apache host and the GitHub Android workflow default use port `881`. This would affect direct Flutter runs that do not supply `API_BASE_URL`, even though the workflow-built APK overrides it.
- Corrected only the fallback URL to `http://192.168.18.9:881/AhlElKheir/api/v1`, matching the existing phone-test setup. No backend or web URL was changed.
- Commit: `273bd040d7ad697f9dce66708e681f43cb97c390`. Android build and PHP/database workflow runs are pending at this checkpoint; do not claim verification until both finish. This URL remains LAN-only and depends on the laptop IP/Apache port; it is not suitable for an external production release. Do not request phone testing yet.

## 2026-10-25 — Corrected mobile API fallback to verified Apache port

- Compared `mobile/ahl_el_kheir_app/lib/main.dart` with `.github/workflows/build-flutter-mobile-app.yml` and the prior local reachability evidence. The Flutter compile-time fallback still used `http://192.168.18.9:881/AhlElKheir/api/v1`, while the workflow default and the recorded successful local/phone-browser reachability checks use port `8081`; the previous local check explicitly found no listener on port `881`.
- Corrected only the Dart fallback to `http://192.168.18.9:8081/AhlElKheir/api/v1` in commit `41cd2e0176ee4bf2254dfc92fadde6b8c3ba6665`. No backend, database, or web behavior changed.
- Android analyzer/test/build/artifact verification for this correction is pending; check the workflow run before claiming it passed. This aligns the default with the last recorded local evidence, but does not prove the laptop IP or Apache port remain unchanged today.
- The live API role/record-scope/action acceptance gate remains outstanding. Do not request physical-device testing until the broader release-readiness gate is satisfied.


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


## 2026-10-2026-10-25 — Project milestone ID response defect found, corrected, and verified
A targeted source audit found `api/v1/projects/detail.php` selected `project_milestones.id` but omitted `id` from each serialized milestone, while Flutter edit/delete actions require that ID. Added the existing row ID to the response in commit `20631106ae8fe9f8267779faf4c6e9a1d7608057`. Focused disposable-database endpoint coverage passed in [run 40160569643](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160569643); cross-project and ended-assignment mutation checks subsequently passed in [run 40160867381](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160867381). No schema or migration changes. These are CI results only; local Apache/MariaDB acceptance, migration-state confirmation, and HTTPS hosting remain release gates. Continue the broader source/schema-led API authorization audit.


## 2026-10-25 — Mobile milestone ID and project-scope integration verification

- Added a disposable-CI integration assertion to tools/test_mobile_api_auth_integration.php that inserts a real test milestone and confirms GET /api/v1/projects/detail.php?id=... serializes the matching milestone id.
- Added an assertion that ends the project supervisor's assignment, attempts to edit a milestone, and verifies HTTP 403 with the stored title unchanged.
- Test commit: eebe53983eed73e9899ca9cf5d85754497e3e092.
- Runtime result: PASS in [Mobile API auth integration run 40160569643](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160569643). The workflow imported the checked-in schema, applied the mobile-session migration, and exercised live PHP endpoints against disposable MariaDB. This is CI evidence only; it does not establish local XAMPP migration state or local runtime behavior.
- Source review also compared the milestone mutation API with modules/projects/view.php: project and milestone IDs are jointly scoped; supervisor mutations require an active primary-supervisor or operations assignment; closed projects are blocked, consistent with the web edit/delete gate. No new production authorization defect was established in this pass.
- Latest source audit checkpoint: milestone response ID defect corrected and CI-tested; broad live local API authorization/record-isolation/action acceptance remains open. Do not run the integration harness against ahl_el_kheir; it is gated for the disposable mobile_api_auth_test database.


## 2026-10-25 — Cross-project milestone isolation integration test

- Extended `tools/test_mobile_api_auth_integration.php` to create a second temporary project and milestone, then attempt to edit that milestone while supplying the assigned project ID. The assertion requires HTTP 404 `milestone_not_found` and verifies the foreign milestone title is unchanged.
- Test commit: `bc67997093d15fd616ac95ac4ead566840004883`.
- Verification: [Mobile API auth integration run 40160867381](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40160867381) completed successfully. Its live PHP API test step and full workflow passed against disposable MariaDB.
- Cleanup removes the foreign milestone and temporary project. No schema/migration or production endpoint code changed in this checkpoint.
- Confirmed scope coverage now includes milestone ID serialization, ended-assignment denial with no mutation, and cross-project milestone mutation denial with no mutation. Local XAMPP runtime/migration state and broader mobile API release acceptance remain open.


## 2026-10-25 — Mobile notification cross-account isolation verified in CI

- Extended `tools/test_mobile_api_auth_integration.php` with a notification addressed to a second existing user in the disposable schema.
- The test confirms `GET /api/v1/notifications/index.php` excludes the foreign notification and `POST /api/v1/notifications/mark_read.php` cannot change its stored `is_read=0` state when called by the first user's token. Cleanup deletes only the exact fixture row.
- Test commit: `ab2a6173e596f88ed4e7c2fb6e962e40e5429192`.
- Runtime result: PASS in [Mobile API auth integration run 40161671591](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161671591). The workflow imported the checked-in schema, applied the mobile-session migration, and ran live PHP endpoints against disposable MariaDB.
- No production endpoint or schema change. Local XAMPP migration-state confirmation and broader local API authorization/record-scope/action acceptance remain open. Never run this harness against `ahl_el_kheir`.


## 2026-10-25 — Sponsor-request authorization integration coverage

- Added a temporary sponsor-request fixture using the checked-in schema to `tools/test_mobile_api_auth_integration.php`.
- Assertions prove `nanny` cannot list requests or change a request's status; the fixture remains `new`. After the live user's role changes to `admin`, `new` → `contacted` succeeds, while a repeated `mark_contacted` returns HTTP 409 `invalid_transition` and preserves `contacted`.
- Test commit: `f0dfa70a376771cbceeae04e73b57f2e6e40cb89`.
- Runtime result: PASS in [Mobile API auth integration run 40161801824](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40161801824). The workflow imported the checked-in schema and exercised the real PHP endpoints against disposable MariaDB.
- Cleanup deletes only the exact temporary sponsor-request row. No production code/schema change. Local XAMPP migration-state confirmation and broader local API authorization/record-scope/action acceptance remain open.


## 2026-10-25 — Nanny family record-scope integration coverage

- Added two temporary family fixtures to `tools/test_mobile_api_auth_integration.php`, one assigned to the authenticated nanny and one assigned to a different existing user.
- Live HTTP assertions pass for viewing the assigned family, denial of the foreign family (403), and family-list scoping (assigned fixture included; foreign fixture excluded).
- The first run failed because the harness's five-second HTTP timeout expired on the full family-list query across the checked-in schema's 2,208 families. Source evidence did not establish an endpoint defect. The request helper now supports a per-request timeout and only the family-list test uses 20 seconds.
- Updated test commit: `9312a7118ebe7876986dcecfda0bdcd0e3e594d9`; [Mobile API auth integration run 40162023286](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162023286) passed. Cleanup deletes the two exact family fixtures. No production code/schema change.
- Local XAMPP migration-state confirmation and broader local API authorization/record-scope/action acceptance remain open. Never run this harness against `ahl_el_kheir`.


## 2026-10-25 — Supervisor sponsor responsibility matrix integration coverage

- Added a disposable sponsor fixture whose `supervisor_id` points to the test user while its first-letter is deliberately outside that user's `supervisor_letters` matrix.
- Assertions pass: sponsor detail returns HTTP 403 and the sponsor is excluded from the supervisor's sponsor list. This verifies that direct `sponsor.supervisor_id` alone does not bypass the authoritative first-letter + gender responsibility rule.
- Test commit: `de9af9c85a56f5f5f593ec404dda7b26753bfdb8`; [Mobile API auth integration run 40162198710](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162198710) passed.
- Cleanup deletes the exact temporary sponsor fixture. No production code/schema change. Local XAMPP migration-state confirmation and broader API acceptance remain open.


## 2026-10-25 — Project milestone lifecycle authorization correction
`api/v1/projects/milestone_action.php` now applies the same approved-project/operational-lifecycle guard as milestone creation for non-admin milestone edit/delete. This closes a source-level inconsistency where an assigned Project Supervisor could mutate a milestone on a draft or non-operational project. Production fix: `bc6f6ee0104326ee536fdaa732f1e693de739eb8`. Disposable integration regression: `c6cc08b3b646c1906392019e116a646ab038a5df`, run 40162518220 completed successfully; the disposable MariaDB live HTTP integration harness passed, including the draft milestone denial/no-mutation assertion. The local XAMPP/MariaDB migration and runtime acceptance gates remain open. Never run `tools/test_mobile_api_auth_integration.php` outside the exact disposable CI database `mobile_api_auth_test` with `MOBILE_API_AUTH_TEST=1`.


## 2026-10-25 — HR leave approval authorization/state CI coverage
- Extended `tools/test_mobile_api_auth_integration.php` to cover leave approval with disposable fixtures. A nanny's approval attempt returns HTTP 403 and leaves the leave pending. HR staff can approve a pending leave for a non-HR employee; the API response and stored status are `hr_approved`, `hr_approved_by` matches the authenticated HR user, and the employee/date attendance row is `on_leave` with note `إجازة معتمدة`. Repeating approval returns HTTP 409 and preserves the approved state.
- Cleanup deletes only notifications linked to the test leave, the exact test-created attendance row, and the test leave itself.
- Latest test commit: `fd7d9652c3d9cc1bc6b6b17900742ef0f7d1d30b`. [GitHub Actions run 40162902436](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40162902436) passed using the checked-in schema and disposable MariaDB.
- Source review found no confirmed production race defect in `api/v1/hr/leaves/action.php`: approval is conditionally updated only while pending, affected rows are checked, and the transaction rolls back on conflict. No production endpoint change was made.
- This is CI-only verification; local XAMPP migration state and live Apache/MariaDB acceptance remain open. Never run this harness against `ahl_el_kheir`; it requires `mobile_api_auth_test` and `MOBILE_API_AUTH_TEST=1`.


## 2026-10-25 — Mobile API authorization source-review pass: nanny, HR reads, sponsorships, transactions
Reviewed current `main` source for:
- `api/v1/nanny/group_verification.php` and `group_verification_action.php`: assigned active group is required; bulk verification and submission are limited to pending groups; individual verification remains available after submission, matching the documented follow-up workflow for older payments.
- `api/v1/nanny/pre_verification.php` and `pre_verification_action.php`: family updates require an active group assignment containing the family; bulk operations validate every selected family before writing and use a transaction.
- `api/v1/hr/attendance.php` and `employee_detail.php`: read access is restricted to HR roles and administrators.
- `api/v1/hr/my_salary_statements.php`: rows are scoped to the authenticated user's linked employee and only paid, accounting-posted payroll.
- `api/v1/sponsors/index.php`, `sponsors/detail.php`, `sponsorships/index.php`, `sponsorships/detail.php`, and `transactions/index.php`: supervisor scope is constrained by the sponsor responsibility matrix; role gates differ by endpoint.

No new production authorization defect was confirmed in this pass, so no application change was made. Direct integration coverage remains incomplete for nanny group/preliminary-verification actions and several HR read, sponsorship, transaction, and supervisor endpoints. Continue source/schema-led review and add disposable-CI assertions only when fixture requirements and expected business rules are grounded. Local XAMPP/Apache/MariaDB acceptance remains open. Never run `tools/test_mobile_api_auth_integration.php` against `ahl_el_kheir`; it requires `mobile_api_auth_test` and `MOBILE_API_AUTH_TEST=1`.


## 2026-10-25 — Read-endpoint role-denial integration assertions added (verification pending)
Added seven test assertions to `tools/test_mobile_api_auth_integration.php` using the existing nanny bearer token. They assert HTTP 403 for nanny access to:
- `api/v1/hr/attendance.php`
- `api/v1/hr/employee_detail.php`
- `api/v1/sponsorships/index.php`
- `api/v1/sponsorships/detail.php`
- `api/v1/transactions/index.php`
- `api/v1/supervisors/index.php`
- `api/v1/accounting/fm_review_queue.php`

The existing HR employee-directory denial assertion is retained, making eight read-endpoint role-denial checks across this set. No production source changed. Commit: `d62656a87dd77f187254b7f342c425e6dac0dcbf`. At checkpoint time, the GitHub combined commit status returned no status entries and the workflow-run lookup returned none; therefore this test change is **not yet runtime/CI verified**. Do not describe the added assertions as passing until a completed run is observed. The harness guard still restricts execution to disposable `mobile_api_auth_test` with `MOBILE_API_AUTH_TEST=1`.


## 2026-10-25 — Mobile API authorization audit continuation

- The user synchronized local `main` through `2c70cdee`; the reported working tree is clean.
- Added four more role-boundary assertions in `tools/test_mobile_api_auth_integration.php` at commit `fbf83136ace25fb80c3f51f82b529552aa311d85`: HR staff must receive HTTP 403 for nanny group verification GET, preliminary verification GET, group verification action POST, and preliminary verification action POST.
- Together with the previously added seven nanny-denial checks and existing HR employee-directory denial, the harness now covers these additional cross-role read/action boundaries. The harness still refuses to run unless `MOBILE_API_AUTH_TEST=1` and the selected DB is exactly `mobile_api_auth_test`; do not run it against `ahl_el_kheir`.
- Source inspection of the nanny endpoints confirms both action routes check the live authenticated role before reading request input or performing writes. The four assertions are regression coverage, not evidence of a newly discovered production defect.
- **Verification status:** the recent assertions passed in the dedicated PHP integration workflow; see run `40164371241` below. Local XAMPP runtime acceptance remains outstanding.
- Continue auditing remaining endpoint scopes and record only source-confirmed findings. Keep local runtime acceptance open; do not run the integration harness against the real `ahl_el_kheir` database.


## 2026-10-25 — Authentication and remaining record-scope source review

- Re-inspected `api/v1/_bootstrap.php` and the login, `/me`, and logout routes. Authenticated requests resolve the token hash against a non-revoked, unexpired mobile session and re-read active-account, password-change-required, and current-role state from the database. The raw bearer token is not stored.
- Re-inspected family, sponsor, and sponsorship detail authorization. Nanny family detail is constrained to the directly assigned nanny; supervisor family detail uses the existing family-scope helper; supervisor sponsor and sponsorship detail use the existing sponsor responsibility helper. These checks are consistent with the documented scope model in the reviewed paths.
- Re-inspected the HR attendance and salary-statement read paths. Attendance is limited to HR manager, HR staff, and admin. Employee salary statements are resolved from the authenticated user's linked employee and restricted to paid payrolls with posted accounting status.
- No additional production defect was confirmed by this source pass. This is a source review only; it is not a substitute for HTTP integration/runtime verification.
- The newly added nanny-endpoint role-denial assertions and previous read-endpoint assertions remain unverified because the available commit-status query returned no checks for these PHP harness commits. The Flutter build workflow does not run this PHP integration harness. Keep this limitation explicit and continue the audit without claiming a pass.


## 2026-10-25 — Mobile API role-denial integration assertions VERIFIED in CI

The earlier pending-verification notes for the new read/action role-denial assertions are superseded for these specific assertions. GitHub Actions run [40163578388](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40163578388) passed on test commit `d62656a87dd77f187254b7f342c425e6dac0dcbf`; run [40163733247](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40163733247) passed on test commit `fbf83136ace25fb80c3f51f82b529552aa311d85`. Both runs imported the checked-in schema, applied the mobile-session migration twice to disposable MariaDB 10.4.32, and passed the live PHP HTTP integration harness. Logs explicitly show all seven nanny read-endpoint denials and all four HR-staff denials for nanny verification read/action routes passing. No production endpoint or schema change was made in those test commits. This verifies the scripted assertions in CI only; it does not establish local XAMPP migration state or local Apache/MariaDB acceptance. Continue the finite source/schema-led audit and keep local runtime/release gates open. Never run the harness against `ahl_el_kheir`; it requires `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`.


## 2026-10-25 — Latest mobile API integration run passed

GitHub Actions run [40164371241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40164371241) passed on commit `428decce211ab7a9cff743375679ba16f3e1fae4`. The run imported the checked-in schema, applied the mobile-session migration, and ran `php tools/test_mobile_api_auth_integration.php` over live HTTP against disposable MariaDB. The log explicitly confirms the added checks `nanny is denied access to the HR leave approval queue` and `nanny cannot create project milestones` passed, along with the broader suite. The same commit's separate salary-advance verification workflow also passed (run `40164371167`). This verifies CI only; local XAMPP acceptance and the final full audit closure remain outstanding. The test harness is restricted to `MOBILE_API_AUTH_TEST=1` and database `mobile_api_auth_test`; never run it against the real `ahl_el_kheir` database.


## 2026-10-25 — Finite mobile API source-review checkpoint

The current source pass covered the remaining project portfolio/detail authorization paths, sponsor-request role gates, notification ownership checks, HR leave request/approval boundaries, nanny verification role and active-assignment checks, and financial-review action gates. Existing tests and source checks were cross-referenced against the live-HTTP harness. No additional production authorization defect was confirmed in this pass, so no production code change was justified. `api/v1/sponsorships/index.php` does load/filter rows in PHP; this is a potential scalability concern, not a proven authorization bypass, and is not being changed as part of this audit without measured evidence and a separate performance scope.

The latest integration test commit `428decce211ab7a9cff743375679ba16f3e1fae4` passed [Mobile API auth integration run 40164371241](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40164371241), including the new HR leave-queue and project-milestone role denials. The same commit's separate salary-advance verification workflow passed run `40164371167`. The CI gate is therefore green for the current harness. Remaining acceptance item is local XAMPP confirmation of the explicit `2026-10-24_mobile_api_sessions.sql` migration and a controlled local API smoke test; do not run the integration harness against `ahl_el_kheir`. The audit remains open only for that local acceptance gate and final reconciliation of the finite checklist, not because a production defect is currently confirmed.


## 2026-10-25 — Flutter menu navigation context fix

- User-reported symptom: the freshly installed APK signs in successfully as GM; tapping menu cards produces a ripple/highlight but does not navigate, and no API request appears in Apache access logs. The language toggle works.
- Root cause identified in source: home-screen menu callbacks captured the _AhlElKheirAppState.build context, which is above MaterialApp. Calling Navigator.of(context) with that context cannot resolve the Navigator; this explains why card taps animate but routes/API loads do not begin.
- Fix committed on main: commit 49ef28ad185f72c4d484909d29a200797d66e718 adds a state-owned GlobalKey<NavigatorState>, attaches it to MaterialApp.navigatorKey, and uses that navigator for the app-level menu route pushes. No PHP endpoint, database schema, or authorization logic changed.
- Source verification: fetched the committed mobile/ahl_el_kheir_app/lib/main.dart; confirmed the navigator key field and MaterialApp binding exist, 16 app-menu navigation calls use the key, and no old context-based Navigator.push call remains in that app-level menu section.
- Not yet runtime-verified: wait for the Flutter build workflow and install its newly built APK. Confirm Projects opens and an API request appears in Apache access.log. This source fix is not considered accepted until that phone test succeeds. Do not run the mobile PHP auth integration harness against the real ahl_el_kheir database.


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

## 2026-10-25 — Mobile dashboard refresh and login/navigation follow-up

User-selected direction: **modern professional dashboard**, retaining the Ahl El Kheir navy/blue palette and Arabic-first RTL behavior. The user also requires a password-visibility eye icon and a forgot-password link on the login screen.

Source changes committed sequentially on `main`:
- `b4c1470a9628fb92a9155ae74b238b2b892dee1b`: introduced responsive two-column work-area tiles, removed the visible role pill from the welcome panel, reduced the app bar from 68 to 56 logical pixels, added password reveal control and a forgot-password action, and corrected the account sheet to use the navigator overlay context.
- `75718e46132b7db06f83728509467411a54f652b`: added the `url_launcher` dependency for opening existing website workflows from the app.
- `a7a60e97adc984d6fa4171bfdba280fea73cf964`: included each notification's existing `link` in the authenticated notifications API response.
- `b85dc7aa57b6e4c5ccbccbb463af922881de9bc3` and `23137b81e4ea3e94e1bfd1251652b7462620f86c`: wired an orphan-form action from each child record to the existing canonical `modules/families/orphan_form.php?child=<id>` web workflow. It opens in the external browser; a browser login may be required because mobile bearer tokens do not create a browser session.
- `7d979e4bcb5a840ce983b4f4d9b7137edba1902a`: added a notification detail screen and tap navigation, and wired family/orphan-form access from the home menu.
- `ca16265515612bfa18442dcd59a987baaa303e0e`: completed the notification detail link callback wiring.

The forgot-password action opens the existing public `modules/users/password_recovery_request.php` flow, which submits a recovery request for review by HR/system administrators; no new password-reset mechanism or API was invented. Notification links are mapped back onto the app's configured website host/path before opening, rather than trusting a notification's origin.

**Verification status:** GitHub Actions Flutter analysis/tests/APK build are pending for the latest commit. No device runtime test has been performed for these changes. Do not claim the account sheet, notification detail/related-page flow, password recovery launch, or orphan form launch is device-verified until the latest APK succeeds and is tested on a phone. The orphan form remains the existing website screen, not a newly implemented native form. No database schema or business authorization rules were changed.
### CI correction follow-up — 2026-10-25

The first Flutter analyzer run for this work exposed concrete integration errors: a duplicate notification-tile `onTap` and required callbacks missing from the existing widget tests after constructor changes. The notification tap now uses one handler, mark-read failure stops navigation (preserving the prior error behavior), and tests provide explicit no-op callbacks where external navigation is not under test.

Correction commits: `e622c2c98aa4dea9462da5d0a632fe795cca5b56` (notification handler) and test callback updates through `c30c10b8e1a6df3f9ac2e197dc4363748419df22`. The new Flutter workflow runs for these commits are still pending/in progress at documentation time. Do not claim analysis, tests, or APK build passed until the latest run confirms it.
### Final notification handler correction — 2026-10-25

The next analyzer pass isolated one remaining Dart error: the notification mark-read helper returned without a boolean when the widget was unmounted during an error. Corrected to return `false` explicitly so the detail route is not opened after an unsuccessful mark-read operation. Source commit: `b572e49403805f3057e4830c211002c0d4fbd0e6`. The matching Flutter workflow run `40183286267` is queued; analysis/tests/APK status remain unverified.

## 2026-10-25 — Mobile dashboard rollback and notification navigation correction

User feedback rejected the recent two-column dashboard redesign and requested the prior single-column operational-card layout. The dashboard cards are now full-width again while preserving the existing module entries, role visibility, routes, and navy branding. Sign-out is a dedicated icon in the signed-in app bar beside notifications, account, and language; it is no longer buried in the account sheet.

Notification details remain a native Flutter screen. The external “Open related page” action and its URL-launch path have been removed from notification details; a linked notification now displays an in-app explanation that the related website destination is not yet available inside the app. This change intentionally avoids launching a browser from notification details. It does not convert arbitrary website workflows into native mobile screens.

Source commits: `92738fdb45ee6b6906555d8e4b0461caaaa556a0`, `b9d3c6fad0ff530dd6568b1a8279d8fcd452afee`. Widget regression test added in `64b140356a8d494ca667a2d264d6413c83d9fbbf`.

Verification: GitHub Actions for the new checkpoint is pending. Do not claim the APK is ready until the workflow for the latest source/test commit completes successfully and publishes the artifact. Physical-device visual acceptance remains with the user.


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


## 2026-10-25 — Coordinated mobile shell + legal-age alert milestone

Source commits on `main`:
- `1c51846a9c348f5fb09fd5fb9d551f10edf7cd62`: mobile shell styling, full organization name in the top identity row, and native legal-age alert sheet integration.
- `9ddf86df2a145f18a553ab452e7577723408c71d`: authenticated `GET api/v1/age_alerts/index.php` endpoint using the existing web alert's active-child/date window and nanny assignment scope.
- `0253a88d3a6201c9a279b879dd2e5fa0fcfe1a46`: role-specific dashboard hero titles/gradients and auto-show age alerts after login/session restoration when there are matching children.
- `17af21974b9decf913954f4b7d96127ac34704b1`: include the new endpoint in PHP syntax CI.

The app now has a clearer pale-blue surface, larger rounded cards, a role-colored dashboard hero, a full organization name beside the logo, a dedicated legal-age alert entry in the header, and a native in-app alert sheet. Eligible roles are admin/system-admin aliases, GM/VGM aliases, and nanny; nanny results are filtered to the nanny's assigned families. The alert sheet shows child, family, birth date and days until age 18. It intentionally does not claim to suspend sponsorships: the native suspension action now calls a separately authorized API endpoint; its runtime acceptance remains pending; alert items now open the native family-detail screen.

Verification status at this note: GitHub Actions run for commit `0253a88d3a6201c9a279b879dd2e5fa0fcfe1a46` was observed in progress. Flutter analyze/tests/APK and PHP endpoint lint have not yet been confirmed green. No local XAMPP/database runtime test has been performed. Do not mark the mobile redesign or feature parity complete on source changes alone.

Remaining major gaps: the sudo/admin native tools dashboard is still not feature-equivalent to `dashboard/admin_dashboard.php`; other role dashboards still need a documented feature-by-feature parity matrix; transaction date-range behavior remains unverified end-to-end; age-alert suspension action source is implemented; CI and local runtime verification remain pending; physical-device acceptance remains pending.


## 2026-10-25 — Dedicated administrator dashboard implementation

Commits on `main` add `api/v1/admin/overview.php`, a new `mobile/ahl_el_kheir_app/lib/admin_dashboard_page.dart`, and route `admin`/`sudo`/`super_admin` to this dedicated dashboard instead of the shared role-card list. The dashboard has its own violet/navy/teal theme, administrator overview metrics, recent audit activity, and separate System Administration, Core Records, Operations & People, Finance, and Protection & Maintenance groups. Native routes are connected for sponsors, families, sponsorships, transactions, projects, employees, attendance, supervisors, sponsor requests, financial review queue, and legal-age alerts. The website's users/departments/settings/reports/finance account/disbursement/Fina collection/backup/database/audit pages are surfaced but currently open in the external browser; they are not represented as native implementations.

Added `docs/MOBILE_ROLE_FEATURE_PARITY.md` as the explicit gap matrix. CI verification for the current changes is pending; no Flutter analysis/tests/APK or local runtime success is claimed yet. Other role dashboards and end-to-end date-range acceptance remain open.


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

## 2026-10-25 — Native role overview implementation checkpoint

The mobile workspace now includes a native, role-specific metrics section backed by `GET api/v1/dashboard/overview.php`. It covers GM, VGM, FM, Accountant/Accountant Staff, Supervisor, Nanny, Administration/Staff/Social Media, HR, and Projects Manager/Supervisor. Metric queries were derived from the real role dashboards; the API uses the existing bearer authentication and canonical role aliases and does not perform runtime DDL. The admin/sudo/super_admin dashboard remains on its separate `api/v1/admin/overview.php` endpoint.

Commits: API `f6ac0b4490b712ae7bebd23474aa97d6ab93d165`; widget `1e74df5e3e601593808121a907a7023faede21ba`; workspace integration `7c96953e298aa83b4d04149e91a2a55efa952283`; PHP lint workflow `4fe6df05679946968666959cfc6025921b28e1fd`. Widget tests were added in `59bf9bb3d6d25c81cbb04fe572d7b62b14527fbf`; a Supervisor tool authorization regression test was added in `74f7e018d0ee640127383ccdec515a34bdb87462`.

Route audit confirmed that the additional web tool paths exist. One invalid-by-role action was removed: Supervisor no longer sees “Record Fina collection”, because the existing web page explicitly denies Supervisor access.

**Verification boundary:** combined-revision CI and API SQL execution are not yet confirmed. After CI, verify authenticated requests for at least one account in each role family against the intended local `ahl_el_kheir` database. Do not apply any schema changes for this endpoint. The mobile transaction date filter exists in both UI and API; complete boundary/pagination/refresh runtime acceptance is still open. The external browser may not share the mobile bearer session with the website, so website links must not be described as authenticated session handoff.

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

**Still open:** complete native implementations for several role-specific financial, HR, beneficiary-form and Fina workflows; website-session handoff for WEB links; local XAMPP/API/database verification; physical-device acceptance. Do not describe mobile parity as complete.


## 2026-10-25 — Native suspension case management and date-filter integration

The mobile app now opens the suspension-case register natively from the existing suspension tool route. `GET api/v1/suspensions/index.php` returns the existing case list with nanny family scope; `POST api/v1/suspensions/lift.php` is restricted server-side to administrator/VGM roles and performs case, beneficiary, and linked sponsorship updates in one transaction, followed by best-effort audit logging. The native page supports refresh, active/lifted case visibility, bilingual reason labels, and a confirmation step before lifting. Non-age suspension creation and other non-native workflows remain outside this slice.

Full-schema PHP verification passed in run `40199009847`, including the rollback-only service output: `PASS | mobile legal-age suspension service | suspend/lift, linked sponsorship pause/resume, duplicate guard, role/scope guards, rollback`. The harness also checks administrator/nanny case-list visibility, nanny scope, and lift capability. Flutter analyze and the widget suite passed on source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6` in run `40199177461`; Android debug APK build/artifact publication was still in progress at the time of this documentation update.

Transaction date filtering also has a rollback-only full-schema test: run `40198548850` passed `PASS | mobile transaction date filter | inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS`.

Still open: authenticated API/database runtime against the intended local `ahl_el_kheir` instance, physical-device acceptance, website-session continuity for remaining WEB links, and full native parity for financial, HR, beneficiary-form and Fina workflows. Do not describe the app as feature-complete.


## 2026-10-25 — Native suspension workflow Android build PASS

Correction to the earlier in-progress checkpoint: GitHub Actions run [40199177461](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40199177461), source `e754b9e30cd3fbf51ad77192fbec8bcb087906b6`, completed successfully. Dart analysis, the complete Flutter widget test suite, Android debug APK build, and artifact publication all passed. Published artifact: `ahl-el-kheir-flutter-android-debug-apk` (77,895,397 bytes). The only later `main` commit at the time of this note updates documentation, so the artifact contains the current mobile application source.

The PHP full-schema rollback-only suspension case/suspend/lift test passed in run `40199009847`; transaction date-boundary integration passed in run `40198548850`. This is automated CI evidence, not proof of live local XAMPP/API/database behavior or physical-device acceptance.

The mobile parity work is not complete: non-age suspension creation, native financial/HR/beneficiary-form/Fina workflows, and website-session continuity for remaining WEB links are still open. Do not claim full native feature parity until those gaps are implemented and verified.


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


## 2026-10-25 — Physical-phone login regression: automatic APK API port corrected

The user reported that the installed APK again displayed `تعذر الاتصال بخادم API`. Source inspection identified the exact cause in `.github/workflows/build-flutter-mobile-app.yml`: the manual-dispatch default used `http://192.168.18.9:8081/AhlElKheir/api/v1`, but the automatic push-build fallback incorrectly used port `881`. Because automatic builds have no `workflow_dispatch` input, their APKs were compiled with the wrong port despite passing Flutter analysis/tests. Corrected the automatic fallback to port `8081` in commit `221937453f0df3606982b6ab0f7aa8fa10711c7d`. This is the previously verified local Apache port and the phone-browser API endpoint that returned the expected JSON 405 for GET.

**Acceptance status:** source/config root cause corrected; the newly triggered GitHub Actions build must pass and publish a fresh APK before the user installs anything. Do not reuse the failing APK artifact. This source correction does not itself prove physical-phone login; after the fresh APK is installed, confirm one login request appears in Apache access logs and login succeeds. If it still fails, capture the exact Android-side exception/status rather than treating all failures as credentials problems. Local API migration was previously reported applied; do not rerun it without evidence it is missing.
## 2026-10-25 — User-reported UI and navigation defects

Latest user acceptance feedback after signing in as Sudo:
- Stat cards show Flutter "bottom overflowed by ..." warnings.
- All System Administration website links fail with "couldn't open the website page".
- The user expects system workflows to open natively in the app; a large number of WEB handoffs is not accepted as the finished product.
- The existing visual theme/colors are explicitly rejected and must be redesigned rather than defended.

### Source findings and targeted fixes on main

1. **Website handoff token extraction:** `AhlElKheirAppState.call()` returns the decoded API envelope's `data` object. `openWebPage()` incorrectly attempted a second `response['data']` lookup. It now reads `response['handoff_token']` directly. Handoff errors now include details rather than swallowing all exceptions into the same message.
2. **Metric overflow risk:** Sudo dashboard cards now use a taller grid ratio and compact, bounded metric text. Role dashboard cards use a taller ratio and fitted value text. A widget regression assertion checks for RenderFlex exceptions in the role metrics test.
3. **Visual theme:** the shared app theme and admin/role dashboard palettes have been shifted from the prior navy/blue/violet look toward a teal/sea-green palette. This is a first correction, not a full design sign-off.

Commits on `main`: `68ab7b57283b4fd3b052c860cb5cf6b64d73daf8`, `141662935f37f3898501eca3a279aa5fb4e949b4`, `c69eb19c122370ef41e1ee4a749977316809a627`, `8180d52e3e73a64d638f2cbb4b324c1ba3dc5891`, `d5ed6f1ce673354dad6c984e5e49941d0ff946e1`.

### Verification and remaining scope

- These commits have not yet been verified by a completed CI run in this checkpoint.
- No physical-device handoff test is claimed.
- A website handoff is not a native mobile workflow. The native API/screens currently cover only a subset of the existing system. Continue by inventorying every visible tool by native/API-backed vs WEB, then implement native workflows in prioritized slices using the existing database/business rules and server-side authorization. Do not invent API routes or infer permissions from client-side role codes.
- Do not ask the user to retest each small change. Run CI and source-level checks first, then provide one consolidated APK for acceptance once the meaningful slice is complete.


## 2026-10-25 — Native-only navigation and native admin workflows

The mobile client no longer uses `url_launcher`, does not call `api/v1/auth/web_handoff.php`, and has no external-browser launch path. Former website routes now stay in the Flutter navigator. Existing native screens are used directly for projects, employees, attendance, leave requests, sponsors, sponsor requests, families, sponsorships, transactions, the financial review queue, salary statements, and suspension cases. Tool labels and account-sheet text no longer advertise website handoffs.

Where a native workflow is still missing, the app displays an explicit in-app notice instead of opening the website. This is a safety boundary, not a claim that the missing workflow has been implemented.

New native administration features:
- `GET api/v1/admin/users.php`: read-only, searchable, paginated user directory; server-side role guard is administrator/Sudo or HR Manager; password hashes and other credentials are never returned.
- `GET api/v1/admin/departments.php` and `POST api/v1/admin/departments_action.php`: native department list/create/update/delete. Authorization is the canonical administrator role, matching the existing department page's guard. Names are required and limited to 100 characters, duplicates are rejected, and deletion is blocked while users or employees remain assigned.
- `mobile/ahl_el_kheir_app/lib/audit_log_page.dart`: native recent audit activity view using the existing administrator overview response. It clearly says it currently displays only the latest six events; full audit pagination remains open.

The department operations were extracted into `api/v1/admin/departments_service.php` and verified by rollback-only full-schema MariaDB test `tools/test_mobile_admin_departments_service.php`. GitHub Actions run [40210824853](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40210824853) passed the full-schema migration job, PHP lint, and the department service integration output: `PASS | mobile admin department service | create, list, duplicate guard, update, assigned-user delete guard, delete, rollback=PASS`.

Flutter widget tests passed for the department form/list, user directory, audit activity, and native-only notice. Current physical-device acceptance has not been performed. Remaining work is substantial: user creation/edit/activation/password reset and accountant assignments are not native; recovery decisions, reports, settings, accounting/disbursements/Fina, backups/database tools, global search, profile/messages, orphan forms, and several HR/finance workflows still need real native implementations. Do not ask the user to test this intermediate checkpoint or describe mobile parity as complete.


## 2026-10-25 — Native password recovery queue

Added native recovery queue for administrator/Sudo and HR Manager:
- `GET api/v1/admin/recovery.php` lists the same latest-50 request queue and status/reviewer/expiry fields as the existing website page.
- `POST api/v1/admin/recovery_action.php` supports approve/reject only for pending requests and re-checks authorization server-side.
- `api/v1/admin/recovery_service.php` locks the request row, atomically updates the user password hash/forced-change flag and request status, sets the 24-hour expiry on approval, and returns the generated 12-character temporary password only in the approval response. Notification/audit writes are best-effort after commit; the UI displays the password in-app with a dismiss control and copy action. No schema change or runtime DDL was added.
- HR Manager now sees the recovery tool; HR Staff does not. The admin dashboard route also opens this native screen. The mobile client still never opens the website or browser.

Rollback-only full-schema MariaDB verification passed in Actions run [40211295992](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40211295992), output: `PASS | mobile admin recovery service | queue, approve, temporary password hash, forced change, expiry, duplicate guard, reject, rollback=PASS`. PHP lint passed in the same run. Flutter analyzer passed on the follow-up build; widget tests and APK publication are pending at this checkpoint. No physical-device test is claimed.



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


## 2026-10-25 — Administrator dashboard responsive layout checkpoint

Source commit: `fa11cde81f98839175431dd2d86348ee363d2270`.

- Added a 360×800 narrow-phone regression test for the dedicated administrator dashboard.
- Increased metric-card height, removed flex-based spacing from the metric-card header, constrained the hero refresh control, and constrained the database/PHP status-pill row to avoid fractional horizontal overflow.
- Changed tool labels so settings, reports, finance dashboard, chart of accounts, monthly disbursements, Fina collection, backup, and database tools are explicitly marked unavailable natively rather than misleadingly labelled as in-app workflows.
- Flutter analysis and the full widget-test suite passed in Actions run [40215385941](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40215385941). At documentation time Android APK compilation/artifact publication is still pending; do not distribute or claim the APK is ready until that run completes successfully.

Authorization finding retained as open: the dashboard is shown for `admin`, `sudo`, and `super_admin`, but existing server guards are narrower for some tools. `modules/departments/index.php` and the mobile department API permit `admin` only; settings is admin-only and has no native API; password recovery permits `admin` and `hr_manager`. Do not widen those permissions speculatively. The native user directory API does permit `sudo` and `super_admin`. Native parity remains incomplete and local XAMPP/physical-device acceptance has not been established.


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

## 2026-10-26 — Dashboard links follow existing server role guards

Source review identified a concrete reason some administrator-dashboard links failed for Sudo: the dashboard showed tools uniformly to `admin`, `sudo`, and `super_admin`, although several underlying endpoints deliberately authorize only exact `admin`. The dashboard now receives the persisted role code and hides Departments, Settings, Password Recovery, Reports and Audit from non-admin roles in this dashboard. Native User Accounts remains visible to Sudo because its existing API supports that role. The Reports tile description was corrected because the reports center and uncovered-families report already have native implementations. Added a widget test for Sudo visibility and user-directory routing. No server authorization or schema was changed. CI and APK publication must be confirmed before testing; real XAMPP/API and physical-device acceptance remain outstanding.



## 2026-10-26 — Admin dashboard role visibility CI PASS

Flutter workflow [40273273719](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40273273719) passed Dart analysis, all widget tests, Android debug APK build and artifact publication at revision `9c57765ab408afa43c1d81823e2aa7a5af1ec703`. The regression test confirms that Sudo retains native User Accounts access while Departments, Settings, Password Recovery, Reports and Audit are hidden when the existing endpoint guards do not permit Sudo. The test-harness issue was fixed by tapping User Accounts while it was first visible, avoiding a stale return-scroll finder; no server permissions were changed. Artifact: `ahl-el-kheir-flutter-android-debug-apk`, 77,507,377 bytes, SHA-256 `0c195da7469284f95aa8d7664874b2c62534ed096060e88f263844c9ce62279c`, expires 2026-11-25. Local XAMPP/API/database testing and physical-device acceptance remain outstanding; do not describe native parity as complete.


## 2026-10-26 — Native read-only administrator finance overview

Added `api/v1/admin/finance_overview.php` and `admin_finance_overview_page.dart`. The endpoint exposes treasury balances, posted current-month income/expenses, administrative fees, monthly-disbursement status/value totals and operational counts using the existing finance dashboard calculations. It requires exact persisted `admin`; Sudo and other roles do not gain access. The administrator dashboard now marks the finance overview IN APP, while chart of accounts, monthly disbursements and Fina collection remain not natively implemented. Finance/protection tools whose server guards do not permit Sudo are hidden from that role. No schema or accounting mutation was added.

Verification: live endpoint/full-schema integration PASS in [40274853455](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274853455); PHP lint PASS in [40274952065](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952065); Flutter analysis and widget tests PASS in [40274952027](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952027). APK build/publication was pending. Local XAMPP/API/database and physical-device acceptance remain open.


Correction — 2026-10-26: Flutter workflow [40274952027](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40274952027) has now completed successfully. Dart analysis, the full widget suite, Android debug APK build and artifact publication all passed for app source `89def08b6adde95bec648cb1349f18860b945ef5`. Published artifact `ahl-el-kheir-flutter-android-debug-apk`: 77,523,067 bytes; SHA-256 `7800554326cf99174b90550284ede7e1d44f634d48cc895dfb522f85335742a0`; expires 2026-11-25. Download via the workflow run’s Artifacts section. This is automated CI evidence only; authenticated runtime against the user’s local XAMPP/API/database and physical-device acceptance remain outstanding. The native finance page is read-only; chart of accounts, monthly disbursements and Fina collection remain unfinished natively.


## 2026-10-26 — Native finance and chart-of-accounts checkpoint

The administrator dashboard now has native read-only Finance Overview and native Chart of Accounts screens. The latter lists account balances based on posted journal lines and supports account creation and active-status toggling using existing account columns; no runtime DDL, schema migration, or accounting-entry mutation was added. The exact-role API endpoints are `GET api/v1/admin/finance_overview.php`, `GET api/v1/admin/accounts.php`, and `POST api/v1/admin/accounts_action.php`. Live full-schema integration passed for finance metrics and chart account listing/create/toggle/zero-balance behavior in [run 40275754236](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40275754236). Flutter analysis and all widget tests passed on current app source `27ff209075056f606db2a85c6c1a16259cac9316` in [run 40276057511](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40276057511); APK build/publication is still in progress at this checkpoint, so no new APK is available yet. Local XAMPP/API/database and physical-device acceptance remain open. Chart of accounts, monthly disbursements and Fina collection are not yet full native feature parity.


Correction — 2026-10-26: The Android build has now completed successfully. Workflow [40276057511](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40276057511) passed Dart analysis, all Flutter widget tests, debug APK build and publication for source `27ff209075056f606db2a85c6c1a16259cac9316`. Artifact `ahl-el-kheir-flutter-android-debug-apk`: 77,526,098 bytes; SHA-256 `798f2756a91c19f0a0448165907b2dbfa364c26d3465925784cbcab96e3bf144`; expires 2026-11-25. Download from the workflow run’s Artifacts section. Live API integration passed on the same backend/test source line in [run 40275754236](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40275754236). This proves CI/disposable-schema behavior only; local XAMPP/API/database and physical-device acceptance remain open. Full native feature parity is not complete.

## 2026-10-26 — Native administrator monthly disbursement register (verification checkpoint)

Added a read-only native monthly-disbursement register and batch-detail view in the Flutter app. The new authenticated `GET api/v1/admin/disbursements.php` endpoint supports optional month/status/search filters, paginated batches, and paginated family items. It authorizes only the exact persisted `admin` role, follows the existing `modules/accounting/disbursements.php` query fields/statuses, and adds no schema changes or financial mutations. The admin dashboard now labels Chart of Accounts and the read-only disbursement register as available in-app; no external browser handoff was added.

At source revision `dea8c633ef446690f6ec91bc7200d859959b5135`, Flutter analysis and all widget tests passed in [workflow 40278814068](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40278814068); Android debug APK build/publication is still pending at this checkpoint. The separate PHP lint job passed its configured file list, but this new endpoint is not yet in that list; do not claim endpoint PHP lint or live/full-schema query verification. Local XAMPP/API/database and physical-device acceptance remain open. Disbursement actions (create/transfer/confirm/return/reopen/void) and native Fina collection are not implemented by this read-only slice; mobile role parity remains incomplete.


## 2026-10-26 — Native report detail implementation checkpoint

The reports catalog now opens a native detail screen for financial, sponsorship, operational, and HR report families rather than showing an unavailable notice. `GET api/v1/reports/detail.php` uses the existing `modules/reports/report_registry.php` access matrix, normalizes only existing role aliases, validates date range/type, uses parameterized date filters, and returns native summary/table data. Report types include financial income/expense, cashflow, treasury, disbursements, returns/voids; sponsorship coverage/retention/distribution/status; supervisor/nanny/verification; and HR headcount/attendance/leaves/payroll. Source commits: `820d1dfb440d0fc81619e43b0e6bf9a0270c68eb` plus analyzer cleanup through `9ab66cd9ff69ceb1553cb69f2d91e066cc4cd34c`.

Flutter analysis, widget tests, Android debug APK build, and artifact publication passed in run `40298607464` for app source `9ab66cd9ff69ceb1553cb69f2d91e066cc4cd34c`. Published artifact `ahl-el-kheir-flutter-android-debug-apk`: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40298607464. Disposable MariaDB API integration passed in run `40298934944`, exercising all four report families, reversed-date rejection, database backup creation/list/download/delete, and role-denial checks. The earlier integration failure was traced to a stale assertion reading `$reopenedItem['json']` from a database row instead of `$reopenItem['json']` from the HTTP response; commit `fedcff2806c4a98798dbd5faafac24b8306906b5` corrected it and now verifies persisted pending status plus cleared `confirmed_at`. No local XAMPP/database or physical-device result is claimed.


## CI recovery update — 2026-10-26

- APK workflow run #571 passed source analysis, widget tests, debug APK build, provenance, and APK artifact publication at commit `2422efc2c7d38e1d86f4bed1ca7b315a48888542`.
- Salary workflow #3495 failed because `migration-full-schema` invoked `tools/test_mobile_admin_users_action_service.php` with `AHL_DB_NAME=salary_advance_migration_test`; its explicit safety guard requires `AHL_DB_NAME=mobile_api_auth_test` and `MOBILE_API_AUTH_TEST=1`. Removed only the incorrect invocation from the salary workflow. Keep the guard unchanged; the dedicated Mobile API auth workflow runs this test under the correct database.
- Await the new salary workflow result. These CI results do not prove the APK's API connection on a physical phone; do not ask for user testing until CI recovery and relevant pre-test checks are complete.

## 2026-10-26 — Native receipt/payment voucher actions

- Native Flutter voucher register now supports authorized receipt/payment issuance and authorized voiding with a required reason. It uses the existing authenticated api/v1/accounting/vouchers.php endpoint; the server remains authoritative for role access, dates, amount bounds, eligible cash and counter-accounts, payment balance, posting, reversal integrity, duplicate void prevention, and audit records.
- Issuance form sends the server-defined payload (action=issue_voucher, voucher type/date/amount, cash account, counter-account, party, description, optional reference). The void dialog owns its TextEditingController and disposes it with its own widget state; this was corrected after CI exposed premature disposal during the dialog exit transition.
- Disposable MariaDB API integration passed in run 40366312607: issuance, balanced posting, void reason validation, balanced reversal, duplicate-void rejection, role denial, and cleanup.
- Flutter analysis and all 138 widget tests passed, including issue and void interaction tests, in run 40369415336. The Android debug APK built and was published in the same run from app-source commit 69bb50391c3509906279e526f4a3a12aed2a25fd; artifact ahl-el-kheir-flutter-android-debug-apk, ID 12672886503, 102,394,809 bytes, expires 2026-11-25. Workflow: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40369415336
- Current main test-only follow-up is 39f8c20baade5d7209c6928da74edaed51443633; its Flutter analysis and all widget tests also passed in run 40369875925. That run's redundant APK build subsequently completed successfully. Workflow 40369875925 published artifact `ahl-el-kheir-flutter-android-debug-apk` (artifact ID `12671918522`, 102,395,467 bytes; expires 2026-11-25). Its source commit is `39f8c20baade5d7209c6928da74edaed51443633`; the current main head is `91f42d83988c42c7664c85bf7099891440901c65`, and the salary-advance verification workflow also passed at that head. These are CI results only, not local XAMPP/API or physical-device acceptance. App Dart source is unchanged from the successful APK build above.
- No local XAMPP/API/database or physical-device acceptance is claimed. Do not ask the user to test until the latest workflow result is checked and final pull/install instructions are ready.


## 2026-10-26 — CI trigger noise reduction

- Root cause: `.github/workflows/php-syntax-check.yml` (workflow display name `Salary advance verification`) triggered on every push to `main` and every pull request, including documentation-only commits. This created redundant parallel verification runs during documentation synchronization.
- Fixed in commit `e34aa69b477d76783df90b6d52f87808351520ed`: both push and pull-request triggers now filter for PHP/API/module/tool/config/database/language/assets and relevant root PHP/SQL/config changes, including the workflow file itself. Manual `workflow_dispatch` remains available.
- Existing runs already started before this trigger change were not cancelled by this edit and may still finish. The new filter prevents documentation-only commits from starting more PHP verification runs. Do not claim the active-run queue is cleared until the Actions page confirms it.


## 2026-10-26 — Native accounting disbursements route

- Audited the shared native-tool route list against `mobile/ahl_el_kheir_app/lib/main.dart`. The role tools exposed `modules/accounting/disbursements.php` as a native tool, but `openNativeTool()` had no corresponding route, so selecting it showed the generic “not available natively yet” message instead of opening the already-implemented native disbursement page.
- Fixed in commit `4444397352d8cb95316e43eb76e5f10e355fb207`: the route now calls `openAdminDisbursements()` and applies the same explicit allowed-role list as the existing `disbursements` route key (`admin`, `financial_manager`, `general_manager`, `accountant_staff`, `nanny`). The API remains authoritative for scoped data and operation capabilities.
- Android workflow `40372872420` later completed successfully for this exact source commit; details are recorded in the verification entry below.
- CI noise fix: commit `e34aa69b477d76783df90b6d52f87808351520ed` limits PHP verification to relevant source paths; the workflow itself passed in run `40372688782`. Documentation-only commits no longer match its trigger paths.


## 2026-10-26 — Native disbursements build verification completed

- Android workflow `40372872420` completed successfully for source commit `4444397352d8cb95316e43eb76e5f10e355fb207`: Dart analysis PASS, Flutter tests PASS, debug APK build PASS, artifact upload PASS.
- Artifact: `ahl-el-kheir-flutter-android-debug-apk`, ID `12673971537`, size `102,393,732` bytes, expires `2026-11-25T18:00:37Z`. [Workflow run](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40372872420).
- Build log confirms the APK was compiled with `API_BASE_URL=http://192.168.18.9:8081/AhlElKheir/api/v1` and `MOBILE_BUILD_REVISION=4444397352d8cb95316e43eb76e5f10e355fb207`. The artifact also contains `mobile-build-info.txt` with build provenance.
- This verifies CI build/test only. It does not prove that `192.168.18.9:8081` is reachable from the user's phone, that Apache/API/database are healthy, or that on-device sign-in succeeds. Do not describe those as tested.


## 2026-10-26 — Native personal-profile tool routing

- Confirmed `mobile/ahl_el_kheir_app/lib/profile_page.dart` and authenticated `api/v1/account/profile.php` / `api/v1/account/avatar.php` already implement personal profile editing and photo upload/removal. However, the staff role-tool tile used `modules/users/profile.php`, which was not marked native and had no matching `openNativeTool()` route; tapping it therefore fell through to the native-pending notice despite the existing page.
- Fixed in source commits `3f5ea08337788168e9dee84f56c959137be2b7d7`, `22467e9a7be468824a4a303ea2dd76159345c9ce`, and `535c6d4899537f8ffe2aea2e5687ff1c5712fc22`: mark the profile tool IN APP, route it to `openMyProfilePage()`, and add a widget regression test for the label and callback.
- Flutter analysis and widget tests completed successfully in workflow run `40375023649` for final source commit `535c6d4899537f8ffe2aea2e5687ff1c5712fc22`; the Android debug APK build was still in progress at the time of this update. A redundant earlier run `40375002234` was triggered by the intermediate per-file commit and also passed analysis/tests; do not distribute its APK because it predates the final regression test.
- Do not claim the final APK build passed until run `40375023649` completes. No local XAMPP/API or physical-device acceptance is claimed.

## 2026-10-27 — Profile APK verified; role-tool audit findings

- Workflow [40375023649](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40375023649) passed Dart analysis, Flutter widget tests, debug APK build, provenance, and artifact publication for source `535c6d4899537f8ffe2aea2e5687ff1c5712fc22`.
- Artifact `ahl-el-kheir-flutter-android-debug-apk`, ID `12675101498`, size `102,389,889` bytes, SHA-256 `bc89c3eaed4bbb4c2d0b84aac0dac0c1b09f803ac587884660734d10104659d6`, expires 2026-11-25. The embedded build provenance records API URL `http://192.168.18.9:8081/AhlElKheir/api/v1` and the source revision above. Do not use intermediate workflow `40375002234` as the final artifact because it predates the regression test.
- The personal-profile role-tool tile is now labelled IN APP and routes to `openMyProfilePage()`; widget regression test and complete build passed.
- Audit found 17 distinct role-tool paths that remain NOT READY natively: GM reconciliation, sponsor win-back, incentive policy, GM salary-advance waiver, deputy-GM groups, opening balance, assigned nannies, chart of accounts, ordinary FM transaction review, salary portal, group disbursements, Fina payment report, nanny returned funds, available families, contract builder, payroll reversal, and project create/edit form. Similar-looking native pages are not sufficient proof of parity: admin-only native chart-of-accounts access does not match the finance-role web page; native FM review queue is a different workflow from ordinary transaction review; and general family/project screens do not implement the exact assignment/form flows. Do not mark these paths native until exact authorization and behavior are implemented and tested.
- The login screen supports changing the API base URL and testing it. The APK default remains the LAN address above; CI cannot prove that it is reachable from the phone. Local XAMPP/API/database and physical-device acceptance remain unverified.

## 2026-10-27 — Native GM reconciliation completed

- Added `api/v1/accounting/gm_reconciliation.php`: bearer-authenticated GET-only read endpoint returning posted-ledger monthly summaries, monthly disbursement totals, open batches, returns, and voided batches. It performs no schema DDL and no financial mutations.
- Added `mobile/ahl_el_kheir_app/lib/gm_reconciliation_page.dart` and connected the native route.
- Role-tool visibility now matches the existing report permission boundary: admin, General Manager, Vice General Manager, and Financial Manager; Accountant no longer sees the tool because the web report guard denies it.
- API integration run `40450633032` passed PHP syntax and disposable-MariaDB tests for GM/FM access, non-GET rejection (405), and Accountant denial (403).
- Flutter run `40450764757` passed analysis, 140 tests, Android debug APK build, build provenance, and artifact publication. Source revision `82fd2570793e794b99fe9bf55f878596107b124c`; artifact ID `12710033148`; SHA-256 `87a816236f9648087c4bef0b3822947f7e2a98e53ccf4f4cb734df8ea936ff47`.
- No migration was added. Local XAMPP/API and physical-device acceptance remain pending.
- Continue with the next audited role-tool gap only after inspecting its exact web authorization, source workflow, and schema dependencies.

## 2026-10-27 — Native sponsor win-back completed

- Added `api/v1/administration/winback.php` and `mobile/ahl_el_kheir_app/lib/sponsor_winback_page.dart`, including queue, case detail, contact logging, decline/reopen, reactivation, prior-family coverage, and available-family reference.
- Preserved source behavior and reactivation attribution by reusing `wb_mark_returned()`; immediate incentive notice is attempted after the successful transition.
- Native role visibility and server authorization match the existing page guard: admin, General Manager, Vice General Manager, administration. Accountant access is denied.
- API run `40452694704` passed PHP syntax and full disposable-MariaDB integration for queue eligibility, duplicate-open rejection, contact logging, detail, decline/reopen, successful reactivation attribution, and duplicate-return rejection.
- Flutter run `40452694443` passed analysis, 141 tests, Android debug APK build, provenance, and artifact publication. Source `d6cab6772b3e2683434a1bb927d62ec55c5be9c6`; artifact ID `12710904687`; SHA-256 `eaf1ecc57262b1a71329dbcdb85dfb23121d478912a81013b6d3225df3e71fd8`.
- No migration was added. Local XAMPP/API and physical-device acceptance remain pending.
- Next: inspect and implement the Incentive Policy native workflow from the current web implementation, without assuming policy or approval rules.
