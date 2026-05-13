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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.date,
            a.time,
            a.status,
            d.full_name,
            bb.name as blood_bank
        FROM tblappointments a
        LEFT JOIN tbldonors d ON a.donor_id = d.id
        LEFT JOIN tblblood_banks bb ON a.blood_bank_id = bb.id
        ORDER BY a.date DESC, a.time DESC
        LIMIT 20
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    foreach ($appointments as &$appointment) {
        $appointment['date'] = date('Y-m-d', strtotime($appointment['date']));
        $appointment['time'] = date('h:i A', strtotime($appointment['time']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => $appointments
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
