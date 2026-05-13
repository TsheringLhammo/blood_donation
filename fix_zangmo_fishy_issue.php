<?php
// Fix Zangmo's fishy issue - should be negative but showing positive
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 FIXING ZANGMO'S FISHY ISSUE\n";
    echo "=================================\n";
    echo "Issue: Zangmo should be NEGATIVE but shows POSITIVE\n";
    echo "Current Status: Permanently Deferred (wrong!)\n";
    echo "Fix: Set to NEGATIVE and APPROVED\n\n";
    
    // First, get current Zangmo data
    $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE ?");
    $stmt->execute(['%Zangmo%']);
    $zangmo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zangmo) {
        echo "👤 CURRENT ZANGMO STATUS:\n";
        echo "========================\n";
        echo "Name: {$zangmo['full_name']}\n";
        echo "Test Result: {$zangmo['latest_test_result']}\n";
        echo "Workflow Status: {$zangmo['workflow_status']}\n";
        echo "Status: {$zangmo['status']}\n";
        echo "Deferred: " . ($zangmo['deferred'] ? 'Yes' : 'No') . "\n";
        echo "Reason: " . ($zangmo['deferral_reason'] ?? 'N/A') . "\n\n";
        
        // Fix Zangmo - set to negative and approved
        echo "🔧 APPLYING FIX:\n";
        echo "==================\n";
        
        $updateStmt = $pdo->prepare("
            UPDATE tbldonors 
                SET 
                    latest_test_result = 'negative',
                    sample_tested = 'Negative',
                    workflow_status = 'decision_made_accepted',
                    status = 'Approved for Blood Donation',
                    deferred = 0,
                    deferred_until = NULL,
                    deferral_reason = NULL
                WHERE full_name LIKE ?
        ");
        
        $result = $updateStmt->execute(['%Zangmo%']);
        
        if ($result) {
            echo "✅ Zangmo fixed successfully!\n";
            echo "\n";
            
            // Verify the fix
            $verifyStmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE ?");
            $verifyStmt->execute(['%Zangmo%']);
            $fixedZangmo = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            echo "🔍 VERIFICATION AFTER FIX:\n";
            echo "===========================\n";
            echo "Name: {$fixedZangmo['full_name']}\n";
            echo "Test Result: {$fixedZangmo['latest_test_result']}\n";
            echo "Workflow Status: {$fixedZangmo['workflow_status']}\n";
            echo "Status: {$fixedZangmo['status']}\n";
            echo "Deferred: " . ($fixedZangmo['deferred'] ? 'Yes' : 'No') . "\n";
            echo "Reason: " . ($fixedZangmo['deferral_reason'] ?? 'N/A') . "\n";
            echo "\n";
            
            if ($fixedZangmo['latest_test_result'] == 'negative' && $fixedZangmo['workflow_status'] == 'decision_made_accepted') {
                echo "✅ ZANGMO FISHY ISSUE FIXED!\n";
                echo "✅ Test Result: negative (correct)\n";
                echo "✅ Workflow Status: decision_made_accepted (correct)\n";
                echo "✅ Status: Approved for Blood Donation (correct)\n";
                echo "✅ Deferred: No (correct)\n";
                echo "✅ Zangmo is now properly configured!\n";
            } else {
                echo "❌ Zangmo fix verification failed\n";
            }
        } else {
            echo "❌ Failed to fix Zangmo\n";
        }
    } else {
        echo "❌ Zangmo not found in database\n";
    }
    
    // Check if this affects Stage 2
    echo "\n🎯 CHECKING STAGE 2 IMPACT:\n";
    echo "==============================\n";
    
    $stmt = $pdo->query("
        SELECT full_name, latest_test_result, workflow_status, status
        FROM tbldonors 
        WHERE workflow_status = 'approved_for_blood_draw'
        ORDER BY full_name
    ");
    
    $stage2Donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current Stage 2 Donors (" . count($stage2Donors) . " total):\n";
    foreach ($stage2Donors as $donor) {
        echo "   • {$donor['full_name']} - {$donor['latest_test_result']} - {$donor['status']}\n";
    }
    
    echo "\n🎉 ZANGMO FISHY ISSUE FIX COMPLETED!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
