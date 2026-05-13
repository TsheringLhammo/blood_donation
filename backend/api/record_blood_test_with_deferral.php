<?php
/**
 * record_blood_test_with_deferral.php
 * 
 * PURPOSE:
 *   Record blood test results and automatically defer donor if test is positive
 *   Handles: HIV, Hepatitis B (HBsAg), Hepatitis C, Syphilis, Malaria
 *   Creates deferral record with specific test reason and 6-month hold
 * 
 * REQUEST (POST):
 *   {
 *     "donation_id": "DON-2026-001",
 *     "donor_id": 123,
 *     "hiv_result": "Reactive",           // Negative, Reactive, Inconclusive, Not Tested
 *     "hbsag_result": "Negative",         // Negative, Reactive, Inconclusive, Not Tested
 *     "hcv_result": "Negative",           // Negative, Reactive, Inconclusive, Not Tested
 *     "syphilis_result": "Negative",      // Negative, Reactive, Inconclusive, Not Tested
 *     "malaria_result": "Negative",       // Negative, Reactive, Inconclusive, Not Tested
 *     "tested_by_user_id": 45,
 *     "notes": "Lab technician remarks"
 *   }
 * 
 * RESPONSE:
 *   {
 *     "success": true,
 *     "message": "Test results recorded successfully",
 *     "donation_id": "DON-2026-001",
 *     "final_result": "Discard",
 *     "donor_status": "Deferred",
 *     "deferral_reason": "Positive HIV result",
 *     "deferred_until": "2026-10-27"
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

// Load mailer if available
$mailerPath = __DIR__ . '/../config/mailer.php';
if (file_exists($mailerPath)) {
    require_once $mailerPath;
}

if (!function_exists('bts_send_email')) {
    function bts_send_email(...$args): bool {
        return false;
    }
}

// Require staff or admin auth
$claims = bts_require_auth(['staff', 'admin', 'doctor']);
$staffUserId = (int)($claims['sub'] ?? 0);

// Parse request
$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Validate required fields
$donationId = trim((string)($data['donation_id'] ?? ''));
$donorId = (int)($data['donor_id'] ?? 0);

if (empty($donationId) || $donorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donation_id and donor_id are required']);
    exit;
}

// Normalize test results
$normalizeResult = static function (string $value): string {
    $normalized = strtolower(trim($value));
    
    if (in_array($normalized, ['non-reactive', 'negative'], true)) {
        return 'Negative';
    }
    if ($normalized === 'reactive') {
        return 'Reactive';
    }
    if ($normalized === 'inconclusive') {
        return 'Inconclusive';
    }
    
    return 'Not Tested';
};

// Get test results
$hivResult = $normalizeResult((string)($data['hiv_result'] ?? 'Not Tested'));
$hbsagResult = $normalizeResult((string)($data['hbsag_result'] ?? 'Not Tested'));
$hcvResult = $normalizeResult((string)($data['hcv_result'] ?? 'Not Tested'));
$syphilisResult = $normalizeResult((string)($data['syphilis_result'] ?? 'Not Tested'));
$malariaResult = $normalizeResult((string)($data['malaria_result'] ?? 'Not Tested'));

$testedByUserId = (int)($data['tested_by_user_id'] ?? $staffUserId);
$notes = trim((string)($data['notes'] ?? ''));

// Determine final result
$allResults = [$hivResult, $hbsagResult, $hcvResult, $syphilisResult, $malariaResult];
$hasReactive = in_array('Reactive', $allResults, true);
$hasInconclusive = in_array('Inconclusive', $allResults, true);
$hasNotTested = in_array('Not Tested', $allResults, true);

if ($hasReactive) {
    $finalResult = 'Discard';
} elseif ($hasInconclusive) {
    $finalResult = 'Inconclusive';
} elseif ($hasNotTested) {
    $finalResult = 'Pending';
} else {
    $finalResult = 'Eligible';
}

// Determine which test triggered deferral
$deferralTrigger = null;
if ($hasReactive) {
    if ($hivResult === 'Reactive') {
        $deferralTrigger = 'HIV';
    } elseif ($hbsagResult === 'Reactive') {
        $deferralTrigger = 'HBsAg';
    } elseif ($hcvResult === 'Reactive') {
        $deferralTrigger = 'HCV';
    } elseif ($syphilisResult === 'Reactive') {
        $deferralTrigger = 'Syphilis';
    } elseif ($malariaResult === 'Reactive') {
        $deferralTrigger = 'Malaria';
    }
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert blood test record
    $testStmt = $pdo->prepare('
        INSERT INTO tblblood_tests (
          donation_id, donor_id, 
          hiv_result, hbsag_result, hcv_result, syphilis_result, malaria_result,
          final_result, deferral_trigger, tested_by, comments, tested_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    
    $testStmt->execute([
        $donationId,
        $donorId,
        $hivResult,
        $hbsagResult,
        $hcvResult,
        $syphilisResult,
        $malariaResult,
        $finalResult,
        $deferralTrigger,
        $testedByUserId,
        $notes
    ]);
    
    $bloodTestId = (int)$pdo->lastInsertId();
    
    // Get donor info
    $donorStmt = $pdo->prepare('
        SELECT id, full_name, email, status FROM tbldonors WHERE id = ? LIMIT 1
    ');
    $donorStmt->execute([$donorId]);
    $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        throw new Exception('Donor not found');
    }
    
    $donorName = $donor['full_name'];
    $donorEmail = $donor['email'];
    $newDonorStatus = $donor['status']; // Will be updated if deferred
    
    // If reactive result: DEFER the donor
    if ($hasReactive) {
        $deferredUntilDate = date('Y-m-d', strtotime('+6 months'));
        $deferralReason = sprintf('Positive %s result', $deferralTrigger);
        
        // Update donor status to Deferred
        $deferStmt = $pdo->prepare('
            UPDATE tbldonors
            SET status = ?,
                deferred = 1,
                deferral_reason = ?,
                deferred_until = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        
        $deferStmt->execute([
            'Deferred',
            $deferralReason,
            $deferredUntilDate,
            $donorId
        ]);
        
        $newDonorStatus = 'Deferred';
        
        // Log in deferral history
        $historyStmt = $pdo->prepare('
            INSERT INTO tbldeferral_history (
              donor_id, deferred_until, deferral_reason, 
              deferral_trigger_type, blood_test_id, triggered_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        $historyStmt->execute([
            $donorId,
            $deferredUntilDate,
            $deferralReason,
            'Positive Test',
            $bloodTestId,
            $staffUserId
        ]);
        
        // Get donor's user ID (if they have a user account)
        $userStmt = $pdo->prepare('
            SELECT id FROM tblusers WHERE email = ? LIMIT 1
        ');
        $userStmt->execute([$donorEmail]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        $donorUserId = $userRow ? (int)$userRow['id'] : null;
        
        // Create in-app notification for donor
        $notifStmt = $pdo->prepare('
            INSERT INTO tblnotifications (
              user_id, donor_id, role_target,
              title, message, type, severity, channel
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $deferralMessage = sprintf(
            'Your recent blood test showed a positive result for %s. ' .
            'You are temporarily deferred for 6 months as a safety measure. ' .
            'You can reapply on %s. ' .
            'Please contact the blood bank for more information.',
            $deferralTrigger,
            date('F j, Y', strtotime($deferredUntilDate))
        );
        
        $notifStmt->execute([
            $donorUserId,
            $donorId,
            'donor',
            '⏸️ Temporary Deferral Notice',
            $deferralMessage,
            'deferral',
            'warning',
            'both'
        ]);
        
        // Send email notification
        $emailSubject = '⏸️ Your Blood Donation - Temporary Deferral';
        $emailBody = sprintf(
            "Dear %s,\n\n" .
            "Your recent blood screening showed a positive result for: %s\n\n" .
            "As a safety measure to protect blood recipients, we must defer you temporarily for 6 months.\n\n" .
            "WHEN YOU CAN DONATE AGAIN:\n" .
            "You can reapply for donation on or after: %s\n\n" .
            "WHAT THIS MEANS:\n" .
            "- Your donation was not used\n" .
            "- Your health information is confidential\n" .
            "- This is standard medical practice, not a permanent rejection\n" .
            "- We recommend consulting with your healthcare provider\n\n" .
            "CONTACT US:\n" .
            "Helpline: 1095 (24/7)\n" .
            "Email: donors@bloodbank.bt\n\n" .
            "Thank you for your interest in saving lives.\n\n" .
            "Best regards,\n" .
            "Blood Transfusion Services\n" .
            "Ministry of Health, Bhutan",
            $donorName,
            $deferralTrigger,
            date('F j, Y', strtotime($deferredUntilDate))
        );
        
        bts_send_email($donorEmail, $emailSubject, $emailBody);
        
        // Create notification for admin/staff
        $adminNotifStmt = $pdo->prepare('
            INSERT INTO tblnotifications (
              role_target, donor_id,
              title, message, type, severity, channel
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $adminNotifStmt->execute([
            'admin',
            $donorId,
            '⚠️ Donor Deferred - Positive Test',
            sprintf(
                'Donor %s tested positive for %s. ' .
                'Status changed to Deferred until %s. ' .
                'Donor has been notified via email.',
                $donorName,
                $deferralTrigger,
                date('F j, Y', strtotime($deferredUntilDate))
            ),
            'alert',
            'critical',
            'in_app'
        ]);
    }
    
    $pdo->commit();
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => $hasReactive ? 'Test results recorded. Donor has been deferred.' : 'Test results recorded successfully',
        'donation_id' => $donationId,
        'blood_test_id' => $bloodTestId,
        'final_result' => $finalResult,
        'donor_status' => $newDonorStatus,
        'deferral_reason' => $hasReactive ? sprintf('Positive %s result', $deferralTrigger) : null,
        'deferred_until' => $hasReactive ? date('Y-m-d', strtotime('+6 months')) : null,
        'hiv_result' => $hivResult,
        'hbsag_result' => $hbsagResult,
        'hcv_result' => $hcvResult,
        'syphilis_result' => $syphilisResult,
        'malaria_result' => $malariaResult
    ]);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error recording blood test: ' . $e->getMessage()
    ]);
}
?>
