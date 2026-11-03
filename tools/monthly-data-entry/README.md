# Monthly Data Entry Tool

A standalone data-entry and monthly reporting tool for Ahl El Kheir. It is isolated from the charity management system's operational modules and uses a dedicated database: `ahl_el_kheir_monthly_data`.

## Current scope

- Record sponsors and their sponsorships.
- One sponsor can have multiple sponsorships for multiple orphans.
- Each orphan is linked to one mother; a mother can have multiple orphans.
- Sponsorships are independent records, allowing an orphan to receive support from multiple sponsors.
- Store monthly sponsorship and service amounts.
- Store other donations as separate monthly entries.
- Record a sponsorship departure without deleting the sponsor, orphan, sponsorship, or historical monthly entries.
- Build reports by reporting month.

## Database installation

1. Create the database by importing `database/schema.sql` into MariaDB/MySQL.
2. Do not run this SQL from application requests. It is an explicit installation script.
3. Configure the tool's database connection in the application config (configuration file is not committed).
4. Use a dedicated database account with access only to `ahl_el_kheir_monthly_data` when the environment is deployed beyond a local development machine.

The schema uses InnoDB, utf8mb4, foreign keys, integer SDG amounts, and restrictive deletes to protect historical records.

## Data model

- `mde_sponsors`: sponsor master records.
- `mde_mothers`: mother records.
- `mde_orphans`: orphan records; each has one mother.
- `mde_sponsorships`: sponsor-to-orphan relationship and lifecycle.
- `mde_monthly_sponsorship_entries`: monthly sponsorship and service values per sponsorship.
- `mde_monthly_other_donations`: monthly donations outside the regular sponsorship amount.
- `mde_sponsorship_departures`: dated departure event and financial snapshot.
- `mde_schema_version`: installed schema version.

## Important reporting rule

A report month is stored as the first calendar day of that month. Monthly values are attached to that month explicitly. Departure reports use the actual departure date. Historical monthly entries are retained after a sponsorship ends.

## Planned application pages

- Sponsor / mother / orphan / sponsorship entry.
- Sponsorship departure entry.
- Monthly reports with PDF and Excel export.

This tool intentionally does not read or write the main application's tables, sessions, permissions, or business workflows.
