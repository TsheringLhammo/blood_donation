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

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donationId = trim((string)($data['donationId'] ?? ''));
$donorId = (int)($data['donorId'] ?? 0);
$bloodType = trim((string)($data['bloodType'] ?? ''));
$component = trim((string)($data['component'] ?? 'Packed Red Cells'));
$expiryDate = trim((string)($data['expiryDate'] ?? ''));
$status = 'Available';

// Validation
if ($donationId !== '' && !ctype_digit($donationId)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Donation ID must be numeric if provided (for example: 54). Leave it blank to add a unit without linking a donation record.',
    ]);
    exit;
}

if ($donorId <= 0 && $donationId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donorId is required for full blood unit creation.']);
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

$allowedComponents = ['Whole Blood', 'Packed Red Cells', 'Platelets', 'FFP', 'Plasma', 'Cryoprecipitate'];
if (!in_array($component, $allowedComponents, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid component type.']);
    exit;
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

    $donationRecordId = null;
    if ($donationId !== '') {
        $donationRecordId = (int)$donationId;

        // Verify donation ID exists in tbldonations and fetch donor info
        $donationCheckStmt = $pdo->prepare('SELECT id, donor_id, blood_type AS donation_blood_type FROM tbldonations WHERE id = ? FOR UPDATE');
        $donationCheckStmt->execute([$donationRecordId]);
        $donationRow = $donationCheckStmt->fetch(PDO::FETCH_ASSOC);
        if (!$donationRow) {
            $donationRecordId = null;
        } else {
            // Check if donation_id already used for same component type
            $dupStmt = $pdo->prepare('SELECT id FROM tblblood_units WHERE donation_id = ? AND component = ? LIMIT 1');
            $dupStmt->execute([(string)$donationRecordId, $component]);
            if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'This donation has already been used to create a '.$component.' unit.']);
                exit;
            }

            // If donation has donor_id, fetch donor blood type and enforce it
            $donorIdFromDonation = (int)($donationRow['donor_id'] ?? 0);
            if ($donorIdFromDonation > 0) {
                $donorId = $donorIdFromDonation;
                $donorStmt = $pdo->prepare('SELECT blood_type FROM tbldonors WHERE id = ? LIMIT 1');
                $donorStmt->execute([$donorIdFromDonation]);
                $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
                if ($donorRow && !empty($donorRow['blood_type'])) {
                    // Override provided blood type with donor's registered blood type to maintain consistency
                    $bloodType = trim((string)$donorRow['blood_type']);
                }
            }
        }
    }

    if ($donorId <= 0) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid donorId is required.']);
        exit;
    }

    $donorStmt = $pdo->prepare('SELECT id, full_name, blood_type, status, sample_tested, sample_tested_at FROM tbldonors WHERE id = ? LIMIT 1 FOR UPDATE');
    $donorStmt->execute([$donorId]);
    $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
    if (!$donorRow) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found.']);
        exit;
    }

    $donorStatus = strtolower(trim((string)($donorRow['status'] ?? 'pending')));
    if (!in_array($donorStatus, ['confirmed', 'eligible', 'active'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only confirmed donors can create a blood unit.']);
        exit;
    }

    if (!empty($donorRow['blood_type'])) {
        $bloodType = trim((string)$donorRow['blood_type']);
    }

    // Generate Unit_id in format U-YYYY-##### with sequential numbering
    $currentYear = date('Y');
    $yearPrefix = "U-{$currentYear}-";
    
    // Get the latest unit_id for current year
    $latestStmt = $pdo->prepare(
        "SELECT unit_id FROM tblblood_units WHERE unit_id LIKE ? ORDER BY unit_id DESC LIMIT 1 FOR UPDATE"
    );
    $latestStmt->execute(["{$yearPrefix}%"]);
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
    
    // Extract sequence number and increment
    if ($latest) {
        // Extract last 5 digits from something like "U-2026-00054"
        $lastNumber = (int)substr($latest['unit_id'], -5);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    
    $unitId = $yearPrefix . str_pad((string)$nextNumber, 5, '0', STR_PAD_LEFT);
    
    // Check if unit_id already exists (should be rare, but safe)
    $unitIdCheckStmt = $pdo->prepare('SELECT id FROM tblblood_units WHERE unit_id = ? LIMIT 1');
    $unitIdCheckStmt->execute([$unitId]);
    if ($unitIdCheckStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to generate unique Unit ID. Please try again.']);
        exit;
    }

    // Create unit record with generated unit_id
    $insertColumns = ['unit_id', 'donation_id', 'blood_bank_id', 'blood_type', 'component', 'expiry_date', 'status'];
    $insertValues = [':unit_id', ':donation_id', ':blood_bank_id', ':blood_type', ':component', ':expiry_date', ':status'];
    if (blood_unit_column_exists($pdo, 'tblblood_units', 'donor_id')) {
        $insertColumns[] = 'donor_id';
        $insertValues[] = ':donor_id';
    }
    if (blood_unit_column_exists($pdo, 'tblblood_units', 'created_at')) {
        $insertColumns[] = 'created_at';
        $insertValues[] = 'CURRENT_TIMESTAMP';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblblood_units (' . implode(', ', $insertColumns) . ')
         VALUES (' . implode(', ', $insertValues) . ')'
    );

    $params = [
        ':unit_id' => $unitId,
        ':donation_id' => $donationRecordId,
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
        ':component' => $component,
        ':expiry_date' => $expiryDate,
        ':status' => $status,
    ];
    if (blood_unit_column_exists($pdo, 'tblblood_units', 'donor_id')) {
        $params[':donor_id'] = $donorId;
    }

    $stmt->execute($params);

    // Update aggregate inventory
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

    // Map component to inventory column. Count the unit immediately regardless of status.
    $whole = ($component === 'Whole Blood') ? 1 : 0;
    $prbc = ($component === 'Packed Red Cells') ? 1 : 0;
    $platelets = ($component === 'Platelets') ? 1 : 0;
    $ffp = (in_array($component, ['FFP', 'Plasma'], true)) ? 1 : 0;

    $inventoryStmt->execute([
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
        ':whole' => $whole,
        ':prbc' => $prbc,
        ':platelets' => $platelets,
        ':ffp' => $ffp,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Blood unit added successfully.',
        'unitId' => $unitId,
        'generatedUnitId' => $unitId,
        'donationId' => $donationRecordId,
        'donorId' => $donorId,
        'component' => $component,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
?>
