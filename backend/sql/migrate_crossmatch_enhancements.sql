-- Adds richer cross-match tracking fields to tbllab_logs for auditability and traceability.

ALTER TABLE tbllab_logs
    ADD COLUMN IF NOT EXISTS request_id INT UNSIGNED NULL AFTER sample_reference,
    ADD COLUMN IF NOT EXISTS patient_name VARCHAR(160) NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS blood_type VARCHAR(5) NULL AFTER patient_name,
    ADD COLUMN IF NOT EXISTS component VARCHAR(80) NULL AFTER blood_type,
    ADD COLUMN IF NOT EXISTS units_requested SMALLINT UNSIGNED NULL AFTER component,
    ADD COLUMN IF NOT EXISTS donor_unit_refs VARCHAR(255) NULL AFTER units_requested,
    ADD COLUMN IF NOT EXISTS test_parameters TEXT NULL AFTER donor_unit_refs,
    ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL AFTER test_parameters;

ALTER TABLE tbllab_logs
    ADD INDEX idx_tbllab_logs_request_id (request_id),
    ADD INDEX idx_tbllab_logs_result (result),
    ADD INDEX idx_tbllab_logs_created_at (created_at);
