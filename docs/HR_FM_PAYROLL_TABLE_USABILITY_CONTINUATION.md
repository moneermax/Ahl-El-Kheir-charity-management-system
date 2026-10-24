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


## 2026-10-20 — Step 2 account, proof and schema audit

### Source-confirmed findings

- `hrPayrollResolvePaymentAccount(PDO $pdo, ?int $requestedAccountId)` accepts any explicitly requested account whose `accounts.is_active = 1`; it does not check an account type/category. When no account is requested, it falls back to active account code `1200`. Preserve the existing fallback unless the actual account policy/schema proves a necessary change.
- The current FM row action sends an optional `payment_account_id`, but the rendered payroll table has no account selector, proof upload, or separate transfer-confirmation step. `hrPayrollChangeStatus(..., 'paid')` currently changes status to paid and posts accounting in the same transaction; it must no longer be the route for an unconfirmed transfer.
- `payroll.payment_account_id` is already read by accounting posting, so per-employee account overrides can use the existing row field. A period default is not represented in the inspected payroll helper/page. Group overrides can be applied as explicit per-row choices, provided the UI makes the affected rows clear and preserves individual overrides.
- Employee notification helper `hrPayrollNotifyStatementAvailable()` currently returns `void`; both an existing matching notification and a failed insert are indistinguishable to completion logic. GM completion currently checks only paid + posted + journal ID. Both helpers need a reliable, idempotent success result/check after the payment workflow is separated.
- The salary statement checks login, resolves the employee from the logged-in user, and requires the requested payroll to belong to that employee and be paid + posted. Keep this authorization boundary. `storage/.htaccess` denies direct web access, so proof downloads should go through an authenticated PHP endpoint that authorizes the employee/role against the payroll record and streams a generated storage path, never a user-supplied path.
- The inspected statement source contains no proof rendering/download path. Repository code-search returned no usable upload/stream examples, so do not copy a guessed pattern.
- The repository's schema file fetch returned an empty body even when requesting a bounded line range. This is a tooling/content retrieval limitation, not evidence that the schema is empty. Exact DDL types, foreign-key conventions, account classifications and current storage conventions are therefore **not yet verified**. Do not write or apply a migration until those facts are read from the user's actual local schema/repository checkout.

### Proposed model — design only, not a migration

1. Keep the existing `payroll.payment_account_id` as the resolved account for each employee. A period-scoped settings record is needed for the default account, keyed uniquely by payroll month/year; group and individual choices should resolve to the row-level account before transfer confirmation. The account dropdown must be populated from actual active account records and use the same validation policy as the server-side resolver.
2. Store proof metadata in a dedicated per-payroll proof record rather than public web storage or a shared group file. Metadata should include a server-generated opaque storage name, original display name, validated media type/size, uploader and upload timestamp, plus distinct evidence/confirmation fields. Unique payroll linkage should prevent multiple active proofs for one payroll while allowing replacement to be audited if the existing audit conventions support it.
3. Model the states distinctly: approved → proof uploaded/prepared → transfer explicitly confirmed → accounting posted → employee notification recorded. Upload alone is not confirmation. Confirmation and accounting posting must be one transaction boundary with row locking and duplicate-submit protection; notify only after commit. GM completion requires every eligible employee to satisfy all required states and have an individual proof.
4. For legacy paid+posted payrolls, attach evidence without changing paid status, payment date, journal IDs or accounting rows. Legacy evidence attachment must be visibly distinct from a new transfer confirmation and must not cause accounting to repost. Until evidence is attached and validated, such a row is not proof-complete.
5. Use a private storage directory guarded by server configuration, randomized storage names, MIME/content validation, upload size limits, safe streaming headers, authorization on every request, and no direct path exposure. Exact implementation must match the project's verified PHP/storage conventions.

### Step 2 boundary / dependency

