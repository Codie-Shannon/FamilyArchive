# Screenshot Group 19 Manual Test

Screenshot Group 19 proves the v2.4.0 Verified Photo Workflow with a wholly
fictional local photo. No private family media is part of this evidence pack.

## Prepare the fictional dataset

```powershell
$env:ARCHIVE_PROVIDER='local'
php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup19DemoSeeder
```

Sign in locally as `sg19-owner@example.test` with the fictional password
`SG19Demo!2026`.

## Required screenshots

1. `01_v240_Pending_Intake_Boundary.png` — retained fictional upload before
   owner acceptance.
2. `02_v240_Approved_Intake_Lineage.png` — accepted upload with its immutable
   archive and restoration lineage.
3. `03_v240_Focused_Restoration_Review.png` — focused source/candidate review.
4. `04_v240_Approved_Archive_Detail.png` — approved private archive detail
   using the owner-approved restoration.
5. `05_v240_Focused_Member_Update.png` — permission-aware member view focused
   on the fictional approved photo.
6. `06_v240_Viewing_Derivative_Lineage.png` — web and thumbnail derivatives
   parented to the approved restoration.
7. `07_v240_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated, but the validation script and final
approval remain manual.
