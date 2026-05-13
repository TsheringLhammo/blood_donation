<?php
declare(strict_types=1);

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
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.',
    ]);
    exit;
}

$email = trim((string)($payload['email'] ?? ''));
$newPassword = (string)($payload['newPassword'] ?? '');

if ($email === '' || $newPassword === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Email and new password are required.',
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format.',
    ]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'New password must be at least 6 characters.',
    ]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $findStmt = $pdo->prepare('SELECT id FROM tblusers WHERE email = ? LIMIT 1');
    $findStmt->execute([$email]);
    $user = $findStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No account found for this email.',
        ]);
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE tblusers SET password = :password WHERE email = :email LIMIT 1');
    $updateStmt->execute([
        ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ':email' => $email,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successful.',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not reset password.',
        'error' => $exception->getMessage(),
    ]);
}
