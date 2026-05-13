<?php
require_once '../../backend/config/auth.php';
require_once '../../backend/config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$claims = bts_require_auth(['admin']);
$adminId = (int)($claims['sub'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true);
$donorId = isset($data['donor_id']) ? (int)$data['donor_id'] : 0;

if ($donorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donor ID is required.']);
    exit;
}

try {
    $donorStmt = $pdo->prepare('SELECT full_name FROM tbldonors WHERE id = ? LIMIT 1');
    $donorStmt->execute([$donorId]);
    $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE tbldonors SET workflow_status = ? WHERE id = ?');
    $stmt->execute(['approved_for_blood_draw', $donorId]);

    if ($donor) {
        $notificationStmt = $pdo->prepare(
            'INSERT INTO tblnotifications (user_id, role_target, request_id, title, message, severity, channel, is_read, type, created_at)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $notificationStmt->execute([
            $donorId,
            'donor',
            'Blood Donation Approved',
            'Dear ' . $donor['full_name'] . ', your blood donation status has been approved. Please check your donor dashboard for the next steps.',
            'success',
            'in_app',
            0,
            'approval',
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Donor approved successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
