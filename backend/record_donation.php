<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

session_start();
if (!isset($_SESSION['role']) || !in_array((string)$_SESSION['role'], ['staff', 'admin'], true)) {
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

function donor_name_column(PDO $pdo): string
{
    return has_column($pdo, 'tbldonors', 'name') ? 'name' : 'full_name';
}

function inventory_ffp_column(PDO $pdo): string
{
    return has_column($pdo, 'tblinventory', 'fpp_units') ? 'fpp_units' : 'ffp_units';
}

function component_inventory_column(PDO $pdo, string $component): string
{
    $normalized = strtolower(trim($component));
    if ($normalized === 'prbc') {
        return 'prbc_units';
    }
    if ($normalized === 'platelets') {
        return 'platelets_units';
    }
    if ($normalized === 'ffp') {
        return inventory_ffp_column($pdo);
    }

    return 'whole_units';
}

function table_exists(PDO $pdo, string $table): bool
{
  try {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetch(PDO::FETCH_NUM);
  } catch (Throwable $exception) {
    return false;
  }
}

function ensure_donation_history_table(PDO $pdo): void
{
  if (table_exists($pdo, 'donation_history')) {
    return;
  }

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS donation_history (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      appointment_id INT UNSIGNED NULL,
      donation_id VARCHAR(40) NULL,
      donor_id INT UNSIGNED NULL,
      donor_name VARCHAR(160) NULL,
      blood_bank_id INT UNSIGNED NOT NULL,
      blood_type VARCHAR(5) NOT NULL,
      component ENUM('Whole Blood','Packed Red Cells','Plasma','Platelets') NOT NULL DEFAULT 'Whole Blood',
      units_collected SMALLINT UNSIGNED NOT NULL DEFAULT 1,
      donation_date DATETIME NOT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'Completed',
      notes VARCHAR(255) NULL,
      completed_by_user_id INT UNSIGNED NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_donation_history_donor (donor_id),
      INDEX idx_donation_history_appointment (appointment_id),
      INDEX idx_donation_history_status (status),
      INDEX idx_donation_history_date (donation_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function insert_donation_history(PDO $pdo, array $appointment, string $donationId, int $bloodBankId, string $bloodType, string $component, int $unitsCollected, int $actorUserId): void
{
  try {
    ensure_donation_history_table($pdo);
  } catch (Throwable $exception) {
    return;
  }

  if (!table_exists($pdo, 'donation_history')) {
    return;
  }

  $createdAt = date('Y-m-d H:i:s');
  $donationDate = date('Y-m-d H:i:s');
  $donorName = trim((string)($appointment['donor_name'] ?? $appointment['full_name'] ?? ''));

  $payload = [
    'appointment_id' => (int)($appointment['id'] ?? 0),
    'donation_id' => $donationId,
    'donor_id' => (int)($appointment['donor_id'] ?? 0),
    'donor_name' => $donorName !== '' ? $donorName : null,
    'blood_bank_id' => $bloodBankId,
    'blood_type' => $bloodType,
    'component' => $component,
    'units_collected' => $unitsCollected,
    'donation_date' => $donationDate,
    'status' => 'Completed',
    'notes' => 'Completed appointment donation recorded from staff workflow.',
    'completed_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
    'created_at' => $createdAt,
    'updated_at' => $createdAt,
  ];

  $columns = [];
  $values = [];

  foreach ($payload as $column => $value) {
    if (!has_column($pdo, 'donation_history', $column)) {
      continue;
    }

    $columns[] = $column;
    $values[] = $value;
  }

  if (empty($columns)) {
    return;
  }

  $placeholders = implode(', ', array_fill(0, count($columns), '?'));
  $stmt = $pdo->prepare('INSERT INTO donation_history (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
  $stmt->execute($values);
}

function mark_appointment_completed(PDO $pdo, string $tableName, int $appointmentId): void
{
  if (!table_exists($pdo, $tableName)) {
    return;
  }

  $hasUpdatedAt = has_column($pdo, $tableName, 'updated_at');
  $sql = 'UPDATE `' . str_replace('`', '``', $tableName) . '` SET status = "completed"';
  if ($hasUpdatedAt) {
    $sql .= ', updated_at = NOW()';
  }
  $sql .= ' WHERE id = ?';

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$appointmentId]);
}

function next_donation_id(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'U-' . $year . '-';

    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(donation_id, "-", -1) AS UNSIGNED)), 0)
         FROM tblblood_units
         WHERE donation_id LIKE ?
         FOR UPDATE'
    );
    $stmt->execute([$prefix . '%']);
    $last = (int)$stmt->fetchColumn();
    $next = $last + 1;

    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

