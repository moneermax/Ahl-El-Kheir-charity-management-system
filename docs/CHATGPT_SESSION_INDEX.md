# Ahl El Kheir — Session Index

## Canonical reading order

1. AHL_EL_KHEIR_SYSTEM_ANALYSIS.md
2. AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
3. AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
4. AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
5. AHL_EL_KHEIR_OPERATIONS_SECURITY.md
6. AHL_EL_KHEIR_DOCUMENTATION_INDEX.md

## Domain references

Projects: PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md and related Projects checkpoint documents.

HR/Salary Advance: HR_SALARY_ADVANCE_CONTINUATION.md and HR_SALARY_ADVANCE_STAGE5_AUDIT.md.

Fina: FINA_SETTLEMENT_PROCESS.md and FINA_STANDALONE_PAYMENT_MODEL.md.

Accounting: ACCOUNTING_JOURNAL_INTEGRITY_CHECKPOINT_2026-09-15.md and AUDIT_SUPERVISOR_ACCOUNTING_INTEGRATION_20260914.md.

## Current continuation point — 2026-10-05

Projects Phase 5 is closed after full controlled reconciliation and financial closure.

Do not repeat closed fixtures without regression evidence. Continue from the next genuinely open engineering or audit item.

## Working rule

Repository source, schema and later verified evidence outrank chat history and older checkpoint text.


### HR checkpoint — 2026-10-05

The HR employee salary register is implemented and refined:
- first positive salary-history value is used instead of the mutable current salary;
- the automatic 0.00 provisioning placeholder is ignored;
- current varied salary values are database test data only;
- non-working employment states are excluded from the current register using `hr_employment_states.category = 'working'` and `is_active = 1`;
- historical HR/payroll/accounting evidence remains preserved.

Latest related implementation commit: `b8adb067c3de34441192e63fff56c6b707b3aa22`.

The next session should inspect the current `main` state and identify the next concrete HR/accounting/reporting requirement rather than changing salary-register data again without a business reason.

### HR attendance policy checkpoint — 2026-10-05

Attendance policy foundation is implemented on main. Rules are versioned in hr_attendance_policy_versions; administration is modules/hr/attendance_policy.php; successful-login integration is in index.php through modules/hr/lib_attendance_policy.php; automatic absence is tools/finalize_daily_attendance.php. Default/example values are remote work, 07:00–16:00, stored as policy data. Canonical attendance eligibility remains based on employment state and approved leave.

Status: source implementation complete; migration application and runtime verification pending. Next gate: apply the migration, create/verify the first effective policy, test qualifying login, verify repeated login preserves the first check-in, verify non-working/approved-leave exclusions, and execute the finalizer idempotently.
