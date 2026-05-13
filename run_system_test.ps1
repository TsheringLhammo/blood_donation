# PowerShell script to run integrated system test
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "test_integrated_system.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error running system test: $_"
}
