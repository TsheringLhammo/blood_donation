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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}

require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$id = (int)($data['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid blood bank id.']);
    exit;
}

function table_exists_delete(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $ignored) {
        return false;
    }
}

function column_exists_delete(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column_name");
        $stmt->execute([':column_name' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

try {
    if (!table_exists_delete($pdo, 'tblblood_banks')) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Blood bank database table not initialized. Please run database migration.']);
        exit;
    }

    $hasIsActive = column_exists_delete($pdo, 'tblblood_banks', 'is_active');
    $hasDirectoryStatus = column_exists_delete($pdo, 'tblblood_banks', 'directory_status');

    if (!$hasIsActive && !$hasDirectoryStatus) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Blood bank table is missing archive status columns (is_active/directory_status).']);
        exit;
    }

    $setClauses = [];
    if ($hasIsActive) {
        $setClauses[] = 'is_active = 0';
    }
    if ($hasDirectoryStatus) {
        $setClauses[] = 'directory_status = "inactive"';
    }

    $stmt = $pdo->prepare('UPDATE tblblood_banks SET ' . implode(', ', $setClauses) . ' WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Blood bank not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Blood bank archived successfully.']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
