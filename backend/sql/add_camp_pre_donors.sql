-- Phase 1: Camp Request + Pre-Registered Donor List
-- Adds camp_code (CAMP-YYYY-XXXX) to tblblood_camps and creates a
-- per-camp pre-registered donor table that organizations fill in when
-- submitting the camp request.

SET @col := (SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'tblblood_camps'
               AND column_name = 'camp_code');
SET @sql := IF(@col = 0,
               'ALTER TABLE tblblood_camps
                  ADD COLUMN camp_code VARCHAR(20) NULL AFTER id,
                  ADD UNIQUE INDEX uniq_camp_code (camp_code)',
               'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tblcamp_pre_donors (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    camp_id      INT UNSIGNED NOT NULL,
    donor_name   VARCHAR(120) NOT NULL,
    cid_number   VARCHAR(20)  NULL,
    blood_group  VARCHAR(5)   NULL,
    phone_number VARCHAR(20)  NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_camp_pre_donor_camp FOREIGN KEY (camp_id)
        REFERENCES tblblood_camps(id) ON DELETE CASCADE,
    INDEX idx_camp_pre_donor_camp (camp_id)
);

-- Backfill camp_code for existing rows.
UPDATE tblblood_camps
   SET camp_code = CONCAT('CAMP-', YEAR(created_at), '-', LPAD(id, 4, '0'))
 WHERE camp_code IS NULL;
