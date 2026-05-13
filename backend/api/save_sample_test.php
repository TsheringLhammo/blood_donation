<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Database configuration - direct connection (simpler approach)
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'blood_donation';

require_once __DIR__ . '/workflow_helpers.php';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Helper function to check if column exists
function table_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
                 AND table_name = ?
                 AND column_name = ?
             LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// Get JSON input
$payload = json_decode((string)file_get_contents('php://input'), true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

// Extract values - support both naming conventions
$sampleId = (int)($payload['sampleId'] ?? $payload['sample_id'] ?? 0);
$hiv = trim((string)($payload['hiv'] ?? $payload['hiv_result'] ?? 'Non-reactive'));
$hbsag = trim((string)($payload['hbsag'] ?? $payload['hbsag_result'] ?? 'Non-reactive'));
$hcv = trim((string)($payload['hcv'] ?? $payload['hcv_result'] ?? 'Non-reactive'));
$syphilis = trim((string)($payload['syphilis'] ?? $payload['syphilis_result'] ?? 'Non-reactive'));
$malaria = trim((string)($payload['malaria'] ?? $payload['malaria_result'] ?? 'Non-reactive'));
$notes = trim((string)($payload['notes'] ?? $payload['comments'] ?? ''));
$testedBy = trim((string)($payload['technician'] ?? $payload['tested_by'] ?? $payload['testedBy'] ?? ''));

// Validate inputs
if ($sampleId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'sampleId is required.']);
    exit;
}

