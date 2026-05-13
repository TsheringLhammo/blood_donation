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
require_once __DIR__ . '/../config/request_workflow.php';
$mailerPath = __DIR__ . '/../config/mailer.php';
if (file_exists($mailerPath)) {
    require_once $mailerPath;
}
if (!function_exists('bts_send_email')) {
    function bts_send_email(): bool
    {
        return false;
    }
}

$claims = bts_require_auth(['staff', 'admin']);
$staffUserId = (int)($claims['sub'] ?? 0);
$staffName = trim((string)($claims['email'] ?? 'Staff'));

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$requestId = (int)($data['requestId'] ?? 0);
$bloodBankId = max(1, (int)($data['bloodBankId'] ?? 1));
$isEmergency = (bool)($data['isEmergency'] ?? false);
$staffComment = trim((string)($data['staffComment'] ?? ''));
$compatibleUnitId = trim((string)($data['compatibleUnitId'] ?? ''));
$staffConfirmedBy = trim((string)($data['staffConfirmedBy'] ?? ''));

function resolve_issue_unit_identifier_column(PDO $pdo): ?string
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tblblood_units LIKE 'unit_id'");
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return 'unit_id';
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM tblblood_units LIKE 'donation_id'");
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return 'donation_id';
        }
    } catch (Throwable $ignored) {
        return null;
    }

    return null;
}

function issue_has_column(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }

    function issue_resolve_expired_status(PDO $pdo): string
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM tblblood_units LIKE 'status'");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$row || !isset($row['Type'])) {
                return 'Expired';
            }

            if (preg_match('/^enum\((.*)\)$/i', (string)$row['Type'], $matches) !== 1) {
                return 'Expired';
            }

            $values = array_map(static fn(string $value): string => trim(trim($value), "'\""), explode(',', (string)$matches[1]));
            foreach (['Expired', 'Rejected'] as $candidate) {
                if (in_array($candidate, $values, true)) {
                    return $candidate;
                }
            }
        } catch (Throwable $exception) {
            return 'Expired';
        }

        return 'Expired';
    }
}

