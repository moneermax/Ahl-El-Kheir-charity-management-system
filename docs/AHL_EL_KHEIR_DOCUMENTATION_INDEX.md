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

## 2026-10-06 — Production go-live onboarding guide

Added the durable production onboarding reference:

- `PRODUCTION_GO_LIVE_INITIAL_SETUP_AND_FIRST_LOGIN_GUIDE.md` — step-by-step production setup, role onboarding order, first-login checks, HR/attendance/payroll/accounting/Fina/salary-advance/project setup, pilot-user verification, and final go-live acceptance gate.

The guide establishes the recommended first-user sequence: Developer/System Administrator for technical preparation, then **General Manager as the first normal organizational user**, followed by HR Manager and Financial Manager before wider operational onboarding.

The guide is based on the current documented role/workflow model and the verified production-preparation database contract. It does not replace source/schema or module-specific workflow documentation.

## Employee salary statement — 2026-10-19

Implementation reference: `modules/hr/payroll_statement.php` and the post-commit hook in `modules/hr/lib_payroll_accounting.php`. The statement is employee-scoped, available only for paid/accounting-posted payrolls, uses saved payroll components and period-effective salary currency, and supports A4 print / Save as PDF. **Implementation and requested heading layout are CLOSED at the agreed scope.** The user approved the final stacked header. Existing notification delivery/opening was confirmed for EMP-0017's paid October 2026 payroll (accounting entry 157). The final local A4 print preview after the last heading correction was not independently observed; reopen only for a concrete user-reported defect.


## 2026-10-19 — Employee salary statement implementation CLOSED

The user approved the final heading arrangement: the logo and “جمعية أهل الخير” remain together on the first row, with “بيان تفاصيل الراتب” and “كشف راتب رسمي” centered on a separate row below. The source changes are committed on `main`; the existing paid October 2026 payroll acceptance confirmed statement-notification delivery and initial statement opening for EMP-0017 (10,000.00 SDG; accounting entry 157). The implementation preserves employee-only access, the paid + accounting-posted guard, saved payroll values, incentive separation, and the existing `net_salary + incentive_amount` total-paid formula.

**Disposition:** implementation and the requested presentation correction are closed at the agreed scope. Do not run another payroll payment, mutate financial data, or repeat closed acceptance suites for this task. The assistant has not independently observed the user's final local A4 print preview after the last heading correction; this is not represented as runtime verification. Reopen only if the user reports a concrete defect.


## 2026-10-19 — GM payroll period report
The GM-only period completion report and verification boundary are documented in `HR_GM_PAYROLL_PERIOD_REPORT_CONTINUATION.md`. Local runtime verification is still pending.


## 2026-10-19 — GM payroll payment history
The GM dashboard and sidebar now link to `modules/hr/gm_payroll_period_history.php`, a GM-only, read-only index of periods with paid payroll records. Each period links to the existing detailed employee payment report. Local syntax and browser verification remain pending; payroll/accounting records must not be mutated for this navigation change.


## 2026-10-19 — GM payroll payment history CLOSED
The user confirmed the GM payroll payment history navigation works as intended. `modules/hr/gm_payroll_period_history.php` is the GM-only, read-only history index linked from the GM dashboard and sidebar, with links to each detailed period report. No payroll/accounting data was changed. Do not reopen without a concrete regression.

## 2026-10-19 — FM payroll table usability (active)
Target: `modules/hr/payroll.php?lang=en`. The active UI request is to reduce table width, keep the header visible during vertical scrolling, preserve row/action context after actions, and remove only redundant display information. Payroll calculations, payment states, and accounting behavior must remain unchanged. See the current continuation note in the master status and master continuation prompt.


## 2026-10-19 — FM payroll table usability
See `HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md` for source findings, committed UI/scroll changes, preserved financial behavior, and the focused local verification boundary. The source is committed to `main`, including the valid draft-form markup follow-up; local syntax/browser verification remains pending.


## 2026-10-19 — FM payroll table refinement and selected approval
See `HR_FM_PAYROLL_TABLE_USABILITY_CONTINUATION.md` for the current checkpoint: compact translated headings, dark sticky header, Save action placement, selected draft approval validation, and the remaining local verification boundary.


## HR Employment Contracts
- `HR_EMPLOYMENT_CONTRACT_CONTINUATION.md` — current scope, source checkpoint, legal-review boundary, and local verification steps for the in-system editable contract PDF builder.


## Nanny group verification continuation

- `ACCOUNTING_NANNY_GROUP_VERIFICATION_CONTINUATION.md` — bulk verification design, existing schema constraints, security behavior, verification boundary, and local acceptance checks.

 
## 2026-10-22 — Role-aware AI assistant (initial source milestone)

- Continuation: CHATGPT_AI_ASSISTANT_CONTINUATION.md.
- Shared floating panel and authenticated JSON endpoint are committed on main. The endpoint searches only the signed-in role's permitted section of the employee user guide, then optionally calls Gemini for general questions only after per-question consent.
- Internal documentation, application source, database rows and identity/role data are not sent to Google. No database/schema change was made.
- Verification remains pending: PHP 8.2 CI lint, local browser/session/CSRF tests, and a real InfinityFree connectivity/quota check. Do not call runtime-verified yet.


## 2026-10-22 — AI assistant chat history
See `CHATGPT_AI_ASSISTANT_CONTINUATION.md` for the gold launcher, persistent per-user history implementation, explicit database migration, and remaining local verification gates. History is not runtime verified until the migration is applied and cross-user isolation is tested.
