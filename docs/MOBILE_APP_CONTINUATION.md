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

- Added authenticated `POST /api/v1/projects/milestone_action.php` for edit/delete. It accepts only `edit` or `delete`, requires both project and milestone IDs, scopes the milestone lookup/update/delete to that project, locks the project and milestone rows during the transaction, permits only `project_supervisor` accounts with an active primary-supervisor or operations assignment, and blocks closed projects. Authorization was corrected to match the existing web edit/delete gate: no admin bypass and no additional approval/lifecycle restriction beyond the web module's closed-project check. Edit follows the web module's existing editable fields and derives milestone status from completion percentage. No schema change or runtime DDL. The first two Android widget runs failed at the new edit-dialog expectation. The test now targets stable control keys and ensures the controls are visible before tapping; the subsequent run was still pending at the latest check. A separate `can_manage_milestones` capability was added to project detail so edit/delete visibility follows the web permission boundary (assigned project supervisors, non-closed projects) rather than the distinct create capability. The latest Android and PHP/database runs for this follow-up remain pending; no green result should be inferred.
- Project detail now includes milestone IDs; the Flutter detail page exposes Edit/Delete only when the server's milestone capability is true and the action callback is wired. Delete requires confirmation; successful actions refresh the detail. Added a widget test for action payloads.
- Source commits: API `329291d150eec4cf0315fba1d81c189e90e0ce32`; PHP lint workflow `bfb0799ffe7645fefcce7e6b5d85e153e543c824`; API wiring `7915b0767bb0b3bb94919463c86fcbd88bd656f4`; portfolio wiring `1951d3ba0688a3af9ec88899b44714f7dc16d2a0`; home wiring `8f9f0cbc56bb2a4c47b173de31ea8274b0877dd4`; UI `3e837fef13e1c04c62605944bbcb238e99fe6fc3`; milestone ID response `99e95e09e4dc069dd6ea32688c720eccc56c3bc2`; widget test `88d0df39cf92efcb3919ac0fea5d6a373fa7e2b7`.
- Android workflow `40120100653` was in progress at this checkpoint. Do not call analyzer, widget tests, APK build or artifact publication passed until it finishes. The PHP/database workflow for the new endpoint and live local Apache/MariaDB role-and-record-scope acceptance also remain unverified. Do not request physical-device testing yet.
