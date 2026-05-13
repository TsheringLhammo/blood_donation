<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

function json_or_cli(array $payload, int $statusCode = 200): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }

    $encoded = json_encode($payload, JSON_PRETTY_PRINT);
    if ($encoded === false) {
        $encoded = '{"success":false,"message":"Failed to encode output."}';
    }

    echo $encoded;
    if (PHP_SAPI === 'cli') {
        echo PHP_EOL;
    }
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function map_component_column_to_name(string $inventoryColumn): string
{
    if ($inventoryColumn === 'whole_units') {
        return 'Whole Blood';
    }
    if ($inventoryColumn === 'prbc_units') {
        return 'PRBC';
    }
    if ($inventoryColumn === 'platelets_units') {
        return 'Platelets';
    }

    return 'FFP';
}

function get_inventory_plasma_column(PDO $pdo): string
{
    if (table_has_column($pdo, 'tblinventory', 'fpp_units')) {
        return 'fpp_units';
    }
    if (table_has_column($pdo, 'tblinventory', 'ffp_units')) {
        return 'ffp_units';
    }

    throw new RuntimeException('tblinventory must contain either fpp_units or ffp_units.');
}

function has_unique_index(PDO $pdo, string $table, string $column): bool
{
    $sql = 'SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND column_name = :column_name
              AND non_unique = 0
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (bool)$stmt->fetchColumn();
}

function build_insert_statement(PDO $pdo, bool $hasUnitId, bool $hasDonationId): PDOStatement
{
    $columns = [];
    $values = [];

    if ($hasUnitId) {
        $columns[] = 'unit_id';
        $values[] = ':unit_id';
    }
    if ($hasDonationId) {
        $columns[] = 'donation_id';
        $values[] = ':donation_id';
    }

    $columns[] = 'blood_bank_id';
    $values[] = ':blood_bank_id';
    $columns[] = 'blood_type';
    $values[] = ':blood_type';
    $columns[] = 'component';
    $values[] = ':component';
    $columns[] = 'expiry_date';
    $values[] = ':expiry_date';
    $columns[] = 'status';
    $values[] = ':status';

    if (table_has_column($pdo, 'tblblood_units', 'request_id')) {
        $columns[] = 'request_id';
        $values[] = 'NULL';
    }
    if (table_has_column($pdo, 'tblblood_units', 'created_at')) {
        $columns[] = 'created_at';
        $values[] = 'CURRENT_TIMESTAMP';
    }
    if (table_has_column($pdo, 'tblblood_units', 'updated_at')) {
        $columns[] = 'updated_at';
        $values[] = 'CURRENT_TIMESTAMP';
    }

    $sql = 'INSERT INTO tblblood_units (' . implode(', ', $columns) . ')
            VALUES (' . implode(', ', $values) . ')';

    return $pdo->prepare($sql);
}

function get_next_sequence(PDO $pdo, string $columnName, string $prefix): int
{
    $stmt = $pdo->prepare(
        'SELECT MAX(CAST(SUBSTRING(' . $columnName . ', 8) AS UNSIGNED))
         FROM tblblood_units
         WHERE ' . $columnName . ' LIKE ?'
    );
    $stmt->execute([$prefix . '%']);

    return (int)$stmt->fetchColumn();
}

