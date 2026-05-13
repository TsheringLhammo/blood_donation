USE blood_donation;

ALTER TABLE tbldonor_samples
    ADD COLUMN IF NOT EXISTS test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending' AFTER malaria,
    ADD COLUMN IF NOT EXISTS admin_finalized TINYINT(1) NOT NULL DEFAULT 0 AFTER test_status;

ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS defer_until DATE NULL AFTER status;

UPDATE tbldonor_samples
SET test_status = CASE
    WHEN LOWER(COALESCE(hiv, '')) IN ('reactive', 'positive')
      OR LOWER(COALESCE(hbsag, '')) IN ('reactive', 'positive')
      OR LOWER(COALESCE(hcv, '')) IN ('reactive', 'positive')
      OR LOWER(COALESCE(syphilis, '')) IN ('reactive', 'positive')
      OR LOWER(COALESCE(malaria, '')) IN ('reactive', 'positive')
    THEN 'deferred'
    ELSE 'eligible'
END
WHERE test_status = 'pending';
