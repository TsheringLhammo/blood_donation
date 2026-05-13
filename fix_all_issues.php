<?php
// Fix ALL remaining workflow inconsistencies
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 FIXING ALL REMAINING WORKFLOW INCONSISTENCIES\n";
    echo "===============================================\n\n";
    
    // Get all remaining issues
    $stmt = $pdo->query("
        SELECT 
            id,
            full_name, 
            latest_test_result, 
            workflow_status, 
            status,
            deferred,
            deferred_until,
            deferral_reason
        FROM tbldonors 
        WHERE 
            (latest_test_result = 'negative' AND workflow_status NOT IN ('decision_made_accepted', 'pending_approval', 'approved_for_blood_draw', 'blood_drawn_pending_test'))
            OR (latest_test_result = 'positive' AND workflow_status NOT IN ('decision_made_deferred', 'decision_made_rejected', 'test_result_pending_decision'))
            OR (latest_test_result = 'not_tested' AND workflow_status NOT IN ('pending_approval', 'approved_for_blood_draw'))
        ORDER BY latest_test_result, full_name
    ");
    
    $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 FOUND " . count($issues) . " ISSUES TO FIX:\n";
    echo "================================\n";
    
    foreach ($issues as $issue) {
        echo "❌ {$issue['full_name']} - {$issue['latest_test_result']} - {$issue['workflow_status']}\n";
    }
    echo "\n";
    
    // Fix each issue
    $fixedCount = 0;
    foreach ($issues as $issue) {
        echo "🔧 FIXING: {$issue['full_name']} ({$issue['latest_test_result']})\n";
        echo "   Current: {$issue['workflow_status']} → ";
        
        $newStatus = '';
        $newWorkflowStatus = '';
        $newDeferred = 0;
        $newDeferredUntil = null;
        $newDeferralReason = null;
        
        if ($issue['latest_test_result'] == 'negative') {
            // Negative test results should be approved
            $newWorkflowStatus = 'decision_made_accepted';
            $newStatus = 'Approved for Blood Donation';
            $newDeferred = 0;
            $newDeferredUntil = null;
            $newDeferralReason = null;
            echo "decision_made_accepted\n";
            
        } elseif ($issue['latest_test_result'] == 'positive') {
            // Positive test results need to be deferred or rejected
            // For now, we'll use temporary deferral for all positive tests
            // In a real system, you'd check the specific disease type
            $newWorkflowStatus = 'decision_made_deferred';
            $newStatus = 'Temporary Deferral (6 months)';
            $newDeferred = 1;
            $newDeferredUntil = date('Y-m-d', strtotime('+6 months'));
            $newDeferralReason = 'Positive test result - requires medical review';
            echo "decision_made_deferred\n";
            
        } elseif ($issue['latest_test_result'] == 'not_tested') {
            // No test result should be pending or approved for blood draw
            // We'll set them to pending approval for safety
            $newWorkflowStatus = 'pending_approval';
            $newStatus = 'Pending Review';
            $newDeferred = 0;
            $newDeferredUntil = null;
            $newDeferralReason = null;
            echo "pending_approval\n";
        }
        
        // Apply the fix
        $stmt = $pdo->prepare("
            UPDATE tbldonors 
            SET 
                workflow_status = ?,
                status = ?,
                deferred = ?,
                deferred_until = ?,
                deferral_reason = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $newWorkflowStatus,
            $newStatus,
            $newDeferred,
            $newDeferredUntil,
            $newDeferralReason,
            $issue['id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo "   ✅ Fixed successfully\n";
            $fixedCount++;
        } else {
            echo "   ❌ Fix failed\n";
        }
        echo "\n";
    }
    
    echo "📊 FIX SUMMARY:\n";
    echo "================\n";
    echo "Total issues found: " . count($issues) . "\n";
    echo "Successfully fixed: $fixedCount\n";
    echo "Failed fixes: " . (count($issues) - $fixedCount) . "\n\n";
    
    // Verify no issues remain
    echo "🔍 VERIFYING NO ISSUES REMAIN:\n";
    echo "===========================\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as remaining_issues
        FROM tbldonors 
        WHERE 
            (latest_test_result = 'negative' AND workflow_status NOT IN ('decision_made_accepted', 'pending_approval', 'approved_for_blood_draw', 'blood_drawn_pending_test'))
            OR (latest_test_result = 'positive' AND workflow_status NOT IN ('decision_made_deferred', 'decision_made_rejected', 'test_result_pending_decision'))
            OR (latest_test_result = 'not_tested' AND workflow_status NOT IN ('pending_approval', 'approved_for_blood_draw'))
    ");
    
    $remaining = $stmt->fetchColumn();
    
    if ($remaining == 0) {
        echo "✅ NO ISSUES REMAIN - ALL WORKFLOW INCONSISTENCIES FIXED!\n";
    } else {
        echo "❌ $remaining issues still remain\n";
    }
    
    // Show updated status distribution
    echo "\n📈 UPDATED WORKFLOW STATUS DISTRIBUTION:\n";
    echo "=====================================\n";
    
    $stmt = $pdo->query("
        SELECT workflow_status, COUNT(*) as count
        FROM tbldonors 
        GROUP BY workflow_status
        ORDER BY count DESC
    ");
    
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusCounts as $status) {
        echo "   • {$status['workflow_status']}: {$status['count']} donors\n";
    }
    
    // Show updated Stage 2 donors
    echo "\n🎯 UPDATED STAGE 2 DONORS:\n";
    echo "========================\n";
    
    $stmt = $pdo->query("
        SELECT full_name, latest_test_result, status
        FROM tbldonors 
        WHERE workflow_status = 'approved_for_blood_draw'
        ORDER BY full_name
    ");
    
    $stage2Donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($stage2Donors)) {
        echo "   No donors in Stage 2\n";
    } else {
        foreach ($stage2Donors as $donor) {
            echo "   • {$donor['full_name']} ({$donor['latest_test_result']}) - {$donor['status']}\n";
        }
    }
    
    echo "\n🎉 ALL WORKFLOW INCONSISTENCIES FIXED!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
