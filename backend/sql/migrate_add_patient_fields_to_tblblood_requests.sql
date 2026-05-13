-- Add missing patient fields to tblblood_requests
-- Run this once on the blood_donation database.

USE blood_donation;

ALTER TABLE tblblood_requests
    ADD COLUMN IF NOT EXISTS patient_dob DATE NULL AFTER patient_name;

ALTER TABLE tblblood_requests
    ADD COLUMN IF NOT EXISTS patient_age TINYINT UNSIGNED NULL AFTER patient_dob;

ALTER TABLE tblblood_requests
    ADD COLUMN IF NOT EXISTS patient_gender ENUM('Male','Female','Other') NULL AFTER patient_age;

ALTER TABLE tblblood_requests
    ADD COLUMN IF NOT EXISTS patient_address VARCHAR(255) NULL AFTER patient_gender;

UPDATE tblblood_requests
SET patient_gender = CASE
    WHEN patient_gender IN ('M', 'Male', 'male') THEN 'Male'
    WHEN patient_gender IN ('F', 'Female', 'female') THEN 'Female'
    WHEN patient_gender = 'Other' THEN 'Other'
    ELSE 'Other'
END;

ALTER TABLE tblblood_requests
    MODIFY COLUMN patient_gender ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other';
