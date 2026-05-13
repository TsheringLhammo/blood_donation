<?php
declare(strict_types=1);

if (!function_exists('workflow_table_columns')) {
    function workflow_table_columns(PDO $pdo, string $tableName): array
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`');
            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($row['Field'])) {
                    $columns[] = (string)$row['Field'];
                }
            }
            $cache[$tableName] = $columns;
            return $columns;
        } catch (Throwable $exception) {
            $cache[$tableName] = [];
            return [];
        }
    }
}

if (!function_exists('workflow_table_has_column')) {
    function workflow_table_has_column(PDO $pdo, string $tableName, string $columnName): bool
    {
        return in_array($columnName, workflow_table_columns($pdo, $tableName), true);
    }
}

if (!function_exists('workflow_clean_email')) {
    function workflow_clean_email(?string $email): string
    {
        return preg_replace('/\s+/', '', trim((string)$email)) ?? '';
    }
}

if (!function_exists('workflow_first_existing_column')) {
    function workflow_first_existing_column(PDO $pdo, string $tableName, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (workflow_table_has_column($pdo, $tableName, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('workflow_compute_test_result')) {
    function workflow_compute_test_result(array $row): string
    {
        $results = [];
        foreach (['hiv_result', 'hbsag_result', 'hcv_result', 'syphilis_result', 'malaria_result'] as $field) {
            $value = strtolower(trim((string)($row[$field] ?? '')));
            if ($value !== '') {
                $results[] = $value;
            }
        }

        if (empty($results)) {
            return 'not_tested';
        }

        foreach ($results as $value) {
            if (in_array($value, ['positive', 'reactive'], true)) {
                return 'positive';
            }
        }

        foreach ($results as $value) {
            if (in_array($value, ['negative', 'non-reactive', 'non reactive'], true)) {
                return 'negative';
            }
        }

        return 'inconclusive';
    }
}

if (!function_exists('workflow_normalize_workflow_status')) {
    function workflow_normalize_workflow_status(array $row): string
    {
        $workflowStatus = strtolower(trim((string)($row['workflow_status'] ?? '')));
        if ($workflowStatus !== '') {
            return $workflowStatus;
        }

        $initial = strtolower(trim((string)($row['initial_approval_status'] ?? '')));
        $final = strtolower(trim((string)($row['final_decision'] ?? '')));
        $approval = strtolower(trim((string)($row['status'] ?? '')));
        $testResult = workflow_compute_test_result($row);
        $sampleTested = strtolower(trim((string)($row['sample_tested'] ?? '')));
        $eligibility = strtolower(trim((string)($row['eligibility'] ?? '')));

        if ($initial === 'pending' || $approval === 'pending') {
            return 'pending_initial_approval';
        }

        if ($initial === 'rejected' || $approval === 'initially rejected') {
            return 'initially_rejected';
        }

        if ($initial === 'approved' && $testResult === 'not_tested') {
            return 'approved_for_blood_draw';
        }

        if (in_array($final, ['accepted', 'temp_defer', 'perm_defer', 'retest'], true)) {
            return match ($final) {
                'accepted' => 'active_donor',
                'temp_defer' => 'deferred_until_date',
                'perm_defer' => 'permanently_deferred',
                'retest' => 'retest_requested',
            };
        }

        if (
            $testResult === 'negative' ||
            $sampleTested === 'negative' ||
            in_array($eligibility, ['eligible', 'approved'], true)
        ) {
            return 'approved_donor';
        }

        if ($testResult !== 'not_tested') {
            return 'blood_drawn_awaiting_test_result';
        }

        return 'awaiting_workflow_review';
    }
}

if (!function_exists('workflow_insert_notification')) {
    function workflow_insert_notification(PDO $pdo, array $data): int
    {
        $columns = workflow_table_columns($pdo, 'tblnotifications');
        if (empty($columns)) {
            return 0;
        }

        $payload = [];
        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $value;
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($payload as $column => $value) {
            $fields[] = '`' . str_replace('`', '``', $column) . '`';
            $placeholder = ':' . preg_replace('/[^A-Za-z0-9_]/', '_', $column);
            $placeholders[] = $placeholder;
            $values[$placeholder] = $value;
        }

        $sql = 'INSERT INTO tblnotifications (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        return (int)$pdo->lastInsertId();
    }
}
