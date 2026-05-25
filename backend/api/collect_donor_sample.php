<?php
/**
 * collect_donor_sample.php
 * Staff collects a blood sample from a donor
 * 
 * Settings: host=localhost, user=root, password="", database=blood_donation
 */

// Error reporting for debugging (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Database configuration
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'blood_donation';

if (file_exists(__DIR__ . '/../config/mailer.php')) {
    require_once __DIR__ . '/../config/mailer.php';
}

if (!function_exists('bts_send_email')) {
    function bts_send_email(...$args): bool
    {
        return false;
    }
}

// Connect to database
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $mysqli->connect_error
    ]);
    exit;
}

// Set charset
$mysqli->set_charset("utf8mb4");

function notification_column_exists(mysqli $mysqli, string $column): bool
{
    $safeColumn = $mysqli->real_escape_string($column);
    $query = "SHOW COLUMNS FROM tblnotifications LIKE '{$safeColumn}'";
    $result = $mysqli->query($query);
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function insert_donor_notification(mysqli $mysqli, int $donorId, string $title, string $message, string $createdAt): void
{
    $payload = [];
    $columns = [];
    $types = '';
    $values = [];

    $payload['title'] = $title;
    $payload['message'] = $message;
    $payload['is_read'] = 0;
    $payload['created_at'] = $createdAt;
    $payload['updated_at'] = $createdAt;
    $payload['type'] = 'appointment';
    $payload['severity'] = 'info';
    $payload['channel'] = 'in_app';
    $payload['role_target'] = 'donor';
    $payload['user_id'] = $donorId;
    $payload['donor_id'] = $donorId;

    foreach ($payload as $column => $value) {
        if (!notification_column_exists($mysqli, $column)) {
            continue;
        }
        $columns[] = $column;
        if (is_int($value)) {
            $types .= 'i';
        } else {
            $types .= 's';
        }
        $values[] = $value;
    }

    if (empty($columns)) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO tblnotifications (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return;
    }

    $bindValues = [];
    foreach ($values as $index => $value) {
        $bindValues[$index] = $value;
    }
    $bindParams = [&$types];
    foreach ($bindValues as &$value) {
        $bindParams[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $stmt->close();
}

// Get and decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if input is valid
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload. Please send valid JSON data.'
    ]);
    $mysqli->close();
    exit;
}

// Extract values
$donorId = isset($input['donorId']) ? (int)$input['donorId'] : 0;
$collectionDate = isset($input['collectionDate']) ? trim($input['collectionDate']) : date('Y-m-d');
$collectionTime = isset($input['collectionTime']) ? trim((string)$input['collectionTime']) : '';
$technician = isset($input['technician']) ? trim($input['technician']) : '';

// Validate inputs
if ($donorId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid donor ID. Please select a valid donor.'
    ]);
    $mysqli->close();
    exit;
}

if (empty($technician)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Technician name is required.'
    ]);
    $mysqli->close();
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $collectionDate)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format. Use YYYY-MM-DD.'
    ]);
    $mysqli->close();
    exit;
}

if ($collectionTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $collectionTime)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid time format. Use HH:MM.'
    ]);
    $mysqli->close();
    exit;
}

