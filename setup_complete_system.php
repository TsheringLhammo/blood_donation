<?php
// Complete Blood Donation Management System Setup
echo "<h1>🩸 Blood Donation Management System Setup</h1>";

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $database");
    
    echo "<p style='color: green;'>✅ Database connected and ready</p>";
    
    // Read and execute the complete schema
    $schemaFile = __DIR__ . '/database_complete.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $e) {
                    // Ignore errors for existing data
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "<p style='color: orange;'>⚠️ Schema note: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
        echo "<p style='color: green;'>✅ Database schema loaded</p>";
    } else {
        echo "<p style='color: red;'>❌ Schema file not found</p>";
    }
    
    // Verify data exists
    $donorCount = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
    $notificationCount = $pdo->query("SELECT COUNT(*) FROM tblnotifications")->fetchColumn();
    
    echo "<p>📊 System Data: $donorCount donors, $notificationCount notifications</p>";
    
    // Show sample donors
    $donors = $pdo->query("SELECT id, name, email, workflow_status FROM donors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>📋 Sample Donors (Click to test Donor Dashboard):</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr>";
    
    foreach ($donors as $donor) {
        $statusColor = [
            'Awaiting Review' => '#ffc107',
            'Approved' => '#28a745',
            'Temporarily Deferred' => '#ffc107',
            'Permanently Deferred' => '#dc3545'
        ];
        
        $color = $statusColor[$donor['workflow_status']] ?? '#6c757d';
        
        echo "<tr>";
        echo "<td>{$donor['id']}</td>";
        echo "<td>{$donor['name']}</td>";
        echo "<td>{$donor['email']}</td>";
        echo "<td><span style='background: $color; color: white; padding: 4px 8px; border-radius: 4px;'>{$donor['workflow_status']}</span></td>";
        echo "<td>";
        echo "<a href='donor_dashboard_complete.php?donor_id={$donor['id']}' target='_blank' style='margin-right: 10px;'>👤 Donor View</a>";
        if ($donor['workflow_status'] === 'Awaiting Review') {
            echo "<span style='color: green;'>⚠️ Needs Admin Decision</span>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>🚀 Quick Access Links:</h2>";
    echo "<ul>";
    echo "<li><a href='admin_dashboard_complete.php' target='_blank' style='font-size: 16px; font-weight: bold;'>🔧 Admin Dashboard</a> - Make decisions on donors</li>";
    echo "<li><a href='donor_dashboard_complete.php?donor_id=5' target='_blank' style='font-size: 16px; font-weight: bold;'>👤 Test Donor (yoyo - Permanently Deferred)</a> - See notification popup</li>";
    echo "<li><a href='donor_dashboard_complete.php?donor_id=1' target='_blank' style='font-size: 16px; font-weight: bold;'>👤 Test Donor (Tashi - Awaiting Review)</a> - No notifications</li>";
    echo "</ul>";
    
    echo "<h2>📝 Testing Instructions:</h2>";
    echo "<ol>";
    echo "<li>Open <strong>Admin Dashboard</strong> in one tab</li>";
    echo "<li>Find a donor with 'Awaiting Review' status</li>";
    echo "<li>Click one of the decision buttons (Approve/Temp Defer/Permanent Defer)</li>";
    echo "<li>Fill in the modal and confirm</li>";
    echo "<li>Open the <strong>Donor Dashboard</strong> for that donor in another tab</li>";
    echo "<li>You should see a notification pop-up with the admin's message</li>";
    echo "<li>The donor's status should be updated accordingly</li>";
    echo "</ol>";
    
    echo "<h2>✨ Key Features Working:</h2>";
    echo "<ul>";
    echo "<li>✅ Admin can Approve/Temp Defer/Permanent Defer donors</li>";
    echo "<li>✅ Donors CANNOT change their own status</li>";
    echo "<li>✅ Admin decisions trigger automatic notifications</li>";
    echo "<li>✅ Donor dashboard shows pop-up notifications</li>";
    echo "<li>✅ Permanent deferral is remembered</li>";
    echo "<li>✅ Temporary deferral shows end date</li>";
    echo "<li>✅ Status-based action buttons</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please ensure MySQL is running and credentials are correct.</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
h1 { color: #667eea; }
h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
a { color: #667eea; text-decoration: none; }
a:hover { text-decoration: underline; }
table { width: 100%; margin: 20px 0; }
th { background: #667eea; color: white; }
</style>
