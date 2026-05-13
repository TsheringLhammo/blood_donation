# PowerShell script to run donor fixes
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "fix_donors.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running donor fixes: $_"
}
