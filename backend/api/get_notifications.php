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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['doctor', 'staff', 'admin', 'donor']);
$userId = (int)($claims['sub'] ?? 0);
$role = trim((string)($claims['role'] ?? ''));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit < 1 || $limit > 100) {
    $limit = 20;
}

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

try {
    $notificationColumns = [];
    foreach (['id', 'donor_id', 'admin_id', 'user_id', 'role_target', 'type', 'title', 'message', 'severity', 'channel', 'is_read', 'created_at'] as $columnName) {
        if ($tableHasColumn($pdo, 'tblnotifications', $columnName)) {
            $notificationColumns[] = $columnName;
        }
    }

    $select = [
        'n.id',
        in_array('donor_id', $notificationColumns, true) ? 'n.donor_id' : 'NULL AS donor_id',
        in_array('admin_id', $notificationColumns, true) ? 'n.admin_id' : 'NULL AS admin_id',
        in_array('user_id', $notificationColumns, true) ? 'n.user_id' : 'NULL AS user_id',
        in_array('role_target', $notificationColumns, true) ? 'n.role_target' : 'NULL AS role_target',
        in_array('type', $notificationColumns, true) ? 'n.type' : (in_array('role_target', $notificationColumns, true) ? 'COALESCE(n.role_target, "info") AS type' : '"info" AS type'),
        in_array('title', $notificationColumns, true) ? 'n.title' : '"" AS title',
        in_array('message', $notificationColumns, true) ? 'n.message' : '"" AS message',
        in_array('severity', $notificationColumns, true) ? 'n.severity' : '"info" AS severity',
        in_array('channel', $notificationColumns, true) ? 'n.channel' : '"in_app" AS channel',
        in_array('is_read', $notificationColumns, true) ? 'n.is_read' : '0 AS is_read',
        in_array('created_at', $notificationColumns, true) ? 'n.created_at' : 'NOW() AS created_at',
    ];

    $joinDonor = in_array('donor_id', $notificationColumns, true)
        ? 'LEFT JOIN tbldonors donor ON donor.id = n.donor_id'
        : '';

    $select[] = in_array('donor_id', $notificationColumns, true) ? 'donor.full_name AS donor_name' : 'NULL AS donor_name';
    $select[] = in_array('donor_id', $notificationColumns, true) ? 'donor.deferral_reason AS deferral_reason' : 'NULL AS deferral_reason';
    $select[] = in_array('donor_id', $notificationColumns, true) ? 'donor.deferred_until AS deferred_until' : 'NULL AS deferred_until';

    $conditions = [];
    if (in_array('user_id', $notificationColumns, true)) {
        $conditions[] = 'n.user_id = :user_id';
    }
    if (in_array('admin_id', $notificationColumns, true)) {
        $conditions[] = 'n.admin_id = :admin_id';
    }
    if (in_array('role_target', $notificationColumns, true)) {
        $conditions[] = 'n.role_target = :role_target';
    }

    if (empty($conditions)) {
        $conditions[] = '1 = 1';
    }

    $bindings = [];
    if (in_array('user_id', $notificationColumns, true)) {
        $bindings[':user_id'] = $userId;
    }
    if (in_array('admin_id', $notificationColumns, true)) {
        $bindings[':admin_id'] = $userId;
    }
    if (in_array('role_target', $notificationColumns, true)) {
        $bindings[':role_target'] = $role;
    }
    $bindings[':limit_rows'] = $limit;

    $stmt = $pdo->prepare(
        'SELECT ' . implode(",\n                ", $select) . '
         FROM tblnotifications n
         ' . $joinDonor . '
         WHERE ' . implode(' OR ', $conditions) . '
         ORDER BY n.id DESC
         LIMIT :limit_rows'
    );
    foreach ($bindings as $placeholder => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($placeholder, $value, $paramType);
    }
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
