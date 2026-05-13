# PowerShell script to run final verification
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "final_verification.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running final verification: $_"
}
