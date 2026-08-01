# Screenshot Group 21 Manual Test

Screenshot Group 21 proves the v2.6.0 Consolidated Intake Review boundary.
The evidence uses a fictional trusted contributor and generated family-style
images. It does not read or retain real family media.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup21DemoSeeder
```

Sign in locally as `sg21-reviewer@example.test` with the fictional password
`SG21Demo!2026`, then open `/intake`.

## Required screenshots

1. `01_v260_Intake_Review_Home.png` — one consolidated workspace for retained
   batches, decisions and exceptions.
2. `02_v260_Original_And_Suggested.png` — original and suggested edit shown
   together before approval.
3. `03_v260_Exception_First_Filter.png` — attention-only review isolates the
   item that needs a human decision.
4. `04_v260_Bulk_Decision_Bar.png` — visible-item selection and bulk decisions
   remain inside the batch review flow.
5. `05_v260_Responsive_Batch_Review.png` — the same review workflow on a narrow
   viewport.
6. `06_v260_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script, final pack
approval and any real-photo rehearsal remain manual.
