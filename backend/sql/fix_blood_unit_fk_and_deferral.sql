-- Blood donation repair migration
-- Fixes tblblood_units -> tbldonors foreign key issues and adds deferral/notification support.

USE blood_donation;

SET @db_name := DATABASE();

-- Ensure the core tables use InnoDB.
ALTER TABLE tbldonors ENGINE = InnoDB;
ALTER TABLE tblblood_units ENGINE = InnoDB;
ALTER TABLE tbldonation_tests ENGINE = InnoDB;
ALTER TABLE tblnotifications ENGINE = InnoDB;

-- Clean invalid donor_id values before adding the foreign key.
UPDATE tblblood_units bu
LEFT JOIN tbldonors d ON d.id = bu.donor_id
SET bu.donor_id = NULL
WHERE bu.donor_id IS NOT NULL
  AND d.id IS NULL;

-- Normalize donor_id type to match tbldonors.id.
SET @donor_id_is_unsigned := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblblood_units'
      AND COLUMN_NAME = 'donor_id'
      AND COLUMN_TYPE LIKE '%unsigned%'
);

SET @sql := IF(
    @donor_id_is_unsigned = 1,
    'ALTER TABLE tblblood_units MODIFY donor_id INT UNSIGNED NULL',
    'ALTER TABLE tblblood_units MODIFY donor_id INT UNSIGNED NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add missing donor deferral columns.
SET @has_deferred_until := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'deferred_until'
);
SET @sql := IF(
    @has_deferred_until = 0,
    'ALTER TABLE tbldonors ADD COLUMN deferred_until DATE NULL AFTER deferred',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_deferral_reason := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'deferral_reason'
);
SET @sql := IF(
    @has_deferral_reason = 0,
    'ALTER TABLE tbldonors ADD COLUMN deferral_reason VARCHAR(255) NULL AFTER deferred_until',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_donor_status := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @has_donor_status = 0,
    'ALTER TABLE tbldonors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''Pending'' AFTER deferral_reason',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make sure the donor key column is indexed before adding the FK.
SET @has_donor_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblblood_units'
      AND INDEX_NAME = 'idx_tblblood_units_donor_id'
);
SET @sql := IF(
    @has_donor_index = 0,
    'CREATE INDEX idx_tblblood_units_donor_id ON tblblood_units (donor_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop any existing foreign key on tblblood_units.donor_id.
SET @existing_fk := (
    SELECT CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblblood_units'
      AND COLUMN_NAME = 'donor_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);
SET @sql := IF(
    @existing_fk IS NOT NULL,
    CONCAT('ALTER TABLE tblblood_units DROP FOREIGN KEY `', @existing_fk, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Re-create the foreign key.
ALTER TABLE tblblood_units
    ADD CONSTRAINT fk_bloodunit_donor
    FOREIGN KEY (donor_id) REFERENCES tbldonors(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;

-- Test results table for donation screening.
CREATE TABLE IF NOT EXISTS tbldonation_tests (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id VARCHAR(60) NOT NULL,
    donor_id    INT UNSIGNED NOT NULL,
    hiv         VARCHAR(20) NOT NULL,
    hbsag       VARCHAR(20) NOT NULL,
    hcv         VARCHAR(20) NOT NULL,
    syphilis    VARCHAR(20) NOT NULL,
    malaria     VARCHAR(20) NOT NULL,
    test_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    technician  VARCHAR(120) NULL,
    remarks     VARCHAR(255) NULL,
    final_result VARCHAR(30) NULL,
    INDEX idx_tbldonation_tests_donation_id (donation_id),
    INDEX idx_tbldonation_tests_donor_id (donor_id),
    CONSTRAINT fk_tbldonation_tests_donor FOREIGN KEY (donor_id) REFERENCES tbldonors(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

SET @has_test_donor := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'donor_id'
);
SET @sql := IF(@has_test_donor = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN donor_id INT UNSIGNED NOT NULL AFTER donation_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_hiv := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'hiv'
);
SET @sql := IF(@has_test_hiv = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN hiv VARCHAR(20) NOT NULL AFTER donor_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_hbsag := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'hbsag'
);
SET @sql := IF(@has_test_hbsag = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN hbsag VARCHAR(20) NOT NULL AFTER hiv', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_hcv := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'hcv'
);
SET @sql := IF(@has_test_hcv = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN hcv VARCHAR(20) NOT NULL AFTER hbsag', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_syphilis := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'syphilis'
);
SET @sql := IF(@has_test_syphilis = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN syphilis VARCHAR(20) NOT NULL AFTER hcv', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_malaria := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'malaria'
);
SET @sql := IF(@has_test_malaria = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN malaria VARCHAR(20) NOT NULL AFTER syphilis', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_date := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'test_date'
);
SET @sql := IF(@has_test_date = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN test_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER malaria', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_technician := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'technician'
);
SET @sql := IF(@has_test_technician = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN technician VARCHAR(120) NULL AFTER test_date', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_remarks := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'remarks'
);
SET @sql := IF(@has_test_remarks = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN remarks VARCHAR(255) NULL AFTER technician', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_test_final := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'final_result'
);
SET @sql := IF(@has_test_final = 0, 'ALTER TABLE tbldonation_tests ADD COLUMN final_result VARCHAR(30) NULL AFTER remarks', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Notifications table. Keep compatibility columns used by the current app.
CREATE TABLE IF NOT EXISTS tblnotifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id      INT UNSIGNED NULL,
    admin_id      INT UNSIGNED NULL,
    user_id       INT UNSIGNED NULL,
    role_target   ENUM('admin','doctor','staff','donor') NULL,
    type          VARCHAR(30) NOT NULL DEFAULT 'info',
    title         VARCHAR(160) NOT NULL DEFAULT '',
    message       VARCHAR(255) NOT NULL,
    severity      ENUM('info','success','warning','critical') NOT NULL DEFAULT 'info',
    channel       ENUM('in_app','email','sms','both') NOT NULL DEFAULT 'in_app',
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblnotifications_donor_id (donor_id),
    INDEX idx_tblnotifications_admin_id (admin_id),
    INDEX idx_tblnotifications_user_id (user_id),
    INDEX idx_tblnotifications_role_target (role_target),
    INDEX idx_tblnotifications_type (type),
    INDEX idx_tblnotifications_is_read (is_read),
    INDEX idx_tblnotifications_created_at (created_at)
);

SET @notification_columns := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblnotifications'
      AND COLUMN_NAME = 'donor_id'
);
SET @sql := IF(@notification_columns = 0, 'ALTER TABLE tblnotifications ADD COLUMN donor_id INT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @notification_columns := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblnotifications'
      AND COLUMN_NAME = 'admin_id'
);
SET @sql := IF(@notification_columns = 0, 'ALTER TABLE tblnotifications ADD COLUMN admin_id INT UNSIGNED NULL AFTER donor_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @notification_columns := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblnotifications'
      AND COLUMN_NAME = 'type'
);
SET @sql := IF(@notification_columns = 0, 'ALTER TABLE tblnotifications ADD COLUMN type VARCHAR(30) NOT NULL DEFAULT ''info'' AFTER role_target', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @notification_columns := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblnotifications'
      AND COLUMN_NAME = 'is_read'
);
SET @sql := IF(@notification_columns = 0, 'ALTER TABLE tblnotifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER channel', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Link donor notifications to donor records if we can.
UPDATE tblnotifications n
LEFT JOIN tbldonors d ON d.id = n.donor_id
SET n.donor_id = NULL
WHERE n.donor_id IS NOT NULL
  AND d.id IS NULL;
