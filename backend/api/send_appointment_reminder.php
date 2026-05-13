<?php
declare(strict_types=1);

ini_set('display_errors', '0');

/**
 * Cron job to send appointment reminder emails 2 days before appointment
 * Usage: php send_appointment_reminder.php
 */

header('Content-Type: application/json');

function sendReminderEmail(string $email, string $donorName, string $date, string $time, string $bloodBank): void {
    $subject = "Reminder: Your Blood Donation Appointment on {$date}";
    
    $timeDisplay = !empty($time) ? "⏰ Time: {$time}" : "⏰ Time: To be confirmed";
    $message = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #C8102E; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
        .reminder-box { background: #fff8e1; border-left: 4px solid #FFA500; padding: 15px; margin: 15px 0; }
        .prep-list { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2E7D4F; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📅 Appointment Reminder</h2>
        </div>
        <div class="content">
            <p>Dear <strong>{$donorName}</strong>,</p>
            
            <p>This is a friendly reminder of your upcoming blood donation appointment.</p>
            
            <div class="reminder-box">
                <h3>Appointment Details:</h3>
                <p><strong>Date:</strong> {$date}</p>
                <p><strong>{$timeDisplay}</strong></p>
                <p><strong>Blood Bank:</strong> {$bloodBank}</p>
            </div>
            
            <h3>✅ Before Your Donation:</h3>
            <ul>
                <li>Get a good night's sleep</li>
                <li>Eat a healthy meal</li>
                <li>Drink plenty of water</li>
            </ul>
            
            <h3>❌ Avoid:</h3>
            <ul>
                <li>Alcohol for 24 hours before donation</li>
                <li>Strenuous exercise</li>
            </ul>
            
            <p><strong>📍 Important:</strong> Please arrive 15 minutes early and bring your CID card.</p>
            
            <p>Thank you for your commitment to saving lives! 💪</p>
            
            <p>Best regards,<br>
            <strong>National Blood Transfusion Services</strong><br>
            Ministry of Health, Bhutan</p>
            
            <div class="footer">
                <p>Helpline: 1095 | Available 24/7</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@blood-transfusion.gov.bt" . "\r\n";
    
    @mail($email, $subject, $message, $headers);
}

try {
    $dbPath = __DIR__ . '/../config/db.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database config not found');
    }
    require_once $dbPath;

    // Find appointments scheduled for 2 days from now
    $targetDate = date('Y-m-d', strtotime('+2 days'));
    
    $query = "
        SELECT id, email, full_name, preferred_date, preferred_time, blood_bank 
        FROM tblappointments 
        WHERE preferred_date = ? 
        AND status = 'pending' 
        AND reminder_sent = 0 OR reminder_sent IS NULL
        LIMIT 100
    ";
    
    // Try legacy table if tblappointments doesn't exist
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tblappointments'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            $query = str_replace('tblappointments', 'appointments', $query);
        }
    } catch (Exception $e) {
        $query = str_replace('tblappointments', 'appointments', $query);
    }

    $statement = $pdo->prepare($query);
    $statement->execute([$targetDate]);
    $appointments = $statement->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0;
    foreach ($appointments as $apt) {
        try {
            $email = trim((string)($apt['email'] ?? ''));
            $name = trim((string)($apt['full_name'] ?? ''));
            $date = trim((string)($apt['preferred_date'] ?? ''));
            $time = trim((string)($apt['preferred_time'] ?? ''));
            $bank = trim((string)($apt['blood_bank'] ?? ''));

            if (empty($email) || empty($name)) continue;

            sendReminderEmail($email, $name, $date, $time, $bank);

            // Mark as sent
            $updateQuery = "UPDATE " . (str_contains($query, 'appointments') ? 'appointments' : 'tblappointments') . " SET reminder_sent = 1 WHERE id = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([$apt['id']]);

            $sent++;
        } catch (Exception $e) {
            error_log("Failed to send reminder for appointment {$apt['id']}: " . $e->getMessage());
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Sent {$sent} reminder emails for {$targetDate}",
        'count' => $sent,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing reminders',
        'error' => $e->getMessage(),
    ]);
}
