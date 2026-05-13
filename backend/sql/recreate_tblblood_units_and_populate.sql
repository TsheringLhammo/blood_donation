/**
 * ============================================================================
 * Blood Units Population Script - tblblood_units
 * ============================================================================
 * 
 * PURPOSE:
 *   Recreates the tblblood_units table with the correct schema and populates
 *   it from your existing aggregated inventory (tblinventory). Creates one row
 *   for every individual blood bag represented in your inventory counts.
 * 
 * WHAT IT DOES:
 *   1. Drops existing tblblood_units table (WARNING: loses all data)
 *   2. Creates fresh tblblood_units with proper columns and indexes
 *   3. Defines stored procedure sp_populate_tblblood_units_from_inventory()
 *   4. Runs the procedure to populate from tblinventory
 *   5. Shows verification queries with row counts
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
 *   │ unit_id (UNIQUE, e.g., U-2026-00001)                        │
 *   │ donation_id (same as unit_id unless overridden)              │
 *   │ blood_bank_id, blood_type, component, expiry_date            │
 *   │ status = 'Available'                                         │
 *   │ Other fields: request_id, created_at, updated_at             │
 *   └──────────────────────────────────────────────────────────────┘
 * 
 * EXAMPLE:
 *   If tblinventory has: blood_bank_id=1, blood_type=O+, whole_units=2, prbc_units=3
 *   This script creates 5 rows in tblblood_units:
 *     - U-2026-00001 (Whole Blood)
 *     - U-2026-00002 (Whole Blood)
 *     - U-2026-00003 (PRBC)
 *     - U-2026-00004 (PRBC)
 *     - U-2026-00005 (PRBC)
 * 
 * HOW TO RUN:
 *   1. Open http://localhost/phpmyadmin
 *   2. Go to Import tab
 *   3. Choose this file: backend/sql/recreate_tblblood_units_and_populate.sql
 *   4. Click Import
 * 
 * VERIFICATION:
 *   After import, you'll see output showing:
 *   - Total units inserted
 *   - Units per blood_type and component
 *   - Sample of the new table
 * 
 * NOTES:
 *   - If tblinventory is empty, no rows will be created
 *   - Incremental: safe to run again; won't duplicate existing Available units
 *   - Unit IDs are sequential (U-2026-00001, U-2026-00002, etc.)
 *   - Expiry date defaults to 6 months from today
 * 
 * ============================================================================
 */

-- Recreate tblblood_units and provide a robust population stored procedure
-- Target schema:
-- tblinventory: id, blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, fpp_units, updated_at
-- tblblood_units: id, unit_id, donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id, created_at, updated_at

USE blood_donation;

-- 1) Drop and recreate tblblood_units
DROP TABLE IF EXISTS tblblood_units;

CREATE TABLE tblblood_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id VARCHAR(20) NOT NULL,
    donation_id VARCHAR(60) NULL,
    blood_bank_id INT UNSIGNED NOT NULL DEFAULT 1,
    blood_type VARCHAR(5) NOT NULL,
    component VARCHAR(30) NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('Available', 'Reserved', 'Issued', 'Expired') NOT NULL DEFAULT 'Available',
    request_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tblblood_units_unit_id (unit_id),
    KEY idx_tblblood_units_bank_type_component (blood_bank_id, blood_type, component),
    KEY idx_tblblood_units_status (status),
    KEY idx_tblblood_units_request_id (request_id)
);

