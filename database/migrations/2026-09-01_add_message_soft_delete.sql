-- Ahl El Kheir Messaging: single-message soft deletion
-- Run once against the active `ahl_el_kheir` database.
-- Messages are intentionally NOT physically deleted because parent/reply
-- relationships must remain intact.

ALTER TABLE messages
    ADD COLUMN deleted_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_by INT(10) UNSIGNED NULL AFTER deleted_at,
    ADD KEY idx_msg_deleted_at (deleted_at),
    ADD KEY idx_msg_deleted_by (deleted_by),
    ADD CONSTRAINT fk_messages_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id)
        ON DELETE SET NULL;
