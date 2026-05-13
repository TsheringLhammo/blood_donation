<?php
declare(strict_types=1);

ini_set('display_errors', '0');

/**
 * Send appointment confirmation email to donor
 */
function sendConfirmationEmail(string $email, string $donorName, string $date, string $time, string $bloodBank): void {
    $subject = "Appointment Confirmation - Blood Donation";
    
    $timeDisplay = !empty($time) ? "⏰ Time: {$time}" : "⏰ Time: To be confirmed";
    $message = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #C8102E; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
        .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #C8102E; }
        .details p { margin: 8px 0; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🩸 Appointment Confirmation</h2>
        </div>
        <div class="content">
            <p>Dear <strong>{$donorName}</strong>,</p>
            
            <p>Thank you for booking a blood donation appointment with us. Your appointment has been successfully recorded.</p>
            
            <div class="details">
                <h3>Appointment Details:</h3>
                <p><strong>📅 Date:</strong> {$date}</p>
                <p><strong>{$timeDisplay}</strong></p>
                <p><strong>🏥 Blood Bank:</strong> {$bloodBank}</p>
            </div>
            
            <p><strong>Before Your Donation:</strong></p>
            <ul>
                <li>Get a good night's sleep</li>
                <li>Eat a healthy meal</li>
                <li>Drink plenty of water</li>
                <li>Bring your CID card</li>
                <li>Arrive 15 minutes early</li>
            </ul>
            
            <p><strong>Please avoid:</strong></p>
            <ul>
                <li>Alcohol for 24 hours before donation</li>
                <li>Strenuous exercise on the day of donation</li>
            </ul>
            
            <p>If you need to reschedule or cancel, please log in to your account or contact us at the blood bank.</p>
            
            <p>Thank you for saving lives! 💪</p>
            
            <p>Best regards,<br>
            <strong>National Blood Transfusion Services</strong><br>
            Ministry of Health, Bhutan</p>
            
            <div class="footer">
                <p>Helpline: 1095 | Available 24/7</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@blood-transfusion.gov.bt" . "\r\n";
    
    @mail($email, $subject, $message, $headers);
}

set_exception_handler(static function (Throwable $exception): void {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error while processing appointment request.',
        'error' => $exception->getMessage(),
    ]);
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

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
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.',
    ]);
    exit;
}

$fullName = trim((string)($payload['fullName'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$preferredDate = trim((string)($payload['preferredDate'] ?? ''));
$bloodBank = trim((string)($payload['bloodBank'] ?? ''));
$ageRaw = $payload['age'] ?? null;
$gender = trim((string)($payload['gender'] ?? ''));
$bloodGroup = trim((string)($payload['bloodGroup'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$preferredTime = trim((string)($payload['preferredTime'] ?? ''));

$normalizedPhone = preg_replace('/\D+/', '', $phone ?? '');
if (!is_string($normalizedPhone)) {
    $normalizedPhone = '';
}
$phone = $normalizedPhone;

if ($fullName === '' || $preferredDate === '' || $bloodBank === '' || $gender === '' || $email === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: fullName, email, gender, preferredDate, bloodBank.',
    ]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format.',
    ]);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $preferredDate);
$dateErrors = DateTime::getLastErrors();
if (
    !$date ||
    $dateErrors['warning_count'] > 0 ||
    $dateErrors['error_count'] > 0 ||
    $date->format('Y-m-d') !== $preferredDate
) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid preferredDate format. Use YYYY-MM-DD.',
    ]);
    exit;
}

$age = null;
if ($ageRaw !== null && $ageRaw !== '') {
    $age = (int)$ageRaw;
    if ($age < 18 || $age > 60) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Age must be between 18 and 60.',
        ]);
        exit;
    }
}

if ($phone !== '' && !preg_match('/^(16|17|77)\d{6}$/', $phone)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Phone number must be 8 digits and start with 16, 17, or 77.',
    ]);
    exit;
}

$allowedBloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if ($bloodGroup !== '' && !in_array($bloodGroup, $allowedBloodGroups, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid blood group value.',
    ]);
    exit;
}

$allowedGenders = ['Male', 'Female', 'Other'];
$gender = ucfirst(strtolower(trim((string)$gender)));
if (!in_array($gender, $allowedGenders, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid gender value.',
    ]);
    exit;
}

if (strlen($fullName) > 120 || strlen($bloodBank) > 255 || strlen($preferredTime) > 20) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'One or more fields are too long.',
    ]);
    exit;
}

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database config not found at: ' . $dbPath,
    ]);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['donor']);
$userId = null;
$authenticatedUserName = '';
if (is_array($claims)) {
    $candidateUserId = (int)($claims['sub'] ?? 0);
    if ($candidateUserId > 0) {
        $userId = $candidateUserId;

        try {
            $userStmt = $pdo->prepare('SELECT name FROM tblusers WHERE id = ? LIMIT 1');
            $userStmt->execute([$candidateUserId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            $authenticatedUserName = trim((string)($userRow['name'] ?? ''));
        } catch (Throwable $exception) {
            // Keep fallback behavior if user lookup fails.
        }
    }
}

if ($userId === null) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Donor login is required to book an appointment.',
    ]);
    exit;
}

if ($fullName === '' && $authenticatedUserName !== '') {
    $fullName = $authenticatedUserName;
}

