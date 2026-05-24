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
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

bts_require_auth(['admin', 'staff']);

function appointment_table_exists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
}

$data   = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$table  = trim((string)($data['table']  ?? ''));
$id     = (int)($data['id'] ?? 0);
$status = trim((string)($data['status'] ?? ''));

$allowedTables  = ['tblappointments', 'appointments', 'tblblood_camps'];
$allowedStatuses = ['pending', 'confirmed', 'rejected', 'completed'];

if (!in_array($table, $allowedTables, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid table']);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

try {
    // Table name is whitelisted above, safe to interpolate
    $stmt = $pdo->prepare("UPDATE `$table` SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    $affectedRows = $stmt->rowCount();

    if (($table === 'tblappointments' || $table === 'appointments') && appointment_table_exists($pdo, 'tblappointments') && appointment_table_exists($pdo, 'appointments')) {
        $mirrorTable = $table === 'tblappointments' ? 'appointments' : 'tblappointments';
        $mirrorStmt = $pdo->prepare("UPDATE `$mirrorTable` SET status = ? WHERE id = ?");
        $mirrorStmt->execute([$status, $id]);
        $affectedRows += $mirrorStmt->rowCount();
    }

    if ($affectedRows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    // Send email notifications based on table and status
    try {
        if ($table === 'tblblood_camps' && $status === 'confirmed') {
            $campStmt = $pdo->prepare('SELECT organization_name, contact_person, email, phone_number, preferred_date, venue_address FROM tblblood_camps WHERE id = ?');
            $campStmt->execute([$id]);
            $camp = $campStmt->fetch(PDO::FETCH_ASSOC);

            if ($camp && !empty($camp['email'])) {
                $subject = 'Blood Donation Camp Request – Approved ✓';
                $body = sprintf(
                    "Dear %s,\n\n" .
                    "Your blood donation camp request has been APPROVED!\n\n" .
                    "Camp Details:\n" .
                    "Date: %s\n" .
                    "Venue: %s\n" .
                    "Contact: %s\n\n" .
                    "Donors can now book appointments at your camp.\n" .
                    "For questions, please contact the Blood Bank.\n\n" .
                    "Best regards,\nBlood Transfusion Services",
                    $camp['organization_name'],
                    $camp['preferred_date'],
                    $camp['venue_address'],
                    $camp['phone_number']
                );
                @bts_send_email($camp['email'], $subject, $body);
            }
        } elseif ($table === 'tblblood_camps' && $status === 'rejected') {
            $campStmt = $pdo->prepare('SELECT organization_name, email FROM tblblood_camps WHERE id = ?');
            $campStmt->execute([$id]);
            $camp = $campStmt->fetch(PDO::FETCH_ASSOC);

            if ($camp && !empty($camp['email'])) {
                $subject = 'Blood Donation Camp Request – Review Required';
                $body = sprintf(
                    "Dear %s,\n\n" .
                    "Your blood donation camp request could not be approved at this time.\n" .
                    "Please contact the Blood Bank office for more information.\n\n" .
                    "Helpline: 1095 (24/7)\n\n" .
                    "Best regards,\nBlood Transfusion Services",
                    $camp['organization_name']
                );
                @bts_send_email($camp['email'], $subject, $body);
            }
        } elseif (($table === 'tblappointments' || $table === 'appointments') && $status === 'confirmed') {
            $aptStmt = $pdo->prepare('SELECT full_name, blood_bank, preferred_date, preferred_time FROM ' . ($table === 'tblappointments' ? 'tblappointments' : 'appointments') . ' WHERE id = ?');
            $aptStmt->execute([$id]);
            $apt = $aptStmt->fetch(PDO::FETCH_ASSOC);

            if ($apt) {
                // Appointment confirmed - user will see in their dashboard
                // Email sending optional per mailer availability
            }
        } elseif (($table === 'tblappointments' || $table === 'appointments') && $status === 'rejected') {
            $aptStmt = $pdo->prepare('SELECT full_name FROM ' . ($table === 'tblappointments' ? 'tblappointments' : 'appointments') . ' WHERE id = ?');
            $aptStmt->execute([$id]);
            $apt = $aptStmt->fetch(PDO::FETCH_ASSOC);

            if ($apt) {
                // Appointment rejected - user will see in their dashboard
            }
        }
    } catch (Throwable $e) {
        // Log but don't fail the API call
        error_log("Email notification failed: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'table' => $table, 'id' => $id, 'status' => $status]);

} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
}
