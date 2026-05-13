<?php
// Verify Stage 2 donors after all specific fixes
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🎯 STAGE 2 VERIFICATION AFTER ALL FIXES\n";
    echo "========================================\n\n";
    
    // Query Stage 2 donors
    echo "📊 CURRENT STAGE 2 DONORS:\n";
    echo "==============================\n";
    
    $stmt = $pdo->query("
        SELECT 
            full_name, 
            email, 
            phone, 
            blood_type,
            latest_test_result,
            workflow_status,
            status
        FROM tbldonors 
        WHERE workflow_status = 'approved_for_blood_draw'
        ORDER BY full_name
    ");
    
    $stage2Donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($stage2Donors)) {
        echo "❌ No donors found in Stage 2\n";
    } else {
        foreach ($stage2Donors as $i => $donor) {
            echo ($i + 1) . ". {$donor['full_name']}\n";
            echo "   Email: {$donor['email']}\n";
            echo "   Phone: {$donor['phone']}\n";
            echo "   Blood Type: {$donor['blood_type']}\n";
            echo "   Test Result: {$donor['latest_test_result']}\n";
            echo "   Status: {$donor['status']}\n";
            echo "\n";
        }
    }
    
    echo "📊 Stage 2 Count: " . count($stage2Donors) . " donors\n\n";
    
    // Expected donors in Stage 2
    echo "🎯 EXPECTED STAGE 2 DONORS:\n";
    echo "==============================\n";
    echo "1. tt (fixed - no test result, ready for blood draw) ✅\n";
    echo "2. Tshering yangdon (already correct - no test result, ready for blood draw) ✅\n";
    echo "3. Sonam (already correct - no test result, ready for blood draw) ✅\n";
    echo "4. Tshering Lhamo (already correct - negative test, ready for blood draw) ✅\n";
    echo "5. tts (already correct - no test result, ready for blood draw) ✅\n";
    echo "\n";
    
    // Verify expected donors are present
    echo "🔍 VERIFICATION CHECK:\n";
    echo "====================\n";
    
    $expectedDonors = ['tt', 'Tshering yangdon', 'Sonam', 'Tshering Lhamo', 'tts'];
    $actualDonors = array_column($stage2Donors, 'full_name');
    
    foreach ($expectedDonors as $donor) {
        if (in_array($donor, $actualDonors)) {
            echo "✅ $donor - Found in Stage 2\n";
        } else {
            echo "❌ $donor - Missing from Stage 2\n";
        }
    }
    
    // Check for unexpected donors
    $unexpectedDonors = array_diff($actualDonors, $expectedDonors);
    if (!empty($unexpectedDonors)) {
        echo "\n⚠️  UNEXPECTED DONORS IN STAGE 2:\n";
        foreach ($unexpectedDonors as $donor) {
            echo "   - $donor\n";
        }
    } else {
        echo "\n✅ No unexpected donors in Stage 2\n";
    }
    
    // Check if any incorrect donors are in Stage 2
    echo "\n🔍 INCORRECT DONORS IN STAGE 2 CHECK:\n";
    echo "=====================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            full_name,
            latest_test_result,
            workflow_status,
            status,
            'Should NOT be in Stage 2' as issue
        FROM tbldonors 
        WHERE workflow_status = 'approved_for_blood_draw'
        AND (
            latest_test_result = 'positive' 
            OR (latest_test_result = 'negative' AND workflow_status != 'approved_for_blood_draw')
            OR deferred = 1
        )
        ORDER BY full_name
    ");
    
    $incorrectStage2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($incorrectStage2)) {
        echo "✅ No incorrect donors in Stage 2\n";
    } else {
        foreach ($incorrectStage2 as $donor) {
            echo "❌ {$donor['full_name']} - {$donor['latest_test_result']} - {$donor['issue']}\n";
        }
    }
    
    // Final status
    echo "\n🎉 STAGE 2 VERIFICATION SUMMARY:\n";
    echo "=================================\n";
    
    $totalExpected = count($expectedDonors);
    $totalActual = count($stage2Donors);
    $totalCorrect = count(array_intersect($expectedDonors, $actualDonors));
    
    echo "Expected: $totalExpected donors\n";
    echo "Actual: $totalActual donors\n";
    echo "Correct: $totalCorrect donors\n";
    echo "Accuracy: " . round(($totalCorrect / $totalExpected) * 100, 1) . "%\n";
    
    if ($totalCorrect == $totalExpected && empty($incorrectStage2)) {
        echo "✅ STAGE 2 IS PERFECTLY CONFIGURED!\n";
        echo "✅ ALL EXPECTED DONORS PRESENT\n";
        echo "✅ NO INCORRECT DONORS IN STAGE 2\n";
        echo "✅ SYSTEM READY FOR PRODUCTION!\n";
    } else {
        echo "⚠️  STAGE 2 NEEDS ATTENTION\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
