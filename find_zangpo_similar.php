<?php
// Find Zangpo or similar names in database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 SEARCHING FOR ZANGPO OR SIMILAR NAMES\n";
    echo "==========================================\n\n";
    
    // Search for exact Zangpo
    echo "1️⃣ SEARCHING FOR EXACT 'ZANGPO':\n";
    echo "====================================\n";
    
    $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name = ?");
    $stmt->execute(['Zangpo']);
    $exactZangpo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exactZangpo) {
        echo "✅ Found exact match: Zangpo\n";
        echo "Test Result: {$exactZangpo['latest_test_result']}\n";
        echo "Workflow Status: {$exactZangpo['workflow_status']}\n";
        echo "Status: {$exactZangpo['status']}\n";
        echo "Deferred: " . ($exactZangpo['deferred'] ? 'Yes' : 'No') . "\n";
        echo "Reason: " . ($exactZangpo['deferral_reason'] ?? 'N/A') . "\n";
    } else {
        echo "❌ No exact match for 'Zangpo'\n";
    }
    echo "\n";
    
    // Search for partial 'Zangpo'
    echo "2️⃣ SEARCHING FOR PARTIAL 'ZANGPO':\n";
    echo "====================================\n";
    
    $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE ?");
    $stmt->execute(['%Zangpo%']);
    $partialZangpo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($partialZangpo)) {
        echo "✅ Found " . count($partialZangpo) . " partial matches:\n";
        foreach ($partialZangpo as $donor) {
            echo "   • {$donor['full_name']}\n";
            echo "     Test Result: {$donor['latest_test_result']}\n";
            echo "     Workflow Status: {$donor['workflow_status']}\n";
            echo "     Status: {$donor['status']}\n";
            echo "     Deferred: " . ($donor['deferred'] ? 'Yes' : 'No') . "\n";
            echo "     Reason: " . ($donor['deferral_reason'] ?? 'N/A') . "\n";
            echo "\n";
        }
    } else {
        echo "❌ No partial matches for 'Zangpo'\n";
    }
    echo "\n";
    
    // Search for names containing 'Zang'
    echo "3️⃣ SEARCHING FOR NAMES CONTAINING 'ZANG':\n";
    echo "============================================\n";
    
    $stmt = $pdo->prepare("SELECT * FROM tbldonors WHERE full_name LIKE ?");
    $stmt->execute(['%Zang%']);
    $zangDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($zangDonors)) {
        echo "✅ Found " . count($zangDonors) . " donors with 'Zang' in name:\n";
        foreach ($zangDonors as $donor) {
            echo "   • {$donor['full_name']}\n";
            echo "     Test Result: {$donor['latest_test_result']}\n";
            echo "     Workflow Status: {$donor['workflow_status']}\n";
            echo "     Status: {$donor['status']}\n";
            echo "     Deferred: " . ($donor['deferred'] ? 'Yes' : 'No') . "\n";
            echo "     Reason: " . ($donor['deferral_reason'] ?? 'N/A') . "\n";
            echo "\n";
        }
    } else {
        echo "❌ No donors with 'Zang' in name\n";
    }
    echo "\n";
    
    // Search for any donors with positive results that should be negative
    echo "4️⃣ SEARCHING FOR POSITIVE RESULTS THAT SHOULD BE NEGATIVE:\n";
    echo "========================================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            full_name,
            latest_test_result,
            workflow_status,
            status,
            deferral_reason,
            created_at,
            updated_at
        FROM tbldonors 
        WHERE latest_test_result = 'positive'
        ORDER BY full_name
    ");
    
    $positiveDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($positiveDonors)) {
        echo "✅ Found " . count($positiveDonors) . " donors with positive results:\n";
        foreach ($positiveDonors as $donor) {
            echo "   • {$donor['full_name']}\n";
            echo "     Test Result: {$donor['latest_test_result']}\n";
            echo "     Workflow Status: {$donor['workflow_status']}\n";
            echo "     Status: {$donor['status']}\n";
            echo "     Reason: {$donor['deferral_reason'] ?? 'N/A'}\n";
            echo "     Created: {$donor['created_at']}\n";
            echo "     Updated: {$donor['updated_at']}\n";
            echo "\n";
        }
    } else {
        echo "✅ No donors with positive results\n";
    }
    echo "\n";
    
    // Show all donors for reference
    echo "5️⃣ ALL DONORS IN DATABASE (for reference):\n";
    echo "===============================================\n";
    
    $stmt = $pdo->query("SELECT full_name, latest_test_result, workflow_status, status FROM tbldonors ORDER BY full_name");
    $allDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allDonors as $donor) {
        $status = $donor['deferred'] ? '⚠️' : '✅';
        echo "   $status {$donor['full_name']} - {$donor['latest_test_result']} - {$donor['status']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
