@echo off
cd /d C:\xampp\mysql\data
if not exist mysql-fix-backup-20260401 mkdir mysql-fix-backup-20260401
for /f "delims=" %%F in ('dir /b master-mysqld@002eexe*') do move /Y "%%F" mysql-fix-backup-20260401
for /f "delims=" %%F in ('dir /b mysql-relay-bin-mysqld@002eexe*') do move /Y "%%F" mysql-fix-backup-20260401
echo DONE