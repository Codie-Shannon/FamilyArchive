# Screenshot Group 25 Manual Test

Screenshot Group 25 proves the v3.0.0 Role-Aware Workflow Polish boundary.
The evidence uses fictional identities and synthetic operational counts only.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup25DemoSeeder
```

Use these local-only fictional accounts:

- archive owner: `sg25-owner@example.test`
- archive administrator: `sg25-admin@example.test`
- trusted contributor: `sg25-trusted@example.test`
- approved member: `sg25-member@example.test`
- password for all four: `SG25Demo!2026`

## Required screenshots

1. `01_v300_Owner_Work_Hub.png` — the Owner starts from elevated exceptions,
   policy and oversight while routine work remains delegated.
2. `02_v300_Administrator_Work_Hub.png` — the administrator sees routine
   accounts, reported activity and shared intake without Owner policy controls.
3. `03_v300_Trusted_Contributor_Work_Hub.png` — the trusted contributor sees
   only their own batches, attention count and contribution path.
4. `04_v300_Clean_Member_Navigation.png` — an ordinary member retains the
   focused Home, Archive and Messages experience without operational queues.
5. `05_v300_Responsive_Work_Hub.png` — the Work hub remains usable at a narrow
   viewport.
6. `06_v300_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final
pack approval remain manual.
