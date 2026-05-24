<?php
declare(strict_types=1);

ini_set('display_errors', '0');

set_exception_handler(static function (Throwable $exception): void {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error while processing camp request.',
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
header('Access-Control-Allow-Headers: Content-Type');

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

$organizationName = trim((string)($payload['organizationName'] ?? ''));
$contactPerson = trim((string)($payload['contactPerson'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$dzongkhag = trim((string)($payload['dzongkhag'] ?? ''));
$campType = trim((string)($payload['campType'] ?? ''));
$venue = trim((string)($payload['venue'] ?? ''));
$preferredDate = trim((string)($payload['preferredDate'] ?? ''));
$alternateDate = trim((string)($payload['alternateDate'] ?? ''));
$expectedDonorsRaw = $payload['expectedDonors'] ?? '';
$facilities = trim((string)($payload['facilities'] ?? ''));
$additionalInfo = trim((string)($payload['additionalInfo'] ?? ''));

$requiredFields = [
    'organizationName' => $organizationName,
    'contactPerson' => $contactPerson,
    'phone' => $phone,
    'dzongkhag' => $dzongkhag,
    'campType' => $campType,
    'venue' => $venue,
    'preferredDate' => $preferredDate,
];

foreach ($requiredFields as $field => $value) {
    if ($value === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: {$field}",
        ]);
        exit;
    }
}

if (!preg_match('/^(16|17|77)\d{6}$/', $phone)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Phone number must be exactly 8 digits and start with 16, 17, or 77.',
    ]);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format.',
    ]);
    exit;
}

$expectedDonors = (int)$expectedDonorsRaw;
if ($expectedDonors < 20) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Expected donors must be at least 20.',
    ]);
    exit;
}

$preferredDateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
$preferredDateErrors = DateTime::getLastErrors();
if (
    !$preferredDateObj ||
    $preferredDateErrors['warning_count'] > 0 ||
    $preferredDateErrors['error_count'] > 0 ||
    $preferredDateObj->format('Y-m-d') !== $preferredDate
) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid preferredDate format. Use YYYY-MM-DD.',
    ]);
    exit;
}

$alternateDateValue = null;
if ($alternateDate !== '') {
    $alternateDateObj = DateTime::createFromFormat('Y-m-d', $alternateDate);
    $alternateDateErrors = DateTime::getLastErrors();
    if (
        !$alternateDateObj ||
        $alternateDateErrors['warning_count'] > 0 ||
        $alternateDateErrors['error_count'] > 0 ||
        $alternateDateObj->format('Y-m-d') !== $alternateDate
    ) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid alternateDate format. Use YYYY-MM-DD.',
        ]);
        exit;
    }
    $alternateDateValue = $alternateDate;
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

$tableName = 'tblblood_camps';

try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'tblblood_camps'");
    if (!$tableCheck || $tableCheck->rowCount() === 0) {
        $fallbackTableCheck = $pdo->query("SHOW TABLES LIKE 'blood_camps'");
        if ($fallbackTableCheck && $fallbackTableCheck->rowCount() > 0) {
            $tableName = 'blood_camps';
        }
    }
} catch (Throwable $exception) {
    // Keep default table if metadata check fails.
}

if ($email !== '') {
    try {
        $duplicateEmailStmt = $pdo->prepare("SELECT id FROM {$tableName} WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $duplicateEmailStmt->execute([':email' => $email]);
        if ($duplicateEmailStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'This email is already used for a camp request.',
            ]);
            exit;
        }
    } catch (Throwable $exception) {
        // If email checks fail due to schema issues, continue with insert and let DB constraints apply.
    }
}

try {
    $query = "INSERT INTO {$tableName}
        (organization_name, contact_person, phone_number, email, dzongkhag, camp_type, venue_address, preferred_date, alternate_date, expected_donors, facilities_available, additional_info)
        VALUES
        (:organization_name, :contact_person, :phone_number, :email, :dzongkhag, :camp_type, :venue_address, :preferred_date, :alternate_date, :expected_donors, :facilities_available, :additional_info)";

    $statement = $pdo->prepare($query);
    $statement->execute([
        ':organization_name' => $organizationName,
        ':contact_person' => $contactPerson,
        ':phone_number' => $phone,
        ':email' => $email !== '' ? $email : null,
        ':dzongkhag' => $dzongkhag,
        ':camp_type' => $campType,
        ':venue_address' => $venue,
        ':preferred_date' => $preferredDate,
        ':alternate_date' => $alternateDateValue,
        ':expected_donors' => $expectedDonors,
        ':facilities_available' => $facilities !== '' ? $facilities : null,
        ':additional_info' => $additionalInfo !== '' ? $additionalInfo : null,
    ]);

    http_response_code(201);
    // Best-effort: send confirmation email and create in-app notification for admins
    try {
        require_once __DIR__ . '/../config/mailer.php';
        require_once __DIR__ . '/workflow_helpers.php';

        $campId = (int)$pdo->lastInsertId();
        $subject = 'Camp request received';
        $textBody = "Dear {$contactPerson},\n\nWe have received your camp request (ID: {$campId}). We will review and contact you with next steps.\n\nRegards,\nBlood Transfusion Services";
        $htmlBody = "<p>Dear {$contactPerson},</p><p>We have received your camp request (ID: <strong>{$campId}</strong>). We will review and contact you with next steps.</p><p>Regards,<br/>Blood Transfusion Services</p>";

        $meta = [];
        if ($email !== '') {
            @bts_send_email($email, $subject, $htmlBody, $textBody, $meta);
        }

        try {
            $notif = [
                'role_target' => 'admin',
                'title' => 'New camp request',
                'message' => "Camp request #{$campId} from {$organizationName} ({$contactPerson})",
                'severity' => 'info',
                'channel' => 'in_app',
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            workflow_insert_notification($pdo, $notif);
        } catch (Throwable $_) {}
    } catch (Throwable $_) {
        // ignore notification/email errors
    }

    echo json_encode([
        'success' => true,
        'message' => 'Camp request submitted successfully.',
        'id' => (int)$pdo->lastInsertId(),
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not save camp request.',
        'error' => $exception->getMessage(),
    ]);
}
