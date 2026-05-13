<?php
require 'backend/config/db.php';

echo "=== Testing Book Appointment Flow ===\n\n";

// 1. Find Nado in database
echo "1. Checking Nado's status...\n";
$stmt = $pdo->prepare('SELECT id, full_name, email, status, workflow_status, deferred FROM tbldonors WHERE email = ?');
$stmt->execute(['Nado@gmail.com']);
$nado = $stmt->fetch(PDO::FETCH_ASSOC);

if ($nado) {
    echo "   Full Name: " . $nado['full_name'] . "\n";
    echo "   Status: " . $nado['status'] . "\n";
    echo "   Workflow Status: " . $nado['workflow_status'] . "\n";
    echo "   Deferred: " . ($nado['deferred'] ?? 0) . "\n\n";
} else {
    echo "   ERROR: Nado not found!\n\n";
    exit;
}

// 2. Check deferred
echo "2. Checking deferral status...\n";
$donorDeferred = (int)($nado['deferred'] ?? 0);
if ($donorDeferred === 1) {
    echo "   ❌ FAIL: Donor is deferred\n";
    exit;
} else {
    echo "   ✅ PASS: Not deferred\n\n";
}

// 3. Check status field
echo "3. Checking status field approval...\n";
$donorStatus = strtolower(trim((string)($nado['status'] ?? 'Pending')));
echo "   Status value: '" . $donorStatus . "'\n";

$approvedStatuses = ['active', 'confirmed', 'approved', 'pending', 'approved for blood d', 'ready'];
$isApprovedStatus = false;
foreach ($approvedStatuses as $approvedStatus) {
    if (stripos($donorStatus, trim($approvedStatus)) !== false) {
        $isApprovedStatus = true;
        echo "   ✅ MATCH: Found '" . $approvedStatus . "' in status\n";
        break;
    }
}

if (!$isApprovedStatus) {
    echo "   No match in approved statuses, checking workflow_status...\n\n";
    
    // 4. Check workflow status
    echo "4. Checking workflow_status...\n";
    $workflowStatus = strtolower(trim((string)($nado['workflow_status'] ?? '')));
    echo "   Workflow Status value: '" . $workflowStatus . "'\n";
    
    if ($workflowStatus === 'decision_made_accepted') {
        echo "   ✅ PASS: workflow_status is decision_made_accepted\n\n";
    } else {
        echo "   ❌ FAIL: workflow_status is not decision_made_accepted\n";
        exit;
    }
} else {
    echo "\n";
}

// 5. Check tblappointments table structure
echo "5. Checking tblappointments table structure...\n";
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'tblappointments'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        echo "   ✅ Table 'tblappointments' exists\n";
        
        $columnsCheck = $pdo->query("SHOW COLUMNS FROM tblappointments");
        $columns = $columnsCheck->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "   Columns: " . implode(', ', array_slice($columns, 0, 10)) . "...\n";
    } else {
        echo "   ❌ Table 'tblappointments' not found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error checking table: " . $e->getMessage() . "\n";
}

echo "\n✅ All checks passed! Appointment should be bookable.\n";
