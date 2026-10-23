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


## UI refinement — 2026-10-22

The assistant widget now omits the two introductory privacy notices from the panel, no longer renders source filenames below answers, shows a Font Awesome comments icon beside the launcher text, and starts with the Google-consent checkbox checked. The user's checkbox choice is retained between messages instead of being reset after each submission. The endpoint still performs local-first retrieval and blocks internal/sensitive prompts from Google; the checkbox does not bypass those filters. Source commit: `5149e20b6e42a393ca59fd6d84cec12edaf08372`. PHP lint and browser regression checks for this UI refinement are pending.


### 2026-10-22 — Gold launcher + chat-history assessment
The floating assistant launcher now uses a gold gradient, dark text, stronger shadow, brighter hover state, and visible keyboard-focus outline. Source commit: `f2e92ac73692ac95a340c338811f565ed3839df3`. PHP lint and browser verification remain pending.

Persistent per-user chat history is not implemented yet. The assistant currently does not store conversations. The canonical `database/ahl_el_kheir.sql` dump is approximately 6.4 MB and the current repository reader cannot retrieve its contents, so the exact `users.id` type and FK compatibility could not be verified. Before implementing the history migration, inspect the actual local schema and confirm the user primary-key definition; do not guess it or create tables at runtime. Intended boundary: users may view only their own conversations; history should be loaded through authenticated, CSRF-protected server endpoints and should not be sent to Gemini as context.

### 2026-10-22 — Persistent assistant history implementation
The launcher gold styling is committed (`f2e92ac73692ac95a340c338811f565ed3839df3`). Persistent history source is now implemented in `includes/ai_assistant_widget.php` and `modules/ai_assistant/chat.php`: users can open their saved conversation list, load prior exchanges, and start a new conversation. Server queries scope list/load/continue operations to the authenticated `Session::getUserId()`; a submitted conversation ID is never trusted without an owner-scoped lookup. The current question alone may be sent to Gemini under the existing filters/consent; saved history is not sent as Gemini context.

Migration: `database/migrations/2026-10-22_ai_assistant_chat_history.sql`. Local `SHOW CREATE TABLE users` verified `users.id` is `INT(10) UNSIGNED`, matching the migration FK. The migration is explicit and no runtime DDL is added. It must be applied to `ahl_el_kheir` before testing chat history. Runtime verification is still pending: PHP lint both assistant files, apply migration, verify two separate users cannot see/load/continue each other's conversations, verify history survives sign-out/sign-in, and test local-answer plus not-found/unconfigured-Gemini response persistence. Preserve existing untracked local files. Do not configure paid billing.


### 2026-10-22 — Persistent assistant history implementation
The launcher gold styling is committed (`f2e92ac73692ac95a340c338811f565ed3839df3`). Persistent history source is now implemented in `includes/ai_assistant_widget.php` and `modules/ai_assistant/chat.php`: users can open their saved conversation list, load prior exchanges, and start a new conversation. Server queries scope list/load/continue operations to the authenticated `Session::getUserId()`; a submitted conversation ID is never trusted without an owner-scoped lookup. The current question alone may be sent to Gemini under the existing filters/consent; saved history is not sent as Gemini context.

Migration: `database/migrations/2026-10-22_ai_assistant_chat_history.sql`. Local `SHOW CREATE TABLE users` verified `users.id` is `INT(10) UNSIGNED`, matching the migration FK. The migration is explicit and no runtime DDL is added. It must be applied to `ahl_el_kheir` before testing chat history. Runtime verification is still pending: PHP lint both assistant files, apply migration, verify two separate users cannot see/load/continue each other's conversations, verify history survives sign-out/sign-in, and test local-answer plus not-found/unconfigured-Gemini response persistence. Preserve existing untracked local files. Do not configure paid billing.

## 2026-10-22 — Arabic Nanny retrieval and voice diagnostic follow-up

