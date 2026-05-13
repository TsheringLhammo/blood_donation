# PowerShell script to check Zangmo's status
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "check_zangmo_status.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error checking Zangmo status: $_"
}
