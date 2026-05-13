# PowerShell script to fix Zangmo's fishy issue
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "fix_zangmo_fishy_issue.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error fixing Zangmo fishy issue: $_"
}
