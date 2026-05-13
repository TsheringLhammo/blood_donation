-- Blood Donation System - Database Migration
-- Run these statements ONE BY ONE in phpMyAdmin (paste each into a separate query box)

-- 1. Add test_status column to tbldonor_samples
ALTER TABLE tbldonor_samples 
ADD COLUMN IF NOT EXISTS test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending';

-- 2. Add admin_finalized column to tbldonor_samples
ALTER TABLE tbldonor_samples 
ADD COLUMN IF NOT EXISTS admin_finalized TINYINT(1) NOT NULL DEFAULT 0;

-- 3. Add defer_until column to tbldonors
ALTER TABLE tbldonors
ADD COLUMN IF NOT EXISTS defer_until DATE NULL;

-- 4. Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    sample_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sample_id (sample_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Backfill test_status for existing samples
UPDATE tbldonor_samples 
SET test_status = CASE 
    WHEN COALESCE(hiv_result, '') LIKE '%Reactive%'
      OR COALESCE(hbsag_result, '') LIKE '%Reactive%'
      OR COALESCE(hcv_result, '') LIKE '%Reactive%'
      OR COALESCE(syphilis_result, '') LIKE '%Reactive%'
      OR COALESCE(malaria_result, '') LIKE '%Reactive%'
    THEN 'deferred'
    ELSE 'eligible'
END
WHERE test_status = 'pending' AND (
    hiv_result IS NOT NULL OR hbsag_result IS NOT NULL OR 
    hcv_result IS NOT NULL OR syphilis_result IS NOT NULL OR 
    malaria_result IS NOT NULL
);

-- 6. Verify columns were added
SHOW COLUMNS FROM tbldonor_samples;
SHOW COLUMNS FROM tbldonors;
SHOW TABLES LIKE 'notifications';
