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
- Individual notification items now use a dedicated POST+CSRF mark-read action before redirecting to their actionable link.
- Transaction review has event-aware notification helpers for FM and reference-aware deduplication.

## Completed in this continuation

### Password recovery — fixed

`modules/users/recovery.php` now:

1. notifies the requesting user when recovery is approved;
2. uses an absolute `APP_URL` link for the forced password-change destination;
3. notifies the requesting user when the recovery request is rejected;
4. sends the rejection notification only when the pending request was actually changed to rejected.

Fix commit: `21362ef456db73303734acdede3e332f330aedf0`

### Password recovery GET-mutation audit — fixed

The recovery page previously executed a notification state mutation during ordinary page GET by marking all `type='recovery'` notifications for the current user as read. This violated the notification audit rule that state-changing notification actions must not be performed by GET.

The block was removed from `modules/users/recovery.php`. Opening the recovery page no longer changes notification read state. Existing notifications remain unread until an explicit notification read action is implemented.

Fix commit: `2559b05f757aa408ddab2a4b4c8660a6af68b97b`

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

## Disbursement notifications — implementation confirmed

The current `modules/accounting/disbursements.php` contains the required notification calls at the principal recipient-driven state transitions. These calls were already implemented in the existing source and were re-read as part of this continuation; no replacement of the large workflow/UI file was made.

| Action | State change | Recipient | Classification | Current source status |
|---|---|---|---|---|
| Create batch | `monthly_disbursements` → `pending_approval` | No downstream recipient established yet | **SHOULD NOT SEND** to nanny | No nanny notification is sent while the batch is awaiting approval. |
| Transfer with receipt | `pending_approval` → `transferred` | Assigned nanny (`monthly_disbursements.nanny_id`) | **MUST SEND** | Sends event `disbursement_transferred` after the transfer commit with a batch-specific actionable link. |
| Confirm family item | item `pending` → `paid` | No downstream actor required | **ACTOR CONFIRMATION** | No automatic self-notification is sent to the nanny. |
| Fully close batch | `transferred` → `received`; group → `closed` | Responsible assigned accounting user(s) | **MUST SEND / operational workflow** | Sends event `disbursement_received` after the receipt/close commit to active assigned accounting recipients resolved through `accountant_nanny_assignments`. |
| Close with return | batch `transferred` → `returned`; pending items → `returned`; reversal journal posted; group → `closed` | Active Financial Manager responsibility | **MUST SEND** | Sends event `disbursement_returned` after the successful close-with-return transition. |
| Reopen whole batch | `received`/`returned` → `transferred`; group reopened | Assigned nanny | **MUST SEND** | Sends event `disbursement_reopened` after the reopen commit. |
| Reopen family item | item `paid`/`returned` → `pending`; parent may reopen to `transferred` | Assigned nanny | **MUST SEND** | Sends event `disbursement_item_reopened` after the item/batch reopen commit. |
| Void batch | active batch → `voided` | Assigned nanny only when the batch was already actionable | **CONDITIONAL MUST SEND** | Sends event `disbursement_voided` only when the old state was `transferred` or `received`; pending-approval void does not notify the nanny. |

Important authorization facts from the implementation:

- Nanny access is restricted to batches where `monthly_disbursements.nanny_id` equals the logged-in nanny.
- `accountant_staff` scope is restricted through `accountant_nanny_assignments`.
- Manager-level users are not a safe reason by themselves to broadcast every event; recipient selection follows the actual workflow responsibility.
- Notification delivery is not implemented by database triggers.
- Notification delivery occurs after the relevant business state transition succeeds and is isolated so notification insertion failure does not roll back the completed accounting/disbursement workflow.
- The existing transaction-review helper infrastructure provides event/reference-aware deduplication and is reused rather than introducing another incompatible notification schema.

### Disbursement source-edit constraint

The current `modules/accounting/disbursements.php` blob is a large mixed workflow/UI file. The repository connector can replace a file only with its complete contents; it does not provide a safe server-side patch operation for this file. The existing implementation was therefore preserved rather than replaced from a reconstructed/partial copy.

The seven notification transitions above were previously reviewed for ordering, recipient scope, event/reference values, and notification-error isolation. No old disbursement fixture or accounting verification was repeated during this continuation.

## Disbursement notification implementation record

The required implementation is now recorded as source-complete:

