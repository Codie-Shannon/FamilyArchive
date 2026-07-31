$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-15'
$required = @(
    '01_v200_Live_Public_Product.png',
    '02_v200_Live_Archive_Experience.png',
    '03_v200_Production_Readiness.png'
)

Push-Location $repoRoot

try {
    Write-Host '=== Screenshot Group 15 focused tests ===' -ForegroundColor Cyan
    & php -d memory_limit=1G vendor/pestphp/pest/bin/pest tests/Feature/Group15/HostedProductionTest.php --compact
    if ($LASTEXITCODE -ne 0) {
        throw "Focused tests failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Desktop screenshot quality gate ===" -ForegroundColor Cyan

    Add-Type -AssemblyName System.Drawing

    foreach ($name in $required) {
        $path = Join-Path $evidenceDir $name

        if (-not (Test-Path -LiteralPath $path)) {
            throw "Missing required screenshot: $name"
        }

        $image = [System.Drawing.Image]::FromFile($path)

        try {
            if ($image.Width -lt 1800 -or $image.Height -lt 1000) {
                throw "$name is below the 1800x1000 desktop evidence floor."
            }

            Write-Host ("OK  {0}  {1}x{2}" -f $name, $image.Width, $image.Height) -ForegroundColor Green
        }
        finally {
            $image.Dispose()
        }
    }

    Write-Host "`n=== Repository evidence state ===" -ForegroundColor Cyan
    & git status --short

    Write-Host "`nScreenshot Group 15 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 04_v200_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
