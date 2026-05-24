<?php
require_once __DIR__ . '/backend/config/db.php';

function esc(?string $value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $pdo, string $table): bool
{
  try {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $exception) {
    return false;
  }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
  try {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $exception) {
    return false;
  }
}

function loadDonationSummary(PDO $pdo, int $donorId, string $donorName, ?int $userId): array
{
  $donations = [];

  if (tableExists($pdo, 'donation_history')) {
    $dateColumn = columnExists($pdo, 'donation_history', 'donation_date') ? 'donation_date' : (columnExists($pdo, 'donation_history', 'created_at') ? 'created_at' : 'id');
    if (columnExists($pdo, 'donation_history', 'donor_id')) {
      $stmt = $pdo->prepare('SELECT id, ' . $dateColumn . ' AS donation_date FROM donation_history WHERE donor_id = ? ORDER BY ' . $dateColumn . ' DESC, id DESC');
      $stmt->execute([$donorId]);
      $donations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif (columnExists($pdo, 'donation_history', 'donor_name')) {
      $stmt = $pdo->prepare('SELECT id, ' . $dateColumn . ' AS donation_date FROM donation_history WHERE donor_name = ? ORDER BY ' . $dateColumn . ' DESC, id DESC');
      $stmt->execute([$donorName]);
      $donations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } elseif (tableExists($pdo, 'tbldonations')) {
    $dateColumn = columnExists($pdo, 'tbldonations', 'donation_date') ? 'donation_date' : (columnExists($pdo, 'tbldonations', 'created_at') ? 'created_at' : 'id');
    $statusFilter = columnExists($pdo, 'tbldonations', 'status') ? " AND LOWER(COALESCE(status, '')) IN ('safe', 'stocked')" : '';
    if (columnExists($pdo, 'tbldonations', 'donor_id')) {
      $stmt = $pdo->prepare('SELECT id, ' . $dateColumn . ' AS donation_date FROM tbldonations WHERE donor_id = ?' . $statusFilter . ' ORDER BY ' . $dateColumn . ' DESC, id DESC');
      $stmt->execute([$donorId]);
      $donations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif (columnExists($pdo, 'tbldonations', 'donor_name')) {
      $stmt = $pdo->prepare('SELECT id, ' . $dateColumn . ' AS donation_date FROM tbldonations WHERE donor_name = ?' . $statusFilter . ' ORDER BY ' . $dateColumn . ' DESC, id DESC');
      $stmt->execute([$donorName]);
      $donations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }

  $lastDonation = $donations[0]['donation_date'] ?? null;
  $lastDonationDisplay = 'Never';
  $nextEligibleDisplay = 'N/A';

  if ($lastDonation) {
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$lastDonation)
      ?: DateTimeImmutable::createFromFormat('Y-m-d', substr((string)$lastDonation, 0, 10));
    if ($parsed) {
      $lastDonationDisplay = $parsed->format('d M Y');
      $nextEligibleDisplay = $parsed->modify('+90 days')->format('d M Y');
    }
  }

  $appointmentCount = 0;
  if (tableExists($pdo, 'appointments')) {
    if ($userId !== null && columnExists($pdo, 'appointments', 'user_id')) {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE user_id = ?');
      $stmt->execute([$userId]);
      $appointmentCount = (int)$stmt->fetchColumn();
    } elseif (columnExists($pdo, 'appointments', 'full_name')) {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE full_name = ?');
      $stmt->execute([$donorName]);
      $appointmentCount = (int)$stmt->fetchColumn();
    }
  } elseif (tableExists($pdo, 'tblappointments')) {
    if ($userId !== null && columnExists($pdo, 'tblappointments', 'user_id')) {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM tblappointments WHERE user_id = ?');
      $stmt->execute([$userId]);
      $appointmentCount = (int)$stmt->fetchColumn();
    } elseif (columnExists($pdo, 'tblappointments', 'donor_id')) {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM tblappointments WHERE donor_id = ?');
      $stmt->execute([$donorId]);
      $appointmentCount = (int)$stmt->fetchColumn();
    } elseif (columnExists($pdo, 'tblappointments', 'full_name')) {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM tblappointments WHERE full_name = ?');
      $stmt->execute([$donorName]);
      $appointmentCount = (int)$stmt->fetchColumn();
    }
  }

  return [
    'total_donations' => count($donations),
    'total_appointments' => $appointmentCount,
    'last_donation' => $lastDonationDisplay,
    'next_eligible' => $nextEligibleDisplay,
  ];
}

$email = isset($_GET['donor']) ? trim((string)$_GET['donor']) : 'yoyo@example.com';

$stmt = $pdo->prepare('SELECT * FROM tbldonors WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$donor = $stmt->fetch();

if (!$donor) {
    $donor = $pdo->query('SELECT * FROM tbldonors ORDER BY id ASC LIMIT 1')->fetch();
}

if (!$donor) {
    echo 'No donor records found. Import database.sql first.';
    exit;
}

$linkedUserId = null;
$userStmt = $pdo->prepare('SELECT id FROM tblusers WHERE email = ? LIMIT 1');
$userStmt->execute([(string)$donor['email']]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
if ($userRow) {
  $linkedUserId = (int)$userRow['id'];
}

$donationSummary = loadDonationSummary($pdo, (int)$donor['id'], (string)$donor['full_name'], $linkedUserId);

$notificationStmt = $pdo->prepare('SELECT n.id, n.decision, n.message, n.created_at, d.full_name AS donor_name FROM tblnotifications n INNER JOIN tbldonors d ON d.id = n.donor_id WHERE n.donor_id = ? AND n.is_read = 0 ORDER BY n.created_at DESC');
$notificationStmt->execute([(int)$donor['id']]);
$newNotifications = $notificationStmt->fetchAll();

if (!empty($newNotifications)) {
    $ids = array_map(static fn ($row) => (int)$row['id'], $newNotifications);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $mark = $pdo->prepare('UPDATE tblnotifications SET is_read = 1 WHERE id IN (' . $placeholders . ')');
    $mark->execute($ids);
}

$latestNotification = $newNotifications[0] ?? null;

$status = strtolower((string)$donor['workflow_status']);
$statusLabel = $donor['workflow_status'];
$statusMessage = '';
$primaryButtonLabel = '';
$showContact = false;

if ($status === 'approved' || $status === 'approved_to_donate' || $status === 'approved_donor') {
    $statusMessage = 'You are eligible to donate.';
    $primaryButtonLabel = 'Book Appointment';
} elseif ($status === 'temporarily_deferred') {
    $statusMessage = 'You are temporarily deferred from donation at this time.';
    $primaryButtonLabel = 'Contact Blood Bank';
    $showContact = true;
} elseif ($status === 'permanently_deferred') {
    $statusMessage = 'You are permanently deferred from donation.';
    $primaryButtonLabel = 'Contact Blood Bank';
    $showContact = true;
} else {
    $statusMessage = 'Your status is currently awaiting review.';
    $primaryButtonLabel = 'Contact Blood Bank';
    $showContact = true;
}

function notificationTitle(string $decision): string
{
    return match ($decision) {
    'approve' => 'Approved Donor',
        'temp_defer' => 'Temporarily Deferred',
        'perm_defer' => 'Permanently Deferred',
        default => ucfirst($decision),
    };
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Donor Dashboard</title>
  <style>
    :root { --bg:#f8f6f2; --panel:#fff; --text:#1f2937; --muted:#6b7280; --line:#ddd6cb; --accent:#8b1e3f; --ok:#14532d; --warn:#92400e; --bad:#991b1b; }
    * { box-sizing:border-box; }
    body { margin:0; font-family: Arial, Helvetica, sans-serif; background: radial-gradient(circle at top, #fff6ea 0%, var(--bg) 42%, #efe7dc 100%); color:var(--text); }
    .wrap { max-width: 1024px; margin: 0 auto; padding: 24px; }
    .hero { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; }
    .hero h1 { margin:0; font-size:32px; }
    .hero p { margin:8px 0 0; color:var(--muted); }
    .panel { background:var(--panel); border:1px solid var(--line); border-radius:18px; box-shadow:0 14px 34px rgba(31,41,55,.08); overflow:hidden; margin-bottom:20px; }
    .panel-head { padding:18px 20px; border-bottom:1px solid var(--line); }
    .panel-body { padding:20px; }
    .status { font-size:18px; line-height:1.6; }
    .pill { display:inline-flex; padding:7px 12px; border-radius:999px; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.04em; margin-left:10px; vertical-align:middle; }
    .pill.approved { background:#dcfce7; color:#166534; }
    .pill.temp { background:#fef3c7; color:#92400e; }
    .pill.perm { background:#fee2e2; color:#991b1b; }
    .pill.pending { background:#e5e7eb; color:#374151; }
    .btn { border:0; border-radius:14px; padding:12px 18px; font-weight:700; cursor:pointer; margin-top:14px; }
    .btn-primary { background:#14532d; color:#fff; }
    .btn-contact { background:#8b1e3f; color:#fff; }
    .btn-soft { background:#eef2ff; color:#312e81; }
    .small { color:var(--muted); font-size:13px; }
    .stack { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
    .modal { position:fixed; inset:0; background:rgba(15,23,42,.58); display:none; align-items:center; justify-content:center; padding:16px; z-index:999; }
    .modal-card { width:min(560px, 100%); background:#fff; border-radius:18px; box-shadow:0 24px 60px rgba(0,0,0,.26); overflow:hidden; }
    .modal-head { padding:18px 20px; border-bottom:1px solid #eee7df; }
    .modal-body { padding:20px; }
    .modal-footer { padding:16px 20px 20px; display:flex; justify-content:flex-end; gap:10px; }
    .message-box { background:#fff8f1; border:1px solid #f3d4b5; border-radius:14px; padding:14px 16px; margin-top:12px; }
    .contact-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .badge-line { margin-top:12px; }
    .activity-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-top:14px; }
    .activity-card { border:1px solid var(--line); border-radius:14px; padding:14px; background:linear-gradient(180deg, #fff, #fff8f1); }
    .activity-card .label { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
    .activity-card .value { margin-top:8px; font-size:24px; font-weight:800; color:var(--accent); }
    .activity-card .value.small { font-size:18px; }
    @media (max-width: 720px) { .hero { flex-direction:column; } .contact-grid { grid-template-columns:1fr; } }
    @media (max-width: 900px) { .activity-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { .activity-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hero">
      <div>
        <h1>Donor Dashboard</h1>
        <p>Current status and admin notifications for the selected donor only.</p>
      </div>
      <div class="small">
        Test donor links:
        <a href="donor.php?donor=yoyo@example.com">yoyo</a> | 
        <a href="donor.php?donor=henry@example.com">henry</a> |
        <a href="donor.php?donor=tshering@example.com">tshering</a>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <strong><?= esc($donor['full_name']) ?></strong>
        <div class="badge-line">
          <span class="pill <?= in_array($status, ['approved', 'approved_to_donate', 'approved_donor'], true) ? 'approved' : ($status === 'temporarily_deferred' ? 'temp' : ($status === 'permanently_deferred' ? 'perm' : 'pending')) ?>"><?= esc($statusLabel) ?></span>
        </div>
      </div>
      <div class="panel-body">
        <div class="status"><?= esc($statusMessage) ?></div>
        <div class="small">Email: <?= esc($donor['email']) ?> | Phone: <?= esc($donor['phone']) ?> | Blood Type: <?= esc($donor['blood_type']) ?></div>

        <div class="activity-grid">
          <div class="activity-card">
            <div class="label">Total Donations</div>
            <div class="value"><?= esc((string)$donationSummary['total_donations']) ?></div>
          </div>
          <div class="activity-card">
            <div class="label">Total Appointments</div>
            <div class="value"><?= esc((string)$donationSummary['total_appointments']) ?></div>
          </div>
          <div class="activity-card">
            <div class="label">Last Donation</div>
            <div class="value small"><?= esc((string)$donationSummary['last_donation']) ?></div>
          </div>
          <div class="activity-card">
            <div class="label">Next Eligible</div>
            <div class="value small"><?= esc((string)$donationSummary['next_eligible']) ?></div>
          </div>
        </div>

        <?php if ($latestNotification): ?>
          <div class="message-box">
            <strong>Latest admin message:</strong><br>
            <span class="small"><?= esc(notificationTitle((string)$latestNotification['decision'])) ?> • <?= esc($latestNotification['created_at']) ?></span>
            <div style="margin-top:8px; white-space:pre-line;"><?= esc($latestNotification['message']) ?></div>
          </div>
        <?php endif; ?>

        <div class="stack">
          <?php if (in_array($status, ['approved', 'approved_to_donate', 'approved_donor'], true)): ?>
            <button class="btn btn-primary" type="button" onclick="alert('Appointment booking flow would open here.')">Book Appointment</button>
          <?php else: ?>
            <button class="btn btn-contact" type="button" onclick="openContact()">Contact Blood Bank</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="notificationModal" role="dialog" aria-modal="true" aria-labelledby="notificationTitle">
    <div class="modal-card">
      <div class="modal-head">
        <h3 id="notificationTitle" style="margin:0;"><?= $latestNotification ? esc(notificationTitle((string)$latestNotification['decision'])) : 'Notification' ?></h3>
      </div>
      <div class="modal-body">
        <p style="margin-top:0;">You have a new message from the admin.</p>
        <div class="message-box" style="margin-top:0; white-space:pre-line;"><?= $latestNotification ? esc($latestNotification['message']) : '' ?></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" onclick="closeNotification()">Close</button>
      </div>
    </div>
  </div>

  <div class="modal" id="contactModal" role="dialog" aria-modal="true" aria-labelledby="contactTitle">
    <div class="modal-card">
      <div class="modal-head"><h3 id="contactTitle" style="margin:0;">Contact Blood Bank</h3></div>
      <div class="modal-body">
        <div class="contact-grid">
          <div><strong>Phone</strong><br><span class="small">+975 2 345 678</span></div>
          <div><strong>Email</strong><br><span class="small">bloodbank@health.gov.bt</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" onclick="closeContact()">Close</button>
      </div>
    </div>
  </div>

  <script>
    const donorEmail = <?= json_encode((string)$donor['email']) ?>;
    const initialNotification = <?= json_encode($latestNotification) ?>;
    const notificationState = { lastId: initialNotification ? Number(initialNotification.id) : 0 };

    if (initialNotification) {
      document.getElementById('notificationModal').style.display = 'flex';
    }

    async function pollNotifications() {
      try {
        const response = await fetch('donor_notifications.php?donor=' + encodeURIComponent(donorEmail), { cache: 'no-store' });
        const data = await response.json();
        if (data.success && data.notification && Number(data.notification.id) !== notificationState.lastId) {
          notificationState.lastId = Number(data.notification.id);
          document.getElementById('notificationTitle').textContent = ({
            approve: 'Approved Donor',
            temp_defer: 'Temporarily Deferred',
            perm_defer: 'Permanently Deferred'
          })[data.notification.decision] || 'Notification';
          document.querySelector('#notificationModal .message-box').textContent = data.notification.message;
          document.getElementById('notificationModal').style.display = 'flex';
        }
      } catch (error) {
        console.warn('Notification poll failed', error);
      }
    }

    setInterval(pollNotifications, 3000);

    function closeNotification() {
      document.getElementById('notificationModal').style.display = 'none';
    }

    function openContact() {
      document.getElementById('contactModal').style.display = 'flex';
    }

    function closeContact() {
      document.getElementById('contactModal').style.display = 'none';
    }

    document.getElementById('notificationModal').addEventListener('click', function (event) {
      if (event.target === this) closeNotification();
    });

    document.getElementById('contactModal').addEventListener('click', function (event) {
      if (event.target === this) closeContact();
    });
  </script>
</body>
</html>
