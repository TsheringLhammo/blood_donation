<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    // Add sample appointments if they don't exist
    $appointments = [
        [
            'full_name' => 'Lhamo',
            'date' => '2026-04-03',
            'time' => '10:00:00',
            'blood_bank' => 'National Blood Bank',
            'status' => 'rejected'
        ],
        [
            'full_name' => 'Rinchen',
            'date' => '2026-04-09',
            'time' => '10:00:00',
            'blood_bank' => 'Mongar Blood Bank',
            'status' => 'confirmed'
        ],
        [
            'full_name' => 'Dema Lhamo',
            'date' => '2026-04-20',
            'time' => '09:00:00',
            'blood_bank' => 'National Blood Bank',
            'status' => 'pending'
        ]
    ];

    foreach ($appointments as $appointment) {
        // Check if appointment already exists
        $stmt = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE full_name = ? AND date = ? AND time = ?
        ");
        $stmt->execute([$appointment['full_name'], $appointment['date'], $appointment['time']]);
        
        if ($stmt->fetchColumn() === false) {
            // Insert new appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (full_name, date, time, blood_bank, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $appointment['full_name'],
                $appointment['date'],
                $appointment['time'],
                $appointment['blood_bank'],
                $appointment['status']
            ]);
        }
    }

    // Add sample camp requests if they don't exist
    $campRequests = [
        [
            'organization' => 'Royal Institute of Management',
            'date' => '2026-04-02',
            'status' => 'confirmed',
            'contact_person' => 'Dr. Tashi',
            'phone' => '17771234',
            'email' => 'tashi@rim.edu.bt',
            'expected_participants' => 50
        ],
        [
            'organization' => 'S/Jongkhar',
            'date' => '2026-04-24',
            'status' => 'pending',
            'contact_person' => 'Mr. Dorji',
            'phone' => '17775678',
            'email' => 'dorji@sjongkhar.gov.bt',
            'expected_participants' => 30
        ],
        [
            'organization' => 'Thimphu Tech Park',
            'date' => '2026-04-24',
            'status' => 'pending',
            'contact_person' => 'Ms. Sonam',
            'phone' => '17779012',
            'email' => 'sonam@ttp.gov.bt',
            'expected_participants' => 40
        ]
    ];

    foreach ($campRequests as $camp) {
        // Check if camp request already exists
        $stmt = $pdo->prepare("
            SELECT id FROM camp_requests 
            WHERE organization = ? AND date = ?
        ");
        $stmt->execute([$camp['organization'], $camp['date']]);
        
        if ($stmt->fetchColumn() === false) {
            // Insert new camp request
            $stmt = $pdo->prepare("
                INSERT INTO camp_requests 
                (organization, date, status, contact_person, phone, email, expected_participants, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $camp['organization'],
                $camp['date'],
                $camp['status'],
                $camp['contact_person'],
                $camp['phone'],
                $camp['email'],
                $camp['expected_participants']
            ]);
        }
    }

    // Add sample blood inventory data
    $inventoryItems = [
        ['blood_type' => 'A+', 'quantity' => 15],
        ['blood_type' => 'A-', 'quantity' => 8],
        ['blood_type' => 'B+', 'quantity' => 12],
        ['blood_type' => 'B-', 'quantity' => 5], // Low stock
        ['blood_type' => 'O+', 'quantity' => 20],
        ['blood_type' => 'O-', 'quantity' => 6], // Low stock
        ['blood_type' => 'AB+', 'quantity' => 10],
        ['blood_type' => 'AB-', 'quantity' => 4], // Low stock
    ];

    foreach ($inventoryItems as $item) {
        // Check if inventory item already exists
        $stmt = $pdo->prepare("
            SELECT id FROM blood_inventory WHERE blood_type = ?
        ");
        $stmt->execute([$item['blood_type']]);
        
        if ($stmt->fetchColumn() === false) {
            // Insert new inventory item
            $stmt = $pdo->prepare("
                INSERT INTO blood_inventory (blood_type, quantity, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$item['blood_type'], $item['quantity']]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Sample admin data added successfully',
        'data' => [
            'appointments_added' => count($appointments),
            'camp_requests_added' => count($campRequests),
            'inventory_items_added' => count($inventoryItems)
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
