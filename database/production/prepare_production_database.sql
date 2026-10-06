-- ============================================================
-- Ahl El Kheir Charity Management System
-- PRODUCTION DATABASE PREPARATION / TEST-DATA CLEANUP
-- Baseline: AhlElKheir_dev_backup_2026-09-04.sql
-- MariaDB 10.4+
--
-- PURPOSE:
--   Prepare the current development database for production by
--   clearing all records except the tables/data explicitly preserved
--   by the original production-preparation contract.
--
-- PRESERVED BY THE ORIGINAL SCRIPT:
--   * users / employees
--   * families / family children
--   * sponsors
--   * sponsorships / sponsorship children
--   * sponsor-supervisor assignment history
--   * supervisor letters and assignment history
--   * reference/configuration data represented by roles, departments,
--     currencies, letters, medical_needs, settings and accounts
--
-- IMPORTANT:
--   1. Make a FULL backup immediately before running this script.
--   2. Run this against the restored development backup first.
--   3. This script intentionally does NOT drop tables.
--   4. This script clears database records only. It does NOT delete
--      physical files from storage/.
--   5. Any table not explicitly preserved above is treated as
--      development/test data for production preparation.
--   6. Account cleanup retains the original script's exact eight
--      test-account deletions; all other account rows are preserved.
-- ============================================================

USE `ahl_el_kheir`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- Every current table not explicitly preserved by the original
-- production-preparation contract is development/test data.
TRUNCATE TABLE `accountant_nanny_assignments`;
TRUNCATE TABLE `accounting_admin_fee_policies`;
TRUNCATE TABLE `attendance`;
TRUNCATE TABLE `audit_log`;
TRUNCATE TABLE `contracts`;
TRUNCATE TABLE `fina_collections`;
TRUNCATE TABLE `fina_settlements`;
TRUNCATE TABLE `fina_settlement_allocations`;
TRUNCATE TABLE `fina_sources`;
TRUNCATE TABLE `family_bank_accounts`;
TRUNCATE TABLE `family_documents`;
TRUNCATE TABLE `group_children`;
TRUNCATE TABLE `group_workflow_audit_log`;
TRUNCATE TABLE `hr_attendance_policy_versions`;
TRUNCATE TABLE `hr_employee_contracts`;
TRUNCATE TABLE `hr_employee_salary_history`;
TRUNCATE TABLE `hr_employee_state_history`;
TRUNCATE TABLE `hr_employment_states`;
TRUNCATE TABLE `hr_leave_returns`;
TRUNCATE TABLE `hr_payroll_policy_versions`;
TRUNCATE TABLE `hr_payroll_reversals`;
TRUNCATE TABLE `hr_salary_advance_direct_repayments`;
TRUNCATE TABLE `hr_salary_advance_direct_repayment_documents`;
TRUNCATE TABLE `hr_salary_advance_documents`;
TRUNCATE TABLE `hr_salary_advance_payroll_repayments`;
TRUNCATE TABLE `hr_salary_advance_policy_versions`;
TRUNCATE TABLE `hr_salary_advance_repayment_schedule`;
TRUNCATE TABLE `hr_salary_advance_requests`;
TRUNCATE TABLE `hr_salary_advance_waiver_decisions`;
TRUNCATE TABLE `hr_salary_advance_waiver_items`;
TRUNCATE TABLE `hr_salary_advance_waiver_schedule_items`;
TRUNCATE TABLE `journal_entries`;
TRUNCATE TABLE `journal_lines`;
TRUNCATE TABLE `leaves`;
TRUNCATE TABLE `messages`;
TRUNCATE TABLE `message_attachments`;
TRUNCATE TABLE `message_reads`;
TRUNCATE TABLE `monthly_disbursements`;
TRUNCATE TABLE `nanny_family_verifications`;
TRUNCATE TABLE `nanny_group_assignments`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `orphan_documents`;
TRUNCATE TABLE `orphan_groups`;
TRUNCATE TABLE `other_projects`;
TRUNCATE TABLE `password_recovery_requests`;
TRUNCATE TABLE `payroll`;
TRUNCATE TABLE `performance_reviews`;
TRUNCATE TABLE `project_approval`;
TRUNCATE TABLE `project_beneficiaries`;
TRUNCATE TABLE `project_beneficiary_records`;
TRUNCATE TABLE `project_budgets`;
TRUNCATE TABLE `project_budget_lines`;
TRUNCATE TABLE `project_contacts`;
TRUNCATE TABLE `project_details`;
TRUNCATE TABLE `project_documents`;
TRUNCATE TABLE `project_expenses`;
TRUNCATE TABLE `project_expense_approvals`;
TRUNCATE TABLE `project_funding_allocations`;
TRUNCATE TABLE `project_funding_returns`;
TRUNCATE TABLE `project_government_requirements`;
TRUNCATE TABLE `project_labor_comments`;
TRUNCATE TABLE `project_labor_helpers`;
TRUNCATE TABLE `project_lifecycle`;
TRUNCATE TABLE `project_milestones`;
TRUNCATE TABLE `project_partners`;
TRUNCATE TABLE `project_payment_evidence`;
TRUNCATE TABLE `project_procurement_methods`;
TRUNCATE TABLE `project_progress_updates`;
TRUNCATE TABLE `project_status_history`;
TRUNCATE TABLE `project_supervisor_assignments`;
TRUNCATE TABLE `project_team`;
TRUNCATE TABLE `returned_disbursement_reissues`;
TRUNCATE TABLE `sponsor_import_exceptions`;
TRUNCATE TABLE `sponsor_payments`;
TRUNCATE TABLE `sponsor_requests`;
TRUNCATE TABLE `supervisor_leaves`;
TRUNCATE TABLE `suspension_cases`;
TRUNCATE TABLE `transactions`;
TRUNCATE TABLE `user_sessions`;
TRUNCATE TABLE `verification_audit_log`;
TRUNCATE TABLE `vouchers`;
TRUNCATE TABLE `winback_campaigns`;
TRUNCATE TABLE `winback_contacts`;

