<?php
// CLI script to query the local blood_donation DB for donors named 'Tshering YO'
// Usage: php scripts/run_queries.php

$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=blood_donation;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $name1 = '%Tshering YO%';
    $name2 = '%Tshering%';

    $q1 = "SELECT id, full_name, email, status, sample_tested FROM tbldonors WHERE full_name LIKE :name1 OR full_name LIKE :name2 LIMIT 50";
    $stmt1 = $pdo->prepare($q1);
    $stmt1->execute([':name1' => $name1, ':name2' => $name2]);
    $donors = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $q2 = "SELECT id, donation_id, donor_id, component, blood_type, created_at FROM tblblood_units WHERE donor_id IN (SELECT id FROM tbldonors WHERE full_name LIKE :name1 OR full_name LIKE :name2) ORDER BY created_at DESC LIMIT 50";
    $stmt2 = $pdo->prepare($q2);
    $stmt2->execute([':name1' => $name1, ':name2' => $name2]);
    $units = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $q3 = "SELECT id, donation_id, final_result, created_at FROM tbldonation_tests WHERE CAST(donation_id AS CHAR) IN (SELECT CAST(donation_id AS CHAR) FROM tblblood_units WHERE donor_id IN (SELECT id FROM tbldonors WHERE full_name LIKE :name1 OR full_name LIKE :name2)) ORDER BY created_at DESC LIMIT 50";
    $stmt3 = $pdo->prepare($q3);
    $stmt3->execute([':name1' => $name1, ':name2' => $name2]);
    $tests = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $out = [
        'donors' => $donors,
        'units' => $units,
        'tests' => $tests,
    ];

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(2);
}

