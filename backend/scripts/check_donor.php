<?php
require __DIR__ . '/../config/db.php';
$email = $argv[1] ?? 'kileyye12@gmail.com';
$stmt = $pdo->prepare('SELECT id, full_name, email FROM tbldonors WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($r, JSON_PRETTY_PRINT) . PHP_EOL;
