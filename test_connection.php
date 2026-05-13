<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=blood_donation;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo json_encode(['status' => 'SUCCESS', 'message' => 'PHP-MySQL connection working']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'FAILED', 'error' => $e->getMessage()]);
}
?>