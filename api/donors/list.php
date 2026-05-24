<?php
declare(strict_types=1);
ini_set('display_errors', '0');

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

require_once __DIR__ . '/_common.php';

bts_require_auth(['admin']);

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 25, 50, 100], true)) {
        $perPage = 10;
    }

    $filters = [
        'search' => (string)($_GET['search'] ?? ''),
        'blood_group' => (string)($_GET['blood_group'] ?? ''),
        'status' => (string)($_GET['status'] ?? ''),
        'deferral' => (string)($_GET['deferral'] ?? ''),
    ];

    $rows = bts_donor_records_get_all_donors($pdo);
    $filtered = bts_donor_records_filter_rows($rows, $filters);
    $total = count($filtered);
    $pageRows = bts_donor_records_paginate_rows($filtered, $page, $perPage);

    echo json_encode([
        'success' => true,
        'data' => $pageRows,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / max(1, $perPage))),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $exception->getMessage()]);
}
