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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

// Admin/staff only
$claims = bts_require_auth(['admin', 'staff']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$campId = (int)($data['campId'] ?? 0);
$cancellationReason = trim((string)($data['reason'] ?? ''));

if ($campId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Camp ID is required.']);
    exit;
}

try {
    // Fetch camp
    $campStmt = $pdo->prepare(
        'SELECT id, organization_name, email, status FROM tblblood_camps WHERE id = ? LIMIT 1'
    );
    $campStmt->execute([$campId]);
    $camp = $campStmt->fetch(PDO::FETCH_ASSOC);

    if (!$camp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Camp not found.']);
        exit;
    }

    // Cannot cancel if already in terminal states
    $currentStatus = trim((string)($camp['status'] ?? ''));
    if (in_array($currentStatus, ['completed', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This camp cannot be cancelled (status: ' . $currentStatus . ').']);
        exit;
    }

    // Update status to cancelled
    $updateStmt = $pdo->prepare('UPDATE tblblood_camps SET status = "cancelled" WHERE id = ?');
    $updateStmt->execute([$campId]);

    // Optional: Send cancellation email to organization
    if (!empty($camp['email'])) {
        try {
            require_once __DIR__ . '/../config/mailer.php';
            $mailSubject = 'Blood Donation Camp Request – Cancellation Notification';
            $mailBody = sprintf(
                "Dear %s,\n\n" .
                "Your blood donation camp request (ID: %d) has been cancelled.\n" .
                "%s\n\n" .
                "If you have any questions, please contact the Blood Bank.\n\n" .
                "Best regards,\nBlood Transfusion Services",
                $camp['organization_name'],
                $campId,
                $cancellationReason ? "Reason: " . $cancellationReason : ""
            );
            @bts_send_email($camp['email'], $mailSubject, $mailBody);
        } catch (Throwable $e) {
            // Log but don't fail the API call
            error_log("Camp cancellation email failed: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Camp cancelled successfully.',
        'campId' => $campId,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
?>
