# PowerShell script to run MySQL backup
Write-Host "Creating backup of tbldonors table..."

$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$database = "blood_donation"
$user = "root"

# Create backup table
$backupSQL = "CREATE TABLE tbldonors_backup_20250509 AS SELECT * FROM tbldonors;"

try {
    # Run backup command
    $process = Start-Process -FilePath $mysqlPath -ArgumentList "-u", $user, $database, "-e", $backupSQL -Wait -PassThru -NoNewWindow
    
    if ($process.ExitCode -eq 0) {
        Write-Host "Backup created successfully!"
        
        # Verify backup
        $verifySQL = "SELECT COUNT(*) as backup_count FROM tbldonors_backup_20250509;"
        $countProcess = Start-Process -FilePath $mysqlPath -ArgumentList "-u", $user, $database, "-e", $verifySQL -Wait -PassThru -NoNewWindow -RedirectStandardOutput "backup_count.txt"
        
        if (Test-Path "backup_count.txt") {
            $count = Get-Content "backup_count.txt"
            Write-Host "Backup record count: $count"
            Remove-Item "backup_count.txt"
        }
        
        Write-Host "Backup completed successfully!"
    } else {
        Write-Host "Error: Backup failed with exit code $($process.ExitCode)"
    }
} catch {
    Write-Host "Error creating backup: $_"
}
