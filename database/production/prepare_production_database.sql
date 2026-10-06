-- ============================================================
-- Ahl El Kheir Charity Management System
-- PRODUCTION DATABASE PREPARATION / TEST-DATA CLEANUP
-- Baseline: AhlElKheir_dev_backup_2026-09-04.sql
-- MariaDB 10.4+
--
-- PURPOSE:
--   Remove development/test operational data while preserving:
--     * users / employees
--     * families / family children
--     * sponsors
--     * sponsorships and sponsorship amounts
--     * sponsor-supervisor assignment history
--     * supervisor letter assignments and their history
--     * reference/configuration data
--
-- IMPORTANT:
--   1. Make a FULL backup immediately before running this script.
--   2. Run this against the restored development backup first.
--   3. This script intentionally does NOT drop tables.
--   4. This script clears database records only. It does NOT delete
--      physical files from storage/.
--   5. The cleanup list below is aligned with the current schema,
--      including salary-advance, Fina, and newer project workflow data.
-- ============================================================

USE `ahl_el_kheir`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- Development/test HR and workflow records. Employees are preserved.
TRUNCATE TABLE `attendance`;
TRUNCATE TABLE `contracts`;
TRUNCATE TABLE `leaves`;
TRUNCATE TABLE `payroll`;
TRUNCATE TABLE `performance_reviews`;
TRUNCATE TABLE `supervisor_leaves`;
TRUNCATE TABLE `suspension_cases`;
TRUNCATE TABLE `accountant_nanny_assignments`;
TRUNCATE TABLE `nanny_family_verifications`;
TRUNCATE TABLE `verification_audit_log`;
TRUNCATE TABLE `user_sessions`;

-- Development/test salary-advance operational records.
-- Policy versions are configuration/history and are intentionally preserved.
TRUNCATE TABLE `hr_salary_advance_documents`;
TRUNCATE TABLE `hr_salary_advance_direct_repayment_documents`;
TRUNCATE TABLE `hr_salary_advance_direct_repayments`;
TRUNCATE TABLE `hr_salary_advance_payroll_repayments`;
TRUNCATE TABLE `hr_salary_advance_repayment_schedule`;
TRUNCATE TABLE `hr_salary_advance_waiver_items`;
TRUNCATE TABLE `hr_salary_advance_waiver_decisions`;
TRUNCATE TABLE `hr_salary_advance_requests`;

-- Development/test family documents and banking records.
TRUNCATE TABLE `family_documents`;
TRUNCATE TABLE `family_bank_accounts`;

-- Development/test orphan-group and disbursement workflow.
TRUNCATE TABLE `returned_disbursement_reissues`;
TRUNCATE TABLE `disbursement_items`;
TRUNCATE TABLE `monthly_disbursements`;
TRUNCATE TABLE `group_children`;
TRUNCATE TABLE `nanny_group_assignments`;
TRUNCATE TABLE `group_workflow_audit_log`;
TRUNCATE TABLE `orphan_groups`;

-- Development/test accounting activity.
TRUNCATE TABLE `journal_lines`;
TRUNCATE TABLE `journal_entries`;
TRUNCATE TABLE `sponsor_payments`;
TRUNCATE TABLE `transactions`;
TRUNCATE TABLE `vouchers`;

-- Development/test Fina operational data.
-- Fina source records and collections are operational records, not
-- organization reference/configuration data. Core chart accounts remain.
TRUNCATE TABLE `fina_settlement_allocations`;
TRUNCATE TABLE `fina_settlements`;
TRUNCATE TABLE `fina_collections`;
TRUNCATE TABLE `fina_sources`;

-- Development/test project subsystem data.
TRUNCATE TABLE `project_budget_lines`;
TRUNCATE TABLE `project_budgets`;
TRUNCATE TABLE `project_funding_allocations`;
TRUNCATE TABLE `project_funding_returns`;
TRUNCATE TABLE `project_expenses`;
TRUNCATE TABLE `project_expense_approvals`;
TRUNCATE TABLE `project_labor_comments`;
TRUNCATE TABLE `project_labor_helpers`;
TRUNCATE TABLE `project_milestones`;
TRUNCATE TABLE `project_progress_updates`;
TRUNCATE TABLE `project_status_history`;
TRUNCATE TABLE `project_supervisor_assignments`;
TRUNCATE TABLE `project_team`;
TRUNCATE TABLE `project_partners`;
TRUNCATE TABLE `project_payment_evidence`;
TRUNCATE TABLE `project_documents`;
TRUNCATE TABLE `project_beneficiary_records`;
TRUNCATE TABLE `project_beneficiaries`;
TRUNCATE TABLE `project_lifecycle`;
TRUNCATE TABLE `project_approval`;
TRUNCATE TABLE `project_details`;
TRUNCATE TABLE `other_projects`;

-- Development/test sponsor workflow and recovery data.
TRUNCATE TABLE `sponsor_import_exceptions`;
TRUNCATE TABLE `sponsor_requests`;
TRUNCATE TABLE `winback_contacts`;
TRUNCATE TABLE `winback_campaigns`;
TRUNCATE TABLE `password_recovery_requests`;

