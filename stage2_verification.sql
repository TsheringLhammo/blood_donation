-- ========================================
-- STAGE 2 CONFIRMATION QUERY
-- ========================================

-- After all fixes, Stage 2 should show ONLY donors with workflow_status = 'approved_for_blood_draw'

SELECT 
    full_name, 
    email, 
    phone, 
    blood_type,
    latest_test_result,
    workflow_status,
    status
FROM tbldonors 
WHERE workflow_status = 'approved_for_blood_draw'
ORDER BY full_name;

-- Expected donors in Stage 2 after fixes:
-- 1. tt (fixed - no test result, ready for blood draw)
-- 2. Tshering yangdon (already correct - no test result, ready for blood draw)
-- 3. Sonam (already correct - no test result, ready for blood draw)
-- 4. Tshering Lhamo (already correct - negative test, ready for blood draw)
-- 5. tts (already correct - no test result, ready for blood draw)

-- Count verification
SELECT 
    COUNT(*) as stage2_count,
    workflow_status
FROM tbldonors 
WHERE workflow_status = 'approved_for_blood_draw'
GROUP BY workflow_status;

-- Verify no incorrect donors in Stage 2
SELECT 
    full_name,
    latest_test_result,
    workflow_status,
    status,
    'Should NOT be in Stage 2' as issue
FROM tbldonors 
WHERE workflow_status = 'approved_for_blood_draw'
AND (
    latest_test_result = 'positive' 
    OR (latest_test_result = 'negative' AND workflow_status != 'approved_for_blood_draw')
    OR deferred = 1
)
ORDER BY full_name;
