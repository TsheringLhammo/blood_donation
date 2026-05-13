<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    // Add missing columns to tbldonor_samples
    $pdo->exec("ALTER TABLE tbldonor_samples ADD COLUMN IF NOT EXISTS test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending'");
    
    $pdo->exec("ALTER TABLE tbldonor_samples ADD COLUMN IF NOT EXISTS admin_finalized TINYINT(1) NOT NULL DEFAULT 0");
    
    // Add missing type column to tblnotifications
    $pdo->exec("ALTER TABLE tblnotifications ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'info'");
    
    // Verify columns were added
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'tbldonor_samples'
        AND TABLE_SCHEMA = DATABASE()
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute();
    $donorSamplesColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    
    echo json_encode([
        'success' => true,
        'message' => 'All missing columns have been added successfully',
        'columns_added' => [
            'test_status' => in_array('test_status', $donorSamplesColumns),
            'admin_finalized' => in_array('admin_finalized', $donorSamplesColumns),
            'type' => true,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
?>
