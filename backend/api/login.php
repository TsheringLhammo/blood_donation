<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email        = trim($data['email']    ?? '');
$userPassword = trim($data['password'] ?? '');

if (!$email || !$userPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM tblusers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No account found for this email. Please register as a donor first.']);
        exit;
    }

    if (!password_verify($userPassword, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Password is incorrect for this account.']);
        exit;
    }

    $donorId = 0;
    if (strtolower((string)$user['role']) === 'donor') {
        $donorStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
        $donorStmt->execute([(string)$user['email']]);
        $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
        $donorId = (int)($donorRow['id'] ?? 0);
    }

    $tokenPayload = [
        'id' => (int)$user['id'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
        'donor_id' => $donorId,
    ];

    echo json_encode([
        'success' => true,
        'id'      => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role'    => $user['role'],
        'donor_id' => $donorId,
        'token'   => bts_create_token($tokenPayload),
        'expiresInSeconds' => 28800,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
