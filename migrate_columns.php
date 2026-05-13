<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/backend/config/db.php';

try {
    // Add test_status column to tbldonor_samples
    $pdo->exec("ALTER TABLE tbldonor_samples ADD COLUMN IF NOT EXISTS test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending' AFTER malaria");
    
    // Add admin_finalized column to tbldonor_samples if missing
    $pdo->exec("ALTER TABLE tbldonor_samples ADD COLUMN IF NOT EXISTS admin_finalized TINYINT(1) NOT NULL DEFAULT 0 AFTER test_status");
    
    // Add type column to tblnotifications if missing
    $pdo->exec("ALTER TABLE tblnotifications ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'info' AFTER id");
    
    // Verify columns were added
    $stmt = $pdo->query("DESCRIBE tbldonor_samples");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnNames = array_column($columns, 'Field');
    
    echo json_encode([
        'success' => true,
        'message' => 'Database migration completed',
        'columns_added' => [
            'test_status' => in_array('test_status', $columnNames),
            'admin_finalized' => in_array('admin_finalized', $columnNames),
        ],
        'all_columns' => $columnNames,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
?>
