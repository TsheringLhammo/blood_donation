USE blood_donation;

-- 1. Donor workflow columns for sample testing and approval.
ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS sample_status ENUM('No sample yet','Sample taken','Tested') NOT NULL DEFAULT 'No sample yet',
    ADD COLUMN IF NOT EXISTS test_result ENUM('Pending','Negative','Positive') NOT NULL DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS eligibility ENUM('Pending','Eligible','Not Eligible') NOT NULL DEFAULT 'Pending';

-- 2. Sample workflow table and approval metadata.
CREATE TABLE IF NOT EXISTS tbldonor_samples (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT UNSIGNED NOT NULL,
    collection_date DATE NOT NULL,
    technician VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    hiv_result VARCHAR(20) NULL,
    hbsag_result VARCHAR(20) NULL,
    hcv_result VARCHAR(20) NULL,
    syphilis_result VARCHAR(20) NULL,
    malaria_result VARCHAR(20) NULL,
    tested_by VARCHAR(120) NULL,
    notes TEXT NULL,
    reactive_tests VARCHAR(255) NULL,
    review_status ENUM('Collected','Pending Admin Approval','Approved','Rejected') NOT NULL DEFAULT 'Collected',
    approved_by_admin_id INT UNSIGNED NULL,
    approved_by_admin_name VARCHAR(120) NULL,
    approved_at DATETIME NULL,
    tested_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_donor_id (donor_id),
    INDEX idx_review_status (review_status),
    INDEX idx_collection_date (collection_date),
    INDEX idx_tested_at (tested_at)
);

-- 3. Message log table to prevent duplicate notifications and store audit context.
CREATE TABLE IF NOT EXISTS message_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT UNSIGNED NOT NULL,
    sample_id INT UNSIGNED NULL,
    admin_id INT UNSIGNED NULL,
    admin_name VARCHAR(120) NULL,
    message_key VARCHAR(80) NOT NULL,
    message_type ENUM('sample_test_result','system_alert','other') NOT NULL DEFAULT 'sample_test_result',
    channel ENUM('email','sms','both','in_app') NOT NULL DEFAULT 'both',
    message_content TEXT NOT NULL,
    sent_date DATETIME NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    error_message VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_logs_donor_id (donor_id),
    INDEX idx_message_logs_sample_id (sample_id),
    INDEX idx_message_logs_admin_id (admin_id),
    INDEX idx_message_logs_status (status),
    UNIQUE KEY uk_message_logs_donor_sample_key (donor_id, sample_id, message_key)
);

-- 4. Remove legacy sample result columns that can conflict with new workflow.
ALTER TABLE tbldonors
    DROP COLUMN IF EXISTS latest_test_result,
    DROP COLUMN IF EXISTS latest_sample_result;

-- 5. Fix missing registration timestamps on donors.
UPDATE tbldonors
SET created_at = NOW()
WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00';

-- 6. Optional: update existing sample table status type if it used an older enum.
ALTER TABLE tbldonor_samples
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Pending';

-- 7. Optional: Seed donor sample_status and test_result from current sample rows.
UPDATE tbldonors d
LEFT JOIN (
    SELECT s.donor_id,
           s.review_status,
           s.status AS sample_status,
           s.hiv_result,
           s.hbsag_result,
           s.hcv_result,
           s.syphilis_result,
           s.malaria_result
    FROM tbldonor_samples s
    WHERE s.id = (
        SELECT s2.id
        FROM tbldonor_samples s2
        WHERE s2.donor_id = s.donor_id
        ORDER BY COALESCE(s2.tested_at, s2.collection_date, s2.id) DESC
        LIMIT 1
    )
) latest ON latest.donor_id = d.id
SET d.sample_status = CASE
        WHEN latest.donor_id IS NULL THEN 'No sample yet'
        WHEN latest.review_status = 'Collected' THEN 'Sample taken'
        ELSE 'Tested'
    END,
    d.test_result = CASE
        WHEN latest.hiv_result = 'Reactive' OR latest.hbsag_result = 'Reactive' OR latest.hcv_result = 'Reactive' OR latest.syphilis_result = 'Reactive' OR latest.malaria_result = 'Reactive' THEN 'Positive'
        WHEN latest.donor_id IS NOT NULL THEN 'Negative'
        ELSE d.test_result
    END,
    d.eligibility = CASE
        WHEN latest.hiv_result = 'Reactive' OR latest.hbsag_result = 'Reactive' OR latest.hcv_result = 'Reactive' OR latest.syphilis_result = 'Reactive' OR latest.malaria_result = 'Reactive' THEN 'Not Eligible'
        WHEN latest.donor_id IS NOT NULL THEN 'Eligible'
        ELSE d.eligibility
    END
WHERE d.id IS NOT NULL;
