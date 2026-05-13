<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

session_start();
if (!isset($_SESSION['role']) || !in_array((string)$_SESSION['role'], ['admin', 'staff'], true)) {
  http_response_code(403);
  echo 'Access denied. Staff/Admin login required.';
  exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function request_pk_column(PDO $pdo): string
{
    return has_column($pdo, 'tblblood_requests', 'request_id') ? 'request_id' : 'id';
}

function request_units_column(PDO $pdo): string
{
    return has_column($pdo, 'tblblood_requests', 'units_requested') ? 'units_requested' : 'units';
}

function request_patient_column(PDO $pdo): string
{
    return has_column($pdo, 'tblblood_requests', 'patient_name') ? 'patient_name' : 'patient';
}

function request_priority_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_requests', 'priority')) {
        return 'priority';
    }
    if (has_column($pdo, 'tblblood_requests', 'urgency')) {
        return 'urgency';
    }
    return 'status';
}

function request_doctor_name_column(PDO $pdo): string
{
  if (has_column($pdo, 'tblblood_requests', 'doctor_name')) {
    return 'doctor_name';
  }
  return 'requested_by';
}

function resolve_crossmatch_unit_column(PDO $pdo): ?string
{
  if (has_column($pdo, 'tblcrossmatch', 'donation_id')) {
    return 'donation_id';
  }
  if (has_column($pdo, 'tblcrossmatch', 'unit_id')) {
    return 'unit_id';
  }

  return null;
}

  function request_code_expression(PDO $pdo, string $requestPk): string
  {
    if (has_column($pdo, 'tblblood_requests', 'request_code')) {
      return 'request_code';
    }

    return 'CONCAT("REQ-", ' . $requestPk . ')';
  }

function unit_identifier_column(PDO $pdo): string
{
    if (has_column($pdo, 'tblblood_units', 'unit_id')) {
        return 'unit_id';
    }
    if (has_column($pdo, 'tblblood_units', 'donation_id')) {
        return 'donation_id';
    }
    throw new RuntimeException('tblblood_units needs unit_id or donation_id column.');
}

function component_inventory_column(PDO $pdo, string $component): string
{
    $normalized = strtolower(trim($component));
    if (in_array($normalized, ['prbc', 'packed red cells'], true)) {
        return 'prbc_units';
    }
    if ($normalized === 'platelets') {
        return 'platelets_units';
    }
    if (in_array($normalized, ['ffp', 'plasma', 'fresh frozen plasma'], true)) {
      return has_column($pdo, 'tblinventory', 'fpp_units') ? 'fpp_units' : 'ffp_units';
    }
    return 'whole_units';
}

  function find_doctor_email(PDO $pdo, int $requestId, string $requestPk): ?string
  {
    if (has_column($pdo, 'tblblood_requests', 'doctor_email')) {
      $stmt = $pdo->prepare('SELECT doctor_email FROM tblblood_requests WHERE ' . $requestPk . ' = ? LIMIT 1');
      $stmt->execute([$requestId]);
      $email = trim((string)$stmt->fetchColumn());
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
      }
    }

    if (has_column($pdo, 'tblblood_requests', 'doctor_user_id')) {
      $stmt = $pdo->prepare(
        'SELECT u.email
         FROM tblblood_requests r
         INNER JOIN tblusers u ON u.id = r.doctor_user_id
         WHERE r.' . $requestPk . ' = ?
         LIMIT 1'
      );
      $stmt->execute([$requestId]);
      $email = trim((string)$stmt->fetchColumn());
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
      }
    }

    return null;
  }

$message = '';
$error = '';