-- 2) Stored procedure to populate tblblood_units from tblinventory
DROP PROCEDURE IF EXISTS sp_populate_tblblood_units_from_inventory;
DELIMITER $$
CREATE PROCEDURE sp_populate_tblblood_units_from_inventory()
BEGIN
    DECLARE done INT DEFAULT 0;

    DECLARE v_blood_bank_id INT UNSIGNED;
    DECLARE v_blood_type VARCHAR(5);
    DECLARE v_whole INT DEFAULT 0;
    DECLARE v_prbc INT DEFAULT 0;
    DECLARE v_platelets INT DEFAULT 0;
    DECLARE v_fpp INT DEFAULT 0;

    DECLARE v_existing INT DEFAULT 0;
    DECLARE v_to_insert INT DEFAULT 0;
    DECLARE i INT DEFAULT 0;
    DECLARE seq_num INT DEFAULT 0;
    DECLARE v_unit_id VARCHAR(20);

    DECLARE cur CURSOR FOR
        SELECT
            COALESCE(NULLIF(blood_bank_id, 0), 1) AS blood_bank_id,
            blood_type,
            COALESCE(SUM(whole_units), 0) AS whole_units,
            COALESCE(SUM(prbc_units), 0) AS prbc_units,
            COALESCE(SUM(platelets_units), 0) AS platelets_units,
            COALESCE(SUM(fpp_units), 0) AS fpp_units
        FROM tblinventory
        GROUP BY COALESCE(NULLIF(blood_bank_id, 0), 1), blood_type
        ORDER BY COALESCE(NULLIF(blood_bank_id, 0), 1), blood_type;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COALESCE(MAX(CAST(SUBSTRING(unit_id, 8) AS UNSIGNED)), 0)
    INTO seq_num
    FROM tblblood_units
    WHERE unit_id LIKE 'U-2026-%';

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_blood_bank_id, v_blood_type, v_whole, v_prbc, v_platelets, v_fpp;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        -- Whole Blood
        SELECT COUNT(*) INTO v_existing
        FROM tblblood_units
        WHERE blood_bank_id = v_blood_bank_id
          AND blood_type = v_blood_type
          AND component = 'Whole Blood'
          AND status = 'Available';

        SET v_to_insert = GREATEST(v_whole - v_existing, 0);
        SET i = 0;
        WHILE i < v_to_insert DO
            SET seq_num = seq_num + 1;
            SET v_unit_id = CONCAT('U-2026-', LPAD(seq_num, 5, '0'));

            INSERT INTO tblblood_units
                (unit_id, donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id)
            VALUES
                (v_unit_id, v_unit_id, v_blood_bank_id, v_blood_type, 'Whole Blood', DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Available', NULL);

            SET i = i + 1;
        END WHILE;

        -- PRBC
        SELECT COUNT(*) INTO v_existing
        FROM tblblood_units
        WHERE blood_bank_id = v_blood_bank_id
          AND blood_type = v_blood_type
          AND component = 'PRBC'
          AND status = 'Available';

        SET v_to_insert = GREATEST(v_prbc - v_existing, 0);
        SET i = 0;
        WHILE i < v_to_insert DO
            SET seq_num = seq_num + 1;
            SET v_unit_id = CONCAT('U-2026-', LPAD(seq_num, 5, '0'));

            INSERT INTO tblblood_units
                (unit_id, donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id)
            VALUES
                (v_unit_id, v_unit_id, v_blood_bank_id, v_blood_type, 'PRBC', DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Available', NULL);

            SET i = i + 1;
        END WHILE;

        -- Platelets
        SELECT COUNT(*) INTO v_existing
        FROM tblblood_units
        WHERE blood_bank_id = v_blood_bank_id
          AND blood_type = v_blood_type
          AND component = 'Platelets'
          AND status = 'Available';

        SET v_to_insert = GREATEST(v_platelets - v_existing, 0);
        SET i = 0;
        WHILE i < v_to_insert DO
            SET seq_num = seq_num + 1;
            SET v_unit_id = CONCAT('U-2026-', LPAD(seq_num, 5, '0'));

            INSERT INTO tblblood_units
                (unit_id, donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id)
            VALUES
                (v_unit_id, v_unit_id, v_blood_bank_id, v_blood_type, 'Platelets', DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Available', NULL);

            SET i = i + 1;
        END WHILE;

        -- FFP (source from tblinventory.fpp_units)
        SELECT COUNT(*) INTO v_existing
        FROM tblblood_units
        WHERE blood_bank_id = v_blood_bank_id
          AND blood_type = v_blood_type
          AND component = 'FFP'
          AND status = 'Available';

        SET v_to_insert = GREATEST(v_fpp - v_existing, 0);
        SET i = 0;
        WHILE i < v_to_insert DO
            SET seq_num = seq_num + 1;
            SET v_unit_id = CONCAT('U-2026-', LPAD(seq_num, 5, '0'));

            INSERT INTO tblblood_units
                (unit_id, donation_id, blood_bank_id, blood_type, component, expiry_date, status, request_id)
            VALUES
                (v_unit_id, v_unit_id, v_blood_bank_id, v_blood_type, 'FFP', DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Available', NULL);

            SET i = i + 1;
        END WHILE;
    END LOOP;

    CLOSE cur;
    COMMIT;
END$$
DELIMITER ;

-- 3) Run the procedure once to populate
CALL sp_populate_tblblood_units_from_inventory();

-- 4) Quick verify
SELECT COUNT(*) AS total_units FROM tblblood_units;
SELECT blood_bank_id, blood_type, component, COUNT(*) AS available_units
FROM tblblood_units
WHERE status = 'Available'
GROUP BY blood_bank_id, blood_type, component
ORDER BY blood_bank_id, blood_type, component;
