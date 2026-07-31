# Screenshot Group 18 Manual Test

Screenshot Group 18 proves the v2.3.0 Archive Exploration navigation using
only the fictional local dataset.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup17DemoSeeder
php artisan db:seed --class=ScreenshotGroup18DemoSeeder
```

Sign in locally as `sg12-owner@example.test` with the fictional password
`SG12Demo!2026`.

## Required screenshots

1. `01_v230_Photos_Navigation.png` — Photos with the shared bar active.
2. `02_v230_Public_Map_Navigation.png` — public map with Places & map active.
3. `03_v230_Reviewed_Places.png` — reviewed locations and the Public map path.
4. `04_v230_Reviewed_People.png` — reviewed people with shared navigation.
5. `05_v230_Reviewed_Events.png` — reviewed events with shared navigation.
6. `06_v230_Family_Branches.png` — family branches with shared navigation.
7. `07_v230_Archive_Search.png` — permission-aware archive search.
8. `08_v230_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated, but the validation script and final
approval remain manual.
