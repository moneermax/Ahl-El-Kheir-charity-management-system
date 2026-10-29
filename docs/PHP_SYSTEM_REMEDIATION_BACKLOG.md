# PHP System Remediation Backlog

Checkpoint: 2026-10-28  
Repository: `moneermax/Ahl-El-Kheir-charity-management-system`  
Branch: `main`  
Tracking issue: [#96 — PHP system issues and runtime acceptance gaps found during mobile development](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/issues/96)

## Scope and rules

Flutter/native-app development is **PAUSED** at the user's request. Resume work on the existing procedural PHP application and its real MariaDB/MySQL database. This backlog records items surfaced while reviewing existing PHP workflows and adding mobile API endpoints; it intentionally distinguishes confirmed source mismatches from verification gaps and items that need re-triage.

Do not treat every item as a confirmed defect. For each item: inspect current `main`, relevant module continuation docs and `database/ahl_el_kheir.sql`; reproduce or verify the evidence; identify root cause; make the smallest justified change; lint/test; update the canonical docs. No branches, destructive Git operations, runtime DDL, guessed schema, or unrelated module edits.

## Backlog

### PHP-01 — Local API runtime and authorization acceptance
**Status:** OPEN — verification gap, not a proven general API defect. CI lint coverage expansion is now verified.  
**Priority:** High before relying on API features outside CI.

The API grew during mobile development. Many endpoint integrations and service harnesses passed against disposable CI MariaDB schemas, but that does not prove behavior against the real local XAMPP/Apache/PHP 8.2 instance and `ahl_el_kheir` database. Existing notes report that physical-phone login succeeded after the Android INTERNET permission correction, and the user confirmed the `mobile_api_sessions` migration was applied; preserve that evidence rather than restarting from zero.

Next:
- Inventory current `api/v1/` endpoints and existing PHP/API test coverage from current `main`.
- Confirm only the still-unverified routes and critical flows need runtime acceptance; do not rerun already-closed PHP acceptance suites without regression evidence.
- On the real local app/database, verify auth/session invalidation, exact role guards, record-level scope, upload/download authorization, and relevant financial mutations against existing web behavior.
- Confirm website browser login/session behavior remains unchanged.
- Do not claim online/production readiness from CI-only evidence.

Evidence: `docs/MOBILE_APP_CONTINUATION.md`, `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` mobile API checkpoints dated 2026-10-24 through 2026-10-27.

### PHP-02 — API authentication hardening scope for any future public deployment
**Status:** OPEN — documented capability/security review.  
**Priority:** High before exposing the API publicly; not a reason to change existing web authentication without evidence.

The initial API continuation document explicitly lists missing capabilities: refresh-token flow, password-reset/change API, persistent login throttling, device/session-management UI, and account-wide mobile-session revocation. It also calls for review of the 12-hour access-token lifetime and brute-force/rate-limiting controls. These are missing API capabilities/review items, not proof that the existing browser login is defective.

Next:
- Decide which capabilities are actually required for the intended deployment.
- Review current token storage/revocation, expiry, disabled-account/changed-role behavior, and login-abuse protections.
- Implement only approved scope and test without weakening existing PHP browser auth.
- Never send credentials or bearer tokens over an unverified/plain-HTTP public endpoint.

Evidence: `docs/MOBILE_APP_CONTINUATION.md`, “Initial API slice” and “Important current limits”.

### PHP-03 — Production host and shared-host compatibility
**Status:** OPEN — deployment not accepted.  
**Priority:** Before any online use.

No verified HTTPS production API host has been accepted. Earlier staging research identified possible shared-host constraints, including a 15 MiB upload limit, restricted PHP functions, and runtime/database-version differences from public documentation. These need fresh verification against the actual host/account; do not assume those older measurements remain current.

Next:
- Verify HTTPS, PHP/MariaDB compatibility, request limits, upload limits, filesystem permissions, disabled functions, database size/query/connection limits, and API reachability on the actual target.
- Confirm charity receipt/document upload/download paths work within the host limits.
- Keep XAMPP private; do not expose the local development server publicly.
- Do not use real charity data for unapproved hosting tests.

Evidence: prior hosting investigation summarized in project conversation notes; mobile continuation's production-host warning.

### PHP-04 — Salary portal / incentive-policy role mismatch
**Status:** SOURCE FIX COMMITTED — local browser acceptance pending.
**Priority:** Medium.

The destination page `modules/hr/incentive_policy.php` authorizes `general_manager`/`gm`, `hr_manager`, and `financial_manager` for their distinct policy actions; `admin` is not authorized. The salary portal previously showed the link to `admin`, leading to an unauthorized page.

Commit `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9` changes `modules/hr/salary_portal.php` so only `financial_manager` sees that link. The destination guard was not broadened. Remaining acceptance: verify in a real browser that Financial Manager sees the link, Admin does not, and HR Manager/General Manager retain their existing destination access.
### PHP-05 — Incentive/project workflow browser acceptance
**Status:** OPEN — one stale route corrected; real-browser acceptance remains pending.
**Priority:** Medium.

Documented browser gates remain:
- PM-to-FM financial closure earned notices, including assigned recipients and retry/deduplication;
- provisional new-sponsor/reactivation notices appearing in the browser inbox;
- project-team assignment UI (initial selection, add, duplicate rejection, unassign/history retention, edit-page behavior).

Source audit found `modules/projects/form.php` explicitly owns project-team mutations on the edit page, while `modules/projects/view.php` retained a second, unrendered pair of assignment/unassignment POST handlers. Commit `0e67f8f63682d8370453f1dc35f135b6784ea3a9` removes those stale duplicate handlers so the edit page remains the supported route. Workflow [#40633372107](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633372107) passed all five jobs. A follow-up [run #40633600907](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633600907) also passed all five jobs and explicitly added `modules/hr/salary_portal.php`, `modules/projects/form.php`, and `modules/projects/view.php` to the PHP lint list. The source cleanup is CI-verified.

Next: verify the sequential PHP workflow; keep browser acceptance open until tested with correct roles and safe representative records. Do not modify active incentive policy R5, recreate deleted disposable incentive databases, or perform mutating acceptance against live `ahl_el_kheir`.
### PHP-06 — Test workflow invocation guard/configuration mismatch
**Status:** RESOLVED by current workflow configuration review.
**Priority:** Closed.

The current `.github/workflows/mobile-api-auth-integration.yml` sets `MOBILE_API_AUTH_TEST: '1'` and `AHL_DB_NAME: mobile_api_auth_test` at job level, provisions/imports the same disposable database, and invokes `tools/test_mobile_admin_users_action_service.php`. The harness guard matches the current job configuration. `.github/workflows/php-syntax-check.yml` merely includes the file in its syntax-check list; that is not an execution. No production-code change or test rerun is needed for this item.
### PHP-08 — User-management page performs schema DDL at runtime
**Status:** SOURCE FIX + CI VERIFIED — local browser acceptance pending.
**Priority:** High for schema/deployment reliability.

A source review of the original PHP page `modules/users/index.php` found runtime `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` statements. The page also performed role/department data updates and seed inserts during page load. This conflicted with the project rule that schema changes must be represented by migrations and that application requests must not create/alter schema. The uploaded 6.6 MB checked-in schema was inspected: all seven referenced tables exist; `users.department_id`, `users.manager_id`, `sponsors.gender`, `families.nanny_id`, and `supervisor_letters.gender` already exist; `family_documents` also contains `file_name`, `file_size`, `notes`, and `doc_type` beyond the old runtime definition.

Observed runtime schema operations include:
- create `departments`;
- add `users.department_id` and `users.manager_id`;
- add `sponsors.gender`, `families.nanny_id`, and `supervisor_letters.gender`;
- create `family_documents` and `accountant_nanny_assignments`.

**Do not simply delete these statements.** The checked-in `database/ahl_el_kheir.sql` must first be inspected for each table/column/index/foreign-key definition, along with the full page's dependent queries and existing schema history. Then create only the justified migration(s), verify them on a disposable database, and remove runtime DDL/data-seeding from page requests while preserving existing behavior. Migration `database/migrations/2026-10-28_user_management_runtime_schema_cleanup.sql` now owns the schema reconciliation and role/department seeding. Commit `f8b8b099ec4f307737ae7dcec7179f6439043408` adds the migration; commit `519bf7a21d562235c95f3bca7f66170557df54ce` removes the request-time schema/data mutations from `modules/users/index.php`. Commit `bbd81cacf738d9a369de947aa6df3e1d63a95f25` adds the page to PHP lint and applies the migration twice in the disposable full-schema CI job with schema/reference-data assertions. GitHub Actions run [#40641855323](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40641855323) passed all five jobs, including `php-lint` and `migration-full-schema`. The full-schema job imported `database/ahl_el_kheir.sql`, applied the new migration twice, verified the expected columns and reference data, then simulated the pre-cleanup shape by removing the five formerly runtime-added columns and the two formerly runtime-created tables; the migration restored them successfully. Existing regression harnesses also passed. Local browser acceptance of User Management remains pending; do not claim live local-runtime verification.

### PHP-09 — Family workflows perform schema DDL during page requests
**Status:** SOURCE FIX + CI VERIFIED — local family workflow acceptance pending.
**Priority:** High for schema/deployment reliability.

Source inspection found request-time schema setup in:
- `modules/families/create.php` — creates `family_bank_accounts` and adds family/child columns.
- `modules/families/edit.php` — repeats the bank-account/table/column setup and creates `orphan_documents`.
- `modules/families/orphan_form.php` — adds orphan/family fields during form requests.
- `modules/families/documents.php` — creates `family_documents` using an incomplete definition and adds `doc_type`.

The uploaded checked-in schema confirms `family_bank_accounts`, `orphan_documents`, `family_documents`, and the referenced family/child columns already exist. The checked-in `family_children.form_serial` is `VARCHAR(20)` with a unique index; source has historically attempted `VARCHAR(30)` in one path and `VARCHAR(20)` in others. Migration `database/migrations/2026-10-28_family_runtime_schema_cleanup.sql` now owns these schema additions, including the family-bank-account/orphan-document tables and the family/child columns. The four family pages no longer run schema DDL on requests. Commit `599ad25302df14af3e7d0fb034fa3ddc35214c41` also adds those pages to PHP lint and adds a disposable legacy-shape migration test. The migration adds `form_serial` as `VARCHAR(20)` when absent and restores its unique index; it does not shrink an already-existing wider column, avoiding potential data truncation. GitHub Actions run [#40642297829](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40642297829) passed all five jobs. The full-schema job applied this migration twice, simulated the missing family/child columns and family tables in the disposable database, restored them, and verified the unique form-serial index. Local browser acceptance of family creation/editing/orphan form/document upload remains pending.

### PHP-10 — Accounting core helpers perform runtime schema DDL
**Status:** SOURCE FIX + CI VERIFIED — local accounting workflow acceptance pending.
**Priority:** High because these helpers are shared by accounting operations.

Source review confirmed request-time DDL in `modules/accounting/lib.php` and `modules/accounting/lib_admin_fee_policy.php`: creation of `accounts`, `journal_entries`, `journal_lines`, `vouchers`, `project_beneficiaries`, and `accounting_admin_fee_policies`, plus additions to `other_projects`, `transactions`, and `sponsor_payments`. The uploaded schema dump contains all referenced objects and columns. Migration `database/migrations/2026-10-28_accounting_core_runtime_schema_cleanup.sql` now owns these definitions; compatibility wrapper functions remain but perform no DDL. The existing runtime chart-of-accounts seed behavior in `ak_seed_accounts()` is intentionally unchanged in this item because it is accounting data initialization, not schema DDL. GitHub Actions run [#40643161875](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40643161875) passed all five jobs. The full-schema job applied the migration twice, and a separate disposable database verified that all six accounting core tables can be created from missing schema. Local accounting workflow acceptance remains pending.

### PHP-11 — Additional original PHP pages perform runtime schema changes
**Status:** SOURCE CLEANUP COMMITTED — disposable legacy-schema CI verification pending.
**Priority:** Medium to high depending on the endpoint's call frequency.

Source hits found in:
- `modules/users/profile.php` — adds profile columns to `users`.
- `modules/system/import_sponsors.php` — creates `sponsor_import_exceptions`.
- `modules/system/sync_sponsors.php` — adds `sponsors.is_manual_override`.
- `modules/transactions/receipt_file.php` — adds receipt-path columns to `transactions`.
- `modules/hr/payroll_reversal.php` — creates `hr_payroll_reversals`.

The checked-in schema contains all referenced columns/tables. Migration `database/migrations/2026-10-28_other_php_runtime_schema_cleanup.sql` now owns them. The five listed pages no longer run schema DDL during requests. GitHub Actions run [#40643694231](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40643694231) passed all five jobs. The full-schema job applied the migration twice and simulated the missing columns/tables in the disposable database before confirming restoration. Local profile/import/sponsor-sync/receipt/payroll-reversal acceptance remains separate.

### PHP-12 — Accounting outflow library performs runtime schema synchronization
**Status:** SOURCE FIX + CI VERIFIED — local outflow workflow acceptance pending.
**Priority:** High because this helper supports disbursement and nanny verification workflows.

`modules/accounting/lib_outflows.php` previously created `monthly_disbursements`, `disbursement_items`, and `nanny_family_verifications` during requests; dynamically added columns; and modified status ENUM definitions. The checked-in schema has canonical monthly statuses without `pending`, while the old runtime table definition included it. Source evidence confirms `ak_out_approve()` accepts both `pending` and `pending_approval`, while the web/API create/transition paths use `pending_approval`; the checked-in dump has no monthly batch with `pending`. Migration `database/migrations/2026-10-28_accounting_outflows_schema_cleanup.sql` now creates/reconciles the tables and columns, ensures the group index/FK, maps legacy `pending` to the canonical `pending_approval` state, and restores the canonical ENUM. The schema-sync function and DDL helper were removed while read-only enum introspection remains. GitHub Actions run [#40644551812](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40644551812) passed all five jobs. The full-schema job applied the migration twice. A separate disposable legacy fixture verified restoration of missing group/return columns and the group foreign key/index, normalized `pending` to `pending_approval`, and restored the canonical ENUM. Local outflow workflow acceptance remains pending.

### PHP-13 — Public sponsor application conflicts with the canonical request schema/workflow
**Status:** IMPLEMENTED; MIGRATION CI VERIFIED. Local browser acceptance is still required before closure.
**Priority:** High; the public form previously wrote to a schema/status contract incompatible with the canonical staff/API queue.

**Business decision confirmed 2026-10-29:** Public self-registration is an optional future intake path. Staff continue creating sponsors through the existing authenticated sponsor workflow. Public submissions are applications and must enter the shared request queue for staff review; they must not create active sponsors automatically. The form may accept individual, company, and organization applicants.

**Implemented on `main`:**
- Removed runtime `CREATE TABLE`/`ALTER TABLE` from `apply.php`; public submission now uses canonical `sponsor_name` and `new` status.
- Added CSRF validation to public submissions, server-side validation for sponsor type, individual gender, proposed amount, and desired orphan count, and type-aware supervisor assignment.
- Public company/organization applications store the canonical `organization` gender value and do not receive individual-gender supervisor assignment.
- Staff conversion now preserves request sponsor type, gender semantics, contact details, payment preference, notes, desired orphan count, source, and brought-by information. Individual conversion still requires a valid male/female value; company/organization conversion uses the sponsor schema's organization value and does not assign an individual supervisor.
- Added `database/migrations/2026-10-29_sponsor_requests_canonical_workflow.sql` to reconcile legacy `full_name` and status values, restore queue/form fields, and widen request gender to support `organization`. Migration must be applied before deploying the updated PHP files to any existing database.

**Verification boundary:**
- Source/schema mismatch was confirmed from the checked-in schema and source before edits.
- GitHub Actions run [#40818372238](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40818372238) passed all five jobs. The sponsor migration test applied the migration twice against both the canonical schema and a legacy runtime-created fixture, confirming name-column reconciliation, legacy status mapping, and repeatability.
- No live public submission or staff conversion has been tested against the user's local database. Do not test by inserting dummy records into the live charity database; use disposable/controlled data.
- Public application and individual/company/organization conversion require local browser acceptance after the user pulls the change.


### PHP-07 — HR job-description migration CI fixture starts from the post-migration schema
**Status:** FIX VERIFIED — all jobs in the sequential CI run passed.
**Priority:** Medium; CI verification blocker, not evidence of a live-data defect.

Workflow run [#40632821897](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40632821897) failed in job `hr-job-description-migrations` with:

`ERROR 1091 (42000) at line 4: Can't DROP INDEX `uq_hr_job_description_position`; check that it exists`

Root cause from the checked-in schema and migration source:
- `database/ahl_el_kheir.sql` already contains `system_role_code` and the post-migration unique key `uq_hr_job_description_position_role`.
- The CI job imported that post-migration schema, then `CREATE TABLE IF NOT EXISTS` in `2026-10-20_hr_job_description_catalogue.sql` left the existing table unchanged.
- The subsequent `2026-10-20_hr_job_description_system_roles.sql` expects the legacy title-only index and fails when it tries to drop it.

Fix committed in `daa821f1d607aa74d5efd90227e47567043c5ec1`: the workflow now drops and recreates only `hr_job_description_templates` in its disposable CI database using the catalogue migration before applying the historical migration sequence. Schema inspection found no foreign-key references to this table in the checked-in dump. This is CI fixture setup only; it does not modify production schema or application data. Run [#40633086558](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40633086558) passed all five jobs: `php-lint`, `hr-job-description-migrations`, `migration-mariadb-10-4`, `rescheduling-service-tests`, and `migration-full-schema`. This closes the CI fixture issue; local HR browser/PDF acceptance remains a separate, previously documented boundary.

## Explicitly paused or excluded

- **Flutter/native feature parity is paused.** Remaining mobile-only work (such as native opening-balance management, contract builder, payroll reversal, project create/edit, and other not-ready role tools) is not a PHP defect merely because a native screen is missing. Preserve the mobile continuation document for when app work resumes.
- Do not reopen closed attendance/payroll integration, GM salary-advance waiver, i18n, Salary Advance Stages 1–6, or other closed acceptance without concrete regression evidence.
- The existing `REQUEST_METHOD` CLI warning at `config/functions.php:52` is known and non-blocking. Do not change production code just to suppress it.
- Do not reopen the previously closed attendance same-page POST issue unless new regression evidence appears.
- Older system-wide UI consistency observations (mixed localization/date formatting, role-label/action conventions) must be checked against current source and later closure evidence before adding them back as open work.

## Recommended order — refreshed 2026-10-29

1. **PHP-13 public sponsor application mismatch:** implementation is on `main`; verify the dedicated migration CI step and then perform controlled local browser acceptance before closure.
2. **Local PHP browser acceptance:** after syncing the current `main` revision, verify PHP-08 User Management, PHP-09 family workflows, PHP-10 accounting core, PHP-11 profile/import/sync/receipt/payroll-reversal pages, and PHP-12 accounting outflows in the local browser. CI/migration checks do not prove local acceptance; use safe read-only or controlled test data and avoid unapproved mutations to live charity records.
3. **PHP-04/PHP-05:** perform the pending real-browser acceptance for the salary portal role link, project-team assignment, and incentive/project notifications; do not infer success from CI alone.
4. **PHP-01:** design a minimal route-level local API runtime/authorization acceptance matrix from current source and existing harness coverage. Do not run destructive integration tests against `ahl_el_kheir`.
5. Keep PHP-02/PHP-03 as pre-public-deployment reviews. Keep Flutter/native development paused and preserve closed PHP acceptance unless concrete regression evidence appears.

## PHP-01 inventory checkpoint — 2026-10-28

A read-only repository inventory of current `main` found **111 PHP files under `api/v1/`**. The current `.github/workflows/php-syntax-check.yml` explicitly runs `php -l` against **74** of those API PHP files; **37** API PHP files are not in that selected lint list. This is a coverage inventory, not evidence that those 37 files contain syntax errors or runtime defects.

API files absent from the selected lint list:
- `api/v1/account/avatar.php`, `api/v1/account/profile.php`
- `api/v1/accounting/accounts.php`, `api/v1/accounting/assigned_nannies.php`, `api/v1/accounting/fm_transaction_receipt.php`, `api/v1/accounting/fm_transaction_review.php`, `api/v1/accounting/gm_reconciliation.php`, `api/v1/accounting/journal.php`, `api/v1/accounting/trial_balance.php`
- `api/v1/admin/backups.php`, `api/v1/admin/database.php`, `api/v1/admin/disbursements.php`, `api/v1/admin/disbursements_action.php`
- `api/v1/administration/available_families.php`, `api/v1/administration/winback.php`
- `api/v1/auth/change_password.php`, `api/v1/auth/complete_forced_password_change.php`, `api/v1/auth/password_recovery_request.php`, `api/v1/auth/web_handoff.php`
- `api/v1/deputy_gm/groups.php`
- `api/v1/fina/collections.php`, `api/v1/fina/dashboard.php`, `api/v1/fina/receipt.php`, `api/v1/fina/report.php`, `api/v1/fina/review.php`, `api/v1/fina/sponsors.php`
- `api/v1/hr/incentive_policy.php`, `api/v1/hr/incentive_verification.php`, `api/v1/hr/payroll_management.php`, `api/v1/hr/payroll_policy.php`, `api/v1/hr/payroll_proof.php`, `api/v1/hr/salary_advance_dashboard.php`
- `api/v1/nanny/returned_funds.php`
- `api/v1/search/document_file.php`, `api/v1/search/documents.php`, `api/v1/search/sponsors.php`
- `api/v1/transactions/query_service.php`

The existing mobile API auth integration workflow runs an endpoint integration harness against a disposable full-schema MariaDB database and includes auth, role/scope, archive, project, HR, sponsor, nanny, accounting and other paths. That CI coverage is useful but does not replace runtime acceptance against the user's actual local XAMPP/Apache/PHP 8.2 instance. Do not run its destructive integration harness against `ahl_el_kheir`; the harness explicitly refuses databases other than `mobile_api_auth_test`.

**Source change committed; CI pending:** commit `c4c212980d1a1677729db94d2b6815a07aba0289` adds all 37 previously unlisted API PHP files to the existing selected PHP lint list, so the workflow now explicitly lints all 111 API PHP files. This is a verification-coverage correction, not a claim that the files were defective. GitHub Actions run [#40634508587](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40634508587) completed successfully; all five jobs passed, including `php-lint`, `hr-job-description-migrations`, `migration-mariadb-10-4`, `rescheduling-service-tests`, and `migration-full-schema`. The expanded lint coverage is CI-verified. Next, inspect sensitive route/service coverage and define the smallest local runtime acceptance matrix. PHP-01 remains OPEN until still-unverified local runtime acceptance is completed.

## PHP-01 API integration-test inventory — 2026-10-28

A source-only comparison between `api/v1/*.php` and URL literals in `tools/test_mobile_api_auth_integration.php` found 100 PHP files that are neither `_bootstrap.php` nor named `*_service.php`. The harness directly references 80 of these route files; 20 are not directly referenced by a URL literal in this harness:

- `api/v1/admin/departments.php`
- `api/v1/admin/departments_action.php`
- `api/v1/admin/overview.php`
- `api/v1/admin/recovery.php`
- `api/v1/admin/recovery_action.php`
- `api/v1/admin/users.php`
- `api/v1/admin/users_action.php`
- `api/v1/age_alerts/index.php`
- `api/v1/age_alerts/suspend_child.php`
- `api/v1/audit/index.php`
- `api/v1/dashboard/overview.php`
- `api/v1/hr/my_salary_statements.php`
- `api/v1/hr/salary_advance_waiver.php`
- `api/v1/reports/orphaned_families.php`
- `api/v1/reports/overview.php`
- `api/v1/sponsors/assign.php`
- `api/v1/sponsors/assignment_options.php`
- `api/v1/supervisors/action.php`
- `api/v1/suspensions/index.php`
- `api/v1/suspensions/lift.php`

This is a test-discovery result, **not** proof that these routes are untested by every other harness or that they contain defects. Service-level tests may cover underlying operations without exercising route authentication/role checks. The next source review should prioritize role-sensitive admin user/departments/recovery mutations and suspension/age-alert actions, then determine whether route-level negative/positive cases can be added safely to the disposable CI harness. Do not modify salary-advance waiver business logic or reopen its closed acceptance without concrete regression evidence. No local database or business records were changed during this inventory.

## Change log

- 2026-10-29: Sponsor request notification follow-up: the public form already assigns individual requests through the authoritative first-letter + gender matrix and notifies the assigned supervisor, but the notification previously linked to the management-only full queue. Added `modules/sponsors/my_request.php`, a supervisor-only read-only detail page that requires the request ID and `assigned_supervisor_id = Session::getUserId()`; updated `apply.php` so the assigned supervisor receives that scoped detail link while management recipients retain the general queue link. This deliberately does not grant supervisors access to the full queue or its conversion/delete actions. Commits: `d6719a97bd039562838a8902fe67d2f0161a5ff5` and `89ecf6d8de7ef1b602871e34faf829e8e084dcc4`. Local PHP lint and runtime access tests are still required; confirm an assigned supervisor can view their own request and receives 404 for another supervisor's request.

- 2026-10-29: Local PHP-13 browser acceptance exposed an unclosed POST-handler block in `modules/sponsors/requests.php`, causing a PHP parse error and HTTP 500 when opening the sponsor request queue. Fixed narrowly in commit `916d7c44c53aa8b31e0fd38b3fb9b7f7e0b30ba3` by closing the POST guard before queue rendering. Local PHP lint and browser retest remain pending; do not mark PHP-13 runtime acceptance closed yet.

- 2026-10-28: Created at the user's request to pause Flutter work and log PHP/backend findings for future remediation. Tracking issue: #96.
- 2026-10-28: PHP-04 navigation mismatch corrected in commit `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9`; real-browser acceptance remains pending. PHP-06 re-triaged and closed after confirming the current auth integration workflow sets the required test flag and database.
- 2026-10-28: PHP-07 added after workflow #40632821897 exposed a stale migration fixture; CI-only fixture correction committed as `daa821f1d607aa74d5efd90227e47567043c5ec1`, verified by workflow #40633086558: all five jobs passed.
- 2026-10-28: PHP-01 API inventory found 111 API PHP files but only 74 listed in the PHP lint workflow. Commit `c4c212980d1a1677729db94d2b6815a07aba0289` added the other 37 files to the lint list; workflow #40634508587 passed all five jobs, including `php-lint`; the coverage expansion is CI-verified.

- 2026-10-28: PHP-08 remediation committed on `main`: schema dump inspected; schema/data setup moved to `2026-10-28_user_management_runtime_schema_cleanup.sql`; runtime mutations removed from `modules/users/index.php`; CI now tests repeat application and key schema/reference-data invariants. GitHub Actions run #40640508040 passed all five jobs. Local browser acceptance remains pending.

- 2026-10-28: PHP-08 migration CI passed in workflow [#40640508040](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40640508040): all five jobs succeeded; `migration-full-schema` applied the new migration twice and passed schema/reference-data assertions. Local browser acceptance of User Management remains pending.

- 2026-10-28: Follow-up read-only scan of all 206 PHP files under `modules/` found additional runtime DDL source findings PHP-09 (family workflows), PHP-10 (accounting libraries), and PHP-11 (profile/import/sync/receipt/payroll-reversal pages). Each remains open until schema/migration reconciliation and targeted CI verification.

- 2026-10-28: PHP-09 source cleanup committed as `599ad25302df14af3e7d0fb034fa3ddc35214c41`; family-page runtime DDL removed, schema moved to migration, lint coverage and legacy-shape CI test added. Awaiting CI result.

- 2026-10-28: PHP-09 migration CI passed in workflow #40642297829: all five jobs succeeded, including the legacy-shape migration test. Local family workflow acceptance remains pending.

- 2026-10-28: PHP-10 accounting core runtime DDL cleanup committed with migration and CI coverage; source no longer performs DDL. CI pending. PHP-12 split outflow schema synchronization into a separate open item due to enum/data semantics.

- 2026-10-28: PHP-10 accounting core migration CI passed in workflow #40643161875: all five jobs passed, including the blank-database creation test. PHP-11 source cleanup and migration committed with legacy-shape CI coverage; awaiting the triggered workflow result.

- 2026-10-28: PHP-11 migration CI passed in workflow #40643694231: all five jobs succeeded, including the legacy-shape migration test. Local workflow acceptance remains pending.

- 2026-10-28: PHP-12 outflow schema cleanup committed with canonical table/column definitions, legacy `pending` → `pending_approval` normalization grounded in the current source, and a disposable legacy-status CI fixture. Awaiting CI result.

- 2026-10-28: PHP-12 outflow migration CI passed in workflow #40644551812: all five jobs succeeded, including legacy-schema and status-normalization verification. Local outflow workflow acceptance remains pending.

- 2026-10-28: PHP-13 public sponsor application schema/workflow mismatch confirmed from source and the uploaded schema. Logged without changing code because individual gender versus company/organization conversion behavior needs confirmation.


## 2026-10-29 — Linux Mint development environment restored

The user has moved the development workstation from Windows to Linux Mint and confirmed that the existing PHP application opens and accepts login locally.

- OS: Linux Mint.
- XAMPP installation: `/opt/lampp` (XAMPP for Linux 8.2.12-0).
- Apache document root / project path: `/opt/lampp/htdocs/AhlElKheir/`.
- Local application URL confirmed by the user: `http://localhost/AhlElKheir/`.
- VS Code is installed and connected to that exact project folder, so edits there are served by the local Apache instance.
- XAMPP Apache and MariaDB were started successfully during setup.
- The recovered database imports available in the fresh XAMPP MariaDB include `ahl_el_kheir`, `ahl_el_kheir-development db`, `ahlelkheir_fina_current_20260916`, `ahl_el_kheir_production_test`, and `pharmacy_management_dev`.
- The preserved recovery snapshot is `/opt/lampp/recovery/data_snapshot`; the working recovery copy is `/opt/lampp/recovery/work_oct28`; exports are preserved under `/opt/lampp/recovery/exports_oct28`. Do not delete these recovery copies as part of routine project work.
- The old Windows partition is mounted read-only at `/mnt/windows_recovery`; preserve it as a recovery source.
- The old `school_db_production` export was only 1028 bytes and did not contain the database schema/data; a separate backup exists and is intentionally set aside for later. Do not claim that database is restored.
- This environment checkpoint records setup, not full database row-by-row equivalence or application-wide runtime acceptance. The user confirmed only that the project URL opens and login succeeds.

Continue on the existing repository and database; do not create a replacement project. Before code changes, read the canonical project documents and the current PHP remediation backlog from `main`. Keep Flutter/native development paused.
- 2026-10-29: Follow-up on the supervisor notification still redirecting to the dashboard found the likely stale-row path: older `sponsor_request` notifications still store `modules/sponsors/requests.php`, while the supervisor is correctly denied access to the full management queue. Commit `79f2da32f49c59eadc1f25cdb7e2d940deb6949c` adds click-time repair in `modules/notifications/mark_read.php`: for a supervisor, it resolves a legacy notification only to a request assigned to that supervisor and rewrites the stored link; if no safe match exists it returns to the notifications page rather than the queue. Commit `e72be9cec86c19b3394e7639bb7202e98cbdc7ea` makes toast navigation follow the server-resolved redirect. Runtime acceptance and PHP lint are still pending; verify old and new notifications, own-request scope, and management queue access before closure.
