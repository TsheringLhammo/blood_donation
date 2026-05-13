<?php
// Fix HIV positive donors to permanent deferral (not temporary)
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 FIXING HIV POSITIVE DONORS TO PERMANENT DEFERRAL\n";
    echo "==================================================\n\n";
    
    // Find HIV positive donors with wrong deferral status
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
        WHERE 
            (latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%')
            AND workflow_status != 'decision_made_rejected'
        ORDER BY full_name
    ");
    
    $hivIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($hivIssues)) {
        echo "✅ No HIV deferral issues found\n";
    } else {
        echo "🚨 FOUND " . count($hivIssues) . " HIV POSITIVE DONORS WITH WRONG DEFERRAL STATUS:\n";
        echo "========================================================\n";
        
        foreach ($hivIssues as $donor) {
            echo "❌ {$donor['full_name']}\n";
            echo "   Test Result: {$donor['latest_test_result']}\n";
            echo "   Current Status: {$donor['workflow_status']} ({$donor['status']})\n";
            echo "   Deferred: " . ($donor['deferred'] ? 'Yes' : 'No') . "\n";
            echo "   Issue: Should be PERMANENTLY DEFERRED\n";
            echo "\n";
        }
        
        echo "\n🔧 APPLYING FIXES:\n";
        echo "==================\n";
        
        $fixedCount = 0;
        foreach ($hivIssues as $donor) {
            // Only fix those that are NOT already permanently deferred
            if ($donor['workflow_status'] != 'decision_made_rejected') {
                echo "🔧 Fixing {$donor['full_name']}...\n";
                
                $updateStmt = $pdo->prepare("
                    UPDATE tbldonors 
                        SET 
                            workflow_status = 'decision_made_rejected',
                            status = 'Permanently Deferred',
                            deferred = 1,
                            deferred_until = NULL,
                            deferral_reason = 'Positive (HIV) - Permanent Deferral',
                            updated_at = NOW()
                        WHERE full_name = ?
                ");
                
                $result = $updateStmt->execute([$donor['full_name']]);
                
                if ($result) {
                    echo "✅ {$donor['full_name']} fixed successfully\n";
                    echo "   New Status: decision_made_rejected (Permanent)\n";
                    echo "   Display Status: Permanently Deferred\n";
                    echo "   Deferred Until: NULL (Permanent)\n";
                    echo "   Reason: Positive (HIV) - Permanent Deferral\n";
                    $fixedCount++;
                } else {
                    echo "❌ Failed to fix {$donor['full_name']}\n";
                }
                echo "\n";
            } else {
                echo "✅ {$donor['full_name']} already correctly permanently deferred\n";
            }
        }
        
        echo "\n📊 HIV DEFERRAL FIX SUMMARY:\n";
        echo "===============================\n";
        echo "Total HIV issues found: " . count($hivIssues) . "\n";
        echo "Successfully fixed: $fixedCount\n";
        echo "Already correct: " . (count($hivIssues) - $fixedCount) . "\n";
        
        // Verify all HIV donors are now correct
        echo "\n🔍 VERIFYING ALL HIV DONORS AFTER FIX:\n";
        echo "========================================\n";
        
        $verifyStmt = $pdo->query("
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
            WHERE latest_test_result LIKE '%HIV%' OR deferral_reason LIKE '%HIV%'
            ORDER BY full_name
        ");
        
        $allHivDonors = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correctCount = 0;
        $wrongCount = 0;
        
        foreach ($allHivDonors as $donor) {
            if ($donor['hiv_status'] == '✅ CORRECT') {
                $correctCount++;
                echo "✅ {$donor['full_name']} - {$donor['workflow_status']} (CORRECT)\n";
            } else {
                $wrongCount++;
                echo "❌ {$donor['full_name']} - {$donor['workflow_status']} (STILL WRONG)\n";
            }
        }
        
        echo "\n📊 HIV DEFERRAL VERIFICATION SUMMARY:\n";
        echo "====================================\n";
        echo "Total HIV donors: " . count($allHivDonors) . "\n";
        echo "Correctly deferred: $correctCount\n";
        echo "Still wrong: $wrongCount\n";
        
        if ($wrongCount == 0) {
            echo "✅ ALL HIV DONORS ARE NOW CORRECTLY PERMANENTLY DEFERRED!\n";
        } else {
            echo "❌ $wrongCount HIV donors still have issues\n";
        }
        
        echo "\n🎉 HIV PERMANENT DEFERRAL FIX COMPLETED!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
