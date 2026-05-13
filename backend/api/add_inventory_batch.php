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

bts_require_auth(['staff', 'admin']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$bloodType = trim((string)($data['bloodType'] ?? ''));
$bloodBankId = max(1, (int)($data['bloodBankId'] ?? 1));
$whole = max(0, (int)($data['whole'] ?? 0));
$prbc = max(0, (int)($data['prbc'] ?? 0));
$platelets = max(0, (int)($data['platelets'] ?? 0));
$ffp = max(0, (int)($data['ffp'] ?? 0));

$allowedBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (!in_array($bloodType, $allowedBloodTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid blood type.']);
    exit;
}

try {
    $bankStmt = $pdo->prepare('SELECT id FROM tblblood_banks WHERE id = ? LIMIT 1');
    $bankStmt->execute([$bloodBankId]);
    if (!$bankStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid blood bank id.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ffp_units)
         VALUES (:blood_bank_id, :blood_type, :whole_units, :prbc_units, :platelets_units, :ffp_units)
         ON DUPLICATE KEY UPDATE
           whole_units = whole_units + VALUES(whole_units),
           prbc_units = prbc_units + VALUES(prbc_units),
           platelets_units = platelets_units + VALUES(platelets_units),
           ffp_units = ffp_units + VALUES(ffp_units),
           updated_at = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
        ':whole_units' => $whole,
        ':prbc_units' => $prbc,
        ':platelets_units' => $platelets,
        ':ffp_units' => $ffp,
    ]);

    echo json_encode(['success' => true, 'message' => 'Inventory batch added successfully.']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
