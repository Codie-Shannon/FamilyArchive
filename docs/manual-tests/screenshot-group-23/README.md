# Screenshot Group 23 Manual Test

Screenshot Group 23 proves the v2.8.0 Unified Archive Experience boundary.
The evidence uses fictional identities and synthetic reviewed knowledge only.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup23DemoSeeder
```

Use these local-only fictional accounts:

- approved member: `sg23-member@example.test`
- archive owner: `sg23-owner@example.test`
- password for both: `SG23Demo!2026`

## Required screenshots

1. `01_v280_Unified_Archive_Home.png` — the reviewed knowledge home presents
   accessible archive domains and the shared exploration bar.
2. `02_v280_Reviewed_People.png` — an ordinary member browses safe reviewed
   identities without curation controls.
3. `03_v280_Places_And_Map.png` — reviewed place discovery preserves reduced
   location precision and the route to the public map.
4. `04_v280_Event_Context.png` — a reviewed event connects date and safe place
   context without revealing provenance administration.
5. `05_v280_Family_Branch.png` — a member follows reviewed people through a
   permitted family branch.
6. `06_v280_Permission_Aware_Search.png` — one search spans permitted people,
   events, places and branches.
7. `07_v280_Responsive_Archive_Navigation.png` — the shared archive journey
   remains usable at a narrow viewport.
8. `08_v280_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final
pack approval remain manual.
