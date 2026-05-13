# PowerShell script to find Zangpo or similar names
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "find_zangpo_similar.php"

try {
    & $phpPath $scriptPath
} catch {
    Write-Host "Error finding Zangpo similar names: $_"
}
