# Ahl El Kheir Charity Management System

## Production Go-Live — Initial Setup and First-Login Guide

**Purpose:**  
This guide defines the recommended sequence for taking the clean production database online and onboarding the organization's employees for their first use of the system.

**Principle:**  
Do not put all employees online at once. Establish the organizational hierarchy and control roles first, verify each dependency, and then progressively onboard operational users.

---

# Part 1 — Who goes online first?

The recommended order is:

1. **Developer / System Administrator**
2. **General Manager (GM)**
3. **HR Manager**
4. **Financial Manager (FM)**
5. **Vice General Manager (VGM)**
6. **Projects Manager (PM)**
7. **Project Supervisor(s) (PS)**
8. **Accountant / Accountant Staff**
9. **Administration / Staff / Social Media**
10. **Supervisors**
11. **Nannies**
12. **Remaining ordinary employees**

The exact employees occupying these roles must be determined from the organization's real staff list. Do not assign roles merely because someone needs access to a page.

---

# Part 2 — Developer / System Administrator: First Login

The developer/system administrator is the first person to access the production system.

The purpose of this login is **technical and organizational preparation**, not ordinary daily operation.

## Step 1 — Confirm the production environment

Verify:

- Apache is running.
- MariaDB/MySQL is running.
- The production database is the intended database.
- The application opens normally.
- Login works.
- No database errors appear.
- The production database cleanup was successfully completed.
- The pre-cleanup backup is safely stored.

Do not begin employee onboarding until the application itself is functioning correctly.

## Step 2 — Verify system configuration

Review the existing system configuration and confirm:

- Organization name and identity.
- Default language.
- Timezone.
- Currency.
- Application paths/URLs.
- Required system settings.
- Email/notification configuration if used.
- Other organization-level settings exposed by the system.

Do not recreate settings that already exist simply because the database was cleaned. The production-preparation process intentionally preserves the settings table.

## Step 3 — Verify reference data

Before creating employees, verify:

- Roles.
- Departments.
- Currencies.
- Letters.
- Medical needs.
- Employment-state/reference data.
- Accounting accounts.
- Other controlled reference/configuration records.

These are preserved by the production-preparation process and therefore need **verification**, not blind recreation.

If something is incorrect, correct it through the application's supported administration workflow or the appropriate controlled migration/process.

---

# Part 3 — Establish the General Manager

## Step 4 — Create/verify the GM user

The System Administrator provisions the real General Manager account.

Verify:

- Correct employee identity.
- Correct username.
- Correct role: **General Manager**.
- Correct department/organizational information.
- Active account.
- Correct employee linkage.
- Correct initial password.

The GM should not share the developer's account.

## Step 5 — GM's first login

The GM should:

1. Sign in.
2. Change the temporary password if required.
3. Confirm that the dashboard is the GM dashboard.
4. Open the notification center.
5. Confirm that the account identity is correct.
6. Confirm that management/reporting access is appropriate.
7. Review the organizational structure visible to the GM.
8. Confirm that no unexpected operational or accounting authority has been assigned.

**Do not proceed if** the GM sees another role's dashboard, lacks required management functions, has inappropriate permissions, or has incorrect employee linkage.

---

# Part 4 — Establish HR Manager

## Step 6 — Create/verify HR Manager

Verify:

- Correct employee.
- Correct role: **HR Manager**.
- Correct department.
- Active employee state.
- Employee/account linkage.
- Initial password.

## Step 7 — HR Manager first login

HR Manager should:

1. Sign in.
2. Change the temporary password.
3. Confirm the HR dashboard.
4. Verify employee-management access.
5. Verify employment-state information.
6. Verify contract/employee management.
7. Verify attendance access.
8. Verify leave access.
9. Verify payroll access.
10. Verify salary-related HR functions.
11. Confirm notifications.

The HR Manager becomes the primary organizational owner for employee/HR data.

---

# Part 5 — Establish the Financial Manager

The Financial Manager is the next critical control role because the system separates operational activity from financial authority.

## Step 8 — Create/verify FM

Verify:

- Correct employee.
- Role: **Financial Manager (FM)**.
- Active account.
- Employee linkage.
- Correct department.
- Initial password.

## Step 9 — FM first login

The FM should:

