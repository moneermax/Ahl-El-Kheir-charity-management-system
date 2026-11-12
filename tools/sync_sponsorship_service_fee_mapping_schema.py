#!/usr/bin/env python3
"""Synchronize sponsorship service-fee account mapping into the canonical SQL dump.

Only CREATE/ALTER schema statements are changed. Existing data INSERT statements
are asserted byte-for-byte unchanged. This script never applies DDL to a live DB.
"""
from pathlib import Path
import re

SCHEMA = Path("database/ahl_el_kheir.sql")
Q = chr(96)


def require_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one exact schema anchor, found {count}")
    return text.replace(old, new, 1)


def insert_statements(text: str) -> list[str]:
    return re.findall(r"(?ms)^INSERT INTO .*?;(?=\n)", text)


def synchronize(text: str) -> str:
    if "CREATE TABLE " + Q + "sponsorship_monthly_agreements" + Q not in text:
        raise RuntimeError("Monthly agreement schema must be synchronized first.")

    table_name = "sponsorship_service_fee_account_mappings"
    if "CREATE TABLE " + Q + table_name + Q in text:
        required = [
            Q + "service_fee_account_mapping_id" + Q + " int(10) UNSIGNED DEFAULT NULL",
            Q + "service_fee_account_id" + Q + " int(10) UNSIGNED DEFAULT NULL",
            Q + "effective_from" + Q + " date NOT NULL",
            Q + "version_no" + Q + " int(10) UNSIGNED NOT NULL",
            Q + "account_id" + Q + " int(10) UNSIGNED NOT NULL",
            Q + "authorized_by_name" + Q + " varchar(150) NOT NULL",
            Q + "authorization_reference" + Q + " varchar(255) NOT NULL",
            Q + "authorization_note" + Q + " text DEFAULT NULL",
            Q + "created_by" + Q + " int(10) UNSIGNED NOT NULL",
            Q + "uq_ssfam_effective_version" + Q,
            Q + "idx_ssfam_account" + Q,
            Q + "idx_ssfam_effective_latest" + Q,
            Q + "fk_ssfam_account" + Q,
            Q + "fk_ssfam_creator" + Q,
            Q + "fk_transactions_ssfam_mapping" + Q,
            Q + "fk_transactions_ssfam_account" + Q,
            Q + "idx_transactions_service_fee_mapping" + Q,
        ]
        missing = [item for item in required if item not in text]
        if missing:
            raise RuntimeError("Service-fee mapping schema is partially present; missing: " + ", ".join(missing))
        return text

    # Add the immutable posting snapshot fields only to transactions.
    txn_start = text.index("CREATE TABLE " + Q + "transactions" + Q)
    txn_end = text.index(";", txn_start)
    txn_block = text[txn_start:txn_end]
    anchor = "  " + Q + "actual_service_fee_received" + Q + " decimal(12,2) DEFAULT NULL\n"
    replacement = (
        anchor
        + "  " + Q + "service_fee_account_mapping_id" + Q + " int(10) UNSIGNED DEFAULT NULL,\n"
        + "  " + Q + "service_fee_account_id" + Q + " int(10) UNSIGNED DEFAULT NULL\n"
    )
    txn_block = require_once(txn_block, anchor, replacement, "transactions service-fee snapshot columns")
    text = text[:txn_start] + txn_block + text[txn_end:]

    # Add the effective-dated mapping table structure before the index section.
    table_marker = "-- Indexes for dumped tables\n"
    table_block = (
        "--\n-- Table structure for table " + Q + table_name + Q + "\n--\n"
        "CREATE TABLE " + Q + table_name + Q + " (\n"
        "  " + Q + "id" + Q + " int(10) UNSIGNED NOT NULL,\n"
        "  " + Q + "effective_from" + Q + " date NOT NULL,\n"
        "  " + Q + "version_no" + Q + " int(10) UNSIGNED NOT NULL,\n"
        "  " + Q + "account_id" + Q + " int(10) UNSIGNED NOT NULL,\n"
        "  " + Q + "authorized_by_name" + Q + " varchar(150) NOT NULL,\n"
        "  " + Q + "authorization_reference" + Q + " varchar(255) NOT NULL,\n"
        "  " + Q + "authorization_note" + Q + " text DEFAULT NULL,\n"
        "  " + Q + "created_by" + Q + " int(10) UNSIGNED NOT NULL,\n"
        "  " + Q + "created_at" + Q + " datetime NOT NULL DEFAULT current_timestamp()\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
    )
    text = require_once(text, table_marker, table_block + table_marker, "service-fee mapping table")

    # Add mapping and transaction indexes.
    agreement_index = (
        "ALTER TABLE " + Q + "sponsorship_monthly_agreements" + Q + "\n"
        "  ADD PRIMARY KEY (" + Q + "id" + Q + "),\n"
        "  ADD UNIQUE KEY " + Q + "uq_spma_sponsorship_month_version" + Q + " (" + Q + "sponsorship_id" + Q + "," + Q + "covered_month" + Q + "," + Q + "version_no" + Q + "),\n"
        "  ADD KEY " + Q + "idx_spma_month_latest" + Q + " (" + Q + "sponsorship_id" + Q + "," + Q + "covered_month" + Q + "," + Q + "id" + Q + ");"
    )
    mapping_indexes = (
        "--\n-- Indexes for table " + Q + table_name + Q + "\n--\n"
        "ALTER TABLE " + Q + table_name + Q + "\n"
        "  ADD PRIMARY KEY (" + Q + "id" + Q + "),\n"
        "  ADD UNIQUE KEY " + Q + "uq_ssfam_effective_version" + Q + " (" + Q + "effective_from" + Q + "," + Q + "version_no" + Q + "),\n"
        "  ADD KEY " + Q + "idx_ssfam_account" + Q + " (" + Q + "account_id" + Q + "),\n"
        "  ADD KEY " + Q + "idx_ssfam_effective_latest" + Q + " (" + Q + "effective_from" + Q + "," + Q + "version_no" + Q + "," + Q + "id" + Q + ");\n\n"
    )
    text = require_once(text, agreement_index + "\n\n", agreement_index + "\n\n" + mapping_indexes, "service-fee mapping indexes")

    txn_index = (
        "  ADD KEY " + Q + "idx_transactions_monthly_agreement" + Q + " (" + Q + "monthly_agreement_id" + Q + ");"
    )
    text = require_once(
        text, txn_index,
        "  ADD KEY " + Q + "idx_transactions_monthly_agreement" + Q + " (" + Q + "monthly_agreement_id" + Q + "),\n"
        "  ADD KEY " + Q + "idx_transactions_service_fee_mapping" + Q + " (" + Q + "service_fee_account_mapping_id" + Q + ");",
        "transactions service-fee mapping index",
    )

    auto_marker = "-- AUTO_INCREMENT for dumped tables\n--\n"
    mapping_auto = (
        "--\n-- AUTO_INCREMENT for table " + Q + table_name + Q + "\n--\n"
        "ALTER TABLE " + Q + table_name + Q + "\n"
        "  MODIFY " + Q + "id" + Q + " int(10) UNSIGNED NOT NULL AUTO_INCREMENT;\n\n"
    )
    text = require_once(text, auto_marker, auto_marker + mapping_auto, "service-fee mapping auto increment")

    # Add the mapping table's foreign keys before the existing agreement constraints.
    agreement_constraints = (
        "--\n-- Constraints for table " + Q + "sponsorship_monthly_agreements" + Q + "\n--"
    )
    mapping_constraints = (
        "--\n-- Constraints for table " + Q + table_name + Q + "\n--\n"
        "ALTER TABLE " + Q + table_name + Q + "\n"
        "  ADD CONSTRAINT " + Q + "fk_ssfam_account" + Q + " FOREIGN KEY (" + Q + "account_id" + Q + ") REFERENCES " + Q + "accounts" + Q + " (" + Q + "id" + Q + ") ON UPDATE CASCADE ON DELETE RESTRICT,\n"
        "  ADD CONSTRAINT " + Q + "fk_ssfam_creator" + Q + " FOREIGN KEY (" + Q + "created_by" + Q + ") REFERENCES " + Q + "users" + Q + " (" + Q + "id" + Q + ") ON UPDATE CASCADE ON DELETE RESTRICT;\n\n"
    )
    text = require_once(text, agreement_constraints, mapping_constraints + agreement_constraints, "service-fee mapping foreign keys")

    txn_fk = (
        "  ADD CONSTRAINT " + Q + "fk_transactions_spma" + Q + " FOREIGN KEY (" + Q + "monthly_agreement_id" + Q + ") "
        "REFERENCES " + Q + "sponsorship_monthly_agreements" + Q + " (" + Q + "id" + Q + ") ON UPDATE CASCADE ON DELETE RESTRICT;"
    )
    text = require_once(
        text, txn_fk,
        "  ADD CONSTRAINT " + Q + "fk_transactions_spma" + Q + " FOREIGN KEY (" + Q + "monthly_agreement_id" + Q + ") "
        "REFERENCES " + Q + "sponsorship_monthly_agreements" + Q + " (" + Q + "id" + Q + ") ON UPDATE CASCADE ON DELETE RESTRICT,\n"
        "  ADD CONSTRAINT " + Q + "fk_transactions_ssfam_mapping" + Q + " FOREIGN KEY (" + Q + "service_fee_account_mapping_id" + Q + ") "
        "REFERENCES " + Q + table_name + Q + " (" + Q + "id" + Q + ") ON UPDATE CASCADE ON DELETE RESTRICT,\n"
        "  ADD CONSTRAINT " + Q + "fk_transactions_ssfam_account" + Q + " FOREIGN KEY (" + Q + "service_fee_account_id" + Q + ") "
        "REFERENCES " + Q + "accounts" + Q + " (" + Q + "id" + Q + ") ON UPDATE CASCADE ON DELETE RESTRICT;",
        "transactions service-fee mapping foreign keys",
    )

    return text


def main() -> None:
    original = SCHEMA.read_text(encoding="utf-8")
    updated = synchronize(original)
    if insert_statements(original) != insert_statements(updated):
        raise RuntimeError("Data INSERT statements changed; refusing canonical schema write.")
    if updated != original:
        SCHEMA.write_text(updated, encoding="utf-8", newline="\n")
        print("UPDATED canonical schema with effective-dated sponsorship service-fee account mapping.")
    else:
        print("Canonical service-fee mapping schema already synchronized; no changes.")
    print("Data INSERT statements preserved:", len(insert_statements(original)))


if __name__ == "__main__":
    main()
