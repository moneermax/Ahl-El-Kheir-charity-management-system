# Sponsor Request Conversion Continuation

**Baseline:** 2026-10-29  
**Branch:** `main`  
**Status:** Core conversion flow locally verified; targeted success-toast duration fix committed and awaiting local verification.

## Agreed workflow

An assigned supervisor may mark a request as contacted without activating a sponsor. The explicit **تحويل إلى كفيل ومتابعة** action converts an actionable request (`new` or `contacted`) into an active sponsor, marks the request `converted`, records the created sponsor link and audit event, then redirects to the created sponsor detail page so the supervisor can continue the existing sponsorship workflow.

## Implementation

- `modules/sponsors/my_request.php`: supervisor-scoped request detail and explicit conversion action; successful conversion redirects to `modules/sponsors/view.php?id=<sponsor_id>`.
- `modules/sponsors/lib_sponsor_request_conversion.php`: transaction, row lock, field mapping, active sponsor creation, request transition and audit record.
- `database/migrations/2026-10-29_sponsor_requests_canonical_workflow.sql`: canonical request schema/workflow migration.

Commits:
- `78d74e55d0210dd4976aa38f928c5a41b48ae109` — explicit supervisor conversion workflow.
- `5af3b1c86b999546ccc5042ff7bfd7c7bbaa0040` — recheck assigned-supervisor ownership inside the conversion transaction under the request row lock. This closes the reassignment race between the page-level scope read and the conversion transaction.

## Verification boundary

The user has locally verified the core conversion path (contact status, successful conversion/redirect, converted request link, and access to the existing sponsorship workflow). The targeted 8-second success-toast behavior awaits local verification after pulling `main`. Duplicate-conversion rejection and unauthorized ownership isolation were subsequently verified by the disposable runtime harness. A true concurrent reassignment race remains unverified.

## Required local acceptance

1. Pull `main` and confirm the working tree has no unrelated local changes before testing.
2. Open an assigned `new` request as its supervisor; click **تم التواصل** and confirm it only changes the request status and remains on the request page.
3. Click **تحويل إلى كفيل ومتابعة** on a valid `contacted` request; confirm one active sponsor is created and the browser redirects to that sponsor's detail page.
4. Confirm the new sponsor page exposes the existing action for adding sponsorships/orphans and that the monthly amount is entered through the established sponsorship workflow; do not invent a separate amount field or workflow.
5. Confirm the original request is `converted` and links to the created sponsor ID.
6. Confirm a repeated conversion cannot create a second sponsor.
7. Confirm a supervisor cannot convert a request assigned to another supervisor, including if assignment changes between page load and POST.
8. Run PHP syntax checks for the changed PHP files and record the actual output.

User-reported local acceptance on 2026-10-29: contacted status, conversion redirect to sponsor 2859, converted request status/link, and access to the existing sponsorship workflow all passed. PHP 8.2.12 syntax checks passed for the request detail, conversion helper, and sponsor detail files. Duplicate-conversion and reassignment-race tests remain unreported.

The success flash after conversion now requests an 8-second toast; ordinary flash messages keep their existing default duration. This is source-committed but awaits local verification after pulling `main`. Commits: `ed1f4c75b55cb133b43a93ecc663df89665c878d`, `426697fac7aa16d0bbb0729b886f3fc301ff7fe9`, `04f45fa72ccc0c461f04a3fd2fd376f2b278f862`.


## 2026-10-29 — Toast-duration root cause and correction

The user reported that the conversion success toast still disappeared too quickly. Source inspection identified the exact cause: the same-page response handler in `assets/js/app.js` extracted `data-ak-flash` and `data-ak-message` but discarded `data-ak-duration`, then called `AKNotify.toast` without a duration. This bypassed the existing 8-second opt-in setting even though `includes/alerts.php` and `assets/js/notifications.js` supported it.

Correction committed to `main`: `cfb828cdb2db68a364512e5ac30c7ef3a6dacceb` — preserve `data-ak-duration` in the extracted flash object and pass it to `AKNotify.toast`.

**Verification boundary:** local browser acceptance is still pending; do not mark the toast fix runtime-verified until the user pulls this commit and retries a conversion. Sponsor SP-002860 / أمير علي طه is reported active with zero currently sponsored orphans and is the user's last suitable inactive test sponsor. Its prior state has not been reset by this remote repository session because the local MariaDB database is not accessible here. Before reset, inspect the linked `sponsor_requests` row and all existing references to the sponsor; preserve audit history and unrelated records. The intended reset is to remove only the conversion-created sponsor record if it has no dependent operational data, and return its original request to its pre-conversion actionable state with its conversion linkage/timestamps cleared. Do not run a guessed destructive SQL statement.


## 2026-10-29 — Sponsor conversion toast redirect lifecycle correction

