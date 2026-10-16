-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: ahl_el_kheir
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accountant_nanny_assignments`
--

DROP TABLE IF EXISTS `accountant_nanny_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accountant_nanny_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `accountant_id` int(10) unsigned NOT NULL,
  `nanny_id` int(10) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acct_nanny` (`accountant_id`,`nanny_id`),
  KEY `nanny_id` (`nanny_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `accountant_nanny_assignments_ibfk_1` FOREIGN KEY (`accountant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accountant_nanny_assignments_ibfk_2` FOREIGN KEY (`nanny_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accountant_nanny_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `accounting_admin_fee_policies`
--

DROP TABLE IF EXISTS `accounting_admin_fee_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_admin_fee_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(150) NOT NULL DEFAULT 'سياسة الرسوم الإدارية',
  `method` enum('none','fixed','percentage') NOT NULL DEFAULT 'none',
  `value` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency_code` char(3) NOT NULL DEFAULT 'SDG',
  `effective_from` date NOT NULL,
  `status` enum('draft','active','superseded') NOT NULL DEFAULT 'draft',
  `opening_balance_entry_id` int(10) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_afp_status_effective` (`status`,`effective_from`),
  KEY `idx_afp_opening_balance` (`opening_balance_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) DEFAULT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_code` (`code`),
  KEY `idx_accounts_type` (`account_type`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `work_mode` enum('remote','onsite') NOT NULL DEFAULT 'remote',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address for remote workers',
  `location_note` varchar(255) DEFAULT NULL COMMENT 'Location note (e.g., home, office)',
  `status` enum('present','absent','late','half_day','on_leave','remote_work') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_employee_date` (`employee_id`,`date`),
  KEY `idx_attendance_date` (`date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_user_id` (`user_id`),
  KEY `idx_audit_log_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_log_action` (`action`),
  KEY `idx_audit_log_created_at` (`created_at`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2454 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `contract_type` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `salary` decimal(10,2) NOT NULL,
  `terms` text DEFAULT NULL,
  `status` enum('active','expired','terminated') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contract_employee` (`employee_id`),
  KEY `idx_contract_status` (`status`),
  CONSTRAINT `fk_contract_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `code` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name_en` varchar(50) NOT NULL,
  `name_ar` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disbursement_items`
--

DROP TABLE IF EXISTS `disbursement_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disbursement_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_id` int(10) unsigned NOT NULL,
  `child_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `family_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','return_requested','returned') NOT NULL DEFAULT 'pending',
  `confirmed_at` datetime DEFAULT NULL,
  `is_orphan_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Nanny confirms orphan still eligible/alive',
  `is_mother_contact_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Nanny confirms mother phone is active',
  `is_bank_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Nanny confirms bank account/payment method is valid',
  `verification_notes` text DEFAULT NULL,
  `verified_by_user_id` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `receipt_file_path` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `return_reason` text DEFAULT NULL,
  `return_requested_at` datetime DEFAULT NULL,
  `return_requested_by_user_id` int(10) unsigned DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `returned_by_user_id` int(10) unsigned DEFAULT NULL,
  `reversal_journal_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `disbursement_id` (`disbursement_id`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `disbursement_items_ibfk_1` FOREIGN KEY (`disbursement_id`) REFERENCES `monthly_disbursements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disbursement_items_ibfk_2` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'Links to users table for system access',
  `full_name` varchar(150) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `employee_code` varchar(50) NOT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `position` varchar(100) NOT NULL,
  `employment_type` enum('full_time','part_time','contract','volunteer') NOT NULL DEFAULT 'full_time',
  `work_mode` enum('remote','onsite','hybrid') NOT NULL DEFAULT 'remote',
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bank_account` varchar(100) DEFAULT NULL,
  `contract_file_path` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded contract file',
  `status` enum('active','on_leave','terminated','suspended') NOT NULL DEFAULT 'active',
  `employment_state_id` int(10) unsigned DEFAULT NULL,
  `employment_state_changed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_code` (`employee_code`),
  UNIQUE KEY `uq_employee_user` (`user_id`),
  KEY `idx_employee_status` (`status`),
  KEY `fk_employee_created_by` (`created_by`),
  KEY `idx_employees_employment_state` (`employment_state_id`),
  CONSTRAINT `fk_employee_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `families`
--

DROP TABLE IF EXISTS `families`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `families` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mother_name` varchar(150) NOT NULL,
  `mother_phone` varchar(30) DEFAULT NULL,
  `mother_job` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `children_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `monthly_need_amount` decimal(12,2) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `status` enum('pending','active','paused','completed','archived','inactive','closed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `family_code` varchar(50) DEFAULT NULL,
  `mother_national_id` varchar(20) DEFAULT NULL,
  `mother_birth_date` date DEFAULT NULL,
  `mother_alt_phone` varchar(30) DEFAULT NULL,
  `mother_workplace` varchar(150) DEFAULT NULL,
  `legacy_mother_first_letter` char(1) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `closure_date` date DEFAULT NULL,
  `closure_reason` text DEFAULT NULL,
  `supervisor_id` int(10) unsigned DEFAULT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `nanny_id` int(10) unsigned DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `bank_account_holder` varchar(150) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_death_date` date DEFAULT NULL,
  `father_death_cause` varchar(150) DEFAULT NULL,
  `mother_marital_status` varchar(30) DEFAULT 'أرملة',
  `mother_bank_name` varchar(150) DEFAULT NULL,
  `mother_bank_account` varchar(100) DEFAULT NULL,
  `mother_bank_branch` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_families_family_code` (`family_code`),
  KEY `idx_families_status` (`status`),
  KEY `idx_families_created_by` (`created_by`),
  KEY `idx_families_mother_phone` (`mother_phone`),
  KEY `idx_families_family_code` (`family_code`),
  KEY `idx_families_registration_number` (`registration_number`),
  KEY `idx_families_supervisor_id` (`supervisor_id`),
  KEY `idx_families_assigned_by` (`assigned_by`),
  KEY `idx_families_updated_by` (`updated_by`),
  CONSTRAINT `fk_families_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `family_bank_accounts`
--

DROP TABLE IF EXISTS `family_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int(10) unsigned NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_holder` varchar(150) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `family_id` (`family_id`),
  CONSTRAINT `family_bank_accounts_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `family_children`
--

DROP TABLE IF EXISTS `family_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_children` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int(10) unsigned NOT NULL,
  `child_name` varchar(150) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','unknown') NOT NULL DEFAULT 'unknown',
  `education_level` varchar(50) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `father_name` varchar(150) DEFAULT NULL,
  `legacy_gender` varchar(20) DEFAULT NULL,
  `medical_need` varchar(150) DEFAULT NULL,
  `medical_need_id` int(10) unsigned DEFAULT NULL,
  `has_medical_needs` tinyint(1) NOT NULL DEFAULT 0,
  `legacy_status` varchar(20) DEFAULT NULL,
  `disqualified_at` date DEFAULT NULL,
  `disqualification_reason` varchar(100) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `nationality` varchar(50) NOT NULL DEFAULT 'سودانية',
  `health_status` varchar(150) DEFAULT NULL,
  `health_status_other` varchar(150) DEFAULT NULL,
  `psychological_state` varchar(150) DEFAULT NULL,
  `psychological_state_other` varchar(150) DEFAULT NULL,
  `guardian_name` varchar(150) DEFAULT NULL,
  `guardian_relationship` varchar(60) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `form_serial` varchar(20) DEFAULT NULL,
  `application_date` date DEFAULT NULL COMMENT 'Date the orphan was added to the waiting list',
  `is_critical` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Priority Override',
  `match_status` enum('unmatched','waiting_list','lost_sponsor','matched') NOT NULL DEFAULT 'unmatched' COMMENT 'State used by Auto-Matching',
  `monthly_sponsorship_value` decimal(12,2) DEFAULT NULL,
  `extra_allowance` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_family_children_form_serial` (`form_serial`),
  KEY `idx_family_children_family_id` (`family_id`),
  KEY `idx_family_children_father_name` (`father_name`),
  KEY `idx_family_children_medical_need_id` (`medical_need_id`),
  KEY `idx_family_children_legacy_status` (`legacy_status`),
  CONSTRAINT `fk_family_children_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5026 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `family_documents`
--

DROP TABLE IF EXISTS `family_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int(10) unsigned NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `doc_type` varchar(50) NOT NULL DEFAULT 'other',
  PRIMARY KEY (`id`),
  KEY `family_id` (`family_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `family_documents_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `family_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fina_collections`
--

DROP TABLE IF EXISTS `fina_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fina_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fina_source_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `payment_method` varchar(32) NOT NULL,
  `collection_date` date NOT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `purpose_note` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','returned') NOT NULL DEFAULT 'pending',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `return_note` text DEFAULT NULL,
  `accounting_journal_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fina_collections_source` (`fina_source_id`),
  KEY `idx_fina_collections_status` (`status`),
  KEY `idx_fina_collections_date` (`collection_date`),
  KEY `idx_fina_collections_journal` (`accounting_journal_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fina_settlement_allocations`
--

DROP TABLE IF EXISTS `fina_settlement_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fina_settlement_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_id` bigint(20) unsigned NOT NULL,
  `fina_collection_id` bigint(20) unsigned NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fina_settlement_collection` (`settlement_id`,`fina_collection_id`),
  KEY `idx_fina_allocations_collection` (`fina_collection_id`),
  KEY `idx_fina_allocations_settlement` (`settlement_id`),
  CONSTRAINT `fk_fina_allocations_collection` FOREIGN KEY (`fina_collection_id`) REFERENCES `fina_collections` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_fina_allocations_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `fina_settlements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fina_settlements`
--

DROP TABLE IF EXISTS `fina_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fina_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_code` varchar(50) NOT NULL,
  `settlement_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `payment_method` varchar(32) NOT NULL,
  `remitting_account_id` int(10) unsigned DEFAULT NULL,
  `transfer_reference` varchar(150) DEFAULT NULL,
  `evidence_path` varchar(500) DEFAULT NULL,
  `status` enum('draft','approved','transferred','reconciled','closed','cancelled') NOT NULL DEFAULT 'draft',
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `settlement_journal_id` int(10) unsigned DEFAULT NULL,
  `reconciliation_note` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `transferred_by` int(10) unsigned DEFAULT NULL,
  `transferred_at` datetime DEFAULT NULL,
  `reconciled_by` int(10) unsigned DEFAULT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fina_settlements_code` (`settlement_code`),
  KEY `idx_fina_settlements_date` (`settlement_date`),
  KEY `idx_fina_settlements_status` (`status`),
  KEY `idx_fina_settlements_currency` (`currency_code`),
  KEY `idx_fina_settlements_account` (`remitting_account_id`),
  KEY `idx_fina_settlements_journal` (`settlement_journal_id`),
  CONSTRAINT `fk_fina_settlements_account` FOREIGN KEY (`remitting_account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_fina_settlements_journal` FOREIGN KEY (`settlement_journal_id`) REFERENCES `journal_entries` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fina_sources`
--

DROP TABLE IF EXISTS `fina_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fina_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_type` enum('sponsor','person','organization','other') NOT NULL DEFAULT 'person',
  `sponsor_id` int(10) unsigned DEFAULT NULL,
  `source_name` varchar(255) DEFAULT NULL,
  `source_phone` varchar(100) DEFAULT NULL,
  `source_alt_phone` varchar(100) DEFAULT NULL,
  `source_email` varchar(255) DEFAULT NULL,
  `source_address` text DEFAULT NULL,
  `source_id_number` varchar(150) DEFAULT NULL,
  `source_reference` varchar(150) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `source_details` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fina_sources_sponsor` (`sponsor_id`),
  KEY `idx_fina_sources_type` (`source_type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_children`
--

DROP TABLE IF EXISTS `group_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_children` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `child_id` int(10) unsigned NOT NULL,
  `joined_date` date NOT NULL,
  `left_date` date DEFAULT NULL COMMENT 'NULL means currently in group',
  `added_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_child` (`group_id`,`child_id`),
  KEY `idx_child_id` (`child_id`),
  KEY `fk_gc_added_by` (`added_by`),
  CONSTRAINT `fk_gc_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gc_child` FOREIGN KEY (`child_id`) REFERENCES `family_children` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gc_group` FOREIGN KEY (`group_id`) REFERENCES `orphan_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_workflow_audit_log`
--

DROP TABLE IF EXISTS `group_workflow_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_workflow_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) DEFAULT NULL,
  `family_id` int(11) DEFAULT NULL,
  `month` varchar(7) DEFAULT NULL,
  `action_type` enum('group_submitted','group_approved','group_transferred','group_reopened','family_reopened','batch_created') NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_role` varchar(50) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gwal_group` (`group_id`),
  KEY `idx_gwal_family` (`family_id`),
  KEY `idx_gwal_action` (`action_type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_attendance_policy_versions`
--

DROP TABLE IF EXISTS `hr_attendance_policy_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_attendance_policy_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `version_no` int(10) unsigned NOT NULL,
  `policy_name` varchar(150) NOT NULL,
  `effective_from` date NOT NULL,
  `working_start_time` time NOT NULL DEFAULT '07:00:00',
  `working_end_time` time NOT NULL DEFAULT '16:00:00',
  `working_days` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `attendance_cutoff_time` time NOT NULL DEFAULT '16:00:00',
  `absence_finalization_time` time NOT NULL DEFAULT '16:00:00',
  `auto_login_attendance` tinyint(1) NOT NULL DEFAULT 1,
  `auto_absence_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `default_work_mode` enum('remote','onsite','hybrid') NOT NULL DEFAULT 'remote',
  `notes` varchar(2000) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_attendance_policy_version` (`version_no`),
  UNIQUE KEY `uq_hr_attendance_policy_effective` (`effective_from`),
  KEY `idx_hr_attendance_policy_effective` (`effective_from`),
  KEY `idx_hr_attendance_policy_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_employee_contracts`
--

DROP TABLE IF EXISTS `hr_employee_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `contract_number` varchar(100) DEFAULT NULL,
  `contract_type` enum('permanent','fixed_term','part_time','temporary','internship','other') NOT NULL DEFAULT 'permanent',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','expired','terminated','cancelled') NOT NULL DEFAULT 'draft',
  `basic_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `salary_currency` char(3) NOT NULL DEFAULT 'SDG',
  `pay_frequency` enum('monthly','weekly','daily','hourly') NOT NULL DEFAULT 'monthly',
  `working_hours_per_week` decimal(5,2) DEFAULT NULL,
  `probation_end_date` date DEFAULT NULL,
  `contract_file_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_contract_number` (`contract_number`),
  KEY `idx_hr_contract_employee_dates` (`employee_id`,`start_date`,`end_date`),
  KEY `idx_hr_contract_employee_status` (`employee_id`,`status`),
  KEY `idx_hr_contract_active_dates` (`status`,`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_employee_salary_history`
--

DROP TABLE IF EXISTS `hr_employee_salary_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_salary_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `salary_currency` char(3) NOT NULL DEFAULT 'SDG',
  `pay_frequency` enum('monthly','weekly','daily','hourly') NOT NULL DEFAULT 'monthly',
  `reason` enum('initial','annual_increase','promotion','adjustment','contract_change','correction','other') NOT NULL DEFAULT 'initial',
  `notes` varchar(500) DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hr_salary_employee_dates` (`employee_id`,`effective_from`,`effective_to`),
  KEY `idx_hr_salary_contract` (`contract_id`),
  KEY `idx_hr_salary_current` (`employee_id`,`effective_to`)
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_employee_state_history`
--

DROP TABLE IF EXISTS `hr_employee_state_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_state_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `employment_state_id` int(10) unsigned NOT NULL,
  `effective_from` datetime NOT NULL,
  `effective_to` datetime DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hr_state_history_employee_dates` (`employee_id`,`effective_from`),
  KEY `idx_hr_state_history_current` (`employee_id`,`effective_to`),
  KEY `idx_hr_state_history_state` (`employment_state_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_employment_states`
--

DROP TABLE IF EXISTS `hr_employment_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employment_states` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `category` enum('working','temporary_unavailable','separation') NOT NULL DEFAULT 'working',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_employment_states_code` (`code`),
  KEY `idx_hr_employment_states_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_adjustments`
--

DROP TABLE IF EXISTS `hr_incentive_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `award_id` int(10) unsigned NOT NULL,
  `adjustment_type` enum('correction','reversal') NOT NULL,
  `amount_delta` decimal(12,2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `reason` varchar(2000) NOT NULL,
  `requested_by_user_id` int(10) unsigned NOT NULL,
  `reviewed_by_user_id` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `status` enum('pending_review','approved','rejected','posted') NOT NULL DEFAULT 'pending_review',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hr_incentive_adjustment_award` (`award_id`,`status`),
  KEY `idx_hr_incentive_adjustment_reviewer` (`reviewed_by_user_id`),
  KEY `idx_hr_incentive_adjustment_journal` (`journal_entry_id`),
  CONSTRAINT `fk_hr_incentive_adjustment_award` FOREIGN KEY (`award_id`) REFERENCES `hr_incentive_awards` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_awards`
--

DROP TABLE IF EXISTS `hr_incentive_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_awards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `recipient_user_id` int(10) unsigned NOT NULL,
  `project_allocation_id` int(10) unsigned DEFAULT NULL,
  `reward_amount` decimal(12,2) NOT NULL,
  `reward_currency_code` char(3) NOT NULL,
  `salary_currency_code` char(3) DEFAULT NULL,
  `status` enum('earned','held_currency','scheduled','paid','rejected','adjustment_pending','adjusted') NOT NULL DEFAULT 'earned',
  `hold_reason` varchar(1000) DEFAULT NULL,
  `payroll_id` int(10) unsigned DEFAULT NULL,
  `recognition_journal_entry_id` int(10) unsigned DEFAULT NULL,
  `settlement_journal_entry_id` int(10) unsigned DEFAULT NULL,
  `earned_at` datetime NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_award_event_employee` (`event_id`,`employee_id`),
  UNIQUE KEY `uq_hr_incentive_award_project_allocation` (`project_allocation_id`),
  KEY `idx_hr_incentive_award_employee_status` (`employee_id`,`status`,`earned_at`),
  KEY `idx_hr_incentive_award_user_status` (`recipient_user_id`,`status`,`earned_at`),
  KEY `idx_hr_incentive_award_payroll` (`payroll_id`),
  KEY `idx_hr_incentive_award_recognition_journal` (`recognition_journal_entry_id`),
  KEY `idx_hr_incentive_award_settlement_journal` (`settlement_journal_entry_id`),
  CONSTRAINT `fk_hr_incentive_award_event` FOREIGN KEY (`event_id`) REFERENCES `hr_incentive_events` (`id`),
  CONSTRAINT `fk_hr_incentive_award_project_allocation` FOREIGN KEY (`project_allocation_id`) REFERENCES `hr_incentive_project_allocations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_events`
--

DROP TABLE IF EXISTS `hr_incentive_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(190) NOT NULL,
  `source_table` varchar(80) NOT NULL,
  `source_record_id` bigint(20) unsigned NOT NULL,
  `incentive_type` varchar(80) NOT NULL,
  `policy_version_id` int(10) unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `source_amount` decimal(12,2) DEFAULT NULL,
  `source_currency_code` char(3) DEFAULT NULL,
  `recipient_user_id` int(10) unsigned DEFAULT NULL,
  `recipient_employee_id` int(10) unsigned DEFAULT NULL,
  `status` enum('pending_verification','verified','rejected','held') NOT NULL DEFAULT 'pending_verification',
  `verification_note` varchar(2000) DEFAULT NULL,
  `verified_by_user_id` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_event_idempotency` (`idempotency_key`),
  KEY `idx_hr_incentive_event_source` (`source_table`,`source_record_id`),
  KEY `idx_hr_incentive_event_type_status` (`incentive_type`,`status`,`occurred_at`),
  KEY `idx_hr_incentive_event_policy` (`policy_version_id`),
  KEY `idx_hr_incentive_event_recipient` (`recipient_user_id`,`recipient_employee_id`,`status`),
  CONSTRAINT `fk_hr_incentive_event_policy` FOREIGN KEY (`policy_version_id`) REFERENCES `hr_incentive_policy_versions` (`id`),
  CONSTRAINT `fk_hr_incentive_event_type` FOREIGN KEY (`incentive_type`) REFERENCES `hr_incentive_types` (`type_code`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_notification_outbox`
--

DROP TABLE IF EXISTS `hr_incentive_notification_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_notification_outbox` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `award_id` int(10) unsigned NOT NULL,
  `recipient_user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','sent') NOT NULL DEFAULT 'pending',
  `notification_id` int(10) unsigned DEFAULT NULL,
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `last_error` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_notification_award` (`award_id`),
  KEY `idx_hr_incentive_notification_pending` (`status`,`id`),
  KEY `idx_hr_incentive_notification_recipient` (`recipient_user_id`),
  CONSTRAINT `fk_hr_incentive_notification_award` FOREIGN KEY (`award_id`) REFERENCES `hr_incentive_awards` (`id`),
  CONSTRAINT `fk_hr_incentive_notification_user` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_policy_approval_history`
--

DROP TABLE IF EXISTS `hr_incentive_policy_approval_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_policy_approval_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_version_id` int(10) unsigned NOT NULL,
  `action` enum('submitted','approved','rejected','returned_to_draft') NOT NULL,
  `actor_user_id` int(10) unsigned NOT NULL,
  `reason` varchar(2000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hr_incentive_approval_policy_time` (`policy_version_id`,`created_at`,`id`),
  KEY `idx_hr_incentive_approval_actor` (`actor_user_id`),
  CONSTRAINT `fk_hr_incentive_approval_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_hr_incentive_approval_policy` FOREIGN KEY (`policy_version_id`) REFERENCES `hr_incentive_policy_versions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_policy_custom_fields`
--

DROP TABLE IF EXISTS `hr_incentive_policy_custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_policy_custom_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_version_id` int(10) unsigned NOT NULL,
  `field_key` varchar(80) NOT NULL,
  `label_ar` varchar(190) NOT NULL,
  `label_en` varchar(190) DEFAULT NULL,
  `field_type` enum('short_text','long_text','number','date','boolean','select') NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `options_json` text DEFAULT NULL,
  `field_value` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `created_by_user_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_policy_custom_field_key` (`policy_version_id`,`field_key`),
  KEY `idx_hr_incentive_policy_custom_field_order` (`policy_version_id`,`sort_order`,`id`),
  CONSTRAINT `fk_hr_incentive_custom_field_policy` FOREIGN KEY (`policy_version_id`) REFERENCES `hr_incentive_policy_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_policy_rules`
--

DROP TABLE IF EXISTS `hr_incentive_policy_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_policy_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_version_id` int(10) unsigned NOT NULL,
  `incentive_type` varchar(80) NOT NULL,
  `reward_amount` decimal(12,2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `rule_notes` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_rule_type` (`policy_version_id`,`incentive_type`),
  KEY `idx_hr_incentive_rule_currency` (`currency_code`),
  KEY `idx_hr_incentive_rule_type_catalog` (`incentive_type`),
  CONSTRAINT `fk_hr_incentive_rule_policy` FOREIGN KEY (`policy_version_id`) REFERENCES `hr_incentive_policy_versions` (`id`),
  CONSTRAINT `fk_hr_incentive_rule_type` FOREIGN KEY (`incentive_type`) REFERENCES `hr_incentive_types` (`type_code`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_policy_versions`
--

DROP TABLE IF EXISTS `hr_incentive_policy_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_policy_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `version_no` int(10) unsigned NOT NULL,
  `policy_name` varchar(150) NOT NULL,
  `status` enum('draft','pending_gm','approved','active','retired') NOT NULL DEFAULT 'draft',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `agreement_recorded_at` datetime DEFAULT NULL,
  `agreement_notes` text DEFAULT NULL,
  `created_by_user_id` int(10) unsigned NOT NULL,
  `activated_by_user_id` int(10) unsigned DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_policy_version` (`version_no`),
  KEY `idx_hr_incentive_policy_effective` (`status`,`effective_from`,`effective_to`),
  KEY `idx_hr_incentive_policy_created_by` (`created_by_user_id`),
  KEY `idx_hr_incentive_policy_activated_by` (`activated_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_project_allocations`
--

DROP TABLE IF EXISTS `hr_incentive_project_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_project_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `recipient_user_id` int(10) unsigned NOT NULL,
  `allocation_percent` decimal(5,2) NOT NULL,
  `reward_amount` decimal(12,2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `approved_by_user_id` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `allocation_note` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_project_recipient` (`event_id`,`employee_id`),
  KEY `idx_hr_incentive_project_user` (`recipient_user_id`),
  KEY `idx_hr_incentive_project_approver` (`approved_by_user_id`),
  CONSTRAINT `fk_hr_incentive_project_event` FOREIGN KEY (`event_id`) REFERENCES `hr_incentive_events` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_incentive_types`
--

DROP TABLE IF EXISTS `hr_incentive_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_incentive_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_code` varchar(80) NOT NULL,
  `name_ar` varchar(190) NOT NULL,
  `name_en` varchar(190) NOT NULL,
  `qualification_mode` enum('source_event','manual_review') NOT NULL DEFAULT 'manual_review',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `created_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_incentive_type_code` (`type_code`),
  KEY `idx_hr_incentive_type_active_sort` (`is_active`,`sort_order`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_leave_returns`
--

DROP TABLE IF EXISTS `hr_leave_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_leave_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leave_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `return_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_leave_returns_leave` (`leave_id`),
  KEY `idx_hr_leave_returns_employee_date` (`employee_id`,`return_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_payroll_policy_versions`
--

DROP TABLE IF EXISTS `hr_payroll_policy_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll_policy_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_no` int(10) unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `absence_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `absence_deduction_percent` decimal(7,4) NOT NULL DEFAULT 100.0000,
  `unpaid_leave_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `unpaid_leave_deduction_percent` decimal(7,4) NOT NULL DEFAULT 100.0000,
  `paid_leave_deduction_percent` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `late_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `early_departure_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `overtime_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `overtime_multiplier` decimal(7,4) NOT NULL DEFAULT 1.0000,
  `daily_deduction_method` enum('monthly_salary_div_30') NOT NULL DEFAULT 'monthly_salary_div_30',
  `rounding_decimals` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_payroll_policy_version` (`version_no`),
  UNIQUE KEY `uq_hr_payroll_policy_effective_from` (`effective_from`),
  KEY `idx_hr_payroll_policy_effective` (`effective_from`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_payroll_reversals`
--

DROP TABLE IF EXISTS `hr_payroll_reversals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll_reversals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` int(10) unsigned NOT NULL,
  `original_entry_id` int(10) unsigned NOT NULL,
  `reversal_entry_id` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `reversed_by` int(10) unsigned DEFAULT NULL,
  `reversed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_payroll_reversal_payroll` (`payroll_id`),
  KEY `idx_hr_payroll_reversal_entry` (`reversal_entry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_direct_repayment_documents`
--

DROP TABLE IF EXISTS `hr_salary_advance_direct_repayment_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_direct_repayment_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `direct_repayment_id` int(10) unsigned NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_direct_repayment_document` (`direct_repayment_id`),
  KEY `idx_hr_salary_advance_direct_repayment_documents_uploader` (`uploaded_by`),
  CONSTRAINT `fk_hr_salary_advance_direct_repayment_documents_repayment` FOREIGN KEY (`direct_repayment_id`) REFERENCES `hr_salary_advance_direct_repayments` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_direct_repayments`
--

DROP TABLE IF EXISTS `hr_salary_advance_direct_repayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_direct_repayments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `salary_advance_request_id` int(10) unsigned NOT NULL,
  `repayment_amount` decimal(18,2) NOT NULL,
  `repayment_account_id` int(10) unsigned NOT NULL,
  `accounting_entry_id` int(10) unsigned NOT NULL,
  `repayment_reference` varchar(100) DEFAULT NULL,
  `repayment_date` date NOT NULL,
  `received_by` int(10) unsigned NOT NULL,
  `notes` varchar(2000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_direct_repayment_journal` (`accounting_entry_id`),
  KEY `idx_hr_salary_advance_direct_repayment_request` (`salary_advance_request_id`),
  KEY `idx_hr_salary_advance_direct_repayment_account` (`repayment_account_id`),
  KEY `idx_hr_salary_advance_direct_repayment_receiver` (`received_by`),
  KEY `idx_hr_salary_advance_direct_repayment_date` (`repayment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_documents`
--

DROP TABLE IF EXISTS `hr_salary_advance_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `salary_advance_request_id` int(10) unsigned NOT NULL,
  `document_type` enum('payment_receipt') NOT NULL DEFAULT 'payment_receipt',
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_document` (`salary_advance_request_id`,`document_type`),
  KEY `idx_hr_salary_advance_documents_uploader` (`uploaded_by`),
  CONSTRAINT `fk_hr_salary_advance_documents_request` FOREIGN KEY (`salary_advance_request_id`) REFERENCES `hr_salary_advance_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_payroll_repayments`
--

DROP TABLE IF EXISTS `hr_salary_advance_payroll_repayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_payroll_repayments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `salary_advance_request_id` int(10) unsigned NOT NULL,
  `repayment_schedule_id` int(10) unsigned DEFAULT NULL,
  `payroll_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `eligible_salary` decimal(18,2) NOT NULL DEFAULT 0.00,
  `maximum_allowed_deduction` decimal(18,2) DEFAULT NULL,
  `scheduled_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `actual_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `outcome` enum('applied','partial','skipped') NOT NULL,
  `outcome_reason` varchar(1000) DEFAULT NULL,
  `accounting_entry_id` int(10) unsigned DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_payroll_repayment` (`salary_advance_request_id`,`payroll_id`),
  KEY `idx_hr_salary_advance_payroll_repayment_schedule` (`repayment_schedule_id`),
  KEY `idx_hr_salary_advance_payroll_repayment_payroll` (`payroll_id`),
  KEY `idx_hr_salary_advance_payroll_repayment_employee` (`employee_id`),
  KEY `idx_hr_salary_advance_payroll_repayment_journal` (`accounting_entry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_policy_versions`
--

DROP TABLE IF EXISTS `hr_salary_advance_policy_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_policy_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `version_no` int(10) unsigned NOT NULL,
  `policy_name` varchar(150) NOT NULL,
  `effective_from` date NOT NULL,
  `notes` varchar(2000) DEFAULT NULL,
  `allow_any_request_amount` tinyint(1) NOT NULL DEFAULT 1,
  `minimum_request_amount` decimal(18,2) DEFAULT NULL,
  `maximum_request_amount` decimal(18,2) DEFAULT NULL,
  `allow_multiple_active_advances` tinyint(1) NOT NULL DEFAULT 0,
  `allow_fixed_monthly_repayment` tinyint(1) NOT NULL DEFAULT 1,
  `allow_full_eligible_salary_repayment` tinyint(1) NOT NULL DEFAULT 1,
  `allow_full_settlement_from_salary` tinyint(1) NOT NULL DEFAULT 1,
  `allow_direct_repayment` tinyint(1) NOT NULL DEFAULT 1,
  `allow_custom_repayment_terms` tinyint(1) NOT NULL DEFAULT 1,
  `maximum_monthly_deduction` decimal(18,2) DEFAULT NULL,
  `maximum_repayment_months` int(10) unsigned DEFAULT NULL,
  `allow_fm_repayment_rescheduling` tinyint(1) NOT NULL DEFAULT 0,
  `repayment_start_rule` enum('next_payroll','specified_month') NOT NULL DEFAULT 'next_payroll',
  `insufficient_salary_rule` enum('available_salary','skip_month') NOT NULL DEFAULT 'available_salary',
  `eligible_salary_basis` enum('net_before_advance','gross') NOT NULL DEFAULT 'net_before_advance',
  `minimum_service_days` int(10) unsigned NOT NULL DEFAULT 0,
  `probation_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `terminated_employee_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `require_accounting_verification` tinyint(1) NOT NULL DEFAULT 1,
  `allow_early_settlement` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_policy_version` (`version_no`),
  UNIQUE KEY `uq_hr_salary_advance_policy_effective` (`effective_from`),
  KEY `idx_hr_salary_advance_policy_effective` (`effective_from`),
  KEY `idx_hr_salary_advance_policy_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_repayment_plans`
--

DROP TABLE IF EXISTS `hr_salary_advance_repayment_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_repayment_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `salary_advance_request_id` int(10) unsigned NOT NULL,
  `plan_no` int(10) unsigned NOT NULL,
  `status` enum('active','superseded') NOT NULL DEFAULT 'active',
  `reason` varchar(1000) DEFAULT NULL,
  `outstanding_balance_at_creation` decimal(18,2) NOT NULL,
  `start_month` date NOT NULL,
  `maximum_repayment_months` int(10) unsigned DEFAULT NULL,
  `maximum_monthly_deduction` decimal(18,2) NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `activated_at` datetime DEFAULT NULL,
  `superseded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_plan_no` (`salary_advance_request_id`,`plan_no`),
  KEY `idx_hr_salary_advance_plan_active` (`salary_advance_request_id`,`status`),
  KEY `idx_hr_salary_advance_plan_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_repayment_schedule`
--

DROP TABLE IF EXISTS `hr_salary_advance_repayment_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_repayment_schedule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `salary_advance_request_id` int(10) unsigned NOT NULL,
  `repayment_plan_id` int(10) unsigned DEFAULT NULL,
  `installment_no` int(10) unsigned NOT NULL,
  `scheduled_month` date NOT NULL,
  `scheduled_amount` decimal(18,2) NOT NULL,
  `applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partial','paid','skipped') NOT NULL DEFAULT 'pending',
  `applied_payroll_id` int(10) unsigned DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  `skip_reason` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_schedule_plan_installment` (`salary_advance_request_id`,`repayment_plan_id`,`installment_no`),
  UNIQUE KEY `uq_hr_salary_advance_schedule_plan_month` (`salary_advance_request_id`,`repayment_plan_id`,`scheduled_month`),
  KEY `idx_hr_salary_advance_schedule_request_status` (`salary_advance_request_id`,`status`),
  KEY `idx_hr_salary_advance_schedule_payroll` (`applied_payroll_id`),
  KEY `idx_hr_salary_advance_schedule_plan` (`repayment_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_requests`
--

DROP TABLE IF EXISTS `hr_salary_advance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(40) NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `policy_version_id` int(10) unsigned NOT NULL,
  `requested_amount` decimal(18,2) NOT NULL,
  `requested_repayment_method` enum('fixed_monthly','full_eligible_salary','full_settlement','direct_repayment') NOT NULL,
  `requested_monthly_amount` decimal(18,2) DEFAULT NULL,
  `requested_start_month` date DEFAULT NULL,
  `request_reason` varchar(2000) DEFAULT NULL,
  `status` enum('submitted','fm_review','approved','disbursed','settled','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  `submitted_by` int(10) unsigned NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `fm_reviewed_by` int(10) unsigned DEFAULT NULL,
  `fm_reviewed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `fm_decision` varchar(20) DEFAULT NULL,
  `fm_rejection_reason` varchar(2000) DEFAULT NULL,
  `approved_amount` decimal(18,2) DEFAULT NULL,
  `approved_repayment_method` enum('fixed_monthly','full_eligible_salary','full_settlement','direct_repayment') DEFAULT NULL,
  `approved_monthly_amount` decimal(18,2) DEFAULT NULL,
  `approved_start_month` date DEFAULT NULL,
  `fm_customized` tinyint(1) NOT NULL DEFAULT 0,
  `fm_customization_reason` varchar(2000) DEFAULT NULL,
  `accounting_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `accounting_verified_by` int(10) unsigned DEFAULT NULL,
  `accounting_verified_at` datetime DEFAULT NULL,
  `accounting_rejection_reason` varchar(2000) DEFAULT NULL,
  `disbursed_by` int(10) unsigned DEFAULT NULL,
  `disbursed_at` datetime DEFAULT NULL,
  `disbursement_account_id` int(10) unsigned DEFAULT NULL,
  `disbursement_journal_entry_id` int(10) unsigned DEFAULT NULL,
  `disbursement_reference` varchar(100) DEFAULT NULL,
  `outstanding_balance` decimal(18,2) DEFAULT NULL,
  `settled_by` int(10) unsigned DEFAULT NULL,
  `settled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_request_no` (`request_no`),
  KEY `idx_hr_salary_advance_request_employee` (`employee_id`),
  KEY `idx_hr_salary_advance_request_policy` (`policy_version_id`),
  KEY `idx_hr_salary_advance_request_status` (`status`),
  KEY `idx_hr_salary_advance_request_submitted_by` (`submitted_by`),
  KEY `idx_hr_salary_advance_request_fm_reviewed_by` (`fm_reviewed_by`),
  KEY `idx_hr_salary_advance_request_fm_decision` (`fm_decision`),
  KEY `idx_hr_salary_advance_accounting_status` (`accounting_status`),
  KEY `idx_hr_salary_advance_disbursement_account` (`disbursement_account_id`),
  KEY `idx_hr_salary_advance_disbursement_journal` (`disbursement_journal_entry_id`),
  KEY `idx_hr_salary_advance_status_balance` (`status`,`outstanding_balance`),
  KEY `idx_hr_salary_advance_closed_at` (`closed_at`),
  CONSTRAINT `chk_hr_salary_advance_request_amount` CHECK (`requested_amount` > 0),
  CONSTRAINT `chk_hr_salary_advance_request_monthly` CHECK (`requested_monthly_amount` is null or `requested_monthly_amount` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_waiver_decisions`
--

DROP TABLE IF EXISTS `hr_salary_advance_waiver_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_waiver_decisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `decision_no` varchar(50) NOT NULL,
  `decision_type` enum('individual','blanket') NOT NULL,
  `effective_month` date NOT NULL,
  `reason` varchar(2000) NOT NULL,
  `status` enum('pending_gm','rejected_by_gm','approved_by_gm','executed') NOT NULL DEFAULT 'pending_gm',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `prepared_by` int(10) unsigned DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `refund_account_id` int(10) unsigned DEFAULT NULL,
  `waiver_expense_account_id` int(10) unsigned DEFAULT NULL,
  `gm_approved_by` int(10) unsigned DEFAULT NULL,
  `gm_approved_at` datetime DEFAULT NULL,
  `gm_rejection_reason` varchar(2000) DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_waiver_decision_no` (`decision_no`),
  KEY `idx_hr_salary_advance_waiver_decision_status` (`status`),
  KEY `idx_hr_salary_advance_waiver_decision_effective_month` (`effective_month`),
  KEY `idx_hr_salary_advance_waiver_decision_type` (`decision_type`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_waiver_items`
--

DROP TABLE IF EXISTS `hr_salary_advance_waiver_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_waiver_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `decision_id` int(10) unsigned NOT NULL,
  `salary_advance_request_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `balance_before` decimal(18,2) NOT NULL DEFAULT 0.00,
  `current_period_repayment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_before_waiver` decimal(18,2) NOT NULL DEFAULT 0.00,
  `waived_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `previous_request_status` varchar(50) NOT NULL,
  `resulting_request_status` varchar(50) NOT NULL,
  `refund_account_id` int(10) unsigned DEFAULT NULL,
  `refund_journal_entry_id` int(10) unsigned DEFAULT NULL,
  `waiver_expense_account_id` int(10) unsigned DEFAULT NULL,
  `waiver_journal_entry_id` int(10) unsigned DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_salary_advance_waiver_item_decision` (`decision_id`),
  KEY `idx_hr_salary_advance_waiver_item_request` (`salary_advance_request_id`),
  KEY `idx_hr_salary_advance_waiver_item_employee` (`employee_id`),
  KEY `idx_hr_salary_advance_waiver_item_refund_journal` (`refund_journal_entry_id`),
  KEY `idx_hr_salary_advance_waiver_item_waiver_journal` (`waiver_journal_entry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_salary_advance_waiver_schedule_items`
--

DROP TABLE IF EXISTS `hr_salary_advance_waiver_schedule_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_salary_advance_waiver_schedule_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `waiver_item_id` int(10) unsigned NOT NULL,
  `repayment_schedule_id` int(10) unsigned NOT NULL,
  `previous_schedule_status` varchar(50) NOT NULL,
  `waived_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_salary_advance_waiver_schedule` (`repayment_schedule_id`),
  KEY `idx_hr_salary_advance_waiver_schedule_item` (`waiver_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_code` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `status` enum('posted','voided') NOT NULL DEFAULT 'posted',
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_je_code` (`entry_code`),
  KEY `idx_je_date` (`entry_date`),
  KEY `idx_je_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `journal_lines`
--

DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jl_entry` (`entry_id`),
  KEY `idx_jl_account` (`account_id`),
  CONSTRAINT `fk_jl_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_jl_entry` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=324 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leaves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `leave_type` enum('annual','sick','emergency','unpaid','remote_work_request') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','manager_approved','hr_approved','rejected') NOT NULL DEFAULT 'pending',
  `manager_approved_by` int(10) unsigned DEFAULT NULL,
  `manager_approved_at` datetime DEFAULT NULL,
  `hr_approved_by` int(10) unsigned DEFAULT NULL,
  `hr_approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_employee` (`employee_id`),
  KEY `idx_leave_status` (`status`),
  KEY `fk_leave_manager` (`manager_approved_by`),
  KEY `fk_leave_hr` (`hr_approved_by`),
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_hr` FOREIGN KEY (`hr_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_manager` FOREIGN KEY (`manager_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `letters`
--

DROP TABLE IF EXISTS `letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `letters` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `code` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name_ar` varchar(20) NOT NULL,
  `name_en` varchar(30) DEFAULT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_letters_code` (`code`),
  UNIQUE KEY `uq_letters_sort_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `medical_needs`
--

DROP TABLE IF EXISTS `medical_needs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_needs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medical_needs_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `message_attachments`
--

DROP TABLE IF EXISTS `message_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL,
  `uploader_user_id` int(10) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_attachments_message` (`message_id`,`id`),
  KEY `idx_message_attachments_uploader` (`uploader_user_id`,`id`),
  CONSTRAINT `fk_message_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_attachments_uploader` FOREIGN KEY (`uploader_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `message_reads`
--

DROP TABLE IF EXISTS `message_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_reads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msg_read` (`message_id`,`user_id`),
  KEY `idx_mread_user` (`user_id`),
  KEY `idx_mread_msg` (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=572 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int(10) unsigned NOT NULL,
  `recipient_user_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = broadcast or role-based message',
  `recipient_role` varchar(50) DEFAULT NULL COMMENT 'e.g., nanny, supervisor, accountant, general_manager',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL COMMENT 'For reply threading; points to original message',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g., disbursement, family, sponsor, employee',
  `reference_id` int(10) unsigned DEFAULT NULL,
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Reserved for Plan 3 urgent banner',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_msg_sender` (`sender_id`),
  KEY `idx_msg_recipient_user` (`recipient_user_id`),
  KEY `idx_msg_recipient_role` (`recipient_role`),
  KEY `idx_msg_parent` (`parent_id`),
  KEY `idx_msg_reference` (`reference_type`,`reference_id`),
  KEY `idx_msg_created` (`created_at`),
  KEY `idx_msg_deleted_at` (`deleted_at`),
  KEY `idx_msg_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_messages_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monthly_disbursements`
--

DROP TABLE IF EXISTS `monthly_disbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_disbursements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `month` varchar(7) NOT NULL,
  `nanny_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','pending_approval','approved','transferred','received','returned','cancelled','voided') NOT NULL DEFAULT 'draft',
  `transaction_id` int(11) DEFAULT NULL COMMENT 'Links to transactions.id upon FM approval',
  `expense_account_code` varchar(20) NOT NULL DEFAULT '5100' COMMENT 'COA code for outflow journal (e.g., 5100 Programs & Aid)',
  `reviewed_by_user_id` int(11) DEFAULT NULL COMMENT 'The FM user_id who approved/returned',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'Timestamp of FM review',
  `return_note` text DEFAULT NULL COMMENT 'FM justification if disbursement is returned',
  `returned_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount returned to organization safe',
  `return_receipt_file_path` varchar(255) DEFAULT NULL COMMENT 'Receipt proving return of outstanding amount',
  `returned_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'User who returned/closed the outstanding amount',
  `returned_at` datetime DEFAULT NULL COMMENT 'Return/closure timestamp',
  `return_receipt_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'User who uploaded return receipt',
  `return_receipt_at` datetime DEFAULT NULL COMMENT 'Return receipt upload timestamp',
  `created_by` int(10) unsigned DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `transferred_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `receipt_file_path` varchar(255) DEFAULT NULL,
  `voided_by_user_id` int(10) unsigned DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `reversal_journal_id` int(10) unsigned DEFAULT NULL COMMENT 'Links to the reversing journal entry',
  `transfer_receipt_by_user_id` int(10) unsigned DEFAULT NULL,
  `transfer_receipt_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nanny_id` (`nanny_id`),
  KEY `idx_md_status_created` (`status`,`created_at`),
  KEY `idx_group_id` (`group_id`),
  CONSTRAINT `fk_disbursements_group` FOREIGN KEY (`group_id`) REFERENCES `orphan_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monthly_disbursements_ibfk_1` FOREIGN KEY (`nanny_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nanny_family_verifications`
--

DROP TABLE IF EXISTS `nanny_family_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nanny_family_verifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int(10) unsigned NOT NULL,
  `month` varchar(7) NOT NULL,
  `nanny_id` int(10) unsigned NOT NULL,
  `is_orphan_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_mother_contact_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_bank_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_notes` text DEFAULT NULL,
  `verified_by_user_id` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nfv_family_month` (`family_id`,`month`),
  KEY `idx_nfv_nanny_month` (`nanny_id`,`month`),
  CONSTRAINT `fk_nfv_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nfv_nanny` FOREIGN KEY (`nanny_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nanny_group_assignments`
--

DROP TABLE IF EXISTS `nanny_group_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nanny_group_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `nanny_id` int(10) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL means currently active',
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_nanny_id` (`nanny_id`),
  KEY `idx_active_assignment` (`group_id`,`end_date`),
  KEY `fk_ng_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_ng_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ng_group` FOREIGN KEY (`group_id`) REFERENCES `orphan_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ng_nanny` FOREIGN KEY (`nanny_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`recipient_user_id`),
  KEY `idx_notif_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `orphan_documents`
--

DROP TABLE IF EXISTS `orphan_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orphan_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL DEFAULT 'other',
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_od_child` (`child_id`),
  KEY `idx_od_family` (`family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `orphan_groups`
--

DROP TABLE IF EXISTS `orphan_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orphan_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `lifecycle_reason` varchar(500) DEFAULT NULL,
  `verification_status` enum('pending','submitted','approved','transferred','closed','returned') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_orphan_groups_status` (`status`),
  KEY `idx_orphan_groups_updated_at` (`updated_at`),
  KEY `idx_orphan_groups_closed_by` (`closed_by`),
  CONSTRAINT `fk_group_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `other_projects`
--

DROP TABLE IF EXISTS `other_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `other_projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `target_amount` decimal(12,2) DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `project_code` varchar(50) DEFAULT NULL,
  `project_type` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `total_beneficiaries` int(11) DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `revenue_account_id` int(10) unsigned DEFAULT NULL,
  `expense_account_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_other_projects_status` (`status`),
  KEY `idx_other_projects_currency_code` (`currency_code`),
  KEY `idx_other_projects_created_by` (`created_by`),
  KEY `idx_other_projects_project_code` (`project_code`),
  KEY `idx_other_projects_project_type` (`project_type`),
  KEY `idx_other_projects_updated_by` (`updated_by`),
  CONSTRAINT `fk_other_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_other_projects_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_recovery_requests`
--

DROP TABLE IF EXISTS `password_recovery_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_recovery_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` varchar(64) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','expired') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prr_user` (`user_id`),
  KEY `idx_prr_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payroll`
--

DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `attendance_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `salary_advance_deduction` decimal(18,2) NOT NULL DEFAULT 0.00,
  `incentive_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL,
  `status` enum('draft','approved','paid') NOT NULL DEFAULT 'draft',
  `payroll_policy_version_id` bigint(20) unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL COMMENT 'Links to accounting journal entries',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `accounting_status` enum('none','ready','posted') NOT NULL DEFAULT 'none',
  `accounting_entry_id` int(10) unsigned DEFAULT NULL,
  `payment_account_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_employee_month` (`employee_id`,`month`,`year`),
  UNIQUE KEY `uq_payroll_employee_period` (`employee_id`,`month`,`year`),
  KEY `idx_payroll_status` (`status`),
  KEY `fk_payroll_journal` (`journal_entry_id`),
  KEY `fk_payroll_created_by` (`created_by`),
  KEY `idx_payroll_policy_version` (`payroll_policy_version_id`),
  KEY `idx_payroll_accounting_entry` (`accounting_entry_id`),
  KEY `idx_payroll_salary_advance_deduction` (`salary_advance_deduction`),
  CONSTRAINT `fk_payroll_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `performance_reviews`
--

DROP TABLE IF EXISTS `performance_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `review_month` int(11) NOT NULL,
  `review_year` int(11) NOT NULL,
  `kpi_score` decimal(5,2) DEFAULT NULL COMMENT 'Performance score out of 100',
  `productivity_notes` text DEFAULT NULL,
  `manager_recommendation` text DEFAULT NULL COMMENT 'Recommendation: promotion, training, excellent, etc.',
  `hr_notes` text DEFAULT NULL,
  `status` enum('draft','submitted','finalized') NOT NULL DEFAULT 'draft',
  `evaluated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_performance_employee_month` (`employee_id`,`review_month`,`review_year`),
  KEY `idx_performance_status` (`status`),
  KEY `fk_performance_evaluated_by` (`evaluated_by`),
  CONSTRAINT `fk_performance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_performance_evaluated_by` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_approval`
--

DROP TABLE IF EXISTS `project_approval`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_approval` (
  `project_id` int(10) unsigned NOT NULL,
  `approval_status` enum('draft','submitted','fm_approved','approved','rejected') NOT NULL DEFAULT 'draft',
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `fm_reviewed_by` int(10) unsigned DEFAULT NULL,
  `fm_reviewed_at` datetime DEFAULT NULL,
  `fm_accounting_approved_by` int(11) DEFAULT NULL,
  `fm_accounting_approved_at` datetime DEFAULT NULL,
  `fm_rejection_reason` varchar(1000) DEFAULT NULL,
  `rejection_reason` varchar(1000) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`project_id`),
  KEY `idx_project_approval_status` (`approval_status`),
  KEY `idx_project_approval_submitted_by` (`submitted_by`),
  KEY `idx_project_approval_approved_by` (`approved_by`),
  KEY `idx_project_approval_fm_accounting_approved` (`fm_accounting_approved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_beneficiaries`
--

DROP TABLE IF EXISTS `project_beneficiaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_beneficiaries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `beneficiary_name` varchar(150) NOT NULL,
  `details` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pb_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_beneficiary_records`
--

DROP TABLE IF EXISTS `project_beneficiary_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_beneficiary_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `legacy_beneficiary_id` int(10) unsigned DEFAULT NULL,
  `beneficiary_type` varchar(100) DEFAULT NULL,
  `beneficiary_name` varchar(255) NOT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `age` int(10) unsigned DEFAULT NULL,
  `household_count` int(10) unsigned DEFAULT NULL,
  `planned_support_amount` decimal(14,2) DEFAULT NULL,
  `delivered_support_amount` decimal(14,2) DEFAULT NULL,
  `support_date` date DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_beneficiary_records_project` (`project_id`,`verification_status`),
  KEY `idx_project_beneficiary_records_legacy` (`legacy_beneficiary_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_budget_lines`
--

DROP TABLE IF EXISTS `project_budget_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_budget_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` bigint(20) unsigned NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL,
  `account_id` int(10) unsigned DEFAULT NULL,
  `estimated_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(14,2) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_budget_lines_budget` (`budget_id`),
  KEY `idx_project_budget_lines_account` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_budgets`
--

DROP TABLE IF EXISTS `project_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `version_no` int(10) unsigned NOT NULL DEFAULT 1,
  `budget_name` varchar(150) NOT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `status` enum('draft','submitted','approved','superseded','closed') NOT NULL DEFAULT 'draft',
  `effective_date` date DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_budget_version` (`project_id`,`version_no`),
  KEY `idx_project_budgets_project` (`project_id`,`status`),
  KEY `idx_project_budgets_approved_by` (`approved_by`),
  KEY `idx_project_budgets_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_contacts`
--

DROP TABLE IF EXISTS `project_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `contact_name` varchar(150) NOT NULL,
  `role_description` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_contacts_project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_details`
--

DROP TABLE IF EXISTS `project_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_details` (
  `project_id` int(10) unsigned NOT NULL,
  `objectives` text DEFAULT NULL,
  `justification` text DEFAULT NULL,
  `expected_outcomes` text DEFAULT NULL,
  `target_beneficiary_description` text DEFAULT NULL,
  `implementing_partner` varchar(255) DEFAULT NULL,
  `donor_restrictions` text DEFAULT NULL,
  `procurement_method` varchar(100) DEFAULT NULL,
  `sustainability_plan` text DEFAULT NULL,
  `risk_mitigation` text DEFAULT NULL,
  `government_requirements` text DEFAULT NULL,
  `government_fees` decimal(12,2) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`project_id`),
  KEY `idx_project_details_updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_documents`
--

DROP TABLE IF EXISTS `project_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `document_type` enum('receipt','invoice','certificate','government_fee','permit','contract','quotation','photo','progress_report','closure_report','other') NOT NULL DEFAULT 'other',
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `issuer` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `verification_status` enum('unverified','verified','rejected') NOT NULL DEFAULT 'unverified',
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(1000) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_documents_project` (`project_id`,`document_type`),
  KEY `idx_project_documents_verification` (`verification_status`),
  KEY `idx_project_documents_uploaded_by` (`uploaded_by`),
  KEY `idx_project_documents_verified_by` (`verified_by`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_expense_approvals`
--

DROP TABLE IF EXISTS `project_expense_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_expense_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_id` bigint(20) unsigned NOT NULL,
  `action` enum('submitted','approved','rejected','posted','voided') NOT NULL,
  `actor_id` int(10) unsigned DEFAULT NULL,
  `comments` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_expense_approvals_expense` (`expense_id`,`created_at`),
  KEY `idx_project_expense_approvals_actor` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_expenses`
--

DROP TABLE IF EXISTS `project_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `budget_id` bigint(20) unsigned DEFAULT NULL,
  `budget_line_id` bigint(20) unsigned DEFAULT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(1000) NOT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `vendor_contact` varchar(100) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `government_fee_type` varchar(150) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `payment_source_type` enum('treasury','bank','electronic_wallet','cash','other') NOT NULL DEFAULT 'treasury',
  `payment_account_id` int(10) unsigned DEFAULT NULL,
  `expense_account_id` int(10) unsigned DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `primary_document_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('draft','submitted','approved','posted','voided') NOT NULL DEFAULT 'draft',
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `posted_by` int(10) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(1000) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_expenses_project` (`project_id`,`status`,`expense_date`),
  KEY `idx_project_expenses_budget` (`budget_id`),
  KEY `idx_project_expenses_budget_line` (`budget_line_id`),
  KEY `idx_project_expenses_payment_account` (`payment_account_id`),
  KEY `idx_project_expenses_expense_account` (`expense_account_id`),
  KEY `idx_project_expenses_journal` (`journal_entry_id`),
  KEY `idx_project_expenses_document` (`primary_document_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_funding_allocations`
--

DROP TABLE IF EXISTS `project_funding_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_funding_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `budget_id` bigint(20) unsigned DEFAULT NULL,
  `source_type` enum('direct_donation','admin_fee_surplus','treasury','bank','electronic_wallet','other') NOT NULL,
  `source_account_id` int(10) unsigned DEFAULT NULL,
  `destination_account_id` int(10) unsigned DEFAULT NULL,
  `transaction_id` int(10) unsigned DEFAULT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_code` char(3) NOT NULL,
  `allocation_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `status` enum('draft','approved','posted','voided') NOT NULL DEFAULT 'draft',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `posted_by` int(10) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(1000) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_funding_project` (`project_id`,`status`),
  KEY `idx_project_funding_budget` (`budget_id`),
  KEY `idx_project_funding_source_account` (`source_account_id`),
  KEY `idx_project_funding_destination_account` (`destination_account_id`),
  KEY `idx_project_funding_transaction` (`transaction_id`),
  KEY `idx_project_funding_journal` (`journal_entry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_funding_returns`
--

DROP TABLE IF EXISTS `project_funding_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_funding_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `funding_allocation_id` int(10) unsigned NOT NULL,
  `source_account_id` int(10) unsigned NOT NULL,
  `journal_entry_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_code` varchar(10) NOT NULL DEFAULT 'SDG',
  `return_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `returned_by` int(10) unsigned NOT NULL,
  `returned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_funding_return_allocation` (`funding_allocation_id`),
  KEY `idx_project_funding_return_project` (`project_id`),
  KEY `idx_project_funding_return_source` (`source_account_id`),
  KEY `idx_project_funding_return_journal` (`journal_entry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_government_requirements`
--

DROP TABLE IF EXISTS `project_government_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_government_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `requirement_text` varchar(500) NOT NULL,
  `fee_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pgr_project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_labor_comments`
--

DROP TABLE IF EXISTS `project_labor_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_labor_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `labor_id` bigint(20) unsigned NOT NULL,
  `manager_user_id` int(10) unsigned NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_labor_comments_labor` (`labor_id`,`created_at`),
  KEY `idx_project_labor_comments_manager` (`manager_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_labor_helpers`
--

DROP TABLE IF EXISTS `project_labor_helpers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_labor_helpers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `supervisor_user_id` int(10) unsigned DEFAULT NULL,
  `provider_type` enum('individual','company') NOT NULL DEFAULT 'individual',
  `provider_name` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `contact_person_name` varchar(150) DEFAULT NULL,
  `contact_person_phone` varchar(30) DEFAULT NULL,
  `number_of_workers` int(10) unsigned NOT NULL DEFAULT 1,
  `work_description` varchar(1000) NOT NULL,
  `payment_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency_code` char(3) NOT NULL DEFAULT 'SDG',
  `payment_timing` enum('upfront','daily','weekly','monthly','upon_completion') NOT NULL DEFAULT 'upon_completion',
  `status` enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `notes` text DEFAULT NULL,
  `manager_comment` text DEFAULT NULL,
  `manager_comment_by` int(10) unsigned DEFAULT NULL,
  `manager_comment_at` datetime DEFAULT NULL,
  `payment_status` enum('not_paid','partially_paid','paid') NOT NULL DEFAULT 'not_paid',
  `project_expense_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_labor_project` (`project_id`,`status`),
  KEY `idx_project_labor_supervisor` (`supervisor_user_id`),
  KEY `idx_project_labor_expense` (`project_expense_id`),
  KEY `idx_project_labor_payment_status` (`payment_status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_lifecycle`
--

DROP TABLE IF EXISTS `project_lifecycle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_lifecycle` (
  `project_id` int(10) unsigned NOT NULL,
  `lifecycle_status` enum('planned','active','completed','under_review','closed','reopened','cancelled') NOT NULL DEFAULT 'planned',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by` int(10) unsigned DEFAULT NULL,
  `reopen_reason` varchar(1000) DEFAULT NULL,
  `closure_summary` text DEFAULT NULL,
  `final_budget_amount` decimal(14,2) DEFAULT NULL,
  `total_funded_amount` decimal(14,2) DEFAULT NULL,
  `total_expensed_amount` decimal(14,2) DEFAULT NULL,
  `variance_amount` decimal(14,2) DEFAULT NULL,
  `variance_percent` decimal(8,2) DEFAULT NULL,
  `variance_explanation` text DEFAULT NULL,
  `residual_amount` decimal(14,2) DEFAULT NULL,
  `residual_action` enum('returned_to_treasury','returned_to_bank','returned_to_wallet','transferred_to_project','retained_for_followup','other') DEFAULT NULL,
  `closing_document_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `close_reason` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`project_id`),
  KEY `idx_project_lifecycle_status` (`lifecycle_status`),
  KEY `idx_project_lifecycle_closed_by` (`closed_by`),
  KEY `idx_project_lifecycle_reopened_by` (`reopened_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_milestones`
--

DROP TABLE IF EXISTS `project_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_milestones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','delayed','cancelled') NOT NULL DEFAULT 'pending',
  `completion_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_milestones_project` (`project_id`,`status`,`planned_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_partners`
--

DROP TABLE IF EXISTS `project_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_partners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `partner_name` varchar(255) NOT NULL,
  `role_description` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_partners_project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_payment_evidence`
--

DROP TABLE IF EXISTS `project_payment_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_payment_evidence` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `funding_allocation_id` int(10) unsigned NOT NULL,
  `source_account_id` int(10) unsigned NOT NULL,
  `journal_entry_id` int(10) unsigned DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','e_wallet') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_code` varchar(10) NOT NULL DEFAULT 'SDG',
  `payment_date` date NOT NULL,
  `status` enum('pending','documented') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `receipt_file_path` varchar(255) DEFAULT NULL,
  `receipt_original_name` varchar(255) DEFAULT NULL,
  `receipt_mime_type` varchar(100) DEFAULT NULL,
  `voucher_confirmed_at` datetime DEFAULT NULL,
  `documented_by` int(10) unsigned DEFAULT NULL,
  `documented_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_payment_funding` (`funding_allocation_id`),
  KEY `idx_project_payment_project` (`project_id`),
  KEY `idx_project_payment_status` (`status`),
  KEY `idx_project_payment_method` (`payment_method`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_procurement_methods`
--

DROP TABLE IF EXISTS `project_procurement_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_procurement_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `method_name` varchar(255) NOT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_procurement_project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_progress_updates`
--

DROP TABLE IF EXISTS `project_progress_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_progress_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `update_date` date NOT NULL,
  `completion_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `summary` text NOT NULL,
  `achievements` text DEFAULT NULL,
  `issues` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_progress_project_date` (`project_id`,`update_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_status_history`
--

DROP TABLE IF EXISTS `project_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_status_history_project` (`project_id`,`created_at`),
  KEY `idx_project_status_history_changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_supervisor_assignments`
--

DROP TABLE IF EXISTS `project_supervisor_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_supervisor_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `supervisor_user_id` int(10) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `end_reason` varchar(500) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_supervisor_current` (`project_id`,`supervisor_user_id`,`ended_at`),
  KEY `idx_project_supervisor_user` (`supervisor_user_id`,`ended_at`),
  KEY `idx_project_supervisor_assigned_by` (`assigned_by`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_team`
--

DROP TABLE IF EXISTS `project_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_team` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `section_code` enum('general','finance','operations','documents','closure') NOT NULL,
  `is_lead` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `unassigned_at` datetime DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_team_active` (`project_id`,`user_id`,`section_code`,`unassigned_at`),
  KEY `idx_project_team_project` (`project_id`,`section_code`,`unassigned_at`),
  KEY `idx_project_team_user` (`user_id`,`section_code`,`unassigned_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `returned_disbursement_reissues`
--

DROP TABLE IF EXISTS `returned_disbursement_reissues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `returned_disbursement_reissues` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_item_id` int(10) unsigned NOT NULL,
  `disbursement_id` int(10) unsigned NOT NULL,
  `family_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `original_month` varchar(7) NOT NULL,
  `original_return_journal_id` int(10) unsigned DEFAULT NULL,
  `original_return_reason` varchar(500) DEFAULT NULL,
  `original_returned_at` datetime DEFAULT NULL,
  `original_return_receipt_path` varchar(255) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'awaiting_redelivery',
  `authorized_by` int(10) unsigned DEFAULT NULL,
  `authorized_at` datetime DEFAULT NULL,
  `authorization_reason` varchar(500) DEFAULT NULL,
  `redelivered_by` int(10) unsigned DEFAULT NULL,
  `redelivered_at` datetime DEFAULT NULL,
  `redelivery_receipt_path` varchar(255) DEFAULT NULL,
  `redelivery_transaction_id` int(10) unsigned DEFAULT NULL,
  `redelivery_journal_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_returned_reissue_item` (`disbursement_item_id`),
  KEY `idx_returned_reissue_status` (`status`),
  KEY `idx_returned_reissue_family` (`family_id`),
  KEY `idx_returned_reissue_disbursement` (`disbursement_id`),
  CONSTRAINT `fk_returned_reissue_batch` FOREIGN KEY (`disbursement_id`) REFERENCES `monthly_disbursements` (`id`),
  CONSTRAINT `fk_returned_reissue_item` FOREIGN KEY (`disbursement_item_id`) REFERENCES `disbursement_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsor_import_exceptions`
--

DROP TABLE IF EXISTS `sponsor_import_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsor_import_exceptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` varchar(50) DEFAULT NULL,
  `sheet_row` int(11) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `sponsor_entry` varchar(500) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7510 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsor_payments`
--

DROP TABLE IF EXISTS `sponsor_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsor_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_type` enum('monthly_sponsorship','admin_fee','general_donation','project_donation','school_fees','medicine','gift','other') NOT NULL DEFAULT 'monthly_sponsorship',
  `sponsorship_id` int(10) unsigned DEFAULT NULL,
  `sponsor_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `other_source_note` varchar(255) DEFAULT NULL,
  `purpose_note` varchar(255) DEFAULT NULL,
  `supervisor_id` int(10) unsigned NOT NULL COMMENT 'The Supervisor who logged the payment',
  `payment_period` varchar(20) NOT NULL COMMENT 'e.g., "March/2026"',
  `amount` decimal(12,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'SDG',
  `receipt_file_path` varchar(255) DEFAULT NULL COMMENT 'Relative path to uploaded receipt',
  `unified_receipt_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','returned') NOT NULL DEFAULT 'pending' COMMENT 'pending=بانتظار المدير المالي, approved=معتمد ومُرحّل, returned=مرفوض/مرتجع',
  `reviewed_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'المدير المالي الذي قام بالمراجعة',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'تاريخ ووقت المراجعة',
  `return_note` text DEFAULT NULL COMMENT 'سبب الارجاع في حال رفض المعاملة',
  `transaction_id` int(10) unsigned DEFAULT NULL COMMENT 'رابط القيد المحاسبي في جدول transactions بعد الاعتماد',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',
  PRIMARY KEY (`id`),
  KEY `fk_payment_sponsorship` (`sponsorship_id`),
  KEY `fk_payment_supervisor` (`supervisor_id`),
  KEY `idx_sp_status` (`status`),
  KEY `idx_sp_reviewed_by` (`reviewed_by_user_id`),
  KEY `idx_sp_transaction` (`transaction_id`),
  KEY `idx_sp_payment_type` (`payment_type`),
  KEY `idx_sp_sponsor_id` (`sponsor_id`),
  CONSTRAINT `fk_payment_sponsorship` FOREIGN KEY (`sponsorship_id`) REFERENCES `sponsorships` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sp_reviewed_by` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sp_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsor_requests`
--

DROP TABLE IF EXISTS `sponsor_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsor_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sponsor_name` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `sponsor_type` enum('individual','company','organization') NOT NULL DEFAULT 'individual',
  `preferred_payment_method` enum('cash','bank_transfer','credit_card','mobile','other') NOT NULL DEFAULT 'cash',
  `proposed_amount` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `first_letter_raw` varchar(10) DEFAULT NULL,
  `first_letter_id` tinyint(3) unsigned DEFAULT NULL,
  `assigned_supervisor_id` int(10) unsigned DEFAULT NULL,
  `status` enum('new','contacted','converted','lost') NOT NULL DEFAULT 'new',
  `contact_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_by` int(10) unsigned DEFAULT NULL,
  `created_sponsor_id` int(10) unsigned DEFAULT NULL,
  `phone_purpose` varchar(20) NOT NULL DEFAULT 'both',
  `alt_phone` varchar(30) DEFAULT NULL,
  `alt_phone_purpose` varchar(20) NOT NULL DEFAULT 'both',
  `desired_orphans` smallint(5) unsigned DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `contacted_at` datetime DEFAULT NULL,
  `brought_by` varchar(190) DEFAULT NULL,
  `brought_by_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sr_status` (`status`),
  KEY `idx_sr_supervisor` (`assigned_supervisor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsor_supervisor_assignments`
--

DROP TABLE IF EXISTS `sponsor_supervisor_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsor_supervisor_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sponsor_id` int(10) unsigned NOT NULL,
  `supervisor_id` int(10) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assignment_type` enum('permanent','temporary') NOT NULL DEFAULT 'permanent',
  `ended_at` datetime DEFAULT NULL,
  `ended_by` int(10) unsigned DEFAULT NULL,
  `end_reason` varchar(100) DEFAULT NULL,
  `assignment_reason` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ssa_sponsor` (`sponsor_id`),
  KEY `idx_ssa_supervisor` (`supervisor_id`),
  KEY `idx_ssa_active` (`sponsor_id`,`ended_at`),
  KEY `fk_ssa_assigned_by` (`assigned_by`),
  KEY `fk_ssa_ended_by` (`ended_by`),
  CONSTRAINT `fk_ssa_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ssa_ended_by` FOREIGN KEY (`ended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ssa_sponsor` FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ssa_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=271 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsors`
--

DROP TABLE IF EXISTS `sponsors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `first_letter_raw` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `first_letter_id` tinyint(3) unsigned DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `sponsor_type` enum('individual','company','organization') NOT NULL DEFAULT 'individual',
  `preferred_payment_method` enum('cash','bank_transfer','credit_card','mobile','other') NOT NULL DEFAULT 'cash',
  `status` enum('active','inactive','suspended','cancelled') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sponsor_code` varchar(50) DEFAULT NULL,
  `supervisor_id` int(10) unsigned DEFAULT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `gender` enum('male','female','organization','unknown') DEFAULT 'unknown',
  `phone_purpose` varchar(20) NOT NULL DEFAULT 'both',
  `alt_phone` varchar(30) DEFAULT NULL,
  `alt_phone_purpose` varchar(20) NOT NULL DEFAULT 'both',
  `desired_orphans` smallint(5) unsigned DEFAULT NULL,
  `is_manual_override` tinyint(1) NOT NULL DEFAULT 0,
  `brought_by_user_id` int(10) unsigned DEFAULT NULL COMMENT 'ID of the employee/PR person who brought the sponsor',
  `acquisition_source` varchar(100) DEFAULT NULL COMMENT 'Source: e.g., Media, Direct, Event, Employee',
  `brought_by_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sponsors_first_letter_id` (`first_letter_id`),
  KEY `idx_sponsors_status` (`status`),
  KEY `idx_sponsors_created_by` (`created_by`),
  KEY `idx_sponsors_phone` (`phone`),
  KEY `idx_sponsors_sponsor_code` (`sponsor_code`),
  KEY `idx_sponsors_supervisor_id` (`supervisor_id`),
  KEY `idx_sponsors_assigned_by` (`assigned_by`),
  KEY `idx_sponsors_updated_by` (`updated_by`),
  KEY `fk_sponsor_brought_by` (`brought_by_user_id`),
  CONSTRAINT `fk_sponsor_brought_by` FOREIGN KEY (`brought_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsors_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsors_first_letter` FOREIGN KEY (`first_letter_id`) REFERENCES `letters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2859 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsorship_children`
--

DROP TABLE IF EXISTS `sponsorship_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsorship_children` (
  `sponsorship_id` int(10) unsigned NOT NULL,
  `family_child_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`sponsorship_id`,`family_child_id`),
  KEY `idx_sponsorship_children_family_child_id` (`family_child_id`),
  CONSTRAINT `fk_sponsorship_children_family_child` FOREIGN KEY (`family_child_id`) REFERENCES `family_children` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsorship_children_sponsorship` FOREIGN KEY (`sponsorship_id`) REFERENCES `sponsorships` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sponsorships`
--

DROP TABLE IF EXISTS `sponsorships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsorships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sponsor_id` int(10) unsigned NOT NULL,
  `child_id` int(10) unsigned DEFAULT NULL,
  `monthly_amount` decimal(12,2) NOT NULL,
  `currency_code` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sponsorship_code` varchar(50) DEFAULT NULL,
  `pause_reason` text DEFAULT NULL,
  `pause_start_date` date DEFAULT NULL,
  `pause_end_date` date DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sponsorships_sponsor_id` (`sponsor_id`),
  KEY `idx_sponsorships_status` (`status`),
  KEY `idx_sponsorships_start_date` (`start_date`),
  KEY `idx_sponsorships_currency_code` (`currency_code`),
  KEY `idx_sponsorships_created_by` (`created_by`),
  KEY `idx_sponsorships_sponsorship_code` (`sponsorship_code`),
  KEY `idx_sponsorships_updated_by` (`updated_by`),
  KEY `fk_sponsorship_child` (`child_id`),
  CONSTRAINT `fk_sponsorship_child` FOREIGN KEY (`child_id`) REFERENCES `family_children` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsorships_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsorships_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsorships_sponsor` FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3561 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supervisor_leaves`
--

DROP TABLE IF EXISTS `supervisor_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisor_leaves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supervisor_id` int(10) unsigned NOT NULL,
  `leave_type` enum('vacation','personal','medical','maternity','study','other') NOT NULL DEFAULT 'other',
  `start_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supervisor_leave_supervisor` (`supervisor_id`),
  KEY `idx_supervisor_leave_status` (`status`),
  KEY `idx_supervisor_leave_dates` (`start_date`,`expected_return_date`),
  KEY `fk_supervisor_leave_created_by` (`created_by`),
  KEY `fk_supervisor_leave_updated_by` (`updated_by`),
  CONSTRAINT `fk_supervisor_leave_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supervisor_leave_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_supervisor_leave_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supervisor_letter_assignment_history`
--

DROP TABLE IF EXISTS `supervisor_letter_assignment_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisor_letter_assignment_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supervisor_letter_id` int(10) unsigned DEFAULT NULL,
  `supervisor_id` int(10) unsigned NOT NULL,
  `letter_id` tinyint(3) unsigned NOT NULL,
  `gender` enum('male','female','both') DEFAULT 'both',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assignment_type` enum('permanent','temporary') NOT NULL DEFAULT 'permanent',
  `assignment_reason` varchar(100) DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `ended_by` int(10) unsigned DEFAULT NULL,
  `end_reason` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_slah_supervisor_letter_id` (`supervisor_letter_id`),
  KEY `idx_slah_supervisor_id` (`supervisor_id`),
  KEY `idx_slah_letter_id` (`letter_id`),
  KEY `idx_slah_assigned_by` (`assigned_by`),
  KEY `idx_slah_ended_by` (`ended_by`),
  KEY `idx_slah_active` (`supervisor_id`,`ended_at`),
  CONSTRAINT `fk_slah_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_slah_ended_by` FOREIGN KEY (`ended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_slah_letter` FOREIGN KEY (`letter_id`) REFERENCES `letters` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_slah_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_slah_supervisor_letter` FOREIGN KEY (`supervisor_letter_id`) REFERENCES `supervisor_letters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supervisor_letters`
--

DROP TABLE IF EXISTS `supervisor_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisor_letters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supervisor_id` int(10) unsigned NOT NULL,
  `letter_id` tinyint(3) unsigned NOT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `gender` enum('male','female','both') DEFAULT 'both',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supervisor_letter_gender` (`letter_id`,`gender`),
  KEY `idx_supervisor_letters_supervisor_id` (`supervisor_id`),
  KEY `idx_supervisor_letters_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_supervisor_letters_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_supervisor_letters_letter` FOREIGN KEY (`letter_id`) REFERENCES `letters` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_supervisor_letters_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suspension_cases`
--

DROP TABLE IF EXISTS `suspension_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suspension_cases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('family','child') NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `reason` varchar(30) NOT NULL,
  `notes` text DEFAULT NULL,
  `suspended_by` int(10) unsigned DEFAULT NULL,
  `suspended_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','lifted') NOT NULL DEFAULT 'active',
  `lifted_by` int(10) unsigned DEFAULT NULL,
  `lifted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sc_entity` (`entity_type`,`entity_id`),
  KEY `suspended_by` (`suspended_by`),
  KEY `lifted_by` (`lifted_by`),
  CONSTRAINT `suspension_cases_ibfk_1` FOREIGN KEY (`suspended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `suspension_cases_ibfk_2` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sponsorship_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency_code` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payment_method` enum('cash','bank_transfer','credit_card','mobile','other') NOT NULL DEFAULT 'cash',
  `transaction_date` date NOT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `months_covered` decimal(5,2) NOT NULL DEFAULT 1.00,
  `payment_period` varchar(20) DEFAULT NULL,
  `purpose_note` varchar(255) DEFAULT NULL,
  `purpose` varchar(30) DEFAULT NULL,
  `admin_fee_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `admin_fee_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending_fm_review','returned','cancelled','posted','voided') NOT NULL DEFAULT 'pending_fm_review',
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `fm_reviewed_by` int(10) unsigned DEFAULT NULL,
  `fm_reviewed_at` datetime DEFAULT NULL,
  `fm_review_reason` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `resubmitted_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `transaction_code` varchar(50) DEFAULT NULL,
  `transaction_type` varchar(50) DEFAULT NULL,
  `family_id` int(10) unsigned DEFAULT NULL,
  `sponsor_id` int(10) unsigned DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `child_id` int(10) unsigned DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `month_start` date DEFAULT NULL,
  `month_end` date DEFAULT NULL,
  `admin_fee_separate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `legacy_admin_fee_deducted` decimal(12,2) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `unified_receipt_path` varchar(255) DEFAULT NULL,
  `admin_fee_method` varchar(20) NOT NULL DEFAULT 'none',
  `admin_fee_value` decimal(14,2) NOT NULL DEFAULT 0.00,
  `admin_fee_policy_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transactions_sponsorship_id` (`sponsorship_id`),
  KEY `idx_transactions_transaction_date` (`transaction_date`),
  KEY `idx_transactions_status` (`status`),
  KEY `idx_transactions_payment_method` (`payment_method`),
  KEY `idx_transactions_currency_code` (`currency_code`),
  KEY `idx_transactions_receipt_number` (`receipt_number`),
  KEY `idx_transactions_created_by` (`created_by`),
  KEY `idx_transactions_voided_by` (`voided_by`),
  KEY `idx_transactions_transaction_code` (`transaction_code`),
  KEY `idx_transactions_transaction_type` (`transaction_type`),
  KEY `idx_transactions_family_id` (`family_id`),
  KEY `idx_transactions_sponsor_id` (`sponsor_id`),
  KEY `idx_transactions_project_id` (`project_id`),
  KEY `idx_transactions_child_id` (`child_id`),
  KEY `idx_txn_period` (`payment_period`),
  KEY `idx_transactions_fm_review_status` (`status`),
  KEY `idx_transactions_created_by_status` (`created_by`,`status`),
  CONSTRAINT `fk_transactions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_sponsorship` FOREIGN KEY (`sponsorship_id`) REFERENCES `sponsorships` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user_id` (`user_id`),
  KEY `idx_user_sessions_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint(3) unsigned NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `supervisor_status` enum('active','on_leave','suspended','returning','departed','archived') NOT NULL DEFAULT 'active',
  `password_change_required` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `legacy_status` varchar(20) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `manager_id` int(10) unsigned DEFAULT NULL,
  `theme_preference` varchar(20) DEFAULT 'light',
  `language_preference` varchar(10) DEFAULT 'ar',
  `email_notifications` tinyint(1) DEFAULT 1,
  `push_notifications` tinyint(1) DEFAULT 1,
  `email_newsletter` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_created_by` (`created_by`),
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `verification_audit_log`
--

DROP TABLE IF EXISTS `verification_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int(10) unsigned NOT NULL,
  `month` varchar(7) NOT NULL,
  `nanny_id` int(10) unsigned NOT NULL,
  `old_orphan` tinyint(4) NOT NULL DEFAULT 0,
  `old_mother` tinyint(4) NOT NULL DEFAULT 0,
  `old_bank` tinyint(4) NOT NULL DEFAULT 0,
  `new_orphan` tinyint(4) NOT NULL DEFAULT 0,
  `new_mother` tinyint(4) NOT NULL DEFAULT 0,
  `new_bank` tinyint(4) NOT NULL DEFAULT 0,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_family_month` (`family_id`,`month`),
  KEY `idx_nanny` (`nanny_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vouchers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_type` enum('receipt','payment') NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `party_name` varchar(150) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `cash_account_id` int(10) unsigned NOT NULL,
  `other_account_id` int(10) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `entry_id` int(10) unsigned DEFAULT NULL,
  `status` enum('posted','voided') NOT NULL DEFAULT 'posted',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vouchers_no` (`voucher_no`),
  KEY `idx_vouchers_type` (`voucher_type`),
  KEY `idx_vouchers_date` (`voucher_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `winback_campaigns`
--

DROP TABLE IF EXISTS `winback_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `winback_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sponsor_id` int(10) unsigned NOT NULL,
  `handled_by` int(10) unsigned DEFAULT NULL,
  `reactivated_by_user_id` int(10) unsigned DEFAULT NULL,
  `reactivation_confirmed_by_user_id` int(10) unsigned DEFAULT NULL,
  `reactivated_at` datetime DEFAULT NULL,
  `status` enum('open','contacted','persuaded','declined') NOT NULL DEFAULT 'open',
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_contact_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sponsor_id` (`sponsor_id`),
  KEY `handled_by` (`handled_by`),
  KEY `idx_winback_reactivated_by` (`reactivated_by_user_id`),
  KEY `idx_winback_reactivation_confirmer` (`reactivation_confirmed_by_user_id`),
  CONSTRAINT `winback_campaigns_ibfk_1` FOREIGN KEY (`sponsor_id`) REFERENCES `sponsors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `winback_campaigns_ibfk_2` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `winback_contacts`
--

DROP TABLE IF EXISTS `winback_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `winback_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `contacted_by` int(10) unsigned DEFAULT NULL,
  `contact_date` datetime NOT NULL DEFAULT current_timestamp(),
  `method` varchar(30) NOT NULL DEFAULT 'phone',
  `outcome` varchar(50) NOT NULL DEFAULT 'no_answer',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `contacted_by` (`contacted_by`),
  CONSTRAINT `winback_contacts_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `winback_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `winback_contacts_ibfk_2` FOREIGN KEY (`contacted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16 17:46:37
