<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/backend/config/db.php';

try {
    // Check what columns exist in tbldonor_samples
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'tbldonor_samples'
        AND TABLE_SCHEMA = DATABASE()
    ");
    $stmt->execute();
    $donorSamplesColumns = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Check what columns exist in tblnotifications
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'tblnotifications'
        AND TABLE_SCHEMA = DATABASE()
    ");
    $stmt->execute();
    $notificationsColumns = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo json_encode([
        'success' => true,
        'tbldonor_samples' => $donorSamplesColumns,
        'tblnotifications' => $notificationsColumns,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
?>
