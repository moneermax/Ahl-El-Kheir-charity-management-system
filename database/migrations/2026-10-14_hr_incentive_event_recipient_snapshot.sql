-- Snapshot the intended employee recipient when a source event is recorded.
-- The source-event creator (for example, the FM reviewer) is not the reward recipient.
-- IDs are historical snapshots; no FK is added so the ledger retains attribution history.

ALTER TABLE hr_incentive_events
    ADD COLUMN recipient_user_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER source_currency_code,
    ADD COLUMN recipient_employee_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER recipient_user_id,
    ADD KEY idx_hr_incentive_event_recipient (recipient_user_id, recipient_employee_id, status);
