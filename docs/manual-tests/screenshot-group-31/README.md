# Screenshot Group 31 Manual Test

Screenshot Group 31 proves the v3.6.0 Embedded Family Messaging boundary.
Routine one-to-one family messages are immediate, private to their participants
and available throughout the signed-in product without Owner approval.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup31DemoSeeder
```

Use these local-only fictional accounts:

- member/contributor: `sg31-mary@example.test`
- Owner: `sg31-jordan@example.test`
- administrator: `sg31-casey@example.test`
- password: `SG31Demo!2026`

## Required screenshots

1. `01_v360_Family_Chat_Home.png` — conversation list opened from the floating
   launcher on a normal member page.
2. `02_v360_New_Conversation.png` — approved family contact picker.
3. `03_v360_Family_Conversation.png` — immediate two-person conversation.
4. `04_v360_Conversation_Controls.png` — visible mute, archive and block menu.
5. `05_v360_Responsive_Family_Chat.png` — full-screen chat at 360 by 780.
6. `06_v360_Reported_Message_Exception.png` — administrator queue containing
   only an explicitly reported private message.
7. `07_v360_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final pack
approval remain manual.
