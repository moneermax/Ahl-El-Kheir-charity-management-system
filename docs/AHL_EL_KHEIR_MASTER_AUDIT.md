

## 2026-10-24 — Mobile app/API work boundary

A new requirement supersedes the previous continuation item: build a Flutter Android/iOS client for the existing system, Arabic/English, preserving all server-side roles and record-level permissions. Do not rebuild the PHP system. Initial source-only API authentication files and a dedicated mobile-token migration have been added; browser session handling is intentionally untouched. No local lint/runtime or migration acceptance is yet evidenced. Details and acceptance checks: `docs/MOBILE_APP_CONTINUATION.md`.
