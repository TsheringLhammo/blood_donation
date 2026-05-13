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

bts_require_auth(['admin']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$notificationId = (int)($data['notificationId'] ?? 0);
$sampleId = (int)($data['sampleId'] ?? 0);
$donorId = (int)($data['donorId'] ?? 0);
$approve = (bool)($data['approve'] ?? false);
$adminNotes = trim((string)($data['adminNotes'] ?? ''));

if (!$notificationId || !$sampleId || !$donorId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'notificationId, sampleId, and donorId are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Mark notification as read
    $markReadStmt = $pdo->prepare(
        'UPDATE tblnotifications SET is_read = 1 WHERE id = ? AND user_id IS NULL'
    );
    $markReadStmt->execute([$notificationId]);

    // Get sample and donor info
    $infoStmt = $pdo->prepare(
        'SELECT s.*, d.full_name as donor_name, d.email as donor_email
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.id = ? AND s.donor_id = ?'
    );
    $infoStmt->execute([$sampleId, $donorId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sample or donor not found.']);
        exit;
    }

    $donorName = $info['donor_name'];
    $hasReactive = strtolower(trim($info['status'])) === 'reactive' ||
                   (strtolower(trim($info['hiv_result'])) === 'reactive' ||
                    strtolower(trim($info['hbsag_result'])) === 'reactive' ||
                    strtolower(trim($info['hcv_result'])) === 'reactive' ||
                    strtolower(trim($info['syphilis_result'])) === 'reactive' ||
                    strtolower(trim($info['malaria_result'])) === 'reactive');

    if ($hasReactive) {
        // For reactive results: Donor is already deferred, just notify them of deferral
        $donorTitle = "Blood Test Results - Deferral Notice";
        $donorMessage = "Your blood sample test has shown reactive results. For your safety and the safety of recipients, you have been temporarily deferred from donation. Our team will contact you with further guidance.";

        $donorNotifyStmt = $pdo->prepare(
            'INSERT INTO tblnotifications (user_id, title, message, type, is_read, created_at)
             VALUES (?, ?, ?, "warning", 0, NOW())'
        );
        $donorNotifyStmt->execute([$donorId, $donorTitle, $donorMessage]);

        // Log admin action
        $logStmt = $pdo->prepare(
            'INSERT INTO tbladmin_logs (admin_id, action, details, created_at)
             VALUES (?, "review_reactive", ?, NOW())'
        );
        $logDetails = json_encode([
            'sample_id' => $sampleId,
            'donor_id' => $donorId,
            'notification_id' => $notificationId,
            'admin_notes' => $adminNotes,
            'results' => [
                'hiv' => $info['hiv_result'],
                'hbsag' => $info['hbsag_result'],
                'hcv' => $info['hcv_result'],
                'syphilis' => $info['syphilis_result'],
                'malaria' => $info['malaria_result']
            ]
        ]);
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $logStmt->execute([$adminId, $logDetails]);

        $successMessage = "Reactive result review completed. Donor {$donorName} has been notified of deferral.";
    } else {
        // For non-reactive results approved by admin: Mark donor as eligible and notify them
        if ($approve) {
            $updateDonorStmt = $pdo->prepare(
                'UPDATE tbldonors
                 SET status = "Eligible",
                     sample_eligible = 1,
                     last_negative_sample_date = CURDATE()
                 WHERE id = ?'
            );
            $updateDonorStmt->execute([$donorId]);

            // Create donor notification
            $donorTitle = "🎉 Blood Test Results Approved!";
            $donorMessage = "Great news! Your blood sample test results have been reviewed and approved. All tests are non-reactive and you are now eligible for blood donation. You may proceed to schedule your donation appointment.";

            $donorNotifyStmt = $pdo->prepare(
                'INSERT INTO tblnotifications (user_id, title, message, type, is_read, created_at)
                 VALUES (?, ?, ?, "success", 0, NOW())'
            );
            $donorNotifyStmt->execute([$donorId, $donorTitle, $donorMessage]);

            $successMessage = "Results approved. Donor {$donorName} has been notified and marked as eligible.";
        } else {
            // Admin rejected/disputed the results - donor stays in current status
            // Staff will be notified to collect a new sample

            // Create notification for staff to retest
            $staffNotifyStmt = $pdo->prepare(
                'INSERT INTO tblnotifications (user_id, title, message, type, is_read, created_at)
                 SELECT id, ?, ?, "alert", 0, NOW()
                 FROM tblusers WHERE role IN ("staff", "admin")'
            );
            $staffTitle = "Sample Requires Retest - {$donorName}";
            $staffMessage = "Admin has requested a retest for donor {$donorName} (ID: {$donorId}). Sample ID: {$sampleId}. Reason: {$adminNotes}";
            $staffNotifyStmt->execute([$staffTitle, $staffMessage]);

            $successMessage = "Results flagged for retest. Staff has been notified.";
        }

        // Log admin action
        $logStmt = $pdo->prepare(
            'INSERT INTO tbladmin_logs (admin_id, action, details, created_at)
             VALUES (?, "approve_sample", ?, NOW())'
        );
        $logDetails = json_encode([
            'sample_id' => $sampleId,
            'donor_id' => $donorId,
            'notification_id' => $notificationId,
            'approved' => $approve,
            'admin_notes' => $adminNotes
        ]);
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $logStmt->execute([$adminId, $logDetails]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $successMessage,
        'data' => [
            'sample_id' => $sampleId,
            'donor_id' => $donorId,
            'donor_name' => $donorName,
            'has_reactive_results' => $hasReactive,
            'approved' => $approve
        ]
    ]);

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
