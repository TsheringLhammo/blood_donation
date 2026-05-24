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

$claims = bts_require_auth(['staff', 'admin']);

function blood_unit_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
}

function blood_unit_table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
}

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donationId = trim((string)($data['donationId'] ?? ''));
$donationHistoryId = max(0, (int)($data['donationHistoryId'] ?? 0));
$donorId = (int)($data['donorId'] ?? 0);
$bloodType = trim((string)($data['bloodType'] ?? ''));
$component = trim((string)($data['component'] ?? 'Packed Red Cells'));
$expiryDate = trim((string)($data['expiryDate'] ?? ''));
$addMode = strtolower(trim((string)($data['addMode'] ?? 'local')));
$transferFromBankId = max(0, (int)($data['transferFromBankId'] ?? 0));
$transferReference = trim((string)($data['transferReference'] ?? ''));
$transferDate = trim((string)($data['transferDate'] ?? ''));
$transportMethod = trim((string)($data['transportMethod'] ?? 'Ambulance'));
$transferEmail = trim((string)($data['transferEmail'] ?? ''));
$notifySendingBank = !empty($data['notifySendingBank']);
$notifyReceivingBank = !empty($data['notifyReceivingBank']);
$notifyDoctor = !empty($data['notifyDoctor']);
$notifyDriver = !empty($data['notifyDriver']);
$status = 'Available';
$componentRowsInput = $data['components'] ?? $data['componentRows'] ?? null;
$componentRows = [];

if (is_array($componentRowsInput) && !empty($componentRowsInput)) {
    foreach ($componentRowsInput as $rowInput) {
        if (!is_array($rowInput)) {
            continue;
        }

        $componentRows[] = [
            'component' => trim((string)($rowInput['component'] ?? $component)),
            'quantity' => max(1, (int)($rowInput['quantity'] ?? 1)),
            'expiryDate' => trim((string)($rowInput['expiryDate'] ?? $expiryDate)),
        ];
    }
} else {
    $componentRows[] = [
        'component' => $component,
        'quantity' => max(1, (int)($data['quantity'] ?? 1)),
        'expiryDate' => $expiryDate,
    ];
}

// Validation

if ($addMode === 'transfer') {
    if ($transferFromBankId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'transferFromBankId is required for transfer mode.']);
        exit;
    }
    if ($transferReference === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Transfer reference is required for transfer mode.']);
        exit;
    }
    if ($transferDate === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Transfer date is required for transfer mode.']);
        exit;
    }

    if ($transferEmail !== '' && filter_var($transferEmail, FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Optional transfer email is invalid.']);
        exit;
    }
} elseif ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donorId is required for full blood unit creation.']);
    exit;
}

if ($addMode !== 'transfer' && $donationHistoryId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A completed donation record is required. Please complete an appointment first.']);
    exit;
}

if (!$expiryDate) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Expiry date is required.']);
    exit;
}

$allowedBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (!in_array($bloodType, $allowedBloodTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid blood type.']);
    exit;
}

$allowedComponents = ['Whole Blood', 'Packed Red Cells', 'Platelets', 'Plasma'];
foreach ($componentRows as $row) {
    if (!in_array($row['component'], $allowedComponents, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid component type.']);
        exit;
    }
    if ($row['quantity'] < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1 for every component row.']);
        exit;
    }
    if ($row['expiryDate'] === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Expiry date is required for every component row.']);
        exit;
    }
}


if ($status !== 'Available') {
    $status = 'Available';
}

// Default blood bank to 1 (primary bank)
$bloodBankId = (int)($data['bloodBankId'] ?? 1);

try {
    $pdo->beginTransaction();

    // Verify blood bank exists
    $bankStmt = $pdo->prepare('SELECT id FROM tblblood_banks WHERE id = ? LIMIT 1');
    $bankStmt->execute([$bloodBankId]);
    if (!$bankStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid blood bank id.']);
        exit;
    }

    $unitDonationLinkId = null;
    if ($addMode === 'transfer') {
        $unitDonationLinkId = $transferReference;
    } else {
        if (!blood_unit_table_exists($pdo, 'donation_history')) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'No completed donations found. Please complete an appointment first.']);
            exit;
        }

        $hasDhDonorId = blood_unit_column_exists($pdo, 'donation_history', 'donor_id');
        $hasDhStatus = blood_unit_column_exists($pdo, 'donation_history', 'status');
        $hasDhDonationId = blood_unit_column_exists($pdo, 'donation_history', 'donation_id');
        $hasDhBloodType = blood_unit_column_exists($pdo, 'donation_history', 'blood_type');

        if (!$hasDhStatus) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Completed donation records are not available.']);
            exit;
        }

        $selectCols = ['id', 'status'];
        if ($hasDhDonorId) {
            $selectCols[] = 'donor_id';
        }
        if ($hasDhDonationId) {
            $selectCols[] = 'donation_id';
        }
        if ($hasDhBloodType) {
            $selectCols[] = 'blood_type';
        }

        $dhStmt = $pdo->prepare('SELECT ' . implode(', ', $selectCols) . ' FROM donation_history WHERE id = ? LIMIT 1 FOR UPDATE');
        $dhStmt->execute([$donationHistoryId]);
        $dhRow = $dhStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dhRow) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'No completed donations found. Please complete an appointment first.']);
            exit;
        }

        $historyStatus = strtolower(trim((string)($dhRow['status'] ?? '')));
        if ($historyStatus !== 'completed') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Only completed donations can be used to add blood units.']);
            exit;
        }

        if ($hasDhDonorId) {
            $donorIdFromHistory = (int)($dhRow['donor_id'] ?? 0);
            if ($donorIdFromHistory > 0 && $donorId > 0 && $donorIdFromHistory !== $donorId) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Selected donor does not match the completed donation record.']);
                exit;
            }
            if ($donorIdFromHistory > 0) {
                $donorId = $donorIdFromHistory;
            }
        }

        if ($hasDhBloodType) {
            $historyBloodType = trim((string)($dhRow['blood_type'] ?? ''));
            if ($historyBloodType !== '') {
                $bloodType = $historyBloodType;
            }
        }

        $historyDonationId = $hasDhDonationId ? trim((string)($dhRow['donation_id'] ?? '')) : '';
        $unitDonationLinkId = $historyDonationId !== '' ? $historyDonationId : ('DH-' . (string)$donationHistoryId);

        // Keep submitted donation id aligned to the validated donation_history record.
        $donationId = $unitDonationLinkId;
    }

    if ($unitDonationLinkId === null || $unitDonationLinkId === '') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A valid completed donation record is required for blood unit traceability.']);
        exit;
    }

    if ($addMode !== 'transfer' && $donorId <= 0) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid donorId is required.']);
        exit;
    }

    if ($addMode !== 'transfer') {
        $donorStmt = $pdo->prepare('SELECT id, full_name, blood_type, status, sample_tested, sample_tested_at FROM tbldonors WHERE id = ? LIMIT 1 FOR UPDATE');
        $donorStmt->execute([$donorId]);
        $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$donorRow) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Donor not found.']);
            exit;
        }

        if (!empty($donorRow['blood_type'])) {
            $bloodType = trim((string)$donorRow['blood_type']);
        }
    }

    // Generate Unit_id in format U-YYYY-##### with sequential numbering
    $currentYear = date('Y');
    $yearPrefix = "U-{$currentYear}-";

    $latestStmt = $pdo->prepare(
        'SELECT unit_id FROM tblblood_units WHERE unit_id LIKE ? ORDER BY unit_id DESC LIMIT 1 FOR UPDATE'
    );
    $latestStmt->execute(["{$yearPrefix}%"]);
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);

    if ($latest) {
        $lastNumber = (int)substr($latest['unit_id'], -5);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    $hasDonorIdColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'donor_id');
    $hasTransferFromBankIdColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'transfer_from_bank_id');
    $hasTransferReferenceColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'transfer_reference');
    $hasTransferDateColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'transfer_date');
    $hasTransportMethodColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'transport_method');
    $hasTransferNotifyColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'transfer_notify_json');
    $hasCreatedAtColumn = blood_unit_column_exists($pdo, 'tblblood_units', 'created_at');

    $insertColumns = ['unit_id', 'donation_id', 'blood_bank_id', 'blood_type', 'component', 'expiry_date', 'status'];
    $insertValues = [':unit_id', ':donation_id', ':blood_bank_id', ':blood_type', ':component', ':expiry_date', ':status'];
    if ($hasDonorIdColumn) {
        $insertColumns[] = 'donor_id';
        $insertValues[] = ':donor_id';
    }
    if ($hasTransferFromBankIdColumn) {
        $insertColumns[] = 'transfer_from_bank_id';
        $insertValues[] = ':transfer_from_bank_id';
    }
    if ($hasTransferReferenceColumn) {
        $insertColumns[] = 'transfer_reference';
        $insertValues[] = ':transfer_reference';
    }
    if ($hasTransferDateColumn) {
        $insertColumns[] = 'transfer_date';
        $insertValues[] = ':transfer_date';
    }
    if ($hasTransportMethodColumn) {
        $insertColumns[] = 'transport_method';
        $insertValues[] = ':transport_method';
    }
    if ($hasTransferNotifyColumn) {
        $insertColumns[] = 'transfer_notify_json';
        $insertValues[] = ':transfer_notify_json';
    }
    if ($hasCreatedAtColumn) {
        $insertColumns[] = 'created_at';
        $insertValues[] = 'CURRENT_TIMESTAMP';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblblood_units (' . implode(', ', $insertColumns) . ')
         VALUES (' . implode(', ', $insertValues) . ')'
    );

    $inventoryStmt = $pdo->prepare(
        'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ffp_units)
         VALUES (:blood_bank_id, :blood_type, :whole, :prbc, :platelets, :ffp)
         ON DUPLICATE KEY UPDATE
           whole_units = whole_units + VALUES(whole_units),
           prbc_units = prbc_units + VALUES(prbc_units),
           platelets_units = platelets_units + VALUES(platelets_units),
           ffp_units = ffp_units + VALUES(ffp_units),
           updated_at = CURRENT_TIMESTAMP'
    );

    $createdUnits = [];
    $totalUnitsAdded = 0;

    foreach ($componentRows as $row) {
        for ($quantityIndex = 0; $quantityIndex < $row['quantity']; $quantityIndex++) {
            $unitId = $yearPrefix . str_pad((string)$nextNumber, 5, '0', STR_PAD_LEFT);
            $nextNumber++;

            $params = [
                ':unit_id' => $unitId,
                ':donation_id' => $unitDonationLinkId,
                ':blood_bank_id' => $bloodBankId,
                ':blood_type' => $bloodType,
                ':component' => $row['component'],
                ':expiry_date' => $row['expiryDate'],
                ':status' => $status,
            ];
            if ($hasDonorIdColumn) {
                $params[':donor_id'] = $addMode === 'transfer' ? null : $donorId;
            }
            if ($hasTransferFromBankIdColumn) {
                $params[':transfer_from_bank_id'] = $addMode === 'transfer' ? $transferFromBankId : null;
            }
            if ($hasTransferReferenceColumn) {
                $params[':transfer_reference'] = $addMode === 'transfer' ? $transferReference : null;
            }
            if ($hasTransferDateColumn) {
                $params[':transfer_date'] = $addMode === 'transfer' ? $transferDate : null;
            }
            if ($hasTransportMethodColumn) {
                $params[':transport_method'] = $addMode === 'transfer' ? $transportMethod : null;
            }
            if ($hasTransferNotifyColumn) {
                $params[':transfer_notify_json'] = $addMode === 'transfer'
                    ? json_encode([
                        'sending_bank' => $notifySendingBank,
                        'receiving_bank' => $notifyReceivingBank,
                        'doctor' => $notifyDoctor,
                        'driver' => $notifyDriver,
                    ], JSON_UNESCAPED_SLASHES)
                    : null;
            }

            $stmt->execute($params);

            $whole = ($row['component'] === 'Whole Blood') ? 1 : 0;
            $prbc = ($row['component'] === 'Packed Red Cells') ? 1 : 0;
            $platelets = ($row['component'] === 'Platelets') ? 1 : 0;
            $ffp = (in_array($row['component'], ['FFP', 'Plasma'], true)) ? 1 : 0;

            $inventoryStmt->execute([
                ':blood_bank_id' => $bloodBankId,
                ':blood_type' => $bloodType,
                ':whole' => $whole,
                ':prbc' => $prbc,
                ':platelets' => $platelets,
                ':ffp' => $ffp,
            ]);

            $createdUnits[] = $unitId;
            $totalUnitsAdded++;
        }
    }

    $pdo->commit();

    $transferEmailSent = false;
    $transferEmailMeta = [];
    if ($addMode === 'transfer' && $transferEmail !== '') {
        $mailSubject = 'Blood Transfer Recorded - ' . $bloodType;
        $mailHtml = '<p>A transfer blood unit has been recorded.</p>'
            . '<ul>'
            . '<li>Blood Type: ' . htmlspecialchars($bloodType, ENT_QUOTES, 'UTF-8') . '</li>'
            . '<li>Reference: ' . htmlspecialchars($transferReference, ENT_QUOTES, 'UTF-8') . '</li>'
            . '<li>Transfer Date: ' . htmlspecialchars($transferDate, ENT_QUOTES, 'UTF-8') . '</li>'
            . '<li>Transport Method: ' . htmlspecialchars($transportMethod, ENT_QUOTES, 'UTF-8') . '</li>'
            . '<li>Total Units: ' . htmlspecialchars((string)$totalUnitsAdded, ENT_QUOTES, 'UTF-8') . '</li>'
            . '<li>Sending Bank ID: ' . htmlspecialchars((string)$transferFromBankId, ENT_QUOTES, 'UTF-8') . '</li>'
            . '</ul>';
        $mailText = 'A transfer blood unit has been recorded. Blood Type: ' . $bloodType
            . ' | Reference: ' . $transferReference
            . ' | Transfer Date: ' . $transferDate
            . ' | Transport Method: ' . $transportMethod
            . ' | Total Units: ' . $totalUnitsAdded
            . ' | Sending Bank ID: ' . $transferFromBankId;
        $transferEmailSent = bts_send_email($transferEmail, $mailSubject, $mailHtml, $mailText, $transferEmailMeta);
    }

    echo json_encode([
        'success' => true,
        'message' => $totalUnitsAdded === 1 ? 'Blood unit added successfully.' : 'Blood units added successfully.',
        'unitId' => $createdUnits[0] ?? null,
        'generatedUnitId' => $createdUnits[0] ?? null,
        'createdUnits' => $createdUnits,
        'totalUnits' => $totalUnitsAdded,
        'donationId' => $unitDonationLinkId,
        'donationHistoryId' => $addMode === 'transfer' ? null : $donationHistoryId,
        'donorId' => $donorId,
        'components' => $componentRows,
        'transferEmailSent' => $transferEmailSent,
        'transferEmail' => $transferEmail !== '' ? $transferEmail : null,
        'transferEmailMeta' => $transferEmailMeta,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
?>
