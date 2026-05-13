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

function notif_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "tblnotifications"
           AND column_name = ?
         LIMIT 1'
    );
    $stmt->execute([$column]);
    $cache[$column] = (bool)$stmt->fetchColumn();
    return $cache[$column];
}

try {
    $claims = bts_require_auth(['donor']);
    $donorId = (int)($claims['donor_id'] ?? 0);

    if ($donorId <= 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Donor ID is required.'
        ]);
        exit;
    }

    // Get notification ID from URL or request body
    $notificationId = null;
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('/\/(\d+)\/read/', $path, $matches)) {
        $notificationId = (int)$matches[1];
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = (int)($data['notification_id'] ?? 0);
    }

    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Notification ID is required.'
        ]);
        exit;
    }

    $whereParts = [];
    $verifyParams = [$notificationId];

    if (notif_has_column($pdo, 'donor_id')) {
        $whereParts[] = 'donor_id = ?';
        $verifyParams[] = $donorId;
    }
    if (notif_has_column($pdo, 'user_id')) {
        if (notif_has_column($pdo, 'role_target')) {
            $whereParts[] = '(user_id = ? AND role_target = "donor")';
        } else {
            $whereParts[] = 'user_id = ?';
        }
        $verifyParams[] = $donorId;
    }

    if (empty($whereParts)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Notification ownership keys are not available in schema.'
        ]);
        exit;
    }

    // Verify the notification belongs to this donor.
    $verifySql = 'SELECT id FROM tblnotifications WHERE id = ? AND (' . implode(' OR ', $whereParts) . ')';
    $verifyStmt = $pdo->prepare($verifySql);
    $verifyStmt->execute($verifyParams);
    if (!$verifyStmt->fetch()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or access denied.'
        ]);
        exit;
    }

    // Mark as read
    $stmt = $pdo->prepare(
        'UPDATE tblnotifications SET is_read = 1 WHERE id = ?'
    );
    $stmt->execute([$notificationId]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read.'
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
}
