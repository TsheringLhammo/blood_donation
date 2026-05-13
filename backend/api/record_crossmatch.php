<?php
declare(strict_types=1);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found.']);
    exit;
}
require_once $dbPath;
require_once __DIR__ . '/../config/auth.php';

$claims = bts_require_auth(['staff', 'admin']);
$technicianName = trim((string)($claims['email'] ?? 'Staff'));

function canonical_status(string $status): string
{
    $s = strtolower(trim($status));
    $s = str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}"], '-', $s);
    $compact = preg_replace('/[^a-z]/', '', $s) ?? '';

    if (in_array($compact, ['crossmatch', 'crossmatching'], true)) {
        return 'cross-matching';
    }
    if (in_array($compact, ['crossmatchcomplete', 'readytoissue', 'matched'], true)) {
        return 'matched';
    }
    if (in_array($compact, ['crossmatchfailed', 'rejected'], true)) {
        return 'rejected';
    }
    return $compact;
}

function normalize_crossmatch_result(string $result): string
{
    $normalized = strtolower(trim($result));
    if ($normalized === 'compatible') {
        return 'Compatible';
    }
    if ($normalized === 'incompatible') {
        return 'Incompatible';
    }
    return 'Pending';
}

function resolve_unit_identifier_column(PDO $pdo): ?string
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM tblblood_units LIKE 'unit_id'");
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return 'unit_id';
        }

        $stmt = $pdo->prepare("SHOW COLUMNS FROM tblblood_units LIKE 'donation_id'");
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return 'donation_id';
        }
    } catch (Throwable $ignored) {
        return null;
    }

    return null;
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];

    $requestId = isset($input['requestId']) ? (int)$input['requestId'] : 0;
    $result = normalize_crossmatch_result((string)($input['result'] ?? 'Pending'));
    $donorUnitRefs = trim((string)($input['donorUnitRefs'] ?? ''));
    $testParameters = trim((string)($input['testParameters'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));

    if ($requestId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid request id.']);
        exit;
    }

    $units = [];
    if ($donorUnitRefs !== '') {
        $units = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $donorUnitRefs))));
    }

    if ($result === 'Compatible' && count($units) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Compatible result requires donor unit ID(s).']);
        exit;
    }

    $unitIdentifierColumn = resolve_unit_identifier_column($pdo);
    if ($result === 'Compatible' && $unitIdentifierColumn === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'tblblood_units must contain unit_id or donation_id column.']);
        exit;
    }

    $pdo->beginTransaction();

    $requestStmt = $pdo->prepare('SELECT id, status FROM tblblood_requests WHERE id = ? FOR UPDATE');
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $currentStatusRaw = trim((string)($request['status'] ?? ''));
    $currentStatus = canonical_status($currentStatusRaw);
    if ($currentStatus !== 'cross-matching') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Cross-match can only be recorded when request status is "Cross-Matching". Please click "Start Cross-Match" first.',
            'currentStatus' => $currentStatusRaw,
            'normalizedStatus' => $currentStatus,
        ]);
        exit;
    }

    $latestLogStmt = $pdo->prepare(
        'SELECT result
         FROM tbllab_logs
         WHERE request_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $latestLogStmt->execute([$requestId]);
    $latestResult = trim((string)$latestLogStmt->fetchColumn());

    if (in_array($latestResult, ['Compatible', 'Incompatible'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Cross-match is already finalized for this request. Duplicate final results are blocked.',
            'currentStatus' => $currentStatusRaw,
        ]);
        exit;
    }

    $sampleReference = 'REQ-' . $requestId;
    $insertLabStmt = $pdo->prepare(
        'INSERT INTO tbllab_logs
            (test_name, sample_reference, request_id, patient_name, blood_type, component, units_requested, donor_unit_refs, test_parameters, notes, result, technician_name)
         SELECT
            :test_name, :sample_reference, br.id, br.patient_name, br.blood_type, br.component, br.units_requested,
            :donor_unit_refs, :test_parameters, :notes, :result, :technician_name
         FROM tblblood_requests br
         WHERE br.id = :request_id'
    );
    $insertLabStmt->execute([
        ':test_name' => 'Cross-Match',
        ':sample_reference' => $sampleReference,
        ':donor_unit_refs' => $donorUnitRefs !== '' ? $donorUnitRefs : null,
        ':test_parameters' => $testParameters !== '' ? $testParameters : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':result' => $result,
        ':technician_name' => $technicianName,
        ':request_id' => $requestId,
    ]);

    if ($result === 'Compatible') {
        $reserveStmt = $pdo->prepare(
            "UPDATE tblblood_units
             SET status = 'Reserved', request_id = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE {$unitIdentifierColumn} = ?
                             AND (LOWER(status) = 'available' OR (LOWER(status) = 'reserved' AND request_id = ?))"
        );

        foreach ($units as $donationId) {
            $reserveStmt->execute([$requestId, $donationId, $requestId]);
        }

        $updateRequestStmt = $pdo->prepare("UPDATE tblblood_requests SET status = 'Matched', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateRequestStmt->execute([$requestId]);
    } elseif ($result === 'Incompatible') {
        $updateRequestStmt = $pdo->prepare("UPDATE tblblood_requests SET status = 'Rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateRequestStmt->execute([$requestId]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cross-match recorded successfully.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
