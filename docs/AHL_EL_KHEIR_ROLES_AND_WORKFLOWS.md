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
5. Payroll schedule and payroll repayment reduce the balance.
6. Evidence is recorded.
7. Direct employee repayment and final settlement are reserved for Stage 6 and are not yet implemented at this baseline.

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
