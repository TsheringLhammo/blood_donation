<?php
// Test the donor API without authentication
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Connect to database
    $dbHost = '127.0.0.1';
    $dbPort = '3306';
    $dbName = 'blood_donation';
    $dbUser = 'root';
    $dbPass = '';
    
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // Test the exact query from get_confirmed_donors.php
    $stmt = $pdo->query(
        'SELECT DISTINCT d.id,
                d.full_name,
                d.email,
                d.blood_type,
                d.status,
                NULL AS sample_tested,
                NULL AS sample_tested_at,
                d.deferred_until,
                d.deferral_reason
         FROM tbldonors d
         WHERE LOWER(TRIM(COALESCE(d.status, "pending"))) IN ("confirmed", "eligible", "active")
         ORDER BY d.full_name ASC, d.id DESC'
    );
    
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($donors),
        'data' => $donors,
        'debug' => [
            'query' => 'SELECT confirmed/eligible/active donors',
            'total_donors' => $pdo->query('SELECT COUNT(*) FROM tbldonors')->fetchColumn(),
            'confirmed_count' => count($donors)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => 'Database connection or query failed'
    ]);
}
?>
