<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/config/db.php';
require_once __DIR__ . '/backend/api/message_helpers.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selectValue(array $data, string $key, string $default = ''): string
{
    return trim((string)($data[$key] ?? $default));
}

$successMessage = '';
$errorMessage = '';
$searchTerm = '';
$adminName = '';
$adminId = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_sample'])) {
        $sampleId = (int)($_POST['sample_id'] ?? 0);
        $adminName = selectValue($_POST, 'admin_name');
        $adminId = selectValue($_POST, 'admin_id');

        if ($sampleId <= 0) {
            throw new RuntimeException('A valid sample must be selected for approval.');
        }
        if ($adminName === '') {
            throw new RuntimeException('Admin name is required to approve samples.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT s.*, d.full_name, d.email, d.phone, d.blood_type
             FROM tbldonor_samples s
             INNER JOIN tbldonors d ON d.id = s.donor_id
             WHERE s.id = ?
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sample) {
            throw new RuntimeException('Sample was not found.');
        }
        if ($sample['review_status'] !== 'Pending Admin Approval') {
            throw new RuntimeException('Only samples pending admin approval may be approved.');
        }

        $reactiveTests = [];
        foreach (['hiv_result' => 'HIV', 'hbsag_result' => 'HBsAg', 'hcv_result' => 'HCV', 'syphilis_result' => 'Syphilis', 'malaria_result' => 'Malaria'] as $field => $label) {
            if (strtolower(trim((string)$sample[$field])) === 'reactive') {
                $reactiveTests[] = $label;
            }
        }

        $allNegative = empty($reactiveTests);
        $hasPermanentReactive =
            strtolower(trim((string)$sample['hiv_result'])) === 'reactive' ||
            strtolower(trim((string)$sample['hbsag_result'])) === 'reactive' ||
            strtolower(trim((string)$sample['hcv_result'])) === 'reactive';
        $donorEligibility = $allNegative ? 'Eligible' : 'Not Eligible';
        $donorTestResult = $allNegative ? 'Negative' : 'Positive';
        $messageKey = sprintf('sample_test_result_%d', $sampleId);

        if ($allNegative) {
            $messageContent = sprintf(
                'Dear %s, your blood sample test results are NEGATIVE for HIV, Hepatitis B, Hepatitis C, Syphilis, and Malaria. You are eligible to donate blood. Thank you for saving lives!',
                trim((string)$sample['full_name'])
            );
        } else {
            $messageContent = sprintf(
                'Dear %s, thank you for your willingness to donate. Your test was REACTIVE/POSITIVE for %s. You are not eligible to donate at this time. Please consult a healthcare provider for further guidance.',
                trim((string)$sample['full_name']),
                implode(', ', $reactiveTests)
            );
        }

        $updateSample = $pdo->prepare(
            'UPDATE tbldonor_samples
             SET review_status = "Approved",
                 approved_by_admin_id = ?,
                 approved_by_admin_name = ?,
                 approved_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?'
        );
        $updateSample->execute([(int)$adminId, $adminName, $sampleId]);

        if ($allNegative) {
            $donorUpdate = $pdo->prepare(
                'UPDATE tbldonors SET sample_status = "Tested", test_result = ?, eligibility = ?, updated_at = NOW() WHERE id = ?'
            );
            $donorUpdate->execute([$donorTestResult, $donorEligibility, (int)$sample['donor_id']]);
        } else {
            $donorWorkflowStatus = $hasPermanentReactive ? 'permanently_deferred' : 'decision_made_deferred';
            $donorStatus = $hasPermanentReactive ? 'Permanently Deferred' : 'Deferred';
            $donorDeferred = 1;
            $donorDeferredUntil = $hasPermanentReactive ? null : date('Y-m-d', strtotime('+6 months'));
            $donorDeferralReason = 'Reactive: ' . implode(', ', $reactiveTests);

            $donorUpdate = $pdo->prepare(
                'UPDATE tbldonors
                 SET sample_status = "Tested",
                     test_result = ?,
                     eligibility = ?,
                     workflow_status = ?,
                     status = ?,
                     deferred = ?,
                     deferred_until = ?,
                     deferral_reason = ?,
                     updated_at = NOW()
                 WHERE id = ?'
            );
            $donorUpdate->execute([
                $donorTestResult,
                $donorEligibility,
                $donorWorkflowStatus,
                $donorStatus,
                $donorDeferred,
                $donorDeferredUntil,
                $donorDeferralReason,
                (int)$sample['donor_id'],
            ]);
        }

        $notification = send_donor_test_result_notification($pdo, [
            'donor_id' => (int)$sample['donor_id'],
            'sample_id' => $sampleId,
            'admin_id' => (int)$adminId,
            'admin_name' => $adminName,
            'message_key' => $messageKey,
            'message_content' => $messageContent,
            'subject' => 'Blood Sample Test Result',
            'email' => trim((string)$sample['email']),
            'phone' => trim((string)$sample['phone']),
            'channel' => 'both',
        ]);

        $pdo->commit();

        $successMessage = 'Sample approved successfully. Notification has been queued.';
        if (!empty($notification['errors'])) {
            $successMessage .= ' ' . implode(' ', $notification['errors']);
        }
    }

    $searchTerm = trim((string)($_GET['search'] ?? ''));
    $query = 'SELECT s.id AS sample_id, s.donor_id, d.full_name, d.email, d.phone, d.blood_type, s.collection_date, s.tested_at, s.technician, s.hiv_result, s.hbsag_result, s.hcv_result, s.syphilis_result, s.malaria_result, s.reactive_tests, s.notes, s.approved_by_admin_name, s.approved_at FROM tbldonor_samples s INNER JOIN tbldonors d ON d.id = s.donor_id WHERE s.review_status = "Pending Admin Approval"';
    $params = [];
    if ($searchTerm !== '') {
        $query .= ' AND (d.full_name LIKE ? OR d.email LIKE ? OR d.phone LIKE ? OR s.id = ?)';
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
        $params[] = is_numeric($searchTerm) ? (int)$searchTerm : 0;
    }
    $query .= ' ORDER BY COALESCE(s.tested_at, s.collection_date, s.id) DESC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pendingSamples = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = $exception->getMessage();
    $pendingSamples = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Pending Approvals</title>
  <style>
    body { font-family: Arial, sans-serif; background:#fbfcfd;color:#1f2937;margin:0;padding:0; }
    .page { max-width: 1100px; margin: 0 auto; padding: 24px; }
    .card { background:#fff;border:1px solid #dde3ed;border-radius:12px;padding:20px;box-shadow:0 2px 14px rgba(15,23,42,.08);margin-bottom:20px; }
    h1,h2 { margin-top:0; }
    .field { margin-bottom:14px; }
    label { display:block;font-weight:700;margin-bottom:6px; }
    input[type=text], input[type=number], textarea { width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem; }
    textarea { min-height:100px; resize:vertical; }
    button { background:#2563eb;color:#fff;border:none;padding:12px 18px;border-radius:10px;font-size:1rem;cursor:pointer; }
    button:disabled { opacity:.65; cursor:not-allowed; }
    .message { padding:14px 18px;border-radius:12px;margin-bottom:18px; }
    .success { background:#ecfdf5;color:#166534;border:1px solid #a7f3d0; }
    .error { background:#f8d7da;color:#842029;border:1px solid #f5c2c7; }
    table { width:100%; border-collapse:collapse; margin-top:14px; }
    th, td { padding:12px 10px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
    th { background:#f8fafc; }
    .badge { display:inline-block;padding:4px 10px;border-radius:999px;font-size:.85rem;background:#e2e8f0;color:#334155; }
    .info { color:#0f172a; }
    .note { color:#475569; font-size:.95rem; }
  </style>
</head>
<body>
  <div class="page">
    <h1>Pending Admin Approvals</h1>
    <p class="note">Review staff-entered sample results and approve with one click. Approved donors are removed from this list automatically.</p>

    <?php if ($successMessage !== ''): ?>
      <div class="message success"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
      <div class="message error"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="card">
      <form method="get">
        <div class="field">
          <label for="search">Filter pending approvals</label>
          <input id="search" name="search" type="text" placeholder="Search donor name, email, phone, or sample ID" value="<?= e($searchTerm) ?>">
        </div>
        <button type="submit">Search</button>
      </form>
    </div>

    <?php if (empty($pendingSamples)): ?>
      <div class="card"><p>No samples are currently pending admin approval.</p></div>
    <?php else: ?>
      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Sample #</th>
              <th>Donor</th>
              <th>Test Results</th>
              <th>Collected By</th>
              <th>Collected</th>
              <th>Admin</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingSamples as $sample): ?>
              <tr>
                <td><?= e((string)$sample['sample_id']) ?></td>
                <td>
                  <strong><?= e($sample['full_name']) ?></strong><br>
                  <span class="note">Email: <?= e($sample['email']) ?> | Phone: <?= e($sample['phone']) ?></span><br>
                  <span class="badge">Blood type: <?= e($sample['blood_type']) ?></span>
                </td>
                <td>
                  <strong>HIV:</strong> <?= e($sample['hiv_result'] ?: 'N/A') ?><br>
                  <strong>HBsAg:</strong> <?= e($sample['hbsag_result'] ?: 'N/A') ?><br>
                  <strong>HCV:</strong> <?= e($sample['hcv_result'] ?: 'N/A') ?><br>
                  <strong>Syphilis:</strong> <?= e($sample['syphilis_result'] ?: 'N/A') ?><br>
                  <strong>Malaria:</strong> <?= e($sample['malaria_result'] ?: 'N/A') ?><br>
                  <?php if ($sample['reactive_tests']): ?>
                    <span class="note">Reactive: <?= e($sample['reactive_tests']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= e($sample['technician']) ?><br>
                  <span class="note">Notes: <?= e($sample['notes']) ?></span>
                </td>
                <td>
                  <?= e($sample['collection_date'] ?? 'N/A') ?><br>
                  <span class="note">Tested: <?= e($sample['tested_at'] ?? 'Pending') ?></span>
                </td>
                <td>
                  <?= e($sample['approved_by_admin_name'] ?: '-') ?><br>
                  <span class="note"><?= e($sample['approved_at'] ?? '-') ?></span>
                </td>
                <td>
                  <form method="post">
                    <input type="hidden" name="sample_id" value="<?= e((string)$sample['sample_id']) ?>">
                    <input type="hidden" name="search" value="<?= e($searchTerm) ?>">
                    <div class="field"><label for="admin_name_<?= e((string)$sample['sample_id']) ?>">Admin Name</label>
                      <input id="admin_name_<?= e((string)$sample['sample_id']) ?>" type="text" name="admin_name" value="<?= e($adminName) ?>" required>
                    </div>
                    <div class="field"><label for="admin_id_<?= e((string)$sample['sample_id']) ?>">Admin ID (optional)</label>
                      <input id="admin_id_<?= e((string)$sample['sample_id']) ?>" type="number" name="admin_id" value="<?= e($adminId) ?>">
                    </div>
                    <button type="submit" name="approve_sample">Approve & Send Message</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