$message = '';
$error = '';
$selectedDate = trim((string)($_GET['date'] ?? date('Y-m-d')));
$appointmentId = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
$mode = trim((string)($_GET['mode'] ?? 'list'));

try {
    $donorNameColumn = donor_name_column($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $component = trim((string)($_POST['component'] ?? ''));
        $allowedComponents = ['Whole Blood', 'PRBC', 'Platelets'];
        if (!in_array($component, $allowedComponents, true)) {
            throw new RuntimeException('Invalid component selected.');
        }

        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment id.');
        }

        $pdo->beginTransaction();

         $appointmentStmt = $pdo->prepare(
             'SELECT a.id, a.donor_id, a.blood_bank_id, a.appointment_date, a.status,
                d.' . $donorNameColumn . ' AS donor_name,
                d.blood_type
              FROM tblappointments a
              INNER JOIN tbldonors d ON d.id = a.donor_id
              WHERE a.id = ?
              FOR UPDATE'
         );
        $appointmentStmt->execute([$appointmentId]);
        $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        if (strtolower((string)$appointment['status']) !== 'confirmed') {
            throw new RuntimeException('Only confirmed appointments can be recorded.');
        }

        $donationId = next_donation_id($pdo);
        $bloodBankId = (int)$appointment['blood_bank_id'];
        $bloodType = (string)$appointment['blood_type'];

        $insertUnit = $pdo->prepare(
            'INSERT INTO tblblood_units
            (donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id, created_at)
            VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), "Available", NULL, NOW())'
        );
        $insertUnit->execute([$donationId, $bloodBankId, $bloodType, $component]);

        $inventoryColumn = component_inventory_column($pdo, $component);

        $inventoryLock = $pdo->prepare('SELECT id FROM tblinventory WHERE blood_bank_id = ? AND blood_type = ? FOR UPDATE');
        $inventoryLock->execute([$bloodBankId, $bloodType]);
        $inventoryRow = $inventoryLock->fetch(PDO::FETCH_ASSOC);

        if (!$inventoryRow) {
            $ffpColumn = inventory_ffp_column($pdo);
            $insertInventory = $pdo->prepare(
                'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ' . $ffpColumn . ')
                 VALUES (?, ?, 0, 0, 0, 0)'
            );
            $insertInventory->execute([$bloodBankId, $bloodType]);
        }

        $updateInventory = $pdo->prepare(
            'UPDATE tblinventory
             SET ' . $inventoryColumn . ' = ' . $inventoryColumn . ' + 1
             WHERE blood_bank_id = ? AND blood_type = ?'
        );
        $updateInventory->execute([$bloodBankId, $bloodType]);

        mark_appointment_completed($pdo, 'tblappointments', $appointmentId);
        mark_appointment_completed($pdo, 'appointments', $appointmentId);

        insert_donation_history(
          $pdo,
          $appointment,
          $donationId,
          $bloodBankId,
          $bloodType,
          $component,
          1,
          (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0)
        );

        if (has_column($pdo, 'tbldonors', 'last_donation_date')) {
            $donorUpdate = $pdo->prepare('UPDATE tbldonors SET last_donation_date = CURDATE() WHERE id = ?');
            $donorUpdate->execute([(int)$appointment['donor_id']]);
        }

        $pdo->commit();
        $message = 'Donation recorded successfully. Donation ID: ' . $donationId;
        $mode = 'list';
    }

    $listStmt = $pdo->prepare(
        'SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.blood_bank_id,
                d.id AS donor_id,
                d.' . $donorNameColumn . ' AS donor_name,
                d.blood_type,
                bb.name AS blood_bank_name
         FROM tblappointments a
         INNER JOIN tbldonors d ON d.id = a.donor_id
         LEFT JOIN tblblood_banks bb ON bb.id = a.blood_bank_id
         WHERE LOWER(a.status) = "confirmed"
           AND a.appointment_date = ?
         ORDER BY a.appointment_time ASC, a.id ASC'
    );
    $listStmt->execute([$selectedDate]);
    $appointments = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $selectedAppointment = null;
    if ($mode === 'form' && $appointmentId > 0) {
        $detailStmt = $pdo->prepare(
            'SELECT a.id, a.appointment_date, a.appointment_time, a.blood_bank_id,
              d.id AS donor_id,
              d.' . $donorNameColumn . ' AS donor_name,
              d.blood_type,
              bb.name AS blood_bank_name
             FROM tblappointments a
             INNER JOIN tbldonors d ON d.id = a.donor_id
             LEFT JOIN tblblood_banks bb ON bb.id = a.blood_bank_id
             WHERE a.id = ?
             LIMIT 1'
        );
        $detailStmt->execute([$appointmentId]);
        $selectedAppointment = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $exception->getMessage();
    $appointments = [];
    $selectedAppointment = null;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Record Donation</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f6f8fb; color: #0f172a; }
    .wrap { max-width: 980px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,0.07); }
    .ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; }
    .btn { border: 0; border-radius: 7px; padding: 8px 12px; cursor: pointer; text-decoration: none; }
    .primary { background: #1d4ed8; color: #fff; }
    .success { background: #15803d; color: #fff; }
    .field { margin-bottom: 10px; display: flex; flex-direction: column; gap: 6px; }
    input, select { border: 1px solid #cbd5e1; border-radius: 7px; padding: 9px; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Staff Donation Recording</h1>

  <?php if ($message !== ''): ?>
    <div class="ok"><?php echo h($message); ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="error"><?php echo h($error); ?></div>
  <?php endif; ?>

  <form method="get">
    <div class="field">
      <label>Appointment Date</label>
      <input type="date" name="date" value="<?php echo h($selectedDate); ?>">
    </div>
    <button class="btn primary" type="submit">Load Confirmed Appointments</button>
  </form>

  <?php if ($mode === 'form' && $selectedAppointment): ?>
    <hr>
    <h2>Record Donation for Appointment #<?php echo h((string)$selectedAppointment['id']); ?></h2>
    <form method="post">
      <input type="hidden" name="appointment_id" value="<?php echo h((string)$selectedAppointment['id']); ?>">
      <div class="field">
        <label>Donor Name</label>
        <input type="text" value="<?php echo h((string)$selectedAppointment['donor_name']); ?>" readonly>
      </div>
      <div class="field">
        <label>Blood Type</label>
        <input type="text" value="<?php echo h((string)$selectedAppointment['blood_type']); ?>" readonly>
      </div>
      <div class="field">
        <label>Blood Bank</label>
        <input type="text" value="<?php echo h((string)($selectedAppointment['blood_bank_name'] ?? 'N/A')); ?>" readonly>
      </div>
      <div class="field">
        <label>Component Collected</label>
        <select name="component" required>
          <option value="Whole Blood">Whole Blood</option>
          <option value="PRBC">PRBC</option>
          <option value="Platelets">Platelets</option>
        </select>
      </div>
      <button class="btn success" type="submit">Save Donation</button>
      <a class="btn" href="?date=<?php echo h($selectedDate); ?>">Cancel</a>
    </form>
  <?php endif; ?>

  <hr>
  <h2>Confirmed Appointments</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Donor</th>
        <th>Blood Type</th>
        <th>Date</th>
        <th>Time</th>
        <th>Blood Bank</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$appointments): ?>
        <tr><td colspan="7">No confirmed appointments for selected date.</td></tr>
      <?php else: ?>
        <?php foreach ($appointments as $row): ?>
          <tr>
            <td><?php echo h((string)$row['id']); ?></td>
            <td><?php echo h((string)$row['donor_name']); ?></td>
            <td><?php echo h((string)$row['blood_type']); ?></td>
            <td><?php echo h((string)$row['appointment_date']); ?></td>
            <td><?php echo h((string)$row['appointment_time']); ?></td>
            <td><?php echo h((string)($row['blood_bank_name'] ?? 'N/A')); ?></td>
            <td>
              <a class="btn primary" href="?mode=form&appointment_id=<?php echo h((string)$row['id']); ?>&date=<?php echo h($selectedDate); ?>">Record Donation</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
