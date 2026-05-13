USE blood_donation;

ALTER TABLE tblnotifications
    ADD COLUMN IF NOT EXISTS donor_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS admin_id INT UNSIGNED NULL AFTER donor_id,
    ADD COLUMN IF NOT EXISTS type VARCHAR(30) NOT NULL DEFAULT 'info' AFTER role_target,
    ADD COLUMN IF NOT EXISTS action_url VARCHAR(255) NULL AFTER message,
    ADD COLUMN IF NOT EXISTS action_type VARCHAR(80) NULL AFTER action_url,
    ADD INDEX IF NOT EXISTS idx_tblnotifications_donor_id (donor_id),
    ADD INDEX IF NOT EXISTS idx_tblnotifications_admin_id (admin_id),
    ADD INDEX IF NOT EXISTS idx_tblnotifications_type (type);