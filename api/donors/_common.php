<?php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/auth.php';

function bts_donor_records_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $exception) {
        return false;
    }
}

function bts_donor_records_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
}

function bts_donor_records_first_column(PDO $pdo, string $table, array $candidates): ?string
{
    foreach ($candidates as $column) {
        if (bts_donor_records_column_exists($pdo, $table, $column)) {
            return $column;
        }
    }

    return null;
}

function bts_donor_records_pick_donor_table(PDO $pdo): array
{
    foreach (['donors', 'tbldonors'] as $table) {
        if (!bts_donor_records_table_exists($pdo, $table)) {
            continue;
        }

        return [
            'table' => $table,
            'name_column' => bts_donor_records_first_column($pdo, $table, ['full_name', 'name']),
            'cid_column' => bts_donor_records_first_column($pdo, $table, ['cid_number', 'cid']),
            'blood_group_column' => bts_donor_records_first_column($pdo, $table, ['blood_type', 'blood_group']),
            'phone_column' => bts_donor_records_first_column($pdo, $table, ['phone']),
            'email_column' => bts_donor_records_first_column($pdo, $table, ['email']),
            'dob_column' => bts_donor_records_first_column($pdo, $table, ['date_of_birth', 'dob']),
            'gender_column' => bts_donor_records_first_column($pdo, $table, ['gender']),
            'address_column' => bts_donor_records_first_column($pdo, $table, ['address']),
            'workflow_status_column' => bts_donor_records_first_column($pdo, $table, ['workflow_status']),
            'status_column' => bts_donor_records_first_column($pdo, $table, ['status']),
            'deferral_reason_column' => bts_donor_records_first_column($pdo, $table, ['deferral_reason']),
            'deferred_until_column' => bts_donor_records_first_column($pdo, $table, ['deferred_until']),
            'last_donation_column' => bts_donor_records_first_column($pdo, $table, ['last_donation_date', 'last_donation']),
            'weight_column' => bts_donor_records_first_column($pdo, $table, ['weight']),
            'created_at_column' => bts_donor_records_first_column($pdo, $table, ['created_at']),
        ];
    }

    throw new RuntimeException('No donor table found.');
}

function bts_donor_records_pick_history_table(PDO $pdo): ?array
{
    foreach (['donation_history', 'tbldonations'] as $table) {
        if (!bts_donor_records_table_exists($pdo, $table)) {
            continue;
        }

        return [
            'table' => $table,
            'date_column' => bts_donor_records_first_column($pdo, $table, ['donation_date', 'collection_date', 'tested_at', 'created_at']),
            'blood_bank_column' => bts_donor_records_first_column($pdo, $table, ['blood_bank_id']),
            'component_column' => bts_donor_records_first_column($pdo, $table, ['component_type', 'component', 'blood_type']),
            'units_column' => bts_donor_records_first_column($pdo, $table, ['units', 'units_collected']),
            'status_column' => bts_donor_records_first_column($pdo, $table, ['status', 'review_status', 'sample_tested']),
            'staff_id_column' => bts_donor_records_first_column($pdo, $table, ['staff_id', 'tested_by_id', 'approved_by_admin_id']),
            'staff_name_column' => bts_donor_records_first_column($pdo, $table, ['staff_name', 'tested_by', 'approved_by_admin_name', 'technician']),
            'hiv_column' => bts_donor_records_first_column($pdo, $table, ['hiv_result']),
            'hbsag_column' => bts_donor_records_first_column($pdo, $table, ['hbsag_result']),
            'hcv_column' => bts_donor_records_first_column($pdo, $table, ['hcv_result']),
            'syphilis_column' => bts_donor_records_first_column($pdo, $table, ['syphilis_result']),
            'malaria_column' => bts_donor_records_first_column($pdo, $table, ['malaria_result']),
            'notes_column' => bts_donor_records_first_column($pdo, $table, ['notes']),
            'created_at_column' => bts_donor_records_first_column($pdo, $table, ['created_at']),
        ];
    }

    return null;
}

