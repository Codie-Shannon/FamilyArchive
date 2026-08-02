# Screenshot Group 28 Manual Test

Screenshot Group 28 proves the v3.3.0 Album-Centred Archive boundary. Members
use Photos, Albums and Search, while reviewed events, places, people and family
branches supply album context without duplicating archive media.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup28DemoSeeder
```

Use either local-only fictional account:

- member: `sg28-member@example.test`
- trusted contributor: `sg28-trusted@example.test`
- password: `SG28Demo!2026`

## Required screenshots

1. `01_v330_Album_Library.png` — album library showing curated and generated
   album types with private derivative covers.
2. `02_v330_Curated_Album.png` — Harbour Memories as a familiar photo album.
3. `03_v330_Event_Album.png` — Harbour Family Reunion with its linked detail
   context and no duplicated media.
4. `04_v330_Create_Album.png` — trusted contributor album creation boundary.
5. `05_v330_Batch_Add_To_Album.png` — searchable visual selection of multiple
   eligible approved photos on the dedicated album membership page.
6. `06_v330_Archive_Search.png` — one query returning matching albums and
   photos under the same access rules.
7. `07_v330_Responsive_Albums.png` — album library and primary archive
   navigation at a narrow viewport.
8. `08_v330_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final
pack approval remain manual.
