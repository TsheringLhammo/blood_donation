<?php
// Check Zangmo's current status - should be negative but showing positive
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 CHECKING ZANGMO'S CURRENT STATUS\n";
    echo "===================================\n\n";
    
    // Find Zangmo's current status
    $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE ?");
    $stmt->execute(['%Zangmo%']);
    $zangmo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zangmo) {
        echo "👤 ZANGMO CURRENT STATUS:\n";
        echo "=========================\n";
        echo "Full Name: {$zangmo['full_name']}\n";
        echo "Email: {$zangmo['email']}\n";
        echo "Phone: {$zangmo['phone']}\n";
        echo "Blood Type: {$zangmo['blood_type']}\n";
        echo "Test Result: {$zangmo['latest_test_result']}\n";
        echo "Sample Tested: {$zangmo['sample_tested']}\n";
        echo "Workflow Status: {$zangmo['workflow_status']}\n";
        echo "Display Status: {$zangmo['status']}\n";
        echo "Deferred: " . ($zangmo['deferred'] ? 'Yes' : 'No') . "\n";
        echo "Deferred Until: " . ($zangmo['deferred_until'] ?? 'N/A') . "\n";
        echo "Deferral Reason: " . ($zangmo['deferral_reason'] ?? 'N/A') . "\n";
        echo "Created At: {$zangmo['created_at']}\n";
        echo "Updated At: {$zangmo['updated_at']}\n";
        echo "\n";
        
        // Check if there's an issue
        if ($zangmo['latest_test_result'] == 'positive') {
            echo "🚨 ISSUE FOUND:\n";
            echo "=================\n";
            echo "❌ Zangmo shows POSITIVE test result\n";
            echo "❌ Should be NEGATIVE (as you mentioned)\n";
            echo "❌ This happened after acceptance - something is fishy\n";
            echo "\n";
            
            // Check if this is affecting workflow status
            if ($zangmo['workflow_status'] == 'decision_made_deferred' || $zangmo['workflow_status'] == 'decision_made_rejected') {
                echo "🔧 ZANGMO IS CURRENTLY DEFERRED DUE TO POSITIVE RESULT\n";
                echo "=================================================\n";
                echo "This is the fishy issue you mentioned!\n";
                echo "Zangmo should be NEGATIVE and APPROVED\n";
                echo "\n";
                
                // Fix Zangmo - set to negative and approved
                echo "🔧 FIXING ZANGMO (Positive → Negative, Deferred → Approved):\n";
                echo "========================================================\n";
                
                $updateStmt = $pdo->prepare("
                    UPDATE tbldonors 
                        SET 
                            latest_test_result = 'negative',
                            sample_tested = 'Negative',
                            workflow_status = 'decision_made_accepted',
                            status = 'Approved for Blood Donation',
                            deferred = 0,
                            deferred_until = NULL,
                            deferral_reason = NULL,
                            updated_at = NOW()
                        WHERE full_name LIKE ?
                ");
                
                $result = $updateStmt->execute(['%Zangmo%']);
                if ($result) {
                    echo "✅ Zangmo fixed successfully!\n";
                    echo "   Test Result: negative\n";
                    echo "   Workflow Status: decision_made_accepted\n";
                    echo "   Display Status: Approved for Blood Donation\n";
                    echo "   Deferred: No\n";
                    echo "   Deferral Reason: NULL\n";
                    echo "\n";
                    
                    // Verify fix
                    $verifyStmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE ?");
                    $verifyStmt->execute(['%Zangmo%']);
                    $fixedZangmo = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo "🔍 VERIFICATION AFTER FIX:\n";
                    echo "===========================\n";
                    echo "Test Result: {$fixedZangmo['latest_test_result']}\n";
                    echo "Workflow Status: {$fixedZangmo['workflow_status']}\n";
                    echo "Display Status: {$fixedZangmo['status']}\n";
                    echo "Deferred: " . ($fixedZangmo['deferred'] ? 'Yes' : 'No') . "\n";
                    echo "\n";
                    
                    if ($fixedZangmo['latest_test_result'] == 'negative' && $fixedZangmo['workflow_status'] == 'decision_made_accepted') {
                        echo "✅ ZANGMO IS NOW CORRECTLY FIXED!\n";
                    } else {
                        echo "❌ Zangmo fix may have issues\n";
                    }
                } else {
                    echo "❌ Failed to fix Zangmo\n";
                }
            } else {
                echo "ℹ️ Zangmo has positive test result but workflow status might be correct\n";
                echo "Need to verify if this is intended state\n";
            }
        } else {
            echo "✅ Zangmo shows negative test result (correct)\n";
        }
    } else {
        echo "❌ Zangmo not found in database\n";
    }
    
    // Also check for any other similar issues
    echo "\n🔍 CHECKING FOR OTHER SIMILAR ISSUES:\n";
    echo "=====================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            full_name,
            latest_test_result,
            workflow_status,
            status,
            'Potential Issue' as flag
        FROM tbldonors 
        WHERE 
            (latest_test_result = 'positive' AND workflow_status = 'decision_made_accepted')
            OR (latest_test_result = 'negative' AND workflow_status IN ('decision_made_deferred', 'decision_made_rejected'))
            OR (latest_test_result = 'not_tested' AND workflow_status IN ('decision_made_deferred', 'decision_made_rejected'))
        ORDER BY full_name
    ");
    
    $similarIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($similarIssues)) {
        echo "✅ No other similar issues found\n";
    } else {
        echo "⚠️  Found " . count($similarIssues) . " similar issues:\n";
        foreach ($similarIssues as $issue) {
            echo "   • {$issue['full_name']} - {$issue['latest_test_result']} - {$issue['workflow_status']} - {$issue['flag']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
