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
require_once __DIR__ . '/workflow_helpers.php';

bts_require_auth(['admin']);

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

try {
    $healthNoColdColumn = $tableHasColumn($pdo, 'tbldonors', 'health_no_cold_flush') ? 'health_no_cold_flush' : ($tableHasColumn($pdo, 'tbldonors', 'health_no_cold_flu') ? 'health_no_cold_flu' : '');
    $consentColumn = $tableHasColumn($pdo, 'tbldonors', 'consent') ? 'consent' : ($tableHasColumn($pdo, 'tbldonors', 'consent_medical') ? 'consent_medical' : '');

    $requiredDonorColumns = [
        'health_tattoo',
        'health_antibiotics',
        'health_surgery',
        $healthNoColdColumn,
        $consentColumn,
        'emergency_contact_name',
        'emergency_contact_phone',
    ];
    $requiredDonorColumns = array_values(array_filter($requiredDonorColumns));
    $missingColumns = [];
    foreach ($requiredDonorColumns as $column) {
        if (!$tableHasColumn($pdo, 'tbldonors', $column)) {
            $missingColumns[] = $column;
        }
    }
    if (!empty($missingColumns)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database schema is outdated. Missing donor columns: ' . implode(', ', $missingColumns) . '. Add health/consent donor columns in tbldonors.',
        ]);
        exit;
    }

    $hasStatusColumn = $tableHasColumn($pdo, 'tbldonors', 'status');
    $hasInitialApprovalColumn = $tableHasColumn($pdo, 'tbldonors', 'initial_approval_status');
    $hasWorkflowStatusColumn = $tableHasColumn($pdo, 'tbldonors', 'workflow_status');
    $approvalStatusExpr = $hasInitialApprovalColumn
        ? 'LOWER(TRIM(COALESCE(initial_approval_status, status, "pending")))'
        : 'LOWER(TRIM(COALESCE(status, "pending")))';
    $whereClause = $approvalStatusExpr . ' = "pending"';
    $orderBy = $hasStatusColumn
        ? 'id DESC'
        : 'id DESC';

    $columns = [
            'id', 'full_name', 'email', 'phone', 'date_of_birth', 'gender', 'blood_type', 'weight',
        'last_donation_date', 'health_tattoo', 'health_antibiotics', 'health_surgery',
        'emergency_contact_name',
        'emergency_contact_phone', 'deferred', 'deferral_reason', 'status', 'workflow_status',
        'initial_approval_status', 'approval_rejection_reason', 'created_at'
    ];

    $selectColumns = [];
    foreach ($columns as $column) {
        if (in_array($column, ['gender', 'weight', 'last_donation_date', 'health_tattoo', 'health_antibiotics', 'health_surgery', 'emergency_contact_name', 'emergency_contact_phone', 'deferred', 'deferral_reason', 'status', 'workflow_status', 'initial_approval_status', 'approval_rejection_reason'], true)) {
            if (!$tableHasColumn($pdo, 'tbldonors', $column)) {
                continue;
            }
        }

        if ($column === 'status') {
            $selectColumns[] = $approvalStatusExpr . ' AS status';
            continue;
        }

        if ($column === 'workflow_status') {
            $selectColumns[] = ($hasWorkflowStatusColumn ? 'workflow_status' : 'NULL') . ' AS workflow_status';
            continue;
        }

        if ($column === 'initial_approval_status') {
            $selectColumns[] = ($hasInitialApprovalColumn ? 'initial_approval_status' : 'NULL') . ' AS initial_approval_status';
            continue;
        }

        if ($column === 'approval_rejection_reason') {
            $selectColumns[] = ($tableHasColumn($pdo, 'tbldonors', 'approval_rejection_reason') ? 'approval_rejection_reason' : 'NULL') . ' AS approval_rejection_reason';
            continue;
        }

        $selectColumns[] = $column;
    }

    if (!in_array('status', $selectColumns, true)) {
        $selectColumns[] = "'pending' AS status";
    }
    if (!in_array('deferred', $selectColumns, true)) {
        $selectColumns[] = '0 AS deferred';
    }

    if ($healthNoColdColumn === '' || $consentColumn === '') {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database schema is missing either health_no_cold_flu/health_no_cold_flush or consent/consent_medical.',
        ]);
        exit;
    }

    $selectColumns[] = "CASE WHEN COALESCE(health_tattoo, 0) = 1 AND COALESCE(health_antibiotics, 0) = 1 AND COALESCE(health_surgery, 0) = 1 AND COALESCE({$healthNoColdColumn}, 0) = 1 THEN 'Yes' ELSE 'No' END AS health_declaration_display";
    $selectColumns[] = "CASE WHEN COALESCE({$consentColumn}, 0) = 1 THEN 'Yes' ELSE 'No' END AS consent_display";

    $selectColumns[] = 'TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age';
    $selectColumns[] = "REPLACE(TRIM(COALESCE(email, '')), ' ', '') AS email";
    $selectColumns[] = "REPLACE(TRIM(COALESCE(phone, '')), ' ', '') AS phone";

    $stmt = $pdo->query(
        'SELECT ' . implode(', ', $selectColumns) . '
         FROM tbldonors
            WHERE ' . $whereClause . '
            ORDER BY ' . $orderBy
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
