# Accountant Staff — Monthly Nanny Disbursement Authority

**Status:** Source and CI contract verified; local runtime acceptance pending  
**Date:** 2026-11-13  
**Branch:** `main`

## Approved business rule

Accountant Staff (ACC1) may execute outgoing monthly Nanny transfers only for Nannies assigned through `accountant_nanny_assignments(accountant_id, nanny_id)`. A real Financial Manager approval must occur before money leaves the organization's cash/bank balance.

The workflow is:

`draft → pending_approval → approved → transferred`

- ACC1 creates a batch for an assigned Nanny. It starts as `draft`.
- ACC1 may correct the amounts on the existing family rows while the batch is `draft` or `pending_approval`. Every correction requires a reason and a mandatory audit record.
- Corrections cannot change the Nanny, group, month, or family membership. Each amount must be positive and no greater than the family's currently active sponsorship total for that group/month, with all three monthly verification flags set.
- ACC1 submits a valid draft. The batch enters `pending_approval` and active FM-role users are notified. If ACC1 corrects a pending batch, it remains pending and FM is notified of the revision.
- Only the canonical `financial_manager` role may approve/reject. Admin, GM, and VGM are not approval substitutes. An FM cannot approve/reject a batch they submitted themselves.
- Approval changes the state to `approved` and records reviewer/time/audit evidence. It does **not** create a transaction or journal and does **not** deduct cash.
- Rejection requires a reason, records it in the audit log, notifies the submitter, and returns the batch to `draft` for correction and resubmission.
- Once approved, ACC1 cannot edit the batch. Transfer POST and UI require `approved` and a mandatory transfer receipt. The transfer transaction, balanced journal, batch state, and audit record commit in one transaction.
- ACC1 cannot void. Admin/FM retain controlled void authority for draft, pending, approved, and transferred batches. A posted transfer is reversed through the existing reversing-journal process.
- Nanny-side family confirmations, family receipts, returns, and closure remain separate.

This authority does not grant ACC1 permission to:
- enter incoming collections or donations;
- issue generic incoming receipt vouchers;
- approve or reject a disbursement;
- edit after FM approval;
- void/cancel a batch;
- operate outside the assigned-Nanny scope.

## Source and schema findings

The previous source created batches directly as `pending_approval`, while the transfer POST accepted that same state and immediately posted a transaction and journal. There was no distinct FM approval action.

The canonical `database/ahl_el_kheir.sql` already defines `draft`, `pending_approval`, and `approved` in the status enum, and includes nullable `submitted_by`, `submitted_at`, `reviewed_by_user_id`, and `reviewed_at`. No migration or runtime DDL was required. The existing `return_note` field was not reused because it is also part of the Nanny return workflow.

## Implementation on main

- Added `modules/accounting/lib_disbursement_approval.php` for strict FM role detection, assignment checks, mandatory audit writes, and active/verified sponsorship amount validation.
- Changed batch creation to transactional draft creation.
- Added ACC1 pre-approval amount correction, explicit submission, FM approval, and reason-required rejection actions. All state-changing actions lock the batch row.
- FM approval does not insert into `transactions` or `journal_entries`.
- Changed transfer UI and POST to accept only `approved`, re-lock the batch, reject duplicate transaction references, and write the transfer audit record inside the posting transaction.
- Extended the protected void guard to support draft/pending/approved/transferred states. Admin/FM only; ACC1 remains denied.
- Added rejection-reason display and status labels.
- Extended `tools/test_accountant_staff_incoming_collection_restriction.php` and the existing CI workflows to lint/test the new helper, page, and void guard.

## Verification

GitHub Actions:
- Run `43465126565`: PHP 8.2 lint passed; sponsorship component contract `63 checks | PASS`; VGM read-only contract `6/6 PASS`; Accountant Staff/disbursement contract `29/29 PASS`.
- Full GitHub Actions workflow run `43466946661` completed successfully, including the PHP lint job for `modules/accounting/lib_disbursement_approval.php`, `modules/accounting/disbursements.php`, and `modules/accounting/disbursement_void_guard.php`, plus the existing MariaDB migration and integration jobs.

These are static/source and syntax checks. No local Apache/browser/database runtime acceptance has been performed. No database rows, transactions, journals, receipts, or cash balances were changed by the implementation.

## Voided-batch presentation correction — 2026-11-13

