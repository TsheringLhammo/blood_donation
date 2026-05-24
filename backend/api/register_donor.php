<?php
declare(strict_types=1);

ini_set('display_errors', '0');

set_exception_handler(static function (Throwable $exception): void {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error while processing donor registration.',
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
    $payload = $_POST;
}

if (!is_array($payload) || empty($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request payload.',
    ]);
    exit;
}

// --- Debug logging (temporary) -------------------------------------------
$enableDebug = (isset($_GET['debug']) && $_GET['debug'] === '1') || (!empty($payload['__debug']));
if ($enableDebug) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/register_donor_debug.log';
    $logEntry = "---- REGISTER DEBUG " . date('c') . " ----\n";
    $logEntry .= "REMOTE_ADDR=" . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . "\n";
    $logEntry .= "RAW_INPUT=" . substr($rawInput ?? '', 0, 2000) . "\n";
    $logEntry .= "PAYLOAD_KEYS=" . implode(',', array_keys($payload)) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
// -------------------------------------------------------------------------

$toBool = static function ($value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;
};

$healthDeclaration = is_array($payload['healthDeclaration'] ?? null) ? $payload['healthDeclaration'] : [];

$fullName    = trim((string)($payload['fullName']    ?? ''));
$email       = trim((string)($payload['email']       ?? ''));
$password    = (string)($payload['password']         ?? '');
$phone       = trim((string)($payload['phone']       ?? ''));
$cidNumber   = trim((string)($payload['cidNumber']   ?? ($payload['cid_number'] ?? '')));
$dateOfBirth = trim((string)($payload['dateOfBirth'] ?? ''));
$gender      = trim((string)($payload['gender']      ?? ''));
$bloodType   = trim((string)($payload['bloodType']   ?? ''));
$address     = trim((string)($payload['address']     ?? ''));
$city        = trim((string)($payload['city']        ?? ''));
$dzongkhag   = trim((string)($payload['dzongkhag']   ?? ''));
$weightRaw   = $payload['weight'] ?? null;
$lastDonationDate = trim((string)($payload['lastDonationDate'] ?? ''));

$healthTattoo = $toBool($payload['health_tattoo'] ?? ($healthDeclaration['no_tattoo_piercing_acupuncture_last_6_months'] ?? false));
$healthAntibiotics = $toBool($payload['health_antibiotics'] ?? ($healthDeclaration['not_taking_antibiotics_or_blood_thinners'] ?? false));
$healthSurgery = $toBool($payload['health_surgery'] ?? ($healthDeclaration['no_surgery_last_6_months'] ?? false));
$healthNoColdFlu = $toBool($payload['health_no_cold_flu'] ?? ($payload['health_no_cold_flush'] ?? ($healthDeclaration['no_cold_flu_or_fever_today'] ?? false)));
$consentMedical = $toBool($payload['consent_medical'] ?? ($payload['consent'] ?? false));

$emergencyContactName = trim((string)($payload['emergencyContactName'] ?? ($payload['emergency_contact_name'] ?? '')));
$emergencyContactPhone = trim((string)($payload['emergencyContactPhone'] ?? ($payload['emergency_contact_phone'] ?? '')));
$weight = is_numeric($weightRaw) ? (float)$weightRaw : 0.0;

$isValidDate = static function (string $value): bool {
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    return $date instanceof DateTime && ($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0 && $date->format('Y-m-d') === $value;
};

$requiredFields = [
    'fullName' => $fullName,
    'email' => $email,
    'password' => $password,
    'phone' => $phone,
    'cidNumber' => $cidNumber,
    'dateOfBirth' => $dateOfBirth,
    'gender' => $gender,
    'bloodType' => $bloodType,
    'address' => $address,
    'city' => $city,
    'dzongkhag' => $dzongkhag,
    'weight' => $weightRaw,
    'emergencyContactName' => $emergencyContactName,
    'emergencyContactPhone' => $emergencyContactPhone,
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

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

if (!preg_match('/^(16|17|77)\d{6}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 8 digits and start with 16, 17, or 77.']);
    exit;
}

if (!preg_match('/^\d{11}$/', $cidNumber)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'CID must be 11 digits']);
    exit;
}

