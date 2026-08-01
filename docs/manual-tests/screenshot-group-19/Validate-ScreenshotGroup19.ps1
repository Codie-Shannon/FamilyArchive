$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-19'
$desktop = @(
    '01_v240_Pending_Intake_Boundary.png',
    '02_v240_Approved_Intake_Lineage.png',
    '03_v240_Focused_Restoration_Review.png',
    '04_v240_Approved_Archive_Detail.png',
    '05_v240_Focused_Member_Update.png',
    '06_v240_Viewing_Derivative_Lineage.png'
)
$privacyTextTargets = @(
    (Join-Path $repoRoot 'database\seeders\ScreenshotGroup19DemoSeeder.php'),
    (Join-Path $repoRoot 'tests\Feature\Group19'),
    (Join-Path $repoRoot 'docs\manual-tests\screenshot-group-19'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-19\README.md'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-19\Evidence_Index.md')
)
$forbiddenTokens = @(
    ('co' + 'dex'),
    ('chat' + 'gpt'),
    ('2025' + '0313')
)

Push-Location $repoRoot

try {
    Write-Host '=== Screenshot Group 19 focused tests ===' -ForegroundColor Cyan
    & php vendor/pestphp/pest/bin/pest `
        tests/Feature/Group19/VerifiedPhotoWorkflowTest.php `
        tests/Feature/Group13/RestorationAutomationTest.php `
        tests/Feature/Group10/PrivateArchiveGalleryTest.php `
        tests/Feature/Intake/IncomingPhotoAutomationTest.php `
        --compact
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
        $signature = [System.IO.File]::ReadAllBytes($path)[0..7]
        if ([BitConverter]::ToString($signature) -ne '89-50-4E-47-0D-0A-1A-0A') {
            throw "$name is not encoded as PNG."
        }
        $image = [System.Drawing.Image]::FromFile($path)
        try {
            if ($image.Width -lt 1800 -or $image.Height -lt 1000) {
                throw "$name is below the 1800x1000 desktop evidence floor."
            }
            Write-Host ("OK  {0}  {1}x{2}  PNG" -f $name, $image.Width, $image.Height) -ForegroundColor Green
        }
        finally {
            $image.Dispose()
        }
    }

    Write-Host "`n=== Privacy-safe evidence text scan ===" -ForegroundColor Cyan
    $privacyTextFiles = foreach ($target in $privacyTextTargets) {
        if (Test-Path -LiteralPath $target -PathType Container) {
            Get-ChildItem -LiteralPath $target -File -Recurse
        }
        elseif (Test-Path -LiteralPath $target -PathType Leaf) {
            Get-Item -LiteralPath $target
        }
    }
    $privacyMatches = $privacyTextFiles | Select-String -Pattern $forbiddenTokens -ErrorAction SilentlyContinue
    if ($privacyMatches) {
        $privacyMatches | Format-Table Path, LineNumber, Line -AutoSize
        throw 'Evidence text contains a forbidden private or tooling reference.'
    }
    Write-Host 'OK  SG19 text contains no forbidden private or tooling references.' -ForegroundColor Green

    Write-Host "`n=== Repository evidence state ===" -ForegroundColor Cyan
    & git status --short

    Write-Host "`nScreenshot Group 19 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 07_v240_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
