<?php
require __DIR__ . '/../config/db.php';
$rows = $pdo->query("SELECT COALESCE(TRIM(status), '') AS status_value, COUNT(*) AS total FROM tbldonors GROUP BY COALESCE(TRIM(status), '') ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
