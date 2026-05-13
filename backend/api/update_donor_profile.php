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

bts_require_auth(['donor']);

// Get the logged-in donor's ID from the session/token
$donorId = $_SESSION['user_id'] ?? null;
if (!$donorId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Get JSON input
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

// Define allowed fields for editing (security measure)
$allowedFields = [
    'full_name',
    'email', 
    'phone',
    'address',
    'city',
    'dzongkhag',
    'emergency_contact_name',
    'emergency_contact_phone'
];

// Validate and sanitize input
$updateData = [];
$errors = [];

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        $value = trim($data[$field]);
        
        // Validation rules
        switch ($field) {
            case 'full_name':
                if (empty($value)) {
                    $errors[] = 'Full name is required.';
                } elseif (strlen($value) > 255) {
                    $errors[] = 'Full name is too long (max 255 characters).';
                } else {
                    $updateData[$field] = $value;
                }
                break;
                
            case 'email':
                if (empty($value)) {
                    $errors[] = 'Email is required.';
                } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format.';
                } elseif (strlen($value) > 255) {
                    $errors[] = 'Email is too long (max 255 characters).';
                } else {
                    // Check if email is already used by another donor
                    $stmt = $pdo->prepare('SELECT id FROM tbldonors WHERE email = ? AND id != ?');
                    $stmt->execute([$value, $donorId]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Email is already in use by another account.';
                    } else {
                        $updateData[$field] = $value;
                    }
                }
                break;
                
            case 'phone':
                if (empty($value)) {
                    $errors[] = 'Phone number is required.';
                } elseif (!preg_match('/^[17]\d{6}$/', $value)) {
                    $errors[] = 'Phone number must be 8 digits starting with 17 or 1.';
                } else {
                    // Check if phone is already used by another donor
                    $stmt = $pdo->prepare('SELECT id FROM tbldonors WHERE phone = ? AND id != ?');
                    $stmt->execute([$value, $donorId]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Phone number is already in use by another account.';
                    } else {
                        $updateData[$field] = $value;
                    }
                }
                break;
                
            case 'address':
            case 'city':
            case 'dzongkhag':
            case 'emergency_contact_name':
                if (strlen($value) > 255) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is too long (max 255 characters).';
                } else {
                    $updateData[$field] = $value ?: null;
                }
                break;
                
            case 'emergency_contact_phone':
                if (!empty($value) && !preg_match('/^[17]\d{6}$/', $value)) {
                    $errors[] = 'Emergency contact phone must be 8 digits starting with 17 or 1.';
                } else {
                    $updateData[$field] = $value ?: null;
                }
                break;
        }
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

if (empty($updateData)) {
    http_response_code(400);
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
    $stmt->execute($values);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found.']);
        exit;
    }
    
    // Fetch the updated profile data
    $stmt = $pdo->prepare('SELECT * FROM tbldonors WHERE id = ?');
    $stmt->execute([$donorId]);
    $updatedProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$updatedProfile) {
        http_response_code(500);
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
