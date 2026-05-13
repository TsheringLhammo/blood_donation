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
            id,
            organization,
            date,
            status,
            contact_person,
            phone,
            email,
            expected_participants
        FROM camp_requests 
        WHERE status != 'deleted'
        ORDER BY date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    foreach ($camps as &$camp) {
        $camp['date'] = date('Y-m-d', strtotime($camp['date']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => $camps
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
