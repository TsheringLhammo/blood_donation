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
    $stats = [];
    
    // Get total donors
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tbldonors WHERE status != 'deleted'");
    $stmt->execute();
    $stats['totalDonors'] = $stmt->fetchColumn();
    
    // Get upcoming appointments (next 5)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tblappointments 
        WHERE date >= CURDATE() 
        AND status IN ('pending', 'confirmed')
        ORDER BY date ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $stats['upcomingAppointments'] = $stmt->fetchColumn();
    
    // Get low stock alerts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM blood_inventory 
        WHERE quantity < 10
    ");
    $stmt->execute();
    $stats['lowStockAlerts'] = $stmt->fetchColumn();
    
    // Get camp requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tblblood_camps 
        WHERE status != 'deleted'
    ");
    $stmt->execute();
    $stats['campRequests'] = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
