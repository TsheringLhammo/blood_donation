<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/_common.php';

bts_require_auth(['admin']);

try {
    $filters = [
        'search' => (string)($_GET['search'] ?? ''),
        'blood_group' => (string)($_GET['blood_group'] ?? ''),
        'status' => (string)($_GET['status'] ?? ''),
        'deferral' => (string)($_GET['deferral'] ?? ''),
    ];

    $rows = bts_donor_records_get_all_donors($pdo);
    $filtered = bts_donor_records_filter_rows($rows, $filters);

    $filename = 'donor-records-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to open output stream.');
    }

    fputcsv($output, ['#', 'CID', 'Name', 'Blood Group', 'Phone', 'Email', 'Last Donation', 'Total Donations', 'Next Eligible', 'Status', 'Deferral Type']);

    $index = 1;
    foreach ($filtered as $row) {
        fputcsv($output, [
            $index++,
            (string)($row['cid_masked'] ?? 'N/A'),
            (string)($row['name'] ?? ''),
            (string)($row['blood_group'] ?? ''),
            (string)($row['phone'] ?? ''),
            (string)($row['email'] ?? ''),
            (string)($row['last_donation'] ?? 'Never'),
            (string)($row['total_donations'] ?? 0),
            (string)($row['next_eligible'] ?? 'N/A'),
            (string)($row['status'] ?? 'Pending'),
            (string)($row['deferral_type'] ?? 'None'),
        ]);
    }

    fclose($output);
} catch (Throwable $exception) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $exception->getMessage()]);
}
