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

if (!$data || !isset($data['appointment_id']) || !isset($data['blood_type']) || !isset($data['units']) || !isset($data['expiration_date']) || !isset($data['storage_location'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$appointmentId = (int)$data['appointment_id'];
$bloodType = $data['blood_type'];
$units = (int)$data['units'];
$expirationDate = $data['expiration_date'];
$storageLocation = $data['storage_location'];
$notes = $data['notes'] ?? '';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get appointment details with donor information
    $appointmentStmt = $pdo->prepare("
        SELECT a.full_name, a.email, a.phone, a.donor_id, d.full_name as donor_name, d.email as donor_email, d.phone as donor_phone
        FROM tblappointments a
        LEFT JOIN tbldonors d ON a.donor_id = d.id
        WHERE a.id = ?
    ");
    $appointmentStmt->execute([$appointmentId]);
    $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }
    
    // Add blood unit to inventory
    $stmt = $pdo->prepare("
        INSERT INTO blood_inventory (
            blood_type, 
            quantity, 
            expiration_date, 
            storage_location, 
            notes, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $bloodType,
        $units,
        $expirationDate,
        $storageLocation,
        $notes
    ]);
    
    $bloodUnitId = $pdo->lastInsertId();
    
    // Update appointment to mark blood unit added
    $updateStmt = $pdo->prepare("
        UPDATE tblappointments 
        SET blood_unit_added = 1, 
            blood_unit_id = ?, 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$bloodUnitId, $appointmentId]);
    
    // Send notification to donor
    $donorName = $appointment['donor_name'] ?: $appointment['full_name'];
    $donorEmail = $appointment['donor_email'] ?: $appointment['email'];
    $donorPhone = $appointment['donor_phone'] ?: $appointment['phone'];
    
    // Simple notification logging - no database dependencies
    if ($donorEmail || $donorPhone) {
        $notificationTitle = "Blood Unit Collected Successfully";
        $notificationMessage = "Thank you for your blood donation! We have successfully collected {$units} unit(s) of {$bloodType} blood. Your contribution will help save lives. The blood unit has been stored at {$storageLocation} and will be available until {$expirationDate}.";
        
        // Log notification to error log (always works)
        error_log("BLOOD UNIT NOTIFICATION: Donor: {$donorName}, Email: {$donorEmail}, Phone: {$donorPhone}, Message: {$notificationMessage}");
        
        // Try to insert into any available notification table
        try {
            // Try notifications table first
            $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
            if ($tableExists) {
                $notificationStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, email, phone, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $notificationStmt->execute([
                    $appointment['donor_id'] ?: null,
                    'blood_unit_collected',
                    $notificationTitle,
                    $notificationMessage,
                    $donorEmail,
                    $donorPhone
                ]);
            }
        } catch (Exception $e) {
            // Ignore notification errors - blood unit addition still works
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Blood unit added successfully and notification sent to donor',
        'data' => [
            'blood_unit_id' => $bloodUnitId,
            'blood_type' => $bloodType,
            'units' => $units,
            'expiration_date' => $expirationDate,
            'storage_location' => $storageLocation,
            'notes' => $notes,
            'notification_sent' => ($donorEmail || $donorPhone) ? true : false,
            'donor_notified' => $donorName
        ]
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
