# PowerShell script to fix all remaining issues
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "fix_all_issues.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error fixing all issues: $_"
}
