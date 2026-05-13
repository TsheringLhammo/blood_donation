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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

// Get JSON input
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!$data || !isset($data['appointment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID.']);
    exit;
}

$appointmentId = (int)$data['appointment_id'];

try {
    // Get appointment with donor information
    $stmt = $pdo->prepare("
        SELECT a.id, a.full_name, a.donor_id, d.status as donor_status, a.status as appointment_status
        FROM tblappointments a
        LEFT JOIN tbldonors d ON a.donor_id = d.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }
    
    // Check if donor is confirmed
    $donorStatus = strtolower(trim($appointment['donor_status'] ?? ''));
    $canCreateBloodUnit = ($donorStatus === 'confirmed');
    
    echo json_encode([
        'success' => true,
        'data' => [
            'appointment_id' => $appointmentId,
            'donor_id' => $appointment['donor_id'],
            'donor_name' => $appointment['full_name'],
            'donor_status' => $donorStatus,
            'appointment_status' => $appointment['appointment_status'],
            'can_create_blood_unit' => $canCreateBloodUnit
        ],
        'message' => $canCreateBloodUnit 
            ? 'Donor is confirmed and can create blood units' 
            : 'Only confirmed donors can create blood units'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
