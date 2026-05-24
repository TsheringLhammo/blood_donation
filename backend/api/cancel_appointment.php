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

// Optional auth - allows both users and staff
$claims = @bts_optional_auth();
$userId = (int)($claims['sub'] ?? 0);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$appointmentId = (int)($data['appointmentId'] ?? 0);
$cancellationReason = trim((string)($data['reason'] ?? ''));

function appointment_table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
}

if ($appointmentId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
    exit;
}

try {
    // Fetch appointment
    $aptStmt = $pdo->prepare(
        'SELECT id, user_id, full_name, preferred_date, blood_bank, status FROM tblappointments WHERE id = ? LIMIT 1'
    );
    $aptStmt->execute([$appointmentId]);
    $appointment = $aptStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }

    // Check ownership (authenticated user must own this, or be admin/staff)
    if ($appointment['user_id'] && $userId > 0 && $appointment['user_id'] !== $userId) {
        if (!is_array($claims) || ($claims['role'] ?? '') !== 'admin' && ($claims['role'] ?? '') !== 'staff') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to cancel this appointment.']);
            exit;
        }
    }

    // Cannot cancel if already in terminal states
    $currentStatus = trim((string)($appointment['status'] ?? ''));
    if (in_array($currentStatus, ['completed', 'no-show', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This appointment cannot be cancelled (status: ' . $currentStatus . ').']);
        exit;
    }

    // Update status to cancelled
    $updatedRows = 0;
    foreach (['tblappointments', 'appointments'] as $tableName) {
        if (!appointment_table_exists($pdo, $tableName)) {
            continue;
        }

        $updateStmt = $pdo->prepare('UPDATE `' . $tableName . '` SET status = "cancelled" WHERE id = ?');
        $updateStmt->execute([$appointmentId]);
        $updatedRows += $updateStmt->rowCount();
    }

    if ($updatedRows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully.',
        'appointmentId' => $appointmentId,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
?>
