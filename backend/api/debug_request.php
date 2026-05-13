<?php
/**
 * Debug: Check request status and workflow state
 * Usage: Call via POST with requestId parameter
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/request_workflow.php';

header('Content-Type: application/json');

try {
    $claims = bts_require_auth(['staff', 'admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true);
$requestId = (int)($data['requestId'] ?? 0);

if ($requestId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'requestId required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, request_code, status, patient_name, blood_type, units_requested FROM tblblood_requests WHERE id = ?');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    $status = $request['status'];
    $normalized = bts_normalize_request_status($status);
    
    echo json_encode([
        'success' => true,
        'request' => $request,
        'statusDebug' => [
            'rawStatus' => $status,
            'normalizedStatus' => $normalized,
            'isValidForCrossMatch' => ($normalized === 'cross-matching'),
            'canTransitionToMatched' => bts_can_transition_request_status($status, 'Matched'),
            'canTransitionToRejected' => bts_can_transition_request_status($status, 'Rejected'),
        ],
        'message' => "Status is: '{$status}' (normalized: '{$normalized}')"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
