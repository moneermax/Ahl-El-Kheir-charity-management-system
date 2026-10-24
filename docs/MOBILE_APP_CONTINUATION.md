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