function bts_donor_records_history_row_is_completed(array $row, string $table): bool
{
    $status = strtolower(trim((string)($row['status'] ?? $row['review_status'] ?? $row['final_result'] ?? '')));

    if ($table === 'donation_history') {
        return $status === '' || in_array($status, ['completed'], true);
    }

    if ($table === 'tbldonations') {
        return in_array($status, ['safe', 'stocked'], true);
    }

    return false;
}

function bts_donor_records_mask_cid(?string $cid): string
{
    $digits = preg_replace('/\D+/', '', (string)$cid) ?? '';
    if ($digits === '') {
        return '—';
    }

    if (strlen($digits) <= 4) {
        return $digits;
    }

    return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 4);
}

function bts_donor_records_parse_date_value($value): ?DateTimeImmutable
{
    if ($value === null) {
        return null;
    }

    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }

    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd M Y', 'd-m-Y', 'm/d/Y'];
    foreach ($formats as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $text);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
    }

    try {
        return new DateTimeImmutable($text);
    } catch (Throwable $exception) {
        return null;
    }
}

function bts_donor_records_format_date($value, string $fallback = 'N/A'): string
{
    $parsed = bts_donor_records_parse_date_value($value);
    return $parsed ? $parsed->format('d M Y') : $fallback;
}

function bts_donor_records_days_left(?string $date): ?int
{
    $parsed = bts_donor_records_parse_date_value($date);
    if (!$parsed) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    $diff = $today->diff($parsed);
    return (int)$diff->format('%r%a');
}

function bts_donor_records_status_label(?string $workflowStatus, ?string $status = null): string
{
    $workflow = strtolower(trim((string)$workflowStatus));
    $statusText = strtolower(trim((string)$status));

    if ($workflow === '' && $statusText === '') {
        return 'Pending';
    }

    if (
        str_contains($workflow, 'defer') ||
        str_contains($workflow, 'reject') ||
        str_contains($statusText, 'defer') ||
        str_contains($statusText, 'reject')
    ) {
        return 'Deferred';
    }

    if (
        in_array($workflow, ['approved', 'approved_donor', 'approved_for_blood_draw', 'decision_made_accepted', 'active'], true) ||
        in_array($statusText, ['approved', 'approved donor', 'active'], true) ||
        str_contains($statusText, 'approved')
    ) {
        return 'Active';
    }

    return 'Pending';
}

function bts_donor_records_deferral_type(?string $workflowStatus, ?string $status = null, ?string $deferralReason = null, ?string $deferredUntil = null): string
{
    $workflow = strtolower(trim((string)$workflowStatus));
    $statusText = strtolower(trim((string)$status));
    $reason = strtolower(trim((string)$deferralReason));

    if ($workflow === '' && $statusText === '' && $reason === '' && trim((string)$deferredUntil) === '') {
        return 'None';
    }

    if (
        str_contains($workflow, 'permanent') ||
        str_contains($statusText, 'permanent') ||
        str_contains($reason, 'permanent')
    ) {
        return 'Permanent';
    }

    if (
        str_contains($workflow, 'defer') ||
        str_contains($workflow, 'reject') ||
        str_contains($statusText, 'defer') ||
        str_contains($reason, 'reactive') ||
        trim((string)$deferredUntil) !== ''
    ) {
        return 'Temporary';
    }

    return 'None';
}

