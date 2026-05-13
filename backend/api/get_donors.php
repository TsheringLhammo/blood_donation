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

function donor_column_exists(PDO $pdo, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM tbldonors LIKE ?");
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

try {
    $genderSelect = donor_column_exists($pdo, 'gender') ? 'gender' : 'NULL AS gender';
    $weightSelect = donor_column_exists($pdo, 'weight') ? 'weight' : 'NULL AS weight';
    $lastDonationSelect = donor_column_exists($pdo, 'last_donation_date') ? 'last_donation_date' : 'NULL AS last_donation_date';
    $statusSelect = donor_column_exists($pdo, 'status') ? 'LOWER(TRIM(COALESCE(status, "pending"))) AS status' : 'status';
    $rawStatusSelect = donor_column_exists($pdo, 'status') ? 'status AS raw_status' : 'status';
    $stmt = $pdo->query(
        "SELECT id, full_name, email, phone, date_of_birth, {$genderSelect}, blood_type, {$weightSelect}, {$lastDonationSelect}, {$statusSelect}, {$rawStatusSelect}, city, dzongkhag, created_at
         FROM tbldonors
         ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
