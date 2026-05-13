<?php
/**
 * check_donor_eligibility.php
 * 
 * PURPOSE:
 *   Check if a donor is eligible to book an appointment
 *   Verifies: Donor is confirmed and has a negative sample test
 * 
 * REQUEST:
 *   GET /api/check_donor_eligibility.php?donor_id=123
 * 
 * RESPONSE:
 *   {
 *     "success": true,
 *     "eligible": true,
 *     "status": "Confirmed",
 *     "message": "Donor is eligible for appointment",
 *     "deferral_reason": null,
 *     "deferred_until": null
 *   }
 * 
 * OR (if deferred):
 *   {
 *     "success": true,
 *     "eligible": false,
 *     "status": "Deferred",
 *     "message": "Donor is temporarily deferred until 2026-10-27",
 *     "deferral_reason": "Positive HIV result",
 *     "deferred_until": "2026-10-27"
 *   }
 */

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

try {
    // Get donor_id from query
    $donorId = (int)($_GET['donor_id'] ?? 0);
    
    if ($donorId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid donor_id.']);
        exit;
    }
    
    // Query donor status
    $stmt = $pdo->prepare('
        SELECT 
          id,
          status,
                    sample_tested,
                    sample_tested_at,
          deferral_reason,
          deferred_until
        FROM tbldonors
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Donor not found.'
        ]);
        exit;
    }
    
    // Normalize status
    $status = trim((string)($donor['status'] ?? 'Pending'));
    if (empty($status)) {
        $status = 'Pending';
    }
    $status = ucfirst(strtolower($status));
    $sampleTested = strtolower(trim((string)($donor['sample_tested'] ?? 'pending')));
    if ($sampleTested === '') {
        $sampleTested = 'pending';
    }
    
    // Check eligibility
    $eligible = false;
    $message = '';
    
    if (strtolower($status) === 'deferred') {
        // Check if deferral has expired
        $deferredUntil = $donor['deferred_until'] ? new DateTime($donor['deferred_until']) : null;
        $today = new DateTime(date('Y-m-d'));
        
        if ($deferredUntil && $deferredUntil > $today) {
            // Still deferred
            $eligible = false;
            $message = sprintf(
                'Donor is temporarily deferred until %s. Reason: %s',
                $deferredUntil->format('F j, Y'),
                $donor['deferral_reason'] ?? 'Medical hold'
            );
        } else if ($deferredUntil && $deferredUntil <= $today) {
            // Deferral expired - need to update status
            // Auto-restore to Confirmed if deferral period ended
            $restoreSql = '
                UPDATE tbldonors
                SET status = ?, deferred = 0, deferral_reason = NULL, deferred_until = NULL';
            if (isset($donor['sample_tested'])) {
                $restoreSql .= ', sample_tested = "Pending"';
            }
            if (isset($donor['sample_tested_at'])) {
                $restoreSql .= ', sample_tested_at = NULL';
            }
            $restoreSql .= ' WHERE id = ?';
            $pdo->prepare($restoreSql)->execute(['Confirmed', $donorId]);
            
            $eligible = false;
            $status = 'Confirmed';
            $sampleTested = 'pending';
            $message = 'Deferral period has ended, but a new sample test is required before booking.';
        } else {
            $eligible = false;
            $message = 'Donor is temporarily deferred.';
        }
    } else if ((strtolower($status) === 'confirmed' || strtolower($status) === 'eligible' || strtolower($status) === 'active') && $sampleTested === 'negative') {
        $eligible = true;
        $message = 'Donor is eligible for appointment. Sample testing is negative.';
    } else if ($sampleTested === 'reactive') {
        $eligible = false;
        $message = 'Donor is deferred because the latest sample test was reactive.';
    } else if ($sampleTested === 'pending' || $sampleTested === 'inconclusive') {
        $eligible = false;
        $message = 'Donor must complete a negative sample test before booking an appointment.';
    } else {
        $eligible = false;
        $message = sprintf('Donor status is %s. Must be Confirmed with a negative sample test to book appointment.', $status);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'eligible' => $eligible,
        'status' => $status,
        'sample_tested' => $sampleTested,
        'message' => $message,
        'deferral_reason' => $donor['deferral_reason'] ?? null,
        'deferred_until' => $donor['deferred_until'] ?? null
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
