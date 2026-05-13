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

$toBool = static function ($value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;
};

$healthDeclaration = is_array($payload['healthDeclaration'] ?? null) ? $payload['healthDeclaration'] : [];

$fullName    = trim((string)($payload['fullName']    ?? ''));
$email       = trim((string)($payload['email']       ?? ''));
$password    = (string)($payload['password']         ?? '');
$phone       = trim((string)($payload['phone']       ?? ''));
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
    $healthNoColdColumn = donor_column_exists($pdo, 'health_no_cold_flush') ? 'health_no_cold_flush' : (donor_column_exists($pdo, 'health_no_cold_flu') ? 'health_no_cold_flu' : '');
    $consentColumn = donor_column_exists($pdo, 'consent') ? 'consent' : (donor_column_exists($pdo, 'consent_medical') ? 'consent_medical' : '');

    if ($healthNoColdColumn === '' || $consentColumn === '') {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database schema is missing either health_no_cold_flu/health_no_cold_flush or consent/consent_medical.',
        ]);
        exit;
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

    $missingColumns = missing_donor_columns($pdo, $requiredDonorColumns);
    if (!empty($missingColumns)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database schema is outdated. Missing donor columns: ' . implode(', ', $missingColumns) . '. Run SQL migration to add donor health/consent columns.',
        ]);
        exit;
    }

    $existingUserStmt = $pdo->prepare('SELECT id FROM tblusers WHERE email = ? LIMIT 1');
    $existingUserStmt->execute([$email]);
    if ($existingUserStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email already has a login account. Please sign in.']);
        exit;
    }

    $pdo->beginTransaction();

    $donorColumns = [
        'full_name', 'email', 'phone', 'date_of_birth', 'blood_type', 'address', 'city', 'dzongkhag',
        'gender', 'weight', 'last_donation_date',
        'health_tattoo', 'health_antibiotics', 'health_surgery',
        'emergency_contact_name', 'emergency_contact_phone'
    ];
    $donorPlaceholders = [
        ':full_name', ':email', ':phone', ':date_of_birth', ':blood_type', ':address', ':city', ':dzongkhag',
        ':gender', ':weight', ':last_donation_date',
        ':health_tattoo', ':health_antibiotics', ':health_surgery',
        ':emergency_contact_name', ':emergency_contact_phone'
    ];
    $params = [
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
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

    $statement = $pdo->prepare('INSERT INTO tbldonors (' . implode(', ', $donorColumns) . ') VALUES (' . implode(', ', $donorPlaceholders) . ')');
    $statement->execute($params);

    $hasRoleColumn = user_column_exists($pdo, 'role');
    $userInsertQuery = $hasRoleColumn
        ? 'INSERT INTO tblusers (name, email, password, role) VALUES (:name, :email, :password, :role)'
        : 'INSERT INTO tblusers (name, email, password) VALUES (:name, :email, :password)';

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

    $pdo->commit();

    http_response_code(201);
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

    if ((int)$exception->getCode() === 23000) {
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