-- Add test_status column to tbldonor_samples if it doesn't exist
ALTER TABLE tbldonor_samples
ADD COLUMN IF NOT EXISTS test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending' AFTER malaria;

-- Verify the column was added
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'tbldonor_samples' 
AND COLUMN_NAME = 'test_status';
