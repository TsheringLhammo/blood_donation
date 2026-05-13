# PowerShell script to run PHP backup
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "simple_backup.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running backup: $_"
}
