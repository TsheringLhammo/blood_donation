-- Add missing columns to tbldonor_samples table
ALTER TABLE tbldonor_samples 
ADD COLUMN IF NOT EXISTS test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS admin_finalized TINYINT(1) NOT NULL DEFAULT 0;

-- Add missing type column to tblnotifications table  
ALTER TABLE tblnotifications 
ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'info';

-- Verify
SELECT 'tbldonor_samples columns:' AS table_name;
SHOW COLUMNS FROM tbldonor_samples;

SELECT 'tblnotifications columns:' AS table_name;
SHOW COLUMNS FROM tblnotifications;
