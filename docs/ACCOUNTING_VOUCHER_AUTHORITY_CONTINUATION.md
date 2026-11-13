# Accounting Voucher Authority — Accountant Staff Direction Restriction

**Status:** Source change committed; CI and local runtime acceptance pending  
**Date:** 2026-11-13  
**Branch:** `main`

## Business boundary

Accountant Staff must not record incoming collections. Incoming sponsorship/donation collections belong to Supervisors or other explicitly authorized collection staff and proceed through the existing FM review workflow.

Accountant Staff may retain authorized outgoing accounting work. For the generic voucher module:
- `payment` vouchers (سند صرف / cash out): permitted for Accountant Staff;
- `receipt` vouchers (سند قبض / cash in): not permitted for Accountant Staff.

Admin and Financial Manager retain both voucher directions. Existing view and void permissions are unchanged.

The dedicated Accountant-to-Nanny monthly-disbursement workflow remains separate and unchanged. Do not record a Nanny monthly disbursement a second time through a generic payment voucher unless the approved accounting procedure explicitly requires that separate voucher.

## Implementation

- `modules/accounting/lib_vouchers.php`: separate `issue_receipt` and `issue_payment` capabilities.
- `modules/accounting/vouchers.php`: direct POST rejects unauthorized receipt vouchers; Accountant Staff form defaults to and displays only payment vouchers.
- `api/v1/accounting/vouchers.php`: direct mobile API issuance rejects receipt vouchers for Accountant Staff and exposes direction-specific capabilities.
- `mobile/ahl_el_kheir_app/lib/voucher_page.dart`: renders only voucher directions authorized by the API.
- `mobile/ahl_el_kheir_app/test/voucher_page_test.dart`: payment-only Accountant Staff UI coverage.
- `tools/test_accountant_staff_incoming_collection_restriction.php`: expanded source-contract assertions for web/API voucher direction boundaries.

Commits: `1500e9c4f40f93ad37c2a298633ec93075726064` (implementation); `20a77e931c2aaa0d3f8d81c55f69d543b5001c96` (contract assertion correction).

No database/schema/migration, journal, voucher, cash-balance, or historical record was changed. No changes were made to `modules/accounting/disbursements.php`.

## Verification boundary

Pending:
1. GitHub Actions PHP lint + focused Accountant Staff contract.
2. Flutter analyze/tests/build.
3. Local Apache acceptance: ACC1 cannot issue receipt voucher (including a forged POST), can issue a payment voucher only in a controlled test environment.
4. Local mobile/API acceptance: same type-specific denial and payment allowance.

Do not submit a real receipt or payment to test this restriction. Preserve existing voucher/journal evidence.
