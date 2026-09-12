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

## Confirmed remaining findings

### HR leave notifications — finding pending

`modules/hr/leaves.php` sends request/approval/rejection notifications, but its role-recipient query does not currently require `u.is_active = 1`. This can allow inactive accounts with the relevant role to receive workflow notifications.

Required correction: restrict role recipients to active users without changing the leave workflow or recipient roles.

### Supervisor sponsor-payment notifications — finding pending

`modules/transactions/create.php` still lacks explicit FM notification delivery for:

1. a newly created supervisor sponsor payment;
2. a supervisor resubmitting a returned sponsor payment.

The event-aware FM helper already exists in `modules/accounting/lib_transaction_review.php` and should be used for both paths with payment-specific reference IDs/types and actionable FM review links.

### Disbursement notifications — detailed workflow audit

`modules/accounting/disbursements.php` was inspected across its principal state transitions. The file currently performs the state changes and audit logging but contains no notification delivery for the recipient-driven transitions below.

| Action | State change | Recipient | Classification | Finding |
|---|---|---|---|---|
| Create batch | `monthly_disbursements` → `pending_approval` | Reviewer/manager | Required if this is a human approval queue | No notification currently sent. The exact reviewer scope must follow the existing authorization model rather than a blanket broadcast. |
| Transfer with receipt | `pending_approval` → `transferred` | Assigned nanny (`monthly_disbursements.nanny_id`) | **Required workflow notification** | Nanny immediately becomes able to confirm family items, so the nanny must be notified. |
| Confirm family item | item `pending` → `paid` | No downstream actor required by this transition | No notification | Correct to avoid notification spam; this is the nanny's own action. |
| Fully close batch | `transferred` → `received` | Accountant/manager | Operational / likely required for monitoring | No notification currently sent. Recipient scope needs to follow the assigned-accountant relationship where applicable. |
| Close with return | batch `transferred` → `returned`; pending items → `returned`; reversal journal posted | Assigned accountant/financial oversight | **Required workflow notification** | The nanny's return creates a financial/accounting follow-up event. The responsible accountant/financial role must be notified. |
| Reopen whole batch | `received`/`returned` → `transferred` | Assigned nanny | **Required workflow notification** | Nanny becomes actionable again, but no notification is sent. |
| Reopen family item | item `paid`/`returned` → `pending` | Assigned nanny | **Required workflow notification** | Nanny becomes actionable again, but no notification is sent. |
| Void batch | active batch → `voided` | Assigned nanny when the batch had become actionable; otherwise no nanny action | Conditional operational notification | If a transferred/received/returned batch is voided, the nanny should be informed that the previously actionable batch is no longer valid. A pending-approval void does not require a nanny notification. |

Important authorization facts from the implementation:

- Nanny access is restricted to batches where `monthly_disbursements.nanny_id` equals the logged-in nanny.
- `accountant_staff` scope is restricted through `accountant_nanny_assignments`.
- Manager-level users are not a safe reason by themselves to broadcast every event; recipient selection must follow the actual workflow responsibility.
- Notification delivery must not be implemented by changing accounting state logic or by using database triggers.

The disbursement audit therefore establishes **at least four mandatory recipient-driven notification events**: transfer→nanny, return→responsible accounting user(s), reopen-batch→nanny, and reopen-item→nanny. A fifth conditional event exists for voiding an already-actionable batch. Batch creation/approval and final receipt require a recipient-scope decision based on the existing operational responsibility before code is added.

### Payroll — no mandatory recipient notification established yet

Payroll approval and payment are currently HR-controlled state transitions, with automatic accounting posting on payment. Repository inspection did not establish a separate human approval recipient who must act after the payroll transition. Therefore no notification is classified as mandatory yet; this remains an architectural decision point rather than a defect.

### Generic notification helper — architectural finding pending caller audit

`config/messaging.php::send_system_notification()` accepts type/reference parameters but currently stores only recipient/title/body/link and derives the link from the reference ID as a message-center URL. Before changing it, all active callers must be audited to determine whether it is legacy, incorrectly implemented, or intentionally limited.

### Notification read state — pending

`config/messaging.php` already has recipient-scoped `mark_notification_read()` support, while the notification widget currently provides mark-all-read. Individual notification links do not yet have a dedicated POST+CSRF mark-read action. This should be addressed after required workflow delivery is complete so read-state work does not obscure missing business notifications.

## Next exact work

1. Wire supervisor sponsor-payment submit/resubmit → active FM notifications.
2. Correct HR leave recipient filtering to active users.
3. Implement the confirmed disbursement notifications using existing authorization scope and event/reference-aware helpers.
4. Audit remaining direct notification writers and generic helper callers.
5. Add individual notification mark-read behavior where appropriate.
6. Run only targeted end-to-end notification tests for newly changed workflows.

## Protected principle

Do not rerun old accounting tests, recreate old fixtures, or modify historical accounting evidence merely for this notification audit. Notification delivery must not roll back an already-completed business transaction unless the business workflow explicitly requires atomic notification persistence.
