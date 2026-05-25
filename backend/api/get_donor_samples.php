<?php
/**
 * get_donor_samples.php
 * Returns donor samples for Staff Dashboard (Samples tab)
 */

declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

// Database configuration
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'blood_donation';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

try {
    // Query for samples
    $query = "
        SELECT 
            s.id,
            s.donor_id,
            d.full_name AS donor_name,
            d.blood_type,
            s.collection_date,
            s.technician,
            s.status,
            s.hiv_result,
            s.hbsag_result,
            s.hcv_result,
            s.syphilis_result,
            s.malaria_result,
            s.tested_by,
            s.tested_at,
            s.test_status,
            s.admin_finalized,
            s.notes,
            s.created_at,
            s.updated_at
        FROM tbldonor_samples s
        INNER JOIN tbldonors d ON d.id = s.donor_id
        ORDER BY s.collection_date DESC, s.id DESC
        LIMIT 200
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $samples = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $samples,
        'total' => count($samples)
    ]);
    
} catch (PDOException $exception) {
    error_log("get_donor_samples.php error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $exception->getMessage()
    ]);
} catch (Throwable $exception) {
    error_log("get_donor_samples.php error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
}
?>