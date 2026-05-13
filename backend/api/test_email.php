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

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';

bts_require_auth(['doctor', 'staff', 'admin']);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

$recipient = trim((string)($data['recipient'] ?? ''));
$recipient = $recipient !== '' ? $recipient : bts_get_default_test_email();

$hospitalName = trim((string)($data['hospitalName'] ?? 'JDWNRH'));
$bloodType = trim((string)($data['bloodType'] ?? 'A+'));
$subject = trim((string)($data['subject'] ?? 'Blood Donation System - Email Test'));

$emailMeta = [];
$html =
    '<h2>Email Test - Blood Donation Management System</h2>' .
    '<p>This is a test email from the doctor request workflow.</p>' .
    '<p><strong>Hospital:</strong> ' . htmlspecialchars($hospitalName, ENT_QUOTES, 'UTF-8') . '</p>' .
    '<p><strong>Blood Type:</strong> ' . htmlspecialchars($bloodType, ENT_QUOTES, 'UTF-8') . '</p>';

$text =
    "Email Test - Blood Donation Management System\n" .
    "This is a test email from the doctor request workflow.\n" .
    "Hospital: {$hospitalName}\n" .
    "Blood Type: {$bloodType}\n";

$sent = bts_send_email($recipient, $subject, $html, $text, $emailMeta);

if (!$sent) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Test email failed to send.',
        'email' => [
            'recipient' => $recipient,
            'transport' => $emailMeta['transport'] ?? null,
            'phpmailerAvailable' => $emailMeta['phpmailerAvailable'] ?? null,
            'smtpConfigured' => $emailMeta['smtpConfigured'] ?? null,
            'error' => $emailMeta['error'] ?? null,
        ],
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Test email sent successfully.',
    'email' => [
        'recipient' => $recipient,
        'transport' => $emailMeta['transport'] ?? null,
        'phpmailerAvailable' => $emailMeta['phpmailerAvailable'] ?? null,
        'smtpConfigured' => $emailMeta['smtpConfigured'] ?? null,
        'error' => null,
    ],
]);
