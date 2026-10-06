# Ahl El Kheir Charity Management System — Complete System Analysis

Status: Canonical system reference
Baseline: 2026-10-03
Repository: moneermax/Ahl-El-Kheir-charity-management-system
Branch: main

## 1. Purpose

Ahl El Kheir is an existing Arabic-first charity management system covering organizational administration, families and orphans, sponsors and sponsorships, supervisors, transactions, accounting, Fina protected funds, HR/payroll, salary advances, projects, messaging, notifications, reports and system administration.

The system is workflow-driven rather than a generic CRUD application. Visibility, record scope, workflow authority, accounting authority and final approval are separate concerns.

This document is the canonical explanation of the implemented system. Dated audit/checkpoint documents remain evidence and history.

## 2. Technology baseline

- Procedural PHP 8.2+
- PDO with MariaDB/MySQL
- Bootstrap 5.3 RTL
- Vanilla JavaScript
- Font Awesome 6
- Cairo
- UTF-8/utf8mb4
- Currency: SDG / Sudanese Pound
- Timezone: Africa/Khartoum
- Arabic default; English supported
- Server-rendered PHP pages with shared header/sidebar/footer and role dashboards

Do not introduce an OOP rewrite or SPA architecture as an incidental feature change.

## 3. Request and security model

A normal request follows:

1. configuration/environment loading;
2. database connection;
3. session/authentication;
4. role/page authorization;
5. record/scope authorization;
6. workflow-state validation;
7. CSRF and request-method protection for state changes;
8. business operation;
9. transaction/audit/notification/evidence handling;
10. response rendering.

The server is the security boundary. Hiding a button is never sufficient authorization.

## 4. Roles

Implemented role identities include:
- admin / system administration aliases;
- general_manager;
- vice_general_manager;
- financial_manager;
- accountant / accountant_staff;
- supervisor;
- nanny;
- administration;
- staff;
- social_media;
- projects_manager;
- project_supervisor;
- hr_manager;
- hr_staff.

The database role code is authoritative. Routing aliases are compatibility mechanisms, not permission grants.

## 5. Organizational scope

Supervisor sponsor responsibility is determined by:

Sponsor first-name letter + Sponsor gender -> responsible Supervisor.

Family, mother, orphan or family-code information must not replace this rule.

Family access is a separate scope problem and follows its own documented assignment/sponsor-linked rules.

## 6. Functional domains

### Authentication and users
Login/session, password recovery/change, profiles, user provisioning, employee linkage, role assignment and user lifecycle.

### Families and children
Family register, verification, children/orphan records, documents, suspensions and scoped operational work.

The implemented model uses families and family_children. There is no generic children table.

### Sponsors and sponsorships
Sponsor registration, requests, assignment, sponsorship lifecycle, payment/transaction history, receipts, reports and Winback.

VGM owns sponsor assignment/reassignment. Supervisor sponsor access is scope-controlled.

### Supervisors and operational payments
Supervisors perform scoped sponsorship work and may submit operational payment requests. Accounting confirmation/posting remains with authorized accounting roles.

### Accounting
Journal, accounts, vouchers, disbursements, transaction review, returned funds, ledger, trial balance, reconciliation, treasury, Fina, salary advances, project funding and financial reports.

Treasury accounts:
- 1100 Cash
- 1200 Bank
- 1300 Electronic wallet

Other controlled accounts include:
- 2300 Fina liability/control
- 1401 Fina-held-funds asset/control
- 1410 salary-advance receivable/control
- 4200 administrative-fee revenue/control

### Fina
Fina is a protected third-party fund, not Ahl revenue. The current model uses permanent 2300 liability/control and 1401 Fina-held-funds control. Settlement is full-balance and FM-only.

### HR/payroll
Employees, employment states, contracts, attendance, leave, payroll, payroll accounting, salary-advance policy, requests, disbursement and repayment.

### Projects
Projects use other_projects and related approval/lifecycle/assignment/funding structures. They do not use a generic projects table.

Core approval flow: PM -> FM -> GM, with documented backward rejection routing.

PS is Project Supervisor, not Sponsor Supervisor.

