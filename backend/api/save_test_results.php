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

if (file_exists(__DIR__ . '/../config/mailer.php')) {
    require_once __DIR__ . '/../config/mailer.php';
}

if (!function_exists('bts_send_email')) {
    function bts_send_email(...$args): bool
    {
        return false;
    }
}

$claims = bts_require_auth(['staff', 'admin', 'doctor']);
$actorUserId = (int)($claims['sub'] ?? 0);
$actorName = trim((string)($claims['name'] ?? $claims['full_name'] ?? ''));

$tableColumnsCache = [];
$getTableColumns = static function (PDO $pdo, string $tableName) use (&$tableColumnsCache): array {
    if (isset($tableColumnsCache[$tableName])) {
        return $tableColumnsCache[$tableName];
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`');
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['Field'])) {
                $columns[] = (string)$row['Field'];
            }
        }
        $tableColumnsCache[$tableName] = $columns;
        return $columns;
    } catch (Throwable $exception) {
        $tableColumnsCache[$tableName] = [];
        return [];
    }
};

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName) use ($getTableColumns): bool {
    return in_array($columnName, $getTableColumns($pdo, $tableName), true);
};

$normalizeResult = static function (?string $value): ?string {
    $normalized = strtolower(trim((string)$value));
    if ($normalized === 'reactive' || $normalized === 'positive') {
        return 'Reactive';
    }
    if ($normalized === 'non-reactive' || $normalized === 'negative' || $normalized === 'non reactive') {
        return 'Non-reactive';
    }
    if ($normalized === '') {
        return 'Non-reactive';
    }
    return null;
};

$pickInput = static function (array $data, array $keys, ?string $default = null): ?string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            return is_string($data[$key]) || is_numeric($data[$key]) ? (string)$data[$key] : $default;
        }
    }
    return $default;
};

$insertRow = static function (PDO $pdo, string $tableName, array $data) use ($getTableColumns): int {
    $availableColumns = array_flip($getTableColumns($pdo, $tableName));
    $columns = [];
    $placeholders = [];
    $values = [];

    foreach ($data as $column => $value) {
        if (!isset($availableColumns[$column])) {
            continue;
        }

        if ($value === null) {
            continue;
        }

        $columns[] = '`' . str_replace('`', '``', $column) . '`';
        $placeholders[] = ':' . $column;
        $values[':' . $column] = $value;
    }

    if (empty($columns)) {
        throw new RuntimeException('No writable columns found for ' . $tableName . '.');
    }

    $sql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    return (int)$pdo->lastInsertId();
};

