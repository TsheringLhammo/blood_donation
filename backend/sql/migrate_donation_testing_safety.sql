-- Donation testing safety migration
-- Target DB: blood_donation

USE blood_donation;

START TRANSACTION;

-- 1) Ensure tbldonation_tests has all required screening and audit columns.
ALTER TABLE tblblood_units
  MODIFY COLUMN status ENUM('Available','Reserved','Issued','Expired','Rejected') NOT NULL DEFAULT 'Available';

ALTER TABLE tbldonation_tests
  MODIFY COLUMN donation_id VARCHAR(60) NOT NULL,
  ADD COLUMN IF NOT EXISTS hiv_result ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
  ADD COLUMN IF NOT EXISTS hbsag_result ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
  ADD COLUMN IF NOT EXISTS hcv_result ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
  ADD COLUMN IF NOT EXISTS syphilis_result ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
  ADD COLUMN IF NOT EXISTS malaria_result ENUM('Negative','Reactive','Not Tested') NOT NULL DEFAULT 'Not Tested',
  ADD COLUMN IF NOT EXISTS final_result ENUM('Pending','Safe','Rejected') NOT NULL DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS remarks VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS tested_by_user_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS tested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 2) Add indexes for quick lookup during issuance validation.
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'tbldonation_tests'
    AND index_name = 'idx_tbldonation_tests_donation'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_tbldonation_tests_donation ON tbldonation_tests (donation_id)',
  'SELECT "idx_tbldonation_tests_donation exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'tbldonation_tests'
    AND index_name = 'idx_tbldonation_tests_donation_final'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_tbldonation_tests_donation_final ON tbldonation_tests (donation_id, final_result)',
  'SELECT "idx_tbldonation_tests_donation_final exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'tblblood_units'
    AND index_name = 'idx_tblblood_units_donation'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_tblblood_units_donation ON tblblood_units (donation_id)',
  'SELECT "idx_tblblood_units_donation exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Optional: enforce one active test row per donation id in this workflow.
-- If you want strict one-row-per-donation behavior, keep this unique key.
-- If your hospital policy allows repeated test attempts, comment it out.
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'tbldonation_tests'
    AND index_name = 'uq_tbldonation_tests_donation'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE UNIQUE INDEX uq_tbldonation_tests_donation ON tbldonation_tests (donation_id)',
  'SELECT "uq_tbldonation_tests_donation exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Logical link between units and test results through donation_id.
-- This view is used by issuance and dashboards.
CREATE OR REPLACE VIEW v_blood_unit_test_status AS
SELECT
    u.id AS unit_db_id,
    u.unit_id,
    CAST(u.donation_id AS CHAR) AS donation_id,
    u.blood_type,
    u.component,
    u.status AS unit_status,
    t.final_result,
    t.tested_at,
    t.tested_by_user_id
FROM tblblood_units u
LEFT JOIN tbldonation_tests t
  ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR);

COMMIT;

-- Verification queries
-- SELECT COUNT(*) AS units_without_donation_link FROM tblblood_units WHERE donation_id IS NULL OR TRIM(CAST(donation_id AS CHAR)) = '';
-- SELECT final_result, COUNT(*) FROM tbldonation_tests GROUP BY final_result;
-- SELECT * FROM v_blood_unit_test_status ORDER BY tested_at DESC LIMIT 20;
