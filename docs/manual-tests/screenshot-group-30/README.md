# Screenshot Group 30 Manual Test

Screenshot Group 30 proves the v3.5.0 Everyday Family Experience boundary.
It keeps routine family communication and contribution screens understandable
without removing the technical and audit evidence available to authorized
archive operators.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup30DemoSeeder
```

Use either local-only fictional account:

- owner: `sg30-owner@example.test`
- member/contributor: `sg30-member@example.test`
- password: `SG30Demo!2026`

## Required screenshots

1. `01_v350_Message_Requests.png` — a plain-language, recipient-controlled
   request queue.
2. `02_v350_Private_Conversations.png` — accepted conversations without
   encryption implementation jargon.
3. `03_v350_Shared_Files.png` — family-facing attachment safety states.
4. `04_v350_Family_Activity.png` — rooms, presence and voice stories in family
   language.
5. `05_v350_Add_Photo_Batch.png` — a clear contributor batch starting point.
6. `06_v350_Responsive_Messages.png` — private inbox at a narrow viewport.
7. `07_v350_Operational_Details.png` — owner-only expandable technical evidence.
8. `08_v350_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final pack
approval remain manual.
