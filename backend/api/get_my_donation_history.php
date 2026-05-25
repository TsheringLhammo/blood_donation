<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['donor']);
$userId = (int)($claims['sub'] ?? 0);
$donorId = (int)($claims['donor_id'] ?? 0);
$email = trim((string)($claims['email'] ?? ''));

// JWT sub is the tblusers id; donation_history.donor_id refers to
// tbldonors.id. Prefer the explicit user_id link, then fall back to
// email-based lookup.
if ($donorId <= 0) {
    try {
        $hasUserIdColumn = (bool)$pdo->query("SHOW COLUMNS FROM tbldonors LIKE 'user_id'")->fetchColumn();
        if ($hasUserIdColumn && $userId > 0) {
            $lookup = $pdo->prepare('SELECT id FROM tbldonors WHERE user_id = ? LIMIT 1');
            $lookup->execute([$userId]);
            $donorId = (int)($lookup->fetchColumn() ?: 0);
        }
        if ($donorId <= 0 && $email !== '') {
            $lookup = $pdo->prepare('SELECT id FROM tbldonors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
            $lookup->execute([$email]);
            $donorId = (int)($lookup->fetchColumn() ?: 0);
        }
    } catch (Throwable $_) {
        $donorId = 0;
    }
}

if ($donorId <= 0) {
    // No matching donor record yet — return an empty success payload
    // so the dashboard renders the "No donations yet" state cleanly.
    echo json_encode([
        'success' => true,
        'data' => [],
        'summary' => ['total' => 0, 'this_year' => 0, 'units' => 0],
        'resolved_donor_id' => 0,
        'resolved_via' => $userId > 0 ? 'user_id_no_donor_match' : 'missing_identity',
    ]);
    exit;
}

try {
    $hasHistory = (bool)$pdo->query("SHOW TABLES LIKE 'donation_history'")->fetchColumn();
    if (!$hasHistory) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'summary' => ['total' => 0, 'this_year' => 0, 'units' => 0],
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, donation_date, blood_bank_id, component, units_collected, status, created_at
           FROM donation_history
          WHERE donor_id = :donor_id
            AND LOWER(TRIM(COALESCE(status, ""))) = "completed"
          ORDER BY donation_date DESC, id DESC'
    );
    $stmt->execute([':donor_id' => $donorId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Friendly blood bank names where available.
    $bankNames = [];
    try {
        $bankStmt = $pdo->query('SELECT id, name FROM tblblood_banks');
        foreach ($bankStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $bankNames[(int)$row['id']] = (string)$row['name'];
        }
    } catch (Throwable $_) { /* leave map empty */ }

    $normalized = [];
    $units = 0;
    $thisYear = 0;
    $currentYear = (int)date('Y');
    foreach ($rows as $row) {
        $bankId = (int)($row['blood_bank_id'] ?? 0);
        $u = (int)($row['units_collected'] ?? 0);
        $units += $u;
        if (substr((string)$row['donation_date'], 0, 4) === (string)$currentYear) {
            $thisYear++;
        }
        $normalized[] = [
            'id' => (int)$row['id'],
            'donation_date' => $row['donation_date'],
            'blood_bank_id' => $bankId,
            'blood_bank_name' => $bankNames[$bankId] ?? null,
            'component' => $row['component'] ?? '',
            'units_collected' => $u,
            'status' => $row['status'] ?? 'completed',
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $normalized,
        'summary' => [
            'total' => count($normalized),
            'this_year' => $thisYear,
            'units' => $units,
        ],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
