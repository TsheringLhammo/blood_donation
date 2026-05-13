$body = @{
    donorId = 26
    collectionDate = "2026-05-12"
    collectionTime = "10:00"
    technician = "Lemo"
} | ConvertTo-Json

$response = Invoke-WebRequest -Uri "http://localhost/blood_donation/backend/api/collect_donor_sample.php" -Method POST -Headers @{"Content-Type" = "application/json"} -Body $body -ErrorAction Continue

Write-Host "Status Code: $($response.StatusCode)"
Write-Host "Response:"
$response.Content
