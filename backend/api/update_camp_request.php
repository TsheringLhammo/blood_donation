<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Load config files
$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

// Verify authentication
bts_require_auth(['admin']);

// Get the JSON payload
$data = json_decode((string)file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing camp ID']);
    exit;
}

$campId = (int)$data['id'];

try {
    // Build the update query dynamically
    $updates = [];
    $values = [];
    
    // Map frontend field names to database column names
    $fieldMapping = [
        'organization' => 'organization_name',
        'contact_person' => 'contact_person',
        'phone' => 'phone_number',
        'email' => 'email',
        'date' => 'preferred_date',
        'expected_participants' => 'expected_donors'
    ];
    
    foreach ($fieldMapping as $frontendField => $dbColumn) {
        if (isset($data[$frontendField]) && $data[$frontendField] !== null && $data[$frontendField] !== '') {
            $updates[] = "{$dbColumn} = ?";
            $values[] = $data[$frontendField];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    // Add the ID to the values array
    $values[] = $campId;
    
    // Build the query using PDO
    $query = "UPDATE tblblood_camps SET " . implode(", ", $updates) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }
    
    if (!$stmt->execute($values)) {
        throw new Exception('Failed to execute statement');
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Camp request updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating camp: ' . $e->getMessage()
    ]);
}
?>


