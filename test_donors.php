<?php
// Simple script to check donors in database
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Connect to database
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
    
    echo "<h2>Database Connection: SUCCESS</h2>";
    
    // Check if tbldonors table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'tbldonors'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p style='color: red;'>ERROR: tbldonors table does not exist!</p>";
        exit;
    }
    
    echo "<h2>All Donors in Database:</h2>";
    $stmt = $pdo->query("SELECT id, full_name, email, blood_type, status FROM tbldonors ORDER BY id DESC LIMIT 10");
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($donors)) {
        echo "<p style='color: orange;'>No donors found in database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Blood Type</th><th>Status</th></tr>";
        foreach ($donors as $donor) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($donor['id']) . "</td>";
            echo "<td>" . htmlspecialchars($donor['full_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($donor['email'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($donor['blood_type'] ?? 'N/A') . "</td>";
            echo "<td><strong>" . htmlspecialchars($donor['status'] ?? 'N/A') . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>Confirmed/Eligible/Active Donors (what API looks for):</h2>";
    $stmt = $pdo->query("SELECT id, full_name, status FROM tbldonors WHERE LOWER(TRIM(COALESCE(status, 'pending'))) IN ('confirmed', 'eligible', 'active') ORDER BY full_name");
    $confirmed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($confirmed)) {
        echo "<p style='color: red;'>No confirmed/eligible/active donors found! This is why dropdown is empty.</p>";
        echo "<p>Solution: Update donor statuses to 'confirmed', 'eligible', or 'active'</p>";
    } else {
        echo "<p style='color: green;'>Found " . count($confirmed) . " confirmed/eligible/active donors:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
        foreach ($confirmed as $donor) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($donor['id']) . "</td>";
            echo "<td>" . htmlspecialchars($donor['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($donor['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
