$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..\..\..'))

function Invoke-Checked {
    param(
        [Parameter(Mandatory)]
        [string] $Label,
        [Parameter(Mandatory)]
        [scriptblock] $Command
    )

    Write-Host "`n=== $Label ===" -ForegroundColor Cyan
    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

Invoke-Checked 'Clear configuration cache' { php artisan config:clear }
Invoke-Checked 'Formatting verification' { php vendor/bin/pint --parallel --test }
Invoke-Checked 'Static analysis' { php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress }
Invoke-Checked 'Automated tests' { php -d memory_limit=1G vendor/pestphp/pest/bin/pest --compact }
Invoke-Checked 'Production asset build' { npm run build }
Invoke-Checked 'Screenshot Group 14 storage tests' { php -d memory_limit=1G vendor/pestphp/pest/bin/pest --compact tests/Feature/Group14/WasabiProductionStorageTest.php }
Invoke-Checked 'Read-only Wasabi migration plan' { php artisan archive:wasabi-migrate }
Invoke-Checked 'Composer security audit' { composer audit --no-interaction }
Invoke-Checked 'NPM security audit' { npm audit --audit-level=high }
Invoke-Checked 'Migration status' { php artisan migrate:status }
Invoke-Checked 'Desktop screenshot quality gate' {
    & .\docs\manual-tests\Test-DesktopScreenshotEvidence.ps1 -EvidencePath @(
        '.\docs\screenshot-groups\screenshot-group-14\01_v190_Production_Storage.png',
        '.\docs\screenshot-groups\screenshot-group-14\02_v190_Private_Prefix_Boundaries.png',
        '.\docs\screenshot-groups\screenshot-group-14\03_v190_Live_Verification.png',
        '.\docs\screenshot-groups\screenshot-group-14\04_v190_Migration_Dry_Run.png'
    )
}
Invoke-Checked 'Repository evidence state' { git status --short }

Write-Host "`nScreenshot Group 14 validation commands passed." -ForegroundColor Green
Write-Host 'Capture this PowerShell window as 05_v190_Validation.png.' -ForegroundColor Yellow
