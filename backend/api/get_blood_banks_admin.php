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

bts_require_auth(['admin']);

function table_exists_admin(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $ignored) {
        return false;
    }
}

function column_exists_admin(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column_name");
        $stmt->execute([':column_name' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

function fallback_banks_admin(): array {
    return [
        [
            'id' => 1,
            'name' => 'National Blood Bank',
            'hospital' => 'Jigme Dorji Wangchuck National Referral Hospital',
            'dzongkhag' => 'Thimphu',
            'address' => 'Gongphel Lam, Thimphu',
            'phone' => '02-322496',
            'emergency_phone' => '02-322496',
            'email' => 'info@nationalbloodbank.bt',
            'hours' => 'Mon-Sat: 9:00 AM - 5:00 PM',
            'hours_json' => json_encode([
                'mon' => ['start' => '09:00', 'end' => '17:00'],
                'tue' => ['start' => '09:00', 'end' => '17:00'],
                'wed' => ['start' => '09:00', 'end' => '17:00'],
                'thu' => ['start' => '09:00', 'end' => '17:00'],
                'fri' => ['start' => '09:00', 'end' => '17:00'],
                'sat' => ['start' => '09:00', 'end' => '17:00'],
                'sun' => ['open' => false],
            ]),
            'emergency' => '02-322496',
            'latitude' => 27.4727924,
            'longitude' => 89.6392862,
            'services_json' => json_encode(['Blood Donation', 'Screening', 'Cross-match']),
            'status' => 'open',
            'directory_status' => 'active',
            'types_csv' => 'A+, B+, O+, AB+, O-, A-, B-, AB-',
            'is_active' => 1,
        ],
        [
            'id' => 2,
            'name' => 'Phuentsholing Blood Bank',
            'hospital' => 'Phuentsholing General Hospital',
            'dzongkhag' => 'Chukha',
            'address' => 'Hospital Road, Phuentsholing',
            'phone' => '05-252431',
            'emergency_phone' => '05-252431',
            'email' => 'info@phuentsholing-bb.bt',
            'hours' => 'Mon-Fri: 9:00 AM - 4:00 PM',
            'hours_json' => json_encode([
                'mon' => ['start' => '09:00', 'end' => '16:00'],
                'tue' => ['start' => '09:00', 'end' => '16:00'],
                'wed' => ['start' => '09:00', 'end' => '16:00'],
                'thu' => ['start' => '09:00', 'end' => '16:00'],
                'fri' => ['start' => '09:00', 'end' => '16:00'],
                'sat' => ['open' => false],
                'sun' => ['open' => false],
            ]),
            'emergency' => '05-252431',
            'latitude' => 26.8588058,
            'longitude' => 89.3886278,
            'services_json' => json_encode(['Blood Donation', 'Emergency Issue']),
            'status' => 'open',
            'directory_status' => 'active',
            'types_csv' => 'A+, B+, O+',
            'is_active' => 1,
        ],
        [
            'id' => 3,
            'name' => 'Mongar Blood Bank',
            'hospital' => 'Mongar Regional Referral Hospital',
            'dzongkhag' => 'Mongar',
            'address' => 'Mongar Town',
            'phone' => '04-641114',
            'emergency_phone' => '04-641114',
            'email' => 'info@mongar-bb.bt',
            'hours' => 'Mon-Fri: 9:00 AM - 4:00 PM',
            'hours_json' => json_encode([
                'mon' => ['start' => '09:00', 'end' => '16:00'],
                'tue' => ['start' => '09:00', 'end' => '16:00'],
                'wed' => ['start' => '09:00', 'end' => '16:00'],
                'thu' => ['start' => '09:00', 'end' => '16:00'],
                'fri' => ['start' => '09:00', 'end' => '16:00'],
                'sat' => ['open' => false],
                'sun' => ['open' => false],
            ]),
            'emergency' => '04-641114',
            'latitude' => null,
            'longitude' => null,
            'services_json' => json_encode(['Blood Donation', 'Testing']),
            'status' => 'open',
            'directory_status' => 'active',
            'types_csv' => 'A+, B+, O+',
            'is_active' => 1,
        ],
    ];
}

try {
    $includeInactive = in_array(strtolower((string)($_GET['include_inactive'] ?? '0')), ['1', 'true', 'yes'], true);
    $search = trim((string)($_GET['search'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 10);
    if ($perPage < 1) {
        $perPage = 10;
    }
    if ($perPage > 100) {
        $perPage = 100;
    }

    $mapRow = static function (array $row): array {
        $types = array_values(array_filter(array_map('trim', explode(',', (string)($row['types_csv'] ?? '')))));
        $services = json_decode((string)($row['services_json'] ?? ''), true);
        if (!is_array($services)) {
            $services = [];
        }
        $hoursJson = json_decode((string)($row['hours_json'] ?? ''), true);
        if (!is_array($hoursJson)) {
            $hoursJson = [];
        }

        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'hospital' => (string)$row['hospital'],
            'dzongkhag' => (string)$row['dzongkhag'],
            'address' => (string)$row['address'],
            'phone' => (string)$row['phone'],
            'emergencyPhone' => (string)($row['emergency_phone'] ?: $row['phone']),
            'email' => $row['email'],
            'hours' => (string)$row['hours'],
            'hoursJson' => $hoursJson,
            'emergency' => (string)$row['emergency'],
            'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
            'services' => $services,
            'availabilityStatus' => (string)($row['status'] ?: 'open'),
            'status' => (string)($row['directory_status'] ?: (($row['is_active'] ?? 1) ? 'active' : 'inactive')),
            'types' => $types,
            'isActive' => (int)($row['is_active'] ?? 1) === 1,
        ];
    };

    if (!table_exists_admin($pdo, 'tblblood_banks')) {
        $fallback = fallback_banks_admin();

        $filtered = array_values(array_filter($fallback, static function (array $row) use ($includeInactive, $search): bool {
            $isActive = (int)($row['is_active'] ?? 1) === 1;
            if (!$includeInactive && !$isActive) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $needle = mb_strtolower($search);
            $name = mb_strtolower((string)($row['name'] ?? ''));
            $dzongkhag = mb_strtolower((string)($row['dzongkhag'] ?? ''));

            return str_contains($name, $needle) || str_contains($dzongkhag, $needle);
        }));

        usort($filtered, static function (array $a, array $b): int {
            $dz = strcmp((string)($a['dzongkhag'] ?? ''), (string)($b['dzongkhag'] ?? ''));
            if ($dz !== 0) {
                return $dz;
            }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $total = count($filtered);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($filtered, $offset, $perPage);
        $data = array_map($mapRow, $slice);

        echo json_encode([
            'success' => true,
            'data' => $data,
            'source' => 'api-fallback',
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ],
        ]);
        exit;
    }

    $hasIsActive = column_exists_admin($pdo, 'tblblood_banks', 'is_active');
    $hasEmergencyPhone = column_exists_admin($pdo, 'tblblood_banks', 'emergency_phone');
    $hasEmail = column_exists_admin($pdo, 'tblblood_banks', 'email');
    $hasHours = column_exists_admin($pdo, 'tblblood_banks', 'hours');
    $hasHoursJson = column_exists_admin($pdo, 'tblblood_banks', 'hours_json');
    $hasEmergency = column_exists_admin($pdo, 'tblblood_banks', 'emergency');
    $hasLatitude = column_exists_admin($pdo, 'tblblood_banks', 'latitude');
    $hasLongitude = column_exists_admin($pdo, 'tblblood_banks', 'longitude');
    $hasServicesJson = column_exists_admin($pdo, 'tblblood_banks', 'services_json');
    $hasStatus = column_exists_admin($pdo, 'tblblood_banks', 'status');
    $hasDirectoryStatus = column_exists_admin($pdo, 'tblblood_banks', 'directory_status');
    $hasTypesCsv = column_exists_admin($pdo, 'tblblood_banks', 'types_csv');

    $whereClauses = [];
    $params = [];

    if (!$includeInactive && $hasIsActive) {
        $whereClauses[] = 'is_active = 1';
    }

    if ($search !== '') {
        $whereClauses[] = '(name LIKE :search OR dzongkhag LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = '';
    if ($whereClauses) {
        $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
    }

    $countSql = 'SELECT COUNT(*) FROM tblblood_banks' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $selectColumns = [
        'id',
        'name',
        'hospital',
        'dzongkhag',
        'address',
        'phone',
        $hasEmergencyPhone ? 'emergency_phone' : 'phone AS emergency_phone',
        $hasEmail ? 'email' : 'NULL AS email',
        $hasHours ? 'hours' : "'Mon-Fri: 9:00 AM - 5:00 PM' AS hours",
        $hasHoursJson ? 'hours_json' : "'[]' AS hours_json",
        $hasEmergency ? 'emergency' : "'' AS emergency",
        $hasLatitude ? 'latitude' : 'NULL AS latitude',
        $hasLongitude ? 'longitude' : 'NULL AS longitude',
        $hasServicesJson ? 'services_json' : "'[]' AS services_json",
        $hasStatus ? 'status' : "'open' AS status",
        $hasDirectoryStatus
            ? 'directory_status'
            : ($hasIsActive
                ? "CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END AS directory_status"
                : "'active' AS directory_status"),
        $hasTypesCsv ? 'types_csv' : "'' AS types_csv",
        $hasIsActive ? 'is_active' : '1 AS is_active',
    ];

    $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM tblblood_banks'
        . $whereSql
        . ' ORDER BY dzongkhag ASC, name ASC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map($mapRow, $rows);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
