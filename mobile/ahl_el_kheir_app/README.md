# Ahl El Kheir Flutter app

This Flutter app lives inside the existing Ahl El Kheir repository. It is separate from the native sponsor QR scanner under `android/sponsor-qr-scanner`.

## Current integrated features

- Arabic-first login with Arabic/English toggle and RTL/LTR direction.
- Authenticated sessions using the existing PHP API, secure bearer-token storage, session restoration, and logout.
- Notifications and mark-as-read.
- Project directory/details, milestones, and project progress updates.
- Sponsor directory/details, sponsorships/details, and sponsor-request actions.
- Supervisor directory, family directory/details, and transaction listing.
- HR employee directory/details, attendance viewing, leave-request workflow, and self-service salary statements limited to paid/accounting-posted rows.
- Nanny group verification and monthly preliminary family verification.

This is incremental mobile coverage, not yet full parity with every web module. Each feature must retain the existing web workflow's role and record-scope rules; do not expose a web action in the app until its API authorization and behavior have been verified.

## Build without installing Flutter locally

GitHub Actions builds the Android debug APK in the cloud. No Flutter or Android SDK installation is required on the Windows development machine.

1. Open [Build Ahl El Kheir Flutter Android APK](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/workflows/build-flutter-mobile-app.yml).
2. Open the latest successful run.
3. Download the `ahl-el-kheir-flutter-android-debug-apk` artifact ZIP and extract `app-debug.apk`.
4. Install the APK on an Android device only when the project readiness gate says device acceptance is due.

The workflow runs on relevant pushes to `main` and can also be started manually. For a manual run, set `api_base_url` to the intended API v1 base URL:
- Android emulator on the same computer: `http://10.0.2.2:8081/AhlElKheir/api/v1`
- Physical phone on the local network: use the computer's LAN IP and matching Apache port/path.
- Hosted server: use its HTTPS API URL.

The debug build permits HTTP for local development only. Netlify's static hosting alone is not a PHP/MariaDB backend for this application. Do not use HTTP for a hosted server, expose XAMPP directly to the internet, or use real accounts until API runtime and security checks pass.

## Verification boundary

GitHub Actions runs Flutter dependency resolution, static analysis, widget tests, Android debug APK compilation, and artifact publication. The latest employee-detail revision passed the Android workflow at [run 40115687291](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40115687291).

A successful build does not prove API connectivity, authorization against the live database, or production readiness. The mobile API migration must be applied only to the explicitly confirmed local development database, and API role/scope runtime acceptance plus deployment configuration remain outstanding. Do not treat the debug APK as a production release.

- Financial review queue (read-only): pending supervisor collections and the latest 20 approved/returned decisions for authorized financial-review roles. Approval/return and accounting posting are not exposed in this mobile slice; CI and live API acceptance are pending.
