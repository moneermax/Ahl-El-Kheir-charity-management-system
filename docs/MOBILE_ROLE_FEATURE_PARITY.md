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

**Still open; do not claim full completion:** role-specific dashboard metrics and operational workflows are not all native yet; website-only tools require a valid website session in the external browser; legal-age sponsorship suspension is not implemented natively; transaction date-range end-to-end acceptance and physical-device runtime remain pending; the mobile API migration must be applied to the intended local development database before API runtime testing. Current GitHub Actions for these newest source changes must be checked before claiming the new revision builds successfully.