function bts_donor_records_normalize_donor(array $row, array $historySummary = []): array
{
    $id = (int)($row['id'] ?? 0);
    $cid = (string)($row['cid_number'] ?? $row['cid'] ?? '');
    $name = trim((string)($row['full_name'] ?? $row['name'] ?? ''));
    $bloodGroup = trim((string)($row['blood_type'] ?? $row['blood_group'] ?? ''));
    $workflowStatus = trim((string)($row['workflow_status'] ?? ''));
    $rawStatus = trim((string)($row['status'] ?? ''));
    $deferralReason = trim((string)($row['deferral_reason'] ?? ''));
    $deferredUntil = trim((string)($row['deferred_until'] ?? ''));
    $lastDonationRaw = $historySummary['last_donation_raw'] ?? ($row['last_donation_date'] ?? $row['last_donation'] ?? null);
    $lastDonationDate = bts_donor_records_parse_date_value($lastDonationRaw);
    $lastDonationDisplay = $lastDonationDate ? $lastDonationDate->format('d M Y') : 'Never';
    $nextEligibleDate = null;

    if ($lastDonationDate && bts_donor_records_deferral_type($workflowStatus, $rawStatus, $deferralReason, $deferredUntil) !== 'Permanent') {
        $nextEligibleDate = $lastDonationDate->modify('+90 days');
    }

    $nextEligibleDisplay = $nextEligibleDate ? $nextEligibleDate->format('d M Y') : 'N/A';

    return [
        'id' => $id,
        'cid' => $cid,
        'cid_masked' => bts_donor_records_mask_cid($cid),
        'name' => $name,
        'blood_group' => $bloodGroup,
        'phone' => trim((string)($row['phone'] ?? '')),
        'email' => trim((string)($row['email'] ?? '')),
        'dob' => trim((string)($row['date_of_birth'] ?? $row['dob'] ?? '')) ?: null,
        'gender' => trim((string)($row['gender'] ?? '')) ?: null,
        'address' => trim((string)($row['address'] ?? '')) ?: null,
        'status' => bts_donor_records_status_label($workflowStatus, $rawStatus),
        'workflow_status' => $workflowStatus !== '' ? $workflowStatus : $rawStatus,
        'status_raw' => $rawStatus,
        'deferral_type' => bts_donor_records_deferral_type($workflowStatus, $rawStatus, $deferralReason, $deferredUntil),
        'deferral_reason' => $deferralReason !== '' ? $deferralReason : null,
        'deferred_until' => $deferredUntil !== '' ? $deferredUntil : null,
        'last_donation_raw' => $lastDonationDate ? $lastDonationDate->format('Y-m-d') : null,
        'last_donation' => $lastDonationDisplay,
        'total_donations' => (int)($historySummary['total_donations'] ?? 0),
        'next_eligible_raw' => $nextEligibleDate ? $nextEligibleDate->format('Y-m-d') : null,
        'next_eligible' => $nextEligibleDisplay,
        'created_at' => trim((string)($row['created_at'] ?? '')) ?: null,
        'weight' => isset($row['weight']) && $row['weight'] !== '' ? (float)$row['weight'] : null,
    ];
}

function bts_donor_records_get_aggregate_history(PDO $pdo): array
{
    $history = bts_donor_records_pick_history_table($pdo);
    if ($history === null) {
        return [];
    }

    $table = $history['table'];
    $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '` ORDER BY donor_id ASC, id ASC';
    $stmt = $pdo->query($sql);
    $summary = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!bts_donor_records_history_row_is_completed($row, $table)) {
            continue;
        }

        $donorId = (int)($row['donor_id'] ?? 0);
        if ($donorId <= 0) {
            continue;
        }

        $dateValue = $row['donation_date'] ?? $row['completed_at'] ?? $row['tested_at'] ?? $row['created_at'] ?? null;
        $parsedDate = bts_donor_records_parse_date_value($dateValue);
        if ($parsedDate === null) {
            continue;
        }

        if (!isset($summary[$donorId])) {
            $summary[$donorId] = [
                'last_donation_raw' => $parsedDate->format('Y-m-d H:i:s'),
                'total_donations' => 1,
            ];
            continue;
        }

        $summary[$donorId]['total_donations']++;
        $existingDate = bts_donor_records_parse_date_value($summary[$donorId]['last_donation_raw'] ?? null);
        if ($existingDate === null || $parsedDate > $existingDate) {
            $summary[$donorId]['last_donation_raw'] = $parsedDate->format('Y-m-d H:i:s');
        }
    }

    return $summary;
}

