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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['staff', 'admin']);

$tableExists = static function (PDO $pdo, string $tableName): bool {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
};

$columnExists = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$maskCid = static function (string $value): string {
    $digits = preg_replace('/\D+/', '', $value) ?: '';
    if ($digits === '') {
        return '—';
    }

    if (strlen($digits) <= 4) {
        return $digits;
    }

    return substr($digits, 0, 4) . '****';
};

$normalizeDate = static function (?string $value): ?string {
    if (!$value) return null;
    $trimmed = trim($value);
    if ($trimmed === '') return null;
    $date = date_create($trimmed);
    return $date ? $date->format('Y-m-d H:i:s') : null;
};

try {
    if (!$tableExists($pdo, 'donation_history')) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'summary' => ['total_donations' => 0, 'this_month' => 0, 'total_units' => 0, 'active_blood_banks' => 0],
            'pagination' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 1],
        ]);
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $search = trim((string)($_GET['search'] ?? ''));
    $bloodGroup = trim((string)($_GET['blood_group'] ?? ''));
    $componentType = trim((string)($_GET['component_type'] ?? ''));
    $bloodBank = trim((string)($_GET['blood_bank'] ?? ''));
    $dateFrom = $normalizeDate($_GET['date_from'] ?? null);
    $dateTo = $normalizeDate($_GET['date_to'] ?? null);

    $hasDonors = $tableExists($pdo, 'tbldonors');
    $hasBanks = $tableExists($pdo, 'tblblood_banks');
    $hasUsers = $tableExists($pdo, 'tblusers');

    $donorCidColumn = $hasDonors && $columnExists($pdo, 'tbldonors', 'cid_number') ? 'cid_number' : ($hasDonors && $columnExists($pdo, 'tbldonors', 'cid') ? 'cid' : null);
    $donorNameColumn = $hasDonors && $columnExists($pdo, 'tbldonors', 'full_name') ? 'full_name' : ($hasDonors && $columnExists($pdo, 'tbldonors', 'name') ? 'name' : null);
    $bankNameColumn = $hasBanks && $columnExists($pdo, 'tblblood_banks', 'name') ? 'name' : null;
    $bankHospitalColumn = $hasBanks && $columnExists($pdo, 'tblblood_banks', 'hospital') ? 'hospital' : null;
    $staffNameColumn = $hasUsers && $columnExists($pdo, 'tblusers', 'name') ? 'name' : ($hasUsers && $columnExists($pdo, 'tblusers', 'full_name') ? 'full_name' : null);

    $bankNameParts = [];
    if ($hasBanks && $bankNameColumn) {
        $bankNameParts[] = 'CASE WHEN CHAR_LENGTH(TRIM(bb.' . $bankNameColumn . ')) = 0 THEN NULL ELSE TRIM(bb.' . $bankNameColumn . ') END';
    }
    if ($hasBanks && $bankHospitalColumn) {
        $bankNameParts[] = 'CASE WHEN CHAR_LENGTH(TRIM(bb.' . $bankHospitalColumn . ')) = 0 THEN NULL ELSE TRIM(bb.' . $bankHospitalColumn . ') END';
    }
    $bankNameParts[] = 'CONCAT("Blood Bank #", dh.blood_bank_id)';
    $bankNameSelect = $hasBanks
        ? 'COALESCE(' . implode(', ', $bankNameParts) . ') AS blood_bank_name'
        : 'CONCAT("Blood Bank #", dh.blood_bank_id) AS blood_bank_name';

    $donorNameParts = [];
    if ($hasDonors && $donorNameColumn) {
        $donorNameParts[] = 'CASE WHEN CHAR_LENGTH(TRIM(d.' . $donorNameColumn . ')) = 0 THEN NULL ELSE TRIM(d.' . $donorNameColumn . ') END';
    }
    $donorNameParts[] = 'CASE WHEN CHAR_LENGTH(TRIM(dh.donor_name)) = 0 THEN NULL ELSE TRIM(dh.donor_name) END';
    $donorNameParts[] = '"Unknown donor"';
    $donorNameSelect = 'COALESCE(' . implode(', ', $donorNameParts) . ') AS donor_name';

    $cidSelect = $hasDonors && $donorCidColumn
        ? 'CASE WHEN CHAR_LENGTH(TRIM(CAST(d.' . $donorCidColumn . ' AS CHAR))) = 0 THEN "" ELSE TRIM(CAST(d.' . $donorCidColumn . ' AS CHAR)) END AS cid_number'
        : '"" AS cid_number';

    $staffNameSelect = $hasUsers && $staffNameColumn
        ? 'COALESCE(CASE WHEN CHAR_LENGTH(TRIM(u.' . $staffNameColumn . ')) = 0 THEN NULL ELSE TRIM(u.' . $staffNameColumn . ') END, CASE WHEN CHAR_LENGTH(TRIM(u.email)) = 0 THEN NULL ELSE TRIM(u.email) END, "Staff") AS staff_name'
        : '"Staff" AS staff_name';

    $baseFrom = '
        FROM donation_history dh
        LEFT JOIN tbldonors d ON d.id = dh.donor_id
        ' . ($hasBanks ? 'LEFT JOIN tblblood_banks bb ON bb.id = dh.blood_bank_id' : '') . '
        ' . ($hasUsers ? 'LEFT JOIN tblusers u ON u.id = dh.completed_by_user_id' : '') . '
        WHERE LOWER(TRIM(COALESCE(dh.status, ""))) = "completed"
    ';

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(LOWER(COALESCE(dh.donor_name, "") COLLATE utf8mb4_general_ci) LIKE :search OR LOWER(COALESCE(d.' . ($donorNameColumn ?: 'full_name') . ', "") COLLATE utf8mb4_general_ci) LIKE :search OR REPLACE(COALESCE(dh.donor_name, "") COLLATE utf8mb4_general_ci, " ", "") LIKE :search_nospace OR REPLACE(COALESCE(CAST(d.' . ($donorCidColumn ?: 'id') . ' AS CHAR), "") COLLATE utf8mb4_general_ci, " ", "") LIKE :search_nospace OR CAST(dh.id AS CHAR) LIKE :search_id)';
        $params[':search'] = '%' . mb_strtolower($search) . '%';
        $params[':search_nospace'] = '%' . str_replace(' ', '', mb_strtolower($search)) . '%';
        $params[':search_id'] = '%' . preg_replace('/\s+/', '', $search) . '%';
    }

    if ($bloodGroup !== '') {
        $where[] = 'LOWER(COALESCE(d.blood_type, dh.blood_type, "") COLLATE utf8mb4_general_ci) = :blood_group';
        $params[':blood_group'] = mb_strtolower($bloodGroup);
    }

    if ($componentType !== '') {
        $where[] = 'LOWER(COALESCE(dh.component, "") COLLATE utf8mb4_general_ci) = :component_type';
        $params[':component_type'] = mb_strtolower($componentType);
    }

    if ($bloodBank !== '') {
        $where[] = 'LOWER(COALESCE(bb.name, bb.hospital, CONCAT("Blood Bank #", dh.blood_bank_id), "") COLLATE utf8mb4_general_ci) = :blood_bank';
        $params[':blood_bank'] = mb_strtolower($bloodBank);
    }

    if ($dateFrom) {
        $where[] = 'DATE(dh.donation_date) >= :date_from';
        $params[':date_from'] = substr($dateFrom, 0, 10);
    }

    if ($dateTo) {
        $where[] = 'DATE(dh.donation_date) <= :date_to';
        $params[':date_to'] = substr($dateTo, 0, 10);
    }

    $whereSql = $baseFrom . (count($where) > 0 ? ' AND ' . implode(' AND ', $where) : '');

    $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $sql = '
        SELECT
            dh.id,
            CASE WHEN CHAR_LENGTH(TRIM(CAST(dh.donation_id AS CHAR))) = 0 THEN CONCAT("DH-", dh.id) ELSE TRIM(CAST(dh.donation_id AS CHAR)) END AS donation_id,
            dh.donor_id,
            ' . $donorNameSelect . ',
            ' . $cidSelect . ',
            CASE WHEN CHAR_LENGTH(TRIM(COALESCE(dh.blood_type, d.blood_type, ""))) = 0 THEN "" ELSE TRIM(COALESCE(dh.blood_type, d.blood_type, "")) END AS blood_group,
            ' . $bankNameSelect . ',
            CASE
                WHEN LOWER(TRIM(COALESCE(dh.component, "") COLLATE utf8mb4_general_ci)) = "packed red cells" THEN "PRBC"
                WHEN LOWER(TRIM(COALESCE(dh.component, "") COLLATE utf8mb4_general_ci)) = "prbc" THEN "PRBC"
                ELSE CASE WHEN CHAR_LENGTH(TRIM(dh.component)) = 0 THEN "Whole Blood" ELSE TRIM(dh.component) END
            END AS component_type,
            COALESCE(dh.units_collected, 1) AS units,
            DATE_FORMAT(dh.donation_date, "%Y-%m-%d %H:%i:%s") AS donation_date_time,
            ' . $staffNameSelect . ',
            dh.status
        ' . $whereSql . '
        ORDER BY dh.donation_date DESC, dh.id DESC
        LIMIT :limit OFFSET :offset
    ';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summaryTotalStmt = $pdo->query('SELECT COUNT(*) FROM donation_history WHERE LOWER(TRIM(COALESCE(status, "") COLLATE utf8mb4_general_ci)) = "completed"');
    $summaryTotal = (int)($summaryTotalStmt ? $summaryTotalStmt->fetchColumn() : 0);

    $summaryMonthStmt = $pdo->prepare('SELECT COUNT(*) FROM donation_history WHERE LOWER(TRIM(COALESCE(status, "") COLLATE utf8mb4_general_ci)) = "completed" AND DATE_FORMAT(donation_date, "%Y-%m") = :month_key');
    $summaryMonthStmt->execute([':month_key' => date('Y-m')]);
    $summaryMonth = (int)$summaryMonthStmt->fetchColumn();

    $summaryUnitsStmt = $pdo->query('SELECT COALESCE(SUM(units_collected), 0) FROM donation_history WHERE LOWER(TRIM(COALESCE(status, "") COLLATE utf8mb4_general_ci)) = "completed"');
    $summaryUnits = (int)($summaryUnitsStmt ? $summaryUnitsStmt->fetchColumn() : 0);

    $activeBloodBanks = 0;
    if ($hasBanks) {
        if ($columnExists($pdo, 'tblblood_banks', 'is_active')) {
            $activeBloodBanks = (int)$pdo->query('SELECT COUNT(*) FROM tblblood_banks WHERE COALESCE(is_active, 0) = 1')->fetchColumn();
        } elseif ($columnExists($pdo, 'tblblood_banks', 'status')) {
            $activeBloodBanks = (int)$pdo->query('SELECT COUNT(*) FROM tblblood_banks WHERE LOWER(TRIM(COALESCE(status, "") COLLATE utf8mb4_general_ci)) = "active"')->fetchColumn();
        } else {
            $activeBloodBanks = (int)$pdo->query('SELECT COUNT(DISTINCT blood_bank_id) FROM donation_history WHERE LOWER(TRIM(COALESCE(status, "") COLLATE utf8mb4_general_ci)) = "completed"')->fetchColumn();
        }
    }

    $bloodGroups = [];
    if ($hasDonors && $donorCidColumn) {
        $bloodGroupsStmt = $pdo->query('SELECT DISTINCT CASE WHEN CHAR_LENGTH(TRIM(blood_type)) = 0 THEN "" ELSE TRIM(blood_type) END AS blood_group FROM tbldonors WHERE CHAR_LENGTH(TRIM(blood_type)) > 0 ORDER BY blood_group ASC');
        $bloodGroups = $bloodGroupsStmt ? array_values(array_filter(array_map(static fn ($row) => (string)$row['blood_group'], $bloodGroupsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []))) : [];
    }

    $componentTypes = [];
    $componentTypesStmt = $pdo->query('SELECT DISTINCT CASE WHEN LOWER(TRIM(COALESCE(component, "") COLLATE utf8mb4_general_ci)) = "packed red cells" THEN "PRBC" WHEN LOWER(TRIM(COALESCE(component, "") COLLATE utf8mb4_general_ci)) = "prbc" THEN "PRBC" ELSE CASE WHEN CHAR_LENGTH(TRIM(component)) = 0 THEN "Whole Blood" ELSE TRIM(component) END END AS component_type FROM donation_history WHERE LOWER(TRIM(COALESCE(status, "") COLLATE utf8mb4_general_ci)) = "completed" ORDER BY component_type ASC');
    if ($componentTypesStmt) {
        $componentTypes = array_values(array_filter(array_map(static fn ($row) => (string)$row['component_type'], $componentTypesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [])));
    }

    $bloodBanks = [];
    if ($hasBanks) {
        $bloodBanksStmt = $pdo->query('SELECT DISTINCT COALESCE(CASE WHEN CHAR_LENGTH(TRIM(name)) = 0 THEN NULL ELSE TRIM(name) END, CASE WHEN CHAR_LENGTH(TRIM(hospital)) = 0 THEN NULL ELSE TRIM(hospital) END) AS blood_bank_name FROM tblblood_banks WHERE COALESCE(CASE WHEN CHAR_LENGTH(TRIM(name)) = 0 THEN NULL ELSE TRIM(name) END, CASE WHEN CHAR_LENGTH(TRIM(hospital)) = 0 THEN NULL ELSE TRIM(hospital) END) IS NOT NULL ORDER BY blood_bank_name ASC');
        if ($bloodBanksStmt) {
            $bloodBanks = array_values(array_filter(array_map(static fn ($row) => (string)$row['blood_bank_name'], $bloodBanksStmt->fetchAll(PDO::FETCH_ASSOC) ?: [])));
        }
    }

    echo json_encode([
        'success' => true,
        'data' => array_map(static function (array $row) use ($maskCid): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'donation_id' => (string)($row['donation_id'] ?? ''),
                'donor_id' => (int)($row['donor_id'] ?? 0),
                'donor_name' => (string)($row['donor_name'] ?? 'Unknown donor'),
                'cid_number' => (string)($row['cid_number'] ?? ''),
                'cid_masked' => $maskCid((string)($row['cid_number'] ?? '')),
                'blood_group' => (string)($row['blood_group'] ?? ''),
                'blood_bank' => (string)($row['blood_bank_name'] ?? ''),
                'component_type' => (string)($row['component_type'] ?? 'Whole Blood'),
                'units' => (int)($row['units'] ?? 1),
                'donation_date_time' => (string)($row['donation_date_time'] ?? ''),
                'staff_name' => (string)($row['staff_name'] ?? 'Staff'),
                'status' => 'Completed',
            ];
        }, $rows),
        'summary' => [
            'total_donations' => $summaryTotal,
            'this_month' => $summaryMonth,
            'total_units' => $summaryUnits,
            'active_blood_banks' => $activeBloodBanks,
        ],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ],
        'options' => [
            'blood_groups' => $bloodGroups,
            'component_types' => $componentTypes,
            'blood_banks' => $bloodBanks,
        ],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}