try {
    $hasUnitId = table_has_column($pdo, 'tblblood_units', 'unit_id');
    $hasDonationId = table_has_column($pdo, 'tblblood_units', 'donation_id');
    if (!$hasUnitId && !$hasDonationId) {
        throw new RuntimeException('tblblood_units must contain unit_id or donation_id column.');
    }

    $currentYear = date('Y');
    $unitPrefix = 'U-' . $currentYear . '-';
    $donationPrefix = 'D-' . $currentYear . '-';

    $unitSeq = $hasUnitId ? get_next_sequence($pdo, 'unit_id', $unitPrefix) : 0;
    $donationSeq = $hasDonationId ? get_next_sequence($pdo, 'donation_id', $donationPrefix) : 0;

    $plasmaColumn = get_inventory_plasma_column($pdo);

    $inventoryStmt = $pdo->query(
        'SELECT blood_bank_id,
                blood_type,
                COALESCE(SUM(whole_units), 0) AS whole_units,
                COALESCE(SUM(prbc_units), 0) AS prbc_units,
                COALESCE(SUM(platelets_units), 0) AS platelets_units,
                COALESCE(SUM(' . $plasmaColumn . '), 0) AS plasma_units
         FROM tblinventory
         GROUP BY blood_bank_id, blood_type
         ORDER BY blood_bank_id, blood_type'
    );
    $inventoryRows = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$inventoryRows) {
        json_or_cli(['success' => true, 'message' => 'No rows in tblinventory. Nothing to populate.', 'inserted' => 0]);
        exit;
    }

    $componentColumns = ['whole_units', 'prbc_units', 'platelets_units', 'plasma_units'];
    $expiryDate = (new DateTimeImmutable('+6 months'))->format('Y-m-d');
    $insertStmt = build_insert_statement($pdo, $hasUnitId, $hasDonationId);

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM tblblood_units
         WHERE blood_bank_id = :blood_bank_id
           AND blood_type = :blood_type
           AND component = :component'
    );

    $enforceUniqueUnit = $hasUnitId && has_unique_index($pdo, 'tblblood_units', 'unit_id');
    $enforceUniqueDonation = $hasDonationId && has_unique_index($pdo, 'tblblood_units', 'donation_id');

    $pdo->beginTransaction();
    $inserted = 0;
    $skipped = 0;
    $details = [];

    foreach ($inventoryRows as $row) {
        $bloodBankId = (int)$row['blood_bank_id'];
        $bloodType = trim((string)$row['blood_type']);

        foreach ($componentColumns as $componentColumn) {
            $targetCount = (int)($row[$componentColumn] ?? 0);
            if ($targetCount <= 0) {
                continue;
            }

            $componentName = map_component_column_to_name($componentColumn);

            $countStmt->execute([
                ':blood_bank_id' => $bloodBankId,
                ':blood_type' => $bloodType,
                ':component' => $componentName,
            ]);
            $existingCount = (int)$countStmt->fetchColumn();
            $toInsert = max(0, $targetCount - $existingCount);

            if ($toInsert === 0) {
                $skipped += $targetCount;
                continue;
            }

            for ($i = 0; $i < $toInsert; $i++) {
                $payload = [
                    ':blood_bank_id' => $bloodBankId,
                    ':blood_type' => $bloodType,
                    ':component' => $componentName,
                    ':expiry_date' => $expiryDate,
                    ':status' => 'Available',
                ];

                if ($hasUnitId) {
                    do {
                        $unitSeq++;
                        $unitId = sprintf('%s%05d', $unitPrefix, $unitSeq);
                        if (!$enforceUniqueUnit) {
                            break;
                        }
                        $checkUnitStmt = $pdo->prepare('SELECT 1 FROM tblblood_units WHERE unit_id = ? LIMIT 1');
                        $checkUnitStmt->execute([$unitId]);
                    } while ((bool)$checkUnitStmt->fetchColumn());
                    $payload[':unit_id'] = $unitId;
                }

                if ($hasDonationId) {
                    do {
                        $donationSeq++;
                        $donationId = sprintf('%s%05d', $donationPrefix, $donationSeq);
                        if (!$enforceUniqueDonation) {
                            break;
                        }
                        $checkDonationStmt = $pdo->prepare('SELECT 1 FROM tblblood_units WHERE donation_id = ? LIMIT 1');
                        $checkDonationStmt->execute([$donationId]);
                    } while ((bool)$checkDonationStmt->fetchColumn());
                    $payload[':donation_id'] = $donationId;
                }

                $insertStmt->execute($payload);
                $inserted++;
            }

            $details[] = [
                'blood_bank_id' => $bloodBankId,
                'blood_type' => $bloodType,
                'component' => $componentName,
                'target_count' => $targetCount,
                'already_present' => $existingCount,
                'inserted_now' => $toInsert,
            ];
        }
    }

    $pdo->commit();

    json_or_cli([
        'success' => true,
        'message' => 'Population completed. Missing unit rows were inserted from tblinventory.',
        'inserted' => $inserted,
        'skipped' => $skipped,
        'usesColumns' => [
            'unit_id' => $hasUnitId,
            'donation_id' => $hasDonationId,
            'inventory_plasma_column' => $plasmaColumn,
        ],
        'prefixes' => [
            'unit_id' => $hasUnitId ? $unitPrefix : null,
            'donation_id' => $hasDonationId ? $donationPrefix : null,
        ],
        'expiryDateUsed' => $expiryDate,
        'details' => $details,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_or_cli([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
