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
    $sampleColumns = workflow_table_columns($pdo, 'tbldonor_samples');
    $hasAdminFinalized = in_array('admin_finalized', $sampleColumns, true);
    $hasDecisionAfterTest = in_array('decision_after_test', $sampleColumns, true);
    $hasDecisionDate = in_array('decision_date', $sampleColumns, true);
    $hasDecisionNotes = in_array('decision_notes', $sampleColumns, true);
    $hasDonorNotified = in_array('donor_notified', $sampleColumns, true);
    $hasNotificationSentAt = in_array('notification_sent_at', $sampleColumns, true);

    $adminFinalizedExpr = $hasAdminFinalized ? 'COALESCE(s.admin_finalized, 0) AS admin_finalized' : '0 AS admin_finalized';
    $decisionAfterTestExpr = $hasDecisionAfterTest ? 's.decision_after_test' : 'NULL AS decision_after_test';
    $decisionDateExpr = $hasDecisionDate ? 's.decision_date' : 'NULL AS decision_date';
    $decisionNotesExpr = $hasDecisionNotes ? 's.decision_notes' : 'NULL AS decision_notes';
    $donorNotifiedExpr = $hasDonorNotified ? 's.donor_notified' : 'NULL AS donor_notified';
    $notificationSentAtExpr = $hasNotificationSentAt ? 's.notification_sent_at' : 'NULL AS notification_sent_at';

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
            ' . $adminFinalizedExpr . ',
            ' . $decisionAfterTestExpr . ',
            ' . $decisionDateExpr . ',
            ' . $decisionNotesExpr . ',
            ' . $donorNotifiedExpr . ',
            ' . $notificationSentAtExpr . ',
            d.status AS donor_status,
            d.workflow_status,
            d.final_decision,
            d.defer_until_date,
            d.deferred_until,
            d.approval_rejection_reason
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         ORDER BY COALESCE(s.tested_at, s.collection_date, s.id) DESC'
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $results = [];

    foreach ($rows as $row) {
        $testResult = workflow_compute_test_result($row);
        $decision = strtolower(trim((string)($row['decision_after_test'] ?? '')));
        $adminFinalized = (int)($row['admin_finalized'] ?? 0) === 1;

        if ($adminFinalized || in_array($decision, ['accept', 'defer', 'reject', 'retest'], true)) {
            continue;
        }

        $results[] = [
            'sample_id' => (int)($row['sample_id'] ?? 0),
            'donor_id' => (int)($row['donor_id'] ?? 0),
            'full_name' => (string)($row['full_name'] ?? ''),
            'email' => workflow_clean_email($row['email'] ?? ''),
            'phone' => workflow_clean_email($row['phone'] ?? ''),
            'blood_type' => (string)($row['blood_type'] ?? ''),
            'tested_at' => $row['tested_at'] ?? $row['collection_date'] ?? null,
            'technician' => (string)($row['technician'] ?? ''),
            'test_result' => $testResult,
            'test_result_label' => ucfirst($testResult),
            'admin_decision_needed' => true,
            'sample_status' => (string)($row['sample_status'] ?? ''),
            'workflow_status' => workflow_normalize_workflow_status($row),
            'decision_after_test' => $row['decision_after_test'] ?? null,
            'decision_notes' => $row['decision_notes'] ?? null,
            'donor_status' => $row['donor_status'] ?? null,
            'deferred_until' => $row['deferred_until'] ?? $row['defer_until_date'] ?? null,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
