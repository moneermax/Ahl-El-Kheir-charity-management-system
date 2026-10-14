-- Preserve the confirmer independently from the employee credited with reactivation.
-- reactivated_by_user_id means the selected actual reactivating employee's user ID.
-- No FK is added so historical attribution survives user-account deletion.
ALTER TABLE winback_campaigns
    ADD COLUMN reactivation_confirmed_by_user_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER reactivated_by_user_id,
    ADD KEY idx_winback_reactivation_confirmer (reactivation_confirmed_by_user_id);
