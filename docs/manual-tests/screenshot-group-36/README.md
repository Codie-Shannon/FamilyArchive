# Screenshot Group 36 Manual Test

Screenshot Group 36 proves the v4.1.0 Clipping-Safe Photo Separation order
using fictional composite scans only. It demonstrates that each child photo is
padded before independent rotation or deskew and is cropped only after that
orientation step.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup36DemoSeeder
```

Use this local-only reviewer account:

- email: `sg34-reviewer@example.test`
- password: `SG34Demo!2026`

Open the synthetic batch review:

```text
/intake/batches/34000000-0000-4000-8000-000000000001?filter=attention
```

Open each four-photo item with **Review 4 detected photos**. The first item has
an independently applied 90-degree clockwise override; the second has a
270-degree override on another child. Saving the editor regenerates only that
proposal's reversible candidates.

## Required screenshots

1. `01_v410_Clipping_Safe_Order.png` — editor explanation of padded extraction, independent orientation and final crop.
2. `02_v410_Independent_Photo_Rotation.png` — four-photo editor with one child orientation override.
3. `03_v410_Rotated_Preview_Geometry.png` — rendered previews retaining complete rotated geometry.
4. `04_v410_Manual_Rotation_Override.png` — reviewer-controlled left/right orientation controls.
5. `05_v410_Immutable_Source_Lineage.png` — original-preservation and reversible-child boundary.
6. `06_v410_Responsive_Split_Review.png` — narrow-viewport split review at or above 360×780.
7. `07_v410_Validation.png` — manually run validation and capture its successful output.

Application screenshots may be automated. The validation script and final pack
approval remain manual. Do not use private family media in this evidence pack.
