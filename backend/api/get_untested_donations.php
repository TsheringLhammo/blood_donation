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
    if (!$tableHasColumn($pdo, 'tblblood_units', 'donation_id')) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $hasBloodUnitDonorId = $tableHasColumn($pdo, 'tblblood_units', 'donor_id');
    $hasBloodDonationTests = $tableHasColumn($pdo, 'tbldonation_tests', 'donation_id');
    $hasDonorStatus = $tableHasColumn($pdo, 'tbldonors', 'status');
    $hasDeferredUntil = $tableHasColumn($pdo, 'tbldonors', 'deferred_until');
    $hasDeferralReason = $tableHasColumn($pdo, 'tbldonors', 'deferral_reason');

    $donorJoin = $hasBloodUnitDonorId
        ? 'LEFT JOIN tbldonors donor ON donor.id = u.donor_id'
        : 'LEFT JOIN tbldonations d ON CAST(d.id AS CHAR) = CAST(u.donation_id AS CHAR)
           LEFT JOIN tbldonors donor ON donor.id = d.donor_id';

    $donorIdSelect = $hasBloodUnitDonorId
        ? 'MIN(u.donor_id) AS donor_id'
        : 'MIN(d.donor_id) AS donor_id';

    $donorNameSelect = $hasBloodUnitDonorId
        ? 'MIN(COALESCE(donor.full_name, "Unknown donor")) AS donor_name'
        : 'MIN(COALESCE(donor.full_name, d.donor_name, "Unknown donor")) AS donor_name';

    $donorStatusSelect = $hasDonorStatus ? 'MIN(COALESCE(donor.status, "Confirmed")) AS donor_status' : '"Confirmed" AS donor_status';
    $deferredUntilSelect = $hasDeferredUntil ? 'MIN(donor.deferred_until) AS deferred_until' : 'NULL AS deferred_until';
    $deferralReasonSelect = $hasDeferralReason ? 'MIN(donor.deferral_reason) AS deferral_reason' : 'NULL AS deferral_reason';

    $donationTestExists = $hasBloodDonationTests
        ? 'AND NOT EXISTS (
                SELECT 1
                FROM tbldonation_tests t
                WHERE CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
            )'
        : '';

    $donationsSql = 'SELECT
                        CAST(u.donation_id AS CHAR) AS donation_id,
                        MIN(u.blood_type) AS blood_type,
                        MIN(u.component) AS component,
                        COUNT(*) AS unit_count,
                        MAX(u.created_at) AS latest_unit_at,
                        ' . $donorIdSelect . ',
                        ' . $donorNameSelect . ',
                        ' . $donorStatusSelect . ',
                        ' . $deferredUntilSelect . ',
                        ' . $deferralReasonSelect . '
                FROM tblblood_units u
                ' . $donorJoin . '
                WHERE u.donation_id IS NOT NULL
                    AND TRIM(CAST(u.donation_id AS CHAR)) <> ""
                    ' . $donationTestExists . '
                GROUP BY CAST(u.donation_id AS CHAR)
                ORDER BY latest_unit_at DESC
                LIMIT 200';

    $rows = $pdo->query($donationsSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rows = array_map(static function (array $row): array {
        return [
            'donation_id' => (string)($row['donation_id'] ?? ''),
            'blood_type' => (string)($row['blood_type'] ?? ''),
            'component' => (string)($row['component'] ?? ''),
            'unit_count' => (int)($row['unit_count'] ?? 0),
            'latest_unit_at' => (string)($row['latest_unit_at'] ?? ''),
            'donorId' => (int)($row['donor_id'] ?? 0),
            'donorName' => (string)($row['donor_name'] ?? ''),
            'donorStatus' => (string)($row['donor_status'] ?? 'Confirmed'),
            'deferred_until' => $row['deferred_until'] ?? null,
            'deferral_reason' => $row['deferral_reason'] ?? null,
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'data' => $rows,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