if ($weight < 45) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Weight must be at least 45 kg.']);
    exit;
}

if ($lastDonationDate !== '' && !$isValidDate($lastDonationDate)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Last donation date must be a valid date.']);
    exit;
}

if (!preg_match('/^(16|17|77)\d{6}$/', $emergencyContactPhone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Emergency contact phone must be exactly 8 digits and start with 16, 17, or 77.']);
    exit;
}

$allowedGenders = ['Male', 'Female', 'Other'];
$gender = ucfirst(strtolower(trim((string)$gender)));
if (!in_array($gender, $allowedGenders, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid gender value.']);
    exit;
}

$healthFailures = [];
if (!$healthTattoo) {
    $healthFailures[] = 'No tattoo/piercing/acupuncture (last 6 months)';
}
if (!$healthAntibiotics) {
    $healthFailures[] = 'No antibiotics/blood thinners';
}
if (!$healthSurgery) {
    $healthFailures[] = 'No surgery (last 6 months)';
}
if (!$healthNoColdFlu) {
    $healthFailures[] = 'No cold/flu/fever today';
}

if (!empty($healthFailures)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Health declaration incomplete: ' . implode(', ', $healthFailures),
    ]);
    exit;
}

if (!$consentMedical) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Medical consent is required.']);
    exit;
}

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database config not found at: ' . $dbPath]);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

