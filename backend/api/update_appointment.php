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
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

bts_require_auth(['admin']);

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$id = (int)($payload['id'] ?? 0);
$fullName = trim((string)($payload['full_name'] ?? ''));
$preferredDate = trim((string)($payload['preferred_date'] ?? ''));
$preferredTime = trim((string)($payload['preferred_time'] ?? ''));
$bloodBank = trim((string)($payload['blood_bank'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment id']);
    exit;
}

if ($fullName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Full name is required']);
    exit;
}

if (mb_strlen($fullName) > 120) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Full name is too long']);
    exit;
}

if ($preferredDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preferred date is required']);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $preferredDate);
if (!$date || $date->format('Y-m-d') !== $preferredDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preferred date must be in YYYY-MM-DD format']);
    exit;
}

if ($bloodBank === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Blood bank is required']);
    exit;
}

if (mb_strlen($bloodBank) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Blood bank name is too long']);
    exit;
}

if (mb_strlen($preferredTime) > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preferred time is too long']);
    exit;
}

try {
    $existsStmt = $pdo->prepare('SELECT id FROM tblappointments WHERE id = ? LIMIT 1');
    $existsStmt->execute([$id]);
    if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE tblappointments
         SET full_name = :full_name,
             preferred_date = :preferred_date,
             preferred_time = :preferred_time,
             blood_bank = :blood_bank
         WHERE id = :id'
    );
    $updateStmt->execute([
        ':full_name' => $fullName,
        ':preferred_date' => $preferredDate,
        ':preferred_time' => $preferredTime !== '' ? $preferredTime : null,
        ':blood_bank' => $bloodBank,
        ':id' => $id,
    ]);

    $rowStmt = $pdo->prepare(
        'SELECT id, full_name, age, blood_group, phone_number, preferred_date, preferred_time, blood_bank, status, created_at
         FROM tblappointments
         WHERE id = ? LIMIT 1'
    );
    $rowStmt->execute([$id]);
    $appointment = $rowStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Appointment updated successfully',
        'appointment' => $appointment,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
