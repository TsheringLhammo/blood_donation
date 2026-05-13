<?php
declare(strict_types=1);

if (!function_exists('bts_normalize_request_status')) {
    function bts_normalize_request_status(string $status): string
    {
        $normalized = strtolower(trim($status));

        if (in_array($normalized, ['cross-match', 'crossmatch'], true)) {
            return 'cross-matching';
        }
        if (in_array($normalized, ['cross-match complete', 'crossmatch complete', 'ready to issue'], true)) {
            return 'matched';
        }
        if ($normalized === 'cross-match failed') {
            return 'rejected';
        }

        return $normalized;
    }
}

if (!function_exists('bts_can_transition_request_status')) {
    function bts_can_transition_request_status(string $fromStatus, string $toStatus): bool
    {
        $from = bts_normalize_request_status($fromStatus);
        $to = bts_normalize_request_status($toStatus);

        if ($from === $to) {
            return true;
        }

        $allowed = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['cross-matching', 'rejected'],
            'cross-matching' => ['matched', 'rejected'],
            'matched' => ['issued', 'rejected'],
            'rejected' => [],
            'issued' => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }
}

if (!function_exists('bts_log_request_status_change')) {
    function bts_log_request_status_change(
        PDO $pdo,
        int $requestId,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?int $actorUserId,
        ?string $notes = null
    ): void {
        try {
            // Check if table exists
            $result = $pdo->query("SHOW TABLES LIKE 'tblrequest_status_logs'");
            if (!$result || $result->rowCount() === 0) {
                // Table doesn't exist, skip logging
                return;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO tblrequest_status_logs
                (request_id, from_status, to_status, action, notes, actor_user_id)
                VALUES (:request_id, :from_status, :to_status, :action, :notes, :actor_user_id)'
            );

            $stmt->execute([
                ':request_id' => $requestId,
                ':from_status' => $fromStatus,
                ':to_status' => $toStatus,
                ':action' => $action,
                ':notes' => $notes,
                ':actor_user_id' => $actorUserId,
            ]);
        } catch (Throwable $e) {
            // Logging failed, but don't break the status update
        }
    }
}
