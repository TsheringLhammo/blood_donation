<?php
// Test script to debug profile update issues
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/backend/config/db.php';

// Get JSON input
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!$data || !is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.', 'debug' => $jsonInput]);
    exit;
}

// For testing, let's use a hardcoded donor ID (you should change this)
$donorId = 1; // Replace with actual donor ID

// Define allowed fields
$allowedFields = [
    'full_name', 'email', 'phone', 'address', 'city', 'dzongkhag',
    'emergency_contact_name', 'emergency_contact_phone'
];

// Validate and sanitize input
$updateData = [];
$errors = [];

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        $value = trim($data[$field]);
        
        switch ($field) {
            case 'full_name':
                if (empty($value)) {
                    $errors[] = 'Full name is required.';
                } else {
                    $updateData[$field] = $value;
                }
                break;
                
            case 'email':
                if (empty($value)) {
                    $errors[] = 'Email is required.';
                } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format.';
                } else {
                    $updateData[$field] = $value;
                }
                break;
                
            case 'phone':
                if (empty($value)) {
                    $errors[] = 'Phone number is required.';
                } elseif (!preg_match('/^[17]\d{6}$/', $value)) {
                    $errors[] = 'Phone number must be 8 digits starting with 17 or 1.';
                } else {
                    $updateData[$field] = $value;
                }
                break;
                
            default:
                $updateData[$field] = $value ?: null;
                break;
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

if (empty($updateData)) {
    echo json_encode(['success' => false, 'message' => 'No valid fields to update.']);
    exit;
}

try {
    // Build the SQL query dynamically
    $setParts = [];
    $values = [];
    
    foreach ($updateData as $field => $value) {
        $setParts[] = "`$field` = ?";
        $values[] = $value;
    }
    
    // Add donor ID to values array for WHERE clause
    $values[] = $donorId;
    
    $sql = "UPDATE tbldonors SET " . implode(', ', $setParts) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($values);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found.', 'debug' => ['sql' => $sql, 'values' => $values]]);
        exit;
    }
    
    // Fetch the updated profile data
    $stmt = $pdo->prepare('SELECT * FROM tbldonors WHERE id = ?');
    $stmt->execute([$donorId]);
    $updatedProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$updatedProfile) {
        echo json_encode(['success' => false, 'message' => 'Profile updated but failed to retrieve updated data.']);
        exit;
    }
    
    // Remove sensitive data from response
    unset($updatedProfile['password']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Your profile has been updated successfully.',
        'data' => $updatedProfile
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
