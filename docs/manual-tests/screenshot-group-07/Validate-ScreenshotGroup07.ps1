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
Invoke-Checked 'Automated tests' { php artisan test --compact }
Invoke-Checked 'Production asset build' { npm run build }
Invoke-Checked 'Screenshot Group 07 release tests' { php artisan test --compact tests/Feature/Release/MediaCloudImportReleaseTest.php }
Invoke-Checked 'Composer security audit' { composer audit --no-interaction }
Invoke-Checked 'NPM security audit' { npm audit --audit-level=high }
Invoke-Checked 'Migration status' { php artisan migrate:status }
Invoke-Checked 'Desktop screenshot quality gate' {
    & .\docs\manual-tests\Test-DesktopScreenshotEvidence.ps1 -EvidencePath @(
        '.\docs\screenshot-groups\screenshot-group-07\01_v120_Cloud_Import.png',
        '.\docs\screenshot-groups\screenshot-group-07\02_v120_Mixed_Media.png'
    )
}
Invoke-Checked 'Repository evidence state' { git status --short }

Write-Host "`nScreenshot Group 07 validation commands passed." -ForegroundColor Green
Write-Host 'Capture this PowerShell window as 03_v120_Validation.png.' -ForegroundColor Yellow
