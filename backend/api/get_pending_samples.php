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

bts_require_auth(['staff', 'admin']);

try {
    $stmt = $pdo->query(
        'SELECT 
            s.id,
            s.donor_id,
            d.full_name as donor_name,
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
            s.notes
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.status = "Pending"
         ORDER BY s.collection_date DESC
         LIMIT 100'
    );
    
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    echo json_encode([
        'success' => true,
        'data' => $samples
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
