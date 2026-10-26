# Native Fina Collection Registration — Continuation

Updated: 2026-10-26

## Implemented on main

- `api/v1/fina/collections.php`: authenticated POST endpoint to register a Fina collection as `pending`.
- `mobile/ahl_el_kheir_app/lib/fina_collection_page.dart`: native Arabic/English registration form.
- `mobile/ahl_el_kheir_app/lib/main.dart`: native navigation and authenticated API call.
- API authorization accepts canonical `admin` and `financial_manager` roles. The API re-checks the bearer session and current role.
- Existing Fina tables and transaction-review notification helper are reused. Creation is transactional; review notification is isolated after commit.
- No journal is posted during creation. The existing Finance Manager approval workflow remains responsible for approval and journal posting.
- No schema changes were made.

## Explicit limitation

Receipt upload is not implemented in this native screen/API. The UI warns users to retain supporting evidence through the approved existing process. Do not describe native Fina collection as full feature parity until evidence upload and native review/history workflows are implemented and verified.

## Verification still required

1. Run Flutter analyzer and all widget tests against current `main`.
2. PHP lint `api/v1/fina/collections.php`.
3. Add this endpoint to the configured PHP lint/integration coverage if the workflow maintains explicit file lists.
4. Test the API against the checked-in schema: unauthenticated request, disallowed role, valid external-source collection, active/inactive/missing sponsor, invalid amount/date/method, rollback on insert failure, notification failure isolation, and confirm status stays pending with no journal entry.
5. Verify on the real local XAMPP/API/database before using it for real collections.
6. Update the canonical session index, master status, master audit and continuation prompt after these checks.

Do not create a test collection in the user's production/local database without explicit authorization. Do not claim this slice is runtime verified based only on CI.
