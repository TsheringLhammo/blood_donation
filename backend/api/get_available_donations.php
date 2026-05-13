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

$claims = bts_require_auth(['staff', 'admin']);

$component = trim((string)($_GET['component'] ?? 'Packed Red Cells'));
$bloodBankId = max(1, (int)($_GET['bloodBankId'] ?? 1));
$limit = min(200, max(20, (int)($_GET['limit'] ?? 100)));

$allowedComponents = ['Whole Blood', 'Packed Red Cells', 'Platelets', 'FFP', 'Cryoprecipitate'];
if (!in_array($component, $allowedComponents, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid component filter.']);
    exit;
}

try {
    $sql = '
        SELECT
            d.id,
            d.donor_name,
            d.blood_type,
            d.component AS donation_component,
            d.status,
            d.donation_date
        FROM tbldonations d
        WHERE d.blood_bank_id = :blood_bank_id
          AND d.status IN ("Collected", "Testing Pending", "Safe", "Stocked")
          AND NOT EXISTS (
              SELECT 1
              FROM tblblood_units u
              WHERE u.donation_id = CAST(d.id AS CHAR)
                AND u.component = :component
          )
        ORDER BY d.donation_date DESC, d.id DESC
        LIMIT :limit_rows';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':blood_bank_id', $bloodBankId, PDO::PARAM_INT);
    $stmt->bindValue(':component', $component, PDO::PARAM_STR);
    $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $donations = array_map(static function (array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'donorName' => (string)($row['donor_name'] ?? ''),
            'bloodType' => (string)($row['blood_type'] ?? ''),
            'donationComponent' => (string)($row['donation_component'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'donationDate' => (string)($row['donation_date'] ?? ''),
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'donations' => $donations,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
