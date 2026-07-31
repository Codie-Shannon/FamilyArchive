# Screenshot Group 17 Manual Test

Screenshot Group 17 proves the v2.2.0 Owner Command Centre using only the
fictional local dataset.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup17DemoSeeder
```

Sign in locally as `sg12-owner@example.test` with the fictional password
`SG12Demo!2026`.

## Required screenshots

1. `01_v220_Owner_Command_Centre.png`
   - open **Command Centre** at desktop width;
   - show the overview, needs-attention count and consolidated Owner sidebar.
2. `02_v220_Owner_Work_Queue.png`
   - open **Work queue**;
   - show the cross-workflow action cards and fictional pending counts.
3. `03_v220_Family_And_Access.png`
   - open **Family & access**;
   - show the four grouped people, knowledge, community and public paths.
4. `04_v220_System_And_Storage.png`
   - open **System & storage**;
   - show preservation, processing and production paths.
5. `05_v220_Responsive_Owner_Navigation.png`
   - use a 390×844 mobile viewport;
   - open the sidebar and show one Owner destination: Command Centre.
6. `06_v220_Validation.png`
   - run `Validate-ScreenshotGroup17.ps1` manually and capture its successful
     final output.

Application screenshots may be automated, but the validation script and final
approval remain manual.
