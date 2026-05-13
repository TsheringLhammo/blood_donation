<?php
/**
 * Reset Script: Reset test requests to "Approved" status for testing
 * Usage: Run from browser at http://your-site/backend/reset_requests.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Check authentication
try {
    $claims = bts_require_auth(['admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Reset all requests that are in terminal states back to "Approved"
    // This allows re-testing the workflow
    $stmt = $pdo->prepare(
        "UPDATE tblblood_requests 
         SET status = 'Approved', updated_at = CURRENT_TIMESTAMP 
         WHERE status IN ('Rejected', 'Issued', 'Pending')"
    );
    $stmt->execute();
    $count = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Reset {$count} requests to 'Approved' status",
        'affectedRows' => $count
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
