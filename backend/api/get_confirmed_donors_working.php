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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['staff', 'admin']);

try {
    $stmt = $pdo->query(
        'SELECT id, full_name, email, blood_type, status 
         FROM tbldonors 
         WHERE LOWER(TRIM(COALESCE(status, "pending"))) IN ("confirmed", "eligible", "active") 
         ORDER BY full_name ASC'
    );
    
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    echo json_encode([
        'success' => true,
        'data' => $donors
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
