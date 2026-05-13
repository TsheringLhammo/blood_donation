# PowerShell script to check Zangpo's status
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "check_zangpo_status.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error checking Zangpo status: $_"
}