The conversion POST form now opts out of the global same-page `fetch()` POST handler with `data-ak-native-post="1"`. Source tracing showed that the handler follows the conversion's cross-page redirect during `fetch`; the redirected sponsor-detail response renders and consumes the one-time session flash, then JavaScript navigates the browser to that URL a second time. This makes the flash lifecycle depend on the intermediate fetch response rather than the actual document navigation. Native POST/redirect/GET lets the sponsor-detail page render the `success_long` flash once and use its configured 8000 ms duration. Change committed to `main` as `6b398049b530e50d32ef364647eeaed10e02aeb5`. At the time of this source change, browser verification and PHP syntax checks were pending. PHP syntax and the isolated runtime harness have since passed; actual browser toast acceptance remains pending.


## 2026-10-29 — Sponsor conversion toast regression guard

The conversion feedback path was reviewed end-to-end across `modules/sponsors/my_request.php`, `includes/alerts.php`, `assets/js/notifications.js`, and `assets/js/app.js`.

Verified source contract:
- Conversion emits `success_long` and redirects to the created sponsor detail page.
- The conversion form opts out of the global same-page `fetch()` handler so the one-time session flash is rendered by the real redirected document, not consumed by an intermediate fetch response.
- `includes/alerts.php` maps only `success_long` to an 8000 ms toast duration.
- The normal page-load toast handler reads and forwards that duration; the shared in-place response handler also preserves it for other pages.
- SweetAlert2's toast timer receives the positive supplied duration instead of relying on its 3200 ms default.

Added database-free regression test: `php tools/test_sponsor_conversion_toast_contract.php`, wired into the PHP syntax workflow. This guards source wiring, not real-browser timing. Local browser acceptance remains pending; do not claim runtime verification until the deployed local app is exercised.


## 2026-10-29 — Sponsor conversion runtime verification update

The user ran `/opt/lampp/bin/php tools/run_sponsor_request_conversion_runtime_test.php` locally. PHP syntax validation passed, and the disposable-database runtime harness reported all 13 checks passing, including conversion creation/linkage, assignment-history and audit writes, duplicate-conversion rejection, and rejection of a supervisor acting on another supervisor's request. The generated test database was removed after the run; the production sponsor records were not used as test fixtures or modified by this harness. The toast source-contract test (7 checks) and authorization-contract test (10 checks) also passed. GitHub Actions run [#40880584416](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40880584416) passed all six jobs.

**Remaining boundary:** the toast's actual appearance and approximately 8-second duration have not been verified in a real browser session. The runtime harness does not prove a true two-process concurrent reassignment race. Do not claim either result as proven.


## 2026-10-30 — Non-individual sponsor edit follow-up

The company/organization conversion source audit found that `modules/sponsors/edit.php` previously required male/female gender for all sponsors and always ran individual supervisor resolution. The committed correction (`e2468966f5667a3b90c6d201f0b006e37ea49e96`) preserves `gender='organization'` for company/organization records, limits gender validation and supervisor resolution to individuals, and adds the legal-entity form option. Regression contract commits: `bcf388aac68e89f029d9c6579ae49fab5041902d`, `f1e3dc9ae2f841ea07175a9b832b5ec1f2ea84e4`; CI wiring: `0a69318d555ba0bce08643144643a0ea66f4533e`.

