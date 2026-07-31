$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-18'
$desktop = @(
    '01_v230_Photos_Navigation.png',
    '02_v230_Public_Map_Navigation.png',
    '03_v230_Reviewed_Places.png',
    '04_v230_Reviewed_People.png',
    '05_v230_Reviewed_Events.png',
    '06_v230_Family_Branches.png',
    '07_v230_Archive_Search.png'
)

Push-Location $repoRoot

try {
    Write-Host '=== Screenshot Group 18 focused tests ===' -ForegroundColor Cyan
    & php vendor/pestphp/pest/bin/pest tests/Feature/Group18/ArchiveExplorationNavigationTest.php --compact
    if ($LASTEXITCODE -ne 0) {
        throw "Focused tests failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Production asset build ===" -ForegroundColor Cyan
    & npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "Production asset build failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Screenshot quality gate ===" -ForegroundColor Cyan
    Add-Type -AssemblyName System.Drawing

    foreach ($name in $desktop) {
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

    Write-Host "`nScreenshot Group 18 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 08_v230_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
