<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

$allowedOrigin = 'http://localhost:3000';
if (!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/config/auth.php';

bts_require_auth(['admin']);

function table_column_exists(PDO $pdo, string $table, string $column): bool
{
        $stmt = $pdo->prepare(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                     AND table_name = ?
                     AND column_name = ?
                 LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$sampleId = (int)($payload['sample_id'] ?? $payload['sampleId'] ?? 0);
$decision = strtolower(trim((string)($payload['decision'] ?? '')));
$deferUntil = trim((string)($payload['defer_until'] ?? $payload['deferUntil'] ?? ''));

if ($sampleId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'sample_id is required.']);
    exit;
}

if (!in_array($decision, ['accept', 'defer', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'decision must be accept, defer, or reject.']);
    exit;
}

try {
    $donorNameColumn = table_column_exists($pdo, 'tbldonors', 'name') ? 'name' : (table_column_exists($pdo, 'tbldonors', 'full_name') ? 'full_name' : 'NULL');

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT s.id, s.donor_id, s.admin_finalized, s.test_status, d.{$donorNameColumn} AS donor_name
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.id = ?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$sampleId]);
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sample) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sample not found.']);
        exit;
    }

    if ((int)$sample['admin_finalized'] === 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Sample has already been finalized.']);
        exit;
    }

    $donorId = (int)$sample['donor_id'];
    $donorName = (string)($sample['donor_name'] ?? '');
    $newStatus = 'Confirmed';
    $deferValue = null;

    if ($decision === 'defer') {
        $newStatus = 'Deferred';
        if ($deferUntil !== '') {
            $date = date_create($deferUntil);
            if (!$date) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'defer_until must be a valid date (YYYY-MM-DD).']);
                exit;
            }
            $deferValue = $date->format('Y-m-d');
        } else {
            $deferValue = date('Y-m-d', strtotime('+6 months'));
        }
    } elseif ($decision === 'reject') {
        $newStatus = 'Rejected';
    }

    $updateDonor = $pdo->prepare(
        "UPDATE tbldonors
         SET status = ?, defer_until = ?
         WHERE id = ?"
    );
    $updateDonor->execute([$newStatus, $deferValue, $donorId]);

    $updateSample = $pdo->prepare(
        "UPDATE tbldonor_samples
         SET admin_finalized = 1
         WHERE id = ?"
    );
    $updateSample->execute([$sampleId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Decision saved successfully.',
        'data' => [
            'sample_id' => $sampleId,
            'donor_id' => $donorId,
            'donor_name' => $donorName,
            'decision' => $decision,
            'donor_status' => $newStatus,
            'defer_until' => $deferValue,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