The user tested the assistant repeatedly. The Arabic post-login question now returns a local-guide answer, proving that local retrieval can work. The question “كيف أستطيع عرض كل المجموعات المخصصه لي” missed the English-only Nanny guide and fell through to the unconfigured Gemini response. Commit `1e0d98874e95b85d6454f007bc89483fb9f24b66` adds reviewed Arabic navigation instructions for the Nanny's assigned groups and all-families pre-verification page. Commit `cea8e775842f0f7a18ed7d790e5099fa9cbb7fac` normalizes Arabic ta marbuta in retrieval and adds Arabic internal-workflow terms to the external-AI blocklist, so Arabic group/workflow questions are not sent externally after a local miss.

Voice input is still not runtime-verified. The old error handler hid the browser's actual SpeechRecognition error code. Commit `57d5cfeb982f9e24459eb79c3fe6d1cf88d12844` now displays that code and suggests checking microphone permission/local Arabic language-pack availability. This is diagnostic instrumentation, not a claim that voice is fixed. On the next pull, test the Nanny groups question once and check the microphone once; record the exact speech error code. Do not switch to network-based speech recognition without an explicit privacy decision.

Chat-history migration has been applied locally and both assistant PHP files passed local syntax checks before this follow-up. The new follow-up commits still require local PHP lint and browser retesting. Preserve untracked local files and use the existing `ahl_el_kheir` database only.

## 2026-10-22 — Launch-readiness follow-up: deterministic Nanny answer

The user emphasized that ordinary staff must not need to diagnose technical errors. The Nanny groups question continued to miss the local guide despite the reviewed Arabic paragraph. Commit `dc0e7640fe98cb134590c0ec3e314c80133e16f4` adds a deterministic local answer for the common Nanny assigned-groups question and normalizes role codes before section selection. The response explains both “توثيق المجموعات والعائلات” and “التحقق المسبق للأسر”; it does not invoke Gemini or send the question externally. This source change is committed on main but has not yet been linted or browser-tested locally.

Voice root cause is now evidenced as browser error `language-not-supported` for on-device Arabic recognition. Do not show technical error codes to normal users in a launch build. At the time of this finding, online speech recognition was awaiting owner approval. The owner subsequently approved it on 2026-10-22 with mandatory clear consent before audio processing and a typing fallback; see the implementation checkpoint below. Do not remove that consent gate or switch to unannounced audio processing. Keep text chat functional at all times.


## 2026-10-22 — Online voice input consent implementation

The project owner explicitly approved online Arabic speech recognition, provided that users clearly consent before audio processing and can type instead. Commit `18bf40af2f905b5509396d92e17374c0c79ecd7c` updates `includes/ai_assistant_widget.php` to use the browser's online SpeechRecognition path where available, instead of requiring on-device `processLocally` support. The first microphone click opens a clear Arabic/English consent panel; only the explicit “Agree and start voice input” action starts recognition. Consent is held in page memory only and is requested again after reload. Declining closes the prompt and preserves typing. The transcript is inserted into the editable question field and is not submitted automatically. User-facing failures are simple and do not expose browser error codes.

Privacy boundary: speech audio is handled by the browser's speech-recognition implementation/provider; it is not posted to the Ahl El Kheir PHP assistant endpoint by this code. Text submission continues through the existing local-first retrieval, per-question Google text consent and internal/sensitive-question restrictions. Speech support remains dependent on browser/provider availability and permissions.

**Verification status: source committed; local PHP lint, browser consent/decline/recognition tests, Arabic transcript test, and text fallback are still required.** Use the existing local application and `ahl_el_kheir` database. Do not claim runtime-verified until the user confirms those checks.


