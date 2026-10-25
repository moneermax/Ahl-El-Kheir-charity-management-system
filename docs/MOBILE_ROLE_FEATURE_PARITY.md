# Mobile role feature parity — evidence matrix

Last reviewed: 2026-10-25
Source of truth: current `main`, especially `config/functions.php::dashboard_for_role()`, `dashboard/admin_dashboard.php`, and the Flutter app under `mobile/ahl_el_kheir_app/`.

## Administrator / system developer

The web role mapping routes `admin`, `sudo`, and `super_admin` to `dashboard/admin_dashboard.php`. That page explicitly defines these control groups:

| Web dashboard group | Source tools | Mobile status after dedicated dashboard change |
|---|---|---|
| System administration | Users, departments, system settings | Visible in dedicated admin dashboard; currently opens the existing web page in the external browser. Not native screens yet. |
| Core records | Sponsors, families, sponsorships, reports | Sponsors, families and sponsorships open native screens. Reports currently opens the existing web page in the external browser. |
| Finance | Finance dashboard, chart of accounts, transaction ledger, monthly disbursements, Fina Al-Khair collection | Transaction ledger opens a native screen. Other listed tools open existing web pages in the external browser. |
| Protection and maintenance | Database backup, database tools, audit log | Visible in the dedicated admin dashboard and opens the existing web page in the external browser. |
| Dashboard overview | Counts for users, sponsors, families, sponsorships; unread notifications; pending recovery requests; settings and audit counts; recent audit activity; PHP/database status | Authenticated `GET api/v1/admin/overview.php` returns record counts, recent audit entries, PHP version and database connectivity. Current mobile cards show eight metrics: users, sponsors, families, sponsorships, pending password-recovery requests, unread notifications, audit-record count, and settings count, plus recent audit activity. |

Additional existing mobile-native operational screens are included in the administrator dashboard: projects, employees, attendance, supervisors, sponsor requests, financial review queue, and legal-age alerts.

### Authorization boundary

`api/v1/admin/overview.php` requires the existing bearer-session authentication and restricts the response to `admin`, `sudo`, and `super_admin`. The web-only tool routes are not new API permissions; the existing website remains responsible for enforcing access when those pages open.

### Verification status

The code is committed to `main`; automated Flutter analyze/tests/APK and PHP lint must complete successfully before calling this change verified. No local XAMPP/database or physical-device runtime result is claimed by this document.

## Other roles

The website routes each role to a distinct dashboard in `config/functions.php`: GM, VGM, FM, accountant, supervisor, nanny, staff/administration, HR, and projects. A role-specific mobile quick-tools section now surfaces additional links derived from those actual dashboards. These links are explicitly labelled **WEB** and open the existing site; they do not count as native implementations and require a valid website session in the external browser.

| Role | Existing native mobile capabilities | Additional website tools surfaced | Remaining parity work |
|---|---|---|---|
| GM | Sponsors, families, sponsorships, sponsor requests, projects, transactions, legal-age alerts, personal leave requests/salary statements | Executive reports, uncovered-family/sponsorship/operational reports, win-back, incentive policy, GM payroll-period history | Native executive metrics/attention queue and approval workflows; web session handoff |
| VGM | Sponsors, families, sponsorships, sponsor requests, projects, supervisors, FM review queue, transactions, legal-age alerts | Sponsor assignment and reports | Native sponsor assignment and full VGM dashboard indicators |
| FM | Projects, transactions, FM review queue, legal-age alerts | Disbursements, chart of accounts, journal, vouchers, transaction review, financial reports, payroll portal, Fina dashboard | Native finance operations and dashboards; accounting API parity |
| Accountant / Accountant Staff | Sponsorships/transactions where permitted, projects where permitted, personal leave requests/salary statements | Group disbursements, monthly disbursements, reports | Exact assignment-scoped native workflow parity and dashboard indicators |
| Supervisor | Sponsors, families, sponsorships, transactions, legal-age alerts | Fina review/history/report | Native Fina workflows and supervisor-specific dashboard/record scope |
| Nanny | Families, preliminary verification, group verification, legal-age alerts, personal leave requests/salary statements | Monthly disbursements, returned funds, reports | Native disbursement/returned-fund workflows and dashboard reconciliation |
| Administration / Staff / Social Media | Sponsor requests and personal leave requests/salary statements | Orphan forms; Administration additionally gets available families and win-back | Native beneficiary forms, follow-up workflows and real per-role dashboard metrics |
| HR Manager / HR Staff | Employees, employee detail, attendance, leave requests/approval queue, personal salary statements | Payroll, contracts, payroll policy, incentive verification, attendance policy, payroll integrity | Native HR operational screens and dashboard metrics; manager/staff-specific action boundaries |
| Projects Manager / Project Supervisor | Projects, project detail, progress and milestone actions | Project register link | Full dashboard metrics and remaining project-specific website workflows |

