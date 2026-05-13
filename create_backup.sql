-- Create backup of tbldonors table
CREATE TABLE tbldonors_backup_20250509 AS SELECT * FROM tbldonors;

-- Show backup status
SELECT 'Backup created successfully' as status;
SELECT COUNT(*) as backup_count FROM tbldonors_backup_20250509;
