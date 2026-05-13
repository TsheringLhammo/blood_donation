<?php
declare(strict_types=1);

if (!function_exists('bts_env')) {
    function bts_mail_config(): array
    {
        static $config = null;
        if (is_array($config)) {
            return $config;
        }

        $configPath = __DIR__ . '/mail.local.php';
        if (file_exists($configPath)) {
            $loaded = require $configPath;
            if (is_array($loaded)) {
                $config = $loaded;
                return $config;
            }
        }

        $config = [];
        return $config;
    }

    function bts_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if (!is_string($value)) {
            $cfg = bts_mail_config();
            $fromFile = $cfg[$key] ?? null;
            if (!is_string($fromFile)) {
                return $default;
            }

            $trimmedFromFile = trim($fromFile);
            return $trimmedFromFile !== '' ? $trimmedFromFile : $default;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : $default;
    }
}

if (!function_exists('bts_get_default_test_email')) {
    function bts_get_default_test_email(): string
    {
        return bts_env('BTS_TEST_NOTIFY_EMAIL', '11102000153@rim.edu.bt');
    }
}

if (!function_exists('bts_mail_setup_status')) {
    function bts_mail_setup_status(): array
    {
        $smtpHost = bts_env('BTS_SMTP_HOST', 'smtp.gmail.com');
        $smtpPort = bts_env('BTS_SMTP_PORT', '587');
        $smtpEncryption = bts_env('BTS_SMTP_ENCRYPTION', 'tls');
        $smtpUser = bts_env('BTS_SMTP_USER', '');
        $smtpPass = bts_env('BTS_SMTP_PASS', '');
        $mailFrom = bts_env('BTS_MAIL_FROM', $smtpUser !== '' ? $smtpUser : '');
        $testRecipient = bts_get_default_test_email();

        $missing = [];
        if ($smtpUser === '') {
            $missing[] = 'BTS_SMTP_USER';
        }
        if ($smtpPass === '') {
            $missing[] = 'BTS_SMTP_PASS';
        }
        if ($mailFrom === '') {
            $missing[] = 'BTS_MAIL_FROM';
        }

        return [
            'ready' => $missing === [],
            'missing' => $missing,
            'config' => [
                'smtpHost' => $smtpHost,
                'smtpPort' => $smtpPort,
                'smtpEncryption' => strtolower($smtpEncryption),
                'smtpUserConfigured' => $smtpUser !== '',
                'smtpPassConfigured' => $smtpPass !== '',
                'mailFromConfigured' => $mailFrom !== '',
                'defaultTestRecipient' => $testRecipient,
            ],
        ];
    }
}

if (!function_exists('bts_send_email')) {
    function bts_send_email(string $to, string $subject, string $htmlBody, string $textBody, array &$meta = []): bool
    {
        $meta = [
            'transport' => 'none',
            'error' => null,
            'phpmailerAvailable' => false,
            'smtpConfigured' => false,
        ];

        $to = trim($to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $meta['error'] = 'Recipient email is invalid.';
            return false;
        }

        $autoloadCandidates = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];

        foreach ($autoloadCandidates as $autoloadPath) {
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
                break;
            }
        }

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                $smtpHost = bts_env('BTS_SMTP_HOST', 'smtp.gmail.com');
                $smtpPort = (int)bts_env('BTS_SMTP_PORT', '587');
                $smtpEncryption = strtolower(bts_env('BTS_SMTP_ENCRYPTION', 'tls'));
                $smtpUser = bts_env('BTS_SMTP_USER', '');
                $smtpPass = bts_env('BTS_SMTP_PASS', '');
                $fromEmail = bts_env('BTS_MAIL_FROM', $smtpUser !== '' ? $smtpUser : '');
                $fromName = bts_env('BTS_MAIL_FROM_NAME', 'Blood Donation Management System');
                $meta['phpmailerAvailable'] = true;
                $meta['smtpConfigured'] = ($smtpUser !== '' && $smtpPass !== '' && $fromEmail !== '');

                if (!$meta['smtpConfigured']) {
                    $status = bts_mail_setup_status();
                    $meta['transport'] = 'smtp-phpmailer';
                    $meta['error'] = 'SMTP is not configured. Missing: ' . implode(', ', $status['missing']) . '. Update backend/config/mail.local.php with Gmail + App Password.';
                    return false;
                }

                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = $smtpPort > 0 ? $smtpPort : 587;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;

                if ($smtpEncryption === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }

                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $htmlBody;
                $mail->AltBody = $textBody;
                $mail->isHTML(true);

                $mail->send();
                $meta['transport'] = 'smtp-phpmailer';
                return true;
            } catch (Throwable $exception) {
                $meta['transport'] = 'smtp-phpmailer';
                $meta['error'] = 'SMTP send failed: ' . $exception->getMessage() . ' Check Gmail App Password, SMTP user/pass, and spam folder.';
                return false;
            }
        }

        $fromFallback = bts_env('BTS_MAIL_FROM', 'no-reply@bts.local');
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromFallback,
        ];

        $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        $meta['transport'] = 'php-mail';

        if (!$sent) {
            $meta['error'] = 'Email send failed using PHP mail(). PHPMailer is not installed and local PHP mail is often disabled on Windows/XAMPP. Install PHPMailer (Composer) and configure BTS_SMTP_USER/BTS_SMTP_PASS.';
            return false;
        }

        return true;
    }
}
