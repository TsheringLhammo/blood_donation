-- ========================================
-- SPECIFIC DONOR FIXES SQL
-- ========================================

-- 1. Fix tt (no test result, wrongly deferred)
UPDATE tbldonors 
SET 
    workflow_status = 'approved_for_blood_draw',
    status = 'Ready for Blood Draw',
    latest_test_result = 'not_tested',
    sample_tested = 'Pending',
    deferred = 0,
    deferred_until = NULL,
    deferral_reason = NULL
WHERE full_name = 'tt';

-- 2. Fix Henry (Malaria - temporary deferral, 6 months default)
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

-- 3. Fix Tshering Gyeltshen (HIV - permanent deferral)
UPDATE tbldonors 
SET 
    workflow_status = 'decision_made_rejected',
    status = 'Permanently Deferred',
    latest_test_result = 'positive',
    sample_tested = 'Reactive',
    deferred = 1,
    deferred_until = NULL,
    deferral_reason = 'Positive (HIV) - Permanent Deferral'
WHERE full_name = 'Tshering Gyeltshen';

-- ========================================
-- VERIFICATION QUERIES
-- ========================================

-- Verify fixes applied
SELECT 
    full_name, 
    latest_test_result, 
    workflow_status, 
    status,
    deferred,
    deferred_until,
    deferral_reason
FROM tbldonors 
WHERE full_name IN ('tt', 'Henry', 'Tshering Gyeltshen')
ORDER BY full_name;
