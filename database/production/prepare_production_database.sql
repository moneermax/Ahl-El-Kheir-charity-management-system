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

-- Development/test family documents and banking records.
TRUNCATE TABLE `family_documents`;
TRUNCATE TABLE `family_bank_accounts`;

-- Development/test orphan-group and disbursement workflow.
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

-- Development/test project subsystem data.
TRUNCATE TABLE `project_budget_lines`;
TRUNCATE TABLE `project_budgets`;
TRUNCATE TABLE `project_funding_allocations`;
TRUNCATE TABLE `project_expenses`;
TRUNCATE TABLE `project_expense_approvals`;
TRUNCATE TABLE `project_labor_comments`;
TRUNCATE TABLE `project_labor_helpers`;
TRUNCATE TABLE `project_milestones`;
TRUNCATE TABLE `project_progress_updates`;
TRUNCATE TABLE `project_status_history`;
TRUNCATE TABLE `project_supervisor_assignments`;
TRUNCATE TABLE `project_team`;
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

-- Remove only project-created test chart-of-account rows.
-- Core production accounts remain intact.
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

-- Verification: reference/configuration data.
SELECT
    'REFERENCE DATA PRESERVED' AS section,
    (SELECT COUNT(*) FROM `roles`) AS roles_count,
    (SELECT COUNT(*) FROM `departments`) AS departments_count,
    (SELECT COUNT(*) FROM `currencies`) AS currencies_count,
    (SELECT COUNT(*) FROM `letters`) AS letters_count,
    (SELECT COUNT(*) FROM `medical_needs`) AS medical_needs_count,
    (SELECT COUNT(*) FROM `settings`) AS settings_count,
    (SELECT COUNT(*) FROM `accounts`) AS accounts_count;

-- Verification: selected test areas should now be empty.
SELECT
    'TEST DATA CLEARED' AS section,
    (SELECT COUNT(*) FROM `orphan_groups`) AS orphan_groups,
    (SELECT COUNT(*) FROM `group_children`) AS group_children,
    (SELECT COUNT(*) FROM `nanny_group_assignments`) AS nanny_group_assignments,
    (SELECT COUNT(*) FROM `monthly_disbursements`) AS monthly_disbursements,
    (SELECT COUNT(*) FROM `disbursement_items`) AS disbursement_items,
    (SELECT COUNT(*) FROM `sponsor_payments`) AS sponsor_payments,
    (SELECT COUNT(*) FROM `transactions`) AS transactions,
    (SELECT COUNT(*) FROM `journal_entries`) AS journal_entries,
    (SELECT COUNT(*) FROM `journal_lines`) AS journal_lines,
    (SELECT COUNT(*) FROM `other_projects`) AS other_projects,
    (SELECT COUNT(*) FROM `messages`) AS messages,
    (SELECT COUNT(*) FROM `message_attachments`) AS message_attachments,
    (SELECT COUNT(*) FROM `audit_log`) AS audit_log,
    (SELECT COUNT(*) FROM `family_documents`) AS family_documents;

SELECT
    'PROJECT TEST ACCOUNTS REMAINING (MUST BE 0)' AS check_name,
    COUNT(*) AS remaining_count
FROM `accounts`
WHERE `code` IN (
    '4400-1', '5100-1', '4400-2', '5100-2',
    '4400-3', '5100-3', '4400-4', '5100-4'
);
