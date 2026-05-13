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

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

try {
    $claims = bts_require_auth(['donor']);
    $donorId = (int)($claims['donor_id'] ?? 0);
    $tokenEmail = trim((string)($claims['email'] ?? ''));

    if ($donorId <= 0 && $tokenEmail !== '') {
        $fallbackStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
        $fallbackStmt->execute([$tokenEmail]);
        $fallbackRow = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        $donorId = (int)($fallbackRow['id'] ?? 0);
    }

    if ($donorId <= 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token is missing donor context. Please log in again.',
        ]);
        exit;
    }

    $columnOrNull = static function (PDO $pdo, string $table, string $column): string {
        return workflow_table_has_column($pdo, $table, $column) ? $column : 'NULL AS ' . $column;
    };

    $stmt = $pdo->prepare(
        'SELECT id,
                full_name,
                REPLACE(TRIM(COALESCE(email, "")), " ", "") AS email,
                REPLACE(TRIM(COALESCE(phone, "")), " ", "") AS phone,
                ' . $columnOrNull($pdo, 'tbldonors', 'date_of_birth') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'address') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'city') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'dzongkhag') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'emergency_contact_name') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'emergency_contact_phone') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'blood_group') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'blood_type') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'age') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'gender') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'weight') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'profile_picture') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'hiv_result') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'hbsag_result') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'hcv_result') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'syphilis_result') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'malaria_result') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'last_donation_date') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'next_eligible_date') . ',
                COALESCE(status, "Pending") AS status,
                ' . $columnOrNull($pdo, 'tbldonors', 'initial_approval_status') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'approval_rejection_reason') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'blood_drawn') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'test_result') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'sample_tested') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'eligibility') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'final_decision') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'defer_until_date') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'donor_notified_stage1') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'donor_notified_stage2') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'workflow_status') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'deferred_until') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'deferral_reason') . '
         FROM tbldonors
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Donor profile not found.',
        ]);
        exit;
    }

    $approvalStatus = strtolower(trim((string)($donor['initial_approval_status'] ?? 'pending')));
    $finalDecision = strtolower(trim((string)($donor['final_decision'] ?? 'pending')));
    $workflowStatus = strtolower(trim((string)($donor['workflow_status'] ?? '')));
    if ($workflowStatus === '') {
        $workflowStatus = workflow_normalize_workflow_status($donor);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$donor['id'],
            'full_name' => (string)$donor['full_name'],
            'email' => (string)$donor['email'],
            'phone' => (string)($donor['phone'] ?? ''),
            'date_of_birth' => $donor['date_of_birth'] ?? null,
            'blood_group' => $donor['blood_group'] ?? ($donor['blood_type'] ?? null),
            'blood_type' => $donor['blood_type'] ?? ($donor['blood_group'] ?? null),
            'age' => (int)($donor['age'] ?? 0),
            'gender' => $donor['gender'] ?? null,
            'weight' => $donor['weight'] ?? null,
            'address' => $donor['address'] ?? null,
            'city' => $donor['city'] ?? null,
            'dzongkhag' => $donor['dzongkhag'] ?? null,
            'profile_picture' => $donor['profile_picture'] ?? null,
            'hiv_result' => $donor['hiv_result'] ?? null,
            'hbsag_result' => $donor['hbsag_result'] ?? null,
            'hcv_result' => $donor['hcv_result'] ?? null,
            'syphilis_result' => $donor['syphilis_result'] ?? null,
            'malaria_result' => $donor['malaria_result'] ?? null,
            'last_donation_date' => $donor['last_donation_date'] ?? null,
            'next_eligible_date' => $donor['next_eligible_date'] ?? null,
            'emergency_contact_name' => $donor['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $donor['emergency_contact_phone'] ?? null,
            'status' => (string)$donor['status'],
            'initial_approval_status' => $approvalStatus,
            'approval_rejection_reason' => $donor['approval_rejection_reason'] ?? null,
            'blood_drawn' => (int)($donor['blood_drawn'] ?? 0),
            'test_result' => strtolower(trim((string)($donor['test_result'] ?? 'not_tested'))),
            'sample_tested' => strtolower(trim((string)($donor['sample_tested'] ?? 'pending'))),
            'eligibility' => strtolower(trim((string)($donor['eligibility'] ?? ''))),
            'final_decision' => $finalDecision,
            'defer_until_date' => $donor['defer_until_date'] ?? null,
            'deferred_until' => $donor['deferred_until'] ?? null,
            'deferral_reason' => $donor['deferral_reason'] ?? null,
            'donor_notified_stage1' => (int)($donor['donor_notified_stage1'] ?? 0),
            'donor_notified_stage2' => (int)($donor['donor_notified_stage2'] ?? 0),
            'workflow_status' => $workflowStatus,
        ],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage(),
    ]);
}
