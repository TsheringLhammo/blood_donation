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
require_once __DIR__ . '/workflow_helpers.php';

bts_require_auth(['admin']);

try {
    $stmt = $pdo->query(
        'SELECT
            d.id,
            d.full_name,
            REPLACE(TRIM(COALESCE(d.email, "")), " ", "") AS email,
            REPLACE(TRIM(COALESCE(d.phone, "")), " ", "") AS phone,
            d.date_of_birth,
            d.gender,
            d.blood_type,
            d.city,
            d.dzongkhag,
            d.status,
            d.workflow_status,
            d.initial_approval_status,
            d.approval_rejection_reason,
            d.blood_drawn,
            d.test_result,
            d.final_decision,
            d.defer_until_date,
            d.deferred_until,
            d.donor_notified_stage1,
            d.donor_notified_stage2,
            d.created_at,
            s.id AS sample_id,
            s.status AS sample_status,
            s.hiv_result,
            s.hbsag_result,
            s.hcv_result,
            s.syphilis_result,
            s.malaria_result,
            s.admin_finalized,
            s.decision_after_test,
            s.decision_date,
            s.decision_notes,
            s.donor_notified,
            s.notification_sent_at,
            s.tested_at,
            s.collection_date,
            s.technician
         FROM tbldonors d
         LEFT JOIN tbldonor_samples s ON s.id = (
             SELECT s2.id
             FROM tbldonor_samples s2
             WHERE s2.donor_id = d.id
             ORDER BY COALESCE(s2.tested_at, s2.collection_date, s2.id) DESC
             LIMIT 1
         )
         ORDER BY d.id DESC, COALESCE(s.tested_at, s.collection_date, s.id) DESC'
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $donors = [];

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        if (!isset($donors[$id])) {
            $donors[$id] = [
                'id' => $id,
                'full_name' => (string)($row['full_name'] ?? ''),
                'email' => workflow_clean_email($row['email'] ?? ''),
                'phone' => workflow_clean_email($row['phone'] ?? ''),
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'gender' => $row['gender'] ?? null,
                'blood_type' => $row['blood_type'] ?? null,
                'city' => $row['city'] ?? null,
                'dzongkhag' => $row['dzongkhag'] ?? null,
                'status' => $row['status'] ?? null,
                'workflow_status' => $row['workflow_status'] ?? null,
                'initial_approval_status' => $row['initial_approval_status'] ?? null,
                'approval_rejection_reason' => $row['approval_rejection_reason'] ?? null,
                'blood_drawn' => $row['blood_drawn'] ?? null,
                'test_result' => $row['test_result'] ?? null,
                'final_decision' => $row['final_decision'] ?? null,
                'defer_until_date' => $row['defer_until_date'] ?? null,
                'deferred_until' => $row['deferred_until'] ?? null,
                'donor_notified_stage1' => $row['donor_notified_stage1'] ?? null,
                'donor_notified_stage2' => $row['donor_notified_stage2'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'sample_id' => $row['sample_id'] ?? null,
                'sample_status' => $row['sample_status'] ?? null,
                'hiv_result' => $row['hiv_result'] ?? null,
                'hbsag_result' => $row['hbsag_result'] ?? null,
                'hcv_result' => $row['hcv_result'] ?? null,
                'syphilis_result' => $row['syphilis_result'] ?? null,
                'malaria_result' => $row['malaria_result'] ?? null,
                'admin_finalized' => $row['admin_finalized'] ?? null,
                'decision_after_test' => $row['decision_after_test'] ?? null,
                'decision_date' => $row['decision_date'] ?? null,
                'decision_notes' => $row['decision_notes'] ?? null,
                'donor_notified' => $row['donor_notified'] ?? null,
                'notification_sent_at' => $row['notification_sent_at'] ?? null,
                'tested_at' => $row['tested_at'] ?? null,
                'collection_date' => $row['collection_date'] ?? null,
                'technician' => $row['technician'] ?? null,
            ];
        }

        if (empty($donors[$id]['sample_id']) && !empty($row['sample_id'])) {
            $donors[$id]['sample_id'] = $row['sample_id'];
            $donors[$id]['sample_status'] = $row['sample_status'];
            $donors[$id]['hiv_result'] = $row['hiv_result'];
            $donors[$id]['hbsag_result'] = $row['hbsag_result'];
            $donors[$id]['hcv_result'] = $row['hcv_result'];
            $donors[$id]['syphilis_result'] = $row['syphilis_result'];
            $donors[$id]['malaria_result'] = $row['malaria_result'];
            $donors[$id]['admin_finalized'] = $row['admin_finalized'];
            $donors[$id]['decision_after_test'] = $row['decision_after_test'];
            $donors[$id]['decision_date'] = $row['decision_date'];
            $donors[$id]['decision_notes'] = $row['decision_notes'];
            $donors[$id]['donor_notified'] = $row['donor_notified'];
            $donors[$id]['notification_sent_at'] = $row['notification_sent_at'];
            $donors[$id]['tested_at'] = $row['tested_at'];
            $donors[$id]['collection_date'] = $row['collection_date'];
            $donors[$id]['technician'] = $row['technician'];
        }
    }

    echo json_encode(['success' => true, 'data' => array_values($donors)]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
