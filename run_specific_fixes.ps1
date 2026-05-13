# PowerShell script to run specific donor fixes
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "execute_specific_fixes.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running specific fixes: $_"
}
