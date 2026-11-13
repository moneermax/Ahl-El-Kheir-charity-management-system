# Monthly Nanny Disbursement FM Approval — Local Runtime Checkpoint
**Checkpoint date:** 2026-11-13  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch at checkpoint:** `main`  
**Parent source commit:** `5ed2c45377b5597ae8a7c3fcec01c7eab8dabfef`  
**Status:** Local browser workflow started with a known test-only group and draft batch #14 created. Paused before ACC1 submits the draft to FM. Do not restart setup or recreate the group/batch.

## Purpose and pause point

This checkpoint preserves the exact local acceptance state for the new mandatory Financial Manager (FM) approval gate in monthly Nanny disbursements. The user is switching to a separate task in another chat. When the user explicitly returns to this checkpoint, continue from the live state of batch #14; do not repeat the VGM group setup or ACC1 draft creation.

The user explicitly confirmed that the records involved in this acceptance workflow are test data only and no real payment is expected.

## Business authority already agreed

The intended lifecycle is:

`draft → pending_approval → approved → transferred`

- VGM may create/edit groups and assign them to Nannies, but VGM has no authority to approve or interact with FM accounting approval work.
- ACC1 creates monthly disbursement drafts only for Nannies assigned to that accountant through the existing assignment relationship.
- ACC1 may correct amounts only on existing family rows while the batch is `draft` or `pending_approval`. Each correction requires a reason and mandatory audit evidence. ACC1 cannot change Nanny, group, month, or family membership.
- Only the canonical `financial_manager` role may approve or reject. Admin, GM, VGM, and other accountant roles are not substitutes. An FM cannot approve/reject a batch they submitted themselves.
- Submission moves the draft to `pending_approval` and notifies active FM-role users.
- FM approval records reviewer/time/audit evidence and changes status to `approved`; it must not create a transaction, journal, or cash movement.
- FM rejection requires a reason, is audited/notified, and returns the batch to editable `draft`.
- After approval, ACC1 cannot edit. Transfer is allowed only from `approved`, with mandatory transfer receipt. The transfer transaction, balanced journal, batch status, and audit record must commit atomically.
- ACC1 cannot void. Admin/FM retain controlled void authority; if a transfer was posted, void must use the existing reversal journal path.
- Nanny-side family confirmations, receipts, returns, and closure are distinct from FM approval and transfer.

## Local workflow state actually shown by the user

### VGM setup completed

The user restarted the test workflow from its beginning:
1. Logged in as VGM.
2. Created a group.
3. Assigned that group to `nany1`.

The resulting group is displayed in the ACC1 batch detail as **Fm Acceptance Test**.

### ACC1 draft creation completed

The user then logged in as ACC1 and created **batch #14** for **2026-11**.

The ACC1 detail page showed:

- Batch: `#14`
- Month: `2026-11`
- Nanny: `nany1`
- Group: `Fm Acceptance Test`
- Total: **139,000 ج.س**
- Status label: **مسودة قابلة للتصحيح** (editable draft)
- Five family items, all shown as **قيد الانتظار**:

| Family code | Displayed family name | Children | Current amount |
|---|---|---:|---:|
| IMP-FAM-001126 | ابتهال احمد احمد | 2 | 13,000 ج.س |
| IMP-FAM-001115 | ابتهال علي فضل الله | 2 | 48,000 ج.س |
| IMP-FAM-001729 | احلام عمر احمد | 4 | 43,000 ج.س |
| IMP-FAM-001398 | احلام يوسف التاج | 1 | 25,000 ج.س |
| IMP-FAM-001901 | ازدهار الطاهر بشري | 3 | 10,000 ج.س |

Arithmetic confirmed from the displayed values:
`13,000 + 48,000 + 43,000 + 25,000 + 10,000 = 139,000 ج.س`.

The page also displayed the correction form:
- Only the amount for each existing family can be corrected.
- The amount must not exceed that family's active, verified sponsorship total for the month.
- A correction reason is mandatory and the change is recorded in audit history.
- Nanny/group/month/family membership cannot be changed or expanded through this form.

The page displayed the **إرسال الدفعة للمراجعة المالية** section and stated that submission and FM approval do not deduct cash or create a journal entry.

### Exact pause point

**Batch #14 is still at draft status in the last user-provided screen. It has not yet been submitted to FM.**

No amount correction was shown or requested. Treat the five amounts and 139,000 total as the baseline. Do not assume any later action occurred after this screenshot; on resumption, inspect the current UI state first.

## Earlier batch history preserved as context

Before the user restarted from the VGM setup, the ACC1 monthly-disbursement list showed:

| Batch | Month | Group | Nanny | Amount | Displayed state |
|---:|---|---|---|---:|---|
| 13 | 2026-11 | مجموعة الحاضنة 1 | nany1 | 3,000 ج.س | مبطلة |
| 12 | 2026-10 | مجموعة الحاضنة 1 | nany1 | 3,000 ج.س | مغلق مع إرجاع; progress 1/1 |
| 11 | 2026-10 | مجموعة الحاضنة 1 | nany1 | 100 ج.س | مبطلة |
| 9 | 2026-08 | مجوعة 2 الحاضنة 1 | nany1 | 738,000 ج.س | مستلم; progress 4/4 |
| 8 | 2026-08 | مجوعة 2 الحاضنة 1 | nany1 | 738,000 ج.س | مبطلة |
| 7 | 2026-08 | مجوعة 2 الحاضنة 1 | nany1 | 833,000 ج.س | مبطلة |
| 6 | 2026-08 | مجموعة الحاضنة 1 | nany1 | 59,000 ج.س | محوّل; progress 6/7 |

