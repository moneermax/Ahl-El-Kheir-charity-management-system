# Ahl El Kheir — Employee Tasks and Step-by-Step User Guide

Status: Canonical employee guide
Baseline: 2026-10-03

This guide describes implemented business tasks. It does not grant permissions.

## 1. All employees

### Sign in
1. Open the system.
2. Enter assigned credentials.
3. Complete a required password change if shown.
4. Confirm the dashboard matches the job role.

### Notifications
1. Open the notification bell.
2. Read the relevant item.
3. Follow the workflow destination.
4. Use full history when needed.
5. Clearing the bell menu does not delete history.

### Messages
1. Open Messages.
2. Open the conversation.
3. Read/reply.
4. Attach documents when required.
5. Use formal workflow pages for approvals, not messages.

## 2. GM

### Approval
1. Open the relevant approval queue.
2. Review the complete business/financial context.
3. Approve or reject.
4. Record the required rejection reason when applicable.
5. Verify the resulting workflow/notification.

### Project approval
1. Open the project awaiting GM action.
2. Review scope and FM-confirmed financial information.
3. Approve or reject.
4. Verify the next state.

## 3. VGM

### Sponsor assignment
1. Open sponsor assignment.
2. Review unassigned/available sponsors.
3. Assign the responsible Supervisor.
4. Save.
5. Verify assignment/history.
6. Use reassignment only when justified.

## 4. FM

### Financial review
1. Open FM dashboard/review queue.
2. Select the pending item.
3. Verify source, amount, payment method and evidence.
4. Verify accounting treatment.
5. Approve/reject/return/post as permitted.
6. Confirm journal/reference/evidence.

### Treasury
1. Open FM Dashboard.
2. Review Cash 1100, Bank 1200, Electronic Wallet 1300.
3. Use reconciliation reports for differences.
4. Never edit a balance directly.

### Project financial closure
1. Open the project routed for financial closure.
2. Confirm administrative closure.
3. Review released funds, expenses and remaining balance.
4. Return the remaining balance to the correct institutional account.
5. Verify the return evidence/journal.
6. Complete financial closure.
7. Confirm controlled balance is zero.

### Fina settlement
1. Open Fina Settlement.
2. Review the 2300 liability.
3. Confirm the full balance.
4. Execute settlement.
5. Verify Dr 2300 / Cr 1401.
6. Confirm 2300 is zero.

### Salary advance
1. Open Salary Advance Processing.
2. Review policy/customization.
3. Approve/reject.
4. Complete approved disbursement.
5. Review repayment schedule/payroll deduction.
6. For direct repayment, verify receipt/evidence and journal.
7. Confirm final outstanding balance is zero.

## 5. Accountant / Accountant Staff

### Accounting processing
1. Open the assigned accounting queue.
2. Review source and evidence.
3. Process only permitted actions.
4. Verify balanced journal/evidence.
5. Use returned-funds and receipt controls when applicable.
6. Escalate FM-only decisions.

### Ledger/report investigation
1. Open the authorized ledger/report.
2. Apply date/account filters.
3. Trace balances to posted journal lines.
4. Record the finding rather than manually changing balances.

## 6. Supervisor

### Sponsor work
1. Open Sponsors.
2. Work only within letter+gender scope.
3. Open the sponsor.
4. Review linked sponsorship/family information.
5. Perform authorized operational updates.
6. Escalate financial matters.

### Payment/request submission
1. Open the authorized payment/request page.
2. Select sponsor/sponsorship.
3. Enter amount and payment method.
4. Provide required evidence.
5. Submit.
6. Wait for Accounting/FM review.
7. Follow the final result notification.

## 7. Nanny

1. Open Nanny Dashboard.
2. Open assigned families/orphan forms.
3. Review/update permitted operational information.
4. Complete required verification/forms.
5. Use only authorized financial/reporting functions.

## 8. Administration

### Sponsor requests
1. Open Administration Dashboard.
2. Open sponsor requests.
3. Review required information.
4. Process the request workflow.
5. Verify status.

### Winback
1. Open Winback.
2. Review stopped/eligible sponsors.
3. Review uncovered families.
4. Open the relevant case.
5. Perform the permitted follow-up/reassignment action.
6. Record the result.

Winback is Administration-only.

## 9. Staff

1. Open Staff Dashboard.
2. Review sponsor-request/orphan-form tasks.
3. Open the authorized workflow.
4. Complete the operational task.
5. Escalate actions requiring another role.

## 10. Social Media

1. Open the shared Staff/Social Media Dashboard.
2. Review authorized requests.
3. Perform the social/operational task.
4. Record the result.
5. Do not use Administration-only Winback.

## 11. PM

### Create/submit project
1. Open Projects Dashboard.
2. Create project.
3. Enter operational details and proposed budget.
4. Save repeatable details/evidence.
5. Review.
6. Submit.
7. Follow approval notifications.

