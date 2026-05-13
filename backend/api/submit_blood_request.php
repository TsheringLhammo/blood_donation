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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/request_workflow.php';

$claims = bts_require_auth(['doctor']);
$doctorUserId = (int)($claims['sub'] ?? 0);
$doctorName = trim((string)($claims['email'] ?? ''));

$generateRequestCode = static function(): string {
    return 'REQ-' . strtoupper(substr(hash('crc32b', uniqid((string) mt_rand(), true)), 0, 8));
};

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$requestId = $generateRequestCode();

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$hospitalName = trim((string)($data['hospitalName'] ?? ''));
$patientName = trim((string)($data['patientName'] ?? ''));
$patientDob = trim((string)($data['patientDob'] ?? ''));
$patientAge = trim((string)($data['patientAge'] ?? ''));
$patientGender = trim((string)($data['patientGender'] ?? ''));
$patientAddress = trim((string)($data['patientAddress'] ?? ''));
$patientRefNo = trim((string)($data['patientRefNo'] ?? ''));
$ward = trim((string)($data['ward'] ?? ''));
$bloodType = trim((string)($data['bloodType'] ?? ''));
$component = trim((string)($data['component'] ?? ''));
$units = (int)($data['units'] ?? 0);
$urgency = trim((string)($data['urgency'] ?? 'Routine'));
$diagnosis = trim((string)($data['diagnosis'] ?? ''));
$reason = trim((string)($data['reason'] ?? ''));
$dateRequired = trim((string)($data['dateRequired'] ?? ''));
$doctorDisplayName = trim((string)($data['doctorName'] ?? ''));
$notificationEmail = trim((string)($data['notificationEmail'] ?? ''));

if ($hospitalName === '' || $patientName === '' || $component === '' || $units <= 0 || $dateRequired === '' || $diagnosis === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: Diagnosis is mandatory. All blood transfusion requests must include a clinical diagnosis.']);
    exit;
}

$allowedUrgency = ['Routine', 'Urgent', 'Critical'];
if (!in_array($urgency, $allowedUrgency, true)) {
    $urgency = 'Routine';
}

