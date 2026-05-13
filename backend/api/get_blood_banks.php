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

function normalize_day_key(string $day): string
{
    $map = [
        'mon' => 'mon', 'monday' => 'mon',
        'tue' => 'tue', 'tues' => 'tue', 'tuesday' => 'tue',
        'wed' => 'wed', 'wednesday' => 'wed',
        'thu' => 'thu', 'thur' => 'thu', 'thurs' => 'thu', 'thursday' => 'thu',
        'fri' => 'fri', 'friday' => 'fri',
        'sat' => 'sat', 'saturday' => 'sat',
        'sun' => 'sun', 'sunday' => 'sun',
    ];
    $key = strtolower(trim($day));
    return $map[$key] ?? $key;
}

function parse_time_to_minutes(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('g:i A', strtoupper($value));
    if (!$dt) {
        $dt = DateTime::createFromFormat('g A', strtoupper($value));
    }
    if (!$dt) {
        $dt = DateTime::createFromFormat('H:i', $value);
    }
    if (!$dt) {
        return null;
    }

    return ((int)$dt->format('H')) * 60 + (int)$dt->format('i');
}

function parse_legacy_hours_to_json(string $hours): array
{
    $hours = trim($hours);
    if ($hours === '') {
        return [];
    }

    $parts = explode(':', $hours, 2);
    if (count($parts) !== 2) {
        return [];
    }

    $daysText = trim($parts[0]);
    $timesText = trim($parts[1]);

    if (!preg_match('/(.+?)\s*-\s*(.+)/', $timesText, $timeMatches)) {
        return [];
    }

    $open = trim($timeMatches[1]);
    $close = trim($timeMatches[2]);

    $days = [];
    if (preg_match('/^([A-Za-z]{3,9})\s*-\s*([A-Za-z]{3,9})$/', $daysText, $dayMatches)) {
        $ordered = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $start = normalize_day_key($dayMatches[1]);
        $end = normalize_day_key($dayMatches[2]);
        $startIdx = array_search($start, $ordered, true);
        $endIdx = array_search($end, $ordered, true);
        if ($startIdx !== false && $endIdx !== false && $startIdx <= $endIdx) {
            for ($i = $startIdx; $i <= $endIdx; $i++) {
                $days[] = $ordered[$i];
            }
        }
    } else {
        $chunks = preg_split('/[,\s]+/', $daysText) ?: [];
        foreach ($chunks as $chunk) {
            $k = normalize_day_key($chunk);
            if (in_array($k, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
                $days[] = $k;
            }
        }
    }

    $result = [];
    foreach (array_unique($days) as $day) {
        $result[$day] = [
            'open' => true,
            'start' => $open,
            'end' => $close,
        ];
    }

    return $result;
}

function compute_open_now(array $hoursJson): bool
{
    if (!$hoursJson) {
        return false;
    }

    $today = strtolower(date('D'));
    $key = normalize_day_key($today);
    $todayHours = $hoursJson[$key] ?? null;
    if (!is_array($todayHours)) {
        return false;
    }

    if (array_key_exists('open', $todayHours) && !$todayHours['open']) {
        return false;
    }

    $start = parse_time_to_minutes((string)($todayHours['start'] ?? ''));
    $end = parse_time_to_minutes((string)($todayHours['end'] ?? ''));
    if ($start === null || $end === null) {
        return false;
    }

    $now = ((int)date('H')) * 60 + (int)date('i');
    return $now >= $start && $now <= $end;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute([':column' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $ignored) {
        return false;
    }
}

function fallback_banks_data(): array
{
    return [
        [
            'id' => 1,
            'name' => 'National Blood Bank',
            'hospital' => 'Jigme Dorji Wangchuck National Referral Hospital',
            'dzongkhag' => 'Thimphu',
            'address' => 'Gongphel Lam, Thimphu',
            'phone' => '02-322496',
            'email' => null,
            'hours' => 'Mon-Sat: 9:00 AM - 5:00 PM',
            'hours_json' => [],
            'emergency' => '24/7 Emergency',
            'emergency_phone' => '02-322496',
            'latitude' => 27.4727924,
            'longitude' => 89.6392862,
            'services' => ['Blood Donation', 'Screening'],
            'status' => 'active',
            'availability_status' => 'open',
            'types' => ['A+', 'B+', 'O+', 'AB+', 'O-'],
            'inventory' => ['A+' => 8, 'B+' => 6, 'O+' => 12, 'AB+' => 4, 'O-' => 2],
            'total_available_units' => 32,
            'is_open_now' => true,
        ],
        [
            'id' => 2,
            'name' => 'Phuentsholing Blood Bank',
            'hospital' => 'Phuentsholing General Hospital',
            'dzongkhag' => 'Chukha',
            'address' => 'Hospital Road, Phuentsholing',
            'phone' => '05-252431',
            'email' => null,
            'hours' => 'Mon-Fri: 9:00 AM - 4:00 PM',
            'hours_json' => [],
            'emergency' => 'Emergency on call',
            'emergency_phone' => '05-252431',
            'latitude' => 26.8588058,
            'longitude' => 89.3886278,
            'services' => ['Blood Donation', 'Emergency Issue'],
            'status' => 'active',
            'availability_status' => 'open',
            'types' => ['A+', 'B+', 'O+'],
            'inventory' => ['A+' => 4, 'B+' => 3, 'O+' => 5],
            'total_available_units' => 12,
            'is_open_now' => true,
        ],
    ];
}

function apply_fallback_filters(array $banks, string $q, string $dzongkhag, string $bloodType, bool $openNowOnly): array
{
    $needle = strtolower(trim($q));

    return array_values(array_filter($banks, static function (array $bank) use ($needle, $dzongkhag, $bloodType, $openNowOnly): bool {
        if ($needle !== '') {
            $matchesSearch =
                str_contains(strtolower((string)$bank['name']), $needle) ||
                str_contains(strtolower((string)$bank['address']), $needle) ||
                str_contains(strtolower((string)$bank['dzongkhag']), $needle);
            if (!$matchesSearch) {
                return false;
            }
        }

        if ($dzongkhag !== '' && (string)$bank['dzongkhag'] !== $dzongkhag) {
            return false;
        }

        if ($bloodType !== '' && ((int)(($bank['inventory'][$bloodType] ?? 0)) <= 0)) {
            return false;
        }

        if ($openNowOnly && empty($bank['is_open_now'])) {
            return false;
        }

        return true;
    }));
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $dzongkhag = trim((string)($_GET['dzongkhag'] ?? ''));
    $bloodType = strtoupper(trim((string)($_GET['blood_type'] ?? '')));
    $openNowOnly = in_array(strtolower((string)($_GET['open_now'] ?? '0')), ['1', 'true', 'yes'], true);

    if (!table_exists($pdo, 'tblblood_banks')) {
        $fallback = apply_fallback_filters(fallback_banks_data(), $q, $dzongkhag, $bloodType, $openNowOnly);
        echo json_encode(['success' => true, 'data' => $fallback, 'source' => 'api-fallback']);
        exit;
    }

    $hasEmergencyPhone = column_exists($pdo, 'tblblood_banks', 'emergency_phone');
    $hasLatitude = column_exists($pdo, 'tblblood_banks', 'latitude');
    $hasLongitude = column_exists($pdo, 'tblblood_banks', 'longitude');
    $hasServicesJson = column_exists($pdo, 'tblblood_banks', 'services_json');
    $hasHoursJson = column_exists($pdo, 'tblblood_banks', 'hours_json');
    $hasDirectoryStatus = column_exists($pdo, 'tblblood_banks', 'directory_status');

    $selectCols = [
        'id', 'name', 'hospital', 'dzongkhag', 'address', 'phone', 'email', 'hours', 'emergency', 'status', 'types_csv',
    ];
    if ($hasEmergencyPhone) {
        $selectCols[] = 'emergency_phone';
    }
    if ($hasLatitude) {
        $selectCols[] = 'latitude';
    }
    if ($hasLongitude) {
        $selectCols[] = 'longitude';
    }
    if ($hasServicesJson) {
        $selectCols[] = 'services_json';
    }
    if ($hasHoursJson) {
        $selectCols[] = 'hours_json';
    }
    if ($hasDirectoryStatus) {
        $selectCols[] = 'directory_status';
    }

    $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM tblblood_banks WHERE is_active = 1';
    if ($hasDirectoryStatus) {
        $sql .= ' AND directory_status = "active"';
    }
    $params = [];

    if ($q !== '') {
        $sql .= ' AND (name LIKE :q OR address LIKE :q OR dzongkhag LIKE :q OR hospital LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    if ($dzongkhag !== '') {
        $sql .= ' AND dzongkhag = :dzongkhag';
        $params[':dzongkhag'] = $dzongkhag;
    }

    $sql .= ' ORDER BY dzongkhag ASC, name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasUnitsTable = false;
    try {
        $tableStmt = $pdo->query("SHOW TABLES LIKE 'tblblood_units'");
        $hasUnitsTable = (bool)$tableStmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $ignored) {
        $hasUnitsTable = false;
    }

    $inventoryByBank = [];
    if ($hasUnitsTable) {
        $inventoryStmt = $pdo->query(
            "SELECT blood_bank_id, blood_type, COUNT(*) AS unit_count
             FROM tblblood_units
             WHERE status = 'Available' AND expiry_date >= CURDATE()
             GROUP BY blood_bank_id, blood_type"
        );
        foreach ($inventoryStmt->fetchAll(PDO::FETCH_ASSOC) as $invRow) {
            $bankId = (int)$invRow['blood_bank_id'];
            $bt = strtoupper((string)$invRow['blood_type']);
            $count = (int)$invRow['unit_count'];
            if (!isset($inventoryByBank[$bankId])) {
                $inventoryByBank[$bankId] = [];
            }
            $inventoryByBank[$bankId][$bt] = $count;
        }
    } else {
        $inventoryStmt = $pdo->query(
            'SELECT blood_bank_id, blood_type,
                    (COALESCE(whole_units,0) + COALESCE(prbc_units,0) + COALESCE(platelets_units,0) + COALESCE(ffp_units,0)) AS unit_count
             FROM tblinventory'
        );
        foreach ($inventoryStmt->fetchAll(PDO::FETCH_ASSOC) as $invRow) {
            $bankId = (int)$invRow['blood_bank_id'];
            $bt = strtoupper((string)$invRow['blood_type']);
            $count = (int)$invRow['unit_count'];
            if (!isset($inventoryByBank[$bankId])) {
                $inventoryByBank[$bankId] = [];
            }
            $inventoryByBank[$bankId][$bt] = $count;
        }
    }

    $data = [];
    foreach ($rows as $row) {
        $types = array_values(array_filter(array_map('trim', explode(',', (string)$row['types_csv']))));
        $bankId = (int)$row['id'];
        $inventory = $inventoryByBank[$bankId] ?? [];

        $servicesJson = json_decode((string)($row['services_json'] ?? ''), true);
        if (!is_array($servicesJson)) {
            $servicesJson = [];
        }

        $hoursJson = json_decode((string)($row['hours_json'] ?? ''), true);
        if (!is_array($hoursJson) || !$hoursJson) {
            $hoursJson = parse_legacy_hours_to_json((string)($row['hours'] ?? ''));
        }

        $isOpenNow = compute_open_now($hoursJson);

        if ($bloodType !== '' && (($inventory[$bloodType] ?? 0) <= 0)) {
            continue;
        }

        if ($openNowOnly && !$isOpenNow) {
            continue;
        }

        $data[] = [
            'id' => $bankId,
            'name' => $row['name'],
            'hospital' => $row['hospital'],
            'dzongkhag' => $row['dzongkhag'],
            'address' => $row['address'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'hours' => $row['hours'],
            'hours_json' => $hoursJson,
            'emergency' => $row['emergency'],
            'emergency_phone' => ($row['emergency_phone'] ?? '') ?: $row['phone'],
            'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
            'services' => $servicesJson,
            'status' => ($row['directory_status'] ?? '') ?: 'active',
            'availability_status' => $row['status'],
            'types' => $types,
            'inventory' => $inventory,
            'total_available_units' => array_sum($inventory),
            'is_open_now' => $isOpenNow,
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
