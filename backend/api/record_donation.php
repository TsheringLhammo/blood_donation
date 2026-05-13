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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['staff', 'admin']);
$actorUserId = (int)($claims['sub'] ?? 0);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donorId = isset($data['donorId']) ? (int)$data['donorId'] : null;
$donorName = trim((string)($data['donorName'] ?? ''));
$bloodBankId = max(1, (int)($data['bloodBankId'] ?? 1));
$bloodType = trim((string)($data['bloodType'] ?? ''));
$component = trim((string)($data['component'] ?? 'Whole Blood'));
$unitsCollected = max(1, (int)($data['unitsCollected'] ?? 1));
$donationDate = trim((string)($data['donationDate'] ?? date('Y-m-d H:i:s')));
$notes = trim((string)($data['notes'] ?? ''));

$allowedBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$allowedComponents = ['Whole Blood', 'Packed Red Cells', 'Plasma', 'Platelets'];

if (!in_array($bloodType, $allowedBloodTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid blood type.']);
    exit;
}

if (!in_array($component, $allowedComponents, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid blood component.']);
    exit;
}

if ($donorId === null && $donorName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Provide donorId or donorName.']);
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
        'INSERT INTO tbldonations
         (donor_id, donor_name, blood_bank_id, blood_type, component, units_collected, donation_date, status, collected_by_user_id, notes)
         VALUES
         (:donor_id, :donor_name, :blood_bank_id, :blood_type, :component, :units_collected, :donation_date, :status, :collected_by_user_id, :notes)'
    );

    $stmt->execute([
        ':donor_id' => $donorId !== null && $donorId > 0 ? $donorId : null,
        ':donor_name' => $donorName !== '' ? $donorName : null,
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
        ':component' => $component,
        ':units_collected' => $unitsCollected,
        ':donation_date' => $donationDate,
        ':status' => 'Testing Pending',
        ':collected_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
        ':notes' => $notes !== '' ? $notes : null,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Donation recorded. Testing is pending.',
        'id' => (int)$pdo->lastInsertId(),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
