$base = 'http://127.0.0.1:8090'
$login = Invoke-WebRequest -Uri "$base/api/v1/auth/login" -Method Post -Headers @{ 'Accept' = 'application/json' } -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes('{"identifier":"admin","password":"Forkiva@2026"}')) -UseBasicParsing -TimeoutSec 180
$token = ($login.Content | ConvertFrom-Json).body.token
$auth = @{ 'Authorization' = "Bearer $token"; 'Accept' = 'application/json' }
Write-Output ("TOKEN_LEN: " + $token.Length)

$id = 14
Write-Output ("CUSTOMER_ID: " + $id)

$export = Invoke-WebRequest -Uri "$base/api/v1/customers/$id/pdpa/export" -Headers $auth -UseBasicParsing -TimeoutSec 180
Write-Output ("EXPORT_HTTP: " + $export.StatusCode)
Write-Output ("EXPORT_NAME: " + (($export.Content | ConvertFrom-Json).body.name))
Write-Output ("EXPORT_CONSENT: " + (($export.Content | ConvertFrom-Json).body.pdpa_consent_at))

$anon = Invoke-WebRequest -Uri "$base/api/v1/customers/$id/pdpa/anonymize" -Method Post -Headers $auth -UseBasicParsing -TimeoutSec 180
$anonBody = ($anon.Content | ConvertFrom-Json).body
Write-Output ("ANONYMIZE_HTTP: " + $anon.StatusCode)
Write-Output ("ANON_NAME: " + $anonBody.name)
Write-Output ("ANON_EMAIL_NULL: " + ($null -eq $anonBody.email))
Write-Output ("ANON_STAMP: " + $anonBody.pdpa_anonymized_at)
Write-Output 'PDPA_SMOKE_DONE'