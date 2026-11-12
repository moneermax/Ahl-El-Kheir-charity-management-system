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
    if "CREATE TABLE `sponsorship_monthly_agreements`" in text:
        required = [
            "`component_model` enum('legacy','monthly_agreement') NOT NULL DEFAULT 'legacy'",
            "`monthly_agreement_id` int(10) UNSIGNED DEFAULT NULL",
            "`agreed_sponsorship_amount` decimal(12,2) DEFAULT NULL",
            "`agreed_service_fee_amount` decimal(12,2) DEFAULT NULL",
            "`actual_sponsorship_received` decimal(12,2) DEFAULT NULL",
            "`actual_service_fee_received` decimal(12,2) DEFAULT NULL",
            "`fk_transactions_spma`",
            "`fk_sponsor_payments_spma`",
        ]
        missing = [item for item in required if item not in text]
        if missing:
            raise RuntimeError("Agreement schema is partially present; missing: " + ", ".join(missing))
        if "service_fee_agreement_id" in text:
            raise RuntimeError("Obsolete service_fee_agreement_id remains in canonical schema.")
        return text

    sponsor_marker = (
        "  `payment_method` varchar(30) NOT NULL DEFAULT 'cash'\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    )
    sponsor_replacement = (
        "  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',\n"
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
        "  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        "  `sponsorship_id` int(10) UNSIGNED NOT NULL,\n"
        "  `covered_month` date NOT NULL COMMENT 'First day of the covered calendar month',\n"
        "  `version_no` int(10) UNSIGNED NOT NULL,\n"
        "  `agreed_sponsorship_amount` decimal(12,2) NOT NULL,\n"
        "  `agreed_service_fee_amount` decimal(12,2) NOT NULL,\n"
        "  `currency_code` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
        "  `meeting_reference` varchar(255) DEFAULT NULL,\n"
        "  `amendment_reason` text DEFAULT NULL,\n"
        "  `created_by` int(10) UNSIGNED NOT NULL,\n"
        "  `created_at` datetime NOT NULL DEFAULT current_timestamp()\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
    )
    text = require_once(text, table_marker, agreement_table + table_marker, "agreement table")

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
        "  ADD PRIMARY KEY (`id`),\n"
        "  ADD UNIQUE KEY `uq_spma_sponsorship_month_version` (`sponsorship_id`,`covered_month`,`version_no`),\n"
        "  ADD KEY `idx_spma_month_latest` (`sponsorship_id`,`covered_month`,`id`);\n"
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
