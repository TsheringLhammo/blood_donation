# PowerShell script to fix HIV permanent deferral
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "fix_hiv_permanent_deferral.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error fixing HIV permanent deferral: $_"
}