The source-level workflow and proposed model are documented, but Step 2 is not marked fully closed because the schema source could not be retrieved through the repository connector and no upload-serving convention was found. Before creating the migration, obtain the relevant local `SHOW CREATE TABLE` output for `payroll`, `accounts`, `users`, and `notifications`, plus the relevant employee/user key definitions, and inspect local migration/storage examples. No application source, migration, live schema, payroll row or accounting record was changed in this step.


## 2026-10-20 — Step 2 closed: schema verified and data model finalized

The user supplied actual local `SHOW CREATE TABLE` output from `ahl_el_kheir`. The relevant schema facts are now verified:

- `payroll.id`, `employee_id`, `journal_entry_id`, `created_by`, and `payment_account_id` are `INT UNSIGNED`; `month` and `year` are `INT`; payroll amount fields use existing `DECIMAL` definitions. The table is InnoDB and has unique employee/month/year keys (two equivalent unique keys already exist; do not modify them in this task).
- `accounts.id` is `INT UNSIGNED`; `account_type` is enum asset/liability/equity/revenue/expense; `is_active` is a boolean-like tinyint. The existing resolver accepts any active account and falls back to active code `1200`. This redesign should preserve that resolver policy unless a separately approved business rule changes it.
- `employees.user_id` is nullable, unique, and references `users.id`; notification recipient IDs are nullable unsigned ints without an FK in this schema. Missing employee user links must be handled explicitly rather than treated as notification success.
- The real migration convention uses explicit InnoDB table definitions, utf8mb4 collation, named unique/index/foreign-key constraints, and RESTRICT semantics where records are audit evidence. No runtime DDL is allowed.

### Finalized proposed data model (not yet applied)

1. Add a period settings table, one row per `(month, year)`, with a required default account ID, creator/updater user IDs and timestamps. The default account is resolved into each payroll row's existing `payment_account_id` when preparing transfers; individual row choices remain overrides. Group override is a UI operation that explicitly sets the selected rows and never silently overwrites an individual choice.
2. Add a proof/evidence table with one row per payroll ID and a unique key on that ID. Store a server-generated opaque storage key (never an absolute/public path), original display filename, validated MIME type, byte size, uploader and upload timestamp, proof state, and confirmation actor/time. Use separate states for a prepared new-transfer proof, confirmed new transfer, and legacy paid evidence. Legacy evidence is never interpreted as a new transfer confirmation.
3. For new transfers, only the explicit confirmation action may mark proof confirmed, mark payroll paid and post accounting. It must lock the payroll/proof rows, verify approved status and a valid proof, then commit confirmation + paid status + accounting in one transaction; employee notification attempts happen after commit. Uploading a file does not confirm payment.
4. Employee statement notification success means either an existing matching notification is found or a new notification insert succeeds. Any lookup/insert exception or missing linked active user is failure for completion tracking, without rolling back committed accounting.
5. GM period completion must use the existing eligible-employee policy and require for every eligible row: paid + posted + journal ID, an individual proof (confirmed new-transfer proof or explicitly attached legacy evidence), and employee statement notification recorded. GM notifications remain idempotent by recipient/link/title and only occur after all requirements pass.
6. Legacy paid/posted rows get an evidence-only attachment path. It must not update payroll status/date/account IDs/journal IDs, modify journal entries, rerun deductions, or create a new transfer confirmation. Such evidence can satisfy the evidence-presence gate only after successful attachment/validation; the existing notification requirement remains independently evaluated.
7. Private file delivery must authorize the request against the payroll record on every download. Employee self-service may only access their own proof; authorized payroll/finance roles may access the proofs needed for their duties. Store files under a server-controlled private directory covered by deny-all web-server configuration; generate random storage names, validate upload error/size and MIME/content, disallow executable content, and stream with safe content-disposition and no-cache headers. Do not expose the storage key as a URL.

### Account-policy boundary