-- Preserve the original script's exact account cleanup rule.
DELETE FROM `accounts`
WHERE `code` IN (
    '4400-1',
    '5100-1',
    '4400-2',
    '5100-2',
    '4400-3',
    '5100-3',
    '4400-4',
    '5100-4'
);

ALTER TABLE `accounts` AUTO_INCREMENT = 19;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Verification: preserved core data.
SELECT
    'CORE DATA PRESERVED' AS section,
    (SELECT COUNT(*) FROM `users`) AS users_count,
    (SELECT COUNT(*) FROM `employees`) AS employees_count,
    (SELECT COUNT(*) FROM `families`) AS families_count,
    (SELECT COUNT(*) FROM `family_children`) AS family_children_count,
    (SELECT COUNT(*) FROM `sponsors`) AS sponsors_count,
    (SELECT COUNT(*) FROM `sponsorships`) AS sponsorships_count,
    (SELECT COUNT(*) FROM `sponsorship_children`) AS sponsorship_children_count,
    (SELECT COUNT(*) FROM `sponsor_supervisor_assignments`) AS sponsor_supervisor_history_count,
    (SELECT COUNT(*) FROM `supervisor_letters`) AS current_supervisor_letters_count,
    (SELECT COUNT(*) FROM `supervisor_letter_assignment_history`) AS supervisor_letter_history_count;

-- Verification: preserved reference/configuration data.
SELECT
    'REFERENCE DATA PRESERVED' AS section,
    (SELECT COUNT(*) FROM `roles`) AS roles_count,
    (SELECT COUNT(*) FROM `departments`) AS departments_count,
    (SELECT COUNT(*) FROM `currencies`) AS currencies_count,
    (SELECT COUNT(*) FROM `letters`) AS letters_count,
    (SELECT COUNT(*) FROM `medical_needs`) AS medical_needs_count,
    (SELECT COUNT(*) FROM `settings`) AS settings_count,
    (SELECT COUNT(*) FROM `accounts`) AS accounts_count;

