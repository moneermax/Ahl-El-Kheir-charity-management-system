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

Repository source has been inspected. The local browser workflow has **not** been runtime-verified by this checkpoint. Do not claim end-to-end success until tested against the real local application/database.

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
