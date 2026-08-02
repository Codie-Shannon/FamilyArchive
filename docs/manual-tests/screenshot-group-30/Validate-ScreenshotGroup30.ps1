$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-30'
$desktop = @(
    '01_v350_Message_Requests.png',
    '02_v350_Private_Conversations.png',
    '03_v350_Shared_Files.png',
    '04_v350_Family_Activity.png',
    '05_v350_Add_Photo_Batch.png',
    '07_v350_Operational_Details.png'
)
$responsive = '06_v350_Responsive_Messages.png'
$privacyTextTargets = @(
    (Join-Path $repoRoot 'app\Http\Controllers\SecureMessagingController.php'),
    (Join-Path $repoRoot 'app\Http\Controllers\CommunityWorkspaceController.php'),
    (Join-Path $repoRoot 'database\seeders\ScreenshotGroup30DemoSeeder.php'),
    (Join-Path $repoRoot 'resources\views\secure-messages'),
    (Join-Path $repoRoot 'resources\views\community'),
    (Join-Path $repoRoot 'resources\views\contributor'),
    (Join-Path $repoRoot 'tests\Feature\Group30'),
    (Join-Path $repoRoot 'docs\manual-tests\screenshot-group-30'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-30')
)
$forbiddenTokens = @(
    ('co' + 'dex'),
    ('chat' + 'gpt'),
    ('2025' + '0313')
)

Push-Location $repoRoot
try {
    Write-Host '=== Screenshot Group 30 focused tests ===' -ForegroundColor Cyan
    & php vendor/pestphp/pest/bin/pest tests/Feature/Group30/EverydayFamilyExperienceTest.php tests/Feature/Release/SecureCommunicationReleaseTest.php tests/Feature/Release/RealtimeCommunityReleaseTest.php tests/Feature/Group16/UnifiedMemberExperienceTest.php --compact
    if ($LASTEXITCODE -ne 0) { throw "Focused tests failed with exit code $LASTEXITCODE." }

    Write-Host "`n=== Formatting verification ===" -ForegroundColor Cyan
    & php vendor/bin/pint --test app/Http/Controllers/SecureMessagingController.php app/Http/Controllers/CommunityWorkspaceController.php database/seeders/ScreenshotGroup30DemoSeeder.php tests/Feature/Group30/EverydayFamilyExperienceTest.php tests/Feature/Group16/UnifiedMemberExperienceTest.php tests/Feature/Release/SecureCommunicationReleaseTest.php tests/Feature/Release/RealtimeCommunityReleaseTest.php
    if ($LASTEXITCODE -ne 0) { throw "Formatting verification failed with exit code $LASTEXITCODE." }

    Write-Host "`n=== Static analysis ===" -ForegroundColor Cyan
    & php -d memory_limit=1G vendor/bin/phpstan analyse app/Http/Controllers/SecureMessagingController.php app/Http/Controllers/CommunityWorkspaceController.php database/seeders/ScreenshotGroup30DemoSeeder.php --no-progress
    if ($LASTEXITCODE -ne 0) { throw "Static analysis failed with exit code $LASTEXITCODE." }

    Write-Host "`n=== Production asset build ===" -ForegroundColor Cyan
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw "Production build failed with exit code $LASTEXITCODE." }

    Write-Host "`n=== Dependency security audits ===" -ForegroundColor Cyan
    & composer audit --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "Composer audit failed with exit code $LASTEXITCODE." }
    & npm audit --audit-level=high
    if ($LASTEXITCODE -ne 0) { throw "NPM audit failed with exit code $LASTEXITCODE." }

    Write-Host "`n=== Screenshot quality gate ===" -ForegroundColor Cyan
    Add-Type -AssemblyName System.Drawing
    foreach ($name in $desktop) {
        $path = Join-Path $evidenceDir $name
        if (-not (Test-Path -LiteralPath $path)) { throw "Missing required screenshot: $name" }
        $signature = [System.IO.File]::ReadAllBytes($path)[0..7]
        if ([BitConverter]::ToString($signature) -ne '89-50-4E-47-0D-0A-1A-0A') { throw "$name is not encoded as PNG." }
        $image = [System.Drawing.Image]::FromFile($path)
        try {
            if ($image.Width -lt 1200 -or $image.Height -lt 700) { throw "$name is below the 1200x700 desktop evidence floor." }
            Write-Host ("OK  {0}  {1}x{2}  PNG" -f $name, $image.Width, $image.Height) -ForegroundColor Green
        }
        finally { $image.Dispose() }
    }

    $responsivePath = Join-Path $evidenceDir $responsive
    if (-not (Test-Path -LiteralPath $responsivePath)) { throw "Missing required screenshot: $responsive" }
    $responsiveSignature = [System.IO.File]::ReadAllBytes($responsivePath)[0..7]
    if ([BitConverter]::ToString($responsiveSignature) -ne '89-50-4E-47-0D-0A-1A-0A') { throw "$responsive is not encoded as PNG." }
    $responsiveImage = [System.Drawing.Image]::FromFile($responsivePath)
    try {
        if ($responsiveImage.Width -lt 360 -or $responsiveImage.Height -lt 780) { throw "$responsive is below the 360x780 responsive evidence floor." }
        Write-Host ("OK  {0}  {1}x{2}  PNG" -f $responsive, $responsiveImage.Width, $responsiveImage.Height) -ForegroundColor Green
    }
    finally { $responsiveImage.Dispose() }

    Write-Host "`n=== Privacy-safe evidence text scan ===" -ForegroundColor Cyan
    $privacyTextFiles = foreach ($target in $privacyTextTargets) {
        if (Test-Path -LiteralPath $target -PathType Container) { Get-ChildItem -LiteralPath $target -File -Recurse }
        elseif (Test-Path -LiteralPath $target -PathType Leaf) { Get-Item -LiteralPath $target }
    }
    $privacyMatches = $privacyTextFiles | Select-String -Pattern $forbiddenTokens -ErrorAction SilentlyContinue
    if ($privacyMatches) {
        $privacyMatches | Format-Table Path, LineNumber, Line -AutoSize
        throw 'Evidence text contains a forbidden private or tooling reference.'
    }
    Write-Host 'OK  SG30 text contains no forbidden private or tooling references.' -ForegroundColor Green

    Write-Host "`n=== Repository evidence state ===" -ForegroundColor Cyan
    & git status --short

    Write-Host "`nScreenshot Group 30 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 08_v350_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
