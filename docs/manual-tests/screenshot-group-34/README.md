# Screenshot Group 34 Manual Test

Screenshot Group 34 proves the v3.9.0 Composite Photo Separation boundary with
synthetic photos only. It covers a four-photo contact sheet with visible
gutters, a four-photo borderless scan and a deliberately undetected source for
free-form manual override.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup34DemoSeeder
```

Use this local-only Owner account:

- email: `sg34-reviewer@example.test`
- password: `SG34Demo!2026`

Open the batch review:

```text
/intake/batches/34000000-0000-4000-8000-000000000001?filter=attention
```

The first composite has saved independent previews. The second proves a
borderless four-photo suggestion. The third opens the same free-form editor
from a conservative manual two-region starting point.

## Required screenshots

1. `01_v390_Multi_Photo_Detection.png` — batch attention view and detected-photo counts.
2. `02_v390_Four_Photo_Split_Editor.png` — four detected regions over the preserved source.
3. `03_v390_Borderless_Layout_Suggestion.png` — four-region borderless suggestion.
4. `04_v390_Freeform_Manual_Override.png` — user-added or resized free-form regions.
5. `05_v390_Independent_Photo_Previews.png` — saved child candidates before publication.
6. `06_v390_Responsive_Split_Review.png` — narrow-viewport split editor.
7. `07_v390_Validation.png` — manually run validation and capture its successful output.

Application screenshots may be automated. The validation script and final pack
approval remain manual. Do not use private family media in this evidence pack.
