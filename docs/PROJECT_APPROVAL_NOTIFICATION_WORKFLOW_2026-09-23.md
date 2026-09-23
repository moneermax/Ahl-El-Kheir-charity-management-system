# Project Approval Notification Workflow — 2026-09-23

## Required workflow

The project approval chain is:

1. **Projects Manager submits** the project → active FM receives a notification for financial review.
2. **FM approves financially** → active GM/VGM recipients receive a notification for final approval.
3. **FM rejects financially** → the submitting Projects Manager receives a rejection notification containing the FM rejection reason and a link to the project.
4. **GM/VGM approves finally** → active FM recipients receive a notification that the project is finally approved and that payment-evidence/disbursement work can now proceed. The submitting Projects Manager also receives confirmation that final approval has been completed and execution can proceed after payment release.
5. **GM/VGM rejects finally** → the submitting Projects Manager receives a rejection notification containing the final rejection reason and a link to the project.

## Accounting boundary

GM/VGM final approval remains the single accounting release event. Payment-evidence notification/documentation does not create another journal entry.

## Implementation

The existing FM→GM notification remains in `modules/projects/view.php` for the FM financial-approval action.

The remaining project approval notifications are now registered in `modules/accounting/lib_transaction_review.php` for requests targeting `modules/projects/view.php`. They execute through a request-shutdown callback so the notification is sent only after the project action has completed and, for final approval, after the accounting transaction has committed.

Notification delivery uses the existing `ak_transaction_review_notify_event()` infrastructure with workflow reference IDs/types and the existing legacy-column fallback. Duplicate suppression applies only while the equivalent workflow notification remains unread.

## Verification sequence

Use a controlled project test and verify:

- PM submission creates an unread FM notification.
- FM approval creates an unread GM/VGM notification.
- FM rejection creates an unread PM notification with the rejection reason.
- GM/VGM final approval creates an unread FM notification and an unread PM confirmation.
- GM/VGM rejection creates an unread PM notification with the rejection reason.
- Opening/reading an earlier notification does not prevent a later occurrence of the same workflow event from creating a fresh notification.
- Notification failure cannot roll back a completed project approval/rejection action.
- GM final approval creates the accounting release once; notification delivery does not create a second journal.

## Current test project

The current controlled project is `PRJ-0008` / project ID `8`.

Because its FM→GM notification was already missed before this routing correction, the clean runtime test is to return the project to the PM/FM workflow state and submit it again, then verify the notification at each transition. Do not manually insert notifications into the database for the test.