$updateRow = static function (PDO $pdo, string $tableName, array $data, string $whereSql, array $whereValues) use ($getTableColumns): int {
    $availableColumns = array_flip($getTableColumns($pdo, $tableName));
    $assignments = [];
    $values = [];

    foreach ($data as $column => $value) {
        if (!isset($availableColumns[$column])) {
            continue;
        }

        $assignments[] = '`' . str_replace('`', '``', $column) . '` = :' . $column;
        $values[':' . $column] = $value;
    }

    if (empty($assignments)) {
        return 0;
    }

    $sql = 'UPDATE `' . str_replace('`', '``', $tableName) . '` SET ' . implode(', ', $assignments) . ' WHERE ' . $whereSql;
    $stmt = $pdo->prepare($sql);
    foreach ($values as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    foreach ($whereValues as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->rowCount();
};

$sendEmail = static function (string $to, string $subject, string $plainBody, string $htmlBody = ''): bool {
    if (function_exists('bts_send_email')) {
        try {
            return (bool)bts_send_email($to, $subject, $plainBody, $htmlBody !== '' ? $htmlBody : $plainBody, []);
        } catch (Throwable $exception) {
            // Fall through to mail().
        }
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: Blood Bank <no-reply@bloodbank.local>';

    return @mail($to, $subject, $plainBody, implode("\r\n", $headers));
};

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donationId = trim((string)($pickInput($data, ['donation_id', 'donationId'], '') ?? ''));
if ($donationId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donation_id is required.']);
    exit;
}

$hiv = $normalizeResult($pickInput($data, ['hiv', 'hivResult'], 'Non-reactive'));
$hbsag = $normalizeResult($pickInput($data, ['hbsag', 'hbsagResult'], 'Non-reactive'));
$hcv = $normalizeResult($pickInput($data, ['hcv', 'hcvResult'], 'Non-reactive'));
$syphilis = $normalizeResult($pickInput($data, ['syphilis', 'syphilisResult'], 'Non-reactive'));
$malaria = $normalizeResult($pickInput($data, ['malaria', 'malariaResult'], 'Non-reactive'));
$remarks = trim((string)($pickInput($data, ['remarks', 'notes'], '') ?? ''));
$technicianName = trim((string)($pickInput($data, ['technician', 'technicianName', 'technician_name'], '') ?? ''));

if ($hiv === null || $hbsag === null || $hcv === null || $syphilis === null || $malaria === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid result value. Use Non-reactive or Reactive.']);
    exit;
}

$testResults = [
    'HIV' => $hiv,
    'HBsAg' => $hbsag,
    'HCV' => $hcv,
    'Syphilis' => $syphilis,
    'Malaria' => $malaria,
];

$reactiveTestName = null;
foreach ($testResults as $testName => $result) {
    if ($result === 'Reactive') {
        $reactiveTestName = $testName;
        break;
    }
}

$isReactive = $reactiveTestName !== null;
$deferredUntil = $isReactive ? date('Y-m-d', strtotime('+6 months')) : null;
$deferralReason = $isReactive ? 'Positive ' . $reactiveTestName : null;

try {
    $pdo->beginTransaction();

    $donationSql = 'SELECT
                        u.id AS blood_unit_row_id,
                        CAST(u.donation_id AS CHAR) AS donation_id,
                        u.donor_id AS unit_donor_id,
                        u.blood_type,
                        u.component,
                        donor.full_name AS donor_name,
                        donor.email AS donor_email,
                        donor.status AS donor_status,
                        donor.deferred_until,
                        donor.deferral_reason,
                        legacy.donor_id AS legacy_donor_id,
                        legacy.donor_name AS legacy_donor_name,
                        legacy.email AS legacy_email
                    FROM tblblood_units u
                    LEFT JOIN tbldonors donor ON donor.id = u.donor_id
                    LEFT JOIN tbldonations legacy ON CAST(legacy.id AS CHAR) = CAST(u.donation_id AS CHAR)
                    WHERE CAST(u.donation_id AS CHAR) = :donation_id
                       OR u.donation_id = :numeric_donation_id
                    LIMIT 1 FOR UPDATE';

    $donationStmt = $pdo->prepare($donationSql);
    $donationStmt->execute([
        ':donation_id' => $donationId,
        ':numeric_donation_id' => ctype_digit($donationId) ? (int)$donationId : 0,
    ]);
    $donation = $donationStmt->fetch(PDO::FETCH_ASSOC);

    if (!$donation) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donation not found in tblblood_units.']);
        exit;
    }

    $donorId = (int)($donation['unit_donor_id'] ?? 0);
    if ($donorId <= 0) {
        $donorId = (int)($donation['legacy_donor_id'] ?? 0);
    }

    $donorName = trim((string)($donation['donor_name'] ?? ''));
    if ($donorName === '') {
        $donorName = trim((string)($donation['legacy_donor_name'] ?? ''));
    }
    if ($donorName === '') {
        $donorName = 'Unknown donor';
    }

    $donorEmail = trim((string)($donation['donor_email'] ?? ''));
    if ($donorEmail === '') {
        $donorEmail = trim((string)($donation['legacy_email'] ?? ''));
    }

    if ($donorId <= 0) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'tblblood_units.donor_id is missing for this donation.']);
        exit;
    }

    $testRowData = [
        'donation_id' => $donationId,
        'donor_id' => $donorId,
        'hiv' => $hiv,
        'hbsag' => $hbsag,
        'hcv' => $hcv,
        'syphilis' => $syphilis,
        'malaria' => $malaria,
        'test_date' => date('Y-m-d H:i:s'),
        'technician' => $technicianName !== '' ? $technicianName : ($actorName !== '' ? $actorName : null),
        'remarks' => $remarks !== '' ? $remarks : null,
        'final_result' => $isReactive ? 'Reactive' : 'Non-reactive',
        'hiv_result' => $hiv,
        'hbsag_result' => $hbsag,
        'hcv_result' => $hcv,
        'syphilis_result' => $syphilis,
        'malaria_result' => $malaria,
        'tested_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
        'tested_at' => date('Y-m-d H:i:s'),
    ];
    $testId = $insertRow($pdo, 'tbldonation_tests', $testRowData);

    $donorUpdate = [
        'status' => $isReactive ? 'Deferred' : 'Confirmed',
        'deferred' => $isReactive ? 1 : 0,
        'deferred_until' => $isReactive ? $deferredUntil : null,
        'deferral_reason' => $isReactive ? $deferralReason : null,
    ];
    if ($tableHasColumn($pdo, 'tbldonors', 'updated_at')) {
        $donorUpdate['updated_at'] = date('Y-m-d H:i:s');
    }
    $updateRow($pdo, 'tbldonors', $donorUpdate, 'id = :donor_id', [':donor_id' => $donorId]);

    $donorUserId = null;
    if ($donorEmail !== '' && $tableHasColumn($pdo, 'tblusers', 'email')) {
        $userStmt = $pdo->prepare('SELECT id FROM tblusers WHERE email = :email LIMIT 1');
        $userStmt->execute([':email' => $donorEmail]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $donorUserId = (int)$userRow['id'];
        }
    }

    $adminMessage = null;
    $donorMessage = null;

    if ($isReactive) {
        $adminMessage = sprintf('Donor %s deferred due to positive %s', $donorName, $reactiveTestName);
        $donorMessage = sprintf(
            'A test result requires you to not donate until %s. Contact blood bank for details.',
            date('F j, Y', strtotime($deferredUntil))
        );

        $insertRow($pdo, 'tblnotifications', [
            'donor_id' => $donorId,
            'admin_id' => $actorUserId > 0 ? $actorUserId : null,
            'user_id' => $donorUserId,
            'role_target' => 'donor',
            'type' => 'donor',
            'title' => 'Temporary Deferral Notice',
            'message' => $donorMessage,
            'severity' => 'warning',
            'channel' => 'both',
            'is_read' => 0,
        ]);

        $insertRow($pdo, 'tblnotifications', [
            'donor_id' => $donorId,
            'admin_id' => $actorUserId > 0 ? $actorUserId : null,
            'user_id' => $actorUserId > 0 ? $actorUserId : null,
            'role_target' => 'admin',
            'type' => 'admin',
            'title' => 'Donor Deferred - Positive Test',
            'message' => $adminMessage,
            'severity' => 'critical',
            'channel' => 'in_app',
            'is_read' => 0,
        ]);

        if ($donorEmail !== '' && filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
            $subject = 'Blood Donation Deferred';
            $body = sprintf(
                "Dear %s,\n\nA %s test result requires you to not donate until %s. Contact the blood bank for details.\n\nDeferral reason: %s\n\nThank you,\nBlood Bank",
                $donorName,
                $reactiveTestName,
                date('F j, Y', strtotime($deferredUntil)),
                $deferralReason
            );
            $sendEmail($donorEmail, $subject, $body, nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')));
        }
    } else {
        $donorMessage = 'Your tests are negative. Thank you for donating.';

        $insertRow($pdo, 'tblnotifications', [
            'donor_id' => $donorId,
            'admin_id' => $actorUserId > 0 ? $actorUserId : null,
            'user_id' => $donorUserId,
            'role_target' => 'donor',
            'type' => 'donor',
            'title' => 'Test Results Saved',
            'message' => $donorMessage,
            'severity' => 'success',
            'channel' => 'in_app',
            'is_read' => 0,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $isReactive ? 'Test results saved. Donor has been deferred.' : 'Test results saved. Donor confirmed.',
        'data' => [
            'test_id' => $testId,
            'donation_id' => $donationId,
            'donor_id' => $donorId,
            'donor_name' => $donorName,
            'donor_status' => $isReactive ? 'Deferred' : 'Confirmed',
            'deferral_reason' => $deferralReason,
            'deferred_until' => $deferredUntil,
            'reactive_test' => $reactiveTestName,
            'hiv' => $hiv,
            'hbsag' => $hbsag,
            'hcv' => $hcv,
            'syphilis' => $syphilis,
            'malaria' => $malaria,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving test results: ' . $exception->getMessage(),
    ]);
}