GitHub Actions run [#41010987431](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41010987431) passed all six jobs for the edit correction. Added `tools/test_sponsor_non_individual_conversion_contract.php` with 9 source assertions for the public form and conversion helper; CI now executes and syntax-checks it. Full workflow run [#41011212069](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41011212069) passed all six jobs, including PHP 8.2 lint, the new contract, MariaDB 10.4, and canonical/legacy sponsor-request migration validation.

**Still unverified:** database-backed company/organization conversion/edit round-trip and public browser acceptance. CI source checks are not a substitute for these. No active database migration or live sponsor mutation was performed. Next: safe isolated runtime coverage for non-individual conversion/edit preservation, then complete the public application → staff queue → conversion → sponsor detail acceptance. Do not repeat the already-verified individual request 8 flow.

## 2026-10-30 — Company/organization runtime conversion and edit persistence

Extended the disposable-database runtime harness to exercise both company and organization requests through the production conversion helper. For each type it verifies request conversion/linkage, preserved sponsor type, `gender='organization'`, null `supervisor_id`, no supervisor assignment history, and conversion audit. It then exercises the edit path's `recordSponsorAssignment($id, null, ...)` behavior and the identity/supervisor persistence fields; both entity types remain legal entities and unassigned after the round-trip. The uniquely named disposable database is removed after the test.

GitHub Actions run [#41012254620](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41012254620) passed all six jobs. PHP 8.2 lint and the runtime harness passed; CI logs explicitly show all company and organization conversion/edit assertions passing.

**Remaining:** actual authenticated browser POST acceptance is still outstanding. Complete public application → staff queue → conversion → sponsor detail → edit submission and verify the displayed and persisted values. Do not repeat the individual request 8 flow. No active database migration or live sponsor mutation was performed.


## 2026-10-30 — Legal-entity sponsor access after conversion

A follow-up authorization defect was found after company/organization conversion: legal entities intentionally have `gender='organization'` and `supervisor_id=NULL`, so the individual first-letter/gender supervisor matrix denied the converting supervisor access to the sponsor detail and edit pages.

Correction committed on `main`:
- `config/sponsor_assignments.php`: `supervisorCanAccessSponsor()` now grants access to a company/organization only when its `created_by` matches the requesting supervisor. It does not assign an individual supervisor or create assignment-history rows.
- `modules/sponsors/edit.php`: supervisor edit authorization now delegates to the same canonical helper used by the detail page, eliminating the divergent page-specific matrix check.
- Runtime regression coverage now checks that the converting supervisor can access the company/organization, another supervisor is denied, `created_by` records the processor, and `brought_by_user_id` remains NULL when no acquisition attribution was supplied.
- The edit contract test guards the shared authorization path and creator-only legal-entity scope; CI lints the authorization helper.

Acquisition incentive policy is unchanged: the processor identity in `created_by` is audit/record ownership only. Incentive attribution remains separate in `brought_by_user_id`; no incentive or accounting records are changed by this access fix.

**Verification:** GitHub Actions run [#41166572961](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41166572961) passed all six jobs: PHP 8.2 lint/source contracts, MariaDB 10.4 migration, sponsor-request migration, full-schema migration (including the expanded disposable-database sponsor conversion harness), HR catalogue migrations, and rescheduling service tests. The runtime harness passed creator-only access for both company and organization, denied another supervisor, confirmed processor `created_by` with NULL acquisition attribution, preserved NULL individual supervisor assignment/history, and removed its disposable database. Authenticated browser access remains unverified. No live sponsor record or production database was modified.

## 2026-10-31 — VGM legal-entity request routing

The accepted workflow is now implemented on `main`:

- Public individual applications continue to receive automatic supervisor assignment through the existing first-letter + gender matrix.
- Public company/organization applications remain unassigned (`assigned_supervisor_id = NULL`) on receipt.
- The VGM can either process an unassigned legal-entity request directly from the management queue or assign it to one active supervisor.
- Manual assignment is VGM-only, limited to company/organization requests in `new` or `contacted` state, and only succeeds while the request is unassigned. The request row and selected active supervisor are locked during the transaction.
- Assignment records an `ASSIGN_SUPERVISOR` audit event. After commit, the selected supervisor receives an in-app notification linking to the supervisor-scoped request page. Notification failure is isolated from the committed assignment.
- Once assigned, management-queue processing/status actions are hidden and server-side conversion is rejected. The selected supervisor follows up and converts through `my_request.php`.
- VGM direct conversion is restricted to unassigned company/organization requests. Individual management processing behavior is unchanged.
- Legal-entity sponsor conversion still leaves `sponsors.supervisor_id` NULL. When a supervisor performs the conversion, `created_by` provides the existing creator-only access to the resulting legal-entity sponsor; no individual assignment history or acquisition incentive attribution is created.

Implementation commits on `main` include the VGM assignment service, management queue routing controls, server-side processing restrictions, and regression coverage. No schema migration was required because `sponsor_requests.assigned_supervisor_id` already exists.

**Verification — CI + disposable runtime: PASS**

GitHub Actions run [#41185490815](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41185490815) passed all six jobs on commit `9aef240d938f3e2040ed61cec52184e242f413f4`:
- PHP 8.2 lint and all sponsor request source-contract assertions, including the real native POST conversion form.
- MariaDB 10.4.32 migration checks, sponsor-request migration checks, full-schema migration checks and the remaining workflow jobs.
- The disposable-database sponsor request runtime harness passed VGM assignment, audit event, post-commit notification/link, duplicate assignment rejection, management-queue conversion blocking after assignment, assigned-supervisor conversion, creator-only legal-entity access, VGM direct processing while unassigned, and denial of supervisor use of the VGM-only assignment operation. The uniquely named test database was removed.

No production sponsor/request records or live database were modified by this CI runtime harness. No migration was required.

**Remaining acceptance boundary:** authenticated local browser acceptance is still outstanding. Pull `main`, then verify one VGM direct-processing case and one VGM assignment → supervisor notification → follow-up/conversion → sponsor view/edit case. Verify another supervisor is denied and a non-VGM management user cannot assign or directly convert legal-entity requests. Do not repeat the already-verified individual request conversion flow.

A final queue review also found that the old management conversion button targeted `#convertLeadModal`, but no such modal existed in the page. It has been replaced with a CSRF-protected native POST form that performs the authorized conversion and follows the sponsor-detail redirect. The regression contract now guards this real action path; the supervisor detail copy was also made neutral so it accurately covers both matrix-routed individuals and VGM-assigned legal entities.

