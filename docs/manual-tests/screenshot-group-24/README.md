# Screenshot Group 24 Manual Test

Screenshot Group 24 proves the v2.9.0 Delegated Family Operations boundary.
The evidence uses fictional identities and synthetic family activity only.

## Prepare the fictional dataset

```powershell
php artisan migrate --force
php artisan db:seed --class=ScreenshotGroup24DemoSeeder
```

Use these local-only fictional accounts:

- archive administrator: `sg24-admin@example.test`
- approved member: `sg24-member@example.test`
- archive owner: `sg24-owner@example.test`
- password for all three: `SG24Demo!2026`

## Required screenshots

1. `01_v290_Delegated_Family_Operations.png` — the administrator sees ordinary
   operational queues and the explicit Owner-exception count.
2. `02_v290_Routine_Account_Approval.png` — viewer and contributor approvals
   stay in the delegated family-operations workspace.
3. `03_v290_Reported_Content_Review.png` — reported posts, voice exceptions and
   anonymous contact are reviewed without involving the Owner.
4. `04_v290_Recipient_Message_Consent.png` — an approved member directly
   accepts or blocks their own private-message request.
5. `05_v290_Owner_Exception_Queue.png` — the Owner command centre reports only
   elevated-role and other policy exceptions.
6. `06_v290_Immediate_Family_Conversation.png` — ordinary family conversation
   is visible immediately and another member may report it.
7. `07_v290_Responsive_Family_Operations.png` — delegated review remains usable
   at a narrow viewport.
8. `08_v290_Validation.png` — manually run the validation script and capture
   its successful final output.

Application screenshots may be automated. The validation script and final
pack approval remain manual.
