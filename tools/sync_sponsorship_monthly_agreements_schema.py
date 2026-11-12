#!/usr/bin/env python3
"""Synchronize the monthly sponsorship agreement migration into the canonical SQL dump.

This is a narrowly scoped, idempotent repository-maintenance operation. It changes
only CREATE/ALTER schema statements; existing INSERT/data statements are asserted
byte-for-byte unchanged. It never applies DDL to an application database.
"""
from pathlib import Path
import re

SCHEMA = Path("database/ahl_el_kheir.sql")


def require_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one exact schema anchor, found {count}")
    return text.replace(old, new, 1)


def insert_statements(text: str) -> list[str]:
    return re.findall(r"(?ms)^INSERT INTO .*?;(?=\n)", text)


def synchronize(text: str) -> str:
    if "CREATE TABLE " + chr(96) + "sponsorship_monthly_agreements" + chr(96) in text:
        q = chr(96)
        evidence_columns = [
            q + "transaction_date" + q + " date DEFAULT NULL",
            q + "receipt_number" + q + " varchar(50) DEFAULT NULL",
            q + "reference_number" + q + " varchar(100) DEFAULT NULL",
        ]
        sponsor_table_start = text.find("CREATE TABLE " + q + "sponsor_payments" + q)
        if sponsor_table_start < 0:
            raise RuntimeError("sponsor_payments table is missing from canonical schema.")
        sponsor_table_end = text.find(";", sponsor_table_start)
        sponsor_table_block = text[sponsor_table_start:sponsor_table_end]
        present_evidence_columns = sum(column in sponsor_table_block for column in evidence_columns)
        if present_evidence_columns not in (0, 3):
            raise RuntimeError("Sponsor payment row-evidence columns are only partially present.")
        if present_evidence_columns == 0:
            payment_method_line = "  " + q + "payment_method" + q + " varchar(30) NOT NULL DEFAULT 'cash',"
            if payment_method_line not in sponsor_table_block:
                payment_method_line = "  " + q + "payment_method" + q + " varchar(30) NOT NULL DEFAULT 'cash'"
            if payment_method_line not in sponsor_table_block:
                raise RuntimeError("Cannot find the payment_method anchor in sponsor_payments.")
            replacement = (
                "  " + q + "payment_method" + q + " varchar(30) NOT NULL DEFAULT 'cash',\n"
                "  " + q + "transaction_date" + q + " date DEFAULT NULL,\n"
                "  " + q + "receipt_number" + q + " varchar(50) DEFAULT NULL,\n"
                "  " + q + "reference_number" + q + " varchar(100) DEFAULT NULL,"
            )
            if payment_method_line.endswith(","):
                replacement = replacement
            else:
                replacement = replacement[:-1]
            sponsor_table_block = sponsor_table_block.replace(payment_method_line, replacement, 1)
            text = text[:sponsor_table_start] + sponsor_table_block + text[sponsor_table_end:]
        required = [
            q + "component_model" + q + " enum('legacy','monthly_agreement') NOT NULL DEFAULT 'legacy'",
            q + "monthly_agreement_id" + q + " int(10) UNSIGNED DEFAULT NULL",
            q + "agreed_sponsorship_amount" + q + " decimal(12,2) DEFAULT NULL",
            q + "agreed_service_fee_amount" + q + " decimal(12,2) DEFAULT NULL",
            q + "actual_sponsorship_received" + q + " decimal(12,2) DEFAULT NULL",
            q + "actual_service_fee_received" + q + " decimal(12,2) DEFAULT NULL",
            q + "transaction_date" + q + " date DEFAULT NULL",
            q + "receipt_number" + q + " varchar(50) DEFAULT NULL",
            q + "reference_number" + q + " varchar(100) DEFAULT NULL",
            q + "fk_transactions_spma" + q, q + "fk_sponsor_payments_spma" + q,
            q + "fk_spma_sponsorship" + q, q + "fk_spma_currency" + q, q + "fk_spma_creator" + q,
            q + "uq_spma_sponsorship_month_version" + q, q + "idx_spma_month_latest" + q,
        ]
        missing = [item for item in required if item not in text]
        if missing:
            raise RuntimeError("Agreement schema is partially present; missing: " + ", ".join(missing))
        if "service_fee_agreement_id" in text:
            raise RuntimeError("Obsolete service_fee_agreement_id remains in canonical schema.")
        table = "sponsorship_monthly_agreements"
        index_statement = (
            "ALTER TABLE " + q + table + q + "\n"
            "  ADD PRIMARY KEY (" + q + "id" + q + "),\n"
            "  ADD UNIQUE KEY " + q + "uq_spma_sponsorship_month_version" + q + " (" + q + "sponsorship_id" + q + "," + q + "covered_month" + q + "," + q + "version_no" + q + "),\n"
            "  ADD KEY " + q + "idx_spma_month_latest" + q + " (" + q + "sponsorship_id" + q + "," + q + "covered_month" + q + "," + q + "id" + q + ");"
        )
        auto_pos = text.index("-- AUTO_INCREMENT for dumped tables")
        if index_statement not in text or text.index(index_statement) > auto_pos:
            misplaced = "--\n-- Constraints for table " + q + table + q + "\n--\n" + index_statement + "\n"
            constraint_header = "--\n-- Constraints for table " + q + table + q + "\n--\n"
            text = require_once(text, misplaced, constraint_header, "move agreement indexes out of constraints section")
            header = "-- Indexes for dumped tables\n--\n\n"
            index_block = "--\n-- Indexes for table " + q + table + q + "\n--\n" + index_statement + "\n\n"
            text = require_once(text, header, header + index_block, "agreement indexes section")
        index_pos = text.index(index_statement)
        constraint_pos = text.index("-- Constraints for table " + q + table + q)
        if not (index_pos < auto_pos < constraint_pos):
            raise RuntimeError("Agreement schema index/auto-increment/constraint ordering is invalid.")
        table_start = text.index("CREATE TABLE " + q + table + q)
        table_end = text.index(";", table_start)
        table_block = text[table_start:table_end]
        table_block = table_block.replace("  " + q + "id" + q + " int(10) UNSIGNED NOT NULL AUTO_INCREMENT,", "  " + q + "id" + q + " int(10) UNSIGNED NOT NULL,", 1)
        check_marker = "  " + q + "created_at" + q + " datetime NOT NULL DEFAULT current_timestamp()"
        check_one = q + "chk_spma_sponsorship_amount" + q
        check_two = q + "chk_spma_service_fee_amount" + q
        if check_one not in table_block and check_two not in table_block:
            check_replacement = (
                check_marker + ",\n"
                "  CONSTRAINT " + check_one + " CHECK (agreed_sponsorship_amount >= 0),\n"
                "  CONSTRAINT " + check_two + " CHECK (agreed_service_fee_amount >= 0)\n"
            )
            table_block = require_once(table_block, check_marker, check_replacement, "agreement amount checks")
        elif check_one not in table_block or check_two not in table_block:
            raise RuntimeError("Agreement table has only one of the two required amount checks.")
        text = text[:table_start] + table_block + text[table_end:]
        if "AUTO_INCREMENT" in text[table_start:text.index(";", table_start)]:
            raise RuntimeError("Agreement table must add AUTO_INCREMENT only after its primary key is created.")
        return text

    sponsor_marker = (
        "  `payment_method` varchar(30) NOT NULL DEFAULT 'cash'\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    )
    sponsor_replacement = (
        "  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',\n"
        "  `transaction_date` date DEFAULT NULL,\n"
        "  `receipt_number` varchar(50) DEFAULT NULL,\n"
        "  `reference_number` varchar(100) DEFAULT NULL,\n"
        "  `component_model` enum('legacy','monthly_agreement') NOT NULL DEFAULT 'legacy',\n"
        "  `monthly_agreement_id` int(10) UNSIGNED DEFAULT NULL,\n"
        "  `agreed_sponsorship_amount` decimal(12,2) DEFAULT NULL,\n"
        "  `agreed_service_fee_amount` decimal(12,2) DEFAULT NULL,\n"
        "  `actual_sponsorship_received` decimal(12,2) DEFAULT NULL,\n"
        "  `actual_service_fee_received` decimal(12,2) DEFAULT NULL\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    )
    text = require_once(text, sponsor_marker, sponsor_replacement, "sponsor_payments columns")

    txn_marker = (
        "  `admin_fee_policy_id` int(10) UNSIGNED DEFAULT NULL\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
    )
    txn_replacement = (
        "  `admin_fee_policy_id` int(10) UNSIGNED DEFAULT NULL,\n"
        "  `component_model` enum('legacy','monthly_agreement') NOT NULL DEFAULT 'legacy',\n"
        "  `monthly_agreement_id` int(10) UNSIGNED DEFAULT NULL,\n"
        "  `agreed_sponsorship_amount` decimal(12,2) DEFAULT NULL,\n"
        "  `agreed_service_fee_amount` decimal(12,2) DEFAULT NULL,\n"
        "  `actual_sponsorship_received` decimal(12,2) DEFAULT NULL,\n"
        "  `actual_service_fee_received` decimal(12,2) DEFAULT NULL\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
    )
    text = require_once(text, txn_marker, txn_replacement, "transactions columns")

    table_marker = "-- Indexes for dumped tables\n"
    agreement_table = (
        "--\n-- Table structure for table `sponsorship_monthly_agreements`\n--\n"
        "CREATE TABLE `sponsorship_monthly_agreements` (\n"
        "  `id` int(10) UNSIGNED NOT NULL,\n"
        "  `sponsorship_id` int(10) UNSIGNED NOT NULL,\n"
        "  `covered_month` date NOT NULL COMMENT 'First day of the covered calendar month',\n"
        "  `version_no` int(10) UNSIGNED NOT NULL,\n"
        "  `agreed_sponsorship_amount` decimal(12,2) NOT NULL,\n"
        "  `agreed_service_fee_amount` decimal(12,2) NOT NULL,\n"
        "  `currency_code` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
        "  `meeting_reference` varchar(255) DEFAULT NULL,\n"
        "  `amendment_reason` text DEFAULT NULL,\n"
        "  `created_by` int(10) UNSIGNED NOT NULL,\n"
        "  `created_at` datetime NOT NULL DEFAULT current_timestamp(),\n"
        "  CONSTRAINT `chk_spma_sponsorship_amount` CHECK (agreed_sponsorship_amount >= 0),\n"
        "  CONSTRAINT `chk_spma_service_fee_amount` CHECK (agreed_service_fee_amount >= 0)\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
    )
    agreement_indexes = (
        "--\n-- Indexes for table `sponsorship_monthly_agreements`\n--\n"
        "ALTER TABLE `sponsorship_monthly_agreements`\n"
        "  ADD PRIMARY KEY (`id`),\n"
        "  ADD UNIQUE KEY `uq_spma_sponsorship_month_version` (`sponsorship_id`,`covered_month`,`version_no`),\n"
        "  ADD KEY `idx_spma_month_latest` (`sponsorship_id`,`covered_month`,`id`);\n\n"
    )
    text = require_once(text, table_marker, agreement_table + table_marker + agreement_indexes, "agreement table and indexes")

    sponsor_index = "  ADD KEY `idx_sp_sponsor_id` (`sponsor_id`);"
    text = require_once(
        text, sponsor_index,
        "  ADD KEY `idx_sp_sponsor_id` (`sponsor_id`),\n"
        "  ADD KEY `idx_sp_monthly_agreement` (`monthly_agreement_id`);",
        "sponsor_payments index",
    )
    txn_index = "  ADD KEY `idx_transactions_created_by_status` (`created_by`,`status`);"
    text = require_once(
        text, txn_index,
        "  ADD KEY `idx_transactions_created_by_status` (`created_by`,`status`),\n"
        "  ADD KEY `idx_transactions_monthly_agreement` (`monthly_agreement_id`);",
        "transactions index",
    )

    auto_marker = "-- AUTO_INCREMENT for dumped tables\n--\n"
    text = require_once(
        text, auto_marker,
        auto_marker
        + "--\n-- AUTO_INCREMENT for table `sponsorship_monthly_agreements`\n--\n"
        + "ALTER TABLE `sponsorship_monthly_agreements`\n"
        + "  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;\n\n",
        "agreement auto increment",
    )

    sp_fk = (
        "  ADD CONSTRAINT `fk_sp_transaction` FOREIGN KEY (`transaction_id`) "
        "REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;"
    )
    text = require_once(
        text, sp_fk,
        "  ADD CONSTRAINT `fk_sp_transaction` FOREIGN KEY (`transaction_id`) "
        "REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,\n"
        "  ADD CONSTRAINT `fk_sponsor_payments_spma` FOREIGN KEY (`monthly_agreement_id`) "
        "REFERENCES `sponsorship_monthly_agreements` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;",
        "sponsor_payments agreement FK",
    )
    txn_fk = (
        "  ADD CONSTRAINT `fk_transactions_voided_by` FOREIGN KEY (`voided_by`) "
        "REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;"
    )
    text = require_once(
        text, txn_fk,
        "  ADD CONSTRAINT `fk_transactions_voided_by` FOREIGN KEY (`voided_by`) "
        "REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,\n"
        "  ADD CONSTRAINT `fk_transactions_spma` FOREIGN KEY (`monthly_agreement_id`) "
        "REFERENCES `sponsorship_monthly_agreements` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;",
        "transactions agreement FK",
    )

    constraint_marker = "--\n-- Constraints for table `sponsor_payments`\n--"
    agreement_constraints = (
        "--\n-- Constraints for table `sponsorship_monthly_agreements`\n--\n"
        "ALTER TABLE `sponsorship_monthly_agreements`\n"
        "  ADD CONSTRAINT `fk_spma_sponsorship` FOREIGN KEY (`sponsorship_id`) "
        "REFERENCES `sponsorships` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,\n"
        "  ADD CONSTRAINT `fk_spma_currency` FOREIGN KEY (`currency_code`) "
        "REFERENCES `currencies` (`code`) ON UPDATE CASCADE ON DELETE RESTRICT,\n"
        "  ADD CONSTRAINT `fk_spma_creator` FOREIGN KEY (`created_by`) "
        "REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;\n\n"
    )
    text = require_once(text, constraint_marker, agreement_constraints + constraint_marker, "agreement foreign keys")

    if "service_fee_agreement_id" in text:
        raise RuntimeError("Obsolete service_fee_agreement_id remains after synchronization.")
    return text


def main() -> None:
    original = SCHEMA.read_text(encoding="utf-8")
    updated = synchronize(original)
    if insert_statements(original) != insert_statements(updated):
        raise RuntimeError("Data INSERT statements changed; refusing canonical schema write.")
    if updated != original:
        SCHEMA.write_text(updated, encoding="utf-8", newline="\n")
        print("UPDATED canonical schema with monthly agreement table and 12 legacy-default snapshot columns.")
    else:
        print("Canonical schema already synchronized; no changes.")
    print("Data INSERT statements preserved:", len(insert_statements(original)))


if __name__ == "__main__":
    main()
