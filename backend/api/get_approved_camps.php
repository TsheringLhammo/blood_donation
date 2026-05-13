<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

try {
    // Get approved camps scheduled for future dates, ordered by date
    $stmt = $pdo->prepare(
        'SELECT id, organization_name, contact_person, phone_number, email,
                dzongkhag, camp_type, venue_address, preferred_date, alternate_date,
                expected_donors, facilities_available, additional_info, status, created_at
         FROM tblblood_camps
         WHERE status = "confirmed" AND preferred_date >= CURDATE()
         ORDER BY preferred_date ASC, id DESC
         LIMIT 50'
    );
    $stmt->execute();
    $camps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'total' => count($camps),
        'data' => $camps,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
?>
