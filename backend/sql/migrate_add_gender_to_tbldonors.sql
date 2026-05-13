ALTER TABLE tbldonors
    ADD COLUMN IF NOT EXISTS gender ENUM('Male','Female','Other') NOT NULL AFTER date_of_birth;
