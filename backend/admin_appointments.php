<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

session_start();
if (!isset($_SESSION['role']) || (string)$_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo 'Access denied. Admin login required.';
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

$message = '';
$error = '';

try {
    $donorNameColumn = donor_name_column($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        $action = trim((string)($_POST['action'] ?? ''));

        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment id.');
        }

        if (!in_array($action, ['confirm', 'reject'], true)) {
            throw new RuntimeException('Invalid action.');
        }

        $newStatus = $action === 'confirm' ? 'Confirmed' : 'Rejected';

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT a.id,
              a.donor_id,
              a.appointment_date,
              a.appointment_time,
              d.' . $donorNameColumn . ' AS donor_name,
              d.email,
              bb.name AS blood_bank_name
             FROM tblappointments a
             INNER JOIN tbldonors d ON d.id = a.donor_id
             LEFT JOIN tblblood_banks bb ON bb.id = a.blood_bank_id
             WHERE a.id = ?
             FOR UPDATE'
        );
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        $update = $pdo->prepare('UPDATE tblappointments SET status = ? WHERE id = ?');
        $update->execute([$newStatus, $appointmentId]);

        $pdo->commit();

        $email = trim((string)($appointment['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $subject = 'Appointment ' . $newStatus;
            $body = '<p>Dear ' . h((string)$appointment['donor_name']) . ',</p>'
                . '<p>Your blood donation appointment has been <strong>' . h($newStatus) . '</strong>.</p>'
                . '<ul>'
                . '<li>Date: ' . h((string)$appointment['appointment_date']) . '</li>'
                . '<li>Time: ' . h((string)$appointment['appointment_time']) . '</li>'
                . '<li>Blood Bank: ' . h((string)($appointment['blood_bank_name'] ?? 'N/A')) . '</li>'
                . '</ul>'
                . '<p>Thank you for supporting blood donation services.</p>';
            $text = 'Appointment status: ' . $newStatus
                . ' | Date: ' . (string)$appointment['appointment_date']
                . ' | Time: ' . (string)$appointment['appointment_time']
                . ' | Blood Bank: ' . (string)($appointment['blood_bank_name'] ?? 'N/A');

            $mailMeta = [];
            bts_send_email($email, $subject, $body, $text, $mailMeta);
        }

        $message = 'Appointment #' . (string)$appointmentId . ' updated to ' . $newStatus . '.';
    }

    $pendingStmt = $pdo->query(
      'SELECT a.id,
          a.appointment_date,
          a.appointment_time,
          a.status,
          d.' . $donorNameColumn . ' AS donor_name,
          bb.name AS blood_bank_name
       FROM tblappointments a
       INNER JOIN tbldonors d ON d.id = a.donor_id
       LEFT JOIN tblblood_banks bb ON bb.id = a.blood_bank_id
       WHERE LOWER(a.status) = "pending"
       ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.id ASC'
    );
    $appointments = $pendingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $exception->getMessage();
    $appointments = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Appointment Confirmation</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f7f9fc; color: #0f172a; }
    .wrap { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,0.07); }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; }
    .ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    .btn { border: 0; border-radius: 7px; padding: 8px 10px; cursor: pointer; color: #fff; }
    .confirm { background: #15803d; }
    .reject { background: #b91c1c; }
    .action-form { display: inline-block; margin-right: 8px; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Pending Appointments</h1>

  <?php if ($message !== ''): ?>
    <div class="ok"><?php echo h($message); ?></div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="error"><?php echo h($error); ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Donor</th>
        <th>Date</th>
        <th>Time</th>
        <th>Blood Bank</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$appointments): ?>
        <tr><td colspan="6">No pending appointments.</td></tr>
      <?php else: ?>
        <?php foreach ($appointments as $row): ?>
          <tr>
            <td><?php echo h((string)$row['id']); ?></td>
            <td><?php echo h((string)$row['donor_name']); ?></td>
            <td><?php echo h((string)$row['appointment_date']); ?></td>
            <td><?php echo h((string)$row['appointment_time']); ?></td>
            <td><?php echo h((string)($row['blood_bank_name'] ?? 'N/A')); ?></td>
            <td>
              <form class="action-form" method="post">
                <input type="hidden" name="appointment_id" value="<?php echo h((string)$row['id']); ?>">
                <input type="hidden" name="action" value="confirm">
                <button class="btn confirm" type="submit">Confirm</button>
              </form>
              <form class="action-form" method="post">
                <input type="hidden" name="appointment_id" value="<?php echo h((string)$row['id']); ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn reject" type="submit">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