### 2026-10-22 — Voice recognition failure diagnostics
After the owner reported that online recognition still failed after consent, source inspection found the widget mapped every non-permission recognition error to the same generic message, hiding the browser event's actual error category. Commit `753a3b225c1a992bcb3a6b1ce627ea365fef3092` maps common Web Speech API categories (network, no speech, microphone capture, service unavailable, unsupported language, blocked permission, aborted) to plain Arabic/English guidance. The underlying category is logged to the browser console for diagnosis, while no raw browser code is shown to staff. This is a diagnostic improvement, not proof that recognition works. Pull main and retry once; report the specific user-facing message. If it still fails, inspect the browser console warning `Ahl El Kheir voice recognition error:` and provide only the error category (not unrelated page data). Local PHP lint and browser verification remain pending.
## 2026-10-22 — Direct Gemini audio transcription implementation

The owner clarified and approved sending the actual recorded audio to Google's Gemini API for transcription, with explicit consent before recording and transmission. This replaces browser-vendor Web Speech recognition; the browser now records a short clip with MediaRecorder and posts the audio to the authenticated PHP endpoint.

Source commits:
- `d5d91e478235c323d10204762c802f3cb9cf2ca3` — adds the `transcribe_audio` action to `modules/ai_assistant/chat.php`, using the existing server-side Gemini key, strict MIME/size checks, per-session rate limiting, TLS verification, and a transcript-only response.
- `d30efdbb97223f483dae9285027584a025637f61` — changes `includes/ai_assistant_widget.php` to ask consent before recording, record up to 20 seconds, send audio to the PHP endpoint for Gemini transcription, and insert the transcript into the editable question box without auto-submitting it.

The recording is not written to chat history; only the user's edited/sent text and assistant response follow the existing history behavior. The API key remains server-side. The server must have `GEMINI_API_KEY` or the documented hosting configuration entry set; no paid billing should be enabled.

**Verification is pending:** pull `main`, PHP-lint both assistant files, then test consent decline, microphone permission denial, recording stop/20-second cap, Arabic and English transcription, editable transcript/no auto-submit, API unavailable/unconfigured errors, and typing fallback on `http://localhost:8081/AhlElKheir/` using the existing `ahl_el_kheir` database. This implementation has not been runtime-verified, and no local browser/network test was possible from the repository editing session.


## 2026-10-22 — Gemini audio transcription diagnostic follow-up

The user-approved MediaRecorder → Gemini transcription path reached the endpoint but returned the generic upstream-failure response in the browser. Source audit found the endpoint was discarding both cURL transport diagnostics and Gemini's structured error response, preventing a root-cause diagnosis. Commit `130af192c49aafae11c1369e7e884e9367016ac8` now logs the HTTP status, cURL errno/error, and a bounded Gemini error status/message to the PHP error log while keeping the user-facing response generic and never logging the API key or audio payload. It also removes the undefined `$lang` reference from the transcription prompt, using one language-neutral bilingual prompt.

**Verification is pending.** The repository connector cannot run PHP lint or exercise the user's local Apache/PHP/Gemini request. Pull `main`, run `D:\\xampp\\php\\php.exe -l D:\\xampp\\htdocs\\AhlElKheir\\modules\\ai_assistant\\chat.php`, then make one short consented recording. If it fails, inspect `D:\\xampp\\apache\\logs\\error.log` for lines beginning `[Ahl El Kheir AI transcription]` and share only those lines; do not share the API key or audio. This is diagnostic instrumentation, not a claim that transcription now succeeds.

### 2026-10-22 — User-facing Gemini transcription failure classification

The owner reported an application JSON response with HTTP 502 from the audio transcription request. Source inspection confirms this status is emitted by `modules/ai_assistant/chat.php` when the outgoing Gemini request fails or Gemini returns a non-2xx response; it is not evidence of an Apache gateway failure by itself. The underlying provider/network cause is not yet proven because the response body and PHP error log were not available.

