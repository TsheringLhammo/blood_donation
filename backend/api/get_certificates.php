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
require_once __DIR__ . '/certificate_helpers.php';

bts_require_auth(['staff', 'admin']);

$sync = strtolower(trim((string)($_GET['sync'] ?? '1')));
$search = trim((string)($_GET['search'] ?? ''));
$milestone = trim((string)($_GET['milestone'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$donorId = (int)($_GET['donor_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 20)));

try {
    bts_ensure_certificate_table($pdo);

    $donorNameColumn = bts_donor_name_column($pdo);
    $cidColumn = bts_donor_cid_column($pdo);

    if ($sync !== '0' && $sync !== 'false') {
        bts_sync_all_milestone_certificates($pdo);
    }

    if (!bts_table_exists($pdo, 'donor_certificates')) {
        echo json_encode(['success' => true, 'data' => [], 'summary' => ['total' => 0, 'appreciation' => 0, 'recognition' => 0, 'honor' => 0], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1]]);
        exit;
    }

    $where = [];
    $params = [];

    if ($donorId > 0) {
        $where[] = 'c.donor_id = ?';
        $params[] = $donorId;
    }

    if ($search !== '') {
        $where[] = '(LOWER(COALESCE(c.donor_name, "")) LIKE ? OR LOWER(COALESCE(c.cid, "")) LIKE ? OR LOWER(COALESCE(c.certificate_type, "")) LIKE ?)';
        $like = '%' . strtolower($search) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($milestone !== '') {
        $where[] = 'c.milestone_key = ?';
        $params[] = $milestone;
    }

    if ($status !== '') {
        $where[] = 'LOWER(COALESCE(c.status, "")) = ?';
        $params[] = strtolower($status);
    }

    $whereSql = $where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM donor_certificates c' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $sql = '
        SELECT
            c.id,
            c.donor_id,
            c.donation_history_id,
            c.milestone_key,
            c.certificate_type,
            c.milestone_threshold,
            c.total_completed_donations,
            c.certificate_number,
            c.donor_name,
            c.cid,
            c.blood_type,
            c.issue_date,
            c.status,
            COALESCE(d.`' . $donorNameColumn . '`, c.donor_name) AS donor_display_name,
            COALESCE(d.`' . $cidColumn . '`, c.cid) AS donor_display_cid,
            dh.donation_date,
            dh.blood_bank_id,
            dh.component,
            dh.units_collected
        FROM donor_certificates c
        LEFT JOIN tbldonors d ON d.id = c.donor_id
        LEFT JOIN donation_history dh ON dh.id = c.donation_history_id
        ' . $whereSql . '
        ORDER BY c.issue_date DESC, c.id DESC
        LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summaryStmt = $pdo->query('
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN milestone_key = "milestone_5" THEN 1 ELSE 0 END) AS appreciation,
            SUM(CASE WHEN milestone_key = "milestone_10" THEN 1 ELSE 0 END) AS recognition,
            SUM(CASE WHEN milestone_key = "milestone_20" THEN 1 ELSE 0 END) AS honor
        FROM donor_certificates
    ');
    $summary = $summaryStmt ? ($summaryStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'summary' => [
            'total' => (int)($summary['total'] ?? 0),
            'appreciation' => (int)($summary['appreciation'] ?? 0),
            'recognition' => (int)($summary['recognition'] ?? 0),
            'honor' => (int)($summary['honor'] ?? 0),
        ],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
        'milestones' => bts_certificate_milestones(),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
