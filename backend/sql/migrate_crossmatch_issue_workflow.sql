-- Cross-match + Issue workflow extension
-- Creates tblcrossmatch, automatic inventory sync procedure/triggers, and one-time inventory rebuild from unit-level data.

USE blood_donation;

CREATE TABLE IF NOT EXISTS tblcrossmatch (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    unit_id VARCHAR(80) NULL,
    result ENUM('Compatible', 'Incompatible') NOT NULL,
    test_parameters TEXT NULL,
    notes TEXT NULL,
    performed_by VARCHAR(120) NOT NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tblcrossmatch_request_id (request_id),
    INDEX idx_tblcrossmatch_unit_id (unit_id),
    INDEX idx_tblcrossmatch_result (result),
    INDEX idx_tblcrossmatch_performed_at (performed_at)
);

DROP PROCEDURE IF EXISTS sp_sync_inventory_for_type;
DELIMITER $$
CREATE PROCEDURE sp_sync_inventory_for_type(IN p_blood_bank_id INT UNSIGNED, IN p_blood_type VARCHAR(5))
BEGIN
    DECLARE v_whole INT UNSIGNED DEFAULT 0;
    DECLARE v_prbc INT UNSIGNED DEFAULT 0;
    DECLARE v_platelets INT UNSIGNED DEFAULT 0;
    DECLARE v_fpp INT UNSIGNED DEFAULT 0;
    DECLARE v_plasma_column VARCHAR(20) DEFAULT 'fpp_units';
    DECLARE v_column_exists INT DEFAULT 0;
    DECLARE v_sql TEXT;

    SELECT
        COALESCE(SUM(CASE WHEN component = 'Whole Blood' THEN 1 ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN component IN ('PRBC', 'Packed Red Cells') THEN 1 ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN component = 'Platelets' THEN 1 ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN component IN ('FFP', 'Plasma', 'Fresh Frozen Plasma') THEN 1 ELSE 0 END), 0)
        INTO v_whole, v_prbc, v_platelets, v_fpp
    FROM tblblood_units
    WHERE blood_bank_id = p_blood_bank_id
      AND blood_type = p_blood_type
      AND status = 'Available';

        SELECT COUNT(*) INTO v_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
            AND table_name = 'tblinventory'
            AND column_name = 'fpp_units';

        IF v_column_exists = 0 THEN
                SET v_plasma_column = 'ffp_units';
        END IF;

        SET @p_bank_id = p_blood_bank_id;
        SET @p_blood_type = p_blood_type;
        SET @p_whole = v_whole;
        SET @p_prbc = v_prbc;
        SET @p_platelets = v_platelets;
        SET @p_fpp = v_fpp;

        SET v_sql = CONCAT(
                'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ', v_plasma_column, ', updated_at) ',
                'VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) ',
                'ON DUPLICATE KEY UPDATE ',
                'whole_units = VALUES(whole_units), ',
                'prbc_units = VALUES(prbc_units), ',
                'platelets_units = VALUES(platelets_units), ',
                v_plasma_column, ' = VALUES(', v_plasma_column, '), ',
                'updated_at = CURRENT_TIMESTAMP'
        );

        PREPARE stmt_sync_inv FROM v_sql;
        EXECUTE stmt_sync_inv USING @p_bank_id, @p_blood_type, @p_whole, @p_prbc, @p_platelets, @p_fpp;
        DEALLOCATE PREPARE stmt_sync_inv;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_tblblood_units_ai;
DELIMITER $$
CREATE TRIGGER trg_tblblood_units_ai
AFTER INSERT ON tblblood_units
FOR EACH ROW
BEGIN
    CALL sp_sync_inventory_for_type(NEW.blood_bank_id, NEW.blood_type);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_tblblood_units_au;
DELIMITER $$
CREATE TRIGGER trg_tblblood_units_au
AFTER UPDATE ON tblblood_units
FOR EACH ROW
BEGIN
    CALL sp_sync_inventory_for_type(OLD.blood_bank_id, OLD.blood_type);
    IF NEW.blood_bank_id <> OLD.blood_bank_id OR NEW.blood_type <> OLD.blood_type THEN
        CALL sp_sync_inventory_for_type(NEW.blood_bank_id, NEW.blood_type);
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_tblblood_units_ad;
DELIMITER $$
CREATE TRIGGER trg_tblblood_units_ad
AFTER DELETE ON tblblood_units
FOR EACH ROW
BEGIN
    CALL sp_sync_inventory_for_type(OLD.blood_bank_id, OLD.blood_type);
END$$
DELIMITER ;

-- One-time inventory initialization from current unit-level stock.
SET @plasma_col := (
    SELECT CASE
        WHEN EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'tblinventory' AND column_name = 'fpp_units'
        ) THEN 'fpp_units'
        ELSE 'ffp_units'
    END
);

SET @sql_reset := CONCAT(
    'UPDATE tblinventory ',
    'SET whole_units = 0, prbc_units = 0, platelets_units = 0, ', @plasma_col, ' = 0, updated_at = CURRENT_TIMESTAMP'
);
PREPARE stmt_reset_inventory FROM @sql_reset;
EXECUTE stmt_reset_inventory;
DEALLOCATE PREPARE stmt_reset_inventory;

SET @sql_rebuild := CONCAT(
    'INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ', @plasma_col, ', updated_at) ',
    'SELECT u.blood_bank_id, u.blood_type, ',
    'SUM(CASE WHEN u.component = ''Whole Blood'' THEN 1 ELSE 0 END) AS whole_units, ',
    'SUM(CASE WHEN u.component IN (''PRBC'', ''Packed Red Cells'') THEN 1 ELSE 0 END) AS prbc_units, ',
    'SUM(CASE WHEN u.component = ''Platelets'' THEN 1 ELSE 0 END) AS platelets_units, ',
    'SUM(CASE WHEN u.component IN (''FFP'', ''Plasma'', ''Fresh Frozen Plasma'') THEN 1 ELSE 0 END) AS plasma_units, ',
    'CURRENT_TIMESTAMP ',
    'FROM tblblood_units u ',
    'WHERE u.status = ''Available'' ',
    'GROUP BY u.blood_bank_id, u.blood_type ',
    'ON DUPLICATE KEY UPDATE ',
    'whole_units = VALUES(whole_units), ',
    'prbc_units = VALUES(prbc_units), ',
    'platelets_units = VALUES(platelets_units), ',
    @plasma_col, ' = VALUES(', @plasma_col, '), ',
    'updated_at = CURRENT_TIMESTAMP'
);
PREPARE stmt_rebuild_inventory FROM @sql_rebuild;
EXECUTE stmt_rebuild_inventory;
DEALLOCATE PREPARE stmt_rebuild_inventory;
