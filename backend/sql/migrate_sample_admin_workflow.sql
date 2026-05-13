USE blood_donation;

-- 1. Donor columns to support sample workflow and admin approval
ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS sample_status ENUM('No sample yet','Sample taken','Tested') NOT NULL DEFAULT 'No sample yet',
    ADD COLUMN IF NOT EXISTS test_result ENUM('Pending','Negative','Positive') NOT NULL DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS eligibility ENUM('Pending','Eligible','Not Eligible') NOT NULL DEFAULT 'Pending';

-- 2. Sample table state columns for approval workflow and rejection details
ALTER TABLE tbldonor_samples
    ADD COLUMN IF NOT EXISTS review_status ENUM('Collected','Pending Admin Approval','Approved','Rejected') NOT NULL DEFAULT 'Collected',
    ADD COLUMN IF NOT EXISTS reactive_tests VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS approved_by_admin_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS approved_by_admin_name VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL;

-- 2b. Allow sample status values that represent reactive/negative workflow outcomes.
ALTER TABLE tbldonor_samples
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Pending';

-- 3. Message logging table for donor notifications and duplicate prevention
CREATE TABLE IF NOT EXISTS message_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT UNSIGNED NOT NULL,
    sample_id INT UNSIGNED NULL,
    admin_id INT UNSIGNED NULL,
    admin_name VARCHAR(120) NULL,
    message_key VARCHAR(80) NOT NULL DEFAULT '',
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

-- 4. Remove legacy latest sample result columns if present
ALTER TABLE tbldonors
    DROP COLUMN IF EXISTS latest_test_result,
    DROP COLUMN IF EXISTS latest_sample_result;

-- 5. Fix missing or empty registration date values
UPDATE tbldonors
SET created_at = NOW()
WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00';

-- 6. Seed sample_status from existing sample rows for current donors
UPDATE tbldonors d
LEFT JOIN (
    SELECT s.donor_id,
           s.review_status,
           s.status AS sample_status,
           s.tested_at
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
    END
WHERE d.id IS NOT NULL;
