# Screenshot Group 20 Manual Test

Screenshot Group 20 proves the v2.5.0 High-Volume Batch Intake boundary with
a fictional 30,000-photo rehearsal. It does not read or retain real family
media.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup20DemoSeeder
```

Sign in locally as `sg20-owner@example.test` with the fictional password
`SG20Demo!2026`, then open `/admin/batch-imports`.

## Required screenshots

1. `01_v250_Thirty_Thousand_Inventory.png` — aggregate 30,000-photo inventory.
2. `02_v250_Checkpoint_Progress.png` — bounded 500-file checkpoint and resume state.
3. `03_v250_Resume_And_Failure_Isolation.png` — retained, failed and queued outcomes.
4. `04_v250_Preservation_Boundary.png` — path, drift, review and promotion boundaries.
5. `05_v250_Responsive_Batch_Status.png` — responsive Owner batch status.
6. `06_v250_Validation.png` — manually run the validation script and capture its
   successful final output.

Application screenshots may be automated. The validation script, final pack
approval and any real-photo rehearsal remain manual.
