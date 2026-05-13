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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$claims = bts_require_auth(['donor']);
$userId = (int)($claims['sub'] ?? 0);
$email = trim((string)($claims['email'] ?? ''));

if ($userId <= 0 || $email === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid authenticated user.']);
    exit;
}

try {
    $hasStatusColumn = $tableHasColumn($pdo, 'tbldonors', 'status');
    $hasDeferredColumn = $tableHasColumn($pdo, 'tbldonors', 'deferred');
    $hasDeferralReasonColumn = $tableHasColumn($pdo, 'tbldonors', 'deferral_reason');
    $hasDeferredUntilColumn = $tableHasColumn($pdo, 'tbldonors', 'deferred_until');
    $hasSampleTestedColumn = $tableHasColumn($pdo, 'tbldonors', 'sample_tested');
    $hasSampleTestedAtColumn = $tableHasColumn($pdo, 'tbldonors', 'sample_tested_at');
    $hasEligibilityColumn = $tableHasColumn($pdo, 'tbldonors', 'eligibility');
    $hasTestResultColumn = $tableHasColumn($pdo, 'tbldonors', 'test_result');
    $hasWorkflowStatusColumn = $tableHasColumn($pdo, 'tbldonors', 'workflow_status');

    $statusSelect = $hasStatusColumn ? "COALESCE(status, 'Pending') AS status" : "'Pending' AS status";
    $deferredSelect = $hasDeferredColumn ? 'COALESCE(deferred, 0) AS deferred' : '0 AS deferred';
    $deferralReasonSelect = $hasDeferralReasonColumn ? 'deferral_reason' : 'NULL AS deferral_reason';
    $deferredUntilSelect = $hasDeferredUntilColumn ? 'deferred_until' : 'NULL AS deferred_until';
    $sampleTestedSelect = $hasSampleTestedColumn ? 'sample_tested' : 'NULL AS sample_tested';
    $sampleTestedAtSelect = $hasSampleTestedAtColumn ? 'sample_tested_at' : 'NULL AS sample_tested_at';
    $eligibilitySelect = $hasEligibilityColumn ? 'eligibility' : 'NULL AS eligibility';
    $testResultSelect = $hasTestResultColumn ? 'test_result' : 'NULL AS test_result';
    $workflowStatusSelect = $hasWorkflowStatusColumn ? 'workflow_status' : "NULL AS workflow_status";

    // Try exact email match first, then case-insensitive + trimmed email to avoid stale fallback profiles.
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, phone, date_of_birth, blood_type, address, city, dzongkhag,
                emergency_contact_name, emergency_contact_phone, age, gender,
                ' . $statusSelect . ',
                ' . $deferredSelect . ',
                ' . $deferralReasonSelect . ',
                ' . $deferredUntilSelect . ',
            ' . $sampleTestedSelect . ',
            ' . $sampleTestedAtSelect . ',
            ' . $eligibilitySelect . ',
            ' . $testResultSelect . ',
            ' . $workflowStatusSelect . ',
                created_at
         FROM tbldonors
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $stmtByNormalizedEmail = $pdo->prepare(
            'SELECT id, full_name, email, phone, date_of_birth, blood_type, address, city, dzongkhag,
                    ' . $statusSelect . ',
                    ' . $deferredSelect . ',
                    ' . $deferralReasonSelect . ',
                    ' . $deferredUntilSelect . ',
                    ' . $sampleTestedSelect . ',
                    ' . $sampleTestedAtSelect . ',
                          ' . $eligibilitySelect . ',
                          ' . $testResultSelect . ',
                    ' . $workflowStatusSelect . ',
                    created_at
             FROM tbldonors
             WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
             LIMIT 1'
        );
        $stmtByNormalizedEmail->execute([$email]);
        $profile = $stmtByNormalizedEmail->fetch(PDO::FETCH_ASSOC);
    }

    if (!$profile) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Donor profile not found in tbldonors for authenticated user.',
            'data' => [
                'user_id' => $userId,
                'email' => $email,
            ],
        ]);
        exit;
    }

    // Add computed current_status based on workflow_status (which is the source of truth)
    $workflowStatus = (string)($profile['workflow_status'] ?? '');
    $oldStatus = (string)($profile['status'] ?? 'Pending');
    
    if ($workflowStatus === 'test_result_pending_decision') {
        $profile['current_status'] = 'pending_review';
    } elseif ($workflowStatus === 'decision_made_deferred') {
        $profile['current_status'] = 'deferred';
    } elseif ($workflowStatus === 'decision_made_accepted') {
        $profile['current_status'] = 'confirmed';
    } elseif ($workflowStatus === 'decision_made_rejected') {
        $profile['current_status'] = 'rejected';
    } else {
        // Fallback to old status field with text mapping
        $statusLower = strtolower($oldStatus);
        if (strpos($statusLower, 'approved') !== false) {
            $profile['current_status'] = 'confirmed';
        } elseif (strpos($statusLower, 'deferred') !== false) {
            $profile['current_status'] = 'deferred';
        } elseif (strpos($statusLower, 'rejected') !== false) {
            $profile['current_status'] = 'rejected';
        } elseif (strpos($statusLower, 'pending') !== false || strpos($statusLower, 'awaiting') !== false) {
            $profile['current_status'] = 'pending';
        } else {
            $profile['current_status'] = 'pending'; // Default fallback
        }
    }

    echo json_encode(['success' => true, 'data' => $profile]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