Commit `3b7cc562c57b87533678146412cf0f44f5b1ca7c` classifies failures into plain Arabic/English guidance for connection failures, setup/permission failures, temporary rate limits, unavailable models, and rejected recording formats. Detailed provider diagnostics remain server-side; API keys and audio are not logged. This improves diagnosis and user guidance but does not claim transcription is fixed. Next verification: pull `main`, make one short consented recording, and report only the friendly error text shown. Then correct the evidenced configuration/network/API issue. Local PHP lint and runtime verification remain pending.

### 2026-10-22 — Root cause found: retired default transcription model

The user reproduced the message “The transcription model setup needs administrator attention.” The endpoint only emits that message for an upstream HTTP 404 / NOT_FOUND response. The committed source default was gemini-2.5-flash. Current official Google documentation says Gemini 2.5 Flash was shut down for the Gemini Developer API on October 16, 2026, and documents gemini-3.5-transcribe as the current speech-to-text model. See https://ai.google.dev/gemini-api/docs/transcribe and https://firebase.google.com/docs/ai-logic/models.

Commit b4b7c6e7a3069a0c8d5eb254b37db9d9941f5927 changes the default model to gemini-3.5-transcribe, which Google documents as supported through the Generate Content API. This is the smallest evidence-based correction. A server-side GEMINI_MODEL environment variable or deployment hosting.php value can override the default; if it explicitly names the retired model, it must also be updated to gemini-3.5-transcribe. Local PHP lint and one real-browser transcription test are still pending, so do not call this runtime-verified yet.



## 2026-10-22 — Gemini transcription API contract correction

The user tested the updated dedicated model and received “No usable transcript was returned.” The endpoint had been changed to `gemini-3.5-transcribe` but still called the legacy `generateContent` endpoint and parsed the legacy `candidates[0].content.parts[0].text` response. Official Google documentation specifies that Gemini 3.5 Transcribe uses `POST /v1beta/interactions`, accepts audio content with `type`, `data`, and `mime_type`, and returns the transcript in `output_text` (or text content within response steps). This explains the observed successful upstream response with an empty transcript under the old parser.

Commit `7f1ab66a8e8b386eca3bfa4d0482138d82658241` changes the audio request to the Interactions API, uses its `transcription_config.mode=smart`, maps browser `audio/mp4` to the API's documented `audio/m4a` MIME type, and reads `output_text` with a steps-content fallback. It preserves explicit consent, the editable composer, server-side API-key handling, existing size/rate checks, and typing fallback. Official reference: https://ai.google.dev/gemini-api/docs/transcribe and https://ai.google.dev/api/interactions-api-v1.

**Verification status: source correction committed on main; local PHP lint and one real-browser Arabic/English transcription test are still pending.** Do not claim the transcription path is fixed until the user confirms that words appear in the editable composer without automatic submission. If the next test fails, diagnose the exact new user-facing response and existing server log evidence before changing code again.


### 2026-10-22 — Gemini response-shape diagnostic (not yet resolved)
The user tested the Interactions API change in both Arabic and English; the endpoint still returned the “No usable transcript was returned” message. This proves the latest response parser did not find transcript text, but does not yet identify whether the provider returned a non-final interaction, a different response shape, or empty output. Do not guess another request format.

Commit `1b733cc0fef5aeca86b3753230afced29429509d` adds safe server-side shape diagnostics when transcript text is empty: JSON validity, top-level keys, interaction status, output text length, and counts of `steps`/`output`. It does not log the audio, API key, or transcript. The user-facing message now distinguishes unexpected response shape from ordinary empty transcript. After pull and one short consented test, use the matching `[Ahl El Kheir AI transcription] Empty transcript response shape:` line in `D:\\xampp\\apache\\logs\\error.log` to select the next fix. Do not share any other log lines or secrets. PHP lint and local runtime verification are still pending; do not claim the feature is fixed.

### 2026-10-22 — Gemini Files API upload correction (runtime verification pending)

