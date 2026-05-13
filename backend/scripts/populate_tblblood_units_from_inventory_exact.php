<?php
/**
 * ============================================================================
 * Blood Units Population Script - tblblood_units Filler
 * ============================================================================
 * 
 * PURPOSE:
 *   Populates tblblood_units table from your existing aggregated inventory
 *   (tblinventory). Creates one row in tblblood_units for every individual
 *   blood bag represented in your inventory counts.
 * 
 * WHAT IT DOES:
 *   1. Validates that tblinventory and tblblood_units have required columns
 *   2. Queries tblinventory grouped by blood_bank_id + blood_type
 *   3. For each combination and component type:
 *      - Counts existing Available units
 *      - Calculates how many new units to create
 *      - Inserts new rows with incremented unit_id values
 *   4. Returns JSON with inserted count and per-component breakdown
 * 
 * SCHEMA MAPPING:
 *   ┌─ tblinventory (source) ──────────────────────────────────────┐
 *   │ blood_bank_id (1 if NULL/0)                                  │
 *   │ blood_type (e.g., O+, AB-, A-)                               │
 *   │ whole_units → component='Whole Blood'                        │
 *   │ prbc_units → component='PRBC'                                │
 *   │ platelets_units → component='Platelets'                      │
 *   │ fpp_units → component='FFP'                                  │
 *   └──────────────────────────────────────────────────────────────┘
 *                              ↓
 *   ┌─ tblblood_units (target) ────────────────────────────────────┐
 *   │ id (auto-increment PK)                                       │
 *   │ unit_id (UNIQUE, e.g., U-2026-00001) ← auto-incremented     │
 *   │ donation_id (same as unit_id for import)                     │
 *   │ blood_bank_id, blood_type, component, expiry_date            │
 *   │ status = 'Available'                                         │
 *   │ Other: request_id (NULL), created_at, updated_at             │
 *   └──────────────────────────────────────────────────────────────┘
 * 
 * EXAMPLE:
 *   If tblinventory has: blood_bank_id=1, blood_type=O+, 
 *     whole_units=2, prbc_units=3, platelets_units=0, fpp_units=1
 *   
 *   This script creates 6 rows in tblblood_units:
 *     - U-2026-00001 (Whole Blood)
 *     - U-2026-00002 (Whole Blood)
 *     - U-2026-00003 (PRBC)
 *     - U-2026-00004 (PRBC)
 *     - U-2026-00005 (PRBC)
 *     - U-2026-00006 (FFP)
 * 
 * HOW TO RUN:
 *   Option A: Browser (direct)
 *     1. Open: http://localhost/blood_donation/backend/scripts/populate_tblblood_units_from_inventory_exact.php
 *     2. Optional: append ?year=2026 to customize year prefix (default: 2026)
 *     3. View JSON output
 * 
 *   Option B: Command line
 *     "C:\xampp\php\php.exe" "backend/scripts/populate_tblblood_units_from_inventory_exact.php?year=2026"
 * 
 *   Option C: Via curl
 *     curl http://localhost/blood_donation/backend/scripts/populate_tblblood_units_from_inventory_exact.php
 * 
 * RESPONSE (JSON):
 *   Success example:
 *   {
 *     "success": true,
 *     "message": "tblblood_units population completed successfully.",
 *     "year_prefix": "U-2026-",
 *     "inserted": 6,
 *     "details": [
 *       {
 *         "blood_bank_id": 1,
 *         "blood_type": "O+",
 *         "component": "Whole Blood",
 *         "target_count_from_inventory": 2,
 *         "existing_available_before_insert": 0,
 *         "inserted_now": 2
 *       },
 *       ...
 *     ]
 *   }
 * 
 *   Error example:
 *   {
 *     "success": false,
 *     "message": "Missing tblinventory column: fpp_units"
 *   }
 * 
 * VERIFICATION:
 *   After successful run, check in phpMyAdmin:
 *   
 *   SELECT COUNT(*) FROM tblblood_units;
 *   
 *   Should return the value in "inserted" from JSON response.
 * 
 * TROUBLESHOOTING:
 *   - "Missing column" error → Check your exact column names in tblinventory/tblblood_units
 *   - "No inventory rows found" → Your tblinventory is empty or has no valid blood_type values
 *   - "inserted": 0 → Inventory might have 0 units for all types
 *   - "No rows created" → All required components might be 0 in inventory
 * 
 * INCREMENTAL SAFETY:
 *   This script is safe to run multiple times:
 *   - Checks how many Available units of each type already exist
 *   - Only creates (target_count - existing_count) new units
 *   - Won't create duplicates on subsequent runs
 * 
 * NOTES:
 *   - Unit IDs increment sequentially (U-2026-00001, U-2026-00002, etc.)
 *   - Expiry date defaults to 6 months from today
 *   - Requires database connection (configured in backend/config/db.php)
 *   - Transactions ensure all-or-nothing insertion
 * 
 * ============================================================================
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function fail(string $message, int $status = 400, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => false,
        'message' => $message,
    ], $extra), JSON_PRETTY_PRINT);
    exit;
}

function ok(array $payload): void
{
    echo json_encode(array_merge(['success' => true], $payload), JSON_PRETTY_PRINT);
    exit;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    // Validate exact required columns before running inserts.
    $requiredInventoryColumns = ['blood_bank_id', 'blood_type', 'whole_units', 'prbc_units', 'platelets_units', 'fpp_units'];
    foreach ($requiredInventoryColumns as $column) {
        if (!table_has_column($pdo, 'tblinventory', $column)) {
            fail('Missing tblinventory column: ' . $column, 422);
        }
    }

    $requiredUnitColumns = ['unit_id', 'donation_id', 'blood_bank_id', 'blood_type', 'component', 'expiry_date', 'status', 'request_id'];
    foreach ($requiredUnitColumns as $column) {
        if (!table_has_column($pdo, 'tblblood_units', $column)) {
            fail('Missing tblblood_units column: ' . $column, 422);
        }
    }

    $year = (int)($_GET['year'] ?? 2026);
    if ($year < 2000 || $year > 9999) {
        $year = 2026;
    }
    $prefix = 'U-' . $year . '-';

    $maxSeqStmt = $pdo->prepare(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(unit_id, 8) AS UNSIGNED)), 0) AS max_seq
         FROM tblblood_units
         WHERE unit_id LIKE ?'
    );
    $maxSeqStmt->execute([$prefix . '%']);
    $sequence = (int)$maxSeqStmt->fetchColumn();

    $inventoryStmt = $pdo->query(
        'SELECT
            COALESCE(NULLIF(blood_bank_id, 0), 1) AS blood_bank_id,
            blood_type,
            COALESCE(SUM(whole_units), 0) AS whole_units,
            COALESCE(SUM(prbc_units), 0) AS prbc_units,
            COALESCE(SUM(platelets_units), 0) AS platelets_units,
            COALESCE(SUM(fpp_units), 0) AS fpp_units
         FROM tblinventory
         GROUP BY COALESCE(NULLIF(blood_bank_id, 0), 1), blood_type
         ORDER BY COALESCE(NULLIF(blood_bank_id, 0), 1), blood_type'
    );
    $inventoryRows = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$inventoryRows) {
        ok([
            'message' => 'No inventory rows found. Nothing inserted.',
            'inserted' => 0,
            'details' => [],
        ]);
    }

    $countAvailableStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM tblblood_units
         WHERE blood_bank_id = :blood_bank_id
           AND blood_type = :blood_type
           AND component = :component
           AND status = "Available"'
    );

    $insertStmt = $pdo->prepare(
        'INSERT INTO tblblood_units
            (unit_id, donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id, created_at, updated_at)
         VALUES
            (:unit_id, :donation_id, :blood_bank_id, :blood_type, :component, :expiry_date, :status, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
    );

    $componentMap = [
        'whole_units' => 'Whole Blood',
        'prbc_units' => 'PRBC',
        'platelets_units' => 'Platelets',
        'fpp_units' => 'FFP',
    ];

    $pdo->beginTransaction();

    $inserted = 0;
    $details = [];

    foreach ($inventoryRows as $row) {
        $bloodBankId = (int)$row['blood_bank_id'];
        $bloodType = trim((string)$row['blood_type']);

        if ($bloodType === '') {
            continue;
        }

        foreach ($componentMap as $inventoryCol => $componentLabel) {
            $targetCount = (int)($row[$inventoryCol] ?? 0);
            if ($targetCount <= 0) {
                continue;
            }

            $countAvailableStmt->execute([
                ':blood_bank_id' => $bloodBankId,
                ':blood_type' => $bloodType,
                ':component' => $componentLabel,
            ]);
            $existingAvailable = (int)$countAvailableStmt->fetchColumn();
            $toInsert = max(0, $targetCount - $existingAvailable);

            for ($i = 0; $i < $toInsert; $i++) {
                $sequence++;
                $unitId = sprintf('%s%05d', $prefix, $sequence);

                $insertStmt->execute([
                    ':unit_id' => $unitId,
                    ':donation_id' => $unitId,
                    ':blood_bank_id' => $bloodBankId > 0 ? $bloodBankId : 1,
                    ':blood_type' => $bloodType,
                    ':component' => $componentLabel,
                    ':expiry_date' => (new DateTimeImmutable('+6 months'))->format('Y-m-d'),
                    ':status' => 'Available',
                ]);
                $inserted++;
            }

            $details[] = [
                'blood_bank_id' => $bloodBankId,
                'blood_type' => $bloodType,
                'component' => $componentLabel,
                'target_count_from_inventory' => $targetCount,
                'existing_available_before_insert' => $existingAvailable,
                'inserted_now' => $toInsert,
            ];
        }
    }

    $pdo->commit();

    ok([
        'message' => 'tblblood_units population completed successfully.',
        'year_prefix' => $prefix,
        'inserted' => $inserted,
        'details' => $details,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Population failed: ' . $e->getMessage(), 500);
}
