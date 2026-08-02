# Screenshot Group 29 Manual Test

Screenshot Group 29 proves the v3.4.0 Guided Family Access boundary. It keeps
Family Archive private while allowing relatives to join and recover access
without needing to manage an email account.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup29DemoSeeder
```

Use either local-only fictional operator:

- owner: `jordan.vale`
- administrator: `morgan.harbour`
- password: `SG29Demo!2026`

## Required screenshots

1. `01_v340_Flexible_Login.png` — sign-in accepts email or member name and
   offers the family access-code route.
2. `02_v340_Access_Code.png` — large, simple one-time code entry.
3. `03_v340_Guided_Setup_And_Recovery.png` — delegated setup, recovery and the
   routine approval queue.
4. `04_v340_Printable_Access_Card.png` — a newly issued fictional access card.
5. `05_v340_Approval_Waiting.png` — managed member waiting for approval.
6. `06_v340_Responsive_Access.png` — access-code experience at a narrow viewport.
7. `07_v340_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final pack
approval remain manual.
