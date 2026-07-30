$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..\..\..'))

Write-Host "`n=== Immutable original focused validation ===" -ForegroundColor Cyan
php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw 'Configuration cache clear failed.' }

php artisan test --compact tests/Feature/Release/CollaborationRestorationReleaseTest.php --filter='versions restoration recipes and queues only preferred originals'
if ($LASTEXITCODE -ne 0) { throw 'Immutable original focused validation failed.' }

Write-Host "`nThe focused test confirmed queueing did not change the source disk, path, hash, preferred flag, type or version count." -ForegroundColor Green
Write-Host 'Capture this PowerShell window as 06_No_Original_Mutation_Validation.png.' -ForegroundColor Yellow
