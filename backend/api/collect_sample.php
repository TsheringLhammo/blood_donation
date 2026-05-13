<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['staff', 'admin']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donorId = (int)($data['donorId'] ?? 0);
$collectionDate = trim((string)($data['collectionDate'] ?? date('Y-m-d')));
$technician = trim((string)($data['technician'] ?? ''));

if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donorId is required.']);
    exit;
}

if (empty($technician)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'technician is required.']);
    exit;
}

try {
    // Verify donor exists
    $donorStmt = $pdo->prepare('SELECT id, full_name, status FROM tbldonors WHERE id = ? LIMIT 1');
    $donorStmt->execute([$donorId]);
    $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    // Check for duplicate sample (same donor, same date)
    $duplicateStmt = $pdo->prepare(
        'SELECT id FROM tbldonor_samples WHERE donor_id = ? AND collection_date = ? LIMIT 1'
    );
    $duplicateStmt->execute([$donorId, $collectionDate]);
    if ($duplicateStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A sample has already been collected for this donor on this date.']);
        exit;
    }

    // Insert new sample with status "Collected"
    $insertStmt = $pdo->prepare(
        'INSERT INTO tbldonor_samples (donor_id, collection_date, technician, status, created_at) 
         VALUES (?, ?, ?, "Collected", CURRENT_TIMESTAMP)'
    );
    $insertStmt->execute([$donorId, $collectionDate, $technician]);

    $sampleId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Sample collected successfully.',
        'data' => [
            'id' => $sampleId,
            'donor_id' => $donorId,
            'donor_name' => $donor['full_name'],
            'collection_date' => $collectionDate,
            'technician' => $technician,
            'status' => 'Collected'
        ]
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
