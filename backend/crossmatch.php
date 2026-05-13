<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

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

function component_aliases(string $component): array
{
    $normalized = strtolower(trim($component));
    $map = [
        'whole blood' => ['Whole Blood'],
        'prbc' => ['PRBC', 'Packed Red Cells'],
        'packed red cells' => ['PRBC', 'Packed Red Cells'],
        'platelets' => ['Platelets'],
        'plasma' => ['FFP', 'Plasma', 'Fresh Frozen Plasma'],
        'ffp' => ['FFP', 'Plasma', 'Fresh Frozen Plasma'],
        'fresh frozen plasma' => ['FFP', 'Plasma', 'Fresh Frozen Plasma'],
    ];

    return $map[$normalized] ?? [$component];
}

$message = '';
$error = '';

$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(422);
    echo 'Missing request_id.';
    exit;
}

try {
    $requestPk = request_pk_column($pdo);
    $unitsColumn = request_units_column($pdo);
    $patientColumn = request_patient_column($pdo);
    $unitIdColumn = unit_identifier_column($pdo);

    $requestSql = 'SELECT ' . $requestPk . ' AS request_id,
                          ' . $patientColumn . ' AS patient,
                          blood_type,
                          component,
                          ' . $unitsColumn . ' AS units_needed,
                          status
                   FROM tblblood_requests
                   WHERE ' . $requestPk . ' = :request_id
                   LIMIT 1';
    $requestStmt = $pdo->prepare($requestSql);
    $requestStmt->execute([':request_id' => $requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Request not found.');
    }

    $bloodType = trim((string)($request['blood_type'] ?? ''));
    $component = trim((string)($request['component'] ?? ''));
    $unitsNeeded = max(1, (int)($request['units_needed'] ?? 1));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = trim((string)($_POST['result'] ?? 'Compatible'));
        if (!in_array($result, ['Compatible', 'Incompatible'], true)) {
            $result = 'Compatible';
        }

        $selectedUnitIds = $_POST['unit_ids'] ?? [];
        if (!is_array($selectedUnitIds)) {
            $selectedUnitIds = [];
        }
        $selectedUnitIds = array_values(array_unique(array_filter(array_map('intval', $selectedUnitIds), static fn(int $id): bool => $id > 0)));

        $testParameters = trim((string)($_POST['test_parameters'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $performedBy = trim((string)($_POST['performed_by'] ?? ''));
        if ($performedBy === '') {
            $performedBy = 'Lab Staff';
        }

        if ($result === 'Compatible' && count($selectedUnitIds) !== $unitsNeeded) {
            throw new RuntimeException('Compatible result requires exactly ' . $unitsNeeded . ' selected unit(s).');
        }

        if ($result === 'Incompatible' && count($selectedUnitIds) > $unitsNeeded) {
            throw new RuntimeException('You selected too many units. Maximum allowed is ' . $unitsNeeded . '.');
        }

        $pdo->beginTransaction();

        $lockStmt = $pdo->prepare(
            'SELECT ' . $requestPk . ' AS request_id, status
             FROM tblblood_requests
             WHERE ' . $requestPk . ' = ?
             FOR UPDATE'
        );
        $lockStmt->execute([$requestId]);
        $lockedRequest = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$lockedRequest) {
            throw new RuntimeException('Request not found while locking row.');
        }

        if ($selectedUnitIds) {
            $placeholders = implode(',', array_fill(0, count($selectedUnitIds), '?'));
            $unitCheckSql = 'SELECT id, ' . $unitIdColumn . ' AS unit_ref, blood_type, component, status
                             FROM tblblood_units
                             WHERE id IN (' . $placeholders . ')
                             FOR UPDATE';
            $unitCheckStmt = $pdo->prepare($unitCheckSql);
            $unitCheckStmt->execute($selectedUnitIds);
            $lockedUnits = $unitCheckStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (count($lockedUnits) !== count($selectedUnitIds)) {
                throw new RuntimeException('One or more selected units no longer exist.');
            }

            $allowedComponents = component_aliases($component);
            foreach ($lockedUnits as $unit) {
                if ((string)$unit['status'] !== 'Available') {
                    throw new RuntimeException('Unit ' . $unit['unit_ref'] . ' is no longer available.');
                }
                if (strcasecmp((string)$unit['blood_type'], $bloodType) !== 0) {
                    throw new RuntimeException('Unit ' . $unit['unit_ref'] . ' blood type mismatch.');
                }
                if (!in_array((string)$unit['component'], $allowedComponents, true)) {
                    throw new RuntimeException('Unit ' . $unit['unit_ref'] . ' component mismatch.');
                }
            }
        }

        $crossmatchTableExists = $pdo->query("SHOW TABLES LIKE 'tblcrossmatch'");
        if (!$crossmatchTableExists || $crossmatchTableExists->rowCount() === 0) {
            throw new RuntimeException('tblcrossmatch table does not exist. Run backend/sql/migrate_crossmatch_issue_workflow.sql first.');
        }

        $insertCrossmatchStmt = $pdo->prepare(
            'INSERT INTO tblcrossmatch
             (request_id, unit_id, result, test_parameters, notes, performed_by, performed_at)
             VALUES (:request_id, :unit_id, :result, :test_parameters, :notes, :performed_by, NOW())'
        );

        if ($selectedUnitIds) {
            $fetchUnitsStmt = $pdo->prepare(
                'SELECT id, ' . $unitIdColumn . ' AS unit_ref FROM tblblood_units WHERE id IN (' . implode(',', array_fill(0, count($selectedUnitIds), '?')) . ')'
            );
            $fetchUnitsStmt->execute($selectedUnitIds);
            $unitsById = [];
            foreach ($fetchUnitsStmt->fetchAll(PDO::FETCH_ASSOC) as $unitRow) {
                $unitsById[(int)$unitRow['id']] = (string)$unitRow['unit_ref'];
            }

            foreach ($selectedUnitIds as $selectedUnitPk) {
                $insertCrossmatchStmt->execute([
                    ':request_id' => $requestId,
                    ':unit_id' => $unitsById[$selectedUnitPk] ?? null,
                    ':result' => $result,
                    ':test_parameters' => $testParameters,
                    ':notes' => $notes,
                    ':performed_by' => $performedBy,
                ]);
            }
        } else {
            $insertCrossmatchStmt->execute([
                ':request_id' => $requestId,
                ':unit_id' => null,
                ':result' => $result,
                ':test_parameters' => $testParameters,
                ':notes' => $notes,
                ':performed_by' => $performedBy,
            ]);
        }

        if ($result === 'Compatible' && $selectedUnitIds) {
            $reserveSql = 'UPDATE tblblood_units
                   SET status = "Reserved", request_id = ?, updated_at = CURRENT_TIMESTAMP
                           WHERE id IN (' . implode(',', array_fill(0, count($selectedUnitIds), '?')) . ')';
            $reserveStmt = $pdo->prepare($reserveSql);
          $reserveParams = array_merge([$requestId], $selectedUnitIds);
            $reserveStmt->execute($reserveParams);
        }

        $newStatus = $result === 'Compatible' ? 'Ready to Issue' : 'Rejected';
        $updateSql = 'UPDATE tblblood_requests SET status = :status';
        if (has_column($pdo, 'tblblood_requests', 'updated_at')) {
          $updateSql .= ', updated_at = CURRENT_TIMESTAMP';
        }
        $updateSql .= ' WHERE ' . $requestPk . ' = :request_id';
        $updateRequestStmt = $pdo->prepare($updateSql);
        $updateRequestStmt->execute([
            ':status' => $newStatus,
            ':request_id' => $requestId,
        ]);

        $pdo->commit();
        $message = 'Cross-match saved successfully. Request moved to status: ' . $newStatus . '.';

        $requestStmt->execute([':request_id' => $requestId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC) ?: $request;
    }

    $allowedComponents = component_aliases($component);
    $componentPlaceholders = implode(',', array_fill(0, count($allowedComponents), '?'));
    $availableSql = 'SELECT id, ' . $unitIdColumn . ' AS unit_ref, expiry_date
                     FROM tblblood_units
                     WHERE status = "Available"
                       AND blood_type = ?
                       AND component IN (' . $componentPlaceholders . ')
                     ORDER BY expiry_date ASC, id ASC';
    $availableStmt = $pdo->prepare($availableSql);
    $availableStmt->execute(array_merge([$bloodType], $allowedComponents));
    $availableUnits = $availableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = $e->getMessage();
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $availableUnits = [];
    $request = null;
    $unitsNeeded = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cross-match</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1d2733; }
    .wrap { max-width: 960px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,0.07); }
    h1 { margin-top: 0; }
    .muted { color: #64748b; }
    .error { background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
    .ok { background: #dcfce7; color: #166534; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px; }
    .field { display: flex; flex-direction: column; gap: 6px; }
    input, select, textarea { padding: 10px; border: 1px solid #d6deea; border-radius: 8px; font-size: 14px; }
    .units-box { border: 1px solid #d6deea; border-radius: 8px; padding: 12px; max-height: 300px; overflow: auto; background: #f8fafc; }
    .unit-row { display: flex; gap: 10px; margin-bottom: 8px; align-items: center; }
    .actions { margin-top: 16px; }
    button { background: #c8102e; color: #fff; border: none; padding: 11px 16px; border-radius: 8px; cursor: pointer; }
    button:hover { background: #a60d25; }
    @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Cross-match Request</h1>

  <?php if ($error !== ''): ?>
    <div class="error"><?php echo h($error); ?></div>
  <?php endif; ?>

  <?php if ($message !== ''): ?>
    <div class="ok"><?php echo h($message); ?></div>
  <?php endif; ?>

  <?php if ($request): ?>
    <p class="muted">
      Request #<?php echo h((string)$request['request_id']); ?> |
      Patient: <?php echo h((string)$request['patient']); ?> |
      Blood: <?php echo h((string)$request['blood_type']); ?> |
      Component: <?php echo h((string)$request['component']); ?> |
      Units Needed: <?php echo h((string)$request['units_needed']); ?>
    </p>

    <form method="post">
      <input type="hidden" name="request_id" value="<?php echo h((string)$request['request_id']); ?>">

      <div class="grid">
        <div class="field">
          <label>Result</label>
          <select name="result" id="result">
            <option value="Compatible">Compatible</option>
            <option value="Incompatible">Incompatible</option>
          </select>
        </div>
        <div class="field">
          <label>Performed By</label>
          <input name="performed_by" placeholder="Technician name" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label>Select Units (choose exactly <?php echo h((string)$unitsNeeded); ?> if Compatible)</label>
        <div class="units-box" id="units-box" data-max="<?php echo h((string)$unitsNeeded); ?>">
          <?php if (!$availableUnits): ?>
            <div class="muted">No matching available units found.</div>
          <?php else: ?>
            <?php foreach ($availableUnits as $unit): ?>
              <label class="unit-row">
                <input type="checkbox" name="unit_ids[]" value="<?php echo h((string)$unit['id']); ?>" class="unit-checkbox">
                <span><?php echo h((string)$unit['unit_ref']); ?></span>
                <span class="muted">(Expiry: <?php echo h((string)$unit['expiry_date']); ?>)</span>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label>Test Parameters</label>
        <textarea name="test_parameters" rows="3" placeholder="AHG phase, saline phase, DAT, antibody screen, etc."></textarea>
      </div>

      <div class="field">
        <label>Notes</label>
        <textarea name="notes" rows="3"></textarea>
      </div>

      <div class="actions">
        <button type="submit">Save Cross-match</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
(function () {
  var max = Number(document.getElementById('units-box')?.dataset.max || 0);
  var resultSelect = document.getElementById('result');
  var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.unit-checkbox'));

  function enforceMax(changedBox) {
    var checked = checkboxes.filter(function (box) { return box.checked; });
    if (checked.length > max && changedBox) {
      changedBox.checked = false;
      alert('You can select at most ' + max + ' unit(s).');
    }
  }

  checkboxes.forEach(function (box) {
    box.addEventListener('change', function () {
      enforceMax(box);
    });
  });

  resultSelect?.addEventListener('change', function () {
    var incompatible = resultSelect.value === 'Incompatible';
    checkboxes.forEach(function (box) {
      box.disabled = incompatible;
      if (incompatible) {
        box.checked = false;
      }
    });
  });
})();
</script>
</body>
</html>