Do not infer parity from role-specific color or a shared module list. Server-side authorization remains authoritative for every endpoint and website page.


## 2026-10-25 — Role-aware mobile tools and employee self-service

Source commits on `main`:
- `8f659a0cec3ae9d565dd8bba98297da2f94b7639`: expose native leave requests and salary statements from the mobile workspace.
- `842e4382bcf922f9c67b82fcc414abdd7e72fba8` and `9e3044d842c1893bf685ef2bb87ae253a7d2c150`: add a role-specific quick-tools section derived from each existing web dashboard. Website-only links are labelled WEB and remain subject to website server-side authorization; this is navigation coverage, not native workflow parity.
- `fbc30ebf5c1df671e6fcca6b07c39963abe3661e` and `a2eb57da68fde5a1cb206732ed4781df609c0382`: align the mobile API default and Android workflow default with the documented phone LAN endpoint `http://192.168.18.9:881/AhlElKheir/api/v1`. The previous 8081 default did not match the documented phone-facing Apache port and is superseded.

The workspace now exposes the existing native personal leave-request page and finalized salary-statement page, and adds role-scoped quick links matching the real GM, VGM, FM, Accountant, Supervisor, Nanny, Administration/Staff, HR, and Projects dashboard routes. The API enforces leave-queue permissions and returns salary statements only for paid payrolls posted to accounting. No account without a linked employee profile is given another employee's records.

**Still open; do not claim full completion:** role-specific dashboard metrics and operational workflows are not all native yet; website-only tools require a valid website session in the external browser; native legal-age suspension source is now implemented; automated and local runtime verification remain pending; transaction date-range end-to-end acceptance and physical-device runtime remain pending; the mobile API migration must be applied to the intended local development database before API runtime testing. The newest source checks have now passed; see the verified CI milestone below.


## 2026-10-25 — Native legal-age suspension action added

Source commits on `main` add `POST api/v1/age_alerts/suspend_child.php`, wire a confirmation action into the native legal-age alert sheet, and include the endpoint in PHP syntax CI. The endpoint mirrors the existing web child-suspension business operation: it permits admin/sudo/super_admin, VGM aliases, and nanny; rechecks that the child is active and within the web alert's 90-day-to-age-18 / age-21 lower window; enforces nanny family assignment; locks the child row; rejects an existing active suspension; inserts a `suspension_cases` record with reason `age_out`; marks the child inactive/disqualified; pauses active linked sponsorships; and records the existing audit-log event. The General Manager can see alerts but cannot initiate this action, matching the web page's hidden create form for GM.

The API action adds no runtime DDL or schema change. The redundant runtime `CREATE TABLE` block was removed from `modules/families/suspensions.php` because `suspension_cases` and its referenced columns are already present in `database/ahl_el_kheir.sql`; PHP syntax CI lints both suspension entry points, and the latest PHP workflow passed. Verification boundary: the endpoint/service passed PHP lint and a rollback-only integration test against the checked-in full schema on MariaDB 10.4.32; Flutter analyze, widget tests, APK build, and artifact publication also passed. Real local XAMPP/API and physical-device acceptance are still required. The CI integration harness verifies an eligible child, active linked sponsorship pausing, duplicate rejection, nanny scope rejection, GM denial, and rollback restoration against a disposable full-schema fixture. Do not test this on a real beneficiary record.


## 2026-10-25 — Mobile API role-alias authorization correction

The API bootstrap now normalizes only the role aliases explicitly routed to the same canonical dashboard by `config/functions.php::dashboard_for_role()`: `sudo`/`super_admin` → `admin`, `gm` → `general_manager`, `vgm` → `vice_general_manager`, and `fm`/`finance` → `financial_manager`. API authorization checks therefore evaluate the same canonical role as the web router, while `auth/me.php` returns the stored role code so the app retains the user's actual role identity. The `staff` alias is intentionally not normalized globally because its dashboard exposes a different subset of Administration tools.

This corrects concrete native-screen/API mismatches where the UI offered role-approved tools but endpoints rejected the equivalent alias account (including sponsor/family/sponsorship lists, supervisor management, HR screens, project portfolio, financial review, and transactions). A PHP regression test covers 14 canonical/alias cases and is run by `.github/workflows/php-syntax-check.yml`. Automated CI passed: the PHP workflow ran `PASS | mobile API role normalization | 14 cases`, and the Flutter APK workflow passed analyze, all widget tests, APK build, and artifact publication. Local XAMPP/mobile runtime acceptance is still required.


## 2026-10-25 — Verified CI milestone

