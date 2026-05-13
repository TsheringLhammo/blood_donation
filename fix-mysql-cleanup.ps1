Set-Location 'C:\xampp\mysql\data'
$backup = 'C:\xampp\mysql\data\mysql-fix-backup-20260401'
if (-Not (Test-Path $backup)) { New-Item -ItemType Directory -Path $backup | Out-Null }
function MoveFiles($filter) {
    Get-ChildItem -Force -Filter $filter | ForEach-Object {
        try {
            Move-Item -LiteralPath $_.FullName -Destination $backup -Force
            Write-Host 'MOVED:' $_.Name
        } catch {
            Write-Host 'FAILED:' $_.Name '->' $_.FullName
            Write-Host $_.Exception.Message
        }
    }
}
MoveFiles 'master-*'
MoveFiles 'mysql-relay-bin*'
MoveFiles 'ib_logfile*'
Write-Host 'CLEANUP_DONE'