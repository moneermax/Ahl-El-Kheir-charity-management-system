# Ahl El Kheir Flutter app

This Flutter app lives inside the existing Ahl El Kheir repository. It is separate from the native sponsor QR scanner under `android/sponsor-qr-scanner`.

## Build without installing Flutter locally

GitHub Actions builds the Android debug APK in the cloud. No Flutter or Android SDK installation is required on the Windows development machine.

1. Open [Build Ahl El Kheir Flutter Android APK](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/workflows/build-flutter-mobile-app.yml).
2. Open the latest successful run.
3. Download the `ahl-el-kheir-flutter-android-debug-apk` artifact ZIP and extract `app-debug.apk).
4. Install the APK on an Android device.

The workflow runs on relevant pushes to `main` and can also be started manually. For a manual run, set `api_base_url` to the intended API v1 base URL:
- Android emulator on the same computer: `http://10.0.2.2:8081/AhlElKheir/api/v1`
- Physical phone on the local network: use the computer's LAN IP and the matching Apache port/path.
- Hosted server: use its HTTPS API URL.

The debug APK permits HTTP for local development only. Do not use HTTP for a hosted server, expose XAMPP directly to the internet, or use real accounts until API runtime and security checks pass.

## Included

- Arabic-first login with Arabic/English toggle and RTL/LTR direction.
- Existing PHP API login, current-user session restoration, and logout.
- Bearer token storage through `flutter_secure_storage`.
- Minimal signed-in screen only. No business-module data is exposed.

## Verification boundary

The cloud workflow runs Flutter dependency resolution, static analysis, the widget smoke test, and Android debug APK compilation. A successful build does not prove API connectivity, authentication runtime behavior, or production readiness. The API migration still needs to be applied to the explicitly confirmed local development database, and API security/runtime acceptance remains pending.
