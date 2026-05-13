# PowerShell script to verify Stage 2 donors
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "verify_stage2.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error verifying Stage 2: $_"
}
