<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['donor_id'])) {
    header('Location: /blood_donation/login.php');
    exit;
}

require_once __DIR__ . '/backend/config/db.php';

$donorId = (int)$_SESSION['donor_id'];

if ($donorId <= 0) {
    http_response_code(401);
    echo 'Invalid donor session.';
    exit;
}

/**
 * Escape output for safe HTML rendering.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Check table existence.
 */
function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $t) {
        return false;
    }
}

/**
 * Check column existence in a table.
 */
function columnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        return false;
    }
}

function appointmentStatusLabel(?string $status): string
{
  $normalized = strtolower(trim((string)$status));

  return match ($normalized) {
    'completed' => 'Completed',
    'cancelled', 'canceled', 'rejected' => 'Cancelled',
    'deferred', 'temporarily_deferred', 'permanently_deferred' => 'Deferred',
    'confirmed' => 'Confirmed',
    'pending' => 'Pending',
    default => $normalized !== '' ? ucwords(str_replace(['_', '-'], ' ', $normalized)) : 'Pending',
  };
}

function appointmentStatusClass(?string $status): string
{
  $normalized = strtolower(trim((string)$status));

  return match ($normalized) {
    'completed' => 'status-completed',
    'cancelled', 'canceled', 'rejected' => 'status-cancelled',
    'deferred', 'temporarily_deferred', 'permanently_deferred' => 'status-deferred',
    'confirmed' => 'status-confirmed',
    default => 'status-pending',
  };
}

function pickAppointmentTable(PDO $pdo): ?string
{
  foreach (['tblappointments', 'appointments'] as $table) {
    if (tableExists($pdo, $table)) {
      return $table;
    }
  }

  return null;
}

