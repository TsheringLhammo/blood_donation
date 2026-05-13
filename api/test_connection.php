<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$response = [
    'success' => true,
    'message' => 'API is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
    'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'None',
];

// Test database connection
try {
    require_once __DIR__ . '/../backend/config/db.php';
    $response['database'] = 'Connected';
    
    // Check tables
    $tables = $pdo->query("SHOW TABLES LIKE 'tbldonors'")->fetchAll();
    $response['tbldonors_exists'] = count($tables) > 0;
    
    $tables2 = $pdo->query("SHOW TABLES LIKE 'tbldonor_samples'")->fetchAll();
    $response['tbldonor_samples_exists'] = count($tables2) > 0;
    
} catch (Throwable $e) {
    $response['database'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
