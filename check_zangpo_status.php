<?php
// Check Zangpo's current status - should be negative but showing positive
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 CHECKING ZANGPO'S CURRENT STATUS\n";
    echo "===================================\n\n";
    
    // Find Zangpo's current status
    $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE '%Zangpo%'");
    $stmt->execute();
    $zangpo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zangpo) {
        echo "👤 ZANGPO CURRENT STATUS:\n";
        echo "=========================\n";
        echo "Full Name: {$zangpo['full_name']}\n";
        echo "Email: {$zangpo['email']}\n";
        echo "Phone: {$zangpo['phone']}\n";
        echo "Blood Type: {$zangpo['blood_type']}\n";
        echo "Test Result: {$zangpo['latest_test_result']}\n";
        echo "Sample Tested: {$zangpo['sample_tested']}\n";
        echo "Workflow Status: {$zangpo['workflow_status']}\n";
        echo "Display Status: {$zangpo['status']}\n";
        echo "Deferred: " . ($zangpo['deferred'] ? 'Yes' : 'No') . "\n";
        echo "Deferred Until: " . ($zangpo['deferred_until'] ?? 'N/A') . "\n";
        echo "Deferral Reason: " . ($zangpo['deferral_reason'] ?? 'N/A') . "\n";
        echo "Created At: {$zangpo['created_at']}\n";
        echo "Updated At: {$zangpo['updated_at']}\n";
        echo "\n";
        
        // Check if there's an issue
        if ($zangpo['latest_test_result'] == 'positive') {
            echo "🚨 ISSUE FOUND:\n";
            echo "================\n";
            echo "❌ Zangpo shows POSITIVE test result\n";
            echo "❌ Should be NEGATIVE (as you mentioned)\n";
            echo "❌ This happened after acceptance - something is fishy\n";
            echo "\n";
            
            // Check if this is affecting workflow status
            if ($zangpo['workflow_status'] == 'decision_made_deferred' || $zangpo['workflow_status'] == 'decision_made_rejected') {
                echo "🔧 ZANGPO IS CURRENTLY DEFERRED DUE TO POSITIVE RESULT\n";
                echo "=================================================\n";
                echo "This is the fishy issue you mentioned!\n";
                echo "Zangpo should be NEGATIVE and APPROVED\n";
                echo "\n";
                
                // Fix Zangpo - set to negative and approved
                echo "🔧 FIXING ZANGPO (Positive → Negative, Deferred → Approved):\n";
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
                    WHERE full_name LIKE '%Zangpo%'
                ");
                
                $result = $updateStmt->execute();
                if ($result) {
                    echo "✅ Zangpo fixed successfully!\n";
                    echo "   Test Result: negative\n";
                    echo "   Workflow Status: decision_made_accepted\n";
                    echo "   Display Status: Approved for Blood Donation\n";
                    echo "   Deferred: No\n";
                    echo "   Deferral Reason: NULL\n";
                    echo "\n";
                    
                    // Verify the fix
                    $verifyStmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE '%Zangpo%'");
                    $verifyStmt->execute();
                    $fixedZangpo = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo "🔍 VERIFICATION AFTER FIX:\n";
                    echo "===========================\n";
                    echo "Test Result: {$fixedZangpo['latest_test_result']}\n";
                    echo "Workflow Status: {$fixedZangpo['workflow_status']}\n";
                    echo "Display Status: {$fixedZangpo['status']}\n";
                    echo "Deferred: " . ($fixedZangpo['deferred'] ? 'Yes' : 'No') . "\n";
                    echo "\n";
                    
                    if ($fixedZangpo['latest_test_result'] == 'negative' && $fixedZangpo['workflow_status'] == 'decision_made_accepted') {
                        echo "✅ ZANGPO IS NOW CORRECTLY FIXED!\n";
                    } else {
                        echo "❌ Zangpo fix may have issues\n";
                    }
                } else {
                    echo "❌ Failed to fix Zangpo\n";
                }
            } else {
                echo "ℹ️ Zangpo has positive test result but workflow status might be correct\n";
                echo "Need to verify if this is the intended state\n";
            }
        } else {
            echo "✅ Zangpo shows negative test result (correct)\n";
        }
    } else {
        echo "❌ Zangpo not found in database\n";
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
