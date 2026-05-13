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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

function has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
}

function request_pk_column(PDO $pdo): string
{
    return has_column($pdo, 'tblblood_requests', 'request_id') ? 'request_id' : 'id';
}

function request_patient_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_requests', 'patient')) {
        return 'patient';
    }

    return 'patient_name';
}

function request_gender_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_requests', 'patient_gender')) {
        return 'patient_gender';
    }

    if (has_column($pdo, 'tblblood_requests', 'gender')) {
        return 'gender';
    }

    return '';
}

function request_units_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_requests', 'units')) {
        return 'units';
    }

    return 'units_requested';
}

function request_priority_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_requests', 'priority')) {
        return 'priority';
    }

    return 'urgency';
}

function request_requested_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_requests', 'requested_at')) {
        return 'requested_at';
    }

    return 'created_at';
}

$claims = bts_require_auth(['doctor', 'staff', 'admin']);
$role = (string)($claims['role'] ?? '');
$userId = (int)($claims['sub'] ?? 0);

try {
    $patient = trim((string)($_GET['patient'] ?? ''));
    $requestPk = request_pk_column($pdo);
    $patientColumn = request_patient_column($pdo);
    $genderColumn = request_gender_column($pdo);
    $unitsColumn = request_units_column($pdo);
    $priorityColumn = request_priority_column($pdo);
    $requestedColumn = request_requested_column($pdo);

    $doctorFilter = '';
    $queryParams = [];
    if ($role === 'doctor' && has_column($pdo, 'tblblood_requests', 'doctor_user_id')) {
        $doctorFilter = 'WHERE br.doctor_user_id = ?';
        $queryParams[] = $userId;
    }

    $sql = 'SELECT br.' . $requestPk . ' AS id,
                    br.' . $requestPk . ' AS request_id,
                    COALESCE(br.request_code, CONCAT("REQ-", br.' . $requestPk . ')) AS request_code,
                    br.' . $patientColumn . ' AS patient,
                    br.' . $patientColumn . ' AS patient_name,
                    ' . ($genderColumn !== '' ? 'br.' . $genderColumn . ' AS patient_gender,' : 'NULL AS patient_gender,') . '
                    br.blood_type,
                    br.component,
                    br.' . $unitsColumn . ' AS units,
                    br.' . $unitsColumn . ' AS units_requested,
                    br.' . $priorityColumn . ' AS priority,
                    br.' . $priorityColumn . ' AS urgency,
                    br.diagnosis,
                    br.reason_for_transfusion,
                    br.status,
                    br.' . $requestedColumn . ' AS requested_at,
                    br.created_at,
                    br.updated_at,
                    ll.result AS crossmatch_result, ll.donor_unit_refs, ll.test_parameters,
                    ll.technician_name, ll.created_at AS tested_at
             FROM tblblood_requests br
             LEFT JOIN tbllab_logs ll ON ll.id = (
                SELECT l2.id
                FROM tbllab_logs l2
                WHERE l2.request_id = br.' . $requestPk . '
                ORDER BY l2.created_at DESC, l2.id DESC
                LIMIT 1
             )
             ' . $doctorFilter . '
             ORDER BY br.' . $requestPk . ' DESC';

    if ($doctorFilter !== '') {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParams);
    } else {
        $stmt = $pdo->query($sql);
    }

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $history = [];
    if ($patient !== '') {
                $historyStmt = $pdo->prepare(
                    'SELECT DATE(created_at) AS date,
                            COALESCE(NULLIF(TRIM(diagnosis), ""), NULLIF(TRIM(reason_for_transfusion), ""), "Not recorded") AS diagnosis,
                            CONCAT(' . $unitsColumn . ', " unit(s) ", component) AS transfusion,
                            status AS outcome
             FROM tblblood_requests
             WHERE ' . $patientColumn . ' = ?
             ORDER BY ' . $requestPk . ' DESC
             LIMIT 10'
        );
        $historyStmt->execute([$patient]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode([
        'success' => true,
        'data' => $requests,
        'history' => $history,
        'serverTime' => gmdate('c'),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
