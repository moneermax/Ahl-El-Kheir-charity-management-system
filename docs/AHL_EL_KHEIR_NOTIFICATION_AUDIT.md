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

## Confirmed remaining findings

### HR leave notifications — finding pending

`modules/hr/leaves.php` sends request/approval/rejection notifications, but its role-recipient query does not currently require `u.is_active = 1`. This can allow inactive accounts with the relevant role to receive workflow notifications.

Required correction: restrict role recipients to active users without changing the leave workflow or recipient roles.

### Supervisor sponsor-payment notifications — finding pending

`modules/transactions/create.php` still lacks explicit FM notification delivery for:

1. a newly created supervisor sponsor payment;
2. a supervisor resubmitting a returned sponsor payment.

The event-aware FM helper already exists in `modules/accounting/lib_transaction_review.php` and should be used for both paths with payment-specific reference IDs/types and actionable FM review links.

### Disbursement notifications — workflow gap identified

`modules/accounting/disbursements.php` performs the major state transitions but does not currently provide system notifications for recipient-driven steps such as:

- newly created/assigned batch becoming actionable for the nanny;
- completed transfer becoming actionable for the nanny;
- accountant reopening a batch/item for nanny action;
- nanny closing a batch with a return requiring accountant/management awareness.

These must be mapped against actual assignment/authorization scope before implementation; do not broadcast blindly.

### Payroll — no mandatory recipient notification established yet

Payroll approval and payment are currently HR-controlled state transitions, with automatic accounting posting on payment. Repository inspection did not establish a separate human approval recipient who must act after the payroll transition. Therefore no notification is classified as mandatory yet; this remains an architectural decision point rather than a defect.

### Generic notification helper — architectural finding pending caller audit

`config/messaging.php::send_system_notification()` accepts type/reference parameters but currently stores only recipient/title/body/link and derives the link from the reference ID as a message-center URL. Before changing it, all active callers must be audited to determine whether it is legacy, incorrectly implemented, or intentionally limited.

## Next exact work

1. Wire supervisor sponsor-payment submit/resubmit → active FM notifications.
2. Correct HR leave recipient filtering to active users.
3. Audit disbursement recipient/action notifications and implement only workflow-required events.
4. Audit remaining direct notification writers and generic helper callers.
5. Add individual notification mark-read behavior where appropriate.
6. Run only targeted end-to-end notification tests for newly changed workflows.

## Protected principle

Do not rerun old accounting tests, recreate old fixtures, or modify historical accounting evidence merely for this notification audit. Notification delivery must not roll back an already-completed business transaction unless the business workflow explicitly requires atomic notification persistence.
