# Screenshot Group 13 Manual Test Plan

Status: approved and closed.

Use only the local fictional SG13 dataset. Do not use the supplied family-photo
examples as repository fixtures or screenshot evidence.

## Prepare the Local Evidence State

```powershell
php artisan migrate
php artisan db:seed --class=ScreenshotGroup13DemoSeeder
```

Sign in as `sg13-owner@example.test` with the local-only test password
`SG13Demo!2026`, then open `/admin/restoration`.

## Application Evidence

1. Capture the release header, immutable-source count and queue controls as
   `01_v180_Restoration_Automation.png`.
2. Keep all enabled/disabled operation controls and crop target visible for
   `02_v180_Uploader_Controls.png`.
3. Position the pending fictional before/after candidate and analysis facts for
   `03_v180_Candidate_Comparison.png`.
4. Position the approved fictional candidate and review note for
   `04_v180_Approved_Review.png`.
5. Position the processing history and manual-only safety boundary for
   `05_v180_Immutable_Processing_History.png`.

Confirm a viewer receives HTTP 403, preview responses are private/no-store,
disabled operations are not applied, and no original storage facts change.

## Manual Terminal Evidence

After inspecting the five application screenshots, run:

```powershell
.\docs\manual-tests\screenshot-group-13\Validate-ScreenshotGroup13.ps1
```

Capture the completed PowerShell window as `06_v180_Validation.png`. The script
validates but does not capture or approve evidence.
