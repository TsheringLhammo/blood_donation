<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/backend/config/db.php';

try {
    $pdo->exec("ALTER TABLE tbldonors ADD COLUMN IF NOT EXISTS cid_number VARCHAR(11) NULL AFTER phone");

    $cidIndexStmt = $pdo->query("SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'tbldonors' AND column_name = 'cid_number' AND non_unique = 0 LIMIT 1");
    if (!$cidIndexStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE tbldonors ADD UNIQUE KEY uq_tbldonors_cid_number (cid_number)");
    }

    // Add staff profile columns to tblusers if missing
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER role");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER phone");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER date_of_birth");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER address");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS dzongkhag VARCHAR(120) NULL AFTER city");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(120) NULL AFTER dzongkhag");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(30) NULL AFTER emergency_contact_name");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS profile_picture LONGTEXT NULL AFTER emergency_contact_phone");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS assigned_blood_bank VARCHAR(255) NULL AFTER profile_picture");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS position VARCHAR(120) NULL AFTER assigned_blood_bank");
    $pdo->exec("ALTER TABLE tblusers ADD COLUMN IF NOT EXISTS employee_id VARCHAR(80) NULL AFTER position");

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
            'tbldonors.cid_number' => true,
            'tblusers.phone' => true,
            'tblusers.profile_picture' => true,
            'tblusers.assigned_blood_bank' => true,
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
