# PowerShell script to re-check HIV deferral status
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "recheck_hiv_status.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error re-checking HIV status: $_"
}
