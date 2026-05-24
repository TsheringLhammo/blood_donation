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

require_once __DIR__ . '/_common.php';

$claims = bts_require_auth(['admin']);
$adminId = (int)($claims['sub'] ?? 0);
$adminName = trim((string)($claims['name'] ?? 'Admin'));

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donorId = (int)($payload['id'] ?? $payload['donor_id'] ?? 0);
if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Donor id is required.']);
    exit;
}

$name = trim((string)($payload['name'] ?? $payload['full_name'] ?? ''));
$email = strtolower(trim((string)($payload['email'] ?? '')));
$phone = trim((string)($payload['phone'] ?? ''));
$address = trim((string)($payload['address'] ?? ''));
$cid = trim((string)($payload['cid'] ?? ''));

if ($name === '' || $email === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Name, email, and phone are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

try {
    $donorTable = bts_donor_records_pick_donor_table($pdo);
    $table = $donorTable['table'];
    $nameColumn = $donorTable['name_column'] ?? 'full_name';
    $cidColumn = $donorTable['cid_column'] ?? null;

    $currentStmt = $pdo->prepare('SELECT * FROM `' . str_replace('`', '``', $table) . '` WHERE id = ? LIMIT 1');
    $currentStmt->execute([$donorId]);
    $currentRow = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    $existingEmailStmt = $pdo->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
    $existingEmailStmt->execute([$email, $donorId]);
    if ($existingEmailStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Email already exists for another donor.']);
        exit;
    }

    $updates = [];
    $values = [];

    if ($cid !== '') {
        $digitsCid = preg_replace('/\D+/', '', $cid) ?? '';
        if (strlen($digitsCid) !== 11) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'CID must be exactly 11 digits.']);
            exit;
        }
        $cid = $digitsCid;

        if ($cidColumn !== null) {
            $existingCidStmt = $pdo->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE `' . str_replace('`', '``', $cidColumn) . '` = ? AND id <> ? LIMIT 1');
            $existingCidStmt->execute([$cid, $donorId]);
            if ($existingCidStmt->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'This CID is already registered for another donor.']);
                exit;
            }
        }
    }

    if ($nameColumn !== null) {
        $updates[] = '`' . str_replace('`', '``', $nameColumn) . '` = ?';
        $values[] = $name;
    }

    if ($cidColumn !== null && array_key_exists($cidColumn, $currentRow) && $cid !== '') {
        $updates[] = '`' . str_replace('`', '``', $cidColumn) . '` = ?';
        $values[] = $cid;
    }

    if (array_key_exists('email', $currentRow)) {
        $updates[] = 'email = ?';
        $values[] = $email;
    }

    if (array_key_exists('phone', $currentRow)) {
        $updates[] = 'phone = ?';
        $values[] = $phone;
    }

    if (array_key_exists('address', $currentRow)) {
        $updates[] = 'address = ?';
        $values[] = $address;
    }

    if (bts_donor_records_column_exists($pdo, $table, 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }

    if ($updates === []) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No editable fields available for this donor table.']);
        exit;
    }

    $values[] = $donorId;
    $sql = 'UPDATE `' . str_replace('`', '``', $table) . '` SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    if (bts_donor_records_table_exists($pdo, 'tbldonor_audit_log')) {
        $auditStmt = $pdo->prepare(
            'INSERT INTO tbldonor_audit_log (donor_id, changed_by_admin_id, changed_by_admin_name, field_name, old_value, new_value, changed_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ([
            [$nameColumn, (string)($currentRow[$nameColumn] ?? ''), $name],
            [$cidColumn, (string)($currentRow[$cidColumn] ?? ''), $cid],
            ['email', (string)($currentRow['email'] ?? ''), $email],
            ['phone', (string)($currentRow['phone'] ?? ''), $phone],
            ['address', (string)($currentRow['address'] ?? ''), $address],
        ] as [$field, $oldValue, $newValue]) {
            if ($field === null || $field === '' || $newValue === '' || $oldValue === $newValue) {
                continue;
            }

            $auditStmt->execute([$donorId, $adminId, $adminName, $field, $oldValue, $newValue]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Donor information updated successfully.',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $exception->getMessage()]);
}
