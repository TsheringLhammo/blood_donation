Set-Location 'C:\xampp\mysql\data'
$src = 'C:\xampp\mysql\data\master-2025@002d11@002d19@0020@00209@003a36@003a51@00200@0020@005bnote@005d@0020added@0020new@0020master_info@0020@0027@0027@0020to@0020hash@0020table@000d.info'
$dst = 'C:\xampp\mysql\data\mysql-fix-backup-20260401'
Write-Host 'SRC:' $src
Write-Host 'DEST:' $dst
Move-Item -LiteralPath $src -Destination $dst -Force
Write-Host 'MOVED'