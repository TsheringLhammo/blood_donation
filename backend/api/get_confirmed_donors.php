<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

bts_require_auth(['staff', 'admin']);

$tableHasColumn = static function (PDO $pdo, string $tableName, string $columnName): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
};

try {
    $hasSampleTested = $tableHasColumn($pdo, 'tbldonors', 'sample_tested');
    $samplePendingCondition = $hasSampleTested
        ? ' AND LOWER(TRIM(COALESCE(d.sample_tested, "pending"))) IN ("pending", "", "0", "not_tested") '
        : '';
    
        // Exclude donors who already have a donation/unit that is awaiting testing
        $hasUnitsDonationId = $tableHasColumn($pdo, 'tblblood_units', 'donation_id');
        $hasDonationTests = $tableHasColumn($pdo, 'tbldonation_tests', 'donation_id');
        $excludePendingDonationCondition = '';

        // Helper to test table existence safely
        $tableExists = static function (PDO $pdo, string $tableName): bool {
            try {
                $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
                $stmt->execute([$tableName]);
                return (bool)$stmt->fetch(PDO::FETCH_NUM);
            } catch (Throwable $e) {
                return false;
            }
        };

        $hasDonationsTable = $tableExists($pdo, 'tbldonations');

        if ($hasUnitsDonationId && $hasDonationTests) {
            // Build a safe NOT EXISTS clause. Only reference tbldonations when it exists.
            $donationJoinCondition = $hasDonationsTable
                ? ' OR (u.donation_id IS NOT NULL AND EXISTS (SELECT 1 FROM tbldonations dd WHERE CAST(dd.id AS CHAR) = CAST(u.donation_id AS CHAR) AND dd.donor_id = d.id))'
                : '';

            $excludePendingDonationCondition = ' AND NOT EXISTS (
                    SELECT 1 FROM tblblood_units u
                    WHERE (
                        (u.donor_id IS NOT NULL AND u.donor_id = d.id)'
                        . $donationJoinCondition . '
                    )
                    AND TRIM(CAST(u.donation_id AS CHAR)) <> ""
                    AND NOT EXISTS (
                        SELECT 1 FROM tbldonation_tests t WHERE CAST(t.donation_id AS CHAR) = CAST(u.donation_id AS CHAR)
                    )
                ) ';
        }

        // If tbldonor_samples exists, exclude donors that already have any sample record
        $hasDonorSamplesTable = $tableExists($pdo, 'tbldonor_samples');
        $excludeDonorSamplesCondition = '';
        if ($hasDonorSamplesTable) {
            $excludeDonorSamplesCondition = ' AND NOT EXISTS (SELECT 1 FROM tbldonor_samples s WHERE s.donor_id = d.id) ';
        }
    
    $stmt = $pdo->query(
        'SELECT DISTINCT d.id,
                d.full_name,
                d.email,
                d.blood_type,
                d.status,
                ' . ($hasSampleTested ? 'd.sample_tested' : 'NULL AS sample_tested') . ',
                ' . ($hasSampleTested ? 'd.sample_tested_at' : 'NULL AS sample_tested_at') . ',
                d.deferred_until,
                d.deferral_reason
         FROM tbldonors d
         WHERE (
             LOWER(TRIM(COALESCE(d.status, "pending"))) IN ("confirmed", "eligible", "active", "pending", "0")
             OR LOWER(COALESCE(d.status, "")) LIKE "%confirm%"
             OR LOWER(COALESCE(d.status, "")) LIKE "%approv%"
             OR LOWER(COALESCE(d.status, "")) LIKE "%ready%"
         )
            AND LOWER(COALESCE(d.status, "")) NOT LIKE "%defer%"
            AND LOWER(COALESCE(d.status, "")) NOT LIKE "%reject%"'
                . $samplePendingCondition . $excludePendingDonationCondition . $excludeDonorSamplesCondition .
           '
         ORDER BY d.full_name ASC, d.id DESC'
    );
    
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($donors)) {
                // Fallback: include likely eligible donor states while excluding deferred/rejected.
        $stmt = $pdo->query(
            'SELECT DISTINCT d.id,
                d.full_name,
                d.email,
                d.blood_type,
                d.status,
                ' . ($hasSampleTested ? 'd.sample_tested' : 'NULL AS sample_tested') . ',
                ' . ($hasSampleTested ? 'd.sample_tested_at' : 'NULL AS sample_tested_at') . ',
                d.deferred_until,
                d.deferral_reason
             FROM tbldonors d
             WHERE (
                             LOWER(TRIM(COALESCE(d.status, "pending"))) IN ("confirmed", "eligible", "active", "pending", "0")
               OR LOWER(COALESCE(d.status, "")) LIKE "%confirm%"
               OR LOWER(COALESCE(d.status, "")) LIKE "%approv%"
               OR LOWER(COALESCE(d.status, "")) LIKE "%ready%"
             )
                                                     AND LOWER(COALESCE(d.status, "")) NOT LIKE "%defer%"
                                                     AND LOWER(COALESCE(d.status, "")) NOT LIKE "%reject%"'
                                                     . $samplePendingCondition . $excludeDonorSamplesCondition .
                          '
             ORDER BY d.full_name ASC, d.id DESC'
        );
        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode(['success' => true, 'data' => $donors ?: []]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
