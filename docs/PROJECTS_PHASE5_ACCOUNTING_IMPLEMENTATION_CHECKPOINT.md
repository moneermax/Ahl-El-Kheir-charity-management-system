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

The closure workflow follows a strict role boundary:

- The primary Project Supervisor submits the project closure request. **That is the end of the PS financial/closure responsibility.**
- The system checks the controlled balance at the time of the closure request.
- If controlled balance > 0, FM is automatically notified and receives the refund-processing link. **Only FM handles the savings/refund and accounting reconciliation.**
- If controlled balance = 0, **no FM notification is generated** and no refund workflow is created.
- PS is not asked to return, confirm, monitor, or complete the saved-funds process after submitting the closure request.
- The Projects Manager continues the existing project-closure workflow; final closure remains blocked while controlled balance remains.
- FM processing creates the balanced return journal and `project_funding_returns` record, then notifies the Projects Manager that the financial reconciliation is complete.

This preserves the existing PS → Projects Manager closure request while making any savings reconciliation an FM-only financial task.


### Static audit correction — repeated/partial savings returns

The FM return path was hardened before runtime verification: one funding allocation may require a partial return and a later additional return. The reconciliation table therefore indexes (rather than uniquely constrains) the allocation, the accounting helper validates cumulative returned amount against both the allocation amount and the project's current controlled balance, and the FM UI aggregates all returns per allocation. This prevents a partial first return from making the remaining savings permanently unrecoverable.
