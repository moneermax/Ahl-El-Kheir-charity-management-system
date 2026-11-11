# Ordinary Transaction FM Review — VGM Return/Resubmission Continuation

**Status (2026-11-11): SOURCE FIX COMMITTED / LOCAL RUNTIME VERIFICATION PENDING**

## Scope

This continuation covers the ordinary `transactions` workflow:

`pending_fm_review → returned → pending_fm_review → posted`

It does not cover the separate Supervisor `sponsor_payments` queue or change the FM/Admin approval authority.

## Source findings

- `modules/transactions/create.php` already allows `vice_general_manager` to create ordinary transactions and includes a creator-owned returned-transaction correction path.
- `modules/accounting/fm_transaction_review.php` allows only the Financial Manager aliases/Admin to approve or return ordinary transactions. Return requires a reason, records the reviewer/reason and audit event, and creates no journal.
- `modules/accounting/lib_transaction_review.php` validates that a correction target is in `returned` state and that the current user is its creator.
- The standalone correction page, `modules/transactions/edit_returned.php`, and the returned-action gate in `modules/transactions/index.php` previously excluded VGM. The FM return notification linked directly to the standalone page, so the creator could be sent to a page that rejected their role.
- The transaction list already scopes the edit/cancel controls to the transaction creator. The cancellation endpoint also enforces returned state and creator ownership; it does not create a journal.

## Implemented source correction

Commits on `main`:
- `0b220ec3fa4980246bde41049ed62c874191a3ea` — allow VGM into the standalone returned-transaction editor; preserve only whitelisted transaction-list filters in its Back/return URL; remove a stale JavaScript reference to a non-existent `grpOther` element.
- `e4251346f47687d7c8482c1fdb5b58a93c69526c` — expose returned edit/creator-cancellation actions to VGM in the transaction list.

The edit page continues to enforce `status='returned'` and `created_by=current user`. The list's FM approval/return controls, posted-transaction void controls, and journal permissions were not broadened. No schema, migration, journal, allocation, or accounting-policy change was made.

## Runtime acceptance — pending

The earlier successful transaction `TR-000023` is already posted and fully allocated to `SH-003557` for November 2026. **Do not reuse, return, or modify it.** Use a new disposable test transaction.

Recommended focused scenario: create a small `general_donation` transaction as VGM (avoids reusing the November sponsorship obligation), with no receipt upload required.

1. PHP 8.2 lint:
   - `php -l modules/transactions/index.php`
   - `php -l modules/transactions/edit_returned.php`
2. As VGM, submit the new test transaction. Confirm it appears as `pending_fm_review`.
3. As FM, return it with a meaningful test reason. Confirm it becomes `returned`, the reason/reviewer are saved, and no journal exists.
4. As the original VGM creator, open Transaction History. Confirm the returned row shows edit and creator-cancel actions; open Edit and confirm the standalone page loads without PHP/JavaScript errors.
5. Make a small, deliberate correction and resubmit. Confirm status returns to `pending_fm_review`, `fm_reviewed_by`, `fm_reviewed_at`, and `fm_review_reason` are cleared, `resubmitted_at` is set, and `RESUBMIT_FM` audit evidence exists.
6. Before FM approval, confirm no journal has been created for this transaction.
7. As FM, confirm the resubmitted transaction appears in the pending queue. Approve only after the preceding checks pass; then confirm `posted`, exactly one balanced journal, and no duplicate posting.
8. Keep creator cancellation as a separate creator action. If testing it, use a second fresh returned test transaction and verify `cancelled`, audit evidence, and no journal.

Do not mark this workflow runtime-verified until the local browser/database evidence is reported. If any step fails, stop and inspect the exact source/database state before changing code.
