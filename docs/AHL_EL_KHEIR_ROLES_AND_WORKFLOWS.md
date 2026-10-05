# Ahl El Kheir — Roles and Workflows

Status: Canonical business reference
Baseline: 2026-10-03

## 1. Role responsibility matrix

| Role | Main responsibility |
|---|---|
| Admin | system/user administration and controlled maintenance |
| GM | senior organizational decisions and final management approvals |
| VGM | delegated organizational oversight and sponsor assignment/reassignment |
| FM | financial review, posting, treasury, reconciliation and financial closure |
| Accountant / Accountant Staff | authorized accounting operations and assigned financial processing |
| Supervisor | scoped sponsor/sponsorship operations and operational payment requests |
| Nanny | assigned family/orphan operational work and authorized related functions |
| Administration | sponsor requests, Winback and administration dashboard work |
| Staff | general operational/staff functions exposed to the role |
| Social Media | authorized shared staff/social-media work |
| PM | project preparation, submission, management and administrative closure |
| PS | assigned project execution and non-financial operational updates |
| HR Manager | employee, employment, attendance, leave, payroll and HR control |
| HR Staff | HR operational processing |

## 2. Supervisor scope

Sponsor scope is Sponsor first-name letter + Sponsor gender.

Direct URL access must enforce the same rule.

## 3. Project lifecycle

### Approval
1. PM creates and submits.
2. FM reviews financial controls.
3. GM completes the organizational approval step.
4. Rejection routes backward through the documented approval chain.
5. After FM final confirmation, PM and assigned PS receive the operational handoff notification.

### Execution
1. PM launches after required approvals.
2. PS executes assigned operational work.
3. PS records permitted project expenses/evidence.
4. Accounting remains separated from PS authority.

### Closure
1. PM administratively closes the project.
2. Remaining controlled funds are routed to FM.
3. FM reconciles the balance.
4. FM returns the remaining balance to the selected institutional treasury account.
5. FM completes financial closure.
6. PM receives final completion notification.
7. Administrative lifecycle remains closed; financial closure is independently evidenced.

PRJ-0015 Phase 5 is the closed controlled acceptance example: 300,000 released, 50,000 expense, 250,000 returned to 1200 Bank, final balance 0.

## 4. Accounting workflow

Operational request -> accounting review -> authorized approval/posting -> balanced journal -> evidence -> ledger/report.

FM controls financial review and final financial actions. Operational users must not bypass FM.

## 5. Fina

Fina is a protected third-party fund.

Current settlement:
1. collect through Fina workflow;
2. liability accumulates in 2300;
3. funds are controlled separately through 1401;
4. FM settles the full current liability;
5. journal is Dr 2300 / Cr 1401;
6. 2300 becomes zero and remains available.

Partial settlement is not production behavior.

## 6. Salary advances

1. Employee submits request.
2. FM reviews against active policy/customization.
3. Approved request is disbursed.
4. Receivable/control is recorded.
5. Payroll schedule and/or approved direct repayment reduces the balance.
6. Evidence is recorded.
7. Final balance reaches zero.

The detailed salary-advance policy is maintained in the dedicated domain documentation.

## 7. Sponsorship

Sponsor/request -> authorized operational processing -> responsibility assignment -> sponsorship -> payment/transaction workflow -> receipt/reporting.

VGM owns sponsor assignment/reassignment.

## 8. HR

Employee provisioning/linkage -> employment state -> contract -> attendance/leave -> payroll -> accounting integration.

HR authority and Accounting authority remain separate.

## 9. Notifications

Notifications communicate workflow results; they do not confer authority. Employee-facing notifications should expose only the business outcome required by the workflow.

## 10. Workflow specification rule

Every new workflow must define:
actors, scope, states, transitions, rejection path, financial effect, accounting reference, evidence, notifications, final state and duplicate-action protection.


## 2026-10-05 — Attendance policy workflow

HR Manager/Admin can configure future attendance-policy versions through modules/hr/attendance_policy.php. The policy controls working hours, attendance cutoff, absence-finalization time, automatic login attendance, automatic absence and default work mode. Current/historical versions are protected; future versions may be edited or deleted.

Employee attendance lifecycle: successful authentication resolves the linked active employee, loads the effective policy, applies canonical attendance eligibility, and creates today's present record when policy permits. The first check-in is preserved. At the policy finalization time, the scheduled finalizer marks eligible working employees with no attendance row as absent. Approved leave and non-working employment states remain excluded.

Runtime status: source implementation complete; local migration and end-to-end verification pending.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.
