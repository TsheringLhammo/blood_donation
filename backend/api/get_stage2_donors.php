<?php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

try {
    $sql = "
        SELECT
            d.id,
            d.full_name AS donor_name,
            d.email,
            d.phone,
            d.blood_type,
            d.status AS donor_status,
            COALESCE(d.workflow_status, '') AS workflow_status,
            d.created_at AS registration_date,
            s.id AS sample_id,
            s.collection_date AS sample_date,
            s.test_status AS test_status,
            s.admin_finalized AS admin_finalized,
            s.status AS sample_status,
            s.positive_diseases,
            s.decision_after_test,
            s.decision_date,
            s.hiv_result AS hiv,
            s.hbsag_result AS hbsag,
            s.hcv_result AS hcv,
            s.syphilis_result AS syphilis,
            s.malaria_result AS malaria,
            s.technician,
            s.tested_by,
            s.tested_at,
            s.notes
        FROM tbldonors d
        LEFT JOIN tbldonor_samples s ON s.id = (
            SELECT s2.id
            FROM tbldonor_samples s2
            WHERE s2.donor_id = d.id
            ORDER BY s2.collection_date DESC, s2.id DESC
            LIMIT 1
        )
        WHERE LOWER(d.status) = 'approved for blood draw'
           OR d.workflow_status = 'approved_for_blood_draw'
        ORDER BY d.created_at DESC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $data = [];
    foreach ($rows as $row) {
        $testStatus = strtolower(trim((string)($row['test_status'] ?? '')));
        if ($testStatus === '') {
            $testStatus = 'no_sample';
        }

        $data[] = [
            'id' => (int)($row['id'] ?? 0),
            'donor_name' => $row['donor_name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'blood_type' => $row['blood_type'] ?? '',
            'donor_status' => $row['donor_status'] ?? '',
            'workflow_status' => $row['workflow_status'] ?? '',
            'registration_date' => $row['registration_date'] ?? null,
            'sample_id' => isset($row['sample_id']) ? (int)$row['sample_id'] : null,
            'sample_date' => $row['sample_date'] ?? null,
            'test_status' => $testStatus,
            'admin_finalized' => isset($row['admin_finalized']) ? (int)$row['admin_finalized'] : 0,
            'sample_status' => $row['sample_status'] ?? null,
            'latest_test_result' => $row['sample_status'] ?? null,
            'positive_diseases' => $row['positive_diseases'] ?? null,
            'decision_after_test' => $row['decision_after_test'] ?? null,
            'decision_date' => $row['decision_date'] ?? null,
            'hiv' => $row['hiv'] ?? null,
            'hbsag' => $row['hbsag'] ?? null,
            'hcv' => $row['hcv'] ?? null,
            'syphilis' => $row['syphilis'] ?? null,
            'malaria' => $row['malaria'] ?? null,
            'technician' => $row['technician'] ?? null,
            'tested_by' => $row['tested_by'] ?? null,
            'tested_at' => $row['tested_at'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $exception) {
    error_log('get_stage2_donors.php error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load Stage 2 donors.']);
}
