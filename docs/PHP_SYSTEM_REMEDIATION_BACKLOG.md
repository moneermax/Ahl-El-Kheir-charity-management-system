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
**Status:** OPEN — concrete source-level permission inconsistency recorded; re-confirm current source before editing.  
**Priority:** Medium.

During role-parity review, `modules/hr/salary_portal.php` exposed an incentive-policy destination while `modules/hr/incentive_policy.php` denies the `admin` role. The mobile hub deliberately disabled the admin card rather than broadening authorization. This leaves a user-facing inconsistency in the PHP portal/navigation if the source still matches the documented finding.

Evidence and change:
- `modules/hr/incentive_policy.php` authorizes only `general_manager`/`gm`, `hr_manager`, and `financial_manager` for their distinct policy actions; `admin` is not an authorized role.
- `modules/hr/salary_portal.php` previously showed the incentive-policy link to `admin`, which would lead to a page that redirects unauthorized roles.
- Commit `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9` changes the portal navigation condition to `financial_manager` only. It does not broaden the destination's access policy.

Remaining acceptance:
- Confirm the link is shown to Financial Manager and hidden from admin in a real browser; confirm the page's existing authorization remains unchanged for HR Manager and General Manager.
- CI/runtime evidence is not yet recorded for this specific change.

### PHP-05 — Incentive/project workflow browser acceptance
**Status:** OPEN — prior module notes report acceptance gaps; current state must be rechecked.  
**Priority:** Medium.

The employee-incentives module continuation notes identify real-browser acceptance still outstanding for:
- project PM-to-FM closure notices;
- provisional notice-inbox behavior;
- project-team assignment UI.

These are workflow acceptance gaps, not automatically proven implementation defects.

Next:
- Read the relevant incentive/project continuation docs and current PHP source.
- Check whether later commits or user runtime confirmation already closed any item.
- Test only still-open workflows with the correct roles and representative records; do not modify active incentive policy R5 or recreate deleted disposable test databases.

### PHP-06 — Test workflow invocation guard/configuration mismatch
**Status:** RE-TRIAGE — previous CI failure may already be corrected.  
**Priority:** Low unless it still blocks verification.

An earlier salary-verification workflow attempted `tools/test_mobile_admin_users_action_service.php` with the wrong database/context and the harness refused to run outside `mobile_api_auth_test` with `MOBILE_API_AUTH_TEST=1`. This was a test invocation/configuration mismatch, not evidence of a production PHP defect.

Next:
- Inspect the current workflow YAML and harness guard.
- If current configuration is correct, mark this item resolved with evidence; do not change production code or rerun unrelated suites.

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

## Change log

- 2026-10-28: Created at the user's request to pause Flutter work and log PHP/backend findings for future remediation. Tracking issue: #96.
- 2026-10-28: PHP-04 navigation mismatch corrected in commit `046c2a604749f0bb2b4bcaa5488ce6f6c68261d9`; real-browser acceptance remains pending. PHP-06 re-triaged and closed after confirming the current auth integration workflow sets the required test flag and database.
