<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['donor', 'admin', 'doctor', 'staff']);
$userId = (int)($claims['sub'] ?? 0);
$userEmail = trim((string)($claims['email'] ?? ''));

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid authenticated user.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $userName = '';
    if ($userEmail !== '') {
        $userStmt = $pdo->prepare('SELECT name FROM tblusers WHERE id = ? AND email = ? LIMIT 1');
        $userStmt->execute([$userId, $userEmail]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = trim((string)($user['name'] ?? ''));
    }

    $hasUserIdColumn = false;
    $columnCheck = $pdo->query("SHOW COLUMNS FROM tblappointments LIKE 'user_id'");
    if ($columnCheck && $columnCheck->rowCount() > 0) {
        $hasUserIdColumn = true;
    }

    if ($hasUserIdColumn) {
        $stmt = $pdo->prepare(
              'SELECT id, full_name, age, blood_group, phone_number, preferred_date, preferred_time, blood_bank, status, created_at
                     FROM tblappointments
                     WHERE user_id = :user_id
                         OR (user_id IS NULL AND :user_name <> "" AND full_name = :user_name)
                     ORDER BY preferred_date ASC'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':user_name' => $userName,
        ]);
    } else {
        // Legacy fallback: no user_id column yet, so use authenticated user's own name.
        $stmt = $pdo->prepare(
            'SELECT id, full_name, age, blood_group, phone_number, preferred_date, preferred_time, blood_bank, status, created_at
             FROM tblappointments
             WHERE full_name = ?
             ORDER BY preferred_date ASC'
        );
        $stmt->execute([$userName]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If we have a donation_history table, prefer the latest donation_history.status
    // for appointments whose row status is empty/stale (pending/confirmed/blank).
    $tablesRes = $pdo->query("SHOW TABLES LIKE 'donation_history'");
    if ($rows && $tablesRes && $tablesRes->rowCount() > 0) {
        $appointmentIds = array_values(array_filter(array_map(function($r) { return isset($r['id']) ? (int)$r['id'] : 0; }, $rows)));
        if ($appointmentIds) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $historySql = "SELECT dh.appointment_id, dh.status
                           FROM donation_history dh
                           INNER JOIN (
                               SELECT appointment_id, MAX(id) AS max_id
                               FROM donation_history
                               WHERE appointment_id IN ({$placeholders})
                               GROUP BY appointment_id
                           ) latest ON latest.appointment_id = dh.appointment_id AND latest.max_id = dh.id";
            $historyStmt = $pdo->prepare($historySql);
            $historyStmt->execute($appointmentIds);
            $historyStatusByAppointment = [];
            foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $hrow) {
                $historyStatusByAppointment[(int)($hrow['appointment_id'] ?? 0)] = (string)($hrow['status'] ?? '');
            }

            foreach ($rows as &$row) {
                $appointmentId = (int)($row['id'] ?? 0);
                $rawStatus = strtolower(trim((string)($row['status'] ?? '')));
                $historyStatus = strtolower(trim((string)($historyStatusByAppointment[$appointmentId] ?? '')));

                if (in_array($rawStatus, ['', 'pending', 'confirmed'], true) && $historyStatus !== '') {
                    $row['status'] = $historyStatus;
                }
            }
            unset($row);
        }
    }

    echo json_encode(['success' => true, 'count' => count($rows), 'data' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