These are user-provided UI observations, not a fresh database query. Preserve all existing batches and their accounting/audit evidence. In particular:
- #13 was shown as voided. Its prior transfer/reversal history must remain intact.
- #12 was closed with a return; do not reuse or alter it.
- #9 and #6 show completed/transferred activity; do not reuse or alter them.
- #11, #8, and #7 were shown as voided; retain them as historical records.

The earlier November checklist had shown no eligible groups. The user therefore intentionally restarted with a new VGM-created group and assigned it to Nanny 1. Do not revive old verification rows, alter verification flags, or manufacture eligibility.

## Implementation/source boundary on main

Relevant files:
- `modules/accounting/disbursements.php`
- `modules/accounting/lib_disbursement_approval.php`
- `modules/accounting/disbursement_void_guard.php`
- `tools/test_accountant_staff_incoming_collection_restriction.php`
- `.github/workflows/php-syntax-check.yml`
- `.github/workflows/sponsorship-monthly-agreement-components.yml`

The implementation and exact approved rules are documented in `docs/ACCOUNTING_NANNY_DISBURSEMENT_AUTHORITY_CONTINUATION.md`.

Existing CI/source verification already recorded:
- GitHub Actions run `43466946535`: PHP 8.2 lint and component workflow passed; sponsorship suite `63 checks | PASS`, VGM read-only contract `6/6 PASS`, Accountant Staff/disbursement contract `29/29 PASS`.
- Full workflow run `43466946661` completed successfully, including PHP lint for the disbursement page, void guard, and approval helper.
- A focused UI correction for voided batches was committed in `45141e5e17e8bd4083c8d8133277b809710308d0`, `fc703935f2743fcc694d0d720745fe00a138fb4c`, and `e6889532331ce7829b578d60e1117116a5989a67`; latest source head before this documentation checkpoint is `5ed2c45377b5597ae8a7c3fcec01c7eab8dabfef`.
- These checks do not replace local Apache/browser/MariaDB acceptance. Do not describe the entire workflow as runtime-verified.

No local transaction, journal, receipt, or cash movement has been reported for batch #14. No local DB inspection was available to the assistant; all local runtime evidence above is from the user's screenshots/text.

## Exact continuation sequence when the user returns

Do not perform any steps until the user explicitly asks to resume this checkpoint.

1. Re-read the current canonical documents and this checkpoint from latest `main`. Check whether commits after this checkpoint changed the relevant source/docs. Preserve any local user changes.
2. Ask the user to open the local application only if needed; do not ask them to repeat setup. Log in as ACC1 and inspect batch #14's current state. If still draft, continue at step 3. If already submitted/approved/transferred, skip completed steps and inspect the current state/evidence instead.
3. As ACC1, submit batch #14 for FM review without changing amounts unless the user explicitly requests a correction. Confirm status becomes `pending_approval`.
4. Log in as an actual user with canonical `financial_manager` role. Confirm the submission notification arrives and the correct batch/rows are visible. Do not use Admin, GM, or VGM as a substitute FM.
5. Test the rejection path with a reason only if it can be tested without losing the required approval path; after rejection, confirm status returns to draft, notification is delivered, and audit history preserves the reason. Then resubmit the same batch.
6. As FM, approve batch #14. Verify status `approved`, reviewer/time/audit evidence, and specifically confirm no transaction, journal, or cash change occurs at approval. Use local application/database evidence; do not invent SQL or ask for broad dumps.
7. As ACC1, confirm post-approval amount editing is denied. Then, only with explicit user confirmation to execute the test transfer, upload the required receipt and transfer the approved test batch.
8. Verify exactly one transfer transaction, a balanced journal, required audit evidence, correct batch status, and expected cash movement.
9. As Admin or FM, use the supported void action on the test transfer. Verify the reversal journal and restored cash position. Do not delete or directly mutate transactions/journals.
10. Test an unassigned ACC1 boundary only if a legitimate second test assignment/account exists; never fabricate role/assignment data just for a shortcut.
11. Update this checkpoint and canonical docs with exact runtime evidence, then close only the acceptance gates actually proven.

## Strict no-repeat / no-guess safeguards

- Do not recreate the VGM group or batch #14.
- Do not rerun already-passed CI/contract suites absent source changes or regression evidence.
- Do not rerun the previous batch #13 void workflow unless a concrete regression is found.
- Do not alter the five family rows merely to create a correction test; if testing amount correction, first inspect the actual active sponsorship ceiling and use a valid, bounded test amount with an explicit reason.
- Do not approve with Admin/GM/VGM.
- Do not transfer before a real FM approval.
- Do not create a transfer/journal/cash movement without the user's explicit authorization at that point.
- Do not claim FM notification, approval, rejection, audit, transaction, journal, or reversal has passed until observed.
- The next chat's separate task is independent. Do not automatically resume this checkpoint until the user explicitly requests it.

## Files to keep synchronized

When new evidence is obtained, update:
- `docs/ACCOUNTING_NANNY_DISBURSEMENT_AUTHORITY_CONTINUATION.md`
- `docs/CHATGPT_SESSION_INDEX.md`
- `docs/AHL_EL_KHEIR_MASTER_STATUS.md`
- `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`
- `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md`

