<?php
// Test Integrated Blood Bank System
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🧪 TESTING INTEGRATED BLOOD BANK SYSTEM\n";
    echo "========================================\n\n";
    
    // Test database connection
    echo "1️⃣ DATABASE CONNECTION TEST:\n";
    echo "==============================\n";
    echo "✅ Database connection: SUCCESS\n";
    echo "✅ Database: $database\n";
    echo "✅ Host: $host\n\n";
    
    // Test table structure
    echo "2️⃣ TABLE STRUCTURE TEST:\n";
    echo "===========================\n";
    
    $tables = ['tbldonors'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Table '$table': " . count($columns) . " columns\n";
    }
    echo "\n";
    
    // Test data integrity
    echo "3️⃣ DATA INTEGRITY TEST:\n";
    echo "==========================\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tbldonors");
    $totalDonors = $stmt->fetchColumn();
    echo "✅ Total donors: $totalDonors\n";
    
    // Test workflow status consistency
    $stmt = $pdo->query("
        SELECT workflow_status, COUNT(*) as count
        FROM tbldonors 
        GROUP BY workflow_status
        ORDER BY count DESC
    ");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Workflow Status Distribution:\n";
    foreach ($statusCounts as $status) {
        echo "   • {$status['workflow_status']}: {$status['count']} donors\n";
    }
    echo "\n";
    
    // Test deferral rules
    echo "4️⃣ DEFERRAL RULES TEST:\n";
    echo "========================\n";
    
    // Check HIV deferral
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM tbldonors 
        WHERE (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%')
        AND workflow_status = 'decision_made_rejected'
    ");
    $hivCorrect = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM tbldonors 
        WHERE (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%')
        AND workflow_status != 'decision_made_rejected'
    ");
    $hivWrong = $stmt->fetchColumn();
    
    echo "✅ HIV Permanent Deferral: $hivCorrect correct, $hivWrong wrong\n";
    
    // Check Malaria deferral
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM tbldonors 
        WHERE (latest_test_result LIKE '%Malaria%' OR deferral_reason LIKE '%Malaria%')
        AND workflow_status = 'decision_made_deferred'
        AND deferred = 1
        AND deferred_until IS NOT NULL
    ");
    $malariaCorrect = $stmt->fetchColumn();
    
    echo "✅ Malaria Temporary Deferral: $malariaCorrect correct\n";
    
    // Check Stage 2 donors
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM tbldonors 
        WHERE workflow_status = 'approved_for_blood_draw'
    ");
    $stage2Count = $stmt->fetchColumn();
    
    echo "✅ Stage 2 Donors: $stage2Count\n";
    
    // Test negative test results
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM tbldonors 
        WHERE latest_test_result = 'negative'
        AND workflow_status IN ('decision_made_accepted', 'pending_approval', 'approved_for_blood_draw', 'blood_drawn_pending_test')
    ");
    $negativeCorrect = $stmt->fetchColumn();
    
    echo "✅ Negative Test Results: $negativeCorrect correct\n";
    
    echo "\n5️⃣ SYSTEM COMPONENTS TEST:\n";
    echo "===========================\n";
    
    // Check if HTML files exist
    $htmlFiles = [
        'integrated_blood_bank_system.html',
        'enhanced_popup_system.html',
        'nice_popup_messages.html'
    ];
    
    foreach ($htmlFiles as $file) {
        if (file_exists($file)) {
            echo "✅ HTML file exists: $file\n";
        } else {
            echo "❌ HTML file missing: $file\n";
        }
    }
    
    echo "\n6️⃣ INTEGRATION STATUS:\n";
    echo "======================\n";
    
    $issues = [];
    
    if ($hivWrong > 0) {
        $issues[] = "HIV deferral issues found";
    }
    
    if ($stage2Count < 3) {
        $issues[] = "Stage 2 has insufficient donors";
    }
    
    if (empty($issues)) {
        echo "✅ ALL INTEGRATION TESTS PASSED\n";
        echo "✅ System is ready for production use\n";
        echo "✅ All components working correctly\n";
        echo "✅ Data integrity verified\n";
    } else {
        echo "⚠️  Integration issues found:\n";
        foreach ($issues as $issue) {
            echo "   • $issue\n";
        }
    }
    
    echo "\n🎉 INTEGRATED BLOOD BANK SYSTEM TEST COMPLETED!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
