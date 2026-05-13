<?php
// Test if we can fetch Nado's profile like the API does

require 'backend/config/db.php';
require 'backend/config/auth.php';

// First, let's find Nado's user record
$stmt = $pdo->prepare('SELECT u.*, d.id as donor_id FROM tblusers u LEFT JOIN tbldonors d ON LOWER(TRIM(u.email)) = LOWER(TRIM(d.email)) WHERE u.email = ? LIMIT 1');
$stmt->execute(['Nado@gmail.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== User Login Info ===\n";
if ($user) {
    echo "Email: " . $user['email'] . "\n";
    echo "ID (tblusers): " . $user['id'] . "\n";
    echo "Donor ID: " . $user['donor_id'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    
    // Create a token like login would
    $token = bts_create_token([
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'donor_id' => $user['donor_id'] ?? 0
    ]);
    
    echo "\n=== Generated Token ===\n";
    echo $token . "\n";
    
    // Verify it
    $verified = bts_verify_token($token);
    echo "\n=== Verified Claims ===\n";
    echo json_encode($verified, JSON_PRETTY_PRINT);
} else {
    echo "User not found!\n";
}
