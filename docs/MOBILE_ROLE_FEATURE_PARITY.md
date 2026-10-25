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
| Dashboard overview | Counts for users, sponsors, families, sponsorships; unread notifications; pending recovery requests; settings and audit counts; recent audit activity; PHP/database status | Authenticated `GET api/v1/admin/overview.php` returns record counts, recent audit entries, PHP version and database connectivity. Current mobile cards show four record counts and recent audit activity; other returned counts are available in the payload but not yet surfaced as separate tiles. |

Additional existing mobile-native operational screens are included in the administrator dashboard: projects, employees, attendance, supervisors, sponsor requests, financial review queue, and legal-age alerts.

### Authorization boundary

`api/v1/admin/overview.php` requires the existing bearer-session authentication and restricts the response to `admin`, `sudo`, and `super_admin`. The web-only tool routes are not new API permissions; the existing website remains responsible for enforcing access when those pages open.

### Verification status

The code is committed to `main`; automated Flutter analyze/tests/APK and PHP lint must complete successfully before calling this change verified. No local XAMPP/database or physical-device runtime result is claimed by this document.

## Other roles

The website routes each role to a distinct dashboard in `config/functions.php`: GM, VGM, FM, accountant, supervisor, nanny, staff/administration, HR, and projects. Their native parity remains open and must be audited against each real dashboard and server-side permission rules. Do not infer parity from role-specific color or a shared module list.
