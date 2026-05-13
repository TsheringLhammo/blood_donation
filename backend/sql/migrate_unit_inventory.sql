-- Unit-Level Inventory Migration
-- Enables tracking of individual blood units (bags) with donation ID, expiry, status, and per-unit cross-match results

-- ─── 1. Create tblblood_units table (individual bags) ─────────────────────
CREATE TABLE IF NOT EXISTS tblblood_units (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id         VARCHAR(60)  NOT NULL UNIQUE,
    blood_bank_id       INT UNSIGNED NOT NULL,
    blood_type          VARCHAR(5)   NOT NULL,
    component           VARCHAR(80)  NOT NULL,
    expiry_date         DATE         NOT NULL,
    status              ENUM('Quarantined','Available','Reserved','Issued','Expired','Rejected') NOT NULL DEFAULT 'Quarantined',
    request_id          INT UNSIGNED NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tblblood_units_blood_bank FOREIGN KEY (blood_bank_id) REFERENCES tblblood_banks(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tblblood_units_request FOREIGN KEY (request_id) REFERENCES tblblood_requests(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_tblblood_units_blood_type (blood_type),
    INDEX idx_tblblood_units_status (status),
    INDEX idx_tblblood_units_request_id (request_id),
    INDEX idx_tblblood_units_expiry_date (expiry_date),
    INDEX idx_tblblood_units_donation_id (donation_id)
);

-- ─── 2. Add unique constraint to tbllab_logs (prevent duplicate cross-match per unit+request) ───
ALTER TABLE tbllab_logs 
    ADD CONSTRAINT uq_tbllab_logs_request_unit 
    UNIQUE (request_id, donor_unit_refs);

-- ─── 3. Enhance tblblood_requests with patient demographics ─────────────
ALTER TABLE tblblood_requests
    ADD COLUMN IF NOT EXISTS patient_dob DATE NULL AFTER patient_name,
    ADD COLUMN IF NOT EXISTS patient_age TINYINT UNSIGNED NULL AFTER patient_dob,
    ADD COLUMN IF NOT EXISTS patient_gender ENUM('M','F','Other') NULL AFTER patient_age,
    ADD COLUMN IF NOT EXISTS patient_address VARCHAR(255) NULL AFTER patient_gender;

-- Health check: verify all tables exist
SELECT 'tblblood_units' AS table_name, COUNT(*) AS row_count FROM tblblood_units
UNION ALL
SELECT 'tbllab_logs' AS table_name, COUNT(*) AS row_count FROM tbllab_logs
UNION ALL
SELECT 'tblblood_requests' AS table_name, COUNT(*) AS row_count FROM tblblood_requests;
