<?php
require_once 'backend/config/db.php';

try {
    $stmt = $pdo->query('SELECT id, full_name, email, blood_type, status, sample_tested, sample_tested_at FROM tbldonors ORDER BY id DESC LIMIT 10');
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Recent Donors (Last 10)</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Blood Type</th><th>Status</th><th>Sample Tested</th><th>Sample Tested At</th></tr>";
    
    foreach ($donors as $donor) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($donor['id']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['email']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['blood_type']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['status']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['sample_tested'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($donor['sample_tested_at'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check for confirmed donors specifically
    echo "<h2>Confirmed/Eligible/Active Donors</h2>";
    $stmt = $pdo->query('SELECT id, full_name, status FROM tbldonors WHERE LOWER(TRIM(COALESCE(status, "pending"))) IN ("confirmed", "eligible", "active") ORDER BY full_name');
    $confirmed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($confirmed)) {
        echo "<p>No confirmed/eligible/active donors found.</p>";
    } else {
        echo "<table border='1'>";
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
    
} catch (Throwable $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
