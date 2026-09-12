# Ahl El Kheir Charity Management System
## System-Wide Notification Integrity Audit

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Checkpoint:** 2026-09-12  

This document records the continuation of the existing notification audit. It is not a replacement for the Accounting Audit; Accounting Audit remains complete. No completed accounting fixtures or verification tests are to be repeated unless new notification code causes a genuine regression.

## Audit rule

For each workflow:

```text
Action
→ business-state change
→ required recipient(s)
→ active recipient scope
→ exactly-once delivery
→ correct event/reference
→ actionable link
→ read state
→ next workflow action
```

Notifications are classified as required workflow notifications, operational notifications, optional/self confirmations, or no notification where no recipient action is required.

## Current confirmed architecture

- System notifications use `notifications` with current recipient fields such as `recipient_user_id`, `title`, `body`, `link`, `is_read`, and optional `type/reference_*` fields.
- Internal messaging is separate and uses `messages` / `message_reads`.
- The notification bell uses POST+CSRF for mark-all-read.
- Individual notification links currently do not mark the notification read automatically; this remains a UI/read-state follow-up.
- Transaction review has event-aware notification helpers for FM and reference-aware deduplication.

## Completed in this continuation

### Password recovery — fixed

`modules/users/recovery.php` now:

1. notifies the requesting user when recovery is approved;
2. uses an absolute `APP_URL` link for the forced password-change destination;
3. notifies the requesting user when the recovery request is rejected;
4. sends the rejection notification only when the pending request was actually changed to rejected.

Fix commit: `21362ef456db73303734acdede3e332f330aedf0`

### Transaction-review notification infrastructure — fixed

The accounting transaction-review notification path now uses the current notification schema, active financial-manager role codes, event/reference-aware delivery, and notification-error isolation. Supervisor payment approval/return paths were also repaired, and an FM-targeted event helper was added for remaining supervisor submission paths.

Relevant commits include `3ca3814cf01a676536c2cf91b89f3842fc07cbe5`, `717ed19104bf94ea1a6a4114bad7c5dce8b57898`, `0d06152736b2b905d63c07ee9f6fc7e49614d5cb`, and `5f7fc01e2c884f42d604f4b12be67027613e6377`.

### Supervisor sponsor-payment notifications — fixed

`modules/transactions/create.php` now sends event-aware FM notifications for both previously missing supervisor payment paths:

1. a newly created supervisor sponsor payment (`pending`), using event type `sponsor_payment_submitted` and the payment-specific `sponsor_payments.id` reference;
2. a supervisor resubmitting a returned sponsor payment (`returned` → `pending`), using event type `sponsor_payment_resubmitted` and the payment-specific reference.

Both notifications:

- target active Financial Manager role codes through `ak_transaction_review_notify_fm_event()`;
- exclude the submitting supervisor from the FM recipient set;
- use the payment-specific FM review queue link;
- use the existing event/reference-aware deduplication path;
- are isolated from the business-state mutation so notification delivery failure does not roll back the completed payment workflow.

The monthly sponsorship loop notifies per inserted payment row, and the non-monthly supervisor-payment path notifies after its inserted payment row. The returned-payment path notifies only after the guarded `returned` → `pending` update succeeds and after obsolete receipt cleanup.

### HR leave notifications — fixed

`modules/hr/leaves.php::notifyLeaveRoleUsers()` now restricts role recipients to active users with `u.is_active = 1`. This prevents inactive accounts from receiving leave workflow notifications while preserving the existing role-based recipient scope.

Current verified source commit: `100738461d7a4b030a3ff0f1f5c25ff920dadfda`.

## Confirmed remaining findings

### Disbursement notifications — detailed workflow audit

`modules/accounting/disbursements.php` was re-read across its principal state transitions. The business state changes and audit logging are present, but the recipient-driven transitions below currently have no notification delivery in the file.

| Action | State change | Recipient | Classification | Current finding |
|---|---|---|---|---|
| Create batch | `monthly_disbursements` → `pending_approval` | No downstream recipient established yet | No automatic notification currently required | The batch is created by an authorized accounting user and is not yet actionable by the nanny. Do not broadcast to the nanny at this point. Any approval-queue notification must follow the real reviewer responsibility. |
| Transfer with receipt | `pending_approval` → `transferred` | Assigned nanny (`monthly_disbursements.nanny_id`) | **MUST SEND** | The nanny immediately becomes able to confirm family items, but no notification is currently sent. |
| Confirm family item | item `pending` → `paid` | No downstream actor required | **SHOULD NOT SEND** | This is the nanny's own action; an automatic self-notification would add noise. |
| Fully close batch | `transferred` → `received`; orphan group → `closed` | Responsible accounting user(s) | **MUST SEND / operational workflow** | Closing confirms all families were received and permanently closes the group. No notification is currently sent. Recipient scope should follow active accounting responsibility, including assigned accountant staff where applicable and financial oversight where required. |
| Close with return | batch `transferred` → `returned`; pending items → `returned`; reversal journal posted; group → `closed` | Responsible accounting user(s) / financial oversight | **MUST SEND** | This creates a financial reversal/follow-up event. No notification is currently sent. Recipient scope must respect `accountant_nanny_assignments` for accountant staff and active financial-manager responsibility. |
| Reopen whole batch | `received`/`returned` → `transferred`; group reopened | Assigned nanny | **MUST SEND** | Nanny becomes actionable again, but no notification is sent. |
| Reopen family item | item `paid`/`returned` → `pending`; parent may reopen to `transferred` | Assigned nanny | **MUST SEND** | Nanny becomes actionable again, but no notification is sent. |
| Void batch | active batch → `voided` | Assigned nanny only when the batch was already actionable | **CONDITIONAL MUST SEND** | If a transferred/received/returned batch is voided, the nanny should be informed that the previously actionable batch is no longer valid. A `pending_approval` void does not require a nanny notification. |

