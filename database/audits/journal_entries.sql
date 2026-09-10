-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2026 at 10:18 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ahl_el_kheir`
--

-- --------------------------------------------------------

--
-- Table structure for table `je`
--

CREATE TABLE `je` (
  `id` int(10) UNSIGNED NOT NULL,
  `entry_code` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('posted','voided') NOT NULL DEFAULT 'posted',
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) UNSIGNED DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `je`
--

INSERT INTO `je` (`id`, `entry_code`, `entry_date`, `reference_type`, `reference_id`, `status`, `created_by`, `voided_by`, `voided_at`, `void_reason`, `line_count`, `total_debit`, `total_credit`) VALUES
(1, 'JE-000001', '2026-08-16', 'transaction', 1, 'posted', 29, NULL, NULL, NULL, 2, 11000.00, 11000.00),
(2, 'JE-000002', '2026-08-16', 'transaction', 2, 'posted', 29, NULL, NULL, NULL, 2, 11000.00, 11000.00),
(3, 'JE-000003', '2026-08-16', 'transaction', 3, 'posted', 29, NULL, NULL, NULL, 2, 10000.00, 10000.00),
(4, 'JE-OUT-000004', '2026-08-17', 'transaction', 4, 'posted', 29, NULL, NULL, NULL, 2, 833000.00, 833000.00),
(5, 'JE-OUT-000005', '2026-08-18', 'transaction', 5, 'posted', 29, NULL, NULL, NULL, 2, 195000.00, 195000.00),
(7, 'JE-REV-000003', '2026-08-18', 'disbursement_void', 3, 'posted', 29, NULL, NULL, NULL, 2, 195000.00, 195000.00),
(8, 'JE-RET-000006', '2026-08-18', 'item_return', 6, 'posted', 17, NULL, NULL, NULL, 2, 95000.00, 95000.00),
(9, 'JE-DISB-5', '2026-08-21', 'disbursement', 5, 'posted', 17, NULL, NULL, NULL, 2, 59000.00, 59000.00),
(14, 'JE-DISB-6-6a8801dd4cae4', '2026-08-21', 'disbursement', 6, 'posted', 17, NULL, NULL, NULL, 2, 59000.00, 59000.00),
(15, 'JE-DISB-5-1787300527', '2026-08-21', 'disbursement', 5, 'posted', 17, NULL, NULL, NULL, 2, 59000.00, 59000.00),
(16, 'JE-DISB-7-6a8a84d116c50', '2026-08-23', 'disbursement', 7, 'voided', 17, 1, '2026-08-23 14:07:49', 'دفعة #7 أُنشئت قبل اكتمال التوثيق بسبب خطأ في حساب المبلغ شمل عائلات غير موثقة. تم إبطال القيد لتصحيح السجلات المحاسبية.', 2, 833000.00, 833000.00),
(17, 'JE-DISB-8-6a8b22ad5d30e', '2026-08-23', 'disbursement', 8, 'voided', 17, 29, '2026-08-23 20:44:48', 'weqweqeq', 2, 738000.00, 738000.00),
(18, 'JE-DISB-9-6a8b32f2c64a7', '2026-08-23', 'disbursement', 9, 'posted', 17, NULL, NULL, NULL, 2, 738000.00, 738000.00),
(19, 'JE-000014', '2026-08-25', 'manual', NULL, 'voided', 29, 29, '2026-08-26 22:15:50', 'wrong values', 2, 100000000.00, 100000000.00),
(20, 'JE-000015', '2026-08-26', 'transaction', 17, 'posted', 29, NULL, NULL, NULL, 2, 15000.00, 15000.00),
(21, 'JE-000016', '2026-08-26', 'transaction', 18, 'posted', 29, NULL, NULL, NULL, 2, 15000.00, 15000.00),
(22, 'JE-OB-000017', '2026-01-01', 'opening_balance', NULL, 'posted', 29, NULL, NULL, NULL, 4, 100000000.00, 100000000.00),
(29, 'JE-PRJ-0002-20260829181607', '2026-08-29', 'project', 2, 'posted', 2, NULL, NULL, NULL, 4, 250000.00, 250000.00),
(30, 'JE-PRJ-0004-20260829184954', '2026-08-29', 'project', 4, 'posted', 2, NULL, NULL, NULL, 4, 500000.00, 500000.00),
(31, 'PAY-20', '2026-09-07', 'payroll', 20, 'posted', NULL, NULL, NULL, NULL, 2, 6000.00, 6000.00),
(32, 'REV-PAY-20', '2026-09-07', 'payroll_reversal', 20, 'posted', 32, NULL, NULL, NULL, 2, 6000.00, 6000.00),
(33, 'PAY-22', '2026-09-07', 'payroll', 22, 'posted', NULL, NULL, NULL, NULL, 2, 7000.00, 7000.00),
(34, 'PAY-23', '2026-09-08', 'payroll', 23, 'posted', NULL, NULL, NULL, NULL, 2, 7000.00, 7000.00),
(35, 'JE-000024', '2026-09-08', 'transaction', 19, 'posted', 17, NULL, NULL, NULL, 2, 50000.00, 50000.00),
(36, 'JE-000025', '2026-09-09', 'transaction', 20, 'posted', 29, NULL, NULL, NULL, 2, 250000.00, 250000.00),
(37, 'JE-000026', '2026-09-09', 'transaction', 22, 'voided', 29, 29, '2026-09-09 18:31:25', 'اختبار رقابي — إبطال TR-000016', 2, 1000.00, 1000.00),
(38, 'JE-VOID-TXN-22', '2026-09-09', 'transaction_void', 22, 'posted', 29, NULL, NULL, NULL, 2, 1000.00, 1000.00),
(39, 'JE-000027', '2026-09-09', 'manual', NULL, 'posted', 29, NULL, NULL, NULL, 2, 1000.00, 1000.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `je`
--
ALTER TABLE `je`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_je_code` (`entry_code`),
  ADD KEY `idx_je_date` (`entry_date`),
  ADD KEY `idx_je_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `je`
--
ALTER TABLE `je`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
