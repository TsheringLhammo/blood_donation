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
require_once __DIR__ . '/../config/mailer.php';

bts_require_auth(['admin']);

// Get JSON input
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Camp request ID is required.']);
    exit;
}

$campId = (int)$data['id'];

try {
    // Update camp request status
    $stmt = $pdo->prepare("
        UPDATE tblblood_camps 
        SET status = 'confirmed'
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$campId]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Camp request not found or already processed.']);
        exit;
    }
    
    // Get camp details for notification
    $stmt = $pdo->prepare("
        SELECT * FROM tblblood_camps WHERE id = ?
    ");
    $stmt->execute([$campId]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Send acceptance email
    $organizerName = trim((string)($camp['contact_person'] ?? ''));
    $campName = trim((string)($camp['organization_name'] ?? ''));
    $campDate = trim((string)($camp['preferred_date'] ?? ''));
    $campLocation = trim((string)($camp['venue_address'] ?? 'Not specified'));
    $organizerEmail = trim((string)($camp['email'] ?? ''));
    $organizationName = 'Blood Donation Management System';
    
    if ($organizerEmail !== '' && filter_var($organizerEmail, FILTER_VALIDATE_EMAIL)) {
        // Format the date for display
        $formattedDate = !empty($campDate) ? date('Y-m-d', strtotime($campDate)) : 'Not specified';
        
        $emailSubject = 'Blood Donation Camp Booking - Accepted';
        
        $htmlBody = <<<EOT
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <p>Dear $organizerName,</p>
    
    <p>We are happy to inform you that your blood donation camp booking has been accepted successfully.</p>
    
    <h3 style="color: #d9534f;">📍 Camp Details:</h3>
    <ul style="margin: 10px 0; padding-left: 20px;">
        <li><strong>Camp Name:</strong> $campName</li>
        <li><strong>Date:</strong> $formattedDate</li>
        <li><strong>Venue:</strong> $campLocation</li>
    </ul>
    
    <p>Thank you for organizing a blood donation camp and helping save lives.</p>
    
    <p>If you have any questions, please feel free to contact us.</p>
    
    <p>Best regards,<br>
    <strong>$organizationName</strong></p>
</body>
</html>
EOT;
        
        $textBody = <<<EOT
Dear $organizerName,

We are happy to inform you that your blood donation camp booking has been accepted successfully.

📍 Camp Details:
• Camp Name: $campName
• Date: $formattedDate
• Venue: $campLocation

Thank you for organizing a blood donation camp and helping save lives.

If you have any questions, please feel free to contact us.

Best regards,
$organizationName
EOT;
        
        $mailMeta = [];
        bts_send_email($organizerEmail, $emailSubject, $htmlBody, $textBody, $mailMeta);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Camp request accepted successfully',
        'data' => $camp
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