- Flutter workflow run `40192383388` for source `c680777307d55513f01aff5dbe5536be497b2611`: **success**. Dart analysis, Flutter tests, debug APK build, and artifact publication all passed. Artifact: `ahl-el-kheir-flutter-android-debug-apk` (SHA-256 `9d407beda8a74d81c76655da2e6b74dcde015dba73ff237d5de9d0a9e41b782b`).
- PHP workflow run `40192899764` for source `5362b5dd250971db65b68f690560be208f16f43f`: **success**. Selected PHP lint passed, full-schema/migration jobs passed, and the role-alias regression reported `PASS | mobile API role normalization | 14 cases`.
- These CI runs verify source/build and disposable-schema checks only. They do not replace runtime verification against the user's local `ahl_el_kheir` database or physical Android device.


## 2026-10-25 — Suspension service integration and latest APK verified

- Flutter workflow `40193299239`, source `9e12cc7f491ed4e76649bdaf6f18a33d46f8e601`: **success** — Dart analysis, all Flutter widget tests, debug APK build, and artifact publication passed. Artifact `ahl-el-kheir-flutter-android-debug-apk`, SHA-256 `8ce5a5a2823be3a4be102a17894ee01032e8605bcc6333af4969b19f1e3384df`.
- PHP workflow `40193517552`, source `abc98c0a7e46dee67ce64daa87e59950812c397e`: **success** — selected PHP lint, full-schema migration suite, MariaDB 10.4.32 checks, and the rollback-only suspension service integration test passed. Exact service result: `PASS | mobile legal-age suspension service | success, linked sponsorship pause, duplicate guard, role/scope guards, rollback`.
- Role-alias regression: `PASS | mobile API role normalization | 14 cases` in PHP workflow `40192899764`.

Remaining acceptance boundary: these CI checks use disposable databases and cannot verify the user's running XAMPP server, actual local `ahl_el_kheir` schema alignment, the phone's network route, or a live authenticated session. The mobile API session migration must be applied to the intended local database before first use if it has not already been applied. Website-only tools remain labelled WEB and need a valid website session; they are not native parity claims.

## 2026-10-25 — Native role-specific dashboard metrics (source implementation; runtime gate pending)

Added `GET api/v1/dashboard/overview.php` and a native Flutter `RoleDashboardPage` section for General Manager, Vice General Manager, Financial Manager, Accountant/Accountant Staff, Supervisor, Nanny, Administration/Staff/Social Media, HR Manager/Staff, Project Manager and Project Supervisor. The API re-authenticates the bearer token on each request, uses the existing canonical role normalization, rejects administrator roles in favor of the dedicated administrator endpoint, and returns only metrics scoped to the authenticated role. Metric SQL was derived from the corresponding existing web dashboard queries.

Commits: `f6ac0b4490b712ae7bebd23474aa97d6ab93d165` (API), `1e74df5e3e601593808121a907a7023faede21ba` (native metrics section), `7c96953e298aa83b4d04149e91a2a55efa952283` (workspace integration), `4fe6df05679946968666959cfc6025921b28e1fd` (PHP lint CI).

The additional role-tool routes were checked against the current repository tree. The Supervisor-only “Record Fina collection” link was removed after source inspection confirmed `modules/transactions/fina_payment_create.php` permits only financial_manager/fm/admin. A widget regression test covers that permission boundary.

Automated verification for the final combined revision is pending. Do not claim the new dashboard SQL is runtime-verified against the local database until its workflow and local XAMPP/API request pass. Transaction date filters already exist in both the mobile UI and API, including inclusive `transaction_date >= from` and `transaction_date <= to`; same-day widget coverage exists, but the complete date-boundary, empty-result, reversed-date, pagination/refresh and authenticated API acceptance remains open. Website-only links remain website navigation, not native feature parity. Physical-device and local-database acceptance remain pending.

## 2026-10-25 — Role-tool authorization follow-up

A second guard-level review corrected additional mobile quick-tool visibility:
- HR Manager-only pages (employment states, incentive verification, payroll reversal, attendance policy, payroll integrity, and incentive policy management) are now shown only to `hr_manager`; HR Staff retain pages whose real web guards permit them.
- Accountant-only role cards no longer surface monthly/group disbursement pages whose existing web guards deny the `accountant` role. Those links remain available to `accountant_staff`.
- Regression tests cover the HR Manager/HR Staff split, accountant disbursement restrictions, and the Supervisor Fina-creation restriction.

Commits: `98e2e48a724b0b403a8795988005f5c4f30cd311`, `d845ec9a67a6540d9f5764c54f6096c9bfceaf5a`, `07a8d34ca187a9b6c28692e32c813a0cc76e2166`, `7532b0f63fc24d7916003f54d8bfee9860ed72d9`. Latest tests/build after these changes are still pending. Continue auditing role tools against actual page guards; repository file existence alone is not sufficient.
