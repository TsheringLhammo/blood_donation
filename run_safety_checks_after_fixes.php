<?php
// Run safety checks after specific fixes
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 SAFETY CHECKS AFTER SPECIFIC FIXES\n";
    echo "========================================\n\n";
    
    // Check 1: Malaria positive but deferred_until is NULL or less than 6 months from now
    echo "1️⃣ MALARIA DEFERRAL ISSUES:\n";
    echo "==============================\n";
    
    $stmt = $pdo->query("
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
            )
    ");
    
    $malariaIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($malariaIssues)) {
        echo "✅ No malaria deferral issues found\n";
    } else {
        foreach ($malariaIssues as $issue) {
            echo "❌ {$issue['full_name']} - {$issue['issue_description']}\n";
        }
    }
    echo "\n";
    
    // Check 2: HIV positive but workflow_status is NOT 'decision_made_rejected'
    echo "2️⃣ HIV DEFERRAL ISSUES:\n";
    echo "============================\n";
    
    $stmt = $pdo->query("
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
            AND workflow_status != 'decision_made_rejected'
    ");
    
    $hivIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($hivIssues)) {
        echo "✅ No HIV deferral issues found\n";
    } else {
        foreach ($hivIssues as $issue) {
            echo "❌ {$issue['full_name']} - {$issue['issue_description']}\n";
        }
    }
    echo "\n";
    
    // Check 3: No test result but deferred = 1
    echo "3️⃣ NO TEST RESULT DEFERRAL ISSUES:\n";
    echo "===================================\n";
    
    $stmt = $pdo->query("
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
            AND deferred = 1
    ");
    
    $noTestIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($noTestIssues)) {
        echo "✅ No test result deferral issues found\n";
    } else {
        foreach ($noTestIssues as $issue) {
            echo "❌ {$issue['full_name']} - {$issue['issue_description']}\n";
        }
    }
    echo "\n";
    
    // Status of the 3 fixed donors
    echo "🔍 STATUS OF FIXED DONORS:\n";
    echo "==========================\n";
    
    $stmt = $pdo->query("
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
        ORDER BY full_name
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "👤 {$row['full_name']}:\n";
        echo "   Test Result: {$row['latest_test_result']}\n";
        echo "   Workflow Status: {$row['workflow_status']}\n";
        echo "   Display Status: {$row['status']}\n";
        echo "   Deferred: " . ($row['deferred'] ? 'Yes' : 'No') . "\n";
        echo "   Until: " . ($row['deferred_until'] ?? 'N/A') . "\n";
        echo "   Reason: " . ($row['deferral_reason'] ?? 'N/A') . "\n";
        echo "\n";
    }
    
    echo "🔍 SAFETY CHECKS COMPLETED\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
