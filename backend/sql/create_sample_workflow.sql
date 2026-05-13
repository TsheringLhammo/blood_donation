USE blood_donation;

-- Create tbldonor_samples table
CREATE TABLE IF NOT EXISTS tbldonor_samples (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    collection_date DATE NOT NULL,
    technician VARCHAR(120) NOT NULL,
    status ENUM('Pending','Processed') NOT NULL DEFAULT 'Pending',
    hiv_result VARCHAR(20) NULL,
    hbsag_result VARCHAR(20) NULL,
    hcv_result VARCHAR(20) NULL,
    syphilis_result VARCHAR(20) NULL,
    malaria_result VARCHAR(20) NULL,
    tested_by VARCHAR(120) NULL,
    tested_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_donor_id (donor_id),
    INDEX idx_status (status),
    INDEX idx_collection_date (collection_date)
);

-- Add sample-related columns to tbldonors if they don't exist
ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS last_negative_sample_date DATE NULL,
    ADD COLUMN IF NOT EXISTS sample_eligible BOOLEAN DEFAULT FALSE;

-- Ensure deferred columns exist for deferral logic
ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS deferred BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS deferred_until DATE NULL,
    ADD COLUMN IF NOT EXISTS deferral_reason VARCHAR(255) NULL;
