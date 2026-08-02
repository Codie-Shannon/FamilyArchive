# Screenshot Group 26 Manual Test

Screenshot Group 26 proves the v3.1.0 Original-First Manual Restoration
boundary using synthetic images and a fictional trusted contributor.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup26DemoSeeder
```

Use this local-only fictional account:

- trusted reviewer: `sg26-reviewer@example.test`
- password: `SG26Demo!2026`

Open the fictional batch at:

`/intake/batches/26000000-0000-4000-8000-000000000001?filter=pending`

## Required screenshots

1. `01_v310_Original_And_Suggestion.png` — the batch compares the retained
   original with an optional automatic suggestion.
2. `02_v310_Edit_Original.png` — the editor identifies the retained original
   as its working source and keeps the automatic version separate.
3. `03_v310_Manual_Adjustments.png` — live manual adjustments are visible in
   the original-first editor.
4. `04_v310_Manual_Without_Suggestion.png` — the fourth item remains manually
   editable without an automatic candidate.
5. `05_v310_Responsive_Original_Editor.png` — the editor remains usable at a
   narrow viewport.
6. `06_v310_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final
pack approval remain manual.
