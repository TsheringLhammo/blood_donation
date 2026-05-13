# PowerShell script to run safety checks after fixes
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "run_safety_checks_after_fixes.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running safety checks: $_"
}
