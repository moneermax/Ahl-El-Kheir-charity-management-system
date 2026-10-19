# FM Payroll Table Usability — Continuation Checkpoint

**Date:** 2026-10-19  
**Status:** Source committed to `main`; local PHP syntax/browser verification pending.  
**Target:** `modules/hr/payroll.php?lang=en`, as viewed by FM.

## User request

- Reduce the payroll table's excessive width and horizontal scrolling.
- Keep column headings visible during vertical scrolling.
- Avoid losing the user's table position after row actions.
- Remove only information that is redundant for the table's purpose.

## Source findings and changes

- The previous table used Bootstrap's `.table-responsive` horizontal overflow wrapper without a bounded vertical scroll region. The table header therefore had no dedicated vertical scroll container for sticky positioning.
- The shared same-page POST handler in `assets/js/app.js` preserved the window viewport but did not preserve the scroll offsets of a nested table scroller that is replaced with the `.content` fragment.
- Draft component editing used a form spanning multiple table cells, which is invalid table/form markup.

Committed source changes:
- `modules/hr/payroll.php` (commits `85269f123d042a08735680d487c78b0c7715783b` and form-markup follow-up `fecd42a9edbae3ff91b1092f81bcb4dc8c06eeb8`):
  - compact fixed-layout table with explicit column proportions and smaller cell spacing;
  - bounded vertical/horizontal table scroller with sticky headings;
  - merged the payroll-status and accounting-status cells while retaining both badges and the journal-entry ID;
  - removed the repeated salary-history effective date from each row and its now-unneeded display-only join;
  - corrected draft component form markup using a valid form in the employee cell and explicit `form` associations for the amount inputs/save button;
  - tagged the table scroller for scroll preservation.
- `assets/js/app.js` (commit `f9cf0c0a2e079ae297c1674a8d18b7b150d738f0`):
  - captures/restores scroll offsets for explicitly marked nested scroll containers after same-page POST fragment replacement. Existing window-scroll behavior remains in place.

## Preserved behavior

No payroll calculations, status transitions, authorization rules, payment actions, accounting posting logic, database schema, or payroll/accounting rows were changed. No migration or runtime DDL is involved.

## Verification boundary

The source changes are committed on `main`. Local PHP syntax checks and real-browser visual/interaction verification remain pending. Verify the table layout and sticky header by scrolling the existing payroll period; if an already-existing draft/approved row is available and safe to act on, confirm the scroller retains its position after its normal action. Do not create/pay test payrolls or mutate paid payroll/accounting data solely for this UI task. Reopen on an exact visual/behavioral defect rather than repeating unrelated payroll suites.


## 2026-10-19 — FM payroll refinement follow-up

Source is committed on `main`; local syntax and browser verification remain pending.

- Replaced fixed percentage widths with automatic table sizing and short translated column headings. Full labels remain in header tooltips, and table values can wrap.
- Changed the sticky table header to dark navy with white text.
- Added stable Arabic/English translation keys for the payroll page, including English month names, period controls, summary cards, headings, actions, statuses, policy text, and page-specific messages.
- Added checkboxes for draft rows and a select-all-drafts control. The **Approve selected** button displays the selection count and remains disabled until a draft is selected. The existing **Approve all drafts** action remains available.
- Added `hrPayrollApproveSelected()` to validate all selected IDs belong to the chosen period and are still drafts with a positive basic salary. It locks and approves the selected rows atomically, setting only `status='approved'` and `accounting_status='ready'`. It does not pay rows or post accounting.
- Kept Save because edited allowances, overtime, and general deductions require explicit persistence and trigger existing recalculation of attendance deduction, salary-advance deduction, and scheduled incentives. The compact Save action now sits beside Approve instead of below Net salary.

No schema or migration changes were made. Static source audit confirms all literal `hr.payroll...` keys used by the page exist in both catalogs, the sticky dark header and checkbox/form wiring exist, fixed percentage widths are removed, and selected approval has no payment/posting path. Local PHP lint and focused browser verification remain pending. Do not submit real approvals/payments just to test the UI.