1. Sign in.
2. Change the temporary password.
3. Confirm the FM dashboard.
4. Verify Cash account `1100`.
5. Verify Bank account `1200`.
6. Verify Electronic Wallet account `1300`.
7. Review the treasury dashboard.
8. Review accounting/reconciliation access.
9. Verify Fina access.
10. Verify project financial-control access.
11. Verify salary-advance financial processing.
12. Confirm that financial workflows require FM authority where documented.

The FM should not expect development journals, payrolls, salary advances, projects, or transactions to remain after production preparation. Those are development/test operational data and were intentionally cleared.

---

# Part 6 — Establish the Vice General Manager

## Step 10 — Create/verify VGM

Provision the real VGM account and verify:

- Correct employee.
- VGM role.
- Active account.
- Employee linkage.

## Step 11 — VGM first login

The VGM should:

1. Sign in.
2. Change the temporary password.
3. Confirm the VGM dashboard.
4. Verify management/reporting access.
5. Verify Sponsor assignment/reassignment access.
6. Verify that sponsor assignment is available.
7. Confirm that financial accounting authority has **not** been granted merely because the VGM has management access.

The VGM is the operational owner of Sponsor assignment/reassignment.

---

# Part 7 — Establish Project Management

If the organization will use the Projects module immediately, establish these roles next.

## Step 12 — Create/verify PM

The PM should verify:

- Projects Dashboard.
- Project creation.
- Project preparation.
- Budget entry.
- Project submission.
- Approval-state visibility.
- Project management functions.

The PM must not receive FM authority merely because projects contain financial information.

## Step 13 — Create/verify PS

Each Project Supervisor should:

1. Sign in.
2. Change the temporary password.
3. Confirm the Projects Dashboard.
4. Confirm assigned-project visibility.
5. Confirm operational project access.
6. Confirm permitted expense/evidence functions.
7. Confirm that FM-only financial controls are unavailable.

**PS = Project Supervisor** and is completely different from **Supervisor = Sponsor/operational Supervisor**.

---

# Part 8 — Establish Accounting Staff

After FM is confirmed, onboard the accounting staff.

## Step 14 — Create Accountant / Accountant Staff accounts

For each accounting employee:

- Confirm the real employee identity.
- Assign the correct accounting role.
- Link the employee.
- Confirm active status.
- Set temporary password.

Do not give every accountant FM authority. The system intentionally separates Accountant / Accountant Staff from Financial Manager.

## Step 15 — Accountant first login

Each accountant should:

1. Sign in.
2. Change the temporary password.
3. Confirm the accounting dashboard.
4. Confirm assigned accounting functions.
5. Confirm transaction/disbursement access as applicable.
6. Confirm receipt/evidence functions as applicable.
7. Confirm that FM-only approval/posting/financial-control functions remain protected.

---

# Part 9 — Establish Administration / Staff / Social Media

These roles can then be onboarded.

## Administration

Verify:

- Administration dashboard.
- Sponsor requests.
- Administrative workflows.
- Winback functions.

Winback is Administration-only.

## Staff

Verify:

- Staff dashboard.
- Authorized operational tasks.
- Sponsor-request/orphan-form functions where assigned.

## Social Media

Verify:

- Shared Staff/Social Media dashboard.
- Authorized social/operational tasks.
- No Administration-only Winback authority.

---

# Part 10 — Establish Supervisors

Supervisors should be onboarded **after VGM**, because VGM is responsible for Sponsor assignment/reassignment.

## Step 16 — Create Supervisor accounts

For every real Supervisor:

1. Create/link the employee.
2. Assign Supervisor role.
3. Confirm active employment state.
4. Set temporary password.
5. Do not manually invent sponsor scope.

## Step 17 — Supervisor first login

Each Supervisor should:

1. Sign in.
2. Change the temporary password.
3. Confirm the Supervisor dashboard.
4. Confirm that Sponsors are visible only within the correct responsibility scope.
5. Confirm that sponsorship/family operational information is accessible as intended.
6. Confirm payment/request functions.
7. Confirm that submitted financial requests route toward Accounting/FM.
8. Confirm that the Supervisor cannot bypass FM/accounting authority.

### Critical Supervisor rule

Supervisor responsibility is:

**Sponsor first-name letter + Sponsor gender → responsible Supervisor**

It is not based on family name, mother's name, family code, or orphan identity.

Do not assign Sponsors manually using an unrelated rule.

