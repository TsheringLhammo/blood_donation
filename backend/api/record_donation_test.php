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

$mailerPath = __DIR__ . '/../config/mailer.php';
if (file_exists($mailerPath)) {
    require_once $mailerPath;
}

if (!function_exists('bts_send_email')) {
    function bts_send_email(...$args): bool
    {
        return false;
    }
}

$claims = bts_require_auth(['staff', 'admin']);
$actorUserId = (int)($claims['sub'] ?? 0);

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$donationId = trim((string)($data['donationId'] ?? ''));
if ($donationId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'donationId is required.']);
    exit;
}

// Keep DB enum-compatible values while allowing UI labels such as "Non-reactive".
$normalizeScreeningResult = static function (string $value): ?string {
    $normalized = strtolower(trim($value));
    if ($normalized === 'non-reactive' || $normalized === 'negative') {
        return 'Negative';
    }
    if ($normalized === 'reactive') {
        return 'Reactive';
    }
    if ($normalized === 'not tested' || $normalized === 'pending' || $normalized === '') {
        return 'Not Tested';
    }
    return null;
};

$hiv = $normalizeScreeningResult((string)($data['hivResult'] ?? 'Not Tested'));
$hbsag = $normalizeScreeningResult((string)($data['hbsagResult'] ?? 'Not Tested'));
$hcv = $normalizeScreeningResult((string)($data['hcvResult'] ?? 'Not Tested'));
$syphilis = $normalizeScreeningResult((string)($data['syphilisResult'] ?? 'Not Tested'));
$malaria = $normalizeScreeningResult((string)($data['malariaResult'] ?? 'Not Tested'));
$remarks = trim((string)($data['remarks'] ?? ''));
$testedAt = trim((string)($data['testedAt'] ?? date('Y-m-d H:i:s')));
$sendReactiveAlertEmail = filter_var($data['sendReactiveAlertEmail'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($hiv === null || $hbsag === null || $hcv === null || $syphilis === null || $malaria === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid screening result. Allowed: Non-reactive or Reactive.']);
    exit;
}

$allResults = [$hiv, $hbsag, $hcv, $syphilis, $malaria];
$hasNotTested = in_array('Not Tested', $allResults, true);
$hasReactive = in_array('Reactive', $allResults, true);

if ($hasNotTested) {
    $finalResult = 'Pending';
} elseif ($hasReactive) {
    $finalResult = 'Discard';
} else {
    $finalResult = 'Eligible';
}

$nextDonationStatus = $finalResult === 'Eligible' ? 'Safe' : ($finalResult === 'Discard' ? 'Rejected' : 'Testing Pending');

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$resolveDiscardUnitStatus = static function (PDO $pdo): ?string {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tblblood_units LIKE 'status'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$row || !isset($row['Type'])) {
            return null;
        }

        $type = (string)$row['Type'];
        if (preg_match('/^enum\((.*)\)$/i', $type, $matches) !== 1) {
            return null;
        }

        $rawValues = array_map('trim', explode(',', $matches[1]));
        $values = array_map(static fn(string $v): string => trim($v, "'\""), $rawValues);

        foreach (['Discarded', 'Rejected', 'Quarantined', 'Expired'] as $candidate) {
            if (in_array($candidate, $values, true)) {
                return $candidate;
            }
        }
    } catch (Throwable $exception) {
        return null;
    }

    return null;
};

$resolveExpiredUnitStatus = static function (PDO $pdo): string {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tblblood_units LIKE 'status'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$row || !isset($row['Type'])) {
            return 'Expired';
        }

        $type = (string)$row['Type'];
        if (preg_match('/^enum\((.*)\)$/i', $type, $matches) !== 1) {
            return 'Expired';
        }

        $rawValues = array_map('trim', explode(',', $matches[1]));
        $values = array_map(static fn(string $v): string => trim($v, "'\""), $rawValues);

        foreach (['Expired', 'Rejected'] as $candidate) {
            if (in_array($candidate, $values, true)) {
                return $candidate;
            }
        }
    } catch (Throwable $exception) {
        return 'Expired';
    }

    return 'Expired';
};