$genderAliases = [
    'm' => 'Male',
    'male' => 'Male',
    'f' => 'Female',
    'female' => 'Female',
    'other' => 'Other',
];
if ($patientGender !== '') {
    $genderKey = strtolower(trim($patientGender));
    if (isset($genderAliases[$genderKey])) {
        $patientGender = $genderAliases[$genderKey];
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid patient gender.']);
        exit;
    }
}
$allowedComponents = ['Whole Blood', 'Packed Red Cells', 'Plasma', 'Platelets', 'Others'];
if (!in_array($component, $allowedComponents, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid blood component.']);
    exit;
}

if (strtotime($dateRequired) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The required date and time is not valid.']);
    exit;
}

try {
    $hasPatientRefNo = $tableHasColumn($pdo, 'tblblood_requests', 'patient_ref_no');

    $duplicateWhere = [
        'hospital_name = :hospital_name',
        'status IN ("Pending", "Approved", "Cross-Matching", "Matched")',
    ];
    $duplicateParams = [
        ':hospital_name' => $hospitalName,
    ];

    if ($hasPatientRefNo && $patientRefNo !== '') {
        $duplicateWhere[] = 'patient_ref_no = :patient_ref_no';
        $duplicateParams[':patient_ref_no'] = $patientRefNo;
    } else {
        $duplicateWhere[] = 'patient_name = :patient_name';
        $duplicateParams[':patient_name'] = $patientName;
    }

    $duplicateSql = 'SELECT id, request_code, status FROM tblblood_requests WHERE '
        . implode(' AND ', $duplicateWhere)
        . ' ORDER BY id DESC LIMIT 1';

    $duplicateCheckStmt = $pdo->prepare($duplicateSql);
    $duplicateCheckStmt->execute($duplicateParams);

    $duplicateRow = $duplicateCheckStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicateRow) {
        $existingCode = trim((string)($duplicateRow['request_code'] ?? ''));
        $existingStatus = trim((string)($duplicateRow['status'] ?? 'Pending'));
        $codeLabel = $existingCode !== '' ? $existingCode : ('#' . (string)($duplicateRow['id'] ?? ''));
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Active request already exists for this patient.',
            'duplicateRequestCode' => $codeLabel,
            'duplicateStatus' => $existingStatus,
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $insertColumns = [
        'request_code',
        'doctor_user_id',
        'doctor_name',
        'hospital_name',
        'patient_name',
    ];

    $optionalPatientFields = [
        'patient_dob' => $patientDob !== '' ? $patientDob : null,
        'patient_age' => $patientAge !== '' ? (int)$patientAge : null,
        'patient_gender' => $patientGender !== '' ? $patientGender : null,
        'patient_address' => $patientAddress !== '' ? $patientAddress : null,
    ];

    foreach ($optionalPatientFields as $column => $value) {
        if ($tableHasColumn($pdo, 'tblblood_requests', $column)) {
            $insertColumns[] = $column;
        }
    }

    $evenMoreColumns = [
        'patient_ref_no',
        'ward',
        'blood_type',
        'component',
        'units_requested',
        'urgency',
        'diagnosis',
        'reason_for_transfusion',
        'date_time_required',
        'status',
    ];

    foreach ($evenMoreColumns as $column) {
        if ($tableHasColumn($pdo, 'tblblood_requests', $column)) {
            $insertColumns[] = $column;
        }
    }

    $placeholders = array_map(static fn($column) => ':' . $column, $insertColumns);
    $query = 'INSERT INTO tblblood_requests (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($query);

    $params = [
        ':request_code' => $requestId,
        ':doctor_user_id' => $doctorUserId,
        ':doctor_name' => $doctorDisplayName !== '' ? $doctorDisplayName : $doctorName,
        ':hospital_name' => $hospitalName,
        ':patient_name' => $patientName,
    ];

    foreach ($optionalPatientFields as $column => $value) {
        if (in_array($column, $insertColumns, true)) {
            $params[':' . $column] = $value;
        }
    }

    $params[':patient_ref_no'] = $patientRefNo !== '' ? $patientRefNo : null;
    $params[':ward'] = $ward !== '' ? $ward : null;
    $params[':blood_type'] = $bloodType !== '' ? $bloodType : null;
    $params[':component'] = $component;
    $params[':units_requested'] = $units;
    $params[':urgency'] = $urgency;
    $params[':diagnosis'] = $diagnosis !== '' ? $diagnosis : null;
    $params[':reason_for_transfusion'] = $reason !== '' ? $reason : null;
    $params[':date_time_required'] = $dateRequired;
    $params[':status'] = 'Pending';

    $stmt->execute($params);

    $requestRowId = (int)$pdo->lastInsertId();
    bts_log_request_status_change(
        $pdo,
        $requestRowId,
        null,
        'Pending',
        'create',
        $doctorUserId > 0 ? $doctorUserId : null,
        'Request submitted by doctor.'
    );

    try {
        $result = $pdo->query("SHOW TABLES LIKE 'tblnotifications'");
        if ($result && $result->rowCount() > 0) {
            $noteStmt = $pdo->prepare(
                'INSERT INTO tblnotifications (user_id, role_target, request_id, title, message, severity, channel)
                 VALUES (:user_id, :role_target, :request_id, :title, :message, :severity, :channel)'
            );
            $noteStmt->execute([
                ':user_id' => null,
                ':role_target' => 'staff',
                ':request_id' => $requestRowId,
                ':title' => 'New Blood Request Submitted',
                ':message' => sprintf('Request %s for %s requires %d unit(s) %s.', $requestId, $patientName, $units, $component),
                ':severity' => 'info',
                ':channel' => 'in_app',
            ]);
        }
    } catch (Throwable $ignoreNotificationsFailure) {
        // Keep request creation successful even when notifications table is unavailable.
    }

    $availability = null;
    if ($bloodType !== '') {
        $componentColumn = 'whole_units';
        if ($component === 'Packed Red Cells') {
            $componentColumn = 'prbc_units';
        } elseif ($component === 'Platelets') {
            $componentColumn = 'platelets_units';
        } elseif ($component === 'Plasma') {
            $componentColumn = 'ffp_units';
        }

        $availabilityStmt = $pdo->prepare(
            "SELECT
                IFNULL(SUM({$componentColumn}), 0) AS available_component_units,
                IFNULL(SUM(whole_units + prbc_units + platelets_units + ffp_units), 0) AS total_available_units
             FROM tblinventory
             WHERE blood_type = ?"
        );
        $availabilityStmt->execute([$bloodType]);
        $availabilityRow = $availabilityStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $availableComponentUnits = (int)($availabilityRow['available_component_units'] ?? 0);
        $totalAvailableUnits = (int)($availabilityRow['total_available_units'] ?? 0);
        $availability = [
            'bloodType' => $bloodType,
            'component' => $component,
            'requestedUnits' => $units,
            'availableComponentUnits' => $availableComponentUnits,
            'totalAvailableUnits' => $totalAvailableUnits,
            'isSufficient' => $availableComponentUnits >= $units,
            'isLowStock' => $availableComponentUnits > 0 && $availableComponentUnits <= 5,
        ];

        if ($availableComponentUnits < $units) {
            try {
                $noteStmt = $pdo->prepare(
                    'INSERT INTO tblnotifications (user_id, role_target, request_id, title, message, severity, channel)
                     VALUES (:user_id, :role_target, :request_id, :title, :message, :severity, :channel)'
                );
                $noteStmt->execute([
                    ':user_id' => null,
                    ':role_target' => 'staff',
                    ':request_id' => $requestRowId,
                    ':title' => 'Insufficient Stock vs Request',
                    ':message' => sprintf('Request %s needs %d unit(s) %s (%s), but only %d available.', $requestId, $units, $component, $bloodType, $availableComponentUnits),
                    ':severity' => 'warning',
                    ':channel' => 'in_app',
                ]);
            } catch (Throwable $ignoreNotificationsFailure) {
                // Keep request creation successful even when notifications table is unavailable.
            }
        }
    }

    $pdo->commit();

    $recipientEmail = $notificationEmail !== '' ? $notificationEmail : bts_get_default_test_email();
    $emailMeta = [];
    $emailSent = false;
    $sendEmail = false;

    if ($sendEmail) {
        $bloodTypeLabel = $bloodType !== '' ? $bloodType : 'Not specified';
        $doctorLabel = $doctorDisplayName !== '' ? $doctorDisplayName : $doctorName;

        $subject = sprintf('[Blood Request] %s | %s', $requestId, $hospitalName);
        $html =
            '<h2>New Blood Request Submitted</h2>' .
            '<p><strong>Request Code:</strong> ' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Hospital:</strong> ' . htmlspecialchars($hospitalName, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Patient:</strong> ' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Blood Type:</strong> ' . htmlspecialchars($bloodTypeLabel, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Component:</strong> ' . htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Units:</strong> ' . (int)$units . '</p>' .
            '<p><strong>Urgency:</strong> ' . htmlspecialchars($urgency, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Date Required:</strong> ' . htmlspecialchars($dateRequired, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Requested By:</strong> ' . htmlspecialchars($doctorLabel, ENT_QUOTES, 'UTF-8') . '</p>';

        $text =
            "New Blood Request Submitted\n" .
            "Request Code: {$requestId}\n" .
            "Hospital: {$hospitalName}\n" .
            "Patient: {$patientName}\n" .
            "Blood Type: {$bloodTypeLabel}\n" .
            "Component: {$component}\n" .
            "Units: {$units}\n" .
            "Urgency: {$urgency}\n" .
            "Date Required: {$dateRequired}\n" .
            "Requested By: {$doctorLabel}\n";

        $emailSent = bts_send_email($recipientEmail, $subject, $html, $text, $emailMeta);
    }

    $message = 'Blood request submitted successfully.';
    if ($sendEmail && !$emailSent) {
        $message .= ' Request was saved, but email could not be sent.';
    } elseif ($sendEmail && $emailSent) {
        $message .= ' Request saved and email sent.';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $requestRowId,
        'requestCode' => $requestId,
        'email' => [
            'sent' => $sendEmail ? $emailSent : false,
            'enabled' => $sendEmail,
            'recipient' => $sendEmail ? $recipientEmail : null,
            'transport' => $sendEmail ? ($emailMeta['transport'] ?? null) : null,
            'phpmailerAvailable' => $sendEmail ? ($emailMeta['phpmailerAvailable'] ?? null) : null,
            'smtpConfigured' => $sendEmail ? ($emailMeta['smtpConfigured'] ?? null) : null,
            'error' => $sendEmail ? ($emailMeta['error'] ?? null) : null,
        ],
        'availability' => $availability,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
