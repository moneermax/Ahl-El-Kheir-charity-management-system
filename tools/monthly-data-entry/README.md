# Monthly Data Entry Tool

A standalone Arabic RTL data-entry and monthly reporting tool for Ahl El Kheir. It is isolated from the charity management system's operational modules and uses a dedicated database: `ahl_el_kheir_monthly_data`.

## Implemented first pass

- Register a new sponsor or add new sponsorships to an existing sponsor, with an optional sponsor file number.
- Enter multiple mother groups for one sponsor, with one or more orphans under each mother.
- Each orphan is linked to one mother; a mother can have multiple orphans.
- Each sponsor/orphan relationship is a separate sponsorship record. An orphan can also have multiple sponsors.
- Store monthly sponsorship and service amounts per sponsorship.
- Store other donations as separate monthly entries.
- Record sponsor departure with file number, last-paid month, orphan count, and dated financial snapshots; end that sponsor's active sponsorships without deleting historical data.
- Monthly report page with totals and detail lists.
- Export the selected month as PDF, native XLSX, or Excel-compatible CSV.

## Database installation

1. Import `database/schema.sql` into MariaDB/MySQL. It creates the dedicated database `ahl_el_kheir_monthly_data`.
2. Copy `config/database.example.php` to `config/database.php` and set the local database credentials.
3. Open `/tools/monthly-data-entry/` under the repository's local web root.

The SQL file is an explicit installation script and is never executed by application requests. The schema uses InnoDB, utf8mb4, foreign keys, integer SDG amounts, and restrictive deletes to protect historical records.

## Requirements

- PHP 8.2+ with PDO MySQL.
- MariaDB 10.4+ or compatible MySQL.
- The repository's root `TCPDF/` library for PDF export.
- PHP `ZipArchive` extension for XLSX export. CSV export remains available if ZipArchive is unavailable.

## Data model

- `mde_sponsors`: sponsor master records.
- `mde_mothers`: mother records.
- `mde_orphans`: orphan records; each has one mother.
- `mde_sponsorships`: sponsor-to-orphan relationship and lifecycle.
- `mde_monthly_sponsorship_entries`: monthly sponsorship and service values per sponsorship.
- `mde_monthly_other_donations`: monthly donations outside the regular sponsorship amount.
- `mde_sponsor_departures`: sponsor-level dated departure event and financial snapshot.
- `mde_schema_version`: installed schema version.

## Reporting rules

- A report month is stored as the first calendar day of that month.
- Sponsorship, service, and other-donation values are assigned to an explicit report month.
- Departures are grouped by the actual departure date.
- Historical monthly entries remain reportable after a sponsorship ends.
- Amounts are stored as integer SDG units; no floating-point arithmetic is used.

## Security and deployment status

This is an initial local-development build, not yet safe for public internet deployment: the standalone tool does not yet have its own login or role-based access control. Keep it on localhost or behind trusted network access until authentication and authorization are implemented. Database credentials are ignored by Git, and direct HTTP access to the config and SQL folders is denied by the included Apache rules.

The tool intentionally does not read or write the main application's tables, sessions, permissions, or business workflows.
