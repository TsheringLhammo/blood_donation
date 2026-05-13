<?php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    require_once __DIR__ . '/../backend/config/db.php';
    require_once __DIR__ . '/../backend/config/auth.php';
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $exception->getMessage()]);
    exit;
}

bts_require_auth(['admin']);

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$notificationId = (int)($payload['id'] ?? $payload['notificationId'] ?? 0);
if ($notificationId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'notification id is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'UPDATE tblnotifications
         SET is_read = 1
         WHERE id = :id
           AND user_id IS NULL'
    );
    $stmt->execute([':id' => $notificationId]);

    echo json_encode([
        'success' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'Notification marked as read.' : 'Notification not found or already read.',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
