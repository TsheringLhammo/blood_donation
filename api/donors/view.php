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

require_once __DIR__ . '/_common.php';

bts_require_auth(['admin']);

$donorId = (int)($_GET['id'] ?? 0);
if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid donor id is required.']);
    exit;
}

try {
    $payload = bts_donor_records_build_view_payload($pdo, $donorId);
    if ($payload === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $payload]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $exception->getMessage()]);
}
