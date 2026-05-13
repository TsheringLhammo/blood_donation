<?php
declare(strict_types=1);

if (!function_exists('send_email_message')) {
    function send_email_message(string $to, string $subject, string $plainText, string $htmlBody = ''): bool
    {
        if (function_exists('bts_send_email')) {
            try {
                return (bool)bts_send_email($to, $subject, $htmlBody !== '' ? $htmlBody : $plainText, $plainText, []);
            } catch (Throwable $exception) {
                // fall through to fallback mail
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: Blood Bank <no-reply@bloodbank.local>',
        ];

        return (bool)@mail($to, $subject, $plainText, implode("\r\n", $headers));
    }
}

if (!function_exists('send_sms_message')) {
    function send_sms_message(string $phone, string $message, array &$meta = []): bool
    {
        $meta['reason'] = 'SMS provider not configured. Replace send_sms_message with real gateway integration.';
        return false;
    }
}

if (!function_exists('get_message_log')) {
    function get_message_log(PDO $pdo, int $donorId, ?int $sampleId, string $messageKey): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM message_logs WHERE donor_id = ? AND sample_id <=> ? AND message_key = ? LIMIT 1'
        );
        $stmt->execute([$donorId, $sampleId, $messageKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('insert_message_log')) {
    function insert_message_log(PDO $pdo, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $col) => ':' . $col, $columns);
        $sql = 'INSERT INTO message_logs (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        foreach ($data as $col => $value) {
            $stmt->bindValue(':' . $col, $value);
        }
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('update_message_log')) {
    function update_message_log(PDO $pdo, int $id, array $data): int
    {
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = sprintf('`%s` = :%s', str_replace('`', '``', $column), $column);
        }
        $sql = 'UPDATE message_logs SET ' . implode(', ', $assignments) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}

if (!function_exists('send_donor_test_result_notification')) {
    function send_donor_test_result_notification(PDO $pdo, array $params): array
    {
        $donorId = (int)$params['donor_id'];
        $sampleId = isset($params['sample_id']) ? (int)$params['sample_id'] : null;
        $adminId = isset($params['admin_id']) ? (int)$params['admin_id'] : null;
        $adminName = trim((string)($params['admin_name'] ?? ''));
        $messageKey = trim((string)($params['message_key'] ?? ''));
        $messageContent = trim((string)($params['message_content'] ?? ''));
        $subject = trim((string)($params['subject'] ?? 'Blood Sample Test Results'));
        $email = trim((string)($params['email'] ?? ''));
        $phone = trim((string)($params['phone'] ?? ''));
        $channel = in_array($params['channel'] ?? 'both', ['email', 'sms', 'both', 'in_app'], true) ? $params['channel'] : 'both';

        if ($messageKey === '') {
            return ['success' => false, 'message' => 'message_key is required.'];
        }

        $existing = get_message_log($pdo, $donorId, $sampleId, $messageKey);
        if ($existing && $existing['status'] === 'sent') {
            return ['success' => true, 'skipped' => true, 'message' => 'Notification already sent.'];
        }

        $logData = [
            'donor_id' => $donorId,
            'sample_id' => $sampleId,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'message_key' => $messageKey,
            'message_type' => 'sample_test_result',
            'channel' => $channel,
            'message_content' => $messageContent,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $logId = (int)$existing['id'];
            update_message_log($pdo, $logId, $logData);
        } else {
            $logId = insert_message_log($pdo, $logData);
        }

        $sent = false;
        $errors = [];
        if ($channel === 'email' || $channel === 'both') {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emailOk = send_email_message($email, $subject, $messageContent, nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8')));
                if ($emailOk) {
                    $sent = true;
                } else {
                    $errors[] = 'Email send failed.';
                }
            } else {
                $errors[] = 'Invalid donor email.';
            }
        }

        if ($channel === 'sms' || $channel === 'both') {
            if ($phone !== '') {
                $smsMeta = [];
                $smsOk = send_sms_message($phone, $messageContent, $smsMeta);
                if ($smsOk) {
                    $sent = true;
                } else {
                    $errors[] = 'SMS send failed: ' . ($smsMeta['reason'] ?? 'provider not configured');
                }
            } else {
                $errors[] = 'Donor phone number missing.';
            }
        }

        $status = $sent ? 'sent' : 'failed';
        $updateData = [
            'status' => $status,
            'sent_date' => $sent ? date('Y-m-d H:i:s') : null,
            'error_message' => $sent ? null : implode(' ', $errors),
        ];

        update_message_log($pdo, $logId, $updateData);

        return [
            'success' => $sent,
            'skipped' => false,
            'log_id' => $logId,
            'status' => $status,
            'errors' => $errors,
        ];
    }
}
