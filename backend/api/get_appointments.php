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

    $stmt = $pdo->query(
        "SELECT id, full_name, age, {$genderSelect}, blood_group, {$phoneColumn} AS phone_number, preferred_date, preferred_time, blood_bank, status, created_at
         FROM {$tableName}
         ORDER BY preferred_date ASC, id DESC"
    );
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
