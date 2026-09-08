# HR Dashboard Navigation Audit — 2026-09-08

## Result

The HR module was reviewed to ensure user-facing HR pages are reachable from the HR dashboard without exposing internal API/library files as navigation items.

### User-facing HR pages

- `employees.php` — linked
- `employment_states.php` — linked
- `attendance.php` — linked
- `leaves.php` — linked
- `payroll.php` — linked
- `contracts.php` — linked
- `payroll_policy.php` — requires dashboard link
- `payroll_reversal.php` — requires a role-aware dashboard link

### Internal endpoints/libraries

- `bulk_attendance.php` is an API/JSON endpoint and should not be linked as a normal page.
- HR `lib_*.php` files are internal libraries and should not be exposed as dashboard navigation.

### Required navigation rule

`payroll_policy.php` should be visible to the same authorized HR dashboard audience (`hr_manager`, `hr_staff`, `admin`).

`payroll_reversal.php` performs a sensitive payroll/accounting reversal and should only be shown to `hr_manager` and `admin`, matching its server-side authorization rather than weakening access control.

## Implementation status

The dashboard navigation fix is intentionally limited to adding these two missing links. No HR business logic, database behavior, authorization rules, or existing dashboard statistics should be changed.

After implementation, the dashboard should provide a complete navigation path to all user-facing HR pages while keeping API endpoints and internal libraries out of the menu.
