<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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

    $whereParts = [];
    $params = [];

    if (notif_has_column($pdo, 'donor_id')) {
        $whereParts[] = 'donor_id = ?';
        $params[] = $donorId;
    }
    if (notif_has_column($pdo, 'user_id')) {
        if (notif_has_column($pdo, 'role_target')) {
            $whereParts[] = '(user_id = ? AND role_target = "donor")';
        } else {
            $whereParts[] = 'user_id = ?';
        }
        $params[] = $donorId;
    }

    if (empty($whereParts)) {
        echo json_encode([
            'success' => true,
            'unread_count' => 0
        ]);
        exit;
    }

    $isReadFilter = notif_has_column($pdo, 'is_read') ? 'is_read = 0 AND ' : '';
    $sql = 'SELECT COUNT(*) as unread_count FROM tblnotifications WHERE ' . $isReadFilter . '(' . implode(' OR ', $whereParts) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'unread_count' => (int)($result['unread_count'] ?? 0)
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
}
