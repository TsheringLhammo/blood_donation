<?php
// Verify Stage 2 donors list after fixes
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🎯 VERIFYING STAGE 2 DONORS (Ready for Blood Draw)\n";
    echo "=================================================\n\n";
    
    // Query Stage 2 donors
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
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "❌ No donors found in Stage 2\n";
    } else {
        echo "📊 STAGE 2 DONORS (" . count($results) . " total):\n";
        echo "=====================================\n";
        
        foreach ($results as $i => $row) {
            echo ($i + 1) . ". {$row['full_name']}\n";
            echo "   Email: {$row['email']}\n";
            echo "   Phone: {$row['phone']}\n";
            echo "   Blood Type: {$row['blood_type']}\n";
            echo "   Test Result: {$row['latest_test_result']}\n";
            echo "   Status: {$row['status']}\n";
            echo "\n";
        }
    }
    
    // Expected Stage 2 donors
    echo "🎯 EXPECTED STAGE 2 DONORS:\n";
    echo "==========================\n";
    echo "1. tts (no test result, approved for blood draw)\n";
    echo "2. Tshering yangdon (no test result, approved for blood draw) ✅\n";
    echo "3. tt (no test result, approved for blood draw) ✅\n";
    echo "\n";
    
    // Check if expected donors are present
    $expectedDonors = ['tts', 'Tshering yangdon', 'tt'];
    $actualDonors = array_column($results, 'full_name');
    
    echo "🔍 VERIFICATION CHECK:\n";
    echo "====================\n";
    
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
    
    echo "\n🎯 STAGE 2 VERIFICATION COMPLETED\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
