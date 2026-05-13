<?php
require_once __DIR__ . '/config/db.php';

try {
    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM tblinventory');
    $count = $stmt->fetchColumn();
    echo "Total inventory records: $count\n";
    
    if ($count > 0) {
        $stmt = $pdo->query('SELECT * FROM tblinventory LIMIT 2');
        echo "\nSample inventory rows:\n";
        var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM tblblood_units');
    $unitCount = $stmt->fetchColumn();
    echo "\n\nTotal blood units: $unitCount\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
