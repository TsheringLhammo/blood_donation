# PowerShell script to verify Stage 2 after fixes
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "verify_stage2_after_fixes.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error verifying Stage 2: $_"
}
