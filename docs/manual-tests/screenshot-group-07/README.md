# Screenshot Group 07 Manual Test Plan

Status: implemented — screenshot evidence pending.

Use only fictional import sessions, provider identifiers, filenames and media
profiles. Do not expose provider credentials, account identifiers, real cloud
libraries or private family media.

## Application Evidence

1. Open `/admin/cloud-imports` as the verified fictional Owner.
2. Confirm the page identifies v1.2.0 Media & Cloud Import and Post-v1 B.
3. Confirm Google Photos reports credentials required when no Picker
   credentials are configured.
4. Confirm Apple Photos shows the manual export pathway and does not claim that
   native access has been validated.
5. Confirm document OCR is visibly excluded.
6. Confirm fictional photo, video, audio and document selections remain in
   preflight and do not appear as accepted archive records.
7. Confirm versioned video, audio and document playback/preview profiles are
   displayed without storage paths or processing secrets.
8. Sign out and confirm the route requires authentication; confirm a verified
   non-Owner receives HTTP 403.

## Manual Terminal Evidence

After the two application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-07\Validate-ScreenshotGroup07.ps1
```

Capture the completed PowerShell window as `03_v120_Validation.png`.

The script validates the repository and both desktop application captures. It
does not capture or approve evidence.
