-- Durable, idempotent delivery of employee incentive payroll-scheduling notices.
-- The pending outbox row is created in the same transaction as award scheduling.
-- Notification delivery happens after commit and is retried without undoing payroll.

CREATE TABLE IF NOT EXISTS hr_incentive_notification_outbox (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    award_id INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','sent') NOT NULL DEFAULT 'pending',
    notification_id INT UNSIGNED NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_error VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_incentive_notification_award (award_id),
    KEY idx_hr_incentive_notification_pending (status, id),
    KEY idx_hr_incentive_notification_recipient (recipient_user_id),
    CONSTRAINT fk_hr_incentive_notification_award
        FOREIGN KEY (award_id) REFERENCES hr_incentive_awards (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_hr_incentive_notification_user
        FOREIGN KEY (recipient_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