function bts_donor_records_get_all_donors(PDO $pdo): array
{
    $donorTable = bts_donor_records_pick_donor_table($pdo);
    $orderColumn = bts_donor_records_first_column($pdo, $donorTable['table'], ['created_at']) ?? 'id';
    $sql = 'SELECT * FROM `' . str_replace('`', '``', $donorTable['table']) . '` ORDER BY `' . str_replace('`', '``', $orderColumn) . '` DESC, id DESC';
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $historySummary = bts_donor_records_get_aggregate_history($pdo);

    $normalized = [];
    foreach ($rows as $row) {
        $normalized[] = bts_donor_records_normalize_donor($row, $historySummary[(int)($row['id'] ?? 0)] ?? []);
    }

    return $normalized;
}

function bts_donor_records_filter_rows(array $rows, array $filters): array
{
    $search = strtolower(trim((string)($filters['search'] ?? '')));
    $bloodGroup = trim((string)($filters['blood_group'] ?? ''));
    $status = trim((string)($filters['status'] ?? ''));
    $deferral = trim((string)($filters['deferral'] ?? ''));

    $matchesSearchText = static function (string $needle, string $value): bool {
        $normalizedNeedle = preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($needle))) ?? '';
        $normalizedValue = preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($value))) ?? '';

        if ($normalizedNeedle === '') {
            return true;
        }

        return str_contains($normalizedValue, $normalizedNeedle);
    };

    return array_values(array_filter($rows, static function (array $row) use ($search, $bloodGroup, $status, $deferral): bool {
        $matchesSearch = $search === '' || str_contains(strtolower((string)($row['cid'] ?? '')), $search) || str_contains(strtolower((string)($row['name'] ?? '')), $search) || str_contains(strtolower((string)($row['email'] ?? '')), $search) || str_contains(strtolower((string)($row['phone'] ?? '')), $search) || $matchesSearchText($search, (string)($row['blood_group'] ?? '')) || $matchesSearchText($search, (string)($row['status'] ?? '')) || $matchesSearchText($search, (string)($row['deferral_type'] ?? 'None')) || $matchesSearchText($search, (string)($row['workflow_status'] ?? ''));
        $matchesBloodGroup = $bloodGroup === '' || (string)($row['blood_group'] ?? '') === $bloodGroup;
        $matchesStatus = $status === '' || (string)($row['status'] ?? '') === $status;
        $matchesDeferral = $deferral === '' || (string)($row['deferral_type'] ?? 'None') === $deferral;

        return $matchesSearch && $matchesBloodGroup && $matchesStatus && $matchesDeferral;
    }));
}

function bts_donor_records_paginate_rows(array $rows, int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    return array_slice($rows, $offset, $perPage);
}

function bts_donor_records_get_blood_bank_map(PDO $pdo): array
{
    if (!bts_donor_records_table_exists($pdo, 'tblblood_banks')) {
        return [];
    }

    try {
        $stmt = $pdo->query('SELECT id, name FROM tblblood_banks ORDER BY name ASC');
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id']] = (string)($row['name'] ?? '');
        }

        return $map;
    } catch (Throwable $exception) {
        return [];
    }
}

function bts_donor_records_get_user_map(PDO $pdo): array
{
    if (!bts_donor_records_table_exists($pdo, 'tblusers')) {
        return [];
    }

    try {
        $stmt = $pdo->query('SELECT id, name FROM tblusers ORDER BY name ASC');
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id']] = (string)($row['name'] ?? '');
        }

        return $map;
    } catch (Throwable $exception) {
        return [];
    }
}

