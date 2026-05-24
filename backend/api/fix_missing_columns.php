<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column_name');
    $stmt->execute([':column_name' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    // Add missing columns to tbldonor_samples
    if (!column_exists($pdo, 'tbldonor_samples', 'test_status')) {
        $pdo->exec("ALTER TABLE tbldonor_samples ADD COLUMN test_status ENUM('pending','eligible','deferred') NOT NULL DEFAULT 'pending'");
    }
    if (!column_exists($pdo, 'tbldonor_samples', 'admin_finalized')) {
        $pdo->exec("ALTER TABLE tbldonor_samples ADD COLUMN admin_finalized TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Add missing type column to tblnotifications
    if (!column_exists($pdo, 'tblnotifications', 'type')) {
        $pdo->exec("ALTER TABLE tblnotifications ADD COLUMN type VARCHAR(50) DEFAULT 'info'");
    }

    // Add notes and updated_at columns to tblappointments if missing
    if (!column_exists($pdo, 'tblappointments', 'notes')) {
        $pdo->exec("ALTER TABLE tblappointments ADD COLUMN notes TEXT NULL AFTER blood_bank");
    }
    if (!column_exists($pdo, 'tblappointments', 'updated_at')) {
        $pdo->exec("ALTER TABLE tblappointments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    echo json_encode([
        'success' => true,
        'message' => 'All missing columns have been added successfully',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
?>
