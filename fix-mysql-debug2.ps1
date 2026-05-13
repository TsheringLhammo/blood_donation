Set-Location 'C:\xampp\mysql\data'
$f = Get-ChildItem -Force -Filter 'master-*' | Select-Object -First 1
$chars = $f.Name.ToCharArray()
Write-Host 'NAME:' $f.Name
Write-Host 'LENGTH:' $chars.Length
Write-Host 'LAST 40 CHAR CODES:'
for ($i = $chars.Length - 40; $i -lt $chars.Length; $i++) {
    if ($i -ge 0) { Write-Host "$i:`t$($chars[$i])`t$([int]$chars[$i])" }
}

$f2 = Get-ChildItem -Force -Filter 'mysql-relay-bin*' | Select-Object -First 1
$chars2 = $f2.Name.ToCharArray()
Write-Host '---'
Write-Host 'NAME2:' $f2.Name
Write-Host 'LENGTH2:' $chars2.Length
Write-Host 'LAST 40 CHAR CODES2:'
for ($i = $chars2.Length - 40; $i -lt $chars2.Length; $i++) {
    if ($i -ge 0) { Write-Host "$i:`t$($chars2[$i])`t$([int]$chars2[$i])" }
}