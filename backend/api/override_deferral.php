<?php
/**
 * override_deferral.php
 * 
 * PURPOSE:
 *   Allow admin to manually override a donor's deferral status
 *   Restores donor to 'Confirmed' status
 *   Logs the action in deferral history
 * 
 * REQUEST (POST):
 *   {
 *     "donor_id": 123,
 *     "notes": "Approved by medical director after review"
 *   }
 * 
 * RESPONSE:
 *   {
 *     "success": true,
 *     "message": "Deferral overridden successfully",
 *     "donor_id": 123,
 *     "new_status": "Confirmed"
 *   }
 */

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
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Require admin auth only
$claims = bts_require_auth(['admin']);
$adminUserId = (int)($claims['sub'] ?? 0);

// Parse request
$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$donorId = (int)($data['donor_id'] ?? 0);
$notes = trim((string)($data['notes'] ?? 'Manually overridden by admin'));

if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donor_id is required']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get current donor status
    $checkStmt = $pdo->prepare('SELECT id, status, deferred_until FROM tbldonors WHERE id = ? LIMIT 1');
    $checkStmt->execute([$donorId]);
    $donor = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        throw new Exception('Donor not found');
    }
    
    $currentStatus = trim((string)($donor['status'] ?? ''));
    
    if (strtolower($currentStatus) !== 'deferred') {
        throw new Exception('Donor is not currently deferred');
    }
    
    // Update donor status to Confirmed
    $updateStmt = $pdo->prepare('
        UPDATE tbldonors
        SET status = ?,
            deferred = 0,
            deferral_reason = NULL,
            deferred_until = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    
    $updateStmt->execute(['Confirmed', $donorId]);
    
    // Log the override in deferral history
    $historyStmt = $pdo->prepare('
        UPDATE tbldeferral_history
        SET expired_at = CURRENT_TIMESTAMP,
            expired_action = ?
        WHERE donor_id = ?
          AND deferred_until = ?
          AND expired_at IS NULL
        LIMIT 1
    ');
    
    $historyStmt->execute(['Manual Override', $donorId, $donor['deferred_until']]);
    
    // Get donor info for notification
    $donorStmt = $pdo->prepare('SELECT full_name, email FROM tbldonors WHERE id = ? LIMIT 1');
    $donorStmt->execute([$donorId]);
    $donorInfo = $donorStmt->fetch(PDO::FETCH_ASSOC);
    
    // Create notification for donor
    $notifStmt = $pdo->prepare('
        INSERT INTO tblnotifications (
          user_id, donor_id, role_target,
          title, message, type, severity, channel
        ) VALUES (
          (SELECT id FROM tblusers WHERE email = ? LIMIT 1),
          ?,
          ?,
          ?,
          ?,
          ?,
          ?,
          ?
        )
    ');
    
    $notifStmt->execute([
        $donorInfo['email'] ?? null,
        $donorId,
        'donor',
        '🔄 Deferral Status Updated',
        sprintf(
            'Your deferral has been reviewed and lifted. You are now eligible to donate again. Thank you!'
        ),
        'expiry',
        'info',
        'both'
    ]);
    
    // Create notification for admin log
    $adminNotifStmt = $pdo->prepare('
        INSERT INTO tblnotifications (
          role_target, donor_id,
          title, message, type, severity, channel
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    
    $adminNotifStmt->execute([
        'admin',
        $donorId,
        '🔄 Deferral Override - Manual Action',
        sprintf(
            'Admin %d manually overrode deferral for donor %s. Reason: %s',
            $adminUserId,
            $donorInfo['full_name'] ?? 'Unknown',
            $notes
        ),
        'alert',
        'info',
        'in_app'
    ]);
    
    $pdo->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Deferral overridden successfully',
        'donor_id' => $donorId,
        'new_status' => 'Confirmed'
    ]);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error overriding deferral: ' . $e->getMessage()
    ]);
}
?>
