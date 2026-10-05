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