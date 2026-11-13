# Accountant Staff — Monthly Nanny Disbursement Authority

**Status:** Source change committed; CI and local runtime acceptance pending  
**Date:** 2026-11-13  
**Branch:** `main`

## Approved business rule

Accountant Staff must be allowed to execute outgoing monthly Nanny disbursements, upload the bank/cash transfer receipt, and complete the existing transfer/posting step.

This authority is limited to each Accountant Staff user's assigned Nannies. The assignment relation is `accountant_nanny_assignments(accountant_id, nanny_id)`.

This does not grant authority to:
- enter incoming collections or donations;
- issue generic incoming receipt vouchers;
- void a monthly disbursement batch;
- operate outside the assigned-Nanny scope.

Batch void remains restricted to Admin and Financial Manager as a segregation-of-duties control. Existing Nanny-side family confirmation, family receipts, partial return, final receipt, and closure behavior remain separate.

## Source findings

Before the change, `modules/accounting/disbursements.php`:
- admitted ACC1 to the page and limited batch visibility to assigned Nannies;
- allowed ACC1 to create/manage assigned-scope batches and reopen closed batches/items;
- restricted the transfer POST and transfer receipt UI to `$canManage` (Admin, GM, FM), excluding ACC1;
- already restricted ACC1 batch void through a separate Admin/Financial Manager-only guard.

The result was inconsistent with the approved role: ACC1 could manage assigned batch preparation/reopening but could not perform the actual outgoing transfer, receipt upload, or transaction/journal posting.

## Implementation

- Transfer POST now uses the existing `$canManageAssigned` role gate.
- Before accepting an ACC1 transfer, the server verifies the batch's `nanny_id` is assigned to the current accountant in `accountant_nanny_assignments`.
- The transfer UI is displayed through the same assigned-scope page gate. The view route already suppresses unassigned ACC1 batches; the POST independently rechecks assignment to prevent forged requests.
- `modules/accounting/serve_receipt.php` no longer treats ACC1 as a globally authorized receipt viewer. It resolves the receipt's parent Nanny and grants ACC1 access only when that Nanny is assigned to the current accountant.
- Existing `pending_approval` state validation and mandatory receipt upload remain.
- Existing transaction and balanced journal posting logic is unchanged.
- Batch void remains Admin/Financial Manager only.

No schema or migration change is required. No database rows, receipts, transactions, journals, or cash balances were changed by this source edit.

## Verification

Added contract checks to `tools/test_accountant_staff_incoming_collection_restriction.php` for:
1. ACC1 transfer access under the assigned-scope role gate;
2. server-side assigned-Nanny validation;
3. transfer UI visibility;
4. pending-state and mandatory receipt checks;
5. retained Admin/Financial Manager-only batch void restriction;
6. assigned-scope authorization for direct batch/item receipt URLs.

**Pending:** GitHub Actions PHP lint and focused contract execution, followed by local Apache acceptance using a controlled test batch. Runtime acceptance must verify:
- assigned ACC1 can see the transfer form and complete a test transfer with a valid receipt;
- unassigned ACC1 cannot transfer by direct POST;
- a non-pending batch cannot be transferred again;
- missing/invalid receipt is rejected;
- Admin/FM existing transfer behavior remains;
- ACC1 cannot void the batch;
- assigned ACC1 can view that batch's transfer receipt, while an unassigned ACC1 is denied a forged batch/item receipt URL.

Do not use a real operational payment merely to test permissions. Do not repeat unrelated closed accounting tests.
