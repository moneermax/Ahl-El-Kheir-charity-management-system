

## 2026-10-24 — Mobile application/API foundation started

New active project requirement: a polished cross-platform Flutter app for Android/iOS with Arabic/English language switching, using the existing PHP/MariaDB system as the authoritative backend. Hosting plan: XAMPP for local development; InfinityFree Free for public development/testing, with the explicit limitation that its browser-verification layer may block native API clients. Do not bypass host controls; keep the API portable and use an API-compatible HTTPS host for remote mobile tests if required.

Initial source slice adds a versioned PHP JSON API authentication foundation and dedicated `mobile_api_sessions` migration. It deliberately does not repurpose `user_sessions`, does not add business-data endpoints, and does not alter existing browser login code. See `docs/MOBILE_APP_CONTINUATION.md`. **Status: source added; PHP lint, local runtime, migration application, security acceptance, and Flutter work are not yet verified.**