-- Verification: every cleaned table must be empty.
SELECT
    'TEST DATA CLEARED' AS section,
    (SELECT COUNT(*) FROM `accountant_nanny_assignments`) AS accountant_nanny_assignments,
    (SELECT COUNT(*) FROM `accounting_admin_fee_policies`) AS accounting_admin_fee_policies,
    (SELECT COUNT(*) FROM `attendance`) AS attendance,
    (SELECT COUNT(*) FROM `audit_log`) AS audit_log,
    (SELECT COUNT(*) FROM `contracts`) AS contracts,
    (SELECT COUNT(*) FROM `fina_collections`) AS fina_collections,
    (SELECT COUNT(*) FROM `fina_settlements`) AS fina_settlements,
    (SELECT COUNT(*) FROM `fina_settlement_allocations`) AS fina_settlement_allocations,
    (SELECT COUNT(*) FROM `fina_sources`) AS fina_sources,
    (SELECT COUNT(*) FROM `family_bank_accounts`) AS family_bank_accounts,
    (SELECT COUNT(*) FROM `family_documents`) AS family_documents,
    (SELECT COUNT(*) FROM `group_children`) AS group_children,
    (SELECT COUNT(*) FROM `group_workflow_audit_log`) AS group_workflow_audit_log,
    (SELECT COUNT(*) FROM `hr_attendance_policy_versions`) AS hr_attendance_policy_versions,
    (SELECT COUNT(*) FROM `hr_employee_contracts`) AS hr_employee_contracts,
    (SELECT COUNT(*) FROM `hr_employee_salary_history`) AS hr_employee_salary_history,
    (SELECT COUNT(*) FROM `hr_employee_state_history`) AS hr_employee_state_history,
    (SELECT COUNT(*) FROM `hr_employment_states`) AS hr_employment_states,
    (SELECT COUNT(*) FROM `hr_leave_returns`) AS hr_leave_returns,
    (SELECT COUNT(*) FROM `hr_payroll_policy_versions`) AS hr_payroll_policy_versions,
    (SELECT COUNT(*) FROM `hr_payroll_reversals`) AS hr_payroll_reversals,
    (SELECT COUNT(*) FROM `hr_salary_advance_direct_repayments`) AS hr_salary_advance_direct_repayments,
    (SELECT COUNT(*) FROM `hr_salary_advance_direct_repayment_documents`) AS hr_salary_advance_direct_repayment_documents,
    (SELECT COUNT(*) FROM `hr_salary_advance_documents`) AS hr_salary_advance_documents,
    (SELECT COUNT(*) FROM `hr_salary_advance_payroll_repayments`) AS hr_salary_advance_payroll_repayments,
    (SELECT COUNT(*) FROM `hr_salary_advance_policy_versions`) AS hr_salary_advance_policy_versions,
    (SELECT COUNT(*) FROM `hr_salary_advance_repayment_schedule`) AS hr_salary_advance_repayment_schedule,
    (SELECT COUNT(*) FROM `hr_salary_advance_requests`) AS hr_salary_advance_requests,
    (SELECT COUNT(*) FROM `hr_salary_advance_waiver_decisions`) AS hr_salary_advance_waiver_decisions,
    (SELECT COUNT(*) FROM `hr_salary_advance_waiver_items`) AS hr_salary_advance_waiver_items,
    (SELECT COUNT(*) FROM `hr_salary_advance_waiver_schedule_items`) AS hr_salary_advance_waiver_schedule_items,
    (SELECT COUNT(*) FROM `journal_entries`) AS journal_entries,
    (SELECT COUNT(*) FROM `journal_lines`) AS journal_lines,
    (SELECT COUNT(*) FROM `leaves`) AS leaves,
    (SELECT COUNT(*) FROM `messages`) AS messages,
    (SELECT COUNT(*) FROM `message_attachments`) AS message_attachments,
    (SELECT COUNT(*) FROM `message_reads`) AS message_reads,
    (SELECT COUNT(*) FROM `monthly_disbursements`) AS monthly_disbursements,
    (SELECT COUNT(*) FROM `nanny_family_verifications`) AS nanny_family_verifications,
    (SELECT COUNT(*) FROM `nanny_group_assignments`) AS nanny_group_assignments,
    (SELECT COUNT(*) FROM `notifications`) AS notifications,
    (SELECT COUNT(*) FROM `orphan_documents`) AS orphan_documents,
    (SELECT COUNT(*) FROM `orphan_groups`) AS orphan_groups,
    (SELECT COUNT(*) FROM `other_projects`) AS other_projects,
    (SELECT COUNT(*) FROM `password_recovery_requests`) AS password_recovery_requests,
    (SELECT COUNT(*) FROM `payroll`) AS payroll,
    (SELECT COUNT(*) FROM `performance_reviews`) AS performance_reviews,
    (SELECT COUNT(*) FROM `project_approval`) AS project_approval,
    (SELECT COUNT(*) FROM `project_beneficiaries`) AS project_beneficiaries,
    (SELECT COUNT(*) FROM `project_beneficiary_records`) AS project_beneficiary_records,
    (SELECT COUNT(*) FROM `project_budgets`) AS project_budgets,
    (SELECT COUNT(*) FROM `project_budget_lines`) AS project_budget_lines,
    (SELECT COUNT(*) FROM `project_contacts`) AS project_contacts,
    (SELECT COUNT(*) FROM `project_details`) AS project_details,
    (SELECT COUNT(*) FROM `project_documents`) AS project_documents,
    (SELECT COUNT(*) FROM `project_expenses`) AS project_expenses,
    (SELECT COUNT(*) FROM `project_expense_approvals`) AS project_expense_approvals,
    (SELECT COUNT(*) FROM `project_funding_allocations`) AS project_funding_allocations,
    (SELECT COUNT(*) FROM `project_funding_returns`) AS project_funding_returns,
    (SELECT COUNT(*) FROM `project_government_requirements`) AS project_government_requirements,
    (SELECT COUNT(*) FROM `project_labor_comments`) AS project_labor_comments,
    (SELECT COUNT(*) FROM `project_labor_helpers`) AS project_labor_helpers,
    (SELECT COUNT(*) FROM `project_lifecycle`) AS project_lifecycle,
    (SELECT COUNT(*) FROM `project_milestones`) AS project_milestones,
    (SELECT COUNT(*) FROM `project_partners`) AS project_partners,
    (SELECT COUNT(*) FROM `project_payment_evidence`) AS project_payment_evidence,
    (SELECT COUNT(*) FROM `project_procurement_methods`) AS project_procurement_methods,
    (SELECT COUNT(*) FROM `project_progress_updates`) AS project_progress_updates,
    (SELECT COUNT(*) FROM `project_status_history`) AS project_status_history,
    (SELECT COUNT(*) FROM `project_supervisor_assignments`) AS project_supervisor_assignments,
    (SELECT COUNT(*) FROM `project_team`) AS project_team,
    (SELECT COUNT(*) FROM `returned_disbursement_reissues`) AS returned_disbursement_reissues,
    (SELECT COUNT(*) FROM `sponsor_import_exceptions`) AS sponsor_import_exceptions,
    (SELECT COUNT(*) FROM `sponsor_payments`) AS sponsor_payments,
    (SELECT COUNT(*) FROM `sponsor_requests`) AS sponsor_requests,
    (SELECT COUNT(*) FROM `supervisor_leaves`) AS supervisor_leaves,
    (SELECT COUNT(*) FROM `suspension_cases`) AS suspension_cases,
    (SELECT COUNT(*) FROM `transactions`) AS transactions,
    (SELECT COUNT(*) FROM `user_sessions`) AS user_sessions,
    (SELECT COUNT(*) FROM `verification_audit_log`) AS verification_audit_log,
    (SELECT COUNT(*) FROM `vouchers`) AS vouchers,
    (SELECT COUNT(*) FROM `winback_campaigns`) AS winback_campaigns,
    (SELECT COUNT(*) FROM `winback_contacts`) AS winback_contacts;

-- Verification: the original eight project-created test accounts
-- must be gone, while all other account rows remain untouched.
SELECT
    'ORIGINAL TEST ACCOUNTS REMAINING (MUST BE 0)' AS check_name,
    COUNT(*) AS remaining_count
FROM `accounts`
WHERE `code` IN (
    '4400-1', '5100-1', '4400-2', '5100-2',
    '4400-3', '5100-3', '4400-4', '5100-4'
);
