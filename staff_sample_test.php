<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/config/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeResult(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === 'reactive' || $normalized === 'positive') {
        return 'Reactive';
    }
    return 'Non-reactive';
}

function table_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

$successMessage = '';
$errorMessage = '';
$selectedSampleId = 0;
$sampleTestForm = [
    'technician' => '',
    'hiv_result' => 'Non-reactive',
    'hbsag_result' => 'Non-reactive',
    'hcv_result' => 'Non-reactive',
    'syphilis_result' => 'Non-reactive',
    'malaria_result' => 'Non-reactive',
    'notes' => '',
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_test'])) {
        $selectedSampleId = (int)($_POST['sample_id'] ?? 0);
        $sampleTestForm['technician'] = trim((string)($_POST['technician'] ?? ''));
        $sampleTestForm['hiv_result'] = trim((string)($_POST['hiv_result'] ?? 'Non-reactive'));
        $sampleTestForm['hbsag_result'] = trim((string)($_POST['hbsag_result'] ?? 'Non-reactive'));
        $sampleTestForm['hcv_result'] = trim((string)($_POST['hcv_result'] ?? 'Non-reactive'));
        $sampleTestForm['syphilis_result'] = trim((string)($_POST['syphilis_result'] ?? 'Non-reactive'));
        $sampleTestForm['malaria_result'] = trim((string)($_POST['malaria_result'] ?? 'Non-reactive'));
        $sampleTestForm['notes'] = trim((string)($_POST['notes'] ?? ''));

        if ($selectedSampleId <= 0) {
            throw new RuntimeException('Please select a pending sample before saving.');
        }

        if ($sampleTestForm['technician'] === '') {
            throw new RuntimeException('Technician name is required.');
        }

        $stmt = $pdo->prepare(
            'SELECT s.id, s.donor_id, s.review_status, d.full_name AS donor_name FROM tbldonor_samples s INNER JOIN tbldonors d ON d.id = s.donor_id WHERE s.id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$selectedSampleId]);
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sample) {
            throw new RuntimeException('Selected sample not found.');
        }

        if ($sample['review_status'] !== 'Collected') {
            throw new RuntimeException('Only samples currently in "Collected" status may be saved for admin approval.');
        }

        $hivResult = normalizeResult($sampleTestForm['hiv_result']);
        $hbsagResult = normalizeResult($sampleTestForm['hbsag_result']);
        $hcvResult = normalizeResult($sampleTestForm['hcv_result']);
        $syphilisResult = normalizeResult($sampleTestForm['syphilis_result']);
        $malariaResult = normalizeResult($sampleTestForm['malaria_result']);

        $reactiveTests = [];
        if ($hivResult === 'Reactive') {
            $reactiveTests[] = 'HIV';
        }
        if ($hbsagResult === 'Reactive') {
            $reactiveTests[] = 'HBsAg';
        }
        if ($hcvResult === 'Reactive') {
            $reactiveTests[] = 'HCV';
        }
        if ($syphilisResult === 'Reactive') {
            $reactiveTests[] = 'Syphilis';
        }
        if ($malariaResult === 'Reactive') {
            $reactiveTests[] = 'Malaria';
        }

        $sampleStatus = empty($reactiveTests) ? 'Negative' : 'Reactive';
        $donorResult = empty($reactiveTests) ? 'Negative' : 'Positive';

        $pdo->beginTransaction();

        $updateSampleStmt = $pdo->prepare(
            'UPDATE tbldonor_samples
             SET hiv_result = ?, hbsag_result = ?, hcv_result = ?, syphilis_result = ?, malaria_result = ?, tested_by = ?, notes = ?, status = ?, review_status = "Pending Admin Approval", reactive_tests = ?, tested_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        );
        $updateSampleStmt->execute([
            $hivResult,
            $hbsagResult,
            $hcvResult,
            $syphilisResult,
            $malariaResult,
            $sampleTestForm['technician'],
            $sampleTestForm['notes'],
            $sampleStatus,
            implode(', ', $reactiveTests) ?: null,
            $selectedSampleId,
        ]);

        $donorColumns = [];
        $donorValues = [];
        if (table_column_exists($pdo, 'tbldonors', 'sample_status')) {
            $donorColumns[] = 'sample_status = ?';
            $donorValues[] = 'Tested';
        }
        if (table_column_exists($pdo, 'tbldonors', 'test_result')) {
            $donorColumns[] = 'test_result = ?';
            $donorValues[] = $donorResult;
        }
        if (table_column_exists($pdo, 'tbldonors', 'eligibility')) {
            $donorColumns[] = 'eligibility = ?';
            $donorValues[] = 'Pending';
        }

        if (!empty($donorColumns)) {
            $donorValues[] = (int)$sample['donor_id'];
            $donorUpdateStmt = $pdo->prepare('UPDATE tbldonors SET ' . implode(', ', $donorColumns) . ' WHERE id = ?');
            $donorUpdateStmt->execute($donorValues);
        }

        $pdo->commit();

        $successMessage = sprintf('Sample test saved for donor "%s" and is now pending admin approval.', $sample['donor_name']);
    }

    $sampleStmt = $pdo->query(
        'SELECT s.id AS sample_id, s.collection_date, s.technician, d.full_name, d.email, d.phone, d.blood_type, s.status, s.notes
         FROM tbldonor_samples s
         INNER JOIN tbldonors d ON d.id = s.donor_id
         WHERE s.review_status = "Collected"
         ORDER BY s.collection_date DESC, s.id DESC'
    );
    $pendingSamples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Sample Testing</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f4f7fb;color:#222;margin:0;padding:0; }
    .page { max-width: 980px; margin: 0 auto; padding: 24px; }
    h1 { margin-top:0; }
    .card { background:#fff;border:1px solid #d6dae1;border-radius:10px;padding:20px;box-shadow:0 2px 12px rgba(34,50,84,.08);margin-bottom:20px; }
    .field { margin-bottom:14px; }
    label { display:block;font-weight:600;margin-bottom:6px; }
    select, input[type=text], textarea { width:100%;padding:10px;border:1px solid #bcc0c8;border-radius:8px;font-size:1rem; }
    textarea { min-height:120px; resize:vertical; }
    .flex { display:flex;gap:16px;flex-wrap:wrap; }
    .flex > * { flex:1; min-width:220px; }
    .button { background:#0b5ed7;color:#fff;border:none;padding:12px 18px;border-radius:8px;font-size:1rem;cursor:pointer; }
    .button:disabled { opacity:.65; cursor:not-allowed; }
    .message { padding:12px 16px;border-radius:8px;margin-bottom:18px; }
    .message.success { background:#e6f4ea;color:#0f5132;border:1px solid #badbcc; }
    .message.error { background:#f8d7da;color:#842029;border:1px solid #f5c2c7; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    th, td { padding:12px 10px; border-bottom:1px solid #e2e6ef; text-align:left; }
    th { background:#f7f9fc; }
    .note { color:#555; font-size:.95rem; }
  </style>
</head>
<body>
  <div class="page">
    <h1>Staff Sample Testing</h1>
    <p class="note">Select a collected sample, enter results, optional notes, then save. The sample will move to <strong>Pending Admin Approval</strong>.</p>

    <?php if ($successMessage !== ''): ?>
      <div class="message success"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
      <div class="message error"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="card">
      <form id="sampleSelectForm" method="post">
        <div class="field">
          <label for="sample_id">Pending Sample</label>
          <select id="sample_id" name="sample_id" required onchange="document.getElementById('sampleSelectForm').submit()">
            <option value="">-- Select pending sample --</option>
            <?php foreach ($pendingSamples as $sample): ?>
              <option value="<?= e((string)$sample['sample_id']) ?>"<?= $selectedSampleId === (int)$sample['sample_id'] ? ' selected' : '' ?>>
                <?= e($sample['full_name']) ?> · Sample #<?= e((string)$sample['sample_id']) ?> · <?= e($sample['collection_date']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <?php if ($selectedSampleId > 0): ?>
        <?php
          $selectedSample = null;
          foreach ($pendingSamples as $sample) {
              if ((int)$sample['sample_id'] === $selectedSampleId) {
                  $selectedSample = $sample;
                  break;
              }
          }
        ?>
        <?php if ($selectedSample): ?>
          <div class="card">
            <h2>Selected Sample Details</h2>
            <p><strong>Donor:</strong> <?= e($selectedSample['full_name']) ?></p>
            <p><strong>Blood Type:</strong> <?= e($selectedSample['blood_type']) ?></p>
            <p><strong>Collection Date:</strong> <?= e($selectedSample['collection_date']) ?></p>
            <p><strong>Collected by:</strong> <?= e($selectedSample['technician']) ?></p>
            <p class="note"><strong>Email:</strong> <?= e($selectedSample['email']) ?> · <strong>Phone:</strong> <?= e($selectedSample['phone']) ?></p>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if (!empty($pendingSamples)): ?>
        <form id="sampleForm" method="post">
          <input type="hidden" name="sample_id" value="<?= e((string)$selectedSampleId) ?>">

          <div class="field">
            <label for="technician">Technician Name</label>
            <input type="text" id="technician" name="technician" value="<?= e($sampleTestForm['technician']) ?>" required>
          </div>

          <div class="flex">
            <div class="field">
              <label for="hiv_result">HIV Result</label>
              <select id="hiv_result" name="hiv_result">
                <option value="Non-reactive"<?= $sampleTestForm['hiv_result'] === 'Non-reactive' ? ' selected' : '' ?>>Non-reactive</option>
                <option value="Reactive"<?= $sampleTestForm['hiv_result'] === 'Reactive' ? ' selected' : '' ?>>Reactive</option>
              </select>
            </div>
            <div class="field">
              <label for="hbsag_result">HBsAg Result</label>
              <select id="hbsag_result" name="hbsag_result">
                <option value="Non-reactive"<?= $sampleTestForm['hbsag_result'] === 'Non-reactive' ? ' selected' : '' ?>>Non-reactive</option>
                <option value="Reactive"<?= $sampleTestForm['hbsag_result'] === 'Reactive' ? ' selected' : '' ?>>Reactive</option>
              </select>
            </div>
            <div class="field">
              <label for="hcv_result">HCV Result</label>
              <select id="hcv_result" name="hcv_result">
                <option value="Non-reactive"<?= $sampleTestForm['hcv_result'] === 'Non-reactive' ? ' selected' : '' ?>>Non-reactive</option>
                <option value="Reactive"<?= $sampleTestForm['hcv_result'] === 'Reactive' ? ' selected' : '' ?>>Reactive</option>
              </select>
            </div>
          </div>

          <div class="flex">
            <div class="field">
              <label for="syphilis_result">Syphilis Result</label>
              <select id="syphilis_result" name="syphilis_result">
                <option value="Non-reactive"<?= $sampleTestForm['syphilis_result'] === 'Non-reactive' ? ' selected' : '' ?>>Non-reactive</option>
                <option value="Reactive"<?= $sampleTestForm['syphilis_result'] === 'Reactive' ? ' selected' : '' ?>>Reactive</option>
              </select>
            </div>
            <div class="field">
              <label for="malaria_result">Malaria Result</label>
              <select id="malaria_result" name="malaria_result">
                <option value="Non-reactive"<?= $sampleTestForm['malaria_result'] === 'Non-reactive' ? ' selected' : '' ?>>Non-reactive</option>
                <option value="Reactive"<?= $sampleTestForm['malaria_result'] === 'Reactive' ? ' selected' : '' ?>>Reactive</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label for="notes">Optional Notes</label>
            <textarea id="notes" name="notes"><?= e($sampleTestForm['notes']) ?></textarea>
          </div>

          <button type="submit" name="save_test" class="button">Save and Send for Admin Approval</button>
        </form>
      <?php else: ?>
        <p class="note">No collected samples are currently awaiting lab processing.</p>
      <?php endif; ?>
    </div>

    <?php if (!empty($pendingSamples)): ?>
      <div class="card">
        <h2>Pending Collected Samples</h2>
        <table>
          <thead>
            <tr>
              <th>Sample ID</th>
              <th>Donor</th>
              <th>Blood Type</th>
              <th>Collected</th>
              <th>Collected By</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingSamples as $sample): ?>
              <tr>
                <td><?= e((string)$sample['sample_id']) ?></td>
                <td><?= e($sample['full_name']) ?></td>
                <td><?= e($sample['blood_type']) ?></td>
                <td><?= e($sample['collection_date']) ?></td>
                <td><?= e($sample['technician']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