The schema supports account type but the existing resolver only enforces `is_active = 1` and fallback code `1200`. To avoid silently changing current accounting rules, this task preserves that server-side account policy for now. The account selector must be populated from actual active accounts and display code/name; a stricter asset-only rule requires an explicit business decision, not an inferred change.

### Step 2 result and next gate

Step 2 is closed at the design boundary: schema evidence was supplied, the relevant types and key relationships were reviewed, a real migration example was inspected, and the data model/state transitions are documented. No migration, application source, live schema or payroll/accounting data has been changed. Before any DDL is applied, the exact migration SQL must be reviewed as a standalone artifact and then applied only through the project's migration process. Proceed next to Step 3: author the migration and private-proof storage contract, document and commit that step, then move to implementation.


## 2026-10-20 — Step 3 migration draft and private-proof contract

Created `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql` in commit `f1225411c429f0b0eb9486ccec03f6be02e1ed8a`. The migration draft adds:

- `hr_payroll_period_settings`: one row per month/year with a required default account, optional creator/updater references and timestamps.
- `hr_payroll_payment_proofs`: one evidence record per payroll, unique opaque storage key, original filename, detected MIME type, byte size, uploader/time, and state `prepared`, `confirmed`, or `legacy_evidence` with confirmation actor/time.

The draft uses InnoDB, utf8mb4 Unicode collation, unsigned integer IDs consistent with the inspected schema, named keys/constraints, and RESTRICT semantics for payroll/account parents. The payroll foreign key uses RESTRICT intentionally so a payroll row cannot be deleted after proof evidence has been attached. No columns were added to `payroll`, and no historical rows are backfilled.

### Private-proof storage contract for implementation

- Store proof bytes under a server-controlled directory outside public serving, retaining the existing deny-all protection. The database `storage_key` is a random opaque identifier, not an absolute path or user-selected filename. Resolve it only beneath the fixed private directory and reject path separators/traversal.
- Accept only a documented allowlist of evidence formats; check PHP upload error, actual byte size, detected MIME/content, and extension consistency. Do not trust the browser-supplied MIME type. Reject executable/active content. Exact size limits and allowed formats must be set in the implementation after checking current PHP configuration and confirming the required formats; they are not inferred from the schema.
- Do not overwrite existing evidence silently. The unique payroll key means the first implementation must reject a second upload and surface a controlled error; a replacement/audit workflow is a separate design decision.
- Downloads go through a PHP endpoint. It must verify login, resolve the payroll/evidence record by ID, enforce employee ownership for employee self-service and the existing role/permission boundary for staff access, then stream only the server-resolved file with safe content headers and private/no-store cache headers. Never return the physical path or expose a direct public URL.
- Uploading sets state to `prepared`; only the explicit post-transfer confirmation transaction may set `confirmed`. A legacy evidence action sets `legacy_evidence` and must not alter any payroll/payment/accounting columns.
- Because a proof is unique per payroll, duplicate upload/confirmation requests must be rejected or treated idempotently based on persisted state; no duplicate accounting posting is permitted. File persistence and DB changes require compensation/cleanup if one side fails.

### Step 3 boundary

The migration has been authored and committed to `main`, but it has **not** been executed against any database. No PHP workflow or file endpoint was changed. Before application, review the exact migration SQL and verify local migration execution convention and private storage directory configuration. Next step: implement the period/account preparation and proof upload/download layer, with its own documentation checkpoint before moving to payment confirmation/accounting changes.


## 2026-10-20 — Step 4 source checkpoint: account/proof preparation layer

Added procedural helpers in `modules/hr/lib_payroll_transfer.php` and the authorized download endpoint `modules/hr/payroll_proof_download.php`; wired preparation actions and UI into `modules/hr/payroll.php`.

