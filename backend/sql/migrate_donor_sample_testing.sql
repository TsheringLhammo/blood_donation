USE blood_donation;

ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS sample_tested ENUM('Pending','Negative','Reactive','Inconclusive') NOT NULL DEFAULT 'Pending' AFTER status,
    ADD COLUMN IF NOT EXISTS sample_tested_at DATETIME NULL AFTER sample_tested;

ALTER TABLE tblblood_units
    ADD COLUMN IF NOT EXISTS donor_id INT UNSIGNED NULL AFTER donation_id,
    ADD INDEX IF NOT EXISTS idx_tblblood_units_donor_id (donor_id);

CREATE TABLE IF NOT EXISTS tbldonor_samples (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT UNSIGNED NOT NULL,
    collection_date DATE NOT NULL,
    technician VARCHAR(120) NOT NULL,
    status ENUM('Pending','Negative','Reactive','Inconclusive') NOT NULL DEFAULT 'Pending',
    hiv_result VARCHAR(20) NULL,
    hbsag_result VARCHAR(20) NULL,
    hcv_result VARCHAR(20) NULL,
    syphilis_result VARCHAR(20) NULL,
    malaria_result VARCHAR(20) NULL,
    tested_by VARCHAR(120) NULL,
    tested_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tbldonor_samples_donor_id (donor_id),
    INDEX idx_tbldonor_samples_status (status),
    INDEX idx_tbldonor_samples_collection_date (collection_date),
    CONSTRAINT fk_tbldonor_samples_donor FOREIGN KEY (donor_id) REFERENCES tbldonors(id) ON UPDATE CASCADE ON DELETE CASCADE
);
