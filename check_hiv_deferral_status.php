<?php
// Check HIV deferral status - should be permanent not temporary
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 CHECKING HIV DEFERRAL STATUS\n";
    echo "=================================\n\n";
    
    // Find all HIV positive donors
    echo "1️⃣ FINDING HIV POSITIVE DONORS:\n";
    echo "=================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            full_name,
            latest_test_result,
            workflow_status,
            status,
            deferred,
            deferred_until,
            deferral_reason,
            'ISSUE - Should be permanent' as issue_flag
        FROM tbldonors 
        WHERE 
            (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%')
            AND workflow_status != 'decision_made_rejected'
        ORDER BY full_name
    ");
    
    $hivDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($hivDonors)) {
        echo "✅ No HIV deferral issues found\n";
    } else {
        echo "⚠️  Found " . count($hivDonors) . " HIV donors with wrong deferral status:\n";
        foreach ($hivDonors as $donor) {
            echo "❌ {$donor['full_name']}\n";
            echo "   Test Result: {$donor['latest_test_result']}\n";
            echo "   Current Status: {$donor['workflow_status']} ({$donor['status']})\n";
            echo "   Deferred: " . ($donor['deferred'] ? 'Yes' : 'No') . "\n";
            echo "   Until: " . ($donor['deferred_until'] ?? 'N/A') . "\n";
            echo "   Reason: " . ($donor['deferral_reason'] ?? 'N/A') . "\n";
            echo "   Issue: {$donor['issue_flag']}\n";
            echo "\n";
        }
    }
    
    echo "\n2️⃣ CURRENT HIV DEFERRAL RULES:\n";
    echo "================================\n";
    echo "✅ CORRECT RULE: HIV = PERMANENT DEFERRAL\n";
    echo "❌ WRONG RULE: HIV = TEMPORARY DEFERRAL\n";
    echo "\n";
    
    // Also check all positive donors for deferral consistency
    echo "3️⃣ ALL POSITIVE DONORS DEFERRAL STATUS:\n";
    echo "========================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            full_name,
            latest_test_result,
            workflow_status,
            status,
            deferred,
            deferred_until,
            deferral_reason,
            CASE 
                WHEN (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%') 
                     AND workflow_status = 'decision_made_rejected' THEN '✅ CORRECT'
                WHEN (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%') 
                     AND workflow_status != 'decision_made_rejected' THEN '❌ WRONG'
                WHEN (latest_test_result LIKE '%Malaria%' OR deferral_reason LIKE '%Malaria%') 
                     AND workflow_status = 'decision_made_deferred' THEN '✅ CORRECT'
                WHEN (latest_test_result LIKE '%Malaria%' OR deferral_reason LIKE '%Malaria%') 
                     AND workflow_status != 'decision_made_deferred' THEN '❌ WRONG'
                ELSE '✅ OK'
            END as deferral_status
        FROM tbldonors 
        WHERE latest_test_result = 'positive'
        ORDER BY full_name
    ");
    
    $allPositiveDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allPositiveDonors as $donor) {
        $statusIcon = $donor['deferral_status'] == '✅ CORRECT' ? '✅' : '❌';
        echo "$statusIcon {$donor['full_name']}:\n";
        echo "   Test Result: {$donor['latest_test_result']}\n";
        echo "   Workflow Status: {$donor['workflow_status']}\n";
        echo "   Status: {$donor['status']}\n";
        echo "   Deferral Status: {$donor['deferral_status']}\n";
        echo "   Reason: " . ($donor['deferral_reason'] ?? 'N/A') . "\n";
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
