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
    $stmt = $pdo->query(
        "SELECT id, organization_name, contact_person, phone_number, email,
            dzongkhag, camp_type, venue_address, preferred_date, alternate_date,
            expected_donors, facilities_available, additional_info, status, created_at
         FROM tblblood_camps
         WHERE status != 'deleted'
         ORDER BY preferred_date ASC, id DESC"
    );
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
