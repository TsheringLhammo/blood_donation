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
require_once __DIR__ . '/message_helpers.php';

$claims = bts_require_auth(['admin']);
$adminId = (int)($claims['sub'] ?? 0);
$adminName = trim((string)($claims['name'] ?? ''));

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$sampleId = (int)($payload['sample_id'] ?? $payload['sampleId'] ?? 0);
if ($sampleId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'sample_id is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $sampleStmt = $pdo->prepare(
        'SELECT s.*, d.full_name, d.email, d.phone, d.blood_type
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.id = ?
         LIMIT 1 FOR UPDATE'
    );
    $sampleStmt->execute([$sampleId]);
    $sample = $sampleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sample) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sample not found.']);
        exit;
    }

    if ($sample['review_status'] !== 'Pending Admin Approval') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only samples pending admin approval can be approved.']);
        exit;
    }

    $reactiveTests = [];
    if (strtolower(trim((string)$sample['hiv_result'])) === 'reactive') {
        $reactiveTests[] = 'HIV';
    }
    if (strtolower(trim((string)$sample['hbsag_result'])) === 'reactive') {
        $reactiveTests[] = 'HBsAg';
    }
    if (strtolower(trim((string)$sample['hcv_result'])) === 'reactive') {
        $reactiveTests[] = 'HCV';
    }
    if (strtolower(trim((string)$sample['syphilis_result'])) === 'reactive') {
        $reactiveTests[] = 'Syphilis';
    }
    if (strtolower(trim((string)$sample['malaria_result'])) === 'reactive') {
        $reactiveTests[] = 'Malaria';
    }

    $allNegative = empty($reactiveTests);
    $hasPermanentReactive = (
        strtolower(trim((string)$sample['hiv_result'])) === 'reactive' ||
        strtolower(trim((string)$sample['hbsag_result'])) === 'reactive' ||
        strtolower(trim((string)$sample['hcv_result'])) === 'reactive' ||
        strtolower(trim((string)$sample['syphilis_result'])) === 'reactive'
    );
    $eligibility = $allNegative ? 'Eligible' : 'Not Eligible';
    $subject = 'Blood Sample Test Results';

    if ($allNegative) {
        $messageContent = sprintf(
            'Dear %s, your blood sample test results are NEGATIVE for HIV, Hepatitis B, Hepatitis C, Syphilis, and Malaria. You are eligible to donate blood. Thank you for saving lives!',
            trim((string)$sample['full_name'])
        );
    } else {
        $positiveList = implode(', ', $reactiveTests);
        $messageContent = sprintf(
            'Dear %s, thank you for your willingness to donate blood. Unfortunately, your test showed REACTIVE/POSITIVE for %s. You are not eligible to donate at this time. Please consult a healthcare provider for further guidance. This information is kept confidential.',
            trim((string)$sample['full_name']),
            $positiveList
        );
    }

    $updateSampleStmt = $pdo->prepare(
        'UPDATE tbldonor_samples
         SET review_status = "Approved",
             approved_by_admin_id = ?,
             approved_by_admin_name = ?,
             approved_at = NOW(),
             updated_at = NOW()
         WHERE id = ?'
    );
    $updateSampleStmt->execute([$adminId, $adminName, $sampleId]);

    $donorTestResult = $allNegative ? 'Negative' : 'Positive';
    $donorEligibility = $eligibility;

    if ($allNegative) {
        $donorUpdateStmt = $pdo->prepare(
            'UPDATE tbldonors
             SET sample_status = "Tested",
                 test_result = ?,
                 eligibility = ?,
                 workflow_status = ?,
                 status = ?,
                 deferred = 0,
                 deferred_until = NULL,
                 deferral_reason = NULL,
                 updated_at = NOW()
             WHERE id = ?'
        );
        $donorUpdateStmt->execute([
            $donorTestResult,
            $donorEligibility,
            'approved_donor',
            'Approved Donor',
            (int)$sample['donor_id'],
        ]);
    } else {
        $positiveList = implode(', ', $reactiveTests);
        $donorWorkflowStatus = $hasPermanentReactive ? 'permanently_deferred' : 'decision_made_deferred';
        $donorStatus = $hasPermanentReactive ? 'Permanently Deferred' : 'Deferred';
        $donorDeferred = 1;
        $donorDeferredUntil = $hasPermanentReactive ? null : date('Y-m-d', strtotime('+6 months'));
        $donorDeferralReason = 'Reactive: ' . $positiveList;

        $donorUpdateStmt = $pdo->prepare(
            'UPDATE tbldonors
             SET sample_status = "Tested",
                 test_result = ?,
                 eligibility = ?,
                 workflow_status = ?,
                 status = ?,
                 deferred = ?,
                 deferred_until = ?,
                 deferral_reason = ?,
                 updated_at = NOW()
             WHERE id = ?'
        );
        $donorUpdateStmt->execute([
            $donorTestResult,
            $donorEligibility,
            $donorWorkflowStatus,
            $donorStatus,
            $donorDeferred,
            $donorDeferredUntil,
            $donorDeferralReason,
            (int)$sample['donor_id'],
        ]);
    }

    $messageKey = sprintf('sample_test_result_%d', $sampleId);
    $notification = send_donor_test_result_notification($pdo, [
        'donor_id' => (int)$sample['donor_id'],
        'sample_id' => $sampleId,
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'message_key' => $messageKey,
        'message_content' => $messageContent,
        'subject' => $subject,
        'email' => trim((string)$sample['email']),
        'phone' => trim((string)$sample['phone']),
        'channel' => 'both',
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $allNegative ? 'Sample approved and donor notification queued.' : 'Sample processed and donor notification queued.',
        'data' => [
            'sample_id' => $sampleId,
            'donor_id' => (int)$sample['donor_id'],
            'donor_name' => trim((string)$sample['full_name']),
            'eligibility' => $eligibility,
            'preview_message' => $messageContent,
            'notification_status' => $notification['status'] ?? 'failed',
            'notification_errors' => $notification['errors'] ?? [],
            'notification_skipped' => $notification['skipped'] ?? false,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
