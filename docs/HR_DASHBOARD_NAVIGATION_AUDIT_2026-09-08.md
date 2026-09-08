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
- `payroll_policy.php` — **linked by the dashboard fix**
- `payroll_reversal.php` — **linked conditionally by role**

### Internal endpoints/libraries

- `bulk_attendance.php` is an API/JSON endpoint and should not be linked as a normal page.
- HR `lib_*.php` files are internal libraries and should not be exposed as dashboard navigation.

### Navigation and authorization rule

`payroll_policy.php` is visible to the same authorized HR dashboard audience (`hr_manager`, `hr_staff`, `admin`).

`payroll_reversal.php` performs a sensitive payroll/accounting reversal and is shown only to `hr_manager` and `admin`, matching its server-side authorization rather than weakening access control.

## Implementation

`modules/hr/index.php` was updated to add the two missing navigation paths while preserving the existing dashboard statistics, business logic, and authorization behavior.

Commit: `1094ff515fd1eaaef966e44ccb4bc546b16061b7`

The dashboard now provides a navigation path to all user-facing HR pages identified in this audit, while keeping API endpoints and internal libraries out of the menu.

## Verification note

The code change has been committed to `main`. Browser verification should confirm that the links appear correctly for `hr_staff`, while the payroll-reversal link is visible only to `hr_manager`/`admin`, and that each link opens the intended page.
