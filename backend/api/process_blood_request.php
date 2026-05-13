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
require_once __DIR__ . '/../config/request_workflow.php';

$claims = bts_require_auth(['staff', 'admin']);
$actorUserId = (int)($claims['sub'] ?? 0);

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$requestId = (int)($payload['requestId'] ?? 0);
$action = strtolower(trim((string)($payload['action'] ?? '')));
$notes = trim((string)($payload['notes'] ?? ''));

if ($requestId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'requestId is required.']);
    exit;
}

$actionToStatus = [
    'approve' => 'Approved',
    'reject' => 'Rejected',
    'start_crossmatch' => 'Cross-Matching',
];

if (!isset($actionToStatus[$action])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
    exit;
}

$nextStatus = $actionToStatus[$action];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, request_code, doctor_user_id, patient_name, status
         FROM tblblood_requests
         WHERE id = ?
         FOR UPDATE'
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $currentStatus = trim((string)($request['status'] ?? 'Pending'));
    if (!bts_can_transition_request_status($currentStatus, $nextStatus)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => sprintf('Invalid status transition: %s -> %s.', $currentStatus, $nextStatus),
        ]);
        exit;
    }

    $update = $pdo->prepare('UPDATE tblblood_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([$nextStatus, $requestId]);

    bts_log_request_status_change(
        $pdo,
        $requestId,
        $currentStatus,
        $nextStatus,
        $action,
        $actorUserId > 0 ? $actorUserId : null,
        $notes !== '' ? $notes : null
    );

    // Try to send notification if table exists
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'tblnotifications'");
        if ($result && $result->rowCount() > 0) {
            $doctorUserId = isset($request['doctor_user_id']) ? (int)$request['doctor_user_id'] : 0;
            $notificationStmt = $pdo->prepare(
                'INSERT INTO tblnotifications (user_id, role_target, request_id, title, message, severity, channel)
                 VALUES (:user_id, :role_target, :request_id, :title, :message, :severity, :channel)'
            );

            $notificationStmt->execute([
                ':user_id' => $doctorUserId > 0 ? $doctorUserId : null,
                ':role_target' => $doctorUserId > 0 ? null : 'doctor',
                ':request_id' => $requestId,
                ':title' => 'Request Status Updated',
                ':message' => sprintf(
                    'Request %s for %s moved to %s.',
                    (string)($request['request_code'] ?? ('REQ-' . $requestId)),
                    (string)($request['patient_name'] ?? 'patient'),
                    $nextStatus
                ),
                ':severity' => $nextStatus === 'Rejected' ? 'warning' : 'info',
                ':channel' => 'in_app',
            ]);
        }
    } catch (Throwable $notifError) {
        // Notification failed, but don't break the status update
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Request status updated successfully.',
        'data' => [
            'requestId' => $requestId,
            'fromStatus' => $currentStatus,
            'toStatus' => $nextStatus,
            'action' => $action,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
