# PowerShell script to backup tbldonors table
Write-Host "Creating backup of tbldonors table..."

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupTable = "tbldonors_backup_$timestamp"

# Create backup SQL
$sql = @"
CREATE TABLE $backupTable AS SELECT * FROM tbldonors;
SELECT 'Backup created successfully' as status;
SELECT COUNT(*) as backup_count FROM $backupTable;
"@

# Write SQL to temporary file
$sql | Out-File -FilePath "temp_backup.sql" -Encoding UTF8

# Run MySQL command
try {
    $result = mysql -u root blood_donation -e "CREATE TABLE $backupTable AS SELECT * FROM tbldonors;"
    Write-Host "Backup table created: $backupTable"
    
    # Verify backup
    $count = mysql -u root blood_donation -e "SELECT COUNT(*) FROM $backupTable;" -s -N
    Write-Host "Backup record count: $count"
    
    # Clean up
    Remove-Item "temp_backup.sql" -ErrorAction SilentlyContinue
    
    Write-Host "Backup completed successfully!"
} catch {
    Write-Host "Error creating backup: $_"
}
