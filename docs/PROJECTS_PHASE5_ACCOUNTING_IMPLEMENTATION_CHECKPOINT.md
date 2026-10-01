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
