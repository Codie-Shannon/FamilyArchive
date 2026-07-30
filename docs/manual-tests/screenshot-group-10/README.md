# Screenshot Group 10 Manual Test Plan

Status: implemented — screenshot evidence pending.

Use only fictional aliases, users, envelope summaries, attachments, bot
records and bridge deliveries. Do not expose plaintext private messages,
ciphertext, wrapped keys, digests, moderation fingerprints, storage paths,
checksums, provider identifiers, bridge metadata, endpoints or credentials.

## Application Evidence

1. Open `/secure-messages` as a verified fictional recipient.
2. Confirm the page identifies v1.5.0 Secure & Federated Communication and
   Post-v1 E.
3. Confirm anonymous aliases and pending, accepted and closed consent states
   appear without real identity details.
4. Confirm a pending request states that message content is unavailable before
   explicit consent.
5. Confirm encrypted-envelope summaries appear only for accepted threads and
   report the versioned protocol without exposing encrypted payload fields.
6. Confirm attachment cards show clean, pending and rejected scan states
   without private storage keys or checksums.
7. Open `/admin/secure-communication` as the verified fictional Owner.
8. Confirm the guidance bot is disabled, private archive access is prohibited
   and no prompt or response content is displayed.
9. Confirm encryption reports runtime setup required.
10. Confirm WhatsApp and Messenger are described only through their official
    business platforms and require credentials.
11. Confirm the page explicitly excludes arbitrary personal-chat access.
12. Sign out and confirm both routes require authentication; confirm a verified
    non-Owner receives HTTP 403 from the operations route.

## Manual Terminal Evidence

After the three application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-10\Validate-ScreenshotGroup10.ps1
```

Capture the completed PowerShell window as `04_v150_Validation.png`.

The script validates the repository and all three application captures. It
does not capture or approve evidence. Closure still requires visual, privacy,
dimension and PNG metadata inspection plus explicit human approval.
