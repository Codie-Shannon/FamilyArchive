# Screenshot Group 08 Manual Test Plan

Status: closed — screenshot evidence approved.

Use only fictional showcase stories, public place labels and publication
receipts. Do not expose private family records, exact locations, storage
coordinates, provider references or publication credentials.

## Application Evidence

1. Open `/discover` and confirm only approved fictional public stories appear.
2. Confirm the page identifies v1.3.0 Public Discovery & Archive Maps and
   Post-v1 C.
3. Open `/discover/map` and confirm only privacy-reviewed neighbourhood, town
   or region labels appear.
4. Confirm no exact coordinate, private storage fact or unpublished story is
   visible in either public page.
5. Open `/admin/public-discovery` as the verified fictional Owner.
6. Confirm publication state, location privacy review and social-card receipt
   state are visible without raw external references or coordinates.
7. Sign out and confirm the administration route requires authentication;
   confirm a verified non-Owner receives HTTP 403.

## Manual Terminal Evidence

After the three application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-08\Validate-ScreenshotGroup08.ps1
```

Capture the completed PowerShell window as `04_v130_Validation.png`.

The script validates the repository and all three desktop application
captures. It does not capture or approve evidence. The completed validation
was captured manually, and all four evidence files passed visual, privacy,
dimension and PNG metadata inspection before explicit human approval.
