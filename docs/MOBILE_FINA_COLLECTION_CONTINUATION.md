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
- `mobile/ahl_el_kheir_app/test/fina_collection_page_test.dart` adds a widget test for external-source submission (`b5be391`, `da7fdc4`). The first CI execution exposed a test-only viewport issue; commit `1c940e0b74ba50612566be0148e3be8830f8021c` calls `ensureVisible` before tapping the submit button. Re-run CI before marking it passed.
- New collections remain `pending`; the API does not create a journal during registration. Finance review/history is available in the native app and continues to use the existing server-side approval and accounting journal workflow.

## Verification still required

1. Current CI for commit `1c940e0b74ba50612566be0148e3be8830f8021c` is rerunning dependency resolution, analyzer, widget tests, and Android debug APK build after the test viewport fix. The previous run passed Dart analysis and 84 tests, but `fina_collection_page_test.dart` failed because the submit button was below the test viewport and `tap()` did not reach it; APK build was skipped. Follow the new workflow through artifact publication before treating the viewer as build-verified.
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


## Fina collection widget-test follow-up — 2026-10-26

- Workflow run `40285937691` (`124f0dc6d55e504035225e70785e6c16cd8d609f`) passed Dart analysis and 84 widget tests, but one test failed: `fina_collection_page_test.dart` attempted to tap the submit button at y=1360 in a 600px-high test viewport. The `submitted` payload remained null because the tap was outside the render view. This was a test harness visibility failure, not evidence of a production form defect.
- Commit `1c940e0b74ba50612566be0148e3be8830f8021c` updates the test to call `tester.ensureVisible(submitButton)` before tapping. New CI run `40286768725`: https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40286768725
- Current status at documentation update: the new run is queued/in progress. Do not ask for a phone install until the widget tests and APK build complete successfully.


## Fina form test and Android compile SDK blocker — 2026-10-26

- Workflow [40287521591](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40287521591), source 841916ae88481a28ca33bb0ef3d6a0f591ad1566: Dart analysis passed and all 85 Flutter tests passed. The dragUntilVisible change resolves the prior test viewport failure.
- The same workflow failed at Android APK compilation, not Dart/test execution. Exact Gradle cause: :file_picker:checkDebugAarMetadata reported flutter_plugin_android_lifecycle requires compile SDK 36 or later, while the generated Android app was compiling against API 34. APK publication was skipped.
- Root-cause fix committed in workflow configuration: 37b8a2756c7c2699a85ff277e98c4b1a97724804 updates .github/workflows/build-flutter-mobile-app.yml to explicitly set the generated app's compile SDK to 36 and fails clearly if the expected Flutter Gradle declaration cannot be found.
- Follow-up build run: [40288510863](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40288510863). Pending verification: wait for analyzer, all tests, APK build and artifact publication. No APK from the failed run is valid for handoff.
- No local XAMPP API/database test or physical-device receipt/PDF preview test is implied by CI. Do not ask the user to install until the current run publishes an APK and documentation identifies the exact source revision/artifact.
