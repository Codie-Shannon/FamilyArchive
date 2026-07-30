# Screenshot Group 05 Manual Test Plan

Status: closed — evidence approved.

Use only fictional feedback, acceptance runs, users, custodian designations,
backup records and provider status. Do not use real family participants,
custodians, infrastructure, credentials, endpoints or production evidence.

## Application Evidence

1. Open `/dashboard` as the verified fictional Owner and confirm v1.0.0,
   Family Archive v1.0 and Build Groups 45–46.
2. Open `/admin/release-acceptance` and confirm the deterministic acceptance
   matrix reports each repository-backed gate explicitly.
3. Confirm pilot approval, production proof and primary-custodian confirmation
   remain visibly unrecorded when their real human evidence is absent.
4. Confirm the acceptance surface remains usable at mobile width and a
   keyboard-focused navigation control has a visible focus indicator.
5. Confirm proposed custodianship roles do not imply confirmation or widen the
   Owner boundary.
6. Confirm the whole-system walkthrough describes the implemented v1
   capabilities without exposing private data.
7. Sign out and confirm `/admin/release-acceptance` requires authentication;
   confirm a verified non-Owner receives HTTP 403.

## Manual Terminal Evidence

After the six application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-05\Validate-ScreenshotGroup05.ps1
```

Capture the completed PowerShell window as
`07_v100_Complete_Validation.png`.

The script validated the repository but did not capture or approve evidence.
Screenshot Group 05 closed after all seven files were present, inspected and
approved. The real human acceptance gates remain separate.
