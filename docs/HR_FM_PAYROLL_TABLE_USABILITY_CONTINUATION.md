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


### Source checkpoint details
- Payroll page: `49ab655e6202368139f957eb4faf2a42fce12e75`, with follow-up refinements through `262f7de3b1f7b3528e5fbb396449fcffd5961b9b` and `88376c343a499eddd487125029c715b1564361ac`.
- Selected approval helper: `01f4ea32e3c7f693a7f4c4469d4df5dd9105dc70`, with strict input validation `739f5b4a90e9d84484d42fdde99b2062177cc09b` and the follow-up removing request-time accounting initialization `236f843801632e8db510c4e88930f1f60a48c475`.
- Arabic/English catalogs and page messages were updated in the same follow-up series. The selected-approval helper deliberately does not call runtime schema initializers or seed accounts; it changes only the selected payroll status fields.


## 2026-10-20 — FM payroll workflow redesign checkpoint

**Active task:** extend the existing FM payroll workflow; this is not a replacement payroll implementation.

### Inspection findings confirmed from current `main`

- `payroll` already has row-level `payment_account_id`, status values `draft/approved/paid`, payment date, journal reference, and accounting state `none/ready/posted`. The existing payroll row is the natural place to associate per-employee transfer evidence; a separate period-default setting still needs a schema-backed design.
- `hrPayrollResolvePaymentAccount()` is the existing account-selection boundary. Its validation and fallback must be preserved; new period/group/employee preferences must resolve through the same active-account rules rather than hardcoding example account codes.
- `hrPayrollChangeStatus()` currently performs the approved-to-paid transition and accounting posting, then attempts employee-statement and GM period notifications. The redesign must separate proof preparation from explicit post-transfer confirmation and preserve the transaction boundary.
- `hrPayrollNotifyStatementAvailable()` currently returns `void` and catches failures. Completion needs a reliable success result while recognizing an already-existing matching notification as success.
- `hrGmPayrollPeriodState()` currently evaluates payroll status/accounting completion without individual transfer-proof or employee-notification completion. Its existing eligibility rules and duplicate-notification protection must be retained and extended.
- `payroll_statement.php` checks the logged-in user's employee identity and requires paid payroll plus posted accounting. Those authorization conditions must remain unchanged.
- `storage/.htaccess` currently contains `Require all denied`; proof delivery should use authenticated PHP streaming, not public file paths.
- Existing approved/paid payroll history must not be silently treated as proof-complete. Missing legacy proof needs an explicit evidence-attachment path that does not alter paid status, historical financial amounts, or posted accounting.

### Required sequence and documentation gate

The user requires documentation to be updated **after each completed step and before proceeding to the next**. Accordingly, each implementation stage must end with its own documented checkpoint and commit before the next stage begins. Do not mark the whole redesign complete until all stages and required local verification are complete.

### Current boundary

This checkpoint records source inspection only. No payroll source, schema, migration, accounting record, or notification was changed. Full account-helper, upload-handler and schema details must be confirmed before choosing the exact migration and file-serving implementation. Local XAMPP verification remains the user's responsibility when exact commands are supplied.
