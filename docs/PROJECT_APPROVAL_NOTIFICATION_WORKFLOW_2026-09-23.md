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


## 2026-09-25 — Runtime notification checkpoint

The controlled project PRJ-0010 / project ID 10 (اختبار المشاريع 2026) was used to verify the corrected notification routing.

Observed successfully: PM submission → FM financial-review notification; FM approval → GM final-review notification; GM final approval → PM final-approval notification; existing FM post-final-approval execution/completion notifications also arrived.

The GM final approval notification is therefore now observed in the local runtime test.

Important lifecycle clarification: final GM/VGM approval does not launch the project. It leaves the project planned and returns the next action to PM. The next notification event to certify is the explicit PM launch event, which must notify the assigned active Project Supervisor. The Project Supervisor must not receive an execution notification merely from assignment before launch.

### Next controlled notification test

Using the same PRJ-0010 test project: 1) PM confirms the project is approved and waiting for launch. 2) PM explicitly launches the project. 3) Verify one launch notification is created for the assigned Project Supervisor. 4) Verify the project appears on the supervisor dashboard only after launch. 5) Verify direct project access is allowed to the assigned supervisor after launch and remains blocked before launch. 6) Do not manually insert notifications; use the actual workflow action.
