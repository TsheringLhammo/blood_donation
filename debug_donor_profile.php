<?php
require_once __DIR__ . "/backend/config/db.php";
require_once __DIR__ . "/backend/api/workflow_helpers.php";

function check_columns($pdo) {
    echo "Checking columns in tbldonors table:\n";
    $columnsToCheck = [
        "address", "city", "dzongkhag", "date_of_birth", 
        "emergency_contact_name", "emergency_contact_phone",
        "blood_type", "age", "gender"
    ];
    
    foreach ($columnsToCheck as $col) {
        $has = workflow_table_has_column($pdo, "tbldonors", $col) ? "EXISTS" : "MISSING";
        echo "- $col: $has\n";
    }
}

function test_profile_sql($pdo) {
    echo "\nTesting Profile SQL Generation:\n";
    $columnOrNull = function ($pdo, $table, $column) {
        return workflow_table_has_column($pdo, $table, $column) ? $column : "NULL AS " . $column;
    };

    $sql = "SELECT id,
                full_name,
                REPLACE(TRIM(COALESCE(email, \"\")), \" \", \"\") AS email,
                REPLACE(TRIM(COALESCE(phone, \"\")), \" \", \"\") AS phone,
                " . $columnOrNull($pdo, "tbldonors", "date_of_birth") . ",
                " . $columnOrNull($pdo, "tbldonors", "address") . ",
                " . $columnOrNull($pdo, "tbldonors", "city") . ",
                " . $columnOrNull($pdo, "tbldonors", "dzongkhag") . ",
                " . $columnOrNull($pdo, "tbldonors", "emergency_contact_name") . ",
                " . $columnOrNull($pdo, "tbldonors", "emergency_contact_phone") . "
         FROM tbldonors
         LIMIT 1";
    
    echo "Generated SQL:\n$sql\n";
    
    try {
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nActual Data Returned (First Row):\n";
        print_r($row);
    } catch (Exception $e) {
        echo "\nError executing SQL: " . $e->getMessage() . "\n";
    }
}

check_columns($pdo);
test_profile_sql($pdo);