### Manage execution
1. Open approved project.
2. Confirm approval state.
3. Launch when permitted.
4. Coordinate with PS.
5. Monitor execution and controlled funds.
6. Leave FM-only actions to FM.

### Administrative closure
1. Confirm execution complete.
2. Review expenses and balance.
3. Enter closure reason/evidence.
4. Administratively close.
5. Verify routing to FM.
6. Wait for final financial-closure notification.

## 12. PS

### Execute project
1. Open Projects Dashboard.
2. Open assigned project.
3. Review operational scope.
4. Update permitted non-financial fields.
5. Record execution expenses/evidence.
6. Verify saved amount.
7. Escalate reconciliation/closure to PM/FM.

PS is Project Supervisor, not Sponsor Supervisor.

## 13. HR Manager / HR Staff

### Employee
1. Open HR Dashboard.
2. Create/update employee when authorized.
3. Maintain employment state and contract.
4. Verify employee identity/linkage.

### Attendance
1. Open Attendance.
2. Select period.
3. Enter/verify attendance.
4. Save through the authorized workflow.

### Leave
1. Open Leave Requests.
2. Review request.
3. Approve/reject according to authority.
4. Verify notification/result.

### Payroll
1. Open Payroll.
2. Select period.
3. Verify eligible employees and calculations.
4. Review salary-advance deductions.
5. Process payroll.
6. Verify accounting integration when authorized.

## 14. Universal blocked-action procedure

If an action is unavailable:
1. check your role;
2. check record scope;
3. check workflow state;
4. read the system message;
5. route the work to the responsible role.

Never bypass the application with a direct URL or manual database edit.


## 2026-10-05 — HR salary register

### Salary register

HR Manager/HR Staff can use **سجل الرواتب عند التعيين** from Employee Management to review the salary register for currently working employees.

The register:
1. shows the employee's first positive salary-history entry;
2. ignores the automatic 0.00 provisioning placeholder;
3. shows hire date, salary effective date, salary, currency and department;
4. calculates the employee count, total and average salary;
5. excludes suspended, terminated/separated and other non-working employment states.

The current varied salary amounts are development/test data. They are stored in the database and are not hard-coded application values.

Non-working employees are not deleted from HR history or accounting history. They simply fall outside the current working/payroll scope.

## 2026-10-05 — Attendance policy and automatic attendance

HR Manager/Admin: open سياسة الحضور والانصراف from the HR Dashboard, create a future-effective policy, configure working start/end, attendance cutoff and absence-finalization time, configure the automation switches, save and verify the version.

Employee: no separate attendance action is required for the normal remote-work V1 flow. A successful qualifying system login can establish the day's attendance. The first check-in is preserved across later logins.

Automatic absence: the server-side scheduled finalizer creates an absence only when an eligible working employee has no attendance record for that date. Approved leave and non-working employment states are excluded. Runtime verification remains pending.


## 2026-10-05 — Attendance Policy ↔ Payroll Deduction Integration
Attendance and payroll policies are intentionally separate but coordinated. The **attendance policy is the source of attendance facts** (working hours, working days, automatic login attendance, absence finalization and work mode); the **payroll policy is the source of monetary treatment** (whether absence/unpaid leave are deductible and the applicable percentage). Payroll must never infer an absence merely because an attendance row is missing.

The integration now adds `payroll.attendance_deduction` as a separate payroll component. Draft payroll refresh calculates it from authoritative attendance/approved-leave records, using the payroll record's linked payroll-policy version and the attendance policy effective on each calendar date. Working days come from the effective attendance policy; non-working days produce no automatic absence and no attendance-derived deduction. Approved paid leave produces no deduction; approved unpaid leave uses the payroll policy's unpaid-leave percentage; an explicit attendance `absent` row uses the payroll policy's absence percentage. Missing attendance rows are not treated as absence. Late/early-departure monetary deductions remain disabled until their policy contains sufficient threshold/rate semantics.

Salary-advance payroll integration remains downstream: attendance deduction reduces the eligible salary before salary-advance deduction when the salary-advance policy uses `net_before_advance`. The salary-advance component remains separate and continues to be bounded by its own policy/schedule. No salary-advance accounting semantics were changed. Existing zero attendance-deduction behavior remains unchanged for payrolls without attendance-derived deductions.

The payroll UI displays **general deductions**, **attendance deduction**, and **salary-advance deduction** separately. Posted payrolls are immutable; attendance changes after payroll approval do not retroactively rewrite the posted payroll. Draft payrolls can be refreshed before approval.

Migration: `database/migrations/2026-10-05_hr_attendance_payroll_integration.sql` adds `attendance_deduction` to payroll and `working_days` to attendance-policy versions. Runtime verification of the new cross-policy path is pending on the local XAMPP/MariaDB environment.
