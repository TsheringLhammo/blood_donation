-- Migration: Canonical dynamic blood bank directory
-- Creates dzongkhags + blood_banks schema, links units to blood banks,
-- and prevents duplicate cross-match rows by (sample_reference, unit_id).

USE blood_donation;

CREATE TABLE IF NOT EXISTS dzongkhags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(120) NOT NULL UNIQUE,
    name_dz VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO dzongkhags (name_en, name_dz) VALUES
    ('Bumthang', 'Bumthang'),
    ('Chhukha', 'Chhukha'),
    ('Dagana', 'Dagana'),
    ('Gasa', 'Gasa'),
    ('Haa', 'Haa'),
    ('Lhuentse', 'Lhuentse'),
    ('Mongar', 'Mongar'),
    ('Paro', 'Paro'),
    ('Pemagatshel', 'Pemagatshel'),
    ('Punakha', 'Punakha'),
    ('Samdrup Jongkhar', 'Samdrup Jongkhar'),
    ('Samtse', 'Samtse'),
    ('Sarpang', 'Sarpang'),
    ('Thimphu', 'Thimphu'),
    ('Trashigang', 'Trashigang'),
    ('Trashiyangtse', 'Trashiyangtse'),
    ('Trongsa', 'Trongsa'),
    ('Tsirang', 'Tsirang'),
    ('Wangdue Phodrang', 'Wangdue Phodrang'),
    ('Zhemgang', 'Zhemgang')
ON DUPLICATE KEY UPDATE
    name_dz = VALUES(name_dz);

CREATE TABLE IF NOT EXISTS blood_banks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    dzongkhag_id INT UNSIGNED NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    hours JSON NULL,
    emergency_phone VARCHAR(30) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    services JSON NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    legacy_bank_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blood_banks_name_dzongkhag (name, dzongkhag_id),
    UNIQUE KEY uq_blood_banks_legacy_bank_id (legacy_bank_id),
    INDEX idx_blood_banks_status (status),
    INDEX idx_blood_banks_dzongkhag_id (dzongkhag_id),
    CONSTRAINT fk_blood_banks_dzongkhag FOREIGN KEY (dzongkhag_id)
        REFERENCES dzongkhags(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Backfill canonical blood_banks table from existing tblblood_banks when available.
INSERT INTO blood_banks (
    name,
    dzongkhag_id,
    address,
    phone,
    hours,
    emergency_phone,
    latitude,
    longitude,
    services,
    status,
    legacy_bank_id
)
SELECT
    b.name,
    d.id,
    b.address,
    b.phone,
    CASE
        WHEN JSON_VALID(COALESCE(b.hours_json, '')) THEN b.hours_json
        WHEN b.hours IS NULL OR b.hours = '' THEN NULL
        ELSE JSON_OBJECT('legacy', b.hours)
    END AS hours_json_value,
    COALESCE(NULLIF(COALESCE(b.emergency_phone, ''), ''), b.phone) AS emergency_phone,
    b.latitude,
    b.longitude,
    CASE
        WHEN JSON_VALID(COALESCE(b.services_json, '')) THEN b.services_json
        ELSE JSON_ARRAY()
    END AS services_json_value,
    CASE
        WHEN COALESCE(b.directory_status, '') IN ('active', 'inactive') THEN b.directory_status
        WHEN COALESCE(b.is_active, 1) = 1 THEN 'active'
        ELSE 'inactive'
    END AS canonical_status,
    b.id
FROM tblblood_banks b
JOIN dzongkhags d ON LOWER(d.name_en) = LOWER(b.dzongkhag)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    dzongkhag_id = VALUES(dzongkhag_id),
    address = VALUES(address),
    phone = VALUES(phone),
    hours = VALUES(hours),
    emergency_phone = VALUES(emergency_phone),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    services = VALUES(services),
    status = VALUES(status);

-- Ensure units table (if present) has a blood_bank_id link.
SET @units_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units'
);
SET @units_has_bank_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' AND COLUMN_NAME = 'blood_bank_id'
);
SET @sql_units_bank := IF(
    @units_exists > 0 AND @units_has_bank_id = 0,
    'ALTER TABLE units ADD COLUMN blood_bank_id INT UNSIGNED NULL',
    'SELECT 1'
);
PREPARE stmt_units_bank FROM @sql_units_bank;
EXECUTE stmt_units_bank;
DEALLOCATE PREPARE stmt_units_bank;

-- Cross-match dedupe support in tbllab_logs.
SET @tbllab_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbllab_logs'
);
SET @tbllab_has_unit_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbllab_logs' AND COLUMN_NAME = 'unit_id'
);
SET @sql_add_tbllab_unit := IF(
    @tbllab_exists > 0 AND @tbllab_has_unit_id = 0,
    'ALTER TABLE tbllab_logs ADD COLUMN unit_id VARCHAR(60) NULL AFTER sample_reference',
    'SELECT 1'
);
PREPARE stmt_add_tbllab_unit FROM @sql_add_tbllab_unit;
EXECUTE stmt_add_tbllab_unit;
DEALLOCATE PREPARE stmt_add_tbllab_unit;

SET @tbllab_has_uq := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbllab_logs'
      AND INDEX_NAME = 'uq_tbllab_logs_sample_unit'
);
SET @sql_add_tbllab_uq := IF(
    @tbllab_exists > 0 AND @tbllab_has_uq = 0,
    'ALTER TABLE tbllab_logs ADD CONSTRAINT uq_tbllab_logs_sample_unit UNIQUE (sample_reference, unit_id)',
    'SELECT 1'
);
PREPARE stmt_add_tbllab_uq FROM @sql_add_tbllab_uq;
EXECUTE stmt_add_tbllab_uq;
DEALLOCATE PREPARE stmt_add_tbllab_uq;

-- Also support alternate table name tblab_logs if that is used in a deployment.
SET @tblab_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblab_logs'
);
SET @tblab_has_unit_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblab_logs' AND COLUMN_NAME = 'unit_id'
);
SET @sql_add_tblab_unit := IF(
    @tblab_exists > 0 AND @tblab_has_unit_id = 0,
    'ALTER TABLE tblab_logs ADD COLUMN unit_id VARCHAR(60) NULL AFTER sample_reference',
    'SELECT 1'
);
PREPARE stmt_add_tblab_unit FROM @sql_add_tblab_unit;
EXECUTE stmt_add_tblab_unit;
DEALLOCATE PREPARE stmt_add_tblab_unit;

SET @tblab_has_uq := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblab_logs'
      AND INDEX_NAME = 'uq_tblab_logs_sample_unit'
);
SET @sql_add_tblab_uq := IF(
    @tblab_exists > 0 AND @tblab_has_uq = 0,
    'ALTER TABLE tblab_logs ADD CONSTRAINT uq_tblab_logs_sample_unit UNIQUE (sample_reference, unit_id)',
    'SELECT 1'
);
PREPARE stmt_add_tblab_uq FROM @sql_add_tblab_uq;
EXECUTE stmt_add_tblab_uq;
DEALLOCATE PREPARE stmt_add_tblab_uq;

SELECT 'ok' AS migration_status;