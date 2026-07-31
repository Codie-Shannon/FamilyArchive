$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-16'
$desktop = @(
    '01_v210_Member_Home.png',
    '02_v210_Unified_Archive.png',
    '03_v210_Contributor_Journey.png',
    '04_v210_Family_Activity.png',
    '05_v210_Messages.png'
)
$mobile = '06_v210_Responsive_Navigation.png'

Push-Location $repoRoot

try {
    Write-Host '=== Screenshot Group 16 focused tests ===' -ForegroundColor Cyan
    & php -d memory_limit=1G vendor/pestphp/pest/bin/pest tests/Feature/Group16/UnifiedMemberExperienceTest.php --compact
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

    $mobilePath = Join-Path $evidenceDir $mobile
    if (-not (Test-Path -LiteralPath $mobilePath)) {
        throw "Missing required screenshot: $mobile"
    }
    $mobileImage = [System.Drawing.Image]::FromFile($mobilePath)
    try {
        if ($mobileImage.Width -lt 360 -or $mobileImage.Height -lt 780 -or $mobileImage.Width -ge $mobileImage.Height) {
            throw "$mobile must be a portrait mobile capture of at least 360x780."
        }
        Write-Host ("OK  {0}  {1}x{2}" -f $mobile, $mobileImage.Width, $mobileImage.Height) -ForegroundColor Green
    }
    finally {
        $mobileImage.Dispose()
    }

    Write-Host "`n=== Repository evidence state ===" -ForegroundColor Cyan
    & git status --short

    Write-Host "`nScreenshot Group 16 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 07_v210_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
