<?php
declare(strict_types=1);

ini_set('display_errors', '0');

/**
 * Send test result notification email to donor
 * Called when blood test results are processed
 */

header('Content-Type: application/json');

function sendTestResultEmail(string $email, string $donorName, string $result, string $status): void {
    $resultIcon = strtolower($result) === 'negative' ? '✅' : '⚠️';
    $subject = "Your Blood Test Results - {$result}";
    
    $resultMessage = '';
    if (strtolower($result) === 'negative') {
        $resultMessage = <<<HTML
<p style="color: #2E7D4F; font-weight: bold; font-size: 16px;">
    ✅ Congratulations! Your blood test results are <strong>NEGATIVE</strong>.
</p>
<p>You are now eligible to donate blood. You can book your next appointment through your donor dashboard.</p>
HTML;
    } else {
        $resultMessage = <<<HTML
<p style="color: #8a1c1c; font-weight: bold; font-size: 16px;">
    ⚠️ Your blood test results show <strong>POSITIVE</strong> or require further review.
</p>
<p>This means you are temporarily or permanently deferred from blood donation. Please contact your nearest blood bank for more information and guidance.</p>
HTML;
    }

    $message = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #C8102E; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
        .result-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #C8102E; border-radius: 4px; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🧪 Your Blood Test Results</h2>
        </div>
        <div class="content">
            <p>Dear <strong>{$donorName}</strong>,</p>
            
            <p>Your blood test results are now available.</p>
            
            <div class="result-box">
                <p><strong>Test Result:</strong> {$result}</p>
                <p><strong>Status:</strong> {$status}</p>
                {$resultMessage}
            </div>
            
            <p>If you have any questions about your results, please contact your nearest blood bank or call our helpline at <strong>1095</strong>.</p>
            
            <p>Thank you for your interest in saving lives! 💪</p>
            
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

// This function is called from other APIs when test results are processed
// Example usage in test result processing:
// sendTestResultEmail($donorEmail, $donorName, 'Negative', 'Approved Donor');