---

# Part 11 — VGM assigns Supervisors to their operational responsibility

Once all Supervisor accounts exist:

1. VGM opens Sponsor assignment.
2. Reviews available Sponsors.
3. Assigns each Sponsor to the appropriate Supervisor according to the documented responsibility rule.
4. Saves the assignments.
5. Reviews assignment/history.
6. Checks several representative Sponsors.
7. Confirms each Supervisor sees only the intended scope.

Do this before asking Supervisors to begin normal Sponsor work.

---

# Part 12 — Establish Nannies

After Supervisors and family/orphan operational scope are ready, onboard Nannies.

Each Nanny should:

1. Sign in.
2. Change the temporary password.
3. Confirm Nanny Dashboard.
4. Confirm assigned families/orphans.
5. Verify authorized forms.
6. Verify permitted updates.
7. Confirm that unauthorized records are not visible.
8. Confirm that financial/accounting controls are unavailable.

Do not use direct database assignment to give a Nanny access. Use the application's authorized assignment workflow.

---

# Part 13 — Employee/HR production setup

After the HR Manager is established, HR should perform the real employee setup.

For every real employee:

1. Verify identity.
2. Verify department.
3. Verify employment state.
4. Verify contract information.
5. Verify salary information.
6. Verify employee/user linkage.
7. Confirm role.
8. Confirm account status.
9. Set the employee's initial password.
10. Give the employee their login instructions.

### Important

An employee's historical record and current working status are separate.

Employees who are suspended, terminated, separated, or otherwise non-working must remain available as historical records but must not automatically enter current payroll/work scope.

---

# Part 14 — HR attendance setup

Before normal employee attendance begins, HR must establish the attendance policy.

## HR Manager

1. Open Attendance Policy.
2. Review the current policy.
3. Confirm working days.
4. Confirm working hours.
5. Confirm attendance cutoff.
6. Confirm absence-finalization time.
7. Confirm automatic login attendance behavior.
8. Confirm automatic absence behavior.
9. Confirm default work mode.
10. Save/activate the correct production policy.

Do not blindly use development/test policy values.

The attendance policy is the source of attendance facts.

---

# Part 15 — Payroll setup

Before the first real payroll period:

## HR Manager

1. Review payroll policy.
2. Confirm the effective date.
3. Confirm deduction rules.
4. Confirm attendance-related treatment.
5. Confirm unpaid-leave treatment.
6. Confirm salary-advance treatment.
7. Confirm eligible employment states.
8. Verify the first production payroll period.

## FM

Then verify the accounting side:

1. Review payroll accounting integration.
2. Confirm the accounting treatment.
3. Confirm required accounts.
4. Confirm posting/approval authority.
5. Confirm that payroll accounting is routed through the authorized financial workflow.

Do not generate a real payroll until both HR and FM have confirmed their respective sides.

---

# Part 16 — Accounting opening setup

Because the production-preparation script removes development/test journals and transactions, the organization must establish the real opening financial position before normal accounting starts.

The FM should verify:

- Cash `1100`.
- Bank `1200`.
- Electronic Wallet `1300`.
- Fina control/liability `2300`, if applicable.
- Fina-held funds `1401`, if applicable.
- Salary-advance receivable/control `1410`, if applicable.
- Other required production accounts.

The organization must determine actual opening balances from its real financial records.

**Do not copy development/test balances into production. Do not manually edit ledger balances.**

Opening financial entries must follow the organization's approved accounting procedure and the system's accounting workflow.

---

# Part 17 — Fina initial setup

If Fina is active at go-live, FM should verify:

1. Fina source configuration.
2. Fina collection workflow.
3. Liability/control account `2300`.
4. Fina-held-funds control `1401`.
5. Current opening liability/funds, if any.
6. Settlement authority.
7. Full-balance settlement behavior.

Fina must remain separate from Ahl El Kheir revenue.

---

# Part 18 — Salary Advance production setup

Before employees can submit real salary advances, FM/HR should verify:

1. Active salary-advance policy.
2. Effective date.
3. Maximum/eligibility rules.
4. Repayment configuration.
5. Payroll integration.
6. Direct repayment workflow.
7. Required receipt/evidence rules.
8. Financial account `1410`.
9. Disbursement source accounts `1100`, `1200`, and/or `1300`.

