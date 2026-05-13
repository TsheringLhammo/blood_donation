<?php
require_once __DIR__ . '/backend/config/db.php';

$adminId = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_decision'])) {
    $donorId = (int)($_POST['donor_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $statusMap = [
        'approve' => 'approved',
        'temp_defer' => 'temporarily_deferred',
        'perm_defer' => 'permanently_deferred',
    ];

    if ($donorId > 0 && isset($statusMap[$decision])) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE tbldonors SET workflow_status = ?, deferral_reason = ? WHERE id = ?');
        $stmt->execute([$statusMap[$decision], $reason !== '' ? $reason : null, $donorId]);

        $insert = $pdo->prepare('INSERT INTO tblnotifications (donor_id, admin_id, decision, message, created_at) VALUES (?, ?, ?, ?, NOW())');
        $insert->execute([$donorId, $adminId, $decision, $message]);

        $pdo->commit();
        header('Location: admin.php?saved=1');
        exit;
    }
}

$donors = $pdo->query('SELECT id, full_name, email, phone, blood_type, test_result, workflow_status FROM tbldonors ORDER BY id ASC')->fetchAll();
$notifications = $pdo->query('SELECT n.id, n.donor_id, n.admin_id, n.decision, n.message, n.is_read, n.created_at, d.full_name AS donor_name FROM tblnotifications n INNER JOIN tbldonors d ON d.id = n.donor_id ORDER BY n.created_at DESC LIMIT 5')->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
  <style>
    :root { --bg:#f6f1ea; --panel:#ffffff; --text:#1f2937; --muted:#6b7280; --line:#d6d3d1; --accent:#8b1e3f; --accent2:#14532d; --warn:#92400e; --danger:#991b1b; }
    * { box-sizing:border-box; }
    body { margin:0; font-family: Arial, Helvetica, sans-serif; background: linear-gradient(180deg, #f6f1ea 0%, #efe7de 100%); color:var(--text); }
    .wrap { max-width: 1400px; margin: 0 auto; padding: 24px; }
    .hero { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:20px; }
    .hero h1 { margin:0; font-size:32px; }
    .hero p { margin:6px 0 0; color:var(--muted); }
    .panel { background:var(--panel); border:1px solid var(--line); border-radius:16px; box-shadow:0 12px 30px rgba(31,41,55,.08); overflow:hidden; margin-bottom:24px; }
    .panel-head { padding:18px 20px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .panel-head h2, .panel-head h3 { margin:0; }
    .table-wrap { overflow:auto; }
    table { width:100%; border-collapse:collapse; min-width:1180px; }
    th, td { padding:14px 12px; border-bottom:1px solid #eee7df; text-align:left; vertical-align:top; }
    th { background:#faf7f2; font-size:13px; letter-spacing:.04em; text-transform:uppercase; color:#4b5563; }
    .chip { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .chip.approved { background:#dcfce7; color:#166534; }
    .chip.temp { background:#fef3c7; color:#92400e; }
    .chip.perm { background:#fee2e2; color:#991b1b; }
    .chip.pending { background:#e5e7eb; color:#374151; }
    .btn { border:0; border-radius:12px; padding:9px 12px; font-weight:700; cursor:pointer; transition:.2s transform ease, .2s opacity ease; margin:0 6px 6px 0; }
    .btn:hover { transform: translateY(-1px); opacity:.94; }
    .btn-approve { background:#14532d; color:#fff; }
    .btn-temp { background:#92400e; color:#fff; }
    .btn-perm { background:#991b1b; color:#fff; }
    .btn-soft { background:#eef2ff; color:#312e81; }
    .notice { background:#ecfeff; border:1px solid #a5f3fc; padding:12px 14px; border-radius:12px; margin:0 0 20px; }
    .modal { position:fixed; inset:0; background:rgba(15,23,42,.55); display:none; align-items:center; justify-content:center; padding:16px; z-index:999; }
    .modal-card { width:min(560px, 100%); background:#fff; border-radius:18px; box-shadow:0 24px 60px rgba(0,0,0,.24); overflow:hidden; }
    .modal-head { padding:18px 20px; border-bottom:1px solid #eee7df; }
    .modal-body { padding:20px; }
    .modal-footer { padding:16px 20px 20px; display:flex; justify-content:flex-end; gap:10px; }
    label { display:block; font-weight:700; margin-bottom:8px; }
    input, textarea { width:100%; padding:11px 12px; border:1px solid #d8d2ca; border-radius:12px; font:inherit; }
    textarea { min-height:110px; resize:vertical; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .last5 { min-width:100%; }
    .small { font-size:13px; color:var(--muted); }
    .link-btn { background:transparent; border:0; color:var(--accent); font-weight:700; cursor:pointer; padding:0; }
    @media (max-width: 900px) { .hero { flex-direction:column; align-items:flex-start; } .grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hero">
      <div>
        <h1>Admin Dashboard</h1>
        <p>Admin-only decisions create donor notifications immediately.</p>
      </div>
      <div class="small">Test admin ID: 1</div>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
      <div class="notice">Decision saved and donor notification queued.</div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head">
        <h2>Donor Review Table</h2>
        <span class="small">Approve, defer, or permanently defer from here only</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Blood Type</th>
              <th>Test Result</th>
              <th>Workflow Status</th>
              <th>Admin Decision</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($donors as $donor): ?>
              <?php
                $status = (string)$donor['workflow_status'];
                $statusClass = str_contains($status, 'approved') ? 'approved' : (str_contains($status, 'temporary') ? 'temp' : (str_contains($status, 'permanent') ? 'perm' : 'pending'));
                $testResult = strtolower((string)$donor['test_result']);
              ?>
              <tr>
                <td><?= esc($donor['id']) ?></td>
                <td><?= esc($donor['full_name']) ?></td>
                <td><?= esc($donor['email']) ?></td>
                <td><?= esc($donor['phone']) ?></td>
                <td><?= esc($donor['blood_type']) ?></td>
                <td><?= esc($donor['test_result']) ?></td>
                <td><span class="chip <?= $statusClass ?>"><?= esc($status) ?></span></td>
                <td>
                  <button class="btn btn-approve" type="button" onclick="openDecision(<?= (int)$donor['id'] ?>, 'approve', 'Approve Donor', '<?= esc($donor['test_result']) ?>')">✅ Approve</button>
                  <button class="btn btn-temp" type="button" onclick="openDecision(<?= (int)$donor['id'] ?>, 'temp_defer', 'Temporary Deferral', '<?= esc($donor['test_result']) ?>')">⏸️ Temp Defer</button>
                  <button class="btn btn-perm" type="button" onclick="openDecision(<?= (int)$donor['id'] ?>, 'perm_defer', 'Permanent Deferral', '<?= esc($donor['test_result']) ?>')">❌ Permanent Defer</button>
                </td>
                <td><button class="link-btn" type="button" onclick="alert('Name: <?= esc($donor['full_name']) ?>\nEmail: <?= esc($donor['email']) ?>\nPhone: <?= esc($donor['phone']) ?>\nBlood Type: <?= esc($donor['blood_type']) ?>\nResult: <?= esc($donor['test_result']) ?>')">View</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h3>Test Mode</h3>
        <span class="small">Last 5 notifications</span>
      </div>
      <div class="table-wrap">
        <table class="last5">
          <thead>
            <tr>
              <th>ID</th>
              <th>Donor</th>
              <th>Decision</th>
              <th>Message</th>
              <th>Read</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($notifications as $n): ?>
              <tr>
                <td><?= esc($n['id']) ?></td>
                <td><?= esc($n['donor_name']) ?></td>
                <td><?= esc($n['decision']) ?></td>
                <td><?= esc($n['message']) ?></td>
                <td><?= (int)$n['is_read'] === 1 ? 'Yes' : 'No' ?></td>
                <td><?= esc($n['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="modal" id="decisionModal" role="dialog" aria-modal="true" aria-labelledby="decisionTitle">
    <div class="modal-card">
      <div class="modal-head">
        <h3 id="decisionTitle" style="margin:0;">Confirm Decision</h3>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="donor_id" id="donor_id">
          <input type="hidden" name="decision" id="decision">
          <div class="grid">
            <div>
              <label for="reason">Reason for decision</label>
              <input type="text" name="reason" id="reason" placeholder="Enter reason">
            </div>
            <div>
              <label for="message">Additional message to donor</label>
              <textarea name="message" id="message" placeholder="Write the message donor will see"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" onclick="closeDecision()">Cancel</button>
          <button type="submit" name="save_decision" class="btn btn-perm" id="confirmBtn">Confirm Decision</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openDecision(donorId, decision, title, testResult = '') {
      // Check if it's a serious disease
      const testResultLower = testResult.toLowerCase();
      const seriousDiseases = ['hiv', 'hepatitis b', 'hepatitis c', 'syphilis', 'mad cow', 'creutzfeldt'];
      const hasSeriousDisease = seriousDiseases.some(disease => testResultLower.includes(disease));
      
      // Force permanent deferral for serious diseases when clicking temp_defer
      let finalDecision = decision;
      let finalTitle = title;
      
      if (decision === 'temp_defer' && hasSeriousDisease) {
        finalDecision = 'perm_defer';
        finalTitle = '❌ Permanent Deferral (Serious Disease)';
      }
      
      document.getElementById('donor_id').value = donorId;
      document.getElementById('decision').value = finalDecision;
      document.getElementById('decisionTitle').textContent = finalTitle;
      document.getElementById('reason').value = '';
      document.getElementById('message').value = '';
      
      // Update button text based on decision
      const confirmBtn = document.getElementById('confirmBtn');
      if (finalDecision === 'approve') {
        confirmBtn.textContent = '✅ Approve Donor';
      } else if (finalDecision === 'perm_defer') {
        confirmBtn.textContent = '❌ Defer Permanently';
      } else {
        confirmBtn.textContent = 'Confirm Decision';
      }
      
      document.getElementById('decisionModal').style.display = 'flex';
    }

    function closeDecision() {
      document.getElementById('decisionModal').style.display = 'none';
    }

    document.getElementById('decisionModal').addEventListener('click', function (event) {
      if (event.target === this) closeDecision();
    });
  </script>
</body>
</html>