if ($testedBy === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'technician name is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get sample and donor information
    $sampleStmt = $pdo->prepare(
        'SELECT s.id, s.donor_id, s.status as sample_status, 
                d.full_name AS donor_name, d.email, d.blood_type
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.id = ?
         LIMIT 1'
    );
    $sampleStmt->execute([$sampleId]);
    $sample = $sampleStmt->fetch();

    if (!$sample) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sample not found.']);
        exit;
    }

    // Normalize result values
    $normalizeResult = function(string $value): string {
        $normalized = strtolower(trim($value));
        if ($normalized === 'reactive' || $normalized === 'positive') {
            return 'Reactive';
        }
        return 'Non-reactive';
    };

    $hivResult = $normalizeResult($hiv);
    $hbsagResult = $normalizeResult($hbsag);
    $hcvResult = $normalizeResult($hcv);
    $syphilisResult = $normalizeResult($syphilis);
    $malariaResult = $normalizeResult($malaria);

    $extraDiseases = is_array($payload['extra_diseases'] ?? null) ? $payload['extra_diseases'] : [];

    // Determine which tests are reactive
    $reactiveTests = [];
    if ($hivResult === 'Reactive') $reactiveTests[] = 'HIV';
    if ($hbsagResult === 'Reactive') $reactiveTests[] = 'Hepatitis B';
    if ($hcvResult === 'Reactive') $reactiveTests[] = 'Hepatitis C';
    if ($syphilisResult === 'Reactive') $reactiveTests[] = 'Syphilis';
    if ($malariaResult === 'Reactive') $reactiveTests[] = 'Malaria';

    foreach ($extraDiseases as $extra) {
        if (!is_array($extra)) {
            continue;
        }
        $name = trim((string)($extra['name'] ?? $extra['disease'] ?? ''));
        $result = $normalizeResult(trim((string)($extra['result'] ?? $extra['value'] ?? 'Non-reactive')));

        if ($name === '') {
            continue;
        }

        if ($result === 'Reactive') {
            $reactiveTests[] = $name;
        }
    }

    $hasReactive = !empty($reactiveTests);
    $sampleStatus = $hasReactive ? 'Reactive' : 'Negative';
    $donorResult = $hasReactive ? 'Positive' : 'Negative';
    $testStatus = $hasReactive ? 'deferred' : 'eligible';
    $positiveList = implode(', ', $reactiveTests);

    // Update tbldonor_samples table
    $updateStmt = $pdo->prepare(
        'UPDATE tbldonor_samples
         SET hiv_result = ?,
             hbsag_result = ?,
             hcv_result = ?,
             syphilis_result = ?,
             malaria_result = ?,
             tested_by = ?,
             notes = ?,
             status = ?,
             test_status = ?,
             positive_diseases = ?,
             tested_at = NOW(),
             updated_at = NOW()
         WHERE id = ?'
    );
    
    $updateStmt->execute([
        $hivResult,
        $hbsagResult,
        $hcvResult,
        $syphilisResult,
        $malariaResult,
        $testedBy,
        $notes,
        $sampleStatus,
        $testStatus,
        $positiveList ?: null,
        $sampleId,
    ]);

    // Update tbldonors table with the tested state only.
    // Final donor approval/deferral is applied after admin review.
    $donorUpdateFields = [];
    $donorUpdateValues = [];

    if (table_column_exists($pdo, 'tbldonors', 'sample_tested')) {
        $donorUpdateFields[] = 'sample_tested = ?';
        $donorUpdateValues[] = $hasReactive ? 'reactive' : 'negative';
    }

    if (table_column_exists($pdo, 'tbldonors', 'sample_tested_at')) {
        $donorUpdateFields[] = 'sample_tested_at = NOW()';
    }

    if (table_column_exists($pdo, 'tbldonors', 'latest_test_result')) {
        $donorUpdateFields[] = 'latest_test_result = ?';
        $donorUpdateValues[] = $donorResult;
    }

    if (table_column_exists($pdo, 'tbldonors', 'latest_test_date')) {
        $donorUpdateFields[] = 'latest_test_date = NOW()';
    }

    if (table_column_exists($pdo, 'tbldonors', 'workflow_status')) {
        $donorUpdateFields[] = 'workflow_status = ?';
        $donorUpdateValues[] = 'test_result_pending_decision';
    }

    if (table_column_exists($pdo, 'tbldonors', 'status')) {
        $donorUpdateFields[] = 'status = ?';
        $donorUpdateValues[] = 'Awaiting Admin Review';
    }

    if (table_column_exists($pdo, 'tbldonors', 'deferred')) {
        $donorUpdateFields[] = 'deferred = 0';
    }

    if (table_column_exists($pdo, 'tbldonors', 'deferred_until')) {
        $donorUpdateFields[] = 'deferred_until = NULL';
    }

    if (table_column_exists($pdo, 'tbldonors', 'deferral_reason')) {
        $donorUpdateFields[] = 'deferral_reason = NULL';
    }

    // Add donor ID at the end for WHERE clause
    $donorUpdateValues[] = $sample['donor_id'];

    // Build and execute donor update query if there are fields to update
    if (!empty($donorUpdateFields)) {
        $donorUpdateSql = 'UPDATE tbldonors SET ' . implode(', ', $donorUpdateFields) . ' WHERE id = ?';
        $donorUpdateStmt = $pdo->prepare($donorUpdateSql);
        $donorUpdateStmt->execute($donorUpdateValues);
    }

    // Notify admins first so they review the result before the donor receives anything.
    $adminNotificationTitle = $hasReactive
        ? 'Sample Test Needs Review: Positive Result'
        : 'Sample Test Ready for Review';
    $adminNotificationMessage = $hasReactive
        ? 'Staff recorded a positive sample result for ' . $sample['donor_name'] . ' (' . $positiveList . '). Please review and accept/defer the case.'
        : 'Staff recorded a non-reactive sample result for ' . $sample['donor_name'] . '. Please review and accept the case.';
    $adminNotificationSeverity = $hasReactive ? 'critical' : 'warning';

    workflow_insert_notification($pdo, [
        'donor_id' => (int)$sample['donor_id'],
        'user_id' => null,
        'role_target' => 'admin',
        'title' => $adminNotificationTitle,
        'message' => $adminNotificationMessage,
        'type' => $hasReactive ? 'sample_test_reactive' : 'sample_test_pending_review',
        'severity' => $adminNotificationSeverity,
        'channel' => 'in_app',
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $hasReactive 
            ? '⚠️ Reactive results detected for ' . $sample['donor_name'] . '. Admin review is pending.' 
            : '✅ All tests negative for ' . $sample['donor_name'] . '. Admin review is pending.',
        'data' => [
            'sample_id' => $sampleId,
            'donor_id' => (int)$sample['donor_id'],
            'donor_name' => (string)$sample['donor_name'],
            'donor_email' => (string)$sample['email'],
            'blood_type' => (string)$sample['blood_type'],
            'sample_status' => $sampleStatus,
            'has_reactive_results' => $hasReactive,
            'reactive_tests' => $reactiveTests,
            'positive_list' => $positiveList,
            'test_status' => $testStatus,
            'awaiting_admin' => true
        ],
    ]);

} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("save_sample_test.php PDO error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $exception->getMessage()]);
    
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("save_sample_test.php error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
}
?>