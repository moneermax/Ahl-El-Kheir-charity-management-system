# Ahl El Kheir — Architecture and Data Model

Status: Canonical technical reference
Baseline: 2026-10-03

## 1. Repository layers

| Layer | Location | Responsibility |
|---|---|---|
| Configuration | config/ | environment, DB, sessions, auth, helpers, scope/integrity |
| Shared UI | includes/, assets/ | header, sidebar, footer, notifications, messaging, styles/scripts |
| Dashboards | dashboard/ | role-oriented entry points |
| Business modules | modules/ | domain workflows |
| Database | database/ | schema, audits, migrations, production preparation |
| Tools | tools/ | controlled test/repair utilities |
| Documentation | docs/ | canonical references and evidence |
| PDF dependency | TCPDF/ | PDF generation |

## 2. Core infrastructure

config/config.php defines application identity, currency, paths and table constants.

config/database.php provides PDO access and prepared-query helpers.

config/auth.php and config/functions.php provide authentication, role guards, routing and shared helpers.

Scope/integrity services include:
- config/data_integrity.php
- config/family_scope.php
- config/search_permissions.php
- config/sponsor_assignments.php
- config/supervisor_lifecycle.php

## 3. Module map

Accounting: modules/accounting/
Families: modules/families/
Sponsors: modules/sponsors/
Sponsorships: modules/sponsorships/
Transactions: modules/transactions/
Supervisors: modules/supervisors/
HR: modules/hr/
Projects: modules/projects/
Administration: modules/administration/
Reports: modules/reports/
Messages: modules/messages/
Notifications: modules/notifications/
Users: modules/users/
System: modules/system/

## 4. Core relationships

Explicitly established by current code/documentation:

- users.role_id -> roles.id
- families.id <- family_children.family_id
- family_children.id <- sponsorships.child_id
- accounting: journal_entries -> journal_lines
- Projects: other_projects plus approval/lifecycle/assignment/funding structures
- Fina: collection, settlement and control structures
- Salary advances: policy, request, accounting, payroll and repayment structures

Important negative facts:
- no generic projects table;
- no generic children table;
- sponsorships do not use a guessed sponsorships.family_id relationship.

Always inspect the actual schema before SQL.

## 5. Financial architecture

Treasury:
1100 Cash
1200 Bank
1300 Electronic wallet

Controlled/domain accounts:
2300 Fina liability/control
1401 Fina-held funds asset/control
1410 salary-advance receivable/control
4200 administrative-fee revenue/control

Financial flow:
Operational source -> review/approval -> accounting event -> posted journal -> ledger -> evidence -> reporting.

## 6. UI architecture

Shared header, sidebar, footer, notification widget and messaging widget provide common presentation.

Dashboards are role-specific. Sidebar visibility is not authorization.

Forms use server-side validation and CSRF. State-changing actions use POST.

## 7. Reports/search

modules/reports/report_registry.php centralizes report definitions and authorization.

Global search uses config/search_permissions.php. Search must not become an alternate path around record-level authorization.

## 8. Database changes

Use dated migrations under database/migrations/.

Do not mutate schema during ordinary page requests. No triggers, views, stored procedures, functions or events.

## 9. Developer inspection order

1. canonical system analysis;
2. relevant domain module;
3. config/auth.php and config/functions.php;
4. scope/integrity helpers;
5. schema and relevant migrations;
6. dashboard/sidebar;
7. domain evidence;
8. tests/runtime evidence.

## 10. Repository inventory at baseline

- 198 module paths
- 9 dashboard paths
- 36 database/schema/migration/audit/production paths
- 27 asset paths
- 16 configuration paths
- 8 shared include paths

These are baseline inventory figures, not design limits.


## 2026-10-05 — HR attendance policy architecture

New attendance policy structures: hr_attendance_policy_versions, modules/hr/lib_attendance_policy.php, modules/hr/attendance_policy.php and tools/finalize_daily_attendance.php. The policy contains name/effective date, working start/end, attendance cutoff, absence-finalization time, automatic login attendance, automatic absence, default work mode, notes and creator metadata. The application timezone remains the authoritative time basis. The event flow is successful login -> linked active employee -> effective policy -> canonical attendance eligibility -> daily attendance record. Absence is separate: scheduled finalizer -> effective policy -> canonical eligibility -> existing-record check -> absent record. The finalizer does not overwrite an existing daily row and is idempotent.

Runtime boundary: source implementation complete; migration application, login test and scheduled finalizer test remain pending locally.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.

## 2026-10-06 — GM Salary Advance Waiver: Payroll Repayment Dependency Runtime Verified

The controlled payroll-integration dependency required by the GM salary-advance waiver has now been runtime verified locally on `main` using the existing rollback-only Stage 5 harness:

`php tools\\run_salary_advance_stage5_payroll_tests.php`

Result:
- PASS — payroll repayment application: `SAR-2026-00004`, temporary payroll ID 24, deduction 5,000 SDG, one repayment trace row, schedule status `paid`, outstanding balance 45,000 SDG, balanced journal 116.
- PASS — duplicate repayment protection for the same request/payroll.
- PASS — rollback-only cleanup; no payroll/request/schedule/journal mutation was committed.

This verifies the real payroll repayment path used by salary advances: payroll deduction -> repayment allocation -> schedule update -> outstanding-balance reduction -> Cr 1410 accounting -> duplicate protection, without creating permanent test data.

This does **not** close the GM waiver feature. The waiver-specific runtime matrix remains open, including FM preparation, GM approval/rejection, refund of already-paid current-period deductions, remaining-balance waiver, future-deduction blocking, undistributed-request cancellation, concurrency/duplicate execution protection, draft refresh, post-commit notifications, audit evidence and final 1410 reconciliation.

Evidence boundary:
- Current waiver implementation commits include `b5e0bbd`, `2d035ae`, `1a67312`, and `7e63fd0`.
- The local database already contains the waiver migration; no new schema change was made for this verification.
- No production payroll-generation code was changed as a result of the earlier read-only October simulation or this rollback-only test.

The unfinished attendance same-page POST scroll-jump issue remains separate and must not be reopened during this work.
