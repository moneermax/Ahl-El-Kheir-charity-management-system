# HR Dashboard Navigation Audit — 2026-09-08

## Result

The HR entry-point architecture was re-audited after identifying two competing HR landing pages.

### Canonical HR dashboard

`dashboard/hr_dashboard.php` is the **single canonical/main HR dashboard**.

It is the page that should be used as the HR landing page after role-based login and as the authoritative navigation hub for HR.

### Historical duplicate entry point

`modules/hr/index.php` previously contained a second, duplicate HR dashboard implementation. Maintaining two independent HR dashboards created a navigation and maintenance risk because changes could be applied to one page while the other remained stale.

It has now been converted to a compatibility redirect to:

`dashboard/hr_dashboard.php`

This preserves existing bookmarks/legacy internal references without maintaining duplicate dashboard logic.

### User-facing HR pages reachable from the canonical dashboard

- `employees.php` — linked
- `employment_states.php` — linked
- `attendance.php` — linked
- `leaves.php` — linked
- `payroll.php` — linked
- `contracts.php` — linked
- `payroll_policy.php` — linked
- `payroll_reversal.php` — conditionally linked for `hr_manager`/`admin`
- `payroll_integrity.php` — conditionally linked for `hr_manager`/`admin`

The seven normal HR navigation cards plus the privileged **التصحيحات المالية للرواتب** control are now presented as one unified action-card grid. On desktop, the grid uses four equal-width columns, producing two balanced rows when all eight controls are visible to `hr_manager`/`admin`. The financial corrections control is no longer a separate full-width toolbar; it occupies the same visual card row and retains separate buttons for reversal and integrity checking.

### Internal endpoints/libraries

- `bulk_attendance.php` is an API/JSON endpoint and is not a dashboard navigation item.
- HR `lib_*.php` files are internal libraries and are not dashboard navigation items.

## Authorization rule

The canonical HR dashboard is authorized for `hr_manager`, `hr_staff`, and `admin`.

`payroll_policy.php` remains a normal HR-management page for the HR dashboard audience.

`payroll_reversal.php` and `payroll_integrity.php` are sensitive payroll/accounting controls and are shown only to `hr_manager` and `admin`. Their dashboard visibility matches their server-side authorization and does not replace server-side access control.

## Implementation

1. `dashboard/hr_dashboard.php` was made the authoritative HR dashboard and now includes the missing Payroll Policy navigation path.
2. `modules/hr/index.php` was reduced to a compatibility redirect to the canonical dashboard.
3. The top HR action area was normalized to four equal-width columns on desktop, with eight total controls forming two balanced rows for privileged HR roles.
4. The Payroll Financial Corrections control was integrated into the same card grid while preserving its two existing destinations: Payroll Reversal and Payroll Integrity.
5. No HR business logic, database schema, payroll workflow, leave workflow, attendance workflow, or authorization rules were intentionally changed by this UI adjustment.

Latest implementation commit:

- `d20e83960cda8acb642e0035d1fe68aa1cb541e4` — align HR dashboard action cards into two equal rows and integrate Payroll Financial Corrections into the same grid.

Earlier navigation commits:

- `9ca3e8ab26610702a256b9f495527890fa5eb71c` — convert `modules/hr/index.php` into compatibility redirect.
- `6b471457e8eaf33690da683189b6b9b5e4675c07` — complete canonical HR dashboard navigation.

## Verification

Browser verification should confirm:

- `/dashboard/hr_dashboard.php` opens as the main HR dashboard.
- `/modules/hr/index.php` redirects to the same canonical dashboard.
- `hr_staff` can see Payroll Policy but cannot see Payroll Reversal/Integrity controls.
- `hr_manager` and `admin` can see the sensitive payroll controls.
- For `hr_manager`/`admin`, the eight top action controls appear in two equal desktop rows of four.
- The **التصحيحات المالية للرواتب** control is aligned with the other cards rather than appearing as a separate full-width section.
- All dashboard links open their intended HR pages.

## Architectural decision

There is now **one HR dashboard implementation**. Future HR navigation changes must be made in `dashboard/hr_dashboard.php`; `modules/hr/index.php` must remain only a compatibility redirect unless the architecture is deliberately changed and documented.
