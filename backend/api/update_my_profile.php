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

$hasColumn = static function (PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$pickColumn = static function (PDO $pdo, string $table, array $columns) use ($hasColumn): ?string {
    foreach ($columns as $column) {
        if ($hasColumn($pdo, $table, $column)) {
            return $column;
        }
    }
    return null;
};

try {
    $claims = bts_require_auth(['donor']);
    $donorId = (int)($claims['donor_id'] ?? 0);
    $userId = (int)($claims['id'] ?? $claims['sub'] ?? 0);
    $tokenEmail = trim((string)($claims['email'] ?? ''));

    if ($donorId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $pkColumn = $hasColumn($pdo, 'tbldonors', 'donor_id') ? 'donor_id' : 'id';
    $bloodColumn = $pickColumn($pdo, 'tbldonors', ['blood_group', 'blood_type']);
    $passwordColumn = $pickColumn($pdo, 'tbldonors', ['password']);
    $profilePictureColumn = $pickColumn($pdo, 'tbldonors', ['profile_picture']);
    $usersPasswordColumn = $pickColumn($pdo, 'tblusers', ['password']);

    $donorStmt = $pdo->prepare('SELECT ' . $pkColumn . ' AS donor_key, email, ' . ($passwordColumn ? $passwordColumn : 'NULL') . ' AS donor_password FROM tbldonors WHERE ' . $pkColumn . ' = ? LIMIT 1');
    $donorStmt->execute([$donorId]);
    $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$donorRow && $tokenEmail !== '') {
        $donorStmt = $pdo->prepare('SELECT ' . $pkColumn . ' AS donor_key, email, ' . ($passwordColumn ? $passwordColumn : 'NULL') . ' AS donor_password FROM tbldonors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
        $donorStmt->execute([$tokenEmail]);
        $donorRow = $donorStmt->fetch(PDO::FETCH_ASSOC);
        if ($donorRow) {
            $donorId = (int)$donorRow['donor_key'];
        }
    }

    if (!$donorRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor profile not found.']);
        exit;
    }

    $fullName = trim((string)($payload['full_name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $phone = trim((string)($payload['phone'] ?? ''));
    $dateOfBirth = trim((string)($payload['date_of_birth'] ?? ''));
    $bloodGroup = trim((string)($payload['blood_group'] ?? ''));
    $gender = trim((string)($payload['gender'] ?? ''));
    $weight = trim((string)($payload['weight'] ?? ''));
    $address = trim((string)($payload['address'] ?? ''));
    $city = trim((string)($payload['city'] ?? ''));
    $dzongkhag = trim((string)($payload['dzongkhag'] ?? ''));
    $profilePicture = trim((string)($payload['profile_picture'] ?? ''));
    $currentPassword = (string)($payload['current_password'] ?? '');
    $newPassword = (string)($payload['new_password'] ?? '');
    $confirmPassword = (string)($payload['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full name, email and phone are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    if ($dateOfBirth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth format.']);
        exit;
    }

    if ($bloodGroup !== '' && !preg_match('/^(A|B|AB|O)[+-]$/', strtoupper($bloodGroup))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid blood group.']);
        exit;
    }

    if ($profilePicture !== '') {
        if (!preg_match('#^data:image/(png|jpeg|jpg|gif);base64,#i', $profilePicture)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported profile picture format.']);
            exit;
        }
        $base64 = preg_replace('#^data:image/[^;]+;base64,#', '', $profilePicture);
        $decoded = base64_decode((string)$base64, true);
        if ($decoded === false || strlen($decoded) > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Profile picture exceeds 2MB or is invalid.']);
            exit;
        }
    }

    $emailCheck = $pdo->prepare('SELECT ' . $pkColumn . ' FROM tbldonors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) AND ' . $pkColumn . ' != ? LIMIT 1');
    $emailCheck->execute([$email, $donorId]);
    if ($emailCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another donor.']);
        exit;
    }

    $userCheck = $pdo->prepare('SELECT id FROM tblusers WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) AND id != ? LIMIT 1');
    $userCheck->execute([$email, $userId > 0 ? $userId : -1]);
    if ($userCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another account.']);
        exit;
    }

    $donorPasswordHash = (string)($donorRow['donor_password'] ?? '');
    $userPasswordHash = '';
    if ($usersPasswordColumn) {
        $userStmt = $pdo->prepare('SELECT ' . $usersPasswordColumn . ' AS password_hash FROM tblusers WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
        $userStmt->execute([$tokenEmail !== '' ? $tokenEmail : $email]);
        $userPasswordHash = (string)($userStmt->fetch(PDO::FETCH_ASSOC)['password_hash'] ?? '');
    }

    $changingPassword = $newPassword !== '' || $currentPassword !== '' || $confirmPassword !== '';
    if ($changingPassword) {
        if ($newPassword === '' || $confirmPassword === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password and confirm password are required.']);
            exit;
        }
        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match.']);
            exit;
        }
        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
            exit;
        }

        $verified = false;
        if ($currentPassword !== '') {
            if ($donorPasswordHash !== '' && password_verify($currentPassword, $donorPasswordHash)) {
                $verified = true;
            }
            if (!$verified && $userPasswordHash !== '' && password_verify($currentPassword, $userPasswordHash)) {
                $verified = true;
            }
        }

        if (!$verified) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }
    }

    $age = null;
    if ($dateOfBirth !== '') {
        $birthDate = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
        if ($birthDate instanceof DateTime) {
            $age = $birthDate->diff(new DateTime('now'))->y;
        }
    }

    $setParts = [];
    $params = [':donor_id' => $donorId];
    $index = 0;
    $bindField = static function (string $field, $value) use (&$setParts, &$params, &$index): void {
        if ($value === null) {
            $setParts[] = $field . ' = NULL';
            return;
        }
        $placeholder = ':p' . $index++;
        $setParts[] = $field . ' = ' . $placeholder;
        $params[$placeholder] = $value;
    };

    $bindField('full_name', $fullName);
    $bindField('email', $email);
    $bindField('phone', $phone);
    if ($hasColumn($pdo, 'tbldonors', 'date_of_birth')) $bindField('date_of_birth', $dateOfBirth !== '' ? $dateOfBirth : null);
    if ($bloodColumn) $bindField($bloodColumn, $bloodGroup !== '' ? $bloodGroup : null);
    if ($hasColumn($pdo, 'tbldonors', 'gender')) $bindField('gender', $gender !== '' ? $gender : null);
    if ($hasColumn($pdo, 'tbldonors', 'weight')) $bindField('weight', $weight !== '' ? $weight : null);
    if ($hasColumn($pdo, 'tbldonors', 'address')) $bindField('address', $address !== '' ? $address : null);
    if ($hasColumn($pdo, 'tbldonors', 'city')) $bindField('city', $city !== '' ? $city : null);
    if ($hasColumn($pdo, 'tbldonors', 'dzongkhag')) $bindField('dzongkhag', $dzongkhag !== '' ? $dzongkhag : null);
    if ($profilePictureColumn) $bindField($profilePictureColumn, $profilePicture !== '' ? $profilePicture : null);
    if ($hasColumn($pdo, 'tbldonors', 'age') && $age !== null) $bindField('age', $age);
    if ($changingPassword && $passwordColumn) $bindField($passwordColumn, password_hash($newPassword, PASSWORD_DEFAULT));

    if (empty($setParts)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    $pdo->beginTransaction();
    $updateSql = 'UPDATE tbldonors SET ' . implode(', ', $setParts) . ' WHERE ' . $pkColumn . ' = :donor_id LIMIT 1';
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($params);

    $userUpdateSql = 'UPDATE tblusers SET email = :email';
    $userParams = [':email' => $email, ':match_email' => $tokenEmail !== '' ? $tokenEmail : $email];
    if ($changingPassword && $usersPasswordColumn) {
        $userUpdateSql .= ', ' . $usersPasswordColumn . ' = :user_password';
        $userParams[':user_password'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $userUpdateSql .= ' WHERE LOWER(TRIM(email)) = LOWER(TRIM(:match_email)) LIMIT 1';
    $userUpdate = $pdo->prepare($userUpdateSql);
    $userUpdate->execute($userParams);

    $pdo->commit();

    $selectColumns = [
        $pkColumn . ' AS id',
        'full_name',
        'email',
        'phone',
        $hasColumn($pdo, 'tbldonors', 'date_of_birth') ? 'date_of_birth' : 'NULL AS date_of_birth',
        $bloodColumn ? $bloodColumn . ' AS blood_group' : 'NULL AS blood_group',
        $hasColumn($pdo, 'tbldonors', 'gender') ? 'gender' : 'NULL AS gender',
        $hasColumn($pdo, 'tbldonors', 'weight') ? 'weight' : 'NULL AS weight',
        $hasColumn($pdo, 'tbldonors', 'address') ? 'address' : 'NULL AS address',
        $hasColumn($pdo, 'tbldonors', 'city') ? 'city' : 'NULL AS city',
        $hasColumn($pdo, 'tbldonors', 'dzongkhag') ? 'dzongkhag' : 'NULL AS dzongkhag',
        $hasColumn($pdo, 'tbldonors', 'age') ? 'age' : 'NULL AS age',
        $profilePictureColumn ? 'profile_picture' : 'NULL AS profile_picture',
        $hasColumn($pdo, 'tbldonors', 'hiv_result') ? 'hiv_result' : 'NULL AS hiv_result',
        $hasColumn($pdo, 'tbldonors', 'hbsag_result') ? 'hbsag_result' : 'NULL AS hbsag_result',
        $hasColumn($pdo, 'tbldonors', 'hcv_result') ? 'hcv_result' : 'NULL AS hcv_result',
        $hasColumn($pdo, 'tbldonors', 'syphilis_result') ? 'syphilis_result' : 'NULL AS syphilis_result',
        $hasColumn($pdo, 'tbldonors', 'malaria_result') ? 'malaria_result' : 'NULL AS malaria_result',
        $hasColumn($pdo, 'tbldonors', 'last_donation_date') ? 'last_donation_date' : 'NULL AS last_donation_date',
        $hasColumn($pdo, 'tbldonors', 'next_eligible_date') ? 'next_eligible_date' : 'NULL AS next_eligible_date',
        $hasColumn($pdo, 'tbldonors', 'workflow_status') ? 'workflow_status' : 'NULL AS workflow_status',
    ];
    $profileStmt = $pdo->prepare('SELECT ' . implode(', ', $selectColumns) . ' FROM tbldonors WHERE ' . $pkColumn . ' = ? LIMIT 1');
    $profileStmt->execute([$donorId]);
    $updated = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'data' => $updated,
    ]);
    exit;
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
}
