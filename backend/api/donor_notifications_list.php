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
            'data' => [],
            'count' => 0
        ]);
        exit;
    }

    $titleExpr = notif_has_column($pdo, 'title') ? 'title' : '"Notification" AS title';
    $messageExpr = notif_has_column($pdo, 'message') ? 'message' : '"" AS message';
    $isReadExpr = notif_has_column($pdo, 'is_read') ? 'is_read' : '0 AS is_read';
    $createdAtExpr = notif_has_column($pdo, 'created_at') ? 'created_at' : 'NOW() AS created_at';
    $orderBy = notif_has_column($pdo, 'created_at') ? 'created_at DESC' : 'id DESC';

    $sql = 'SELECT id, ' . $titleExpr . ', ' . $messageExpr . ', ' . $isReadExpr . ', ' . $createdAtExpr
         . ' FROM tblnotifications'
         . ' WHERE (' . implode(' OR ', $whereParts) . ')'
         . ' ORDER BY ' . $orderBy
         . ' LIMIT 50';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format notifications with time ago
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $createdTime = strtotime($notification['created_at']);
        $currentTime = time();
        $timeDiff = $currentTime - $createdTime;

        if ($timeDiff < 60) {
            $timeAgo = 'Just now';
        } elseif ($timeDiff < 3600) {
            $minutes = floor($timeDiff / 60);
            $timeAgo = $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($timeDiff < 86400) {
            $hours = floor($timeDiff / 3600);
            $timeAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($timeDiff < 2592000) {
            $days = floor($timeDiff / 86400);
            $timeAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            $timeAgo = date('M d, Y', $createdTime);
        }

        $formattedNotifications[] = [
            'id' => (int)$notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'isRead' => (int)$notification['is_read'],
            'createdAt' => $notification['created_at'],
            'timeAgo' => $timeAgo
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $formattedNotifications,
        'count' => count($formattedNotifications)
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
}
