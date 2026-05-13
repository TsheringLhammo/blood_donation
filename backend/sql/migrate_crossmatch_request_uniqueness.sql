-- Enforce one cross-match log per request_id (real-world: one finalized test record per request)
-- Safe to run multiple times.

USE blood_donation;

-- Keep only the latest log for each request_id, delete older duplicates.
DELETE l_old
FROM tbllab_logs l_old
INNER JOIN tbllab_logs l_new
    ON l_old.request_id = l_new.request_id
   AND l_old.request_id IS NOT NULL
   AND l_old.id < l_new.id;

-- Add a uniqueness constraint on request_id if missing.
SET @has_uq := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbllab_logs'
      AND INDEX_NAME = 'uq_tbllab_logs_request'
);

SET @sql_add_uq := IF(
    @has_uq = 0,
    'ALTER TABLE tbllab_logs ADD CONSTRAINT uq_tbllab_logs_request UNIQUE (request_id)',
    'SELECT 1'
);

PREPARE stmt_add_uq FROM @sql_add_uq;
EXECUTE stmt_add_uq;
DEALLOCATE PREPARE stmt_add_uq;

SELECT 'ok' AS migration_status;
