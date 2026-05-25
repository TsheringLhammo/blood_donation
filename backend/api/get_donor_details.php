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

bts_require_auth(['admin']);

$donorId = (int)($_GET['id'] ?? 0);
if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid donor id is required.']);
    exit;
}

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$toHealthSummary = static function (array $row): string {
    $parts = [];

    $raw = $row['health_declaration'] ?? null;
    if (is_string($raw) && trim($raw) !== '') {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            foreach ($parsed as $key => $value) {
                $label = ucwords(str_replace('_', ' ', (string)$key));
                $parts[] = $label . ': ' . (((bool)$value) ? 'Yes' : 'No');
            }
        } else {
            $parts[] = trim($raw);
        }
    }

    $flagMap = [
        'health_tattoo' => 'Recent tattoo or piercing',
        'health_antibiotics' => 'On antibiotics',
        'health_surgery' => 'Recent surgery',
        'health_no_cold_flu' => 'No current cold/flu',
    ];

    foreach ($flagMap as $column => $label) {
        if (array_key_exists($column, $row) && $row[$column] !== null && $row[$column] !== '') {
            $parts[] = $label . ': ' . ((int)$row[$column] === 1 ? 'Yes' : 'No');
        }
    }

    if (array_key_exists('consent_medical', $row) && $row['consent_medical'] !== null && $row['consent_medical'] !== '') {
        $parts[] = 'Medical consent: ' . ((int)$row['consent_medical'] === 1 ? 'Yes' : 'No');
    } elseif (array_key_exists('consent', $row) && $row['consent'] !== null && $row['consent'] !== '') {
        $parts[] = 'Medical consent: ' . ((int)$row['consent'] === 1 ? 'Yes' : 'No');
    }

    if (empty($parts)) {
        return '-';
    }

    return implode(' | ', $parts);
};

try {
    $genderSelect = $columnExists($pdo, 'tbldonors', 'gender') ? 'gender' : 'NULL AS gender';
    $weightSelect = $columnExists($pdo, 'tbldonors', 'weight') ? 'weight' : 'NULL AS weight';
    $citySelect = $columnExists($pdo, 'tbldonors', 'city') ? 'city' : 'NULL AS city';
    $dzongkhagSelect = $columnExists($pdo, 'tbldonors', 'dzongkhag') ? 'dzongkhag' : 'NULL AS dzongkhag';
    $statusSelect = $columnExists($pdo, 'tbldonors', 'status') ? 'status' : 'NULL AS status';
    $lastDonationSelect = $columnExists($pdo, 'tbldonors', 'last_donation_date') ? 'last_donation_date' : 'NULL AS last_donation_date';
    $deferredUntilSelect = $columnExists($pdo, 'tbldonors', 'deferred_until') ? 'deferred_until' : 'NULL AS deferred_until';
    $deferralReasonSelect = $columnExists($pdo, 'tbldonors', 'deferral_reason') ? 'deferral_reason' : 'NULL AS deferral_reason';

    $healthDeclarationSelect = $columnExists($pdo, 'tbldonors', 'health_declaration') ? 'health_declaration' : 'NULL AS health_declaration';
    $consentSelect = $columnExists($pdo, 'tbldonors', 'consent') ? 'consent' : 'NULL AS consent';
    $consentMedicalSelect = $columnExists($pdo, 'tbldonors', 'consent_medical') ? 'consent_medical' : 'NULL AS consent_medical';
    $tattooSelect = $columnExists($pdo, 'tbldonors', 'health_tattoo') ? 'health_tattoo' : 'NULL AS health_tattoo';
    $antibioticsSelect = $columnExists($pdo, 'tbldonors', 'health_antibiotics') ? 'health_antibiotics' : 'NULL AS health_antibiotics';
    $surgerySelect = $columnExists($pdo, 'tbldonors', 'health_surgery') ? 'health_surgery' : 'NULL AS health_surgery';
    $coldFluSelect = $columnExists($pdo, 'tbldonors', 'health_no_cold_flu') ? 'health_no_cold_flu' : 'NULL AS health_no_cold_flu';
    $cidSelect = $columnExists($pdo, 'tbldonors', 'cid_number') ? 'cid_number' : ($columnExists($pdo, 'tbldonors', 'cid') ? 'cid AS cid_number' : 'NULL AS cid_number');

    $sql = "SELECT
                id,
                full_name,
                email,
                phone,
                {$cidSelect},
                date_of_birth,
                blood_type,
                {$genderSelect},
                {$weightSelect},
                {$citySelect},
                {$dzongkhagSelect},
                {$statusSelect},
                {$lastDonationSelect},
                {$deferredUntilSelect},
                {$deferralReasonSelect},
                {$healthDeclarationSelect},
                {$consentSelect},
                {$consentMedicalSelect},
                {$tattooSelect},
                {$antibioticsSelect},
                {$surgerySelect},
                {$coldFluSelect}
            FROM tbldonors
            WHERE id = ?
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$donorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    $totalDonations = 0;
    if ((bool)$pdo->query("SHOW TABLES LIKE 'donation_history'")->fetchColumn()) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM donation_history WHERE donor_id = ? AND LOWER(TRIM(COALESCE(status, ""))) = "completed"');
        $countStmt->execute([$donorId]);
        $totalDonations = (int)$countStmt->fetchColumn();
    }

    $cidValue = trim((string)($row['cid_number'] ?? ''));

    $payload = [
        'id' => (int)$row['id'],
        'full_name' => (string)($row['full_name'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'phone' => (string)($row['phone'] ?? ''),
        'cid' => $cidValue,
        'cid_number' => $cidValue,
        'date_of_birth' => $row['date_of_birth'] ?? null,
        'gender' => $row['gender'] ?? null,
        'blood_type' => (string)($row['blood_type'] ?? ''),
        'city' => $row['city'] ?? null,
        'dzongkhag' => $row['dzongkhag'] ?? null,
        'weight' => $row['weight'] !== null ? (float)$row['weight'] : null,
        'status' => strtolower(trim((string)($row['status'] ?? 'pending'))),
        'last_donation_date' => $row['last_donation_date'] ?? null,
        'deferred_until' => $row['deferred_until'] ?? null,
        'deferral_reason' => $row['deferral_reason'] ?? null,
        'health_declaration_summary' => $toHealthSummary($row),
        'total_donations' => $totalDonations,
    ];

    echo json_encode(['success' => true, 'data' => $payload]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
