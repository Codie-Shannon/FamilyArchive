# Screenshot Group 06 Manual Test Plan

Status: implemented — screenshot evidence pending.

Use only fictional media, fingerprints, source records and metadata values. Do
not expose real family media, internal hashes, storage paths or private source
coordinates.

## Application Evidence

1. Open `/admin/media-intelligence` as the verified fictional Owner.
2. Confirm the header shows v1.1.0 Advanced Media Intelligence and Post-v1 A.
3. Confirm similarity candidates show method, distance, confidence and pending
   review state without rendering fingerprints or internal version IDs.
4. Confirm alternate original records remain separate and identify preferred
   versus alternate state without exposing paths.
5. Confirm metadata additions and conflicts are visually separated and the
   proposal remains pending human review.
6. Sign out and confirm the route requires authentication; confirm a verified
   non-Owner receives HTTP 403.

## Manual Terminal Evidence

After the two application screenshots have been inspected, run:

```powershell
.\docs\manual-tests\screenshot-group-06\Validate-ScreenshotGroup06.ps1
```

Capture the completed PowerShell window as `03_v110_Validation.png`.

The script validates the repository. It does not capture or approve evidence.
