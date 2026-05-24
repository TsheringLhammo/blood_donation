-- Migration: add staff decision columns to tbldonor_samples if missing
ALTER TABLE tbldonor_samples
ADD COLUMN IF NOT EXISTS staff_decision VARCHAR(32) NULL AFTER decision_after_test,
ADD COLUMN IF NOT EXISTS staff_deferral_period VARCHAR(64) NULL AFTER staff_decision,
ADD COLUMN IF NOT EXISTS staff_deferral_reason VARCHAR(255) NULL AFTER staff_deferral_period;

-- Backfill example: copy staff decision note if present in notes (best-effort)
UPDATE tbldonor_samples
SET staff_decision = CASE
    WHEN notes LIKE '%Permanent deferral%' THEN 'permanent'
    WHEN notes LIKE '%Temporary deferral%' THEN 'temporary'
    ELSE staff_decision
END
WHERE staff_decision IS NULL;

SELECT 'done' as migrated;