- Period default account settings are stored per month/year and applied only to payroll rows without an existing account choice. Individual/group overrides validate that the account is active using the existing `hrPayrollResolvePaymentAccount()` policy and restrict updates to approved rows in the selected period. Saving the default does not overwrite explicit row choices.
- Proof uploads accept PDF/JPG/JPEG/PNG only, enforce a 10 MiB application limit (local PHP limits reported 40M for upload and POST), inspect detected MIME type, generate a random 256-bit storage key, reject a second proof for the same payroll, and compensate by deleting the file if metadata insertion fails. Upload state is `prepared`; legacy attachment is allowed only for already-paid, accounting-posted rows with a journal ID and uses `legacy_evidence`.
- Files are written under `storage/private/payroll-proofs`, below the existing deny-all `storage/.htaccess`. Download requests resolve the storage key from the database, enforce staff roles or employee ownership plus release state, stream as attachment with nosniff/private no-store headers, and never accept a caller-provided filesystem path.
- New POST actions use the existing CSRF token verifier. Preparing proof does not itself change payroll status or accounting fields. The existing paid-status/accounting path has not yet been redesigned in this checkpoint; the explicit confirmation gate and completion-notification requirements remain the next stage, so this intermediate source checkpoint is not the final workflow release.
- Migration `2026-10-20_hr_fm_payroll_transfer_workflow.sql` remains unapplied. The page checks whether both new tables exist before showing the new controls, so pulling source alone does not make the page depend on a missing table for its normal payroll listing.

**Verification boundary:** source changes are committed to `main`; the available GitHub source editor does not provide local PHP lint/runtime evidence. Local syntax checks and the migration/runtime checks remain pending. No database migration was run and no payroll/accounting rows were changed by these source edits.


## 2026-10-20 — Step 5 source checkpoint: explicit transfer confirmation and completion gates

The next source checkpoint is committed on `main`:

- The FM page now uses a CSRF-protected `confirm_transfer` action. The legacy generic `update_status=paid` path is explicitly rejected, so payment cannot bypass the dedicated confirmation action.
- Confirmation locks the payroll row and its proof row, requires an approved payroll, a readable stored file in `prepared` state, and an active saved payment account. Historical evidence cannot confirm a new transfer. The transaction updates payroll to paid, posts the existing accounting journal, applies the existing salary-advance accounting behavior, and changes proof state to `confirmed`; any failure rolls the transaction back.
- Account overrides are rejected once a proof is attached, preventing the saved payment account from drifting after evidence preparation. Upload now locks the payroll row during proof insertion to serialize it against confirmation; file bytes are removed if DB persistence/commit fails.
- `hrPayrollNotifyStatementAvailable()` now returns a boolean: an existing matching recipient/link/title notification counts as success, a successful insert counts as success, and missing/inactive employee links or lookup/insert exceptions count as failure. Notifications remain after financial commit and cannot roll back the committed accounting.
- GM period completion retains the existing eligible employee policy and now requires paid + accounting posted + journal ID, proof state `confirmed` or `legacy_evidence`, and a successfully recorded employee statement notification for every eligible employee. A completion evaluation retries missing employee notifications; GM notice remains idempotent by recipient/link/title and is withheld while any employee gate is incomplete.
- The payroll generation action applies a saved period default to unassigned draft rows. Saving a period default preserves all already assigned row-level choices. Group/individual account changes remain limited to approved rows and reject rows that already have proof.

### Verification boundary and deployment gate

The PHP source is committed, but PHP lint/runtime verification could not be run against the user's Windows/XAMPP checkout from the remote source editor. A network attempt to create a disposable syntax-check checkout failed because this execution environment cannot resolve GitHub; no alternate or obsolete database was used. The migration `2026-10-20_hr_fm_payroll_transfer_workflow.sql` is still **not applied**. Before operational use, pull `main`, run `php -l` on all changed PHP files, apply the reviewed migration through the project's migration process, and perform a focused local test on a safe approved payroll/proof path without touching unrelated closed modules. No real payroll/accounting records were changed by this source work.