Do not carry development salary-advance requests into production. The production database cleanup already removes them.

---

# Part 19 — Project production setup

Before PM creates the first real project, PM should verify:

1. Project Manager account.
2. Project Supervisor accounts.
3. Project workflow.
4. Budget structure.
5. Approval routing.
6. FM review.
7. GM approval.
8. Launch workflow.
9. Expense/evidence workflow.
10. Closure workflow.

The production approval chain is:

**PM → FM → GM**

Financial closure returns to:

**FM**

PS does not become an accounting authority because the project contains financial information.

---

# Part 20 — The first real employee test

Before onboarding everyone simultaneously, select **one real employee from each major operational role**.

Recommended pilot:

- one Supervisor;
- one Nanny;
- one Accountant;
- one HR Staff member;
- one PM/PS if Projects are active;
- one ordinary Staff employee.

Each person should verify:

1. Correct dashboard.
2. Correct navigation.
3. Correct notifications.
4. Correct employee identity.
5. Correct record scope.
6. Correct permitted actions.
7. Correct blocked actions.

Only after these pilot users pass should the remaining employees be onboarded.

---

# Part 21 — Final onboarding sequence

### Phase A — Technical control

**Developer / System Administrator**

↓

Verify application, settings, roles, departments, currencies, accounts and production configuration.

### Phase B — Organizational authority

**General Manager**

↓

**HR Manager**

↓

**Financial Manager**

↓

**Vice General Manager**

### Phase C — Operational management

**Projects Manager**

↓

**Project Supervisor**

↓

**Accountant / Accountant Staff**

### Phase D — Operational departments

**Administration**

↓

**Staff**

↓

**Social Media**

↓

**Supervisors**

↓

**Nannies**

### Phase E — Remaining employees

All other employees are onboarded after their role, employee record, department, employment state and permissions have been verified.

---

# Part 22 — First-login checklist for every employee

1. Open the system.
2. Enter the assigned username/password.
3. Change the temporary password if requested.
4. Confirm the displayed employee name.
5. Confirm the dashboard matches the job.
6. Open the notification bell.
7. Confirm notifications work.
8. Open the available navigation.
9. Verify the expected modules are present.
10. Verify that obviously unauthorized modules are not available.
11. Open one representative record.
12. Confirm that the employee can only see records within their legitimate scope.
13. Perform one harmless read-only task.
14. Perform one authorized operational task if appropriate.
15. Log out.
16. Log back in.
17. Confirm the session works normally.

---

# Part 23 — What employees must NOT do

Employees must not:

- share accounts;
- use another employee's credentials;
- bypass the application using direct database changes;
- use another role's URL to perform unauthorized work;
- manually alter accounting balances;
- create duplicate records to work around a workflow;
- bypass approval routing;
- assume that seeing a record means they are authorized to change it;
- use Messages as a substitute for formal approval workflows.

The application's server-side authorization is the authority.

---

# Part 24 — Go-live acceptance gate

The organization should be considered ready for normal use only when:

- [ ] Developer/System Administrator verified the installation.
- [ ] GM account verified.
- [ ] HR Manager account verified.
- [ ] FM account verified.
- [ ] VGM account verified.
- [ ] Departments verified.
- [ ] Roles verified.
- [ ] Currency/reference data verified.
- [ ] Production accounting accounts verified.
- [ ] HR employee records verified.
- [ ] Employment states verified.
- [ ] Attendance policy verified.
- [ ] Payroll policy verified.
- [ ] Opening financial position established.
- [ ] Fina setup verified if applicable.
- [ ] Salary-advance policy verified.
- [ ] Project workflow verified if Projects are active.
- [ ] Supervisor responsibility assignments established.
- [ ] Nanny/family operational assignments established.
- [ ] Pilot users successfully tested.
- [ ] Notifications verified.
- [ ] Login/logout verified.
- [ ] Unauthorized access checks verified.
- [ ] Management agrees that the system is ready for normal operation.

---

# Final rule

The first normal organizational user after the developer should be the **General Manager**.

However, the developer/system administrator must first complete the technical and foundational setup needed to make the GM account correct.

The GM then becomes the first normal business user.

After the GM is confirmed, establish **HR Manager and FM**, because those two roles provide the foundation for employee/organizational administration and financial control.

Only after those control roles are confirmed should the wider organization begin using the system.
