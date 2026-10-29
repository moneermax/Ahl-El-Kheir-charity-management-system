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

The user has locally verified the core conversion path (contact status, successful conversion/redirect, converted request link, and access to the existing sponsorship workflow). The targeted 8-second success-toast behavior awaits local verification after pulling `main`. Duplicate-conversion and reassignment-race checks remain unverified.

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

The conversion POST form now opts out of the global same-page `fetch()` POST handler with `data-ak-native-post="1"`. Source tracing showed that the handler follows the conversion's cross-page redirect during `fetch`; the redirected sponsor-detail response renders and consumes the one-time session flash, then JavaScript navigates the browser to that URL a second time. This makes the flash lifecycle depend on the intermediate fetch response rather than the actual document navigation. Native POST/redirect/GET lets the sponsor-detail page render the `success_long` flash once and use its configured 8000 ms duration. Change committed to `main` as `6b398049b530e50d32ef364647eeaed10e02aeb5`. Browser verification and PHP syntax check are pending; do not mark runtime-verified until confirmed locally.


## 2026-10-29 — Sponsor conversion toast regression guard

The conversion feedback path was reviewed end-to-end across `modules/sponsors/my_request.php`, `includes/alerts.php`, `assets/js/notifications.js`, and `assets/js/app.js`.

Verified source contract:
- Conversion emits `success_long` and redirects to the created sponsor detail page.
- The conversion form opts out of the global same-page `fetch()` handler so the one-time session flash is rendered by the real redirected document, not consumed by an intermediate fetch response.
- `includes/alerts.php` maps only `success_long` to an 8000 ms toast duration.
- The normal page-load toast handler reads and forwards that duration; the shared in-place response handler also preserves it for other pages.
- SweetAlert2's toast timer receives the positive supplied duration instead of relying on its 3200 ms default.

Added database-free regression test: `php tools/test_sponsor_conversion_toast_contract.php`, wired into the PHP syntax workflow. This guards source wiring, not real-browser timing. Local browser acceptance remains pending; do not claim runtime verification until the deployed local app is exercised.
