<?php
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

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';

bts_require_auth(['doctor', 'staff', 'admin']);

$status = bts_mail_setup_status();

echo json_encode([
    'success' => true,
    'emailSetup' => [
        'ready' => (bool)($status['ready'] ?? false),
        'missing' => $status['missing'] ?? [],
        'config' => $status['config'] ?? [],
        'phpmailerAvailable' => class_exists('PHPMailer\\PHPMailer\\PHPMailer'),
    ],
]);
