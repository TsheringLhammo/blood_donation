<?php
// Add test donors if none exist
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $dbHost = '127.0.0.1';
    $dbPort = '3306';
    $dbName = 'blood_donation';
    $dbUser = 'root';
    $dbPass = '';
    
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // Check if any confirmed donors exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbldonors WHERE LOWER(TRIM(COALESCE(status, 'pending'))) IN ('confirmed', 'eligible', 'active')");
    $confirmedCount = $stmt->fetchColumn();
    
    echo "<h2>Test Donor Setup</h2>";
    
    if ($confirmedCount > 0) {
        echo "<p style='color: green;'>✓ Found $confirmedCount confirmed donors. No action needed.</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No confirmed donors found. Adding test donors...</p>";
        
        // Add some test donors
        $testDonors = [
            ['Tshering Wangchuk', 'tshering@example.com', 'O+', 'confirmed'],
            ['Sonam Dorji', 'sonam@example.com', 'A+', 'confirmed'],
            ['Karma Tenzin', 'karma@example.com', 'B+', 'eligible'],
            ['Pema Lhamo', 'pema@example.com', 'AB+', 'active'],
            ['Dawa Gyeltshen', 'dawa@example.com', 'O-', 'confirmed']
        ];
        
        foreach ($testDonors as $donor) {
            $stmt = $pdo->prepare("INSERT INTO tbldonors (full_name, email, blood_type, status) VALUES (?, ?, ?, ?)");
            $stmt->execute($donor);
            echo "<p>✓ Added: {$donor[0]} ({$donor[2]}, {$donor[3]})</p>";
        }
        
        echo "<p style='color: green; font-weight: bold;'>✓ Added 5 test donors! Refresh your staff dashboard.</p>";
    }
    
    echo "<h2>Current Donor Status:</h2>";
    $stmt = $pdo->query("SELECT id, full_name, blood_type, status FROM tbldonors ORDER BY id DESC LIMIT 10");
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Blood Type</th><th>Status</th></tr>";
    foreach ($donors as $donor) {
        $statusColor = in_array(strtolower($donor['status']), ['confirmed', 'eligible', 'active']) ? 'green' : 'orange';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($donor['id']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['blood_type']) . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . htmlspecialchars($donor['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
