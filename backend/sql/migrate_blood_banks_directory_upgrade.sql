-- Migration: Upgrade blood bank directory schema for dynamic search/map/inventory views
-- Keeps compatibility with existing table tblblood_banks used elsewhere in the system.

ALTER TABLE tblblood_banks
    ADD COLUMN IF NOT EXISTS emergency_phone VARCHAR(30) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER emergency,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN IF NOT EXISTS services_json JSON NULL AFTER longitude,
    ADD COLUMN IF NOT EXISTS hours_json JSON NULL AFTER services_json,
    ADD COLUMN IF NOT EXISTS directory_status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER status;

UPDATE tblblood_banks
SET emergency_phone = COALESCE(NULLIF(emergency_phone, ''), NULLIF(phone, ''))
WHERE emergency_phone IS NULL OR emergency_phone = '';

UPDATE tblblood_banks
SET directory_status = CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END;

-- Optional coordinates for well-known seeded records.
UPDATE tblblood_banks
SET latitude = 27.4727924, longitude = 89.6392862
WHERE id = 1 AND latitude IS NULL AND longitude IS NULL;

UPDATE tblblood_banks
SET latitude = 26.8588058, longitude = 89.3886278
WHERE id = 2 AND latitude IS NULL AND longitude IS NULL;

CREATE INDEX IF NOT EXISTS idx_tblblood_banks_directory_status ON tblblood_banks (directory_status);
CREATE INDEX IF NOT EXISTS idx_tblblood_banks_dzongkhag ON tblblood_banks (dzongkhag);

-- Canonical directory view using the requested field names.
CREATE OR REPLACE VIEW blood_banks AS
SELECT
    id,
    name,
    dzongkhag,
    address,
    phone,
    COALESCE(hours_json, JSON_OBJECT()) AS hours,
    emergency_phone,
    latitude,
    longitude,
    COALESCE(services_json, JSON_ARRAY()) AS services,
    directory_status AS status
FROM tblblood_banks;
