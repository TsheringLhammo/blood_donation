<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['staff', 'admin']);

$tableExists = static function (PDO $pdo, string $tableName): bool {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
};

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

$appointmentDate = trim((string)($_GET['appointment_date'] ?? date('Y-m-d')));
if ($appointmentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment_date. Use YYYY-MM-DD.']);
    exit;
}

try {
    if (!$tableExists($pdo, 'donation_history')) {
        echo json_encode(['success' => true, 'data' => [], 'appointment_date' => $appointmentDate]);
        exit;
    }

    $hasDhDonorId = $tableHasColumn($pdo, 'donation_history', 'donor_id');
    $hasDhStatus = $tableHasColumn($pdo, 'donation_history', 'status');
    $hasDhDonationDate = $tableHasColumn($pdo, 'donation_history', 'donation_date');
    $hasDhDonationId = $tableHasColumn($pdo, 'donation_history', 'donation_id');
    $hasDhAppointmentId = $tableHasColumn($pdo, 'donation_history', 'appointment_id');
    $hasDhBloodType = $tableHasColumn($pdo, 'donation_history', 'blood_type');

    if (!$hasDhDonorId || !$hasDhStatus || !$hasDhDonationDate) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'appointment_date' => $appointmentDate,
            'message' => 'No completed donations found. Please complete an appointment first.',
        ]);
        exit;
    }

    $bloodTypeColumn = $tableHasColumn($pdo, 'tbldonors', 'blood_type') ? 'blood_type' : 'blood_group';

    $hasAppointmentsTable = $tableExists($pdo, 'tblappointments');
    $hasAppointmentDateColumn = $hasAppointmentsTable && $tableHasColumn($pdo, 'tblappointments', 'appointment_date');
    $hasPreferredDateColumn = $hasAppointmentsTable && $tableHasColumn($pdo, 'tblappointments', 'preferred_date');
    $hasAppointmentTimeColumn = $hasAppointmentsTable && $tableHasColumn($pdo, 'tblappointments', 'appointment_time');
    $hasPreferredTimeColumn = $hasAppointmentsTable && $tableHasColumn($pdo, 'tblappointments', 'preferred_time');
    $hasAppointmentStatusColumn = $hasAppointmentsTable && $tableHasColumn($pdo, 'tblappointments', 'status');

    $appointmentDateSelect = 'NULL AS appointment_date';
    if ($hasAppointmentDateColumn) {
        $appointmentDateSelect = 'a.appointment_date AS appointment_date';
    } elseif ($hasPreferredDateColumn) {
        $appointmentDateSelect = 'a.preferred_date AS appointment_date';
    }

    $appointmentTimeSelect = 'NULL AS appointment_time';
    if ($hasAppointmentTimeColumn) {
        $appointmentTimeSelect = 'a.appointment_time AS appointment_time';
    } elseif ($hasPreferredTimeColumn) {
        $appointmentTimeSelect = 'a.preferred_time AS appointment_time';
    }

    $appointmentStatusSelect = $hasAppointmentStatusColumn
        ? 'a.status AS appointment_status'
        : 'dh.status AS appointment_status';

    $joinAppointments = ($hasAppointmentsTable && $hasDhAppointmentId)
        ? ' LEFT JOIN tblappointments a ON a.id = dh.appointment_id '
        : '';

    $bloodTypeSelect = $hasDhBloodType
        ? 'COALESCE(NULLIF(TRIM(dh.blood_type), ""), d.' . $bloodTypeColumn . ') AS blood_type'
        : 'd.' . $bloodTypeColumn . ' AS blood_type';

    $donationIdSelect = $hasDhDonationId
        ? 'COALESCE(NULLIF(TRIM(CAST(dh.donation_id AS CHAR)), ""), CONCAT("DH-", dh.id)) AS donation_id'
        : 'CONCAT("DH-", dh.id) AS donation_id';

    $sql = '
        SELECT
            dh.id AS donation_history_id,
            ' . $donationIdSelect . ',
            ' . ($hasDhAppointmentId ? 'dh.appointment_id' : 'NULL AS appointment_id') . ',
            d.id AS donor_id,
            d.full_name,
            ' . $bloodTypeSelect . ',
            ' . $appointmentDateSelect . ',
            ' . $appointmentTimeSelect . ',
            ' . $appointmentStatusSelect . ',
            dh.status AS donation_status,
            DATE(dh.donation_date) AS donation_date
        FROM donation_history dh
        INNER JOIN tbldonors d ON d.id = dh.donor_id
        ' . $joinAppointments . '
                WHERE DATE(dh.donation_date) = :donation_date
                    AND LOWER(TRIM(COALESCE(dh.status, ""))) = "completed"
                ORDER BY appointment_time ASC, dh.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':donation_date' => $appointmentDate]);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Keep one latest completed donation per donor for this date.
    $rowsByDonor = [];
    foreach ($rawRows as $row) {
        $donorId = (int)($row['donor_id'] ?? 0);
        if ($donorId <= 0) {
            continue;
        }
        if (!isset($rowsByDonor[$donorId])) {
            $rowsByDonor[$donorId] = $row;
        }
    }
    $rows = array_values($rowsByDonor);

    $message = '';
    if (count($rows) === 0) {
        $message = 'No completed donations found. Please complete an appointment first.';
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'appointment_date' => $appointmentDate,
        'message' => $message,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}