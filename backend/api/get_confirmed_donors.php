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

bts_require_auth(['staff', 'admin']);

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
    $hasSampleTested = $tableHasColumn($pdo, 'tbldonors', 'sample_tested');
    
    $stmt = $pdo->query(
        'SELECT DISTINCT d.id,
                d.full_name,
                d.email,
                d.blood_type,
                d.status,
                ' . ($hasSampleTested ? 'd.sample_tested' : 'NULL AS sample_tested') . ',
                ' . ($hasSampleTested ? 'd.sample_tested_at' : 'NULL AS sample_tested_at') . ',
                d.deferred_until,
                d.deferral_reason
         FROM tbldonors d
         WHERE LOWER(TRIM(COALESCE(d.status, "pending"))) IN ("confirmed", "eligible", "active")
         ORDER BY d.full_name ASC, d.id DESC'
    );
    
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($donors)) {
        // Fallback: just get confirmed/eligible/active donors
        $stmt = $pdo->query(
            'SELECT DISTINCT d.id,
                    d.full_name,
                    d.email,
                    d.blood_type,
                    d.status,
                    ' . ($hasSampleTested ? 'd.sample_tested' : 'NULL AS sample_tested') . ',
                    ' . ($hasSampleTested ? 'd.sample_tested_at' : 'NULL AS sample_tested_at') . ',
                    d.deferred_until,
                    d.deferral_reason
             FROM tbldonors d
             WHERE LOWER(TRIM(COALESCE(d.status, "pending"))) IN ("confirmed", "eligible", "active")
             ORDER BY d.full_name ASC, d.id DESC'
        );
        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode(['success' => true, 'data' => $donors ?: []]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
