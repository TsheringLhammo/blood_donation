<?php
require 'backend/config/db.php';
require 'backend/config/auth.php';

echo "=== Testing Appointment API Call ===\n\n";

// 1. Create a token for Nado
echo "1. Creating JWT token for Nado...\n";
$stmt = $pdo->prepare('SELECT u.*, d.id as donor_id FROM tblusers u LEFT JOIN tbldonors d ON LOWER(TRIM(u.email)) = LOWER(TRIM(d.email)) WHERE u.email = ? LIMIT 1');
$stmt->execute(['Nado@gmail.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "ERROR: User not found!\n";
    exit;
}

$token = bts_create_token([
    'id' => $user['id'],
    'email' => $user['email'],
    'role' => $user['role'],
    'donor_id' => $user['donor_id'] ?? 0
]);

echo "   Token: " . substr($token, 0, 50) . "...\n\n";

// 2. Simulate the appointment payload
echo "2. Testing appointment payload...\n";
$payload = [
    'fullName' => 'Nado',
    'email' => 'Nado@gmail.com',
    'age' => 25,
    'gender' => 'Male',
    'bloodGroup' => 'O+',
    'phone' => '17656587',
    'preferredDate' => '2026-05-15',
    'preferredTime' => '10:00 AM',
    'bloodBank' => 'National Blood Bank'
];

echo "   Payload: " . json_encode($payload) . "\n\n";

// 3. Test the approval logic directly
echo "3. Testing approval logic...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

// Simulate the bts_require_auth call
$headerAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$parts = explode(' ', $headerAuth);
if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
    $verified = bts_verify_token($parts[1]);
    if ($verified) {
        echo "   ✅ Token verified\n";
        echo "   Claims: " . json_encode($verified) . "\n\n";
        
        // Test the donor status check
        $claims = $verified;
        $donorMetaStmt = $pdo->prepare('SELECT COALESCE(status, "Pending") AS status, COALESCE(deferred, 0) AS deferred FROM tbldonors WHERE email = ? LIMIT 1');
        $donorMetaStmt->execute([$claims['email'] ?? '']);
        $donorMeta = $donorMetaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        echo "4. Checking donor approval...\n";
        echo "   Donor status: " . $donorMeta['status'] . "\n";
        echo "   Donor deferred: " . $donorMeta['deferred'] . "\n\n";
        
        // Check approval
        $donorStatus = strtolower(trim((string)($donorMeta['status'] ?? 'Pending')));
        $approvedStatuses = ['active', 'confirmed', 'approved', 'pending', 'approved for blood d', 'ready'];
        $isApprovedStatus = false;
        foreach ($approvedStatuses as $approvedStatus) {
            if (stripos($donorStatus, trim($approvedStatus)) !== false) {
                $isApprovedStatus = true;
                break;
            }
        }
        
        if ($isApprovedStatus) {
            echo "   ✅ Donor is approved based on status field\n";
        } else {
            echo "   ⚠️  Status field not approved, checking workflow_status...\n";
        }
        
        // Now test actual INSERT
        echo "\n5. Testing actual INSERT into tblappointments...\n";
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tblappointments (user_id, full_name, email, age, gender, blood_group, phone_number, preferred_date, preferred_time, blood_bank)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'],
                $payload['fullName'],
                $payload['email'],
                $payload['age'],
                $payload['gender'],
                $payload['bloodGroup'],
                $payload['phone'],
                $payload['preferredDate'],
                $payload['preferredTime'],
                $payload['bloodBank']
            ]);
            
            $appointmentId = (int)$pdo->lastInsertId();
            echo "   ✅ Appointment saved! ID: " . $appointmentId . "\n";
            
            // Delete it to clean up
            $pdo->prepare('DELETE FROM tblappointments WHERE id = ?')->execute([$appointmentId]);
            echo "   Cleaned up test record\n";
            
        } catch (PDOException $e) {
            echo "   ❌ INSERT failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❌ Token verification failed\n";
    }
} else {
    echo "   ❌ No valid bearer token\n";
}
