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

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
    exit;
}

$appointmentId = (int)$data['id'];

try {
    // Update appointment status
    $stmt = $pdo->prepare("
        UPDATE tblappointments 
        SET status = 'rejected', 
            updated_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$appointmentId]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or already processed.']);
        exit;
    }
    
    // Get appointment details for notification
    $stmt = $pdo->prepare("
        SELECT a.* 
        FROM tblappointments a
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create a notification for the donor using the proper notification system
    if ($appointment) {
        try {
            // Try to find donor by phone number or name from appointment
            $donorStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE phone = ? OR full_name = ? LIMIT 1');
            $donorStmt->execute([$appointment['phone_number'] ?? '', $appointment['full_name'] ?? '']);
            $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($donor) {
                $date = $appointment['preferred_date'] ?? 'TBD';
                $bank = $appointment['blood_bank'] ?? 'Your Blood Bank';
                
                $title = "Appointment Rejected";
                $message = "Your appointment on {$date} at {$bank} has been rejected.";
                
                $notifStmt = $pdo->prepare(
                    'INSERT INTO tblnotifications (donor_id, title, message, type, severity, channel, is_read)
                     VALUES (?, ?, ?, "appointment", "warning", "in_app", 0)'
                );
                $notifStmt->execute([
                    $donor['id'],
                    $title,
                    $message
                ]);
            }
        } catch (Throwable $notifError) {
            // Log but don't fail if notification creation fails
            error_log("Failed to create notification for donor: " . $notifError->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment rejected successfully',
        'data' => $appointment
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
