-- ============================================================
-- BLOOD DONATION SYSTEM - SQL FIX SCRIPTS (Copy-Paste Ready)
-- ============================================================
-- ⚠️  BACKUP YOUR DATABASE BEFORE RUNNING ANY OF THESE!
-- ⚠️  RUN SAFETY CHECKS FIRST (see Part 1 in DATA_FIX_PACKAGE.md)
-- ============================================================

-- ============================================================
-- PART 1: SAFETY CHECK QUERIES (READ-ONLY)
-- ============================================================

-- CHECK 1: HIV Donors Not Permanently Deferred
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  deferral_reason,
  deferred_until
FROM tbldonors 
WHERE deferral_reason LIKE '%HIV%' 
  AND workflow_status != 'decision_made_rejected'
ORDER BY full_name;

-- CHECK 2: Stage 2 Donors with Wrong Test Result
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  sample_tested
FROM tbldonors 
WHERE workflow_status = 'approved_for_blood_draw' 
  AND latest_test_result != 'not_tested'
ORDER BY full_name;

-- CHECK 3: Negative Donors with Deferred Status
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  deferred,
  deferral_reason
FROM tbldonors 
WHERE latest_test_result = 'negative' 
  AND deferred = 1
ORDER BY full_name;

-- CHECK 4: Malaria Donors Without Future Deferral Date
SELECT 
  id,
  full_name,
  workflow_status,
  latest_test_result,
  deferral_reason,
  deferred_until,
  DATEDIFF(deferred_until, CURDATE()) as days_remaining
FROM tbldonors 
WHERE deferral_reason LIKE '%Malaria%'
  AND workflow_status = 'decision_made_deferred'
  AND (deferred_until IS NULL OR deferred_until < CURDATE())
ORDER BY full_name;

-- ============================================================
-- PART 2: INDIVIDUAL FIX SCRIPTS (Run one at a time)
-- ============================================================

-- ============================================================
-- FIX A: Stage 2 Donors - Reset to "Not Tested"
-- ============================================================
UPDATE tbldonors 
SET 
  latest_test_result = 'not_tested',
  sample_tested = 'Pending'
WHERE workflow_status = 'approved_for_blood_draw' 
  AND latest_test_result = 'negative';

-- Verify FIX A
SELECT full_name, workflow_status, latest_test_result, sample_tested
FROM tbldonors
WHERE workflow_status = 'approved_for_blood_draw'
ORDER BY full_name;

-- ============================================================
-- FIX B: HIV Donor - Enforce Permanent Deferral
-- ============================================================
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_rejected',
  status = 'Permanently Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = NULL,
  deferral_reason = 'Positive (HIV) - Permanent Deferral'
WHERE full_name = 'Tshering sonam';

-- Verify FIX B
SELECT full_name, workflow_status, status, latest_test_result, deferred_until, deferral_reason
FROM tbldonors
WHERE full_name = 'Tshering sonam';

-- ============================================================
-- FIX C: Malaria Donor - Set Temporary Deferral (6 months)
-- ============================================================
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_deferred',
  status = 'Temporarily Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
  deferral_reason = 'Positive (Malaria) - Temporary deferral 6 months'
WHERE full_name = 'Henry';

-- Verify FIX C
SELECT full_name, workflow_status, status, latest_test_result, deferred_until, deferral_reason
FROM tbldonors
WHERE full_name = 'Henry';

-- ============================================================
-- FIX D: Negative Donors with Wrong Status - Reset to Approved
-- ============================================================
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_accepted',
  status = 'Approved Donor',
  latest_test_result = 'negative',
  deferred = 0,
  deferred_until = NULL,
  deferral_reason = NULL
WHERE full_name = 'Yedam Lham';

UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_accepted',
  status = 'Approved Donor',
  latest_test_result = 'negative',
  deferred = 0,
  deferred_until = NULL,
  deferral_reason = NULL
WHERE full_name = 'Lhamo';

-- Verify FIX D
SELECT full_name, workflow_status, status, latest_test_result, deferred, deferral_reason
FROM tbldonors
WHERE full_name IN ('Yedam Lham', 'Lhamo')
ORDER BY full_name;

-- ============================================================
-- PART 3: ALL FIXES COMBINED (Run ONLY after testing individual fixes)
-- ============================================================
-- FIX 1: Stage 2 Donors - Reset to "Not Tested"
UPDATE tbldonors 
SET latest_test_result = 'not_tested', sample_tested = 'Pending'
WHERE workflow_status = 'approved_for_blood_draw' AND latest_test_result = 'negative';

-- FIX 2: HIV Donor - Permanent Deferral
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_rejected',
  status = 'Permanently Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = NULL,
  deferral_reason = 'Positive (HIV) - Permanent Deferral'
WHERE full_name = 'Tshering sonam';

-- FIX 3: Malaria Donor - Temporary Deferral (6 months)
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_deferred',
  status = 'Temporarily Deferred',
  latest_test_result = 'positive',
  sample_tested = 'Reactive',
  deferred = 1,
  deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
  deferral_reason = 'Positive (Malaria) - Temporary deferral 6 months'
WHERE full_name = 'Henry';

-- FIX 4: Negative Donors - Reset to Approved
UPDATE tbldonors 
SET 
  workflow_status = 'decision_made_accepted',
  status = 'Approved Donor',
  latest_test_result = 'negative',
  deferred = 0,
  deferred_until = NULL,
  deferral_reason = NULL
WHERE full_name IN ('Yedam Lham', 'Lhamo');

-- ============================================================
-- FINAL VERIFICATION: Show all affected donors
-- ============================================================
SELECT 
  full_name,
  workflow_status,
  latest_test_result,
  status,
  deferred,
  deferred_until,
  deferral_reason
FROM tbldonors
WHERE full_name IN (
  'Gaki Pem', 
  'Rinchen', 
  'Tshering', 
  'Dorji Wangmo', 
  'Tshering sonam', 
  'Henry', 
  'Yedam Lham', 
  'Lhamo'
)
ORDER BY full_name;
