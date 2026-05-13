<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Support both directory structures
try {
    if (file_exists(__DIR__ . '/../backend/config/db.php')) {
        require_once __DIR__ . '/../backend/config/db.php';
        require_once __DIR__ . '/../backend/config/auth.php';
    } else {
        require_once __DIR__ . '/../config/db.php';
        require_once __DIR__ . '/../config/auth.php';
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

bts_require_auth(['admin']);

function table_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

try {
    // Check if critical tables exist
    $tablesExist = $pdo->query("SHOW TABLES LIKE 'tbldonors'")->rowCount() > 0 &&
                   $pdo->query("SHOW TABLES LIKE 'tbldonor_samples'")->rowCount() > 0;
    
    if (!$tablesExist) {
        throw new Exception('Required tables do not exist');
    }

    $sampleAdminFinalizedSelect = table_column_exists($pdo, 'tbldonor_samples', 'admin_finalized')
        ? 's.admin_finalized'
        : '0 AS admin_finalized';
    $testStatusSelect = table_column_exists($pdo, 'tbldonor_samples', 'test_status')
        ? 's.test_status'
        : 'NULL AS test_status';
    $donorNameSelect = table_column_exists($pdo, 'tbldonors', 'name')
        ? 'd.name AS donor_name'
        : (table_column_exists($pdo, 'tbldonors', 'full_name') ? 'd.full_name AS donor_name' : 'NULL AS donor_name');
    $dateOfBirthSelect = table_column_exists($pdo, 'tbldonors', 'date_of_birth') ? 'd.date_of_birth AS date_of_birth' : 'NULL AS date_of_birth';
    $genderSelect = table_column_exists($pdo, 'tbldonors', 'gender') ? 'd.gender AS gender' : 'NULL AS gender';
    $citySelect = table_column_exists($pdo, 'tbldonors', 'city') ? 'd.city AS city' : 'NULL AS city';
    $dzongkhagSelect = table_column_exists($pdo, 'tbldonors', 'dzongkhag') ? 'd.dzongkhag AS dzongkhag' : 'NULL AS dzongkhag';
    $deferUntilSelect = table_column_exists($pdo, 'tbldonors', 'defer_until') ? 'd.defer_until AS defer_until' : 'NULL AS defer_until';

    // Simpler query using a different approach to get latest sample
    $sql = "
        SELECT
            d.id,
            {$donorNameSelect},
            d.email,
            d.phone,
            {$dateOfBirthSelect},
            {$genderSelect},
            d.blood_type,
            {$citySelect},
            {$dzongkhagSelect},
            d.status AS donor_status,
            d.created_at,
            {$deferUntilSelect},
            s.id AS sample_id,
            s.collection_date AS sample_date,
            {$testStatusSelect},
            {$sampleAdminFinalizedSelect},
            s.hiv_result AS hiv,
            s.hbsag_result AS hbsag,
            s.hcv_result AS hcv,
            s.syphilis_result AS syphilis,
            s.malaria_result AS malaria,
            s.technician,
            s.tested_by,
            s.tested_at,
            s.notes
        FROM tbldonors d
        LEFT JOIN tbldonor_samples s ON s.id = (
            SELECT s2.id
            FROM tbldonor_samples s2
            WHERE s2.donor_id = d.id
            ORDER BY s2.collection_date DESC, s2.id DESC
            LIMIT 1
        )
        ORDER BY d.id DESC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($rows as &$row) {
        $status = strtolower(trim((string)($row['test_status'] ?? '')));
        if ($status === '') {
            $status = 'pending';
        }
        $row['test_status'] = $status;
        $row['admin_finalized'] = (int)($row['admin_finalized'] ?? 0);
        $row['sample_id'] = isset($row['sample_id']) ? (int)$row['sample_id'] : null;
        $row['donor_name'] = $row['donor_name'] ?? null;
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'data' => $rows,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    $errorInfo = [
        'success' => false,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ];
    // Log to server error log
    error_log("get_donors_with_samples.php error: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    echo json_encode($errorInfo);
}
