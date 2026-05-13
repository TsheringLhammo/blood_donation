-- Migration: reactive handling, donor deferral, and confidential counselling support
-- Run once in MySQL (phpMyAdmin, MySQL CLI, or migration runner).

SET @db_name := DATABASE();

-- 1) Ensure tbldonors has donor registration and deferral columns.
SET @has_weight := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'weight'
);
SET @sql := IF(
    @has_weight = 0,
    'ALTER TABLE tbldonors ADD COLUMN weight DECIMAL(5,2) NOT NULL DEFAULT 45.00 AFTER dzongkhag',
    'SELECT ''tbldonors.weight already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_last_donation_date := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'last_donation_date'
);
SET @sql := IF(
    @has_last_donation_date = 0,
    'ALTER TABLE tbldonors ADD COLUMN last_donation_date DATE NULL AFTER weight',
    'SELECT ''tbldonors.last_donation_date already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_health_tattoo := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'health_tattoo'
);
SET @sql := IF(
    @has_health_tattoo = 0,
    'ALTER TABLE tbldonors ADD COLUMN health_tattoo TINYINT(1) NOT NULL DEFAULT 0 AFTER last_donation_date',
    'SELECT ''tbldonors.health_tattoo already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_health_antibiotics := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'health_antibiotics'
);
SET @sql := IF(
    @has_health_antibiotics = 0,
    'ALTER TABLE tbldonors ADD COLUMN health_antibiotics TINYINT(1) NOT NULL DEFAULT 0 AFTER health_tattoo',
    'SELECT ''tbldonors.health_antibiotics already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_health_surgery := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'health_surgery'
);
SET @sql := IF(
    @has_health_surgery = 0,
    'ALTER TABLE tbldonors ADD COLUMN health_surgery TINYINT(1) NOT NULL DEFAULT 0 AFTER health_antibiotics',
    'SELECT ''tbldonors.health_surgery already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_health_no_cold_flu := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'health_no_cold_flu'
);
SET @sql := IF(
    @has_health_no_cold_flu = 0,
    'ALTER TABLE tbldonors ADD COLUMN health_no_cold_flu TINYINT(1) NOT NULL DEFAULT 0 AFTER health_surgery',
    'SELECT ''tbldonors.health_no_cold_flu already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_consent_medical := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'consent_medical'
);
SET @sql := IF(
    @has_consent_medical = 0,
    'ALTER TABLE tbldonors ADD COLUMN consent_medical TINYINT(1) NOT NULL DEFAULT 0 AFTER health_no_cold_flu',
    'SELECT ''tbldonors.consent_medical already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_health_declaration := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'health_declaration'
);
SET @sql := IF(
    @has_health_declaration = 0,
    'ALTER TABLE tbldonors ADD COLUMN health_declaration JSON NULL AFTER last_donation_date',
    'SELECT ''tbldonors.health_declaration already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_consent := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'consent'
);
SET @sql := IF(
    @has_consent = 0,
    'ALTER TABLE tbldonors ADD COLUMN consent TINYINT(1) NOT NULL DEFAULT 0 AFTER health_declaration',
    'SELECT ''tbldonors.consent already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_emergency_contact_name := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'emergency_contact_name'
);
SET @sql := IF(
    @has_emergency_contact_name = 0,
    'ALTER TABLE tbldonors ADD COLUMN emergency_contact_name VARCHAR(120) NOT NULL DEFAULT '''' AFTER consent',
    'SELECT ''tbldonors.emergency_contact_name already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_emergency_contact_phone := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'emergency_contact_phone'
);
SET @sql := IF(
    @has_emergency_contact_phone = 0,
    'ALTER TABLE tbldonors ADD COLUMN emergency_contact_phone VARCHAR(30) NOT NULL DEFAULT '''' AFTER emergency_contact_name',
    'SELECT ''tbldonors.emergency_contact_phone already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_deferred := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'deferred'
);
SET @sql := IF(
    @has_deferred = 0,
    'ALTER TABLE tbldonors ADD COLUMN deferred TINYINT(1) NOT NULL DEFAULT 0 AFTER emergency_contact_phone',
    'SELECT ''tbldonors.deferred already exists'''
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
    'ALTER TABLE tbldonors ADD COLUMN deferral_reason VARCHAR(255) NULL AFTER deferred',
    'SELECT ''tbldonors.deferral_reason already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_status := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonors'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(
    @has_status = 0,
    'ALTER TABLE tbldonors ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''Pending'' AFTER deferral_reason',
    'SELECT ''tbldonors.status already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Normalize existing donor status values if present.
UPDATE tbldonors SET status = 'Pending' WHERE status IS NULL OR status = '';

-- 2) Ensure tblblood_units supports Discarded status for reactive units.
-- Keep existing statuses and append Discarded.
SET @has_units := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tblblood_units'
);
SET @sql := IF(
    @has_units = 1,
    'ALTER TABLE tblblood_units MODIFY status ENUM(''Available'',''Reserved'',''Issued'',''Expired'',''Rejected'',''Quarantined'',''Discarded'') NOT NULL DEFAULT ''Available''',
    'SELECT ''tblblood_units not found - skipped status update'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Notifications table (if missing).
CREATE TABLE IF NOT EXISTS tblnotifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL,
    role_target   ENUM('admin','doctor','staff','donor') NULL,
    request_id    INT UNSIGNED NULL,
    title         VARCHAR(160) NOT NULL,
    message       VARCHAR(255) NOT NULL,
    severity      ENUM('info','success','warning','critical') NOT NULL DEFAULT 'info',
    channel       ENUM('in_app','email','sms') NOT NULL DEFAULT 'in_app',
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblnotifications_user_id (user_id),
    INDEX idx_tblnotifications_role_target (role_target),
    INDEX idx_tblnotifications_is_read (is_read),
    INDEX idx_tblnotifications_created_at (created_at)
);

-- 4) Donor counselling follow-up table.
CREATE TABLE IF NOT EXISTS tbldonor_counselling (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id           INT UNSIGNED NULL,
    donation_id        INT UNSIGNED NULL,
    counselling_status ENUM('Pending','Contacted','Completed') NOT NULL DEFAULT 'Pending',
    notes              VARCHAR(255) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tbldonor_counselling_donor (donor_id),
    INDEX idx_tbldonor_counselling_donation (donation_id),
    INDEX idx_tbldonor_counselling_status (counselling_status)
);

-- 5) Allow new test vocabulary for eligible/discarded screening outcomes.
SET @has_final_result := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbldonation_tests'
      AND COLUMN_NAME = 'final_result'
);
SET @sql := IF(
    @has_final_result = 1,
    'ALTER TABLE tbldonation_tests MODIFY final_result ENUM(''Pending'',''Eligible'',''Discard'',''Safe'',''Rejected'') NOT NULL DEFAULT ''Pending''',
    'SELECT ''tbldonation_tests.final_result not found - skipped'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
