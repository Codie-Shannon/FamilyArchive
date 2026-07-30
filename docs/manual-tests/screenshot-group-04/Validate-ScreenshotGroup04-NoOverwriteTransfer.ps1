$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..\..\..'))

Write-Host "`n=== No-overwrite transfer focused validation ===" -ForegroundColor Cyan
php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw 'Configuration cache clear failed.' }

php artisan test --compact tests/Feature/Release/IntegrityProductionReleaseTest.php --filter='refuses to overwrite an existing target'
if ($LASTEXITCODE -ne 0) { throw 'No-overwrite transfer validation failed.' }

Write-Host "`nThe focused test confirmed the existing destination and source remained unchanged when transfer was refused." -ForegroundColor Green
Write-Host 'Capture this PowerShell window as 06_No_Overwrite_Transfer_Test.png.' -ForegroundColor Yellow
