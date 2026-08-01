$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$evidenceDir = Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-24'
$desktop = @(
    '01_v290_Delegated_Family_Operations.png',
    '02_v290_Routine_Account_Approval.png',
    '03_v290_Reported_Content_Review.png',
    '04_v290_Recipient_Message_Consent.png',
    '05_v290_Owner_Exception_Queue.png',
    '06_v290_Immediate_Family_Conversation.png'
)
$responsive = '07_v290_Responsive_Family_Operations.png'
$privacyTextTargets = @(
    (Join-Path $repoRoot 'app\Domain\Operations'),
    (Join-Path $repoRoot 'app\Http\Controllers\Admin\FamilyOperationsController.php'),
    (Join-Path $repoRoot 'app\Http\Middleware\EnsureUserCanManageFamilyOperations.php'),
    (Join-Path $repoRoot 'database\seeders\ScreenshotGroup24DemoSeeder.php'),
    (Join-Path $repoRoot 'resources\views\admin\family-operations.blade.php'),
    (Join-Path $repoRoot 'tests\Feature\Group24'),
    (Join-Path $repoRoot 'docs\manual-tests\screenshot-group-24'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-24\README.md'),
    (Join-Path $repoRoot 'docs\screenshot-groups\screenshot-group-24\Evidence_Index.md')
)
$forbiddenTokens = @(
    ('co' + 'dex'),
    ('chat' + 'gpt'),
    ('2025' + '0313')
)

Push-Location $repoRoot

try {
    Write-Host '=== Screenshot Group 24 focused tests ===' -ForegroundColor Cyan
    & php vendor/pestphp/pest/bin/pest tests/Feature/Group24/DelegatedFamilyOperationsTest.php tests/Feature/Group22/DelegatedIntakeCompletionTest.php tests/Feature/Release/SecureCommunicationReleaseTest.php --compact
    if ($LASTEXITCODE -ne 0) {
        throw "Focused tests failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Formatting verification ===" -ForegroundColor Cyan
    & php vendor/bin/pint --test app/Domain/Operations/Services/DelegatedFamilyOperations.php app/Http/Controllers/Admin/FamilyOperationsController.php app/Http/Controllers/Admin/OwnerCommandCentreController.php app/Http/Controllers/PublicConversationController.php app/Http/Controllers/SecureMessagingController.php app/Http/Middleware/EnsureUserCanManageFamilyOperations.php app/Models/User.php database/seeders/ScreenshotGroup24DemoSeeder.php tests/Feature/Group24/DelegatedFamilyOperationsTest.php
    if ($LASTEXITCODE -ne 0) {
        throw "Formatting verification failed with exit code $LASTEXITCODE."
    }

    Write-Host "`n=== Static analysis ===" -ForegroundColor Cyan
    & php -d memory_limit=1G vendor/bin/phpstan analyse app/Domain/Operations/Services/DelegatedFamilyOperations.php app/Http/Controllers/Admin/FamilyOperationsController.php app/Http/Middleware/EnsureUserCanManageFamilyOperations.php --no-progress
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
            if ($image.Width -lt 1200 -or $image.Height -lt 700) {
                throw "$name is below the 1200x700 desktop evidence floor."
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
    $responsiveSignature = [System.IO.File]::ReadAllBytes($responsivePath)[0..7]
    if ([BitConverter]::ToString($responsiveSignature) -ne '89-50-4E-47-0D-0A-1A-0A') {
        throw "$responsive is not encoded as PNG."
    }
    $responsiveImage = [System.Drawing.Image]::FromFile($responsivePath)
    try {
        if ($responsiveImage.Width -lt 360 -or $responsiveImage.Height -lt 780) {
            throw "$responsive is below the 360x780 responsive evidence floor."
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
    Write-Host 'OK  SG24 text contains no forbidden private or tooling references.' -ForegroundColor Green

    Write-Host "`n=== Repository evidence state ===" -ForegroundColor Cyan
    & git status --short

    Write-Host "`nScreenshot Group 24 validation commands passed." -ForegroundColor Green
    Write-Host 'Capture this PowerShell window as 08_v290_Validation.png.' -ForegroundColor Yellow
}
finally {
    Pop-Location
}
