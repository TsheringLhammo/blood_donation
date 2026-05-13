<?php
require_once '../../backend/config/auth.php';
require_once '../../backend/config/db.php';

bts_require_auth(['admin']);
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$donorId = isset($data['donor_id']) ? (int)$data['donor_id'] : 0;
$reason = trim((string)($data['reason'] ?? ''));

if ($donorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donor ID is required.']);
    exit;
}

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE tbldonors SET workflow_status = ?, rejection_reason = ? WHERE id = ?');
    $stmt->execute(['decision_made_rejected', $reason, $donorId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Donor rejected successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
