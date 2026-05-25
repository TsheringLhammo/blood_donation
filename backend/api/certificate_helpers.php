<?php
declare(strict_types=1);

if (!function_exists('bts_table_exists')) {
    function bts_table_exists(PDO $pdo, string $tableName): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$tableName]);
            return (bool)$stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('bts_column_exists')) {
    function bts_column_exists(PDO $pdo, string $tableName, string $columnName): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
            $stmt->execute([$columnName]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('bts_certificate_milestones')) {
    function bts_certificate_milestones(): array
    {
        return [
            ['key' => 'milestone_5', 'threshold' => 5, 'type' => 'Certificate of Appreciation'],
            ['key' => 'milestone_10', 'threshold' => 10, 'type' => 'Recognition Certificate'],
            ['key' => 'milestone_20', 'threshold' => 20, 'type' => 'Lifetime Honor Certificate'],
        ];
    }
}

if (!function_exists('bts_ensure_certificate_table')) {
    function bts_ensure_certificate_table(PDO $pdo): void
    {
        if (bts_table_exists($pdo, 'donor_certificates')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS donor_certificates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                donor_id INT UNSIGNED NOT NULL,
                donation_history_id INT UNSIGNED NULL,
                milestone_key VARCHAR(32) NOT NULL,
                certificate_type VARCHAR(80) NOT NULL,
                milestone_threshold SMALLINT UNSIGNED NOT NULL,
                total_completed_donations SMALLINT UNSIGNED NOT NULL,
                certificate_number VARCHAR(40) NOT NULL,
                donor_name VARCHAR(160) NULL,
                cid VARCHAR(40) NULL,
                blood_type VARCHAR(10) NULL,
                issue_date DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Issued',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_donor_milestone (donor_id, milestone_key),
                INDEX idx_donor_certificates_donor (donor_id),
                INDEX idx_donor_certificates_threshold (milestone_threshold),
                INDEX idx_donor_certificates_issue_date (issue_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('bts_donor_name_column')) {
    function bts_donor_name_column(PDO $pdo): string
    {
        return bts_column_exists($pdo, 'tbldonors', 'full_name') ? 'full_name' : 'name';
    }
}

if (!function_exists('bts_donor_cid_column')) {
    function bts_donor_cid_column(PDO $pdo): string
    {
        return bts_column_exists($pdo, 'tbldonors', 'cid') ? 'cid' : (bts_column_exists($pdo, 'tbldonors', 'citizen_id') ? 'citizen_id' : 'cid_number');
    }
}

if (!function_exists('bts_completed_donation_count')) {
    function bts_completed_donation_count(PDO $pdo, int $donorId): int
    {
        if ($donorId <= 0 || !bts_table_exists($pdo, 'donation_history')) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donation_history WHERE donor_id = ? AND LOWER(TRIM(COALESCE(status, ""))) = "completed"');
            $stmt->execute([$donorId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}

if (!function_exists('bts_certificate_number_for')) {
    function bts_certificate_number_for(string $milestoneKey, int $donorId, int $completedCount): string
    {
        $prefixMap = [
            'milestone_5' => 'APP',
            'milestone_10' => 'REC',
            'milestone_20' => 'LIF',
        ];

        $prefix = $prefixMap[$milestoneKey] ?? 'CERT';
        return sprintf('%s-%s-%05d-%03d', $prefix, date('Y'), $donorId, $completedCount);
    }
}

if (!function_exists('bts_issue_donor_milestone_certificates')) {
    function bts_issue_donor_milestone_certificates(PDO $pdo, int $donorId, ?int $triggerDonationHistoryId = null): array
    {
        if ($donorId <= 0) {
            return [];
        }

        bts_ensure_certificate_table($pdo);
        if (!bts_table_exists($pdo, 'donor_certificates')) {
            return [];
        }

        $donorNameColumn = bts_donor_name_column($pdo);
        $cidColumn = bts_donor_cid_column($pdo);

        $donorStmt = $pdo->prepare('SELECT id, ' . $donorNameColumn . ' AS donor_name, ' . $cidColumn . ' AS cid, blood_type FROM tbldonors WHERE id = ? LIMIT 1');
        $donorStmt->execute([$donorId]);
        $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$donor) {
            return [];
        }

        $completedCount = bts_completed_donation_count($pdo, $donorId);
        if ($completedCount < 5) {
            return [];
        }

        $issued = [];
        foreach (bts_certificate_milestones() as $milestone) {
            if ($completedCount < (int)$milestone['threshold']) {
                continue;
            }

            $checkStmt = $pdo->prepare('SELECT id FROM donor_certificates WHERE donor_id = ? AND milestone_key = ? LIMIT 1');
            $checkStmt->execute([$donorId, $milestone['key']]);
            if ($checkStmt->fetchColumn()) {
                continue;
            }

            if ($triggerDonationHistoryId === null) {
                $historyStmt = $pdo->prepare('SELECT id FROM donation_history WHERE donor_id = ? AND LOWER(TRIM(COALESCE(status, ""))) = "completed" ORDER BY donation_date DESC, id DESC LIMIT 1');
                $historyStmt->execute([$donorId]);
                $triggerDonationHistoryId = (int)($historyStmt->fetchColumn() ?: 0);
            }

            $certificateNumber = bts_certificate_number_for((string)$milestone['key'], $donorId, $completedCount);
            $insertStmt = $pdo->prepare(
                'INSERT INTO donor_certificates (
                    donor_id,
                    donation_history_id,
                    milestone_key,
                    certificate_type,
                    milestone_threshold,
                    total_completed_donations,
                    certificate_number,
                    donor_name,
                    cid,
                    blood_type,
                    issue_date,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), "Issued")'
            );
            $insertStmt->execute([
                $donorId,
                $triggerDonationHistoryId > 0 ? $triggerDonationHistoryId : null,
                $milestone['key'],
                $milestone['type'],
                (int)$milestone['threshold'],
                $completedCount,
                $certificateNumber,
                trim((string)($donor['donor_name'] ?? '')) !== '' ? trim((string)$donor['donor_name']) : null,
                trim((string)($donor['cid'] ?? '')) !== '' ? trim((string)$donor['cid']) : null,
                trim((string)($donor['blood_type'] ?? '')) !== '' ? trim((string)$donor['blood_type']) : null,
            ]);

            $issued[] = [
                'milestone_key' => $milestone['key'],
                'certificate_type' => $milestone['type'],
                'certificate_number' => $certificateNumber,
                'total_completed_donations' => $completedCount,
            ];
        }

        return $issued;
    }
}

if (!function_exists('bts_sync_all_milestone_certificates')) {
    function bts_sync_all_milestone_certificates(PDO $pdo): int
    {
        if (!bts_table_exists($pdo, 'donation_history')) {
            return 0;
        }

        $stmt = $pdo->query('SELECT DISTINCT donor_id FROM donation_history WHERE donor_id IS NOT NULL AND donor_id > 0 AND LOWER(TRIM(COALESCE(status, ""))) = "completed"');
        $donorIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $issuedCount = 0;
        foreach ($donorIds as $donorId) {
            $issuedCount += count(bts_issue_donor_milestone_certificates($pdo, (int)$donorId));
        }

        return $issuedCount;
    }
}
