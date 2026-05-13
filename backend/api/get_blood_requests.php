<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

$claims = bts_require_auth(['doctor', 'staff', 'admin']);
$role = (string)($claims['role'] ?? '');
$userId = (int)($claims['sub'] ?? 0);

try {
    if ($role === 'doctor') {
        $stmt = $pdo->prepare(
            'SELECT 
                br.id, br.request_code, br.patient_name, br.patient_gender, br.blood_type, br.component, 
                br.units_requested, br.urgency, br.status, br.date_time_required, 
                br.created_at, br.diagnosis, br.reason_for_transfusion,
                ll.result AS crossmatch_result, ll.donor_unit_refs, ll.test_parameters, 
                ll.technician_name, ll.created_at AS tested_at
             FROM tblblood_requests br
             LEFT JOIN tbllab_logs ll ON br.id = ll.request_id
             WHERE br.doctor_user_id = ?
             ORDER BY br.id DESC, ll.created_at DESC'
        );
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query(
            'SELECT 
                br.id, br.request_code, br.patient_name, br.patient_gender, br.blood_type, br.component, 
                br.units_requested, br.urgency, br.status, br.date_time_required, 
                br.created_at, br.diagnosis, br.reason_for_transfusion,
                ll.result AS crossmatch_result, ll.donor_unit_refs, ll.test_parameters, 
                ll.technician_name, ll.created_at AS tested_at
             FROM tblblood_requests br
             LEFT JOIN tbllab_logs ll ON br.id = ll.request_id
             ORDER BY br.id DESC, ll.created_at DESC'
        );
    }

    $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by request to get only latest cross-match
    $requests = [];
    $seenIds = [];
    foreach ($rawResults as $row) {
        $id = $row['id'];
        if (!isset($seenIds[$id])) {
            $requests[] = $row;
            $seenIds[$id] = true;
        }
    }

    $historyStmt = $pdo->prepare(
        'SELECT DATE(created_at) AS date, diagnosis, CONCAT(units_requested, " unit(s) ", component) AS transfusion, status AS outcome
         FROM tblblood_requests
         WHERE patient_name = ?
         ORDER BY id DESC
         LIMIT 10'
    );

    $history = [];
    if (isset($_GET['patient']) && trim((string)$_GET['patient']) !== '') {
        $historyStmt->execute([trim((string)$_GET['patient'])]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'data' => $requests, 'history' => $history]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
