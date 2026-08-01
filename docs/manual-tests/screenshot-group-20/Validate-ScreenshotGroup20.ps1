$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-20'
$desktop = @(
    '01_v250_Thirty_Thousand_Inventory.png',
    '02_v250_Checkpoint_Progress.png',
    '03_v250_Resume_And_Failure_Isolation.png',
    '04_v250_Preservation_Boundary.png'
)
$responsive = '05_v250_Responsive_Batch_Status.png'
$privacyTextTargets = @(
    (Join-Path $repoRoot 'app\Console\Commands\ImportHighVolumePhotoBatchCommand.php'),
    (Join-Path $repoRoot 'app\Domain\CloudImport\Services\HighVolumePhotoBatch.php'),
    (Join-Path $repoRoot 'app\Http\Controllers\Admin\HighVolumeBatchController.php'),
    (Join-Path $repoRoot 'database\seeders\ScreenshotGroup20DemoSeeder.php'),
    (Join-Path $repoRoot 'tests\Feature\Group20'),
    (Join-Path $repoRoot 'docs\manual-tests\screenshot-group-20'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-20\README.md'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-20\Evidence_Index.md')
)
$forbiddenTokens = @(
    ('co' + 'dex'),
    ('chat' + 'gpt'),
    ('2025' + '0313')
)

Push-Location $repoRoot

try {
    Write-Host '=== Screenshot Group 20 focused tests ===' -ForegroundColor Cyan
    & php vendor/pestphp/pest/bin/pest tests/Feature/Group20/HighVolumeBatchIntakeTest.php --compact
    if ($LASTEXITCODE -ne 0) {
        throw "Focused tests failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Formatting verification ===" -ForegroundColor Cyan
    & php vendor/bin/pint --test app/Console/Commands/ImportHighVolumePhotoBatchCommand.php app/Domain/CloudImport/Services/HighVolumePhotoBatch.php app/Http/Controllers/Admin/HighVolumeBatchController.php database/seeders/ScreenshotGroup20DemoSeeder.php tests/Feature/Group20
    if ($LASTEXITCODE -ne 0) {
        throw "Formatting verification failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Static analysis ===" -ForegroundColor Cyan
    & php -d memory_limit=1G vendor/bin/phpstan analyse app/Console/Commands/ImportHighVolumePhotoBatchCommand.php app/Domain/CloudImport/Services/HighVolumePhotoBatch.php app/Http/Controllers/Admin/HighVolumeBatchController.php --no-progress
    if ($LASTEXITCODE -ne 0) {
        throw "Static analysis failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Production asset build ===" -ForegroundColor Cyan
    & npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "Production asset build failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Dependency security audits ===" -ForegroundColor Cyan
    & composer audit --no-interaction
    if ($LASTEXITCODE -ne 0) {
        throw "Composer audit failed with exit code $LASTEXITCODE."
    }
    & npm audit --audit-level=high
    if ($LASTEXITCODE -ne 0) {
        throw "NPM audit failed with exit code $LASTEXITCODE."
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

    $responsivePath = Join-Path $evidenceDir $responsive
    if (-not (Test-Path -LiteralPath $responsivePath)) {
        throw "Missing required screenshot: $responsive"
    }
    $responsiveImage = [System.Drawing.Image]::FromFile($responsivePath)
    try {
        if ($responsiveImage.Width -lt 375 -or $responsiveImage.Height -lt 800) {
            throw "$responsive is below the 375x800 responsive evidence floor."
        }
        Write-Host ("OK  {0}  {1}x{2}  PNG" -f $responsive, $responsiveImage.Width, $responsiveImage.Height) -ForegroundColor Green
    }
    finally {
        $responsiveImage.Dispose()
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
    Write-Host 'OK  SG20 text contains no forbidden private or tooling references.' -ForegroundColor Green

    Write-Host "`n=== Repository evidence state ===" -ForegroundColor Cyan
    & git status --short

    Write-Host "`nScreenshot Group 20 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 06_v250_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
