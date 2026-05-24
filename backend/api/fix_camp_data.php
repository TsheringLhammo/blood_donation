<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    // First, let's see what data currently exists
    echo "=== Current Camp Requests ===\n";
    $stmt = $pdo->query("SELECT * FROM camp_requests");
    $camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($camps as $camp) {
        echo "ID: {$camp['id']}\n";
        echo "Organization: " . ($camp['organization'] ?? 'NULL') . "\n";
        echo "Date: " . ($camp['date'] ?? 'NULL') . "\n";
        echo "Contact: " . ($camp['contact_person'] ?? 'NULL') . "\n";
        echo "Phone: " . ($camp['phone'] ?? 'NULL') . "\n";
        echo "Email: " . ($camp['email'] ?? 'NULL') . "\n";
        echo "Participants: " . ($camp['expected_participants'] ?? 'NULL') . "\n";
        echo "Status: " . ($camp['status'] ?? 'NULL') . "\n";
        echo "---\n";
    }
    
    // Now update any incomplete records
    echo "\n=== Updating incomplete records ===\n";
    
    $updates = [
        [
            'organization' => 'Royal Institute of Management',
            'date' => '2026-04-02',
            'contact_person' => 'Dr. Tashi',
            'phone' => '17771234',
            'email' => 'tashi@rim.edu.bt',
            'expected_participants' => 50,
            'status' => 'confirmed'
        ],
        [
            'organization' => 'S/Jongkhar',
            'date' => '2026-04-24',
            'contact_person' => 'Mr. Dorji',
            'phone' => '17775678',
            'email' => 'dorji@sjongkhar.gov.bt',
            'expected_participants' => 30,
            'status' => 'pending'
        ],
        [
            'organization' => 'Thimphu Tech Park',
            'date' => '2026-04-24',
            'contact_person' => 'Ms. Sonam',
            'phone' => '17779012',
            'email' => 'sonam@ttp.gov.bt',
            'expected_participants' => 40,
            'status' => 'pending'
        ]
    ];
    
    foreach ($updates as $update) {
        // Check if record with this organization and date exists
        $stmt = $pdo->prepare("
            SELECT id FROM camp_requests 
            WHERE organization = ? AND date = ?
        ");
        $stmt->execute([$update['organization'], $update['date']]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE camp_requests 
                SET contact_person = ?, phone = ?, email = ?, expected_participants = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $update['contact_person'],
                $update['phone'],
                $update['email'],
                $update['expected_participants'],
                $update['status'],
                $existingId
            ]);
            echo "Updated ID {$existingId}: {$update['organization']}\n";
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO camp_requests 
                (organization, date, status, contact_person, phone, email, expected_participants, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $update['organization'],
                $update['date'],
                $update['status'],
                $update['contact_person'],
                $update['phone'],
                $update['email'],
                $update['expected_participants']
            ]);
            echo "Inserted: {$update['organization']}\n";
        }
    }
    
    echo "\n✓ Database updated successfully!\n";
    echo "\nRefresh your browser to see the changes.";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
    http_response_code(500);
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage();
    http_response_code(500);
}
?>
