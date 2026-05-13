<?php
/**
 * get_donor_tables.php
 * Returns donor data for Admin Dashboard tables with POSITIVE DISEASES
 * 
 * Settings: host=localhost, user=root, password="", database=blood_donation
 */

// Configuration
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';  // empty password
$DB_NAME = 'blood_donation';
$DEBUG_MODE = true;

// CORS and JSON headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

// Connect to database
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_error) {
    http_response_code(500);
    $errorMsg = $DEBUG_MODE ? "Connection failed: " . $mysqli->connect_error : "Database connection error";
    echo json_encode(['error' => $errorMsg]);
    exit;
}

$mysqli->set_charset("utf8mb4");

function handleSqlError($mysqli, $context, $debugMode) {
    $errorMsg = $debugMode ? "SQL error in $context: " . $mysqli->error : "Database query failed";
    error_log("get_donor_tables.php: " . $mysqli->error . " in context: $context");
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

try {
    // QUERY 1: Donors Awaiting Test Result Decision (Stage 2) - WITH SAMPLE RESULTS
    $sqlAwaiting = "
        SELECT 
            d.id,
            d.full_name AS name,
            d.email,
            d.phone,
            d.blood_type,
            d.workflow_status,
            d.latest_test_result,
            d.sample_tested,
            d.created_at AS register_date,
            ds.id AS sample_id,
            ds.hiv_result,
            ds.hbsag_result,
            ds.hcv_result,
            ds.syphilis_result,
            ds.malaria_result,
            ds.test_status,
            ds.admin_finalized,
            ds.decision_after_test,
            ds.donor_notified,
            CONCAT_WS(', ',
                CASE WHEN LOWER(TRIM(ds.hiv_result)) IN ('reactive', 'positive') THEN 'HIV' END,
                CASE WHEN LOWER(TRIM(ds.hbsag_result)) IN ('reactive', 'positive') THEN 'Hepatitis B' END,
                CASE WHEN LOWER(TRIM(ds.hcv_result)) IN ('reactive', 'positive') THEN 'Hepatitis C' END,
                CASE WHEN LOWER(TRIM(ds.syphilis_result)) IN ('reactive', 'positive') THEN 'Syphilis' END,
                CASE WHEN LOWER(TRIM(ds.malaria_result)) IN ('reactive', 'positive') THEN 'Malaria' END
            ) AS positive_diseases
        FROM tbldonors d
        LEFT JOIN (
            SELECT donor_id, id, hiv_result, hbsag_result, hcv_result, syphilis_result, malaria_result,
                   test_status, admin_finalized, decision_after_test, donor_notified
            FROM tbldonor_samples 
            WHERE id IN (SELECT MAX(id) FROM tbldonor_samples GROUP BY donor_id)
        ) ds ON d.id = ds.donor_id
        WHERE d.workflow_status IN ('approved_for_blood_draw', 'test_result_pending_decision')
           OR ds.test_status IN ('eligible', 'deferred')
        ORDER BY d.created_at DESC
    ";
    
    $resultAwaiting = $mysqli->query($sqlAwaiting);
    if (!$resultAwaiting) {
        handleSqlError($mysqli, "awaiting_decision query", $DEBUG_MODE);
    }
    
    $awaitingDecision = [];
    while ($row = $resultAwaiting->fetch_assoc()) {
        $awaitingDecision[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'blood_type' => $row['blood_type'] ?? '',
            'donor_name' => $row['name'] ?? '',
            'sample_id' => $row['sample_id'] ? (int)$row['sample_id'] : 0,
            'hiv_result' => $row['hiv_result'] ?? null,
            'hbsag_result' => $row['hbsag_result'] ?? null,
            'hcv_result' => $row['hcv_result'] ?? null,
            'syphilis_result' => $row['syphilis_result'] ?? null,
            'malaria_result' => $row['malaria_result'] ?? null,
            'test_status' => $row['test_status'] ?? 'pending',
            'admin_finalized' => (int)($row['admin_finalized'] ?? 0),
            'decision_after_test' => $row['decision_after_test'] ?? 'pending',
            'donor_notified' => (int)($row['donor_notified'] ?? 0),
            'positive_diseases' => $row['positive_diseases'] ?? '',
            'latest_test_result' => $row['latest_test_result'] ?? 'not_tested',
            'sample_tested' => $row['sample_tested'] ?? 'Pending',
            'workflow_status' => $row['workflow_status'] ?? 'approved_for_blood_draw',
            'register_date' => $row['register_date'] ?? null
        ];
    }
    $resultAwaiting->free();

    // QUERY 2: All Registered Donors (Reference) - WITH POSITIVE DISEASES
    $sqlAllDonors = "
        SELECT 
            d.id,
            d.full_name AS name,
            d.email,
            d.phone,
            d.blood_type,
            d.workflow_status,
            d.latest_test_result,
            d.latest_test_date,
            d.sample_tested,
            d.created_at AS register_date,
            ds.hiv_result,
            ds.hbsag_result,
            ds.hcv_result,
            ds.syphilis_result,
            ds.malaria_result,
            ds.test_status,
            ds.admin_finalized,
            CONCAT_WS(', ',
                CASE WHEN LOWER(TRIM(ds.hiv_result)) IN ('reactive', 'positive') THEN 'HIV' END,
                CASE WHEN LOWER(TRIM(ds.hbsag_result)) IN ('reactive', 'positive') THEN 'Hepatitis B' END,
                CASE WHEN LOWER(TRIM(ds.hcv_result)) IN ('reactive', 'positive') THEN 'Hepatitis C' END,
                CASE WHEN LOWER(TRIM(ds.syphilis_result)) IN ('reactive', 'positive') THEN 'Syphilis' END,
                CASE WHEN LOWER(TRIM(ds.malaria_result)) IN ('reactive', 'positive') THEN 'Malaria' END
            ) AS positive_diseases
        FROM tbldonors d
        LEFT JOIN (
            SELECT donor_id, hiv_result, hbsag_result, hcv_result, syphilis_result, malaria_result,
                   test_status, admin_finalized
            FROM tbldonor_samples 
            WHERE id IN (SELECT MAX(id) FROM tbldonor_samples GROUP BY donor_id)
        ) ds ON d.id = ds.donor_id
        ORDER BY d.created_at DESC
    ";
    
    $resultAllDonors = $mysqli->query($sqlAllDonors);
    if (!$resultAllDonors) {
        handleSqlError($mysqli, "all_donors query", $DEBUG_MODE);
    }
    
    $allDonors = [];
    while ($row = $resultAllDonors->fetch_assoc()) {
        // Format test result with positive diseases
        $testResultDisplay = '—';
        $positiveDiseases = $row['positive_diseases'] ?? '';
        
        $latestResult = strtolower(trim((string)($row['latest_test_result'] ?? '')));
        if ($latestResult === 'negative' || $latestResult === 'non-reactive') {
            $testResultDisplay = 'Negative';
        } elseif (in_array($latestResult, ['positive', 'reactive'], true) || $positiveDiseases !== '') {
            $testResultDisplay = $positiveDiseases ? "Positive ($positiveDiseases)" : 'Positive';
        } elseif ($latestResult === 'not_tested' || $latestResult === '') {
            $testResultDisplay = '—';
        }
        
        $allDonors[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'blood_type' => $row['blood_type'] ?? '',
            'workflow_status' => $row['workflow_status'] ?? 'pending_approval',
            'latest_test_result' => $row['latest_test_result'] ?? 'not_tested',
            'latest_test_result_display' => $testResultDisplay,
            'positive_diseases' => $positiveDiseases,
            'sample_tested' => $row['sample_tested'] ?? 'Pending',
            'register_date' => $row['register_date'] ?? null,
            'hiv_result' => $row['hiv_result'] ?? null,
            'hbsag_result' => $row['hbsag_result'] ?? null,
            'hcv_result' => $row['hcv_result'] ?? null,
            'syphilis_result' => $row['syphilis_result'] ?? null,
            'malaria_result' => $row['malaria_result'] ?? null
        ];
    }
    $resultAllDonors->free();

    // QUERY 3: Donors with pending test results (for Stage 2)
    $sqlPendingTests = "
        SELECT 
            d.id,
            d.full_name,
            d.email,
            d.phone,
            d.blood_type,
            ds.id as sample_id,
            ds.hiv_result,
            ds.hbsag_result,
            ds.hcv_result,
            ds.syphilis_result,
            ds.malaria_result,
            ds.test_status,
            ds.admin_finalized,
            CONCAT_WS(', ',
                CASE WHEN LOWER(TRIM(ds.hiv_result)) IN ('reactive', 'positive') THEN 'HIV' END,
                CASE WHEN LOWER(TRIM(ds.hbsag_result)) IN ('reactive', 'positive') THEN 'Hepatitis B' END,
                CASE WHEN LOWER(TRIM(ds.hcv_result)) IN ('reactive', 'positive') THEN 'Hepatitis C' END,
                CASE WHEN LOWER(TRIM(ds.syphilis_result)) IN ('reactive', 'positive') THEN 'Syphilis' END,
                CASE WHEN LOWER(TRIM(ds.malaria_result)) IN ('reactive', 'positive') THEN 'Malaria' END
            ) AS positive_diseases
        FROM tbldonor_samples ds
        JOIN tbldonors d ON ds.donor_id = d.id
        WHERE ds.admin_finalized = 0 
          AND ds.hiv_result IS NOT NULL
          AND ds.hiv_result != ''
        ORDER BY ds.created_at DESC
    ";
    
    $resultPendingTests = $mysqli->query($sqlPendingTests);
    if (!$resultPendingTests) {
        handleSqlError($mysqli, "pending_tests query", $DEBUG_MODE);
    }
    
    $pendingTests = [];
    while ($row = $resultPendingTests->fetch_assoc()) {
        $pendingTests[] = [
            'id' => (int)$row['id'],
            'donor_name' => $row['full_name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'blood_type' => $row['blood_type'] ?? '',
            'sample_id' => (int)$row['sample_id'],
            'hiv_result' => $row['hiv_result'],
            'hbsag_result' => $row['hbsag_result'],
            'hcv_result' => $row['hcv_result'],
            'syphilis_result' => $row['syphilis_result'],
            'malaria_result' => $row['malaria_result'],
            'test_status' => $row['test_status'],
            'admin_finalized' => (int)$row['admin_finalized'],
            'positive_diseases' => $row['positive_diseases'] ?? ''
        ];
    }
    $resultPendingTests->free();

    $mysqli->close();

    // Return success response
    echo json_encode([
        'success' => true,
        'awaiting_decision' => $awaitingDecision,
        'all_donors' => $allDonors,
        'pending_tests' => $pendingTests,
        'stage2_donors' => $awaitingDecision
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("get_donor_tables.php exception: " . $e->getMessage());
    http_response_code(500);
    $errorMsg = $DEBUG_MODE ? "Error: " . $e->getMessage() : "Internal server error";
    echo json_encode(['error' => $errorMsg]);
}
?>