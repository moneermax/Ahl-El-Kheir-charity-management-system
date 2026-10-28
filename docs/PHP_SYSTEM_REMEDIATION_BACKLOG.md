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
**Status:** OPEN — verification gap, not a proven general API defect.  
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

## Recommended order

1. Read the four canonical project documents and this backlog from `main`.
2. Re-triage PHP-04 and PHP-06 quickly from current source, since these are bounded checks.
3. Audit the current API endpoint inventory and plan a minimal local runtime acceptance matrix for PHP-01.
4. Review PHP-02 and PHP-03 before any public API deployment; these do not block ordinary local PHP development.
5. Continue PHP-05 only after checking the module continuation docs and current evidence.

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

**Next bounded action:** inspect the 37 unlisted files for the reason they are outside the lint list and their actual route/service coverage, then add only justified lint coverage or tests. Prioritize sensitive financial mutations, proof/document delivery, account/profile operations, and authentication/handoff flows. Do not bulk-edit the workflow or claim all 37 are defects solely because they are absent from the list. PHP-01 remains OPEN until the still-unverified local runtime acceptance matrix is completed.

## Change log

- 2026-10-28: Created at the user's request to pause Flutter work and log PHP/backend findings for future remediation. Tracking issue: #96.
- 2026-10-28: PHP-04 navigation mismatch corrected in commit `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9`; real-browser acceptance remains pending. PHP-06 re-triaged and closed after confirming the current auth integration workflow sets the required test flag and database.
- 2026-10-28: PHP-07 added after workflow #40632821897 exposed a stale migration fixture; CI-only fixture correction committed as `daa821f1d607aa74d5efd90227e47567043c5ec1`, verified by workflow #40633086558: all five jobs passed.
