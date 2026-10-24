

## 2026-10-24 — Active task: mobile application for Android and iOS

Continue the existing repository on `main`; do not rebuild the backend. Read `docs/MOBILE_APP_CONTINUATION.md`. The target is a polished Flutter app, Arabic/English, using a versioned JSON API in the existing procedural PHP backend. XAMPP is local; InfinityFree Free may block native API traffic, so do not bypass security controls and keep the API host-portable.

Initial API auth source files and migration were added in the latest mobile-foundation commit. They remain unverified until the user runs PHP 8.2 lint, manually applies the migration to the intended local DB, and tests login/me/logout and token revocation/expiry. Do not claim runtime success, do not add module endpoints before authentication is verified, and preserve the existing browser session flow.
