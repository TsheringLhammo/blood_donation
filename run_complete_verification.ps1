# PowerShell script to run complete system verification
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "complete_system_verification.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running complete verification: $_"
}