Phase 5 controlled project PRJ-0015 is closed and runtime verified: 300,000 SDG released, 50,000 SDG execution expense, 250,000 SDG returned once to 1200 Bank, FM financial closure completed, controlled balance 0.00 SDG, PM notified.

### Messaging and notifications
Internal messaging, attachments, replies, notification polling, unread indicators, full history and workflow notifications.

Clearing the bell menu is non-destructive; notification history remains.

### Reports and search
Universal report registry and role-aware reports. Reporting access does not grant workflow authority. Global search is scope-aware.

### Administration/Winback
Administration handles sponsor requests and Winback. Staff and Social Media receive only the shared functions appropriate to their roles; Winback is Administration-only.

## 7. Accounting invariants

- Posted journals must balance.
- Historical posted journals are business evidence and must not be edited to make tests pass.
- Treasury balances come from posted ledger lines.
- Cash, bank and electronic wallet remain distinct accounts.
- Payment method must survive the operational-to-accounting workflow.
- Fina is not Ahl revenue.
- Project-controlled balances must be reconciled before financial closure.
- Salary advances are receivable/control events, not ordinary expenses.
- Duplicate disbursement/settlement/return actions must be blocked.
- Financial actions require the authorized accounting role.

## 8. Database/change model

The schema dump is the schema reference; migrations are the structural change mechanism.

Never perform runtime CREATE TABLE, ALTER TABLE, trigger creation, view creation, stored procedures, functions or events.

For structural changes:
Inspect -> migration -> apply -> dependent-code review -> verification -> documentation.

## 9. Development method

Inspect -> Understand -> Verify -> Identify risk -> Fix narrowly -> Test -> Document.

Do not rebuild the project, invent tables/columns/statuses/accounts, repeat closed tests without regression evidence, or use destructive Git operations.

## 10. Canonical documents

- AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
- AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
- AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
- AHL_EL_KHEIR_OPERATIONS_SECURITY.md
- AHL_EL_KHEIR_DOCUMENTATION_INDEX.md

Domain evidence remains in the existing Fina, HR Salary Advance, Projects and accounting audit documents.


## 2026-10-05 — HR employment scope and salary register refinement

The HR domain now has an explicit distinction between **historical employee data** and **current working financial scope**.

### Salary-at-hire register

The employee module exposes `?action=salary_register` as a reporting view. It reads the earliest positive salary-history record per employee rather than the mutable `employees.basic_salary` field. This preserves the meaning of a historical salary-at-hire register when current salary changes later.

Automatic employee provisioning can create a 0.00 initial salary-history placeholder. That placeholder is not treated as the employee's actual salary-at-hire value; the register selects the earliest positive salary-history entry.

Current payroll/salary-advance test salaries are development data populated in the database. Salary values are not embedded in application code.

### Current employment/accounting scope

Current salary-register scope is restricted to employment states whose existing canonical state record has `category = 'working'` and `is_active = 1`. Suspended or separated/terminated employees remain part of historical HR records but are not current working payroll scope.

This follows the existing payroll eligibility principle: a non-working employee must not enter a new payroll period merely because an employee record or historical salary exists.

The distinction is:

- **Historical record:** employee, salary history, payroll and accounting evidence remain available for audit.
- **Current working scope:** only working employment states participate in current salary/payroll processing.
- **Accounting history:** prior posted accounting evidence is never removed because an employee becomes non-working.

## 2026-10-05 — Attendance policy-driven automation foundation

HR attendance now has a dedicated versioned policy foundation rather than hard-coded working-hour rules. The policy controls working start/end, attendance cutoff, absence-finalization time, automatic login attendance, automatic absence finalization and default work mode. The effective policy is selected by date. Successful qualifying login can create the day's present record while preserving the first check-in. Automatic absence is handled separately by tools/finalize_daily_attendance.php and remains subject to the canonical employment-state/approved-leave eligibility rules. Default/example values are remote work, 07:00–16:00, but these are policy data rather than PHP constants.

Runtime status: source implementation complete; local migration/application and end-to-end browser/scheduler verification remain pending.


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
