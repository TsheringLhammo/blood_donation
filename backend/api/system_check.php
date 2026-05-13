<?php
/**
 * System Check: Verify all required tables and columns exist
 * Usage: Run from browser to see system status
 */

require_once __DIR__ . '/../config/db.php';

try {
    echo "=== BLOOD DONATION SYSTEM - TABLE CHECK ===\n\n";

    $tables = [
        'tblblood_requests' => ['id', 'status', 'request_code', 'patient_name', 'blood_type', 'component', 'units_requested', 'urgency'],
        'tblinventory' => ['blood_type', 'whole_units', 'prbc_units', 'platelets_units', 'ffp_units'],
        'tblblood_units' => ['id', 'donation_id', 'status', 'blood_type', 'component', 'expiry_date', 'request_id'],
        'tblissue_logs' => ['id', 'request_id', 'request_code', 'patient_name', 'blood_type', 'component', 'units_issued', 'staff_name', 'notes'],
        'tbllab_logs' => ['id', 'request_id', 'result', 'donor_unit_refs', 'technician_name'],
        'tblstock_ledger' => ['blood_bank_id', 'blood_type', 'component', 'movement_type', 'units', 'before_units', 'after_units'],
        'tblnotifications' => ['id', 'user_id', 'role_target', 'title', 'message', 'severity'],
        'tblrequest_status_logs' => ['id', 'request_id', 'from_status', 'to_status', 'action'],
    ];

    foreach ($tables as $tableName => $requiredColumns) {
        $result = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($result && $result->rowCount() > 0) {
            echo "✓ Table: $tableName\n";
            
            $columns = $pdo->query("SHOW COLUMNS FROM $tableName");
            $colNames = [];
            while ($row = $columns->fetch(PDO::FETCH_ASSOC)) {
                $colNames[] = $row['Field'];
            }
            
            foreach ($requiredColumns as $col) {
                if (in_array($col, $colNames)) {
                    echo "  ✓ Column: $col\n";
                } else {
                    echo "  ✗ Column MISSING: $col\n";
                }
            }
        } else {
            echo "✗ Table MISSING: $tableName\n";
        }
        echo "\n";
    }

    echo "\n=== REQUEST STATUS CHECK ===\n";
    $statusCheck = $pdo->query("SELECT status, COUNT(*) as count FROM tblblood_requests GROUP BY status");
    while ($row = $statusCheck->fetch(PDO::FETCH_ASSOC)) {
        echo "Status '{$row['status']}': {$row['count']} requests\n";
    }

    echo "\n=== INVENTORY CHECK ===\n";
    $invCheck = $pdo->query("SELECT blood_type, (whole_units + prbc_units + platelets_units + ffp_units) as total FROM tblinventory");
    $invCount = 0;
    while ($row = $invCheck->fetch(PDO::FETCH_ASSOC)) {
        echo "Blood Type {$row['blood_type']}: {$row['total']} units\n";
        $invCount++;
    }
    if ($invCount === 0) {
        echo "No inventory records found. Run migration: backend/sql/migrate_multibank_inventory.sql\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
