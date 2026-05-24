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

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column_name');
    $stmt->execute([':column_name' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

bts_require_auth(['admin']);

$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
    exit;
}

$appointmentId = (int)$data['id'];
$status = trim((string)($data['status'] ?? ''));
$allowedStatuses = ['pending', 'confirmed', 'rejected'];

if ($appointmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID.']);
    exit;
}

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM tblappointments WHERE id = ? LIMIT 1');
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }

    $hasNotes = column_exists($pdo, 'tblappointments', 'notes');

    $preferredDate = trim((string)($data['preferred_date'] ?? $appointment['preferred_date']));
    $preferredTime = trim((string)($data['preferred_time'] ?? $appointment['preferred_time']));
    $bloodBank = trim((string)($data['blood_bank'] ?? $appointment['blood_bank']));
    $notes = $hasNotes ? (array_key_exists('notes', $data) ? trim((string)$data['notes']) : ($appointment['notes'] ?? null)) : null;
    $newStatus = $status !== '' ? $status : $appointment['status'];

    if ($newStatus === 'confirmed') {
        if ($preferredDate === '' || $preferredTime === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Date and time are required to confirm an appointment.']);
            exit;
        }
    }

    $updateFields = [];
    $updateValues = [];

    if ($preferredDate !== ($appointment['preferred_date'] ?? '')) {
        $updateFields[] = 'preferred_date = ?';
        $updateValues[] = $preferredDate;
    }
    if ($preferredTime !== ($appointment['preferred_time'] ?? '')) {
        $updateFields[] = 'preferred_time = ?';
        $updateValues[] = $preferredTime;
    }
    if ($bloodBank !== ($appointment['blood_bank'] ?? '')) {
        $updateFields[] = 'blood_bank = ?';
        $updateValues[] = $bloodBank;
    }

    if ($hasNotes && array_key_exists('notes', $data)) {
        $updateFields[] = 'notes = ?';
        $updateValues[] = $notes;
    }

    if ($newStatus !== ($appointment['status'] ?? '')) {
        $updateFields[] = 'status = ?';
        $updateValues[] = $newStatus;
    }

    if (column_exists($pdo, 'tblappointments', 'updated_at')) {
        $updateFields[] = 'updated_at = NOW()';
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => true, 'message' => 'No changes were made to the appointment.', 'data' => $appointment]);
        exit;
    }

    $updateValues[] = $appointmentId;
    $sql = 'UPDATE tblappointments SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateValues);

    $stmt = $pdo->prepare('SELECT * FROM tblappointments WHERE id = ? LIMIT 1');
    $stmt->execute([$appointmentId]);
    $updatedAppointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($updatedAppointment) {
        try {
            $donorStmt = $pdo->prepare('SELECT id FROM tbldonors WHERE phone = ? OR full_name = ? LIMIT 1');
            $donorStmt->execute([
                $updatedAppointment['phone_number'] ?? '',
                $updatedAppointment['full_name'] ?? '',
            ]);
            $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

            if ($donor) {
                $title = '';
                $message = '';
                $severity = 'info';

                if ($newStatus === 'confirmed') {
                    $title = 'Appointment Confirmed';
                    $message = sprintf(
                        "Your appointment on %s at %s at %s has been confirmed.",
                        $updatedAppointment['preferred_date'] ?? 'TBD',
                        $updatedAppointment['preferred_time'] ?? 'TBD',
                        $updatedAppointment['blood_bank'] ?? 'the selected blood bank'
                    );
                    $severity = 'info';
                }

                if ($newStatus === 'rejected') {
                    $title = 'Appointment Rejected';
                    $message = sprintf(
                        "Your appointment on %s at %s has been rejected.",
                        $updatedAppointment['preferred_date'] ?? 'TBD',
                        $updatedAppointment['blood_bank'] ?? 'the selected blood bank'
                    );
                    $severity = 'warning';
                }

                if ($title !== '' && $message !== '') {
                    $notifStmt = $pdo->prepare(
                        'INSERT INTO tblnotifications (donor_id, title, message, type, severity, channel, is_read) VALUES (?, ?, ?, "appointment", ?, "in_app", 0)'
                    );
                    $notifStmt->execute([
                        $donor['id'],
                        $title,
                        $message,
                        $severity,
                    ]);
                }
            }
        } catch (Throwable $notificationError) {
            error_log('Appointment notification error: ' . $notificationError->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment updated successfully.',
        'data' => $updatedAppointment,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
