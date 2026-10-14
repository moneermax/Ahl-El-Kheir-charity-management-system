-- Preserve the user who completed the successful win-back transition.
-- handled_by remains the mutable operational case handler; these fields are
-- first-transition attribution for later incentive qualification.
-- Deliberately no FK: retain the historical actor ID if the user is removed.

ALTER TABLE winback_campaigns
    ADD COLUMN reactivated_by_user_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER handled_by,
    ADD COLUMN reactivated_at DATETIME NULL DEFAULT NULL AFTER reactivated_by_user_id,
    ADD KEY idx_winback_reactivated_by (reactivated_by_user_id);
