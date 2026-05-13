<?php
// Safety check queries to identify inconsistent data
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 RUNNING SAFETY CHECK QUERIES\n";
    echo "================================\n\n";
    
    // Check 1: Negative test results with wrong workflow status
    echo "1️⃣ NEGATIVE TEST RESULTS WITH WRONG WORKFLOW STATUS:\n";
    echo "---------------------------------------------------\n";
    $stmt = $pdo->query("
        SELECT 
            full_name, 
            latest_test_result, 
            workflow_status, 
            status,
            'Negative test should be decision_made_accepted' as issue
        FROM tbldonors 
        WHERE 
            latest_test_result = 'negative' 
            AND workflow_status NOT IN ('decision_made_accepted', 'pending_approval', 'approved_for_blood_draw', 'blood_drawn_pending_test')
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "✅ No issues found\n";
    } else {
        foreach ($results as $row) {
            echo "❌ {$row['full_name']} - Status: {$row['workflow_status']} - Issue: {$row['issue']}\n";
        }
    }
    echo "\n";
    
    // Check 2: Positive test results with wrong workflow status
    echo "2️⃣ POSITIVE TEST RESULTS WITH WRONG WORKFLOW STATUS:\n";
    echo "---------------------------------------------------\n";
    $stmt = $pdo->query("
        SELECT 
            full_name, 
            latest_test_result, 
            workflow_status, 
            status,
            'Positive test should be deferred or rejected' as issue
        FROM tbldonors 
        WHERE 
            latest_test_result = 'positive' 
            AND workflow_status NOT IN ('decision_made_deferred', 'decision_made_rejected', 'test_result_pending_decision')
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "✅ No issues found\n";
    } else {
        foreach ($results as $row) {
            echo "❌ {$row['full_name']} - Status: {$row['workflow_status']} - Issue: {$row['issue']}\n";
        }
    }
    echo "\n";
    
    // Check 3: No test result with wrong workflow status
    echo "3️⃣ NO TEST RESULT WITH WRONG WORKFLOW STATUS:\n";
    echo "---------------------------------------------------\n";
    $stmt = $pdo->query("
        SELECT 
            full_name, 
            latest_test_result, 
            workflow_status, 
            status,
            'No test result should be pending or approved_for_blood_draw' as issue
        FROM tbldonors 
        WHERE 
            latest_test_result = 'not_tested' 
            AND workflow_status NOT IN ('pending_approval', 'approved_for_blood_draw')
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "✅ No issues found\n";
    } else {
        foreach ($results as $row) {
            echo "❌ {$row['full_name']} - Status: {$row['workflow_status']} - Issue: {$row['issue']}\n";
        }
    }
    echo "\n";
    
    // Check 4: Deferred donors missing deferral reason
    echo "4️⃣ DEFERRED DONORS MISSING DEFERRAL REASON/DATE:\n";
    echo "---------------------------------------------------\n";
    $stmt = $pdo->query("
        SELECT 
            full_name, 
            latest_test_result, 
            workflow_status, 
            status,
            deferred,
            deferred_until,
            deferral_reason,
            'Deferred donor missing deferral reason or date' as issue
        FROM tbldonors 
        WHERE 
            deferred = 1 
            AND (deferral_reason IS NULL OR deferred_until IS NULL)
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "✅ No issues found\n";
    } else {
        foreach ($results as $row) {
            echo "❌ {$row['full_name']} - Reason: " . ($row['deferral_reason'] ?? 'NULL') . " - Until: " . ($row['deferred_until'] ?? 'NULL') . "\n";
        }
    }
    echo "\n";
    
    // Check 5: Expired deferrals
    echo "5️⃣ EXPIRED DEFERRALS NEEDING REVIEW:\n";
    echo "---------------------------------------------------\n";
    $stmt = $pdo->query("
        SELECT 
            full_name, 
            latest_test_result, 
            workflow_status, 
            status,
            deferred_until,
            'Deferral expired - should be reviewed' as issue
        FROM tbldonors 
        WHERE 
            deferred = 1 
            AND deferred_until < CURDATE()
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "✅ No issues found\n";
    } else {
        foreach ($results as $row) {
            echo "❌ {$row['full_name']} - Expired: {$row['deferred_until']}\n";
        }
    }
    echo "\n";
    
    // Check 6: Current status of the 4 problematic donors
    echo "6️⃣ CURRENT STATUS OF PROBLEMATIC DONORS:\n";
    echo "---------------------------------------------------\n";
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
        WHERE full_name IN ('Henry', 'Nado', 'tts', 'yoyo')
        ORDER BY full_name
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "👤 {$row['full_name']}:\n";
        echo "   Test Result: {$row['latest_test_result']}\n";
        echo "   Workflow Status: {$row['workflow_status']}\n";
        echo "   Status: {$row['status']}\n";
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
