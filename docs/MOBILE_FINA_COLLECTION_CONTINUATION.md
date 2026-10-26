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
- `mobile/ahl_el_kheir_app/lib/fina_review_page.dart` provides native pending review and recent history. Existing role-tools paths for `fina_payment_review.php` and `fina_payment_history.php` route to this page instead of a web page (`a55b16f`, `c8f48d5`, `b6b58e6`). Authorized image receipts preview in-app; authorized PDF receipts now use `pdfrx`'s native Flutter viewer from securely retrieved bytes (`bcacb52`, `37e51c6`, `3bc7475`, `4210fa7`, `089ea66`).
- `mobile/ahl_el_kheir_app/test/fina_review_page_test.dart` covers pending/history rendering and verifies the UI does not offer self-review (`e9ede18`, `6fb6f9f`).
- The native form includes the external-source contact/detail fields, sponsor selection/autofill, and optional receipt attachment. Stale sponsor-search responses are invalidated when the selection or source type changes (`92da8b6`).
- The multipart Flutter request was repaired to build its URI without nested string interpolation (`d126ad6`).
- `mobile/ahl_el_kheir_app/test/fina_collection_page_test.dart` adds a widget test for external-source submission (`b5be391`, `da7fdc4`). The test has been added but has not been executed in this environment.
- New collections remain `pending`; the API does not create a journal during registration. Finance review/history is available in the native app and continues to use the existing server-side approval and accounting journal workflow.

## Verification still required

1. Run Flutter dependency resolution, analyzer, all widget tests, and Android debug APK build against current `main` after adding `pdfrx`. The available execution container has no Flutter/Dart installation; check the GitHub Actions run triggered by these commits before treating the viewer as build-verified.
2. PHP lint `api/v1/fina/collections.php` and `api/v1/fina/sponsors.php`.
3. Test the API against the checked-in schema: unauthenticated request, disallowed role, valid external-source collection, active/inactive/missing sponsor, invalid amount/date/method, valid/invalid/oversized receipt, rollback on insert failure including receipt cleanup, notification failure isolation, and confirm status stays pending with no journal entry.
4. Verify sponsor search and multipart receipt upload on the real local XAMPP/API/database before using this for real collections.
5. Verify PDF preview on the built Android APK with a valid authorized PDF receipt; compilation alone does not prove that PDFium renders the document on-device.
6. Update the canonical session index, master status, master audit and continuation prompt after runtime/build checks.

Do not create a test collection in the user's production/local database without explicit authorization. Do not claim this slice is runtime verified based only on CI.

## Mobile entrypoint source repair — 2026-10-26

- GitHub Actions run `40285101156` failed during Dart analysis before tests/build. The log identified a malformed duplicate trailing block in `mobile/ahl_el_kheir_app/lib/main.dart`: source after the complete `NotificationDetailPage` ended at line 2612 was appended again, beginning with a clipped fragment of the multipart Fina request and duplicating the app widget/page classes. This produced out-of-scope references and duplicate declarations.
- Commit `defdafbd103e7f091b3761501c4dad2b88318b39` removes only that malformed trailing block, preserving the first complete app entrypoint and its Fina/API methods. The post-repair workflow is running: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40285490915
- Do not treat the repair as verified until the current workflow finishes analyzer, tests, APK build, and artifact publication. If analysis reports further errors, inspect the new exact log before another edit.
