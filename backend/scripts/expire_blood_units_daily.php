<?php
declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../config/db.php';

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$resolveExpiredStatus = static function (PDO $pdo): string {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tblblood_units LIKE 'status'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$row || !isset($row['Type'])) {
            return 'Expired';
        }

        $type = (string)$row['Type'];
        if (preg_match('/^enum\((.*)\)$/i', $type, $matches) !== 1) {
            return 'Expired';
        }

        $rawValues = array_map('trim', explode(',', $matches[1]));
        $values = array_map(static fn(string $v): string => trim($v, "'\""), $rawValues);

        if (in_array('Expired', $values, true)) {
            return 'Expired';
        }
        if (in_array('Rejected', $values, true)) {
            return 'Rejected';
        }
    } catch (Throwable $exception) {
        return 'Expired';
    }

    return 'Expired';
};

try {
    if (!$tableHasColumn($pdo, 'tblblood_units', 'donation_id') || !$tableHasColumn($pdo, 'tblblood_units', 'status')) {
        throw new RuntimeException('tblblood_units schema is missing required columns.');
    }

    if (!$tableHasColumn($pdo, 'tbldonation_tests', 'donation_id') || !$tableHasColumn($pdo, 'tbldonation_tests', 'final_result')) {
        throw new RuntimeException('tbldonation_tests schema is missing required columns.');
    }

    $expiredStatus = $resolveExpiredStatus($pdo);

    $sql =
        'UPDATE tblblood_units u
         INNER JOIN tbldonation_tests t
            ON CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
         SET u.status = :expired_status,
             u.updated_at = CURRENT_TIMESTAMP
                 WHERE t.final_result IN ("Eligible", "Safe")
           AND u.expiry_date < CURDATE()
           AND u.status IN ("Available", "Reserved")';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':expired_status' => $expiredStatus]);

    echo json_encode([
        'success' => true,
        'message' => 'Expired unit updater completed.',
        'expiredStatusUsed' => $expiredStatus,
        'rowsUpdated' => $stmt->rowCount(),
        'ranAt' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'ranAt' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_SLASHES);
}
