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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

try {
    $whereClauses = ['user_id IS NULL']; // Admin notifications have NULL user_id
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

    if ($unreadOnly) {
        $whereClauses[] = 'is_read = 0';
    }

    if ($type) {
        $whereClauses[] = 'type = ?';
        $params[] = $type;
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
         LIMIT 50"
    );
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Count unread by type
    $countStmt = $pdo->query(
        'SELECT type, COUNT(*) as count FROM tblnotifications WHERE user_id IS NULL AND is_read = 0 GROUP BY type'
    );
    $unreadCounts = $countStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $countsByType = [];
    foreach ($unreadCounts as $row) {
        $countsByType[$row['type']] = (int)$row['count'];
    }

    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'unread_counts' => $countsByType,
        'total_unread' => array_sum($countsByType)
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