try {
    // Step 1: Check if donor exists
    $checkDonorQuery = "SELECT id, full_name, email, blood_type, status FROM tbldonors WHERE id = ?";
    $stmt = $mysqli->prepare($checkDonorQuery);
    $stmt->bind_param("i", $donorId);
    $stmt->execute();
    $donorResult = $stmt->get_result();
    $donor = $donorResult->fetch_assoc();
    $stmt->close();

    if (!$donor) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Donor not found. Please check the donor ID.'
        ]);
        $mysqli->close();
        exit;
    }

    // Step 2: Check for duplicate sample (same donor, same date)
    $checkDuplicateQuery = "SELECT id, status FROM tbldonor_samples WHERE donor_id = ? AND collection_date = ? LIMIT 1";
    $stmt = $mysqli->prepare($checkDuplicateQuery);
    $stmt->bind_param("is", $donorId, $collectionDate);
    $stmt->execute();
    $duplicateResult = $stmt->get_result();
    $existingSample = $duplicateResult->fetch_assoc();
    $stmt->close();

    if ($existingSample) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A sample has already been collected for this donor on ' . $collectionDate . '.',
            'data' => [
                'existing_sample_id' => $existingSample['id'],
                'existing_status' => $existingSample['status']
            ]
        ]);
        $mysqli->close();
        exit;
    }

    // Step 3: Insert new sample record
    $status = 'collected'; // 'collected', 'tested', 'approved', 'rejected'
    $createdAt = date('Y-m-d H:i:s');
    
    $insertQuery = "INSERT INTO tbldonor_samples (donor_id, collection_date, technician, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($insertQuery);
    $stmt->bind_param("isssss", $donorId, $collectionDate, $technician, $status, $createdAt, $createdAt);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert sample: " . $stmt->error);
    }
    
    $sampleId = $stmt->insert_id;
    $stmt->close();

    // Step 4: Update donor record to reflect sample has been taken
    $updateDonorQuery = "UPDATE tbldonors SET 
                         sample_tested = 'collected',
                         sample_tested_at = ?,
                         workflow_status = 'blood_drawn_pending_test'
                         WHERE id = ?";
    $stmt = $mysqli->prepare($updateDonorQuery);
    $stmt->bind_param("si", $createdAt, $donorId);
    $stmt->execute();
    $stmt->close();

    $formattedDate = date('F j, Y', strtotime($collectionDate));
    $displayTime = date('g:i A', strtotime($createdAt));
    if ($collectionTime !== '') {
        $timeObj = DateTime::createFromFormat('H:i', $collectionTime);
        $displayTime = $timeObj ? $timeObj->format('g:i A') : $collectionTime;
    }

    $notificationTitle = 'SAMPLE COLLECTION APPOINTMENT CONFIRMED';
    $notificationMessage = "SAMPLE COLLECTION APPOINTMENT CONFIRMED\n\n"
        . "Dear {$donor['full_name']},\n\n"
        . "Your sample collection appointment has been confirmed.\n\n"
        . "This is a small blood sample (about 5ml) for testing.\n"
        . "We will test for:\n\n"
        . "HIV\n\n"
        . "Hepatitis B & C\n\n"
        . "Syphilis\n\n"
        . "Malaria\n\n"
        . "...and many more.\n\n"
        . "Blood Bank: National Blood Bank, Thimphu\n"
        . "Date: {$formattedDate}\n"
        . "Time: {$displayTime}\n\n"
        . "Please eat a light meal before coming. If you cannot attend, please inform us at least 24 hours in advance.\n\n"
        . "Thank you for your cooperation.\n\n"
        . "Regards,\n"
        . "Blood Transfusion Services";

    insert_donor_notification(
        $mysqli,
        $donorId,
        $notificationTitle,
        $notificationMessage,
        $createdAt
    );

    $donorEmail = trim((string)($donor['email'] ?? ''));
    if ($donorEmail !== '' && filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
        $emailSubject = 'SAMPLE COLLECTION APPOINTMENT CONFIRMED';
        $emailText = $notificationMessage;
        $emailHtml = nl2br(htmlspecialchars($notificationMessage, ENT_QUOTES, 'UTF-8'));
        bts_send_email($donorEmail, $emailSubject, $emailHtml, $emailText);
    }

    // Step 5: Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Blood sample collected successfully for ' . $donor['full_name'] . '.',
        'data' => [
            'sample_id' => $sampleId,
            'donor_id' => $donorId,
            'donor_name' => $donor['full_name'],
            'donor_blood_type' => $donor['blood_type'],
            'collection_date' => $collectionDate,
            'collection_time' => $collectionTime,
            'technician' => $technician,
            'status' => $status,
            'created_at' => $createdAt
        ]
    ]);

} catch (Exception $e) {
    error_log("collect_donor_sample.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log("collect_donor_sample.php fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}

// Close connection
$mysqli->close();
?>