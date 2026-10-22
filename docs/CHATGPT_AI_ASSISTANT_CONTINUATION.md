# AI Assistant Continuation — Ahl El Kheir

Updated: 2026-10-22
Branch: main
Status: Initial source implementation committed; syntax CI and local runtime acceptance pending.

## Goal

Add a shared, Arabic-first bilingual assistant to the existing procedural PHP application. The assistant must prefer verified local guidance, respect the signed-in user's role, and clearly admit when it cannot find a verified answer.

## Source files

- includes/ai_assistant_widget.php — shared floating panel, responsive layout, text chat, per-question Google consent, and optional on-device speech dictation.
- includes/footer.php — includes the widget only for logged-in sessions.
- modules/ai_assistant/chat.php — authenticated JSON endpoint.
- .github/workflows/php-syntax-check.yml — PHP lint coverage for the two assistant PHP files.

No database/schema changes, migrations, chat-history tables, or business-operation actions were added.

## Security and privacy boundary

- The endpoint accepts POST only, requires an authenticated session and the session CSRF token, limits question length/body size, and applies a per-session request rate limit.
- The user's active role is read from the database; the browser cannot submit a role or user ID to influence retrieval.
- First retrieval source is docs/AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md. Retrieval is limited to the all-employees section and the matching role section. This is a conservative first-stage knowledge source, not full source-code/schema retrieval.
- Local guide text is returned by the PHP endpoint and is never sent to Google.
- Gemini is only considered after explicit per-question opt-in and only when local retrieval found no answer. Questions matching internal-workflow/business terms or basic personal/sensitive-data patterns are blocked from Google.
- The Gemini prompt receives only the user's eligible general question. It does not receive project documentation, database rows, application source, user role, or user identity.
- API key stays server-side and is read from GEMINI_API_KEY or the deployment-only config/hosting.php ai section. Never commit the key or put it in JavaScript.
- Free-tier use is subject to Google's quotas and terms. Do not enable paid billing for this integration. The application code cannot prevent charges if an operator deliberately configures a billing-enabled key.
- The assistant is read-only. It cannot mutate records, approve requests, execute SQL/PHP, or invoke arbitrary tools.

## Voice

The microphone is enabled only if the browser exposes on-device speech recognition via processLocally. The transcript is inserted into the editable text field before submission. Arabic uses the browser's ar-EG recognition locale when available; Sudanese dialect recognition is not guaranteed. If local recognition is unavailable, text chat remains available. Speech playback is not included in this first pass because the locality/privacy characteristics of available browser voices have not been verified.

## Known limitations / next work

1. Local answer retrieval is currently limited to the employee user guide and basic keyword matching; it is not semantic retrieval and may miss relevant passages.
2. The source guide is primarily English, so local answers can remain English even when the question is Arabic. Add reviewed bilingual knowledge entries before claiming full Arabic-first answer quality.
3. Gemini general-answer support has not been tested against the real InfinityFree host. Configure a free-tier key privately and verify HTTPS/cURL availability and quota behavior.
4. Confirm the configured model is available to the free tier at setup time; GEMINI_MODEL can override the default.
5. Run PHP 8.2 lint, inspect the triggered GitHub Actions run, then test at http://localhost:8081/AhlElKheir/ with the existing ahl_el_kheir database. Test role-section boundaries, CSRF rejection, unauthenticated rejection, rate limit, no-consent behavior, internal-question blocking, consent reset after each question, and on-device voice fallback.
6. Before wider deployment, strengthen sensitive-text filtering, add deterministic tests for retrieval and filtering, and review whether docs/ is publicly web-readable on the deployment. Do not add arbitrary source-code or database access.

## Acceptance boundary

Source changes are on main; no local PHP CLI, browser, live database, or InfinityFree runtime was available to this implementation session. Do not call the feature runtime-verified until the local/hosted checks above pass.


## Follow-up security finding — 2026-10-22

The repository root .htaccess only disables directory listings and there was no docs/.htaccess. Since the assistant reads internal project documentation from disk, docs/.htaccess was added with Require all denied to prevent direct HTTP access to internal documentation while preserving PHP filesystem reads. Verify this directive is honored on InfinityFree before deployment acceptance.


## Source review follow-up — 2026-10-22

A source audit found and corrected an unconditional not-found response that would have exited before the Gemini fallback. The endpoint now reaches the explicit-consent, general-question path after local retrieval misses. This is source-level correction only; it is not runtime proof. The latest relevant commit is 3033f565417aa516109f2328f50e136c64cae5e4.


## Local retrieval test finding — 2026-10-22

The first browser test asked in Arabic what to do first after signing in. The current keyword retriever missed the question because the employee guide described sign-in only in English. The canonical employee guide now includes a short, reviewed Arabic/English paragraph for the post-login dashboard check and required password change. Retest that exact Arabic question against the local guide before configuring Gemini. This change does not send guide content to Google and does not alter retrieval code or permissions.


### 2026-10-22 — AI assistant UI refinement
The assistant widget now omits the two introductory privacy notices from the panel, no longer renders source filenames below answers, shows a Font Awesome comments icon beside the launcher text, and starts with the Google-consent checkbox checked. The user's checkbox choice is retained between messages instead of being reset after each submission. The endpoint still performs local-first retrieval and still blocks internal/sensitive prompts from Google; a checked box does not bypass those filters. Source commit: `5149e20b6e42a393ca59fd6d84cec12edaf08372`. PHP lint and browser regression checks for this UI refinement are pending.
