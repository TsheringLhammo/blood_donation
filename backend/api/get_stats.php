<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['admin']);

try {
    $donors       = (int) $pdo->query("SELECT COUNT(*) FROM tbldonors")->fetchColumn();
    $appointments = (int) $pdo->query("SELECT COUNT(*) FROM tblappointments")->fetchColumn();
    $upcoming     = (int) $pdo->query("SELECT COUNT(*) FROM tblappointments WHERE preferred_date >= CURDATE()")->fetchColumn();
    $camps        = (int) $pdo->query("SELECT COUNT(*) FROM tblblood_camps")->fetchColumn();

    echo json_encode([
        'success'      => true,
        'donors'       => $donors,
        'appointments' => $appointments,
        'upcoming'     => $upcoming,
        'camps'        => $camps,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