1. **Transfer → nanny:** notify `monthly_disbursements.nanny_id` after the transfer transaction commits, with event `disbursement_transferred` and an actionable batch link.
2. **Final receipt/close → accounting:** notify responsible active assigned accounting recipients after the `received` state is committed; do not notify the nanny about her own close action.
3. **Close with return → financial oversight:** notify active FM recipients after the reversal and `returned` state commit with event `disbursement_returned`.
4. **Reopen batch → nanny:** notify the assigned nanny after the reopen commit with event `disbursement_reopened`.
5. **Reopen item → nanny:** notify the assigned nanny after the item/batch reopen commit with event `disbursement_item_reopened`.
6. **Void → nanny:** notify the assigned nanny only when the old state was already actionable (`transferred` or `received`); no nanny notification for `pending_approval` void.
7. Every notification is placed after the relevant business commit and notification delivery is isolated from the business mutation.
8. Delivery uses event/reference-aware deduplication.

## Payroll — no mandatory recipient notification established yet

Payroll approval and payment are currently HR-controlled state transitions, with automatic accounting posting on payment. Repository inspection did not establish a separate human approval recipient who must act after the payroll transition. Therefore no notification is classified as mandatory yet; this remains an architectural decision point rather than a defect.

## Generic notification helper — architectural finding pending caller audit

`config/messaging.php::send_system_notification()` accepts type/reference parameters but currently stores only recipient/title/body/link and derives the link from the reference ID as a message-center URL. Before changing it, all active callers must be audited to determine whether it is legacy, incorrectly implemented, or intentionally limited.

## Notification read state — fixed for individual workflow links

`config/messaging.php` already has recipient-scoped `mark_notification_read()` support, while the notification widget provides mark-all-read through `modules/notifications/mark_all_read.php`.

A dedicated `modules/notifications/mark_read.php` endpoint now provides the individual action:

- authenticated users only;
- POST only;
- CSRF protected;
- updates only the matching unread notification belonging to the current user;
- redirects only to an `APP_URL`-validated destination;
- catches notification-update failures so the endpoint does not expose a fatal error.

The notification widget now renders individual notification items as POST forms carrying the notification ID and actionable redirect. The widget also loads the notification IDs directly so the existing header query does not need to be reconstructed or altered.

Endpoint commit: `50b4d27991d85c329ffeab884a52cd5d3eb3bcc5`

Widget integration commit: `6d87c6d2496d33c1d4cb1c5f12c8b289906be50a`

Global widget inclusion commit: `96ea51abbef748e1b5122de66f7b2d0e94369049`

## 2026-09-12 notification-writer / GET-mutation re-check

The current repository was re-checked for the notification infrastructure and the known direct notification writers. The notification module now contains both POST+CSRF mark-all-read and POST+CSRF individual mark-read endpoints. `includes/header.php` still contains the legacy `mark_notif_read` URL construction, but `config/functions.php` explicitly strips that parameter from non-POST requests, so the legacy GET mutation is inert.

Two direct workflow notification writers remain confirmed in source:

- `modules/users/recovery.php` writes approval/rejection notifications with current `recipient_user_id`, `type`, `title`, `body`, `link`, and `is_read` columns.
- `modules/hr/leaves.php` uses its local `createLeaveNotification()` writer and active-role recipient query.

The recovery workflow had an additional GET-side mutation that marked all recovery notifications read whenever the recovery-management page was opened. That mutation has been removed. The business approval/rejection notifications themselves remain unchanged and continue to be generated only from POST workflow actions.

`config/messaging.php::send_system_notification()` remains a legacy/general helper whose current implementation does not persist `type`, `reference_id`, or `reference_type` even though those arguments are accepted. It has not been changed speculatively because active callers must be established first. The event-aware helper used by active accounting workflows remains the authoritative path for reference-aware workflow notifications.

### Leave notification isolation — still requires source patch

The leave notification writer itself is not internally exception-isolated. Its caller is inside the workflow `try/catch`, so a notification database failure cannot roll back the already-executed leave state change, but it can make the completed workflow fall into the generic error path instead of cleanly redirecting. This remains a notification-resilience finding and should be patched when the complete current `modules/hr/leaves.php` blob can be safely edited without reconstructing the source.

## Current checkpoint

The notification UI/read-state layer is now integrated in source. No user-side workflow test is required merely to continue the code audit; testing should be performed after the next set of business-notification source changes or when a targeted regression needs confirmation.

## Next exact work

1. Patch leave notification delivery so notification failures are isolated from the user-facing workflow result as well as from the business state mutation.
2. Re-read the resulting leave source and verify recipient scope, active-user filtering, self-notification exclusion, event/reference values, actionable links, and post-commit ordering.
3. Audit remaining direct notification writers/callers that can be recovered from repository source without relying on incomplete code-search indexing.
4. Review the generic `send_system_notification()` helper only after its active callers are established.
5. Run only targeted end-to-end notification tests for newly changed workflows; do not recreate old fixtures or rerun completed accounting tests.

## Protected principle

Do not rerun old accounting tests, recreate old fixtures, or modify historical accounting evidence merely for this notification audit. Notification delivery must not roll back an already-completed business transaction unless the business workflow explicitly requires atomic notification persistence.
