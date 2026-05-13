-- Migration: Add issued unit tracking to issue logs
-- Run once on blood_donation database

USE blood_donation;

ALTER TABLE tblissue_logs
    ADD COLUMN IF NOT EXISTS issued_unit_id VARCHAR(60) NULL AFTER component,
    ADD INDEX idx_tblissue_logs_issued_unit_id (issued_unit_id);
