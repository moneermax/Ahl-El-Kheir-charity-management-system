# Ahl El Kheir Flutter foundation

This folder is separate from the existing native sponsor QR scanner under `android/sponsor-qr-scanner`; that project is unchanged.

## Setup
1. Install stable Flutter and run `flutter doctor`.
2. From this directory run `flutter create --platforms=android,ios .` to generate platform scaffolding.
3. Run `flutter pub get`, `flutter analyze`, and `flutter test`.
4. Android emulator default API URL: `--dart-define=API_BASE_URL=http://10.0.2.2:8081/AhlElKheir/api/v1`.
5. Override `API_BASE_URL` for other development hosts. Never expose XAMPP directly to the internet; use HTTPS outside local development.

## Included
- Arabic-first login with Arabic/English toggle and RTL/LTR direction.
- Existing PHP API login, current-user session restoration, and logout.
- Bearer token storage through `flutter_secure_storage`.
- Minimal signed-in screen only. No business-module data is exposed.

## Not production-ready
The API still needs local runtime/security acceptance tests and persistent login rate limiting. Do not use real accounts or expose the API publicly until those gaps are resolved. This Flutter source has not yet been compiled or device-tested. Configure local HTTP transport deliberately; production must use HTTPS.
