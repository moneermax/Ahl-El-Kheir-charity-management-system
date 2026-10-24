

## 2026-10-24 — New active task: cross-platform mobile app

The active requirement is a polished Flutter app for Android/iOS, Arabic/English, connected to the existing procedural PHP backend through a secure versioned API. XAMPP remains the local development environment; InfinityFree Free is the current public test host but may block native API clients. Do not bypass host security controls; use an API-compatible HTTPS host for remote mobile testing if needed. Continue from `docs/MOBILE_APP_CONTINUATION.md`.

The initial API auth source slice is on main: `api/v1/_bootstrap.php`, `api/v1/auth/login.php`, `api/v1/auth/me.php`, `api/v1/auth/logout.php`, and migration `database/migrations/2026-10-24_mobile_api_sessions.sql`. Source has not yet been PHP-linted or runtime-tested, and the migration has not been applied. Do not connect a Flutter client or run the endpoints before local syntax/runtime verification. Existing `user_sessions` remains for browser sessions and is intentionally not reused for mobile bearer tokens.
