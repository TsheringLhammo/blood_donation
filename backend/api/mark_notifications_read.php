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

$claims = bts_require_auth(['admin', 'staff', 'doctor', 'donor']);
$userId = (int)($claims['sub'] ?? 0);
$role = trim((string)($claims['role'] ?? ''));

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$validRoles = ['admin', 'staff', 'doctor', 'donor'];
$roleTargets = ['admin'];
if ($role === 'staff') {
    $roleTargets[] = 'staff';
} elseif ($role === 'doctor') {
    $roleTargets[] = 'doctor';
} elseif ($role === 'donor') {
    $roleTargets[] = 'donor';
}

$rolePlaceholders = implode(', ', array_fill(0, count($roleTargets), '?'));
$params = array_merge($roleTargets, [$userId]);

try {
    // Admins also see broadcast notifications stored with user_id IS NULL
    // (matches the SELECT convention used by get_admin_notifications.php).
    $adminBroadcastClause = $role === 'admin' ? ' OR user_id IS NULL' : '';

    $sql = 'UPDATE tblnotifications
            SET is_read = 1
            WHERE is_read = 0
              AND (
                    role_target IN (' . $rolePlaceholders . ')
                    OR user_id = ?'
                  . $adminBroadcastClause . '
                  )';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'marked' => $stmt->rowCount(),
        'message' => 'Notifications marked as read.',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
