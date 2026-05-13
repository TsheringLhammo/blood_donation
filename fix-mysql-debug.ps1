Set-Location 'C:\xampp\mysql\data'
$f = Get-ChildItem -Force -Filter 'master-*' | Select-Object -First 1
Write-Host 'NAME:' $f.Name
Write-Host 'FULL:' $f.FullName
Write-Host 'LEN:' $f.FullName.Length
Write-Host 'CHARS:'
$f.FullName.ToCharArray() | ForEach-Object { Write-Host ([int]$_) }