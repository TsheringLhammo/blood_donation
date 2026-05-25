<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

try {
    $hasCampCode = (bool)$pdo->query("SHOW COLUMNS FROM tblblood_camps LIKE 'camp_code'")->fetchColumn();
    $hasPreDonors = (bool)$pdo->query("SHOW TABLES LIKE 'tblcamp_pre_donors'")->fetchColumn();

    $campCodeSelect = $hasCampCode ? 'camp_code' : 'NULL AS camp_code';
    $preDonorCountSelect = $hasPreDonors
        ? '(SELECT COUNT(*) FROM tblcamp_pre_donors p WHERE p.camp_id = c.id) AS pre_registered_count'
        : '0 AS pre_registered_count';

    $stmt = $pdo->query(
        "SELECT c.id, {$campCodeSelect}, c.organization_name, c.contact_person, c.phone_number, c.email,
            c.dzongkhag, c.camp_type, c.venue_address, c.preferred_date, c.alternate_date,
            c.expected_donors, c.facilities_available, c.additional_info, c.status, c.created_at,
            {$preDonorCountSelect}
         FROM tblblood_camps c
         WHERE c.status != 'deleted'
         ORDER BY c.preferred_date ASC, c.id DESC"
    );
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
