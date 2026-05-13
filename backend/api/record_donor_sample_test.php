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

bts_require_auth(['staff', 'admin']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$sampleId = (int)($data['sampleId'] ?? $data['sample_id'] ?? 0);
$hivResult = trim((string)($data['hivResult'] ?? $data['hiv_result'] ?? $data['hiv'] ?? ''));
$hbsagResult = trim((string)($data['hbsagResult'] ?? $data['hbsag_result'] ?? $data['hbsag'] ?? ''));
$hcvResult = trim((string)($data['hcvResult'] ?? $data['hcv_result'] ?? $data['hcv'] ?? ''));
$syphilisResult = trim((string)($data['syphilisResult'] ?? $data['syphilis_result'] ?? $data['syphilis'] ?? ''));
$malariaResult = trim((string)($data['malariaResult'] ?? $data['malaria_result'] ?? $data['malaria'] ?? ''));
$testedBy = trim((string)($data['technician'] ?? $data['testing_technician'] ?? $data['testedBy'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

// Validate required fields
if ($sampleId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'sampleId is required and must be > 0.']);
    exit;
}

if (empty($testedBy)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'testing_technician is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch sample and donor information
    $sampleStmt = $pdo->prepare(
        'SELECT s.id, s.donor_id, s.status, d.full_name 
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.id = ? LIMIT 1 FOR UPDATE'
    );
    $sampleStmt->execute([$sampleId]);
    $sample = $sampleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sample) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sample not found.']);
        exit;
    }

    if ($sample['status'] !== 'Pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Sample has already been processed. Cannot update.'
        ]);
        exit;
    }

    $donorId = (int)$sample['donor_id'];
    $donorName = (string)$sample['full_name'];

    // Normalize test results to lowercase for comparison
    $hivNorm = strtolower(trim($hivResult));
    $hbsagNorm = strtolower(trim($hbsagResult));
    $hcvNorm = strtolower(trim($hcvResult));
    $syphilisNorm = strtolower(trim($syphilisResult));
    $malariaNorm = strtolower(trim($malariaResult));
    $extraDiseases = is_array($data['extra_diseases'] ?? null) ? $data['extra_diseases'] : [];

    // Normalize extra disease result values and check which are reactive
    $normalizeExtraResult = function(string $value): string {
        $normalized = strtolower(trim($value));
        return ($normalized === 'reactive' || $normalized === 'positive') ? 'reactive' : 'non-reactive';
    };

    $reactiveTests = [];
    if ($hivNorm === 'reactive') $reactiveTests['HIV'] = true;
    if ($hbsagNorm === 'reactive') $reactiveTests['HBsAg'] = true;
    if ($hcvNorm === 'reactive') $reactiveTests['HCV'] = true;
    if ($syphilisNorm === 'reactive') $reactiveTests['Syphilis'] = true;
    if ($malariaNorm === 'reactive') $reactiveTests['Malaria'] = true;

    foreach ($extraDiseases as $extra) {
        if (!is_array($extra)) {
            continue;
        }
        $name = trim((string)($extra['name'] ?? $extra['disease'] ?? ''));
        $result = $normalizeExtraResult(trim((string)($extra['result'] ?? $extra['value'] ?? '')));
        if ($name === '') {
            continue;
        }
        if ($result === 'reactive') {
            $reactiveTests[$name] = true;
        }
    }

    // Update sample with test results and mark as processed
    $updateSampleSql = '
        UPDATE tbldonor_samples 
        SET status = "Processed",
            hiv_result = ?,
            hbsag_result = ?,
            hcv_result = ?,
            syphilis_result = ?,
            malaria_result = ?,
            tested_by = ?,
            tested_at = NOW(),
            notes = ?,
            reactive_tests = ?
        WHERE id = ?
    ';
    
    $updateSampleStmt = $pdo->prepare($updateSampleSql);
    $updateSampleStmt->execute([
        $hivResult,
        $hbsagResult,
        $hcvResult,
        $syphilisResult,
        $malariaResult,
        $testedBy,
        $notes,
        implode(', ', array_keys($reactiveTests)) ?: null,
        $sampleId
    ]);

    $successMessage = '';
    $notificationType = 'info';

    // Handle reactive results: defer donor
    if (!empty($reactiveTests)) {
        $reactiveTestNames = implode(', ', array_keys($reactiveTests));
        $deferralReason = 'Positive ' . $reactiveTestNames;
        $deferredUntil = date('Y-m-d', strtotime('+6 months'));

        // Update donor status to Deferred
        $updateDonorStmt = $pdo->prepare(
            'UPDATE tbldonors 
             SET status = "Deferred", 
                 deferred = 1, 
                 deferred_until = ?, 
                 deferral_reason = ?,
                 updated_at = NOW()
             WHERE id = ?'
        );
        $updateDonorStmt->execute([$deferredUntil, $deferralReason, $donorId]);

        // Create admin notification
        $adminTitle = "Reactive Blood Test - {$donorName}";
        $adminMessage = "Donor {$donorName} (ID: {$donorId}) has tested positive for: {$reactiveTestNames}. Donor automatically deferred until {$deferredUntil}. Sample ID: {$sampleId}.";

        $adminNotifyStmt = $pdo->prepare(
            'INSERT INTO tblnotifications (user_id, title, message, type, is_read, created_at) 
             VALUES (NULL, ?, ?, "alert", 0, NOW())'
        );
        $adminNotifyStmt->execute([$adminTitle, $adminMessage]);

        $successMessage = "Sample test recorded. Donor {$donorName} has been deferred due to positive result(s): {$reactiveTestNames}";
        $notificationType = 'reactive';

    } else {
        // All non-reactive: mark donor as eligible for full donation
        $updateDonorStmt = $pdo->prepare(
            'UPDATE tbldonors 
             SET last_negative_sample_date = CURDATE(), 
                 sample_eligible = 1,
                 updated_at = NOW()
             WHERE id = ?'
        );
        $updateDonorStmt->execute([$donorId]);

        // Create donor notification (thank you)
        $donorTitle = "Sample Test Complete";
        $donorMessage = "Your blood sample test results are all negative. You are eligible to proceed with blood donation.";

        $donorNotifyStmt = $pdo->prepare(
            'INSERT INTO tblnotifications (user_id, title, message, type, is_read, created_at) 
             VALUES (?, ?, ?, "success", 0, NOW())'
        );
        $donorNotifyStmt->execute([$donorId, $donorTitle, $donorMessage]);

        $successMessage = "Sample test recorded. All results non-reactive. Donor {$donorName} is eligible for full blood donation.";
        $notificationType = 'eligible';
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $successMessage,
        'data' => [
            'sample_id' => $sampleId,
            'donor_id' => $donorId,
            'donor_name' => $donorName,
            'status' => 'Processed',
            'has_reactive_results' => !empty($reactiveTests),
            'reactive_tests' => array_keys($reactiveTests),
            'notification_type' => $notificationType
        ]
    ]);

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