$donorStatus = 'Pending';
$donorDeferred = 0;
try {
    $donorMetaStmt = $pdo->prepare('SELECT COALESCE(status, "Pending") AS status, COALESCE(deferred, 0) AS deferred FROM tbldonors WHERE email = ? LIMIT 1');
    $donorMetaStmt->execute([$claims['email'] ?? '']);
    $donorMeta = $donorMetaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $donorStatus = strtolower(trim((string)($donorMeta['status'] ?? 'Pending')));
    $donorDeferred = (int)($donorMeta['deferred'] ?? 0);
} catch (Throwable $exception) {
    // If donor metadata lookup fails, fall back to safe denial below.
}

if ($donorDeferred === 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You are currently not eligible to book an appointment at this time.',
    ]);
    exit;
}

// Accept various approved statuses that indicate donor is eligible to book
$approvedStatuses = ['active', 'confirmed', 'approved', 'pending', 'approved for blood d', 'ready'];
$isApprovedStatus = false;
foreach ($approvedStatuses as $approvedStatus) {
    if (stripos($donorStatus, trim($approvedStatus)) !== false) {
        $isApprovedStatus = true;
        break;
    }
}

// If status check fails, also check workflow_status as fallback
if (!$isApprovedStatus) {
    try {
        $workflowStmt = $pdo->prepare('SELECT workflow_status FROM tbldonors WHERE email = ? LIMIT 1');
        $workflowStmt->execute([$claims['email'] ?? '']);
        $workflowResult = $workflowStmt->fetch(PDO::FETCH_ASSOC);
        $workflowStatus = strtolower(trim((string)($workflowResult['workflow_status'] ?? '')));
        
        // Only reject if workflow_status is NOT approved for donation
        if ($workflowStatus !== 'decision_made_accepted') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Your donor registration is not active yet.',
            ]);
            exit;
        }
    } catch (Throwable $exception) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your donor registration is not active yet.',
        ]);
        exit;
    }
}

$tableName = 'tblappointments';
$phoneColumn = 'phone_number';
$hasUserIdColumn = false;
$hasGenderColumn = false;
$hasEmailColumn = false;

try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'tblappointments'");
    if (!$tableCheck || $tableCheck->rowCount() === 0) {
        $legacyTableCheck = $pdo->query("SHOW TABLES LIKE 'appointments'");
        if ($legacyTableCheck && $legacyTableCheck->rowCount() > 0) {
            $tableName = 'appointments';
            $phoneColumn = 'phone';
        }
    }

    $phoneNumberColumnCheck = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'phone_number'");
    if ($phoneNumberColumnCheck && $phoneNumberColumnCheck->rowCount() > 0) {
        $phoneColumn = 'phone_number';
    } else {
        $phoneColumn = 'phone';
    }

    $columnCheck = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'user_id'");
    if ($columnCheck && $columnCheck->rowCount() > 0) {
        $hasUserIdColumn = true;
    }

    $genderColumnCheck = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'gender'");
    if ($genderColumnCheck && $genderColumnCheck->rowCount() > 0) {
        $hasGenderColumn = true;
    }

    $emailColumnCheck = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'email'");
    if ($emailColumnCheck && $emailColumnCheck->rowCount() > 0) {
        $hasEmailColumn = true;
    }
} catch (Throwable $exception) {
    // Fall back to default table names if metadata check fails.
}

try {
    $columns = [];
    $values = [];

    if ($hasUserIdColumn) {
        $columns[] = 'user_id';
        $values[] = ':user_id';
    }
    $columns[] = 'full_name';
    $values[] = ':full_name';
    if ($hasEmailColumn) {
        $columns[] = 'email';
        $values[] = ':email';
    }
    $columns[] = 'age';
    $values[] = ':age';
    if ($hasGenderColumn) {
        $columns[] = 'gender';
        $values[] = ':gender';
    }
    $columns[] = 'blood_group';
    $values[] = ':blood_group';
    $columns[] = $phoneColumn;
    $values[] = ':phone';
    $columns[] = 'preferred_date';
    $values[] = ':preferred_date';
    $columns[] = 'preferred_time';
    $values[] = ':preferred_time';
    $columns[] = 'blood_bank';
    $values[] = ':blood_bank';

    $query = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";

    $statement = $pdo->prepare($query);
    $params = [
        ':full_name' => $fullName,
        ':age' => $age,
        ':gender' => $gender,
        ':blood_group' => $bloodGroup !== '' ? $bloodGroup : null,
        ':phone' => $phone !== '' ? $phone : null,
        ':preferred_date' => $preferredDate,
        ':preferred_time' => $preferredTime !== '' ? $preferredTime : null,
        ':blood_bank' => $bloodBank,
    ];
    if ($hasEmailColumn) {
        $params[':email'] = $email;
    }
    if ($hasUserIdColumn) {
        $params[':user_id'] = $userId;
    }
    $statement->execute($params);
    $appointmentId = (int)$pdo->lastInsertId();

    // Send confirmation email
    try {
        sendConfirmationEmail($email, $fullName, $preferredDate, $preferredTime, $bloodBank);
    } catch (Throwable $e) {
        // Log but don't fail the appointment booking if email fails
        error_log("Email send failed for appointment {$appointmentId}: " . $e->getMessage());
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Appointment saved successfully.',
        'id' => $appointmentId,
        'table' => $tableName,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not save appointment.',
        'error' => $exception->getMessage(),
    ]);
}
