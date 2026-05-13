<?php
// Simple test API for profile updates
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Get JSON input
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

// Log the input for debugging
error_log("Profile update request: " . $jsonInput);

// Simulate success for testing
echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully (test mode)',
    'data' => [
        'full_name' => $data['full_name'] ?? 'Test User',
        'email' => $data['email'] ?? 'test@example.com',
        'phone' => $data['phone'] ?? '12345678',
        'address' => $data['address'] ?? 'Test Address',
        'city' => $data['city'] ?? 'Test City',
        'dzongkhag' => $data['dzongkhag'] ?? 'Thimphu',
        'emergency_contact_name' => $data['emergency_contact_name'] ?? 'Emergency Contact',
        'emergency_contact_phone' => $data['emergency_contact_phone'] ?? '87654321'
    ]
]);
?>
