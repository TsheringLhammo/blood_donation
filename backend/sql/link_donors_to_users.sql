-- Add user_id link from tbldonors → tblusers so profile / history /
-- appointments can be joined reliably (the email-only link was fragile
-- when case or whitespace differed between the two tables).

SET @col := (SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'tbldonors'
               AND column_name = 'user_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE tbldonors
     ADD COLUMN user_id INT UNSIGNED NULL AFTER id,
     ADD INDEX idx_tbldonors_user_id (user_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill from current email matches (case + whitespace tolerant).
UPDATE tbldonors d
JOIN tblusers u ON LOWER(TRIM(d.email)) = LOWER(TRIM(u.email))
   SET d.user_id = u.id
 WHERE d.user_id IS NULL;
