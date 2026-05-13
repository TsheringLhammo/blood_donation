<?php
// Re-check HIV deferral status after previous fix attempt
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 RE-CHECKING HIV DEFERRAL STATUS\n";
    echo "===================================\n\n";
    
    // Check all positive donors with deferral status
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
                ELSE 'N/A'
            END as hiv_status
        FROM tbldonors 
        WHERE latest_test_result = 'positive'
        ORDER BY full_name
    ");
    
    $allPositiveDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 ALL POSITIVE DONORS CURRENT STATUS:\n";
    echo "========================================\n";
    
    foreach ($allPositiveDonors as $donor) {
        $statusIcon = $donor['hiv_status'] == '✅ CORRECT' ? '✅' : ($donor['hiv_status'] == '❌ WRONG' ? '❌' : '⚠️');
        echo "$statusIcon {$donor['full_name']}:\n";
        echo "   Test Result: {$donor['latest_test_result']}\n";
        echo "   Workflow Status: {$donor['workflow_status']}\n";
        echo "   Display Status: {$donor['status']}\n";
        echo "   Deferred: " . ($donor['deferred'] ? 'Yes' : 'No') . "\n";
        echo "   Until: " . ($donor['deferred_until'] ?? 'N/A') . "\n";
        echo "   Reason: " . ($donor['deferral_reason'] ?? 'N/A') . "\n";
        echo "   HIV Status: {$donor['hiv_status']}\n";
        echo "\n";
    }
    
    // Count HIV vs Non-HIV positive donors
    $hivCount = 0;
    $nonHivCount = 0;
    $correctHivCount = 0;
    $wrongHivCount = 0;
    
    foreach ($allPositiveDonors as $donor) {
        if ($donor['hiv_status'] != 'N/A') {
            $hivCount++;
            if ($donor['hiv_status'] == '✅ CORRECT') {
                $correctHivCount++;
            } else {
                $wrongHivCount++;
            }
        } else {
            $nonHivCount++;
        }
    }
    
    echo "📈 POSITIVE DONORS BREAKDOWN:\n";
    echo "==============================\n";
    echo "Total Positive Donors: " . count($allPositiveDonors) . "\n";
    echo "HIV Positive: $hivCount\n";
    echo "Non-HIV Positive: $nonHivCount\n";
    echo "HIV Correctly Deferred: $correctHivCount\n";
    echo "HIV Wrongly Deferred: $wrongHivCount\n";
    echo "\n";
    
    if ($wrongHivCount == 0) {
        echo "✅ ALL HIV POSITIVE DONORS ARE CORRECTLY PERMANENTLY DEFERRED!\n";
    } else {
        echo "❌ $wrongHivCount HIV POSITIVE DONORS HAVE WRONG DEFERRAL STATUS\n";
        echo "These should be permanently deferred but are not.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
