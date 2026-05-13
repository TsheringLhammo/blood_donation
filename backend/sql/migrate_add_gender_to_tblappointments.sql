ALTER TABLE tblappointments
    ADD COLUMN IF NOT EXISTS gender ENUM('Male','Female','Other') NULL AFTER age;

UPDATE tblappointments
SET gender = 'Other'
WHERE gender IS NULL OR gender = '';

ALTER TABLE tblappointments
    MODIFY COLUMN gender ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other';