The user's next browser test returned “unexpected response” but the supplied Apache log excerpt contained only server startup notices, not the requested [Ahl El Kheir AI transcription] Empty transcript response shape: diagnostic. No conclusion was drawn from those unrelated log lines.

Review of Google's current Gemini 3.5 Transcribe REST examples identified a concrete request-flow mismatch: the documented REST transcription path uploads the audio with the Files API and then passes the returned file URI to POST /v1beta/interactions. The prior implementation sent inline data directly to Interactions. Commit 87c37e4238858b6e126382f41476db8f49929e18 now follows the documented two-stage upload flow, validates that the upload URL and returned file URI use HTTPS on generativelanguage.googleapis.com, then requests smart transcription using the file URI. It retains the existing consent gate, editable transcript behavior, CSRF/authentication, limits, and server-side API key.

Status: source correction committed to main; local PHP lint and one browser transcription test are still required. Do not claim the feature is fixed until the transcript appears in the editable composer and is not auto-submitted. For a failure, share only a matching [Ahl El Kheir AI transcription] diagnostic line, not general Apache startup messages or secrets.

The voice-consent prompt was also updated in commit f89eaf85bc3b91389ab1c11abe541c02a21abfb4 to disclose that Google may temporarily retain the uploaded audio file for up to 48 hours under its Files service. The app's chat history still does not save the audio; the returned transcript remains editable and is not automatically submitted. This disclosure accompanies, rather than replaces, the explicit consent prompt.


## 2026-10-23 — Text chat review; voice deferred

The general-text fallback default was updated from `gemini-2.5-flash` to `gemini-3.5-flash-lite` in commit `2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b`. The microphone control is disabled and labelled as future development in commit `75c52595eef9cd6dd2d8ab71756f06103601c1c8`.

Source review: text requests use authenticated, CSRF-protected POSTs, local-first role-limited guide retrieval, per-question Google consent for general fallback, internal/sensitive-question filters, and owner-scoped conversation history. Local answers do not call Google. The model can still be overridden by `GEMINI_MODEL` or `hosting.php`, which must be checked in the local environment.

Runtime verification is **PENDING** because this review could not access the user's local Apache/PHP/browser, database, or Gemini configuration. After pulling `main`, lint both assistant PHP files and verify a known local-guide answer, no-consent fallback behavior, consented eligible general fallback, internal/sensitive-question blocking, and private persistent history. Do not retry voice while it is deferred; do not call text fully verified until local checks pass.


## 2026-10-23 — Continue assistant conversation across page navigation

