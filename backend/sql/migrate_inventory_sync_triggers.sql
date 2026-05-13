-- Optional inventory synchronization triggers for tblblood_units.
-- This version expects tblinventory columns: whole_units, prbc_units, platelets_units, fpp_units.
-- If your DB uses ffp_units instead, replace fpp_units with ffp_units in this script.

DROP PROCEDURE IF EXISTS sp_sync_inventory_delta;

DELIMITER //
CREATE PROCEDURE sp_sync_inventory_delta(
    IN p_blood_bank_id INT,
    IN p_blood_type VARCHAR(10),
    IN p_component VARCHAR(30),
    IN p_delta INT
)
BEGIN
    IF p_blood_bank_id IS NOT NULL AND p_blood_type IS NOT NULL AND p_component IS NOT NULL AND p_delta <> 0 THEN
        INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, fpp_units)
        VALUES (p_blood_bank_id, p_blood_type, 0, 0, 0, 0)
        ON DUPLICATE KEY UPDATE id = id;

        IF LOWER(p_component) IN ('whole blood', 'whole') THEN
            UPDATE tblinventory
            SET whole_units = GREATEST(0, whole_units + p_delta)
            WHERE blood_bank_id = p_blood_bank_id AND blood_type = p_blood_type;
        ELSEIF LOWER(p_component) IN ('prbc', 'packed red cells') THEN
            UPDATE tblinventory
            SET prbc_units = GREATEST(0, prbc_units + p_delta)
            WHERE blood_bank_id = p_blood_bank_id AND blood_type = p_blood_type;
        ELSEIF LOWER(p_component) = 'platelets' THEN
            UPDATE tblinventory
            SET platelets_units = GREATEST(0, platelets_units + p_delta)
            WHERE blood_bank_id = p_blood_bank_id AND blood_type = p_blood_type;
        ELSEIF LOWER(p_component) IN ('ffp', 'plasma', 'fresh frozen plasma') THEN
            UPDATE tblinventory
            SET fpp_units = GREATEST(0, fpp_units + p_delta)
            WHERE blood_bank_id = p_blood_bank_id AND blood_type = p_blood_type;
        END IF;
    END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS trg_blood_units_after_insert;
DELIMITER //
CREATE TRIGGER trg_blood_units_after_insert
AFTER INSERT ON tblblood_units
FOR EACH ROW
BEGIN
    IF LOWER(NEW.status) = 'available' THEN
        CALL sp_sync_inventory_delta(NEW.blood_bank_id, NEW.blood_type, NEW.component, 1);
    END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS trg_blood_units_after_update;
DELIMITER //
CREATE TRIGGER trg_blood_units_after_update
AFTER UPDATE ON tblblood_units
FOR EACH ROW
BEGIN
    IF LOWER(OLD.status) = 'available' AND LOWER(NEW.status) <> 'available' THEN
        CALL sp_sync_inventory_delta(OLD.blood_bank_id, OLD.blood_type, OLD.component, -1);
    ELSEIF LOWER(OLD.status) <> 'available' AND LOWER(NEW.status) = 'available' THEN
        CALL sp_sync_inventory_delta(NEW.blood_bank_id, NEW.blood_type, NEW.component, 1);
    ELSEIF LOWER(OLD.status) = 'available' AND LOWER(NEW.status) = 'available'
       AND (OLD.blood_bank_id <> NEW.blood_bank_id OR OLD.blood_type <> NEW.blood_type OR OLD.component <> NEW.component) THEN
        CALL sp_sync_inventory_delta(OLD.blood_bank_id, OLD.blood_type, OLD.component, -1);
        CALL sp_sync_inventory_delta(NEW.blood_bank_id, NEW.blood_type, NEW.component, 1);
    END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS trg_blood_units_after_delete;
DELIMITER //
CREATE TRIGGER trg_blood_units_after_delete
AFTER DELETE ON tblblood_units
FOR EACH ROW
BEGIN
    IF LOWER(OLD.status) = 'available' THEN
        CALL sp_sync_inventory_delta(OLD.blood_bank_id, OLD.blood_type, OLD.component, -1);
    END IF;
END //
DELIMITER ;
