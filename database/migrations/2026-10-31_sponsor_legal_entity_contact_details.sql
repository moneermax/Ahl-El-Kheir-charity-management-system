-- AHL EL KHEIR
-- Capture legal-entity representative and contact preferences on applications
-- and carry them into the sponsor profile. Safe to re-run on MariaDB 10.4.
-- Apply as a migration only; never execute DDL from a web request.

ALTER TABLE sponsor_requests
    ADD COLUMN IF NOT EXISTS contact_person_name VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS contact_person_position VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS preferred_contact_method ENUM('call','whatsapp','email','any') DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS preferred_contact_time VARCHAR(100) DEFAULT NULL;

ALTER TABLE sponsors
    ADD COLUMN IF NOT EXISTS contact_person_name VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS contact_person_position VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS preferred_contact_method ENUM('call','whatsapp','email','any') DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS preferred_contact_time VARCHAR(100) DEFAULT NULL;
