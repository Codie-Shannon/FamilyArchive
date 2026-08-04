# Screenshot Group 37 Manual Test

Screenshot Group 37 proves the v4.2.0 Batch Content Safeguards boundary with
neutral synthetic evidence cards only. It demonstrates safe defaults, owner
policy controls, conservative historical-document handling and a permanent
block that no batch setting can bypass.

## Prepare the synthetic dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup37DemoSeeder
```

Use this local-only owner account:

- email: `sg37-owner@example.test`
- password: `SG37Demo!2026`

Open the synthetic review batch:

```text
/intake/batches/37000000-0000-4000-8000-000000000001?filter=all
```

The cards are labelled synthetic examples. They do not depict identification,
minors or illegal material. Do not substitute private family media into this
evidence pack.

## Required screenshots

1. `01_v420_Default_Safeguards.png` — both optional safeguards enabled by default.
2. `02_v420_Identification_Hold.png` — identification item classified and held under the batch policy.
3. `03_v420_Historical_Private_Preservation.png` — 1964 historical record with owner-only private preservation.
4. `04_v420_Permanent_Content_Block.png` — permanent block that cannot be disabled or privately preserved.
5. `05_v420_Owner_Policy_Controls.png` — owner controls and conservative 61-year boundary explanation.
6. `06_v420_Responsive_Safety_Review.png` — narrow-viewport safeguards and item review at or above 360×780.
7. `07_v420_Validation.png` — manually run validation and capture its successful output.

Application screenshots may be automated. The validation script and final pack
approval remain manual.
