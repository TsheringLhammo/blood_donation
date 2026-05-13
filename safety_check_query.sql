-- ========================================
-- SAFETY CHECK QUERY FOR INCONSISTENCIES
-- ========================================

-- 1. Malaria positive but deferred_until is NULL or less than 6 months from now
SELECT 
    full_name,
    latest_test_result,
    workflow_status,
    deferred_until,
    deferral_reason,
    'Malaria deferral issue' as issue_type,
    CASE 
        WHEN deferred_until IS NULL THEN 'Malaria positive but no deferral date set'
        WHEN deferred_until < DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 'Malaria deferral less than 6 months'
        ELSE 'OK'
    END as issue_description
FROM tbldonors 
WHERE 
    (latest_test_result LIKE '%Malaria%' OR deferral_reason LIKE '%Malaria%')
    AND (
        deferred_until IS NULL 
        OR deferred_until < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
    );

-- 2. HIV positive but workflow_status is NOT 'decision_made_rejected'
SELECT 
    full_name,
    latest_test_result,
    workflow_status,
    status,
    'HIV deferral issue' as issue_type,
    'HIV positive but not permanently deferred' as issue_description
FROM tbldonors 
WHERE 
    (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%')
    AND workflow_status != 'decision_made_rejected';

-- 3. No test result but deferred = 1
SELECT 
    full_name,
    latest_test_result,
    workflow_status,
    status,
    deferred,
    deferred_until,
    deferral_reason,
    'No test result deferral issue' as issue_type,
    'No test result but donor is deferred' as issue_description
FROM tbldonors 
WHERE 
    latest_test_result = 'not_tested' 
    AND deferred = 1;

-- 4. All inconsistencies combined
SELECT 
    full_name,
    latest_test_result,
    workflow_status,
    status,
    deferred,
    deferred_until,
    deferral_reason,
    CASE 
        WHEN (latest_test_result LIKE '%Malaria%' OR deferral_reason LIKE '%Malaria%') 
             AND (deferred_until IS NULL OR deferred_until < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)) 
        THEN 'Malaria deferral issue'
        WHEN (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%') 
             AND workflow_status != 'decision_made_rejected' 
        THEN 'HIV deferral issue'
        WHEN latest_test_result = 'not_tested' AND deferred = 1 
        THEN 'No test result deferral issue'
        ELSE 'OK'
    END as issue_type
FROM tbldonors 
WHERE 
    (
        (latest_test_result LIKE '%Malaria%' OR deferral_reason LIKE '%Malaria%') 
        AND (deferred_until IS NULL OR deferred_until < DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
    )
    OR (
        (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%') 
        AND workflow_status != 'decision_made_rejected'
    )
    OR (
        latest_test_result = 'not_tested' 
        AND deferred = 1
    );