-- Development/test internal messaging data.
TRUNCATE TABLE `message_attachments`;
TRUNCATE TABLE `message_reads`;
TRUNCATE TABLE `messages`;

-- Development-generated notifications and audit records.
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `audit_log`;

-- Remove project-created test chart-of-account rows.
-- Core production accounts (including 1100/1200/1300/1410/1401/2300,
-- and the base 4400/5100 accounts) remain intact.
-- All project subaccounts use the 4400-N / 5100-N pattern and belong to
-- the project test data being removed by this preparation script.
DELETE FROM `accounts`
WHERE `code` REGEXP '^(4400|5100)-[0-9]+$';

-- Keep the historical baseline expected by the production seed.
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

-- Verification: reference/configuration data.
-- Salary-advance policy versions are configuration/history and are also
-- deliberately preserved by this script.
SELECT
    'REFERENCE DATA PRESERVED' AS section,
    (SELECT COUNT(*) FROM `roles`) AS roles_count,
    (SELECT COUNT(*) FROM `departments`) AS departments_count,
    (SELECT COUNT(*) FROM `currencies`) AS currencies_count,
    (SELECT COUNT(*) FROM `letters`) AS letters_count,
    (SELECT COUNT(*) FROM `medical_needs`) AS medical_needs_count,
    (SELECT COUNT(*) FROM `settings`) AS settings_count,
    (SELECT COUNT(*) FROM `accounts`) AS accounts_count,
    (SELECT COUNT(*) FROM `hr_salary_advance_policy_versions`) AS salary_advance_policy_versions_count;

-- Verification: selected test areas should now be empty.
SELECT
    'TEST DATA CLEARED' AS section,
    (SELECT COUNT(*) FROM `orphan_groups`) AS orphan_groups,
    (SELECT COUNT(*) FROM `group_children`) AS group_children,
    (SELECT COUNT(*) FROM `nanny_group_assignments`) AS nanny_group_assignments,
    (SELECT COUNT(*) FROM `monthly_disbursements`) AS monthly_disbursements,
    (SELECT COUNT(*) FROM `disbursement_items`) AS disbursement_items,
    (SELECT COUNT(*) FROM `returned_disbursement_reissues`) AS returned_disbursement_reissues,
    (SELECT COUNT(*) FROM `sponsor_payments`) AS sponsor_payments,
    (SELECT COUNT(*) FROM `transactions`) AS transactions,
    (SELECT COUNT(*) FROM `journal_entries`) AS journal_entries,
    (SELECT COUNT(*) FROM `journal_lines`) AS journal_lines,
    (SELECT COUNT(*) FROM `fina_sources`) AS fina_sources,
    (SELECT COUNT(*) FROM `fina_collections`) AS fina_collections,
    (SELECT COUNT(*) FROM `fina_settlements`) AS fina_settlements,
    (SELECT COUNT(*) FROM `fina_settlement_allocations`) AS fina_settlement_allocations,
    (SELECT COUNT(*) FROM `hr_salary_advance_requests`) AS salary_advance_requests,
    (SELECT COUNT(*) FROM `hr_salary_advance_documents`) AS salary_advance_documents,
    (SELECT COUNT(*) FROM `hr_salary_advance_repayment_schedule`) AS salary_advance_repayment_schedule,
    (SELECT COUNT(*) FROM `hr_salary_advance_payroll_repayments`) AS salary_advance_payroll_repayments,
    (SELECT COUNT(*) FROM `hr_salary_advance_direct_repayments`) AS salary_advance_direct_repayments,
    (SELECT COUNT(*) FROM `hr_salary_advance_direct_repayment_documents`) AS salary_advance_direct_repayment_documents,
    (SELECT COUNT(*) FROM `hr_salary_advance_waiver_decisions`) AS salary_advance_waiver_decisions,
    (SELECT COUNT(*) FROM `hr_salary_advance_waiver_items`) AS salary_advance_waiver_items,
    (SELECT COUNT(*) FROM `other_projects`) AS other_projects,
    (SELECT COUNT(*) FROM `project_funding_allocations`) AS project_funding_allocations,
    (SELECT COUNT(*) FROM `project_funding_returns`) AS project_funding_returns,
    (SELECT COUNT(*) FROM `project_payment_evidence`) AS project_payment_evidence,
    (SELECT COUNT(*) FROM `messages`) AS messages,
    (SELECT COUNT(*) FROM `message_attachments`) AS message_attachments,
    (SELECT COUNT(*) FROM `notifications`) AS notifications,
    (SELECT COUNT(*) FROM `audit_log`) AS audit_log,
    (SELECT COUNT(*) FROM `family_documents`) AS family_documents,
    (SELECT COUNT(*) FROM `user_sessions`) AS user_sessions;

SELECT
    'PROJECT TEST ACCOUNTS REMAINING (MUST BE 0)' AS check_name,
    COUNT(*) AS remaining_count
FROM `accounts`
WHERE `code` REGEXP '^(4400|5100)-[0-9]+$';
