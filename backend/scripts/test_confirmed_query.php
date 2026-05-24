<?php
require __DIR__ . '/../config/db.php';
$hasSampleTested = true;
try {
    $stmt = $pdo->query(
        'SELECT DISTINCT d.id,
                d.full_name,
                d.email,
                d.blood_type,
                d.status,
                ' . ($hasSampleTested ? 'd.sample_tested' : 'NULL AS sample_tested') . ',
                ' . ($hasSampleTested ? 'd.sample_tested_at' : 'NULL AS sample_tested_at') . ',
                d.deferred_until,
                d.deferral_reason
         FROM tbldonors d
         WHERE (
             LOWER(TRIM(COALESCE(d.status, "pending"))) IN ("confirmed", "eligible", "active")
             OR LOWER(COALESCE(d.status, "")) LIKE "%confirm%"
             OR LOWER(COALESCE(d.status, "")) LIKE "%approv%"
         )
         ORDER BY d.full_name ASC, d.id DESC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['count' => count($rows), 'first' => $rows[0] ?? null], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
