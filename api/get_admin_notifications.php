<?php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = max(1, min(100, $limit));

try {
    $whereClauses = ['user_id IS NULL'];
    $params = [];

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $ignored) {
            return false;
        }
    };

    $selectColumns = [
        'id',
        'title',
        'message',
        'type',
        'is_read',
        'created_at',
    ];

    if ($columnExists($pdo, 'tblnotifications', 'action_url')) {
        $selectColumns[] = 'action_url';
    } else {
        $selectColumns[] = 'NULL AS action_url';
    }

    if ($columnExists($pdo, 'tblnotifications', 'action_type')) {
        $selectColumns[] = 'action_type';
    } else {
        $selectColumns[] = 'NULL AS action_type';
    }

    if ($columnExists($pdo, 'tblnotifications', 'sample_id')) {
        $selectColumns[] = 'sample_id';
    } else {
        $selectColumns[] = 'NULL AS sample_id';
    }

    if ($unreadOnly) {
        $whereClauses[] = 'is_read = 0';
    }

    $whereSql = implode(' AND ', $whereClauses);

    $stmt = $pdo->prepare(
        "SELECT
            " . implode(",\n            ", $selectColumns) . "
         FROM tblnotifications
         WHERE {$whereSql}
         ORDER BY
            CASE
                WHEN type = 'alert' THEN 0
                WHEN type = 'warning' THEN 1
                ELSE 2
            END,
            created_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $countStmt = $pdo->query('SELECT COUNT(*) AS total_unread FROM tblnotifications WHERE user_id IS NULL AND is_read = 0');
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalUnread = $countRow ? (int)$countRow['total_unread'] : 0;

    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'total_unread' => $totalUnread,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
