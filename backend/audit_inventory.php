<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function get_inventory_plasma_column(PDO $pdo): string
{
    if (table_has_column($pdo, 'tblinventory', 'fpp_units')) {
        return 'fpp_units';
    }
    if (table_has_column($pdo, 'tblinventory', 'ffp_units')) {
        return 'ffp_units';
    }

    throw new RuntimeException('tblinventory must include fpp_units or ffp_units.');
}

$rows = [];
$error = '';
$summary = [
    'totalRows' => 0,
    'mismatchRows' => 0,
    'matchedRows' => 0,
];

try {
    $plasmaColumn = get_inventory_plasma_column($pdo);

    $inventorySql = 'SELECT
                        blood_bank_id,
                        blood_type,
                        COALESCE(SUM(whole_units), 0) AS whole_units,
                        COALESCE(SUM(prbc_units), 0) AS prbc_units,
                        COALESCE(SUM(platelets_units), 0) AS platelets_units,
                        COALESCE(SUM(' . $plasmaColumn . '), 0) AS plasma_units
                     FROM tblinventory
                     GROUP BY blood_bank_id, blood_type';
    $inventoryStmt = $pdo->query($inventorySql);
    $inventoryData = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unitsSql = "SELECT
                    blood_bank_id,
                    blood_type,
                    SUM(CASE WHEN component = 'Whole Blood' THEN 1 ELSE 0 END) AS whole_units,
                    SUM(CASE WHEN component IN ('PRBC', 'Packed Red Cells') THEN 1 ELSE 0 END) AS prbc_units,
                    SUM(CASE WHEN component = 'Platelets' THEN 1 ELSE 0 END) AS platelets_units,
                    SUM(CASE WHEN component IN ('FFP', 'Plasma', 'Fresh Frozen Plasma') THEN 1 ELSE 0 END) AS plasma_units
                 FROM tblblood_units
                 WHERE status = 'Available'
                 GROUP BY blood_bank_id, blood_type";
    $unitsStmt = $pdo->query($unitsSql);
    $unitsData = $unitsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $inventoryMap = [];
    foreach ($inventoryData as $item) {
        $key = (int)$item['blood_bank_id'] . '|' . (string)$item['blood_type'];
        $inventoryMap[$key] = [
            'whole_units' => (int)$item['whole_units'],
            'prbc_units' => (int)$item['prbc_units'],
            'platelets_units' => (int)$item['platelets_units'],
            'plasma_units' => (int)$item['plasma_units'],
        ];
    }

    $unitsMap = [];
    foreach ($unitsData as $item) {
        $key = (int)$item['blood_bank_id'] . '|' . (string)$item['blood_type'];
        $unitsMap[$key] = [
            'whole_units' => (int)$item['whole_units'],
            'prbc_units' => (int)$item['prbc_units'],
            'platelets_units' => (int)$item['platelets_units'],
            'plasma_units' => (int)$item['plasma_units'],
        ];
    }

    $allKeys = array_values(array_unique(array_merge(array_keys($inventoryMap), array_keys($unitsMap))));
    sort($allKeys);

    $components = [
        'whole_units' => 'Whole Blood',
        'prbc_units' => 'PRBC',
        'platelets_units' => 'Platelets',
        'plasma_units' => 'FFP/Plasma',
    ];

    foreach ($allKeys as $key) {
        [$bankId, $bloodType] = explode('|', $key, 2);
        $inv = $inventoryMap[$key] ?? [
            'whole_units' => 0,
            'prbc_units' => 0,
            'platelets_units' => 0,
            'plasma_units' => 0,
        ];
        $unit = $unitsMap[$key] ?? [
            'whole_units' => 0,
            'prbc_units' => 0,
            'platelets_units' => 0,
            'plasma_units' => 0,
        ];

        foreach ($components as $field => $componentLabel) {
            $inventoryCount = (int)$inv[$field];
            $availableCount = (int)$unit[$field];
            $isMismatch = $inventoryCount !== $availableCount;

            $rows[] = [
                'blood_bank_id' => (int)$bankId,
                'blood_type' => $bloodType,
                'component' => $componentLabel,
                'inventory_count' => $inventoryCount,
                'available_units_count' => $availableCount,
                'is_mismatch' => $isMismatch,
            ];

            $summary['totalRows']++;
            if ($isMismatch) {
                $summary['mismatchRows']++;
            } else {
                $summary['matchedRows']++;
            }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Audit</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            background: #f5f7fb;
            color: #0f172a;
        }
        .wrap {
            max-width: 1180px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
            padding: 20px;
        }
        h1 {
            margin: 0 0 8px;
        }
        .sub {
            margin: 0 0 16px;
            color: #64748b;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }
        .summary-card strong {
            display: block;
            font-size: 20px;
            margin-top: 4px;
        }
        .ok {
            color: #166534;
        }
        .warn {
            color: #991b1b;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        tr.mismatch {
            background: #fff1f2;
        }
        tr.match {
            background: #f0fdf4;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-ok {
            background: #dcfce7;
            color: #166534;
        }
        .badge-bad {
            background: #fee2e2;
            color: #991b1b;
        }
        .foot {
            margin-top: 12px;
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Inventory Audit</h1>
    <p class="sub">Compares aggregated counts in <strong>tblinventory</strong> against available unit-level counts in <strong>tblblood_units</strong>.</p>

    <?php if ($error !== ''): ?>
        <div class="error"><?php echo h($error); ?></div>
    <?php else: ?>
        <div class="summary">
            <div class="summary-card">
                Checked Rows
                <strong><?php echo h((string)$summary['totalRows']); ?></strong>
            </div>
            <div class="summary-card">
                Matches
                <strong class="ok"><?php echo h((string)$summary['matchedRows']); ?></strong>
            </div>
            <div class="summary-card">
                Mismatches
                <strong class="warn"><?php echo h((string)$summary['mismatchRows']); ?></strong>
            </div>
        </div>

        <table>
            <thead>
            <tr>
                <th>Blood Bank ID</th>
                <th>Blood Type</th>
                <th>Component</th>
                <th>tblinventory Count</th>
                <th>Available Units Count</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6">No data found to audit.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr class="<?php echo $row['is_mismatch'] ? 'mismatch' : 'match'; ?>">
                        <td><?php echo h((string)$row['blood_bank_id']); ?></td>
                        <td><?php echo h((string)$row['blood_type']); ?></td>
                        <td><?php echo h((string)$row['component']); ?></td>
                        <td><?php echo h((string)$row['inventory_count']); ?></td>
                        <td><?php echo h((string)$row['available_units_count']); ?></td>
                        <td>
                            <?php if ($row['is_mismatch']): ?>
                                <span class="badge badge-bad">Mismatch</span>
                            <?php else: ?>
                                <span class="badge badge-ok">In Sync</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <p class="foot">Tip: If mismatches exist, run your sync procedure/trigger migration and then rerun the population script.</p>
    <?php endif; ?>
</div>
</body>
</html>
