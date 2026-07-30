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

Invoke-Checked 'Application validation' { composer test }
Invoke-Checked 'Production asset build' { npm run build }
Invoke-Checked 'Composer security audit' { composer audit --no-interaction }
Invoke-Checked 'NPM security audit' { npm audit --audit-level=high }
Invoke-Checked 'Migration status' { php artisan migrate:status }
Invoke-Checked 'Repository evidence state' { git status --short }

Write-Host "`nGroup 14 validation commands passed." -ForegroundColor Green
Write-Host 'Capture this PowerShell window as 07_Group14_Validation_And_Evidence_Pending.png.' -ForegroundColor Yellow