Important authorization facts from the implementation:

- Nanny access is restricted to batches where `monthly_disbursements.nanny_id` equals the logged-in nanny.
- `accountant_staff` scope is restricted through `accountant_nanny_assignments`.
- Manager-level users are not a safe reason by themselves to broadcast every event; recipient selection must follow the actual workflow responsibility.
- Notification delivery must not be implemented by database triggers.
- Notification delivery must occur after the business state transition succeeds and must not roll back the completed accounting/disbursement workflow if notification insertion fails.
- The existing transaction-review helper infrastructure provides event/reference-aware deduplication and should be reused where appropriate rather than introducing another incompatible notification schema.

### Disbursement source-edit constraint

The current `modules/accounting/disbursements.php` blob is a large mixed workflow/UI file. The repository connector can replace a file only with its complete contents; it does not provide a safe server-side patch operation for this file. A direct replacement was therefore **not** made from a reconstructed/partial copy. This is intentional: the audit must not introduce a truncation or unrelated regression merely to close notification findings.

The next source change must patch the complete existing file from its exact current blob, preserving all existing accounting, receipt, authorization, and UI logic, and adding only the required notification calls/helper integration.

## Disbursement notification implementation target

The required implementation should follow these rules:

1. **Transfer → nanny:** notify `monthly_disbursements.nanny_id` after the transfer transaction commits, with a batch-specific event/reference and an actionable `disbursements.php?view={id}` link.
2. **Final receipt/close → accounting:** notify the responsible active accounting recipient(s) after the `received` state is committed. This should not notify the nanny about her own close action.
3. **Close with return → accounting:** notify responsible active accounting recipient(s) after the reversal and `returned` state commit, including the batch reference and actionable batch link.
4. **Reopen batch → nanny:** notify the assigned nanny after the reopen commit.
5. **Reopen item → nanny:** notify the assigned nanny after the item/batch reopen commit.
6. **Void → nanny:** notify the assigned nanny only when the old state was already actionable (`transferred`, `received`, or `returned`); no nanny notification for `pending_approval` void.
7. Every notification must be isolated from the business mutation so a notification failure cannot undo a completed accounting action.
8. Delivery must be event/reference-aware and deduplicated.

## Payroll — no mandatory recipient notification established yet

Payroll approval and payment are currently HR-controlled state transitions, with automatic accounting posting on payment. Repository inspection did not establish a separate human approval recipient who must act after the payroll transition. Therefore no notification is classified as mandatory yet; this remains an architectural decision point rather than a defect.

## Generic notification helper — architectural finding pending caller audit

`config/messaging.php::send_system_notification()` accepts type/reference parameters but currently stores only recipient/title/body/link and derives the link from the reference ID as a message-center URL. Before changing it, all active callers must be audited to determine whether it is legacy, incorrectly implemented, or intentionally limited.

## Notification read state — pending

`config/messaging.php` already has recipient-scoped `mark_notification_read()` support, while the notification widget currently provides mark-all-read. Individual notification links do not yet have a dedicated POST+CSRF mark-read action. This should be addressed after required workflow delivery is complete so read-state work does not obscure missing business notifications.

## 2026-09-12 repository re-check

The current `main` branch was re-read after the local supervisor sponsor-payment notification changes were pushed. The sponsor-payment finding is now **closed in source**: the returned-payment resubmission path sends an FM notification after the guarded state transition succeeds, and both supervisor sponsor-payment creation paths send an FM notification for each newly inserted payment row.

The HR leave recipient-scope finding is also now **closed in source**: `notifyLeaveRoleUsers()` requires `u.is_active = 1` for role recipients.

The disbursement source was re-read directly from the current repository blob. No disbursement notification calls were found in the transfer, final-receipt, return, reopen, or void transitions described above. No old accounting fixtures or verification tests were repeated.

## Next exact work

1. Apply the complete-file disbursement notification patch safely, without reconstructing or truncating the large workflow/UI file.
2. Re-read the resulting source and verify each notification is after the successful state transition/commit, uses the correct recipient scope, event/reference, and actionable link, and is isolated from business-state failure.
3. Audit remaining direct notification writers and generic helper callers.
4. Add individual notification mark-read behavior where appropriate.
5. Run only targeted end-to-end notification tests for newly changed workflows, including the disbursement transitions and the already-fixed supervisor sponsor-payment submit/resubmit paths.

## Protected principle

Do not rerun old accounting tests, recreate old fixtures, or modify historical accounting evidence merely for this notification audit. Notification delivery must not roll back an already-completed business transaction unless the business workflow explicitly requires atomic notification persistence.
