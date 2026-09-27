# Projects Runtime / Expense Workflow Checkpoint — 2026-09-26

## Purpose

This document records the verified project-expense/payment workflow and the exact point from which the next session should continue.

## Current project under controlled testing

- Project: **PRJ-0010 — اختبار المشاريع 2026**
- Project ID: **10**
- The project has already passed the PM → FM → GM approval workflow.
- GM/VGM final approval is a funding reservation/allocation event, **not an actual spend**.
- PM must explicitly launch the project; final approval alone does not activate it.
- The project is intentionally **not yet closed** because there is still a remaining item/task that must be completed before PM closure.

## Project-funded expense model — final working rule

For the Project Supervisor's project expenses, payment is made from the project budget already transferred/allocated to the Project Supervisor. This is **not** an organization cash/bank/e-wallet payment and does **not** create an organization accounting journal entry.

When the Project Supervisor records a payment:

1. A payment receipt is required.
2. The expense is recorded directly as a **posted** project expense.
3. The amount is deducted from the project's available approved budget.
4. No organization expense account is selected.
5. No organization payment account is selected.
6. No organizational journal entry is created.
7. The project expense remains visible in the **المصروفات** section.
8. A project payment voucher may be printed where supported.

### Existing/legacy draft expenses

Older draft project expenses were created under the previous workflow. They are **not automatically converted to posted** records because doing so without a payment receipt would falsely represent a completed payment.

The existing **تعديل / تسجيل الدفع** action is now the intended migration path for these records: open the draft expense, provide/confirm the payment receipt, save the edit, and the expense becomes **posted**.

## Edit/Delete behavior

The user explicitly confirmed that Project Supervisor expense records must retain **تعديل** and **حذف** actions after posting.

The final implementation therefore permits the assigned primary Project Supervisor to edit or delete project-funded expenses in both states: `draft` and `posted`. The server-side POST handlers and the UI use the same rule; this is not a visual-only change.

## Important implementation commits

- `14faa7f5da628f0eb13b19eeddffa36463286d6a` — temporary legacy finalization bridge; subsequently removed and **must not be revived**.
- `37e037b0c7b9cf2052dd7adb49ec7fc67ad1eae8` — permanently blocks the obsolete organization-account expense-entry path for Projects.
- `6e4dcf4c8aa0437d6ff50043e9e0c9bf84c1874e` — removed obsolete `project_expense_finalize.php` after moving finalization into the existing edit workflow.
- `b7ca93a4892faa47074428aa2fe4420e754d1ba4` — restored the project-expense success toast/feedback path.
- `f7d7062fdef265bce997baf1e53dc7ce70ffd6` — aligned the project-expense action/summary logic with the posted project-budget model.
- `5dda62c4bc74662fe0e9c415ddbbed88e9263a94` — final fix: Project Supervisor can edit/delete both draft and posted project-funded expenses; user runtime test confirmed the workflow is now complete.

## Expense/accounting boundary that must not be regressed

Do not reintroduce the old finance-form path that asks the Project Supervisor to choose organization expense/payment accounts for these project-funded expenses.

The obsolete legacy `add_expense` path remains blocked in `modules/projects/view.php` for compatibility/history; it must not become the active Projects expense workflow again.

Do not create a new standalone expense-finalization page. The finalization action belongs in the existing project expense edit workflow.

## Project closure workflow — schema correction

The PM closure workflow is already implemented in `modules/projects/view.php` and correctly requires a prior `closure_requested` record from the primary Project Supervisor. It also blocks closure when unposted project expenses remain, records the closure summary/reason, updates the lifecycle and legacy project status, and notifies the GM/VGM.

During the first runtime closure test on PRJ-0010, the closure action failed because `project_lifecycle.close_reason` was missing from the deployed schema while the existing closure handler already writes that field. This was a repository/schema mismatch, not a PRJ-0010 data problem.

A migration was added:

- `database/migrations/2026-09-27_project_closure_reason.sql`
- Commit: `b7bd414fce050b271e9653e47c4becd304622398`

The migration adds `project_lifecycle.close_reason` as a nullable `VARCHAR(64)` using the project's migration-only schema-change rule. No runtime DDL, trigger, or view was introduced.

**PRJ-0010 must remain open until this migration is applied and the PM closure workflow is runtime-tested successfully.**

## Current test state

The user has confirmed that the latest project-expense workflow is **done**.

The project is deliberately **not closed yet**. The next runtime step is to apply the new closure-schema migration, retry PM → إغلاق المشروع on PRJ-0010, and verify the complete closure workflow before treating the project as closed.

## Next session starting point

Do not restart the completed expense work.

Start by inspecting the current `main` branch and the current documentation. Apply/verify the closure schema migration, then perform the appropriate final PM closure/runtime verification when the project is actually ready.
