<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

function appointment_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

try {
    $tableName = 'tblappointments';
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'tblappointments'");
    if (!$tableCheck || $tableCheck->rowCount() === 0) {
        $legacyTableCheck = $pdo->query("SHOW TABLES LIKE 'appointments'");
        if ($legacyTableCheck && $legacyTableCheck->rowCount() > 0) {
            $tableName = 'appointments';
        }
    }

    $phoneColumn = appointment_column_exists($pdo, $tableName, 'phone_number') ? 'phone_number' : 'phone';
    $genderSelect = appointment_column_exists($pdo, $tableName, 'gender') ? 'gender' : 'NULL AS gender';
    $notesSelect = appointment_column_exists($pdo, $tableName, 'notes') ? 'notes' : 'NULL AS notes';

    $stmt = $pdo->query(
        "SELECT id, full_name, age, {$genderSelect}, blood_group, {$phoneColumn} AS phone_number, preferred_date, preferred_time, blood_bank, {$notesSelect}, status, created_at
         FROM {$tableName}
         ORDER BY preferred_date ASC, id DESC"
    );
    $rows = $stmt->fetchAll();

    if ($rows && $pdo->query("SHOW TABLES LIKE 'donation_history'")->rowCount() > 0) {
        $appointmentIds = array_values(array_filter(array_map(static fn($row) => (int)($row['id'] ?? 0), $rows)));
        if ($appointmentIds) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $historyStmt = $pdo->prepare(
                "SELECT dh.appointment_id, dh.status
                 FROM donation_history dh
                 INNER JOIN (
                     SELECT appointment_id, MAX(id) AS max_id
                     FROM donation_history
                     WHERE appointment_id IN ({$placeholders})
                     GROUP BY appointment_id
                 ) latest ON latest.appointment_id = dh.appointment_id AND latest.max_id = dh.id"
            );
            $historyStmt->execute($appointmentIds);
            $historyStatusByAppointment = [];
            foreach ($historyStmt->fetchAll() as $historyRow) {
                $historyStatusByAppointment[(int)($historyRow['appointment_id'] ?? 0)] = (string)($historyRow['status'] ?? '');
            }

            foreach ($rows as &$row) {
                $appointmentId = (int)($row['id'] ?? 0);
                $rawStatus = strtolower(trim((string)($row['status'] ?? '')));
                $historyStatus = strtolower(trim((string)($historyStatusByAppointment[$appointmentId] ?? '')));

                if (in_array($rawStatus, ['', 'pending', 'confirmed'], true) && $historyStatus !== '') {
                    $row['status'] = $historyStatus;
                }
            }
            unset($row);
        }
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