function loadCompletedDonationHistory(PDO $pdo, string $donorName, int $donorId): array
{
  $donationRows = [];

  if (tableExists($pdo, 'donation_history')) {
    $dateColumn = columnExists($pdo, 'donation_history', 'donation_date') ? 'donation_date' : (columnExists($pdo, 'donation_history', 'created_at') ? 'created_at' : 'id');
    $hasDonorId = columnExists($pdo, 'donation_history', 'donor_id');
    $hasDonorName = columnExists($pdo, 'donation_history', 'donor_name');

    if ($hasDonorId) {
      $query = 'SELECT id, ' . $dateColumn . ' AS donation_date, blood_type, component, units_collected, status FROM donation_history WHERE donor_id = :donor_id';
      if (columnExists($pdo, 'donation_history', 'status')) {
        $query .= " AND LOWER(COALESCE(status, '')) = 'completed'";
      }
      $query .= ' ORDER BY ' . $dateColumn . ' DESC, id DESC';
      $stmt = $pdo->prepare($query);
      $stmt->execute([':donor_id' => $donorId]);
      $donationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($hasDonorName) {
      $query = 'SELECT id, ' . $dateColumn . ' AS donation_date, blood_type, component, units_collected, status FROM donation_history WHERE donor_name = :donor_name';
      if (columnExists($pdo, 'donation_history', 'status')) {
        $query .= " AND LOWER(COALESCE(status, '')) = 'completed'";
      }
      $query .= ' ORDER BY ' . $dateColumn . ' DESC, id DESC';
      $stmt = $pdo->prepare($query);
      $stmt->execute([':donor_name' => $donorName]);
      $donationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }

  if (empty($donationRows) && tableExists($pdo, 'tbldonations')) {
    $dateColumn = columnExists($pdo, 'tbldonations', 'donation_date') ? 'donation_date' : (columnExists($pdo, 'tbldonations', 'created_at') ? 'created_at' : 'id');
    $statusFilter = columnExists($pdo, 'tbldonations', 'status') ? " AND LOWER(COALESCE(status, '')) IN ('safe', 'stocked')" : '';

    if (columnExists($pdo, 'tbldonations', 'donor_id')) {
      $stmt = $pdo->prepare('SELECT id, ' . $dateColumn . ' AS donation_date, blood_type, component, units_collected AS units, status FROM tbldonations WHERE donor_id = :donor_id' . $statusFilter . ' ORDER BY ' . $dateColumn . ' DESC, id DESC');
      $stmt->execute([':donor_id' => $donorId]);
      $donationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif (columnExists($pdo, 'tbldonations', 'donor_name')) {
      $stmt = $pdo->prepare('SELECT id, ' . $dateColumn . ' AS donation_date, blood_type, component, units_collected AS units, status FROM tbldonations WHERE donor_name = :donor_name' . $statusFilter . ' ORDER BY ' . $dateColumn . ' DESC, id DESC');
      $stmt->execute([':donor_name' => $donorName]);
      $donationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }

  $normalized = [];
  foreach ($donationRows as $row) {
    $normalized[] = [
      'id' => $row['id'] ?? null,
      'donation_date' => $row['donation_date'] ?? null,
      'blood_type' => $row['blood_type'] ?? '',
      'component' => $row['component'] ?? 'Whole Blood',
      'units' => isset($row['units_collected']) ? (int)$row['units_collected'] : (int)($row['units'] ?? 1),
      'status' => 'Completed',
    ];
  }

  return $normalized;
}

$donor = null;
$linkedUserId = null;
$appointmentHistory = [];
$upcomingAppointments = [];
$pastDonations = [];
$bloodRequests = [];
$notifications = [];
$errors = [];

try {
    $donorStmt = $pdo->prepare(
        'SELECT id, full_name, email, phone, blood_type, city, dzongkhag, status, deferred, deferral_reason, created_at
         FROM tbldonors
         WHERE id = ?
         LIMIT 1'
    );
    $donorStmt->execute([$donorId]);
    $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        throw new RuntimeException('Donor profile not found.');
    }

    $userStmt = $pdo->prepare('SELECT id FROM tblusers WHERE email = ? LIMIT 1');
    $userStmt->execute([(string)$donor['email']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $linkedUserId = $user ? (int)$user['id'] : null;

    // 1) Appointment history (own records)
    $appointmentTable = pickAppointmentTable($pdo);
    if ($appointmentTable !== null) {
      $hasUserIdColumn = columnExists($pdo, $appointmentTable, 'user_id');
      $hasFullNameColumn = columnExists($pdo, $appointmentTable, 'full_name');

      if ($linkedUserId !== null && $hasUserIdColumn) {
        $apptStmt = $pdo->prepare(
          'SELECT id, preferred_date, preferred_time, blood_bank, blood_group, status
           FROM `' . str_replace('`', '``', $appointmentTable) . '` 
           WHERE user_id = :user_id
         ORDER BY preferred_date DESC, preferred_time DESC'
        );
        $apptStmt->execute([':user_id' => $linkedUserId]);
      } elseif ($hasFullNameColumn) {
        // Fallback for older data where user_id may not be linked yet.
        $apptStmt = $pdo->prepare(
          'SELECT id, preferred_date, preferred_time, blood_bank, blood_group, status
           FROM `' . str_replace('`', '``', $appointmentTable) . '` 
           WHERE full_name = :full_name
         ORDER BY preferred_date DESC, preferred_time DESC'
        );
        $apptStmt->execute([':full_name' => (string)$donor['full_name']]);
      } else {
        $appointmentHistory = [];
      }
    }

    $appointmentHistory = isset($apptStmt) ? ($apptStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $today = new DateTimeImmutable('today');

    foreach ($appointmentHistory as $row) {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string)$row['preferred_date']);
      $statusValue = strtolower((string)($row['status'] ?? ''));
      if ($date && $date >= $today && !in_array($statusValue, ['rejected', 'cancelled', 'completed', 'deferred'], true)) {
            $upcomingAppointments[] = $row;
        }
    }

    // 2) Completed donations only
    $pastDonations = loadCompletedDonationHistory($pdo, (string)$donor['full_name'], $donorId);
    $latestDonationRow = $pastDonations[0] ?? null;
    $lastDonationDisplay = 'Never';
    $nextEligibleDisplay = 'N/A';

    if ($latestDonationRow && !empty($latestDonationRow['donation_date'])) {
      $latestDonationDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$latestDonationRow['donation_date'])
        ?: DateTimeImmutable::createFromFormat('Y-m-d', substr((string)$latestDonationRow['donation_date'], 0, 10));
      if ($latestDonationDate) {
        $lastDonationDisplay = $latestDonationDate->format('d M Y');
        $nextEligibleDisplay = $latestDonationDate->modify('+90 days')->format('d M Y');
      }
    }

    $totalAppointments = count($appointmentHistory);
    $totalDonations = count($pastDonations);

    // 3) Blood requests by donor_id if present, otherwise by patient_name
    if (tableExists($pdo, 'tblblood_requests')) {
        if (columnExists($pdo, 'tblblood_requests', 'donor_id')) {
            $requestStmt = $pdo->prepare(
                'SELECT id, request_code, patient_name, blood_type, component, units_requested, urgency, status, created_at
                 FROM tblblood_requests
                 WHERE donor_id = :donor_id
                 ORDER BY created_at DESC'
            );
            $requestStmt->execute([':donor_id' => $donorId]);
        } else {
            $requestStmt = $pdo->prepare(
                'SELECT id, request_code, patient_name, blood_type, component, units_requested, urgency, status, created_at
                 FROM tblblood_requests
                 WHERE patient_name = :patient_name
                 ORDER BY created_at DESC'
            );
            $requestStmt->execute([':patient_name' => (string)$donor['full_name']]);
        }
        $bloodRequests = $requestStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // 4) Notifications targeted to this donor
    if (tableExists($pdo, 'tblnotifications')) {
        if ($linkedUserId !== null) {
            $notifStmt = $pdo->prepare(
                'SELECT title, message, severity, is_read, created_at
                 FROM tblnotifications
                 WHERE user_id = :user_id OR role_target = "donor"
                 ORDER BY created_at DESC
                 LIMIT 20'
            );
            $notifStmt->execute([':user_id' => $linkedUserId]);
        } else {
            $notifStmt = $pdo->query(
                'SELECT title, message, severity, is_read, created_at
                 FROM tblnotifications
                 WHERE role_target = "donor"
                 ORDER BY created_at DESC
                 LIMIT 20'
            );
        }
        $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $t) {
    $errors[] = $t->getMessage();
}

if (!$donor) {
    http_response_code(404);
    echo '<h2>Donor not found</h2>';
    exit;
}

$status = strtolower((string)($donor['status'] ?? 'pending'));
$isPending = $status === 'pending';
$isDeferred = ((int)($donor['deferred'] ?? 0) === 1);
$totalAppointments = $totalAppointments ?? 0;
$totalDonations = $totalDonations ?? 0;
$lastDonationDisplay = $lastDonationDisplay ?? 'Never';
$nextEligibleDisplay = $nextEligibleDisplay ?? 'N/A';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donor Dashboard</title>
  <style>
    :root {
      --bg: #f6f2eb;
      --card: #ffffff;
      --ink: #1e2430;
      --muted: #64748b;
      --accent: #b91c1c;
      --accent-soft: #fee2e2;
      --ok: #166534;
      --ok-soft: #dcfce7;
      --warn: #9a3412;
      --warn-soft: #ffedd5;
      --line: #e5e7eb;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      color: var(--ink);
      background: radial-gradient(circle at top right, #fdf8f4 0%, var(--bg) 55%);
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px;
    }

    .top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 20px;
    }

    .title {
      margin: 0;
      font-size: 1.5rem;
      color: var(--accent);
    }

    .subtitle {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 0.95rem;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 16px;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 2px 10px rgba(2, 6, 23, 0.04);
    }

    .span-4 { grid-column: span 4; }
    .span-8 { grid-column: span 8; }
    .span-12 { grid-column: span 12; }

    .badge {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      margin-right: 8px;
    }

    .b-pending { background: var(--warn-soft); color: var(--warn); }
    .b-active { background: var(--ok-soft); color: var(--ok); }
    .b-deferred { background: var(--accent-soft); color: var(--accent); }

    .alert {
      border-left: 5px solid var(--accent);
      background: #fff;
      padding: 14px;
      border-radius: 10px;
      margin-bottom: 16px;
    }

    .alert p { margin: 6px 0 0; color: var(--muted); }

    h2 {
      margin: 0 0 12px;
      font-size: 1.05rem;
    }

    .meta {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin-top: 8px;
    }

    .meta div {
      border: 1px dashed var(--line);
      border-radius: 10px;
      padding: 10px;
      background: #fafafa;
    }

    .meta small { color: var(--muted); display: block; margin-bottom: 3px; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.93rem;
    }

    th, td {
      text-align: left;
      border-bottom: 1px solid var(--line);
      padding: 10px 8px;
      vertical-align: top;
    }

    th { color: #334155; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; }
    .empty { color: var(--muted); font-style: italic; }

    .notif {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 8px;
      background: #fff;
    }

    .notif h4 {
      margin: 0 0 4px;
      font-size: 0.95rem;
    }

    .notif p { margin: 0; color: #374151; }
    .muted { color: var(--muted); font-size: 0.82rem; margin-top: 6px; display: inline-block; }
    .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .summary-card { background: linear-gradient(180deg, #fff, #fff8f1); border: 1px solid var(--line); border-radius: 14px; padding: 16px; box-shadow: 0 10px 28px rgba(31, 41, 55, 0.05); }
    .summary-card .label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
    .summary-card .value { font-size: 28px; font-weight: 800; color: var(--accent); margin-top: 8px; }
    .summary-card .value.small { font-size: 20px; }
    .status-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; }
    .status-completed { background: #dcfce7; color: #166534; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-deferred { background: #fef3c7; color: #92400e; }
    .status-confirmed { background: #dbeafe; color: #1d4ed8; }
    .status-pending { background: #e5e7eb; color: #374151; }
    .table-wrap { overflow: auto; }

    @media (max-width: 900px) {
      .span-4, .span-8, .span-12 { grid-column: span 12; }
      .meta { grid-template-columns: 1fr; }
      .top { flex-direction: column; align-items: flex-start; }
      .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 720px) {
      .summary-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="top">
      <div>
        <h1 class="title">Donor Dashboard</h1>
        <p class="subtitle">Welcome, <?php echo e((string)$donor['full_name']); ?>. Here is your personal donation information.</p>
      </div>
      <div>
        <span class="badge <?php echo $status === 'active' ? 'b-active' : 'b-pending'; ?>"><?php echo e(ucfirst($status)); ?></span>
        <?php if ($isDeferred): ?>
          <span class="badge b-deferred">Deferred</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isPending): ?>
      <div class="alert">
        <strong>Donation Status: Pending Admin Approval</strong>
        <p>Your registration is under review. You can still view your profile and previous records below.</p>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert">
        <strong>Some data could not be loaded.</strong>
        <p><?php echo e(implode(' | ', $errors)); ?></p>
      </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 16px;">
      <h2>Donation Activity</h2>
      <div class="summary-grid">
        <div class="summary-card">
          <div class="label">Total Donations</div>
          <div class="value"><?php echo e((string)$totalDonations); ?></div>
        </div>
        <div class="summary-card">
          <div class="label">Total Appointments</div>
          <div class="value"><?php echo e((string)$totalAppointments); ?></div>
        </div>
        <div class="summary-card">
          <div class="label">Last Donation</div>
          <div class="value small"><?php echo e((string)$lastDonationDisplay); ?></div>
        </div>
        <div class="summary-card">
          <div class="label">Next Eligible</div>
          <div class="value small"><?php echo e((string)$nextEligibleDisplay); ?></div>
        </div>
      </div>
    </div>

    <div class="grid">
      <section class="card span-4">
        <h2>Basic Donor Info</h2>
        <div class="meta">
          <div><small>Blood Type</small><?php echo e((string)($donor['blood_type'] ?? '—')); ?></div>
          <div><small>Phone</small><?php echo e((string)($donor['phone'] ?? '—')); ?></div>
          <div><small>Email</small><?php echo e((string)($donor['email'] ?? '—')); ?></div>
          <div><small>Location</small><?php echo e(trim(((string)($donor['city'] ?? '')) . ', ' . ((string)($donor['dzongkhag'] ?? '')), ' ,')); ?></div>
        </div>
      </section>

      <section class="card span-8">
        <h2>Appointment History</h2>
        <?php if (empty($appointmentHistory)): ?>
          <p class="empty">No appointment history found.</p>
        <?php else: ?>
          <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Time</th>
                <th>Blood Bank</th>
                <th>Blood Group</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($appointmentHistory as $row): ?>
                <tr>
                  <td><?php echo e((string)$row['id']); ?></td>
                  <td><?php echo e((string)$row['preferred_date']); ?></td>
                  <td><?php echo e((string)($row['preferred_time'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['blood_bank'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['blood_group'] ?? '—')); ?></td>
                  <td><span class="status-badge <?php echo e(appointmentStatusClass((string)($row['status'] ?? 'pending'))); ?>"><?php echo e(appointmentStatusLabel((string)($row['status'] ?? 'pending'))); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="card span-12">
        <h2>Completed Donations</h2>
        <?php if (empty($pastDonations)): ?>
          <p class="empty">No completed donations found.</p>
        <?php else: ?>
          <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Donation Date</th>
                <th>Blood Type</th>
                <th>Units</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pastDonations as $row): ?>
                <tr>
                  <td><?php echo e((string)($row['id'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['donation_date'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['blood_type'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['units'] ?? '—')); ?></td>
                  <td><span class="status-badge status-completed">Completed</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="card span-12">
        <h2>My Blood Requests</h2>
        <?php if (empty($bloodRequests)): ?>
          <p class="empty">No blood requests found for your profile.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Request Code</th>
                <th>Patient</th>
                <th>Blood Type</th>
                <th>Component</th>
                <th>Units</th>
                <th>Urgency</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bloodRequests as $row): ?>
                <tr>
                  <td><?php echo e((string)($row['request_code'] ?? $row['id'])); ?></td>
                  <td><?php echo e((string)($row['patient_name'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['blood_type'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['component'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['units_requested'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['urgency'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['status'] ?? '—')); ?></td>
                  <td><?php echo e((string)($row['created_at'] ?? '—')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <section class="card span-12">
        <h2>Notifications</h2>
        <?php if (empty($notifications)): ?>
          <p class="empty">No notifications available.</p>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <article class="notif">
              <h4><?php echo e((string)($n['title'] ?? 'Notification')); ?><?php echo ((int)($n['is_read'] ?? 0) === 0) ? ' (New)' : ''; ?></h4>
              <p><?php echo e((string)($n['message'] ?? '')); ?></p>
              <span class="muted"><?php echo e((string)($n['severity'] ?? 'info')); ?> • <?php echo e((string)($n['created_at'] ?? '')); ?></span>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
  </div>
</body>
</html>
