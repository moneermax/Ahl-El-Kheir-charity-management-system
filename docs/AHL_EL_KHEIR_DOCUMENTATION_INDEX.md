# Ahl El Kheir — Documentation Index and Governance

Baseline: 2026-10-03

## 1. Canonical documents

1. AHL_EL_KHEIR_SYSTEM_ANALYSIS.md — complete system explanation.
2. AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md — technical architecture and relationships.
3. AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md — role authority and workflows.
4. AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md — employee tasks and step-by-step procedures.
5. AHL_EL_KHEIR_OPERATIONS_SECURITY.md — security, operations and maintenance.
6. AHL_EL_KHEIR_DOCUMENTATION_INDEX.md — this governance/index document.

## 2. Status/evidence documents

AHL_EL_KHEIR_MASTER_STATUS.md and AHL_EL_KHEIR_MASTER_AUDIT.md remain historical/current-status evidence. They must not contradict the canonical documents.

## 3. Domain references

Projects:
- PROJECTS_MODULE_AUDIT_AND_REMEDIATION_PLAN.md
- PROJECTS_RUNTIME_CHECKPOINT_2026-09-26.md
- PROJECTS_VIEW_RUNTIME_FIXES_2026-09-25.md
- PROJECT_APPROVAL_NOTIFICATION_WORKFLOW_2026-09-23.md
- PROJECT_HISTORY_TABLE_FIX_2026-09-25.md

HR/Salary Advance:
- HR_SALARY_ADVANCE_CONTINUATION.md
- HR_SALARY_ADVANCE_STAGE5_AUDIT.md
- HR_SALARY_ADVANCE_GM_WAIVER_CONTINUATION.md

Fina:
- FINA_SETTLEMENT_PROCESS.md
- FINA_STANDALONE_PAYMENT_MODEL.md

Accounting:
- ACCOUNTING_JOURNAL_INTEGRITY_CHECKPOINT_2026-09-15.md
- AUDIT_SUPERVISOR_ACCOUNTING_INTEGRATION_20260914.md

Cross-cutting:
- I18N.md
- PRODUCTION_PREPARATION.md
- CHATGPT_MASTER_CONTINUATION_PROMPT.md
- CHATGPT_SESSION_INDEX.md

## 4. Historical support

docs/checkpoints/ contains dated checkpoint material.
code_artifact.* and images are supporting artifacts.

Historical evidence is retained; later verified state supersedes earlier checkpoint claims.

## 5. Documentation standard

Every durable document should identify:
- status;
- baseline/date;
- scope;
- authoritative facts;
- implementation location where useful;
- verification boundary;
- known limitations/open work.

Avoid chat narration, duplicated architecture, guessed schema and unverified runtime claims.

## 6. New-developer reading order

README.md
-> AHL_EL_KHEIR_SYSTEM_ANALYSIS.md
-> AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
-> AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
-> AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
-> relevant domain reference
-> source/schema/migration
-> AHL_EL_KHEIR_OPERATIONS_SECURITY.md

## 7. Conflict rule

If documents disagree:
1. current source/schema wins for implementation facts;
2. later verified evidence wins over older checkpoint claims;
3. current business rules win over obsolete proposals;
4. unresolved contradictions are documented rather than guessed.


## 2026-10-05 — HR attendance policy reference

Attendance policy implementation references: database/migrations/2026-10-05_hr_attendance_policy.sql, modules/hr/lib_attendance_policy.php, modules/hr/attendance_policy.php and tools/finalize_daily_attendance.php. The policy follows the versioned/effective-date model used by salary advance policy. Runtime verification is an explicit open gate.


### Attendance ↔ Payroll policy integration checkpoint — 2026-10-05
The attendance policy remains the authoritative source for attendance facts, while the payroll/salary-deduction policy remains authoritative for monetary treatment. Cross-policy behavior is documented in the system analysis, architecture/data model, roles/workflows, operations/security, master status/audit, and continuation documents. Runtime verification of the new payroll attendance-deduction migration and end-to-end calculation remains pending locally.


## 2026-10-06 — GM Salary Advance Waiver verification checkpoint

The GM salary-advance waiver is an exceptional lifecycle extension, separate from completed Salary Advance Stages 1–6. Its design and implementation remain under controlled runtime verification.

The real Stage 5 payroll repayment dependency has now passed the existing rollback-only local harness: payroll repayment application, schedule/outstanding update, balanced Cr 1410 accounting, duplicate protection, and rollback-only cleanup all passed for `SAR-2026-00004`. This is dependency verification only; the GM waiver itself remains open until its documented verification matrix passes.

Canonical waiver reference: `HR_SALARY_ADVANCE_GM_WAIVER_CONTINUATION.md`.
