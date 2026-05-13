-- Migration: Make Diagnosis Mandatory for Blood Requests
-- Purpose: Update existing blood request records to have valid diagnosis values
-- Date: 2026-04-20
-- Author: Blood Bank System Admin

-- This migration addresses the "Not recorded" issue in Patient Transfusion History
-- by backfilling existing records with appropriate placeholder or inferred diagnoses.

USE blood_donation;

-- Step 1: Show current status before migration
-- Count records with NULL or empty diagnosis
SELECT 
  COUNT(*) as total_requests,
  SUM(CASE WHEN diagnosis IS NULL OR diagnosis = '' THEN 1 ELSE 0 END) as null_diagnosis_count,
  SUM(CASE WHEN diagnosis IS NOT NULL AND diagnosis != '' THEN 1 ELSE 0 END) as valid_diagnosis_count
FROM tblblood_requests;

-- Step 2: Update NULL diagnosis values to "Pending - Please review"
-- This is a safe placeholder that clearly indicates staff action is needed
UPDATE tblblood_requests 
SET diagnosis = 'Pending - Please review'
WHERE diagnosis IS NULL OR diagnosis = '';

-- Verify the update
SELECT COUNT(*) as updated_records FROM tblblood_requests 
WHERE diagnosis = 'Pending - Please review';

-- Step 3: Optional - Review records by urgency to help prioritize manual updates
SELECT 
  id, 
  request_code, 
  patient_name, 
  urgency, 
  component, 
  units_requested, 
  reason_for_transfusion,
  diagnosis,
  created_at
FROM tblblood_requests 
WHERE diagnosis = 'Pending - Please review'
ORDER BY urgency DESC, created_at DESC
LIMIT 20;

-- MANUAL FOLLOW-UP INSTRUCTIONS:
-- 1. Review the above records
-- 2. For each record, determine the actual diagnosis from:
--    a. The "reason_for_transfusion" field (if available)
--    b. Patient medical history
--    c. Requesting doctor's notes
-- 3. Update the diagnosis field with the correct clinical diagnosis
-- 4. See staff manual below for common diagnoses

-- Example manual updates:
-- UPDATE tblblood_requests SET diagnosis = 'Trauma / Hemorrhage' WHERE id = 123;
-- UPDATE tblblood_requests SET diagnosis = 'Postpartum Hemorrhage' WHERE id = 124;
-- UPDATE tblblood_requests SET diagnosis = 'Anemia' WHERE id = 125;

-- Step 4: Log the migration
-- You can add a note to your system log here if available
-- INSERT INTO system_migration_log (migration_name, status, notes) VALUES 
-- ('migrate_diagnosis_mandatory', 'completed', 'Updated NULL diagnosis values to placeholder.');