function donor_column_exists(PDO $pdo, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM tbldonors LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

function user_column_exists(PDO $pdo, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM tblusers LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

function missing_donor_columns(PDO $pdo, array $columns): array
{
    $missing = [];
    foreach ($columns as $column) {
        if (!donor_column_exists($pdo, $column)) {
            $missing[] = $column;
        }
    }
    return $missing;
}

try {
    if (!donor_column_exists($pdo, 'cid_number')) {
        $pdo->exec("ALTER TABLE tbldonors ADD COLUMN cid_number VARCHAR(11) UNIQUE DEFAULT NULL AFTER phone");
    }

    $healthNoColdColumn = donor_column_exists($pdo, 'health_no_cold_flush') ? 'health_no_cold_flush' : (donor_column_exists($pdo, 'health_no_cold_flu') ? 'health_no_cold_flu' : '');
    $consentColumn = donor_column_exists($pdo, 'consent') ? 'consent' : (donor_column_exists($pdo, 'consent_medical') ? 'consent_medical' : '');

    // Do not abort if optional donor columns are missing. Instead proceed and only
    // include columns that exist in the INSERT. This avoids blocking registration
    // when the DB schema is slightly different between installs.
    if ($enableDebug && ($healthNoColdColumn === '' || $consentColumn === '')) {
        $logFile = __DIR__ . '/../logs/register_donor_debug.log';
        $warnEntry = "WARN: optional donor columns missing: healthNoCold={$healthNoColdColumn} consent={$consentColumn} time=" . date('c') . "\n";
        @file_put_contents($logFile, $warnEntry, FILE_APPEND | LOCK_EX);
    }

    $requiredDonorColumns = [
        'gender',
        'weight',
        'last_donation_date',
        'health_tattoo',
        'health_antibiotics',
        'health_surgery',
        $healthNoColdColumn,
        $consentColumn,
        'emergency_contact_name',
        'emergency_contact_phone',
    ];
    $requiredDonorColumns = array_values(array_filter($requiredDonorColumns));

    // Check which of the commonly required donor columns actually exist. If some
    // are missing, remove them from the list and continue. This makes the
    // registration endpoint tolerant to schema variations while still saving any
    // available donor fields.
    $missingColumns = missing_donor_columns($pdo, $requiredDonorColumns);
    if (!empty($missingColumns)) {
        if ($enableDebug) {
            $logFile = __DIR__ . '/../logs/register_donor_debug.log';
            $warnEntry = "WARN: missing donor columns: " . implode(', ', $missingColumns) . " time=" . date('c') . "\n";
            @file_put_contents($logFile, $warnEntry, FILE_APPEND | LOCK_EX);
        }
        // Remove missing columns from required list so we don't attempt to insert them.
        $requiredDonorColumns = array_values(array_diff($requiredDonorColumns, $missingColumns));
    }

    // If a login account already exists for this email, allow creating the donor
    // record only if there is no donor row yet. This handles cases where an
    // account was pre-created but the donor profile wasn't saved.
    $existingUserStmt = $pdo->prepare('SELECT id FROM tblusers WHERE email = ? LIMIT 1');
    $existingUserStmt->execute([$email]);
    $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingUser) {
        // Check if donor row already exists for this email
        $donorExistsStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE email = ? LIMIT 1');
        $donorExistsStmt->execute([$email]);
        if ($donorExistsStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email already has a donor profile. Please sign in.']);
            exit;
        }
        // We have an existing login but no donor row. We'll proceed to create the donor
        // and reuse the existing user id (skip creating a new user record below).
        $existingUserId = (int)$existingUser['id'];
    } else {
        $existingUserId = 0;
    }

    $cidExistsStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE cid_number = ? LIMIT 1');
    $cidExistsStmt->execute([$cidNumber]);
    if ($cidExistsStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This CID is already registered.']);
        exit;
    }

    $pdo->beginTransaction();

    $donorColumns = [
        'full_name', 'email', 'phone', 'cid_number', 'date_of_birth', 'blood_type', 'address', 'city', 'dzongkhag',
        'gender', 'weight', 'last_donation_date',
        'health_tattoo', 'health_antibiotics', 'health_surgery',
        'emergency_contact_name', 'emergency_contact_phone'
    ];
    $donorPlaceholders = [
        ':full_name', ':email', ':phone', ':cid_number', ':date_of_birth', ':blood_type', ':address', ':city', ':dzongkhag',
        ':gender', ':weight', ':last_donation_date',
        ':health_tattoo', ':health_antibiotics', ':health_surgery',
        ':emergency_contact_name', ':emergency_contact_phone'
    ];
    $params = [
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':cid_number' => $cidNumber,
        ':date_of_birth' => $dateOfBirth,
        ':blood_type' => $bloodType,
        ':address' => $address,
        ':city' => $city,
        ':dzongkhag' => $dzongkhag,
        ':gender' => $gender,
        ':weight' => $weight,
        ':last_donation_date' => $lastDonationDate !== '' ? $lastDonationDate : null,
        ':health_tattoo' => $healthTattoo ? 1 : 0,
        ':health_antibiotics' => $healthAntibiotics ? 1 : 0,
        ':health_surgery' => $healthSurgery ? 1 : 0,
        ':emergency_contact_name' => $emergencyContactName,
        ':emergency_contact_phone' => $emergencyContactPhone,
    ];

    if ($healthNoColdColumn !== '') {
        $donorColumns[] = $healthNoColdColumn;
        $donorPlaceholders[] = ':health_no_cold';
        $params[':health_no_cold'] = $healthNoColdFlu ? 1 : 0;
    }

    if ($consentColumn !== '') {
        $donorColumns[] = $consentColumn;
        $donorPlaceholders[] = ':consent_col';
        $params[':consent_col'] = $consentMedical ? 1 : 0;
    }

    // Keep both legacy/new columns in sync when both exist.
    if ($healthNoColdColumn === 'health_no_cold_flu' && donor_column_exists($pdo, 'health_no_cold_flush')) {
        $donorColumns[] = 'health_no_cold_flush';
        $donorPlaceholders[] = ':health_no_cold_flush';
        $params[':health_no_cold_flush'] = $healthNoColdFlu ? 1 : 0;
    }
    if ($healthNoColdColumn === 'health_no_cold_flush' && donor_column_exists($pdo, 'health_no_cold_flu')) {
        $donorColumns[] = 'health_no_cold_flu';
        $donorPlaceholders[] = ':health_no_cold_flu';
        $params[':health_no_cold_flu'] = $healthNoColdFlu ? 1 : 0;
    }
    if ($consentColumn === 'consent' && donor_column_exists($pdo, 'consent_medical')) {
        $donorColumns[] = 'consent_medical';
        $donorPlaceholders[] = ':consent_medical';
        $params[':consent_medical'] = $consentMedical ? 1 : 0;
    }
    if ($consentColumn === 'consent_medical' && donor_column_exists($pdo, 'consent')) {
        $donorColumns[] = 'consent';
        $donorPlaceholders[] = ':consent';
        $params[':consent'] = $consentMedical ? 1 : 0;
    }

    if (donor_column_exists($pdo, 'health_declaration')) {
        $donorColumns[] = 'health_declaration';
        $donorPlaceholders[] = ':health_declaration';
        $params[':health_declaration'] = json_encode([
            'no_tattoo_piercing_acupuncture_last_6_months' => $healthTattoo,
            'not_taking_antibiotics_or_blood_thinners' => $healthAntibiotics,
            'no_surgery_last_6_months' => $healthSurgery,
            'no_cold_flu_or_fever_today' => $healthNoColdFlu,
        ], JSON_UNESCAPED_SLASHES);
    }

    if (donor_column_exists($pdo, 'deferred')) {
        $donorColumns[] = 'deferred';
        $donorPlaceholders[] = ':deferred';
        $params[':deferred'] = 0;
    }

    if (donor_column_exists($pdo, 'deferral_reason')) {
        $donorColumns[] = 'deferral_reason';
        $donorPlaceholders[] = ':deferral_reason';
        $params[':deferral_reason'] = null;
    }

    if (donor_column_exists($pdo, 'status')) {
        $donorColumns[] = 'status';
        $donorPlaceholders[] = ':status';
        $params[':status'] = 'Pending';
    }

    // Ensure we only include columns that actually exist in the tbldonors table.
    $logFile = __DIR__ . '/../logs/register_donor_debug.log';
    $finalColumns = [];
    $finalPlaceholders = [];
    $finalParams = [];
    foreach ($donorColumns as $i => $col) {
        if (donor_column_exists($pdo, $col)) {
            $finalColumns[] = $col;
            $finalPlaceholders[] = $donorPlaceholders[$i];
            $ph = $donorPlaceholders[$i];
            if (array_key_exists($ph, $params)) {
                $finalParams[$ph] = $params[$ph];
            } else {
                $k = ltrim($ph, ':');
                $alt = ':' . $k;
                if (array_key_exists($alt, $params)) {
                    $finalParams[$alt] = $params[$alt];
                }
            }
        } else {
            if ($enableDebug) {
                @file_put_contents($logFile, "WARN: skipping missing donor column {$col}\n", FILE_APPEND | LOCK_EX);
            }
        }
    }

    if (empty($finalColumns)) {
        // No matching columns to insert into; abort with clear message.
        throw new Exception('No matching tbldonors columns found for insert.');
    }

    // (debugging removed) prepare and execute insert for final columns

    $statement = $pdo->prepare('INSERT INTO tbldonors (' . implode(', ', $finalColumns) . ') VALUES (' . implode(', ', $finalPlaceholders) . ')');
    $statement->execute($finalParams);
    $donorId = (int)$pdo->lastInsertId();

    $hasRoleColumn = user_column_exists($pdo, 'role');
    $userInsertQuery = $hasRoleColumn
        ? 'INSERT INTO tblusers (name, email, password, role) VALUES (:name, :email, :password, :role)'
        : 'INSERT INTO tblusers (name, email, password) VALUES (:name, :email, :password)';

    $userId = 0;
    if ($existingUserId === 0) {
        $userStmt = $pdo->prepare($userInsertQuery);
        $userParams = [
            ':name' => $fullName,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_BCRYPT),
            ':role' => 'donor',
        ];
        if (!$hasRoleColumn) {
            unset($userParams[':role']);
        }
        $userStmt->execute($userParams);
        $userId = (int)$pdo->lastInsertId();
    } else {
        // reuse the existing login id
        $userId = $existingUserId;
    }

    $pdo->commit();

    if ($enableDebug) {
        $logFile = __DIR__ . '/../logs/register_donor_debug.log';
        $successEntry = "SUCCESS: donor inserted. userId={$userId} time=" . date('c') . "\n";
        @file_put_contents($logFile, $successEntry, FILE_APPEND | LOCK_EX);
    }

    http_response_code(201);
    // Send welcome email and create in-app notifications (best-effort)
    try {
        require_once __DIR__ . '/../config/mailer.php';
        require_once __DIR__ . '/workflow_helpers.php';

        $subject = 'Thank you for registering as a blood donor';
        $textBody = "Dear {$fullName},\n\nThank you for registering as a potential blood donor.\n\nAs a next step, we will contact you to arrange a screening and blood draw appointment. Please note that you will not be able to book appointments until after your blood has been collected and tested.\n\nThank you for your willingness to help save lives.\n\nRegards,\nBlood Transfusion Services";
        $htmlBody = "<p>Dear {$fullName},</p><p>Thank you for registering as a potential blood donor.</p><p>As a next step, we will contact you to arrange a screening and blood draw appointment. Please note that you will not be able to sign in or book appointments until after your blood has been collected and tested.</p><p>Thank you for your willingness to help save lives.</p><p>Regards,<br/>Blood Transfusion Services</p>";

        $meta = [];
        if ($email !== '') {
            @bts_send_email($email, $subject, $htmlBody, $textBody, $meta);
        }

        // Notify admins that a new donor registered
        try {
            $notif = [
                'role_target' => 'admin',
                'title' => 'New donor registration',
                'message' => "New donor registered: {$fullName} ({$email})",
                'severity' => 'info',
                'channel' => 'in_app',
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            workflow_insert_notification($pdo, $notif);
        } catch (Throwable $_) {}

        // Create donor in-app notification and ensure email record exists
        try {
            if ($userId > 0) {
                $notifDonor = [
                    'user_id' => $userId,
                    'donor_id' => $donorId,
                    'role_target' => 'donor',
                    'title' => 'Thank you for registering as a blood donor',
                    'message' => "Dear {$fullName},\n\nThank you for registering as a potential blood donor.\n\nAs a next step, we will contact you to arrange a screening and blood draw appointment. Please note that you will not be able to sign in or book appointments until after your blood has been collected and tested.\n\nThank you for your willingness to help save lives.\n\nRegards,\nBlood Transfusion Services",
                    'severity' => 'info',
                    'channel' => 'in_app',
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                workflow_insert_notification($pdo, $notifDonor);
            }
        } catch (Throwable $_) {}
    } catch (Throwable $_) {
        // swallow any notification/email errors so registration still succeeds
    }

    echo json_encode([
        'success' => true,
        'message' => 'Registration submitted successfully. You can now sign in.',
        'id' => $userId,
        'name' => $fullName,
        'email' => $email,
        'role' => 'donor',
        'token' => bts_create_token([
            'id' => $userId,
            'email' => $email,
            'role' => 'donor',
        ]),
    ]);
    exit;
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($enableDebug) {
        $logFile = __DIR__ . '/../logs/register_donor_debug.log';
        $errEntry = "ERROR: " . date('c') . " code=" . $exception->getCode() . " message=" . $exception->getMessage() . "\n";
        @file_put_contents($logFile, $errEntry, FILE_APPEND | LOCK_EX);
    }

    $errorMessage = strtolower($exception->getMessage());
    if ((int)$exception->getCode() === 23000) {
        if (str_contains($errorMessage, 'cid_number')) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'This CID is already registered.',
            ]);
            exit;
        }
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This email is already registered. Please sign in.',
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not save donor registration.',
        'error' => $exception->getMessage(),
    ]);
    exit;
}