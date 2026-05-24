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
$name = trim((string)($data['name'] ?? ''));
$hospital = trim((string)($data['hospital'] ?? ''));
$dzongkhag = trim((string)($data['dzongkhag'] ?? ''));
$address = trim((string)($data['address'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$emergencyPhone = trim((string)($data['emergencyPhone'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$hours = trim((string)($data['hours'] ?? ''));
$hoursJson = $data['hoursJson'] ?? null;
$emergency = trim((string)($data['emergency'] ?? ''));
$availabilityStatus = trim((string)($data['availabilityStatus'] ?? 'open'));
$directoryStatus = trim((string)($data['status'] ?? 'active'));
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$services = $data['services'] ?? [];
$types = $data['types'] ?? [];

if (!is_array($types)) {
    $types = [];
}

if (!is_array($services)) {
    $services = [];
}

if (!is_array($hoursJson)) {
    $hoursJson = [];
}

if ($name === '' || $dzongkhag === '' || $address === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if ($hospital === '') {
    $hospital = $name;
}

if ($hours === '' && $hoursJson) {
    $hours = 'See structured schedule';
}

if ($hours === '') {
    $hours = 'Mon-Fri: 9:00 AM - 5:00 PM';
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (!in_array($availabilityStatus, ['open', 'limited'], true)) {
    $availabilityStatus = 'open';
}

if (!in_array($directoryStatus, ['active', 'inactive'], true)) {
    $directoryStatus = 'active';
}

$typesCsv = implode(', ', array_values(array_filter(array_map(static fn($v) => trim((string)$v), $types))));
if ($typesCsv === '') {
    $typesCsv = 'A+, B+, O+';
}

$servicesJsonEncoded = json_encode(array_values(array_filter(array_map(static fn($v) => trim((string)$v), $services))), JSON_UNESCAPED_UNICODE);
$hoursJsonEncoded = json_encode($hoursJson, JSON_UNESCAPED_UNICODE);

$latValue = is_numeric($latitude) ? (float)$latitude : null;
$lngValue = is_numeric($longitude) ? (float)$longitude : null;
$inventoryPayload = is_array($data['inventory'] ?? null) ? $data['inventory'] : [];

function table_exists_save(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $ignored) {
        return false;
    }
}

function column_exists_save(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column_name");
        $stmt->execute([':column_name' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

function normalize_inventory_payload(array $inventory): array {
    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $components = [
        'Wholeblood' => 'whole_units',
        'PRBC' => 'prbc_units',
        'Platelets' => 'platelets_units',
        'FFP' => 'ffp_units',
    ];

    $rows = [];
    foreach ($bloodTypes as $bloodType) {
        $rows[$bloodType] = [
            'whole_units' => 0,
            'prbc_units' => 0,
            'platelets_units' => 0,
            'ffp_units' => 0,
        ];

        foreach ($components as $componentKey => $columnKey) {
            $units = $inventory[$componentKey][$bloodType]['units'] ?? 0;
            $rows[$bloodType][$columnKey] = max(0, (int)$units);
        }
    }

    return $rows;
}

try {
    if (!table_exists_save($pdo, 'tblblood_banks')) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Blood bank database table not initialized. Please run database migration.']);
        exit;
    }

    $hasEmergencyPhone = column_exists_save($pdo, 'tblblood_banks', 'emergency_phone');
    $hasEmail = column_exists_save($pdo, 'tblblood_banks', 'email');
    $hasHours = column_exists_save($pdo, 'tblblood_banks', 'hours');
    $hasHoursJson = column_exists_save($pdo, 'tblblood_banks', 'hours_json');
    $hasEmergency = column_exists_save($pdo, 'tblblood_banks', 'emergency');
    $hasLatitude = column_exists_save($pdo, 'tblblood_banks', 'latitude');
    $hasLongitude = column_exists_save($pdo, 'tblblood_banks', 'longitude');
    $hasServicesJson = column_exists_save($pdo, 'tblblood_banks', 'services_json');
    $hasInventoryJson = column_exists_save($pdo, 'tblblood_banks', 'inventory_json');
    $hasStatus = column_exists_save($pdo, 'tblblood_banks', 'status');
    $hasDirectoryStatus = column_exists_save($pdo, 'tblblood_banks', 'directory_status');
    $hasIsActive = column_exists_save($pdo, 'tblblood_banks', 'is_active');
    $hasTypesCsv = column_exists_save($pdo, 'tblblood_banks', 'types_csv');
    $hasInventoryTable = table_exists_save($pdo, 'tblinventory');

    if ($id > 0) {
        $setClauses = [
            'name = :name',
            'hospital = :hospital',
            'dzongkhag = :dzongkhag',
            'address = :address',
            'phone = :phone',
        ];
        $params = [
            ':name' => $name,
            ':hospital' => $hospital,
            ':dzongkhag' => $dzongkhag,
            ':address' => $address,
            ':phone' => $phone,
            ':id' => $id,
        ];

        if ($hasEmergencyPhone) {
            $setClauses[] = 'emergency_phone = :emergency_phone';
            $params[':emergency_phone'] = ($emergencyPhone !== '' ? $emergencyPhone : $phone);
        }
        if ($hasEmail) {
            $setClauses[] = 'email = :email';
            $params[':email'] = ($email !== '' ? $email : null);
        }
        if ($hasHours) {
            $setClauses[] = 'hours = :hours';
            $params[':hours'] = $hours;
        }
        if ($hasHoursJson) {
            $setClauses[] = 'hours_json = :hours_json';
            $params[':hours_json'] = $hoursJsonEncoded;
        }
        if (!$hasInventoryJson) {
            // try to add inventory_json column (best-effort)
            try {
                $pdo->exec('ALTER TABLE tblblood_banks ADD COLUMN inventory_json TEXT NULL');
                $hasInventoryJson = true;
            } catch (Throwable $ignored) {
            }
        }
        if ($hasInventoryJson) {
            $setClauses[] = 'inventory_json = :inventory_json';
            $params[':inventory_json'] = json_encode($data['inventory'] ?? null, JSON_UNESCAPED_UNICODE);
        }
        if ($hasEmergency) {
            $setClauses[] = 'emergency = :emergency';
            $params[':emergency'] = $emergency;
        }
        if ($hasLatitude) {
            $setClauses[] = 'latitude = :latitude';
            $params[':latitude'] = $latValue;
        }
        if ($hasLongitude) {
            $setClauses[] = 'longitude = :longitude';
            $params[':longitude'] = $lngValue;
        }
        if ($hasServicesJson) {
            $setClauses[] = 'services_json = :services_json';
            $params[':services_json'] = $servicesJsonEncoded;
        }
        if ($hasStatus) {
            $setClauses[] = 'status = :availability_status';
            $params[':availability_status'] = $availabilityStatus;
        }
        if ($hasDirectoryStatus) {
            $setClauses[] = 'directory_status = :directory_status';
            $params[':directory_status'] = $directoryStatus;
        }
        if ($hasIsActive) {
            $setClauses[] = 'is_active = :is_active';
            $params[':is_active'] = ($directoryStatus === 'active' ? 1 : 0);
        }
        if ($hasTypesCsv) {
            $setClauses[] = 'types_csv = :types_csv';
            $params[':types_csv'] = $typesCsv;
        }

        $stmt = $pdo->prepare('UPDATE tblblood_banks SET ' . implode(', ', $setClauses) . ' WHERE id = :id');
        $stmt->execute($params);
    } else {
        $columns = ['name', 'hospital', 'dzongkhag', 'address', 'phone'];
        $placeholders = [':name', ':hospital', ':dzongkhag', ':address', ':phone'];
        $params = [
            ':name' => $name,
            ':hospital' => $hospital,
            ':dzongkhag' => $dzongkhag,
            ':address' => $address,
            ':phone' => $phone,
        ];

        if ($hasEmergencyPhone) {
            $columns[] = 'emergency_phone';
            $placeholders[] = ':emergency_phone';
            $params[':emergency_phone'] = ($emergencyPhone !== '' ? $emergencyPhone : $phone);
        }
        if ($hasEmail) {
            $columns[] = 'email';
            $placeholders[] = ':email';
            $params[':email'] = ($email !== '' ? $email : null);
        }
        if ($hasHours) {
            $columns[] = 'hours';
            $placeholders[] = ':hours';
            $params[':hours'] = $hours;
        }
        if ($hasHoursJson) {
            $columns[] = 'hours_json';
            $placeholders[] = ':hours_json';
            $params[':hours_json'] = $hoursJsonEncoded;
        }
        if ($hasEmergency) {
            $columns[] = 'emergency';
            $placeholders[] = ':emergency';
            $params[':emergency'] = $emergency;
        }
        if ($hasLatitude) {
            $columns[] = 'latitude';
            $placeholders[] = ':latitude';
            $params[':latitude'] = $latValue;
        }
        if ($hasLongitude) {
            $columns[] = 'longitude';
            $placeholders[] = ':longitude';
            $params[':longitude'] = $lngValue;
        }
        if ($hasServicesJson) {
            $columns[] = 'services_json';
            $placeholders[] = ':services_json';
            $params[':services_json'] = $servicesJsonEncoded;
        }
        if (!$hasInventoryJson) {
            try {
                $pdo->exec('ALTER TABLE tblblood_banks ADD COLUMN inventory_json TEXT NULL');
                $hasInventoryJson = true;
            } catch (Throwable $ignored) {
            }
        }
        if ($hasInventoryJson) {
            $columns[] = 'inventory_json';
            $placeholders[] = ':inventory_json';
            $params[':inventory_json'] = json_encode($data['inventory'] ?? null, JSON_UNESCAPED_UNICODE);
        }
        if ($hasStatus) {
            $columns[] = 'status';
            $placeholders[] = ':availability_status';
            $params[':availability_status'] = $availabilityStatus;
        }
        if ($hasDirectoryStatus) {
            $columns[] = 'directory_status';
            $placeholders[] = ':directory_status';
            $params[':directory_status'] = $directoryStatus;
        }
        if ($hasIsActive) {
            $columns[] = 'is_active';
            $placeholders[] = ':is_active';
            $params[':is_active'] = ($directoryStatus === 'active' ? 1 : 0);
        }
        if ($hasTypesCsv) {
            $columns[] = 'types_csv';
            $placeholders[] = ':types_csv';
            $params[':types_csv'] = $typesCsv;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tblblood_banks (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        $id = (int)$pdo->lastInsertId();
    }

    if ($hasInventoryTable) {
        $inventoryRows = normalize_inventory_payload($inventoryPayload);
        $inventoryStmt = $pdo->prepare(
            'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ffp_units)
             VALUES (:blood_bank_id, :blood_type, :whole_units, :prbc_units, :platelets_units, :ffp_units)
             ON DUPLICATE KEY UPDATE
               blood_bank_id = VALUES(blood_bank_id),
               whole_units = VALUES(whole_units),
               prbc_units = VALUES(prbc_units),
               platelets_units = VALUES(platelets_units),
               ffp_units = VALUES(ffp_units)'
        );

        foreach ($inventoryRows as $bloodType => $counts) {
            $inventoryStmt->execute([
                ':blood_bank_id' => $id,
                ':blood_type' => $bloodType,
                ':whole_units' => (int)$counts['whole_units'],
                ':prbc_units' => (int)$counts['prbc_units'],
                ':platelets_units' => (int)$counts['platelets_units'],
                ':ffp_units' => (int)$counts['ffp_units'],
            ]);
        }
    }

    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Blood bank saved successfully.']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
