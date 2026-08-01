# Screenshot Group 22 Manual Test

Screenshot Group 22 proves the v2.7.0 Delegated Intake Completion boundary.
The evidence uses fictional accounts and generated family-style images. It does
not read or retain real family media.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup22DemoSeeder
```

Use these local-only fictional accounts:

- trusted reviewer: `sg22-reviewer@example.test`
- policy owner: `sg22-owner@example.test`
- password for both: `SG22Demo!2026`

## Required screenshots

1. `01_v270_Simplified_Batch_Start.png` — recommended processing presets keep
   detailed automation optional.
2. `02_v270_Browser_Batch_Ready.png` — the browser upload session ends with a
   direct route into batch review.
3. `03_v270_Delegated_Intake_Home.png` — the signed-in role and delegated
   routine-review boundary are explicit.
4. `04_v270_Original_And_Suggested.png` — the trusted reviewer compares the
   immutable original with the suggested edit in one workspace.
5. `05_v270_Owner_Exception_Queue.png` — the Owner Command Centre summarizes
   batches and exceptions instead of presenting every item as owner work.
6. `06_v270_Responsive_Delegated_Review.png` — the delegated review workspace
   remains usable at a narrow viewport.
7. `07_v270_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script, final pack
approval and any real-photo rehearsal remain manual.
