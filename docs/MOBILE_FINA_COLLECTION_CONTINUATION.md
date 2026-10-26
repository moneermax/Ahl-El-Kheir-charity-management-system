# Native Fina Collection Registration — Continuation

Updated: 2026-10-26

## Implemented on main

- `api/v1/fina/collections.php`: authenticated POST endpoint to register a Fina collection as `pending`.
- `mobile/ahl_el_kheir_app/lib/fina_collection_page.dart`: native Arabic/English registration form.
- `mobile/ahl_el_kheir_app/lib/main.dart`: native navigation and authenticated API call.
- API authorization accepts canonical `admin` and `financial_manager` roles. The API re-checks the bearer session and current role.
- Existing Fina tables and transaction-review notification helper are reused. Creation is transactional; review notification is isolated after commit. A notification exception is logged and cannot turn a committed save into an API failure (commit `7b122fd131ccd2ffb9d078b7cbd4e26397fc96e6`).
- No journal is posted during creation. The existing Finance Manager approval workflow remains responsible for approval and journal posting.
- No schema changes were made.

## Current implementation on main

- `api/v1/fina/collections.php` accepts JSON for collections without an attachment and multipart form data when a receipt is attached.
- Receipt uploads follow the existing web workflow's JPG/JPEG, PNG, or PDF allowlist and 10 MB maximum. The API checks the detected MIME type, writes under the existing `storage/receipts` directory, stores the relative `receipt_path`, and removes the newly written file if the database save fails (`0778b3d`, `0688846`, `9f9660c`).
- `api/v1/fina/sponsors.php` provides authenticated active-sponsor lookup for the native form, using the existing web page's name-token matching rule and maximum 30 results (`01acb45`).
- `api/v1/fina/review.php` provides the existing role-scoped pending queue/history and POST approve/return actions. Approval calls `fina_post_collection_journal()` and updates status inside one database transaction; creator self-approval/self-return remains blocked (`362c0ad`, `bafbb14`).
- `api/v1/fina/receipt.php` serves only a receipt linked to a real Fina collection, after bearer authentication, role authorization, supervisor ownership enforcement, and canonical-path containment checks (`71bbfdf`).
- `mobile/ahl_el_kheir_app/lib/fina_review_page.dart` provides native pending review and recent history. Existing role-tools paths for `fina_payment_review.php` and `fina_payment_history.php` now route to this page instead of a web page (`a55b16f`, `c8f48d5`, `b6b58e6`). The page can securely preview image receipts in-app; PDF bytes are retrieved securely but the native PDF viewer remains unfinished (`bcacb52`, `37e51c6`, `3bc7475`, `4210fa7`).
- `mobile/ahl_el_kheir_app/test/fina_review_page_test.dart` covers pending/history rendering and verifies the UI does not offer self-review (`e9ede18`, `6fb6f9f`).
- The native form includes the external-source contact/detail fields, sponsor selection/autofill, and optional receipt attachment. Stale sponsor-search responses are invalidated when the selection or source type changes (`92da8b6`).
- The multipart Flutter request was repaired to build its URI without nested string interpolation (`d126ad6`).
- `mobile/ahl_el_kheir_app/test/fina_collection_page_test.dart` adds a widget test for external-source submission (`b5be391`, `da7fdc4`). The test has been added but has not been executed in this environment.
- The collection remains `pending`; the API does not create a journal during registration. Finance review/history is still not implemented as a native workflow.

## Verification still required

1. Run Flutter dependency resolution, analyzer, and all widget tests against current `main`. No CI statuses were returned for the new commits, and Flutter/Dart is not installed in the available execution container, so static/runtime checks remain unverified.
2. PHP lint `api/v1/fina/collections.php` and `api/v1/fina/sponsors.php`.
3. Test the API against the checked-in schema: unauthenticated request, disallowed role, valid external-source collection, active/inactive/missing sponsor, invalid amount/date/method, valid/invalid/oversized receipt, rollback on insert failure including receipt cleanup, notification failure isolation, and confirm status stays pending with no journal entry.
4. Verify sponsor search and multipart receipt upload on the real local XAMPP/API/database before using this for real collections.
5. Complete in-app PDF preview for authorized receipts without browser handoff; image receipts have native preview, while PDF preview remains explicitly unfinished.
6. Update the canonical session index, master status, master audit and continuation prompt after runtime/build checks.

Do not create a test collection in the user's production/local database without explicit authorization. Do not claim this slice is runtime verified based only on CI.
