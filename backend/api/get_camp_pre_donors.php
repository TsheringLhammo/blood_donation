<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin', 'staff']);

$campId = (int)($_GET['camp_id'] ?? 0);
if ($campId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'camp_id is required.']);
    exit;
}

try {
    $hasTable = (bool)$pdo->query("SHOW TABLES LIKE 'tblcamp_pre_donors'")->fetchColumn();
    if (!$hasTable) {
        echo json_encode(['success' => true, 'data' => [], 'camp_id' => $campId, 'total' => 0]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, camp_id, donor_name, cid_number, blood_group, phone_number, created_at
           FROM tblcamp_pre_donors
          WHERE camp_id = ?
          ORDER BY id ASC'
    );
    $stmt->execute([$campId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'camp_id' => $campId,
        'total' => count($rows),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
