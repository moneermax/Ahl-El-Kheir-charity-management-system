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

`modules/hr/leaves.php::notifyLeaveRoleUsers()` restricts role recipients to active users with `u.is_active = 1`. This prevents inactive accounts from receiving leave workflow notifications while preserving the existing role-based recipient scope.

The local `createLeaveNotification()` writer is now internally exception-isolated. Notification delivery failures are logged and cannot alter the already-completed leave state transition or turn a successful workflow into a generic error response.

Source fix commit: `ff0e9e2c74bdde820be4a72e1d15bc04629401cc`.

The resulting leave source was re-read after the user's push and the replacement function was verified structurally/syntactically. No user-side leave workflow test is required merely to close this resilience finding.

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

## Direct notification writers / caller audit — current checkpoint

The remaining notification-producing source paths were re-read using the current `main` branch and the repository's notification-related commit history.

### Confirmed active direct writers

1. `modules/users/recovery.php` writes password-recovery approval/rejection notifications directly to `notifications`. These writes are POST-only, CSRF-protected by the workflow, recipient-specific, and notification failures are isolated. The rejection writer is additionally guarded by the successful pending→rejected state change.
2. `modules/hr/leaves.php` uses its local `createLeaveNotification()` writer. It now has internal exception isolation and active-user role filtering. Its workflow writes occur only after the relevant leave state change succeeds.

These two local writers are intentionally retained because their workflows are already self-contained and do not need to be coupled to the accounting transaction-review helper.

### Confirmed active notification helper path

Accounting transaction review, supervisor sponsor-payment review, transaction cancellation, and disbursement workflow notifications use the event/reference-aware notification infrastructure rather than the generic `send_system_notification()` helper. The current helper persists workflow type/reference fields where supplied, uses active recipient scope, deduplicates by workflow reference, and isolates notification failures from the committed business action.

### Generic `send_system_notification()` — retained, not changed speculatively

`config/messaging.php::send_system_notification()` accepts `type`, `referenceType`, and `referenceId`, but its implementation stores only recipient/title/body/link and derives a message-center link from the reference ID. The repository code-search facility does not currently return reliable caller results for this exact symbol, and notification-related commit history does not identify an active production caller.

Therefore the audit does **not** claim that the helper is definitively unused. It is classified as a **legacy/compatibility helper with no recoverable active caller in the current repository audit**, and it remains unchanged to avoid breaking an undiscovered caller. If a future active caller is identified, the helper must be reviewed before use for workflow notifications because its type/reference arguments are not fully persisted.

## Notification read state — fixed for individual workflow links

`config/messaging.php` already has recipient-scoped `mark_notification_read()` support, while the notification widget provides mark-all-read through `modules/notifications/mark_all_read.php`.

A dedicated `modules/notifications/mark_read.php` endpoint now provides the individual action:

- authenticated users only;
- POST only;
- CSRF protected;
- updates only the matching unread notification belonging to the current user;
- safely normalizes legacy relative application links against `APP_BASE_PATH`;
- preserves valid absolute `APP_URL` links;
- rejects external and protocol-relative redirects;
- catches notification-update failures so the endpoint does not expose a fatal error.

The notification widget renders individual notification items as POST forms carrying the notification ID and actionable redirect. The widget also loads the notification IDs directly so the existing header query does not need to be reconstructed or altered.

The mark-all endpoint applies the same safe redirect normalization so its normal relative `REQUEST_URI` destination remains on the current page instead of falling back to the dashboard.

Initial individual-read integration commits: `50b4d27991d85c329ffeab884a52cd5d3eb3bcc5` and `6d87c6d2496d33c1d4cb1c5f12c8b289906be50a`.  
Global widget inclusion commit: `96ea51abbef748e1b5122de66f7b2d0e94369049`.  
Legacy-relative redirect normalization commits: `ac13745f7888347423614e38047e37348b6ffb5d` and `3a567bfc48f98f1ea645c51d2fb1e46d4f412b81`.

## 2026-09-12 targeted password-recovery notification regression — fixed and user-tested

A targeted regression was found after the initial relative-link compatibility fix. Existing password-recovery notification rows could contain a legacy link beginning with the application directory itself, such as `AhlElKheir/modules/users/recovery.php`. The first normalization pass prepended `APP_URL` without removing the already-present application base path, producing the invalid duplicated path:

`/AhlElKheir/AhlElKheir/modules/users/recovery.php`

`modules/notifications/mark_read.php` and `modules/notifications/mark_all_read.php` were corrected to detect `APP_BASE_PATH`, remove it from a relative destination when already present, and then prepend the current `APP_URL`. Absolute application URLs remain unchanged, while external and protocol-relative redirects remain rejected.

The user subsequently retested the existing HR password-recovery notification and confirmed that it now opens the correct recovery page successfully at:

`http://localhost:8081/AhlElKheir/modules/users/recovery.php`

No notification rows were manually rewritten and no old accounting fixtures or tests were recreated.

## 2026-09-12 notification-writer / GET-mutation re-check

The current repository was re-checked for the notification infrastructure and the known direct notification writers. The notification module now contains both POST+CSRF mark-all-read and POST+CSRF individual mark-read endpoints. `includes/header.php` still contains the legacy `mark_notif_read` URL construction, but `config/functions.php` explicitly strips that parameter from non-POST requests, so the legacy GET mutation is inert.

The direct-writer audit now confirms two self-contained workflow writers: password recovery and HR leave. Accounting/disbursement workflow notifications are routed through the event/reference-aware helper path. No additional active direct writer was established from the current repository source and notification-related history available to the audit tooling.

The recovery workflow had an additional GET-side mutation that marked all recovery notifications read whenever the recovery-management page was opened. That mutation has been removed. The business approval/rejection notifications themselves remain unchanged and continue to be generated only from POST workflow actions.

`config/messaging.php::send_system_notification()` remains a legacy/general helper whose current implementation does not persist `type`, `reference_id`, or `reference_type` even though those arguments are accepted. It has not been changed speculatively because an active caller could not be established reliably. The event-aware helper used by active accounting workflows remains the authoritative path for reference-aware workflow notifications.

## Current checkpoint

The notification UI/read-state layer is integrated in source and the legacy-relative-link compatibility issue is now closed and user-tested. The leave notification resilience finding is closed. The remaining notification architecture finding is the legacy generic helper described above; it is intentionally deferred until an active caller can be established with reliable repository evidence.

No additional user-side workflow test is required at this checkpoint. Testing should be performed only after a newly changed business-notification workflow or when a targeted regression requires confirmation.

## Next exact work

1. If reliable caller evidence for `send_system_notification()` becomes available, audit each caller before deciding whether to modernize or retire the helper.
2. Continue a source-level sweep for notification-producing SQL only where repository tooling can provide complete, reliable evidence; do not treat failed code-search indexing as proof of absence.
3. Run only targeted end-to-end notification tests for newly changed workflows; do not recreate old fixtures or rerun completed accounting tests.

## Protected principle

Do not rerun old accounting tests, recreate old fixtures, or modify historical accounting evidence merely for this notification audit. Notification delivery must not roll back an already-completed business transaction unless the business workflow explicitly requires atomic notification persistence.
