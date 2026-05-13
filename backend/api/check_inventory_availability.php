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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['doctor', 'staff', 'admin']);

$bloodType = trim((string)($_GET['bloodType'] ?? ''));
$component = trim((string)($_GET['component'] ?? 'Packed Red Cells'));
$requiredUnits = max(1, (int)($_GET['units'] ?? 1));

if ($bloodType === '') {
    echo json_encode([
        'success' => true,
        'data' => [
            'bloodType' => '',
            'component' => $component,
            'requiredUnits' => $requiredUnits,
            'availableComponentUnits' => 0,
            'totalAvailableUnits' => 0,
            'stockLevel' => 'Unknown',
            'isSufficient' => false,
            'isLowStock' => false,
            'message' => 'Select blood type to check availability.',
        ],
    ]);
    exit;
}

$column = 'whole_units';
if ($component === 'Packed Red Cells') {
    $column = 'prbc_units';
} elseif ($component === 'Platelets') {
    $column = 'platelets_units';
} elseif ($component === 'Plasma') {
    $column = 'ffp_units';
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            IFNULL(SUM({$column}), 0) AS available_component_units,
            IFNULL(SUM(whole_units + prbc_units + platelets_units + ffp_units), 0) AS total_available_units
         FROM tblinventory
         WHERE blood_type = ?"
    );
    $stmt->execute([$bloodType]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $availableComponentUnits = (int)($row['available_component_units'] ?? 0);
    $totalAvailableUnits = (int)($row['total_available_units'] ?? 0);

    $stockLevel = 'Healthy';
    if ($availableComponentUnits <= 5) {
        $stockLevel = 'Low';
    } elseif ($availableComponentUnits <= 20) {
        $stockLevel = 'Watch';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'bloodType' => $bloodType,
            'component' => $component,
            'requiredUnits' => $requiredUnits,
            'availableComponentUnits' => $availableComponentUnits,
            'totalAvailableUnits' => $totalAvailableUnits,
            'stockLevel' => $stockLevel,
            'isSufficient' => $availableComponentUnits >= $requiredUnits,
            'isLowStock' => $availableComponentUnits > 0 && $availableComponentUnits <= 5,
            'message' => $availableComponentUnits >= $requiredUnits
                ? 'Stock is sufficient for this request.'
                : 'Insufficient stock for requested units.',
        ],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
