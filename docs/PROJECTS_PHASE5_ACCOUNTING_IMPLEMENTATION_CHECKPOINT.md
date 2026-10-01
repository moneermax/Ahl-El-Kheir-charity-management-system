# Projects Phase 5 — Accounting Implementation Checkpoint

Status: IMPLEMENTED — RUNTIME VERIFICATION REQUIRED.

## Accounting event
FM financial approval is the treasury-release event. Each approved funding allocation posts a balanced journal from the exact source account (1100/1200/1300) to the existing project expense account (configured account when present, otherwise 5100).

GM final approval does not create another release journal.

Project Supervisor operational expenses remain project-operational records and do not create a second treasury-expense journal.

## Unused balance
Controlled balance = posted funding - posted operational expenses - recorded returns.

FM can return unused balance through the new controlled return workflow. The return creates a balanced journal back to the original funding source and is recorded in project_funding_returns.

Project closure is blocked while controlled balance is non-zero.

## Safety
Pre-launch FM return-to-review and GM rejection reverse the release journals before changing the approval state. Historical phantom project journals are not changed by this implementation.

## Runtime gate
Apply the migration, then test a fresh controlled project through:
FM approval/release → GM approval → PM launch → PS partial spending → project completion → FM unused-fund return → closure.

Do not run historical project-approval journal correction until this flow passes.


## Closure/refund integration — 2026-10-01

The closure workflow now automatically detects unused controlled project funds when the **primary Project Supervisor requests closure**.

- PS does not directly post a treasury return; FM remains the accounting authority.
- If controlled balance > 0, the closure request is marked/audited as requiring a refund.
- The Projects Manager continues to receive the closure request.
- FM is automatically notified with a direct link to the project refund workflow.
- FM can process the unused balance while the project is in `closure_requested`.
- The refund creates the balanced return journal and `project_funding_returns` record.
- FM processing also notifies the Projects Manager so the existing closure approval can continue.
- The Projects Manager cannot execute final closure while controlled balance remains.
- If controlled balance is already zero, no refund notification is generated.

This preserves the existing PS → Projects Manager closure workflow while integrating the financial reconciliation step rather than allowing PS to bypass FM accounting controls.
