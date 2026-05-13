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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}

require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['staff', 'admin']);

function column_exists_compatible(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE :column_name');
        $stmt->execute([':column_name' => $columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

function normalize_component_key(string $component): string
{
    $normalized = strtolower(trim($component));
    if ($normalized === 'prbc' || $normalized === 'packed red cells') {
        return 'prbc';
    }
    if ($normalized === 'platelets') {
        return 'platelets';
    }
    if ($normalized === 'plasma' || $normalized === 'ffp' || $normalized === 'fresh frozen plasma') {
        return 'ffp';
    }
    return 'whole';
}

function get_component_options(string $component): array
{
    $key = normalize_component_key($component);
    if ($key === 'prbc') {
        return ['Packed Red Cells', 'PRBC'];
    }
    if ($key === 'platelets') {
        return ['Platelets'];
    }
    if ($key === 'ffp') {
        return ['FFP', 'Plasma', 'Fresh Frozen Plasma'];
    }
    return ['Whole Blood'];
}

try {
    $requestId = (int)($_GET['requestId'] ?? 0);
    if ($requestId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'requestId is required.']);
        exit;
    }

    $requestStmt = $pdo->prepare('SELECT id, blood_type, component, status FROM tblblood_requests WHERE id = ? LIMIT 1');
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $bloodType = trim((string)($request['blood_type'] ?? ''));
    if ($bloodType === '') {
        echo json_encode(['success' => true, 'units' => [], 'message' => 'Request has no blood type.']);
        exit;
    }

    $unitIdentifierColumn = column_exists_compatible($pdo, 'tblblood_units', 'unit_id') ? 'unit_id' : 'donation_id';
    if (!column_exists_compatible($pdo, 'tblblood_units', $unitIdentifierColumn)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'tblblood_units must contain unit_id or donation_id column.']);
        exit;
    }

    $componentOptions = get_component_options((string)($request['component'] ?? ''));
    $componentPlaceholders = implode(',', array_fill(0, count($componentOptions), '?'));

        $sql = "SELECT {$unitIdentifierColumn} AS unit_id,
                                     blood_type
            FROM tblblood_units
            WHERE blood_type = ?
              AND component IN ({$componentPlaceholders})
              AND LOWER(COALESCE(status, '')) = 'available'
              AND (request_id IS NULL OR request_id = 0)
            ORDER BY expiry_date ASC, id ASC
            LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $params = array_merge([$bloodType], $componentOptions);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $units = [];
    foreach ($rows as $row) {
        $value = trim((string)($row['unit_id'] ?? ''));
        if ($value !== '') {
            $units[] = [
                'unit_id' => $value,
                'blood_type' => trim((string)($row['blood_type'] ?? '')),
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'requestId' => $requestId,
        'bloodType' => $bloodType,
        'component' => (string)($request['component'] ?? ''),
        'units' => $units,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
