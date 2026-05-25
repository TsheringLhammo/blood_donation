<?php
try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'blood_donation';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo json_encode(['status' => 'SUCCESS', 'message' => 'PHP-MySQL connection working']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'FAILED', 'error' => $e->getMessage()]);
}
?>