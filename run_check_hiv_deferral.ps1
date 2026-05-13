# PowerShell script to check HIV deferral status
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "check_hiv_deferral_status.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error checking HIV deferral status: $_"
}
