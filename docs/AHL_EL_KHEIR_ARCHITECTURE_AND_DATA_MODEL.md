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
