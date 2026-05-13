<?php
// Simple PHP script to backup tbldonors table
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create backup table
    $backupTable = 'tbldonors_backup_' . date('Ymd_His');
    $pdo->exec("CREATE TABLE $backupTable AS SELECT * FROM tbldonors");
    
    // Get count
    $stmt = $pdo->query("SELECT COUNT(*) FROM $backupTable");
    $count = $stmt->fetchColumn();
    
    echo "✅ Backup created successfully!\n";
    echo "📋 Backup table: $backupTable\n";
    echo "📊 Records backed up: $count\n";
    
    // Verify original count
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbldonors");
    $originalCount = $stmt->fetchColumn();
    
    echo "📋 Original table count: $originalCount\n";
    
    if ($count == $originalCount) {
        echo "✅ Backup verification: PASSED\n";
    } else {
        echo "❌ Backup verification: FAILED\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
