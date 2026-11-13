# Ordinary Transaction FM Review — VGM Return/Resubmission Continuation

**Status (2026-11-11): SOURCE FIX COMMITTED / MAIN RETURN-RESUBMISSION-POSTING PATH RUNTIME VERIFIED / CLOSED**

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

## Planned runtime acceptance checklist (executed; results recorded below)

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

The executed path and results are recorded in the Runtime acceptance — PASS / CLOSED section below. The separate creator-cancellation branch and independent Transaction History action-visibility check remain untested; do not represent them as proven.

## Runtime acceptance — PASS / CLOSED (2026-11-11)

Fresh test transaction TR-000024 (ID 53), created by VGM user ID 3 as a general_donation for 1,000.00 SDG, passed the complete executed path:

1. VGM submitted; audit event SUBMIT_FM (audit row 2587, 19:40:08).
2. FM returned with required reason «يرجى تصحيح وصف العملية وإعادة الإرسال بعد استكمال بيانات التوضيح»; audit event FM_RETURN (row 2588, 19:42:06).
3. Original VGM creator opened the returned editor from the notification, corrected the description, and resubmitted. UI confirmation: «تم تعديل الدفعة وإعادة إرسالها للمدير المالي للمراجعة.» Audit event RESUBMIT_FM (row 2589, 19:43:42).
4. After resubmission, status was pending_fm_review; FM reviewer/time/reason fields were NULL; resubmitted_at was populated. No journal_entries row existed for reference_id=53 before approval.
5. FM approved at 19:52:42. UI confirmation: «تم اعتماد الدفعة وترحيل القيد المحاسبي بنجاح.» Transaction status became posted; FM reviewer was user ID 29; FM pending queue returned to zero.
6. Exactly one posted journal exists: JE-000054, ID 210, reference_type='transaction', reference_id=53. It has exactly two lines and balances: debit 1,000.00 / credit 1,000.00 / difference 0.00.
7. Account directions: 1100 «الصندوق (نقدي) / Cash» (asset) debit 1,000.00; 4300 «التبرعات العامة / General Donations» (revenue) credit 1,000.00.

PHP 8.2 syntax checks for modules/transactions/index.php and modules/transactions/edit_returned.php passed before runtime acceptance. No schema, migration, accounting-policy, journal, or allocation code changed.

Disposition: the VGM creator's FM-returned ordinary-transaction correction/resubmission and subsequent FM approval/posting path is runtime-verified and closed. Preserve TR-000024 and JE-000054 as test evidence; do not modify or reuse them. The separate creator-cancellation branch and an independent runtime check of Transaction History action visibility were not executed and are not claimed as proven. Do not rerun this acceptance without concrete regression evidence.


## 2026-11-13 — Subsequent authority change: VGM is report-only for collections

The prior VGM → FM return → VGM correction/resubmission → FM posting runtime acceptance above remains valid historical evidence for the code state at that time. It does not authorize the current workflow. The user has since explicitly changed VGM authority: VGM must be report-only for collection records. Current source therefore removes VGM from ordinary collection creation, the standalone returned-transaction editor, returned-transaction creator cancellation, and payment-entry links. VGM retains read-only transaction-register/report access. Preserve the historical test records TR-000024 / JE-000054 unchanged. The prior VGM correction path must not be reopened or offered as a current workflow. Accountant Staff creator-owned correction and the separate accountant-to-Nanny disbursement workflow are not changed. Focused contract/CI and local role acceptance are pending.

Verification update (2026-11-13): workflow `43381253320` passed PHP 8.2 lint and the VGM collection read-only contract (`6/6 checks | PASS`); the existing sponsorship component suite remained `59 checks | PASS`. This is source-contract/CI evidence, not local Apache/browser acceptance. The historical VGM correction runtime test remains historical only and must not be repeated as current authority.


## 2026-11-13 — Accountant Staff incoming-collection authority superseded

The earlier note that Accountant Staff creator-side correction/resubmission remained unchanged is superseded by the user's clarified role boundary. Accountant Staff must not create, correct/resubmit, or cancel incoming collection records. The standalone returned editor is now Admin-only; the ordinary transaction list remains a read-only view of the Accountant Staff user's own records, with no create/edit/cancel controls. A direct cancellation POST is explicitly rejected for Accountant Staff. The separate Accountant-to-Nanny disbursement workflow remains unchanged. Static contract/CI and local role acceptance are pending; do not use Accountant Staff for incoming-payment acceptance.

Verification update (2026-11-13): GitHub Actions workflow run `43387206852` passed. PHP 8.2 lint passed for all workflow-listed files; the monthly agreement component suite remained `63 checks | PASS`; VGM collection read-only contract passed `6/6`; Accountant Staff incoming-collection restriction contract passed `5/5`. This verifies static source contracts only. Local Apache/browser role acceptance remains pending. No database/runtime test is claimed.
