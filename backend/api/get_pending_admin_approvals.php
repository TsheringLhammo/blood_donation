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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

try {
    $stmt = $pdo->query(
        'SELECT
            s.id AS sample_id,
            s.donor_id,
            d.full_name,
            REPLACE(TRIM(COALESCE(d.email, "")), " ", "") AS email,
            REPLACE(TRIM(COALESCE(d.phone, "")), " ", "") AS phone,
            d.blood_type,
            s.collection_date,
            s.tested_at,
            s.technician,
            s.status AS sample_status,
            s.hiv_result,
            s.hbsag_result,
            s.hcv_result,
            s.syphilis_result,
            s.malaria_result,
            s.reactive_tests,
            s.review_status,
            s.approved_by_admin_id,
            s.approved_by_admin_name,
            s.approved_at,
            d.status AS donor_status,
            d.sample_status AS donor_sample_status,
            d.test_result AS donor_test_result,
            d.eligibility AS donor_eligibility
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.review_status = "Pending Admin Approval"
         ORDER BY COALESCE(s.tested_at, s.collection_date, s.id) DESC'
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $pending = [];

    foreach ($rows as $row) {
        $reactive = array_filter([
            strtolower(trim((string)$row['hiv_result'])) === 'reactive' ? 'HIV' : null,
            strtolower(trim((string)$row['hbsag_result'])) === 'reactive' ? 'HBsAg' : null,
            strtolower(trim((string)$row['hcv_result'])) === 'reactive' ? 'HCV' : null,
            strtolower(trim((string)$row['syphilis_result'])) === 'reactive' ? 'Syphilis' : null,
            strtolower(trim((string)$row['malaria_result'])) === 'reactive' ? 'Malaria' : null,
        ]);

        $pending[] = [
            'sample_id' => (int)$row['sample_id'],
            'donor_id' => (int)$row['donor_id'],
            'full_name' => (string)$row['full_name'],
            'email' => (string)$row['email'],
            'phone' => (string)$row['phone'],
            'blood_type' => (string)$row['blood_type'],
            'collection_date' => $row['collection_date'] ?? null,
            'tested_at' => $row['tested_at'] ?? null,
            'technician' => (string)$row['technician'],
            'sample_status' => (string)$row['sample_status'],
            'test_results' => [
                'HIV' => (string)$row['hiv_result'],
                'HBsAg' => (string)$row['hbsag_result'],
                'HCV' => (string)$row['hcv_result'],
                'Syphilis' => (string)$row['syphilis_result'],
                'Malaria' => (string)$row['malaria_result'],
            ],
            'reactive_tests' => array_values($reactive),
            'reactive_tests_label' => $row['reactive_tests'] ?? implode(', ', array_values($reactive)),
            'eligibility' => empty($reactive) ? 'Eligible' : 'Not Eligible',
            'workflow_status' => (string)$row['review_status'],
            'donor_sample_status' => (string)$row['donor_sample_status'],
            'donor_test_result' => (string)$row['donor_test_result'],
            'donor_eligibility' => (string)$row['donor_eligibility'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $pending]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