function bts_donor_records_get_history_rows(PDO $pdo, int $donorId): array
{
    $history = bts_donor_records_pick_history_table($pdo);
    if ($history === null) {
        return [];
    }

    $table = $history['table'];
    $dateColumn = $history['date_column'] ?? $history['created_at_column'] ?? 'id';
    $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '` WHERE donor_id = ? ORDER BY `' . str_replace('`', '``', $dateColumn) . '` DESC, id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$donorId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_values(array_filter($rows, static fn (array $row): bool => bts_donor_records_history_row_is_completed($row, $table)));
}

function bts_donor_records_get_audit_history(PDO $pdo, int $donorId): array
{
    if (!bts_donor_records_table_exists($pdo, 'tbldonor_audit_log')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT field_name, old_value, new_value, change_reason, changed_by_admin_name, changed_at
             FROM tbldonor_audit_log
             WHERE donor_id = ?
               AND field_name IN ("workflow_status", "deferred_until", "deferral_reason", "status")
             ORDER BY changed_at DESC, id DESC'
        );
        $stmt->execute([$donorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function bts_donor_records_build_view_payload(PDO $pdo, int $donorId): ?array
{
    $donorTable = bts_donor_records_pick_donor_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM `' . str_replace('`', '``', $donorTable['table']) . '` WHERE id = ? LIMIT 1');
    $stmt->execute([$donorId]);
    $donorRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donorRow) {
        return null;
    }

    $historySummary = bts_donor_records_get_aggregate_history($pdo);
    $normalized = bts_donor_records_normalize_donor($donorRow, $historySummary[$donorId] ?? []);
    $historyRows = bts_donor_records_get_history_rows($pdo, $donorId);
    $bloodBanks = bts_donor_records_get_blood_bank_map($pdo);
    $users = bts_donor_records_get_user_map($pdo);

    $donationHistory = [];
    $testResults = [];
    $firstDonation = null;
    $lastDonation = null;

    foreach ($historyRows as $row) {
        $dateValue = $row['donation_date'] ?? $row['collection_date'] ?? $row['tested_at'] ?? $row['created_at'] ?? null;
        $parsedDate = bts_donor_records_parse_date_value($dateValue);
        if ($parsedDate && ($firstDonation === null || $parsedDate < $firstDonation)) {
            $firstDonation = $parsedDate;
        }
        if ($parsedDate && ($lastDonation === null || $parsedDate > $lastDonation)) {
            $lastDonation = $parsedDate;
        }

        $bloodBankName = 'N/A';
        $bloodBankId = isset($row['blood_bank_id']) ? (int)$row['blood_bank_id'] : 0;
        if ($bloodBankId > 0 && isset($bloodBanks[$bloodBankId])) {
            $bloodBankName = $bloodBanks[$bloodBankId];
        } elseif (!empty($row['blood_bank_name'])) {
            $bloodBankName = (string)$row['blood_bank_name'];
        }

        $staffName = '';
        if (!empty($row['staff_name'])) {
            $staffName = (string)$row['staff_name'];
        } elseif (!empty($row['tested_by'])) {
            $staffName = (string)$row['tested_by'];
        } elseif (!empty($row['approved_by_admin_name'])) {
            $staffName = (string)$row['approved_by_admin_name'];
        } elseif (!empty($row['technician'])) {
            $staffName = (string)$row['technician'];
        } elseif (!empty($row['staff_id'])) {
            $staffId = (int)$row['staff_id'];
            $staffName = $users[$staffId] ?? ('Staff #' . $staffId);
        }

        $donationHistory[] = [
            'date' => $parsedDate ? $parsedDate->format('d M Y') : 'N/A',
            'blood_bank' => $bloodBankName,
            'component' => trim((string)($row['component_type'] ?? $row['component'] ?? $row['blood_type'] ?? 'Whole Blood')) ?: 'Whole Blood',
            'units' => isset($row['units']) && $row['units'] !== '' ? (int)$row['units'] : 1,
            'status' => 'Completed',
            'staff' => trim($staffName) !== '' ? trim($staffName) : 'N/A',
        ];

        $hasTestResults = false;
        foreach (['hiv_result', 'hbsag_result', 'hcv_result', 'syphilis_result', 'malaria_result'] as $column) {
            if (!empty($row[$column])) {
                $hasTestResults = true;
                break;
            }
        }

        if ($hasTestResults) {
            $testResults[] = [
                'date' => $parsedDate ? $parsedDate->format('d M Y') : 'N/A',
                'hiv' => (string)($row['hiv_result'] ?? 'N/A'),
                'hbsag' => (string)($row['hbsag_result'] ?? 'N/A'),
                'hcv' => (string)($row['hcv_result'] ?? 'N/A'),
                'syphilis' => (string)($row['syphilis_result'] ?? 'N/A'),
                'malaria' => (string)($row['malaria_result'] ?? 'N/A'),
            ];
        }
    }

    if ($firstDonation === null && !empty($normalized['last_donation_raw'])) {
        $firstDonation = bts_donor_records_parse_date_value($normalized['last_donation_raw']);
    }

    $firstDonationDisplay = $firstDonation ? $firstDonation->format('d M Y') : 'N/A';
    $lastDonationDisplay = $lastDonation ? $lastDonation->format('d M Y') : $normalized['last_donation'];
    $nextEligible = $lastDonation ? $lastDonation->modify('+90 days') : null;
    $daysLeft = $nextEligible ? max(0, bts_donor_records_days_left($nextEligible->format('Y-m-d')) ?? 0) : null;

    $deferralHistory = [];
    foreach (bts_donor_records_get_audit_history($pdo, $donorId) as $row) {
        $deferralHistory[] = [
            'date' => bts_donor_records_format_date($row['changed_at'] ?? null),
            'field' => (string)($row['field_name'] ?? ''),
            'old_value' => trim((string)($row['old_value'] ?? '')),
            'new_value' => trim((string)($row['new_value'] ?? '')),
            'changed_by' => trim((string)($row['changed_by_admin_name'] ?? '')) ?: 'Admin',
            'reason' => trim((string)($row['change_reason'] ?? '')) ?: null,
        ];
    }

    $weightValue = isset($normalized['weight']) && $normalized['weight'] !== null ? number_format((float)$normalized['weight'], 1) . ' kg' : 'N/A';

    $healthChecks = [
        ['label' => 'Hemoglobin', 'value' => 'N/A'],
        ['label' => 'BP', 'value' => 'N/A'],
        ['label' => 'Pulse', 'value' => 'N/A'],
        ['label' => 'Temp', 'value' => 'N/A'],
        ['label' => 'Weight', 'value' => $weightValue],
    ];

    return [
        'basic' => [
            'id' => $normalized['id'],
            'cid' => $normalized['cid'],
            'cid_masked' => $normalized['cid_masked'],
            'name' => $normalized['name'],
            'blood_group' => $normalized['blood_group'],
            'dob' => $normalized['dob'],
            'gender' => $normalized['gender'],
            'email' => $normalized['email'],
            'phone' => $normalized['phone'],
            'address' => $normalized['address'],
            'status' => $normalized['status'],
            'workflow_status' => $normalized['workflow_status'],
            'deferral_type' => $normalized['deferral_type'],
            'deferral_reason' => $normalized['deferral_reason'],
            'deferred_until' => $normalized['deferred_until'],
        ],
        'summary' => [
            'total_donations' => $normalized['total_donations'],
            'first_donation' => $firstDonationDisplay,
            'last_donation' => $lastDonationDisplay,
            'next_eligible_date' => $nextEligible ? $nextEligible->format('d M Y') : 'N/A',
            'days_left' => $daysLeft,
        ],
        'donation_history' => $donationHistory,
        'test_results' => $testResults,
        'health_checks' => $healthChecks,
        'deferral_history' => $deferralHistory,
        'created_at' => $normalized['created_at'],
    ];
}
