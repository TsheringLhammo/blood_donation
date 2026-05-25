<?php
/**
 * finalize_test_decision.php
 * Admin finalizes test decision and sends notification to donor
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/mailer.php';

// Database configuration
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'blood_donation';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$sampleId = isset($input['sample_id']) ? (int)$input['sample_id'] : 0;
$decision = $input['decision'] ?? 'pending';
$deferUntil = $input['defer_until'] ?? null;
$decisionNotes = $input['decision_notes'] ?? '';
$notifyDonor = $input['notify_donor'] ?? true;
$adminMessage = trim((string)($input['message'] ?? ''));
$deferMonths = isset($input['defer_months']) ? (int)$input['defer_months'] : 6;
$deferralReason = $input['deferral_reason'] ?? null;

if (!$sampleId) {
    echo json_encode(['success' => false, 'message' => 'Sample ID is required']);
    exit;
}

// Connect to database
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$mysqli->set_charset("utf8mb4");

// Get sample and donor details
$query = "SELECT ds.*, d.id as donor_id, d.full_name, d.email, d.phone, d.blood_type 
          FROM tbldonor_samples ds 
          JOIN tbldonors d ON ds.donor_id = d.id 
          WHERE ds.id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $sampleId);
$stmt->execute();
$result = $stmt->get_result();
$sample = $result->fetch_assoc();

if (!$sample) {
    echo json_encode(['success' => false, 'message' => 'Sample not found']);
    $mysqli->close();
    exit;
}

// Determine positive diseases
$positiveDiseases = [];
if ($sample['hiv_result'] === 'Reactive') $positiveDiseases[] = 'HIV';
if ($sample['hbsag_result'] === 'Reactive') $positiveDiseases[] = 'Hepatitis B';
if ($sample['hcv_result'] === 'Reactive') $positiveDiseases[] = 'Hepatitis C';
if ($sample['syphilis_result'] === 'Reactive') $positiveDiseases[] = 'Syphilis';
if ($sample['malaria_result'] === 'Reactive') $positiveDiseases[] = 'Malaria';

// Determine if there are serious diseases that require permanent deferral
$seriousDiseases = ['HIV', 'Hepatitis B', 'Hepatitis C', 'Syphilis'];
$hasSeriousDisease = !empty(array_intersect($positiveDiseases, $seriousDiseases));

$normalizedDecision = match ($decision) {
    'accept', 'approve' => 'accept',
    'defer_temporary' => 'temp_defer',
    'defer_permanent' => 'perm_defer',
    default => $decision,
};

// Force permanent deferral for serious diseases, even if temp_defer was selected
if ($hasSeriousDisease && $normalizedDecision === 'temp_defer') {
    $normalizedDecision = 'perm_defer';
}

$isEligible = ($normalizedDecision === 'accept' && empty($positiveDiseases));

$workflowStatus = match ($normalizedDecision) {
    'accept' => 'decision_made_accepted',
    'temp_defer' => 'decision_made_deferred',
    'perm_defer' => 'decision_made_rejected',
    default => 'decision_made_rejected',
};
$testResult = $isEligible ? 'negative' : 'positive';
$positiveList = implode(', ', $positiveDiseases);

// Update donor record
$deferUntilValue = ($normalizedDecision === 'temp_defer' && $deferUntil) ? $deferUntil : null;
$deferReason = null;
if ($normalizedDecision !== 'accept') {
    $deferReason = !empty($positiveDiseases) ? 'Reactive ' . $positiveList : ($decisionNotes ?: null);
}

// Handle temp_defer with months calculation
if ($normalizedDecision === 'temp_defer') {
    if ($deferUntil) {
        $deferUntilValue = $deferUntil;
    } elseif ($deferMonths > 0) {
        // Calculate future date by adding X months
        $today = new DateTime();
        $today->add(new DateInterval("P{$deferMonths}M"));
        $deferUntilValue = $today->format('Y-m-d');
    }
  
    // Use custom deferral reason if provided (from frontend), otherwise build from diseases
    if (!empty($deferralReason)) {
        $deferReason = $deferralReason;
    } elseif (!empty($positiveDiseases)) {
        $deferReason = 'Positive (' . $positiveList . ') - Temporary deferral ' . $deferMonths . ' months';
    }
}
$donorStatus = match ($normalizedDecision) {
    'accept' => 'Approved Donor',
    'temp_defer' => 'Temporarily Deferred',
    'perm_defer' => 'Permanently Deferred',
    default => 'Deferred',
};
$donorDeferred = $normalizedDecision === 'accept' ? 0 : 1;

$updateDonor = "UPDATE tbldonors SET 
    workflow_status = ?,
    latest_test_result = ?,
    latest_test_date = NOW(),
    deferred_until = ?,
    deferral_reason = ?,
    status = ?,
    deferred = ?
    WHERE id = ?";
$stmt = $mysqli->prepare($updateDonor);
$stmt->bind_param("ssssisi", $workflowStatus, $testResult, $deferUntilValue, $deferReason, $donorStatus, $donorDeferred, $sample['donor_id']);
$stmt->execute();

// Update sample record
$sampleDecisionAfterTest = match ($normalizedDecision) {
    'accept' => 'accept',
    'temp_defer' => 'defer',
    'perm_defer' => 'reject',
    default => 'reject',
};

$updateSample = "UPDATE tbldonor_samples SET 
    admin_finalized = 1,
    decision_after_test = ?,
    decision_date = NOW(),
    decision_notes = ?,
    donor_notified = 0
    WHERE id = ?";
$stmt = $mysqli->prepare($updateSample);
$stmt->bind_param("ssi", $sampleDecisionAfterTest, $decisionNotes, $sampleId);
$stmt->execute();

// Send email notification
$messageSent = false;
$messageContent = '';
$notificationInserted = false;

if ($notifyDonor && !empty($sample['email'])) {
    $donorName = $sample['full_name'];
    $to = $sample['email'];
    
    if ($normalizedDecision === 'accept' && empty($positiveDiseases)) {
        // ALL NEGATIVE - ELIGIBLE
        $subject = "✅ Blood Donation - You Are Eligible";
        $messageContent = $adminMessage !== ''
            ? $adminMessage
            : "Dear $donorName,\n\nYour blood sample has been reviewed and the results are negative. You are now an Approved Donor.\n\nPlease contact us to schedule your donation appointment.\n\nThank you for helping save lives.\n\n- Blood Transfusion Services, Bhutan";
    } 
    elseif ($normalizedDecision === 'temp_defer') {
        // TEMPORARY DEFERRAL
        $deferDate = $deferUntil ? date('F j, Y', strtotime($deferUntil)) : '6 months';
        $subject = "📋 Blood Donation - Temporary Deferral";
        $messageContent = $adminMessage !== ''
            ? $adminMessage
            : "Dear $donorName,\n\nThank you for your willingness to donate blood. Based on your screening results, you are temporarily deferred from donating blood.\n\nYou may be eligible to return after: $deferDate\n\n" . (!empty($positiveDiseases) ? 'Reason: Positive results for ' . implode(', ', $positiveDiseases) . "\n\n" : ($decisionNotes ? "Reason: $decisionNotes\n\n" : '')) . "Please contact a healthcare provider if you have questions.\n\n- Blood Transfusion Services, Bhutan";
    }
    else {
        // PERMANENT DEFERRAL OR REJECTED
        $subject = "❌ Blood Donation - Permanent Deferral";
        $messageContent = $adminMessage !== ''
            ? $adminMessage
            : "Dear $donorName,\n\nThank you for your willingness to donate blood. Based on your screening results, you are permanently deferred from donating blood for your health and the safety of recipients.\n\n" . (!empty($positiveDiseases) ? 'Reason: Positive results for ' . implode(', ', $positiveDiseases) . "\n\n" : ($decisionNotes ? "Reason: $decisionNotes\n\n" : '')) . "Please contact a healthcare provider for confidential follow-up.\n\n- Blood Transfusion Services, Bhutan";
    }
    
    $htmlMessage = nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8'));

    if (function_exists('bts_send_email')) {
        $mailMeta = [];
        $messageSent = bts_send_email($to, $subject, $htmlMessage, $messageContent, $mailMeta);
    } else {
        $headers = "From: Blood Transfusion Services <noreply@bloodbank.gov.bt>\r\n";
        $headers .= "Reply-To: bloodbank@health.gov.bt\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $messageSent = @mail($to, $subject, $messageContent, $headers);
    }

    $notificationMessage = trim(preg_replace('/\s+/', ' ', $messageContent));
    $notificationMessage = mb_substr($notificationMessage, 0, 255);
    $notificationTitle = mb_substr($subject, 0, 160);
    $notificationSeverity = $normalizedDecision === 'accept' ? 'success' : ($normalizedDecision === 'temp_defer' ? 'warning' : 'critical');
    $notificationStmt = $mysqli->prepare("INSERT INTO tblnotifications (user_id, role_target, title, message, severity, channel, is_read, created_at, type) VALUES (?, 'donor', ?, ?, ?, 'in_app', 0, NOW(), ?)");
    if ($notificationStmt) {
        $notificationType = $notificationSeverity;
        $notificationStmt->bind_param("issss", $sample['donor_id'], $notificationTitle, $notificationMessage, $notificationSeverity, $notificationType);
        $notificationInserted = $notificationStmt->execute();
        $notificationStmt->close();
    }
    
    // Update notification status in sample table
    $notifiedStatus = ($messageSent || $notificationInserted) ? 1 : 0;
    $updateNotif = "UPDATE tbldonor_samples SET donor_notified = ?, notification_sent_at = NOW() WHERE id = ?";
    $stmt = $mysqli->prepare($updateNotif);
    $stmt->bind_param("ii", $notifiedStatus, $sampleId);
    $stmt->execute();
    
    // Also update donor's last_negative_sample_date if eligible
    if ($isEligible && $messageSent) {
        $updateDonorDate = "UPDATE tbldonors SET last_negative_sample_date = NOW() WHERE id = ?";
        $stmt = $mysqli->prepare($updateDonorDate);
        $stmt->bind_param("i", $sample['donor_id']);
        $stmt->execute();
    }
}

$mysqli->close();

echo json_encode([
    'success' => true,
    'message' => $normalizedDecision === 'accept' ? 'Donor accepted and notified' : 'Donor deferred and notified',
    'data' => [
        'donor_name' => $sample['full_name'],
        'donor_email' => $sample['email'],
        'decision' => $normalizedDecision,
        'is_eligible' => $isEligible,
        'positive_diseases' => $positiveDiseases,
        'positive_list' => $positiveList,
        'message_sent' => $messageSent,
        'notification_inserted' => $notificationInserted,
        'message_content' => $messageContent
    ]
]);
?>