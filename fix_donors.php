<?php
// Fix the 4 problematic donors
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 FIXING PROBLEMATIC DONORS\n";
    echo "===========================\n\n";
    
    // Fix 1: Henry - Positive (Malaria) should be Temporary Deferral (6 months)
    echo "1️⃣ FIXING HENRY (Positive Malaria → Temporary Deferral):\n";
    echo "---------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'decision_made_deferred',
            deferred = 1,
            deferred_until = DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
            deferral_reason = 'Positive (Malaria)',
            status = 'Temporary Deferral (6 months)'
        WHERE full_name = 'Henry' AND latest_test_result = 'positive'
    ");
    
    $result = $stmt->execute();
    $affected = $stmt->rowCount();
    
    if ($affected > 0) {
        echo "✅ Henry updated successfully\n";
        echo "   New Status: decision_made_deferred\n";
        echo "   Deferred Until: " . date('Y-m-d', strtotime('+6 months')) . "\n";
        echo "   Reason: Positive (Malaria)\n";
    } else {
        echo "❌ Henry not found or already fixed\n";
    }
    echo "\n";
    
    // Fix 2: Nado - Negative should be Approved for Blood Donation
    echo "2️⃣ FIXING NADO (Negative → Approved for Blood Donation):\n";
    echo "---------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'decision_made_accepted',
            deferred = 0,
            deferred_until = NULL,
            deferral_reason = NULL,
            status = 'Approved for Blood Donation'
        WHERE full_name = 'Nado' AND latest_test_result = 'negative'
    ");
    
    $result = $stmt->execute();
    $affected = $stmt->rowCount();
    
    if ($affected > 0) {
        echo "✅ Nado updated successfully\n";
        echo "   New Status: decision_made_accepted\n";
        echo "   Deferred: No\n";
        echo "   Reason: NULL\n";
    } else {
        echo "❌ Nado not found or already fixed\n";
    }
    echo "\n";
    
    // Fix 3: tts - No test result should be Ready for Blood Draw (Stage 2)
    echo "3️⃣ FIXING TTS (No test result → Ready for Blood Draw):\n";
    echo "---------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'approved_for_blood_draw',
            deferred = 0,
            deferred_until = NULL,
            deferral_reason = NULL,
            status = 'Ready for Blood Draw (Stage 2)'
        WHERE full_name = 'tts' AND latest_test_result = 'not_tested'
    ");
    
    $result = $stmt->execute();
    $affected = $stmt->rowCount();
    
    if ($affected > 0) {
        echo "✅ tts updated successfully\n";
        echo "   New Status: approved_for_blood_draw\n";
        echo "   Deferred: No\n";
        echo "   Reason: NULL\n";
    } else {
        echo "❌ tts not found or already fixed\n";
    }
    echo "\n";
    
    // Fix 4: yoyo - Negative should be Approved for Blood Donation
    echo "4️⃣ FIXING YOYO (Negative → Approved for Blood Donation):\n";
    echo "---------------------------------------------------\n";
    
    $stmt = $pdo->prepare("
        UPDATE tbldonors 
        SET 
            workflow_status = 'decision_made_accepted',
            deferred = 0,
            deferred_until = NULL,
            deferral_reason = NULL,
            status = 'Approved for Blood Donation'
        WHERE full_name = 'yoyo' AND latest_test_result = 'negative'
    ");
    
    $result = $stmt->execute();
    $affected = $stmt->rowCount();
    
    if ($affected > 0) {
        echo "✅ yoyo updated successfully\n";
        echo "   New Status: decision_made_accepted\n";
        echo "   Deferred: No\n";
        echo "   Reason: NULL\n";
    } else {
        echo "❌ yoyo not found or already fixed\n";
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
    
    echo "✅ ALL FIXES COMPLETED SUCCESSFULLY!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
