<?php
require 'backend/config/db.php';

// Check Nado's actual data
$stmt = $pdo->prepare('SELECT id, full_name, workflow_status, email FROM tbldonors WHERE full_name = ? LIMIT 1');
$stmt->execute(['Nado']);
$nado = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== Direct Database Query ===\n";
echo json_encode($nado, JSON_PRETTY_PRINT) . "\n\n";

// Now test what get_donor_profile.php would return
echo "=== Testing get_donor_profile logic ===\n";
if ($nado) {
    $donorId = $nado['id'];
    
    // Helper function from get_donor_profile.php
    $columnOrNull = static function (PDO $pdo, string $table, string $column): string {
        require_once 'backend/api/workflow_helpers.php';
        return workflow_table_has_column($pdo, $table, $column) ? $column : 'NULL AS ' . $column;
    };
    
    $stmt2 = $pdo->prepare(
        'SELECT id,
                full_name,
                REPLACE(TRIM(COALESCE(email, "")), " ", "") AS email,
                REPLACE(TRIM(COALESCE(phone, "")), " ", "") AS phone,
                ' . $columnOrNull($pdo, 'tbldonors', 'date_of_birth') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'address') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'city') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'dzongkhag') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'emergency_contact_name') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'emergency_contact_phone') . ',
                ' . $columnOrNull($pdo, 'tbldonors', 'workflow_status') . '
         FROM tbldonors
         WHERE id = ?
         LIMIT 1'
    );
    $stmt2->execute([$donorId]);
    $result = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
