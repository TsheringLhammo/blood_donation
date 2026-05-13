<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$claims = bts_require_auth(['admin']);
$adminId = (int)($claims['sub'] ?? 0);
$adminName = trim((string)($claims['name'] ?? ''));

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donorId = (int)($payload['donor_id'] ?? 0);
if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donor_id is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get current donor record
    $getDonorStmt = $pdo->prepare(
        'SELECT full_name, email, phone, date_of_birth, gender, blood_type, status, deferred, deferred_until
         FROM tbldonors
         WHERE id = ?
         LIMIT 1 FOR UPDATE'
    );
    $getDonorStmt->execute([$donorId]);
    $currentDonor = $getDonorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentDonor) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    // Allowed fields to update
    $allowedFields = [
        'full_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'blood_type',
        'status',
        'deferred',
        'deferred_until',
    ];

    $updateFields = [];
    $updateValues = [];
    $auditLogs = [];

    foreach ($allowedFields as $field) {
        if (!isset($payload[$field])) {
            continue;
        }

        $newValue = $payload[$field];
        $oldValue = $currentDonor[$field] ?? null;

        // Skip if value hasn't changed
        if ($newValue === $oldValue) {
            continue;
        }

        // Validation
        if ($field === 'email') {
            $newValue = trim(strtolower((string)$newValue));
            if (!filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
                exit;
            }

            // Check if email is unique (excluding current donor)
            $checkEmailStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE email = ? AND id != ?');
            $checkEmailStmt->execute([$newValue, $donorId]);
            if ($checkEmailStmt->fetch()) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Email already in use.']);
                exit;
            }
        } elseif ($field === 'phone') {
            $newValue = trim((string)$newValue);
            if (strlen($newValue) < 7 || strlen($newValue) > 30) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
                exit;
            }
        } elseif ($field === 'full_name') {
            $newValue = trim((string)$newValue);
            if (strlen($newValue) < 2 || strlen($newValue) > 120) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Name must be 2-120 characters.']);
                exit;
            }
        } elseif ($field === 'date_of_birth') {
            $newValue = trim((string)$newValue);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newValue)) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid date format (YYYY-MM-DD).']);
                exit;
            }
        } elseif ($field === 'gender') {
            $validGenders = ['Male', 'Female', 'Other'];
            $normalizedGender = ucfirst(strtolower(trim((string)$newValue)));
            if (!in_array($normalizedGender, $validGenders, true)) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid gender value.']);
                exit;
            }
            $newValue = $normalizedGender;
        } elseif ($field === 'blood_type') {
            $validTypes = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
            if (!in_array(strtoupper((string)$newValue), $validTypes, true)) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid blood type.']);
                exit;
            }
        } elseif ($field === 'status') {
            $validStatuses = ['Pending', 'Awaiting Review', 'Ready for Blood Draw', 'Blood Donated', 'Tested - Negative', 'Approved Donor', 'Temporarily Deferred', 'Permanently Deferred'];
            if (!in_array((string)$newValue, $validStatuses, true)) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
                exit;
            }
        } elseif ($field === 'deferred') {
            $newValue = (int)(bool)$newValue;
        } elseif ($field === 'deferred_until') {
            if ($newValue !== null) {
                $newValue = trim((string)$newValue);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newValue)) {
                    $pdo->rollBack();
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'Invalid deferral date format (YYYY-MM-DD).']);
                    exit;
                }
            }
        }

        $updateFields[] = "$field = ?";
        $updateValues[] = $newValue;

        // Log the change for audit
        $auditLogs[] = [
            'field_name' => $field,
            'old_value' => (string)($oldValue ?? ''),
            'new_value' => (string)$newValue,
        ];
    }

    if (empty($updateFields)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    // Add updated_at only when the table supports it
    if ($tableHasColumn($pdo, 'tbldonors', 'updated_at')) {
        $updateFields[] = 'updated_at = NOW()';
    }
    $updateValues[] = $donorId;

    // Update donor record
    $updateStmt = $pdo->prepare('UPDATE tbldonors SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
    $updateStmt->execute($updateValues);

    // Log all changes to audit table
    $auditStmt = $pdo->prepare(
        'INSERT INTO tbldonor_audit_log (donor_id, changed_by_admin_id, changed_by_admin_name, field_name, old_value, new_value, changed_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );

    foreach ($auditLogs as $log) {
        $auditStmt->execute([
            $donorId,
            $adminId,
            $adminName,
            $log['field_name'],
            $log['old_value'],
            $log['new_value'],
        ]);
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Donor updated successfully with ' . count($auditLogs) . ' field(s) changed.',
        'fieldsUpdated' => count($auditLogs),
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