After batch #13 was voided through the FM application workflow, both FM and ACC1 views showed the raw status `voided`, while the preserved family item still appeared as `pending`. Source inspection confirmed that item status is intentionally retained as historical evidence; the defect was that the UI presented it without the parent-batch context.

Implemented on `main`:
- Added the explicit Arabic status label **مبطلة** to both the batch detail and batch-list status maps.
- For a family item whose parent batch is voided, the UI now displays **غير ساري — الدفعة مبطلة** instead of its preserved operational item status. The database item status is not changed.
- Replaced the batch-list progress bar/count presentation for voided batches with a clear **دفعة مبطلة** label.
- Added three focused source-contract assertions covering both status maps, family-item presentation, and suppression of live progress for voided batches.

No schema, migration, item status, transaction, journal, cash balance, or audit record was changed by this UI correction. The already-voided batch #13 and its reversing accounting records remain untouched.

Source commits:
- `45141e5e17e8bd4083c8d8133277b809710308d0` — detail UI and family-item state presentation.
- `fc703935f2743fcc694d0d720745fe00a138fb4c` — batch-list status and progress presentation.
- `e6889532331ce7829b578d60e1117116a5989a67` — focused static contract assertions.

The three new assertions have been added but have not yet been executed in CI or against the local PHP runtime. Local Apache/browser verification remains required for both FM and ACC1 views.

## Local acceptance still required

1. Pull the latest `main`.
2. Verify the earlier test batch `#13` has been voided through Admin/FM and its posted transaction/journal reversed. Never delete or edit those records directly.
3. Use a genuinely eligible group/month shown by the application's pre-creation checklist. Do not manufacture eligibility.
4. As ACC1, create a draft, correct an existing family amount within its active sponsorship ceiling with a reason, and submit it.
5. As FM, verify notification, review the rows, and approve. Confirm no transaction/journal was created and cash did not change at approval.
6. As ACC1, verify an edit is rejected after approval, then upload the required transfer receipt and execute the approved transfer. Verify one posted transaction and balanced journal.
7. Test rejection with a reason and resubmission on a separate eligible draft if needed.
8. Verify an unassigned ACC1 cannot edit/submit/transfer or open the corresponding receipt by direct URL.
9. As Admin/FM, void the test transfer and verify the reversing journal and restored cash balance. Do not repeat unrelated closed accounting tests.

No local acceptance should use a real operational payment. If no eligible test group is available, stop and prepare a controlled rollback-only fixture after inspecting the live local data.


CI note: An earlier attempt (`43466074018`) failed before running the sponsor-request migration test because the Ubuntu 26.04 runner's package index reported no installation candidate for `mariadb-client` (`apt-get install` exit 100). The subsequent full workflow run `43466946661` on the updated main commit completed successfully, including that job; this was a runner package-availability failure, not a PHP or migration assertion failure.


CI sequencing note: Intermediate component run `43466938716` failed one role assertion because the helper was changed to the canonical `financial_manager` role before the corresponding static contract assertion was updated in the next commit. The next commit updated the test, and component run `43466946535` passed `29/29`. Full workflow run `43466946661` then completed successfully. No CI failure remains outstanding.


## Local runtime checkpoint — 2026-11-13 — batch #14 created; paused before submission

The user deliberately restarted the local acceptance setup to use a fresh group assigned to Nanny 1:
1. Logged in as VGM, created **Fm Acceptance Test**, and assigned it to **nany1**.
2. Logged in as ACC1 and created **batch #14**, month **2026-11**, total **139,000 ج.س**.
3. The draft contained five test-only families: IMP-FAM-001126 (13,000), IMP-FAM-001115 (48,000), IMP-FAM-001729 (43,000), IMP-FAM-001398 (25,000), IMP-FAM-001901 (10,000). The displayed family names/child counts are preserved in the detailed checkpoint document.
4. The user explicitly confirmed all data is test data. The UI showed **مسودة قابلة للتصحيح**. No amount correction or submission to FM had yet occurred.

**Exact pause point:** batch #14 exists as a draft. Continue from inspecting its current live status, then submit as ACC1 if it remains draft. Do not recreate the group or batch. No FM notification, rejection, approval, transfer, transaction, journal, receipt, or cash movement has been verified for #14.

The complete immutable resume record, old batch history, exact row details, sequence, and safeguards are in `docs/checkpoints/2026-11-13-monthly-nanny-disbursement-fm-approval.md`. The user is starting another task in a new chat; do not resume this workflow until explicitly asked.
