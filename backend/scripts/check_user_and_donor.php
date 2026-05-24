<?php
require __DIR__ . '/../config/db.php';
$email = $argv[1] ?? 'kileyye12@gmail.com';
$output = [];
$stmt = $pdo->prepare('SELECT id, name, email FROM tblusers WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$output['tblusers'] = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt2 = $pdo->prepare('SELECT id, full_name, email, status, workflow_status FROM tbldonors WHERE email = ? LIMIT 1');
$stmt2->execute([$email]);
$output['tbldonors'] = $stmt2->fetch(PDO::FETCH_ASSOC);
echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
