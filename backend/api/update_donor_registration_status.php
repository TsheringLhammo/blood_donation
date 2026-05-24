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
require_once __DIR__ . '/workflow_helpers.php';

if (file_exists(__DIR__ . '/../config/mailer.php')) {
    require_once __DIR__ . '/../config/mailer.php';
}

if (!function_exists('bts_send_email')) {
    function bts_send_email(...$args): bool
    {
        return false;
    }
}

bts_require_auth(['admin']);

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donorId = (int)($payload['donorId'] ?? $payload['donor_id'] ?? 0);
$requestedStatus = strtolower(trim((string)($payload['status'] ?? '')));
$reason = trim((string)($payload['reason'] ?? $payload['approval_rejection_reason'] ?? ''));

if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donorId is required.']);
    exit;
}

if (!in_array($requestedStatus, ['confirmed', 'approved', 'active', 'rejected'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid donor status.']);
    exit;
}

$approvalStatus = in_array($requestedStatus, ['confirmed', 'approved', 'active'], true) ? 'approved' : 'rejected';
$publicStatus = $approvalStatus === 'approved' ? 'Approved to Donate' : 'Initially rejected';
$workflowStatus = $approvalStatus === 'approved' ? 'approved_to_donate' : 'initially_rejected';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, full_name, email, status FROM tbldonors WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    $updateParts = [
        'status = :status',
    ];
    $updateValues = [
        ':status' => $publicStatus,
        ':id' => $donorId,
    ];

    $columnMap = [
        'initial_approval_status' => $approvalStatus,
        'approval_rejection_reason' => $approvalStatus === 'rejected' ? $reason : null,
        'blood_drawn' => 0,
        'test_result' => 'not_tested',
        'final_decision' => 'pending',
        'defer_until_date' => null,
        'donor_notified_stage1' => 1,
        'donor_notified_stage2' => 0,
        'workflow_status' => $workflowStatus,
    ];

    foreach ($columnMap as $column => $value) {
        if (workflow_table_has_column($pdo, 'tbldonors', $column)) {
            $updateParts[] = '`' . str_replace('`', '``', $column) . '` = :' . $column;
            $updateValues[':' . $column] = $value;
        }
    }

    if (workflow_table_has_column($pdo, 'tbldonors', 'updated_at')) {
        $updateParts[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    $updateSql = 'UPDATE tbldonors SET ' . implode(', ', $updateParts) . ' WHERE id = :id';
    $update = $pdo->prepare($updateSql);
    $update->execute($updateValues);

    $donorEmail = workflow_clean_email($donor['email'] ?? '');
    $message = $approvalStatus === 'approved'
        ? 'Your registration has been approved. Please book an appointment to donate blood.'
        : 'Your registration was not approved for blood donation at this time. Reason: ' . ($reason !== '' ? $reason : 'No reason provided') . '. Contact blood bank for more information.';

    workflow_insert_notification($pdo, [
        'donor_id' => $donorId,
        'user_id' => null,
        'role_target' => 'donor',
        'title' => $approvalStatus === 'approved' ? 'Registration Approved' : 'Registration Rejected',
        'message' => $message,
        'type' => $approvalStatus === 'approved' ? 'approval' : 'rejection',
        'severity' => $approvalStatus === 'approved' ? 'info' : 'warning',
        'channel' => $approvalStatus === 'approved' ? 'in_app' : 'both',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    if ($approvalStatus === 'rejected' && $donorEmail !== '' && filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
        $subject = $approvalStatus === 'approved' ? 'Blood Donation Registration Approved' : 'Blood Donation Registration Update';
        $body = $message . "\n\nThank you,\nBlood Bank";
        bts_send_email($donorEmail, $subject, $body, nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')));
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $approvalStatus === 'approved' ? 'Donor approved to donate.' : 'Donor rejected for initial approval.',
        'data' => [
            'id' => $donorId,
            'requested_status' => $requestedStatus,
            'persisted_status' => $publicStatus,
            'initial_approval_status' => $approvalStatus,
            'workflow_status' => $workflowStatus,
            'full_name' => $donor['full_name'] ?? '',
            'email' => $donorEmail,
            'reason' => $reason,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
