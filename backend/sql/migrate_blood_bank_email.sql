-- Migration: Add email support for blood banks
-- Uses existing table name in this project: tblblood_banks
-- Target DB: blood_donation

USE blood_donation;

START TRANSACTION;

-- 1) Add email column
ALTER TABLE tblblood_banks
    ADD COLUMN IF NOT EXISTS email VARCHAR(160) NULL AFTER phone;

-- 2) Update existing records with email
UPDATE tblblood_banks
SET email = CASE id
    WHEN 1 THEN 'national.bb@health.bt'
    WHEN 2 THEN 'phuentsholing.bb@health.bt'
    ELSE email
END
WHERE id IN (1, 2);

-- 3) Sample insert data for 2 blood banks with phone and email
INSERT INTO tblblood_banks (id, name, hospital, dzongkhag, address, phone, email, hours, emergency, status, types_csv, is_active)
VALUES
    (101, 'Demo Thimphu Blood Bank', 'JDWNRH', 'Thimphu', 'Gongphel Lam, Thimphu', '02-322496', 'demo.thimphu.bb@health.bt', 'Mon-Fri: 9:00 AM - 5:00 PM', 'Emergency on call', 'open', 'A+, O+, B+, AB+', 1),
    (102, 'Demo Chukha Blood Bank', 'Phuentsholing General Hospital', 'Chukha', 'Hospital Road, Phuentsholing', '05-252431', 'demo.chukha.bb@health.bt', 'Mon-Fri: 9:00 AM - 4:00 PM', 'Emergency on call', 'open', 'A+, O+', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    hospital = VALUES(hospital),
    dzongkhag = VALUES(dzongkhag),
    address = VALUES(address),
    phone = VALUES(phone),
    email = VALUES(email),
    hours = VALUES(hours),
    emergency = VALUES(emergency),
    status = VALUES(status),
    types_csv = VALUES(types_csv),
    is_active = VALUES(is_active);

COMMIT;