try {
    $requestPk = request_pk_column($pdo);
    $unitsColumn = request_units_column($pdo);
    $patientColumn = request_patient_column($pdo);
    $priorityColumn = request_priority_column($pdo);
    $doctorNameColumn = request_doctor_name_column($pdo);
    $requestCodeExpr = request_code_expression($pdo, $requestPk);
    $unitIdColumn = unit_identifier_column($pdo);
    $crossmatchUnitColumn = resolve_crossmatch_unit_column($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $issuedBy = trim((string)($_POST['issued_by'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($requestId <= 0) {
            throw new RuntimeException('request_id is required.');
        }
        if ($issuedBy === '') {
            throw new RuntimeException('issued_by is required.');
        }

        $pdo->beginTransaction();

        $requestSql = 'SELECT ' . $requestPk . ' AS request_id,
                              ' . $patientColumn . ' AS patient,
                              blood_type,
                              component,
                              ' . $unitsColumn . ' AS units_needed,
                              ' . $priorityColumn . ' AS priority,
                ' . $doctorNameColumn . ' AS doctor_name,
                              status,
                ' . $requestCodeExpr . ' AS request_code
                       FROM tblblood_requests
                       WHERE ' . $requestPk . ' = ?
                       FOR UPDATE';
        $requestStmt = $pdo->prepare($requestSql);
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new RuntimeException('Request not found.');
        }

        $status = strtolower(trim((string)$request['status']));
        if ($status !== 'matched') {
          throw new RuntimeException('Only Matched requests can be issued.');
        }

        $reservedUnitsStmt = $pdo->prepare(
            'SELECT id, blood_bank_id, blood_type, component, ' . $unitIdColumn . ' AS unit_ref
             FROM tblblood_units
             WHERE request_id = ? AND status = "Reserved"
             FOR UPDATE'
        );
        $reservedUnitsStmt->execute([$requestId]);
        $reservedUnits = $reservedUnitsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$reservedUnits) {
            throw new RuntimeException('No reserved units found for this request.');
        }

        $reservedUnitIds = array_map(static fn(array $row): int => (int)$row['id'], $reservedUnits);

        $issueUnitsStmt = $pdo->prepare(
            'UPDATE tblblood_units
             SET status = "Issued", updated_at = CURRENT_TIMESTAMP
             WHERE id IN (' . implode(',', array_fill(0, count($reservedUnitIds), '?')) . ')'
        );
        $issueUnitsStmt->execute($reservedUnitIds);

        $grouped = [];
        foreach ($reservedUnits as $unit) {
            $key = (int)$unit['blood_bank_id'] . '|' . (string)$unit['blood_type'] . '|' . (string)$unit['component'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'blood_bank_id' => (int)$unit['blood_bank_id'],
                    'blood_type' => (string)$unit['blood_type'],
                    'component' => (string)$unit['component'],
                    'count' => 0,
                ];
            }
            $grouped[$key]['count']++;
        }

        $hasAutoInventorySync = false;
        $syncCheckStmt = $pdo->query(
          "SELECT COUNT(*)
           FROM information_schema.routines
           WHERE routine_schema = DATABASE()
             AND routine_type = 'PROCEDURE'
             AND routine_name = 'sp_sync_inventory_for_type'"
        );
        if ($syncCheckStmt) {
          $hasAutoInventorySync = ((int)$syncCheckStmt->fetchColumn()) > 0;
        }

        // If sync triggers/procedure are not installed yet, fallback to manual decrement.
        if (!$hasAutoInventorySync) {
          foreach ($grouped as $group) {
            $inventoryColumn = component_inventory_column($pdo, (string)$group['component']);
            $invStmt = $pdo->prepare(
              'UPDATE tblinventory
               SET ' . $inventoryColumn . ' = GREATEST(0, ' . $inventoryColumn . ' - :issued_count),
                 updated_at = CURRENT_TIMESTAMP
               WHERE blood_bank_id = :blood_bank_id AND blood_type = :blood_type'
            );
            $invStmt->execute([
              ':issued_count' => (int)$group['count'],
              ':blood_bank_id' => (int)$group['blood_bank_id'],
              ':blood_type' => (string)$group['blood_type'],
            ]);
          }
        }

        $issueLogColumns = ['request_id'];
        $issueLogValues = [':request_id'];

        if (has_column($pdo, 'tblissue_logs', 'issued_by')) {
          $issueLogColumns[] = 'issued_by';
          $issueLogValues[] = ':issued_by';
        }
        if (has_column($pdo, 'tblissue_logs', 'issued_at')) {
          $issueLogColumns[] = 'issued_at';
          $issueLogValues[] = 'NOW()';
        }
        if (has_column($pdo, 'tblissue_logs', 'notes')) {
          $issueLogColumns[] = 'notes';
          $issueLogValues[] = ':notes';
        }
        if (has_column($pdo, 'tblissue_logs', 'request_code')) {
            $issueLogColumns[] = 'request_code';
            $issueLogValues[] = ':request_code';
        }
        if (has_column($pdo, 'tblissue_logs', 'patient_name')) {
            $issueLogColumns[] = 'patient_name';
            $issueLogValues[] = ':patient_name';
        }
        if (has_column($pdo, 'tblissue_logs', 'blood_type')) {
            $issueLogColumns[] = 'blood_type';
            $issueLogValues[] = ':blood_type';
        }
        if (has_column($pdo, 'tblissue_logs', 'component')) {
            $issueLogColumns[] = 'component';
            $issueLogValues[] = ':component';
        }
        if (has_column($pdo, 'tblissue_logs', 'units_issued')) {
            $issueLogColumns[] = 'units_issued';
            $issueLogValues[] = ':units_issued';
        }
        if (has_column($pdo, 'tblissue_logs', 'staff_name')) {
            $issueLogColumns[] = 'staff_name';
            $issueLogValues[] = ':staff_name';
        }

        $logSql = 'INSERT INTO tblissue_logs (' . implode(', ', $issueLogColumns) . ') VALUES (' . implode(', ', $issueLogValues) . ')';
        $logStmt = $pdo->prepare($logSql);
        $logPayload = [':request_id' => $requestId];
        if (in_array(':issued_by', $issueLogValues, true)) {
          $logPayload[':issued_by'] = $issuedBy;
        }
        if (in_array(':notes', $issueLogValues, true)) {
          $logPayload[':notes'] = $notes;
        }
        if (in_array(':request_code', $issueLogValues, true)) {
          $logPayload[':request_code'] = (string)$request['request_code'];
        }
        if (in_array(':patient_name', $issueLogValues, true)) {
          $logPayload[':patient_name'] = (string)$request['patient'];
        }
        if (in_array(':blood_type', $issueLogValues, true)) {
          $logPayload[':blood_type'] = (string)$request['blood_type'];
        }
        if (in_array(':component', $issueLogValues, true)) {
          $logPayload[':component'] = (string)$request['component'];
        }
        if (in_array(':units_issued', $issueLogValues, true)) {
          $logPayload[':units_issued'] = count($reservedUnits);
        }
        if (in_array(':staff_name', $issueLogValues, true)) {
          $logPayload[':staff_name'] = $issuedBy;
        }
        $logStmt->execute($logPayload);

        $updateSql = 'UPDATE tblblood_requests SET status = "Issued"';
        if (has_column($pdo, 'tblblood_requests', 'updated_at')) {
          $updateSql .= ', updated_at = CURRENT_TIMESTAMP';
        }
        $updateSql .= ' WHERE ' . $requestPk . ' = ?';
        $updateRequestStmt = $pdo->prepare($updateSql);
        $updateRequestStmt->execute([$requestId]);

        $pdo->commit();
        $message = 'Request ' . (string)$request['request_code'] . ' issued successfully.';

        $doctorEmail = find_doctor_email($pdo, $requestId, $requestPk);
        if ($doctorEmail !== null) {
          $subject = 'Blood Issue Completed for ' . (string)$request['patient'];
          $html = '<p>Dear Doctor,</p>'
            . '<p>Blood units are ready and issued for your patient.</p>'
            . '<ul>'
            . '<li><strong>Request:</strong> ' . h((string)$request['request_code']) . '</li>'
            . '<li><strong>Patient:</strong> ' . h((string)$request['patient']) . '</li>'
            . '<li><strong>Blood Type:</strong> ' . h((string)$request['blood_type']) . '</li>'
            . '<li><strong>Component:</strong> ' . h((string)$request['component']) . '</li>'
            . '<li><strong>Units Issued:</strong> ' . (string)count($reservedUnits) . '</li>'
            . '</ul>'
            . '<p>Issued by: ' . h($issuedBy) . '</p>';
          $text = 'Blood units issued. Request: ' . (string)$request['request_code']
            . ' | Patient: ' . (string)$request['patient']
            . ' | Blood Type: ' . (string)$request['blood_type']
            . ' | Component: ' . (string)$request['component']
            . ' | Units Issued: ' . (string)count($reservedUnits)
            . ' | Issued by: ' . $issuedBy;
          $mailMeta = [];
          $emailSent = bts_send_email($doctorEmail, $subject, $html, $text, $mailMeta);
          if (!$emailSent) {
            $message .= ' Issue recorded, but doctor email failed: ' . (string)($mailMeta['error'] ?? 'Unknown mail error.');
          }
        }
    }

    $listSql = 'SELECT ' . $requestPk . ' AS request_id,
                       ' . $requestCodeExpr . ' AS request_code,
                       ' . $patientColumn . ' AS patient,
                       blood_type,
                       component,
                       ' . $unitsColumn . ' AS units_needed,
                       ' . $priorityColumn . ' AS priority,
                       status,
                       created_at
                FROM tblblood_requests
                WHERE LOWER(status) = "matched"
                ORDER BY created_at DESC';
    $requestListStmt = $pdo->query($listSql);
    $requests = $requestListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $selectedRequestId = (int)($_GET['request_id'] ?? 0);
    $reservedUnitsForSelected = [];
    if ($selectedRequestId > 0) {
        $selectedUnitsStmt = $pdo->prepare(
            'SELECT ' . $unitIdColumn . ' AS unit_ref, blood_type, component, expiry_date
             FROM tblblood_units
             WHERE request_id = ? AND status = "Reserved"
             ORDER BY expiry_date ASC, id ASC'
        );
        $selectedUnitsStmt->execute([$selectedRequestId]);
        $reservedUnitsForSelected = $selectedUnitsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

      if (!$reservedUnitsForSelected && $crossmatchUnitColumn !== null) {
        $crossmatchStmt = $pdo->prepare(
          'SELECT DISTINCT c.' . $crossmatchUnitColumn . ' AS unit_ref, "-" AS blood_type, "-" AS component, NULL AS expiry_date
           FROM tblcrossmatch c
           WHERE c.request_id = ?
             AND c.result = "Compatible"
             AND c.' . $crossmatchUnitColumn . ' IS NOT NULL
             AND c.' . $crossmatchUnitColumn . ' <> ""'
        );
        $crossmatchStmt->execute([$selectedRequestId]);
        $reservedUnitsForSelected = $crossmatchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
    $requests = [];
    $selectedRequestId = 0;
    $reservedUnitsForSelected = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Issue Blood</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f4f7fb; color: #0f172a; }
    .wrap { max-width: 1000px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,0.07); }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; }
    .muted { color: #64748b; }
    .error { background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
    .ok { background: #dcfce7; color: #166534; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
    .panel { border: 1px solid #dbe2ef; border-radius: 8px; padding: 12px; margin-top: 16px; background: #f8fafc; }
    .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
    input, textarea { padding: 10px; border: 1px solid #d6deea; border-radius: 8px; font-size: 14px; }
    button { background: #c8102e; color: #fff; border: none; padding: 11px 16px; border-radius: 8px; cursor: pointer; }
    button:hover { background: #a60d25; }
    .link-btn { color: #0f172a; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Issue Blood Units</h1>

  <?php if ($error !== ''): ?>
    <div class="error"><?php echo h($error); ?></div>
  <?php endif; ?>

  <?php if ($message !== ''): ?>
    <div class="ok"><?php echo h($message); ?></div>
  <?php endif; ?>

  <p class="muted">Requests below are in Matched status and ready for final issuance.</p>

  <table>
    <thead>
      <tr>
        <th>Request</th>
        <th>Patient</th>
        <th>Type</th>
        <th>Component</th>
        <th>Units</th>
        <th>Priority</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$requests): ?>
        <tr><td colspan="8">No matched requests available.</td></tr>
      <?php else: ?>
        <?php foreach ($requests as $row): ?>
          <tr>
            <td><?php echo h((string)$row['request_code']); ?></td>
            <td><?php echo h((string)$row['patient']); ?></td>
            <td><?php echo h((string)$row['blood_type']); ?></td>
            <td><?php echo h((string)$row['component']); ?></td>
            <td><?php echo h((string)$row['units_needed']); ?></td>
            <td><?php echo h((string)$row['priority']); ?></td>
            <td><?php echo h((string)$row['status']); ?></td>
            <td><a class="link-btn" href="?request_id=<?php echo h((string)$row['request_id']); ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($selectedRequestId > 0): ?>
    <div class="panel">
      <h2>Reserved Units for Request #<?php echo h((string)$selectedRequestId); ?></h2>
      <?php if (!$reservedUnitsForSelected): ?>
        <div class="muted">No reserved units found for this request.</div>
      <?php else: ?>
        <ul>
          <?php foreach ($reservedUnitsForSelected as $unit): ?>
            <li>
              <?php echo h((string)$unit['unit_ref']); ?> |
              <?php echo h((string)$unit['blood_type']); ?> |
              <?php echo h((string)$unit['component']); ?> |
              Exp: <?php echo h((string)$unit['expiry_date']); ?>
            </li>
          <?php endforeach; ?>
        </ul>

        <form method="post">
          <input type="hidden" name="request_id" value="<?php echo h((string)$selectedRequestId); ?>">
          <div class="field">
            <label>Issued By</label>
            <input type="text" name="issued_by" required placeholder="Staff name">
          </div>
          <div class="field">
            <label>Notes</label>
            <textarea name="notes" rows="3" placeholder="Issue notes"></textarea>
          </div>
          <button type="submit" onclick="return confirm('Confirm issuance for this request?');">Confirm Issuance</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
