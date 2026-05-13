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

require_once __DIR__ . '/../config/db.php';

function has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
}

try {
    $baseWhere = ['is_active = 1'];
    if (has_column($pdo, 'tblblood_banks', 'directory_status')) {
        $baseWhere[] = 'LOWER(directory_status) = "active"';
    }

    $confirmedWhere = $baseWhere;
    $hasStatusColumn = has_column($pdo, 'tblblood_banks', 'status');
    if ($hasStatusColumn) {
        $confirmedWhere[] = 'LOWER(status) = "confirmed"';
    }

    $confirmedSql =
        'SELECT id, name, location, address, phone, status
         FROM tblblood_banks
         WHERE ' . implode(' AND ', $confirmedWhere) . '
         ORDER BY name ASC';
    $stmt = $pdo->query($confirmedSql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Backward compatibility: older datasets may use status="active" instead of "confirmed".
    if ($rows === [] && $hasStatusColumn) {
        $activeWhere = $baseWhere;
        $activeWhere[] = 'LOWER(status) = "active"';
        $activeSql =
            'SELECT id, name, location, address, phone, status
             FROM tblblood_banks
             WHERE ' . implode(' AND ', $activeWhere) . '
             ORDER BY name ASC';
        $fallbackStmt = $pdo->query($activeSql);
        $rows = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
