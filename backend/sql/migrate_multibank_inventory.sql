-- Migration: Support multiple blood banks in inventory
-- Reuses existing table: tblblood_banks (id)
-- Target DB: blood_donation

USE blood_donation;

START TRANSACTION;

-- 1) Ensure inventory has bank reference column.
ALTER TABLE tblinventory
    ADD COLUMN IF NOT EXISTS blood_bank_id INT UNSIGNED NULL AFTER id;

-- 2) Backfill existing inventory rows to a default bank.
--    Uses National Blood Bank if found, otherwise first available bank id.
UPDATE tblinventory i
SET i.blood_bank_id = COALESCE(
    (SELECT bb.id FROM tblblood_banks bb WHERE bb.name = 'National Blood Bank' LIMIT 1),
    (SELECT bb.id FROM tblblood_banks bb ORDER BY bb.id ASC LIMIT 1)
)
WHERE i.blood_bank_id IS NULL;

-- 3) Make bank reference required after backfill.
ALTER TABLE tblinventory
    MODIFY COLUMN blood_bank_id INT UNSIGNED NOT NULL;

-- 4) Replace old uniqueness on blood_type with per-bank uniqueness.
ALTER TABLE tblinventory
    DROP INDEX blood_type,
    ADD CONSTRAINT uq_tblinventory_bank_blood UNIQUE (blood_bank_id, blood_type);

-- 5) Add foreign key to existing blood bank table.
ALTER TABLE tblinventory
    ADD CONSTRAINT fk_tblinventory_blood_bank
    FOREIGN KEY (blood_bank_id)
    REFERENCES tblblood_banks (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;

COMMIT;


-- 6) Example insertion for 2 blood banks + inventory.
--    This project already has tblblood_banks, so we add two sample rows there.
INSERT INTO tblblood_banks (id, name, hospital, dzongkhag, address, phone, hours, emergency, status, types_csv, is_active)
VALUES
    (101, 'Demo Thimphu Blood Bank', 'JDWNRH', 'Thimphu', 'Gongphel Lam, Thimphu', '02-322496', 'Mon-Fri: 9:00 AM - 5:00 PM', 'Emergency on call', 'open', 'A+, O+, B+, AB+', 1),
    (102, 'Demo Chukha Blood Bank', 'Phuentsholing General Hospital', 'Chukha', 'Hospital Road, Phuentsholing', '05-252431', 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, O+', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    hospital = VALUES(hospital),
    dzongkhag = VALUES(dzongkhag),
    address = VALUES(address),
    phone = VALUES(phone),
    hours = VALUES(hours),
    emergency = VALUES(emergency),
    status = VALUES(status),
    types_csv = VALUES(types_csv),
    is_active = VALUES(is_active);

INSERT INTO tblinventory (blood_bank_id, blood_type, whole_units, prbc_units, platelets_units, ffp_units)
VALUES
    (101, 'A+', 20, 12, 6, 9),
    (101, 'O+', 25, 15, 8, 10),
    (102, 'A+', 10, 6, 3, 4),
    (102, 'O+', 12, 7, 4, 5)
ON DUPLICATE KEY UPDATE
    whole_units = VALUES(whole_units),
    prbc_units = VALUES(prbc_units),
    platelets_units = VALUES(platelets_units),
    ffp_units = VALUES(ffp_units),
    updated_at = CURRENT_TIMESTAMP;


-- 7) Optional view query: inventory by blood bank.
SELECT
    b.id AS blood_bank_id,
    b.name AS blood_bank,
    i.blood_type,
    i.whole_units,
    i.prbc_units,
    i.platelets_units,
    i.ffp_units,
    i.updated_at
FROM tblinventory i
JOIN tblblood_banks b ON b.id = i.blood_bank_id
ORDER BY b.name, i.blood_type;