The user confirmed local-guide text answers work and requested the same active conversation to remain available while navigating between pages, plus automatic panel close when the pointer leaves the chat box (matching the sidebar's mouseleave behavior).

Commits `9db6c37945f2af5e775dc4f9bf651acee7d657b7` and `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb` update `includes/ai_assistant_widget.php`:
- Store the active conversation ID in tab-scoped `sessionStorage` when a response creates/returns it or when the user opens a saved conversation.
- On each page load, request that conversation through the existing authenticated `history_load` endpoint and repopulate the message panel while keeping the panel closed.
- The server already verifies conversation ownership against the signed-in user; if a restore fails, the UI displays a recoverable message and the user can open Chat history.
- Clear the stored active ID when the user explicitly starts New chat.
- Close the floating panel on `mouseleave`, and keep `aria-expanded` synchronized; explicit close still returns focus to the launcher.

No database/schema change. Source edits are committed on `main`; local PHP lint, navigation/browser behavior, account-switch edge case, and mouseleave UX are **not yet runtime-verified**. Test one local-guide exchange, navigate to another page, reopen the assistant and confirm the same transcript is restored; continue chatting and verify the new exchange stays in the same conversation. Confirm mouse leave closes the panel and launcher click reopens it. Also verify New chat remains a new conversation across navigation.

## 2026-10-23 — Text chat implementation task closed; voice deferred

The requested text-chat implementation work is closed at the source/documentation boundary. This includes local-first role-limited answers, the existing consent/filter rules for eligible general Gemini fallback, authenticated and CSRF-protected requests, user-scoped saved conversations, restoration of the active conversation across full-page navigation, explicit New chat behavior, and closing the floating panel when the pointer leaves it.

Relevant implementation commits:
- `2bc42b03c5d9f5430d0e6ce07a22f39a57811a7b` — default general-text fallback model changed to `gemini-3.5-flash-lite` (environment/hosting configuration can override it).
- `75c52595eef9cd6dd2d8ab71756f06103601c1c8` — microphone control disabled and marked as future development; voice is out of scope.
- `9db6c37945f2af5e775dc4f9bf651acee7d657b7` — persist/restore active conversation across navigation.
- `ca5a1d8bb34e0325a5ea2da0e67b16ea7fd723cb` — remember a conversation selected from history.

The owner confirmed that a local-guide text question works. Source review also confirms the history-load endpoint scopes access to the authenticated owner. No database/schema change was made for navigation continuity.

**Verification boundary:** this work is closed as an implementation task, but this session did not run local PHP lint or browser acceptance and therefore does not claim full runtime verification. If the owner has not already performed the checks locally, verify with the existing XAMPP application: lint `includes/ai_assistant_widget.php` and `modules/ai_assistant/chat.php`; ask a known local-guide question; navigate to another page and confirm the same transcript restores; send a follow-up and confirm it remains in that conversation; confirm New chat stays separate across navigation; confirm mouseleave closes the panel and the launcher reopens it. Check the effective `GEMINI_MODEL`/hosting override only if eligible general fallback is part of the acceptance test.

Do not reopen this completed implementation task without a concrete regression. Do not retry voice transcription as part of text-chat acceptance; voice remains deferred. The next chat session should start by reading the required canonical documents and identifying the next genuinely open project task rather than assuming another AI-assistant change is needed.

## 2026-10-23 — Account-isolated conversation restore and VGM assignment guidance

A user reported that a different account in the same browser received the stale-conversation restoration error, then asked how to distribute families to nannies and received the generic internal-procedure block.

Source review identified two separate causes:
- The widget used one tab-scoped `sessionStorage` key for all signed-in users. The server correctly enforces conversation ownership, so an account switch can make the previous account's conversation ID fail the owner-scoped `history_load` lookup. The restore failure handler also left the failed ID in storage, causing repeat attempts.
- Local retrieval had a deterministic Arabic shortcut for viewing nanny-assigned groups, but no corresponding verified VGM group/nanny assignment answer. The approved `modules/deputy_gm/groups.php` source confirms VGM-only group management, adding eligible children, and assigning a nanny to a group.

Fixes committed on `main`:
- `ad6d2c7ede03b7e665ff11b74af55949818b5a76`: namespace the active-conversation storage key by the server-rendered signed-in user ID; clear the active pointer after a failed restore so the widget does not retry the same invalid ID on every page.
- `fb030a08ba65093b51cf31cba4979189457e87bf`: document the VGM group/nanny workflow in the approved employee guide.
- `4fe4a98dc3bcf40e5122c04f069b1f3a40cb99dc`: provide a deterministic local answer for VGM/vice-GM roles only. It explains that assignment is to a group, children must be active and have active sponsorship, and current-month disbursement restricts membership edits. Other roles do not receive the VGM-only instructions.

No schema or database changes. Source edits are committed, but PHP lint and real-browser testing have not been executed by this repository-editing session. Required acceptance: switch between two accounts in the same browser tab and confirm no cross-account restore attempt/error; confirm each account can still select its own saved history; as VGM, ask `كيف يمكنني توزيع الأسر على الحاضنات` and verify the documented local answer; as a non-VGM role, verify the VGM-only procedure is not returned. Also test a normal same-user navigation restore and a failed restore followed by a page reload. Do not claim runtime PASS before these checks.
