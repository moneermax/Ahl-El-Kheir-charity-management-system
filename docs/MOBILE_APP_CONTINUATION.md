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

- User confirmed the phone-testing host is laptop LAN IP `192.168.18.9` and Apache port `881`, matching the prior QR-scanner setup. Flutter default and manual workflow default now use `http://192.168.18.9:881/AhlElKheir/api/v1`; this is for the current local network only and can change if the IP changes.
- Reworked the Flutter login and signed-in shell to use the website's navy palette (`#0A1F44`, `#1F4E8C`), Arabic RTL-first branded card layout, improved field/button styling, and the actual organization logo source. The build workflow copies the existing website asset `assets/img/logo.jpg` into Flutter assets before dependency resolution/tests/build; it does not create a replacement logo.
- Updated API URL does not itself prove connectivity or successful login. Phone and PC must share the network, Windows Firewall must permit Apache on port 881, and the URL must be reachable from the phone. The `mobile_api_sessions` migration is still not confirmed applied to `ahl_el_kheir`; do not use real credentials until migration and local API/runtime/security checks pass.
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
