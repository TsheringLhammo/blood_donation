<?php
// Execute specific donor fixes
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 EXECUTING SPECIFIC DONOR FIXES\n";
    echo "===================================\n\n";
    
    // Fix 1: tt (no test result, wrongly deferred)
    echo "1️⃣ FIXING TT (No test result, wrongly deferred):\n";
    echo "----------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'approved_for_blood_draw',
            status = 'Ready for Blood Draw',
            latest_test_result = 'not_tested',
            sample_tested = 'Pending',
            deferred = 0,
            deferred_until = NULL,
            deferral_reason = NULL
        WHERE full_name = 'tt'
    ");
    
    $result = $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ tt fixed successfully\n";
        echo "   New Status: Ready for Blood Draw (Stage 2)\n";
        echo "   Test Result: not_tested\n";
        echo "   Deferred: No\n";
    } else {
        echo "❌ tt not found or already fixed\n";
    }
    echo "\n";
    
    // Fix 2: Henry (Malaria - temporary deferral, 6 months default)
    echo "2️⃣ FIXING HENRY (Malaria - Temporary Deferral):\n";
    echo "----------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'decision_made_deferred',
            status = 'Temporarily Deferred',
            latest_test_result = 'positive',
            sample_tested = 'Reactive',
            deferred = 1,
            deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
            deferral_reason = 'Positive (Malaria) - Temporary deferral 6 months'
        WHERE full_name = 'Henry'
    ");
    
    $result = $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ Henry fixed successfully\n";
        echo "   New Status: Temporarily Deferred\n";
        echo "   Test Result: positive (Malaria)\n";
        echo "   Deferred Until: " . date('Y-m-d', strtotime('+6 months')) . "\n";
        echo "   Reason: Positive (Malaria) - Temporary deferral 6 months\n";
    } else {
        echo "❌ Henry not found or already fixed\n";
    }
    echo "\n";
    
    // Fix 3: Tshering Gyeltshen (HIV - permanent deferral)
    echo "3️⃣ FIXING TSHERING GYELTSHEN (HIV - Permanent Deferral):\n";
    echo "-----------------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'decision_made_rejected',
            status = 'Permanently Deferred',
            latest_test_result = 'positive',
            sample_tested = 'Reactive',
            deferred = 1,
            deferred_until = NULL,
            deferral_reason = 'Positive (HIV) - Permanent Deferral'
        WHERE full_name = 'Tshering Gyeltshen'
    ");
    
    $result = $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ Tshering Gyeltshen fixed successfully\n";
        echo "   New Status: Permanently Deferred\n";
        echo "   Test Result: positive (HIV)\n";
        echo "   Deferred: Yes (Permanent)\n";
        echo "   Reason: Positive (HIV) - Permanent Deferral\n";
    } else {
        echo "❌ Tshering Gyeltshen not found or already fixed\n";
    }
    echo "\n";
    
    // Verify all fixes
    echo "🔍 VERIFYING FIXES:\n";
    echo "==================\n";
    
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
    
    echo "✅ SPECIFIC DONOR FIXES COMPLETED!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
