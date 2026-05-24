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

bts_require_auth(['admin', 'staff']);

function table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
}

function column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
}

function ensure_donation_history_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS donation_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT UNSIGNED NULL,
            donation_id VARCHAR(40) NULL,
            donor_id INT UNSIGNED NULL,
            donor_name VARCHAR(160) NULL,
            blood_bank_id INT UNSIGNED NOT NULL,
            blood_type VARCHAR(5) NOT NULL,
            component ENUM('Whole Blood','Packed Red Cells','Plasma','Platelets') NOT NULL DEFAULT 'Whole Blood',
            units_collected SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            donation_date DATETIME NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Completed',
            notes VARCHAR(255) NULL,
            completed_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_donation_history_donor (donor_id),
            INDEX idx_donation_history_appointment (appointment_id),
            INDEX idx_donation_history_status (status),
            INDEX idx_donation_history_date (donation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function resolve_table_row(PDO $pdo, int $appointmentId): ?array
{
    $fallbackRow = null;

    foreach (['tblappointments', 'appointments'] as $tableName) {
        if (!table_exists($pdo, $tableName)) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT * FROM `' . str_replace('`', '``', $tableName) . '` WHERE id = ? LIMIT 1');
        $stmt->execute([$appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['_table'] = $tableName;
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($status === 'confirmed') {
                return $row;
            }

            if ($fallbackRow === null) {
                $fallbackRow = $row;
            }
        }
    }

    return $fallbackRow;
}

function update_appointment_status(PDO $pdo, int $appointmentId, string $status): int
{
    $affected = 0;

    foreach (['tblappointments', 'appointments'] as $tableName) {
        if (!table_exists($pdo, $tableName)) {
            continue;
        }

        $hasUpdatedAt = column_exists($pdo, $tableName, 'updated_at');
        $sql = 'UPDATE `' . str_replace('`', '``', $tableName) . '` SET status = ?';
        if ($hasUpdatedAt) {
            $sql .= ', updated_at = NOW()';
        }
        $sql .= ' WHERE id = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $appointmentId]);
        $affected += $stmt->rowCount();
    }

    return $affected;
}

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$appointmentId = (int)($data['appointmentId'] ?? $data['id'] ?? 0);
$action = strtolower(trim((string)($data['action'] ?? '')));

if ($appointmentId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'appointmentId is required.']);
    exit;
}

if (!in_array($action, ['completed', 'deferred', 'cancelled'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

try {
    if ($action === 'completed') {
        ensure_donation_history_table($pdo);
    }

    $pdo->beginTransaction();

    $appointment = resolve_table_row($pdo, $appointmentId);
    if (!$appointment) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }

    $currentStatus = strtolower(trim((string)($appointment['status'] ?? '')));
    if ($currentStatus !== 'confirmed') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only confirmed appointments can be updated from this modal.']);
        exit;
    }

    $statusMap = [
        'completed' => 'completed',
        'deferred' => 'deferred',
        'cancelled' => 'cancelled',
    ];
    $newStatus = $statusMap[$action];

    $updatedRows = update_appointment_status($pdo, $appointmentId, $newStatus);
    if ($updatedRows === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found or already processed.']);
        exit;
    }

    $donorName = trim((string)($appointment['full_name'] ?? ''));
    $donorId = 0;
    $bloodType = trim((string)($appointment['blood_group'] ?? $appointment['blood_type'] ?? ''));
    $bloodBankId = (int)($appointment['blood_bank_id'] ?? 0);
    $bloodBankName = trim((string)($appointment['blood_bank'] ?? ''));

    if ($bloodBankId <= 0 && $bloodBankName !== '' && table_exists($pdo, 'tblblood_banks')) {
        $bankStmt = $pdo->prepare('SELECT id FROM tblblood_banks WHERE name = ? LIMIT 1');
        $bankStmt->execute([$bloodBankName]);
        $bloodBankId = (int)($bankStmt->fetchColumn() ?: 0);
    }
    if ($bloodBankId <= 0) {
        $bloodBankId = 1;
    }

    if (!empty($appointment['donor_id'])) {
        $donorId = (int)$appointment['donor_id'];
    } elseif ($donorName !== '' && table_exists($pdo, 'tbldonors')) {
        $donorStmt = $pdo->prepare('SELECT id, blood_type FROM tbldonors WHERE full_name = ? LIMIT 1');
        $donorStmt->execute([$donorName]);
        $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
        if ($donorRow) {
            $donorId = (int)($donorRow['id'] ?? 0);
            if ($bloodType === '') {
                $bloodType = trim((string)($donorRow['blood_type'] ?? ''));
            }
        }
    }

    if ($action === 'completed') {
        if ($bloodType === '' && table_exists($pdo, 'tbldonors') && $donorId > 0) {
            $donorStmt = $pdo->prepare('SELECT blood_type, full_name FROM tbldonors WHERE id = ? LIMIT 1');
            $donorStmt->execute([$donorId]);
            $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
            if ($donorRow) {
                $bloodType = trim((string)($donorRow['blood_type'] ?? ''));
                if ($donorName === '') {
                    $donorName = trim((string)($donorRow['full_name'] ?? ''));
                }
            }
        }

        if ($bloodType === '') {
            $bloodType = 'O+';
        }

        $insertColumns = [
            'appointment_id' => $appointmentId,
            'donation_id' => 'APT-' . $appointmentId . '-' . date('YmdHis'),
            'donor_id' => $donorId > 0 ? $donorId : null,
            'donor_name' => $donorName !== '' ? $donorName : null,
            'blood_bank_id' => $bloodBankId,
            'blood_type' => $bloodType,
            'component' => 'Whole Blood',
            'units_collected' => 1,
            'donation_date' => date('Y-m-d H:i:s'),
            'status' => 'Completed',
            'notes' => 'Recorded from appointment completion modal.',
            'completed_by_user_id' => null,
        ];

        $availableColumns = [];
        $values = [];
        foreach ($insertColumns as $column => $value) {
            if (!column_exists($pdo, 'donation_history', $column)) {
                continue;
            }
            $availableColumns[] = $column;
            $values[] = $value;
        }

        if (!empty($availableColumns)) {
            $placeholders = implode(', ', array_fill(0, count($availableColumns), '?'));
            $stmt = $pdo->prepare('INSERT INTO donation_history (' . implode(', ', $availableColumns) . ') VALUES (' . $placeholders . ')');
            $stmt->execute($values);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment updated successfully.',
        'data' => [
            'appointmentId' => $appointmentId,
            'status' => $newStatus,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
