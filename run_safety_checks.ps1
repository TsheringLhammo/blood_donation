# PowerShell script to run safety checks
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "safety_checks.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running safety checks: $_"
}