$resolveTestFinalResultValue = static function (PDO $pdo, string $preferredValue, string $legacyValue): string {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbldonation_tests LIKE 'final_result'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$row || !isset($row['Type'])) {
            return $preferredValue;
        }

        $type = (string)$row['Type'];
        if (preg_match('/^enum\((.*)\)$/i', $type, $matches) !== 1) {
            return $preferredValue;
        }

        $rawValues = array_map('trim', explode(',', $matches[1]));
        $values = array_map(static fn(string $v): string => trim($v, "'\""), $rawValues);
        if (in_array($preferredValue, $values, true)) {
            return $preferredValue;
        }
        if (in_array($legacyValue, $values, true)) {
            return $legacyValue;
        }
    } catch (Throwable $exception) {
        return $preferredValue;
    }

    return $preferredValue;
};

$finalResultDbValue = $finalResult;
if ($finalResult === 'Eligible') {
    $finalResultDbValue = $resolveTestFinalResultValue($pdo, 'Eligible', 'Safe');
} elseif ($finalResult === 'Discard') {
    $finalResultDbValue = $resolveTestFinalResultValue($pdo, 'Discard', 'Rejected');
}

try {
    $pdo->beginTransaction();

    $donationExists = false;
    if ($tableHasColumn($pdo, 'tbldonations', 'id')) {
        $donationStmt = $pdo->prepare('SELECT id, donor_id, donor_name, status FROM tbldonations WHERE id = ? FOR UPDATE');
        if (ctype_digit($donationId)) {
            $donationStmt->execute([(int)$donationId]);
        } else {
            $donationStmt->execute([-1]);
        }
        $donation = $donationStmt->fetch(PDO::FETCH_ASSOC);
        $donationExists = (bool)$donation;
    }

    $unitHasDonation = false;
    if ($tableHasColumn($pdo, 'tblblood_units', 'donation_id')) {
        $unitDonationStmt = $pdo->prepare('SELECT id FROM tblblood_units WHERE CAST(donation_id AS CHAR) = ? LIMIT 1 FOR UPDATE');
        $unitDonationStmt->execute([$donationId]);
        $unitHasDonation = (bool)$unitDonationStmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$donationExists && !$unitHasDonation) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donation not found in tbldonations or tblblood_units.']);
        exit;
    }

    $currentStatus = 'Testing Pending';
    if ($donationExists) {
        $currentStatus = (string)($donation['status'] ?? 'Testing Pending');
        if ($currentStatus === 'Stocked') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Stocked donation cannot be retested.']);
            exit;
        }
    }

    $existingTestStmt = $pdo->prepare('SELECT id FROM tbldonation_tests WHERE donation_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $existingTestStmt->execute([$donationId]);
    $existingTest = $existingTestStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingTest) {
        $testUpdateStmt = $pdo->prepare(
            'UPDATE tbldonation_tests
             SET hiv_result = :hiv_result,
                 hbsag_result = :hbsag_result,
                 hcv_result = :hcv_result,
                 syphilis_result = :syphilis_result,
                 malaria_result = :malaria_result,
                 final_result = :final_result,
                 remarks = :remarks,
                 tested_by_user_id = :tested_by_user_id,
                 tested_at = :tested_at
             WHERE id = :id'
        );
        $testUpdateStmt->execute([
            ':hiv_result' => $hiv,
            ':hbsag_result' => $hbsag,
            ':hcv_result' => $hcv,
            ':syphilis_result' => $syphilis,
            ':malaria_result' => $malaria,
            ':final_result' => $finalResultDbValue,
            ':remarks' => $remarks !== '' ? $remarks : null,
            ':tested_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ':tested_at' => $testedAt,
            ':id' => (int)$existingTest['id'],
        ]);
    } else {
        $testInsertStmt = $pdo->prepare(
            'INSERT INTO tbldonation_tests
             (donation_id, hiv_result, hbsag_result, hcv_result, syphilis_result, malaria_result, final_result, remarks, tested_by_user_id, tested_at)
             VALUES
             (:donation_id, :hiv_result, :hbsag_result, :hcv_result, :syphilis_result, :malaria_result, :final_result, :remarks, :tested_by_user_id, :tested_at)'
        );
        $testInsertStmt->execute([
            ':donation_id' => $donationId,
            ':hiv_result' => $hiv,
            ':hbsag_result' => $hbsag,
            ':hcv_result' => $hcv,
            ':syphilis_result' => $syphilis,
            ':malaria_result' => $malaria,
            ':final_result' => $finalResultDbValue,
            ':remarks' => $remarks !== '' ? $remarks : null,
            ':tested_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ':tested_at' => $testedAt,
        ]);
    }

    if ($donationExists) {
        $donationUpdateStmt = $pdo->prepare('UPDATE tbldonations SET status = ?, tested_by_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $donationUpdateStmt->execute([$nextDonationStatus, $actorUserId > 0 ? $actorUserId : null, (int)$donation['id']]);
    }

    if ($tableHasColumn($pdo, 'tblblood_units', 'donation_id') && $tableHasColumn($pdo, 'tblblood_units', 'status')) {
        $unitSelectStmt = $pdo->prepare(
            'SELECT id, expiry_date, status
             FROM tblblood_units
             WHERE CAST(donation_id AS CHAR) = ?
             FOR UPDATE'
        );
        $unitSelectStmt->execute([$donationId]);
        $unitRows = $unitSelectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($unitRows) {
            $safeStatus = 'Available';
            $discardStatus = $resolveDiscardUnitStatus($pdo);
            $expiredStatus = $resolveExpiredUnitStatus($pdo);

            $unitUpdateStmt = $pdo->prepare(
                'UPDATE tblblood_units
                 SET status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND LOWER(COALESCE(status, "")) NOT IN ("issued")'
            );

            foreach ($unitRows as $unitRow) {
                $currentUnitStatus = strtolower(trim((string)($unitRow['status'] ?? '')));
                if ($currentUnitStatus === 'issued') {
                    continue;
                }

                $expiryDate = trim((string)($unitRow['expiry_date'] ?? ''));
                $isExpired = $expiryDate !== '' && strtotime($expiryDate) !== false && strtotime($expiryDate . ' 23:59:59') < time();

                if ($finalResult === 'Discard') {
                    $unitUpdateStmt->execute([$discardStatus ?? 'Rejected', (int)$unitRow['id']]);
                    continue;
                }

                if ($finalResult === 'Eligible') {
                    $unitUpdateStmt->execute([$isExpired ? $expiredStatus : $safeStatus, (int)$unitRow['id']]);
                }
            }
        }
    }

    $reactiveAction = [
        'deferredDonorId' => null,
        'notificationsInserted' => 0,
        'counsellingLogged' => false,
        'emailAttempted' => false,
        'emailRecipients' => 0,
        'emailSent' => 0,
    ];

    if ($finalResult === 'Discard' && $donationExists) {
        $donorIdFromDonation = isset($donation['donor_id']) ? (int)$donation['donor_id'] : 0;
        $donorDisplayName = trim((string)($donation['donor_name'] ?? ''));
        $donorEmail = '';

        if ($donorIdFromDonation <= 0 && $donorDisplayName !== '') {
            $donorByNameStmt = $pdo->prepare('SELECT id, full_name, email FROM tbldonors WHERE full_name = ? ORDER BY id DESC LIMIT 1');
            $donorByNameStmt->execute([$donorDisplayName]);
            $donorByName = $donorByNameStmt->fetch(PDO::FETCH_ASSOC);
            if ($donorByName) {
                $donorIdFromDonation = (int)($donorByName['id'] ?? 0);
                $nameFromDonor = trim((string)($donorByName['full_name'] ?? ''));
                if ($nameFromDonor !== '') {
                    $donorDisplayName = $nameFromDonor;
                }
                $donorEmail = trim((string)($donorByName['email'] ?? ''));
            }
        }

        if ($donorIdFromDonation > 0) {
            if ($tableHasColumn($pdo, 'tbldonors', 'deferred') && $tableHasColumn($pdo, 'tbldonors', 'deferral_reason')) {
                $deferStmt = $pdo->prepare(
                    'UPDATE tbldonors
                     SET deferred = 1,
                         deferral_reason = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );

                try {
                    $deferStmt->execute(['Confidential medical', $donorIdFromDonation]);
                } catch (Throwable $exception) {
                    $deferStmt = $pdo->prepare(
                        'UPDATE tbldonors
                         SET deferred = 1,
                             deferral_reason = ?
                         WHERE id = ?'
                    );
                    $deferStmt->execute(['Confidential medical', $donorIdFromDonation]);
                }

                $reactiveAction['deferredDonorId'] = $donorIdFromDonation;
            }

            $donorLookupStmt = $pdo->prepare('SELECT full_name, email FROM tbldonors WHERE id = ? LIMIT 1');
            $donorLookupStmt->execute([$donorIdFromDonation]);
            $donorRow = $donorLookupStmt->fetch(PDO::FETCH_ASSOC);
            if ($donorRow) {
                $nameFromDonor = trim((string)($donorRow['full_name'] ?? ''));
                if ($nameFromDonor !== '') {
                    $donorDisplayName = $nameFromDonor;
                }
                $donorEmail = trim((string)($donorRow['email'] ?? ''));
            }
        }

        if ($donorDisplayName === '') {
            $donorDisplayName = 'Unknown Donor';
        }

        if ($tableHasColumn($pdo, 'tbldonor_counselling', 'donor_id') && $tableHasColumn($pdo, 'tbldonor_counselling', 'notes')) {
            $counsellingStmt = $pdo->prepare(
                'INSERT INTO tbldonor_counselling (donor_id, donation_id, notes, created_by_user_id)
                 VALUES (?, ?, ?, ?)'
            );
            $counsellingStmt->execute([
                $donorIdFromDonation > 0 ? $donorIdFromDonation : null,
                (int)$donation['id'],
                'Reactive screening result - arrange confidential counselling.',
                $actorUserId > 0 ? $actorUserId : null,
            ]);
            $reactiveAction['counsellingLogged'] = true;
        }

        if ($tableHasColumn($pdo, 'tblnotifications', 'role_target') && $tableHasColumn($pdo, 'tblnotifications', 'message')) {
            $notificationStmt = $pdo->prepare(
                'INSERT INTO tblnotifications (user_id, role_target, title, message, severity, channel)
                 VALUES (:user_id, :role_target, :title, :message, :severity, :channel)'
            );

            $notificationMessage = sprintf(
                'Donor %s has a reactive test - arrange confidential counselling',
                $donorDisplayName
            );

            foreach (['admin', 'doctor'] as $roleTarget) {
                $notificationStmt->execute([
                    ':user_id' => null,
                    ':role_target' => $roleTarget,
                    ':title' => 'Reactive Donation Test Alert',
                    ':message' => $notificationMessage,
                    ':severity' => 'critical',
                    ':channel' => 'in_app',
                ]);
                $reactiveAction['notificationsInserted']++;
            }
        }

        if ($sendReactiveAlertEmail) {
            $recipientStmt = $pdo->prepare(
                "SELECT DISTINCT email
                 FROM tblusers
                 WHERE role IN ('admin', 'doctor')
                   AND email IS NOT NULL
                   AND email <> ''"
            );
            $recipientStmt->execute();
            $recipients = $recipientStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $recipients = array_values(array_unique(array_filter(array_map(
                static fn($email) => strtolower(trim((string)$email)),
                $recipients
            ))));

            $reactiveAction['emailAttempted'] = true;
            $reactiveAction['emailRecipients'] = count($recipients);

            foreach ($recipients as $recipientEmail) {
                $recipientEmail = trim((string)$recipientEmail);
                if ($recipientEmail === '') {
                    continue;
                }

                $mailMeta = [];
                $subject = 'Reactive Donor Screening Alert';
                $message = sprintf(
                    'Donor %s has a reactive test. Please arrange confidential counselling.',
                    $donorDisplayName
                );
                $htmlBody = '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';

                if (bts_send_email($recipientEmail, $subject, $htmlBody, $message, $mailMeta)) {
                    $reactiveAction['emailSent']++;
                }
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Donation test recorded successfully.',
        'data' => [
            'donationId' => $donationId,
            'fromStatus' => $currentStatus,
            'toStatus' => $nextDonationStatus,
            'finalResult' => $finalResult,
            'finalResultLabel' => $finalResult === 'Eligible' ? 'Eligible' : ($finalResult === 'Discard' ? 'Discard' : 'Pending'),
            'reactiveAction' => $reactiveAction ?? null,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
