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

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['staff', 'admin']);
$userId = (int)($claims['sub'] ?? 0);

function column_exists_staff(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE :column_name');
        $stmt->execute([':column_name' => $columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ignored) {
        return false;
    }
}

try {
    $lowStockThreshold = 5;
    $expiryAlertWindowDays = 7;
    $unitIdColumn = 'donation_id';
    if (column_exists_staff($pdo, 'tblblood_units', 'unit_id')) {
        $unitIdColumn = 'unit_id';
    } elseif (!column_exists_staff($pdo, 'tblblood_units', 'donation_id')) {
        $unitIdColumn = '';
    }
    $diagnosisSelect = column_exists_staff($pdo, 'tblblood_requests', 'diagnosis') ? 'r.diagnosis' : 'NULL';
    $reasonSelect = column_exists_staff($pdo, 'tblblood_requests', 'reason_for_transfusion') ? 'r.reason_for_transfusion' : 'NULL';

    // Helper function to safely query a table that may not exist
    $safeQuery = function($pdo, $tableName, $sql) {
        try {
            $result = $pdo->query("SHOW TABLES LIKE '$tableName'");
            if ($result && $result->rowCount() > 0) {
                return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            // Table doesn't exist or query failed
        }
        return [];
    };

        $requests = $pdo->query(
                'SELECT r.id,
                                r.request_code,
                                r.patient_name,
                                r.doctor_name,
                                ' . $diagnosisSelect . ' AS diagnosis,
                                ' . $reasonSelect . ' AS reason_for_transfusion,
                                r.component,
                                r.blood_type,
                                r.units_requested,
                                r.urgency,
                                r.status,
                                CASE WHEN r.urgency IN ("Urgent", "Critical") THEN 1 ELSE 0 END AS is_urgent,
                                (
                                        SELECT l.donor_unit_refs
                                        FROM tbllab_logs l
                                        WHERE l.request_id = r.id
                                            AND l.result = "Compatible"
                                            AND l.donor_unit_refs IS NOT NULL
                                            AND TRIM(l.donor_unit_refs) <> ""
                                        ORDER BY l.id DESC
                                        LIMIT 1
                                ) AS compatible_unit_id,
                                (
                                        SELECT u.status
                                        FROM tblblood_units u
                                    WHERE u.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' = (
                                                SELECT l2.donor_unit_refs
                                                FROM tbllab_logs l2
                                                WHERE l2.request_id = r.id
                                                    AND l2.result = "Compatible"
                                                    AND l2.donor_unit_refs IS NOT NULL
                                                    AND TRIM(l2.donor_unit_refs) <> ""
                                                ORDER BY l2.id DESC
                                                LIMIT 1
                                        )
                                        LIMIT 1
                                    ) AS compatible_unit_status,
                                    (
                                        SELECT CAST(u3.donation_id AS CHAR)
                                        FROM tblblood_units u3
                                        WHERE u3.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' = (
                                                SELECT l5.donor_unit_refs
                                                FROM tbllab_logs l5
                                                WHERE l5.request_id = r.id
                                                    AND l5.result = "Compatible"
                                                    AND l5.donor_unit_refs IS NOT NULL
                                                    AND TRIM(l5.donor_unit_refs) <> ""
                                                ORDER BY l5.id DESC
                                                LIMIT 1
                                        )
                                        LIMIT 1
                                    ) AS compatible_donation_id,
                                    (
                                        SELECT t.final_result
                                        FROM tblblood_units u4
                                        LEFT JOIN tbldonation_tests t
                                          ON CAST(t.donation_id AS CHAR) = CAST(u4.donation_id AS CHAR)
                                        WHERE u4.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' = (
                                                SELECT l6.donor_unit_refs
                                                FROM tbllab_logs l6
                                                WHERE l6.request_id = r.id
                                                    AND l6.result = "Compatible"
                                                    AND l6.donor_unit_refs IS NOT NULL
                                                    AND TRIM(l6.donor_unit_refs) <> ""
                                                ORDER BY l6.id DESC
                                                LIMIT 1
                                        )
                                        ORDER BY t.id DESC
                                        LIMIT 1
                                    ) AS compatible_test_final_result,
                                    (
                                        SELECT l3.result
                                        FROM tbllab_logs l3
                                        WHERE l3.request_id = r.id
                                        ORDER BY l3.id DESC
                                        LIMIT 1
                                    ) AS latest_crossmatch_result,
                                    (
                                        SELECT DATE_FORMAT(l4.created_at, "%Y-%m-%d %H:%i:%s")
                                        FROM tbllab_logs l4
                                        WHERE l4.request_id = r.id
                                        ORDER BY l4.id DESC
                                        LIMIT 1
                                    ) AS latest_crossmatch_tested_at,
                                    (
                                        SELECT GROUP_CONCAT(DISTINCT u2.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' ORDER BY u2.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' SEPARATOR ",")
                                        FROM tblblood_units u2
                                        WHERE u2.blood_type = r.blood_type
                                            AND (
                                                (LOWER(TRIM(r.component)) IN ("packed red cells", "prbc") AND LOWER(TRIM(u2.component)) IN ("packed red cells", "prbc"))
                                                OR (LOWER(TRIM(r.component)) = "platelets" AND LOWER(TRIM(u2.component)) = "platelets")
                                                OR (LOWER(TRIM(r.component)) IN ("plasma", "ffp", "fresh frozen plasma") AND LOWER(TRIM(u2.component)) IN ("plasma", "ffp", "fresh frozen plasma"))
                                                OR (LOWER(TRIM(r.component)) NOT IN ("packed red cells", "prbc", "platelets", "plasma", "ffp", "fresh frozen plasma") AND LOWER(TRIM(u2.component)) = "whole blood")
                                            )
                                            AND LOWER(COALESCE(u2.status, "")) = "available"
                                                AND (
                                                    u2.donation_id IS NULL
                                                    OR TRIM(CAST(u2.donation_id AS CHAR)) = ""
                                                    OR EXISTS (
                                                        SELECT 1
                                                        FROM tbldonation_tests t2
                                                        WHERE CAST(t2.donation_id AS CHAR) = CAST(u2.donation_id AS CHAR)
                                                          AND t2.final_result IN ("Safe", "Eligible")
                                                    )
                                                )
                                                AND (u2.request_id IS NULL OR u2.request_id = 0)
                                    ) AS available_unit_ids
                 FROM tblblood_requests r
                 ORDER BY r.id DESC
                 LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);

    $inventory = [];
    try {
                $unitInventorySql = 'SELECT "All" AS blood_bank_name,
                                                                        blood_type,
                                                                        SUM(CASE WHEN LOWER(TRIM(component)) = "whole blood" THEN 1 ELSE 0 END) AS whole_units,
                                                                        SUM(CASE WHEN LOWER(TRIM(component)) IN ("packed red cells", "prbc") THEN 1 ELSE 0 END) AS prbc_units,
                                                                        SUM(CASE WHEN LOWER(TRIM(component)) = "platelets" THEN 1 ELSE 0 END) AS platelets_units,
                                                                        SUM(CASE WHEN LOWER(TRIM(component)) IN ("plasma", "ffp", "fresh frozen plasma") THEN 1 ELSE 0 END) AS ffp_units,
                                                                        CASE
                                                                                WHEN COUNT(*) <= 5 THEN "Low"
                                                                                WHEN COUNT(*) <= 20 THEN "Watch"
                                                                                ELSE "Healthy"
                                                                        END AS stock_level
                                                         FROM tblblood_units
                                                         WHERE (request_id IS NULL OR request_id = 0)
                                                         GROUP BY blood_type
                                                         ORDER BY blood_type ASC';
        $inventory = $pdo->query($unitInventorySql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $inventory = [];
    }

    if (!$inventory) {
        $inventorySql = 'SELECT "All" AS blood_bank_name, blood_type, whole_units, prbc_units, platelets_units, ffp_units,
                    CASE
                        WHEN (whole_units + prbc_units + platelets_units + ffp_units) <= 5 THEN "Low"
                        WHEN (whole_units + prbc_units + platelets_units + ffp_units) <= 20 THEN "Watch"
                        ELSE "Healthy"
                    END AS stock_level
               FROM tblinventory
               ORDER BY blood_type ASC';
        $inventory = $pdo->query($inventorySql)->fetchAll(PDO::FETCH_ASSOC);
    }

    $labLogs = [];
    try {
        $labLogs = $pdo->query(
            'SELECT l.id,
                                        DATE_FORMAT(l.created_at, "%Y-%m-%d %H:%i:%s") AS logged_at,
                    DATE(l.created_at) AS date,
                    l.test_name,
                    l.sample_reference,
                    l.result,
                    l.technician_name,
                    l.request_id,
                                        r.request_code,
                                        r.status AS request_status,
                                        r.doctor_name,
                    COALESCE(l.patient_name, r.patient_name) AS patient_name,
                    COALESCE(l.blood_type, r.blood_type) AS blood_type,
                    COALESCE(l.component, r.component) AS component,
                    COALESCE(l.units_requested, r.units_requested) AS units_requested,
                    l.donor_unit_refs,
                                                                                u.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' AS donor_unit_id,
                                        u.status AS donor_unit_status,
                                        u.blood_type AS donor_blood_type,
                                        u.component AS donor_component,
                    l.test_parameters,
                    l.notes
             FROM tbllab_logs l
             LEFT JOIN tblblood_requests r
               ON r.id = l.request_id OR r.request_code = l.sample_reference
                         LEFT JOIN tblblood_units u
                                                         ON u.' . ($unitIdColumn !== '' ? $unitIdColumn : 'id') . ' = l.donor_unit_refs
             ORDER BY l.id DESC
             LIMIT 60'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $labLogs = $safeQuery($pdo, 'tbllab_logs',
            'SELECT id,
                    DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:%s") AS logged_at,
                    DATE(created_at) AS date,
                    test_name,
                    sample_reference,
                    result,
                    technician_name
             FROM tbllab_logs
             ORDER BY id DESC
             LIMIT 60'
        );
    }

    $issueUnitColumnExists = false;
    try {
        $issueUnitColumnExistsStmt = $pdo->prepare("SHOW COLUMNS FROM tblissue_logs LIKE 'issued_unit_id'");
        $issueUnitColumnExistsStmt->execute();
        $issueUnitColumnExists = (bool)$issueUnitColumnExistsStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $issueUnitColumnExists = false;
    }

    $issueLogSql = 'SELECT request_code, patient_name, blood_type, component, units_issued, staff_name,'
        . ($issueUnitColumnExists ? ' issued_unit_id,' : ' NULL AS issued_unit_id,')
        . ' DATE_FORMAT(issued_at, "%Y-%m-%d %H:%i") AS issued_at
           FROM tblissue_logs
           ORDER BY id DESC
           LIMIT 30';

    $issueLogs = $safeQuery($pdo, 'tblissue_logs', $issueLogSql);

    $urgentRequests = $pdo->query(
        'SELECT id, request_code, patient_name, urgency, status
         FROM tblblood_requests
            WHERE urgency IN ("Urgent", "Critical") AND status NOT IN ("Issued", "Rejected")
         ORDER BY FIELD(urgency, "Critical", "Urgent"), id DESC
         LIMIT 10'
    )->fetchAll(PDO::FETCH_ASSOC);

    $lowStockItemsSql = 'SELECT "All" AS blood_bank_name, blood_type, whole_units, prbc_units, platelets_units, ffp_units,
              (whole_units + prbc_units + platelets_units + ffp_units) AS total_units
           FROM tblinventory
           WHERE (whole_units + prbc_units + platelets_units + ffp_units) < ?
           ORDER BY total_units ASC, blood_type ASC';
    $lowStockItemsStmt = $pdo->prepare($lowStockItemsSql);
    $lowStockItemsStmt->execute([$lowStockThreshold]);
    $lowStockItems = $lowStockItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Only include notifications targeted to staff or specifically to this user.
    $notifications = $safeQuery($pdo, 'tblnotifications',
        'SELECT id, title, message, severity, is_read,
                DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS created_at
         FROM tblnotifications
         WHERE role_target = "staff" OR user_id = ' . (int)$userId . '
         ORDER BY id DESC
         LIMIT 15'
    );

    $unreadCountStmt = $pdo->prepare(
        'SELECT COUNT(*) AS unread_count
         FROM tblnotifications
         WHERE is_read = 0
           AND (role_target = "staff" OR user_id = ? )'
    );
    $unreadCountStmt->execute([$userId]);
    $notificationsUnreadCount = (int)$unreadCountStmt->fetchColumn();

    $incoming = (int)$pdo->query("SELECT COUNT(*) FROM tblblood_requests WHERE status IN ('Pending', 'Approved', 'Cross-Matching', 'Matched')")->fetchColumn();
    $queue = (int)$pdo->query("SELECT COUNT(*) FROM tblblood_requests WHERE status = 'Cross-Matching'")->fetchColumn();
    
    $issuedToday = 0;
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'tblissue_logs'");
        if ($result && $result->rowCount() > 0) {
            $issuedToday = (int)$pdo->query("SELECT IFNULL(SUM(units_issued), 0) FROM tblissue_logs WHERE DATE(issued_at) = CURDATE()")->fetchColumn();
        }
    } catch (Throwable $e) {
        $issuedToday = 0;
    }
    
    $lowStockStmt = $pdo->prepare("SELECT COUNT(*) FROM tblinventory WHERE (whole_units + prbc_units + platelets_units + ffp_units) < ?");
    $lowStockStmt->execute([$lowStockThreshold]);
    $lowStock = (int)$lowStockStmt->fetchColumn();

    $expiredUnitsCount = 0;
    $expiringSoonCount = 0;
    $expiryAlerts = [];
    try {
        $expiredCountStmt = $pdo->query(
            "SELECT COUNT(*)
             FROM tblblood_units
             WHERE LOWER(COALESCE(status, '')) = 'available'
               AND expiry_date < CURDATE()"
        );
        $expiredUnitsCount = (int)$expiredCountStmt->fetchColumn();

        $expiringSoonStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tblblood_units
             WHERE LOWER(COALESCE(status, '')) = 'available'
               AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"
        );
        $expiringSoonStmt->execute([$expiryAlertWindowDays]);
        $expiringSoonCount = (int)$expiringSoonStmt->fetchColumn();

        $expiryAlertsStmt = $pdo->prepare(
            "SELECT blood_type,
                    component,
                    DATE_FORMAT(expiry_date, '%Y-%m-%d') AS expiry_date,
                    COUNT(*) AS unit_count,
                    CASE
                        WHEN expiry_date < CURDATE() THEN 'expired'
                        ELSE 'expiring_soon'
                    END AS alert_type
             FROM tblblood_units
             WHERE LOWER(COALESCE(status, '')) = 'available'
               AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             GROUP BY blood_type, component, expiry_date
             ORDER BY expiry_date ASC, blood_type ASC
             LIMIT 25"
        );
        $expiryAlertsStmt->execute([$expiryAlertWindowDays]);
        $expiryAlerts = $expiryAlertsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $expiredUnitsCount = 0;
        $expiringSoonCount = 0;
        $expiryAlerts = [];
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'incomingRequests' => $incoming,
            'crossMatchQueue' => $queue,
            'unitsIssuedToday' => $issuedToday,
            'lowStockAlerts' => $lowStock,
            'expiredUnits' => $expiredUnitsCount,
            'expiringSoonUnits' => $expiringSoonCount,
        ],
        'requests' => $requests,
        'inventory' => $inventory,
        'labLogs' => $labLogs,
        'issueLogs' => $issueLogs,
        'urgentRequests' => $urgentRequests,
        'lowStockItems' => $lowStockItems,
        'lowStockThreshold' => $lowStockThreshold,
        'expiryAlerts' => $expiryAlerts,
        'expiryAlertWindowDays' => $expiryAlertWindowDays,
        'notifications' => $notifications,
        'notificationsUnreadCount' => $notificationsUnreadCount,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
