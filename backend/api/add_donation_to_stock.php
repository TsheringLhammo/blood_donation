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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['staff', 'admin']);
$actorUserId = (int)($claims['sub'] ?? 0);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donationId = (int)($data['donationId'] ?? 0);
if ($donationId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donationId is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $donationStmt = $pdo->prepare(
        'SELECT id, blood_bank_id, blood_type, component, units_collected, status
         FROM tbldonations
         WHERE id = ?
         FOR UPDATE'
    );
    $donationStmt->execute([$donationId]);
    $donation = $donationStmt->fetch(PDO::FETCH_ASSOC);

    if (!$donation) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donation not found.']);
        exit;
    }

    $status = trim((string)($donation['status'] ?? ''));
    if ($status !== 'Safe') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only Safe donations can be added to stock.']);
        exit;
    }

    $bloodBankId = (int)($donation['blood_bank_id'] ?? 0);
    $bloodType = trim((string)($donation['blood_type'] ?? ''));
    $component = trim((string)($donation['component'] ?? 'Whole Blood'));
    $units = max(1, (int)($donation['units_collected'] ?? 1));

    $column = 'whole_units';
    if ($component === 'Packed Red Cells') {
        $column = 'prbc_units';
    } elseif ($component === 'Platelets') {
        $column = 'platelets_units';
    } elseif ($component === 'Plasma') {
        $column = 'ffp_units';
    }

    $upsertInventory = $pdo->prepare(
        'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ffp_units)
         VALUES (:blood_bank_id, :blood_type, 0, 0, 0, 0)
         ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
    );
    $upsertInventory->execute([
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
    ]);

    $inventoryStmt = $pdo->prepare("SELECT {$column} AS available_units FROM tblinventory WHERE blood_bank_id = ? AND blood_type = ? FOR UPDATE");
    $inventoryStmt->execute([$bloodBankId, $bloodType]);
    $inventory = $inventoryStmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Inventory row missing after upsert.']);
        exit;
    }

    $beforeUnits = (int)($inventory['available_units'] ?? 0);
    $afterUnits = $beforeUnits + $units;

    $updateInventory = $pdo->prepare("UPDATE tblinventory SET {$column} = {$column} + :units, updated_at = CURRENT_TIMESTAMP WHERE blood_bank_id = :blood_bank_id AND blood_type = :blood_type");
    $updateInventory->execute([
        ':units' => $units,
        ':blood_bank_id' => $bloodBankId,
        ':blood_type' => $bloodType,
    ]);

    $updateDonation = $pdo->prepare('UPDATE tbldonations SET status = ?, stocked_at = NOW(), updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $updateDonation->execute(['Stocked', $donationId]);

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
        ':movement_type' => 'IN',
        ':units' => $units,
        ':reference_type' => 'DONATION',
        ':reference_id' => $donationId,
        ':before_units' => $beforeUnits,
        ':after_units' => $afterUnits,
        ':actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
        ':notes' => 'Stock added from tested safe donation.',
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Safe donation added to stock successfully.',
        'data' => [
            'donationId' => $donationId,
            'bloodType' => $bloodType,
            'component' => $component,
            'unitsAdded' => $units,
            'beforeUnits' => $beforeUnits,
            'afterUnits' => $afterUnits,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