if ($requestId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'requestId is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $bankStmt = $pdo->prepare('SELECT id, name FROM tblblood_banks WHERE id = ? LIMIT 1');
    $bankStmt->execute([$bloodBankId]);
    $bank = $bankStmt->fetch(PDO::FETCH_ASSOC);
    if (!$bank) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid blood bank id.']);
        exit;
    }
    $bloodBankName = trim((string)($bank['name'] ?? ('Bank #' . $bloodBankId)));

    $requestStmt = $pdo->prepare('SELECT id, request_code, doctor_user_id, doctor_name, patient_name, blood_type, component, units_requested, urgency, status FROM tblblood_requests WHERE id = ? FOR UPDATE');
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $requestCode = trim((string)($request['request_code'] ?? ('REQ-' . $requestId)));
    $doctorUserId = isset($request['doctor_user_id']) ? (int)$request['doctor_user_id'] : null;
    $doctorName = trim((string)($request['doctor_name'] ?? 'Doctor'));
    $patientName = trim((string)($request['patient_name'] ?? 'Unknown'));
    $bloodType = trim((string)($request['blood_type'] ?? ''));
    $units = (int)($request['units_requested'] ?? 0);
    $component = trim((string)($request['component'] ?? ''));
    $status = trim((string)($request['status'] ?? 'Pending'));
    $statusNormalized = bts_normalize_request_status($status);
    $urgency = trim((string)($request['urgency'] ?? 'Routine'));

    if ($statusNormalized === 'issued') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This request was already issued.']);
        exit;
    }

    // In emergency mode, allow issuance from certain statuses; otherwise require 'matched'
    if (!$isEmergency && $statusNormalized !== 'matched') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Request must be in Matched status before issuing blood.']);
        exit;
    } elseif ($isEmergency && !in_array($statusNormalized, ['matched', 'cross-matching', 'approved'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'message' => 'Even in emergency mode, request must be in Approved stage or beyond. Cannot issue from Pending status.',
        ]);
        exit;
    }

    if ($bloodType === '' || $units <= 0) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Request is missing blood type or units requested.']);
        exit;
    }

    if ($staffConfirmedBy === '') {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Issuing staff confirmation is required.']);
        exit;
    }

    // For normal issuance, require a linked compatible unit and issue that exact unit.
    if (!$isEmergency && $compatibleUnitId === '') {
        $lastCompatibleStmt = $pdo->prepare(
            'SELECT donor_unit_refs
             FROM tbllab_logs
             WHERE request_id = ?
               AND result = "Compatible"
               AND donor_unit_refs IS NOT NULL
               AND TRIM(donor_unit_refs) <> ""
             ORDER BY id DESC
             LIMIT 1'
        );
        $lastCompatibleStmt->execute([$requestId]);
        $compatibleUnitId = trim((string)$lastCompatibleStmt->fetchColumn());
    }

    if (!$isEmergency && $compatibleUnitId === '') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'No compatible cross-match unit linked to this request. Record a compatible result with unit ID first.']);
        exit;
    }

    $column = 'whole_units';
    $componentNormalized = strtolower(trim($component));
    if (in_array($componentNormalized, ['packed red cells', 'prbc'], true)) {
        $column = 'prbc_units';
    } elseif ($componentNormalized === 'platelets') {
        $column = 'platelets_units';
    } elseif (in_array($componentNormalized, ['plasma', 'ffp', 'fresh frozen plasma'], true)) {
        $column = 'ffp_units';
    }

    $issuedUnitRefs = [];
    $unitTableExists = false;
    $unitTableCheck = $pdo->query("SHOW TABLES LIKE 'tblblood_units'");
    if ($unitTableCheck && $unitTableCheck->rowCount() > 0) {
        $unitTableExists = true;
    }

    if ($unitTableExists) {
        $unitIdentifierColumn = resolve_issue_unit_identifier_column($pdo);
        if ($unitIdentifierColumn === null) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'tblblood_units must contain unit_id or donation_id column.']);
            exit;
        }

        $hasDonorIdColumn = issue_has_column($pdo, 'tblblood_units', 'donor_id');

        if (!issue_has_column($pdo, 'tblblood_units', 'donation_id')) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Cannot issue - donation linkage is missing on tblblood_units (donation_id column required).']);
            exit;
        }

        if (!$isEmergency && $compatibleUnitId !== '') {
            $donorIdSelect = $hasDonorIdColumn ? 'donor_id' : 'NULL AS donor_id';
            $donorSampleSelect = $hasDonorIdColumn
                ? ', (
                            SELECT LOWER(TRIM(COALESCE(d.sample_tested, "pending")))
                            FROM tbldonors d
                            WHERE d.id = tblblood_units.donor_id
                            LIMIT 1
                        ) AS donor_sample_tested'
                : ', NULL AS donor_sample_tested';
            $linkedUnitStmt = $pdo->prepare(
                'SELECT id, ' . $unitIdentifierColumn . ' AS unit_ref, donation_id, ' . $donorIdSelect . ', blood_type, component, expiry_date, status, request_id,
                        (
                            SELECT tt.final_result
                            FROM tbldonation_tests tt
                            WHERE CAST(tt.donation_id AS CHAR) = CAST(tblblood_units.donation_id AS CHAR)
                            ORDER BY tt.id DESC
                            LIMIT 1
                        ) AS donation_test_final_result,
                        ' . ltrim($donorSampleSelect, ', ') . '
                 FROM tblblood_units
                 WHERE ' . $unitIdentifierColumn . ' = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $linkedUnitStmt->execute([$compatibleUnitId]);
            $linkedUnit = $linkedUnitStmt->fetch(PDO::FETCH_ASSOC);

            if (!$linkedUnit) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Linked compatible unit not found.']);
                exit;
            }

            $linkedUnitStatus = trim((string)($linkedUnit['status'] ?? ''));
            $linkedUnitRequestId = (int)($linkedUnit['request_id'] ?? 0);
            $linkedExpiryDate = trim((string)($linkedUnit['expiry_date'] ?? ''));
            $linkedIsExpired = $linkedExpiryDate !== '' && strtotime($linkedExpiryDate . ' 23:59:59') < time();
            if (!in_array($linkedUnitStatus, ['Available', 'Reserved'], true)) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => sprintf('Linked compatible unit %s is %s and cannot be issued.', $compatibleUnitId, $linkedUnitStatus)]);
                exit;
            }
            if ($linkedIsExpired) {
                $expiredStatus = issue_resolve_expired_status($pdo);
                $expireUnitStmt = $pdo->prepare("UPDATE tblblood_units SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $expireUnitStmt->execute([$expiredStatus, (int)$linkedUnit['id']]);
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Cannot issue - blood unit has expired.']);
                exit;
            }
            if ($linkedUnitStatus === 'Reserved' && $linkedUnitRequestId > 0 && $linkedUnitRequestId !== $requestId) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Linked compatible unit is reserved for a different request.']);
                exit;
            }

            if (strcasecmp($bloodType, trim((string)($linkedUnit['blood_type'] ?? ''))) !== 0) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Linked compatible unit blood type does not match request blood type.']);
                exit;
            }

            $linkedDonationId = trim((string)($linkedUnit['donation_id'] ?? ''));
            $linkedTestResult = trim((string)($linkedUnit['donation_test_final_result'] ?? ''));
            $linkedDonorSample = strtolower(trim((string)($linkedUnit['donor_sample_tested'] ?? 'pending')));
            $hasLegacySafeResult = $linkedDonationId !== '' && in_array($linkedTestResult, ['Safe', 'Eligible'], true);
            $hasNegativeSample = $hasDonorIdColumn && (int)($linkedUnit['donor_id'] ?? 0) > 0 && $linkedDonorSample === 'negative';
            if (!$hasLegacySafeResult && !$hasNegativeSample) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Cannot issue - donor sample result must be negative or the legacy donation test must be Safe/Eligible.']);
                exit;
            }

            $issueLinkedUnitStmt = $pdo->prepare(
                "UPDATE tblblood_units
                 SET status = 'Issued', request_id = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $issueLinkedUnitStmt->execute([$requestId, (int)$linkedUnit['id']]);
            $issuedUnitRefs = [(string)$linkedUnit['unit_ref']];
            $units = 1;
        }

        $componentOptions = ['Whole Blood'];
        if ($column === 'prbc_units') {
            $componentOptions = ['Packed Red Cells', 'PRBC'];
        } elseif ($column === 'platelets_units') {
            $componentOptions = ['Platelets'];
        } elseif ($column === 'ffp_units') {
            $componentOptions = ['FFP', 'Plasma', 'Fresh Frozen Plasma'];
        }

        if ($isEmergency || $compatibleUnitId === '') {
            $placeholders = implode(',', array_fill(0, count($componentOptions), '?'));
            $sampleCondition = '';
            if ($hasDonorIdColumn) {
                $sampleCondition = "
                            OR (
                                donor_id IS NOT NULL
                                AND donor_id > 0
                                AND (
                                    SELECT LOWER(TRIM(COALESCE(d2.sample_tested, 'pending')))
                                    FROM tbldonors d2
                                    WHERE d2.id = tblblood_units.donor_id
                                    LIMIT 1
                                ) = 'negative'
                            )";
            }
            $unitSql = "SELECT id, {$unitIdentifierColumn} AS unit_ref
                        FROM tblblood_units
                        WHERE blood_bank_id = ?
                          AND blood_type = ?
                          AND component IN ($placeholders)
                          AND LOWER(status) = 'available'
                          AND (
                                (
                                    donation_id IS NOT NULL
                                    AND TRIM(CAST(donation_id AS CHAR)) <> ''
                                    AND (
                                        SELECT tt.final_result
                                        FROM tbldonation_tests tt
                                        WHERE CAST(tt.donation_id AS CHAR) = CAST(tblblood_units.donation_id AS CHAR)
                                        ORDER BY tt.id DESC
                                        LIMIT 1
                                    ) IN ('Safe', 'Eligible')
                                ){$sampleCondition}
                          )
                          AND expiry_date >= CURDATE()
                        ORDER BY expiry_date ASC, id ASC
                        LIMIT $units
                        FOR UPDATE";
            $unitParams = array_merge([$bloodBankId, $bloodType], $componentOptions);
            $unitStmt = $pdo->prepare($unitSql);
            $unitStmt->execute($unitParams);
            $candidateUnits = $unitStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (count($candidateUnits) < $units) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Insufficient available unit-level bags for this request.',
                    'requiredUnits' => $units,
                    'availableUnits' => count($candidateUnits),
                ]);
                exit;
            }

            $unitIds = array_map(static fn(array $row): int => (int)$row['id'], $candidateUnits);
            $issuedUnitRefs = array_map(static fn(array $row): string => (string)$row['unit_ref'], $candidateUnits);
            $unitIdPlaceholders = implode(',', array_fill(0, count($unitIds), '?'));
            $issueUnitsStmt = $pdo->prepare(
                "UPDATE tblblood_units
                 SET status = 'Issued', request_id = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id IN ($unitIdPlaceholders)"
            );
            $issueUnitsStmt->execute(array_merge([$requestId], $unitIds));
        }
    }

    $inventoryStmt = $pdo->prepare("SELECT {$column} AS available_units FROM tblinventory WHERE blood_bank_id = ? AND blood_type = ? FOR UPDATE");
    $inventoryStmt->execute([$bloodBankId, $bloodType]);
    $inventory = $inventoryStmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Inventory row not found for selected blood type.']);
        exit;
    }

    $availableUnits = (int)($inventory['available_units'] ?? 0);
    if ($availableUnits < $units) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient inventory units for this blood type/component.',
            'requiredUnits' => $units,
            'availableUnits' => $availableUnits,
        ]);
        exit;
    }

    $stockStmt = $pdo->prepare("UPDATE tblinventory SET {$column} = {$column} - :units, updated_at = CURRENT_TIMESTAMP WHERE blood_bank_id = :blood_bank_id AND blood_type = :blood_type");
    $stockStmt->execute([':units' => $units, ':blood_bank_id' => $bloodBankId, ':blood_type' => $bloodType]);

    $updateStmt = $pdo->prepare('UPDATE tblblood_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $updateStmt->execute(['Issued', $requestId]);

    bts_log_request_status_change(
        $pdo,
        $requestId,
        $status,
        'Issued',
        'issue',
        $staffUserId > 0 ? $staffUserId : null,
        'Blood issued after stock validation.'
    );

    $hasIssuedUnitColumn = false;
    $issuedUnitColumnCheck = $pdo->prepare("SHOW COLUMNS FROM tblissue_logs LIKE 'issued_unit_id'");
    $issuedUnitColumnCheck->execute();
    if ($issuedUnitColumnCheck->fetch(PDO::FETCH_ASSOC)) {
        $hasIssuedUnitColumn = true;
    }

    $issueLogColumns = 'request_id, request_code, patient_name, blood_type, component, units_issued, staff_user_id, staff_name, notes';
    $issueLogValues = ':request_id, :request_code, :patient_name, :blood_type, :component, :units_issued, :staff_user_id, :staff_name, :notes';
    if ($hasIssuedUnitColumn) {
        $issueLogColumns .= ', issued_unit_id';
        $issueLogValues .= ', :issued_unit_id';
    }

    $issueLogStmt = $pdo->prepare(
        'INSERT INTO tblissue_logs (' . $issueLogColumns . ') VALUES (' . $issueLogValues . ')'
    );
    
    $notesParts = [];
    if ($isEmergency) {
        $notesParts[] = '🚨 EMERGENCY ISSUANCE';
    } else {
        $notesParts[] = 'Issued after compatible cross-match verification';
    }
    
    if ($issuedUnitRefs) {
        $notesParts[] = 'Units: ' . implode(', ', $issuedUnitRefs);
    }
    
    if ($staffComment !== '') {
        $notesParts[] = 'Staff Comment: ' . $staffComment;
    }
    $notesParts[] = 'Confirmed By: ' . $staffConfirmedBy;
    
    $issueLogPayload = [
        ':request_id' => $requestId,
        ':request_code' => $requestCode,
        ':patient_name' => $patientName,
        ':blood_type' => $bloodType,
        ':component' => $component,
        ':units_issued' => $units,
        ':staff_user_id' => $staffUserId > 0 ? $staffUserId : null,
        ':staff_name' => $staffName,
        ':notes' => implode('. ', $notesParts),
    ];
    if ($hasIssuedUnitColumn) {
        $issueLogPayload[':issued_unit_id'] = $issuedUnitRefs[0] ?? null;
    }
    $issueLogStmt->execute($issueLogPayload);

    $remainingUnits = max(0, $availableUnits - $units);

    $ledgerStmt = $pdo->prepare(
        'INSERT INTO tblstock_ledger
        (blood_bank_id, blood_type, component, movement_type, units, reference_type, reference_id, before_units, after_units, actor_user_id, notes)
         VALUES
        (:blood_bank_id, :blood_type, :component, :movement_type, :units, :reference_type, :reference_id, :before_units, :after_units, :actor_user_id, :notes)'
    );
    $ledgerStmt->execute([
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
        ':component' => $component,
        ':movement_type' => 'OUT',
        ':units' => $units,
        ':reference_type' => 'ISSUE',
        ':reference_id' => $requestId,
        ':before_units' => $availableUnits,
        ':after_units' => $remainingUnits,
        ':actor_user_id' => $staffUserId > 0 ? $staffUserId : null,
        ':notes' => 'Issued to request ' . $requestCode,
    ]);

    $notificationStmt = $pdo->prepare(
        'INSERT INTO tblnotifications (user_id, role_target, request_id, title, message, severity, channel)
         VALUES (:user_id, :role_target, :request_id, :title, :message, :severity, :channel)'
    );

    $notificationTitle = $isEmergency ? '🚨 Blood Issued (Emergency)' : 'Blood Issued';
    $notificationSeverity = $isEmergency ? 'critical' : 'success';

    $notificationStmt->execute([
        ':user_id' => $doctorUserId !== null && $doctorUserId > 0 ? $doctorUserId : null,
        ':role_target' => $doctorUserId !== null && $doctorUserId > 0 ? null : 'doctor',
        ':request_id' => $requestId,
        ':title' => $notificationTitle,
        ':message' => sprintf('Request %s for %s has been issued: %d unit(s) %s (%s).%s', 
            $requestCode, 
            $patientName, 
            $units, 
            $component, 
            $bloodType,
            $isEmergency ? ' [Emergency mode - partial validations skipped]' : ''
        ),
        ':severity' => $notificationSeverity,
        ':channel' => 'in_app',
    ]);

    if ($isEmergency) {
        // Log emergency issuance to all admins
        $notificationStmt->execute([
            ':user_id' => null,
            ':role_target' => 'admin',
            ':request_id' => $requestId,
            ':title' => '⚠️ Emergency Blood Issuance Alert',
            ':message' => sprintf('Emergency blood issuance by %s: Request %s (%d units %s). Staff note: %s', 
                $staffName, 
                $requestCode, 
                $units, 
                $bloodType,
                $staffComment !== '' ? $staffComment : '(no additional comment)'
            ),
            ':severity' => 'critical',
            ':channel' => 'in_app',
        ]);
    }

    if ($remainingUnits < 5) {
        $notificationStmt->execute([
            ':user_id' => null,
            ':role_target' => 'staff',
            ':request_id' => $requestId,
            ':title' => 'Low Stock Warning',
            ':message' => sprintf('%s %s stock at %s is low: %d unit(s) remaining.', $bloodType, $component, $bloodBankName, $remainingUnits),
            ':severity' => 'warning',
            ':channel' => 'in_app',
        ]);
    }

    if (strcasecmp($urgency, 'Critical') === 0) {
        $notificationStmt->execute([
            ':user_id' => null,
            ':role_target' => 'staff',
            ':request_id' => $requestId,
            ':title' => 'Critical Request Closed',
            ':message' => sprintf('Critical request %s for %s was issued successfully by %s.', $requestCode, $patientName, $staffName),
            ':severity' => 'critical',
            ':channel' => 'in_app',
        ]);
    }

    $pdo->commit();

    $emailMeta = [
        'attempted' => false,
        'sent' => false,
        'recipient' => null,
        'error' => null,
    ];

    try {
        if ($doctorUserId !== null && $doctorUserId > 0) {
            $doctorStmt = $pdo->prepare('SELECT email, name FROM tblusers WHERE id = ? LIMIT 1');
            $doctorStmt->execute([$doctorUserId]);
            $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
            $doctorEmail = trim((string)($doctor['email'] ?? ''));
            $doctorDisplayName = trim((string)($doctor['name'] ?? $doctorName));

            if ($doctorEmail !== '') {
                $emailMeta['attempted'] = true;
                $emailMeta['recipient'] = $doctorEmail;

                $unitsLabel = implode(', ', $issuedUnitRefs);
                if ($unitsLabel === '') {
                    $unitsLabel = 'Auto-selected stock units';
                }

                $subject = sprintf('Blood ready for issue: %s', $requestCode);
                $htmlBody = '<p>Dear ' . htmlspecialchars($doctorDisplayName, ENT_QUOTES, 'UTF-8') . ',</p>'
                    . '<p>Your blood request <strong>' . htmlspecialchars($requestCode, ENT_QUOTES, 'UTF-8') . '</strong> has been issued and is ready.</p>'
                    . '<p>Patient: ' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'Blood Type: ' . htmlspecialchars($bloodType, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'Component: ' . htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'Units: ' . (int)$units . '<br>'
                    . 'Unit References: ' . htmlspecialchars($unitsLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'Issued By: ' . htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p>Please coordinate transfusion handling as per protocol.</p>';
                $textBody = "Blood request issued and ready\n"
                    . "Request: {$requestCode}\n"
                    . "Patient: {$patientName}\n"
                    . "Blood Type: {$bloodType}\n"
                    . "Component: {$component}\n"
                    . "Units: {$units}\n"
                    . "Unit References: {$unitsLabel}\n"
                    . "Issued By: {$staffName}\n";

                $mailMeta = [];
                $emailMeta['sent'] = bts_send_email($doctorEmail, $subject, $htmlBody, $textBody, $mailMeta);
                $emailMeta['error'] = $mailMeta['error'] ?? null;
            }
        }
    } catch (Throwable $mailException) {
        $emailMeta['error'] = $mailException->getMessage();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Blood units issued successfully.',
        'data' => [
            'requestId' => $requestId,
            'requestCode' => $requestCode,
            'patientName' => $patientName,
            'doctorName' => $doctorName,
            'bloodBankId' => $bloodBankId,
            'bloodBankName' => $bloodBankName,
            'bloodType' => $bloodType,
            'component' => $component,
            'unitsIssued' => $units,
            'issuedBy' => $staffName,
            'remainingUnits' => $remainingUnits,
            'issuedUnitRefs' => $issuedUnitRefs,
        ],
        'email' => $emailMeta,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
