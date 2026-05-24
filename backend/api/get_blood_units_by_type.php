<?php
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

bts_require_auth(['staff', 'admin']);

function table_exists_units(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $ignored) {
        return false;
    }
}

function normalize_component_filter(string $component): array
{
    $normalized = strtolower(trim($component));
    return match ($normalized) {
        'prbc', 'packed red cells' => ['packed red cells', 'prbc'],
        'whole blood', 'wholeblood' => ['whole blood'],
        'platelets' => ['platelets'],
        'ffp', 'plasma', 'fresh frozen plasma' => ['plasma', 'ffp', 'fresh frozen plasma'],
        default => [$normalized],
    };
}

function days_left_from_date(?string $expiryDate): ?int
{
    if (!$expiryDate) {
        return null;
    }

    try {
        $expiry = new DateTimeImmutable($expiryDate . ' 00:00:00');
        $today = new DateTimeImmutable('today');
        return (int)$today->diff($expiry)->format('%r%a');
    } catch (Throwable $ignored) {
        return null;
    }
}

try {
    $bloodType = strtoupper(trim((string)($_GET['blood_type'] ?? '')));
    $component = trim((string)($_GET['component'] ?? ''));

    if ($bloodType === '' || $component === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'blood_type and component are required.']);
        exit;
    }

    $tableName = null;
    if (table_exists_units($pdo, 'tblblood_units')) {
        $tableName = 'tblblood_units';
    } elseif (table_exists_units($pdo, 'blood_units')) {
        $tableName = 'blood_units';
    }

    if ($tableName === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Blood units table not found.']);
        exit;
    }

    $componentVariants = normalize_component_filter($component);
    $placeholders = implode(', ', array_fill(0, count($componentVariants), '?'));
    $sql = "SELECT id, donation_id, expiry_date, status, blood_type, component
            FROM {$tableName}
            WHERE UPPER(TRIM(blood_type)) = ?
              AND LOWER(TRIM(status)) = 'available'
              AND LOWER(TRIM(component)) IN ({$placeholders})
            ORDER BY expiry_date ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$bloodType], $componentVariants));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $units = array_map(static function (array $row): array {
        $expiryDate = (string)($row['expiry_date'] ?? '');
        $daysLeft = days_left_from_date($expiryDate);
        return [
            'id' => (int)$row['id'],
            'donation_id' => $row['donation_id'] !== null ? (string)$row['donation_id'] : '',
            'expiry_date' => $expiryDate,
            'days_left' => $daysLeft,
            'status' => (string)($row['status'] ?? 'Available'),
            'blood_type' => (string)($row['blood_type'] ?? ''),
            'component' => (string)($row['component'] ?? ''),
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'blood_type' => $bloodType,
        'component' => $component,
        'total_units' => count($units),
        'units' => $units,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}