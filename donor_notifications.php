<?php
require_once __DIR__ . '/backend/config/db.php';

header('Content-Type: application/json; charset=utf-8');

$email = isset($_GET['donor']) ? trim((string)$_GET['donor']) : 'yoyo@example.com';

$stmt = $pdo->prepare('SELECT id FROM tbldonors WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$donor = $stmt->fetch();

if (!$donor) {
    echo json_encode(['success' => false, 'message' => 'Donor not found']);
    exit;
}

$notifStmt = $pdo->prepare('SELECT id, decision, message, created_at FROM tblnotifications WHERE donor_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 1');
$notifStmt->execute([(int)$donor['id']]);
$notification = $notifStmt->fetch();

echo json_encode([
    'success' => true,
    'notification' => $notification ?: null,
]);
