CREATE TABLE tbldonors_backup_20260509_163758 AS SELECT * FROM tbldonors;
SELECT 'Backup created successfully' as status;
SELECT COUNT(*) as backup_count FROM tbldonors_backup_20260509_163758;
