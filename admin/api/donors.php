<?php
require_once '../../backend/config/auth.php';
require_once '../../backend/config/db.php';

bts_require_auth(['admin']);

header('Content-Type: application/json');

$status = strtolower(trim((string)($_GET['status'] ?? 'all')));

if ($status !== 'all') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status parameter']);
    exit;
}

try {
    $stmt = $pdo->query(
        'SELECT
            d.id,
            d.full_name,
            d.email,
            d.phone,
            d.blood_type,
            d.workflow_status,
            ds.id AS sample_id,
            ds.review_status,
            ds.hiv_result,
            ds.hbsag_result,
            ds.hcv_result,
            ds.syphilis_result,
            ds.malaria_result,
            CONCAT_WS(", ",
                CASE WHEN ds.hiv_result = "Reactive" THEN "HIV" END,
                CASE WHEN ds.hbsag_result = "Reactive" THEN "Hepatitis B" END,
                CASE WHEN ds.hcv_result = "Reactive" THEN "Hepatitis C" END,
                CASE WHEN ds.syphilis_result = "Reactive" THEN "Syphilis" END,
                CASE WHEN ds.malaria_result = "Reactive" THEN "Malaria" END
            ) AS positive_diseases
         FROM tbldonors d
         LEFT JOIN tbldonor_samples ds ON ds.donor_id = d.id
             AND ds.id = (
                 SELECT MAX(id)
                 FROM tbldonor_samples
                 WHERE donor_id = d.id
             )
         ORDER BY d.created_at DESC'
    );

    $donors = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $diseases = trim((string)$row['positive_diseases']);
        $donors[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'blood_type' => $row['blood_type'] ?? '',
            'workflow_status' => $row['workflow_status'] ?? '',
            'sample_id' => isset($row['sample_id']) ? (int)$row['sample_id'] : 0,
            'review_status' => $row['review_status'] ?? '',
            'hiv_result' => $row['hiv_result'] ?? null,
            'hbsag_result' => $row['hbsag_result'] ?? null,
            'hcv_result' => $row['hcv_result'] ?? null,
            'syphilis_result' => $row['syphilis_result'] ?? null,
            'malaria_result' => $row['malaria_result'] ?? null,
            'positive_diseases' => $diseases,
            'test_result_label' => $diseases !== '' ? "Positive ({$diseases})" : 'Negative',
        ];
    }

    echo json_encode(['success' => true, 'data' => $donors]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
