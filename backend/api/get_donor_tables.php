<?php
/**
 * get_donor_tables.php
 * Returns donor data for Admin Dashboard tables
 * 
 * Settings: host=localhost, user=root, password="", database=blood_donation
 * 
 * Response format:
 * {
 *   "success": true,
 *   "awaiting_decision": [...],
 *   "all_donors": [...]
 * }
 */

// Configuration
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';  // empty password
$DB_NAME = 'blood_donation';
$DEBUG_MODE = true;  // Set to false in production to hide SQL errors

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

// Set charset
$mysqli->set_charset("utf8mb4");

// Helper function to handle SQL errors
function handleSqlError($mysqli, $context, $debugMode) {
    $errorMsg = $debugMode ? "SQL error in $context: " . $mysqli->error : "Database query failed";
    error_log("get_donor_tables.php: " . $mysqli->error . " in context: $context");
    http_response_code(500);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

try {
    $columnExists = static function (mysqli $mysqli, string $table, string $column): bool {
        $table = $mysqli->real_escape_string($table);
        $column = $mysqli->real_escape_string($column);
        $result = $mysqli->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!$result) return false;
        $exists = $result->num_rows > 0;
        $result->free();
        return $exists;
    };

    $formatHealthSummary = static function (array $row): string {
        $parts = [];

        if (!empty($row['health_declaration'])) {
            $raw = $row['health_declaration'];
            $parsed = json_decode((string)$raw, true);
            if (is_array($parsed)) {
                foreach ($parsed as $key => $value) {
                    $label = ucwords(str_replace('_', ' ', (string)$key));
                    $parts[] = $label . ': ' . (((int)$value === 1 || $value === true) ? 'Yes' : 'No');
                }
            } else {
                $parts[] = trim((string)$raw);
            }
        }

        $flagMap = [
            'health_tattoo' => 'Recent tattoo or piercing',
            'health_antibiotics' => 'On antibiotics',
            'health_surgery' => 'Recent surgery',
            'health_no_cold_flu' => 'No current cold/flu',
        ];

        foreach ($flagMap as $column => $label) {
            if (array_key_exists($column, $row) && $row[$column] !== null && $row[$column] !== '') {
                $parts[] = $label . ': ' . ((int)$row[$column] === 1 ? 'Yes' : 'No');
            }
        }

        if (array_key_exists('consent_medical', $row) && $row['consent_medical'] !== null && $row['consent_medical'] !== '') {
            $parts[] = 'Medical consent: ' . ((int)$row['consent_medical'] === 1 ? 'Yes' : 'No');
        } elseif (array_key_exists('consent', $row) && $row['consent'] !== null && $row['consent'] !== '') {
            $parts[] = 'Medical consent: ' . ((int)$row['consent'] === 1 ? 'Yes' : 'No');
        }

        return empty($parts) ? 'Not provided' : implode(' | ', $parts);
    };

    // Query 1: Donors Awaiting Test Result Decision (Stage 2)
    // workflow_status = 'test_result_pending_decision' means test was done, decision not made
    $sqlAwaiting = "
        SELECT 
            id,
            full_name AS name,
            email,
            phone,
            blood_type,
            latest_test_result AS test_result,
            sample_tested,
            workflow_status,
            created_at AS register_date,
            status
        FROM tbldonors
        WHERE workflow_status = 'test_result_pending_decision'
        ORDER BY created_at DESC
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
            'latest_test_result' => $row['test_result'] ?? 'not_tested',
            'sample_tested' => $row['sample_tested'] ?? 'Pending',
            'workflow_status' => $row['workflow_status'] ?? 'test_result_pending_decision',
            'register_date' => $row['register_date'] ?? null
        ];
    }
    $resultAwaiting->free();

    // Query 2: All Registered Donors (Reference)
    $workflowStatusSelect = $columnExists($mysqli, 'tbldonors', 'workflow_status') ? 'd.workflow_status' : 'NULL AS workflow_status';
    $latestTestResultSelect = $columnExists($mysqli, 'tbldonors', 'latest_test_result') ? 'd.latest_test_result' : 'NULL AS latest_test_result';
    $sampleTestedSelect = $columnExists($mysqli, 'tbldonors', 'sample_tested') ? 'd.sample_tested' : 'NULL AS sample_tested';

    $sqlAllDonors = "
        SELECT 
            d.id,
            d.full_name AS name,
            d.email,
            d.phone,
            d.blood_type,
            d.status,
            {$workflowStatusSelect},
            {$latestTestResultSelect},
            {$sampleTestedSelect},
            " . ($columnExists($mysqli, 'tbldonors', 'health_declaration') ? 'd.health_declaration,' : 'NULL AS health_declaration,') . "
            " . ($columnExists($mysqli, 'tbldonors', 'consent_medical') ? 'd.consent_medical,' : 'NULL AS consent_medical,') . "
            " . ($columnExists($mysqli, 'tbldonors', 'consent') ? 'd.consent,' : 'NULL AS consent,') . "
            " . ($columnExists($mysqli, 'tbldonors', 'health_tattoo') ? 'd.health_tattoo,' : 'NULL AS health_tattoo,') . "
            " . ($columnExists($mysqli, 'tbldonors', 'health_antibiotics') ? 'd.health_antibiotics,' : 'NULL AS health_antibiotics,') . "
            " . ($columnExists($mysqli, 'tbldonors', 'health_surgery') ? 'd.health_surgery,' : 'NULL AS health_surgery,') . "
            " . ($columnExists($mysqli, 'tbldonors', 'health_no_cold_flu') ? 'd.health_no_cold_flu,' : 'NULL AS health_no_cold_flu,') . "
            s.id AS sample_id,
            s.status AS sample_status,
            s.hiv_result,
            s.hbsag_result,
            s.hcv_result,
            s.syphilis_result,
            s.malaria_result,
            d.created_at AS register_date
        FROM tbldonors d
        LEFT JOIN tbldonor_samples s ON s.id = (
            SELECT s2.id
            FROM tbldonor_samples s2
            WHERE s2.donor_id = d.id
            ORDER BY COALESCE(s2.tested_at, s2.collection_date, s2.id) DESC
            LIMIT 1
        )
        ORDER BY d.created_at DESC
    ";
    
    $resultAllDonors = $mysqli->query($sqlAllDonors);
    if (!$resultAllDonors) {
        handleSqlError($mysqli, "all_donors query", $DEBUG_MODE);
    }
    
    $allDonors = [];
    while ($row = $resultAllDonors->fetch_assoc()) {
        $allDonors[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'blood_type' => $row['blood_type'] ?? '',
            'status' => $row['status'] ?? 'Pending',
            'workflow_status' => $row['workflow_status'] ?? 'pending_approval',
            'latest_test_result' => $row['latest_test_result'] ?? 'not_tested',
            'sample_id' => $row['sample_id'] ?? null,
            'sample_status' => $row['sample_status'] ?? null,
            'hiv_result' => $row['hiv_result'] ?? null,
            'hbsag_result' => $row['hbsag_result'] ?? null,
            'hcv_result' => $row['hcv_result'] ?? null,
            'syphilis_result' => $row['syphilis_result'] ?? null,
            'malaria_result' => $row['malaria_result'] ?? null,
            'health_declaration' => $row['health_declaration'] ?? null,
            'health_declaration_summary' => $formatHealthSummary($row),
            'positive_diseases' => implode(', ', array_values(array_filter([
                strtolower(trim((string)($row['hiv_result'] ?? ''))) === 'reactive' ? 'HIV' : null,
                strtolower(trim((string)($row['hbsag_result'] ?? ''))) === 'reactive' ? 'Hepatitis B' : null,
                strtolower(trim((string)($row['hcv_result'] ?? ''))) === 'reactive' ? 'Hepatitis C' : null,
                strtolower(trim((string)($row['syphilis_result'] ?? ''))) === 'reactive' ? 'Syphilis' : null,
                strtolower(trim((string)($row['malaria_result'] ?? ''))) === 'reactive' ? 'Malaria' : null,
            ]))),
            'latest_test_result_display' => function_exists('str_contains') && !empty($row['latest_test_result']) && stripos((string)$row['latest_test_result'], 'positive') !== false && !empty($row['sample_id']) ? (
                !empty(array_filter([
                    strtolower(trim((string)($row['hiv_result'] ?? ''))) === 'reactive' ? 'HIV' : null,
                    strtolower(trim((string)($row['hbsag_result'] ?? ''))) === 'reactive' ? 'Hepatitis B' : null,
                    strtolower(trim((string)($row['hcv_result'] ?? ''))) === 'reactive' ? 'Hepatitis C' : null,
                    strtolower(trim((string)($row['syphilis_result'] ?? ''))) === 'reactive' ? 'Syphilis' : null,
                    strtolower(trim((string)($row['malaria_result'] ?? ''))) === 'reactive' ? 'Malaria' : null,
                ])) ? 'Positive (' . implode(', ', array_values(array_filter([
                    strtolower(trim((string)($row['hiv_result'] ?? ''))) === 'reactive' ? 'HIV' : null,
                    strtolower(trim((string)($row['hbsag_result'] ?? ''))) === 'reactive' ? 'Hepatitis B' : null,
                    strtolower(trim((string)($row['hcv_result'] ?? ''))) === 'reactive' ? 'Hepatitis C' : null,
                    strtolower(trim((string)($row['syphilis_result'] ?? ''))) === 'reactive' ? 'Syphilis' : null,
                    strtolower(trim((string)($row['malaria_result'] ?? ''))) === 'reactive' ? 'Malaria' : null,
                ]))) . ')' : ucfirst((string)$row['latest_test_result'])
            ) : ucfirst((string)($row['latest_test_result'] ?? '')),
            'sample_tested' => $row['sample_tested'] ?? 'Pending',
            'register_date' => $row['register_date'] ?? null
        ];
    }
    $resultAllDonors->free();

    // Close connection
    $mysqli->close();

    // Return success response
    echo json_encode([
        'success' => true,
        'awaiting_decision' => $awaitingDecision,
        'all_donors' => $allDonors
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("get_donor_tables.php exception: " . $e->getMessage());
    http_response_code(500);
    $errorMsg = $DEBUG_MODE ? "Error: " . $e->getMessage() : "Internal server error";
    echo json_encode(['error' => $errorMsg]);
}
